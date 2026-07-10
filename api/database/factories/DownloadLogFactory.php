<?php

namespace Database\Factories;

use App\Models\DownloadLog;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownloadLog>
 */
class DownloadLogFactory extends Factory
{
    protected $model = DownloadLog::class;

    public function definition(): array
    {
        $photo = Photo::factory();

        return [
            'photo_id' => $photo,
            'gallery_id' => fn (array $attrs) => Photo::find($attrs['photo_id'])?->gallery_id,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'downloaded_at' => now(),
        ];
    }

    public function forPhoto(Photo $photo): static
    {
        return $this->state(fn () => [
            'photo_id' => $photo->id,
            'gallery_id' => $photo->gallery_id,
        ]);
    }
}
