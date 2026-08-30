<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les cartouches de l'ecran Commandes etaient calcules cote front sur la PAGE COURANTE :
 * « Payees », « A expedier » et « Revenus » decrivaient vingt lignes et changeaient quand on
 * tournait la page. C'est le defaut #63/#64, corrige sur le tableau de bord en 3.6 et reste
 * intact sur cet ecran-la.
 *
 * Ces tests ne verifient pas que l'endpoint repond : ils verifient que chaque chiffre egale
 * la liste qu'il annonce, au-dela d'une page.
 */
class OrderStatsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function stats(): array
    {
        return $this->actingAs($this->admin())->getJson('/api/admin/orders/stats')->assertOk()->json();
    }

    private function commande(string $statut, float $total, ?string $typeArticle = null, ?string $expedition = null): Order
    {
        $commande = Order::factory()->create([
            'status' => $statut,
            'total' => $total,
            'print_status' => $expedition,
        ]);

        if ($typeArticle !== null) {
            OrderItem::factory()->create(['order_id' => $commande->id, 'product_type' => $typeArticle]);
        }

        return $commande;
    }

    /** Au-dela d'une page de vingt : c'est tout l'enjeu. */
    public function test_les_compteurs_portent_sur_toute_la_table_et_non_sur_une_page(): void
    {
        Order::factory()->count(25)->create(['status' => 'paid', 'total' => 10]);
        Order::factory()->count(5)->create(['status' => 'pending', 'total' => 99]);

        $stats = $this->stats();

        $this->assertSame(30, $stats['total']);
        $this->assertSame(25, $stats['paid']);
        $this->assertSame(5, $stats['pending']);
        // Un flottant entier se serialise en JSON sans decimales : comparer apres cast.
        $this->assertSame(250.0, (float) $stats['revenue']);
    }

    /** Le chiffre d'affaires ne compte que l'encaisse. */
    public function test_le_revenu_ignore_les_commandes_non_payees(): void
    {
        $this->commande('paid', 40);
        $this->commande('pending', 1000);
        $this->commande('failed', 1000);

        $this->assertSame(40.0, (float) $this->stats()['revenue']);
    }

    /** « A expedier » : payee, au moins un tirage, pas encore expediee. */
    public function test_a_expedier_ne_compte_que_les_tirages_non_expedies(): void
    {
        $aExpedier = $this->commande('paid', 20, 'print_10x15', null);
        $this->commande('paid', 20, 'print_15x20', 'shipped');
        $this->commande('paid', 20, 'digital', null);
        $this->commande('pending', 20, 'print_10x15', null);

        $this->assertSame(1, $this->stats()['to_ship']);
        $this->assertSame('to_ship', $aExpedier->fresh()->detailed_status);
    }

    /**
     * Le controle qui compte vraiment : l'agregat SQL doit rendre exactement ce que
     * l'accesseur du modele calcule ligne a ligne. Deux chemins, un seul resultat.
     */
    public function test_l_agregat_sql_egale_le_calcul_du_modele(): void
    {
        $this->commande('paid', 12, 'print_10x15', null);
        $this->commande('paid', 12, 'print_scolaire', null);
        $this->commande('paid', 12, 'print_15x20', 'shipped');
        $this->commande('paid', 12, 'digital', null);
        $this->commande('pending', 12, 'print_10x15', null);
        $this->commande('failed', 12, 'print_10x15', null);

        $parLeModele = Order::notEntirelySchool()->with('items')->get()
            ->filter(fn (Order $o) => $o->detailed_status === 'to_ship')
            ->count();

        $this->assertSame($parLeModele, $this->stats()['to_ship']);
    }

    /** Le perimetre du compteur est celui de la liste : sinon il compte autre chose. */
    public function test_le_perimetre_est_celui_de_la_liste(): void
    {
        Order::factory()->count(3)->create(['status' => 'paid', 'total' => 10]);

        $listes = $this->actingAs($this->admin())->getJson('/api/admin/orders?per_page=100')->assertOk()->json();
        $lignes = $listes['data'] ?? $listes['orders'] ?? [];

        $this->assertSame(count($lignes), $this->stats()['total']);
    }
}
