<?php

namespace Tests\Feature\Supervision;

use App\Services\Supervision\HeartbeatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-supervision-de-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHealthySystem();
    }

    /** Seuils confortables : la latence de la base de test ne doit pas rendre les tests instables. */
    private function seedHealthySystem(): void
    {
        $this->fakeMinio();

        config([
            'supervision.storage.disk' => 'minio',
            'supervision.storage.witness' => null,
            'supervision.thresholds.database_slow_ms' => 5000,
            'supervision.token' => self::TOKEN,
        ]);

        $heartbeats = app(HeartbeatService::class);
        $heartbeats->touch(HeartbeatService::SCHEDULER);
        $heartbeats->touch(HeartbeatService::QUEUE);
    }

    private function seedFailedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException: boom',
            'failed_at' => now(),
        ]);
    }

    public function test_public_health_returns_ok_and_keeps_the_historic_contract(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'message' => 'API Océane Torres Photographie',
            ])
            ->assertJsonStructure(['status', 'message', 'version', 'timestamp']);
    }

    public function test_public_health_never_exposes_the_probe_detail(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonMissingPath('checks')
            ->assertJsonMissingPath('environment');
    }

    public function test_public_health_returns_503_when_a_probe_is_degraded(): void
    {
        $this->seedFailedJob();

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJson(['status' => 'degraded']);
    }

    public function test_public_health_returns_503_when_the_scheduler_heartbeat_is_missing(): void
    {
        app(HeartbeatService::class)->forget(HeartbeatService::SCHEDULER);

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJson(['status' => 'down']);
    }

    public function test_public_health_reports_degraded_when_the_scheduler_is_stale(): void
    {
        config(['supervision.thresholds.scheduler_stale_minutes' => 120]);

        $this->travel(3)->hours();

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJson(['status' => 'degraded']);
    }

    public function test_liveness_stays_ok_even_when_the_system_is_degraded(): void
    {
        $heartbeats = app(HeartbeatService::class);
        $heartbeats->forget(HeartbeatService::SCHEDULER);
        $heartbeats->forget(HeartbeatService::QUEUE);

        $this->getJson('/api/health/live')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_api_root_keeps_the_historic_payload(): void
    {
        $this->getJson('/api/')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'message' => 'API Océane Torres Photographie'])
            ->assertJsonStructure(['status', 'message', 'version', 'timestamp']);
    }

    public function test_details_lists_every_probe_when_the_token_is_valid(): void
    {
        $this->withHeader('X-Health-Token', self::TOKEN)
            ->getJson('/api/health/details')
            ->assertOk()
            ->assertJson(['status' => 'ok'])
            ->assertJsonStructure([
                'status', 'version', 'environment', 'timestamp', 'duration_ms',
                'checks' => [
                    'database' => ['status'],
                    'storage' => ['status'],
                    'queue' => ['status'],
                    'scheduler' => ['status'],
                ],
            ]);
    }

    public function test_details_also_accepts_the_token_as_a_query_parameter(): void
    {
        $this->getJson('/api/health/details?token='.self::TOKEN)->assertOk();
    }

    public function test_details_is_forbidden_without_a_token(): void
    {
        $this->getJson('/api/health/details')->assertForbidden();
    }

    public function test_details_is_forbidden_with_a_wrong_token(): void
    {
        $this->withHeader('X-Health-Token', 'mauvais-jeton')
            ->getJson('/api/health/details')
            ->assertForbidden();
    }

    public function test_details_stays_closed_when_no_token_is_configured(): void
    {
        config(['supervision.token' => null]);

        $this->getJson('/api/health/details?token=')->assertForbidden();
        $this->getJson('/api/health/details')->assertForbidden();
    }

    public function test_details_reports_503_when_degraded(): void
    {
        $this->seedFailedJob();

        $this->withHeader('X-Health-Token', self::TOKEN)
            ->getJson('/api/health/details')
            ->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => ['queue' => ['reasons' => ['queue_failed_jobs']]],
            ]);
    }

    public function test_admin_session_reaches_the_detail_without_a_token(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/health')
            ->assertOk()
            ->assertJsonStructure(['checks' => ['database', 'storage', 'queue', 'scheduler']]);
    }

    public function test_admin_health_is_forbidden_to_a_standard_client(): void
    {
        $this->actingAsClient();

        $this->getJson('/api/admin/health')->assertForbidden();
    }

    public function test_admin_health_requires_authentication(): void
    {
        $this->getJson('/api/admin/health')->assertUnauthorized();
    }

    public function test_the_schema_listing_endpoint_no_longer_exists(): void
    {
        $this->getJson('/api/health/tables')->assertNotFound();
    }

    public function test_database_diagnostic_requires_the_token(): void
    {
        $this->getJson('/api/health/database')->assertForbidden();
    }

    public function test_database_diagnostic_never_exposes_the_host(): void
    {
        $response = $this->withHeader('X-Health-Token', self::TOKEN)
            ->getJson('/api/health/database')
            ->assertOk()
            ->assertJson(['status' => 'connected'])
            ->assertJsonMissingPath('host');

        $this->assertStringNotContainsString(
            (string) config('database.connections.pgsql.host'),
            $response->getContent(),
        );
    }

    public function test_a_slow_database_is_reported_as_degraded(): void
    {
        // Seuil à 0 ms : couvre le chemin `database_slow` sans dépendre du temps réel.
        config(['supervision.thresholds.database_slow_ms' => 0]);

        $this->withHeader('X-Health-Token', self::TOKEN)
            ->getJson('/api/health/details')
            ->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => ['database' => ['reasons' => ['database_slow']]],
            ]);
    }

    public function test_an_unreachable_storage_is_reported_as_down(): void
    {
        config(['supervision.storage.disk' => 'disque-inexistant']);

        $this->withHeader('X-Health-Token', self::TOKEN)
            ->getJson('/api/health/details')
            ->assertStatus(503)
            ->assertJson([
                'status' => 'down',
                'checks' => ['storage' => ['status' => 'down']],
            ]);
    }

    public function test_queue_depth_and_stalled_jobs_are_reported(): void
    {
        config([
            'queue.default' => 'database',
            'supervision.thresholds.queue_depth' => 1,
            'supervision.thresholds.queue_oldest_pending_minutes' => 15,
        ]);

        $now = now()->getTimestamp();

        foreach (range(1, 2) as $index) {
            DB::table('jobs')->insert([
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now - 3600,
                'created_at' => $now - 3600,
            ]);
        }

        $this->withHeader('X-Health-Token', self::TOKEN)
            ->getJson('/api/health/details')
            ->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => [
                    'queue' => [
                        'reasons' => ['queue_depth', 'queue_stalled'],
                        'details' => ['pending' => 2],
                    ],
                ],
            ]);
    }
}
