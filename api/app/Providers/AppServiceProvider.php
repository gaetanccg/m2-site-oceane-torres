<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Events\BookingRequested;
use App\Events\ContactMessageSent;
use App\Listeners\SendBookingNotifications;
use App\Listeners\SendContactEmails;
use App\Models\User;
use App\Observers\UserObserver;
use App\Services\Supervision\HeartbeatService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HeartbeatService::class);

        Connection::resolverFor('pgsql', fn ($pdo, $database, $prefix, $config) => new PostgresConnection($pdo, $database, $prefix, $config));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS en production (derriere Cloudflare Tunnel)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Password::defaults(function () {
            $rule = Password::min(12);

            return $this->app->environment('testing') ? $rule : $rule->uncompromised();
        });

        // Observer pour synchroniser Users -> Clients
        User::observe(UserObserver::class);

        // Event listeners
        Event::listen(ContactMessageSent::class, SendContactEmails::class);
        Event::listen(BookingRequested::class, SendBookingNotifications::class);

        $this->registerQueueHeartbeat();

        // Rate limiting for image proxy endpoints
        $this->configureRateLimiting();
    }

    private function registerQueueHeartbeat(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        Event::listen(Looping::class, function (): void {
            $this->app->make(HeartbeatService::class)->touchThrottled(HeartbeatService::QUEUE);
        });
    }

    /**
     * Configure rate limiting for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Rate limit for preview and thumbnail images (500 requests/minute for galleries with many photos)
        RateLimiter::for('images', function (Request $request) {
            return Limit::perMinute(500)->by($request->ip());
        });

        // Rate limit for HD downloads (60 requests/minute)
        RateLimiter::for('downloads', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Rate limit pour le webhook SumUp (endpoint public, non signé).
        // SumUp légitime envoie quelques requêtes par checkout (notification +
        // retries espacés). 60/min/IP couvre largement et bloque le spam.
        RateLimiter::for('sumup-webhook', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Codes promo : 6 caractères → devinables, on borne le brute-force d'énumération.
        RateLimiter::for('gift-code', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('health', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(5)->by(Str::lower((string) $request->input('email'))),
            ];
        });

        RateLimiter::for('gallery-code', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('public-form', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
