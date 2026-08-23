<?php

namespace Tests\Feature\School;

use App\Exceptions\BusinessException;
use App\Models\SchoolSession;
use App\Services\SchoolSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * extractTo() sans controle accepte tout : un ZIP de quelques Mo peut remplir le disque du
 * NAS, et un nom d'entree peut viser hors du dossier d'extraction.
 */
class ZipExtractionTest extends TestCase
{
    use RefreshDatabase;

    private string $zipPath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->zipPath = storage_path('framework/testing/essai-'.uniqid().'.zip');
    }

    protected function tearDown(): void
    {
        @unlink($this->zipPath);
        parent::tearDown();
    }

    /**
     * Chaque fixture porte une arborescence classe/enfant valide : le parsing l'exige, et
     * ces tests portent sur l'extraction.
     *
     * @param  array<string, string>  $entrees
     */
    private function sessionAvec(array $entrees): SchoolSession
    {
        // DEUX classes au premier niveau : resolveRootDirectory replie la racine quand
        // elle n'en contient qu'un seul, le prenant pour un dossier d'emballage.
        $entrees = [
            'CP/Alice/portrait.jpg' => 'octets',
            'CE1/Bob/portrait.jpg' => 'octets',
            ...$entrees,
        ];

        $zip = new \ZipArchive;
        $zip->open($this->zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($entrees as $nom => $contenu) {
            $zip->addFromString($nom, $contenu);
        }
        $zip->close();

        $relatif = 'uploads/essai.zip';
        Storage::disk('local')->put($relatif, file_get_contents($this->zipPath));

        return SchoolSession::create(['title' => 'Session', 'zip_path' => $relatif]);
    }

    private function extraire(SchoolSession $session): array
    {
        return app(SchoolSessionService::class)->extractAndParseZip($session);
    }

    private function racine(SchoolSession $session): string
    {
        return storage_path('app/private/temp/school-sessions/'.$session->id.'/extracted');
    }

    public function test_une_archive_normale_est_extraite(): void
    {
        $session = $this->sessionAvec([
            'CP/Alice/photo1.jpg' => 'octets',
        ]);

        $this->extraire($session);

        $this->assertFileExists($this->racine($session).'/CP/Alice/photo1.jpg');
    }

    public function test_une_entree_qui_remonte_hors_du_dossier_est_ignoree(): void
    {
        $session = $this->sessionAvec([
            '../../evade.jpg' => 'octets',
            'CP/Alice/photo.jpg' => 'octets',
        ]);

        $this->extraire($session);

        $this->assertFileDoesNotExist(dirname($this->racine($session), 2).'/evade.jpg');
        $this->assertFileExists($this->racine($session).'/CP/Alice/photo.jpg');
    }

    public function test_un_chemin_absolu_est_ignore(): void
    {
        $session = $this->sessionAvec(['/tmp/evade-absolu.jpg' => 'octets', 'CP/A/ok.jpg' => 'x']);

        $this->extraire($session);

        $this->assertFileDoesNotExist('/tmp/evade-absolu.jpg');
    }

    /** Seules les extensions que la chaine sait traiter sont extraites. */
    public function test_les_fichiers_non_images_ne_sont_pas_extraits(): void
    {
        $session = $this->sessionAvec([
            'CP/A/script.php' => '<?php echo "x";',
            'CP/A/archive.zip' => 'PK',
            'CP/A/photo.jpg' => 'octets',
        ]);

        $this->extraire($session);

        $this->assertFileDoesNotExist($this->racine($session).'/CP/A/script.php');
        $this->assertFileDoesNotExist($this->racine($session).'/CP/A/archive.zip');
        $this->assertFileExists($this->racine($session).'/CP/A/photo.jpg');
    }

    public function test_une_archive_trop_nombreuse_est_refusee(): void
    {
        $entrees = [];
        for ($i = 0; $i < 5001; $i++) {
            $entrees["CP/A/p{$i}.jpg"] = 'x';
        }
        $session = $this->sessionAvec($entrees);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessageMatches('/limite est de 5000/');

        $this->extraire($session);
    }

    /** Une bombe de decompression : un fichier qui gonfle d'un facteur enorme. */
    public function test_une_bombe_de_decompression_est_refusee(): void
    {
        $session = $this->sessionAvec(['CP/A/bombe.jpg' => str_repeat('0', 2 * 1024 * 1024)]);

        $this->expectException(BusinessException::class);

        $this->extraire($session);
    }
}
