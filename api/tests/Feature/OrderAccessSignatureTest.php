<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Support\OrderAccessSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La signature remplace la règle « commande créée depuis moins de 30 minutes ». Elle
 * est nécessaire parce que le return_url de SumUp est construit avant que le jeton de
 * téléchargement n'existe : celui-ci n'est généré qu'à la confirmation du paiement.
 */
class OrderAccessSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMinio();
    }

    public function test_une_signature_valide_ouvre_la_commande(): void
    {
        $order = Order::factory()->create(['created_at' => now()->subDay()]);

        $this->getJson("/api/orders/{$order->id}?token=".OrderAccessSignature::for($order))
            ->assertOk()
            ->assertJsonPath('order.id', $order->id);
    }

    public function test_une_signature_expiree_est_refusee(): void
    {
        $order = Order::factory()->create(['created_at' => now()->subDay()]);
        $token = OrderAccessSignature::for($order, 1);

        $this->travel(2)->minutes();

        $this->getJson("/api/orders/{$order->id}?token={$token}")->assertStatus(403);
    }

    public function test_une_signature_emise_pour_une_autre_commande_est_refusee(): void
    {
        $mine = Order::factory()->create(['created_at' => now()->subDay()]);
        $other = Order::factory()->create(['created_at' => now()->subDay()]);

        $this->getJson("/api/orders/{$mine->id}?token=".OrderAccessSignature::for($other))
            ->assertStatus(403);
    }

    public function test_une_signature_falsifiee_est_refusee(): void
    {
        $order = Order::factory()->create(['created_at' => now()->subDay()]);
        [$expiry] = explode('.', OrderAccessSignature::for($order), 2);

        foreach ([
            "{$expiry}.".str_repeat('0', 64),
            "{$expiry}.",
            'pasunnombre.abcdef',
            (string) now()->addYear()->getTimestamp(),
            '',
        ] as $forged) {
            $this->getJson("/api/orders/{$order->id}?token={$forged}")
                ->assertStatus(403, "« {$forged} » ne doit pas ouvrir la commande.");
        }
    }

    public function test_repousser_l_expiration_invalide_la_signature(): void
    {
        $order = Order::factory()->create(['created_at' => now()->subDay()]);
        [, $digest] = explode('.', OrderAccessSignature::for($order, 1), 2);

        $prolonge = now()->addYear()->getTimestamp().'.'.$digest;

        $this->getJson("/api/orders/{$order->id}?token={$prolonge}")->assertStatus(403);
    }

    public function test_le_jeton_de_telechargement_reste_accepte(): void
    {
        $order = Order::factory()->create(['created_at' => now()->subDay()]);
        $token = $order->generateDownloadToken();

        $this->getJson("/api/orders/{$order->id}?token={$token}")->assertOk();
    }

    public function test_la_signature_n_ouvre_pas_le_telechargement_d_une_commande_impayee(): void
    {
        $order = Order::factory()->pending()->create();

        $this->getJson("/api/orders/{$order->id}/download-all?token=".OrderAccessSignature::for($order))
            ->assertStatus(403);
    }
}
