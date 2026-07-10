<?php

namespace Tests\Feature\Download;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GET /galleries/download/{token} — listing des photos téléchargeables (mode download).
 */
class DownloadableListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->getJson('/api/galleries/download/nonexistent-token')->assertStatus(404);
    }

    public function test_valid_token_lists_downloadable_photos_in_download_mode(): void
    {
        $gallery = Gallery::factory()->create();
        Photo::factory()->downloadable()->create(['gallery_id' => $gallery->id]);
        Photo::factory()->notDownloadable()->create(['gallery_id' => $gallery->id]);

        $response = $this->getJson("/api/galleries/download/{$gallery->access_token}");

        $response->assertOk()
            ->assertJsonPath('mode', 'download')
            ->assertJsonCount(1, 'gallery.photos');

        // clean_thumbnail_url est exposé uniquement sur cet endpoint.
        $response->assertJsonPath('gallery.photos.0.clean_thumbnail_url', fn ($url) => $url !== null);
    }

    public function test_records_a_view(): void
    {
        $gallery = Gallery::factory()->create(['views_count' => 0]);

        $this->getJson("/api/galleries/download/{$gallery->access_token}")->assertOk();

        $this->assertSame(1, $gallery->fresh()->views_count);
    }
}
