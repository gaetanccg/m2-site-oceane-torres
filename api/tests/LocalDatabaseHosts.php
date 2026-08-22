<?php

namespace Tests;

final class LocalDatabaseHosts
{
    public const ALLOWED = [
        '127.0.0.1', 'localhost', '::1',
        'postgres', 'postgres-dev', 'postgres-test',
        'ot-postgres-dev', 'ot-postgres-test',
        'host.docker.internal',
    ];

    public static function allows(string $host): bool
    {
        return in_array($host, self::ALLOWED, true);
    }
}
