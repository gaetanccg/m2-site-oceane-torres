<?php

namespace Tests;

use App\Support\LocalDatabaseHosts as Source;

/**
 * Alias historique. La liste vit dans App\Support\LocalDatabaseHosts, pour que
 * l'application et la suite de tests partagent la meme — deux listes finiraient par
 * diverger, et c'est exactement ce genre d'ecart qui laisse passer un accident.
 */
final class LocalDatabaseHosts
{
    /** @var list<string> */
    public const ALLOWED = Source::ALLOWED;

    public static function allows(string $host): bool
    {
        return Source::allows($host);
    }
}
