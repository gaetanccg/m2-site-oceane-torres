<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /admin/logs — visionneuse des logs applicatifs (tail + filtres).
 */
class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    private ?string $backup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logPath = storage_path('logs/laravel.log');
        $this->backup = is_file($this->logPath) ? file_get_contents($this->logPath) : null;
    }

    protected function tearDown(): void
    {
        if ($this->backup !== null) {
            file_put_contents($this->logPath, $this->backup);
        } elseif (is_file($this->logPath)) {
            @unlink($this->logPath);
        }
        parent::tearDown();
    }

    private function seedLog(): void
    {
        file_put_contents(
            $this->logPath,
            "[2026-07-15 10:00:00] testing.ERROR: boom happened\n".
            "[2026-07-15 10:00:01] testing.INFO: hello world\n",
        );
    }

    public function test_index_reads_the_daily_file_when_rotation_is_active(): void
    {
        config([
            'logging.default' => 'stack',
            'logging.channels.stack.channels' => 'daily',
        ]);

        $dailyPath = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        $existed = is_file($dailyPath);
        $dailyBackup = $existed ? file_get_contents($dailyPath) : null;

        file_put_contents($dailyPath, "[2026-07-15 10:00:00] testing.ERROR: rotation active\n");
        $this->seedLog();

        try {
            $this->actingAsAdmin();

            $response = $this->getJson('/api/admin/logs')->assertOk();

            $this->assertStringContainsString('rotation active', implode("\n", $response->json('lines')));
            $this->assertStringNotContainsString('boom happened', implode("\n", $response->json('lines')));
        } finally {
            if ($dailyBackup !== null) {
                file_put_contents($dailyPath, $dailyBackup);
            } elseif (is_file($dailyPath)) {
                @unlink($dailyPath);
            }
        }
    }

    public function test_requires_admin(): void
    {
        $this->getJson('/api/admin/logs')->assertStatus(401);

        $this->actingAsClient();
        $this->getJson('/api/admin/logs')->assertStatus(403);
    }

    public function test_index_returns_lines(): void
    {
        $this->actingAsAdmin();
        $this->seedLog();

        $response = $this->getJson('/api/admin/logs');

        $response->assertOk()->assertJsonPath('success', true);
        $lines = $response->json('lines');
        $this->assertNotEmpty($lines);
    }

    public function test_index_filters_by_level(): void
    {
        $this->actingAsAdmin();
        $this->seedLog();

        $lines = $this->getJson('/api/admin/logs?level=error')->assertOk()->json('lines');

        $joined = implode("\n", $lines);
        $this->assertStringContainsString('boom happened', $joined);
        $this->assertStringNotContainsString('hello world', $joined);
    }

    public function test_index_filters_by_search(): void
    {
        $this->actingAsAdmin();
        $this->seedLog();

        $lines = $this->getJson('/api/admin/logs?search=hello')->assertOk()->json('lines');

        $joined = implode("\n", $lines);
        $this->assertStringContainsString('hello world', $joined);
        $this->assertStringNotContainsString('boom happened', $joined);
    }

    public function test_download_returns_file(): void
    {
        $this->actingAsAdmin();
        $this->seedLog();

        $this->get('/api/admin/logs/download')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_clear_requires_admin(): void
    {
        $this->deleteJson('/api/admin/logs')->assertStatus(401);

        $this->actingAsClient();
        $this->deleteJson('/api/admin/logs')->assertStatus(403);
    }

    public function test_clear_empties_the_log_file(): void
    {
        $this->actingAsAdmin();
        $this->seedLog();

        $this->deleteJson('/api/admin/logs')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Le fichier ne contient plus les anciennes lignes ; seule la ligne de
        // traçabilité de l'action (re-loggée aussitôt) peut subsister.
        $content = file_get_contents($this->logPath);
        $this->assertStringNotContainsString('boom happened', $content);
        $this->assertStringNotContainsString('hello world', $content);
    }
}
