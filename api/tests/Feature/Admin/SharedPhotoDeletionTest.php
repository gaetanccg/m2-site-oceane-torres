<?php

namespace Tests\Feature\Admin;

use App\Models\Gallery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\User;
use App\Services\MinioStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Une session scolaire cree une ligne photo par galerie d'eleve, toutes pointant sur le meme
 * fichier partage. Mesure du 30/08/2026 sur la production : 804 lignes sur 5 350 partagent
 * leur fichier, dont 214 vendues. La suppression effaçait le fichier sans compter les
 * references : les autres eleves perdaient leur photo, achats compris.
 */
class SharedPhotoDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const CHEMIN_PARTAGE = 'school-sessions/abc/shared/original/classe.jpg';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function photoDansSaGalerie(array $attributs = []): Photo
    {
        return Photo::factory()->create(array_merge([
            'gallery_id' => Gallery::factory()->create()->id,
        ], $attributs));
    }

    public function test_le_fichier_partage_survit_a_la_suppression_d_une_seule_photo(): void
    {
        $partagee = $this->photoDansSaGalerie([
            'file_path' => self::CHEMIN_PARTAGE,
            'file_path_hd' => self::CHEMIN_PARTAGE,
        ]);

        $this->photoDansSaGalerie([
            'file_path' => self::CHEMIN_PARTAGE,
            'file_path_hd' => self::CHEMIN_PARTAGE,
        ]);

        // La photo possede aussi ses propres derives, qui eux doivent disparaitre : ce qui est
        // interdit, c'est de toucher au fichier que la voisine utilise encore.
        $supprimes = [];
        $stockage = Mockery::mock(MinioStorageService::class);
        $stockage->shouldReceive('deletePhoto')
            ->andReturnUsing(function (string $chemin) use (&$supprimes) {
                $supprimes[] = $chemin;

                return true;
            });
        $this->app->instance(MinioStorageService::class, $stockage);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/photos/{$partagee->id}")
            ->assertOk();

        $this->assertNotContains(self::CHEMIN_PARTAGE, $supprimes);
        $this->assertDatabaseMissing('photos', ['id' => $partagee->id]);
    }

    public function test_un_fichier_non_partage_est_bien_supprime(): void
    {
        $seule = $this->photoDansSaGalerie([
            'file_path' => 'galleries/xyz/unique.jpg',
            'file_path_hd' => 'galleries/xyz/unique.jpg',
        ]);

        $supprimes = [];
        $stockage = Mockery::mock(MinioStorageService::class);
        $stockage->shouldReceive('deletePhoto')
            ->andReturnUsing(function (string $chemin) use (&$supprimes) {
                $supprimes[] = $chemin;

                return true;
            });
        $this->app->instance(MinioStorageService::class, $stockage);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/photos/{$seule->id}")
            ->assertOk();

        $this->assertContains('galleries/xyz/unique.jpg', $supprimes);
    }

    public function test_les_chemins_exclusifs_excluent_ceux_d_une_voisine(): void
    {
        $partagee = $this->photoDansSaGalerie([
            'file_path' => self::CHEMIN_PARTAGE,
            'file_path_hd' => self::CHEMIN_PARTAGE,
            'file_path_preview' => 'school-sessions/abc/eleve-1/preview.jpg',
        ]);

        $this->photoDansSaGalerie(['file_path' => self::CHEMIN_PARTAGE]);

        $exclusifs = $partagee->exclusiveStoragePaths();

        $this->assertNotContains(self::CHEMIN_PARTAGE, $exclusifs);
        $this->assertContains('school-sessions/abc/eleve-1/preview.jpg', $exclusifs);
    }

    public function test_une_galerie_contenant_une_photo_achetee_ne_se_supprime_pas(): void
    {
        $galerie = Gallery::factory()->create();
        $photo = Photo::factory()->create(['gallery_id' => $galerie->id]);

        OrderItem::factory()->create([
            'order_id' => Order::factory()->create()->id,
            'photo_id' => $photo->id,
        ]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/galleries/{$galerie->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('galleries', ['id' => $galerie->id]);
        $this->assertDatabaseHas('photos', ['id' => $photo->id]);
    }

    public function test_une_galerie_sans_vente_se_supprime_normalement(): void
    {
        $galerie = Gallery::factory()->create();
        Photo::factory()->create(['gallery_id' => $galerie->id]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/galleries/{$galerie->id}")
            ->assertOk();

        $this->assertDatabaseMissing('galleries', ['id' => $galerie->id]);
    }
}
