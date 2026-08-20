<?php

namespace App\Services\Supervision\Probes;

use App\Services\Supervision\HeartbeatService;
use App\Services\Supervision\ProbeResult;

class SchedulerProbe implements Probe
{
    public function __construct(
        private HeartbeatService $heartbeats,
    ) {}

    public function key(): string
    {
        return 'scheduler';
    }

    public function check(): ProbeResult
    {
        $age = $this->heartbeats->ageInSeconds(HeartbeatService::SCHEDULER);
        $lastSeen = $this->heartbeats->lastSeen(HeartbeatService::SCHEDULER);
        $staleThreshold = (int) config('supervision.thresholds.scheduler_stale_minutes') * 60;

        $details = [
            'last_seen' => $lastSeen?->toIso8601String(),
            'age_seconds' => $age,
            'stale_after_seconds' => $staleThreshold,
        ];

        if ($age === null) {
            return ProbeResult::down(
                'Aucun signe de vie du scheduler : les tâches planifiées ne tournent pas.',
                $details,
                ['scheduler_missing'],
            );
        }

        if ($age > $staleThreshold) {
            $minutes = (int) round($age / 60);

            return ProbeResult::degraded(
                "Scheduler silencieux depuis {$minutes} min (seuil : ".($staleThreshold / 60).' min).',
                $details,
                ['scheduler_stale'],
            );
        }

        return ProbeResult::ok($details);
    }
}
