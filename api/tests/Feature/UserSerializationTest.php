<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserSerializationTest extends TestCase
{
    use RefreshDatabase;

    private const CLES = [
        'id', 'email', 'first_name', 'last_name', 'phone',
        'address_line1', 'address_line2', 'postal_code', 'city',
        'role', 'created_at', 'updated_at',
    ];

    public function test_le_compte_authentifie_expose_exactement_la_forme_attendue(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $body = $this->getJson('/api/auth/user')->assertOk()->json('data');

        $this->assertSame(self::CLES, array_keys($body));
    }

    public function test_ni_le_mot_de_passe_ni_l_etat_de_verification_ne_sortent(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $body = $this->getJson('/api/auth/user')->assertOk()->json('data');

        foreach (['password', 'remember_token', 'email_verified_at'] as $champ) {
            $this->assertArrayNotHasKey($champ, $body);
        }
    }

    public function test_la_connexion_renvoie_la_meme_forme(): void
    {
        $user = User::factory()->create(['email' => 'cliente@exemple.fr']);

        $body = $this->postJson('/api/auth/login', [
            'email' => 'cliente@exemple.fr',
            'password' => 'password',
        ])->assertOk()->json();

        $user_payload = $body['user'] ?? $body['data']['user'] ?? null;
        $this->assertNotNull($user_payload, 'La réponse de connexion doit porter le compte.');
        $this->assertSame(self::CLES, array_keys($user_payload));
        $this->assertSame($user->id, $user_payload['id']);
    }

    public function test_le_role_reste_expose_le_front_en_depend_pour_le_routage(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/auth/user')->assertOk()->assertJsonPath('data.role', 'admin');
    }

    public function test_le_tableau_de_bord_du_compte_utilise_la_meme_forme(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $body = $this->getJson('/api/account/dashboard')->assertOk()->json('data.user');

        $this->assertSame(self::CLES, array_keys($body));
    }
}
