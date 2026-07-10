<?php

namespace Tests\Feature\Upload;

use App\Models\Gallery;
use App\Models\PhotoUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /admin/upload-status — agrégat de statut d'un batch.
 * Valide le SQL Postgres `COUNT(*) FILTER (WHERE ...)`.
 */
class UploadStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_aggregates_batch_counts(): void
    {
        $gallery = Gallery::factory()->create();
        $batch = 'batch-xyz';

        PhotoUpload::factory()->count(2)->batch($batch)->completed()->create(['gallery_id' => $gallery->id]);
        PhotoUpload::factory()->batch($batch)->failed()->create(['gallery_id' => $gallery->id]);
        PhotoUpload::factory()->batch($batch)->processing()->create(['gallery_id' => $gallery->id]);

        $response = $this->getJson("/api/admin/upload-status?batch_id={$batch}");

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('total', 4)
            ->assertJsonPath('completed', 2)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('processing', 1)
            ->assertJsonPath('is_complete', false)
            ->assertJsonPath('progress', 75); // (2 completed + 1 failed) / 4
    }

    public function test_complete_batch_reports_is_complete(): void
    {
        $gallery = Gallery::factory()->create();
        PhotoUpload::factory()->count(3)->batch('done')->completed()->create(['gallery_id' => $gallery->id]);

        $this->getJson('/api/admin/upload-status?batch_id=done')
            ->assertOk()
            ->assertJsonPath('is_complete', true)
            ->assertJsonPath('progress', 100);
    }

    public function test_unknown_batch_reports_not_found(): void
    {
        $this->getJson('/api/admin/upload-status?batch_id=nope')
            ->assertOk()
            ->assertJsonPath('found', false);
    }
}
