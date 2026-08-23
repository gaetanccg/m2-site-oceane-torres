<?php

namespace App\Jobs;

use App\Models\PrivacyExport;
use App\Services\Privacy\PrivacyExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GeneratePrivacyExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public int $maxExceptions = 1;

    public function __construct(
        public string $exportId,
    ) {
        $this->onQueue('heavy');
    }

    public function handle(PrivacyExportService $service): void
    {
        $export = PrivacyExport::find($this->exportId);
        if (! $export) {
            Log::error('GeneratePrivacyExportJob: export not found', ['export_id' => $this->exportId]);

            return;
        }

        try {
            $export->markAs('processing');
            $service->build($export);
        } catch (\Throwable $e) {
            Log::error('GeneratePrivacyExportJob failed', [
                'export_id' => $this->exportId,
                'error' => $e->getMessage(),
            ]);
            $export->markAs('failed', "La génération de l'export a échoué. Consultez les logs serveur.");

            // Relance apres avoir marque le statut : sans elle la file croit avoir reussi,
            // failed_jobs reste vide et la supervision ne voit rien. $tries = 1, donc
            // aucune reprise — le traitement n'est pas idempotent.
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GeneratePrivacyExportJob: permanently failed', [
            'export_id' => $this->exportId,
            'error' => $exception->getMessage(),
        ]);
    }
}
