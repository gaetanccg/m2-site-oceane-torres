<?php

namespace Tests\Feature\Auth;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    private function headers(): TestResponse
    {
        return $this->getJson('/api/prestations');
    }

    public function test_x_xss_protection_ne_figure_plus(): void
    {
        // Obsolete, retire par tous les navigateurs, et son mode block a introduit des
        // failles par le passe.
        $this->headers()->assertHeaderMissing('X-XSS-Protection');
    }

    public function test_les_en_tetes_utiles_sont_presents(): void
    {
        $response = $this->headers();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /** L'API ne sert que du JSON : rien ne doit s'y charger ni l'encadrer. */
    public function test_la_csp_de_l_api_interdit_tout_chargement(): void
    {
        $csp = $this->headers()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    /**
     * Une directive script-src sur une reponse JSON ne protege rien : la politique du
     * site est posee par Render sur le HTML.
     */
    public function test_la_csp_de_l_api_ne_pretend_pas_proteger_le_site(): void
    {
        $csp = $this->headers()->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('googletagmanager', $csp);
        $this->assertStringNotContainsString('sumup', $csp);
    }
}
