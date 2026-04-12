# Deploiement du Backend Laravel sur NAS UGREEN

## Architecture

```
Internet
    |
    v
Cloudflare Tunnel (cloudflared)
    |
    +-- api.oceanetorresphotographie.fr --> Nginx :8080 --> Laravel PHP-FPM :9000
    |
    +-- s3.oceanetorresphotographie.fr  --> MinIO (stockage photos)
    |
    v
Services externes :
    - Supabase (PostgreSQL)
    - Brevo (emails SMTP)
    - SumUp (paiements)
```

## Prerequis

- NAS UGREEN avec Docker installe
- MinIO deja configure et accessible
- Cloudflare Tunnel (cloudflared) en place
- Git installe sur le NAS (ou acces a l'interface web pour importer les fichiers)

---

## Premier deploiement

### Option A : Avec Git sur le NAS (recommande)

```bash
# 1. Se connecter en SSH au NAS
ssh Gaetan-Admin@192.168.1.49

# 2. Cloner le repo
cd /volume1/docker
git clone https://github.com/gaetanccg/m2-site-oceane-torres.git oceane-api
cd oceane-api

# 3. Creer le fichier de secrets
cp deploy/.env.prod.example deploy/.env.prod
# Editer deploy/.env.prod et remplir toutes les valeurs <SECRET>

# 4. Lancer le deploiement
./deploy/deploy.sh --no-pull
```

### Option B : Sans Git (via l'interface web UGREEN)

1. Telecharger le repo en ZIP depuis GitHub
2. Extraire dans `/volume1/docker/oceane-api/`
3. Creer `deploy/.env.prod` depuis `deploy/.env.prod.example`
4. Remplir les secrets
5. En SSH : `cd /volume1/docker/oceane-api && chmod +x deploy/deploy.sh && ./deploy/deploy.sh --no-pull`

---

## Mise a jour (deploiement courant)

### Avec Git (recommande)

```bash
ssh Gaetan-Admin@192.168.1.49
cd /volume1/docker/oceane-api
./deploy/deploy.sh
```

Le script fait automatiquement :
1. `git pull origin main`
2. Build des containers Docker
3. Stop + restart des containers
4. `composer install` (prod optimise)
5. Migrations Laravel
6. Cache config/routes/views
7. Permissions storage

### Sans Git (via l'interface UGREEN)

1. Importer le dossier `api/` mis a jour via l'UI UGREEN
2. Importer les fichiers modifies dans `deploy/` si necessaire
3. En SSH : `cd /volume1/docker/oceane-api && ./deploy/deploy.sh --no-pull --no-build`

### Options du script

```bash
./deploy/deploy.sh              # Complet : git pull + build + restart + migrate
./deploy/deploy.sh --no-pull    # Sans git pull (premier deploiement ou import manuel)
./deploy/deploy.sh --no-build   # Sans rebuild Docker (changements code uniquement)
```

---

## Structure sur le NAS

```
/volume1/docker/oceane-api/          # Racine du repo clone
|-- api/                             # Backend Laravel
|-- deploy/
|   |-- .env.prod                    # Secrets (gitignored, jamais commite)
|   |-- .env.prod.example            # Template des secrets (commite)
|   |-- docker-compose.prod.yml      # Compose production (commite)
|   |-- nginx.prod.conf              # Nginx production (commite)
|   |-- deploy.sh                    # Script de deploiement (commite)
|   |-- docker/
|   |   |-- php.ini                  # Config PHP (commite)
|   |   +-- www.conf                 # Config PHP-FPM (commite)
|   |-- README.md
|   +-- SSH-COMMANDS.md
|-- docker/                          # Configs dev (pas utilise en prod)
+-- web/                             # Frontend (deploye sur Render, pas sur le NAS)
```

---

## Containers Docker

| Container | Image | Role | Port |
|-----------|-------|------|------|
| oceane-laravel | PHP 8.4 FPM Alpine | Backend API | 9000 (interne) |
| oceane-queue | PHP 8.4 FPM Alpine | Background jobs | - |
| oceane-scheduler | PHP 8.4 FPM Alpine | Taches planifiees | - |
| oceane-nginx | Nginx Alpine | Reverse proxy | 8080 (expose) |

---

## Configuration Cloudflare Tunnel

Le tunnel Cloudflare doit pointer :
- `api.oceanetorresphotographie.fr` --> `http://localhost:8080`
- `s3.oceanetorresphotographie.fr` --> MinIO (port selon config)

---

## Troubleshooting

### Verifier que les containers tournent

```bash
cd /volume1/docker/oceane-api
docker compose -f deploy/docker-compose.prod.yml ps
```

### Voir les logs Laravel

```bash
docker exec oceane-laravel tail -f storage/logs/laravel.log
```

### Voir les logs Nginx

```bash
docker logs oceane-nginx --tail 50
```

### Tester l'API

```bash
curl http://localhost:8080/api/prestations
```

### Relancer les containers sans rebuild

```bash
cd /volume1/docker/oceane-api
docker compose -f deploy/docker-compose.prod.yml restart
```

### Forcer un rebuild complet

```bash
cd /volume1/docker/oceane-api
docker compose -f deploy/docker-compose.prod.yml down
docker compose -f deploy/docker-compose.prod.yml build --no-cache
docker compose -f deploy/docker-compose.prod.yml up -d
```

### Executer une commande artisan

```bash
docker exec oceane-laravel php artisan migrate:status
docker exec oceane-laravel php artisan tinker
```

---

## Commandes SSH utiles

Voir `deploy/SSH-COMMANDS.md` pour les commandes d'administration avancees (verification donnees, traitement commandes manuelles, regeneration watermarks, nettoyage MinIO).
