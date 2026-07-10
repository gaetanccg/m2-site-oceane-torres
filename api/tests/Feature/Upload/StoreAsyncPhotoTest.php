<?php

namespace Tests\Feature\Upload;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoUpload;
use App\Services\ImageProcessingService;
use App\Services\MinioStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * POST /admin/galleries/{gallery}/photos/async — upload synchrone de photos.
 * Le traitement d'image (ImageProcessingService) et le stockage vidéo
 * (MinioStorageService) sont mockés : on teste l'orchestration, pas Imagick.
 */
class StoreAsyncPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function mockImageSuccess(): void
    {
        $mock = Mockery::mock(ImageProcessingService::class);
        $mock->shouldReceive('processUploadedPhoto')->andReturn([
            'hd_path' => 'gal/original/hd.jpg',
            'original_path' => 'gal/original/orig.jpg',
            'preview_path' => 'gal/preview/prev.jpg',
            'thumbnail_path' => 'gal/thumbnail/thumb.jpg',
            'filename' => 'hd.jpg',
            'width' => 1920,
            'height' => 1080,
        ]);
        $this->app->instance(ImageProcessingService::class, $mock);
    }

    public function test_requires_admin_authentication(): void
    {
        $gallery = Gallery::factory()->create();

        $this->postJson("/api/admin/galleries/{$gallery->id}/photos/async", [
            'batch_id' => 'b1',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertStatus(401);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAsClient();
        $gallery = Gallery::factory()->create();

        $this->postJson("/api/admin/galleries/{$gallery->id}/photos/async", [
            'batch_id' => 'b1',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertStatus(403);
    }

    public function test_parent_gallery_is_rejected(): void
    {
        $this->actingAsAdmin();
        $parent = Gallery::factory()->create();
        Gallery::factory()->create(['parent_id' => $parent->id]); // enfant → parent

        $response = $this->postJson("/api/admin/galleries/{$parent->id}/photos/async", [
            'batch_id' => 'b1',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_uploads_an_image_and_creates_photo(): void
    {
        $this->actingAsAdmin();
        $this->mockImageSuccess();
        $gallery = Gallery::factory()->create();

        $response = $this->postJson("/api/admin/galleries/{$gallery->id}/photos/async", [
            'batch_id' => 'batch-42',
            'photos' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('uploads.0.status', 'completed');

        $photo = Photo::first();
        $this->assertNotNull($photo);
        $this->assertTrue($photo->is_processed);
        $this->assertFalse($photo->is_downloadable);
        $this->assertSame('gal/original/hd.jpg', $photo->file_path_hd);

        $this->assertDatabaseHas('photo_uploads', [
            'batch_id' => 'batch-42',
            'status' => 'completed',
            'photo_id' => $photo->id,
        ]);
    }

    public function test_uploads_a_video_via_storage_service(): void
    {
        $this->actingAsAdmin();
        $storage = Mockery::mock(MinioStorageService::class);
        $storage->shouldReceive('uploadPhoto')->once()->andReturn(['path' => 'gal/video.mp4']);
        $this->app->instance(MinioStorageService::class, $storage);
        $gallery = Gallery::factory()->create();

        $response = $this->postJson("/api/admin/galleries/{$gallery->id}/photos/async", [
            'batch_id' => 'batch-vid',
            'photos' => [UploadedFile::fake()->create('clip.mp4', 2000, 'video/mp4')],
        ]);

        $response->assertOk()->assertJsonPath('uploads.0.status', 'completed');
        $photo = Photo::first();
        $this->assertTrue($photo->is_video);
        $this->assertSame('gal/video.mp4', $photo->file_path);
    }

    public function test_partial_success_marks_failed_file_without_aborting_batch(): void
    {
        $this->actingAsAdmin();
        // Première photo OK, seconde renvoie null → RuntimeException → failed.
        $mock = Mockery::mock(ImageProcessingService::class);
        $mock->shouldReceive('processUploadedPhoto')->andReturn(
            ['hd_path' => 'a/hd.jpg', 'preview_path' => 'a/p.jpg', 'thumbnail_path' => 'a/t.jpg', 'width' => 1, 'height' => 1],
            null
        );
        $this->app->instance(ImageProcessingService::class, $mock);
        $gallery = Gallery::factory()->create();

        $response = $this->postJson("/api/admin/galleries/{$gallery->id}/photos/async", [
            'batch_id' => 'batch-mix',
            'photos' => [
                UploadedFile::fake()->image('ok.jpg'),
                UploadedFile::fake()->image('ko.jpg'),
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('uploads.0.status', 'completed')
            ->assertJsonPath('uploads.1.status', 'failed');

        $this->assertNotNull($response->json('uploads.1.error_message'));
        $this->assertSame(1, PhotoUpload::where('status', 'completed')->count());
        $this->assertSame(1, PhotoUpload::where('status', 'failed')->count());
        $this->assertSame(1, Photo::count());
    }

    public function test_event_gallery_upload_via_events_route_clears_cache(): void
    {
        $this->actingAsAdmin();
        $this->mockImageSuccess();
        $gallery = Gallery::factory()->event()->create();

        // Le cache des galeries événement doit être invalidé après un upload réussi.
        Cache::put('event_galleries_page_1', ['stale'], 60);

        $response = $this->postJson("/api/admin/events/{$gallery->id}/photos/async", [
            'batch_id' => 'batch-event',
            'photos' => [UploadedFile::fake()->image('e.jpg')],
        ]);

        $response->assertOk()->assertJsonPath('uploads.0.status', 'completed');
        $this->assertSame(1, Photo::count());
        $this->assertFalse(Cache::has('event_galleries_page_1'));
    }
}
