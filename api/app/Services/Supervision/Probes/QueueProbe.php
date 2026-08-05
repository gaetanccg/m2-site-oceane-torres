<?php

namespace App\Services\Supervision\Probes;

use App\Services\Supervision\HealthStatus;
use App\Services\Supervision\HeartbeatService;
use App\Services\Supervision\ProbeResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueProbe implements Probe
{
    public function __construct(
        private HeartbeatService $heartbeats,
    ) {}

    public function key(): string
    {
        return 'queue';
    }

    public function check(): ProbeResult
    {
        $connection = (string) config('queue.default');
        $details = ['connection' => $connection];
        $reasons = [];
        $statuses = [HealthStatus::Ok];
        $messages = [];

        try {
            $failed = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();
            $details['failed_last_24h'] = $failed;

            if ($failed > 0) {
                $reasons[] = 'queue_failed_jobs';
                $statuses[] = HealthStatus::Degraded;
                $messages[] = "{$failed} job(s) en échec sur les dernières 24 h.";
            }

            // La table `jobs` ne reflète la file que sur le driver database.
            if ($connection === 'database') {
                [$pendingStatuses, $pendingReasons, $pendingMessages] = $this->inspectDatabaseQueue($details);

                $statuses = array_merge($statuses, $pendingStatuses);
                $reasons = array_merge($reasons, $pendingReasons);
                $messages = array_merge($messages, $pendingMessages);
            }
        } catch (Throwable $e) {
            Log::warning('Sonde file d\'attente en échec', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ProbeResult::down(
                "L'état de la file d'attente est illisible (base injoignable ?).",
                $details + ['error_type' => class_basename($e)],
                ['queue_unreadable'],
            );
        }

        [$workerStatus, $workerReasons, $workerMessages] = $this->inspectWorker($details);
        $statuses[] = $workerStatus;
        $reasons = array_merge($reasons, $workerReasons);
        $messages = array_merge($messages, $workerMessages);

        $status = HealthStatus::worst(...$statuses);

        if ($status->isOk()) {
            return ProbeResult::ok($details);
        }

        $message = implode(' ', $messages);

        return $status === HealthStatus::Down
            ? ProbeResult::down($message, $details, $reasons)
            : ProbeResult::degraded($message, $details, $reasons);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{0: list<HealthStatus>, 1: list<string>, 2: list<string>}
     */
    private function inspectDatabaseQueue(array &$details): array
    {
        $statuses = [];
        $reasons = [];
        $messages = [];

        $pending = DB::table('jobs')->whereNull('reserved_at')->count();
        $reserved = DB::table('jobs')->whereNotNull('reserved_at')->count();

        $details['pending'] = $pending;
        $details['reserved'] = $reserved;

        $depthThreshold = (int) config('supervision.thresholds.queue_depth');

        if ($pending > $depthThreshold) {
            $reasons[] = 'queue_depth';
            $statuses[] = HealthStatus::Degraded;
            $messages[] = "File engorgée : {$pending} jobs en attente (seuil : {$depthThreshold}).";
        }

        // Ni réservés, ni différés dans le futur : réellement en attente.
        $oldestAvailableAt = DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->getTimestamp())
            ->min('created_at');

        $oldestAge = $oldestAvailableAt === null
            ? null
            : max(0, now()->getTimestamp() - (int) $oldestAvailableAt);

        $details['oldest_pending_age_seconds'] = $oldestAge;

        $stalledThreshold = (int) config('supervision.thresholds.queue_oldest_pending_minutes') * 60;

        if ($oldestAge !== null && $oldestAge > $stalledThreshold) {
            $minutes = (int) round($oldestAge / 60);
            $reasons[] = 'queue_stalled';
            $statuses[] = HealthStatus::Degraded;
            $messages[] = "Un job attend depuis {$minutes} min (seuil : ".($stalledThreshold / 60).' min).';
        }

        return [$statuses, $reasons, $messages];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{0: HealthStatus, 1: list<string>, 2: list<string>}
     */
    private function inspectWorker(array &$details): array
    {
        $age = $this->heartbeats->ageInSeconds(HeartbeatService::QUEUE);
        $lastSeen = $this->heartbeats->lastSeen(HeartbeatService::QUEUE);

        $details['worker_last_seen'] = $lastSeen?->toIso8601String();
        $details['worker_age_seconds'] = $age;

        $staleThreshold = (int) config('supervision.thresholds.queue_worker_stale_minutes') * 60;

        if ($age === null) {
            return [
                HealthStatus::Down,
                ['queue_worker_missing'],
                ['Aucun signe de vie du worker de queue : les jobs ne sont pas traités.'],
            ];
        }

        if ($age > $staleThreshold) {
            $minutes = (int) round($age / 60);

            return [
                HealthStatus::Degraded,
                ['queue_worker_stale'],
                ["Worker de queue silencieux depuis {$minutes} min (seuil : ".($staleThreshold / 60).' min).'],
            ];
        }

        return [HealthStatus::Ok, [], []];
    }
}
