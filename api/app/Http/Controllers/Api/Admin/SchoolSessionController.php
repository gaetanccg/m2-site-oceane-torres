<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendSchoolSessionMessagesRequest;
use App\Http\Requests\Admin\StoreSchoolSessionRequest;
use App\Http\Requests\Admin\UpdateSchoolSessionRequest;
use App\Jobs\DispatchSmsBatchJob;
use App\Jobs\GenerateSchoolSessionExportJob;
use App\Jobs\ProcessPhotoJob;
use App\Jobs\ProcessSchoolSessionJob;
use App\Mail\GalleryAccessMail;
use App\Models\Gallery;
use App\Models\Order;
use App\Models\PhotoUpload;
use App\Models\SchoolSession;
use App\Models\SchoolSessionExport;
use App\Services\SchoolSessionExportService;
use App\Services\SchoolSessionService;
use App\Services\SmsTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolSessionController extends Controller
{
    public function index(): JsonResponse
    {
        $sessions = SchoolSession::withCount('galleries')
            ->latest()
            ->paginate(20);

        return response()->json($sessions);
    }

    public function store(StoreSchoolSessionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $session = SchoolSession::create([
            'title' => $validated['title'],
            'event_date' => $validated['event_date'] ?? null,
            'status' => 'uploading',
            'product_types_config' => $validated['product_types'] ?? null,
            'gallery_message' => $validated['gallery_message'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $session,
        ], 201);
    }

    public function show(SchoolSession $schoolSession): JsonResponse
    {
        $data = $schoolSession->toArray();
        $data['galleries_count'] = $schoolSession->galleries()->count();

        if ($schoolSession->batch_id) {
            $batchStatus = PhotoUpload::getBatchStatus($schoolSession->batch_id, includeUploads: false);
            $data['batch_progress'] = $batchStatus;

            if (
                $schoolSession->status === 'processing_photos'
                && $batchStatus['found']
                && $batchStatus['is_complete']
            ) {
                $schoolSession->update([
                    'status' => 'completed',
                    'processed_photos' => $batchStatus['completed'],
                ]);
                $data['status'] = 'completed';
                $data['processed_photos'] = $batchStatus['completed'];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /** Orders paid contenant au moins un item issu d'une galerie de cette session. */
    public function orders(SchoolSession $schoolSession): JsonResponse
    {
        $galleryIds = $schoolSession->galleries()->pluck('id');

        $orders = Order::with(['items.photo.gallery', 'user'])
            ->paid()
            ->whereHas('items.photo', fn ($q) => $q->whereIn('gallery_id', $galleryIds))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders->map(fn ($order) => OrderController::formatOrder($order)),
        ]);
    }

    public function galleries(SchoolSession $schoolSession): JsonResponse
    {
        $galleries = $schoolSession->galleries()
            ->withCount('photos')
            ->orderBy('class_name')
            ->orderBy('title')
            ->get()
            ->map(fn ($gallery) => [
                'id' => $gallery->id,
                'title' => $gallery->title,
                'class_name' => $gallery->class_name,
                'photos_count' => $gallery->photos_count,
                'share_code' => $gallery->share_code,
                'access_token' => $gallery->access_token,
                'created_at' => $gallery->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => $galleries,
        ]);
    }

    public function upload(Request $request, SchoolSession $schoolSession): JsonResponse
    {
        if ($schoolSession->status !== 'uploading') {
            return response()->json([
                'success' => false,
                'message' => 'Cette session n\'accepte plus d\'upload.',
            ], 422);
        }

        $request->validate([
            'chunk' => ['required', 'file', 'max:61440'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'offset' => ['required', 'integer', 'min:0'],
            'filename' => ['required', 'string'],
        ]);

        $chunk = $request->file('chunk');
        $chunkIndex = (int) $request->input('chunk_index');
        $totalChunks = (int) $request->input('total_chunks');
        $offset = (int) $request->input('offset');

        $zipDir = 'temp/school-sessions/'.$schoolSession->id;
        $zipPath = $zipDir.'/upload.zip';

        Storage::disk('local')->makeDirectory($zipDir);

        $fullZipPath = Storage::disk('local')->path($zipPath);

        // Idempotent : seek+write à offset → un retry réécrit au même endroit au lieu
        // d'append (qui corromprait le ZIP).
        $handle = fopen($fullZipPath, 'c+b');
        if ($handle === false) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'ouvrir le fichier de destination.',
            ], 500);
        }

        try {
            flock($handle, LOCK_EX);
            fseek($handle, $offset);
            fwrite($handle, file_get_contents($chunk->getRealPath()));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        $isLast = ($chunkIndex === $totalChunks - 1);

        if ($isLast) {
            $schoolSession->update(['zip_path' => $zipPath]);
        }

        return response()->json([
            'success' => true,
            'chunk_index' => $chunkIndex,
            'received' => $chunkIndex + 1,
            'total_chunks' => $totalChunks,
            'upload_complete' => $isLast,
        ]);
    }

    public function process(SchoolSession $schoolSession): JsonResponse
    {
        if ($schoolSession->status !== 'uploading') {
            return response()->json([
                'success' => false,
                'message' => 'Cette session ne peut pas être traitée dans son état actuel.',
            ], 422);
        }

        if (! $schoolSession->zip_path || ! Storage::disk('local')->exists($schoolSession->zip_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun fichier ZIP trouvé. Veuillez d\'abord uploader le ZIP.',
            ], 422);
        }

        ProcessSchoolSessionJob::dispatch($schoolSession->id);

        return response()->json([
            'success' => true,
            'data' => $schoolSession->fresh(),
        ]);
    }

    /** Re-dispatch ProcessPhotoJob pour chaque PhotoUpload failed dont le temp file existe. */
    public function retryFailedPhotos(SchoolSession $schoolSession): JsonResponse
    {
        if (! $schoolSession->batch_id) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun lot de photos à relancer.',
            ], 422);
        }

        $failedUploads = PhotoUpload::where('batch_id', $schoolSession->batch_id)
            ->where('status', 'failed')
            ->get();

        if ($failedUploads->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Aucune photo en échec à relancer.',
                'redispatched' => 0,
                'skipped' => 0,
            ]);
        }

        $redispatched = 0;
        $skipped = 0;
        $disk = Storage::disk('local');

        foreach ($failedUploads as $upload) {
            $extension = strtolower(pathinfo($upload->original_filename, PATHINFO_EXTENSION)) ?: 'jpg';
            $newTempPath = 'temp_uploads/'.$upload->id.'.'.$extension;
            $legacyTempPath = 'temp_uploads/'.$upload->id.'_'.$upload->original_filename;

            $tempPath = null;
            if ($disk->exists($newTempPath)) {
                $tempPath = $newTempPath;
            } elseif ($disk->exists($legacyTempPath)) {
                // Migration legacy (filenames accentués) → ASCII-only.
                @rename($disk->path($legacyTempPath), $disk->path($newTempPath));
                $tempPath = $disk->exists($newTempPath) ? $newTempPath : $legacyTempPath;
            }

            if ($tempPath === null) {
                $skipped++;

                continue;
            }

            $upload->update([
                'status' => 'pending',
                'error_message' => null,
                'completed_at' => null,
            ]);

            $fullPath = $disk->path($tempPath);
            $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';

            ProcessPhotoJob::dispatch(
                $upload->id,
                $upload->gallery_id,
                $tempPath,
                $upload->original_filename,
                $mimeType,
            );

            $redispatched++;
        }

        if ($redispatched > 0 && $schoolSession->status === 'completed') {
            $schoolSession->update(['status' => 'processing_photos']);
        }

        return response()->json([
            'success' => true,
            'message' => "{$redispatched} photo(s) relancée(s)".($skipped > 0 ? ", {$skipped} ignorée(s) (fichier temp absent)" : ''),
            'redispatched' => $redispatched,
            'skipped' => $skipped,
        ]);
    }

    public function sendMessages(SendSchoolSessionMessagesRequest $request, SchoolSession $schoolSession, SmsTemplateService $smsTemplate): JsonResponse
    {
        $validated = $request->validated();
        $channel = $validated['channel'];
        $frontendUrl = config('app.frontend_url', 'https://oceanetorresphotographie.fr');

        // Pré-charge en une query (évite N+1 sur 100+ contacts).
        $galleryIds = collect($validated['contacts'])->pluck('gallery_id')->unique();
        $galleries = Gallery::whereIn('id', $galleryIds)
            ->where('school_session_id', $schoolSession->id)
            ->get()
            ->keyBy('id');

        $sent = 0;
        $errors = [];
        $smsBatch = [];

        foreach ($validated['contacts'] as $contact) {
            $gallery = $galleries->get($contact['gallery_id']);

            if (! $gallery) {
                $errors[] = "Galerie introuvable pour {$contact['recipient_name']}";

                continue;
            }

            $directUrl = $frontendUrl.'/gallery/'.$gallery->share_code;

            try {
                if ($channel === 'email') {
                    Mail::to($contact['email'])->queue(
                        new GalleryAccessMail(
                            gallery: $gallery,
                            recipientName: $contact['recipient_name'],
                            galleryUrl: $directUrl,
                            shareCode: $gallery->share_code,
                            isDirectLink: true,
                        )
                    );
                } else {
                    // SMS : accumulés puis dispatch en un seul batch job (throttle Brevo côté orchestrateur).
                    $content = $smsTemplate->build(
                        $schoolSession->sms_template,
                        $contact['recipient_name'],
                        $directUrl,
                        $gallery->share_code,
                    );
                    $smsBatch[] = ['phone' => $contact['phone'], 'content' => $content];
                }

                $sent++;
            } catch (\Exception $e) {
                \Log::error('SchoolSession: failed to queue message', [
                    'session_id' => $schoolSession->id,
                    'gallery_id' => $gallery->id,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "Erreur pour {$contact['recipient_name']}.";
            }
        }

        if (! empty($smsBatch)) {
            DispatchSmsBatchJob::dispatch($smsBatch);
        }

        $label = $channel === 'sms' ? 'SMS' : 'email(s)';

        return response()->json([
            'success' => true,
            'sent' => $sent,
            'errors' => $errors,
            'message' => $sent > 0
                ? "{$sent} {$label} mis en file d'envoi."
                : "Aucun {$label} envoyé.",
        ]);
    }

    public function update(UpdateSchoolSessionRequest $request, SchoolSession $schoolSession): JsonResponse
    {
        $schoolSession->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $schoolSession->fresh(),
            'message' => 'Session mise à jour.',
        ]);
    }

    /** Clôture la session : les parents ne peuvent plus add-to-cart sur ses galeries. */
    public function close(SchoolSession $schoolSession): JsonResponse
    {
        if ($schoolSession->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette session est deja cloturee.',
            ], 422);
        }

        $schoolSession->update(['closed_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => $schoolSession->fresh(),
            'message' => 'Session cloturee. Les parents ne peuvent plus commander.',
        ]);
    }

    public function reopen(SchoolSession $schoolSession): JsonResponse
    {
        if (! $schoolSession->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette session n\'est pas cloturee.',
            ], 422);
        }

        $schoolSession->update(['closed_at' => null]);

        return response()->json([
            'success' => true,
            'data' => $schoolSession->fresh(),
            'message' => 'Session rouverte.',
        ]);
    }

    public function createExport(Request $request, SchoolSession $schoolSession): JsonResponse
    {
        $validated = $request->validate([
            'include_digital' => ['nullable', 'boolean'],
        ]);

        // On garde les exports `completed` (re-download possible), on nettoie le reste.
        SchoolSessionExport::where('school_session_id', $schoolSession->id)
            ->whereIn('status', ['pending', 'processing', 'failed'])
            ->delete();

        $export = SchoolSessionExport::create([
            'school_session_id' => $schoolSession->id,
            'status' => 'pending',
            'include_digital' => (bool) ($validated['include_digital'] ?? false),
        ]);

        GenerateSchoolSessionExportJob::dispatch($export->id);

        return response()->json([
            'success' => true,
            'data' => $export,
        ], 201);
    }

    public function latestExport(SchoolSession $schoolSession): JsonResponse
    {
        $export = SchoolSessionExport::where('school_session_id', $schoolSession->id)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => $export,
        ]);
    }

    public function downloadExport(SchoolSessionExport $export): BinaryFileResponse|JsonResponse
    {
        if ($export->status !== 'completed' || ! $export->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Export non disponible.',
            ], 404);
        }

        if (! Storage::disk('local')->exists($export->file_path)) {
            // Self-healing : ZIP référencé en DB mais disparu du disque (rebuild container,
            // volume reset, cleanup manuel) → on bascule l'export en `failed` pour que le
            // frontend propose « Réessayer » via le polling de latestExport.
            $export->markAs('failed', "Le fichier ZIP n'est plus disponible sur le serveur. Cliquez sur « Réessayer » pour le régénérer.");

            return response()->json([
                'success' => false,
                'message' => "Le fichier ZIP n'est plus disponible. L'export a été marqué comme à régénérer.",
                'requires_regenerate' => true,
            ], 410);
        }

        $fullPath = Storage::disk('local')->path($export->file_path);
        $downloadName = basename($fullPath);
        // Retire le préfixe "{export_id}_" pour donner un nom propre côté user.
        $cleanName = preg_replace('/^[a-f0-9-]+_/', '', $downloadName);

        return response()->download($fullPath, $cleanName);
    }

    public function destroy(
        SchoolSession $schoolSession,
        SchoolSessionService $service,
        SchoolSessionExportService $exportService,
    ): JsonResponse {
        foreach ($schoolSession->exports as $export) {
            $exportService->deleteExportFile($export);
        }

        // Clean MinIO AVANT le cascade delete : on a besoin des gallery UUIDs.
        $service->deleteSessionFiles($schoolSession);

        $service->cleanupExtractedFiles($schoolSession);

        $schoolSession->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session scolaire et toutes ses galeries supprimées.',
        ]);
    }
}
