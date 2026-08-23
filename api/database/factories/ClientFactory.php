<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '06'.fake()->numerify('########'),
            'source' => 'manual',
            'gdpr_consent' => false,
        ];
    }

    public function fromReservation(): static
    {
        return $this->state(fn () => ['source' => 'reservation']);
    }

    public function fromRegistration(): static
    {
        return $this->state(fn () => ['source' => 'registration']);
    }

    public function consenting(): static
    {
        return $this->state(fn () => ['gdpr_consent' => true, 'gdpr_consent_at' => now()]);
    }
}
