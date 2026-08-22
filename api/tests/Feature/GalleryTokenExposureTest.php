<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `access_token` ouvre la galerie telechargeable, donc les photos sans filigrane. Il
 * etait serialise en clair par les endpoints publics : un code de partage a 6
 * caracteres suffisait a l'obtenir, puis a acceder aux derives non filigranes.
 */
class GalleryTokenExposureTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE_FIELDS = ['access_token', 'assigned_email', 'sms_template'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMinio();
    }

    private function gallery(array $attributes = []): Gallery
    {
        return Gallery::factory()->create([
            'type' => 'private',
            'assigned_email' => 'cliente@exemple.fr',
            ...$attributes,
        ]);
    }

    public function test_le_code_de_partage_ne_livre_pas_le_jeton_de_telechargement(): void
    {
        $gallery = $this->gallery();

        $body = $this->getJson("/api/galleries/code/{$gallery->share_code}")
            ->assertOk()
            ->json('gallery');

        foreach (self::PRIVATE_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $body, "« {$field} » ne doit pas sortir sur un endpoint public.");
        }
    }


    public function test_la_galerie_d_evenement_publique_ne_livre_pas_le_jeton(): void
    {
        $gallery = $this->gallery(['type' => 'event', 'is_published' => true]);

        $body = $this->getJson("/api/events/{$gallery->id}")->assertOk()->json('gallery');

        foreach (self::PRIVATE_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $body);
        }
    }

    /**
     * L'endpoint de telechargement s'atteint deja avec le jeton : le renvoyer n'apprend
     * rien a l'appelant, et le front s'en sert pour construire les URL d'images.
     */
    public function test_l_endpoint_de_telechargement_conserve_le_jeton(): void
    {
        $gallery = $this->gallery();

        $body = $this->getJson("/api/galleries/download/{$gallery->access_token}")
            ->assertOk()
            ->json('gallery');

        $this->assertSame($gallery->access_token, $body['access_token'] ?? null);
        $this->assertArrayNotHasKey('assigned_email', $body);
    }

    public function test_l_administration_voit_les_champs_prives(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $gallery = $this->gallery();

        $body = $this->getJson("/api/admin/galleries/{$gallery->id}")->assertOk()->json('data');

        foreach (self::PRIVATE_FIELDS as $field) {
            $this->assertArrayHasKey($field, $body, "L'administration a besoin de « {$field} ».");
        }
    }

    public function test_le_proprietaire_recoit_son_jeton_mais_pas_le_reste(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $gallery = $this->gallery(['user_id' => $user->id]);

        $body = $this->getJson('/api/account/dashboard')->assertOk()->json('data.galleries.0');

        $this->assertSame($gallery->access_token, $body['access_token'] ?? null);
        $this->assertArrayNotHasKey('sms_template', $body);
    }

    public function test_l_escalade_du_code_de_partage_vers_le_telechargement_est_fermee(): void
    {
        $gallery = $this->gallery();

        $shared = $this->getJson("/api/galleries/code/{$gallery->share_code}")->assertOk()->json('gallery');

        $this->assertArrayNotHasKey('access_token', $shared);
        $this->assertNotContains($gallery->access_token, array_map('strval', array_filter($shared, 'is_scalar')));
    }

    /**
     * `$hidden` ne filtre pas une chaine : getCleanThumbnailUrlAttribute() recompose
     * `?token={access_token}` dans l'URL de repli. Si `clean_thumbnail_url` entrait un
     * jour dans Photo::$appends, le jeton repartirait par le payload des photos —
     * que les assertions sur la racine `gallery` ne voient pas.
     */
    public function test_aucun_jeton_ne_transite_par_le_payload_des_photos(): void
    {
        $gallery = $this->gallery();
        Photo::factory()->create([
            'gallery_id' => $gallery->id,
            'is_downloadable' => true,
            'is_processed' => true,
        ]);

        $reponse = $this->getJson("/api/galleries/code/{$gallery->share_code}")->assertOk();

        $reponse->assertDontSee($gallery->access_token, false);
    }
}
