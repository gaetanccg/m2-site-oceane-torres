<?php

namespace Tests\Feature\Download;

use App\Models\Gallery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GET /images/download/{photo} — téléchargement HD verrouillé par l'achat.
 * Requiert token de commande + order payée + photo présente dans la commande.
 */
class ImageProxyDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Filesystem $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = Storage::fake('minio');
    }

    /** @return array{0: Photo, 1: Order} */
    private function paidOrderWithPhoto(string $token = 'tok_dl'): array
    {
        $gallery = Gallery::factory()->create();
        $photo = Photo::factory()->create(['gallery_id' => $gallery->id]);
        $this->disk->put($photo->resolved_storage_path, 'hd-bytes');

        $order = Order::factory()->paid($token)->create();
        OrderItem::factory()->forPhoto($photo)->create(['order_id' => $order->id]);

        return [$photo, $order];
    }

    public function test_missing_token_or_order_is_forbidden(): void
    {
        [$photo] = $this->paidOrderWithPhoto();

        $this->get("/api/images/download/{$photo->id}")->assertStatus(403);
    }

    public function test_unpaid_order_is_forbidden(): void
    {
        $gallery = Gallery::factory()->create();
        $photo = Photo::factory()->create(['gallery_id' => $gallery->id]);
        $order = Order::factory()->pending()->paid('tok_dl')->create(['status' => 'pending']);
        OrderItem::factory()->forPhoto($photo)->create(['order_id' => $order->id]);

        $this->get("/api/images/download/{$photo->id}?token=tok_dl&order={$order->id}")
            ->assertStatus(403);
    }

    public function test_invalid_token_is_forbidden(): void
    {
        [$photo, $order] = $this->paidOrderWithPhoto('tok_good');

        $this->get("/api/images/download/{$photo->id}?token=tok_wrong&order={$order->id}")
            ->assertStatus(403);
    }

    public function test_photo_not_in_order_is_forbidden(): void
    {
        [, $order] = $this->paidOrderWithPhoto();
        $otherPhoto = Photo::factory()->create();
        $this->disk->put($otherPhoto->resolved_storage_path, 'x');

        $this->get("/api/images/download/{$otherPhoto->id}?token=tok_dl&order={$order->id}")
            ->assertStatus(403);
    }

    public function test_valid_purchase_streams_hd_and_marks_downloaded(): void
    {
        [$photo, $order] = $this->paidOrderWithPhoto();

        $response = $this->get("/api/images/download/{$photo->id}?token=tok_dl&order={$order->id}");

        $response->assertOk();
        // assertDatabaseHas génèrerait `is_downloaded = 1`, rejeté par le type
        // boolean Postgres → on recharge le modèle et on vérifie l'attribut casté.
        $item = OrderItem::where('order_id', $order->id)->where('photo_id', $photo->id)->first();
        $this->assertTrue($item->is_downloaded);
    }

    public function test_missing_hd_file_returns_404(): void
    {
        $gallery = Gallery::factory()->create();
        $photo = Photo::factory()->create(['gallery_id' => $gallery->id]); // pas de fichier posé
        $order = Order::factory()->paid('tok_dl')->create();
        OrderItem::factory()->forPhoto($photo)->create(['order_id' => $order->id]);

        $this->get("/api/images/download/{$photo->id}?token=tok_dl&order={$order->id}")
            ->assertStatus(404);
    }
}
