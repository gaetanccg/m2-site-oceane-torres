<?php

namespace Tests\Feature\Reservations;

use App\Models\Client;
use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function prestation(): Prestation
    {
        return Prestation::create([
            'title' => 'Portrait', 'description' => 'x', 'price' => 100,
            'duration' => 60, 'category' => 'Portrait', 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function reservation(array $attributs = []): Reservation
    {
        return Reservation::create([...[
            'prestation_id' => $this->prestation()->id,
            'date' => now()->addWeek(),
            'status' => 'pending',
            'guest_name' => 'Nom Invité',
            'guest_email' => 'invite@exemple.fr',
            'guest_phone' => '0612345678',
        ], ...$attributs]);
    }

    /** La cascade effaçait tout l'historique des reservations d'une prestation supprimee. */
    public function test_supprimer_une_prestation_reservee_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $reservation = $this->reservation();

        $this->deleteJson("/api/admin/prestations/{$reservation->prestation_id}")
            ->assertStatus(409);

        $this->assertModelExists($reservation);
    }

    public function test_la_base_refuse_aussi_la_suppression(): void
    {
        $reservation = $this->reservation();

        $this->expectException(QueryException::class);

        Prestation::find($reservation->prestation_id)->delete();
    }

    public function test_une_prestation_sans_reservation_reste_supprimable(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $prestation = $this->prestation();

        $this->deleteJson("/api/admin/prestations/{$prestation->id}")->assertOk();

        $this->assertModelMissing($prestation);
    }

    /**
     * Les accesseurs dependaient de relationLoaded() : la meme reservation rendait le nom
     * du compte dans une liste et le nom invite apres un changement de statut.
     */
    public function test_le_nom_du_client_ne_depend_pas_de_l_etat_de_chargement(): void
    {
        $user = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Martin']);
        $reservation = $this->reservation(['user_id' => $user->id]);

        $sansRelation = Reservation::find($reservation->id);
        $avecRelation = Reservation::with('user')->find($reservation->id);

        $this->assertSame('Alice Martin', $sansRelation->client_name);
        $this->assertSame($avecRelation->client_name, $sansRelation->client_name);
    }

    public function test_l_email_du_client_ne_depend_pas_de_l_etat_de_chargement(): void
    {
        $user = User::factory()->create(['email' => 'compte@exemple.fr']);
        $reservation = $this->reservation(['user_id' => $user->id]);

        $this->assertSame('compte@exemple.fr', Reservation::find($reservation->id)->client_email);
        $this->assertSame(
            'compte@exemple.fr',
            Reservation::with('user')->find($reservation->id)->client_email
        );
    }

    public function test_une_reservation_invitee_garde_ses_coordonnees(): void
    {
        $reservation = Reservation::find($this->reservation()->id);

        $this->assertSame('Nom Invité', $reservation->client_name);
        $this->assertSame('invite@exemple.fr', $reservation->client_email);
        $this->assertSame('0612345678', $reservation->client_phone);
        $this->assertTrue($reservation->is_guest);
    }

    /** UserObserver etiquetait toute inscription en 'reservation' : le filtre par source mentait. */
    public function test_une_inscription_cree_un_client_de_source_registration(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'email' => 'alice@exemple.fr',
            'password' => 'MotDePasseSolide2026!',
            'password_confirmation' => 'MotDePasseSolide2026!',
            'gdpr_consent' => true,
        ])->assertSuccessful();

        $this->assertSame('registration', Client::where('email', 'alice@exemple.fr')->sole()->source);
    }

    /** Une nouvelle demande ne doit pas effacer la date de consentement d'origine. */
    public function test_la_date_de_consentement_d_origine_est_preservee(): void
    {
        $origine = now()->subYear();
        $client = Client::create([
            'name' => 'Alice Martin',
            'email' => 'alice@exemple.fr',
            'source' => 'manual',
            'gdpr_consent' => true,
            'gdpr_consent_at' => $origine,
        ]);

        $this->postJson('/api/booking-request', [
            'name' => 'Alice Martin',
            'email' => 'alice@exemple.fr',
            'prestation_id' => $this->prestation()->id,
            'date_preferences' => 'Un samedi de juin',
            'gdpr_consent' => true,
        ])->assertSuccessful();

        $this->assertSame(
            $origine->toDateString(),
            $client->fresh()->gdpr_consent_at->toDateString()
        );
    }
}
