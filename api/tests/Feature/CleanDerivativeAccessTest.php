<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `Gallery::isAccessible(null)` rend true pour une galerie `type = 'public'`. Trois
 * endpoints servant du contenu SANS filigrane s'appuyaient dessus : ils livraient donc
 * les originaux propres sans aucun jeton dès qu'une photo était marquée téléchargeable.
 *
 * Piège latent et non fuite active : la production ne compte aucune galerie `public`
 * (151 private + 45 event sur 196, mesuré le 2026-08-22). La première créée aurait suffi.
 */
class CleanDerivativeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMinio();
    }

    private function downloadablePhotoIn(string $type): array
    {
        $gallery = Gallery::factory()->create(['type' => $type, 'is_published' => true]);
        $photo = Photo::factory()->create([
            'gallery_id' => $gallery->id,
            'is_downloadable' => true,
            'is_processed' => true,
        ]);

        return [$gallery, $photo];
    }

    public static function endpointsServantDuNonFiligrane(): array
    {
        return [
            'image propre' => ['/api/images/clean/%s'],
            'miniature propre' => ['/api/images/clean-thumb/%s'],
            'telechargement' => ['/api/photos/%s/download'],
        ];
    }

    #[DataProvider('endpointsServantDuNonFiligrane')]
    public function test_une_galerie_publique_n_ouvre_plus_le_non_filigrane_sans_jeton(string $gabarit): void
    {
        [, $photo] = $this->downloadablePhotoIn('public');

        $this->getJson(sprintf($gabarit, $photo->id))->assertStatus(403);
    }

    #[DataProvider('endpointsServantDuNonFiligrane')]
    public function test_un_jeton_valide_ouvre_toujours_le_non_filigrane(string $gabarit): void
    {
        [$gallery, $photo] = $this->downloadablePhotoIn('private');

        $reponse = $this->get(sprintf($gabarit, $photo->id).'?token='.$gallery->access_token);

        $this->assertNotSame(403, $reponse->status(), 'Un porteur du jeton doit garder l\'accès.');
    }

    #[DataProvider('endpointsServantDuNonFiligrane')]
    public function test_un_jeton_errone_est_refuse(string $gabarit): void
    {
        [, $photo] = $this->downloadablePhotoIn('private');

        $this->getJson(sprintf($gabarit, $photo->id).'?token=mauvais')->assertStatus(403);
    }

    /**
     * Ce test affirmait jusqu'au 31/08 que la consultation d'une galerie `type = 'public'`
     * restait ouverte sans jeton. C'etait vrai, et c'etait le probleme : cette valeur avait
     * ZERO producteur — aucune galerie ne la portait, aucun FormRequest ne l'acceptait, rien
     * ne l'ecrivait — pour trois consommateurs dans des chemins d'AUTORISATION. Une branche
     * dormante qu'un simple `update` armait. D2 l'a retiree ; le test garde sa place pour
     * interdire son retour.
     */
    public function test_une_galerie_marquee_public_n_est_plus_consultable_sans_jeton(): void
    {
        $gallery = Gallery::factory()->create(['type' => 'private', 'is_published' => true]);
        DB::table('galleries')->where('id', $gallery->id)->update(['type' => 'public']);

        $gallery->refresh();

        $this->assertFalse($gallery->isAccessible(null));
        $this->assertFalse($gallery->grantsCleanDerivatives(null));
    }

    /** Ce qui reste ouvert sans jeton, et qui est reel : l'evenement publie. */
    public function test_une_galerie_d_evenement_publiee_reste_consultable_sans_jeton(): void
    {
        $gallery = Gallery::factory()->event()->create(['is_published' => true]);

        $this->assertTrue($gallery->grantsWatermarkedPreview(null));
        $this->assertFalse($gallery->grantsCleanDerivatives(null));
    }
}
