<?php

namespace Tests\Feature\Upload;

use App\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Règles de validation de StoreAsyncPhotoRequest.
 */
class UploadValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        $gallery = Gallery::factory()->create();
        $this->url = "/api/admin/galleries/{$gallery->id}/photos/async";
    }

    public function test_batch_id_is_required(): void
    {
        $this->postJson($this->url, [
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertStatus(422)->assertJsonValidationErrors(['batch_id']);
    }

    public function test_at_least_one_photo_is_required(): void
    {
        $this->postJson($this->url, ['batch_id' => 'b1', 'photos' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['photos']);
    }

    public function test_rejects_more_than_15_files(): void
    {
        $photos = collect(range(1, 16))->map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"))->all();

        $this->postJson($this->url, ['batch_id' => 'b1', 'photos' => $photos])
            ->assertStatus(422)->assertJsonValidationErrors(['photos']);
    }

    public function test_rejects_disallowed_mime(): void
    {
        $this->postJson($this->url, [
            'batch_id' => 'b1',
            'photos' => [UploadedFile::fake()->create('doc.txt', 10, 'text/plain')],
        ])->assertStatus(422)->assertJsonValidationErrors(['photos.0']);
    }

    public function test_rejects_file_larger_than_50mb(): void
    {
        $this->postJson($this->url, [
            'batch_id' => 'b1',
            'photos' => [UploadedFile::fake()->create('big.jpg', 60000, 'image/jpeg')],
        ])->assertStatus(422)->assertJsonValidationErrors(['photos.0']);
    }
}
