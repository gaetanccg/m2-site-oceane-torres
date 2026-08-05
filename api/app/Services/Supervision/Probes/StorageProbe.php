<?php

namespace App\Services\Supervision\Probes;

use App\Services\Supervision\ProbeResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StorageProbe implements Probe
{
    public function key(): string
    {
        return 'storage';
    }

    public function check(): ProbeResult
    {
        $disk = (string) config('supervision.storage.disk');
        $witness = config('supervision.storage.witness');
        $startedAt = microtime(true);

        try {
            if ($witness) {
                $found = Storage::disk($disk)->fileExists($witness);
            } else {
                // Résultat sans intérêt (le préfixe n'existe pas) : ce qu'on teste,
                // c'est que l'appel S3 aboutisse sans exception.
                Storage::disk($disk)->files((string) config('supervision.storage.probe_prefix'));
                $found = true;
            }
        } catch (Throwable $e) {
            Log::warning('Sonde stockage en échec', [
                'disk' => $disk,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ProbeResult::down(
                'Le stockage objet (MinIO) ne répond pas.',
                ['disk' => $disk, 'error_type' => class_basename($e)],
                ['storage_unreachable'],
            );
        }

        $details = [
            'disk' => $disk,
            'mode' => $witness ? 'objet témoin' : 'listing',
            'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 1),
        ];

        if ($witness && ! $found) {
            return ProbeResult::degraded(
                "L'objet témoin « {$witness} » est introuvable sur le bucket.",
                $details + ['witness' => $witness],
                ['storage_witness_missing'],
            );
        }

        return ProbeResult::ok($details);
    }
}
