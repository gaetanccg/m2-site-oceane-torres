<?php

namespace Tests\Feature\Download;

use App\Models\DownloadLog;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GET /galleries/{gallery}/download-zip — ZIP streamé des photos téléchargeables.
 * Contrôle d'accès via GalleryPolicy (public / event publié / token / propriétaire).
 */
class GalleryZipTest extends TestCase
{
    use RefreshDatabase;

    private Filesystem $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = Storage::fake('minio');
    }

    /** Crée une photo téléchargeable dont l'objet existe sur le disque fake. */
    private function downloadablePhotoWithFile(Gallery $gallery, string $title): Photo
    {
        $photo = Photo::factory()->downloadable()->create([
            'gallery_id' => $gallery->id,
            'title' => $title,
        ]);
        $this->disk->put($photo->resolved_storage_path, 'fake-image-bytes');

        return $photo;
    }

    public function test_private_gallery_without_token_is_forbidden(): void
    {
        $gallery = Gallery::factory()->private()->create();
        $this->downloadablePhotoWithFile($gallery, 'p1');

        $this->get("/api/galleries/{$gallery->id}/download-zip")->assertStatus(403);
    }

    public function test_private_gallery_with_wrong_token_is_forbidden(): void
    {
        $gallery = Gallery::factory()->private()->create();
        $this->downloadablePhotoWithFile($gallery, 'p1');

        $this->get("/api/galleries/{$gallery->id}/download-zip?token=wrong")->assertStatus(403);
    }

    public function test_private_gallery_with_valid_token_streams_zip(): void
    {
        $gallery = Gallery::factory()->private()->create();
        $this->downloadablePhotoWithFile($gallery, 'p1');

        $response = $this->get("/api/galleries/{$gallery->id}/download-zip?token={$gallery->access_token}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_public_gallery_is_downloadable_without_token(): void
    {
        $gallery = Gallery::factory()->public()->create();
        $this->downloadablePhotoWithFile($gallery, 'p1');

        $this->get("/api/galleries/{$gallery->id}/download-zip")->assertOk();
    }

    public function test_empty_downloadable_set_returns_404(): void
    {
        $gallery = Gallery::factory()->public()->create();
        // Photo NON téléchargeable uniquement.
        Photo::factory()->notDownloadable()->create(['gallery_id' => $gallery->id]);

        $this->get("/api/galleries/{$gallery->id}/download-zip")->assertStatus(404);
    }

    public function test_only_downloadable_photos_are_logged_when_streamed(): void
    {
        $gallery = Gallery::factory()->public()->create();
        $this->downloadablePhotoWithFile($gallery, 'a');
        $this->downloadablePhotoWithFile($gallery, 'b');
        // Une photo non téléchargeable : ne doit pas figurer dans le ZIP.
        Photo::factory()->notDownloadable()->create(['gallery_id' => $gallery->id]);

        $response = $this->get("/api/galleries/{$gallery->id}/download-zip");
        $response->streamedContent(); // force l'exécution du closure de streaming

        // recordDownload appelé une fois par photo réellement streamée.
        $this->assertSame(2, DownloadLog::count());
    }

    public function test_photo_with_missing_storage_object_is_skipped(): void
    {
        $gallery = Gallery::factory()->public()->create();
        $this->downloadablePhotoWithFile($gallery, 'present');
        // Photo téléchargeable mais SANS fichier sur le disque → readStream null → ignorée.
        Photo::factory()->downloadable()->create(['gallery_id' => $gallery->id, 'title' => 'missing']);

        $response = $this->get("/api/galleries/{$gallery->id}/download-zip");
        $response->streamedContent();

        // Seule la photo présente est loggée.
        $this->assertSame(1, DownloadLog::count());
    }
}
