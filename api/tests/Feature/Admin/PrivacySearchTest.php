<?php

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\PrivacyAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /admin/privacy/search — résolveur RGPD « personne concernée » (Lot 1).
 * Lecture seule : agrège ce qu'on détient par email / téléphone / n° de commande,
 * et trace la consultation dans le journal d'audit (Lot 0).
 */
class PrivacySearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubject(string $email, string $phone): User
    {
        // UserObserver crée automatiquement le Client lié (email + phone copiés).
        $user = User::factory()->create(['email' => $email, 'phone' => $phone]);
        Order::factory()->create(['user_id' => $user->id]);
        Order::factory()->create(['user_id' => null, 'guest_email' => $email]);
        Cart::factory()->create(['user_id' => null, 'guest_email' => $email]);
        ContactMessage::create([
            'name' => 'Jane Doe',
            'email' => $email,
            'phone' => $phone,
            'subject' => 'Question',
            'message' => 'Bonjour',
        ]);

        return $user;
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/admin/privacy/search?type=email&value=a@b.fr')
            ->assertStatus(401);
    }

    public function test_search_forbidden_for_non_admin(): void
    {
        $this->actingAsClient();

        $this->getJson('/api/admin/privacy/search?type=email&value=a@b.fr')
            ->assertStatus(403);
    }

    public function test_search_by_email_aggregates_related_records(): void
    {
        $this->actingAsAdmin();
        $this->makeSubject('jane@example.com', '0600000000');

        $response = $this->getJson('/api/admin/privacy/search?type=email&value=jane@example.com');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('query.type', 'email')
            ->assertJsonPath('summary.accounts', 1)
            ->assertJsonPath('summary.clients', 1)
            ->assertJsonPath('summary.orders', 2)
            ->assertJsonPath('summary.carts', 1)
            ->assertJsonPath('summary.contact_messages', 1);
    }

    public function test_search_is_case_insensitive_on_email(): void
    {
        $this->actingAsAdmin();
        $this->makeSubject('jane@example.com', '0600000000');

        $this->getJson('/api/admin/privacy/search?type=email&value=JANE@example.com')
            ->assertOk()
            ->assertJsonPath('summary.accounts', 1)
            ->assertJsonPath('summary.clients', 1);
    }

    public function test_search_by_phone_finds_records(): void
    {
        $this->actingAsAdmin();
        $this->makeSubject('jane@example.com', '0611223344');

        $this->getJson('/api/admin/privacy/search?type=phone&value=0611223344')
            ->assertOk()
            ->assertJsonPath('summary.accounts', 1)
            ->assertJsonPath('summary.clients', 1)
            ->assertJsonPath('summary.contact_messages', 1);
    }

    public function test_search_by_order_number_expands_to_related_data(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'guest_email' => 'buyer@example.com',
        ]);

        $this->getJson("/api/admin/privacy/search?type=order_number&value={$order->order_number}")
            ->assertOk()
            ->assertJsonPath('summary.orders', 1)
            ->assertJsonPath('summary.accounts', 1);
    }

    public function test_search_writes_an_audit_log_entry(): void
    {
        $admin = $this->actingAsAdmin();

        $this->getJson('/api/admin/privacy/search?type=email&value=trace@example.com')
            ->assertOk();

        $this->assertSame(1, PrivacyAuditLog::where('action', 'search')->count());
        $log = PrivacyAuditLog::first();
        $this->assertSame('email', $log->subject_type);
        $this->assertSame('trace@example.com', $log->subject_value);
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertIsArray($log->affected);
    }

    public function test_search_rejects_invalid_type(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/privacy/search?type=address&value=x')
            ->assertStatus(422);
    }

    public function test_audit_endpoint_lists_entries(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/admin/privacy/search?type=email&value=a@b.fr')->assertOk();

        $this->getJson('/api/admin/privacy/audit')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'logs' => [['id', 'action', 'subject_type', 'subject_value', 'actor', 'created_at']],
                'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }
}
