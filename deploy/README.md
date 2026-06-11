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

## Voir la doc complete

Consulte `/docs/deploiement-nas-ugreen.md` pour le guide detaille et le troubleshooting.
