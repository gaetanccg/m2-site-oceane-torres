<?php

namespace Tests\Feature\Admin;

use App\Models\GiftCard;
use App\Models\GiftCode;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoCodeAndGiftCardStatsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /**
     * Les statistiques venaient de la page affichée : au-delà de vingt lignes, elles
     * étaient fausses. Vingt-cinq bons ici, deux pages : le chiffre doit rester juste.
     */
    public function test_les_statistiques_de_bons_cadeaux_ignorent_la_pagination(): void
    {
        GiftCard::factory()->count(25)->create(['remaining_amount' => 20]);

        $stats = $this->actingAs($this->admin())
            ->getJson('/api/admin/gift-cards/stats')
            ->assertOk()
            ->json();

        $this->assertSame(25, $stats['total']);
        $this->assertSame(25, $stats['active']);
        $this->assertSame(500.0, (float) $stats['outstanding']);
    }

    public function test_les_statistiques_de_codes_promo_ignorent_la_pagination(): void
    {
        GiftCode::factory()->count(22)->create();
        GiftCode::factory()->count(3)->inactive()->create();

        $stats = $this->actingAs($this->admin())
            ->getJson('/api/admin/gift-codes/stats')
            ->assertOk()
            ->json();

        $this->assertSame(25, $stats['total']);
        $this->assertSame(22, $stats['active']);
        $this->assertSame(3, $stats['inactive']);
    }

    /** Les statistiques d'un écran et celles du tableau de bord doivent concorder. */
    public function test_les_deux_sources_donnent_le_meme_chiffre(): void
    {
        GiftCard::factory()->count(4)->create(['remaining_amount' => 15]);
        GiftCode::factory()->count(3)->create();

        $admin = $this->admin();
        $dashboard = $this->actingAs($admin)->getJson('/api/admin/dashboard/stats')->assertOk()->json();

        $this->assertSame(
            $dashboard['gift_cards'],
            $this->actingAs($admin)->getJson('/api/admin/gift-cards/stats')->assertOk()->json()
        );
        $this->assertSame(
            $dashboard['promo_codes'],
            $this->actingAs($admin)->getJson('/api/admin/gift-codes/stats')->assertOk()->json()
        );
    }

    /**
     * L'allowlist de tri nommait `expires_at`, colonne absente de gift_codes : le clic
     * produisait une 500, pas un tri.
     */
    public function test_le_tri_des_codes_promo_accepte_ses_colonnes_declarees(): void
    {
        GiftCode::factory()->create(['code' => 'AAAAAA', 'valid_until' => now()->addWeek()]);
        GiftCode::factory()->create(['code' => 'ZZZZZZ', 'valid_until' => now()->addMonth()]);

        foreach (['created_at', 'code', 'valid_until', 'is_active'] as $colonne) {
            $this->actingAs($this->admin())
                ->getJson("/api/admin/gift-codes?sort_by={$colonne}&sort_dir=asc")
                ->assertOk();
        }

        $premier = $this->actingAs($this->admin())
            ->getJson('/api/admin/gift-codes?sort_by=code&sort_dir=asc')
            ->assertOk()
            ->json('data.0.code');

        $this->assertSame('AAAAAA', $premier);
    }

    /**
     * Le formulaire d'édition relit `min_order_amount` depuis la réponse : absent, il
     * revenait vide et l'enregistrement effaçait silencieusement le montant minimum.
     */
    public function test_le_montant_minimum_est_renvoye_par_l_api(): void
    {
        $code = GiftCode::factory()->create(['min_order_amount' => 50]);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/gift-codes')
            ->assertOk()
            ->assertJsonPath('data.0.min_order_amount', fn ($v) => (float) $v === 50.0);

        $this->actingAs($this->admin())
            ->getJson("/api/admin/gift-codes/{$code->id}")
            ->assertOk()
            ->assertJsonPath('gift_code.min_order_amount', fn ($v) => (float) $v === 50.0);
    }

    public function test_un_code_sans_montant_minimum_renvoie_null(): void
    {
        GiftCode::factory()->create(['min_order_amount' => null]);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/gift-codes')
            ->assertOk()
            ->assertJsonPath('data.0.min_order_amount', null);
    }

    public function test_les_statistiques_exigent_le_role_admin(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)->getJson('/api/admin/gift-cards/stats')->assertForbidden();
        $this->actingAs($client)->getJson('/api/admin/gift-codes/stats')->assertForbidden();
    }

    /** `/gift-codes/stats` ne doit pas être capté par la route de détail. */
    public function test_la_route_de_statistiques_n_est_pas_prise_pour_un_identifiant(): void
    {
        Order::factory()->create();

        $reponse = $this->actingAs($this->admin())->getJson('/api/admin/gift-codes/stats')->assertOk();

        $this->assertArrayHasKey('paid_uses', $reponse->json());
        $this->assertArrayNotHasKey('gift_code', $reponse->json());
    }
}
