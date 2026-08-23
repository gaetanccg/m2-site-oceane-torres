<?php

namespace Tests\Feature\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Rien ne tracait les connexions : un bourrage d'identifiants ne laissait aucune trace
 * exploitable, et une connexion depuis une IP inhabituelle passait inapercue.
 */
class LoginJournalTest extends TestCase
{
    use RefreshDatabase;

    private function tenter(string $email, string $password): TestResponse
    {
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password]);
    }

    public function test_une_connexion_reussie_est_journalisee(): void
    {
        $user = User::factory()->create(['email' => 'cliente@exemple.fr']);

        $this->tenter('cliente@exemple.fr', 'password')->assertOk();

        $entree = LoginAttempt::sole();
        $this->assertTrue($entree->successful);
        $this->assertSame('cliente@exemple.fr', $entree->email);
        $this->assertSame($user->id, $entree->user_id);
        $this->assertNotNull($entree->ip);
    }

    public function test_un_echec_est_journalise_sans_compte_associe(): void
    {
        User::factory()->create(['email' => 'cliente@exemple.fr']);

        $this->tenter('cliente@exemple.fr', 'mauvais-mot-de-passe')->assertStatus(422);

        $entree = LoginAttempt::sole();
        $this->assertFalse($entree->successful);
        $this->assertNull($entree->user_id);
    }

    public function test_une_tentative_sur_une_adresse_inconnue_est_journalisee(): void
    {
        $this->tenter('inconnue@exemple.fr', 'peu-importe')->assertStatus(422);

        $this->assertSame('inconnue@exemple.fr', LoginAttempt::sole()->email);
    }

    public function test_l_adresse_est_normalisee_en_minuscules(): void
    {
        User::factory()->create(['email' => 'cliente@exemple.fr']);

        $this->tenter('Cliente@Exemple.FR', 'password');

        $this->assertSame('cliente@exemple.fr', LoginAttempt::sole()->email);
    }

    public function test_le_mot_de_passe_n_est_jamais_journalise(): void
    {
        User::factory()->create(['email' => 'cliente@exemple.fr']);

        $this->tenter('cliente@exemple.fr', 'SecretAbsolu2026!');

        $this->assertStringNotContainsString(
            'SecretAbsolu2026!',
            LoginAttempt::sole()->toJson()
        );
    }

    /** Le journal porte email + IP : il ne suit pas la conservation comptable. */
    public function test_la_purge_respecte_la_conservation_du_journal(): void
    {
        $ancienne = LoginAttempt::create([
            'email' => 'vieille@exemple.fr', 'ip' => '10.0.0.1', 'successful' => false,
        ]);
        $ancienne->forceFill(['created_at' => now()->subDays(120)])->save();

        $recente = LoginAttempt::create([
            'email' => 'recente@exemple.fr', 'ip' => '10.0.0.2', 'successful' => true,
        ]);

        $this->artisan('privacy:purge-expired', ['--login-days' => 90])->assertSuccessful();

        $this->assertModelMissing($ancienne);
        $this->assertModelExists($recente);
    }
}
