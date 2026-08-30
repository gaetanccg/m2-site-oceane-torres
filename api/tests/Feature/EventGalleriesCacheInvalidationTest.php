<?php

namespace Tests\Feature;

use App\Models\EventGallery;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use App\Support\EventGalleriesCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * L'invalidation du cache de la liste publique vivait chez les APPELANTS : chaque controleur
 * devait y penser. Trois ne le faisaient pas — `toggleDownloadable`,
 * `bulkToggleDownloadable` et `updateSortOrder` — alors qu'ils changent precisement ce que la
 * liste affiche : quelles photos sont telechargeables, et dans quel ordre. Le listing public
 * restait perime jusqu'a cinq minutes.
 *
 * Elle est desormais declenchee par les MODELES. Ces tests couvrent les trois chemins qui
 * l'oubliaient, et le principe : ce qui change la donnee invalide le cache.
 */
class EventGalleriesCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Marquer une photo telechargeable declenche la generation de sa miniature SANS
        // filigrane. En test, la file est synchrone et le fichier n'existe pas : le job
        // echoue et masque ce qu'on mesure. Ce test porte sur l'invalidation, pas sur la
        // vignette.
        Queue::fake();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** @return array{EventGallery, Photo} */
    private function evenementAvecPhoto(): array
    {
        $galerie = EventGallery::create(['title' => 'Evenement', 'is_published' => true]);
        $photo = Photo::factory()->create(['gallery_id' => $galerie->id]);

        return [$galerie, $photo];
    }

    private function versionApres(callable $action): array
    {
        [$galerie, $photo] = $this->evenementAvecPhoto();
        $avant = EventGalleriesCache::version();

        $action($galerie, $photo);

        return [$avant, EventGalleriesCache::version()];
    }

    public function test_basculer_le_telechargement_invalide_la_liste(): void
    {
        [$avant, $apres] = $this->versionApres(function ($galerie, $photo) {
            $this->actingAs($this->admin())
                ->putJson("/api/admin/photos/{$photo->id}/toggle-downloadable")
                ->assertOk();
        });

        $this->assertNotSame($avant, $apres, 'toggleDownloadable n\'a pas invalidé la liste publique.');
    }

    public function test_basculer_le_telechargement_en_masse_invalide_la_liste(): void
    {
        [$avant, $apres] = $this->versionApres(function ($galerie, $photo) {
            $this->actingAs($this->admin())
                ->putJson('/api/admin/photos/bulk-downloadable', [
                    'photo_ids' => [$photo->id],
                    'is_downloadable' => true,
                ])
                ->assertOk();
        });

        $this->assertNotSame($avant, $apres, 'bulkToggleDownloadable n\'a pas invalidé la liste publique.');
    }

    public function test_reordonner_les_photos_invalide_la_liste(): void
    {
        [$avant, $apres] = $this->versionApres(function ($galerie, $photo) {
            $this->actingAs($this->admin())
                ->putJson('/api/admin/photos/sort-order', [
                    'photos' => [['id' => $photo->id, 'sort_order' => 5]],
                ])
                ->assertOk();
        });

        $this->assertNotSame($avant, $apres, 'updateSortOrder n\'a pas invalidé la liste publique.');
    }

    public function test_modifier_la_galerie_d_evenement_invalide_la_liste(): void
    {
        [$galerie] = $this->evenementAvecPhoto();
        $avant = EventGalleriesCache::version();

        $galerie->update(['title' => 'Titre change']);

        $this->assertNotSame($avant, EventGalleriesCache::version());
    }

    /** Une photo de galerie CLIENTE ne touche pas la liste des evenements. */
    public function test_une_photo_hors_evenement_n_invalide_rien(): void
    {
        $cliente = Gallery::factory()->create(['type' => 'private']);
        $avant = EventGalleriesCache::version();

        Photo::factory()->create(['gallery_id' => $cliente->id]);

        $this->assertSame($avant, EventGalleriesCache::version());
    }
}
