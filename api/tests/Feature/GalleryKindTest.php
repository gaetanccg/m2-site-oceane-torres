<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\SchoolSession;
use App\Policies\GalleryPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La nature d'une galerie etait eclatee sur trois colonnes de deux natures : `type` pour
 * l'evenement, la NULLITE de `school_session_id` pour le scolaire, et l'absence des deux
 * pour la cliente — une double negation que chaque lecteur devait reconstituer.
 *
 * `kind` la rend explicite. Ces tests verrouillent la regle de deduction et son accord avec
 * l'ancien discriminant : le jour ou `type` disparaitra, c'est ce qui garantira qu'aucune
 * galerie n'a change de nature en chemin.
 */
class GalleryKindTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_galerie_scolaire_est_de_nature_scolaire(): void
    {
        $galerie = Gallery::factory()->create([
            'school_session_id' => SchoolSession::factory()->create()->id,
            'type' => 'private',
        ]);

        $this->assertSame(Gallery::KIND_SCHOOL, $galerie->kind);
    }

    public function test_une_galerie_d_evenement_est_de_nature_evenement(): void
    {
        $this->assertSame(Gallery::KIND_EVENT, Gallery::factory()->create(['type' => 'event'])->kind);
    }

    public function test_une_galerie_ordinaire_est_de_nature_cliente(): void
    {
        $this->assertSame(Gallery::KIND_CLIENT, Gallery::factory()->create(['type' => 'private'])->kind);
    }

    /**
     * L'ordre de deduction compte : une galerie scolaire porte `type = 'private'`. Tester le
     * type d'abord la classerait cliente, et elle sortirait du perimetre scolaire.
     */
    public function test_le_scolaire_l_emporte_sur_le_type(): void
    {
        $galerie = Gallery::factory()->create([
            'school_session_id' => SchoolSession::factory()->create()->id,
            'type' => 'event',
        ]);

        $this->assertSame(Gallery::KIND_SCHOOL, $galerie->kind);
    }

    /** Une nature posee explicitement n'est pas ecrasee par la deduction. */
    public function test_une_nature_explicite_est_respectee(): void
    {
        $galerie = Gallery::factory()->create(['kind' => Gallery::KIND_EVENT, 'type' => 'private']);

        $this->assertSame(Gallery::KIND_EVENT, $galerie->kind);
    }

    /** La base refuse une quatrieme nature : une valeur inattendue ne s'installe pas en silence. */
    public function test_la_base_refuse_une_nature_inconnue(): void
    {
        $galerie = Gallery::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('galleries')->where('id', $galerie->id)->update(['kind' => 'portfolio']);
    }

    /** Aucune galerie ne doit rester sans nature : la colonne est NOT NULL. */
    public function test_aucune_galerie_ne_peut_naitre_sans_nature(): void
    {
        foreach ([['type' => 'private'], ['type' => 'event'], []] as $attributs) {
            $galerie = Gallery::factory()->create($attributs);
            $this->assertContains($galerie->kind, Gallery::KINDS);
        }
    }

    /**
     * La branche dormante. `type = 'public'` rendait une galerie lisible SANS jeton dans
     * trois chemins d'autorisation — la Policy, `isAccessible()` et
     * `grantsWatermarkedPreview()` — alors que zero galerie portait cette valeur et que rien
     * ne l'ecrivait. Il suffisait d'un `update` pour l'armer. Ces tests interdisent son retour.
     */
    public function test_une_galerie_marquee_public_n_est_plus_lisible_sans_jeton(): void
    {
        $galerie = Gallery::factory()->create(['type' => 'private', 'kind' => Gallery::KIND_CLIENT]);
        DB::table('galleries')->where('id', $galerie->id)->update(['type' => 'public']);
        $galerie->refresh();

        $this->assertFalse($galerie->isAccessible(null));
        $this->assertFalse($galerie->grantsWatermarkedPreview(null));
        $this->assertFalse($galerie->grantsCleanDerivatives(null));
        $this->assertFalse(app(GalleryPolicy::class)->view(null, $galerie, null));
    }

    /** Le pendant : une galerie d'evenement PUBLIEE reste bien consultable. */
    public function test_une_galerie_d_evenement_publiee_reste_consultable(): void
    {
        $galerie = Gallery::factory()->create(['type' => 'event', 'is_published' => true]);

        $this->assertTrue($galerie->grantsWatermarkedPreview(null));
        $this->assertTrue(app(GalleryPolicy::class)->view(null, $galerie, null));
    }

    /** Et une galerie d'evenement NON publiee ne l'est pas. */
    public function test_une_galerie_d_evenement_non_publiee_exige_un_jeton(): void
    {
        $galerie = Gallery::factory()->create(['type' => 'event', 'is_published' => false]);

        $this->assertFalse($galerie->grantsWatermarkedPreview(null));
        $this->assertFalse(app(GalleryPolicy::class)->view(null, $galerie, null));
    }

    /** Le scope mort ne doit pas revenir : il n'avait aucun appelant et selectionnait 0 ligne. */
    public function test_le_scope_public_n_existe_plus(): void
    {
        $this->assertStringNotContainsString(
            'scopePublic',
            file_get_contents(app_path('Models/Gallery.php'))
        );
    }
}
