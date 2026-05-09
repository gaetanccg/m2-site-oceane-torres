<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendSchoolSessionEmailsRequest;
use App\Http\Requests\Admin\StoreSchoolSessionRequest;
use App\Jobs\GenerateSchoolSessionExportJob;
use App\Jobs\ProcessSchoolSessionJob;
use App\Mail\GalleryAccessMail;
use App\Models\Gallery;
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
            'filename' => ['required', 'string'],
        ]);

        $chunk = $request->file('chunk');
        $chunkIndex = (int) $request->input('chunk_index');
        $totalChunks = (int) $request->input('total_chunks');

        $zipDir = 'temp/school-sessions/'.$schoolSession->id;
        $zipPath = $zipDir.'/upload.zip';

        Storage::disk('local')->makeDirectory($zipDir);

        $fullZipPath = Storage::disk('local')->path($zipPath);
        $chunkContent = file_get_contents($chunk->getRealPath());
        file_put_contents($fullZipPath, $chunkContent, FILE_APPEND | LOCK_EX);
        unset($chunkContent);

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
     * POST /admin/school-sessions/{schoolSession}/send-emails
     */
    public function sendEmails(SendSchoolSessionEmailsRequest $request, SchoolSession $schoolSession): JsonResponse
    {
        $validated = $request->validated();
        $frontendUrl = config('app.frontend_url', 'https://oceanetorresphotographie.fr');

        $sent = 0;
        $errors = [];

        foreach ($validated['contacts'] as $contact) {
            $gallery = Gallery::where('id', $contact['gallery_id'])
                ->where('school_session_id', $schoolSession->id)
                ->first();

            if (! $gallery) {
                $errors[] = "Galerie introuvable pour {$contact['recipient_name']}";

                continue;
            }

            try {
                Mail::to($contact['email'])->queue(
                    new GalleryAccessMail(
                        gallery: $gallery,
                        recipientName: $contact['recipient_name'],
                        galleryUrl: $frontendUrl.'/gallery/'.$gallery->share_code,
                        shareCode: $gallery->share_code,
                        isDirectLink: true,
                    )
                );
                $sent++;
            } catch (\Exception $e) {
                \Log::error('SchoolSession: failed to queue email', [
                    'session_id' => $schoolSession->id,
                    'gallery_id' => $gallery->id,
                    'email' => $contact['email'],
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "Erreur pour {$contact['recipient_name']}: {$e->getMessage()}";
            }
        }

        return response()->json([
            'success' => true,
            'sent' => $sent,
            'errors' => $errors,
            'message' => $sent > 0
                ? "{$sent} email(s) mis en file d'envoi."
                : 'Aucun email envoyé.',
        ]);
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
