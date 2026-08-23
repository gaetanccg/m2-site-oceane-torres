<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fige la forme des reponses galerie/photo publiques. Avant les API Resources, elle
 * dependait de $fillable et de $appends : ajouter un accesseur au modele changeait la
 * reponse de tous les endpoints sans que rien ne le signale.
 */
class GallerySerializationTest extends TestCase
{
    use RefreshDatabase;

    private const GALERIE = [
        'id', 'title', 'description', 'class_name', 'event_date', 'event_link',
        'photos', 'sort_order', 'event_category_id', 'created_at',
    ];

    private const PHOTO = [
        'id', 'title', 'is_video', 'is_liked', 'is_downloadable', 'price',
        'preview_url', 'thumbnail_url',
    ];

    /** Ne doit jamais sortir sur un endpoint public. */
    private const INTERDITS = [
        'access_token', 'assigned_email', 'sms_template', 'share_code',
        'file_path', 'file_path_hd', 'file_path_preview', 'file_path_thumbnail',
        'file_path_thumbnail_clean', 'metadata', 'user_id', 'school_session',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMinio();
    }

    private function galleryWithPhoto(array $attributes = []): Gallery
    {
        $gallery = Gallery::factory()->create([...['type' => 'private', 'is_published' => true], ...$attributes]);
        Photo::factory()->create(['gallery_id' => $gallery->id, 'is_processed' => true]);

        return $gallery;
    }

    public function test_le_code_de_partage_expose_exactement_la_forme_attendue(): void
    {
        $gallery = $this->galleryWithPhoto();

        $body = $this->getJson("/api/galleries/code/{$gallery->share_code}")->assertOk()->json('gallery');

        $this->assertSame(self::GALERIE, array_keys($body));
        $this->assertSame(self::PHOTO, array_keys($body['photos'][0]));
    }

    public function test_l_endpoint_de_telechargement_ajoute_le_jeton_et_la_miniature_propre(): void
    {
        $gallery = $this->galleryWithPhoto();
        Photo::where('gallery_id', $gallery->id)->update(['is_downloadable' => true]);

        $body = $this->getJson("/api/galleries/download/{$gallery->access_token}")->assertOk()->json('gallery');

        $this->assertSame([...self::GALERIE, 'access_token'], array_keys($body));
        $this->assertSame([...self::PHOTO, 'clean_thumbnail_url'], array_keys($body['photos'][0]));
    }

    public function test_l_evenement_expose_la_meme_forme(): void
    {
        $gallery = $this->galleryWithPhoto(['type' => 'event']);

        $body = $this->getJson("/api/events/{$gallery->id}")->assertOk()->json('gallery');

        foreach (self::GALERIE as $cle) {
            $this->assertArrayHasKey($cle, $body);
        }
        $this->assertSame(self::PHOTO, array_keys($body['photos'][0]));
    }

    public function test_la_liste_d_evenements_porte_les_champs_dont_le_front_depend(): void
    {
        $this->galleryWithPhoto(['type' => 'event']);

        $item = $this->getJson('/api/events')->assertOk()->json('data.0');

        foreach (['id', 'title', 'description', 'event_date', 'created_at', 'event_category_id', 'cover_photo', 'children_count'] as $cle) {
            $this->assertArrayHasKey($cle, $item, "Events.vue lit « {$cle} ».");
        }
    }

    public function test_cover_photo_n_apparait_que_la_ou_le_controleur_le_pose(): void
    {
        $gallery = $this->galleryWithPhoto();

        $body = $this->getJson("/api/galleries/code/{$gallery->share_code}")->assertOk()->json('gallery');

        $this->assertArrayNotHasKey('cover_photo', $body);
    }

    public function test_aucun_champ_interdit_sur_les_endpoints_publics(): void
    {
        $gallery = $this->galleryWithPhoto(['type' => 'event']);

        foreach (["/api/galleries/code/{$gallery->share_code}", "/api/events/{$gallery->id}", '/api/events'] as $url) {
            $brut = $this->getJson($url)->assertOk()->content();

            foreach (self::INTERDITS as $champ) {
                $this->assertStringNotContainsString("\"{$champ}\"", $brut, "« {$champ} » sort sur {$url}.");
            }
        }
    }
}
