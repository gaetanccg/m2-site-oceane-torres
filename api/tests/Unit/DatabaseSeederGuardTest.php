<?php

namespace Tests\Unit;

use App\Support\ProductionTargets;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * Le seeder ecrase des donnees. Sur le NAS, ou APP_ENV vaut « production », le garde-fou
 * applicatif se retire volontairement : ce garde-ci est alors le seul filet.
 */
class DatabaseSeederGuardTest extends TestCase
{
    private function assertRefus(array $connexion): void
    {
        config(['database.default' => 'pgsql', 'database.connections.pgsql' => $connexion]);

        $methode = new ReflectionMethod(DatabaseSeeder::class, 'assertTargetIsLocal');
        $methode->setAccessible(true);
        $methode->invoke(new DatabaseSeeder);
    }

    public function test_une_cible_locale_passe(): void
    {
        $this->assertRefus(['driver' => 'pgsql', 'host' => 'postgres-dev', 'database' => 'oceane_dev']);

        $this->addToAssertionCount(1);
    }

    public function test_un_hote_distant_est_refuse(): void
    {
        $this->expectException(RuntimeException::class);

        $this->assertRefus([
            'driver' => 'pgsql',
            'host' => ProductionTargets::DATABASE_HOST,
            'database' => 'postgres',
        ]);
    }

    /**
     * Le piege du 22/08 : DB_URL rend `host` inerte SANS l'effacer. Une configuration par
     * defaut laisse « 127.0.0.1 » en place — accepte — pendant que Laravel ecrit en production.
     */
    public function test_db_url_ne_contourne_pas_le_garde_fou(): void
    {
        $this->expectException(RuntimeException::class);

        $this->assertRefus([
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'database' => 'oceane_dev',
            'url' => 'postgresql://postgres.'.ProductionTargets::SUPABASE_PROJECT
                .':motdepasse@'.ProductionTargets::DATABASE_HOST.':5432/postgres',
        ]);
    }

    /** Une seule liste d'hotes locaux : deux copies finissent par diverger, et l'une ment. */
    public function test_le_seeder_ne_tient_pas_sa_propre_liste_d_hotes(): void
    {
        $source = file_get_contents(__DIR__.'/../../database/seeders/DatabaseSeeder.php');

        $this->assertStringNotContainsString('LOCAL_DB_HOSTS', $source);
        $this->assertStringContainsString('LocalDatabaseHosts::allows', $source);
        $this->assertStringContainsString('EffectiveDatabaseTarget::resolve', $source);
    }
}
