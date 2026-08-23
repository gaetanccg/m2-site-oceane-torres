<?php

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Tests\TestCase;

/**
 * La suite remontait a Sentry : les exceptions volontaires des tests atterrissaient dans
 * le projet reel, consommant le quota et noyant les vraies alertes.
 */
class SentryIsSilentInTestsTest extends TestCase
{
    public function test_aucun_dsn_sentry_pendant_les_tests(): void
    {
        $this->assertEmpty(
            config('sentry.dsn'),
            "phpunit.xml doit vider SENTRY_LARAVEL_DSN et SENTRY_DSN, sinon la suite\n".
            'remonte dans le projet Sentry de production.'
        );
    }

    /** Un cas metier a message affichable n'est pas une erreur technique. */
    public function test_business_exception_n_est_pas_remontee(): void
    {
        $handler = app(ExceptionHandler::class);

        $this->assertFalse(
            $handler->shouldReport(new BusinessException('Le panier est vide.')),
            'BusinessException ne doit pas être remontée : 17 sites la lèvent sur des parcours normaux.'
        );
    }

    public function test_une_erreur_technique_reste_remontee(): void
    {
        $handler = app(ExceptionHandler::class);

        $this->assertTrue($handler->shouldReport(new \RuntimeException('panne réelle')));
    }
}
