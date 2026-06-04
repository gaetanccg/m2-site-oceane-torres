<?php

namespace Tests\Unit;

use App\Models\GiftCode;
use PHPUnit\Framework\TestCase;

/**
 * Test unitaire pur (sans base de données) du calcul de remise — la logique
 * sécurité-critique : le montant facturé dépend entièrement de cette méthode.
 */
class GiftCodeDiscountTest extends TestCase
{
    private function makeCode(array $attributes): GiftCode
    {
        $code = new GiftCode;
        foreach ($attributes as $key => $value) {
            $code->{$key} = $value;
        }

        return $code;
    }

    public function test_fixed_discount_returns_value(): void
    {
        $code = $this->makeCode(['type' => 'fixed', 'value' => 10]);

        $this->assertEqualsWithDelta(10.0, $code->effectiveDiscount(50), 0.001);
    }

    public function test_fixed_discount_is_capped_at_subtotal(): void
    {
        $code = $this->makeCode(['type' => 'fixed', 'value' => 100]);

        // Une remise supérieure au panier ne peut pas rendre le total négatif.
        $this->assertEqualsWithDelta(50.0, $code->effectiveDiscount(50), 0.001);
    }

    public function test_percent_discount(): void
    {
        $code = $this->makeCode(['type' => 'percent', 'value' => 10]);

        $this->assertEqualsWithDelta(5.0, $code->effectiveDiscount(50), 0.001);
    }

    public function test_percent_discount_respects_max_cap(): void
    {
        $code = $this->makeCode([
            'type' => 'percent',
            'value' => 50,
            'max_discount_amount' => 10,
        ]);

        // 50 % de 50 = 25, plafonné à 10.
        $this->assertEqualsWithDelta(10.0, $code->effectiveDiscount(50), 0.001);
    }

    public function test_percent_discount_is_rounded_to_cents(): void
    {
        $code = $this->makeCode(['type' => 'percent', 'value' => 33]);

        // 33 % de 10 = 3.3
        $this->assertEqualsWithDelta(3.30, $code->effectiveDiscount(10), 0.001);
    }

    public function test_zero_subtotal_returns_zero(): void
    {
        $code = $this->makeCode(['type' => 'fixed', 'value' => 10]);

        $this->assertEqualsWithDelta(0.0, $code->effectiveDiscount(0), 0.001);
    }
}
