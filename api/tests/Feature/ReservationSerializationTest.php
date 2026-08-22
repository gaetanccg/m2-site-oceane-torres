<?php

namespace Tests\Feature;

use App\Models\Prestation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationSerializationTest extends TestCase
{
    use RefreshDatabase;

    private const CLES = ['id', 'date', 'status', 'message', 'date_preferences', 'created_at', 'prestation'];

    private const NOTE = 'NOTE INTERNE : demander un acompte';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMinio();
    }

    private function dashboardReservation(): array
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $prestation = Prestation::create([
            'title' => 'Portrait', 'description' => 'x', 'price' => 100,
            'duration' => 60, 'category' => 'Portrait', 'is_active' => true, 'sort_order' => 1,
        ]);

        Reservation::create([
            'user_id' => $user->id,
            'prestation_id' => $prestation->id,
            'date' => now()->addWeek(),
            'status' => 'pending',
            'notes' => self::NOTE,
            'guest_name' => 'Nom Invite',
            'guest_email' => 'invite@exemple.fr',
            'guest_phone' => '+33600000000',
        ]);

        return $this->getJson('/api/account/dashboard')->assertOk()->json('data.reservations.0');
    }

    public function test_le_tableau_de_bord_client_expose_exactement_la_forme_attendue(): void
    {
        $this->assertSame(self::CLES, array_keys($this->dashboardReservation()));
    }

    /**
     * `notes` est le commentaire interne de la photographe, ecrivable seulement par
     * l'administration. Il sortait sur le tableau de bord du client concerne.
     */
    public function test_les_notes_internes_ne_sortent_plus_cote_client(): void
    {
        $reservation = $this->dashboardReservation();

        $this->assertArrayNotHasKey('notes', $reservation);
        $this->assertNotContains(self::NOTE, array_filter($reservation, 'is_scalar'));
    }

    public function test_les_identifiants_internes_de_correlation_ne_sortent_plus(): void
    {
        $reservation = $this->dashboardReservation();

        foreach (['user_id', 'client_id', 'prestation_id', 'guest_email', 'guest_phone', 'guest_name'] as $champ) {
            $this->assertArrayNotHasKey($champ, $reservation);
        }
    }

    public function test_la_prestation_liee_reste_presente(): void
    {
        $reservation = $this->dashboardReservation();

        $this->assertSame('Portrait', $reservation['prestation']['title']);
    }
}
