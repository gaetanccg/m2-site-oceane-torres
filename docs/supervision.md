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
| `database` | PostgreSQL Supabase joignable et réactif | `SELECT 1` + chronomètre | > 1000 ms | `degraded` | email + UptimeRobot (503) |
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
| UptimeRobot — API | `https://api.<domaine>/api/health`, mot-clé `ok` | 5 min | NAS éteint, tunnel Cloudflare coupé, box HS, **et** toute dégradation interne (503) |
| UptimeRobot — site | `https://<domaine>/` | 5 min | panne Render / DNS |
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
2. **Moniteur API** — *Add New Monitor* :
   - Monitor Type : **Keyword**
   - Friendly Name : `API Océane Torres`
   - URL : `https://api.<domaine>/api/health`
   - Keyword Type : **Exists**, Keyword : `"status":"ok"`
   - Monitoring Interval : **5 minutes**
   - Alert Contacts : l'email du compte
   - *Advanced* → laisser le suivi des redirections activé.

   Le mot-clé plutôt que le simple code HTTP : un 503 **et** un `"status":"degraded"`
   renvoyé en 200 par erreur déclenchent tous les deux l'alerte. Double sécurité.
3. **Moniteur site public** — *Add New Monitor* :
   - Monitor Type : **HTTP(s)**
   - Friendly Name : `Site oceanetorresphotographie.fr`
   - URL : `https://<domaine>/`
   - Monitoring Interval : **5 minutes**
4. Régler *My Settings* → **Alert when down for** : 2 vérifications consécutives,
   pour absorber un micro-incident réseau sans email inutile.
5. Facultatif : activer la *Public Status Page* — utile comme preuve d'exploitation
   dans un dossier de certification.

> Pendant un déploiement, `deploy.sh` fait un `down` puis un `up` : l'API est
> réellement indisponible ~1 à 3 min et UptimeRobot alertera. Mettre le moniteur
> en pause (bouton *Pause*) avant un déploiement planifié, ou accepter l'alerte
> comme trace du déploiement.

### 5.2 healthchecks.io — plan gratuit (20 checks)

Détecte le **silence** : c'est l'absence de ping qui alerte.

1. Créer un compte sur [healthchecks.io](https://healthchecks.io).
2. Créer un check **« Purge RGPD hebdomadaire »** :
   - Schedule : **Cron**, expression `0 4 * * 0` (dimanche 04:00), fuseau
     `Europe/Paris`
   - Grace Time : **2 heures** (marge si la purge est lente)
   - Copier l'URL de ping (`https://hc-ping.com/<uuid>`)
3. Créer un check **« Réconciliation commandes »** :
   - Schedule : **Cron**, `*/10 * * * *`, fuseau `Europe/Paris`
   - Grace Time : **20 minutes**
4. Renseigner les URL dans `deploy/.env.prod` :
   ```dotenv
   HEALTHCHECKS_RGPD_URL=https://hc-ping.com/<uuid-purge-rgpd>
   HEALTHCHECKS_RECONCILE_URL=https://hc-ping.com/<uuid-reconciliation>
   ```
5. Redéployer, puis vérifier que le premier ping arrive :
   ```bash
   docker exec api-php php artisan schedule:run
   ```
   Le check passe au vert dans l'interface healthchecks.io.

Sans URL configurée, aucun ping n'est émis : le comportement par défaut n'ajoute
aucune dépendance réseau.

### 5.3 Sentry — plan gratuit (5 000 erreurs/mois)

1. Créer un compte sur [sentry.io](https://sentry.io), puis **deux projets** :
   - plateforme **Laravel** → pour l'API
   - plateforme **Vue** → pour le front
2. **API** : copier le DSN du projet Laravel dans `deploy/.env.prod` :
   ```dotenv
   SENTRY_DSN=https://<clé>@<org>.ingest.sentry.io/<projet>
   SENTRY_TRACES_SAMPLE_RATE=0
   APP_VERSION=2.3.0
   ```
   Redéployer. Vérifier la remontée :
   ```bash
   docker exec api-php php artisan sentry:test
   ```
3. **Front** : dans le service Render du site, ajouter la variable
   d'environnement **de build** `VITE_SENTRY_DSN` avec le DSN du projet Vue, puis
   relancer un déploiement.

   > ⚠️ Point à ne pas manquer : Vite **inline les variables au moment du build**.
   > Sans `VITE_SENTRY_DSN` défini *pendant le build*, le SDK n'est pas seulement
   > inactif — il est totalement absent du bundle (l'import dynamique est
   > éliminé). Bon pour les performances, mais ça veut dire qu'ajouter le DSN
   > exige un **nouveau build**, pas un simple redémarrage.

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
  https://api.<domaine>/api/health/details | jq

# Ou depuis le NAS
docker ps                    # colonne STATUS : (healthy) / (unhealthy)
docker exec api-php php artisan supervision:alert   # force une évaluation
```

| Motif | Gravité | Diagnostic | Remise en service |
|-------|---------|-----------|-------------------|
| `database_unreachable` | 🔴 site HS | Vérifier [status.supabase.com](https://status.supabase.com), puis `docker exec api-php php artisan db:show` | Rien à redémarrer : les conteneurs repartent seuls dès que la base répond. Si Supabase est vert, vérifier le pooler (port `6543`) et les quotas de connexions. |
| `database_slow` | 🟠 dégradé | Charge du pooler, requêtes lentes côté Supabase | Souvent transitoire. Si ça persiste : regarder les connexions ouvertes, envisager `pgbouncer` en mode transaction. |
| `storage_unreachable` | 🔴 photos HS | `docker ps \| grep minio`, puis depuis l'API : `docker exec api-php curl -s -o /dev/null -w "%{http_code}" http://host.docker.internal:9000/minio/health/live` | Redémarrer le compose MinIO (séparé). Vérifier `extra_hosts: host.docker.internal`. |
| `storage_witness_missing` | 🟡 config | Le bucket répond mais l'objet témoin a disparu | Vérifier `SUPERVISION_STORAGE_WITNESS` et le contenu du bucket (console MinIO). |
| `queue_failed_jobs` | 🟠 fonctionnel | `docker exec api-php php artisan queue:failed` | Corriger la cause, puis `php artisan queue:retry all`. **Priorité aux exports RGPD** (délai réglementaire d'un mois) et aux traitements de photos. |
| `queue_depth` | 🟡 charge | Normal après un import massif de photos | Sinon `docker logs --tail 100 api-queue` : vérifier que le worker consomme. |
| `queue_stalled` | 🟠 blocage | Un job attend depuis > 15 min alors qu'un worker devrait le prendre | `docker compose -f deploy/docker-compose.prod.yml restart queue` |
| `queue_worker_stale` | 🟠 blocage | Process vivant mais silencieux | `docker logs --tail 100 api-queue`, puis redémarrer le service `queue`. |
| `queue_worker_missing` | 🔴 aucun job traité | `docker ps -a \| grep api-queue` | `docker compose -f deploy/docker-compose.prod.yml up -d queue`. Aucun email, photo ni export ne part tant que c'est rouge. |
| `scheduler_stale` / `scheduler_missing` | 🔴 conformité + paiements | `docker logs --tail 100 api-scheduler` | Redémarrer le service `scheduler`. Penser aux verrous `withoutOverlapping` restés coincés après un kill : `php artisan cache:clear` les libère. **Vérifier ensuite** que `reconcile-pending-orders` a rattrapé les commandes `pending` et que la purge RGPD hebdo a bien tourné. |
| `queue_unreadable` | — | Symptôme secondaire d'une base injoignable | Traiter d'abord `database_unreachable`. |
| Alerte UptimeRobot sans email interne | 🔴 | Le NAS, sa connexion ou le tunnel sont tombés → l'alerte interne n'a pas pu partir | Vérifier le NAS, puis `cloudflared` / le tunnel côté dashboard Cloudflare. |
| Alerte healthchecks.io | 🔴 conformité | Une tâche planifiée ne s'exécute plus | Même procédure que `scheduler_missing`. |
| Erreur Sentry en rafale après un déploiement | 🟠 régression | Comparer la `release` de l'erreur à la version déployée | Rollback (`git revert` + `deploy.sh`) puis corriger. |

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
| Temps de réponse stockage (ms) | sonde `storage` | — (indicatif) | — |
| Profondeur de file | sonde `queue` | `SUPERVISION_QUEUE_DEPTH` | 100 |
| Âge du plus ancien job en attente | sonde `queue` | `SUPERVISION_QUEUE_OLDEST_PENDING_MINUTES` | 15 |
| Jobs en échec sur 24 h | sonde `queue` | — (seuil fixe : > 0) | 0 |
| Fraîcheur du worker | heartbeat | `SUPERVISION_QUEUE_WORKER_STALE_MINUTES` | 10 |
| Fraîcheur du scheduler | heartbeat | `SUPERVISION_SCHEDULER_STALE_MINUTES` | 120 |
| Disponibilité externe (%) | UptimeRobot | — | — |
| Erreurs applicatives par release | Sentry | `SENTRY_DSN` | — |
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
docker exec api-php php artisan supervision:alert          # évalue + alerte si besoin
curl -s -H "X-Health-Token: <jeton>" https://api.<domaine>/api/health/details | jq

# Heartbeats
docker exec api-php php artisan supervision:heartbeat:check queue
docker exec api-php php artisan supervision:heartbeat:check scheduler
docker exec api-php php artisan supervision:heartbeat scheduler   # forcer un signal

# Rapport de santé à la demande
docker exec api-php php artisan supervision:report

# Santé des conteneurs
docker ps --format "table {{.Names}}\t{{.Status}}"
docker inspect --format '{{json .State.Health}}' api-php | jq

# Logs
docker logs --tail 100 api-queue
docker logs --tail 100 api-scheduler
docker exec api-php tail -n 100 storage/logs/laravel.log
docker exec api-nginx tail -n 100 /var/log/nginx/error.log   # persisté (volume nginx-logs)
```

Les logs applicatifs sont aussi consultables depuis l'admin (**Admin → Logs**),
avec filtres par niveau et recherche : l'alerte prévient, cette vue diagnostique.

**Clés de cache** (inspectables, documentées comme contrat d'exploitation) :

| Clé | Contenu |
|-----|---------|
| `supervision:scheduler:heartbeat` | timestamp Unix du dernier signe de vie du scheduler |
| `supervision:queue:heartbeat` | idem pour le worker de queue |
| `supervision:alert:<motif>` | verrou anti-spam d'un motif |

Ces clés vivent dans le cache applicatif. En production (`CACHE_STORE=file`)
elles sont dans `storage/framework/cache`, monté par bind mount dans `api-php`,
`api-queue` et `api-scheduler` : les trois conteneurs partagent donc le même
cache, ce qui permet à l'API de lire les heartbeats écrits par les deux autres.
**Si `CACHE_STORE` ou le montage de `./api` changent, ce mécanisme casse** —
basculer alors sur `CACHE_STORE=database` (la table `cache` existe déjà).

---

## 9. Vérification après déploiement

```bash
# 1. Les quatre conteneurs sont sains
docker ps --format "table {{.Names}}\t{{.Status}}"   # attendu : 4 × (healthy)

# 2. Le statut global est vert et la version est la bonne
curl -s https://api.<domaine>/api/health | jq
# → {"status":"ok","message":"...","version":"2.3.0","timestamp":"..."}

# 3. Le détail est protégé
curl -s -o /dev/null -w "%{http_code}\n" https://api.<domaine>/api/health/details   # → 403

# 4. Le schéma n'est plus exposé
curl -s -o /dev/null -w "%{http_code}\n" https://api.<domaine>/api/health/tables    # → 404

# 5. Les heartbeats arrivent (attendre 5 min après le démarrage)
docker exec api-php php artisan supervision:heartbeat:check scheduler
docker exec api-php php artisan supervision:heartbeat:check queue
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

**Pas de cache du résultat des sondes.** `/api/health` exécute réellement les
sondes à chaque appel (1 requête SQL + 1 appel S3, ~20 à 50 ms) : un état de
santé mis en cache peut mentir au pire moment. L'abus est borné par un rate limit
de 60 req/min/IP.

**Le SDK Sentry front pèse ~150 ko gzip.** Il est chargé en import dynamique
après le montage de l'application, uniquement si un DSN est configuré au build :
zéro impact sur le rendu initial et sur les pages prérendues.

**Ce que ce dispositif ne couvre pas** : la saturation disque du NAS, le
dépassement de quota Supabase (visible seulement dans leur console),
l'expiration du certificat Cloudflare (géré par eux), et la performance perçue
côté client. Autant de sujets à traiter séparément si le besoin apparaît.

---

## 11. À faire — récapitulatif des actions manuelles

Le code est en place et testé ; **rien de ce qui suit n'est fait par le
déploiement**. Tant que ces points sont ouverts, le dispositif tourne en mode
dégradé (alertes email fonctionnelles, mais aucune sonde externe et aucune
remontée d'erreurs).

### 11.1 Sur le NAS — `deploy/.env.prod`

Ce fichier n'est pas versionné : les variables suivantes doivent y être ajoutées
à la main (elles sont documentées dans `deploy/.env.prod.example`).

| # | Variable | Valeur | Pourquoi |
|---|----------|--------|----------|
| 1 | `APP_VERSION` | tag git déployé, ex. `2.3.0` | Sans elle, `/api/health` annonce `dev` et les erreurs Sentry ne sont rattachées à aucune release |
| 2 | `HEALTH_CHECK_TOKEN` | `php -r "echo bin2hex(random_bytes(32));"` | Sans elle, `/api/health/details` reste fermé (403) |
| 3 | `LOG_STACK` | `daily` (au lieu de `single`) | En `single`, `laravel.log` grossit sans limite jusqu'à saturer le NAS (audit §2) |
| 4 | `LOG_DAILY_DAYS` | `30` | Rétention bornée |
| 5 | `QUEUE_CONNECTION` | vérifier qu'il vaut bien `database` | En `sync`, le conteneur `api-queue` tourne à vide (audit §3) |
| 6 | `SENTRY_DSN` | DSN du projet Laravel | Sans elle, le SDK reste inerte |
| 7 | `HEALTHCHECKS_RGPD_URL`<br>`HEALTHCHECKS_RECONCILE_URL` | URL de ping healthchecks.io | Sans elles, aucun ping n'est émis |

### 11.2 Redéploiement

| # | Action | Pourquoi |
|---|--------|----------|
| 8 | Déployer **avec build d'image** (`./deploy/deploy.sh`, sans `--no-build`) | Le paquet `fcgi` a été ajouté au Dockerfile : sans reconstruction, le healthcheck `api-php` retombe sur le test de socket (moins précis, mais non bloquant) |
| 9 | Vérifier les 4 conteneurs `(healthy)` et le contrat de `/api/health` | Procédure complète au §9 |

### 11.3 Comptes et services externes

| # | Service | Action | Détail |
|---|---------|--------|--------|
| 10 | UptimeRobot | Créer le compte + 2 moniteurs (API mot-clé `"status":"ok"`, site public), intervalle 5 min | §5.1 |
| 11 | healthchecks.io | Créer le compte + 2 checks (purge RGPD hebdo, réconciliation 10 min), coller les URL dans `.env.prod` | §5.2 |
| 12 | Sentry | Créer le compte + **2 projets** (Laravel pour l'API, Vue pour le front), récupérer les 2 DSN | §5.3 |
| 13 | Sentry API | Vérifier la remontée : `docker exec api-php php artisan sentry:test` | §5.3 |

### 11.4 Front (Render)

| # | Action | Pourquoi |
|---|--------|----------|
| 14 | Ajouter `VITE_SENTRY_DSN` dans les variables d'environnement du Static Site | Le DSN du projet Vue |
| 15 | **Relancer un build** (pas un simple redémarrage) | Vite inline les variables au build : sans nouveau build, le SDK est purement et simplement absent du bundle |

### 11.5 Dossier de certification (C4.1.2)

Captures d'écran à faire une fois les points ci-dessus réalisés :

| # | Capture | Ce qu'elle prouve |
|---|---------|-------------------|
| 16 | `docker ps` avec les 4 conteneurs `(healthy)` | Sondes locales opérationnelles |
| 17 | Réponse de `/api/health` en 200, puis d'un `/api/health/details` complet | Indicateurs et sondes internes |
| 18 | Tableau de bord UptimeRobot (2 moniteurs verts + % de disponibilité) | Sonde externe, disponibilité permanente |
| 19 | Page healthchecks.io (checks au vert avec date du dernier ping) | Détection du silence sur les tâches critiques |
| 20 | Un email d'alerte reçu (le provoquer : arrêter `api-queue`, attendre 15 min) | Modalité de signalement, de bout en bout |
| 21 | Un email de rapport quotidien | Signalement périodique / homme mort |
| 22 | Sentry : une erreur avec sa release | Remontée d'erreurs applicatives |

Pour provoquer une alerte de démonstration sans rien casser :

```bash
docker compose -f deploy/docker-compose.prod.yml stop queue
# attendre le prochain passage de supervision:alert (≤ 15 min)
docker compose -f deploy/docker-compose.prod.yml start queue
```
