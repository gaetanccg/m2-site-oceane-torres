<?php

namespace Tests\Feature\Download;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Supprimer une photo doit emporter TOUS ses derives — la miniature sans filigrane
 * comprise. Elle etait oubliee : le fichier restait sur MinIO, accessible par URL signee,
 * apres la suppression de la photo. C'est la fuite que le filigrane est cense empecher.
 */
class PhotoDeletionTest extends TestCase
{
    use RefreshDatabase;

    /** Les sous-dossiers qu'une photo traitee possede sur le stockage. */
    private const DERIVES_ATTENDUS = ['original', 'preview', 'thumbnail', 'thumbnail-clean'];

    private function photoAvecTousSesDerives(): Photo
    {
        $gallery = Gallery::factory()->create(['type' => 'private']);

        $photo = Photo::factory()->create([
            'gallery_id' => $gallery->id,
            'file_path' => "{$gallery->id}/original/photo.jpg",
            'file_path_hd' => "{$gallery->id}/original/photo.jpg",
            'file_path_preview' => "{$gallery->id}/preview/photo.jpg",
            'file_path_thumbnail' => "{$gallery->id}/thumbnail/photo.jpg",
            'file_path_thumbnail_clean' => "{$gallery->id}/thumbnail-clean/photo.jpg",
            'is_processed' => true,
        ]);

        // Deposes depuis une liste ECRITE ICI, et non depuis storagePaths() : un test qui
        // derive ses attentes de la methode testee ne peut pas la prendre en defaut.
        foreach (self::DERIVES_ATTENDUS as $sous_dossier) {
            Storage::disk('minio')->put("{$gallery->id}/{$sous_dossier}/photo.jpg", 'contenu');
        }

        return $photo;
    }

    /** @return list<string> */
    private function cheminsAttendus(Photo $photo): array
    {
        return array_map(
            fn (string $sous_dossier) => "{$photo->gallery_id}/{$sous_dossier}/photo.jpg",
            self::DERIVES_ATTENDUS,
        );
    }

    public function test_supprimer_une_photo_emporte_la_miniature_sans_filigrane(): void
    {
        Storage::fake('minio');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $photo = $this->photoAvecTousSesDerives();
        $propre = $photo->file_path_thumbnail_clean;

        Storage::disk('minio')->assertExists($propre);

        $this->deleteJson("/api/admin/photos/{$photo->id}")->assertOk();

        Storage::disk('minio')->assertMissing($propre);
    }

    public function test_supprimer_une_photo_n_en_laisse_aucun_derive(): void
    {
        Storage::fake('minio');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $photo = $this->photoAvecTousSesDerives();
        $chemins = $this->cheminsAttendus($photo);

        $this->assertCount(4, $chemins);

        $this->deleteJson("/api/admin/photos/{$photo->id}")->assertOk();

        foreach ($chemins as $chemin) {
            Storage::disk('minio')->assertMissing($chemin);
        }

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
    }

    /**
     * L'invariant qui protege l'avenir : `storagePaths()` doit couvrir chaque colonne de
     * chemin de la table. Ajouter un derive sans l'y declarer fera tomber ce test.
     */
    public function test_storage_paths_couvre_toutes_les_colonnes_de_chemin(): void
    {
        $colonnes = array_filter(
            Schema::getColumnListing('photos'),
            fn (string $colonne) => str_starts_with($colonne, 'file_path'),
        );

        $gallery = Gallery::factory()->create(['type' => 'private']);
        $valeurs = [];
        foreach ($colonnes as $colonne) {
            $valeurs[$colonne] = "{$gallery->id}/{$colonne}/x.jpg";
        }

        $photo = Photo::factory()->create(['gallery_id' => $gallery->id, ...$valeurs]);

        foreach ($colonnes as $colonne) {
            $this->assertContains(
                $valeurs[$colonne],
                $photo->storagePaths(),
                "La colonne « {$colonne} » n'est pas couverte par Photo::storagePaths() : son fichier survivrait a la suppression.",
            );
        }
    }

    /** Une URL externe n'a rien a supprimer chez nous. */
    public function test_les_urls_externes_sont_ecartees(): void
    {
        $photo = Photo::factory()->create([
            'gallery_id' => Gallery::factory()->create(['type' => 'private'])->id,
            'file_path' => 'https://exemple.test/photo.jpg',
            'file_path_hd' => null,
            'file_path_preview' => null,
            'file_path_thumbnail' => null,
            'file_path_thumbnail_clean' => null,
        ]);

        $this->assertSame([], $photo->storagePaths());
    }

    public function test_supprimer_une_galerie_emporte_le_dossier_des_derives_propres(): void
    {
        Storage::fake('minio');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $photo = $this->photoAvecTousSesDerives();
        $gallery = $photo->gallery;

        $this->deleteJson("/api/admin/galleries/{$gallery->id}")->assertOk();

        // `allFiles` est recursif : le dossier thumbnail-clean doit partir aussi.
        $this->assertSame([], Storage::disk('minio')->allFiles($gallery->id));
    }
}
