<?php

namespace App\Console\Commands;

use App\Models\PrivacyExport;
use App\Models\SchoolSessionExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeGeneratedExports extends Command
{
    protected $signature = 'exports:purge
        {--school-days=30 : Âge maximum des exports de session scolaire}
        {--privacy-days=7 : Âge maximum des exports RGPD}
        {--delete-orphans : Supprimer aussi les ZIP sans ligne en base}
        {--dry-run : Lister sans supprimer}';

    protected $description = 'Supprime les ZIP d\'export générés au-delà de leur durée de conservation.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('local');

        $total = 0;
        $octets = 0;

        foreach ([
            [SchoolSessionExport::class, (int) $this->option('school-days'), 'session scolaire'],
            [PrivacyExport::class, (int) $this->option('privacy-days'), 'RGPD'],
        ] as [$modele, $jours, $libelle]) {
            $expires = $modele::where('created_at', '<', now()->subDays($jours))->get();

            foreach ($expires as $export) {
                $chemin = $export->file_path;
                $taille = $chemin && $disk->exists($chemin) ? $disk->size($chemin) : 0;

                $this->line(sprintf(
                    '  %s %s · %s · %s',
                    $dryRun ? '[à purger]' : '[purgé]',
                    $libelle,
                    $export->created_at->toDateString(),
                    $chemin ?: '(aucun fichier)'
                ));

                if (! $dryRun) {
                    if ($chemin) {
                        $disk->delete($chemin);
                    }
                    $export->delete();
                }

                $total++;
                $octets += $taille;
            }
        }

        if ($this->option('delete-orphans')) {
            [$n, $o] = $this->purgeOrphans($disk, $dryRun);
            $total += $n;
            $octets += $o;
        }

        // Les repertoires de travail restent apres un export interrompu.
        foreach ($disk->directories('temp/exports') as $reste) {
            $this->line(sprintf('  %s temporaire · %s', $dryRun ? '[à purger]' : '[purgé]', $reste));
            if (! $dryRun) {
                $disk->deleteDirectory($reste);
            }
            $total++;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d export(s), %s.',
            $dryRun ? 'À purger :' : 'Purgé :',
            $total,
            $this->format($octets)
        ));

        return self::SUCCESS;
    }

    /**
     * Le seuil d'age evite d'attraper un export en cours d'ecriture.
     *
     * @return array{0: int, 1: int}
     */
    private function purgeOrphans($disk, bool $dryRun): array
    {
        $references = SchoolSessionExport::whereNotNull('file_path')->pluck('file_path')
            ->merge(PrivacyExport::whereNotNull('file_path')->pluck('file_path'))
            ->all();

        $seuil = now()->subDay()->getTimestamp();
        $n = 0;
        $octets = 0;

        foreach ($disk->allFiles('exports') as $fichier) {
            if (in_array($fichier, $references, true) || $disk->lastModified($fichier) > $seuil) {
                continue;
            }

            $taille = $disk->size($fichier);
            $this->line(sprintf('  %s orphelin · %s · %s',
                $dryRun ? '[à purger]' : '[purgé]', $this->format($taille), $fichier));

            if (! $dryRun) {
                $disk->delete($fichier);
            }

            $n++;
            $octets += $taille;
        }

        return [$n, $octets];
    }

    private function format(int $octets): string
    {
        foreach (['o', 'Ko', 'Mo', 'Go'] as $unite) {
            if ($octets < 1024) {
                return round($octets, 1).' '.$unite;
            }
            $octets = intdiv($octets, 1024);
        }

        return $octets.' To';
    }
}
