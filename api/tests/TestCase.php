<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authentifie un utilisateur admin (middleware `admin`) et le retourne.
     */
    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * Authentifie un client standard et le retourne.
     */
    protected function actingAsClient(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Remplace le disque `minio` par un faux disque en mémoire et le retourne.
     * À appeler dans tout test qui lit/écrit du stockage OU qui sérialise une
     * Photo (les URLs signées appellent MinIO à la sérialisation JSON).
     */
    protected function fakeMinio(): Filesystem
    {
        return Storage::fake('minio');
    }

    /**
     * Stubbe les appels HTTP SumUp. Passer les corps de réponse par endpoint.
     *
     * @param  array<string, mixed>  $createCheckout  Réponse de POST /v0.1/checkouts
     * @param  array<string, mixed>  $getCheckout  Réponse de GET  /v0.1/checkouts/*
     */
    protected function fakeSumUp(array $createCheckout = [], array $getCheckout = []): void
    {
        Http::fake([
            'api.sumup.com/v0.1/checkouts/*' => Http::response($getCheckout ?: [
                'id' => 'chk_test',
                'status' => 'PENDING',
            ]),
            'api.sumup.com/v0.1/checkouts' => Http::response($createCheckout ?: [
                'id' => 'chk_test',
                'status' => 'PENDING',
            ]),
        ]);
    }
}
