<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /orders/{order} — règles d'accès au détail d'une commande (guest + authed).
 */
class OrderAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
    }

    public function test_owner_can_access_their_order(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHour(),
        ]);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_guest_can_access_with_matching_email(): void
    {
        $order = Order::factory()->create([
            'guest_email' => 'guest@example.com',
            'created_at' => now()->subHour(),
        ]);

        $this->getJson("/api/orders/{$order->id}?email=guest@example.com")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_valid_download_token_grants_access(): void
    {
        $order = Order::factory()->paid('tok_valid_123')->create([
            'created_at' => now()->subHour(),
        ]);

        $this->getJson("/api/orders/{$order->id}?token=tok_valid_123")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_a_recent_order_is_no_longer_open_without_credentials(): void
    {
        // Regle supprimee en 2.5 : « creee depuis moins de 30 minutes » accordait la
        // lecture a quiconque connaissait l'identifiant, sans rien prouver.
        $order = Order::factory()->create(['created_at' => now()->subMinutes(5)]);

        $this->getJson("/api/orders/{$order->id}")->assertStatus(403);
    }

    public function test_unauthorized_after_30_minutes_returns_403(): void
    {
        $order = Order::factory()->create([
            'guest_email' => 'guest@example.com',
            'created_at' => now()->subHour(),
        ]);

        $this->getJson("/api/orders/{$order->id}")
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_unknown_order_returns_404(): void
    {
        $this->getJson('/api/orders/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }
}
