<?php

namespace App\Services\Supervision;

use App\Services\Supervision\Probes\DatabaseProbe;
use App\Services\Supervision\Probes\Probe;
use App\Services\Supervision\Probes\QueueProbe;
use App\Services\Supervision\Probes\SchedulerProbe;
use App\Services\Supervision\Probes\StorageProbe;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HealthCheckService
{
    /** @var list<Probe> */
    private array $probes;

    public function __construct(
        DatabaseProbe $database,
        StorageProbe $storage,
        QueueProbe $queue,
        SchedulerProbe $scheduler,
    ) {
        $this->probes = [$database, $storage, $queue, $scheduler];
    }

    public function snapshot(): HealthSnapshot
    {
        $startedAt = microtime(true);
        $results = [];

        foreach ($this->probes as $probe) {
            $results[$probe->key()] = $this->check($probe);
        }

        $status = HealthStatus::worst(
            ...array_map(fn (ProbeResult $result) => $result->status, $results)
        );

        return new HealthSnapshot(
            $status,
            $results,
            round((microtime(true) - $startedAt) * 1000, 1),
        );
    }

    private function check(Probe $probe): ProbeResult
    {
        try {
            return $probe->check();
        } catch (Throwable $e) {
            Log::error('Sonde de supervision en erreur', [
                'probe' => $probe->key(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ProbeResult::down(
                'La sonde elle-même est en erreur.',
                ['error_type' => class_basename($e)],
                [$probe->key().'_probe_error'],
            );
        }
    }

    /**
     * Contrat historique de GET /api/health : les clés status, message, version et
     * timestamp sont conservées à l'identique.
     *
     * @return array<string, mixed>
     */
    public function publicPayload(HealthSnapshot $snapshot): array
    {
        return [
            'status' => $snapshot->status->value,
            'message' => 'API Océane Torres Photographie',
            'version' => $this->version(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailedPayload(HealthSnapshot $snapshot): array
    {
        return [
            'status' => $snapshot->status->value,
            'message' => 'API Océane Torres Photographie',
            'version' => $this->version(),
            'environment' => (string) config('app.env'),
            'timestamp' => now()->toISOString(),
            'duration_ms' => $snapshot->durationMs,
            'checks' => array_map(fn (ProbeResult $result) => $result->toArray(), $snapshot->results),
        ];
    }

    public function httpStatusFor(HealthSnapshot $snapshot): int
    {
        return $snapshot->isHealthy()
            ? Response::HTTP_OK
            : Response::HTTP_SERVICE_UNAVAILABLE;
    }

    public function version(): string
    {
        return (string) config('app.version');
    }
}
