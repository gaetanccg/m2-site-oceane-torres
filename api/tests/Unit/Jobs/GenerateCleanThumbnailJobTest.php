<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateCleanThumbnailJob;
use App\Models\Photo;
use App\Services\ImageProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GenerateCleanThumbnailJobTest extends TestCase
{
    use RefreshDatabase;

    private function imageServiceExpecting(\Closure $expectations): ImageProcessingService
    {
        $mock = Mockery::mock(ImageProcessingService::class);
        $expectations($mock);

        return $mock;
    }

    public function test_skips_when_photo_missing(): void
    {
        $service = $this->imageServiceExpecting(
            fn ($m) => $m->shouldReceive('generateAndStoreCleanThumbnail')->never()
        );

        (new GenerateCleanThumbnailJob('00000000-0000-0000-0000-000000000000'))->handle($service);

        $this->assertTrue(true); // aucune exception
    }

    public function test_skips_video(): void
    {
        $photo = Photo::factory()->video()->downloadable()->create();
        $service = $this->imageServiceExpecting(
            fn ($m) => $m->shouldReceive('generateAndStoreCleanThumbnail')->never()
        );

        (new GenerateCleanThumbnailJob($photo->id))->handle($service);

        $this->assertNull($photo->fresh()->file_path_thumbnail_clean);
    }

    public function test_skips_when_not_downloadable(): void
    {
        $photo = Photo::factory()->notDownloadable()->create();
        $service = $this->imageServiceExpecting(
            fn ($m) => $m->shouldReceive('generateAndStoreCleanThumbnail')->never()
        );

        (new GenerateCleanThumbnailJob($photo->id))->handle($service);

        $this->assertNull($photo->fresh()->file_path_thumbnail_clean);
    }

    public function test_skips_when_clean_thumbnail_already_present(): void
    {
        $photo = Photo::factory()->downloadable()->withCleanThumbnail()->create();
        $service = $this->imageServiceExpecting(
            fn ($m) => $m->shouldReceive('generateAndStoreCleanThumbnail')->never()
        );

        (new GenerateCleanThumbnailJob($photo->id))->handle($service);

        $this->assertTrue(true);
    }

    public function test_generates_and_persists_path_on_success(): void
    {
        $photo = Photo::factory()->downloadable()->create();
        $service = $this->imageServiceExpecting(
            fn ($m) => $m->shouldReceive('generateAndStoreCleanThumbnail')->once()->andReturnTrue()
        );

        (new GenerateCleanThumbnailJob($photo->id))->handle($service);

        $this->assertSame($photo->cleanThumbnailStoragePath(), $photo->fresh()->file_path_thumbnail_clean);
    }

    public function test_throws_to_trigger_retry_on_failure(): void
    {
        $photo = Photo::factory()->downloadable()->create();
        $service = $this->imageServiceExpecting(
            fn ($m) => $m->shouldReceive('generateAndStoreCleanThumbnail')->once()->andReturnFalse()
        );

        $this->expectException(\RuntimeException::class);
        (new GenerateCleanThumbnailJob($photo->id))->handle($service);
    }
}
