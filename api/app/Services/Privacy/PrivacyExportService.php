<?php

namespace App\Services\Privacy;

use App\Models\Invoice;
use App\Models\PrivacyExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Génère les exports RGPD (ZIP : un JSON par table + PDF de factures).
 * Le ZIP est stocké sur le disque `local` (comme les exports scolaires).
 */
class PrivacyExportService
{
    public function __construct(
        private PersonalDataLocator $locator,
    ) {}

    /**
     * Tables incluses dans l'export global. Les tables d'infra (sessions,
     * tokens, cache, jobs) sont volontairement exclues.
     *
     * @var array<int, string>
     */
    private const GLOBAL_TABLES = [
        'users',
        'clients',
        'orders',
        'order_items',
        'payments',
        'invoices',
        'carts',
        'cart_items',
        'reservations',
        'client_forms',
        'contact_messages',
        'galleries',
        'photos',
        'gift_cards',
        'gift_codes',
        'download_logs',
        'photo_uploads',
        'notifications',
        'school_sessions',
        'prestations',
    ];

    public function build(PrivacyExport $export): void
    {
        match ($export->type) {
            'global' => $this->buildGlobal($export),
            'subject' => $this->buildSubject($export),
            default => throw new RuntimeException("Type d'export non supporté : {$export->type}"),
        };
    }

    /**
     * Export ciblé : toutes les données d'UNE personne (email/tél/n° commande)
     * — JSON structuré (via le résolveur) + PDF de ses factures.
     */
    private function buildSubject(PrivacyExport $export): void
    {
        $result = $this->locator->locate($export->subject_type, $export->subject_value);

        $orderIds = collect($result['categories']['orders'] ?? [])->pluck('id')->filter();
        $invoices = $orderIds->isNotEmpty()
            ? Invoice::whereIn('order_id', $orderIds)->whereNotNull('file_path')->get(['id', 'invoice_number', 'file_path'])
            : collect();

        $export->update([
            'total_items' => 1 + $invoices->count(),
            'processed_items' => 0,
        ]);

        [$zip, $relativeZipPath] = $this->openZip($export);

        $zip->addFromString('MANIFEST.json', json_encode([
            'type' => 'subject',
            'subject' => $result['query'],
            'summary' => $result['summary'],
            'generated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->addFromString('data.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $export->increment('processed_items');

        $this->addInvoices($zip, $invoices, $export);

        $zip->close();
        $this->finalize($export, $relativeZipPath);
    }

    private function buildGlobal(PrivacyExport $export): void
    {
        $tables = collect(self::GLOBAL_TABLES)->filter(fn ($t) => Schema::hasTable($t))->values();

        $invoices = Invoice::whereNotNull('file_path')->get(['id', 'invoice_number', 'file_path']);

        $export->update([
            'total_items' => $tables->count() + $invoices->count(),
            'processed_items' => 0,
        ]);

        [$zip, $relativeZipPath] = $this->openZip($export);

        $zip->addFromString('MANIFEST.json', json_encode([
            'type' => 'global',
            'generated_at' => now()->toIso8601String(),
            'tables' => $tables->all(),
            'invoices_count' => $invoices->count(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 1. Un JSON par table.
        foreach ($tables as $table) {
            $rows = DB::table($table)->get();
            $zip->addFromString(
                "data/{$table}.json",
                json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            );
            $export->increment('processed_items');
        }

        // 2. Les PDF de factures (depuis MinIO).
        $this->addInvoices($zip, $invoices, $export);

        $zip->close();
        $this->finalize($export, $relativeZipPath);
    }

    /**
     * @return array{0: \ZipArchive, 1: string}
     */
    private function openZip(PrivacyExport $export): array
    {
        Storage::disk('local')->makeDirectory('exports/privacy');
        $relativeZipPath = "exports/privacy/{$export->id}.zip";
        $zipPath = Storage::disk('local')->path($relativeZipPath);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible de créer l'archive ZIP.");
        }

        return [$zip, $relativeZipPath];
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function addInvoices(\ZipArchive $zip, $invoices, PrivacyExport $export): void
    {
        $minio = Storage::disk('minio');
        foreach ($invoices as $invoice) {
            try {
                if ($minio->exists($invoice->file_path)) {
                    $zip->addFromString(
                        "invoices/{$invoice->invoice_number}.pdf",
                        $minio->get($invoice->file_path),
                    );
                }
            } catch (\Throwable) {
                // Facture illisible sur le stockage : on l'ignore sans casser l'export.
            }
            $export->increment('processed_items');
        }
    }

    private function finalize(PrivacyExport $export, string $relativeZipPath): void
    {
        $export->update([
            'status' => 'completed',
            'file_path' => $relativeZipPath,
            'file_size_bytes' => Storage::disk('local')->size($relativeZipPath),
        ]);
    }
}
