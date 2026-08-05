<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Supervision\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function __construct(
        private HealthCheckService $health,
    ) {}

    public function index(): JsonResponse
    {
        $snapshot = $this->health->snapshot();

        return response()->json(
            $this->health->publicPayload($snapshot),
            $this->health->httpStatusFor($snapshot),
        );
    }

    /**
     * Liveness : aucune sonde, toujours 200. Conserve le contrat historique de la
     * racine de l'API, et sert de cible aux healthchecks Docker — contrairement à
     * index(), qui répond 503 pour des pannes qu'un conteneur ne peut pas réparer.
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'API Océane Torres Photographie',
            'version' => $this->health->version(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function details(): JsonResponse
    {
        $snapshot = $this->health->snapshot();

        return response()->json(
            $this->health->detailedPayload($snapshot),
            $this->health->httpStatusFor($snapshot),
        );
    }

    public function database(): JsonResponse
    {
        try {
            $startedAt = microtime(true);
            $version = DB::selectOne('SELECT version() AS version')->version;
            $elapsedMs = round((microtime(true) - $startedAt) * 1000, 1);

            $tables = DB::selectOne("
                SELECT COUNT(*) AS count
                FROM information_schema.tables
                WHERE table_schema = 'public'
            ");

            return response()->json([
                'status' => 'connected',
                'driver' => DB::connection()->getDriverName(),
                'server_version' => $version,
                'tables_count' => (int) $tables->count,
                'response_time_ms' => $elapsedMs,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (Throwable $e) {
            Log::error('Diagnostic base de données en échec', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'La connexion à la base de données a échoué.',
                'error_type' => class_basename($e),
            ], 503);
        }
    }
}
