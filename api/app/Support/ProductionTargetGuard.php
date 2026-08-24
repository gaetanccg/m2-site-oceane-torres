<?php

namespace App\Support;

use RuntimeException;

/**
 * Refuse de demarrer si un environnement qui n'est PAS la production est configure pour
 * agir sur la production.
 *
 * Pourquoi ce garde-fou existe : le 22 août 2026, « docker compose exec laravel php artisan
 * test » a efface la base de production. Le conteneur portait des identifiants de production
 * alors qu'APP_ENV valait « local », et RefreshDatabase lance migrate:fresh. Rien, nulle
 * part, ne trouvait cette combinaison anormale.
 *
 * Tests\RefusesNonLocalDatabase couvre la suite de tests. Celui-ci couvre tout le reste :
 * chaque requete, chaque commande artisan, chaque job de queue, chaque tache planifiee.
 *
 * Aucune variable d'environnement ne le desactive, et c'est deliberé : une echappatoire
 * serait empruntee par la premiere personne pressee. Pour agir sur la production, il faut
 * APP_ENV=production — donc le dire.
 */
final class ProductionTargetGuard
{
    /**
     * @param  callable(string): mixed  $config  lecteur de configuration
     */
    public static function assert(string $environment, callable $config): void
    {
        if ($environment === 'production') {
            return;
        }

        $valeurs = [];
        $cles = [
            'database.default',
            'filesystems.disks.minio.bucket',
            'sumup.environment',
            'sentry.environment',
            'sentry.dsn',
            'supervision.healthchecks.rgpd_cleanup_url',
            'supervision.healthchecks.reconcile_orders_url',
        ];

        foreach ($cles as $cle) {
            $valeurs[$cle] = $config($cle);
        }

        $connexion = (string) ($valeurs['database.default'] ?? 'pgsql');
        // `url` en fait partie : sans elle, DB_URL rend le garde-fou aveugle.
        foreach (['url', 'host', 'username', 'database', 'port'] as $champ) {
            $valeurs["database.connections.{$connexion}.{$champ}"] = $config("database.connections.{$connexion}.{$champ}");
        }

        $detectees = ProductionTargets::detectedIn($valeurs);
        $violations = ProductionTargets::violatedPoliciesIn($valeurs);

        // En « local », la base doit etre LOCALE — pas seulement « pas la production ».
        // Interdire la seule production laissait le poste ecrire dans la preproduction, et
        // un migrate:fresh l'aurait videe de la meme facon. Le poste travaille sur
        // postgres-dev, avec un jeu de donnees anonymise.
        if ($environment === 'local') {
            $cible = EffectiveDatabaseTarget::resolve([
                'url' => $valeurs["database.connections.{$connexion}.url"] ?? null,
                'host' => $valeurs["database.connections.{$connexion}.host"] ?? null,
                'username' => $valeurs["database.connections.{$connexion}.username"] ?? null,
                'database' => $valeurs["database.connections.{$connexion}.database"] ?? null,
                'port' => $valeurs["database.connections.{$connexion}.port"] ?? null,
            ]);

            if (! LocalDatabaseHosts::allows($cible['host'])) {
                $violations['base de donnees distante'] = sprintf(
                    'en local la base doit etre locale — « %s » ne l\'est pas (acceptes : %s)',
                    $cible['host'] === '' ? 'non definie' : $cible['host'],
                    implode(', ', LocalDatabaseHosts::ALLOWED),
                );
            }
        }

        if ($detectees === [] && $violations === []) {
            return;
        }

        throw new RuntimeException(self::message($environment, $detectees, $violations));
    }

    /**
     * @param  array<string, string>  $detectees
     * @param  array<string, string>  $violations
     */
    private static function message(string $environment, array $detectees, array $violations): string
    {
        // Le titre doit dire la verite : « touche la production » est faux quand le seul
        // grief est une base distante de preproduction.
        $lignes = [
            $detectees !== []
                ? sprintf('Arret : APP_ENV vaut « %s » mais la configuration touche la PRODUCTION.', $environment)
                : sprintf('Arret : la configuration de l\'environnement « %s » sort de son perimetre.', $environment),
        ];

        if ($detectees !== []) {
            $lignes[] = '';
            $lignes[] = 'Infrastructure de production reconnue :';
            foreach ($detectees as $quoi => $quelle) {
                $lignes[] = sprintf('  - %-16s %s', $quoi, $quelle);
            }
        }

        if ($violations !== []) {
            $lignes[] = '';
            $lignes[] = 'Regles non respectees :';
            foreach ($violations as $quoi => $attendu) {
                $lignes[] = sprintf('  - %-16s %s', $quoi, $attendu);
            }
        }

        return implode("\n", [
            ...$lignes,
            '',
            'Un environnement de developpement ne doit jamais viser la production : une commande',
            'destructrice — migrate:fresh, db:wipe, une purge RGPD, un nettoyage de fichiers',
            'orphelins — agirait sur les donnees reelles. C\'est ce qui est arrive le 22/08/2026.',
            '',
            'Que faire :',
            '  - en local, repointer .env sur la PREPRODUCTION (projet koimluuhjlhslwlxiesb,',
            '    bucket galleries-preprod, SUMUP_ENV=sandbox), puis :',
            '        docker compose up -d --force-recreate',
            '    compose fige les variables au demarrage du conteneur : « restart » ne les',
            '    rafraichit PAS.',
            '  - pour agir volontairement sur la production : APP_ENV=production, depuis le NAS.',
            '',
            'Cibles declarees dans App\\Support\\ProductionTargets.',
        ]);
    }
}
