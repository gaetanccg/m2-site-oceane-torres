<?php

namespace Tests\Feature\Download;

use App\Models\DownloadLog;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
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

    public function test_published_event_gallery_is_downloadable_without_token(): void
    {
        // Branche GalleryPolicy : event + is_published → accès sans token.
        $gallery = Gallery::factory()->event()->create();
        $this->downloadablePhotoWithFile($gallery, 'p1');

        $this->get("/api/galleries/{$gallery->id}/download-zip")->assertOk();
    }

    public function test_owner_can_download_private_gallery_without_token(): void
    {
        // Branche GalleryPolicy : propriétaire authentifié → accès sans token.
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $gallery = Gallery::factory()->private()->create(['user_id' => $owner->id]);
        $this->downloadablePhotoWithFile($gallery, 'p1');

        $this->get("/api/galleries/{$gallery->id}/download-zip")->assertOk();
    }

    public function test_caps_at_500_photos(): void
    {
        $gallery = Gallery::factory()->public()->create();
        $photos = Photo::factory()->count(501)->downloadable()->create(['gallery_id' => $gallery->id]);
        foreach ($photos as $photo) {
            $this->disk->put($photo->resolved_storage_path, 'x');
        }

        $response = $this->get("/api/galleries/{$gallery->id}/download-zip");
        $response->streamedContent();

        // Plafond dur de 500 : au-delà, les photos ne sont pas streamées ni loggées.
        $this->assertSame(500, DownloadLog::count());
    }

    public function test_duplicate_titles_get_unique_zip_entry_names(): void
    {
        $gallery = Gallery::factory()->public()->create();
        $this->downloadablePhotoWithFile($gallery, 'dup');
        $this->downloadablePhotoWithFile($gallery, 'dup');

        $response = $this->get("/api/galleries/{$gallery->id}/download-zip");

        $tmp = tempnam(sys_get_temp_dir(), 'ziptest').'.zip';
        file_put_contents($tmp, $response->streamedContent());
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->statIndex($i)['name'];
        }
        $zip->close();
        @unlink($tmp);

        $this->assertCount(2, $names);
        $this->assertSame($names, array_values(array_unique($names)));
    }
}
