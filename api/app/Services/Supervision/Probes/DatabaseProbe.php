<?php

namespace App\Services\Supervision\Probes;

use App\Services\Supervision\ProbeResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DatabaseProbe implements Probe
{
    public function key(): string
    {
        return 'database';
    }

    public function check(): ProbeResult
    {
        $startedAt = microtime(true);

        try {
            DB::select('SELECT 1');
        } catch (Throwable $e) {
            Log::warning('Sonde base de données en échec', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ProbeResult::down(
                'La base de données ne répond pas.',
                ['error_type' => class_basename($e)],
                ['database_unreachable'],
            );
        }

        $elapsedMs = round((microtime(true) - $startedAt) * 1000, 1);
        $threshold = (int) config('supervision.thresholds.database_slow_ms');
        $details = ['response_time_ms' => $elapsedMs];

        if ($elapsedMs > $threshold) {
            return ProbeResult::degraded(
                "La base répond en {$elapsedMs} ms (seuil : {$threshold} ms).",
                $details + ['threshold_ms' => $threshold],
                ['database_slow'],
            );
        }

        return ProbeResult::ok($details);
    }
}
