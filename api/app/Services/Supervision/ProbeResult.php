<?php

namespace App\Services\Supervision;

use Illuminate\Contracts\Support\Arrayable;

/**
 * `reasons` porte des codes machine (queue_failed_jobs, scheduler_stale…) : c'est
 * le contrat entre les sondes et le système d'alerte, qui les utilise comme clé
 * de verrou anti-spam.
 *
 * @implements Arrayable<string, mixed>
 */
final class ProbeResult implements Arrayable
{
    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $reasons
     */
    private function __construct(
        public readonly HealthStatus $status,
        public readonly ?string $message,
        public readonly array $details,
        public readonly array $reasons,
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public static function ok(array $details = []): self
    {
        return new self(HealthStatus::Ok, null, $details, []);
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $reasons
     */
    public static function degraded(string $message, array $details = [], array $reasons = []): self
    {
        return new self(HealthStatus::Degraded, $message, $details, $reasons);
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $reasons
     */
    public static function down(string $message, array $details = [], array $reasons = []): self
    {
        return new self(HealthStatus::Down, $message, $details, $reasons);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status->value,
            'message' => $this->message,
            'reasons' => $this->reasons,
            'details' => $this->details,
        ], fn ($value) => $value !== null && $value !== []);
    }
}
