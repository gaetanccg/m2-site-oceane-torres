<?php

namespace Tests\Feature\Supervision;

use App\Mail\SupervisionAlertMail;
use App\Mail\SupervisionReportMail;
use App\Services\Supervision\HealthCheckService;
use App\Services\Supervision\HeartbeatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupervisionAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->fakeMinio();

        config([
            'supervision.storage.disk' => 'minio',
            'supervision.storage.witness' => null,
            'supervision.thresholds.database_slow_ms' => 5000,
            'supervision.thresholds.database_connect_slow_ms' => 5000,
            'supervision.alerts.enabled' => true,
            'supervision.alerts.cooldown_minutes' => 60,
            'supervision.alerts.recipient' => null,
            'mail.admin_email' => 'admin@example.test',
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

    public function test_nothing_is_sent_when_everything_is_healthy(): void
    {
        $this->artisan('supervision:alert')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_failed_job_triggers_an_alert_to_the_admin(): void
    {
        $this->seedFailedJob();

        $this->artisan('supervision:alert')->assertSuccessful();

        Mail::assertSent(SupervisionAlertMail::class, function (SupervisionAlertMail $mail) {
            return $mail->hasTo('admin@example.test')
                && in_array('queue_failed_jobs', $mail->reasons, true);
        });
    }

    public function test_a_missing_queue_worker_triggers_an_alert(): void
    {
        app(HeartbeatService::class)->forget(HeartbeatService::QUEUE);

        $this->artisan('supervision:alert')->assertSuccessful();

        Mail::assertSent(SupervisionAlertMail::class, function (SupervisionAlertMail $mail) {
            return in_array('queue_worker_missing', $mail->reasons, true);
        });
    }

    public function test_the_same_reason_is_not_notified_twice_within_the_cooldown(): void
    {
        $this->seedFailedJob();

        $this->artisan('supervision:alert')->assertSuccessful();
        $this->artisan('supervision:alert')->assertSuccessful();

        Mail::assertSent(SupervisionAlertMail::class, 1);
    }

    public function test_the_reason_is_notified_again_once_the_cooldown_expired(): void
    {
        $this->seedFailedJob();

        $this->artisan('supervision:alert')->assertSuccessful();

        $this->travel(61)->minutes();

        $heartbeats = app(HeartbeatService::class);
        $heartbeats->touch(HeartbeatService::SCHEDULER);
        $heartbeats->touch(HeartbeatService::QUEUE);

        $this->artisan('supervision:alert')->assertSuccessful();

        Mail::assertSent(SupervisionAlertMail::class, 2);
    }

    public function test_a_new_reason_is_notified_even_while_another_one_is_cooling_down(): void
    {
        $this->seedFailedJob();

        $this->artisan('supervision:alert')->assertSuccessful();

        app(HeartbeatService::class)->forget(HeartbeatService::QUEUE);

        $this->artisan('supervision:alert')->assertSuccessful();

        Mail::assertSent(SupervisionAlertMail::class, 2);
        Mail::assertSent(SupervisionAlertMail::class, function (SupervisionAlertMail $mail) {
            return $mail->reasons === ['queue_worker_missing'];
        });
    }

    public function test_alerts_can_be_disabled_entirely(): void
    {
        config(['supervision.alerts.enabled' => false]);
        $this->seedFailedJob();

        $this->artisan('supervision:alert')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_no_alert_is_sent_when_no_recipient_is_configured(): void
    {
        config(['supervision.alerts.recipient' => null, 'mail.admin_email' => null, 'mail.from.address' => null]);
        $this->seedFailedJob();

        $this->artisan('supervision:alert')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_dedicated_recipient_overrides_the_admin_email(): void
    {
        config(['supervision.alerts.recipient' => 'supervision@example.test']);
        $this->seedFailedJob();

        $this->artisan('supervision:alert')->assertSuccessful();

        Mail::assertSent(SupervisionAlertMail::class, function (SupervisionAlertMail $mail) {
            return $mail->hasTo('supervision@example.test');
        });
    }

    public function test_the_daily_report_is_sent_even_when_everything_is_healthy(): void
    {
        $this->artisan('supervision:report')->assertSuccessful();

        Mail::assertSent(SupervisionReportMail::class, function (SupervisionReportMail $mail) {
            return $mail->hasTo('admin@example.test') && $mail->snapshot->isHealthy();
        });
    }

    public function test_the_alert_email_renders_the_reason_and_the_remediation(): void
    {
        $snapshot = app(HealthCheckService::class)->snapshot();

        $html = (new SupervisionAlertMail($snapshot, ['queue_failed_jobs']))->render();

        $this->assertStringContainsString('Jobs en échec', $html);
        $this->assertStringContainsString('queue:retry all', $html);
        $this->assertStringContainsString('queue_failed_jobs', $html);
        $this->assertStringContainsString('scheduler', $html);
    }

    public function test_the_report_email_renders_every_probe(): void
    {
        $html = (new SupervisionReportMail(app(HealthCheckService::class)->snapshot()))->render();

        $this->assertStringContainsString('Rapport de santé quotidien', $html);

        foreach (['database', 'storage', 'queue', 'scheduler'] as $probe) {
            $this->assertStringContainsString($probe, $html);
        }
    }
}
