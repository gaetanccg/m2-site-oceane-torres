<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessException;
use App\Models\GiftCode;
use App\Models\Order;
use App\Services\GiftCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validation « métier » d'un code promo au moment du checkout.
 * Le calcul pur de la remise est couvert par Tests\Unit\GiftCodeDiscountTest.
 */
class GiftCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private GiftCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GiftCodeService;
    }

    public function test_returns_discount_for_valid_code(): void
    {
        $code = GiftCode::factory()->fixed(10)->create();

        $this->assertEqualsWithDelta(10.0, $this->service->assertUsableForCheckout($code, 50), 0.001);
    }

    public function test_throws_for_inactive_code(): void
    {
        $code = GiftCode::factory()->inactive()->create();

        $this->expectException(BusinessException::class);
        $this->service->assertUsableForCheckout($code, 50);
    }

    public function test_throws_for_expired_code(): void
    {
        $code = GiftCode::factory()->expired()->create();

        $this->expectException(BusinessException::class);
        $this->service->assertUsableForCheckout($code, 50);
    }

    public function test_throws_for_not_yet_valid_code(): void
    {
        $code = GiftCode::factory()->notYetValid()->create();

        $this->expectException(BusinessException::class);
        $this->service->assertUsableForCheckout($code, 50);
    }

    public function test_exception_carries_422_status(): void
    {
        $code = GiftCode::factory()->inactive()->create();

        try {
            $this->service->assertUsableForCheckout($code, 50);
            $this->fail('Expected BusinessException');
        } catch (BusinessException $e) {
            $this->assertSame(422, $e->getHttpStatus());
        }
    }

    public function test_max_uses_counts_only_paid_orders(): void
    {
        $code = GiftCode::factory()->fixed(10)->maxUses(1)->create();

        // Une commande pending ne consomme PAS le quota.
        Order::factory()->pending()->create(['gift_code_id' => $code->id]);
        $this->assertEqualsWithDelta(10.0, $this->service->assertUsableForCheckout($code, 50), 0.001);

        // Une commande payée consomme le quota → code épuisé.
        Order::factory()->paid()->create(['gift_code_id' => $code->id]);

        $this->expectException(BusinessException::class);
        $this->service->assertUsableForCheckout($code, 50);
    }
}
