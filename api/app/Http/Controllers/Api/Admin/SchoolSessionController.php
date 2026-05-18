<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendSchoolSessionMessagesRequest;
use App\Http\Requests\Admin\StoreSchoolSessionRequest;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolSessionController extends Controller
{
    /**
     * GET /admin/school-sessions
     */
    public function index(): JsonResponse
    {
        $sessions = SchoolSession::withCount('galleries')
            ->latest()
            ->paginate(20);

        return response()->json($sessions);
    }

    /**
     * POST /admin/school-sessions
     */
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

    /**
     * GET /admin/school-sessions/{schoolSession}
     */
    public function show(SchoolSession $schoolSession): JsonResponse
    {
        $data = $schoolSession->toArray();
        $data['galleries_count'] = $schoolSession->galleries()->count();

        // Live progress from PhotoUpload batch (counts only, no upload list — scaling)
        if ($schoolSession->batch_id) {
            $batchStatus = PhotoUpload::getBatchStatus($schoolSession->batch_id, includeUploads: false);
            $data['batch_progress'] = $batchStatus;

            // Auto-transition: processing_photos -> completed
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

    /**
     * GET /admin/school-sessions/{schoolSession}/orders
     * Lists orders that contain at least one item from a gallery in this session.
     */
    public function orders(SchoolSession $schoolSession): JsonResponse
    {
        $galleryIds = $schoolSession->galleries()->pluck('id');

        $orders = Order::with(['items.photo.gallery', 'user'])
            ->whereHas('items.photo', fn ($q) => $q->whereIn('gallery_id', $galleryIds))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders->map(fn ($order) => OrderController::formatOrder($order)),
        ]);
    }

    /**
     * GET /admin/school-sessions/{schoolSession}/galleries
     */
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

    /**
     * PUT /admin/school-sessions/{schoolSession}/upload
     */
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

        // Idempotent write: seek to offset so a retried chunk overwrites itself
        // instead of being appended a second time (which would corrupt the ZIP).
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

    /**
     * POST /admin/school-sessions/{schoolSession}/process
     */
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

    /**
     * POST /admin/school-sessions/{schoolSession}/retry-failed-photos
     *
     * Re-dispatches ProcessPhotoJob for every PhotoUpload of this session's batch
     * still in `failed` status, if the temp file is still on disk.
     */
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
            // Preferred (new) ASCII-only path
            $extension = strtolower(pathinfo($upload->original_filename, PATHINFO_EXTENSION)) ?: 'jpg';
            $newTempPath = 'temp_uploads/'.$upload->id.'.'.$extension;
            $legacyTempPath = 'temp_uploads/'.$upload->id.'_'.$upload->original_filename;

            $tempPath = null;
            if ($disk->exists($newTempPath)) {
                $tempPath = $newTempPath;
            } elseif ($disk->exists($legacyTempPath)) {
                // Migrate legacy path (with accented chars) → ASCII-only path
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

    /**
     * POST /admin/school-sessions/{schoolSession}/send-messages
     * Sends gallery access via email or SMS based on the chosen channel.
     */
    public function sendMessages(SendSchoolSessionMessagesRequest $request, SchoolSession $schoolSession): JsonResponse
    {
        $validated = $request->validated();
        $channel = $validated['channel'];
        $frontendUrl = config('app.frontend_url', 'https://oceanetorresphotographie.fr');

        // Pré-charge toutes les galeries en une seule query (évite N+1 sur 100+ contacts)
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
                    // SMS — keep content under ~160 chars, no accents (avoids unicode SMS billing)
                    $content = sprintf(
                        'Bonjour, les photos de classe de %s sont disponibles ici : https://oceanetorresphotographie.fr/gallery/%s  (code: %s). Oceane Torres',
                        $this->stripAccents($gallery->title),
                        $gallery->share_code,
                        $gallery->share_code,
                    );
                    // Accumulate then dispatch as a single batch job — keeps the HTTP
                    // response fast and lets the orchestrator throttle Brevo calls.
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
                : "Aucun {$label} envoye.",
        ]);
    }

    private function stripAccents(string $text): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    }

    /**
     * POST /admin/school-sessions/{schoolSession}/close
     * Closes the session: parents can no longer add to cart on its galleries.
     */
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

    /**
     * POST /admin/school-sessions/{schoolSession}/reopen
     */
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

    /**
     * POST /admin/school-sessions/{schoolSession}/exports
     * Creates a new export and dispatches the generation job.
     */
    public function createExport(Request $request, SchoolSession $schoolSession): JsonResponse
    {
        $validated = $request->validate([
            'include_digital' => ['nullable', 'boolean'],
        ]);

        // Cleanup any previous in-progress or failed export for this session
        // (we keep completed ones to allow re-download)
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

    /**
     * GET /admin/school-sessions/{schoolSession}/exports/latest
     */
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

    /**
     * GET /admin/school-session-exports/{export}/download
     */
    public function downloadExport(SchoolSessionExport $export): BinaryFileResponse|JsonResponse
    {
        if ($export->status !== 'completed' || ! $export->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Export non disponible.',
            ], 404);
        }

        if (! Storage::disk('local')->exists($export->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier introuvable sur le serveur.',
            ], 404);
        }

        $fullPath = Storage::disk('local')->path($export->file_path);
        $downloadName = basename($fullPath);
        // Strip the leading "{export_id}_" so the user sees a clean name
        $cleanName = preg_replace('/^[a-f0-9-]+_/', '', $downloadName);

        return response()->download($fullPath, $cleanName);
    }

    /**
     * DELETE /admin/school-sessions/{schoolSession}
     */
    public function destroy(
        SchoolSession $schoolSession,
        SchoolSessionService $service,
        SchoolSessionExportService $exportService,
    ): JsonResponse {
        // Delete export ZIP files from local disk
        foreach ($schoolSession->exports as $export) {
            $exportService->deleteExportFile($export);
        }

        // Clean MinIO files BEFORE cascade delete (need gallery UUIDs)
        $service->deleteSessionFiles($schoolSession);

        // Clean local temp files
        $service->cleanupExtractedFiles($schoolSession);

        // Cascade: school_session -> galleries -> photos, photo_uploads, gallery_product_types, exports
        $schoolSession->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session scolaire et toutes ses galeries supprimées.',
        ]);
    }
}
