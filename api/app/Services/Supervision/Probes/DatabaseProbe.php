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
        try {
            // Établissement et requête sont chronométrés séparément : PDO n'est
            // pas persistant (config/database.php) et supervision:alert tourne
            // dans un process forké, donc le premier SELECT paierait sinon
            // DNS + TLS + poignée de main du pooler. Mélanger les deux faisait
            // remonter « base lente » alors que la base répondait en 2 ms.
            $startedAt = microtime(true);
            DB::connection()->getPdo();
            $connectMs = round((microtime(true) - $startedAt) * 1000, 1);

            $startedAt = microtime(true);
            DB::select('SELECT 1');
            $queryMs = round((microtime(true) - $startedAt) * 1000, 1);
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

        $queryThreshold = (int) config('supervision.thresholds.database_slow_ms');
        $connectThreshold = (int) config('supervision.thresholds.database_connect_slow_ms');

        $details = [
            'response_time_ms' => $queryMs,
            'connect_time_ms' => $connectMs,
        ];

        if ($queryMs > $queryThreshold) {
            return ProbeResult::degraded(
                "La base répond en {$queryMs} ms (seuil : {$queryThreshold} ms).",
                $details + ['threshold_ms' => $queryThreshold],
                ['database_slow'],
            );
        }

        if ($connectMs > $connectThreshold) {
            return ProbeResult::degraded(
                "L'ouverture de connexion prend {$connectMs} ms (seuil : {$connectThreshold} ms). "
                    ."La base elle-même répond en {$queryMs} ms.",
                $details + ['connect_threshold_ms' => $connectThreshold],
                ['database_connect_slow'],
            );
        }

        return ProbeResult::ok($details);
    }
}
