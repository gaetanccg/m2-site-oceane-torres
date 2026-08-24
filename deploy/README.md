# Deploiement NAS UGREEN

Ce dossier contient la configuration de production. Les fichiers sont versionnes dans git **sauf les secrets** (`.env.prod`, `.env.deploy`).

## Structure

```
deploy/
├── .env.prod.example      # Template (commite, sans secrets)
├── .env.prod               # Secrets reels (gitignored)
├── .env.deploy             # Ancien fichier secrets (gitignored)
├── docker-compose.prod.yml # Compose production (commite)
├── nginx.prod.conf         # Nginx production (commite)
├── deploy.sh               # Script de deploiement (commite)
├── docker/
│   ├── php.ini             # Config PHP production (commite)
│   └── www.conf            # Config PHP-FPM production (commite)
├── README.md
└── SSH-COMMANDS.md
```

## Premier deploiement

```bash
# 1. Cloner le repo sur le NAS
git clone https://github.com/gaetanccg/m2-site-oceane-torres.git
cd m2-site-oceane-torres

# 2. Créer le fichier de secrets
cp deploy/.env.prod.example deploy/.env.prod
# Remplir les valeurs <SECRET> dans deploy/.env.prod

# 3. Lancer le deploiement
./deploy/deploy.sh --no-pull
```

## Mise a jour (deploiement courant)

```bash
# Depuis le NAS, dans le repo :
./deploy/deploy.sh
```

Le script fait automatiquement : `git pull` → `docker build` → `docker down/up` → `migrations` → `cache`.

### Options

```bash
./deploy/deploy.sh --no-pull   # Ne pas faire git pull (premier deploiement)
./deploy/deploy.sh --no-build  # Ne pas rebuild les images Docker
./deploy/deploy.sh --fresh     # Rebuild en ignorant le cache Docker
```

Le build utilise le cache Docker par defaut. `--fresh` n'est pas necessaire pour
prendre en compte du code applicatif : le compose monte `./api` sur `/var/www`,
donc les couches applicatives de l'image sont masquees au runtime, et toute
modification du `Dockerfile` invalide deja le cache d'elle-meme. Le reserver aux
couches systeme qui doivent repartir de zero (`pecl install imagick` n'est pas
epingle) ou a un cache corrompu.

## Environnement de preprod

Une stack preprod iso-prod tourne en parallele sur le meme NAS (containers `-preprod`,
port `8081`, reseau dedie), alimentee par la branche `develop`, avec base/bucket/Brevo
dedies et SumUp en sandbox. Fichiers dedies : `.env.preprod.example`,
`docker-compose.preprod.yml`, `nginx.preprod.conf`, `deploy-preprod.sh`.

```bash
# Premier deploiement preprod (clone DISTINCT, sur develop)
cp deploy/.env.preprod.example deploy/.env.preprod   # puis remplir les <SECRET>
./deploy/deploy-preprod.sh --no-pull
```

Guide complet : `/PREPROD.md` (racine du repo).

## Sauvegarde et restauration

`deploy/backup.sh` couvre la base PostgreSQL (Supabase) et le bucket MinIO.
Aucune procedure n'existait avant la phase 0.2 du plan de refonte.

```bash
./deploy/backup.sh db        # dump + comptages de reference
./deploy/backup.sh verify    # restaure le dump et compare les comptages
./deploy/backup.sh storage   # miroir du bucket MinIO
./deploy/backup.sh all       # les trois, dans l'ordre
```

Les sorties vont dans `./backups/` (gitignore : le dump contient l'integralite
des donnees clientes).

Cible `deploy/.env.prod` par defaut. Pour la preprod :

```bash
BACKUP_ENV_FILE=deploy/.env.preprod ./deploy/backup.sh all
```

### Deux points a ne pas contourner

**Le dump passe par le port 5432, jamais 6543.** `pg_dump` exige une session
stable et un snapshot coherent, ce que le pooler *transaction* ne garantit pas.
Une connexion unique de sauvegarde ne risque pas le `EMAXCONNSESSION` observe en
production, qui venait de la charge web.

**La version du client s'aligne seule.** `pg_dump` refuse de tourner contre un
serveur plus recent que lui. Le script releve `server_version`, choisit
`postgres:<majeur>-alpine` en consequence, et inscrit ce majeur dans le fichier
`.counts` pour que `verify` restaure avec la meme version. Definir `POSTGRES_IMAGE`
soi-meme epingle le client et desactive cet alignement.

**Le dump se limite au schema `public`.** Supabase ne sert que de PostgreSQL
manage — ni Auth, ni Storage, ni Realtime — et toutes les tables de l'application
vivent dans `public` (38, plus `migrations`). Sans ce filtre, `pg_dump` embarque les
schemas internes de Supabase et leurs extensions (`supabase_vault`, `pg_graphql`…),
qui font echouer la restauration partout ailleurs que sur Supabase. Le filtre est
applique au dump ET a la restauration, pour que les dumps pris avant cette regle
restent verifiables. `DUMP_SCHEMA` permet de le changer.

**Un dump non restaure n'est pas une sauvegarde.** `verify` restaure le dernier
dump dans un conteneur jetable (tmpfs, `--network none`) et compare 9 tables
temoins. Tant que cette etape n'est pas au vert, il n'y a pas de sauvegarde.

### Restauration reelle (incident)

```bash
# 1. Identifier le dump a restaurer
ls -lt backups/oceane-prod-*.dump

# 2. Restaurer dans une base NEUVE — jamais par-dessus une base vivante
docker run -d --name ot-restore -e POSTGRES_PASSWORD=postgres \
  -e POSTGRES_DB=oceane_restore -p 55434:5432 postgres:16-alpine
docker cp backups/oceane-prod-<date>.dump ot-restore:/tmp/r.dump
docker exec -e PGPASSWORD=postgres ot-restore \
  pg_restore --dbname=oceane_restore --no-owner --no-privileges \
             --username=postgres --exit-on-error /tmp/r.dump

# 3. Controler avant de basculer quoi que ce soit
docker exec -e PGPASSWORD=postgres ot-restore \
  psql -U postgres -d oceane_restore -c \
  "SELECT count(*) FROM orders WHERE status='paid';"

# 4. Fichiers : le miroir MinIO se remonte avec mc
#    ⚠️ Le serveur MinIO est PARTAGE entre production et preproduction : seul le bucket
#    distingue les deux. Nommer explicitement le bucket, ne jamais recopier cette ligne
#    telle quelle — « galleries » est celui de la PRODUCTION.
BUCKET=galleries-preprod   # ou « galleries » pour la production, en le voulant
mc mirror --preserve "backups/minio/${BUCKET}" "<alias>/${BUCKET}"
```

> Restaurer la base **sans** restaurer MinIO (ou l'inverse) produit un etat
> incoherent : des lignes `photos` pointant des objets absents, ou des objets
> orphelins. Les deux vont ensemble.

### Recurrence

A planifier (cron sur le NAS) une fois la premiere execution validee :

```cron
# Dump quotidien a 02h00, retention 30 jours
0 2 * * *  cd /chemin/du/repo && ./deploy/backup.sh db  >> /var/log/ot-backup.log 2>&1
# Miroir MinIO hebdomadaire, dimanche 02h30
30 2 * * 0 cd /chemin/du/repo && ./deploy/backup.sh storage >> /var/log/ot-backup.log 2>&1
# Restauration de controle mensuelle, le 1er a 03h00
0 3 1 * *  cd /chemin/du/repo && ./deploy/backup.sh verify >> /var/log/ot-backup.log 2>&1
```

## Voir la doc complete

Consulte `/docs/deploiement-nas-ugreen.md` pour le guide detaille et le troubleshooting.
