<?php

namespace App\Console\Commands\Supervision;

use App\Services\Supervision\SupervisionAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupervisionAlertCommand extends Command
{
    protected $signature = 'supervision:alert';

    protected $description = "Vérifie les sondes et envoie une alerte email en cas d'anomalie";

    public function handle(SupervisionAlertService $alerts): int
    {
        try {
            $notified = $alerts->dispatchAlerts();
        } catch (Throwable $e) {
            Log::error("Échec de l'envoi d'une alerte de supervision", [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $this->error("Impossible d'envoyer l'alerte : ".$e->getMessage());

            return self::FAILURE;
        }

        if ($notified === []) {
            $this->info('Aucune alerte à envoyer.');

            return self::SUCCESS;
        }

        $this->warn('Alerte envoyée pour : '.implode(', ', $notified));

        return self::SUCCESS;
    }
}
