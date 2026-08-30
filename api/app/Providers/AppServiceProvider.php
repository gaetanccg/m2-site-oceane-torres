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
use App\Support\ProductionTargetGuard;
use Illuminate\Auth\Notifications\ResetPassword;
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
        // Avant tout le reste : un environnement de developpement ne doit pas pouvoir
        // s'executer contre la production. Place dans register() pour se declencher avant
        // le moindre acces a la base ou au stockage.
        ProductionTargetGuard::assert(
            (string) config('app.env'),
            fn (string $cle) => config($cle),
        );

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

        // Sans cela, la notification construit son lien vers la route web
        // `password.reset`, qui n'existe pas dans une API : le rendu de l'email levait
        // RouteNotFoundException et le parcours entier etait inoperant.
        ResetPassword::createUrlUsing(fn ($user, string $token) => sprintf(
            '%s/reinitialiser-mot-de-passe?token=%s&email=%s',
            rtrim((string) config('app.frontend_url'), '/'),
            $token,
            urlencode($user->getEmailForPasswordReset()),
        ));

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

        // Les 96 routes d'administration n'avaient AUCUN plafond. Le compte de la
        // photographe est le plus interessant du site : il lit les coordonnees de toutes
        // les clientes, exporte les donnees personnelles et supprime des galeries. Sans
        // limite, un jeton vole se moissonne a pleine vitesse.
        //
        // La cle est l'utilisateur, pas l'IP : la photographe travaille depuis chez elle
        // et en deplacement, et une IP partagee ferait payer une cliente pour une autre.
        // Repli sur l'IP quand l'authentification n'a pas encore eu lieu.
        //
        // Le plafond est genereux — l'admin televerse par lots et un ecran declenche
        // plusieurs appels — mais il borne le moissonnage : 600 lectures par minute, la ou
        // une session humaine en fait quelques dizaines.
        RateLimiter::for('admin', function (Request $request) {
            $cle = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(600)->by('admin:'.$cle),
                Limit::perHour(6000)->by('admin-h:'.$cle),
            ];
        });

        // Deux echelles : la limite par minute arrete une rafale, celle par heure arrete
        // un bourrage lent qui reste sous le seuil de la premiere.
        RateLimiter::for('auth', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(5)->by($email),
                Limit::perHour(20)->by('h:'.$request->ip()),
                Limit::perHour(20)->by('h:'.$email),
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
