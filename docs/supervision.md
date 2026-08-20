# Supervision et alerte

Système de supervision de la plateforme : ce qui est surveillé, avec quelles
sondes, quels seuils, par quel canal on est prévenu, et quoi faire à la réception
d'une alerte.

État des lieux avant mise en place : [`audit-supervision.md`](audit-supervision.md).

Principe directeur : **du réel, gratuit, et rien à installer sur le NAS**. Tout
repose sur des briques déjà présentes (Laravel, scheduler, Brevo SMTP, Docker) ou
sur des plans gratuits de services hébergés (UptimeRobot, healthchecks.io,
Sentry). Aucun agent, aucun collecteur, aucune base de métriques.

---

## 1. Périmètre supervisé

| Composant | Où | Supervisé par | Réparable par nous ? |
|-----------|-----|---------------|----------------------|
| API Laravel (`api-php`) | NAS, Docker | healthcheck FastCGI + sonde externe HTTP | Oui |
| Reverse proxy (`api-nginx`) | NAS, Docker | healthcheck HTTP + sonde externe | Oui |
| Worker de queue (`api-queue`) | NAS, Docker | heartbeat + sonde `queue` | Oui |
| Scheduler (`api-scheduler`) | NAS, Docker | heartbeat + sonde `scheduler` + healthchecks.io | Oui |
| PostgreSQL | Supabase (managé) | sonde `database` | Non — subi, à constater |
| Stockage objet | MinIO, NAS (compose séparé) | sonde `storage` | Oui |
| Exposition publique | Cloudflare Tunnel | UptimeRobot (depuis l'extérieur) | Partiellement |
| Front public | Render (statique) | UptimeRobot + Sentry (erreurs JS) | Oui |

Hors périmètre, assumé : les métriques système du NAS (CPU, RAM, disque), déjà
couvertes par l'interface UGREEN, et la performance applicative détaillée (pas de
budget quota pour du tracing).

### 1.1 Cible de déploiement

Le dispositif est **mis en place d'abord sur la preprod**. Le code est identique
dans les deux environnements ; seuls changent les noms de conteneurs, le fichier
compose et les URL. Toutes les commandes de ce document utilisent la colonne
preprod : pour la production, retirer le suffixe `-preprod` et changer de
fichier compose.

| | Preprod (cible actuelle) | Production (plus tard) |
|---|---|---|
| API | `https://preprod-api.oceanetorresphotographie.fr` | `https://api.oceanetorresphotographie.fr` |
| Front | `https://preprod.oceanetorresphotographie.fr` | `https://oceanetorresphotographie.fr` |
| Port nginx (NAS) | `8081` | `8080` |
| Compose | `deploy/docker-compose.preprod.yml` | `deploy/docker-compose.prod.yml` |
| Environnement | `deploy/.env.preprod` | `deploy/.env.prod` |
| Script de déploiement | `deploy/deploy-preprod.sh` | `deploy/deploy.sh` |
| Conteneurs | `api-php-preprod`, `api-queue-preprod`, `api-scheduler-preprod`, `api-nginx-preprod` | `api-php`, `api-queue`, `api-scheduler`, `api-nginx` |
| Branche Render | `develop` | `main` |

Deux particularités de la preprod, traitées dans la configuration :

- `APP_ENV` y vaut `production` (c'est voulu : on teste au plus près du réel).
  Sans `SENTRY_ENVIRONMENT=preprod`, les erreurs de preprod arriveraient dans
  Sentry étiquetées « production » et seraient indistinguables des vraies.
- `LOG_LEVEL=debug` y produit beaucoup plus de volume qu'en production : la
  rotation quotidienne (`LOG_STACK=daily`, rétention 14 jours) y est donc encore
  plus nécessaire.

---

## 2. Architecture : trois niveaux de santé

La distinction est volontaire — un healthcheck de conteneur et une sonde externe
ne répondent pas à la même question.

| Endpoint | Question posée | Coût | Code HTTP | Public |
|----------|----------------|------|-----------|--------|
| `GET /api/health/live` (et `GET /api/`) | « ce conteneur sert-il des requêtes ? » | nul (aucune I/O) | toujours 200 | oui |
| `GET /api/health` | « le système est-il sain ? » | 1 requête SQL + 1 appel S3 | **200** si tout est vert, **503** sinon | oui, minimal |
| `GET /api/health/details`<br>`GET /api/admin/health` | « qu'est-ce qui va mal ? » | idem | 200 / 503 | non (jeton ou admin) |

**Pourquoi les healthchecks Docker ne visent pas `/api/health`** : cet endpoint
renvoie 503 dès qu'un composant est dégradé. Un healthcheck nginx branché dessus
marquerait nginx `unhealthy` parce que MinIO est tombé — un problème que nginx ne
peut pas réparer. C'est la distinction *liveness* (le conteneur fonctionne) /
*readiness* (le système est prêt) : les conteneurs sondent la liveness, les
sondes externes la readiness.

**Ce que le public voit** : `status`, `message`, `version`, `timestamp`. Jamais
quel composant est en cause — sinon `/api/health` deviendrait une carte de
l'infrastructure offerte à un attaquant. Le contrat historique de l'endpoint
(les quatre mêmes clés) est préservé : les vérifications de déploiement existantes
continuent de fonctionner.

---

## 3. Tableau des sondes

### 3.1 Sondes internes (exécutées par l'API, restituées par `/api/health`)

| Sonde | Ce qu'elle surveille | Comment | Seuil | Verdict | Canal d'alerte |
|-------|---------------------|---------|-------|---------|----------------|
| `database` | PostgreSQL Supabase réactif | `SELECT 1` chronométré, **connexion déjà ouverte** | > 1000 ms | `degraded` | email + UptimeRobot (503) |
| | coût d'ouverture d'une connexion | `getPdo()` chronométré à part (DNS + TLS + pooler) | > 2500 ms | `degraded` | idem |
| | | exception | — | `down` | idem |
| `storage` | MinIO joignable, credentials valides, bucket existant | HEAD sur l'objet témoin, sinon LIST d'un préfixe vide | exception | `down` | email + UptimeRobot |
| | objet témoin présent | `fileExists` | absent | `degraded` | email |
| `queue` | jobs en échec | `count(failed_jobs)` sur 24 h | > 0 | `degraded` | email |
| | engorgement de la file | `count(jobs)` non réservés | > 100 | `degraded` | email |
| | jobs qui n'avancent plus | âge du plus ancien job en attente | > 15 min | `degraded` | email |
| | worker vivant | fraîcheur du heartbeat `supervision:queue:heartbeat` | > 10 min | `degraded` | email |
| | | heartbeat absent | — | `down` | email |
| `scheduler` | tâches planifiées vivantes | fraîcheur de `supervision:scheduler:heartbeat` | > 120 min | `degraded` | UptimeRobot (503) |
| | | heartbeat absent | — | `down` | UptimeRobot |

Le statut global est **le pire** des quatre verdicts. Une seule sonde rouge fait
donc passer `/api/health` en 503.

Les seuils sont tous réglables par variable d'environnement (§7), et sont définis
**une seule fois** : la sonde produit des « motifs » machine (`queue_failed_jobs`,
`scheduler_stale`…) que le système d'alerte consomme tels quels. Ce que l'endpoint
affiche et ce qui déclenche un email ne peuvent donc pas diverger.

### 3.2 Healthchecks Docker (locaux, visibles dans `docker ps`)

Noms de conteneurs donnés sans suffixe ; en preprod ils se terminent par
`-preprod` (cf. §1.1).

| Service | Sonde | Intervalle | Ce que ça détecte |
|---------|-------|-----------|-------------------|
| `api-php` | aller-retour FastCGI sur `/ping` (`cgi-fcgi`), repli sur test de socket | 30 s | pool PHP-FPM figé ou saturé |
| `api-nginx` | `wget` sur `/api/health/live` + mot-clé `"status":"ok"` | 30 s | chaîne nginx → php-fpm → Laravel rompue |
| `api-queue` | `supervision:heartbeat:check queue --max-age=600` | 60 s | worker vivant mais bloqué |
| `api-scheduler` | `supervision:heartbeat:check scheduler --max-age=900` | 60 s | `schedule:run` qui ne tourne plus |

Les quatre services sont en `restart: unless-stopped`.

> ⚠️ **Limite connue, importante** : Docker (hors Swarm) **ne redémarre pas** un
> conteneur `unhealthy`. `restart:` ne réagit qu'à la sortie du processus. Un
> healthcheck sert donc à (1) rendre l'état visible dans `docker ps`, (2) ordonner
> le démarrage (`depends_on: service_healthy`), (3) alimenter la même information
> que celle remontée par `/api/health`. La remise en service reste manuelle
> (§6). Un conteneur type `autoheal` pourrait automatiser ça ; volontairement non
> ajouté pour ne pas installer d'agent supplémentaire sur le NAS.

Le seuil du healthcheck scheduler (15 min) est plus serré que celui du endpoint
HTTP (2 h), et c'est voulu : un conteneur `unhealthy` est un signal local qui ne
réveille personne, alors qu'un 503 déclenche une alerte externe et doit rester
conservateur pour ne pas crier au loup.

### 3.3 Sondes externes (hébergées ailleurs — §5)

| Sonde | Cible | Fréquence | Ce que ça détecte, et que rien d'autre ne détecte |
|-------|-------|-----------|---------------------------------------------------|
| UptimeRobot — API | `https://preprod-api.<domaine>/api/health`, mot-clé `ok` | 5 min | NAS éteint, tunnel Cloudflare coupé, box HS, **et** toute dégradation interne (503) |
| UptimeRobot — front | `https://preprod.<domaine>/` | 5 min | panne Render / DNS |
| healthchecks.io — purge RGPD | ping hebdomadaire | hebdo | la purge RGPD **ne s'exécute plus** |
| healthchecks.io — réconciliation | ping toutes les 10 min | 10 min | la réconciliation des paiements ne tourne plus |

---

## 4. Le point aveugle structurel, et comment il est fermé

Le système d'alerte interne (emails) est porté par le scheduler. **Si le scheduler
meurt, il ne peut pas prévenir de sa propre mort.** C'est le classique problème
de l'« homme mort », et c'est ce qui rend les sondes externes non négociables —
la compétence visée parle d'ailleurs de *disponibilité permanente* du dispositif
de signalement.

Trois filets indépendants le couvrent, chacun hébergé hors du NAS :

1. **UptimeRobot sur `/api/health`** : la sonde `scheduler` fait passer l'endpoint
   en 503 → alerte email par UptimeRobot, dont l'infrastructure n'a rien à voir
   avec le NAS.
2. **healthchecks.io** : attend un ping à la fin de chaque tâche critique. Pas de
   ping = alerte. C'est le seul mécanisme qui détecte *l'absence* d'exécution.
3. **Le rapport de santé quotidien de 8 h** : s'il n'arrive plus, quelque chose ne
   tourne plus. Filet le plus lâche des trois, mais gratuit.

Symétriquement, ce que les sondes externes ne verraient pas et que l'alerte
interne détecte : un job en échec, une file qui s'engorge, un worker figé —
autant de pannes invisibles de l'extérieur, où l'API répond parfaitement.

---

## 5. Configuration des services externes (actions manuelles)

### 5.1 UptimeRobot — plan gratuit (50 moniteurs, intervalle 5 min)

1. Créer un compte sur [uptimerobot.com](https://uptimerobot.com) avec l'adresse
   qui reçoit déjà les alertes (`MAIL_ADMIN_EMAIL`).
2. **Moniteur API preprod** — *Add New Monitor* :
   - Monitor Type : **Keyword**
   - Friendly Name : `API Océane Torres (preprod)`
   - URL : `https://preprod-api.oceanetorresphotographie.fr/api/health`
   - Keyword Type : **Exists**, Keyword : `"status":"ok"`
   - Monitoring Interval : **5 minutes**
   - Alert Contacts : l'email du compte
   - *Advanced* → laisser le suivi des redirections activé.

   Le mot-clé plutôt que le simple code HTTP : un 503 **et** un `"status":"degraded"`
   renvoyé en 200 par erreur déclenchent tous les deux l'alerte. Double sécurité.
3. **Moniteur front preprod** — *Add New Monitor* :
   - Monitor Type : **HTTP(s)**
   - Friendly Name : `Front Océane Torres (preprod)`
   - URL : `https://preprod.oceanetorresphotographie.fr/`
   - Monitoring Interval : **5 minutes**
4. Régler *My Settings* → **Alert when down for** : 2 vérifications consécutives,
   pour absorber un micro-incident réseau sans email inutile.
5. Facultatif : activer la *Public Status Page* — utile comme preuve d'exploitation
   dans un dossier de certification.

Le plan gratuit autorise 50 moniteurs : les deux moniteurs de production
(`api.` et le domaine racine) se créeront à l'identique le jour de la promotion,
sans toucher à ceux de preprod.

> Pendant un déploiement, `deploy-preprod.sh` fait un `down` puis un `up` :
> l'API est réellement indisponible ~1 à 3 min et UptimeRobot alertera. Mettre le
> moniteur en pause (bouton *Pause*) avant un déploiement planifié, ou accepter
> l'alerte comme trace du déploiement.

### 5.2 healthchecks.io — plan gratuit (20 checks)

Détecte le **silence** : c'est l'absence de ping qui alerte.

1. Créer un compte sur [healthchecks.io](https://healthchecks.io).
2. Créer un check **« Purge RGPD hebdomadaire (preprod) »** :
   - Schedule : **Cron**, expression `0 4 * * 0` (dimanche 04:00), fuseau
     `Europe/Paris`
   - Grace Time : **2 heures** (marge si la purge est lente)
   - Copier l'URL de ping (`https://hc-ping.com/<uuid>`)
3. Créer un check **« Réconciliation commandes (preprod) »** :
   - Schedule : **Cron**, `*/10 * * * *`, fuseau `Europe/Paris`
   - Grace Time : **20 minutes**
4. Renseigner les URL dans `deploy/.env.preprod` :
   ```dotenv
   HEALTHCHECKS_RGPD_URL=https://hc-ping.com/<uuid-purge-rgpd>
   HEALTHCHECKS_RECONCILE_URL=https://hc-ping.com/<uuid-reconciliation>
   ```
5. Redéployer, puis vérifier que le premier ping arrive :
   ```bash
   docker exec api-php-preprod php artisan schedule:run
   ```
   Le check passe au vert dans l'interface healthchecks.io.

Sans URL configurée, aucun ping n'est émis : le comportement par défaut n'ajoute
aucune dépendance réseau.

> Créer des checks **distincts** pour la production le jour de la promotion :
> un check partagé entre les deux environnements serait maintenu au vert par la
> preprod, masquant exactement la panne qu'il est censé détecter en production.

### 5.3 Sentry — plan gratuit (5 000 erreurs/mois)

1. Créer un compte sur [sentry.io](https://sentry.io), puis **un projet par
   couple (plateforme, environnement)** — quatre au total à terme :
   - plateforme **Laravel** → `api-preprod`, `api-prod`
   - plateforme **Vue** → `front-preprod`, `front-prod`

   > Le choix de projets séparés plutôt qu'un projet par plateforme filtré par
   > `environment` : quotas, règles d'alerte et historique de releases
   > indépendants, et surtout — voir l'étape 3 — le front **ne peut pas** être
   > séparé par `environment`.
2. **API** : copier le DSN du projet Laravel dans `deploy/.env.preprod` :
   ```dotenv
   SENTRY_DSN=https://<clé>@<org>.ingest.sentry.io/<projet>
   SENTRY_TRACES_SAMPLE_RATE=0
   SENTRY_ENVIRONMENT=preprod
   APP_VERSION=2.3.0-preprod
   ```
   Redéployer. Vérifier la remontée :
   ```bash
   docker exec api-php-preprod php artisan sentry:test
   ```

   > `SENTRY_ENVIRONMENT` n'est pas cosmétique : `APP_ENV` vaut `production` en
   > preprod, donc sans cette variable les erreurs des deux environnements
   > arriveraient sous la même étiquette. Avec un projet par environnement, la
   > variable devient une seconde barrière plutôt que la seule — la garder
   > explicite (`preprod` / `production`) reste utile pour lire l'étiquette sans
   > avoir à se souvenir de quel projet on regarde.

   > La variable réellement lue est `SENTRY_LARAVEL_DSN`, avec repli sur
   > `SENTRY_DSN` (`api/config/sentry.php`). Les deux fonctionnent ; les fichiers
   > d'environnement utilisent `SENTRY_LARAVEL_DSN`.

3. **Front** : dans le service Render **de la preprod** (branche `develop`),
   ajouter la variable d'environnement **de build** `VITE_SENTRY_DSN` avec le DSN
   du projet Vue, puis relancer un déploiement.

   > ⚠️ Point à ne pas manquer : Vite **inline les variables au moment du build**.
   > Sans `VITE_SENTRY_DSN` défini *pendant le build*, le SDK n'est pas seulement
   > inactif — il est totalement absent du bundle (l'import dynamique est
   > éliminé). Bon pour les performances, mais ça veut dire qu'ajouter le DSN
   > exige un **nouveau build**, pas un simple redémarrage.

   > ⚠️ **Le front ne peut pas être séparé par `environment`.**
   > `web/src/utils/monitoring.ts` passe `environment: import.meta.env.MODE`, et
   > le script de build (`vue-tsc -b && vite build`, sans `--mode`) laisse Vite
   > sur son défaut `production`. Les deux Static Sites Render se construisent
   > donc à l'identique : preprod et prod remonteraient **tous les deux** en
   > `environment: production`, indiscernables. D'où un **projet Sentry Vue par
   > environnement** (étape 1). L'alternative — passer un `--mode` distinct au
   > build — toucherait au code et aux deux services Render.

   > La `release` front vient de `__APP_VERSION__`, c'est-à-dire du champ
   > `version` de `web/package.json` — pas de `APP_VERSION`. Garder les deux
   > alignés, sinon un même déploiement remonte sous deux releases différentes
   > selon qu'on regarde le projet API ou le projet front.

4. Quota : `traces_sample_rate` à 0, métriques et logs désactivés. Seules les
   erreurs consomment le quota. Si le quota se remplit malgré tout, régler
   `sample_rate` (échantillonnage des erreurs) plutôt que de tout couper.

**Données personnelles** : `send_default_pii = false` des deux côtés (ni IP, ni
cookies, ni email), bindings SQL exclus des fils d'Ariane côté API, et côté front
les URL sont nettoyées de leur query string avant envoi — le site fait circuler
des jetons d'accès aux galeries et des URL signées MinIO en paramètres, ils ne
doivent pas sortir vers un tiers. Les fils d'Ariane `console` sont jetés.

---

## 6. Procédure de réaction aux alertes

Chaque email d'alerte contient déjà le geste à faire (le catalogue est dans
`App\Services\Supervision\AlertCatalog`, à maintenir cohérent avec ce tableau).

**Réflexe commun, avant tout** — obtenir le détail :

```bash
# Depuis l'extérieur, avec le jeton de supervision
curl -s -H "X-Health-Token: $HEALTH_CHECK_TOKEN" \
  https://preprod-api.oceanetorresphotographie.fr/api/health/details | jq

# Ou depuis le NAS
docker ps                    # colonne STATUS : (healthy) / (unhealthy)
docker exec api-php-preprod php artisan supervision:alert   # force une évaluation
```

Le tableau ci-dessous nomme les conteneurs de preprod. En production, retirer le
suffixe `-preprod` et remplacer `docker-compose.preprod.yml` par
`docker-compose.prod.yml` (cf. §1.1).

| Motif | Gravité | Diagnostic | Remise en service |
|-------|---------|-----------|-------------------|
| `database_unreachable` | 🔴 site HS | Vérifier [status.supabase.com](https://status.supabase.com), puis `docker exec api-php-preprod php artisan db:show` | Rien à redémarrer : les conteneurs repartent seuls dès que la base répond. Si Supabase est vert, vérifier le pooler (port `6543`) et les quotas de connexions. |
| `database_slow` | 🟠 dégradé | La base met > 1 s à répondre à un `SELECT 1` sur une connexion **déjà ouverte** : le réseau est hors de la mesure, la charge est bien côté base | Supabase → Reports → Query performance. Chercher les requêtes lentes et les connexions ouvertes. |
| `database_connect_slow` | 🟠 dégradé | La base répond vite mais l'**ouverture** de connexion traîne : DNS, TLS, poignée de main du pooler. PDO n'est pas persistant (`config/database.php`), donc **chaque requête HTTP paie ce coût** | Ponctuel : on ignore. Répété : vérifier `DB_PORT`. `6543` = pooler en mode transaction (connexions légères), `5432` = mode session (un backend PostgreSQL dédié à chaque ouverture). |
| `storage_unreachable` | 🔴 photos HS | `docker ps \| grep minio`, puis depuis l'API : `docker exec api-php-preprod curl -s -o /dev/null -w "%{http_code}" http://host.docker.internal:9000/minio/health/live` | Redémarrer le compose MinIO (séparé). Vérifier `extra_hosts: host.docker.internal`. |
| `storage_witness_missing` | 🟡 config | Le bucket répond mais l'objet témoin a disparu | Vérifier `SUPERVISION_STORAGE_WITNESS` et le contenu du bucket (console MinIO). |
| `queue_failed_jobs` | 🟠 fonctionnel | `docker exec api-php-preprod php artisan queue:failed` | Corriger la cause, puis `php artisan queue:retry all`. **Priorité aux exports RGPD** (délai réglementaire d'un mois) et aux traitements de photos. |
| `queue_depth` | 🟡 charge | Normal après un import massif de photos | Sinon `docker logs --tail 100 api-queue-preprod` : vérifier que le worker consomme. |
| `queue_stalled` | 🟠 blocage | Un job attend depuis > 15 min alors qu'un worker devrait le prendre | `docker compose -f deploy/docker-compose.preprod.yml restart queue` |
| `queue_worker_stale` | 🟠 blocage | Process vivant mais silencieux | `docker logs --tail 100 api-queue-preprod`, puis redémarrer le service `queue`. |
| `queue_worker_missing` | 🔴 aucun job traité | `docker ps -a \| grep api-queue-preprod` | `docker compose -f deploy/docker-compose.preprod.yml up -d queue`. Aucun email, photo ni export ne part tant que c'est rouge. |
| `scheduler_stale` / `scheduler_missing` | 🔴 conformité + paiements | `docker logs --tail 100 api-scheduler-preprod` | Redémarrer le service `scheduler`. Penser aux verrous `withoutOverlapping` restés coincés après un kill : `php artisan cache:clear` les libère. **Vérifier ensuite** que `reconcile-pending-orders` a rattrapé les commandes `pending` et que la purge RGPD hebdo a bien tourné. |
| `queue_unreadable` | — | Symptôme secondaire d'une base injoignable | Traiter d'abord `database_unreachable`. |
| Alerte UptimeRobot sans email interne | 🔴 | Le NAS, sa connexion ou le tunnel sont tombés → l'alerte interne n'a pas pu partir | Vérifier le NAS, puis `cloudflared` / le tunnel côté dashboard Cloudflare. |
| Alerte healthchecks.io | 🔴 conformité | Une tâche planifiée ne s'exécute plus | Même procédure que `scheduler_missing`. |
| Erreur Sentry en rafale après un déploiement | 🟠 régression | Comparer la `release` de l'erreur à la version déployée | Rollback (`git revert` + `deploy-preprod.sh`) puis corriger. |

**Anti-spam** : un même motif n'est pas renvoyé plus d'une fois par heure
(`SUPERVISION_ALERT_COOLDOWN_MINUTES`). Les motifs sont indépendants : une
nouvelle anomalie est notifiée immédiatement même si une autre est en fenêtre de
silence. Le verrou n'est posé qu'**après** un envoi réussi — un échec SMTP ne fait
pas perdre l'alerte, elle repart au passage suivant (15 min).

---

## 7. Indicateurs suivis et réglages

| Indicateur | Source | Variable de réglage | Défaut |
|------------|--------|--------------------|--------|
| Temps de réponse base (ms) | sonde `database` | `SUPERVISION_DATABASE_SLOW_MS` | 1000 |
| Temps d'ouverture de connexion (ms) | sonde `database` | `SUPERVISION_DATABASE_CONNECT_SLOW_MS` | 2500 |
| Temps de réponse stockage (ms) | sonde `storage` | — (indicatif) | — |
| Profondeur de file | sonde `queue` | `SUPERVISION_QUEUE_DEPTH` | 100 |
| Âge du plus ancien job en attente | sonde `queue` | `SUPERVISION_QUEUE_OLDEST_PENDING_MINUTES` | 15 |
| Jobs en échec sur 24 h | sonde `queue` | — (seuil fixe : > 0) | 0 |
| Fraîcheur du worker | heartbeat | `SUPERVISION_QUEUE_WORKER_STALE_MINUTES` | 10 |
| Fraîcheur du scheduler | heartbeat | `SUPERVISION_SCHEDULER_STALE_MINUTES` | 120 |
| Disponibilité externe (%) | UptimeRobot | — | — |
| Erreurs applicatives par release | Sentry | `SENTRY_LARAVEL_DSN` (repli `SENTRY_DSN`) | — |
| Version déployée | `/api/health` | `APP_VERSION` | `dev` |

Autres variables :

| Variable | Rôle |
|----------|------|
| `HEALTH_CHECK_TOKEN` | Jeton d'accès à `/api/health/details`. **Vide = endpoint fermé** (jamais ouvert par défaut). Générer : `php -r "echo bin2hex(random_bytes(32));"` |
| `SUPERVISION_ALERTS_ENABLED` | Coupe tous les envois (utile en preprod) |
| `SUPERVISION_ALERT_EMAIL` | Destinataire dédié ; à défaut `MAIL_ADMIN_EMAIL` |
| `SUPERVISION_ALERT_COOLDOWN_MINUTES` | Fenêtre de silence par motif |
| `SUPERVISION_STORAGE_WITNESS` | Objet témoin MinIO ; vide = listing d'un préfixe inexistant |
| `HEALTHCHECKS_RGPD_URL` / `HEALTHCHECKS_RECONCILE_URL` | URL de ping ; vides = aucun ping |

---

## 8. Exploitation au quotidien

```bash
# État détaillé des sondes
docker exec api-php-preprod php artisan supervision:alert   # évalue + alerte si besoin
curl -s -H "X-Health-Token: <jeton>" \
  https://preprod-api.oceanetorresphotographie.fr/api/health/details | jq

# Latence base : ouverture de connexion vs requête, 5 mesures à froid.
# À lancer quand une alerte database_slow / database_connect_slow arrive, pour
# trancher entre pic passager et coût chronique du chemin réseau.
docker exec api-php php artisan tinker --execute='
for ($i = 0; $i < 5; $i++) {
    DB::purge();
    $t = microtime(true); DB::connection()->getPdo(); $c = (microtime(true) - $t) * 1000;
    $t = microtime(true); DB::select("SELECT 1");      $q = (microtime(true) - $t) * 1000;
    printf("connexion=%7.1f ms   requete=%6.1f ms\n", $c, $q);
}'

# Historique des alertes déjà remontées (le contexte du log porte les mesures)
docker exec api-php sh -c 'grep -ho "\"\(response\|connect\)_time_ms\":[0-9.]*" storage/logs/laravel-*.log | tail -40'

# Heartbeats
docker exec api-php-preprod php artisan supervision:heartbeat:check queue
docker exec api-php-preprod php artisan supervision:heartbeat:check scheduler
docker exec api-php-preprod php artisan supervision:heartbeat scheduler   # forcer un signal

# Rapport de santé à la demande
docker exec api-php-preprod php artisan supervision:report

# Santé des conteneurs
docker ps --format "table {{.Names}}\t{{.Status}}"
docker inspect --format '{{json .State.Health}}' api-php-preprod | jq

# Logs
docker logs --tail 100 api-queue-preprod
docker logs --tail 100 api-scheduler-preprod
docker exec api-php-preprod tail -n 100 storage/logs/laravel.log
docker exec api-nginx-preprod tail -n 100 /var/log/nginx/error.log   # volume nginx-logs
```

Les logs applicatifs sont aussi consultables depuis l'admin (**Admin → Logs**),
avec filtres par niveau et recherche : l'alerte prévient, cette vue diagnostique.

**Clés de cache** (inspectables, documentées comme contrat d'exploitation) :

| Clé | Contenu |
|-----|---------|
| `supervision:scheduler:heartbeat` | timestamp Unix du dernier signe de vie du scheduler |
| `supervision:queue:heartbeat` | idem pour le worker de queue |
| `supervision:alert:<motif>` | verrou anti-spam d'un motif |

Ces clés vivent dans le cache applicatif. En preprod comme en production
(`CACHE_STORE=file`) elles sont dans `storage/framework/cache`, monté par bind
mount dans les conteneurs PHP, queue et scheduler : les trois partagent donc le
même cache, ce qui permet à l'API de lire les heartbeats écrits par les deux
autres. Les deux environnements ayant des dossiers de déploiement distincts sur
le NAS, leurs caches sont bien séparés.
**Si `CACHE_STORE` ou le montage de `./api` changent, ce mécanisme casse** —
basculer alors sur `CACHE_STORE=database` (la table `cache` existe déjà).

---

## 9. Vérification après déploiement

```bash
API=https://preprod-api.oceanetorresphotographie.fr

# 1. Les quatre conteneurs sont sains
docker ps --format "table {{.Names}}\t{{.Status}}"   # attendu : 4 × (healthy)

# 2. Le statut global est vert et la version est la bonne
curl -s $API/api/health | jq
# → {"status":"ok","message":"...","version":"2.3.0-preprod","timestamp":"..."}

# 3. Le détail est protégé
curl -s -o /dev/null -w "%{http_code}\n" $API/api/health/details   # → 403

# 4. Le schéma n'est plus exposé
curl -s -o /dev/null -w "%{http_code}\n" $API/api/health/tables    # → 404

# 5. Les heartbeats arrivent (attendre 5 min après le démarrage)
docker exec api-php-preprod php artisan supervision:heartbeat:check scheduler
docker exec api-php-preprod php artisan supervision:heartbeat:check queue
```

---

## 10. Choix d'implémentation et limites assumées

**`queue:monitor` non utilisé.** La commande Laravel se contente d'émettre un
événement `QueueBusy` sur dépassement de seuil : il aurait fallu y greffer un
listener, un email et un anti-spam parallèles à ceux déjà en place. La sonde
`queue` couvre la profondeur *et* trois autres indicateurs, avec un seul chemin
d'alerte et un seul mécanisme de verrou.

**Heartbeat du worker sur l'événement `Looping`, pas `JobProcessed`.** `Looping`
est émis à chaque tour de boucle, file vide comprise. Avec `JobProcessed`, un site
à quelques jobs par jour aurait signalé une fausse panne dès que la file est
calme. L'écriture est limitée à une fois par minute pour ne pas marteler le cache.

**Heartbeat du scheduler via `Schedule::command` (process forké) et non
`Schedule::call`.** Le heartbeat prouve ainsi que `schedule:run` tourne *et* que
le fork de tâches fonctionne — mécanisme dont dépendent `privacy:purge-expired`
et la réconciliation des paiements. Un `Schedule::call` aurait continué à écrire
un heartbeat vert alors que les tâches forkées échouaient.

**La sonde `database` chronomètre l'ouverture de connexion à part du `SELECT 1`.**
Les deux coûts n'ont ni la même cause ni le même remède, et les mélanger produit
des alertes trompeuses. `PDO::ATTR_PERSISTENT` est à `false` et `supervision:alert`
tourne dans un process forké : la connexion y est donc toujours neuve, et un
premier `SELECT` chronométré seul aurait facturé au compte de la base le DNS, la
poignée de main TLS et celle du pooler. C'est ce qui s'est produit le 20/08/2026
sur la prod — « la base répond en 1490 ms » alors qu'elle répondait en quelques
millisecondes, l'action recommandée envoyant chercher des requêtes lentes qui
n'existaient pas. Corollaire utile : comme rien n'est persistant, `connect_time_ms`
est un coût que **chaque requête HTTP** paie aussi, ce qui en fait un indicateur
de performance à part entière et pas seulement un artefact de mesure.

**Pas de cache du résultat des sondes.** `/api/health` exécute réellement les
sondes à chaque appel (1 requête SQL + 1 appel S3, ~20 à 50 ms) : un état de
santé mis en cache peut mentir au pire moment. L'abus est borné par un rate limit
de 60 req/min/IP.

**Le SDK Sentry front pèse ~150 ko gzip.** Il est chargé en import dynamique
après le montage de l'application, uniquement si un DSN est configuré au build :
zéro impact sur le rendu initial et sur les pages prérendues.

**La remontée d'erreurs front n'est pas exhaustive, et ne peut pas l'être.** Les
domaines d'ingestion de Sentry (`*.ingest.sentry.io`) figurent dans les listes
anti-traceurs courantes (EasyPrivacy, utilisée par uBlock Origin, AdGuard,
Ghostery, les Shields de Brave). Chez ces visiteurs, l'erreur est bien capturée
par le SDK mais l'envoi est bloqué par le navigateur — visible en `(blocked:other)`
dans l'onglet Réseau, à ne pas confondre avec `blocked:csp`. Selon les publics,
20 à 40 % des visiteurs sont concernés.

Conséquence à garder en tête : **le monitoring front voit une majorité des
erreurs, pas leur totalité**, là où la capture côté API est exhaustive. Une
absence d'erreur JS ne prouve donc rien à elle seule.

La parade existe — l'option `tunnel` du SDK fait transiter les événements par
notre propre domaine au lieu de `sentry.io`, ce que les bloqueurs laissent
passer. Elle demande un endpoint côté API qui relaie l'enveloppe, avec
validation du DSN pour ne pas ouvrir un proxy anonyme. Volontairement non
implémentée : elle ajoute une surface à sécuriser pour un gain partiel, et le
volume d'erreurs déjà remonté suffit à détecter une régression. À reconsidérer
si une régression front passe un jour inaperçue.

Pour tester la remontée front sans être trompé par son propre navigateur :
fenêtre de navigation privée, où les extensions sont désactivées par défaut.

**Ce que ce dispositif ne couvre pas** : la saturation disque du NAS, le
dépassement de quota Supabase (visible seulement dans leur console),
l'expiration du certificat Cloudflare (géré par eux), et la performance perçue
côté client. Autant de sujets à traiter séparément si le besoin apparaît.

---

## 11. À faire — récapitulatif des actions manuelles

Cible : **la preprod**. Le code est en place et testé ; **rien de ce qui suit
n'est fait par le déploiement**. Tant que ces points sont ouverts, le dispositif
tourne en mode dégradé (alertes email fonctionnelles, mais aucune sonde externe
et aucune remontée d'erreurs).

### 11.1 Sur le NAS — `deploy/.env.preprod`

Ce fichier n'est pas versionné. Le bloc de supervision y est déjà présent, avec
les seuils préremplis : seules les lignes marquées `# À REMPLIR` attendent une
valeur.

| # | Variable | Valeur | Pourquoi |
|---|----------|--------|----------|
| 1 | `HEALTH_CHECK_TOKEN` | `php -r "echo bin2hex(random_bytes(32));"` | Sans elle, `/api/health/details` reste fermé (403) |
| 2 | `SENTRY_DSN` | DSN du projet Laravel | Sans elle, le SDK reste inerte |
| 3 | `HEALTHCHECKS_RGPD_URL`<br>`HEALTHCHECKS_RECONCILE_URL` | URL de ping healthchecks.io | Sans elles, aucun ping n'est émis |

Déjà positionnées, à vérifier seulement : `APP_VERSION=2.3.0-preprod`,
`SENTRY_ENVIRONMENT=preprod`, `SUPERVISION_ALERTS_ENABLED=true`,
`LOG_STACK=daily` + `LOG_DAILY_DAYS=14`, `QUEUE_CONNECTION=database`.

### 11.2 Redéploiement

| # | Action | Pourquoi |
|---|--------|----------|
| 4 | Déployer **avec build d'image** (`./deploy/deploy-preprod.sh`, sans `--no-build`) | Le paquet `fcgi` a été ajouté au Dockerfile : sans reconstruction, le healthcheck `api-php-preprod` retombe sur le test de socket (moins précis, mais non bloquant) |
| 5 | Vérifier les 4 conteneurs `(healthy)` et le contrat de `/api/health` | Procédure complète au §9 |

### 11.3 Comptes et services externes

| # | Service | Action | Détail |
|---|---------|--------|--------|
| 6 | UptimeRobot | Créer le compte + 2 moniteurs preprod (API mot-clé `"status":"ok"`, front), intervalle 5 min | §5.1 |
| 7 | healthchecks.io | Créer le compte + 2 checks preprod, coller les URL dans `.env.preprod` | §5.2 |
| 8 | Sentry | Créer le compte + **2 projets** (Laravel pour l'API, Vue pour le front), récupérer les 2 DSN | §5.3 |
| 9 | Sentry API | Vérifier la remontée : `docker exec api-php-preprod php artisan sentry:test` | §5.3 |

### 11.4 Front preprod (Render, branche `develop`)

| # | Action | Pourquoi |
|---|--------|----------|
| 10 | Ajouter `VITE_SENTRY_DSN` dans les variables du Static Site **de preprod** | Le DSN du projet Vue |
| 11 | **Relancer un build** (pas un simple redémarrage) | Vite inline les variables au build : sans nouveau build, le SDK est purement et simplement absent du bundle |

### 11.5 Dossier de certification (C4.1.2)

Captures d'écran à faire une fois les points ci-dessus réalisés. La preprod est
un terrain de démonstration légitime, et même préférable : on peut y provoquer de
vraies pannes sans impact client.

| # | Capture | Ce qu'elle prouve |
|---|---------|-------------------|
| 12 | `docker ps` avec les 4 conteneurs `(healthy)` | Sondes locales opérationnelles |
| 13 | Réponse de `/api/health` en 200, puis d'un `/api/health/details` complet | Indicateurs et sondes internes |
| 14 | Tableau de bord UptimeRobot (2 moniteurs verts + % de disponibilité) | Sonde externe, disponibilité permanente |
| 15 | Page healthchecks.io (checks au vert avec date du dernier ping) | Détection du silence sur les tâches critiques |
| 16 | Un email d'alerte reçu (le provoquer : arrêter `api-queue-preprod`) | Modalité de signalement, de bout en bout |
| 17 | Un email de rapport quotidien | Signalement périodique / homme mort |
| 18 | Sentry : une erreur avec sa release et son `environment: preprod` | Remontée d'erreurs applicatives |
| 18b | Sentry : une erreur JS du front (à déclencher en navigation privée, cf. §10) | Couverture front, distincte de l'API |
| 19 | `/api/health` en **503** pendant la panne provoquée | Le passage ok → degraded → alerte, la chaîne complète |

Scénario de démonstration, sans rien casser :

```bash
# 1. État initial : tout est vert
curl -s https://preprod-api.oceanetorresphotographie.fr/api/health | jq

# 2. Provoquer la panne : on arrête le worker de queue
docker compose -f deploy/docker-compose.preprod.yml stop queue

# 3. Après ~10 min, la sonde `queue` passe au rouge et /api/health répond 503
curl -s -o /dev/null -w "%{http_code}\n" https://preprod-api.oceanetorresphotographie.fr/api/health

# 4. Au prochain passage de supervision:alert (≤ 15 min), l'email part.
#    Pour ne pas attendre :
docker exec api-php-preprod php artisan supervision:alert

# 5. Remise en service
docker compose -f deploy/docker-compose.preprod.yml start queue
```

### 11.6 Plus tard — promotion en production

Une fois la preprod validée, la mise en production ne demande aucun changement de
code : c'est le même dépôt, la même image.

| # | Action | Détail |
|---|--------|--------|
| 20 | Renseigner le bloc supervision de `deploy/.env.prod` | Le bloc y est déjà, avec les mêmes lignes `# À REMPLIR`. Générer un **jeton distinct** de celui de preprod. |
| 21 | Corriger `LOG_STACK` dans `deploy/.env.prod` | Passer de `single` à `daily` + `LOG_DAILY_DAYS=30`. Sans rotation le fichier grossit sans fin, et la visionneuse admin attend des fichiers datés (`laravel-AAAA-MM-JJ.log`) |
| 22 | Renseigner `SENTRY_LARAVEL_DSN` (projet API **prod**) et `SENTRY_ENVIRONMENT=production` | Un projet par environnement (§5.3). Laissée vide, `SENTRY_ENVIRONMENT` retomberait sur `APP_ENV`, qui vaut `production` des deux côtés |
| 22b | Vérifier que `APP_VERSION` n'est **pas** resté à la valeur de preprod | Il sort en clair dans `/api/health` et sert de `release` Sentry : une valeur `…-preprod` en production casse le triage du §6 |
| 23 | Créer 2 moniteurs UptimeRobot et 2 checks healthchecks.io **distincts** | Un check partagé serait maintenu au vert par la preprod, masquant la panne de production. Vérifier aussi que `HEALTH_CHECK_TOKEN` n'est pas celui de preprod |
| 24 | Créer un **projet Sentry Vue dédié à la production**, mettre son DSN dans `VITE_SENTRY_DSN` du Static Site de prod (branche `main`), puis **rebuild** | Réutiliser le DSN front de la preprod mélangerait les deux : le front remonte `environment: production` dans les deux environnements (§5.3, étape 3) |
| 25 | Déployer avec build (`./deploy/deploy.sh`) et rejouer le §9 | Sur les URL de production |
