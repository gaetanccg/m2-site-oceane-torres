<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Photo>
 *
 * Par défaut : photo image, traitée, NON téléchargeable (comme après un upload).
 * Les chemins pointent vers un objet MinIO fictif — utiliser Storage::fake('minio')
 * et y déposer les octets attendus dans les tests qui lisent le fichier.
 */
class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition(): array
    {
        $galleryId = Gallery::factory();
        $uuid = (string) Str::uuid();

        return [
            'gallery_id' => $galleryId,
            'file_path' => "photos/original/{$uuid}.jpg",
            'file_path_hd' => "photos/original/{$uuid}.jpg",
            'file_path_preview' => "photos/preview/{$uuid}.jpg",
            'file_path_thumbnail' => "photos/thumbnail/{$uuid}.jpg",
            'title' => fake()->words(2, true),
            'sort_order' => 0,
            'is_video' => false,
            'is_liked' => false,
            'is_processed' => true,
            'is_downloadable' => false,
            'is_purchasable' => true,
            'price' => 13.00,
            'downloads_count' => 0,
            'metadata' => [
                'size' => fake()->numberBetween(100_000, 5_000_000),
                'mime_type' => 'image/jpeg',
            ],
        ];
    }

    public function downloadable(): static
    {
        return $this->state(fn () => ['is_downloadable' => true]);
    }

    public function notDownloadable(): static
    {
        return $this->state(fn () => ['is_downloadable' => false]);
    }

    public function notPurchasable(): static
    {
        return $this->state(fn () => ['is_purchasable' => false]);
    }

    public function unprocessed(): static
    {
        return $this->state(fn () => ['is_processed' => false]);
    }

    public function video(): static
    {
        $uuid = (string) Str::uuid();

        return $this->state(fn () => [
            'is_video' => true,
            'is_downloadable' => false,
            'file_path' => "photos/{$uuid}.mp4",
            'file_path_hd' => null,
            'file_path_preview' => null,
            'file_path_thumbnail' => null,
            'metadata' => ['mime_type' => 'video/mp4'],
        ]);
    }

    /**
     * Miniature clean déjà générée (sans filigrane).
     */
    public function withCleanThumbnail(): static
    {
        $uuid = (string) Str::uuid();

        return $this->state(fn () => [
            'file_path_thumbnail_clean' => "photos/thumbnail-clean/{$uuid}.jpg",
        ]);
    }
}
