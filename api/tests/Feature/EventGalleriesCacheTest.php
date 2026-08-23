<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\User;
use App\Traits\ClearsEventGalleriesCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Le cache de la liste publique des galeries d'evenement.
 *
 * Trois defauts y coexistaient : une purge bornee a dix pages, une cle qui ignorait
 * `per_page` — donc une reponse de 12 elements servie a qui en demandait 100 — et une cle
 * non normalisee, qu'un visiteur pouvait multiplier a volonte.
 */
class EventGalleriesCacheTest extends TestCase
{
    use RefreshDatabase;

    private function galeries(int $combien): void
    {
        Gallery::factory()->count($combien)->create([
            'type' => 'event',
            'parent_id' => null,
            'is_published' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** Le defaut principal : au-dela de la 10e page, la purge ne faisait rien. */
    public function test_la_publication_invalide_meme_les_pages_lointaines(): void
    {
        $this->galeries(150);

        $avant = $this->getJson('/api/events?page=12')->assertOk()->json('total');
        $this->assertSame(150, $avant);

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/events', [
            'title' => 'Nouvelle', 'is_published' => true,
        ])->assertCreated();

        $apres = $this->getJson('/api/events?page=12')->assertOk()->json('total');

        $this->assertGreaterThan($avant, $apres, 'La page 12 est restee perimee : la purge ne la couvrait pas.');
    }

    /** Une demande de 100 elements recevait la reponse mise en cache pour 12. */
    public function test_la_taille_de_page_demandee_est_respectee(): void
    {
        $this->galeries(40);

        $this->getJson('/api/events?page=1')->assertOk()->assertJsonPath('per_page', 12);

        $this->getJson('/api/events?page=1&per_page=40')
            ->assertOk()
            ->assertJsonPath('per_page', 40)
            ->assertJsonCount(40, 'data');
    }

    /** Une page inhabituelle repond juste, sans laisser d'entree derriere elle. */
    public function test_une_taille_de_page_inhabituelle_ne_pollue_pas_le_cache(): void
    {
        $this->galeries(30);

        Cache::flush();
        $this->getJson('/api/events?per_page=7')->assertOk()->assertJsonPath('per_page', 7);

        $version = (int) Cache::get('event_galleries:version', 1);
        $this->assertNull(
            Cache::get("event_galleries:v{$version}:p1"),
            "Une taille de page inhabituelle n'a pas a etre mise en cache.",
        );
    }

    /** `?page=01`, `?page=1.9`, `?page=abc` designent tous la premiere page. */
    public function test_les_variantes_de_page_ne_creent_pas_d_entrees_distinctes(): void
    {
        $this->galeries(5);
        Cache::flush();

        foreach (['1', '01', '1.9', 'abc', ''] as $valeur) {
            $this->getJson('/api/events?page='.$valeur)->assertOk()->assertJsonPath('current_page', 1);
        }

        $version = (int) Cache::get('event_galleries:version', 1);
        $this->assertNotNull(Cache::get("event_galleries:v{$version}:p1"));
    }

    public function test_une_page_negative_retombe_sur_la_premiere(): void
    {
        $this->galeries(5);

        $this->getJson('/api/events?page=-5')->assertOk()->assertJsonPath('current_page', 1);
    }

    /** La purge doit fonctionner avec le driver reellement utilise, pas seulement Redis. */
    public function test_la_purge_ne_depend_pas_d_un_driver_a_etiquettes(): void
    {
        foreach (['array', 'file', 'database'] as $driver) {
            config(['cache.default' => $driver]);
            Cache::store($driver)->flush();

            $controleur = new class
            {
                use ClearsEventGalleriesCache;

                public function purge(): void
                {
                    $this->clearEventGalleriesCache();
                }

                public function version(): int
                {
                    return $this->eventGalleriesCacheVersion();
                }
            };

            $avant = $controleur->version();
            $controleur->purge();

            $this->assertSame(
                $avant + 1,
                $controleur->version(),
                "La purge echoue avec le driver « {$driver} » — les etiquettes n'y sont pas supportees.",
            );
        }
    }

    public function test_la_version_invalide_toutes_les_pages_d_un_coup(): void
    {
        $this->galeries(30);

        $this->getJson('/api/events?page=1')->assertOk();
        $this->getJson('/api/events?page=2')->assertOk();

        $version = (int) Cache::get('event_galleries:version', 1);
        $this->assertNotNull(Cache::get("event_galleries:v{$version}:p1"));
        $this->assertNotNull(Cache::get("event_galleries:v{$version}:p2"));

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/events', ['title' => 'Autre', 'is_published' => true])->assertCreated();

        // Les anciennes entrees existent encore, mais plus personne ne les lit : la version
        // a change. C'est le principe, et c'est ce qui rend la purge independante du driver.
        $nouvelle = (int) Cache::get('event_galleries:version', 1);
        $this->assertGreaterThan($version, $nouvelle);
        $this->assertNull(Cache::get("event_galleries:v{$nouvelle}:p1"));
        $this->assertNull(Cache::get("event_galleries:v{$nouvelle}:p2"));
    }
}
