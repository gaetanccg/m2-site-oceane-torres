<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\Gallery;
use App\Models\GiftCard;
use App\Models\GiftCode;
use App\Models\Order;
use App\Models\SchoolSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Ces tests ne vérifient pas que l'endpoint répond : ils vérifient que chaque compteur
 * égale la liste qu'il annonce. C'est le défaut d'origine — des chiffres calculés sur les
 * cent premières lignes reçues, donc faux dès la cent-unième.
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('dashboard_stats');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function stats(): array
    {
        return $this->actingAs($this->admin())->getJson('/api/admin/dashboard/stats')->assertOk()->json();
    }

    public function test_les_compteurs_de_commandes_portent_sur_toute_la_table(): void
    {
        Order::factory()->count(120)->create(['status' => 'pending']);
        Order::factory()->count(30)->paid()->create();

        $stats = $this->stats();

        $this->assertSame(150, $stats['orders']['total']);
        $this->assertSame(30, $stats['orders']['paid']);
        $this->assertSame(120, $stats['orders']['pending']);
    }

    public function test_le_chiffre_d_affaires_se_compte_sur_la_date_d_encaissement(): void
    {
        // Engagée le mois dernier, encaissée ce mois-ci : elle appartient à ce mois-ci.
        Order::factory()->paid()->create([
            'total' => 200,
            'created_at' => now()->subMonth()->startOfMonth()->addDay(),
            'paid_at' => now()->startOfMonth()->addDay(),
        ]);
        Order::factory()->paid()->create([
            'total' => 50,
            'created_at' => now()->subMonth()->startOfMonth()->addDay(),
            'paid_at' => now()->subMonth()->startOfMonth()->addDays(2),
        ]);
        Order::factory()->create(['total' => 999, 'status' => 'pending']);

        $stats = $this->stats();

        $this->assertSame(200.0, (float) $stats['revenue']['this_month']);
        $this->assertSame(50.0, (float) $stats['revenue']['last_month']);
        $this->assertSame(250.0, (float) $stats['revenue']['total']);
    }

    public function test_l_encours_de_bons_cadeaux_somme_les_soldes_encore_utilisables(): void
    {
        GiftCard::factory()->create(['remaining_amount' => 100]);
        GiftCard::factory()->partiallyUsed(40)->create();
        GiftCard::factory()->neverExpires()->create(['remaining_amount' => 10]);
        GiftCard::factory()->used()->create();
        GiftCard::factory()->expired()->create(['remaining_amount' => 500]);
        GiftCard::factory()->cancelled()->create(['remaining_amount' => 700]);

        $stats = $this->stats()['gift_cards'];

        $this->assertSame(150.0, (float) $stats['outstanding']);
        $this->assertSame(3, $stats['active']);
        $this->assertSame(1, $stats['used']);
        $this->assertSame(1, $stats['expired']);
        $this->assertSame(1, $stats['cancelled']);
        $this->assertSame(6, $stats['total']);
    }

    /** Un bon périmé n'engage plus rien : il ne doit pas gonfler l'encours. */
    public function test_un_bon_perime_reste_actif_en_base_mais_hors_encours(): void
    {
        GiftCard::factory()->expired()->create(['remaining_amount' => 250]);

        $stats = $this->stats()['gift_cards'];

        $this->assertSame(0.0, (float) $stats['outstanding']);
        $this->assertSame(0, $stats['active']);
        $this->assertSame(1, $stats['expired']);
    }

    public function test_les_etats_des_codes_promo_suivent_la_meme_regle_que_la_liste(): void
    {
        GiftCode::factory()->create();
        GiftCode::factory()->inactive()->create();
        GiftCode::factory()->expired()->create();
        GiftCode::factory()->notYetValid()->create();

        $epuise = GiftCode::factory()->maxUses(1)->create();
        Order::factory()->paid()->create(['gift_code_id' => $epuise->id]);

        $quota = GiftCode::factory()->maxUses(5)->create();
        Order::factory()->count(2)->paid()->create(['gift_code_id' => $quota->id]);
        Order::factory()->create(['gift_code_id' => $quota->id, 'status' => 'pending']);

        $stats = $this->stats()['promo_codes'];

        $this->assertSame(6, $stats['total']);
        $this->assertSame(2, $stats['active']);
        $this->assertSame(1, $stats['inactive']);
        $this->assertSame(1, $stats['expired']);
        $this->assertSame(1, $stats['scheduled']);
        $this->assertSame(1, $stats['used_up']);
        // Seules les commandes payées consomment le quota : la pending ne compte pas.
        $this->assertSame(3, $stats['paid_uses']);
    }

    public function test_les_etats_des_codes_promo_se_repartissent_sans_recouvrement(): void
    {
        GiftCode::factory()->count(3)->create();
        GiftCode::factory()->count(2)->inactive()->create();
        GiftCode::factory()->expired()->create();

        $stats = $this->stats()['promo_codes'];

        $this->assertSame(
            $stats['total'],
            $stats['active'] + $stats['inactive'] + $stats['scheduled'] + $stats['expired'] + $stats['used_up']
        );
    }

    public function test_le_compteur_de_galeries_reprend_le_perimetre_des_listes(): void
    {
        Gallery::factory()->count(4)->create(['type' => 'private']);
        Gallery::factory()->count(2)->create(['type' => 'event', 'parent_id' => null]);
        $parent = Gallery::factory()->create(['type' => 'event', 'parent_id' => null]);
        Gallery::factory()->create(['type' => 'event', 'parent_id' => $parent->id]);

        $stats = $this->stats();

        $this->assertSame(4, $stats['galleries']['clients']);
        // Les sous-galeries ne sont pas listées, elles ne sont donc pas comptées.
        $this->assertSame(3, $stats['galleries']['events']);
    }

    public function test_les_galeries_de_session_scolaire_sont_hors_du_compteur_clients(): void
    {
        Gallery::factory()->count(2)->create(['type' => 'private']);
        $session = SchoolSession::factory()->create();
        Gallery::factory()->create(['type' => 'private', 'school_session_id' => $session->id]);

        $this->assertSame(2, $this->stats()['galleries']['clients']);
    }

    /**
     * L'invariant qui compte vraiment : le chiffre annoncé doit être celui que montre la
     * liste vers laquelle il renvoie. Un compteur juste dans l'absolu mais différent de sa
     * liste reste un compteur menteur.
     */
    public function test_chaque_compteur_egale_le_total_de_la_liste_qu_il_annonce(): void
    {
        Client::factory()->count(3)->create();
        User::factory()->count(5)->create(['role' => 'client']);
        Order::factory()->count(4)->create();
        GiftCard::factory()->count(3)->create();
        GiftCode::factory()->count(2)->create();
        Gallery::factory()->count(2)->create(['type' => 'private']);

        $admin = $this->admin();
        $stats = $this->actingAs($admin)->getJson('/api/admin/dashboard/stats')->assertOk()->json();

        $totalDeLaListe = function (string $url) use ($admin): int {
            return (int) $this->actingAs($admin)->getJson($url)->assertOk()->json('total');
        };

        $this->assertSame($totalDeLaListe('/api/admin/clients'), $stats['clients']['total']);
        $this->assertSame(
            (int) $this->actingAs($admin)->getJson('/api/admin/orders?per_page=1')->assertOk()->json('pagination.total'),
            $stats['orders']['total']
        );
        $this->assertSame($totalDeLaListe('/api/admin/gift-cards'), $stats['gift_cards']['total']);
        $this->assertSame($totalDeLaListe('/api/admin/gift-codes'), $stats['promo_codes']['total']);
        $this->assertSame($totalDeLaListe('/api/admin/galleries'), $stats['galleries']['clients']);
    }

    /** UserObserver crée une fiche CRM par inscription : elles doivent être comptées. */
    public function test_les_comptes_crees_alimentent_le_compteur_clients(): void
    {
        Client::factory()->count(3)->create();
        User::factory()->count(5)->create(['role' => 'client']);

        $this->assertSame(8, $this->stats()['clients']['total']);
    }

    public function test_les_chiffres_ne_sont_pas_accessibles_sans_role_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->getJson('/api/admin/dashboard/stats')
            ->assertForbidden();
    }
}
