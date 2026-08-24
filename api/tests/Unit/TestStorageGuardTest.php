<?php

namespace Tests\Unit;

use Tests\LocalStorageEndpoints;
use Tests\TestCase;

/**
 * La suite de tests resolvait le vrai bucket « galleries-preprod » sur le vrai serveur
 * MinIO. Un seul test oubliant Storage::fake() y ecrivait ou y supprimait des fichiers.
 */
class TestStorageGuardTest extends TestCase
{
    public function test_les_tests_ne_designent_aucun_stockage_reel(): void
    {
        $endpoint = (string) config('filesystems.disks.minio.endpoint');

        $this->assertTrue(
            $endpoint === '' || LocalStorageEndpoints::allows($endpoint),
            "Les tests pointent sur « {$endpoint} », un stockage reel.",
        );
    }

    public function test_le_bucket_de_test_n_est_aucun_bucket_reel(): void
    {
        $this->assertNotContains(
            config('filesystems.disks.minio.bucket'),
            ['galleries', 'galleries-preprod'],
        );
    }

    public function test_les_points_d_acces_reels_sont_refuses(): void
    {
        foreach ([
            'https://s3.oceanetorresphotographie.fr',
            'https://s3.amazonaws.com',
            'https://minio.exemple.fr:9000',
        ] as $reel) {
            $this->assertFalse(
                LocalStorageEndpoints::allows($reel),
                "« {$reel} » ne doit pas etre accepte.",
            );
        }
    }

    public function test_les_points_d_acces_locaux_sont_acceptes(): void
    {
        foreach ([
            'http://127.0.0.1:9000',
            'http://localhost:9000/',
            'http://minio:9000',
            'http://host.docker.internal:9100',
        ] as $local) {
            $this->assertTrue(LocalStorageEndpoints::allows($local), "« {$local} » doit etre accepte.");
        }
    }
}
