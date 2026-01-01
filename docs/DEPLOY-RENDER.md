# Deploiement sur Render

Ce guide explique comment deployer l'ensemble du projet (Frontend Vue + API Laravel) sur Render.

## Architecture

```
oceanetorresphotographie.fr (Frontend)
    └── Static Site (Vue/Vite)

api.oceanetorresphotographie.fr (API)
    └── Web Service (Laravel/PHP)

Supabase (externe)
    ├── PostgreSQL Database
    ├── Storage (photos)
    └── Edge Functions
```

---

## Prerequis

- Compte Render (https://render.com)
- Compte Supabase avec projet configure
- Domaine `oceanetorresphotographie.fr` configure

---

## 1. Deployer l'API Laravel

### 1.1 Creer un Web Service

1. Dashboard Render → **New** → **Web Service**
2. Connecter le repository GitHub
3. Configuration :

| Parametre      | Valeur                    |
|----------------|---------------------------|
| Name           | `oceane-torres-api`       |
| Region         | Frankfurt (EU Central)    |
| Branch         | `main`                    |
| Root Directory | `api`                     |
| Runtime        | Docker                    |
| Instance Type  | Starter ($7/mois) ou Free |

### 1.2 Dockerfile

Le Dockerfile (`api/Dockerfile`) est deja configure pour Render :

- Utilise `php:8.4-cli-alpine` avec `php artisan serve`
- Execute les migrations automatiquement au demarrage
- Cache les configurations pour la production
- Utilise la variable `PORT` de Render

### 1.3 Variables d'environnement API

Dans Render → **Environment** → ajouter :

```env
# Application
APP_NAME="Oceane Torres Photographie"
APP_ENV=production
APP_KEY=base64:GENERER_AVEC_php_artisan_key:generate
APP_DEBUG=false
APP_URL=https://api.oceanetorresphotographie.fr
FRONTEND_URL=https://oceanetorresphotographie.fr

# Database Supabase
DB_CONNECTION=pgsql
DB_HOST=db.ekhlybdoblhjigpnpbff.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=votre_password_supabase

# Supabase
SUPABASE_URL=https://ekhlybdoblhjigpnpbff.supabase.co
SUPABASE_KEY=votre_anon_key
SUPABASE_SERVICE_KEY=votre_service_role_key
SUPABASE_EDGE_FUNCTION_URL=https://ekhlybdoblhjigpnpbff.supabase.co/functions/v1

# Session & Cache (utiliser database car pas de Redis sur free tier)
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync

# CORS
CORS_ALLOWED_ORIGINS=https://oceanetorresphotographie.fr,https://www.oceanetorresphotographie.fr

# Sanctum
SANCTUM_STATEFUL_DOMAINS=oceanetorresphotographie.fr,www.oceanetorresphotographie.fr

# Logs
LOG_CHANNEL=stderr
LOG_LEVEL=error
```

> **Note**: Pour generer `APP_KEY`, executer localement : `php artisan key:generate --show`

### 1.4 Configurer le domaine API

1. Render → Settings → Custom Domains
2. Ajouter `api.oceanetorresphotographie.fr`
3. Configurer le DNS :
    - Type: `CNAME`
    - Name: `api`
    - Value: `oceane-torres-api.onrender.com`

---

## 2. Deployer le Frontend Vue

### 2.1 Creer un Static Site

1. Dashboard Render → **New** → **Static Site**
2. Connecter le repository GitHub
3. Configuration :

| Parametre         | Valeur                         |
|-------------------|--------------------------------|
| Name              | `oceane-torres-web`            |
| Branch            | `main`                         |
| Root Directory    | `web`                          |
| Build Command     | `npm install && npm run build` |
| Publish Directory | `dist`                         |

### 2.2 Variables d'environnement Frontend

```env
VITE_API_URL=https://api.oceanetorresphotographie.fr
VITE_SUPABASE_URL=https://ekhlybdoblhjigpnpbff.supabase.co
VITE_SUPABASE_ANON_KEY=votre_anon_key
```

### 2.3 Configurer les Redirects (SPA)

Creer le fichier `web/public/_redirects` :

```
/*    /index.html   200
```

Ou ajouter dans Render → Redirects/Rewrites :

- Source: `/*`
- Destination: `/index.html`
- Action: Rewrite

### 2.4 Configurer le domaine

Le domaine `oceanetorresphotographie.fr` est probablement deja configure. Verifier :

- `oceanetorresphotographie.fr` → pointe vers le Static Site
- `www.oceanetorresphotographie.fr` → redirige vers le domaine principal

---

## 3. Configuration DNS complete

Dans votre registrar DNS :

| Type  | Name | Value                          | TTL |
|-------|------|--------------------------------|-----|
| A     | @    | IP Render (voir dashboard)     | 300 |
| CNAME | www  | oceane-torres-web.onrender.com | 300 |
| CNAME | api  | oceane-torres-api.onrender.com | 300 |

---

## 4. Supabase - Configuration Production

### 4.1 Database

1. Supabase Dashboard → Settings → Database
2. Copier le **Connection string** (mode: URI)
3. Utiliser les credentials dans les variables d'environnement Render

### 4.2 Storage CORS

Supabase → Storage → Policies, verifier que les buckets autorisent les origines :

- `https://oceanetorresphotographie.fr`
- `https://api.oceanetorresphotographie.fr`

### 4.3 Edge Function

La fonction `cleanup-gallery-files` doit etre deployee :

```bash
supabase functions deploy cleanup-gallery-files
```

---

## 5. Verification post-deploiement

### Checklist

- [ ] `https://oceanetorresphotographie.fr` charge le frontend
- [ ] `https://api.oceanetorresphotographie.fr` repond (test: `/api/galleries`)
- [ ] Login admin fonctionne
- [ ] Upload de photos fonctionne
- [ ] Suppression de galerie nettoie le storage
- [ ] HTTPS actif sur les deux domaines

### Test API

```bash
# Verifier que l'API repond
curl https://api.oceanetorresphotographie.fr/api/galleries

# Verifier la sante
curl https://api.oceanetorresphotographie.fr/api/health
```

---

## 6. Maintenance

### Logs

- Render Dashboard → Logs (temps reel)
- Pour Laravel : `LOG_CHANNEL=stderr` envoie les logs dans Render

### Migrations

Les migrations s'executent automatiquement au demarrage (voir CMD dans Dockerfile).

Pour executer manuellement :

1. Render → Shell
2. `php artisan migrate --force`

### Cache

Si besoin de vider le cache :

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 7. Couts estimes

| Service                | Plan    | Cout/mois   |
|------------------------|---------|-------------|
| Static Site (Frontend) | Free    | $0          |
| Web Service (API)      | Starter | $7          |
| Supabase               | Free    | $0          |
| **Total**              |         | **$7/mois** |

> Note: Le plan Free de Render pour les Web Services met le service en veille apres 15min d'inactivite (cold start de ~30s). Le plan Starter ($7) garde le service actif.

---

## Troubleshooting

### L'API ne demarre pas

1. Verifier les logs Render
2. Verifier que `APP_KEY` est defini
3. Verifier la connexion DB (credentials Supabase)

### Erreur CORS

1. Verifier `CORS_ALLOWED_ORIGINS` dans l'API
2. Verifier `SANCTUM_STATEFUL_DOMAINS`
3. Verifier que le frontend utilise bien `https://`

### Photos ne s'affichent pas

1. Verifier `VITE_SUPABASE_URL` et `VITE_SUPABASE_ANON_KEY`
2. Verifier les policies Storage dans Supabase

### Cold start lent (plan Free)

Le premier appel apres 15min d'inactivite prend ~30s. Solutions :

- Passer au plan Starter ($7/mois)
- Utiliser un service de ping externe (UptimeRobot, cron-job.org)
