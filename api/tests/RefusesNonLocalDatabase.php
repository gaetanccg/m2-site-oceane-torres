<?php

namespace Tests;

use App\Support\EffectiveDatabaseTarget;
use RuntimeException;

/**
 * La suite de tests ne doit toucher AUCUNE ressource reelle — ni base, ni stockage.
 *
 * RefreshDatabase execute `migrate:fresh`. phpunit.xml ne protege pas de la cible : ses
 * `<env>`, force="true" comprise, n'ecrivent que getenv() et $_ENV, alors que env() de
 * Laravel lit $_SERVER en premier. D'ou la lecture de la config RESOLUE, dans
 * refreshApplication() car setUpTraits() s'execute juste apres.
 *
 * Le volet stockage a ete ajoute apres coup : la base etait protegee, le disque non. Les
 * tests resolvaient le vrai bucket « galleries-preprod » sur le vrai serveur MinIO — un
 * seul test oubliant `Storage::fake()` suffisait a y ecrire ou a y supprimer. Meme classe
 * de defaut que l'incident du 22/08/2026, une couche au-dessus.
 */
trait RefusesNonLocalDatabase
{
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->assertTestDatabaseIsLocal();
        $this->assertTestStorageIsNotReal();
    }

    /**
     * `Storage::fake()` remplace le disque APRES ce controle : impossible de savoir ici si
     * un test le fera. On exige donc que la configuration elle-meme ne designe pas un
     * stockage reel — ce qui protege aussi les tests qui oublient de le simuler.
     */
    private function assertTestStorageIsNotReal(): void
    {
        $endpoint = (string) config('filesystems.disks.minio.endpoint');
        $bucket = (string) config('filesystems.disks.minio.bucket');

        if ($endpoint === '' || LocalStorageEndpoints::allows($endpoint)) {
            return;
        }

        throw new RuntimeException(
            "Suite de tests refusée : le disque « minio » pointe sur « {$endpoint} » "
            ."(bucket « {$bucket} »), un stockage réel.\n"
            ."Un seul test oubliant Storage::fake() y écrirait ou y supprimerait des\n"
            ."fichiers — des photos de clientes.\n\n"
            ."Comme pour la base, phpunit.xml ne suffit pas : dans un conteneur, la variable\n"
            ."d'environnement réelle l'emporte sur ses <env>.\n\n"
            .'Points d\'accès acceptés : '.implode(', ', LocalStorageEndpoints::ALLOWED)
        );
    }

    private function assertTestDatabaseIsLocal(): void
    {
        $connection = (string) config('database.default');

        // Sur la connexion RESOLUE : `config('...host')` reste renseigne quand DB_URL est
        // definie, alors que Laravel se connecte a l'URL. Mesure : avec DB_URL vers la
        // production, la suite entiere s'executait sans que ce garde-fou ne dise rien.
        $cible = EffectiveDatabaseTarget::resolve([
            'url' => config("database.connections.{$connection}.url"),
            'host' => config("database.connections.{$connection}.host"),
            'username' => config("database.connections.{$connection}.username"),
            'database' => config("database.connections.{$connection}.database"),
            'port' => config("database.connections.{$connection}.port"),
        ]);

        $host = $cible['host'];
        $database = $cible['database'];

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
