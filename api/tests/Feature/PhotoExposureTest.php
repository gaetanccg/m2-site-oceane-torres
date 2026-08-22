<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Les chemins de stockage MinIO sortaient sur les endpoints publics, dont
 * `file_path_thumbnail_clean` — le dérivé miniature SANS filigrane. Le bucket n'est pas
 * lisible anonymement (403 vérifié), donc ce n'était pas un contournement d'accès, mais
 * cela publiait la structure du bucket. Aucun de ces champs n'est lu par le front.
 */
class PhotoExposureTest extends TestCase
{
    use RefreshDatabase;

    private const CHEMINS_INTERNES = [
        'file_path_hd',
        'file_path_preview',
        'file_path_thumbnail',
        'file_path_thumbnail_clean',
        'metadata',
    ];

    /** Champs dont le front dépend : les retirer casserait une vue. */
    private const REQUIS_PAR_LE_FRONT = [
        'id', 'title', 'display_url', 'preview_url', 'thumbnail_url',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMinio();
    }

    private function galleryWithPhoto(string $type = 'private'): Gallery
    {
        $gallery = Gallery::factory()->create(['type' => $type, 'is_published' => true]);
        Photo::factory()->create([
            'gallery_id' => $gallery->id,
            'metadata' => ['storage_path' => 'galleries/interne/original.jpg'],
        ]);

        return $gallery;
    }

    public function test_le_code_de_partage_ne_publie_aucun_chemin_de_stockage(): void
    {
        $gallery = $this->galleryWithPhoto();

        $photo = $this->getJson("/api/galleries/code/{$gallery->share_code}")
            ->assertOk()
            ->json('gallery.photos.0');

        foreach (self::CHEMINS_INTERNES as $champ) {
            $this->assertArrayNotHasKey($champ, $photo, "« {$champ} » ne doit pas sortir.");
        }
    }

    public function test_les_urls_signees_dont_le_front_depend_sont_conservees(): void
    {
        $gallery = $this->galleryWithPhoto();

        $photo = $this->getJson("/api/galleries/code/{$gallery->share_code}")
            ->assertOk()
            ->json('gallery.photos.0');

        foreach (self::REQUIS_PAR_LE_FRONT as $champ) {
            $this->assertArrayHasKey($champ, $photo, "Le front lit « {$champ} ».");
        }
    }

    public function test_la_galerie_d_evenement_ne_publie_aucun_chemin(): void
    {
        $gallery = $this->galleryWithPhoto('event');

        $photo = $this->getJson("/api/events/{$gallery->id}")->assertOk()->json('gallery.photos.0');

        foreach (self::CHEMINS_INTERNES as $champ) {
            $this->assertArrayNotHasKey($champ, $photo);
        }
    }

    /**
     * `$hidden` ne masque que la sérialisation JSON : le code serveur continue de lire
     * les attributs. Sans cela, le nettoyage des orphelins supprimerait des fichiers
     * référencés — c'est le défaut 0.6.
     */
    public function test_le_code_serveur_lit_toujours_les_chemins(): void
    {
        $photo = Photo::factory()->create(['file_path_hd' => 'galleries/x/hd.jpg']);

        $this->assertSame('galleries/x/hd.jpg', $photo->fresh()->file_path_hd);
    }

    public function test_l_administration_n_a_pas_besoin_des_chemins_mais_garde_ses_compteurs(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $gallery = $this->galleryWithPhoto();

        $photo = $this->getJson("/api/admin/galleries/{$gallery->id}")
            ->assertOk()
            ->json('data.photos.0');

        $this->assertArrayHasKey('downloads_count', $photo);
        $this->assertArrayHasKey('is_downloadable', $photo);
    }
}
