<?php

namespace App\Console\Commands;

use App\Helpers\MimeTypes;
use App\Jobs\GenerateCleanThumbnailJob;
use App\Models\Photo;
use App\Services\ImageProcessingService;
use Illuminate\Console\Command;

class GenerateCleanThumbnails extends Command
{
    protected $signature = 'photos:generate-clean-thumbnails
        {--sync : Generate inline instead of dispatching queued jobs}';

    protected $description = 'Backfill clean (no-watermark) thumbnails on MinIO for downloadable photos that are missing one.';

    public function handle(ImageProcessingService $imageProcessingService): int
    {
        $query = Photo::query()
            ->downloadable()
            ->whereRaw('is_video = false')
            ->whereNull('file_path_thumbnail_clean');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No downloadable photos need a clean thumbnail.');

            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');
        $this->info($sync
            ? "Generating {$total} clean thumbnail(s) inline…"
            : "Dispatching {$total} clean thumbnail job(s) to the queue…");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $failed = 0;

        $query->orderBy('id')->chunkById(100, function ($photos) use ($imageProcessingService, $sync, $bar, &$failed) {
            foreach ($photos as $photo) {
                if ($sync) {
                    try {
                        $targetPath = $photo->cleanThumbnailStoragePath();
                        $mimeType = MimeTypes::fromExtension(pathinfo($targetPath, PATHINFO_EXTENSION) ?: 'jpg');

                        $ok = $imageProcessingService->generateAndStoreCleanThumbnail(
                            $photo->resolved_storage_path,
                            $targetPath,
                            $mimeType,
                        );

                        if ($ok) {
                            $photo->update(['file_path_thumbnail_clean' => $targetPath]);
                        } else {
                            $failed++;
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->newLine();
                        $this->warn("Photo {$photo->id}: {$e->getMessage()}");
                    }
                } else {
                    GenerateCleanThumbnailJob::dispatch($photo->id);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($sync) {
            $done = $total - $failed;
            $this->info("Done: {$done} generated, {$failed} failed.");
        } else {
            $this->info("Dispatched {$total} job(s). Ensure a queue worker is running.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
