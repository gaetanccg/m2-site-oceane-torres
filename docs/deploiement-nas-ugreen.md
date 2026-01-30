# Déploiement du Backend Laravel sur NAS UGREEN

Ce guide explique comment déployer le backend Laravel sur ton NAS UGREEN avec Docker et l'exposer via Cloudflare Tunnel.

> **Fichiers prêts à copier** : Tous les fichiers de configuration sont dans le dossier `/deploy/` avec les vraies valeurs. Il suffit de les copier sur le NAS.

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
ssh Gaetan-Admin@192.168.1.49

# Créer le répertoire pour le backend
mkdir -p /volume1/docker/oceane-api
cd /volume1/docker/oceane-api
```

> **Note** : Le chemin `/volume1/docker/` peut varier selon ta configuration UGREEN. Adapte-le si nécessaire.

### 1.2 Transférer les fichiers nécessaires

> **Note importante (NAS UGREEN)** : La commande `scp` ne fonctionne pas correctement sur les NAS UGREEN. Utilise plutôt **l'interface web du NAS** (drag & drop) ou la méthode alternative `cat | ssh` décrite ci-dessous.

#### Méthode recommandée : Interface web du NAS (drag & drop)

1. Connecte-toi à l'interface web de ton NAS UGREEN
2. Navigue vers `/volume1/docker/oceane-api/`
3. Crée la structure de dossiers suivante :
   ```
   /volume1/docker/oceane-api/
   ├── api/                    # Dossier du backend Laravel
   ├── docker/                 # Dossier des configs Docker
   │   ├── nginx.prod.conf     # ATTENTION: doit être un FICHIER, pas un dossier
   │   └── php.ini
   ├── .env.prod
   ├── docker-compose.prod.yml
   └── deploy.sh
   ```

4. Transfère les fichiers depuis `/deploy/` en les renommant :
   - `deploy/.env.deploy` → `.env.prod`
   - `deploy/docker-compose.prod.deploy.yml` → `docker-compose.prod.yml`
   - `deploy/nginx.prod.deploy.conf` → `docker/nginx.prod.conf`
   - `deploy/deploy.sh.deploy` → `deploy.sh`
   - `docker/php.ini` → `docker/php.ini`

5. Transfère le dossier `api/` (sans `vendor/`, `node_modules/` et `.env`)

#### Méthode alternative : via SSH (cat | ssh)

Si tu préfères la ligne de commande :

```bash
# Depuis le répertoire du projet sur ta machine
cd /Users/gaetanchollet/Projets/m2-site-oceane-torres

# 1. Créer la structure de dossiers sur le NAS
ssh Gaetan-Admin@192.168.1.49 "mkdir -p /volume1/docker/oceane-api/docker"

# 2. Transférer les fichiers de configuration
cat deploy/.env.deploy | ssh Gaetan-Admin@192.168.1.49 "cat > /volume1/docker/oceane-api/.env.prod"
cat deploy/docker-compose.prod.deploy.yml | ssh Gaetan-Admin@192.168.1.49 "cat > /volume1/docker/oceane-api/docker-compose.prod.yml"
cat deploy/nginx.prod.deploy.conf | ssh Gaetan-Admin@192.168.1.49 "cat > /volume1/docker/oceane-api/docker/nginx.prod.conf"
cat deploy/deploy.sh.deploy | ssh Gaetan-Admin@192.168.1.49 "cat > /volume1/docker/oceane-api/deploy.sh"
cat docker/php.ini | ssh Gaetan-Admin@192.168.1.49 "cat > /volume1/docker/oceane-api/docker/php.ini"

# 3. Transférer le dossier api (backend Laravel)
rsync -avz --exclude 'vendor' --exclude 'node_modules' --exclude '.env' \
    ./api/ Gaetan-Admin@192.168.1.49:/volume1/docker/oceane-api/api/
```

> **Attention** : Vérifie que `docker/nginx.prod.conf` est bien un **fichier** et non un dossier. Si c'est un dossier, supprime-le et recrée-le comme fichier :
> ```bash
> ssh Gaetan-Admin@192.168.1.49 "rm -rf /volume1/docker/oceane-api/docker/nginx.prod.conf"
> cat deploy/nginx.prod.deploy.conf | ssh Gaetan-Admin@192.168.1.49 "cat > /volume1/docker/oceane-api/docker/nginx.prod.conf"
> ```

---

## Étape 2 : Configuration de production

> **Note** : Les fichiers ci-dessous sont déjà créés dans `/deploy/` avec les vraies valeurs. Tu n'as qu'à les copier comme indiqué à l'étape 1.2.

### 2.1 Fichier docker-compose.prod.yml

Fichier source : `deploy/docker-compose.prod.deploy.yml`
Destination : `/volume1/docker/oceane-api/docker-compose.prod.yml`

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

### 2.2 Configuration Nginx de production

Fichier source : `deploy/nginx.prod.deploy.conf`
Destination : `/volume1/docker/oceane-api/docker/nginx.prod.conf`

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
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
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

### 2.3 Fichier d'environnement de production

Fichier source : `deploy/.env.deploy`
Destination : `/volume1/docker/oceane-api/.env.prod`

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
4. Ajoute un enregistrement CNAME pour `api` :
   - **Type** : CNAME
   - **Name** : `api`
   - **Target** : La même valeur que ton CNAME `minio` existant (format : `<tunnel-id>.cfargotunnel.com`)
   - **Proxy status** : Proxied (orange)

> **Astuce** : Pour trouver la target, regarde l'enregistrement CNAME existant pour `minio.oceanetorresphotographie.fr` et utilise la même valeur.

### 3.4 Redémarrer cloudflared

```bash
# Si cloudflared tourne comme service
sudo systemctl restart cloudflared

# Ou si c'est un container Docker
sudo docker restart cloudflared
```

---

## Étape 4 : Déploiement initial

> **Note (NAS UGREEN)** : Toutes les commandes Docker nécessitent `sudo` sur UGREEN. Pour éviter cela, tu peux ajouter ton utilisateur au groupe docker :
> ```bash
> sudo usermod -aG docker Gaetan-Admin
> ```
> Puis déconnecte-toi et reconnecte-toi en SSH.

### 4.1 Builder et lancer les containers

```bash
cd /volume1/docker/oceane-api

# Builder l'image Laravel
sudo docker compose -f docker-compose.prod.yml build

# Lancer les services
sudo docker compose -f docker-compose.prod.yml up -d

# Vérifier que les containers tournent
sudo docker ps
```

Tu devrais voir `oceane-laravel` et `oceane-nginx` en plus de `minio`.

### 4.2 Initialiser Laravel

```bash
# Entrer dans le container Laravel
sudo docker exec -it oceane-laravel sh

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
sudo docker compose -f docker-compose.prod.yml logs -f

# Tester localement
curl http://localhost:8080/api/health

# Tester via Cloudflare (après configuration DNS)
curl https://api.oceanetorresphotographie.fr/api/health
```

---

## Étape 5 : Mises à jour futures

### 5.1 Script de déploiement

Fichier source : `deploy/deploy.sh.deploy`
Destination : `/volume1/docker/oceane-api/deploy.sh`

Contenu du script :

```bash
#!/bin/bash
set -e

cd /volume1/docker/oceane-api

echo "📦 Pulling latest changes..."
# Si tu utilises git sur le NAS :
# cd api && git pull origin main && cd ..

echo "🔨 Building containers..."
sudo docker compose -f docker-compose.prod.yml build --no-cache

echo "🔄 Restarting services..."
sudo docker compose -f docker-compose.prod.yml down
sudo docker compose -f docker-compose.prod.yml up -d

echo "⏳ Waiting for containers to be ready..."
sleep 10

echo "🚀 Running migrations..."
sudo docker exec oceane-laravel php artisan migrate --force

echo "🧹 Clearing caches..."
sudo docker exec oceane-laravel php artisan config:cache
sudo docker exec oceane-laravel php artisan route:cache
sudo docker exec oceane-laravel php artisan view:cache

echo "✅ Deployment complete!"
sudo docker compose -f docker-compose.prod.yml ps
```

Rends-le exécutable :

```bash
chmod +x deploy.sh
```

### 5.2 Mise à jour manuelle

Pour mettre à jour le backend :

**Option 1 : Via l'interface web du NAS (drag & drop)**
1. Transfère le dossier `api/` (sans `vendor/`, `node_modules/` et `.env`) via l'interface web
2. Connecte-toi en SSH et lance le script de déploiement

**Option 2 : Via rsync**
```bash
# Depuis ta machine locale
rsync -avz --exclude 'vendor' --exclude '.env' \
    ./api/ Gaetan-Admin@192.168.1.49:/volume1/docker/oceane-api/api/
```

Puis sur le NAS :
```bash
ssh Gaetan-Admin@192.168.1.49
cd /volume1/docker/oceane-api
sudo ./deploy.sh
```

---

## Troubleshooting

> **Rappel** : Toutes les commandes Docker nécessitent `sudo` sur NAS UGREEN.

### Les containers ne démarrent pas

```bash
# Voir les logs détaillés
sudo docker compose -f docker-compose.prod.yml logs laravel
sudo docker compose -f docker-compose.prod.yml logs nginx
```

### Erreur "not a directory" au démarrage de nginx

Cette erreur survient quand `nginx.prod.conf` est un dossier au lieu d'un fichier :

```bash
# Vérifier le type
ls -la /volume1/docker/oceane-api/docker/nginx.prod.conf

# Si c'est un dossier (commence par 'd'), le supprimer et recréer comme fichier
sudo rm -rf /volume1/docker/oceane-api/docker/nginx.prod.conf
# Puis retransfère le fichier via l'interface web ou cat | ssh
```

### Erreur 502 Bad Gateway

Le container Laravel n'est pas prêt ou PHP-FPM ne répond pas :

```bash
# Vérifier que PHP-FPM écoute
sudo docker exec oceane-laravel ps aux | grep php-fpm

# Vérifier les logs Laravel
sudo docker exec oceane-laravel cat storage/logs/laravel.log
```

### Erreur de connexion à la base de données

```bash
# Tester la connexion depuis le container
sudo docker exec oceane-laravel php artisan tinker
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
sudo docker logs cloudflared -f
```

### Permissions storage

```bash
sudo docker exec oceane-laravel chmod -R 775 storage bootstrap/cache
sudo docker exec oceane-laravel chown -R www-data:www-data storage bootstrap/cache
```

### Erreur CORS "No 'Access-Control-Allow-Origin' header"

Cette erreur masque souvent une **erreur 500** côté serveur. Laravel ne renvoie pas les headers CORS quand il crash.

**Diagnostic :**
```bash
# Tester directement l'API
curl -I https://api.oceanetorresphotographie.fr/api/cart

# Si 500, vérifier les logs Laravel
sudo docker exec oceane-laravel tail -50 /var/www/storage/logs/laravel.log
```

**Causes fréquentes :**
- Variables d'environnement manquantes (voir ci-dessous)
- Cache de configuration obsolète

### Erreur "Cannot assign null to property" (ex: SumUpService::$apiKey)

Le `.env` est correct mais Laravel utilise des valeurs en cache.

**Solution :**
```bash
# Vider TOUS les caches
sudo docker exec oceane-laravel php artisan config:clear
sudo docker exec oceane-laravel php artisan cache:clear
sudo docker exec oceane-laravel php artisan route:clear
sudo docker exec oceane-laravel php artisan view:clear

# Vérifier que la config est bien lue
sudo docker exec oceane-laravel php artisan tinker --execute="echo config('sumup.api_key') ? 'OK' : 'NULL';"
```

### Erreur 404 sur toutes les routes API

Nginx retourne 404 sans passer à PHP-FPM.

**Causes possibles :**

1. **Mauvais hostname dans nginx.conf** - Vérifier que `fastcgi_pass` utilise le bon nom de conteneur :
   ```bash
   sudo docker exec oceane-nginx cat /etc/nginx/conf.d/default.conf | grep fastcgi_pass
   # Doit afficher : fastcgi_pass oceane-laravel:9000;
   ```

2. **Permissions des fichiers PHP** - Les fichiers doivent être lisibles par nginx :
   ```bash
   # Vérifier les permissions
   sudo docker exec oceane-nginx ls -la /var/www/public/index.php
   # Si -rw------- (600), nginx ne peut pas lire !

   # Corriger les permissions sur le NAS
   find /volume1/docker/oceane-api/api -type f -name "*.php" -exec chmod 644 {} \;
   find /volume1/docker/oceane-api/api -type d -exec chmod 755 {} \;
   ```

3. **Conteneurs sur des réseaux différents** :
   ```bash
   # Vérifier que nginx peut atteindre laravel
   sudo docker exec oceane-nginx ping -c 1 oceane-laravel
   ```

### Après transfert de fichiers : toujours vérifier les permissions

Quand tu transfères des fichiers sur le NAS (via interface web, rsync, scp...), les permissions peuvent être incorrectes. **Toujours exécuter après un transfert :**

```bash
# Sur le NAS
find /volume1/docker/oceane-api/api -type f -exec chmod 644 {} \;
find /volume1/docker/oceane-api/api -type d -exec chmod 755 {} \;
chmod -R 775 /volume1/docker/oceane-api/api/storage
chmod -R 775 /volume1/docker/oceane-api/api/bootstrap/cache
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
