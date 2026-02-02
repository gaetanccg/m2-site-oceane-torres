<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Observer pour synchroniser Users -> Clients
        User::observe(UserObserver::class);

        // Rate limiting for image proxy endpoints
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Rate limit for preview and thumbnail images (120 requests/minute)
        RateLimiter::for('images', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Rate limit for HD downloads (30 requests/minute)
        RateLimiter::for('downloads', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
