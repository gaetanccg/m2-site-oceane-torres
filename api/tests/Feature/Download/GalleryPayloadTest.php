<?php

namespace Tests\Feature\Download;

use App\Models\Gallery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Le poids de la reponse d'une galerie n'est pas un detail : c'est ce que telecharge la
 * cliente sur son telephone. `display_url` etait l'exact doublon de `preview_url` — deux
 * URL signees de 426 octets par photo, soit 30 % de la reponse pour rien.
 */
class GalleryPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function galerie(int $photos = 200): Gallery
    {
        $gallery = Gallery::factory()->create(['type' => 'public', 'share_code' => 'ABC123']);
        Photo::factory()->count($photos)->create(['gallery_id' => $gallery->id, 'is_processed' => true]);

        return $gallery;
    }

    public function test_la_reponse_ne_porte_plus_l_url_dupliquee(): void
    {
        $this->galerie(3);

        $photo = $this->getJson('/api/galleries/code/ABC123')
            ->assertOk()
            ->json('gallery.photos.0');

        $this->assertArrayNotHasKey('display_url', $photo);
        $this->assertArrayHasKey('preview_url', $photo);
        $this->assertArrayHasKey('thumbnail_url', $photo);
    }

    /**
     * Une borne, pas une mesure exacte : elle doit tomber si quelqu'un rajoute une URL
     * signee par photo, ce qui etait precisement le defaut.
     */
    public function test_le_poids_par_photo_reste_sous_un_kilo_octet(): void
    {
        $this->galerie(200);

        $contenu = $this->getJson('/api/galleries/code/ABC123')->assertOk()->getContent();
        $parPhoto = strlen($contenu) / 200;

        $this->assertLessThan(
            1100,
            $parPhoto,
            sprintf('La reponse pese %.0f octets par photo (1470 avant #41).', $parPhoto)
        );
    }

    public function test_la_commande_ne_porte_plus_l_url_dupliquee(): void
    {
        Storage::fake('minio');

        $user = Sanctum::actingAs(User::factory()->create());
        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'photo_id' => Photo::factory()->create()->id,
        ]);

        $article = $this->getJson("/api/orders/{$order->id}")->assertOk()->json('order.items.0');

        $this->assertArrayNotHasKey('display_url', $article);
        $this->assertArrayHasKey('preview_url', $article);
    }

    public function test_le_panier_expose_une_url_pleine_taille_nommee_preview(): void
    {
        Storage::fake('minio');

        $gallery = $this->galerie(1);
        $photo = $gallery->photos()->first();

        // Le panier invite est identifie par X-Cart-Session : sans l'en-tete, chaque
        // requete cree un panier neuf.
        $session = $this->postJson('/api/cart/add', ['photo_id' => $photo->id, 'product_type' => 'digital'])
            ->assertSuccessful()
            ->json('session_id');

        $photoDuPanier = $this->withHeader('X-Cart-Session', $session)
            ->getJson('/api/cart')
            ->assertOk()
            ->json('cart.items.0.photo');

        $this->assertNotNull($photoDuPanier);
        $this->assertArrayNotHasKey('display_url', $photoDuPanier);
        $this->assertArrayHasKey('preview_url', $photoDuPanier);
    }
}
