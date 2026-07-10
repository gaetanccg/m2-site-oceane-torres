<?php

namespace Database\Factories;

use App\Models\GiftCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GiftCode>
 *
 * Par défaut : code fixe de 10 €, actif, utilisable une fois, sans borne de dates.
 */
class GiftCodeFactory extends Factory
{
    protected $model = GiftCode::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'type' => 'fixed',
            'value' => 10.00,
            'max_discount_amount' => null,
            'valid_from' => null,
            'valid_until' => null,
            'max_uses' => 1,
            'is_active' => true,
        ];
    }

    public function fixed(float $value): static
    {
        return $this->state(fn () => ['type' => 'fixed', 'value' => $value]);
    }

    public function percent(float $value, ?float $maxDiscount = null): static
    {
        return $this->state(fn () => [
            'type' => 'percent',
            'value' => $value,
            'max_discount_amount' => $maxDiscount,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unlimited(): static
    {
        return $this->state(fn () => ['max_uses' => null]);
    }

    public function maxUses(int $uses): static
    {
        return $this->state(fn () => ['max_uses' => $uses]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'valid_from' => now()->subMonth(),
            'valid_until' => now()->subDay(),
        ]);
    }

    public function notYetValid(): static
    {
        return $this->state(fn () => [
            'valid_from' => now()->addWeek(),
            'valid_until' => now()->addMonth(),
        ]);
    }
}
