<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureHealthCheckToken;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    // Les listeners sont câblés explicitement dans AppServiceProvider::boot().
    // Sans discover: false, Laravel scanne AUSSI app/Listeners et enregistre
    // une seconde fois chaque listener -> tous les mails partaient en double.
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->use([
            HandleCors::class,
            SecurityHeaders::class,
        ]);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'health.token' => EnsureHealthCheckToken::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Render any uncaught exception on /api/* as a clean JSON response.
        // Business exceptions surface their message verbatim; native Laravel
        // exceptions (validation, auth, 404, HttpException) keep their default
        // rendering so the client still gets specific feedback. Anything else
        // is treated as a technical failure: logged server-side, replaced by
        // a generic message so SQL or stacktraces never reach the client.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof BusinessException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], $e->getHttpStatus());
            }

            // 404: return a clean message (Laravel's default for ModelNotFoundException
            // leaks the model class name, e.g. "No query results for model [App\Models\Order]")
            if (
                $e instanceof ModelNotFoundException
                || $e instanceof NotFoundHttpException
            ) {
                return response()->json([
                    'success' => false,
                    'message' => "La ressource demandée n'existe pas ou a été supprimée.",
                ], 404);
            }

            $passthrough = [
                ValidationException::class,
                AuthenticationException::class,
                AuthorizationException::class,
                HttpExceptionInterface::class,
            ];
            foreach ($passthrough as $class) {
                if ($e instanceof $class) {
                    return null;
                }
            }

            Log::error('Unhandled API exception', [
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
