<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\SchoolSession;
use App\Models\SchoolSessionExport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SchoolSessionExportService
{
    public function __construct(
        private MinioStorageService $storageService,
    ) {}

    /**
     * Build the ZIP export file. Updates the export record as it progresses.
     */
    public function build(SchoolSessionExport $export): void
    {
        $session = $export->schoolSession;

        if (! $session) {
            $export->markAs('failed', 'Session introuvable');

            return;
        }

        // 1. Collect order items for this session
        $items = $this->collectOrderItems($session, $export->include_digital);

        if ($items->isEmpty()) {
            $export->markAs('failed', 'Aucune commande payée trouvee pour cette session.');

            return;
        }

        $export->update([
            'total_items' => $items->count(),
            'processed_items' => 0,
        ]);

        // 2. Prepare temp + output paths
        $tempDir = storage_path('app/private/temp/exports/'.$export->id);
        $outputDir = storage_path('app/private/exports/school-sessions/'.$session->id);
        $zipFilename = $this->buildZipFilename($session);
        $zipPath = $outputDir.'/'.$export->id.'_'.$zipFilename;
        $relativeZipPath = 'exports/school-sessions/'.$session->id.'/'.$export->id.'_'.$zipFilename;

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 3. Build ZIP
        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $export->markAs('failed', 'Impossible de creer le fichier ZIP.');

            return;
        }

        try {
            // Download each unique photo once into temp dir
            $photoTempPaths = []; // photo_id => local temp path
            $usedNames = [];      // class/child => [filename => count] for collision handling
            $indexRows = [];

            foreach ($items as $item) {
                $photo = $item->photo;
                if (! $photo) {
                    continue;
                }

                $gallery = $photo->gallery;
                if (! $gallery) {
                    continue;
                }

                // Download the photo if not already done
                $photoId = $photo->id;
                if (! isset($photoTempPaths[$photoId])) {
                    $sourcePath = $photo->file_path_hd ?? $photo->file_path;
                    $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';
                    $tempFile = $tempDir.'/'.$photoId.'.'.$extension;

                    $content = $this->storageService->getFileContent($sourcePath);
                    if ($content === null) {
                        Log::warning('Export: failed to download photo', ['photo_id' => $photoId]);
                        $export->increment('processed_items');

                        continue;
                    }

                    file_put_contents($tempFile, $content);
                    unset($content);
                    $photoTempPaths[$photoId] = ['path' => $tempFile, 'extension' => $extension];
                }

                // Build the in-zip path: Classe/Eleve/photo.ext (with collision suffix per quantity)
                $className = $this->sanitizeFolder($gallery->class_name ?? 'Sans classe');
                $childName = $this->sanitizeFolder($gallery->title);
                $folder = $className.'/'.$childName;
                $originalFilename = $photo->metadata['original_filename'] ?? ($photo->title.'.'.$photoTempPaths[$photoId]['extension']);
                $baseFilename = $this->sanitizeFilename($originalFilename);

                if (! isset($usedNames[$folder])) {
                    $usedNames[$folder] = [];
                }

                // Duplicate the file once per unit ordered (quantity)
                $itemQuantity = max(1, (int) ($item->quantity ?? 1));

                for ($q = 0; $q < $itemQuantity; $q++) {
                    $count = ($usedNames[$folder][$baseFilename] ?? 0) + 1;
                    $usedNames[$folder][$baseFilename] = $count;

                    $finalName = $count === 1
                        ? $baseFilename
                        : $this->appendSuffix($baseFilename, $count);

                    $inZipPath = $folder.'/'.$finalName;
                    $zip->addFile($photoTempPaths[$photoId]['path'], $inZipPath);
                }

                // Track for index (one row aggregated, quantity recorded)
                $indexRows[] = [
                    'class' => $gallery->class_name ?? '',
                    'child' => $gallery->title,
                    'photo' => $baseFilename,
                    'product_type' => $item->product_type,
                    'price' => $item->price,
                    'quantity' => $itemQuantity,
                ];

                $export->increment('processed_items');
            }

            // 4. Build index CSV (aggregate by photo)
            $csvContent = $this->buildCsvIndex($indexRows);
            $zip->addFromString('_index.csv', $csvContent);

            // 5. Close ZIP (this triggers actual write of all addFile entries)
            $zip->close();

            // 6. Update export record
            $export->update([
                'status' => 'completed',
                'file_path' => $relativeZipPath,
                'file_size_bytes' => filesize($zipPath),
            ]);

            // 7. Cleanup temp files
            $this->cleanupTempDir($tempDir);

        } catch (\Throwable $e) {
            $zip->close();
            @unlink($zipPath);
            $this->cleanupTempDir($tempDir);
            $export->markAs('failed', 'Erreur pendant la generation: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete the export's ZIP file from disk.
     */
    public function deleteExportFile(SchoolSessionExport $export): void
    {
        if (! $export->file_path) {
            return;
        }

        if (Storage::disk('local')->exists($export->file_path)) {
            Storage::disk('local')->delete($export->file_path);
        }
    }

    /**
     * Collect all OrderItem rows for a session's galleries that belong to paid orders.
     * Returns one row per OrderItem (1 unit each — quantity is implicit).
     */
    private function collectOrderItems(SchoolSession $session, bool $includeDigital): \Illuminate\Database\Eloquent\Collection
    {
        $galleryIds = $session->galleries()->pluck('id');

        $query = OrderItem::query()
            ->with(['photo.gallery', 'order'])
            ->whereHas('order', fn ($q) => $q->where('status', 'paid'))
            ->whereHas('photo', fn ($q) => $q->whereIn('gallery_id', $galleryIds));

        if (! $includeDigital) {
            $query->where('product_type', '!=', 'digital');
        }

        return $query->orderBy('created_at')->get();
    }

    /**
     * Aggregate index rows: one CSV line per (class, child, photo, type) with quantity total.
     */
    private function buildCsvIndex(array $rows): string
    {
        // Aggregate by (class + child + photo + type) — sum quantity from each row
        $aggregated = [];
        foreach ($rows as $row) {
            $key = $row['class'].'|'.$row['child'].'|'.$row['photo'].'|'.$row['product_type'];
            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'class' => $row['class'],
                    'child' => $row['child'],
                    'photo' => $row['photo'],
                    'product_type' => $row['product_type'],
                    'unit_price' => (float) $row['price'],
                    'quantity' => 0,
                ];
            }
            $aggregated[$key]['quantity'] += (int) ($row['quantity'] ?? 1);
        }

        // Sort by class, child, photo
        usort($aggregated, function ($a, $b) {
            return [$a['class'], $a['child'], $a['photo']] <=> [$b['class'], $b['child'], $b['photo']];
        });

        // Build CSV with BOM for Excel compatibility
        $output = "\xEF\xBB\xBF";
        $output .= "Classe;Eleve;Photo;Type;Quantite;Prix unitaire;Total ligne\r\n";

        $grandTotal = 0;
        foreach ($aggregated as $row) {
            $lineTotal = $row['unit_price'] * $row['quantity'];
            $grandTotal += $lineTotal;
            $output .= sprintf(
                "%s;%s;%s;%s;%d;%s;%s\r\n",
                $this->csvEscape($row['class']),
                $this->csvEscape($row['child']),
                $this->csvEscape($row['photo']),
                $this->csvEscape($row['product_type']),
                $row['quantity'],
                number_format($row['unit_price'], 2, ',', ''),
                number_format($lineTotal, 2, ',', ''),
            );
        }

        $output .= sprintf(";;;;TOTAL;;%s\r\n", number_format($grandTotal, 2, ',', ''));

        return $output;
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    private function sanitizeFolder(string $name): string
    {
        // Remove characters that are problematic in file paths
        $name = trim($name);
        $name = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);

        return $name ?: 'Sans nom';
    }

    private function sanitizeFilename(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);

        return $name ?: 'photo.jpg';
    }

    private function appendSuffix(string $filename, int $count): string
    {
        $info = pathinfo($filename);
        $base = $info['filename'] ?? $filename;
        $ext = isset($info['extension']) ? '.'.$info['extension'] : '';

        return $base.' ('.$count.')'.$ext;
    }

    private function buildZipFilename(SchoolSession $session): string
    {
        $title = preg_replace('/[^A-Za-z0-9_-]/', '_', $session->title);
        $title = trim($title, '_');

        return ($title ?: 'shooting').'_commandes.zip';
    }

    private function cleanupTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
