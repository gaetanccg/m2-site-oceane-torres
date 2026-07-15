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
}
