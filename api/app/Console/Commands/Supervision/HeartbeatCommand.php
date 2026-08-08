<?php

namespace App\Console\Commands\Supervision;

use App\Services\Supervision\HeartbeatService;
use Illuminate\Console\Command;

class HeartbeatCommand extends Command
{
    protected $signature = 'supervision:heartbeat {component=scheduler : Composant surveillé (scheduler|queue)}';

    protected $description = 'Écrit le heartbeat du composant indiqué dans le cache de supervision';

    public function handle(HeartbeatService $heartbeats): int
    {
        $component = (string) $this->argument('component');

        if (! in_array($component, HeartbeatService::COMPONENTS, true)) {
            $this->error("Composant inconnu : {$component} (attendu : ".implode(', ', HeartbeatService::COMPONENTS).')');

            return self::FAILURE;
        }

        $at = $heartbeats->touch($component);

        $this->info("Heartbeat {$component} écrit à {$at->toIso8601String()}");

        return self::SUCCESS;
    }
}
