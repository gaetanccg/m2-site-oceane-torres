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
            'database.connections.pgsql.url' => null,
            'database.connections.pgsql.host' => 'postgres-dev',
            'database.connections.pgsql.username' => 'postgres',
            'filesystems.disks.minio.bucket' => 'galleries-preprod',
            'sumup.environment' => 'sandbox',
            'sentry.environment' => 'local',
            'sentry.dsn' => '',
            'supervision.healthchecks.rgpd_cleanup_url' => '',
            'supervision.healthchecks.reconcile_orders_url' => '',
            ...$ecarts,
        ];

        return fn (string $cle) => $valeurs[$cle] ?? null;
    }

    public function test_une_configuration_locale_saine_passe(): void
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
     * `config/database.php` declare `'url' => env('DB_URL')`. Quand DB_URL est definie,
     * Laravel l'utilise et `host` / `username` deviennent inertes SANS etre effaces : le
     * garde voyait la preproduction pendant que l'application visait la production. Mesure
     * avant correctif : l'application demarrait, et la suite de tests s'executait.
     */
    public function test_db_url_ne_contourne_pas_le_garde_fou(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/'.ProductionTargets::SUPABASE_PROJECT.'/');

        ProductionTargetGuard::assert('local', $this->saine([
            'database.connections.pgsql.url' => 'postgresql://postgres.'
                .ProductionTargets::SUPABASE_PROJECT.':secret@'
                .ProductionTargets::DATABASE_HOST.':5432/postgres',
        ]));
    }

    /**
     * Supabase expose aussi une connexion DIRECTE, `db.<ref>.supabase.co`, dont
     * l'utilisateur est simplement « postgres » — sans le ref. Comparer l'hote au pooler
     * ne detectait donc rien : le marqueur doit etre cherche dans toute la connexion.
     */
    public function test_l_hote_direct_supabase_est_reconnu(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/'.ProductionTargets::SUPABASE_PROJECT.'/');

        ProductionTargetGuard::assert('local', $this->saine([
            'database.connections.pgsql.host' => 'db.'.ProductionTargets::SUPABASE_PROJECT.'.supabase.co',
            'database.connections.pgsql.username' => 'postgres',
        ]));
    }

    /**
     * La preproduction tourne avec APP_ENV=production : sa propre DB_URL doit donc passer,
     * alors que la meme URL en « local » est refusee (voir le test precedent). C'est
     * l'ENVIRONNEMENT declare qui decide du perimetre autorise, pas la cible seule.
     */
    public function test_la_db_url_de_preproduction_passe_sous_son_propre_environnement(): void
    {
        ProductionTargetGuard::assert('production', $this->saine([
            'database.connections.pgsql.url' => 'postgresql://postgres.koimluuhjlhslwlxiesb:secret@aws-0-eu-central-1.pooler.supabase.com:6543/postgres',
        ]));

        $this->expectNotToPerformAssertions();
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

    /**
     * Un ping emis depuis un poste de developpement maintient le moniteur de production au
     * VERT : une panne reelle ne declencherait plus aucune alerte. Pire que l'absence de
     * supervision, puisqu'on croit etre couvert.
     */
    public function test_une_url_de_ping_de_supervision_est_refusee_hors_production(): void
    {
        foreach ([
            'supervision.healthchecks.rgpd_cleanup_url',
            'supervision.healthchecks.reconcile_orders_url',
        ] as $cle) {
            try {
                ProductionTargetGuard::assert('local', $this->saine([$cle => 'https://hc-ping.com/'.md5($cle)]));
                $this->fail("Une URL de ping sur « {$cle} » aurait du etre refusee.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('doit etre vide hors production', $e->getMessage());
            }
        }
    }

    /**
     * Interdire la seule production laissait le poste ecrire dans la PREPRODUCTION, qu'un
     * migrate:fresh aurait videe de la meme façon. En « local », la base doit etre locale.
     */
    public function test_en_local_une_base_distante_est_refusee_meme_hors_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/la base doit etre locale/');

        // Hote de PREPRODUCTION : aucune cible de production n'est detectee.
        ProductionTargetGuard::assert('local', $this->saine([
            'database.connections.pgsql.host' => 'aws-0-eu-central-1.pooler.supabase.com',
        ]));
    }

    public function test_en_local_les_hotes_locaux_passent(): void
    {
        foreach (['127.0.0.1', 'localhost', 'postgres-dev', 'host.docker.internal'] as $hote) {
            ProductionTargetGuard::assert('local', $this->saine([
                'database.connections.pgsql.host' => $hote,
                'database.connections.pgsql.username' => 'postgres',
            ]));
        }

        $this->expectNotToPerformAssertions();
    }

    /** Et DB_URL ne doit pas permettre de sortir du perimetre local non plus. */
    public function test_en_local_une_db_url_distante_est_refusee(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/la base doit etre locale/');

        ProductionTargetGuard::assert('local', $this->saine([
            'database.connections.pgsql.url' => 'postgresql://postgres:x@aws-0-eu-central-1.pooler.supabase.com:6543/postgres',
        ]));
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
            'supervision.healthchecks.rgpd_cleanup_url' => 'https://hc-ping.com/reel',
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
