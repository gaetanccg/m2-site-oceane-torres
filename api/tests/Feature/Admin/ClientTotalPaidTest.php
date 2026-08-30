<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Total verse » affichait 0,00 € pour tous les clients, en permanence : l'accesseur sommait
 * les paiements des RESERVATIONS, or la table est vide et le seul Payment::create du depot
 * rattache le paiement a une commande. Aucune clef ne relie `clients` a `orders` : le
 * rattachement se fait par `user_id` ou par `guest_email`.
 */
class ClientTotalPaidTest extends TestCase
{
    use RefreshDatabase;

    private function paiement(Order $order, string $status, float $montant): Payment
    {
        return Payment::factory()->create([
            'order_id' => $order->id,
            'reservation_id' => null,
            'status' => $status,
            'amount' => $montant,
        ]);
    }

    public function test_les_commandes_d_un_client_connecte_sont_comptees(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id, 'email' => 'sans-rapport@example.test']);

        $this->paiement(Order::factory()->create(['user_id' => $user->id]), 'completed', 120.00);

        $this->assertSame(120.00, $client->fresh()->total_paid);
    }

    public function test_les_achats_invites_sont_comptes_par_email(): void
    {
        $client = Client::factory()->create(['user_id' => null, 'email' => 'invite@example.test']);

        $this->paiement(Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'invite@example.test',
        ]), 'completed', 45.50);

        $this->assertSame(45.50, $client->fresh()->total_paid);
    }

    public function test_seuls_les_paiements_aboutis_comptent(): void
    {
        $client = Client::factory()->create(['user_id' => null, 'email' => 'partiel@example.test']);
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'partiel@example.test']);

        $this->paiement($order, 'completed', 30.00);
        $this->paiement(Order::factory()->create([
            'user_id' => null, 'guest_email' => 'partiel@example.test',
        ]), 'pending', 999.00);

        $this->assertSame(30.00, $client->fresh()->total_paid);
    }

    public function test_les_commandes_d_autrui_ne_sont_pas_comptees(): void
    {
        $client = Client::factory()->create(['user_id' => null, 'email' => 'moi@example.test']);

        $this->paiement(Order::factory()->create([
            'user_id' => null, 'guest_email' => 'quelqu-un-d-autre@example.test',
        ]), 'completed', 500.00);

        $this->assertSame(0.0, $client->fresh()->total_paid);
    }

    /**
     * `clients.email` est NOT NULL, mais rien n'interdit la chaine vide. Sans garde, le filtre
     * se retrouverait sans critere et la clause `where` vide ramasserait TOUTES les commandes.
     */
    public function test_un_client_sans_identifiant_ne_ramasse_rien(): void
    {
        $client = Client::factory()->create(['user_id' => null, 'email' => '']);

        $this->paiement(Order::factory()->create(['user_id' => null, 'guest_email' => 'x@example.test']), 'completed', 700.00);

        $this->assertSame(0.0, $client->fresh()->total_paid);
    }
}
