<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Visionneuse des logs applicatifs depuis l'admin.
 * Lecture bornée (tail) pour ne jamais charger un gros fichier en mémoire.
 */
class LogController extends Controller
{
    /** Nombre max d'octets lus depuis la fin du fichier. */
    private const MAX_BYTES = 512 * 1024;

    private const LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /**
     * Fichier de log actif : le canal `daily` écrit dans laravel-Y-m-d.log,
     * le canal `single` dans laravel.log.
     */
    private function resolveLogPath(): ?string
    {
        if ($this->usesDailyChannel()) {
            $today = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');

            if (is_file($today)) {
                return $today;
            }

            // Rien écrit aujourd'hui : on montre le plus récent plutôt que rien.
            $rotated = glob(storage_path('logs/laravel-*.log')) ?: [];

            return $rotated === [] ? null : end($rotated);
        }

        $single = storage_path('logs/laravel.log');

        return is_file($single) ? $single : null;
    }

    private function usesDailyChannel(): bool
    {
        $default = (string) config('logging.default');

        if ($default !== 'stack') {
            return $default === 'daily';
        }

        // La config `stack.channels` est déjà un tableau (explode côté config),
        // mais reste tolérante à une valeur en chaîne.
        $channels = config('logging.channels.stack.channels', []);

        if (! is_array($channels)) {
            $channels = explode(',', (string) $channels);
        }

        return in_array('daily', array_map('trim', $channels), true);
    }

    public function index(Request $request): JsonResponse
    {
        $path = $this->resolveLogPath();

        if ($path === null) {
            return response()->json([
                'success' => true,
                'lines' => [],
                'size' => 0,
                'truncated' => false,
            ]);
        }

        $size = filesize($path);
        $offset = max(0, $size - self::MAX_BYTES);

        $handle = fopen($path, 'rb');
        fseek($handle, $offset);
        $content = fread($handle, $size - $offset) ?: '';
        fclose($handle);

        $lines = explode("\n", $content);
        // Si on a coupé au milieu du fichier, la 1re ligne est partielle : on la retire.
        if ($offset > 0) {
            array_shift($lines);
        }

        $level = strtoupper((string) $request->input('level', ''));
        if (in_array($level, self::LEVELS, true)) {
            $lines = array_filter($lines, fn ($l) => str_contains($l, '.'.$level.':'));
        }

        $search = (string) $request->input('search', '');
        if ($search !== '') {
            $lines = array_filter($lines, fn ($l) => stripos($l, $search) !== false);
        }

        $limit = min(2000, max(1, (int) $request->input('limit', 300)));
        $lines = array_slice(array_values(array_filter($lines, fn ($l) => $l !== '')), -$limit);

        return response()->json([
            'success' => true,
            'lines' => array_values($lines),
            'size' => $size,
            'truncated' => $offset > 0,
        ]);
    }

    public function download(): BinaryFileResponse|JsonResponse
    {
        $path = $this->resolveLogPath();

        if ($path === null) {
            return response()->json(['success' => false, 'message' => 'Aucun log disponible.'], 404);
        }

        return response()->download($path, basename($path));
    }

    /**
     * Vide le fichier de log actif — celui que la visionneuse affiche. On tronque
     * plutôt que de supprimer (évite les soucis de permissions à la recréation par
     * le logger), et les fichiers déjà tournés sont laissés intacts.
     * L'action elle-même est immédiatement re-tracée pour garder qui/quand/IP.
     */
    public function clear(Request $request): JsonResponse
    {
        $path = $this->resolveLogPath();

        if ($path !== null) {
            file_put_contents($path, '');
        }

        Log::warning('Logs applicatifs vidés depuis l\'administration', [
            'admin_id' => $request->user()?->id,
            'admin_email' => $request->user()?->email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Logs vidés.',
        ]);
    }
}
