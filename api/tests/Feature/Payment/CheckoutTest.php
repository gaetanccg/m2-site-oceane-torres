<?php

namespace Tests\Feature\Payment;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Gallery;
use App\Models\GiftCode;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesShopData;
use Tests\TestCase;

/**
 * POST /checkout — création d'une commande depuis le panier (OrderController@createFromCart).
 */
class CheckoutTest extends TestCase
{
    use CreatesShopData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
        Mail::fake();
    }

    private function checkout(string $sessionId, array $body = []): TestResponse
    {
        return $this->withHeader('X-Cart-Session', $sessionId)->postJson('/api/checkout', array_merge([
            'guest_email' => 'client@example.com',
            'guest_first_name' => 'Jean',
            'guest_last_name' => 'Dupont',
            'cgv_accepted' => true,
        ], $body));
    }

    public function test_empty_cart_returns_400(): void
    {
        // Panier existant mais vide.
        Cart::factory()->create(['session_id' => 'empty-session']);

        $response = $this->checkout('empty-session');

        $response->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Le panier est vide.']);
    }

    public function test_paid_checkout_creates_pending_order_and_initiates_sumup_payment(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_new_123', 'status' => 'PENDING']);
        $this->makeGuestCartWithDigitalItems(count: 2, sessionId: 'paid-session');

        $response = $this->checkout('paid-session');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment.checkout_id', 'chk_new_123')
            ->assertJsonPath('order.total', 26);

        $order = Order::first();
        $this->assertSame('pending', $order->status);
        $this->assertSame('chk_new_123', $order->sumup_checkout_id);

        // Une ligne de paiement SumUp pending a été créée.
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'sumup',
            'status' => 'pending',
        ]);
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_free_order_when_gift_code_covers_full_cart_skips_sumup(): void
    {
        Http::fake(); // Toute requête SumUp ferait échouer l'assertNothingSent.

        $cart = $this->makeGuestCartWithDigitalItems(count: 1, sessionId: 'free-session');
        $this->attachGiftCode($cart, GiftCode::factory()->fixed(100)->create());

        $response = $this->checkout('free-session');

        $response->assertOk()
            ->assertJsonPath('payment.free', true)
            ->assertJsonPath('order.total', 0);

        // Aucun checkout SumUp n'a été créé, la commande reste pending.
        Http::assertNothingSent();
        $this->assertSame('pending', Order::first()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_print_item_without_shipping_address_is_rejected(): void
    {
        $this->makeGuestCartWithPrintItem('print-session');

        $response = $this->checkout('print-session'); // pas d'adresse fournie

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_address_line1', 'shipping_postal_code', 'shipping_city']);
    }

    public function test_cgv_must_be_accepted(): void
    {
        $this->makeGuestCartWithDigitalItems(sessionId: 'cgv-session');

        $response = $this->checkout('cgv-session', ['cgv_accepted' => false]);

        $response->assertStatus(422)->assertJsonValidationErrors(['cgv_accepted']);
    }

    public function test_guest_email_is_required_for_guest_checkout(): void
    {
        $this->makeGuestCartWithDigitalItems(sessionId: 'noemail-session');

        $response = $this->checkout('noemail-session', ['guest_email' => '']);

        $response->assertStatus(422)->assertJsonValidationErrors(['guest_email']);
    }

    public function test_creating_new_checkout_expires_previous_pending_orders(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_second', 'status' => 'PENDING']);
        $cart = $this->makeGuestCartWithDigitalItems(sessionId: 'reuse-session');

        // Une première commande pending rattachée au même panier/session.
        $firstOrder = Order::factory()->pending()->create(['cart_id' => $cart->id]);

        $this->checkout('reuse-session')->assertOk();

        $this->assertSame('expired', $firstOrder->fresh()->status);
    }

    public function test_shipping_fee_is_the_max_across_galleries_in_a_mixed_cart(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_ship', 'status' => 'PENDING']);

        // Deux galeries avec des frais de port différents, un tirage papier dans chacune.
        $cart = Cart::factory()->create(['session_id' => 'ship-session']);
        foreach ([3.00, 7.00] as $fee) {
            $gallery = Gallery::factory()->withShippingFee($fee)->create();
            $photo = Photo::factory()->create(['gallery_id' => $gallery->id]);
            CartItem::factory()->print('print_10x15')->create([
                'cart_id' => $cart->id,
                'photo_id' => $photo->id,
            ]);
        }

        $response = $this->checkout('ship-session', [
            'shipping_phone' => '0612345678',
            'shipping_address_line1' => '1 rue de la Photo',
            'shipping_postal_code' => '75001',
            'shipping_city' => 'Paris',
        ]);

        $response->assertOk();
        // Frais = max(3, 7) pour ne pas sous-facturer un panier multi-galeries.
        $this->assertSame('7.00', Order::first()->shipping_fee);
        $response->assertJsonPath('order.shipping_fee', 7);
    }

    /**
     * Le champ demande `autocomplete="tel-national"` au navigateur, puis refusait ce qu'il
     * rendait : la validation exigeait dix chiffres colles, sans normalisation. Un numero
     * avec des espaces — la forme que le placeholder de l'inscription affiche lui-meme —
     * etait rejete. L'autoremplissage menait donc a un formulaire refuse.
     */
    public function test_un_numero_avec_espaces_est_accepte_et_range_sous_forme_canonique(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_tel', 'status' => 'PENDING']);

        $cart = Cart::factory()->create(['session_id' => 'tel-session']);
        $gallery = Gallery::factory()->withShippingFee(3.00)->create();
        $photo = Photo::factory()->create(['gallery_id' => $gallery->id]);
        CartItem::factory()->print('print_10x15')->create([
            'cart_id' => $cart->id,
            'photo_id' => $photo->id,
        ]);

        $this->checkout('tel-session', [
            'shipping_phone' => '06 12 34 56 78',
            'shipping_address_line1' => '1 rue de la Photo',
            'shipping_postal_code' => '75001',
            'shipping_city' => 'Paris',
        ])->assertOk();

        $this->assertSame('0612345678', Order::first()->shipping_phone);
    }

    /** La normalisation ne doit pas faire passer un numero qui n'en est pas un. */
    public function test_un_numero_trop_court_reste_refuse(): void
    {
        $cart = Cart::factory()->create(['session_id' => 'tel-court']);
        $gallery = Gallery::factory()->withShippingFee(3.00)->create();
        $photo = Photo::factory()->create(['gallery_id' => $gallery->id]);
        CartItem::factory()->print('print_10x15')->create([
            'cart_id' => $cart->id,
            'photo_id' => $photo->id,
        ]);

        $this->checkout('tel-court', [
            'shipping_phone' => '06 12 34 56',
            'shipping_address_line1' => '1 rue de la Photo',
            'shipping_postal_code' => '75001',
            'shipping_city' => 'Paris',
        ])->assertStatus(422)->assertJsonValidationErrors('shipping_phone');
    }

    /**
     * Un prix en HAUSSE au moment du paiement arrete le checkout : la cliente serait
     * debitee d'un montant qu'elle n'a pas vu. getCartSummary resynchronise les prix a
     * chaque consultation du panier, donc un ecart ici signifie que le tarif a change
     * APRES sa derniere vue.
     */
    public function test_a_price_increase_at_checkout_stops_the_payment(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_price', 'status' => 'PENDING']);

        $cart = Cart::factory()->create(['session_id' => 'stale-session']);
        $photo = Photo::factory()->create();
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'photo_id' => $photo->id,
            'product_type' => 'digital',
            'price' => 5.00,
        ]);

        $this->checkout('stale-session')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Le tarif de votre panier a changé. Vérifiez le nouveau total avant de payer.');

        $this->assertSame(0, Order::count());
    }

    /** Une baisse passe : la cliente paie moins que ce qu'elle a vu. */
    public function test_a_price_decrease_at_checkout_proceeds(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_baisse', 'status' => 'PENDING']);

        $cart = Cart::factory()->create(['session_id' => 'baisse-session']);
        $photo = Photo::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'photo_id' => $photo->id,
            'product_type' => 'digital',
            'price' => 99.00,
        ]);

        $this->checkout('baisse-session')->assertOk()->assertJsonPath('order.total', 13);
        $this->assertSame('13.00', $item->fresh()->price);
    }

    /** Le parcours normal ne doit subir aucune friction : consulter puis payer. */
    public function test_the_normal_flow_is_not_disturbed(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_normal', 'status' => 'PENDING']);

        $cart = Cart::factory()->create(['session_id' => 'normale']);
        $photo = Photo::factory()->create();
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'photo_id' => $photo->id,
            'product_type' => 'digital',
            'price' => 5.00,
        ]);

        // Consultation du panier : les prix sont resynchronises, comme pour une visiteuse.
        $this->withHeader('X-Cart-Session', 'normale')->getJson('/api/cart')->assertOk();

        $this->checkout('normale')->assertOk();
    }

    /**
     * Une photo devenue indisponible arrete le paiement — et le panier est REELLEMENT mis
     * a jour. Sans ça, le message mentirait et chaque nouvelle tentative rejouerait la
     * meme erreur : cul-de-sac de paiement permanent.
     */
    public function test_an_unavailable_photo_is_removed_and_stops_the_payment(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_indispo', 'status' => 'PENDING']);

        $cart = Cart::factory()->create(['session_id' => 'indispo-session']);
        $indisponible = Photo::factory()->create(['is_purchasable' => false]);
        $disponible = Photo::factory()->create();

        $ligneRetiree = CartItem::factory()->create([
            'cart_id' => $cart->id, 'photo_id' => $indisponible->id,
            'product_type' => 'digital', 'price' => 13.00,
        ]);
        CartItem::factory()->create([
            'cart_id' => $cart->id, 'photo_id' => $disponible->id,
            'product_type' => 'digital', 'price' => 13.00,
        ]);

        $this->checkout('indispo-session')->assertStatus(409);

        $this->assertSame(0, Order::count());
        $this->assertModelMissing($ligneRetiree);
    }

    /** La seconde tentative doit aboutir : sinon le paiement est definitivement bloque. */
    public function test_a_retry_after_removal_succeeds(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_retry', 'status' => 'PENDING']);

        $cart = Cart::factory()->create(['session_id' => 'retry-session']);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'photo_id' => Photo::factory()->create(['is_purchasable' => false])->id,
            'product_type' => 'digital', 'price' => 13.00,
        ]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'photo_id' => Photo::factory()->create()->id,
            'product_type' => 'digital', 'price' => 13.00,
        ]);

        $this->checkout('retry-session')->assertStatus(409);
        $this->checkout('retry-session')->assertOk();

        $this->assertSame(1, Order::count());
    }

    /** Un panier qui ne contient QUE des photos indisponibles finit vide. */
    public function test_a_cart_of_only_unavailable_photos_is_rejected(): void
    {
        $this->fakeSumUp(createCheckout: ['id' => 'chk_vide', 'status' => 'PENDING']);

        $cart = Cart::factory()->create(['session_id' => 'vide-session']);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'photo_id' => Photo::factory()->create(['is_purchasable' => false])->id,
            'product_type' => 'digital', 'price' => 13.00,
        ]);

        $this->checkout('vide-session')->assertStatus(409);
        $this->assertSame(0, CartItem::where('cart_id', $cart->id)->count());

        $this->checkout('vide-session')->assertStatus(400);
    }
}
