<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'École '.fake()->lastName(),
            'status' => 'completed',
        ];
    }

    public function closed(?int $moisDepuis = null): static
    {
        return $this->state(fn () => [
            'closed_at' => $moisDepuis ? now()->subMonths($moisDepuis) : now(),
        ]);
    }
}
