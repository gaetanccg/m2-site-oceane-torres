<?php

namespace App\Jobs;

use App\Models\SchoolSessionExport;
use App\Services\SchoolSessionExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateSchoolSessionExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public int $maxExceptions = 1;

    public function __construct(
        public string $exportId,
    ) {}

    public function handle(SchoolSessionExportService $service): void
    {
        $export = SchoolSessionExport::find($this->exportId);
        if (! $export) {
            Log::error('GenerateSchoolSessionExportJob: export not found', [
                'export_id' => $this->exportId,
            ]);

            return;
        }

        try {
            $export->markAs('processing');
            Log::info('Starting school session export', ['export_id' => $export->id]);

            $service->build($export);

            Log::info('School session export complete', [
                'export_id' => $export->id,
                'status' => $export->fresh()->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateSchoolSessionExportJob failed', [
                'export_id' => $this->exportId,
                'error' => $e->getMessage(),
            ]);
            $export->markAs('failed', "La génération de l'export a échoué. Veuillez consulter les logs serveur pour plus de détails.");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateSchoolSessionExportJob: permanently failed', [
            'export_id' => $this->exportId,
            'error' => $exception->getMessage(),
        ]);

        try {
            $export = SchoolSessionExport::find($this->exportId);
            if ($export && $export->status !== 'failed') {
                $export->markAs('failed', "La génération de l'export a échoué. Veuillez consulter les logs serveur pour plus de détails.");
            }
        } catch (\Exception) {
            // ignore
        }
    }
}
