<?php

namespace Tests\Feature\School;

use App\Jobs\GeneratePrivacyExportJob;
use App\Jobs\GenerateSchoolSessionExportJob;
use App\Jobs\ProcessSchoolSessionJob;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Ces trois jobs marquaient un statut `failed` puis avalaient l'exception : la file croyait
 * avoir reussi, failed_jobs restait vide, et la supervision ne voyait rien. Un export ou un
 * traitement pouvait echouer sans que personne ne l'apprenne.
 */
class JobFailureVisibilityTest extends TestCase
{
    public static function jobsLongs(): array
    {
        return [
            'traitement de session' => [ProcessSchoolSessionJob::class],
            'export scolaire' => [GenerateSchoolSessionExportJob::class],
            'export RGPD' => [GeneratePrivacyExportJob::class],
        ];
    }

    #[DataProvider('jobsLongs')]
    public function test_le_job_relance_l_exception_apres_avoir_marque_le_statut(string $job): void
    {
        $source = file_get_contents((new ReflectionClass($job))->getFileName());

        $this->assertStringContainsString(
            'throw $e;',
            $source,
            class_basename($job).' doit relancer : sinon la file croit avoir réussi.'
        );
    }

    /**
     * $tries = 1 est ce qui rend la relance sans danger : le job part directement en
     * failed_jobs sans reexecuter un traitement qui n'est pas idempotent.
     */
    #[DataProvider('jobsLongs')]
    public function test_le_job_ne_se_rejoue_pas(string $job): void
    {
        $this->assertSame(
            1,
            (new ReflectionClass($job))->getDefaultProperties()['tries'] ?? null,
            class_basename($job).' doit rester a une seule tentative : le traitement n\'est pas idempotent.'
        );
    }

    #[DataProvider('jobsLongs')]
    public function test_le_job_expose_un_hook_failed(string $job): void
    {
        $this->assertTrue(
            method_exists($job, 'failed'),
            class_basename($job).' doit marquer le statut même quand la relance mène en failed_jobs.'
        );
    }
}
