<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `/admin/dashboard` — l'encart « recent » — n'avait aucun test, et repondait 500 en
 * production : son `select()` demandait `customer_name` et `customer_email`, qui sont des
 * ACCESSEURS du modele, pas des colonnes. PostgreSQL rejetait la requete entiere.
 *
 * `/admin/dashboard/stats` etait couvert, lui, et passait. C'est exactement l'angle mort que
 * ce fichier ferme : l'endpoint voisin, de la meme page.
 */
class DashboardRecentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function recent(): array
    {
        return $this->actingAs($this->admin())->getJson('/api/admin/dashboard')->assertOk()->json();
    }

    private function commandes(array $charge): array
    {
        foreach (['recent_orders', 'orders'] as $clef) {
            if (isset($charge[$clef])) {
                return $charge[$clef];
            }
            if (isset($charge['data'][$clef])) {
                return $charge['data'][$clef];
            }
        }

        $this->fail('Aucune liste de commandes récentes dans la réponse : '.json_encode(array_keys($charge)));
    }

    public function test_l_encart_recent_repond(): void
    {
        Order::factory()->count(3)->create();

        $this->assertCount(3, $this->commandes($this->recent()));
    }

    /** Le nom d'un acheteur invite vient de guest_first_name / guest_last_name. */
    public function test_le_nom_d_un_acheteur_invite_est_resolu(): void
    {
        Order::factory()->create([
            'user_id' => null,
            'guest_first_name' => 'Camille',
            'guest_last_name' => 'Durand',
            'guest_email' => 'camille@example.test',
        ]);

        $commande = $this->commandes($this->recent())[0];

        $this->assertSame('Camille Durand', $commande['customer_name']);
        $this->assertSame('camille@example.test', $commande['customer_email']);
    }

    /** Celui d'un client connecte vient de la relation — d'ou le besoin de `user_id`. */
    public function test_le_nom_d_un_client_connecte_vient_de_son_compte(): void
    {
        $client = User::factory()->create([
            'first_name' => 'Sofia',
            'last_name' => 'Martin',
            'email' => 'sofia@example.test',
            'role' => 'client',
        ]);

        Order::factory()->create([
            'user_id' => $client->id,
            'guest_first_name' => null,
            'guest_last_name' => null,
            'guest_email' => null,
        ]);

        $commande = $this->commandes($this->recent())[0];

        $this->assertSame('Sofia Martin', $commande['customer_name']);
        $this->assertSame('sofia@example.test', $commande['customer_email']);
    }

    public function test_l_encart_est_borne_a_cinq_commandes(): void
    {
        Order::factory()->count(8)->create();

        $this->assertCount(5, $this->commandes($this->recent()));
    }
}
