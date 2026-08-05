<?php

namespace App\Services\Supervision;

enum HealthStatus: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Down = 'down';

    public function severity(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Degraded => 1,
            self::Down => 2,
        };
    }

    public function isOk(): bool
    {
        return $this === self::Ok;
    }

    public static function worst(self ...$statuses): self
    {
        $worst = self::Ok;

        foreach ($statuses as $status) {
            if ($status->severity() > $worst->severity()) {
                $worst = $status;
            }
        }

        return $worst;
    }
}
