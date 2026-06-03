<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPhotoJob;
use App\Models\PhotoUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Re-dispatch ProcessPhotoJob for failed PhotoUploads whose temp file is still on disk.
 *
 * Use after a worker code change forced fails (the worker held stale class code in memory)
 * or any other transient failure where the source file is still recoverable.
 */
class RetryFailedPhotoUploads extends Command
{
    protected $signature = 'photos:retry-failed
                            {--gallery= : Limit retry to a specific gallery_id}
                            {--dry-run : List what would be retried without dispatching}';

    protected $description = 'Re-dispatch ProcessPhotoJob for failed PhotoUploads whose temp file still exists';

    public function handle(): int
    {
        $query = PhotoUpload::where('status', 'failed');
        if ($galleryId = $this->option('gallery')) {
            $query->where('gallery_id', $galleryId);
        }

        $failed = $query->get(['id', 'gallery_id', 'original_filename']);

        if ($failed->isEmpty()) {
            $this->info('Aucun PhotoUpload en échec.');

            return self::SUCCESS;
        }

        $disk = Storage::disk('local');
        $redispatched = 0;
        $missing = 0;
        $dryRun = $this->option('dry-run');

        foreach ($failed as $upload) {
            $tempPath = $this->findTempPath($disk, $upload->id);
            if ($tempPath === null) {
                $missing++;

                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] would retry {$upload->id} → {$tempPath}");
                $redispatched++;

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

        $this->info("Relancés: {$redispatched}");
        if ($missing > 0) {
            $this->warn("Fichier temporaire absent (non récupérables): {$missing}");
            $this->line('→ La photographe doit ré-uploader ces fichiers.');
        }

        return self::SUCCESS;
    }

    private function findTempPath($disk, string $uploadId): ?string
    {
        // New format used by storeAsync after the storeAs refactor.
        foreach (['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'] as $ext) {
            $path = 'temp_uploads/'.$uploadId.'.'.$ext;
            if ($disk->exists($path)) {
                return $path;
            }
        }

        // Legacy format: {uuid}_{original_filename}
        $files = $disk->files('temp_uploads');
        foreach ($files as $f) {
            if (str_starts_with(basename($f), $uploadId.'_')) {
                return $f;
            }
        }

        return null;
    }
}
