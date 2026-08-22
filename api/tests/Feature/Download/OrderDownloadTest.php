<?php

namespace Tests\Feature\Download;

use App\Models\Gallery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\User;
use App\Services\MinioStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * GET /orders/{order}/download/{item} et /orders/{order}/download-all.
 * Accès décidé par OrderPolicy::download (payée + propriétaire ou jeton valide).
 * Ces tests portent sur la mécanique de téléchargement, pas sur l'autorisation :
 * ils s'authentifient donc comme propriétaire de la commande.
 */
class OrderDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrderOwnedByCurrentUser(): Order
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return Order::factory()->paid()->create(['user_id' => $user->id]);
    }

    public function test_download_photo_returns_signed_url_and_marks_downloaded(): void
    {
        $order = $this->paidOrderOwnedByCurrentUser();
        $photo = Photo::factory()->create();
        $item = OrderItem::factory()->forPhoto($photo)->create(['order_id' => $order->id]);

        $mock = Mockery::mock(MinioStorageService::class);
        $mock->shouldReceive('getSignedUrl')->once()->andReturn('https://minio.example/hd');
        $this->app->instance(MinioStorageService::class, $mock);

        $this->getJson("/api/orders/{$order->id}/download/{$item->id}")
            ->assertOk()
            ->assertJsonPath('download_url', 'https://minio.example/hd');

        $this->assertTrue($item->fresh()->is_downloaded);
    }

    public function test_download_photo_on_unpaid_order_is_forbidden(): void
    {
        $order = Order::factory()->pending()->create();
        $photo = Photo::factory()->create();
        $item = OrderItem::factory()->forPhoto($photo)->create(['order_id' => $order->id]);

        $this->getJson("/api/orders/{$order->id}/download/{$item->id}")->assertStatus(403);
    }

    public function test_download_all_is_capped_at_50_digital_items(): void
    {
        $order = $this->paidOrderOwnedByCurrentUser();
        OrderItem::factory()->count(51)->create(['order_id' => $order->id, 'product_type' => 'digital']);

        $this->getJson("/api/orders/{$order->id}/download-all")
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_download_all_streams_zip_and_marks_items_downloaded(): void
    {
        $disk = Storage::fake('minio');
        $gallery = Gallery::factory()->create();
        $order = $this->paidOrderOwnedByCurrentUser();

        $items = collect(range(1, 2))->map(function ($i) use ($gallery, $order, $disk) {
            $photo = Photo::factory()->create(['gallery_id' => $gallery->id]);
            $disk->put($photo->resolved_storage_path, "bytes-{$i}");

            return OrderItem::factory()->forPhoto($photo)->create(['order_id' => $order->id]);
        });

        $response = $this->get("/api/orders/{$order->id}/download-all");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');

        foreach ($items as $item) {
            $this->assertTrue($item->fresh()->is_downloaded);
        }
    }
}
