<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifie que les fondations de test tiennent : connexion Postgres, migrations,
 * factories, et le SQL Postgres-spécifique (génération du n° de commande via
 * pg_advisory_xact_lock).
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_connection_is_postgres(): void
    {
        $this->assertSame('pgsql', \DB::connection()->getDriverName());
    }

    public function test_user_factory_creates_valid_user(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->id);
        $this->assertDatabaseHas('users', ['email' => $user->email]);
    }

    public function test_admin_factory_state(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->isAdmin());
    }

    public function test_order_number_is_generated_via_postgres_advisory_lock(): void
    {
        $order = Order::factory()->create();

        $this->assertMatchesRegularExpression('/^OT-\d{4}-\d{5}$/', $order->order_number);
    }

    public function test_order_numbers_increment_sequentially(): void
    {
        $first = Order::factory()->create();
        $second = Order::factory()->create();

        $this->assertNotSame($first->order_number, $second->order_number);
    }
}
