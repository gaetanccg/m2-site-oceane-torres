<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use App\Models\GalleryProductType;
use App\Models\Photo;
use App\Models\User;
use App\Support\EffectiveDatabaseTarget;
use App\Support\LocalDatabaseHosts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Les donnees du filet de rendu (`npm run check:render`).
 *
 * Ces parcours s'executent dans un vrai navigateur contre l'API locale : il leur faut une
 * galerie consultable par code, des photos, un type de produit achetable et un compte
 * d'administration. Tout porte le marqueur `rendu-` et `--drop` le retire.
 *
 * Cette commande ECRIT. Elle refuse donc toute base non locale, exactement comme le seeder —
 * et par le meme chemin, `EffectiveDatabaseTarget`, pour que DB_URL ne l'aveugle pas.
 */
class PrepareRenderFixtures extends Command
{
    protected $signature = 'render:fixtures {--drop : Retire les donnees au lieu de les creer}';

    protected $description = 'Prepare (ou retire) les donnees du filet de tests de rendu';

    private const MARQUEUR = 'rendu-';

    private const CODE = 'RENDU1';

    private const EMAIL_ADMIN = 'rendu-admin@example.test';

    private const MOT_DE_PASSE = 'FiletDeRendu!2026';

    public function handle(): int
    {
        $this->refuserTouteBaseNonLocale();

        if ($this->option('drop')) {
            $this->retirer();

            return self::SUCCESS;
        }

        $this->retirer();
        $this->creer();

        return self::SUCCESS;
    }

    private function refuserTouteBaseNonLocale(): void
    {
        $connexion = (string) config('database.default');
        $cible = EffectiveDatabaseTarget::resolve(config("database.connections.{$connexion}", []));

        if (! LocalDatabaseHosts::allows($cible['host'])) {
            throw new RuntimeException(
                "Refuse : la connexion « {$connexion} » cible « {$cible['host']} » "
                ."(base « {$cible['database']} »), qui n'est pas reconnu comme local.\n"
                .'Cette commande ecrit des donnees ; elle ne tourne que sur une base locale.'
            );
        }
    }

    private function creer(): void
    {
        $admin = User::create([
            'first_name' => 'Filet',
            'last_name' => 'Rendu',
            'email' => self::EMAIL_ADMIN,
            'password' => Hash::make(self::MOT_DE_PASSE),
            'role' => 'admin',
        ]);

        $galerie = Gallery::create([
            'title' => self::MARQUEUR.'galerie',
            'description' => 'Galerie du filet de rendu.',
            'share_code' => self::CODE,
            'type' => 'private',
            'is_active' => true,
        ]);

        GalleryProductType::create([
            'gallery_id' => $galerie->id,
            'product_type' => 'digital',
            'price' => 5.00,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            Photo::create([
                'gallery_id' => $galerie->id,
                'title' => self::MARQUEUR."photo-{$i}",
                'file_path' => "rendu/{$galerie->id}/photo-{$i}.jpg",
                'file_path_hd' => "rendu/{$galerie->id}/photo-{$i}.jpg",
                'file_path_preview' => "rendu/{$galerie->id}/preview-{$i}.jpg",
                'file_path_thumbnail' => "rendu/{$galerie->id}/thumb-{$i}.jpg",
                'is_processed' => true,
                'sort_order' => $i,
            ]);
        }

        // Le harnais lit ce JSON : rien n'est code en dur des deux cotes.
        $this->line(json_encode([
            'share_code' => self::CODE,
            'gallery_id' => $galerie->id,
            'admin_email' => self::EMAIL_ADMIN,
            'admin_password' => self::MOT_DE_PASSE,
            'photos' => 3,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function retirer(): void
    {
        DB::transaction(function () {
            $galeries = Gallery::where('title', 'like', self::MARQUEUR.'%')->pluck('id');

            Photo::whereIn('gallery_id', $galeries)->delete();
            GalleryProductType::whereIn('gallery_id', $galeries)->delete();
            Gallery::whereIn('id', $galeries)->delete();
            User::where('email', self::EMAIL_ADMIN)->delete();
        });
    }
}
