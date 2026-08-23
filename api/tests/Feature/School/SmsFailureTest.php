<?php

namespace Tests\Feature\School;

use App\Exceptions\Sms\SmsPermanentFailure;
use App\Exceptions\Sms\SmsTransientFailure;
use App\Jobs\SendSchoolSessionSmsJob;
use App\Jobs\SendSmsJob;
use App\Services\BrevoSmsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Le service rendait un booleen : le job reessayait donc TOUT, y compris un numero
 * invalide. Un SMS se paie, et trois tentatives pour un refus certain sont du gaspillage.
 */
class SmsFailureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.brevo.api_key' => 'cle-de-test', 'services.brevo.sms_sender' => 'OceanePhoto']);
    }

    private function service(): BrevoSmsService
    {
        return app(BrevoSmsService::class);
    }

    public function test_un_numero_invalide_est_un_echec_permanent(): void
    {
        Http::fake();

        $this->expectException(SmsPermanentFailure::class);

        $this->service()->send('pas-un-numero', 'Bonjour');
    }

    public function test_une_cle_absente_est_un_echec_permanent(): void
    {
        config(['services.brevo.api_key' => null]);

        $this->expectException(SmsPermanentFailure::class);

        $this->service()->send('0612345678', 'Bonjour');
    }

    public function test_un_expediteur_invalide_est_un_echec_permanent(): void
    {
        config(['services.brevo.sms_sender' => 'NomBeaucoupTropLong']);

        $this->expectException(SmsPermanentFailure::class);

        $this->service()->send('0612345678', 'Bonjour');
    }

    public function test_un_refus_de_brevo_en_4xx_est_permanent(): void
    {
        Http::fake(['*' => Http::response(['message' => 'invalid recipient'], 400)]);

        $this->expectException(SmsPermanentFailure::class);

        $this->service()->send('0612345678', 'Bonjour');
    }

    public function test_une_panne_serveur_est_transitoire(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->expectException(SmsTransientFailure::class);

        $this->service()->send('0612345678', 'Bonjour');
    }

    /** 429 est une limite de debit : reessayer plus tard a du sens. */
    public function test_une_limite_de_debit_est_transitoire(): void
    {
        Http::fake(['*' => Http::response('', 429)]);

        $this->expectException(SmsTransientFailure::class);

        $this->service()->send('0612345678', 'Bonjour');
    }

    public function test_un_envoi_reussi_ne_leve_rien(): void
    {
        Http::fake(['*' => Http::response(['messageId' => 1], 201)]);

        $this->service()->send('0612345678', 'Bonjour');

        $this->assertTrue(true);
    }

    /** L'ancien nom reste deserialisable : un job deja en file survit au deploiement. */
    public function test_l_ancien_nom_de_job_reste_utilisable(): void
    {
        $this->assertInstanceOf(SendSmsJob::class, new SendSchoolSessionSmsJob('0612345678', 'x'));
    }

    public function test_le_job_reste_a_trois_tentatives_pour_les_echecs_transitoires(): void
    {
        $this->assertSame(3, (new SendSmsJob('0612345678', 'x'))->tries);
    }
}
