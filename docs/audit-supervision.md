# Audit de supervision — état des lieux avant mise en place

Photographie de l'existant en matière de **supervision, journalisation et alerte**
sur la plateforme (api Laravel 12 sur NAS Ugreen + front Vue statique sur Render).
Ce document est le point de départ de la mise en place décrite dans
[`supervision.md`](supervision.md) : il ne décrit **que ce qui existe aujourd'hui**,
avec les références de fichiers, et conclut sur ce qui manque.

Périmètre audité :

- `api/app/Http/Controllers/Api/HealthController.php` + `api/routes/api.php`
- `api/config/logging.php`, `deploy/docker/php.ini`, `deploy/docker/www.conf`
- `api/config/queue.php`, `api/routes/console.php`
- `deploy/docker-compose.prod.yml`, `deploy/docker-compose.preprod.yml`, `docker-compose.yml`
- `deploy/.env.prod` / `deploy/.env.prod.example`, `api/composer.json`, `web/package.json`

---

## 1. Endpoint de santé

### Ce qui existe

Trois routes **publiques, non authentifiées, non rate-limitées**
(`api/routes/api.php:37-40`) — plus la racine `/api/` qui pointe sur le même
contrôleur :

| Route | Méthode | Ce qu'elle vérifie réellement |
|-------|---------|-------------------------------|
| `GET /api/` et `GET /api/health` | `HealthController::index` (`:11-19`) | **Rien.** Renvoie un JSON statique `{status: "ok", message, version: "1.0.0", timestamp}`. Aucune sonde : la réponse est `ok` même base de données morte, MinIO injoignable et queue à l'arrêt. C'est un test de « PHP répond », pas un test de santé. |
| `GET /api/health/database` | `HealthController::database` (`:21-49`) | Un vrai test : `getPdo()` + `SELECT version()` + comptage des tables de `information_schema`. Pas de mesure de temps de réponse. |
| `GET /api/health/tables` | `HealthController::tables` (`:51-74`) | Liste **le nom de toutes les tables** du schéma `public`. |

Il existe par ailleurs la route de santé native du framework, `GET /up`
(`api/bootstrap/app.php:25`), qui déclenche l'événement `DiagnosingHealth` sans
aucun listener enregistré : elle vaut donc, elle aussi, un simple « le framework
boote ». Elle n'est référencée nulle part dans l'exploitation.

### Problèmes identifiés

1. **`/api/health` ne supervise rien.** Une sonde externe branchée dessus
   resterait verte pendant une panne totale de la base ou du stockage. C'est le
   défaut le plus important de l'existant : l'endpoint donne une fausse
   assurance.
2. **`/api/health/tables` divulgue le schéma complet** (`prestations`, `orders`,
   `personal_access_tokens`, `failed_jobs`…) à n'importe qui sur Internet. C'est
   de la reconnaissance offerte à un attaquant : ça oriente directement une
   tentative d'injection SQL ou d'énumération. Aucun usage légitime connu — la
   route n'est appelée par aucun script de déploiement ni par le front (recherche
   sur tout le repo : seule occurrence de `/api/health` = `PREPROD.md:170`).
3. **`/api/health/database` divulgue** la version exacte de PostgreSQL (aide au
   ciblage de CVE) et **le host de la base** (`config('database.connections.pgsql.host')`,
   soit le pooler Supabase). Pire, la branche d'erreur renvoie
   `$e->getMessage()` brut au client (`:46`) : un message PDO peut contenir le
   host, le port, l'utilisateur et la base. C'est la seule route de l'API qui
   contourne la politique de la gestion d'exceptions centralisée
   (`api/bootstrap/app.php:88-105`), laquelle masque justement ce genre de fuite.
4. **La version est mensongère** : `"1.0.0"` codé en dur alors que le projet est
   en 6.x (`web/package.json` est à `2.2.1`). Aucune variable `APP_VERSION`
   n'existe. Impossible de savoir quelle version tourne réellement en production
   — donnée pourtant indispensable pour corréler une alerte à un déploiement.
5. **Toujours HTTP 200.** `index()` ne peut pas renvoyer 503, donc aucune sonde
   externe ne peut détecter une dégradation par le code de statut.
6. **Aucune sonde sur MinIO, la queue ou le scheduler**, alors que ce sont
   précisément les composants qui tombent en silence (cf. §3 et §4).

### Contrainte à préserver

`PREPROD.md:170` documente `curl .../api/health` comme vérification post-déploiement.
Les clés `status`, `message`, `version`, `timestamp` doivent rester présentes pour
ne rien casser. Le script `deploy/deploy.sh`, lui, ne teste **aucun** endpoint : il
`sleep 15` puis affiche `docker compose ps` et suggère un `curl /api/prestations`
manuel (`deploy/deploy.sh:75-96`). Un déploiement cassé n'est donc pas détecté par
le script lui-même.

---

## 2. Journalisation

### Configuration

`api/config/logging.php` est le fichier Laravel standard, non personnalisé
(canaux `single`, `daily`, `slack`, `papertrail`, `stderr`, `syslog`, `errorlog`,
`null`). Ce qui est **réellement actif en production** (`deploy/.env.prod:26-30`) :

```
LOG_CHANNEL=stack
LOG_STACK=single          # ⚠️ un seul fichier, jamais tourné
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error           # WARNING et INFO sont jetés
```

| Aspect | État |
|--------|------|
| Canal actif | `stack` → `single` uniquement |
| Niveau prod | `error` (donc `info`/`warning` perdus) |
| Rotation | **Aucune.** Le canal `daily` (14 jours, `logging.php:68-74`) existe mais n'est pas utilisé. `storage/logs/laravel.log` croît sans limite jusqu'à saturation du volume du NAS. |
| Emplacement conteneur | `/var/www/storage/logs/laravel.log` |
| Persistance | ✅ Oui — bind mount `./api:/var/www` sur `api-php`, `api-queue` et `api-scheduler` (`docker-compose.prod.yml:30,81,121`) : les trois conteneurs écrivent dans **le même** fichier sur le disque du NAS, qui survit à un `docker compose down`. |
| Preprod | `LOG_LEVEL=debug` (`deploy/.env.preprod.example:38`) : croissance encore plus rapide, même absence de rotation. |

### Logs non persistés (perdus à chaque recréation de conteneur)

- `/var/log/php/error.log` — erreurs PHP fatales (`deploy/docker/php.ini:21`)
- `/var/log/php/slow.log` — requêtes > 10 s (`deploy/docker/www.conf:35-36`)
- `/var/log/nginx/{access,error}.log` — tous les accès HTTP (`deploy/nginx.prod.conf:8-9`)

Aucun de ces trois chemins n'est monté sur un volume. Le `mkdir -p /var/log/php`
au démarrage (`docker-compose.prod.yml:39`) confirme qu'ils vivent dans la couche
éphémère du conteneur. **Conséquence concrète** : après un redémarrage (donc après
tout incident), les logs nginx et PHP-FPM de l'incident sont détruits. Le
diagnostic post-mortem est impossible.

### Ce qui existe déjà de bon

- Toute exception non gérée sur `/api/*` est journalisée avec contexte (path,
  méthode, classe, fichier:ligne) avant d'être remplacée par un message
  générique côté client (`api/bootstrap/app.php:88-105`). La discipline est bonne.
- Une visionneuse de logs existe dans l'admin :
  `App\Http\Controllers\Api\Admin\LogController` (lecture en `tail` bornée à
  512 Ko, filtres niveau/recherche, téléchargement, purge). C'est un **outil de
  consultation, pas de supervision** : il faut savoir qu'il y a un problème pour
  aller le consulter. Personne n'est prévenu.

---

## 3. File d'attente

### Configuration

`QUEUE_CONNECTION=database` en production réelle (`deploy/.env.prod:53`), table
`failed_jobs` créée par `0001_01_01_000001_create_cache_table.php` /
`0001_01_01_000002_create_jobs_table.php`, driver `database-uuids`
(`api/config/queue.php:123-127`). Sept jobs métier sont en circulation
(`api/app/Jobs/` : traitement photo, miniatures, exports RGPD et scolaires,
envois SMS par lots).

⚠️ **Dérive de configuration** : `deploy/.env.prod.example:54` indique
`QUEUE_CONNECTION=sync`. Un futur déploiement bootstrapé depuis ce template
transformerait le conteneur `api-queue` en coquille vide (worker qui tourne mais
ne consomme rien, jobs exécutés en synchrone dans la requête HTTP → timeouts sur
les uploads photo). À corriger.

### Que se passe-t-il quand un job échoue ?

**Rien de visible.** Après 3 tentatives (`--tries=3`,
`docker-compose.prod.yml:96`), le job part dans `failed_jobs` et **s'arrête là** :

- Aucun listener sur `Illuminate\Queue\Events\JobFailed`, aucun
  `Queue::failing(...)` — vérifié par recherche sur `api/app`, `api/routes`,
  `api/config` : zéro occurrence.
- Aucun appel à `queue:monitor` (la commande existe dans le framework mais n'est
  ni planifiée ni écoutée).
- Aucune remontée dans l'admin : `failed_jobs` n'est exposée par aucun endpoint.

Conséquence : si le job d'export RGPD d'une personne échoue, la demande reste
sans réponse et **personne ne le sait** — y compris sur un traitement à enjeu
réglementaire (délai d'un mois de l'article 12 du RGPD).

### Conteneur `api-queue`

| Aspect | État |
|--------|------|
| `restart:` | `always` (`docker-compose.prod.yml:71`) |
| `healthcheck:` | ❌ **Aucun** |
| Profondeur de file surveillée | ❌ Non |
| Âge du job le plus ancien | ❌ Non |
| Redémarrage périodique | ❌ Non (`--max-time=3600` en prod : le worker sort toutes les heures et Docker le relance — le dev, lui, utilise `--max-time=300`) |

Le `restart: always` couvre le cas « le process meurt » (le worker est le
processus principal du conteneur : s'il crashe, le conteneur sort et Docker le
relance). Il ne couvre **pas** le cas « le process est vivant mais ne consomme
plus » (connexion base perdue, boucle bloquée sur un job de 3600 s de timeout,
mémoire saturée). Sans healthcheck applicatif, ce mode de panne est invisible.

---

## 4. Tâches planifiées

`api/routes/console.php` définit quatre tâches, toutes portées par le conteneur
`api-scheduler` (`php artisan schedule:work`, `docker-compose.prod.yml:131`) :

| Tâche | Fréquence | Rôle | Criticité |
|-------|-----------|------|-----------|
| `reconcile-pending-orders` | toutes les 10 min | Filet de sécurité si un webhook SumUp est perdu : réconcilie les commandes `pending` | 🔴 **Critique** — sans elle, une commande payée peut rester `pending` : le client a débité et ne reçoit pas ses photos |
| `cleanup-stale-carts-orders` | quotidien 03:00 | Expire paniers > 7 j et commandes `pending` > 24 h | 🟠 Moyenne |
| `rgpd-data-retention-cleanup` | hebdo dimanche 04:00 | Purge/anonymise données personnelles (paniers, commandes, messages, logs de téléchargement, tokens) | 🔴 **Critique (conformité)** — obligation de limitation de conservation, art. 5.1.e RGPD |
| `privacy-purge-expired` | mensuel le 1er 05:00 | Purge comptable au-delà de la durée légale (10 ans) | 🟠 Moyenne |

### Si `api-scheduler` meurt silencieusement, comment le sait-on ?

**On ne le sait pas.** C'est le trou le plus grave de l'audit, et il est double :

1. **Aucun signal de vie.** Aucun heartbeat, aucun healthcheck sur le conteneur
   (`docker-compose.prod.yml:105-135` : ni `healthcheck:`, ni sonde). Le
   `restart: always` ne protège que du crash franc du process.
2. **Aucun signal d'exécution.** Les trois tâches en `Schedule::call()` ne
   journalisent (`Log::info`) **que si elles ont trouvé quelque chose à faire**
   (`console.php:37-42`, `:81-89`). Une semaine sans ligne dans le log est donc
   indistinguable de : « rien à purger » / « le scheduler est mort » /
   « `schedule:work` tourne mais `schedule:run` échoue ». Le silence n'est pas
   interprétable.

Le mode de panne réaliste, ici, est le plus vicieux : `schedule:work` reste
vivant (le conteneur est `Up`, `docker ps` est vert) mais les tâches ne
s'exécutent plus — connexion Supabase perdue au boot, verrou
`withoutOverlapping` jamais relâché après un kill (`reconcile-pending-orders`
utilise un verrou de 10 minutes, les autres un verrou par défaut), ou cache de
configuration périmé. **Résultat : des commandes payées restent bloquées et la
purge RGPD ne s'exécute plus, sans aucun signal.**

Note : le conteneur `scheduler` n'existe **pas** dans `docker-compose.yml` (dev,
services : `frontend`, `laravel`, `queue`, `nginx`, `postgres-test`, `mailpit`).
Les tâches planifiées ne tournent donc jamais en local — elles ne sont
observables qu'en production.

---

## 5. Docker — healthchecks et politiques de redémarrage

### `deploy/docker-compose.prod.yml`

| Service | Conteneur | `restart:` | `healthcheck:` | Verdict |
|---------|-----------|------------|----------------|---------|
| `laravel` | `api-php` | `always` | `php-fpm -t` (`:55-60`) | ⚠️ **Trompeur** : `php-fpm -t` ne teste que la **syntaxe des fichiers de configuration** — il n'ouvre aucune connexion au process en cours d'exécution. Un php-fpm figé, saturé de workers (`pm.max_children = 30`) ou dont le pool ne répond plus reste « healthy ». Il en existe une variante identique en preprod (`docker-compose.preprod.yml:61-63`). |
| `queue` | `api-queue` | `always` | ❌ aucun | Panne « vivant mais bloqué » invisible |
| `scheduler` | `api-scheduler` | `always` | ❌ aucun | Panne totalement invisible (cf. §4) |
| `nginx` | `api-nginx` | `always` | ❌ aucun | Le point d'entrée HTTP — celui que le monde extérieur touche — n'est pas sondé |

**Effet de bord notable** : `queue`, `scheduler` et `nginx` déclarent tous
`depends_on: laravel: condition: service_healthy`. Le healthcheck de `api-php`
est donc la porte d'entrée du démarrage complet de la stack. Le renforcer est
utile, mais toute erreur dans sa définition empêcherait **les trois autres
services de démarrer** — contrainte à garder en tête lors de la refonte.

Sur `restart:` — `always` vs `unless-stopped` : la différence est qu'`always`
relance un conteneur **même s'il a été arrêté manuellement**, au redémarrage du
démon Docker. Sur un NAS que l'on redémarre pour maintenance, `unless-stopped`
respecte l'intention de l'opérateur (« je l'ai arrêté volontairement, ne le
rallume pas ») : c'est ce qui est déjà utilisé partout en dev
(`docker-compose.yml:13,37,99,161,212`). La prod est donc incohérente avec le
dev sur ce point.

### Périmètre hors compose

- **MinIO** tourne dans un `docker-compose` séparé sur le NAS (cf. commentaire
  P3.2, `docker-compose.prod.yml:23-28`) : hors de ce fichier, donc hors de toute
  supervision par ce compose.
- **Cloudflare Tunnel** (`api.<domaine>`, `s3.<domaine>`) : géré côté dashboard
  Cloudflare, aucun contrôle local. Si le tunnel tombe, tout est injoignable de
  l'extérieur alors que les quatre conteneurs sont verts en local — d'où la
  nécessité d'une sonde **externe** (§7).
- **PostgreSQL** est hébergé chez Supabase : indisponibilité subie, à détecter,
  pas à réparer.
- **Front Render** : plateforme managée, aucune sonde locale.

---

## 6. Erreurs applicatives (Sentry ou équivalent)

**Aucune remontée d'erreurs, ni back ni front.**

| Périmètre | Vérification | Résultat |
|-----------|--------------|----------|
| Back Laravel | `api/composer.json` (`require`) | Aucun `sentry/sentry-laravel`, `bugsnag`, `flare`/`spatie/laravel-ignition` en prod, aucun Telescope/Pulse/Nightwatch installé (les variables `PULSE_ENABLED`/`TELESCOPE_ENABLED`/`NIGHTWATCH_ENABLED` de `api/phpunit.xml:60-62` sont des reliquats de convention, sans paquet derrière) |
| Front Vue | `web/package.json` (`dependencies` : `pinia`, `vue`, `vue-router`) | Aucun `@sentry/vue`. Aucun `app.config.errorHandler`, aucun `window.onerror` : `web/src/main.ts` ne pose aucun filet. `web/src/utils/errorHandler.ts` ne fait que traduire les erreurs d'API en messages utilisateur — il ne remonte rien. |
| Canal Slack Monolog | `api/config/logging.php:76-83` | Canal déclaré mais `LOG_SLACK_WEBHOOK_URL` non défini et `slack` absent de `LOG_STACK` → inactif |

Conséquences concrètes :

- Une erreur JavaScript qui casse le tunnel de paiement chez un visiteur ne
  laisse **aucune trace** : ni côté client, ni côté serveur. Le front est
  statique sur Render, il n'y a pas de log applicatif à consulter.
- Côté back, les erreurs sont bien journalisées (§2) mais **dans un fichier que
  personne ne lit** tant qu'il n'y a pas de plainte client. Le délai de détection
  est celui du signalement par un utilisateur.
- Aucune notion d'agrégation, de déduplication ou de « première occurrence /
  régression après déploiement ».

---

## 7. Sondes externes et disponibilité de la supervision

Aucune sonde externe n'est configurée (aucun UptimeRobot, aucun healthchecks.io,
aucun cron distant). Toute la connaissance de l'état du système est **interne au
NAS** — ce qui est un défaut structurel : si le NAS, sa connexion Internet ou le
tunnel Cloudflare tombent, le système de supervision tombe avec, et personne
n'est prévenu de l'indisponibilité. La compétence visée exige explicitement une
**disponibilité permanente** du dispositif de signalement : elle impose donc au
moins une sonde hébergée hors du NAS.

Moyens de notification déjà disponibles et non exploités pour la supervision :

- **Brevo SMTP** configuré et opérationnel (`deploy/.env.prod:78-85`), utilisé par
  7 mailables métier.
- **`MAIL_ADMIN_EMAIL`** existe déjà (`api/config/mail.php:128`, exposé en
  `config('mail.admin_email')`) et sert de destinataire aux notifications
  d'activité (`SendBookingNotifications.php:45`, `SendContactEmails.php:20`,
  `OrderService.php:612`).
- **`BrevoSmsService`** existe pour le SMS (canal d'escalade possible, mais
  payant à l'unité — à réserver au critique, hors périmètre gratuit).

Le socle de notification est donc déjà là : il n'y a rien à acheter ni à
installer pour alerter par email.

---

## 8. Synthèse — existant / manquant / à mettre en place

### Vue par domaine

| Domaine | Existant | Manquant | À mettre en place |
|---------|----------|----------|-------------------|
| **Endpoint de santé** | 3 routes publiques ; `/api/health` statique ; `/health/database` fonctionnel ; `/up` natif inutilisé | Agrégation, sondes DB/storage/queue/scheduler, temps de réponse, HTTP 503 sur dégradation, version réelle | `/api/health` agrégé (statut global + version), HTTP 200/503, 4 sondes internes |
| **Sécurité du health** | Tout est public | Protection du détail | `/health/tables` supprimée ; détail complet derrière `HEALTH_CHECK_TOKEN` ou admin ; plus de `getMessage()` ni de host/version DB en réponse publique |
| **Version applicative** | `"1.0.0"` codé en dur | Version réelle traçable | `APP_VERSION` en env, exposée par le health et taguée dans Sentry |
| **Journalisation** | `stack`→`single`, `LOG_LEVEL=error`, persisté par bind mount, visionneuse admin | Rotation (fichier non borné), persistance des logs nginx/PHP-FPM | Canal `daily` (rétention bornée) ; volumes pour `/var/log/nginx` et `/var/log/php` |
| **File d'attente** | `database`, `failed_jobs`, 3 tentatives, `restart: always` | Toute détection : personne n'est prévenu d'un échec ; ni profondeur, ni âge, ni healthcheck | Sonde `queue` dans le health (profondeur, âge du plus ancien, `failed_jobs` 24 h) + alerte email + healthcheck par heartbeat |
| **Tâches planifiées** | 4 tâches dont 2 critiques ; `schedule:work` ; `restart: always` | Aucun signal de vie ni d'exécution : la mort du scheduler est **indétectable** | Heartbeat toutes les 5 min en cache + sonde `scheduler` (`stale` > 2 h) + healthcheck conteneur + ping healthchecks.io sur la purge RGPD hebdo |
| **Docker** | `restart: always` ×4 ; 1 healthcheck (`php-fpm -t`, non concluant) | Healthchecks réels sur les 4 services ; cohérence de la politique de restart avec le dev | `healthcheck:` par service (nginx HTTP, php-fpm FastCGI `/ping`, queue et scheduler par heartbeat) ; `restart: unless-stopped` |
| **Erreurs applicatives** | Exceptions API journalisées avec contexte | Aucune remontée centralisée ; le front est un angle mort total | `sentry/sentry-laravel` + `@sentry/vue`, activés seulement si `SENTRY_DSN` est défini, `traces_sample_rate=0`, `send_default_pii=false` |
| **Alerte** | Brevo SMTP + `MAIL_ADMIN_EMAIL` opérationnels, non utilisés pour la supervision | Aucune alerte technique | Tâche /15 min : email si `failed_jobs > 0`, file > 100, ou job en attente > 15 min ; anti-spam 1 h par type (verrou en cache) ; rapport de santé quotidien |
| **Sonde externe / disponibilité permanente** | Rien | Tout : la supervision meurt avec le NAS | UptimeRobot (API `/api/health` mot-clé `ok` + page d'accueil, 5 min) ; healthchecks.io sur la purge RGPD |
| **Dérives de config** | — | `.env.prod.example` annonce `QUEUE_CONNECTION=sync` alors que la prod est en `database` | Corriger le template ; documenter `CACHE_STORE` (le cache fichier est partagé entre conteneurs **via le bind mount** `./api:/var/www` — c'est ce qui rend les heartbeats en cache lisibles par le health) |

### Les cinq angles morts, par gravité

1. 🔴 **La mort du scheduler est indétectable** → commandes payées non
   réconciliées + purge RGPD non exécutée, sans aucun signal (§4).
2. 🔴 **`/api/health` répond `ok` quoi qu'il arrive** → toute sonde branchée
   dessus donne une fausse assurance (§1).
3. 🔴 **Un job échoué n'alerte personne** → un export RGPD peut être perdu
   silencieusement, avec un délai réglementaire à la clé (§3).
4. 🟠 **Zéro visibilité sur les erreurs front** → une régression JS dans le
   tunnel de paiement n'est connue que si un client se plaint (§6).
5. 🟠 **Aucune sonde hors du NAS** → panne du NAS, de sa connexion ou du tunnel
   Cloudflare = supervision aveugle, exigence de disponibilité permanente non
   satisfaite (§7).

Points d'hygiène associés, moins graves mais peu coûteux à corriger :
`/health/tables` publique (§1.2), fuite de `getMessage()` et du host DB (§1.3),
log unique non tourné (§2), logs nginx/PHP-FPM non persistés (§2),
`QUEUE_CONNECTION=sync` dans le template de prod (§3).

### Ce qu'on garde tel quel

L'audit n'appelle pas à tout refaire — plusieurs briques sont saines et servent
de fondation :

- la gestion centralisée des exceptions API, qui journalise avec contexte sans
  fuir vers le client (`api/bootstrap/app.php`) ;
- la persistance de `storage/logs` par bind mount ;
- Brevo SMTP + `MAIL_ADMIN_EMAIL` : le canal d'alerte est déjà en place et gratuit ;
- la visionneuse de logs admin, complémentaire de l'alerte (l'alerte prévient, la
  visionneuse permet de diagnostiquer) ;
- la table `failed_jobs`, qui contient déjà l'information — il ne manque que
  quelqu'un pour la lire.

---

## Rappel des contraintes retenues pour l'étape 2

- Briques **gratuites** uniquement, aucun agent lourd sur le NAS.
- **Pas de breaking change** sur le contrat de `/api/health` (clés `status`,
  `message`, `version`, `timestamp` conservées).
- Aucun secret en dur : tout par `.env`, avec mise à jour de `api/.env.example`
  et `deploy/.env.prod.example`.
- Sentry **inerte** si `SENTRY_DSN` est absent → aucun impact en dev ni en CI.
- `make test` vert, `pint --test` propre, `npm run lint` propre.
