<?php

namespace Database\Factories;

use App\Models\SchoolSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolSessionContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_session_id' => SchoolSession::factory(),
            'recipient_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'status' => 'pending',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => 'sent', 'sent_at' => now(), 'channel' => 'email', 'send_attempts' => 1]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed', 'error_message' => 'Échec de test', 'send_attempts' => 1]);
    }
}
