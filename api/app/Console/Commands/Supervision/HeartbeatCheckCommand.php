<?php

namespace App\Console\Commands\Supervision;

use App\Services\Supervision\HeartbeatService;
use Illuminate\Console\Command;

class HeartbeatCheckCommand extends Command
{
    protected $signature = 'supervision:heartbeat:check
        {component=scheduler : Composant surveillé (scheduler|queue)}
        {--max-age= : Âge maximal toléré, en secondes}';

    protected $description = 'Sort en échec si le heartbeat du composant est périmé (healthcheck Docker)';

    public function handle(HeartbeatService $heartbeats): int
    {
        $component = (string) $this->argument('component');

        if (! in_array($component, HeartbeatService::COMPONENTS, true)) {
            $this->error("Composant inconnu : {$component} (attendu : ".implode(', ', HeartbeatService::COMPONENTS).')');

            return self::FAILURE;
        }

        $maxAge = $this->maxAgeInSeconds($component);
        $age = $heartbeats->ageInSeconds($component);

        if ($age === null) {
            $this->error("Aucun heartbeat pour {$component}");

            return self::FAILURE;
        }

        if ($age > $maxAge) {
            $this->error("Heartbeat {$component} périmé : {$age}s (max {$maxAge}s)");

            return self::FAILURE;
        }

        $this->info("Heartbeat {$component} frais : {$age}s (max {$maxAge}s)");

        return self::SUCCESS;
    }

    private function maxAgeInSeconds(string $component): int
    {
        $option = $this->option('max-age');

        if (is_numeric($option) && (int) $option > 0) {
            return (int) $option;
        }

        $minutes = $component === HeartbeatService::QUEUE
            ? config('supervision.thresholds.queue_worker_stale_minutes')
            : config('supervision.thresholds.scheduler_stale_minutes');

        return (int) $minutes * 60;
    }
}
