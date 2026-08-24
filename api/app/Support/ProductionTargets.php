<?php

namespace App\Support;

/**
 * Ce qui identifie la PRODUCTION, en un seul endroit.
 *
 * Ces valeurs ne sont pas des secrets, ce sont des marqueurs d'infrastructure — le meme
 * identifiant de projet Supabase figure deja dans les tests. Les rassembler ici permet de
 * repondre a une question que rien ne posait avant l'incident du 22 août 2026 : « suis-je
 * en train de viser la production ? »
 *
 * @see ProductionTargetGuard
 */
final class ProductionTargets
{
    /** Identifiant du projet Supabase de production, present dans l'utilisateur de connexion. */
    public const SUPABASE_PROJECT = 'ekhlybdoblhjigpnpbff';

    /**
     * Pooler de production. Conserve comme second signal, mais ce n'est PAS le marqueur
     * principal : Supabase expose aussi une connexion directe, `db.<ref>.supabase.co`, dont
     * l'utilisateur est simplement « postgres » — sans le ref. Comparer cet hote-la ne
     * detectait donc rien. Le marqueur fiable est le ref de projet, cherche PARTOUT dans la
     * connexion resolue : il apparait soit dans l'utilisateur (pooler), soit dans le nom
     * d'hote (connexion directe).
     */
    public const DATABASE_HOST = 'aws-1-eu-west-1.pooler.supabase.com';

    /** Bucket MinIO de production. Le serveur est PARTAGE : seul le bucket differe. */
    public const STORAGE_BUCKET = 'galleries';

    /** URLs de ping de supervision : aucune ne doit etre emise hors production. */
    private const HEALTHCHECK_KEYS = [
        'purge RGPD' => 'supervision.healthchecks.rgpd_cleanup_url',
        'reconciliation' => 'supervision.healthchecks.reconcile_orders_url',
    ];

    /**
     * Ce poste est-il une machine de developpement ?
     *
     * La production tourne sur un NAS Linux. Un macOS est donc forcement un poste de
     * developpement — signal automatique, impossible a deployer par erreur, et qui ne
     * demande aucune configuration. Le fichier marqueur couvre les autres cas (une machine
     * Linux de developpement) ; il est ignore par git, donc absent d'un deploiement.
     *
     * Sert a fermer le dernier contournement : `APP_ENV=production` faisait se retirer le
     * garde-fou, y compris sur cette machine. Mesure faite — Laravel tentait alors bien la
     * connexion a la production.
     */
    public static function isDevelopmentMachine(): bool
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            return true;
        }

        return is_file(dirname(__DIR__, 2).'/.poste-de-developpement');
    }

    /**
     * Cibles de production reconnues dans la configuration fournie.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string> intitule => ce qui a ete reconnu
     */
    public static function detectedIn(array $config): array
    {
        $nom = (string) ($config['database.default'] ?? 'pgsql');

        // L'empreinte est construite sur la connexion RESOLUE, DB_URL comprise. Lire
        // `host` et `username` directement laissait passer DB_URL, qui les rend inertes
        // sans les effacer : le garde voyait la preproduction, l'application se connectait
        // a la production.
        $empreinte = EffectiveDatabaseTarget::fingerprint([
            'url' => $config["database.connections.{$nom}.url"] ?? null,
            'host' => $config["database.connections.{$nom}.host"] ?? null,
            'username' => $config["database.connections.{$nom}.username"] ?? null,
            'database' => $config["database.connections.{$nom}.database"] ?? null,
            'port' => $config["database.connections.{$nom}.port"] ?? null,
        ]);

        $bucket = (string) ($config['filesystems.disks.minio.bucket'] ?? '');

        $detectees = [];

        if (str_contains($empreinte, strtolower(self::SUPABASE_PROJECT))) {
            $detectees['base de donnees'] = 'projet Supabase '.self::SUPABASE_PROJECT;
        }

        if (str_contains($empreinte, strtolower(self::DATABASE_HOST))) {
            $detectees['hote de base'] = self::DATABASE_HOST;
        }

        if ($bucket === self::STORAGE_BUCKET) {
            $detectees['stockage'] = 'bucket MinIO « '.self::STORAGE_BUCKET.' »';
        }

        return $detectees;
    }

    /**
     * Regles qu'un environnement de developpement doit respecter, verifiees en echouant
     * PAR DEFAUT : une valeur absente est une violation, pas une permission.
     *
     * C'est la difference qui compte. Chercher « SumUp est-il en live ? » laisse passer une
     * configuration muette — et `config('sumup.environment')` vaut « production » quand
     * SUMUP_ENV n'est pas defini. On exige donc l'inverse : dis « sandbox », ou arrete-toi.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string> intitule => ce qui est attendu
     */
    /** Identifiant de projet lisible dans un DSN, sans en divulguer la cle. */
    private static function sentryProject(string $dsn): string
    {
        $chemin = trim((string) parse_url($dsn, PHP_URL_PATH), '/');

        return $chemin === '' ? 'inconnu' : $chemin;
    }

    public static function violatedPoliciesIn(array $config): array
    {
        $sumup = strtolower((string) ($config['sumup.environment'] ?? ''));
        $sentry = strtolower((string) ($config['sentry.environment'] ?? ''));
        $dsn = trim((string) ($config['sentry.dsn'] ?? ''));

        $violations = [];

        // Un ping emis par un poste de developpement maintient le moniteur de production
        // au VERT : une panne reelle ne declencherait plus aucune alerte. C'est pire que
        // l'absence de supervision, puisqu'on croit etre couvert.
        foreach (self::HEALTHCHECK_KEYS as $intitule => $cle) {
            if (trim((string) ($config[$cle] ?? '')) !== '') {
                $violations['supervision ('.$intitule.')'] = $cle.' doit etre vide hors production';
            }
        }

        // Exiger un DSN VIDE, plutot que d'interdire celui de la production : je n'aurais
        // pas su reconnaitre le bon projet. Le poste avait l'etiquette « local » et
        // pourtant le DSN d'un projet deploye — l'etiquette seule ne protegeait rien.
        if ($dsn !== '') {
            $violations['supervision (DSN)'] = 'SENTRY_LARAVEL_DSN doit etre vide hors production'
                .' (projet actuellement vise : '.self::sentryProject($dsn).')';
        }

        if ($sumup !== 'sandbox') {
            $violations['paiements'] = sprintf(
                'SUMUP_ENV doit valoir « sandbox » hors production (valeur actuelle : %s)',
                $sumup === '' ? 'non definie' : '« '.$sumup.' »',
            );
        }

        if ($sentry === 'production') {
            $violations['supervision'] = 'SENTRY_ENVIRONMENT etiquette les erreurs comme « production »';
        }

        return $violations;
    }
}
