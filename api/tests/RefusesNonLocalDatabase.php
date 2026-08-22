<?php

namespace Tests;

use RuntimeException;

/**
 * RefreshDatabase execute `migrate:fresh`, qui supprime toutes les tables. Rien dans
 * PHPUnit ni dans Laravel ne verifie vers quelle base cela pointe.
 *
 * La cible peut deriver sans que rien ne le signale, et phpunit.xml ne protege pas :
 * ses `<env>`, meme avec force="true", n'ecrivent que getenv() et $_ENV, alors que le
 * helper env() de Laravel interroge $_SERVER en premier. Une variable d'environnement
 * reelle — celles que docker-compose fige au demarrage d'un conteneur, et qui survivent
 * a toute modification ulterieure du .env — l'emporte donc sur la configuration de test.
 *
 * La verification lit la configuration effectivement resolue, apres le demarrage de
 * l'application et avant setUpTraits(), donc avant toute migration.
 */
trait RefusesNonLocalDatabase
{
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->assertTestDatabaseIsLocal();
    }

    private function assertTestDatabaseIsLocal(): void
    {
        $connection = (string) config('database.default');
        $host = (string) config("database.connections.{$connection}.host");
        $database = (string) config("database.connections.{$connection}.database");

        if (LocalDatabaseHosts::allows($host)) {
            return;
        }

        throw new RuntimeException(
            "Suite de tests refusée : la connexion « {$connection} » cible l'hôte "
            ."« {$host} » (base « {$database} »), qui n'est pas une base locale.\n"
            ."RefreshDatabase supprimerait toutes les tables de cette base.\n\n"
            ."phpunit.xml ne suffit pas à s'en protéger : ses <env> n'écrivent que\n"
            ."getenv() et \$_ENV, alors que env() de Laravel lit \$_SERVER en premier.\n"
            ."Une variable d'environnement réelle l'emporte donc, y compris avec force=\"true\".\n\n"
            ."Les tests se lancent depuis l'hôte (« cd api && php artisan test »), pas dans le\n"
            ."conteneur : 127.0.0.1:55432 n'y désigne pas postgres-test. Vérifier avec\n"
            ."« docker compose exec laravel env | grep DB_ ».\n\n"
            .'Hôtes acceptés : '.implode(', ', LocalDatabaseHosts::ALLOWED)
        );
    }
}
