<?php

namespace Tests\Feature\School;

use App\Models\Gallery;
use App\Models\SchoolSession;
use App\Models\SchoolSessionContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * D7 — les contacts n'etaient pas conserves : la diffusion partait d'un formulaire a usage
 * unique, donc ni reenvoi individuel ni relance des non-envoyes.
 */
class ContactPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private SchoolSession $session;

    private Gallery $gallery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMinio();
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->session = SchoolSession::factory()->create();
        $this->gallery = Gallery::factory()->create([
            'school_session_id' => $this->session->id,
            'type' => 'private',
        ]);
    }

    private function diffuser(array $contacts, string $canal = 'email'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/admin/school-sessions/{$this->session->id}/send-messages", [
            'channel' => $canal,
            'contacts' => $contacts,
        ]);
    }

    private function contact(string $email = 'parent@exemple.fr'): array
    {
        return [[
            'gallery_id' => $this->gallery->id,
            'recipient_name' => 'Famille Martin',
            'email' => $email,
        ]];
    }

    public function test_une_diffusion_conserve_le_contact(): void
    {
        $this->diffuser($this->contact())->assertOk();

        $contact = SchoolSessionContact::sole();
        $this->assertSame('Famille Martin', $contact->recipient_name);
        $this->assertSame('parent@exemple.fr', $contact->email);
        $this->assertSame('sent', $contact->status);
        $this->assertSame('email', $contact->channel);
        $this->assertNotNull($contact->sent_at);
        $this->assertSame(1, $contact->send_attempts);
    }

    /** Une seconde diffusion met a jour au lieu de dupliquer. */
    public function test_une_seconde_diffusion_ne_duplique_pas(): void
    {
        $this->diffuser($this->contact())->assertOk();
        $this->diffuser($this->contact())->assertOk();

        $this->assertSame(1, SchoolSessionContact::count());
        $this->assertSame(2, SchoolSessionContact::sole()->send_attempts);
    }

    public function test_les_contacts_sont_consultables(): void
    {
        $this->diffuser($this->contact())->assertOk();

        $this->getJson("/api/admin/school-sessions/{$this->session->id}/contacts")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.sent', 1)
            ->assertJsonPath('meta.not_sent', 0);
    }

    public function test_la_relance_ne_touche_que_les_non_envoyes(): void
    {
        $envoye = SchoolSessionContact::factory()->sent()->create([
            'school_session_id' => $this->session->id,
            'gallery_id' => $this->gallery->id,
            'email' => 'deja@exemple.fr',
        ]);
        $enEchec = SchoolSessionContact::factory()->failed()->create([
            'school_session_id' => $this->session->id,
            'gallery_id' => $this->gallery->id,
            'email' => 'echec@exemple.fr',
        ]);

        $this->postJson("/api/admin/school-sessions/{$this->session->id}/resend-messages", [
            'channel' => 'email',
        ])->assertOk()->assertJsonPath('sent', 1);

        $this->assertSame('sent', $enEchec->fresh()->status);
        $this->assertSame(1, $envoye->fresh()->send_attempts, 'Un contact déjà parti ne doit pas repartir.');
    }

    public function test_la_relance_accepte_une_selection(): void
    {
        $cible = SchoolSessionContact::factory()->failed()->create([
            'school_session_id' => $this->session->id,
            'gallery_id' => $this->gallery->id,
            'email' => 'cible@exemple.fr',
        ]);
        SchoolSessionContact::factory()->failed()->create([
            'school_session_id' => $this->session->id,
            'gallery_id' => $this->gallery->id,
            'email' => 'autre@exemple.fr',
        ]);

        $this->postJson("/api/admin/school-sessions/{$this->session->id}/resend-messages", [
            'channel' => 'email',
            'contact_ids' => [$cible->id],
        ])->assertOk()->assertJsonPath('sent', 1);

        $this->assertSame('sent', $cible->fresh()->status);
    }

    public function test_une_relance_sans_cible_ne_fait_rien(): void
    {
        SchoolSessionContact::factory()->sent()->create([
            'school_session_id' => $this->session->id,
            'gallery_id' => $this->gallery->id,
        ]);

        $this->postJson("/api/admin/school-sessions/{$this->session->id}/resend-messages", [
            'channel' => 'email',
        ])->assertOk()->assertJsonPath('sent', 0);
    }

    /** Coordonnees de parents d'eleves : la table ne peut pas s'accumuler. */
    public function test_la_purge_efface_les_contacts_apres_cloture_plus_trois_mois(): void
    {
        $ancienne = SchoolSession::factory()->closed(6)->create();
        $ancien = SchoolSessionContact::factory()->create(['school_session_id' => $ancienne->id]);

        $recente = SchoolSession::factory()->closed(1)->create();
        $recent = SchoolSessionContact::factory()->create(['school_session_id' => $recente->id]);

        $ouverte = SchoolSessionContact::factory()->create(['school_session_id' => $this->session->id]);

        $this->artisan('privacy:purge-expired')->assertSuccessful();

        $this->assertModelMissing($ancien);
        $this->assertModelExists($recent);
        $this->assertModelExists($ouverte, 'Une session non clôturée conserve ses contacts.');
    }

    public function test_une_recherche_rgpd_trouve_le_contact(): void
    {
        SchoolSessionContact::factory()->create([
            'school_session_id' => $this->session->id,
            'gallery_id' => $this->gallery->id,
            'email' => 'parent.rgpd@exemple.fr',
        ]);

        $this->getJson('/api/admin/privacy/search?type=email&value=parent.rgpd%40exemple.fr')
            ->assertOk()
            ->assertJsonPath('categories.school_session_contacts.0.email', 'parent.rgpd@exemple.fr');
    }

    public function test_un_effacement_rgpd_supprime_le_contact(): void
    {
        $contact = SchoolSessionContact::factory()->create([
            'school_session_id' => $this->session->id,
            'gallery_id' => $this->gallery->id,
            'email' => 'parent.efface@exemple.fr',
        ]);

        $this->postJson('/api/admin/privacy/erasure', [
            'type' => 'email',
            'value' => 'parent.efface@exemple.fr',
            'confirm' => 'parent.efface@exemple.fr',
        ])->assertOk();

        $this->assertModelMissing($contact);
    }
}
