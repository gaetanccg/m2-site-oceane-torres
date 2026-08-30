<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Un controle qui cherche « les routes /admin sans le middleware admin » ne trouve rien : il
 * ne voit pas la route qui DEVRAIT etre admin et n'est ni prefixee ni protegee. C'est ainsi
 * que `POST /api/gift-cards` est reste ouvert a n'importe quel compte client, avec un montant
 * plafonne a 500 EUR et un `authorize()` renvoyant true.
 *
 * Ce test raisonne donc sur le CONTROLEUR, pas sur l'URL.
 */
class AdminRouteCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `gatherMiddleware()` rend ce qui est DECLARE : l'alias tel qu'ecrit dans le fichier de
     * routes, pas la classe resolue par le noyau. On accepte donc les deux ecritures.
     *
     * @var list<string>
     */
    private const MIDDLEWARE_ADMIN = ['admin', 'App\\Http\\Middleware\\EnsureUserIsAdmin'];

    /** @param  list<string>  $middlewares */
    private function porteLaGardeAdmin(array $middlewares): bool
    {
        return array_intersect(self::MIDDLEWARE_ADMIN, $middlewares) !== [];
    }

    /**
     * Les routes servies par un controleur `Api\Admin\*` que la photographe n'est pas seule a
     * pouvoir appeler. `NotificationController` est la seule exception legitime : ses methodes
     * filtrent sur l'utilisateur courant, il sert donc aussi les comptes clients.
     *
     * @var list<string>
     */
    private const EXCEPTIONS = ['App\\Http\\Controllers\\Api\\Admin\\NotificationController'];

    public function test_aucun_controleur_admin_n_est_atteignable_sans_le_middleware(): void
    {
        $nues = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction('controller');

            if (! is_string($action) || ! str_contains($action, '\\Api\\Admin\\')) {
                continue;
            }

            $classe = explode('@', $action)[0];

            if (in_array($classe, self::EXCEPTIONS, true)) {
                continue;
            }

            if (! $this->porteLaGardeAdmin($route->gatherMiddleware())) {
                $nues[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $nues, "Routes admin sans garde :\n  ".implode("\n  ", $nues));
    }

    public function test_toute_route_prefixee_admin_porte_le_middleware(): void
    {
        $nues = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/admin/')) {
                continue;
            }

            if (! $this->porteLaGardeAdmin($route->gatherMiddleware())) {
                $nues[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $nues);
    }

    public function test_un_compte_client_ne_peut_plus_emettre_de_bon_cadeau(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)
            ->postJson('/api/admin/gift-cards', [
                'amount' => 500,
                'recipient_name' => 'Moi',
                'recipient_email' => 'moi@example.test',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('gift_cards', 0);
    }

    public function test_la_route_publique_d_emission_n_existe_plus(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)
            ->postJson('/api/gift-cards', ['amount' => 500])
            ->assertNotFound();
    }
}
