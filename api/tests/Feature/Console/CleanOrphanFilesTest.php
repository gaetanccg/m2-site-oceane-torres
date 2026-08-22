<?php

namespace Tests\Feature\Console;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanOrphanFilesTest extends TestCase
{
    use RefreshDatabase;

    private Gallery $gallery;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('minio');
        $this->gallery = Gallery::factory()->create();
    }

    private function putObject(string $name): string
    {
        $path = "{$this->gallery->id}/{$name}";
        Storage::disk('minio')->put($path, 'octets');

        return $path;
    }

    private function makePhoto(array $attributes): Photo
    {
        return Photo::factory()->create(array_merge([
            'gallery_id' => $this->gallery->id,
            'file_path_hd' => null,
            'file_path_preview' => null,
            'file_path_thumbnail' => null,
        ], $attributes));
    }

    public function test_path_referenced_only_by_supabase_metadata_is_not_an_orphan(): void
    {
        $current = $this->putObject('original/current.jpg');
        $legacy = $this->putObject('original/legacy.jpg');

        $this->makePhoto([
            'file_path' => $current,
            'metadata' => ['supabase_path' => $legacy],
        ]);

        $this->artisan('storage:clean-orphans')
            ->expectsOutputToContain('No orphan files found!')
            ->assertSuccessful();
    }

    public function test_path_referenced_only_by_storage_path_metadata_is_not_an_orphan(): void
    {
        $current = $this->putObject('original/current.jpg');
        $stored = $this->putObject('original/stored.jpg');

        $this->makePhoto([
            'file_path' => $current,
            'metadata' => ['storage_path' => $stored],
        ]);

        $this->artisan('storage:clean-orphans')
            ->expectsOutputToContain('No orphan files found!')
            ->assertSuccessful();
    }

    public function test_clean_thumbnail_is_not_an_orphan(): void
    {
        $original = $this->putObject('original/current.jpg');
        $clean = $this->putObject('thumbnail-clean/current.jpg');

        $this->makePhoto([
            'file_path' => $original,
            'file_path_thumbnail_clean' => $clean,
        ]);

        $this->artisan('storage:clean-orphans')
            ->expectsOutputToContain('No orphan files found!')
            ->assertSuccessful();
    }

    public function test_genuinely_unreferenced_file_is_reported(): void
    {
        $original = $this->putObject('original/current.jpg');
        $stray = $this->putObject('original/stray.jpg');

        $this->makePhoto(['file_path' => $original]);

        $this->artisan('storage:clean-orphans')
            ->expectsOutputToContain($stray)
            ->expectsOutputToContain('Total: 1 orphan files')
            ->assertSuccessful();
    }

    public function test_invoices_prefix_is_never_reported(): void
    {
        $original = $this->putObject('original/current.jpg');
        Storage::disk('minio')->put('invoices/2026/F-2026-00001.pdf', 'pdf');

        $this->makePhoto(['file_path' => $original]);

        $this->artisan('storage:clean-orphans')
            ->expectsOutputToContain('No orphan files found!')
            ->assertSuccessful();
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $original = $this->putObject('original/current.jpg');
        $stray = $this->putObject('original/stray.jpg');

        $this->makePhoto(['file_path' => $original]);

        $this->artisan('storage:clean-orphans')->assertSuccessful();

        Storage::disk('minio')->assertExists($stray);
    }
}
