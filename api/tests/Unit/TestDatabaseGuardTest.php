<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\LocalDatabaseHosts;
use Tests\RefusesNonLocalDatabase;

/**
 * L'allowlist est la seule protection reelle : phpunit.xml n'en est pas une, ses
 * <env> n'ecrivant que getenv() et $_ENV alors que env() de Laravel lit $_SERVER
 * en premier.
 */
class TestDatabaseGuardTest extends TestCase
{
    /** @param string $host */
    public function test_accepte_les_hotes_locaux(): void
    {
        foreach (['127.0.0.1', 'localhost', '::1', 'postgres-test', 'postgres-dev', 'host.docker.internal'] as $host) {
            $this->assertTrue(
                LocalDatabaseHosts::allows($host),
                "« {$host} » devrait être accepté."
            );
        }
    }

    public function test_refuse_les_hotes_distants(): void
    {
        $remotes = [
            'aws-1-eu-west-1.pooler.supabase.com',
            'aws-0-eu-central-1.pooler.supabase.com',
            'db.ekhlybdoblhjigpnpbff.supabase.co',
            '10.0.0.5',
            'preprod-api.oceanetorresphotographie.fr',
            '',
        ];

        foreach ($remotes as $host) {
            $this->assertFalse(
                LocalDatabaseHosts::allows($host),
                "« {$host} » ne devrait pas être accepté."
            );
        }
    }

    public function test_la_garde_est_branchee_sur_le_TestCase_de_base(): void
    {
        $this->assertContains(
            RefusesNonLocalDatabase::class,
            class_uses(\Tests\TestCase::class),
            'Tests\\TestCase doit utiliser le trait, sinon la garde ne protège rien.'
        );
    }

    public function test_la_garde_s_execute_avant_les_traits_donc_avant_migrate_fresh(): void
    {
        $this->assertTrue(
            method_exists(RefusesNonLocalDatabase::class, 'refreshApplication'),
            'La garde doit passer par refreshApplication() : setUpTraits() — et donc '
            .'RefreshDatabase — s\'exécute juste après.'
        );
    }
}
