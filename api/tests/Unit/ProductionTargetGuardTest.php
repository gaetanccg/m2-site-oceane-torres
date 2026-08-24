<?php

namespace Tests\Unit;

use App\Support\ProductionTargetGuard;
use App\Support\ProductionTargets;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Le garde-fou qui aurait empeche l'incident du 22 août 2026 : un conteneur portant les
 * identifiants de production avec APP_ENV=local, et RefreshDatabase par-dessus.
 *
 * Ces tests appellent le garde-fou directement, sans amorcer l'application : ils doivent
 * rester vrais meme si la configuration reelle change.
 */
class ProductionTargetGuardTest extends TestCase
{
    /** Configuration de developpement saine, dont chaque test derive. */
    private function saine(array $ecarts = []): callable
    {
        $valeurs = [
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => 'aws-0-eu-central-1.pooler.supabase.com',
            'database.connections.pgsql.username' => 'postgres.koimluuhjlhslwlxiesb',
            'filesystems.disks.minio.bucket' => 'galleries-preprod',
            'sumup.environment' => 'sandbox',
            'sentry.environment' => 'local',
            'sentry.dsn' => '',
            ...$ecarts,
        ];

        return fn (string $cle) => $valeurs[$cle] ?? null;
    }

    public function test_une_configuration_de_preproduction_passe(): void
    {
        ProductionTargetGuard::assert('local', $this->saine());

        $this->expectNotToPerformAssertions();
    }

    /** Le scenario exact de l'incident. */
    public function test_les_identifiants_de_production_sur_un_env_local_sont_refuses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/touche la PRODUCTION/');

        ProductionTargetGuard::assert('local', $this->saine([
            'database.connections.pgsql.username' => 'postgres.'.ProductionTargets::SUPABASE_PROJECT,
        ]));
    }

    public function test_l_hote_de_production_est_refuse(): void
    {
        $this->expectException(RuntimeException::class);

        ProductionTargetGuard::assert('local', $this->saine([
            'database.connections.pgsql.host' => ProductionTargets::DATABASE_HOST,
        ]));
    }

    /** Le serveur MinIO est partage : seul le bucket distingue les deux environnements. */
    public function test_le_bucket_de_production_est_refuse(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/bucket MinIO/');

        ProductionTargetGuard::assert('local', $this->saine([
            'filesystems.disks.minio.bucket' => ProductionTargets::STORAGE_BUCKET,
        ]));
    }

    /**
     * `config('sumup.environment')` vaut « production » quand SUMUP_ENV n'est pas defini :
     * chercher une valeur interdite laisserait donc passer une configuration muette.
     */
    public function test_un_sumup_non_declare_sandbox_est_refuse(): void
    {
        foreach ([null, '', 'production', 'live'] as $valeur) {
            try {
                ProductionTargetGuard::assert('local', $this->saine(['sumup.environment' => $valeur]));
                $this->fail(sprintf('sumup.environment = %s aurait du etre refuse.', var_export($valeur, true)));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('sandbox', $e->getMessage());
            }
        }
    }

    public function test_une_supervision_etiquetee_production_est_refusee(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/supervision/');

        ProductionTargetGuard::assert('local', $this->saine(['sentry.environment' => 'production']));
    }

    /**
     * Le poste portait l'etiquette « local » ET le DSN d'un projet Sentry deploye : les
     * exceptions de developpement partaient donc dans la supervision d'un environnement
     * reel. L'etiquette seule ne protegeait rien — c'est pourquoi on exige un DSN VIDE.
     */
    public function test_un_dsn_sentry_est_refuse_hors_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/SENTRY_LARAVEL_DSN doit etre vide/');

        ProductionTargetGuard::assert('local', $this->saine([
            'sentry.dsn' => 'https://cle@o4511874201223168.ingest.de.sentry.io/4511942022856784',
        ]));
    }

    /** Le message doit nommer le projet vise, sans divulguer la cle du DSN. */
    public function test_le_message_nomme_le_projet_sans_divulguer_la_cle(): void
    {
        try {
            ProductionTargetGuard::assert('local', $this->saine([
                'sentry.dsn' => 'https://cle_secrete@o1.ingest.de.sentry.io/4511942022856784',
            ]));
            $this->fail('Le garde-fou aurait du refuser.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('4511942022856784', $e->getMessage());
            $this->assertStringNotContainsString('cle_secrete', $e->getMessage());
        }
    }

    public function test_un_dsn_vide_ou_blanc_passe(): void
    {
        foreach (['', '   '] as $vide) {
            ProductionTargetGuard::assert('local', $this->saine(['sentry.dsn' => $vide]));
        }

        $this->expectNotToPerformAssertions();
    }

    /** La production, elle, doit pouvoir viser la production. */
    public function test_le_garde_fou_se_retire_en_production(): void
    {
        ProductionTargetGuard::assert('production', $this->saine([
            'database.connections.pgsql.host' => ProductionTargets::DATABASE_HOST,
            'database.connections.pgsql.username' => 'postgres.'.ProductionTargets::SUPABASE_PROJECT,
            'filesystems.disks.minio.bucket' => ProductionTargets::STORAGE_BUCKET,
            'sumup.environment' => 'live',
            'sentry.environment' => 'production',
            'sentry.dsn' => 'https://cle@o1.ingest.de.sentry.io/999',
        ]));

        $this->expectNotToPerformAssertions();
    }

    /** Y compris l'environnement de test : lui aussi doit rester loin de la production. */
    public function test_l_environnement_de_test_est_soumis_au_meme_garde_fou(): void
    {
        $this->expectException(RuntimeException::class);

        ProductionTargetGuard::assert('testing', $this->saine([
            'filesystems.disks.minio.bucket' => ProductionTargets::STORAGE_BUCKET,
        ]));
    }

    public function test_le_message_nomme_toutes_les_cibles_detectees(): void
    {
        try {
            ProductionTargetGuard::assert('local', $this->saine([
                'database.connections.pgsql.host' => ProductionTargets::DATABASE_HOST,
                'database.connections.pgsql.username' => 'postgres.'.ProductionTargets::SUPABASE_PROJECT,
                'filesystems.disks.minio.bucket' => ProductionTargets::STORAGE_BUCKET,
            ]));
            $this->fail('Le garde-fou aurait du refuser.');
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString(ProductionTargets::SUPABASE_PROJECT, $message);
            $this->assertStringContainsString(ProductionTargets::DATABASE_HOST, $message);
            $this->assertStringContainsString(ProductionTargets::STORAGE_BUCKET, $message);
            // Le message doit dire quoi faire, pas seulement ce qui est faux.
            $this->assertStringContainsString('--force-recreate', $message);
        }
    }

    /** Aucune variable d'environnement ne doit desactiver le garde-fou. */
    public function test_aucune_echappatoire_par_variable_d_environnement(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Support/ProductionTargetGuard.php');

        $this->assertStringNotContainsString('getenv', $source);
        $this->assertStringNotContainsString('$_ENV', $source);
        $this->assertStringNotContainsString('$_SERVER', $source);
        $this->assertStringNotContainsString('env(', $source);
    }
}
