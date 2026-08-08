<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHealthCheckToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('supervision.token');

        // Par défaut fermé : sans jeton configuré, l'endpoint refuse tout le monde
        // plutôt que de s'ouvrir silencieusement.
        if (! is_string($expected) || $expected === '') {
            return response()->json([
                'message' => "Le détail de santé n'est pas exposé : HEALTH_CHECK_TOKEN n'est pas configuré.",
            ], 403);
        }

        $provided = $request->header('X-Health-Token') ?? $request->query('token');

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Jeton de supervision invalide.',
            ], 403);
        }

        return $next($request);
    }
}
