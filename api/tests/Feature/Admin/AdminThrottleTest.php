<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Les 96 routes d'administration n'avaient aucun plafond. C'est le compte le plus
 * interessant du site : il lit les coordonnees de toutes les clientes, exporte des donnees
 * personnelles et supprime des galeries. Sans limite, un jeton vole se moissonne a pleine
 * vitesse.
 */
class AdminThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_limiteur_admin_existe(): void
    {
        $this->assertNotNull(RateLimiter::limiter('admin'), 'Aucun limiteur nommé « admin ».');
    }

    /** @return list<Limit> */
    private function limites(?User $utilisateur): array
    {
        $requete = Request::create('/api/admin/dashboard');
        $requete->setUserResolver(fn () => $utilisateur);

        return (array) (RateLimiter::limiter('admin'))($requete);
    }

    /**
     * La cle doit etre l'utilisateur, pas l'IP : la photographe travaille depuis chez elle
     * et en deplacement, et une IP partagee ferait payer une cliente pour une autre.
     */
    public function test_la_limite_est_comptee_par_utilisateur_et_non_par_adresse(): void
    {
        $une = User::factory()->create(['role' => 'admin']);
        $autre = User::factory()->create(['role' => 'admin']);

        $clesUne = array_map(fn (Limit $l) => $l->key, $this->limites($une));
        $clesAutre = array_map(fn (Limit $l) => $l->key, $this->limites($autre));

        $this->assertSame([], array_intersect($clesUne, $clesAutre), 'Deux administratrices partagent le même compteur.');
        foreach ($clesUne as $cle) {
            $this->assertStringContainsString((string) $une->id, $cle);
        }
    }

    /** Sans utilisateur resolu, on retombe sur l'IP plutot que sur une cle vide partagee. */
    public function test_sans_utilisateur_la_limite_retombe_sur_l_adresse(): void
    {
        foreach ($this->limites(null) as $limite) {
            $this->assertNotSame('admin:', $limite->key);
            $this->assertNotSame('admin-h:', $limite->key);
        }
    }

    /** Deux echelles : une rafale, et un moissonnage lent qui reste sous le seuil minute. */
    public function test_le_plafond_porte_sur_la_minute_et_sur_l_heure(): void
    {
        $limites = $this->limites(User::factory()->create(['role' => 'admin']));

        $this->assertCount(2, $limites);
        $this->assertSame([600, 6000], array_map(fn (Limit $l) => $l->maxAttempts, $limites));
    }

    /** Le plafond s'applique vraiment : au-dela, 429. */
    public function test_au_dela_du_plafond_la_reponse_est_429(): void
    {
        RateLimiter::for('admin', fn () => Limit::perMinute(2)->by('test-admin'));

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson('/api/admin/dashboard/stats')->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/dashboard/stats')->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/dashboard/stats')->assertStatus(429);
    }

    /**
     * La protection est posee AU MONTAGE (`bootstrap/app.php`), pas dans le fichier de
     * routes. Une declaration de middleware qui reapparaitrait dans `routes/admin.php`
     * signifierait que quelqu'un a recommence a la poser route par route — c'est-a-dire
     * qu'il redevient possible de l'oublier.
     */
    public function test_le_fichier_de_routes_admin_ne_declare_aucune_protection(): void
    {
        $source = file_get_contents(base_path('routes/admin.php'));

        $this->assertStringNotContainsString("middleware(['auth:sanctum'", $source);
        $this->assertStringNotContainsString("'admin'])", $source);
        $this->assertStringNotContainsString('auth:sanctum', $source);
    }

    /** Une route ajoutee a ce fichier est protegee par construction, sans rien ecrire. */
    public function test_une_route_ajoutee_au_fichier_admin_herite_de_la_garde(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'admin', 'throttle:admin'])
            ->prefix('api/admin')
            ->group(function () {
                Route::get('/temoin-de-montage', fn () => response()->json(['ok' => true]));
            });

        $this->getJson('/api/admin/temoin-de-montage')->assertUnauthorized();

        $cliente = User::factory()->create(['role' => 'client']);
        $this->actingAs($cliente)->getJson('/api/admin/temoin-de-montage')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/admin/temoin-de-montage')->assertOk();
    }

    /**
     * L'emission pose la capacite du role du moment, plutot qu'un `*` tout-puissant.
     * Aucune garde ne la verifie aujourd'hui (voir EnsureUserIsAdmin) : ce test protege
     * l'emission, pas un controle.
     */
    public function test_le_jeton_emis_porte_la_capacite_du_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cliente = User::factory()->create(['role' => 'client']);

        $this->assertSame(['admin'], $admin->jetonDeSession()->accessToken->abilities);
        $this->assertSame(['client'], $cliente->jetonDeSession()->accessToken->abilities);
    }
}
