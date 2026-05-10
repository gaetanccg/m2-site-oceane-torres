<?php

namespace App\Jobs;

use App\Models\SchoolSession;
use App\Services\SchoolSessionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessSchoolSessionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public int $maxExceptions = 1;

    public function __construct(
        public string $sessionId,
    ) {}

    public function handle(SchoolSessionService $service): void
    {
        $session = SchoolSession::find($this->sessionId);
        if (! $session || $session->status === 'failed') {
            return;
        }

        try {
            // --- Phase 1: Extract ZIP ---
            $session->markAs('extracting');
            Log::info('SchoolSession: extracting ZIP', ['session_id' => $session->id]);

            $structure = $service->extractAndParseZip($session);

            // --- Phase 2: Create galleries ---
            $session->markAs('creating_galleries');

            $batchId = (string) Str::uuid();
            $totalGalleries = 0;
            $totalPhotos = 0;

            foreach ($structure['classes'] as $className => $classData) {
                // Process shared photos for this class (once)
                $sharedAttrs = [];
                if (! empty($classData['shared_photos'])) {
                    Log::info('SchoolSession: processing shared photos for class', [
                        'session_id' => $session->id,
                        'class' => $className,
                        'count' => count($classData['shared_photos']),
                    ]);

                    $sharedAttrs = $service->processSharedPhotos($session, $classData['shared_photos']);
                }

                // Create one gallery per child
                foreach ($classData['children'] as $childName => $portraitPaths) {
                    $service->createChildGallery(
                        $session,
                        $childName,
                        $batchId,
                        $sharedAttrs,
                        $portraitPaths,
                        $className,
                    );

                    $totalGalleries++;
                    $totalPhotos += count($portraitPaths) + count($sharedAttrs);
                }
            }

            // --- Phase 3: Move to processing_photos ---
            $session->update([
                'status' => 'processing_photos',
                'total_galleries' => $totalGalleries,
                'total_photos' => $totalPhotos,
                'batch_id' => $batchId,
            ]);

            Log::info('SchoolSession: galleries created, processing photos', [
                'session_id' => $session->id,
                'galleries' => $totalGalleries,
                'photos' => $totalPhotos,
                'batch_id' => $batchId,
            ]);

            // --- Phase 4: Cleanup extracted files ---
            $service->cleanupExtractedFiles($session);

        } catch (\Throwable $e) {
            Log::error('ProcessSchoolSessionJob failed', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $session->markAs('failed', 'Le traitement a échoué. Veuillez consulter les logs serveur pour plus de détails.');
            $service->cleanupExtractedFiles($session);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessSchoolSessionJob: job failed permanently', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);

        try {
            $session = SchoolSession::find($this->sessionId);
            if ($session && $session->status !== 'failed') {
                $session->markAs('failed', 'Le traitement a échoué. Veuillez consulter les logs serveur pour plus de détails.');
            }
        } catch (\Exception $e) {
            Log::error('ProcessSchoolSessionJob: failed to update session status', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
