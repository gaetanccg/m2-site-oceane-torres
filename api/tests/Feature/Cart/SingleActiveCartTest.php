<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Un seul panier actif par utilisateur » n'etait garanti qu'au niveau applicatif : deux
 * requetes concurrentes pouvaient en creer deux, et l'utilisateur payer un montant
 * different de ce qu'il voyait.
 */
class SingleActiveCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_base_refuse_un_second_panier_actif(): void
    {
        $user = User::factory()->create();
        Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->expectException(QueryException::class);

        Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    }

    public function test_un_panier_converti_ne_bloque_pas_le_suivant(): void
    {
        $user = User::factory()->create();
        Cart::factory()->create(['user_id' => $user->id, 'status' => 'converted']);

        $nouveau = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->assertNotNull($nouveau->id);
    }

    /** La contrainte est partielle : les paniers invites n'ont pas de user_id. */
    public function test_plusieurs_paniers_invites_restent_possibles(): void
    {
        Cart::factory()->create(['user_id' => null, 'session_id' => 'a', 'status' => 'active']);
        Cart::factory()->create(['user_id' => null, 'session_id' => 'b', 'status' => 'active']);

        $this->assertSame(2, Cart::where('status', 'active')->count());
    }

    public function test_get_or_create_reste_idempotent(): void
    {
        $user = User::factory()->create();
        $service = app(CartService::class);

        $premier = $service->getOrCreateCart($user);
        $second = $service->getOrCreateCart($user);

        $this->assertSame($premier->id, $second->id);
        $this->assertSame(1, Cart::where('user_id', $user->id)->where('status', 'active')->count());
    }

    public function test_la_fusion_d_un_panier_invite_ne_laisse_qu_un_actif(): void
    {
        $user = User::factory()->create();
        $service = app(CartService::class);

        $service->getOrCreateCart($user);
        Cart::factory()->create(['session_id' => 'session-invitee', 'status' => 'active']);

        $service->getOrCreateCart($user, 'session-invitee');

        $this->assertSame(
            1,
            Cart::where('user_id', $user->id)->where('status', 'active')->count()
        );
    }
}
