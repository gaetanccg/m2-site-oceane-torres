<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'API Océane Torres Photographie',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function database(): JsonResponse
    {
        try {
            $pdo = DB::connection()->getPdo();
            $version = DB::select('SELECT version()')[0]->version;

            // Count tables
            $tables = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.tables
                WHERE table_schema = 'public'
            ");

            return response()->json([
                'status' => 'connected',
                'database' => 'PostgreSQL',
                'version' => $version,
                'tables_count' => $tables[0]->count,
                'host' => config('database.connections.pgsql.host'),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function tables(): JsonResponse
    {
        try {
            $tables = DB::select("
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = 'public'
                ORDER BY table_name
            ");

            $tableNames = array_map(fn ($t) => $t->table_name, $tables);

            return response()->json([
                'status' => 'ok',
                'tables' => $tableNames,
                'count' => count($tableNames),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
