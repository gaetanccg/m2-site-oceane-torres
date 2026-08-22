<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_the_token_when_authenticated_by_bearer(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_revoked_token_can_no_longer_reach_the_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/user')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_tokens_when_authenticated_by_session(): void
    {
        $user = User::factory()->create();
        $user->createToken('auth-token');

        $this->assertSame(1, DB::table('personal_access_tokens')->count());

        $this->actingAs($user)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_logout_leaves_other_users_tokens_untouched(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->createToken('auth-token');
        $other->createToken('auth-token');

        $this->actingAs($user)->postJson('/api/auth/logout')->assertOk();

        $this->assertSame(1, DB::table('personal_access_tokens')->count());
        $this->assertSame(
            $other->id,
            DB::table('personal_access_tokens')->value('tokenable_id')
        );
    }
}
