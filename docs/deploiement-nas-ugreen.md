# Déploiement du Backend Laravel sur NAS UGREEN

Ce guide explique comment déployer le backend Laravel sur ton NAS UGREEN avec Docker et l'exposer via Cloudflare Tunnel.

## Architecture cible

```
Internet
    │
    ▼
Cloudflare Tunnel (cloudflared)
    │
    ├── minio.oceanetorresphotographie.fr → MinIO (existant)
    │
    └── api.oceanetorresphotographie.fr → Backend Laravel (nouveau)
                │
                ▼
        ┌──────────────┐
        │    Nginx     │ :8080
        │   (reverse   │
        │    proxy)    │
        └──────┬───────┘
               │
               ▼
        ┌──────────────┐
        │   Laravel    │ :9000 (PHP-FPM)
        │   (API)      │
        └──────────────┘
               │
               ▼
        Services externes :
        - Supabase (PostgreSQL)
        - MinIO (stockage local)
        - Brevo (emails)
```

## Prérequis

- [x] NAS UGREEN avec Docker installé
- [x] MinIO déjà configuré et accessible
- [x] Cloudflare Tunnel (cloudflared) déjà en place
- [x] Accès SSH au NAS
- [ ] Sous-domaine `api.oceanetorresphotographie.fr` configuré dans Cloudflare

---

## Étape 1 : Préparer les fichiers sur le NAS

### 1.1 Créer le répertoire de travail

```bash
# Se connecter en SSH au NAS
ssh utilisateur@ip-du-nas

# Créer le répertoire pour le backend
mkdir -p /volume1/docker/oceane-api
cd /volume1/docker/oceane-api
```

> **Note** : Le chemin `/volume1/docker/` peut varier selon ta configuration UGREEN. Adapte-le si nécessaire.

### 1.2 Transférer les fichiers nécessaires

Depuis ta machine locale, transfère les fichiers :

```bash
# Depuis le répertoire du projet sur ta machine
cd /Users/gaetanchollet/Projets/m2-site-oceane-torres

# Transférer le dossier api (backend Laravel)
rsync -avz --exclude 'vendor' --exclude 'node_modules' --exclude '.env' \
    ./api/ utilisateur@ip-du-nas:/volume1/docker/oceane-api/api/

# Transférer les fichiers de configuration Docker
rsync -avz ./docker/ utilisateur@ip-du-nas:/volume1/docker/oceane-api/docker/
```

---

## Étape 2 : Configuration de production

### 2.1 Créer le fichier docker-compose.prod.yml

Sur le NAS, crée le fichier `/volume1/docker/oceane-api/docker-compose.prod.yml` :

```yaml
name: oceane-api-prod

services:
  # ----------------------------
  # Backend Laravel (PHP-FPM)
  # ----------------------------
  laravel:
    build:
      context: ./api
      dockerfile: Dockerfile
      target: development  # Utilise PHP-FPM, plus stable que artisan serve
    container_name: oceane-laravel
    restart: always
    working_dir: /var/www
    volumes:
      - ./api:/var/www
      - ./docker/php.ini:/usr/local/etc/php/conf.d/custom.ini
    env_file:
      - .env.prod
    networks:
      - oceane-network
    healthcheck:
      test: ["CMD", "php-fpm", "-t"]
      interval: 30s
      timeout: 10s
      retries: 3

  # ----------------------------
  # Nginx (Reverse Proxy API)
  # ----------------------------
  nginx:
    image: nginx:alpine
    container_name: oceane-nginx
    restart: always
    ports:
      - "8080:80"  # Port exposé pour Cloudflare Tunnel
    volumes:
      - ./api/public:/var/www/public:ro
      - ./docker/nginx.prod.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      laravel:
        condition: service_healthy
    networks:
      - oceane-network

networks:
  oceane-network:
    driver: bridge
```

### 2.2 Créer la configuration Nginx de production

Sur le NAS, crée le fichier `/volume1/docker/oceane-api/docker/nginx.prod.conf` :

```nginx
server {
    listen 80;
    server_name api.oceanetorresphotographie.fr;
    root /var/www/public;
    index index.php;

    # Logs
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Max upload size (pour les images)
    client_max_body_size 100M;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass laravel:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_read_timeout 300;
    }

    # Deny sensitive files
    location ~ /\.ht {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 2.3 Créer le fichier d'environnement de production

Sur le NAS, crée le fichier `/volume1/docker/oceane-api/.env.prod` :

```env
# ===========================================
# APPLICATION
# ===========================================
APP_NAME="Oceane Torres Photographie"
APP_ENV=production
APP_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
APP_DEBUG=false
APP_URL=https://api.oceanetorresphotographie.fr
FRONTEND_URL=https://oceanetorresphotographie.fr

APP_LOCALE=fr
APP_TIMEZONE=Europe/Paris

# ===========================================
# DATABASE - SUPABASE POSTGRESQL
# ===========================================
DB_CONNECTION=pgsql
DB_HOST=aws-0-eu-west-1.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.xxxxxxxxxx
DB_PASSWORD=ton_mot_de_passe_supabase

# ===========================================
# MINIO STORAGE (ton NAS)
# ===========================================
FILESYSTEM_DISK=minio
MINIO_ENDPOINT=https://minio.oceanetorresphotographie.fr
MINIO_ACCESS_KEY=ta_access_key_minio
MINIO_SECRET_KEY=ta_secret_key_minio
MINIO_BUCKET=galleries
MINIO_REGION=us-east-1
MINIO_USE_PATH_STYLE=true

# ===========================================
# SESSION & CACHE
# ===========================================
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=.oceanetorresphotographie.fr
SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=true

CACHE_STORE=file
QUEUE_CONNECTION=sync

# ===========================================
# MAIL - BREVO SMTP
# ===========================================
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=ton_login_brevo
MAIL_PASSWORD=ton_password_brevo
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="contact@oceanetorres.fr"
MAIL_FROM_NAME="${APP_NAME}"

# ===========================================
# CORS & SANCTUM
# ===========================================
CORS_ALLOWED_ORIGINS=https://oceanetorresphotographie.fr
SANCTUM_STATEFUL_DOMAINS=oceanetorresphotographie.fr,api.oceanetorresphotographie.fr
```

> **Important** : Remplace toutes les valeurs `ton_xxx` par tes vraies credentials.

---

## Étape 3 : Configuration Cloudflare Tunnel

### 3.1 Localiser la configuration existante

Ton tunnel Cloudflare est déjà configuré pour MinIO. Tu dois ajouter une entrée pour l'API.

```bash
# Trouver le fichier de configuration cloudflared
# Généralement dans ~/.cloudflared/ ou /etc/cloudflared/
find / -name "config.yml" -path "*cloudflared*" 2>/dev/null
```

### 3.2 Modifier la configuration du tunnel

Édite le fichier de configuration cloudflared (généralement `config.yml`) :

```yaml
tunnel: ton-tunnel-id
credentials-file: /chemin/vers/credentials.json

ingress:
  # MinIO (existant)
  - hostname: minio.oceanetorresphotographie.fr
    service: http://localhost:9000  # ou le port de ton MinIO

  # API Laravel (nouveau)
  - hostname: api.oceanetorresphotographie.fr
    service: http://localhost:8080
    originRequest:
      noTLSVerify: true

  # Catch-all (obligatoire)
  - service: http_status:404
```

### 3.3 Ajouter le sous-domaine dans Cloudflare Dashboard

1. Connecte-toi à [Cloudflare Dashboard](https://dash.cloudflare.com)
2. Sélectionne le domaine `oceanetorresphotographie.fr`
3. Va dans **DNS** > **Records**
4. Le sous-domaine `api` devrait déjà pointer vers ton tunnel (CNAME vers `xxx.cfargotunnel.com`)
   - Si ce n'est pas le cas, ajoute un enregistrement CNAME

### 3.4 Redémarrer cloudflared

```bash
# Si cloudflared tourne comme service
sudo systemctl restart cloudflared

# Ou si c'est un container Docker
docker restart cloudflared
```

---

## Étape 4 : Déploiement initial

### 4.1 Builder et lancer les containers

```bash
cd /volume1/docker/oceane-api

# Builder l'image Laravel
docker compose -f docker-compose.prod.yml build

# Lancer les services
docker compose -f docker-compose.prod.yml up -d
```

### 4.2 Initialiser Laravel

```bash
# Entrer dans le container Laravel
docker exec -it oceane-laravel sh

# Installer les dépendances (si pas fait au build)
composer install --no-dev --optimize-autoloader

# Générer la clé si nécessaire
php artisan key:generate

# Lancer les migrations
php artisan migrate --force

# Créer le lien storage
php artisan storage:link

# Optimiser pour la production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Vérifier les permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Sortir du container
exit
```

### 4.3 Vérifier le déploiement

```bash
# Vérifier les logs
docker compose -f docker-compose.prod.yml logs -f

# Tester localement
curl http://localhost:8080/api/health

# Tester via Cloudflare (après configuration DNS)
curl https://api.oceanetorresphotographie.fr/api/health
```

---

## Étape 5 : Mises à jour futures

### 5.1 Script de déploiement

Crée un script `/volume1/docker/oceane-api/deploy.sh` :

```bash
#!/bin/bash
set -e

cd /volume1/docker/oceane-api

echo "📦 Pulling latest changes..."
# Si tu utilises git sur le NAS :
# cd api && git pull origin main && cd ..

echo "🔨 Building containers..."
docker compose -f docker-compose.prod.yml build --no-cache

echo "🔄 Restarting services..."
docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.prod.yml up -d

echo "⏳ Waiting for containers to be ready..."
sleep 10

echo "🚀 Running migrations..."
docker exec oceane-laravel php artisan migrate --force

echo "🧹 Clearing caches..."
docker exec oceane-laravel php artisan config:cache
docker exec oceane-laravel php artisan route:cache
docker exec oceane-laravel php artisan view:cache

echo "✅ Deployment complete!"
docker compose -f docker-compose.prod.yml ps
```

Rends-le exécutable :

```bash
chmod +x deploy.sh
```

### 5.2 Mise à jour manuelle

Pour mettre à jour le backend :

```bash
# Depuis ta machine locale
rsync -avz --exclude 'vendor' --exclude '.env' \
    ./api/ utilisateur@ip-du-nas:/volume1/docker/oceane-api/api/

# Sur le NAS
ssh utilisateur@ip-du-nas
cd /volume1/docker/oceane-api
./deploy.sh
```

---

## Troubleshooting

### Les containers ne démarrent pas

```bash
# Voir les logs détaillés
docker compose -f docker-compose.prod.yml logs laravel
docker compose -f docker-compose.prod.yml logs nginx
```

### Erreur 502 Bad Gateway

Le container Laravel n'est pas prêt ou PHP-FPM ne répond pas :

```bash
# Vérifier que PHP-FPM écoute
docker exec oceane-laravel ps aux | grep php-fpm

# Vérifier les logs Laravel
docker exec oceane-laravel cat storage/logs/laravel.log
```

### Erreur de connexion à la base de données

```bash
# Tester la connexion depuis le container
docker exec oceane-laravel php artisan tinker
# Puis dans tinker :
DB::connection()->getPdo();
```

### Le tunnel Cloudflare ne route pas vers l'API

```bash
# Vérifier que cloudflared voit la config
cloudflared tunnel ingress validate

# Vérifier les logs cloudflared
journalctl -u cloudflared -f
# ou
docker logs cloudflared -f
```

### Permissions storage

```bash
docker exec oceane-laravel chmod -R 775 storage bootstrap/cache
docker exec oceane-laravel chown -R www-data:www-data storage bootstrap/cache
```

---

## Configuration du Frontend (Render)

N'oublie pas de mettre à jour les variables d'environnement sur Render pour pointer vers ta nouvelle API :

```env
VITE_API_URL=https://api.oceanetorresphotographie.fr/api
```

---

## Checklist finale

- [ ] Fichiers transférés sur le NAS
- [ ] `docker-compose.prod.yml` créé
- [ ] `nginx.prod.conf` créé
- [ ] `.env.prod` configuré avec les bonnes credentials
- [ ] Cloudflare Tunnel configuré pour `api.oceanetorresphotographie.fr`
- [ ] DNS configuré dans Cloudflare
- [ ] Containers démarrés et fonctionnels
- [ ] Migrations exécutées
- [ ] Test API via `curl https://api.oceanetorresphotographie.fr`
- [ ] Frontend mis à jour avec la nouvelle URL d'API
