<?php

namespace Tests\Feature\Upload;

use App\Jobs\GenerateCleanThumbnailJob;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\MinioStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Gestion admin des photos : downloadable (unitaire + bulk), tri, suppression.
 */
class PhotoManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_toggle_downloadable_dispatches_clean_thumbnail_job_when_enabling(): void
    {
        Queue::fake();
        $photo = Photo::factory()->notDownloadable()->create();

        $this->putJson("/api/admin/photos/{$photo->id}/toggle-downloadable")
            ->assertOk()
            ->assertJsonPath('data.is_downloadable', true);

        $this->assertTrue($photo->fresh()->is_downloadable);
        Queue::assertPushed(GenerateCleanThumbnailJob::class, 1);
    }

    public function test_toggle_downloadable_off_does_not_dispatch_job(): void
    {
        Queue::fake();
        $photo = Photo::factory()->downloadable()->create();

        $this->putJson("/api/admin/photos/{$photo->id}/toggle-downloadable")
            ->assertOk()
            ->assertJsonPath('data.is_downloadable', false);

        Queue::assertNotPushed(GenerateCleanThumbnailJob::class);
    }

    public function test_toggle_downloadable_video_does_not_dispatch_job(): void
    {
        Queue::fake();
        $photo = Photo::factory()->video()->notDownloadable()->create();

        $this->putJson("/api/admin/photos/{$photo->id}/toggle-downloadable")->assertOk();

        Queue::assertNotPushed(GenerateCleanThumbnailJob::class);
    }

    public function test_bulk_toggle_downloadable_updates_and_dispatches_jobs(): void
    {
        Queue::fake();
        $gallery = Gallery::factory()->create();
        $photos = Photo::factory()->count(3)->notDownloadable()->create(['gallery_id' => $gallery->id]);

        $response = $this->putJson('/api/admin/photos/bulk-downloadable', [
            'photo_ids' => $photos->pluck('id')->all(),
            'is_downloadable' => true,
        ]);

        $response->assertOk()->assertJsonPath('updated_count', 3);
        foreach ($photos as $photo) {
            $this->assertTrue($photo->fresh()->is_downloadable);
        }
        Queue::assertPushed(GenerateCleanThumbnailJob::class, 3);
    }

    public function test_update_sort_order_persists_ordering(): void
    {
        $gallery = Gallery::factory()->create();
        $a = Photo::factory()->create(['gallery_id' => $gallery->id, 'sort_order' => 0]);
        $b = Photo::factory()->create(['gallery_id' => $gallery->id, 'sort_order' => 0]);

        $this->putJson('/api/admin/photos/sort-order', [
            'photos' => [
                ['id' => $a->id, 'sort_order' => 5],
                ['id' => $b->id, 'sort_order' => 2],
            ],
        ])->assertOk();

        // NB : Photo ne caste pas sort_order → Postgres renvoie une chaîne (comparaison lâche).
        $this->assertEquals(5, $a->fresh()->sort_order);
        $this->assertEquals(2, $b->fresh()->sort_order);
    }

    public function test_destroy_deletes_photo_and_storage_objects(): void
    {
        $storage = Mockery::mock(MinioStorageService::class);
        $storage->shouldReceive('deletePhoto')->atLeast()->once();
        $this->app->instance(MinioStorageService::class, $storage);

        $photo = Photo::factory()->create();

        $this->deleteJson("/api/admin/photos/{$photo->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
    }
}
