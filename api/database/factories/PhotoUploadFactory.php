<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\PhotoUpload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PhotoUpload>
 *
 * Suivi d'un upload de photo (statut d'un fichier dans un batch).
 */
class PhotoUploadFactory extends Factory
{
    protected $model = PhotoUpload::class;

    public function definition(): array
    {
        return [
            'batch_id' => (string) Str::uuid(),
            'gallery_id' => Gallery::factory(),
            'original_filename' => fake()->word().'.jpg',
            'status' => 'pending',
        ];
    }

    public function batch(string $batchId): static
    {
        return $this->state(fn () => ['batch_id' => $batchId]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => 'processing']);
    }

    public function completed(?string $photoId = null): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'photo_id' => $photoId,
            'completed_at' => now(),
        ]);
    }

    public function failed(string $message = 'Erreur de traitement'): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }
}
