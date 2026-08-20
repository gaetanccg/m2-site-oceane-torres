<?php

namespace App\Console\Commands\Supervision;

use App\Services\Supervision\SupervisionAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupervisionReportCommand extends Command
{
    protected $signature = 'supervision:report';

    protected $description = "Envoie le rapport de santé quotidien à l'administrateur";

    public function handle(SupervisionAlertService $alerts): int
    {
        try {
            $sent = $alerts->sendDailyReport();
        } catch (Throwable $e) {
            Log::error("Échec de l'envoi du rapport de santé", [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $this->error("Impossible d'envoyer le rapport : ".$e->getMessage());

            return self::FAILURE;
        }

        $this->info($sent ? 'Rapport de santé envoyé.' : 'Rapport de santé désactivé (aucun envoi).');

        return self::SUCCESS;
    }
}
