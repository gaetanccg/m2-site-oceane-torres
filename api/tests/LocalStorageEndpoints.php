<?php

namespace Tests;

/**
 * Points d'accès de stockage objet acceptables pour la suite de tests.
 *
 * Classe séparée pour la même raison que LocalDatabaseHosts : une constante déclarée dans
 * un trait n'est pas accessible via le trait lui-même.
 */
final class LocalStorageEndpoints
{
    /** @var list<string> */
    public const ALLOWED = [
        'http://127.0.0.1:9000',
        'http://localhost:9000',
        'http://minio:9000',
        'http://ot-minio:9000',
        'http://host.docker.internal:9000',
    ];

    public static function allows(string $endpoint): bool
    {
        $endpoint = rtrim(trim($endpoint), '/');

        foreach (self::ALLOWED as $autorise) {
            if (strcasecmp($endpoint, $autorise) === 0) {
                return true;
            }
        }

        // Tout hote local, quel que soit le port : un MinIO de developpement peut en
        // choisir un autre.
        $hote = parse_url($endpoint, PHP_URL_HOST);

        return in_array($hote, ['127.0.0.1', 'localhost', '::1', 'minio', 'ot-minio', 'host.docker.internal'], true);
    }
}
