<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * forgotPassword rendait 200 pour une adresse connue et 422 « aucun compte » sinon :
 * une requete par adresse suffisait a savoir qui est inscrit.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function demander(string $email): TestResponse
    {
        return $this->postJson('/api/auth/forgot-password', ['email' => $email]);
    }

    public function test_la_reponse_est_identique_pour_une_adresse_connue_et_inconnue(): void
    {
        User::factory()->create(['email' => 'connue@exemple.fr']);

        $connue = $this->demander('connue@exemple.fr');
        $inconnue = $this->demander('jamais-vue@exemple.fr');

        $this->assertSame($connue->status(), $inconnue->status());
        $this->assertSame($connue->json('message'), $inconnue->json('message'));
    }

    public function test_les_deux_reponses_sont_des_200(): void
    {
        User::factory()->create(['email' => 'connue@exemple.fr']);

        $this->demander('connue@exemple.fr')->assertOk();
        $this->demander('jamais-vue@exemple.fr')->assertOk();
    }

    public function test_le_message_ne_confirme_pas_l_existence_du_compte(): void
    {
        $message = $this->demander('jamais-vue@exemple.fr')->json('message');

        $this->assertStringContainsString('Si un compte existe', $message);
    }

    public function test_le_lien_part_reellement_pour_une_adresse_connue(): void
    {
        $user = User::factory()->create(['email' => 'connue@exemple.fr']);

        $this->demander('connue@exemple.fr')->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_aucun_lien_ne_part_pour_une_adresse_inconnue(): void
    {
        $this->demander('jamais-vue@exemple.fr')->assertOk();

        Notification::assertNothingSent();
    }

    /**
     * Un jeton au hasard distinguait « aucun compte » de « jeton invalide » : meme
     * vecteur d'enumeration.
     */
    public function test_la_reinitialisation_ne_distingue_pas_compte_absent_et_jeton_invalide(): void
    {
        User::factory()->create(['email' => 'connue@exemple.fr']);

        $payload = fn (string $email) => [
            'email' => $email,
            'token' => 'jeton-au-hasard',
            'password' => 'MotDePasseSolide2026!',
            'password_confirmation' => 'MotDePasseSolide2026!',
        ];

        $connue = $this->postJson('/api/auth/reset-password', $payload('connue@exemple.fr'));
        $inconnue = $this->postJson('/api/auth/reset-password', $payload('jamais-vue@exemple.fr'));

        $this->assertSame($connue->status(), $inconnue->status());
        $this->assertSame(
            $connue->json('errors.email'),
            $inconnue->json('errors.email')
        );
    }

    public function test_une_reinitialisation_valide_fonctionne_toujours(): void
    {
        $user = User::factory()->create(['email' => 'connue@exemple.fr']);
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'connue@exemple.fr',
            'token' => $token,
            'password' => 'MotDePasseSolide2026!',
            'password_confirmation' => 'MotDePasseSolide2026!',
        ])->assertOk();

        $this->assertTrue(Hash::check('MotDePasseSolide2026!', $user->fresh()->password));
    }

    /** Un lien reçu par email prouve le contrôle de l'adresse. */
    public function test_la_reinitialisation_marque_l_adresse_comme_verifiee(): void
    {
        $user = User::factory()->create(['email' => 'connue@exemple.fr', 'email_verified_at' => null]);
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'connue@exemple.fr',
            'token' => $token,
            'password' => 'MotDePasseSolide2026!',
            'password_confirmation' => 'MotDePasseSolide2026!',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    /**
     * Bout en bout : le jeton vient de la notification reellement envoyee, pas de
     * Password::createToken. Un lien casse ou une notification muette echouerait ici.
     */
    public function test_le_parcours_complet_de_reinitialisation(): void
    {
        $user = User::factory()->create(['email' => 'cliente@exemple.fr']);

        $this->demander('cliente@exemple.fr')->assertOk();

        $jeton = null;
        Notification::assertSentTo($user, ResetPassword::class,
            function ($notification) use (&$jeton) {
                $jeton = $notification->token;

                return true;
            });

        $this->assertNotNull($jeton, 'La notification doit porter un jeton.');

        $this->postJson('/api/auth/reset-password', [
            'email' => 'cliente@exemple.fr',
            'token' => $jeton,
            'password' => 'NouveauMotDePasse2026!',
            'password_confirmation' => 'NouveauMotDePasse2026!',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'cliente@exemple.fr',
            'password' => 'NouveauMotDePasse2026!',
        ])->assertOk();
    }

    public function test_l_ancien_mot_de_passe_ne_fonctionne_plus_apres_reinitialisation(): void
    {
        $user = User::factory()->create(['email' => 'cliente@exemple.fr']);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'cliente@exemple.fr',
            'token' => Password::createToken($user),
            'password' => 'NouveauMotDePasse2026!',
            'password_confirmation' => 'NouveauMotDePasse2026!',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'cliente@exemple.fr',
            'password' => 'password',
        ])->assertStatus(422);
    }

    /** Un jeton ne doit servir qu'une fois. */
    public function test_un_jeton_ne_peut_pas_etre_rejoue(): void
    {
        $user = User::factory()->create(['email' => 'cliente@exemple.fr']);
        $jeton = Password::createToken($user);

        $payload = [
            'email' => 'cliente@exemple.fr',
            'token' => $jeton,
            'password' => 'NouveauMotDePasse2026!',
            'password_confirmation' => 'NouveauMotDePasse2026!',
        ];

        $this->postJson('/api/auth/reset-password', $payload)->assertOk();
        $this->postJson('/api/auth/reset-password', $payload)->assertStatus(422);
    }
}
