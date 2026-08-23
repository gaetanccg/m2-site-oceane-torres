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

    /** Pooler de production. La preproduction est sur une autre region. */
    public const DATABASE_HOST = 'aws-1-eu-west-1.pooler.supabase.com';

    /** Bucket MinIO de production. Le serveur est PARTAGE : seul le bucket differe. */
    public const STORAGE_BUCKET = 'galleries';

    /**
     * Cibles de production reconnues dans la configuration fournie.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string> intitule => ce qui a ete reconnu
     */
    public static function detectedIn(array $config): array
    {
        $connexion = (string) ($config['database.default'] ?? 'pgsql');
        $hote = (string) ($config["database.connections.{$connexion}.host"] ?? '');
        $utilisateur = (string) ($config["database.connections.{$connexion}.username"] ?? '');
        $bucket = (string) ($config['filesystems.disks.minio.bucket'] ?? '');

        $detectees = [];

        if ($utilisateur !== '' && str_contains($utilisateur, self::SUPABASE_PROJECT)) {
            $detectees['base de donnees'] = 'projet Supabase '.self::SUPABASE_PROJECT;
        }

        if ($hote !== '' && str_contains($hote, self::DATABASE_HOST)) {
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
    public static function violatedPoliciesIn(array $config): array
    {
        $sumup = strtolower((string) ($config['sumup.environment'] ?? ''));
        $sentry = strtolower((string) ($config['sentry.environment'] ?? ''));

        $violations = [];

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
