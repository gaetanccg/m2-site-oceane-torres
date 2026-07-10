<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessPhotoJob;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoUpload;
use App\Services\ImageProcessingService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Chemin queue (sessions scolaires) : ProcessPhotoJob.
 * Points clés : retry sans markAsFailed prématuré, markAsFailed uniquement dans failed().
 */
class ProcessPhotoJobTest extends TestCase
{
    use RefreshDatabase;

    private Filesystem $local;

    protected function setUp(): void
    {
        parent::setUp();
        $this->local = Storage::fake('local');
    }

    private function putTempFile(string $path = 'temp/photo.jpg'): string
    {
        $this->local->put($path, 'fake-bytes');

        return $path;
    }

    private function makeUpload(Gallery $gallery): PhotoUpload
    {
        return PhotoUpload::factory()->processing()->create(['gallery_id' => $gallery->id]);
    }

    public function test_returns_silently_when_upload_missing(): void
    {
        $job = new ProcessPhotoJob(
            (string) Str::uuid(),
            (string) Str::uuid(),
            'temp/none.jpg',
            'x.jpg',
            'image/jpeg'
        );

        $job->handle(); // aucune exception
        $this->assertTrue(true);
    }

    public function test_marks_failed_and_cleans_up_when_gallery_missing(): void
    {
        $gallery = Gallery::factory()->create();
        $upload = $this->makeUpload($gallery);
        $temp = $this->putTempFile();

        // galleryId ≠ galerie réelle → Gallery::find null.
        $job = new ProcessPhotoJob($upload->id, (string) Str::uuid(), $temp, 'x.jpg', 'image/jpeg');
        $job->handle();

        $this->assertSame('failed', $upload->fresh()->status);
        $this->assertSame('Galerie non trouvée', $upload->fresh()->error_message);
        $this->local->assertMissing($temp);
    }

    public function test_transient_failure_retries_without_marking_failed(): void
    {
        $gallery = Gallery::factory()->create();
        $upload = $this->makeUpload($gallery);
        $temp = $this->putTempFile();

        $mock = Mockery::mock(ImageProcessingService::class);
        $mock->shouldReceive('processUploadedPhoto')->andReturnNull();
        $this->app->instance(ImageProcessingService::class, $mock);

        $job = new ProcessPhotoJob($upload->id, $gallery->id, $temp, 'x.jpg', 'image/jpeg');

        try {
            $job->handle();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException) {
            // attendu → la queue relancera
        }

        // Pas de markAsFailed prématuré, temp conservé pour le retry.
        $this->assertNotSame('failed', $upload->fresh()->status);
        $this->local->assertExists($temp);
    }

    public function test_successful_image_completes_upload_and_cleans_temp(): void
    {
        $gallery = Gallery::factory()->create();
        $upload = $this->makeUpload($gallery);
        $temp = $this->putTempFile();

        $mock = Mockery::mock(ImageProcessingService::class);
        $mock->shouldReceive('processUploadedPhoto')->once()->andReturn([
            'hd_path' => 'g/hd.jpg',
            'preview_path' => 'g/p.jpg',
            'thumbnail_path' => 'g/t.jpg',
            'width' => 100,
            'height' => 100,
        ]);
        $this->app->instance(ImageProcessingService::class, $mock);

        $job = new ProcessPhotoJob($upload->id, $gallery->id, $temp, 'x.jpg', 'image/jpeg');
        $job->handle();

        $this->assertSame('completed', $upload->fresh()->status);
        $this->assertSame(1, Photo::where('gallery_id', $gallery->id)->count());
        $this->local->assertMissing($temp);
    }

    public function test_failed_callback_marks_upload_failed_and_cleans_up(): void
    {
        $gallery = Gallery::factory()->create();
        $upload = $this->makeUpload($gallery);
        $temp = $this->putTempFile();

        $job = new ProcessPhotoJob($upload->id, $gallery->id, $temp, 'x.jpg', 'image/jpeg');
        $job->failed(new \RuntimeException('boom'));

        $this->assertSame('failed', $upload->fresh()->status);
        $this->assertStringContainsString('boom', $upload->fresh()->error_message);
        $this->local->assertMissing($temp);
    }
}
