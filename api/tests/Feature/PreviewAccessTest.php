<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * #34 — /api/images/preview et /api/images/thumbnail ne verifiaient rien : connaitre
 * l'UUID d'une photo suffisait, definitivement. Regenerer le code de partage ne
 * revoquait donc rien sur ce chemin.
 */
class PreviewAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMinio();
    }

    private function photoIn(string $type, bool $published = true): array
    {
        $gallery = Gallery::factory()->create(['type' => $type, 'is_published' => $published]);
        $photo = Photo::factory()->create(['gallery_id' => $gallery->id, 'is_processed' => false]);

        return [$gallery, $photo];
    }

    private function assertAutorise(string $url): void
    {
        $this->assertNotSame(403, $this->get($url)->status(), "L'accès à {$url} doit être autorisé.");
    }

    public static function versions(): array
    {
        return ['preview' => ['preview'], 'thumbnail' => ['thumbnail']];
    }

    #[DataProvider('versions')]
    public function test_sans_credential_une_galerie_privee_est_refusee(string $version): void
    {
        [, $photo] = $this->photoIn('private');

        $this->get("/api/images/{$version}/{$photo->id}")->assertStatus(403);
    }

    #[DataProvider('versions')]
    public function test_le_code_de_partage_donne_acces(string $version): void
    {
        [$gallery, $photo] = $this->photoIn('private');

        $this->assertAutorise("/api/images/{$version}/{$photo->id}?token={$gallery->share_code}");
    }

    #[DataProvider('versions')]
    public function test_le_jeton_d_acces_donne_acces_aussi(string $version): void
    {
        [$gallery, $photo] = $this->photoIn('private');

        $this->assertAutorise("/api/images/{$version}/{$photo->id}?token={$gallery->access_token}");
    }

    /** C'est tout l'objet de #34. */
    #[DataProvider('versions')]
    public function test_regenerer_le_code_de_partage_revoque_l_acces(string $version): void
    {
        [$gallery, $photo] = $this->photoIn('private');
        $ancienCode = $gallery->share_code;

        $this->assertAutorise("/api/images/{$version}/{$photo->id}?token={$ancienCode}");

        $gallery->regenerateShareCode();

        $this->get("/api/images/{$version}/{$photo->id}?token={$ancienCode}")->assertStatus(403);
        $this->assertAutorise("/api/images/{$version}/{$photo->id}?token={$gallery->fresh()->share_code}");
    }

    #[DataProvider('versions')]
    public function test_une_galerie_publiee_d_evenement_reste_ouverte(string $version): void
    {
        [, $photo] = $this->photoIn('event');

        $this->assertAutorise("/api/images/{$version}/{$photo->id}");
    }

    #[DataProvider('versions')]
    public function test_une_galerie_d_evenement_non_publiee_est_fermee(string $version): void
    {
        [, $photo] = $this->photoIn('event', published: false);

        $this->get("/api/images/{$version}/{$photo->id}")->assertStatus(403);
    }

    #[DataProvider('versions')]
    public function test_un_credential_errone_est_refuse(string $version): void
    {
        [, $photo] = $this->photoIn('private');

        $this->get("/api/images/{$version}/{$photo->id}?token=ABC123")->assertStatus(403);
    }

    /** L'URL de repli ne doit jamais porter access_token : ce serait rouvrir l'escalade. */
    public function test_l_url_de_repli_porte_le_code_de_partage_pas_le_jeton(): void
    {
        [$gallery, $photo] = $this->photoIn('private');

        $url = $photo->fresh()->preview_url;

        $this->assertStringContainsString("token={$gallery->share_code}", $url);
        $this->assertStringNotContainsString($gallery->access_token, $url);
    }

    public function test_le_code_de_partage_ne_donne_pas_acces_aux_derives_propres(): void
    {
        [$gallery, $photo] = $this->photoIn('private');
        $photo->update(['is_downloadable' => true]);

        $this->get("/api/images/clean/{$photo->id}?token={$gallery->share_code}")
            ->assertStatus(403);
    }
}
