<?php

namespace Tests\Unit\Models;

use App\Models\DownloadLog;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolved_storage_path_prefers_hd(): void
    {
        $photo = new Photo([
            'file_path_hd' => 'hd/path.jpg',
            'file_path' => 'original/path.jpg',
            'metadata' => ['storage_path' => 'meta/path.jpg'],
        ]);

        $this->assertSame('hd/path.jpg', $photo->resolved_storage_path);
    }

    public function test_resolved_storage_path_falls_back_to_metadata_then_file_path(): void
    {
        $withMeta = new Photo([
            'file_path' => 'original/path.jpg',
            'metadata' => ['storage_path' => 'meta/path.jpg'],
        ]);
        $this->assertSame('meta/path.jpg', $withMeta->resolved_storage_path);

        $bare = new Photo(['file_path' => 'original/only.jpg']);
        $this->assertSame('original/only.jpg', $bare->resolved_storage_path);
    }

    public function test_clean_thumbnail_storage_path_uses_gallery_and_basename(): void
    {
        $photo = new Photo([
            'gallery_id' => 'gal-123',
            'file_path_thumbnail' => 'gal-123/thumbnail/abc.jpg',
        ]);

        $this->assertSame('gal-123/thumbnail-clean/abc.jpg', $photo->cleanThumbnailStoragePath());
    }

    public function test_record_download_increments_counter_and_logs(): void
    {
        $photo = Photo::factory()->create(['downloads_count' => 0]);

        $photo->recordDownload('203.0.113.4', 'Mozilla/5.0');

        $this->assertSame(1, $photo->fresh()->downloads_count);
        $this->assertSame(1, DownloadLog::where('photo_id', $photo->id)->count());

        $log = DownloadLog::where('photo_id', $photo->id)->first();
        $this->assertSame($photo->gallery_id, $log->gallery_id);
        $this->assertSame('203.0.113.4', $log->ip_address);
    }

    public function test_record_download_truncates_long_user_agent(): void
    {
        $photo = Photo::factory()->create();

        $photo->recordDownload('203.0.113.4', str_repeat('x', 400));

        $log = DownloadLog::where('photo_id', $photo->id)->first();
        $this->assertSame(255, strlen($log->user_agent));
    }
}
