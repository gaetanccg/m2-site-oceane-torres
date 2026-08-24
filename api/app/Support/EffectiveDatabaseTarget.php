<?php

namespace App\Support;

use Illuminate\Support\ConfigurationUrlParser;

/**
 * La cible de base de donnees REELLEMENT utilisee, DB_URL comprise.
 *
 * Pourquoi cette classe existe : `config/database.php` declare `'url' => env('DB_URL')`.
 * Quand DB_URL est definie, Laravel l'utilise et les entrees `host` / `username` de la
 * configuration deviennent sans effet — mais elles gardent leurs anciennes valeurs. Un
 * garde-fou qui lit `host` voit donc la preproduction pendant que l'application se connecte
 * a la production.
 *
 * Mesure : avec DB_URL vers la production, l'application demarrait sans broncher et la
 * suite de tests s'executait. Une seule variable d'environnement suffisait a rendre les
 * deux garde-fous aveugles.
 *
 * On utilise le parseur de Laravel, et non le notre : c'est la seule facon de voir
 * exactement ce a quoi le framework se connectera.
 */
final class EffectiveDatabaseTarget
{
    /**
     * @param  array<string, mixed>  $connexion  la configuration d'une connexion
     * @return array{host: string, username: string, database: string, port: string}
     */
    public static function resolve(array $connexion): array
    {
        $resolue = (new ConfigurationUrlParser)->parseConfiguration($connexion);

        return [
            'host' => (string) ($resolue['host'] ?? ''),
            'username' => (string) ($resolue['username'] ?? ''),
            'database' => (string) ($resolue['database'] ?? ''),
            'port' => (string) ($resolue['port'] ?? ''),
        ];
    }

    /**
     * Tout ce qui identifie la cible, en une seule chaine — pour y chercher un marqueur
     * sans avoir a deviner dans quel champ il se trouve.
     */
    public static function fingerprint(array $connexion): string
    {
        $resolue = self::resolve($connexion);

        return strtolower(implode('|', [
            $resolue['host'],
            $resolue['username'],
            $resolue['database'],
            (string) ($connexion['url'] ?? ''),
        ]));
    }
}
