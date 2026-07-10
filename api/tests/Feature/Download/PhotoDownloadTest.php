<?php

namespace Tests\Feature\Download;

use App\Models\DownloadLog;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\MinioStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * GET /photos/{photo}/download — téléchargement d'une photo unique.
 * Accès : Gallery::isAccessible($token) (public OU token exact) + is_downloadable.
 */
class PhotoDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function downloadablePhoto(): Photo
    {
        $gallery = Gallery::factory()->private()->create();

        return Photo::factory()->downloadable()->create(['gallery_id' => $gallery->id]);
    }

    public function test_inaccessible_gallery_is_forbidden_before_any_storage_access(): void
    {
        $photo = $this->downloadablePhoto();

        // Pas de token sur galerie privée → 403.
        $this->getJson("/api/photos/{$photo->id}/download")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Accès non autorisé.');

        $this->assertSame(0, DownloadLog::count());
    }

    public function test_non_downloadable_photo_is_forbidden(): void
    {
        $gallery = Gallery::factory()->public()->create();
        $photo = Photo::factory()->notDownloadable()->create(['gallery_id' => $gallery->id]);

        $this->getJson("/api/photos/{$photo->id}/download")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Cette photo n\'est pas téléchargeable.');
    }

    public function test_direct_mode_streams_raw_bytes(): void
    {
        $disk = Storage::fake('minio');
        $photo = $this->downloadablePhoto();
        $disk->put($photo->resolved_storage_path, 'raw-bytes');

        $response = $this->get("/api/photos/{$photo->id}/download?direct=1&token={$photo->gallery->access_token}");

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $this->assertSame('raw-bytes', $response->getContent());
        $this->assertSame(1, DownloadLog::count());
    }

    public function test_direct_mode_returns_500_when_storage_content_missing(): void
    {
        $photo = $this->downloadablePhoto();

        $mock = Mockery::mock(MinioStorageService::class);
        $mock->shouldReceive('getFileContent')->andReturnNull();
        $this->app->instance(MinioStorageService::class, $mock);

        $this->getJson("/api/photos/{$photo->id}/download?direct=1&token={$photo->gallery->access_token}")
            ->assertStatus(500);
    }

    public function test_default_mode_returns_signed_url(): void
    {
        $photo = $this->downloadablePhoto();

        $mock = Mockery::mock(MinioStorageService::class);
        $mock->shouldReceive('getSignedUrl')->once()->andReturn('https://minio.example/signed');
        $this->app->instance(MinioStorageService::class, $mock);

        $this->getJson("/api/photos/{$photo->id}/download?token={$photo->gallery->access_token}")
            ->assertOk()
            ->assertJsonPath('download_url', 'https://minio.example/signed');

        // recordDownload s'exécute avant la génération du lien.
        $this->assertSame(1, DownloadLog::count());
    }

    public function test_default_mode_returns_500_when_signed_url_fails(): void
    {
        $photo = $this->downloadablePhoto();

        $mock = Mockery::mock(MinioStorageService::class);
        $mock->shouldReceive('getSignedUrl')->andReturnNull();
        $this->app->instance(MinioStorageService::class, $mock);

        $this->getJson("/api/photos/{$photo->id}/download?token={$photo->gallery->access_token}")
            ->assertStatus(500);
    }
}
