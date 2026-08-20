<?php

namespace App\Providers;

use App\Events\BookingRequested;
use App\Events\ContactMessageSent;
use App\Listeners\SendBookingNotifications;
use App\Listeners\SendContactEmails;
use App\Models\User;
use App\Observers\UserObserver;
use App\Services\Supervision\HeartbeatService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HeartbeatService::class);
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
    }
}
