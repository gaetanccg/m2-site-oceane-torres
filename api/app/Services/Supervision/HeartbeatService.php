<?php

namespace App\Services\Supervision;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class HeartbeatService
{
    public const SCHEDULER = 'scheduler';

    public const QUEUE = 'queue';

    public const COMPONENTS = [self::SCHEDULER, self::QUEUE];

    /** @var array<string, float> */
    private array $lastWriteAt = [];

    public function key(string $component): string
    {
        return "supervision:{$component}:heartbeat";
    }

    public function touch(string $component): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        Cache::put(
            $this->key($component),
            $now->getTimestamp(),
            now()->addMinutes((int) config('supervision.heartbeat.ttl_minutes')),
        );

        $this->lastWriteAt[$component] = microtime(true);

        return $now;
    }

    public function touchThrottled(string $component): bool
    {
        $interval = (int) config('supervision.heartbeat.write_interval_seconds');
        $lastWrite = $this->lastWriteAt[$component] ?? null;

        if ($lastWrite !== null && (microtime(true) - $lastWrite) < $interval) {
            return false;
        }

        $this->touch($component);

        return true;
    }

    public function lastSeen(string $component): ?CarbonImmutable
    {
        $timestamp = Cache::get($this->key($component));

        if (! is_numeric($timestamp)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $timestamp);
    }

    public function ageInSeconds(string $component): ?int
    {
        $timestamp = Cache::get($this->key($component));

        if (! is_numeric($timestamp)) {
            return null;
        }

        return max(0, now()->getTimestamp() - (int) $timestamp);
    }

    public function isFresh(string $component, int $maxAgeSeconds): bool
    {
        $age = $this->ageInSeconds($component);

        return $age !== null && $age <= $maxAgeSeconds;
    }

    public function forget(string $component): void
    {
        Cache::forget($this->key($component));
        unset($this->lastWriteAt[$component]);
    }
}
