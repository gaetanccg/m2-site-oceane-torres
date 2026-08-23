<?php

namespace Tests\Feature\Cart;

use App\Exceptions\BusinessException;
use App\Models\GiftCode;
use App\Services\GiftCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftCodeMinimumTest extends TestCase
{
    use RefreshDatabase;

    private function code(?float $minimum): GiftCode
    {
        return GiftCode::factory()->create([
            'type' => 'fixed',
            'value' => 10,
            'min_order_amount' => $minimum,
            'is_active' => true,
            'max_uses' => null,
        ]);
    }

    private function service(): GiftCodeService
    {
        return app(GiftCodeService::class);
    }

    public function test_sans_minimum_le_code_s_applique(): void
    {
        $apercu = $this->service()->preview($this->code(null), 30.0);

        $this->assertTrue($apercu['valid']);
        $this->assertSame(10.0, $apercu['discount']);
    }

    /** La remise reste plafonnee au sous-total : on ne rembourse pas la difference. */
    public function test_la_remise_ne_depasse_pas_le_sous_total(): void
    {
        $apercu = $this->service()->preview($this->code(null), 5.0);

        $this->assertTrue($apercu['valid']);
        $this->assertSame(5.0, $apercu['discount']);
    }

    public function test_sous_le_minimum_le_code_est_refuse(): void
    {
        $apercu = $this->service()->preview($this->code(50.0), 30.0);

        $this->assertFalse($apercu['valid']);
        $this->assertSame(0.0, $apercu['discount']);
    }

    public function test_le_message_indique_le_montant_requis(): void
    {
        $apercu = $this->service()->preview($this->code(50.0), 30.0);

        $this->assertStringContainsString('50,00 €', $apercu['reason']);
        $this->assertStringContainsString('à partir de', $apercu['reason']);
    }

    public function test_au_minimum_exact_le_code_s_applique(): void
    {
        $this->assertTrue($this->service()->preview($this->code(50.0), 50.0)['valid']);
    }

    public function test_au_dessus_du_minimum_le_code_s_applique(): void
    {
        $this->assertTrue($this->service()->preview($this->code(50.0), 50.01)['valid']);
    }

    public function test_le_checkout_refuse_sous_le_minimum(): void
    {
        $this->expectException(BusinessException::class);

        $this->service()->assertUsableForCheckout($this->code(50.0), 30.0);
    }

    public function test_le_checkout_accepte_au_dela_du_minimum(): void
    {
        $remise = $this->service()->assertUsableForCheckout($this->code(50.0), 60.0);

        $this->assertSame(10.0, $remise);
    }

    /** Le minimum porte sur le sous-total, comme la remise : les frais de port n'y comptent pas. */
    public function test_le_minimum_ne_compte_pas_les_frais_de_port(): void
    {
        // 45 € d'articles + 10 € de port = 55 €, mais le sous-total reste sous les 50 €.
        $apercu = $this->service()->preview($this->code(50.0), 45.0);

        $this->assertFalse($apercu['valid']);
    }
}
