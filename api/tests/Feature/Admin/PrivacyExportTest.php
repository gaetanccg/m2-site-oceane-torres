<?php

namespace Tests\Feature\Admin;

use App\Jobs\GeneratePrivacyExportJob;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PrivacyAuditLog;
use App\Models\PrivacyExport;
use App\Models\User;
use App\Services\Privacy\PrivacyExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Export global RGPD (ZIP : un JSON par table + PDF de factures), généré en asynchrone.
 */
class PrivacyExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_all_requires_admin(): void
    {
        $this->postJson('/api/admin/privacy/export-all')->assertStatus(401);

        $this->actingAsClient();
        $this->postJson('/api/admin/privacy/export-all')->assertStatus(403);
    }

    public function test_export_all_creates_record_dispatches_job_and_audits(): void
    {
        Queue::fake();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/privacy/export-all');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('export.type', 'global')
            ->assertJsonPath('export.status', 'pending');

        $this->assertSame(1, PrivacyExport::where('type', 'global')->count());
        Queue::assertPushed(GeneratePrivacyExportJob::class);
        $this->assertSame(1, PrivacyAuditLog::where('action', 'export')->count());
    }

    public function test_build_global_produces_zip_with_data_and_invoice_pdf(): void
    {
        Storage::fake('local');
        Storage::fake('minio');

        // Une facture dont le PDF existe sur le stockage.
        $order = Order::factory()->create();
        Storage::disk('minio')->put('invoices/inv-1.pdf', '%PDF-fake');
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-2026-0001',
            'file_path' => 'invoices/inv-1.pdf',
        ]);

        $export = PrivacyExport::create(['type' => 'global', 'status' => 'pending']);
        app(PrivacyExportService::class)->build($export);

        $export->refresh();
        $this->assertSame('completed', $export->status);
        $this->assertNotNull($export->file_path);
        $this->assertTrue(Storage::disk('local')->exists($export->file_path));
        $this->assertGreaterThan(0, $export->file_size_bytes);

        // Vérifie le contenu de l'archive.
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path($export->file_path));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->statIndex($i)['name'];
        }
        $zip->close();

        $this->assertContains('MANIFEST.json', $names);
        $this->assertContains('data/users.json', $names);
        $this->assertContains('data/orders.json', $names);
        $this->assertContains('invoices/INV-2026-0001.pdf', $names);
    }

    public function test_export_subject_creates_record_dispatches_job_and_audits(): void
    {
        Queue::fake();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/privacy/export-subject', [
            'type' => 'email',
            'value' => 'sub@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('export.type', 'subject')
            ->assertJsonPath('export.status', 'pending');

        $export = PrivacyExport::where('type', 'subject')->first();
        $this->assertNotNull($export);
        $this->assertSame('email', $export->subject_type);
        $this->assertSame('sub@example.com', $export->subject_value);
        Queue::assertPushed(GeneratePrivacyExportJob::class);
        $this->assertSame(1, PrivacyAuditLog::where('action', 'export')->where('subject_value', 'sub@example.com')->count());
    }

    public function test_export_subject_validation(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/admin/privacy/export-subject', ['type' => 'foo', 'value' => 'x'])
            ->assertStatus(422);
    }

    public function test_build_subject_produces_zip_with_data_and_person_invoices(): void
    {
        Storage::fake('local');
        Storage::fake('minio');

        $user = User::factory()->create(['email' => 'sub@example.com']);
        $order = Order::factory()->create(['user_id' => $user->id, 'guest_email' => 'sub@example.com']);
        Storage::disk('minio')->put('invoices/sub-1.pdf', '%PDF-fake');
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-SUB-0001',
            'file_path' => 'invoices/sub-1.pdf',
        ]);

        $export = PrivacyExport::create([
            'type' => 'subject',
            'subject_type' => 'email',
            'subject_value' => 'sub@example.com',
            'status' => 'pending',
        ]);
        app(PrivacyExportService::class)->build($export);

        $export->refresh();
        $this->assertSame('completed', $export->status);
        $this->assertTrue(Storage::disk('local')->exists($export->file_path));

        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path($export->file_path));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->statIndex($i)['name'];
        }
        $zip->close();

        $this->assertContains('data.json', $names);
        $this->assertContains('invoices/INV-SUB-0001.pdf', $names);
    }

    public function test_download_is_404_when_not_completed(): void
    {
        $this->actingAsAdmin();
        $export = PrivacyExport::create(['type' => 'global', 'status' => 'pending']);

        $this->getJson("/api/admin/privacy/exports/{$export->id}/download")
            ->assertStatus(404);
    }

    public function test_download_returns_zip_when_completed(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin();

        Storage::disk('local')->put('exports/privacy/test.zip', 'zip-bytes');
        $export = PrivacyExport::create([
            'type' => 'global',
            'status' => 'completed',
            'file_path' => 'exports/privacy/test.zip',
            'file_size_bytes' => 9,
        ]);

        $response = $this->get("/api/admin/privacy/exports/{$export->id}/download");

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }
}
