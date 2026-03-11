<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOrphanFiles extends Command
{
    protected $signature = 'storage:clean-orphans
                            {--delete : Actually delete orphan files (dry-run by default)}
                            {--gallery= : Check only a specific gallery ID}';

    protected $description = 'Find and optionally delete orphan files and gallery folders on MinIO that are not referenced in the database';

    public function handle(): int
    {
        $disk = Storage::disk('minio');
        $delete = $this->option('delete');
        $galleryFilter = $this->option('gallery');

        $this->info($delete ? 'Mode: DELETE orphans' : 'Mode: DRY RUN (use --delete to actually remove files)');
        $this->newLine();

        // 1. Collect all referenced paths from the database
        $this->info('Collecting referenced paths from database...');

        $referencedPaths = collect();

        Photo::query()
            ->select(['id', 'file_path', 'file_path_hd', 'file_path_preview', 'file_path_thumbnail', 'file_path_web', 'file_path_watermark'])
            ->chunkById(500, function ($photos) use (&$referencedPaths) {
                foreach ($photos as $photo) {
                    $referencedPaths->push($photo->file_path);
                    $referencedPaths->push($photo->file_path_hd);
                    $referencedPaths->push($photo->file_path_preview);
                    $referencedPaths->push($photo->file_path_thumbnail);
                    $referencedPaths->push($photo->file_path_web);
                    $referencedPaths->push($photo->file_path_watermark);
                }
            });

        $referencedPaths = $referencedPaths->filter()->unique()->flip();
        $this->info("Found {$referencedPaths->count()} unique referenced paths in DB.");
        $this->newLine();

        // 2. Collect existing gallery IDs from the database
        $existingGalleryIds = Gallery::pluck('id')->flip();
        $this->info("Found {$existingGalleryIds->count()} galleries in DB.");
        $this->newLine();

        // 3. List all files on MinIO
        $this->info('Listing files on MinIO...');

        if ($galleryFilter) {
            $files = $disk->allFiles($galleryFilter);
        } else {
            $files = $disk->allFiles();
        }

        $this->info('Found ' . count($files) . ' files on MinIO.');
        $this->newLine();

        // 4. Categorize orphans: deleted galleries vs orphan files in existing galleries
        $orphanGalleries = [];    // galleryId => file count
        $orphanFiles = [];        // individual orphan files in existing galleries
        $orphanGalleryFiles = []; // all files in orphan galleries
        $totalOrphanSize = 0;

        // Non-gallery folders to ignore (invoices, etc.)
        $ignoredPrefixes = ['invoices'];

        foreach ($files as $file) {
            // Extract gallery ID (first path segment)
            $galleryId = explode('/', $file)[0] ?? null;

            // Skip non-gallery folders
            if ($galleryId && in_array($galleryId, $ignoredPrefixes)) {
                continue;
            }

            if ($galleryId && ! $existingGalleryIds->has($galleryId)) {
                // Entire gallery is deleted from DB
                $orphanGalleries[$galleryId] = ($orphanGalleries[$galleryId] ?? 0) + 1;
                $orphanGalleryFiles[] = $file;
                try {
                    $totalOrphanSize += $disk->size($file);
                } catch (\Exception $e) {
                    //
                }
            } elseif (! $referencedPaths->has($file)) {
                // File is in an existing gallery but not referenced by any photo
                $orphanFiles[] = $file;
                try {
                    $totalOrphanSize += $disk->size($file);
                } catch (\Exception $e) {
                    //
                }
            }
        }

        $totalOrphans = count($orphanGalleryFiles) + count($orphanFiles);

        if ($totalOrphans === 0) {
            $this->info('No orphan files found!');

            return Command::SUCCESS;
        }

        $sizeMb = round($totalOrphanSize / 1024 / 1024, 2);

        // 5. Report orphan galleries
        if (! empty($orphanGalleries)) {
            $this->warn('Orphan galleries (deleted from DB, still on MinIO):');
            $rows = [];
            foreach ($orphanGalleries as $id => $count) {
                $rows[] = [$id, $count];
            }
            $this->table(['Gallery ID', 'Files'], $rows);
            $this->info('Subtotal: ' . count($orphanGalleryFiles) . ' files in ' . count($orphanGalleries) . ' deleted galleries');
            $this->newLine();
        }

        // 6. Report orphan files in existing galleries
        if (! empty($orphanFiles)) {
            $this->warn('Orphan files (in existing galleries, not referenced in DB):');
            $preview = array_slice($orphanFiles, 0, 20);
            foreach ($preview as $path) {
                $this->line("  - {$path}");
            }
            if (count($orphanFiles) > 20) {
                $this->line('  ... and ' . (count($orphanFiles) - 20) . ' more');
            }
            $this->info('Subtotal: ' . count($orphanFiles) . ' orphan files');
            $this->newLine();
        }

        $this->warn("Total: {$totalOrphans} orphan files ({$sizeMb} MB)");
        $this->newLine();

        // 7. Delete if requested
        if ($delete) {
            if (! $this->confirm("Delete {$totalOrphans} orphan files ({$sizeMb} MB)?")) {
                $this->info('Cancelled.');

                return Command::SUCCESS;
            }

            $allOrphans = array_merge($orphanGalleryFiles, $orphanFiles);
            $bar = $this->output->createProgressBar(count($allOrphans));
            $deleted = 0;
            $failed = 0;

            foreach ($allOrphans as $path) {
                try {
                    $disk->delete($path);
                    $deleted++;
                } catch (\Exception $e) {
                    $failed++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("Deleted: {$deleted} | Failed: {$failed}");

            // Clean up empty gallery directories
            if (! empty($orphanGalleries)) {
                foreach (array_keys($orphanGalleries) as $galleryId) {
                    try {
                        $disk->deleteDirectory($galleryId);
                    } catch (\Exception $e) {
                        //
                    }
                }
                $this->info('Cleaned up ' . count($orphanGalleries) . ' empty gallery directories.');
            }
        }

        return Command::SUCCESS;
    }
}
