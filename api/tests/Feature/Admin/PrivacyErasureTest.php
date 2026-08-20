<?php

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\Client;
use App\Models\ContactMessage;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PrivacyAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Effacement / anonymisation RGPD (Lot 3) : anonymise le compte, supprime le
 * CRM/contacts/paniers, CONSERVE le comptable, avec confirmation + audit.
 */
class PrivacyErasureTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubject(string $email): User
    {
        // L'observer crée automatiquement le Client lié.
        $user = User::factory()->create(['email' => $email, 'phone' => '0699887766']);
        Order::factory()->create(['user_id' => $user->id, 'guest_email' => $email]);
        Cart::factory()->create(['user_id' => null, 'guest_email' => $email]);
        ContactMessage::create([
            'name' => 'Jane', 'email' => $email, 'phone' => '0699887766',
            'subject' => 's', 'message' => 'm',
        ]);

        return $user;
    }

    public function test_preview_requires_admin(): void
    {
        $this->getJson('/api/admin/privacy/erasure/preview?type=email&value=a@b.fr')->assertStatus(401);
        $this->actingAsClient();
        $this->getJson('/api/admin/privacy/erasure/preview?type=email&value=a@b.fr')->assertStatus(403);
    }

    public function test_erase_requires_matching_confirmation(): void
    {
        $this->actingAsAdmin();

        // Confirmation manquante.
        $this->postJson('/api/admin/privacy/erasure', ['type' => 'email', 'value' => 'x@y.fr'])
            ->assertStatus(422);

        // Confirmation qui ne correspond pas.
        $this->postJson('/api/admin/privacy/erasure', [
            'type' => 'email', 'value' => 'x@y.fr', 'confirm' => 'autre@y.fr',
        ])->assertStatus(422);
    }

    public function test_preview_classifies_data(): void
    {
        $this->actingAsAdmin();
        $this->makeSubject('jane@example.com');

        $this->getJson('/api/admin/privacy/erasure/preview?type=email&value=jane@example.com')
            ->assertOk()
            ->assertJsonPath('to_anonymize.users', 1)
            ->assertJsonPath('to_delete.clients', 1)
            ->assertJsonPath('to_delete.contact_messages', 1)
            ->assertJsonPath('to_delete.carts', 1)
            ->assertJsonPath('retained_legal.orders', 1);
    }

    public function test_erase_anonymizes_account_deletes_crm_and_retains_accounting(): void
    {
        $this->actingAsAdmin();
        $user = $this->makeSubject('jane@example.com');
        $order = Order::where('user_id', $user->id)->first();
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-KEEP-1',
            'file_path' => null,
        ]);

        $response = $this->postJson('/api/admin/privacy/erasure', [
            'type' => 'email',
            'value' => 'jane@example.com',
            'confirm' => 'jane@example.com',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        // Compte anonymisé (ligne conservée, PII effacé).
        $freshUser = User::find($user->id);
        $this->assertNotNull($freshUser);
        $this->assertNotSame('jane@example.com', $freshUser->email);
        $this->assertStringContainsString('anonymized.local', $freshUser->email);
        $this->assertNull($freshUser->phone);

        // CRM / contacts / paniers supprimés.
        $this->assertSame(0, Client::count());
        $this->assertSame(0, ContactMessage::count());
        $this->assertSame(0, Cart::count());

        // Comptable CONSERVÉ.
        $this->assertSame(1, Order::count());
        $this->assertSame(1, Invoice::count());

        // Audit écrit.
        $this->assertSame(1, PrivacyAuditLog::where('action', 'erasure')->where('subject_value', 'jane@example.com')->count());
    }

    public function test_erase_by_phone_does_not_wipe_unrelated_data(): void
    {
        $this->actingAsAdmin();
        // Une personne ciblée + une autre non concernée.
        $this->makeSubject('target@example.com'); // phone 0699887766
        User::factory()->create(['email' => 'other@example.com', 'phone' => '0700000000']);

        $this->postJson('/api/admin/privacy/erasure', [
            'type' => 'phone', 'value' => '0699887766', 'confirm' => '0699887766',
        ])->assertOk();

        // L'autre compte reste intact.
        $this->assertNotNull(User::where('email', 'other@example.com')->first());
    }

    public function test_purge_command_deletes_only_expired_orders(): void
    {
        $old = Order::factory()->create(['order_number' => 'PURGE-OLD-1']);
        DB::table('orders')->where('id', $old->id)->update(['created_at' => now()->subYears(12)]);
        Invoice::create(['order_id' => $old->id, 'invoice_number' => 'OLD-1', 'file_path' => null]);

        $recent = Order::factory()->create(['order_number' => 'PURGE-RECENT-1']);

        $this->artisan('privacy:purge-expired', ['--years' => 10])->assertExitCode(0);

        $this->assertNull(Order::find($old->id));
        $this->assertNotNull(Order::find($recent->id));
        $this->assertSame(0, Invoice::where('order_id', $old->id)->count());
    }

    public function test_purge_dry_run_deletes_nothing(): void
    {
        $old = Order::factory()->create();
        DB::table('orders')->where('id', $old->id)->update(['created_at' => now()->subYears(12)]);

        $this->artisan('privacy:purge-expired', ['--years' => 10, '--dry-run' => true])->assertExitCode(0);

        $this->assertNotNull(Order::find($old->id));
    }
}
