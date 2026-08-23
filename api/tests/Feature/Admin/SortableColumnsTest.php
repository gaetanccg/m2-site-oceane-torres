<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * L'allowlist de `sorted()` protège de l'injection, mais rien ne garantissait que les noms
 * qu'elle contient existent : `gift_codes` déclarait `expires_at`, colonne absente de la
 * table, et un clic sur cet en-tête aurait produit une 500.
 *
 * Postgres résout les colonnes à l'analyse de la requête : une table vide suffit donc à
 * démasquer un nom fantôme. Ce test est la contrepartie du `sortable: true` déclaré côté
 * front — les deux listes doivent rester alignées.
 */
class SortableColumnsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, list<string>}> */
    public static function endpoints(): array
    {
        return [
            'clients' => ['/api/admin/clients', ['created_at', 'name', 'email', 'source']],
            'commandes' => ['/api/admin/orders', ['created_at', 'total', 'status', 'order_number']],
            'bons cadeaux' => ['/api/admin/gift-cards', ['created_at', 'status', 'expires_at', 'amount', 'code']],
            'codes promo' => ['/api/admin/gift-codes', ['created_at', 'code', 'valid_until', 'is_active']],
            'sessions scolaires' => ['/api/admin/school-sessions', ['created_at', 'title', 'event_date', 'status']],
            'galeries' => ['/api/admin/galleries', ['created_at', 'title', 'event_date']],
            'reservations' => ['/api/admin/reservations', ['created_at', 'date', 'status']],
            'journal rgpd' => ['/api/admin/privacy/audit', ['created_at', 'action']],
        ];
    }

    /**
     * @param  list<string>  $colonnes
     */
    #[DataProvider('endpoints')]
    public function test_chaque_colonne_declaree_triable_existe_vraiment(string $url, array $colonnes): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ($colonnes as $colonne) {
            foreach (['asc', 'desc'] as $sens) {
                $this->actingAs($admin)
                    ->getJson("{$url}?sort_by={$colonne}&sort_dir={$sens}")
                    ->assertOk();
            }
        }
    }

    public function test_une_colonne_inconnue_retombe_sur_le_defaut_sans_erreur(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/admin/clients?sort_by=colonne_qui_n_existe_pas')
            ->assertOk();
    }

    /** Le tri ne doit pas ouvrir de porte : la valeur ne touche jamais le SQL directement. */
    public function test_une_tentative_d_injection_est_ignoree(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson('/api/admin/clients?sort_by=name;DROP+TABLE+clients--')
            ->assertOk();

        $this->assertTrue(Schema::hasTable('clients'));
    }
}
