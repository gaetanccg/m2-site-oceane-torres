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
```

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

## Voir la doc complete

Consulte `/docs/deploiement-nas-ugreen.md` pour le guide detaille et le troubleshooting.
