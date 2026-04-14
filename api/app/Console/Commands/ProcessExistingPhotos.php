<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Services\ImageProcessingService;
use Illuminate\Console\Command;

class ProcessExistingPhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:process-existing
                            {--batch=25 : Number of photos to process per batch}
                            {--gallery= : Process only photos from a specific gallery ID}
                            {--force : Reprocess already processed photos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process existing photos to generate secure preview and thumbnail versions with watermarks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $galleryId = $this->option('gallery');
        $force = $this->option('force');

        $this->info('Starting photo processing...');
        $this->newLine();

        $imageProcessingService = new ImageProcessingService;

        // Build query
        $query = Photo::query()
            ->whereRaw('is_video = false');

        if (! $force) {
            $query->whereRaw('is_processed = false');
        }

        if ($galleryId) {
            $query->where('gallery_id', $galleryId);
            $this->info("Processing photos from gallery: {$galleryId}");
        }

        $totalPhotos = $query->count();

        if ($totalPhotos === 0) {
            $this->info('No photos to process.');

            return Command::SUCCESS;
        }

        $this->info("Found {$totalPhotos} photo(s) to process.");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalPhotos);
        $progressBar->start();

        $processed = 0;
        $failed = 0;
        $errors = [];

        // Process in batches
        $query->chunk($batchSize, function ($photos) use ($imageProcessingService, $progressBar, &$processed, &$failed, &$errors) {
            foreach ($photos as $photo) {
                try {
                    $originalPath = $photo->resolved_storage_path;
                    $galleryId = $photo->gallery_id;

                    // Process the photo
                    $result = $imageProcessingService->processExistingPhoto($originalPath, $galleryId);

                    if ($result) {
                        // Update photo record
                        $photo->update([
                            'file_path' => $result['hd_path'],
                            'file_path_hd' => $result['hd_path'],
                            'file_path_preview' => $result['preview_path'],
                            'file_path_thumbnail' => $result['thumbnail_path'],
                            'is_processed' => true,
                            'metadata' => array_merge($photo->metadata ?? [], [
                                'storage_path' => $result['hd_path'],
                                'processed_at' => now()->toIso8601String(),
                            ]),
                        ]);

                        $processed++;
                    } else {
                        $failed++;
                        $errors[] = "Photo {$photo->id}: Processing returned null";
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "Photo {$photo->id}: {$e->getMessage()}";
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Processing complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Photos', $totalPhotos],
                ['Successfully Processed', $processed],
                ['Failed', $failed],
            ]
        );

        // Show errors if any
        if (count($errors) > 0) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach (array_slice($errors, 0, 10) as $error) {
                $this->line("  - {$error}");
            }
            if (count($errors) > 10) {
                $this->line('  ... and '.(count($errors) - 10).' more errors');
            }
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
