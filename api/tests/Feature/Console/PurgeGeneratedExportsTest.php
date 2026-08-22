<?php

namespace Tests\Feature\Console;

use App\Models\SchoolSession;
use App\Models\SchoolSessionExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeGeneratedExportsTest extends TestCase
{
    use RefreshDatabase;

    private function export(int $joursDAge, string $chemin): SchoolSessionExport
    {
        Storage::disk('local')->put($chemin, 'zip');

        $export = SchoolSessionExport::create([
            'school_session_id' => SchoolSession::create(['title' => 'Session'])->id,
            'status' => 'completed',
            'include_digital' => false,
            'file_path' => $chemin,
            'file_size_bytes' => 3,
            'total_items' => 1,
            'processed_items' => 1,
        ]);

        $export->forceFill(['created_at' => now()->subDays($joursDAge)])->save();

        return $export;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_un_export_recent_est_conserve(): void
    {
        $export = $this->export(2, 'exports/school-sessions/recent.zip');

        $this->artisan('exports:purge')->assertSuccessful();

        Storage::disk('local')->assertExists('exports/school-sessions/recent.zip');
        $this->assertModelExists($export);
    }

    public function test_un_export_perime_est_supprime_avec_sa_ligne(): void
    {
        $export = $this->export(45, 'exports/school-sessions/vieux.zip');

        $this->artisan('exports:purge')->assertSuccessful();

        Storage::disk('local')->assertMissing('exports/school-sessions/vieux.zip');
        $this->assertModelMissing($export);
    }

    public function test_a_blanc_ne_supprime_rien(): void
    {
        $export = $this->export(45, 'exports/school-sessions/vieux.zip');

        $this->artisan('exports:purge', ['--dry-run' => true])->assertSuccessful();

        Storage::disk('local')->assertExists('exports/school-sessions/vieux.zip');
        $this->assertModelExists($export);
    }

    /** Un ZIP sans ligne en base ne sera jamais servi : downloadExport repond 410. */
    public function test_un_orphelin_n_est_supprime_que_sur_demande(): void
    {
        Storage::disk('local')->put('exports/school-sessions/orphelin.zip', 'zip');
        touch(Storage::disk('local')->path('exports/school-sessions/orphelin.zip'), now()->subDays(3)->getTimestamp());

        $this->artisan('exports:purge')->assertSuccessful();
        Storage::disk('local')->assertExists('exports/school-sessions/orphelin.zip');

        $this->artisan('exports:purge', ['--delete-orphans' => true])->assertSuccessful();
        Storage::disk('local')->assertMissing('exports/school-sessions/orphelin.zip');
    }

    public function test_un_orphelin_recent_est_epargne(): void
    {
        Storage::disk('local')->put('exports/school-sessions/en-cours.zip', 'zip');

        $this->artisan('exports:purge', ['--delete-orphans' => true])->assertSuccessful();

        Storage::disk('local')->assertExists('exports/school-sessions/en-cours.zip');
    }

    public function test_les_repertoires_de_travail_sont_nettoyes(): void
    {
        Storage::disk('local')->put('temp/exports/abandonne/partiel.zip', 'zip');

        $this->artisan('exports:purge')->assertSuccessful();

        Storage::disk('local')->assertMissing('temp/exports/abandonne/partiel.zip');
    }
}
