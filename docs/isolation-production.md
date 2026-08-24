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

## 1bis. Le poste de développement tourne sur une base LOCALE

Depuis le 24/08, `.env` et `api/.env` pointent sur le conteneur **`postgres-dev`**
(`127.0.0.1:55433` depuis l'hôte, `postgres-dev:5432` depuis un conteneur), base
`oceane_dev`, chargée avec le **dump anonymisé** (`backups/oceane-anon-2026-08-22.dump` —
196 galeries, 5 350 photos, emails en `@example.test`).

Aucune base **distante** n'est plus jointe depuis le poste. Ce n'est pas qu'une bonne
pratique : c'est ce qui rend l'accident impossible plutôt qu'improbable. Un `migrate:fresh`
lancé par mégarde vide désormais une base jetable, rechargeable en une commande :

```bash
docker run --rm --network oceanephotographie_app-network \
  -v "$PWD/backups:/b" -e PGPASSWORD=postgres postgres:17-alpine \
  pg_restore -h postgres-dev -p 5432 -U postgres -d oceane_dev \
    --clean --if-exists --no-owner --no-privileges /b/oceane-anon-2026-08-22.dump
```

⚠️ **Reste distant** : le stockage objet. Le poste utilise le serveur MinIO partagé avec le
bucket `galleries-preprod`. Le garde-fou interdit le bucket de production, mais les clés
actuelles ouvrent tout le serveur — d'où la clé restreinte demandée au §5.

## 2. Les protections

### 2.1 Le garde-fou applicatif — `ProductionTargetGuard`

Appelé dans `AppServiceProvider::register()`, donc **avant** tout accès à la base ou au
stockage : chaque requête, chaque commande artisan, chaque job de queue, chaque tâche
planifiée. Si `APP_ENV` n'est pas `production` et que la configuration touche la
production, l'application **refuse de démarrer**.

Deux natures de règles, et la distinction compte :

- **Détections** — cette configuration *est* la production : projet Supabase, hôte du
  pooler, bucket MinIO. Déclarés dans `App\Support\ProductionTargets`.

  🔴 **Deux contournements ont été trouvés après coup, dans le garde-fou lui-même**, et
  c'est le genre de trou qu'on ne voit qu'en cherchant à le percer :

  - **`DB_URL`.** `config/database.php` déclare `'url' => env('DB_URL')`. Quand elle est
    définie, Laravel l'utilise et `host` / `username` deviennent **inertes sans être
    effacés** : le garde lisait la préproduction pendant que l'application se connectait à
    la production. Mesuré — l'application démarrait, et la suite de tests s'exécutait. La
    détection porte désormais sur la connexion **résolue**, via le
    `ConfigurationUrlParser` de Laravel : le seul moyen de voir ce à quoi le framework va
    réellement se connecter.
  - **L'hôte direct Supabase.** `db.<ref>.supabase.co` (port 5432) utilise l'utilisateur
    `postgres`, **sans** le ref du projet — que seul le pooler exige. Comparer l'hôte au
    pooler ne détectait donc rien, et l'utilisateur non plus. Le marqueur fiable est le
    **ref de projet cherché partout** dans la connexion : il apparaît soit dans
    l'utilisateur (pooler), soit dans le nom d'hôte (connexion directe).

  Leçon retenue : une liste d'hôtes est une liste noire, et une liste noire est fausse dès
  qu'un fournisseur ajoute un point d'entrée.
- **Règles qui échouent par défaut** — un environnement de développement doit *déclarer*
  son innocuité. `SUMUP_ENV` doit valoir `sandbox` : chercher une valeur interdite
  laisserait passer une configuration muette, puisque `config('sumup.environment')` vaut
  `production` quand la variable n'est pas définie. On exige l'inverse.
  **En `local`, la base doit être LOCALE** — pas seulement « pas la production ».
  N'interdire que la production laissait le poste écrire dans la **préproduction**, qu'un
  `migrate:fresh` aurait vidée exactement de la même façon. C'est l'environnement déclaré
  qui décide du périmètre : la préproduction tourne sous `APP_ENV=production`, sa propre
  base lui reste donc permise.
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

Trois pièges corrigés dans ces scripts, tous du même genre — le script faisait autre chose
que ce qu'il annonçait :

- `backup.sh` : une variable **restée exportée** dans le shell l'emportait *en silence* sur
  le fichier d'environnement, pendant que la bannière annonçait la cible du fichier. Pour
  les variables qui **désignent la cible**, le fichier gagne maintenant, et tout écrasement
  est annoncé.
- `backup.sh` : `MINIO_BUCKET` retombait sur `galleries` — le bucket de **production**. Un
  fichier d'environnement incomplet copiait donc les photos réelles. Plus de repli : la
  variable est exigée.
- `restore.sh` : le message de confirmation livrait la commande **prête à coller**,
  `CONFIRM=<ref>` incluse. Une confirmation qui donne sa propre réponse ne confirme rien.
  Le message dit désormais ce qui est requis, sans le fournir : l'opérateur doit lire le
  bloc « Cible » et recopier lui-même.
- `deploy/README.md` documentait un `mc mirror` écrivant dans `galleries` sans avertir que
  le serveur est partagé. Bucket paramétré, avertissement ajouté.

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
- ✅ **`APP_KEY` de la préproduction : faite.** Elle était **identique à celle de la
  production** — une signature, un cookie ou une URL signée fabriqués en préproduction
  étaient donc valides en production (`OrderAccessSignature` en dépend directement). Une
  clé distincte a été générée ; rien n'est chiffré au repos dans ce schéma (aucun cast
  `encrypted`, aucun appel à `Crypt`), la rotation ne coûte donc que les sessions de
  préproduction. **Prend effet au prochain déploiement de la préproduction.**
- 💾 **Supabase plan Free = aucune sauvegarde.** Ni PITR, ni sauvegarde quotidienne :
  `deploy/backup.sh` est le seul filet. Voir `docs/…` et le plan.
