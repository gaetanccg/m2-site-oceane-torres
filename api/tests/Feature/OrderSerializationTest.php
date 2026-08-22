<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fige la forme serialisee d'une commande. Ces endpoints partageaient une methode
 * statique publique de controleur ; le test doit passer a l'identique avant et apres
 * son remplacement par une API Resource.
 */
class OrderSerializationTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER_KEYS = [
        'id', 'order_number', 'status', 'detailed_status', 'print_status', 'shipped_at',
        'subtotal', 'discount_amount', 'gift_code', 'shipping_fee', 'total', 'currency',
        'paid_at', 'created_at', 'items', 'has_prints', 'customer_email', 'customer_name',
        'shipping',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
    }

    private function orderFor(User $user): Order
    {
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHour(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'photo_id' => Photo::factory()->create()->id,
        ]);

        return $order;
    }

    public function test_public_order_detail_exposes_exactly_the_expected_keys(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/orders/{$this->orderFor($user)->id}")->assertOk();

        $this->assertSame(self::ORDER_KEYS, array_keys($response->json('order')));
    }

    public function test_user_order_list_uses_the_same_shape(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $this->orderFor($user);

        $response = $this->getJson('/api/orders')->assertOk();

        $this->assertSame(self::ORDER_KEYS, array_keys($response->json('orders.0')));
    }

    public function test_admin_order_list_uses_the_same_shape_plus_pagination(): void
    {
        $admin = Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->orderFor($admin);

        $response = $this->getJson('/api/admin/orders')->assertOk();

        $this->assertSame(self::ORDER_KEYS, array_keys($response->json('orders.0')));
        $this->assertSame(
            ['current_page', 'last_page', 'per_page', 'total'],
            array_keys($response->json('pagination'))
        );
    }

    /**
     * Les champs SumUp et le bloc utilisateur ne doivent apparaitre que cote admin :
     * les endpoints publics de commande sont accessibles par jeton, sans compte.
     */
    public function test_admin_order_detail_adds_admin_only_fields(): void
    {
        $admin = Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $order = $this->orderFor($admin);

        $response = $this->getJson("/api/admin/orders/{$order->id}")->assertOk();

        $this->assertSame(
            [...self::ORDER_KEYS, 'user', 'payment', 'sumup_checkout_id', 'sumup_transaction_id'],
            array_keys($response->json('order'))
        );
        $this->assertSame(
            ['id', 'email', 'name'],
            array_keys($response->json('order.user'))
        );
    }

    public function test_public_order_detail_never_exposes_sumup_identifiers(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $order = $this->orderFor($user);
        $order->update(['sumup_checkout_id' => 'chk_secret', 'sumup_transaction_id' => 'txn_secret']);

        $response = $this->getJson("/api/orders/{$order->id}")->assertOk();

        $response->assertJsonMissing(['sumup_checkout_id' => 'chk_secret']);
        $response->assertJsonMissing(['sumup_transaction_id' => 'txn_secret']);
    }
}
