<?php

namespace Tests\Feature;

use App\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'attaquant@example.com',
                'password' => 'mauvais-mot-de-passe',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'attaquant@example.com',
            'password' => 'mauvais-mot-de-passe',
        ])->assertStatus(429);
    }

    public function test_login_throttle_keys_on_email_across_rotating_ips(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
                ->postJson('/api/auth/login', [
                    'email' => 'cible@example.com',
                    'password' => 'x',
                ])->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->postJson('/api/auth/login', [
                'email' => 'cible@example.com',
                'password' => 'x',
            ])->assertStatus(429);
    }

    public function test_login_from_a_fresh_ip_and_email_is_not_throttled(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
                ->postJson('/api/auth/login', [
                    'email' => 'premier@example.com',
                    'password' => 'x',
                ]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->postJson('/api/auth/login', [
                'email' => 'second@example.com',
                'password' => 'x',
            ])->assertStatus(422);
    }

    public function test_forgot_password_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/forgot-password', ['email' => 'inondation@example.com']);
        }

        $this->postJson('/api/auth/forgot-password', ['email' => 'inondation@example.com'])
            ->assertStatus(429);
    }

    public function test_orders_by_email_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/orders/by-email', ['email' => 'enumeration@example.com']);
        }

        $this->postJson('/api/orders/by-email', ['email' => 'enumeration@example.com'])
            ->assertStatus(429);
    }

    public function test_gallery_share_code_lookup_is_throttled(): void
    {
        Gallery::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/galleries/code/AAA'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }

        $this->getJson('/api/galleries/code/ZZZ999')->assertStatus(429);
    }

    public function test_contact_form_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/contact', []);
        }

        $this->postJson('/api/contact', [])->assertStatus(429);
    }

    public function test_booking_request_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/booking-request', []);
        }

        $this->postJson('/api/booking-request', [])->assertStatus(429);
    }
}
