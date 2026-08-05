<?php

namespace Tests\Feature\Supervision;

use App\Services\Supervision\HeartbeatService;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HeartbeatTest extends TestCase
{
    public function test_the_cache_key_matches_the_documented_one(): void
    {
        // Clé documentée dans docs/supervision.md : contrat d'exploitation.
        $this->assertSame(
            'supervision:scheduler:heartbeat',
            app(HeartbeatService::class)->key(HeartbeatService::SCHEDULER),
        );
    }

    public function test_the_command_writes_the_heartbeat(): void
    {
        $this->artisan('supervision:heartbeat scheduler')->assertSuccessful();

        $this->assertNotNull(app(HeartbeatService::class)->ageInSeconds(HeartbeatService::SCHEDULER));
    }

    public function test_an_unknown_component_is_rejected(): void
    {
        $this->artisan('supervision:heartbeat nginx')->assertFailed();
        $this->artisan('supervision:heartbeat:check nginx')->assertFailed();
    }

    public function test_the_check_fails_when_no_heartbeat_was_ever_written(): void
    {
        $this->artisan('supervision:heartbeat:check scheduler --max-age=900')->assertFailed();
    }

    public function test_the_check_succeeds_on_a_fresh_heartbeat(): void
    {
        app(HeartbeatService::class)->touch(HeartbeatService::SCHEDULER);

        $this->artisan('supervision:heartbeat:check scheduler --max-age=900')->assertSuccessful();
    }

    public function test_the_check_fails_on_a_stale_heartbeat(): void
    {
        $heartbeats = app(HeartbeatService::class);
        Cache::put(
            $heartbeats->key(HeartbeatService::SCHEDULER),
            now()->subHour()->getTimestamp(),
            now()->addDay(),
        );

        $this->artisan('supervision:heartbeat:check scheduler --max-age=900')->assertFailed();
    }

    public function test_the_check_falls_back_on_the_configured_threshold(): void
    {
        config(['supervision.thresholds.queue_worker_stale_minutes' => 10]);

        $heartbeats = app(HeartbeatService::class);
        Cache::put(
            $heartbeats->key(HeartbeatService::QUEUE),
            now()->subMinutes(20)->getTimestamp(),
            now()->addDay(),
        );

        $this->artisan('supervision:heartbeat:check queue')->assertFailed();
    }

    public function test_the_queue_worker_writes_its_heartbeat_when_looping(): void
    {
        $heartbeats = app(HeartbeatService::class);
        $this->assertNull($heartbeats->ageInSeconds(HeartbeatService::QUEUE));

        event(new Looping('database', 'default'));

        $this->assertNotNull($heartbeats->ageInSeconds(HeartbeatService::QUEUE));
    }

    public function test_the_worker_heartbeat_write_is_throttled(): void
    {
        config(['supervision.heartbeat.write_interval_seconds' => 60]);

        $heartbeats = app(HeartbeatService::class);

        $this->assertTrue($heartbeats->touchThrottled(HeartbeatService::QUEUE));
        $this->assertFalse($heartbeats->touchThrottled(HeartbeatService::QUEUE));
    }
}
