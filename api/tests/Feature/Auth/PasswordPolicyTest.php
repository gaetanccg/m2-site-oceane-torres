<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La politique vit dans Password::defaults() (AppServiceProvider) : min(12) partout, plus
 * uncompromised() hors tests — cette derniere interroge l'API HIBP, qu'on ne sollicite
 * pas depuis la suite.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public static function motsDePasseRefuses(): array
    {
        return [
            'trop court' => ['Court1!'],
            'onze caracteres' => ['Onze12345!x'],
            'vide' => [''],
        ];
    }

    #[DataProvider('motsDePasseRefuses')]
    public function test_l_inscription_refuse_un_mot_de_passe_faible(string $password): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'email' => 'alice@exemple.fr',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertSame(0, User::where('email', 'alice@exemple.fr')->count());
    }

    #[DataProvider('motsDePasseRefuses')]
    public function test_la_reinitialisation_refuse_un_mot_de_passe_faible(string $password): void
    {
        $user = User::factory()->create(['email' => 'alice@exemple.fr']);
        $ancien = $user->password;

        $this->postJson('/api/auth/reset-password', [
            'email' => 'alice@exemple.fr',
            'token' => Password::createToken($user),
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertSame($ancien, $user->fresh()->password);
    }

    public function test_une_confirmation_qui_ne_correspond_pas_est_refusee(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'email' => 'alice@exemple.fr',
            'password' => 'MotDePasseSolide2026!',
            'password_confirmation' => 'AutreMotDePasse2026!',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_un_mot_de_passe_conforme_est_accepte(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'email' => 'alice@exemple.fr',
            'password' => 'MotDePasseSolide2026!',
            'password_confirmation' => 'MotDePasseSolide2026!',
            'gdpr_consent' => true,
        ])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'alice@exemple.fr')->count());
    }
}
