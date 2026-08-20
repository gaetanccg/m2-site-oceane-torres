<?php

namespace App\Services\Supervision;

final class AlertCatalog
{
    /** @var array<string, array{label: string, action: string}> */
    private const MOTIFS = [
        'database_unreachable' => [
            'label' => 'Base de données injoignable',
            'action' => "Vérifier l'état de Supabase (status.supabase.com) puis la connexion depuis le NAS : "
                .'docker exec api-php php artisan db:show. Rien à redémarrer côté conteneurs, ils repartent '
                .'seuls dès que la base répond.',
        ],
        'database_slow' => [
            'label' => 'Base de données lente',
            'action' => 'La base met trop de temps à répondre à un SELECT 1, une fois la connexion ouverte : '
                .'le temps de trajet réseau est déjà exclu de la mesure. Regarder la charge et les requêtes '
                .'lentes dans le dashboard Supabase (Reports → Query performance).',
        ],
        'database_connect_slow' => [
            'label' => 'Ouverture de connexion à la base lente',
            'action' => "La base répond vite, c'est l'établissement de la connexion qui traîne : DNS, TLS et "
                .'poignée de main du pooler. PDO n\'est pas persistant, donc chaque requête HTTP paie ce coût. '
                .'Vérifier le port utilisé (DB_PORT) : 6543 est le pooler en mode transaction, connexions '
                .'légères ; 5432 est le mode session, qui ouvre un backend PostgreSQL dédié à chaque fois. '
                .'Ponctuel, on ignore ; répété, basculer sur 6543.',
        ],
        'storage_unreachable' => [
            'label' => 'Stockage MinIO injoignable',
            'action' => 'MinIO tourne dans un compose séparé sur le NAS : docker ps | grep minio, puis '
                .'docker exec api-php curl -s -o /dev/null -w "%{http_code}" http://host.docker.internal:9000/minio/health/live. '
                .'Impact : plus aucun affichage ni téléchargement de photo.',
        ],
        'storage_witness_missing' => [
            'label' => 'Objet témoin absent du bucket',
            'action' => "Le bucket répond mais l'objet de contrôle a disparu. Vérifier "
                .'SUPERVISION_STORAGE_WITNESS et le contenu du bucket (console MinIO).',
        ],
        'queue_failed_jobs' => [
            'label' => 'Jobs en échec',
            'action' => 'docker exec api-php php artisan queue:failed pour voir la cause, corriger, puis '
                .'php artisan queue:retry all. Vérifier en priorité les exports RGPD (délai réglementaire) '
                .'et les traitements de photos.',
        ],
        'queue_depth' => [
            'label' => "File d'attente engorgée",
            'action' => 'Normal après un import massif de photos. Sinon : docker logs --tail 100 api-queue '
                .'pour vérifier que le worker consomme bien.',
        ],
        'queue_stalled' => [
            'label' => 'Job en attente depuis trop longtemps',
            'action' => 'Le worker ne consomme plus : docker logs --tail 100 api-queue puis '
                .'docker compose -f deploy/docker-compose.prod.yml restart queue.',
        ],
        'queue_worker_stale' => [
            'label' => 'Worker de queue silencieux',
            'action' => 'Le process est peut-être vivant mais bloqué. docker logs --tail 100 api-queue, '
                .'puis redémarrer le service queue.',
        ],
        'queue_worker_missing' => [
            'label' => 'Aucun signe de vie du worker de queue',
            'action' => 'Le conteneur api-queue est absent ou ne démarre pas : docker ps -a | grep api-queue '
                .'puis docker logs api-queue. Aucun job (email, photo, export) ne part tant que ce point est ouvert.',
        ],
        'queue_unreadable' => [
            'label' => "État de la file d'attente illisible",
            'action' => 'Symptôme secondaire : la base ne répond pas. Traiter d\'abord l\'alerte base de données.',
        ],
        'scheduler_stale' => [
            'label' => 'Scheduler silencieux',
            'action' => 'docker logs --tail 100 api-scheduler. Penser aux verrous withoutOverlapping restés '
                .'coincés après un kill : php artisan cache:clear les libère. Tant que c\'est rouge, la '
                .'réconciliation des paiements SumUp et la purge RGPD ne tournent plus.',
        ],
        'scheduler_missing' => [
            'label' => 'Aucun signe de vie du scheduler',
            'action' => 'Le conteneur api-scheduler est absent : docker ps -a | grep api-scheduler puis '
                .'docker compose -f deploy/docker-compose.prod.yml up -d scheduler.',
        ],
    ];

    public static function label(string $reason): string
    {
        return self::MOTIFS[$reason]['label'] ?? $reason;
    }

    public static function action(string $reason): string
    {
        return self::MOTIFS[$reason]['action']
            ?? 'Consulter GET /api/health/details et les logs applicatifs (admin → Logs).';
    }

    /** @return list<string> */
    public static function known(): array
    {
        return array_keys(self::MOTIFS);
    }
}
