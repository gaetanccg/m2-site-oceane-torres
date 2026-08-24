<?php

namespace App\Support;

/**
 * Hotes de base de donnees consideres comme LOCAUX.
 *
 * Dans `app/` et non dans `tests/` : l'application en a besoin autant que la suite de
 * tests, et `Tests\` n'est charge qu'en developpement (autoload-dev). Une seule liste, pour
 * que les deux garde-fous ne puissent pas divergerentre eux.
 */
final class LocalDatabaseHosts
{
    /** @var list<string> */
    public const ALLOWED = [
        '127.0.0.1', 'localhost', '::1',
        'postgres', 'postgres-dev', 'postgres-test',
        'ot-postgres-dev', 'ot-postgres-test',
        'host.docker.internal',
    ];

    public static function allows(string $host): bool
    {
        return in_array(trim($host), self::ALLOWED, true);
    }
}
