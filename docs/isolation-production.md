# Isolation de la production

> Écrit après l'incident du **22 août 2026** : `docker compose exec laravel php artisan test`
> a effacé la base de production. Le conteneur portait des identifiants de production alors
> qu'`APP_ENV` valait `local`, et `RefreshDatabase` lance `migrate:fresh`. Rien, nulle part,
> ne trouvait cette combinaison anormale.
>
> Ce document décrit ce qui, désormais, l'empêche — et ce qui reste à faire à la main.

## 1. Ce qui distingue les deux environnements

| | Production | Préproduction |
|---|---|---|
Projet Supabase | `ekhlybdoblhjigpnpbff` | `koimluuhjlhslwlxiesb` |
Hôte du pooler | `aws-1-eu-west-1…` | `aws-0-eu-central-1…` |
Bucket MinIO | `galleries` | `galleries-preprod` |
SumUp | compte réel | `SUMUP_ENV=sandbox` |
API | `api.oceanetorres…` | `preprod-api.oceanetorres…` |

⚠️ **Le serveur MinIO est partagé.** `s3.oceanetorresphotographie.fr` sert les deux : seul
le **bucket** distingue la production. Une erreur de bucket suffit donc à toucher les
photos réelles — c'est pourquoi le bucket fait partie des cibles surveillées.

## 2. Les quatre protections

### 2.1 Le garde-fou applicatif — `ProductionTargetGuard`

Appelé dans `AppServiceProvider::register()`, donc **avant** tout accès à la base ou au
stockage : chaque requête, chaque commande artisan, chaque job de queue, chaque tâche
planifiée. Si `APP_ENV` n'est pas `production` et que la configuration touche la
production, l'application **refuse de démarrer**.

Deux natures de règles, et la distinction compte :

- **Détections** — cette configuration *est* la production : projet Supabase, hôte du
  pooler, bucket MinIO. Déclarés dans `App\Support\ProductionTargets`.
- **Règles qui échouent par défaut** — un environnement de développement doit *déclarer*
  son innocuité. `SUMUP_ENV` doit valoir `sandbox` : chercher une valeur interdite
  laisserait passer une configuration muette, puisque `config('sumup.environment')` vaut
  `production` quand la variable n'est pas définie. On exige l'inverse.
  `SENTRY_LARAVEL_DSN` doit être **vide** : 🔴 le poste portait l'étiquette `local` **et**
  le DSN d'un projet Sentry déployé (`4511942022856784`, distinct de celui de la
  préproduction). Les exceptions de développement partaient donc dans la supervision d'un
  environnement réel, où elles consomment le quota du plan gratuit et noient les vraies
  alertes. Corriger l'étiquette ne suffisait pas — c'est le DSN qui décide de la
  destination. `config/sentry.php` lit `SENTRY_LARAVEL_DSN` **avant** `SENTRY_DSN`, et
  `docker-compose.yml` ne passait que la seconde : la première était héritée en silence de
  `api/.env` monté dans le conteneur. Les trois blocs la passent maintenant explicitement.

**Aucune variable d'environnement ne le désactive**, et c'est délibéré : une échappatoire
serait empruntée par la première personne pressée. Pour agir sur la production, il faut
`APP_ENV=production` — donc le dire. Un test vérifie que le fichier ne lit ni `env()`, ni
`getenv()`, ni `$_SERVER`.

### 2.2 Le garde-fou de la suite de tests — `RefusesNonLocalDatabase`

Antérieur, et complémentaire : il exige une base **locale**, pas seulement non-production.
La préproduction est donc refusée elle aussi — `RefreshDatabase` la viderait.

Il s'accroche à `refreshApplication()`, car `setUpTraits()` (et donc `RefreshDatabase`)
s'exécute juste après.

🔴 **Le volet stockage a été ajouté après coup.** La base était protégée, le disque non :
la suite résolvait le vrai bucket `galleries-preprod` sur le vrai serveur MinIO. Un seul
test oubliant `Storage::fake()` y écrivait — ou y supprimait — des photos de clientes.
Même classe de défaut que l'incident, une couche au-dessus. Le garde exige désormais un
point d'accès local (`Tests\LocalStorageEndpoints`).

`Storage::fake()` remplace le disque **après** le garde : impossible de savoir à ce moment
si un test le fera. On exige donc que la *configuration* ne désigne rien de réel, ce qui
protège aussi les tests qui oublient de simuler.

> **`phpunit.xml` ne protège rien.** Ses `<env>` n'écrivent que `getenv()` et `$_ENV`,
> alors que le `env()` de Laravel lit `$_SERVER` **en premier**. Une variable
> d'environnement réelle l'emporte, `force="true"` compris. C'est mesuré, pas supposé.

### 2.3 Les identifiants de production hors de l'arborescence

Sur le poste de développement, `deploy/.env.prod` **n'existe pas**. Il a été déplacé vers :

```
~/.oceane-torres-secrets/env.prod        (chmod 600, dossier en 700)
```

Rien de ce dépôt ne peut donc le lire — ni un script, ni un agent, ni une commande lancée
par mégarde. `deploy/deploy.sh` échoue avec un message qui l'explique ; c'est le
comportement attendu, puisque le déploiement se fait **depuis le NAS**, où le fichier
existe.

### 2.4 Les scripts destructeurs visent la préproduction par défaut

`backup.sh` et `restore.sh` prenaient `deploy/.env.prod` par défaut. C'est désormais
`.env.preprod` : atteindre la production exige de la **nommer**.

```bash
./deploy/backup.sh all                                        # préproduction
BACKUP_ENV_FILE=deploy/.env.prod ./deploy/backup.sh all       # production, explicite
CONFIRM=<project-ref> ./deploy/restore.sh --dump <fichier>    # restauration : + confirmation
```

## 3. Le piège de docker compose

Compose **fige** les variables d'environnement au démarrage du conteneur. `docker compose
restart` ne les rafraîchit pas :

```bash
docker compose up -d --force-recreate    # ← le seul geste qui les recharge
```

C'est ce décalage qui a rendu l'incident possible : le fichier `.env` avait été repointé
sur la préproduction onze heures plus tôt, mais le conteneur tournait encore avec les
variables de production.

Les conteneurs lisent le `.env` de la **racine** (compose y interpole ses `${…}`), pas
`api/.env`. Et à l'intérieur du conteneur, les variables de compose **gagnent** contre
`api/.env`, pour la raison exposée en 2.2.

## 4. Vérifier l'isolation

```bash
docker compose exec laravel php artisan tinker --execute="
  printf('%s | %s | %s', config('app.env'),
    config('database.connections.pgsql.username'),
    config('filesystems.disks.minio.bucket'));"
```

Attendu : `local | postgres.koimluuhjlhslwlxiesb | galleries-preprod`.

Et pour la supervision :

```bash
docker compose exec laravel php artisan tinker --execute="var_dump(config('sentry.dsn'));"
```

Attendu : une valeur vide. Sinon l'application ne démarrerait pas.

Si la configuration touchait la production, l'application ne démarrerait pas — la commande
afficherait le message du garde-fou.

## 5. Ce qui reste à faire à la main

Ces points ne peuvent pas être fermés par du code :

- 🔑 **Clés MinIO de moindre privilège.** Le poste local utilise des clés qui ouvrent tout
  le serveur ; seul le *bucket configuré* est préproduction. Une clé restreinte au bucket
  `galleries-preprod` fermerait définitivement l'accès aux photos réelles. À créer dans la
  console MinIO.
- 🔑 **Rotation.** Les identifiants de production ont séjourné dans l'arborescence du
  projet. Les faire tourner (Supabase, MinIO, SumUp) est la seule façon d'annuler cette
  exposition.
- 💾 **Supabase plan Free = aucune sauvegarde.** Ni PITR, ni sauvegarde quotidienne :
  `deploy/backup.sh` est le seul filet. Voir `docs/…` et le plan.
