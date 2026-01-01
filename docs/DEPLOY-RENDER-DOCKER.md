# Deploiement sur Render avec Docker

Guide detaille pour deployer l'API Laravel sur Render en utilisant Docker.

## Sommaire

1. [Dockerfile explique](#1-dockerfile-explique)
2. [Configuration Render](#2-configuration-render)
3. [Variables d'environnement](#3-variables-denvironnement)
4. [Deploiement etape par etape](#4-deploiement-etape-par-etape)
5. [Dockerfile alternatif (Nginx/PHP-FPM)](#5-dockerfile-alternatif-nginxphp-fpm)
6. [Debugging](#6-debugging)

---

## 1. Dockerfile explique

Le fichier `api/Dockerfile` actuel :

```dockerfile
FROM php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git curl libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev zip unzip postgresql-dev oniguruma-dev \
    icu-dev libwebp-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . /var/www

# Install dependencies (production)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Create storage symlink & cache config
RUN php artisan storage:link || true
RUN php artisan config:cache || true
RUN php artisan route:cache || true
RUN php artisan view:cache || true

EXPOSE 8000

# Run migrations puis start server
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
```

### Points cles

| Element | Description |
|---------|-------------|
| `php:8.4-cli-alpine` | Image legere (~50MB) avec PHP CLI |
| `--no-dev` | N'installe pas les dependances de dev |
| `--optimize-autoloader` | Optimise l'autoload pour la prod |
| `config:cache` | Cache la config pour de meilleures perfs |
| `${PORT:-8000}` | Utilise le port fourni par Render |
| `migrate --force` | Execute les migrations au demarrage |

---

## 2. Configuration Render

### 2.1 Creer le Web Service

1. **Dashboard Render** → New → Web Service
2. **Connect repository** → Selectionner votre repo GitHub
3. **Configuration** :

| Parametre | Valeur |
|-----------|--------|
| Name | `oceane-torres-api` |
| Region | `Frankfurt (EU Central)` |
| Branch | `main` |
| Root Directory | `api` |
| Runtime | `Docker` |
| Instance Type | `Starter` ($7/mois) |

> **Note**: Render detecte automatiquement le Dockerfile dans le Root Directory.

### 2.2 Health Check (optionnel mais recommande)

Dans Settings → Health & Alerts :

| Parametre | Valeur |
|-----------|--------|
| Health Check Path | `/api/health` |
| Health Check Period | 30 seconds |

Creer la route health dans `api/routes/api.php` si elle n'existe pas :

```php
Route::get('/health', fn() => response()->json(['status' => 'ok']));
```

---

## 3. Variables d'environnement

### 3.1 Methode rapide

1. Ouvrir `api/.env.render` dans votre editeur
2. Render → Environment → **Add from .env**
3. Coller le contenu de `.env.render`
4. Cliquer **Save Changes**

### 3.2 Variables obligatoires

```env
APP_NAME="Oceane Torres Photographie"
APP_ENV=production
APP_KEY=base64:fBdAdmkKuMUI0pgTUfsbO0YOGSAxrsKzfw7r6pp7YCE=
APP_DEBUG=false
APP_URL=https://api.oceanetorresphotographie.fr
FRONTEND_URL=https://oceanetorresphotographie.fr

DB_CONNECTION=pgsql
DB_HOST=db.ekhlybdoblhjigpnpbff.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=votre_mot_de_passe

SUPABASE_URL=https://ekhlybdoblhjigpnpbff.supabase.co
SUPABASE_KEY=votre_anon_key
SUPABASE_SERVICE_KEY=votre_service_role_key

LOG_CHANNEL=stderr
```

### 3.3 Variables importantes pour la securite

| Variable | Valeur Production |
|----------|-------------------|
| `APP_DEBUG` | `false` (JAMAIS true en prod!) |
| `APP_ENV` | `production` |
| `LOG_LEVEL` | `error` (evite de logger les infos sensibles) |

---

## 4. Deploiement etape par etape

### Etape 1 : Preparer le code

```bash
# S'assurer que tout est commite
git add .
git commit -m "Prepare for Render deployment"
git push origin main
```

### Etape 2 : Creer le service sur Render

1. https://dashboard.render.com → **New** → **Web Service**
2. Connecter GitHub si pas deja fait
3. Selectionner le repository `m2-site-oceane-torres`
4. Configurer comme indique section 2.1

### Etape 3 : Configurer les variables

1. Aller dans **Environment**
2. Cliquer **Add from .env**
3. Coller le contenu de `api/.env.render`
4. **Save Changes**

### Etape 4 : Lancer le deploiement

Le deploiement demarre automatiquement apres l'ajout des variables.

Suivre les logs : **Logs** tab

### Etape 5 : Verifier

```bash
# Tester l'API
curl https://oceane-torres-api.onrender.com/api/health

# Tester avec le domaine custom (apres config DNS)
curl https://api.oceanetorresphotographie.fr/api/health
```

---

## 5. Dockerfile alternatif (Nginx/PHP-FPM)

Pour de meilleures performances en production, vous pouvez utiliser Nginx + PHP-FPM :

### 5.1 Dockerfile.production

```dockerfile
FROM php:8.4-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    nginx supervisor \
    git curl libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev zip unzip postgresql-dev oniguruma-dev \
    icu-dev libwebp-dev

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl opcache

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy application
COPY . /var/www

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# Nginx config
COPY docker/nginx-render.conf /etc/nginx/http.d/default.conf

# Supervisor config
COPY docker/supervisord.conf /etc/supervisord.conf

# Cache Laravel config
RUN php artisan storage:link || true \
    && php artisan config:cache || true \
    && php artisan route:cache || true \
    && php artisan view:cache || true

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate --force && supervisord -c /etc/supervisord.conf"]
```

### 5.2 docker/nginx-render.conf

```nginx
server {
    listen 8000;
    server_name _;
    root /var/www/public;
    index index.php;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 5.3 docker/supervisord.conf

```ini
[supervisord]
nodaemon=true
logfile=/dev/null
logfile_maxbytes=0
pidfile=/run/supervisord.pid

[program:php-fpm]
command=php-fpm -F
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true

[program:nginx]
command=nginx -g 'daemon off;'
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
```

### 5.4 Utiliser le Dockerfile alternatif

Pour utiliser cette config, renommer les fichiers :

```bash
mv api/Dockerfile api/Dockerfile.simple
mv api/Dockerfile.production api/Dockerfile
```

---

## 6. Debugging

### 6.1 Voir les logs

- **Render Dashboard** → **Logs** (temps reel)
- Filtrer par `error` pour voir uniquement les erreurs

### 6.2 Acceder au shell

Render → **Shell** tab → executer des commandes :

```bash
# Verifier la config
php artisan config:show database

# Tester la connexion DB
php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';"

# Voir les routes
php artisan route:list

# Vider les caches
php artisan cache:clear
php artisan config:clear
```

### 6.3 Erreurs courantes

| Erreur | Solution |
|--------|----------|
| `APP_KEY not set` | Ajouter `APP_KEY` dans les variables |
| `SQLSTATE connection refused` | Verifier `DB_HOST`, `DB_PASSWORD` |
| `Class not found` | Executer `composer dump-autoload` |
| `Permission denied storage` | Verifier les permissions dans Dockerfile |
| `502 Bad Gateway` | Le container demarre trop lentement, augmenter le timeout |

### 6.4 Rebuild force

Si le deploiement est bloque :

1. **Manual Deploy** → **Clear build cache & deploy**
2. Ou modifier un fichier et push pour declencher un nouveau build

---

## Checklist pre-deploiement

- [ ] `api/.env.render` contient toutes les variables
- [ ] `APP_DEBUG=false` en production
- [ ] `APP_KEY` est defini
- [ ] Connexion Supabase testee localement
- [ ] Migrations a jour (`php artisan migrate:status`)
- [ ] Pas de secrets dans le code commite
- [ ] `.gitignore` inclut `.env`, `.env.render`

---

## Ressources

- [Render Docker Docs](https://render.com/docs/docker)
- [Laravel Deployment](https://laravel.com/docs/deployment)
- [Supabase Connection Pooling](https://supabase.com/docs/guides/database/connecting-to-postgres#connection-pooler)
