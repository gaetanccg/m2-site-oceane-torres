<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->use([
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render any uncaught exception on /api/* as a clean JSON response.
        // Business exceptions surface their message verbatim; native Laravel
        // exceptions (validation, auth, 404, HttpException) keep their default
        // rendering so the client still gets specific feedback. Anything else
        // is treated as a technical failure: logged server-side, replaced by
        // a generic message so SQL or stacktraces never reach the client.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof \App\Exceptions\BusinessException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], $e->getHttpStatus());
            }

            // 404: return a clean message (Laravel's default for ModelNotFoundException
            // leaks the model class name, e.g. "No query results for model [App\Models\Order]")
            if (
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
            ) {
                return response()->json([
                    'success' => false,
                    'message' => "La ressource demandée n'existe pas ou a été supprimée.",
                ], 404);
            }

            $passthrough = [
                \Illuminate\Validation\ValidationException::class,
                \Illuminate\Auth\AuthenticationException::class,
                \Illuminate\Auth\Access\AuthorizationException::class,
                \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class,
            ];
            foreach ($passthrough as $class) {
                if ($e instanceof $class) {
                    return null;
                }
            }

            \Illuminate\Support\Facades\Log::error('Unhandled API exception', [
                'path' => $request->path(),
                'method' => $request->method(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Une erreur s'est produite, veuillez réessayer plus tard. Si l'erreur persiste, n'hésitez pas à me contacter.",
            ], 500);
        });
    })->create();
