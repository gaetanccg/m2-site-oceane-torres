<?php

namespace App\Services\Supervision;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final class HealthSnapshot implements Arrayable
{
    /**
     * @param  array<string, ProbeResult>  $results  indexé par clé de sonde
     */
    public function __construct(
        public readonly HealthStatus $status,
        public readonly array $results,
        public readonly float $durationMs,
    ) {}

    public function isHealthy(): bool
    {
        return $this->status->isOk();
    }

    /**
     * @return array<string, ProbeResult>
     */
    public function failing(): array
    {
        return array_filter($this->results, fn (ProbeResult $result) => ! $result->status->isOk());
    }

    /**
     * @return list<string>
     */
    public function reasons(): array
    {
        $reasons = [];

        foreach ($this->results as $result) {
            foreach ($result->reasons as $reason) {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'duration_ms' => $this->durationMs,
            'checks' => array_map(fn (ProbeResult $result) => $result->toArray(), $this->results),
        ];
    }
}
