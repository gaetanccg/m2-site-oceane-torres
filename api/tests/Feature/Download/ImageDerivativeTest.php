<?php

namespace Tests\Feature\Download;

use App\Models\Gallery;
use App\Models\Photo;
use App\Services\ImageProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ImageDerivativeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('minio');
    }

    private function mockGenerator(int $expectedCalls): void
    {
        $mock = Mockery::mock(ImageProcessingService::class);
        $mock->shouldReceive('generatePreviewOnTheFly')
            ->times($expectedCalls)
            ->andReturn('octets-de-preview');
        $this->app->instance(ImageProcessingService::class, $mock);
    }

    public function test_a_generated_derivative_is_persisted_on_storage(): void
    {
        $this->mockGenerator(1);
        $photo = Photo::factory()->unprocessed()->create();

        $this->get("/api/images/preview/{$photo->id}")->assertOk();

        Storage::disk('minio')->assertExists("derived/preview_{$photo->id}.jpg");
    }

    public function test_a_second_request_reuses_the_stored_derivative(): void
    {
        $this->mockGenerator(1);
        $photo = Photo::factory()->unprocessed()->create();

        $this->get("/api/images/preview/{$photo->id}")->assertOk();
        $this->get("/api/images/preview/{$photo->id}")->assertOk();
    }

    public function test_derivatives_are_not_written_to_the_application_cache(): void
    {
        $this->mockGenerator(1);
        $photo = Photo::factory()->unprocessed()->create();

        $this->get("/api/images/preview/{$photo->id}")->assertOk();

        // Le cache est plafonne en memoire avec eviction LRU et porte les heartbeats
        // de supervision : aucun binaire d'image ne doit y entrer.
        $this->assertNull(cache()->get("image_preview_{$photo->id}"));
    }

    public function test_the_derived_prefix_is_ignored_by_the_orphan_scan(): void
    {
        Storage::disk('minio')->put('derived/preview_abc.jpg', 'x');
        $original = "{$this->galleryPath()}/original/photo.jpg";
        Storage::disk('minio')->put($original, 'x');
        Photo::factory()->create(['file_path' => $original, 'file_path_hd' => null]);

        $this->artisan('storage:clean-orphans')
            ->expectsOutputToContain('No orphan files found!')
            ->assertSuccessful();
    }

    private function galleryPath(): string
    {
        return (string) Gallery::factory()->create()->id;
    }
}
