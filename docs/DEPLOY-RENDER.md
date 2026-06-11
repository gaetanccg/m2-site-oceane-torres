# Deploiement du Frontend sur Render

Ce guide explique comment deployer le frontend Vue.js sur Render et le connecter au backend Laravel heberge sur ton NAS UGREEN.

## Architecture

```
                         Internet
                             │
            ┌────────────────┼────────────────┐
            │                │                │
            ▼                ▼                ▼
   ┌─────────────┐   ┌─────────────┐   ┌─────────────┐
   │   Render    │   │  Cloudflare │   │  Supabase   │
   │ Static Site │   │   Tunnel    │   │ (PostgreSQL)│
   └─────────────┘   └──────┬──────┘   └─────────────┘
         │                  │                │
         │           ┌──────┴──────┐         │
         │           │             │         │
         │           ▼             ▼         │
         │    ┌───────────┐ ┌───────────┐    │
         │    │  Laravel  │ │   MinIO   │    │
         │    │   API     │ │  Storage  │    │
         │    └───────────┘ └───────────┘    │
         │           │             │         │
         │           └──────┬──────┘         │
         │                  │                │
         │           ┌──────┴──────┐         │
         │           │  NAS UGREEN │◄────────┘
         │           └─────────────┘
         │
         ▼
oceanetorresphotographie.fr (Frontend Vue.js)
         │
         ├── api.oceanetorresphotographie.fr (Backend Laravel - NAS)
         ├── s3.oceanetorresphotographie.fr (MinIO Storage - NAS)
         └── Supabase (Base de données PostgreSQL)
```

## Services utilises

| Service        | Role                               | URL                             |
|----------------|------------------------------------|---------------------------------|
| **Render**     | Hebergement frontend (Static Site) | oceanetorresphotographie.fr     |
| **NAS UGREEN** | Backend API Laravel + Nginx        | api.oceanetorresphotographie.fr |
| **NAS UGREEN** | Stockage MinIO (S3)                | s3.oceanetorresphotographie.fr  |
| **Supabase**   | Base de données PostgreSQL         | (connexion via API)             |
| **Cloudflare** | DNS + Tunnel vers NAS              | -                               |

---

## Prerequis

Avant de deployer le frontend, assure-toi que :

- [x] **Backend API** deploye sur le NAS et accessible via `https://api.oceanetorresphotographie.fr`
- [x] **MinIO** configure sur le NAS et accessible via `https://s3.oceanetorresphotographie.fr`
- [x] **Supabase** projet cree avec la base de données configuree
- [x] **Cloudflare** domaine configure avec les tunnels actifs
- [ ] **Compte Render** cree (https://render.com)

### Verifier que le backend fonctionne

```bash
curl https://api.oceanetorresphotographie.fr/api/health
```

Doit retourner :

```json
{
    "status": "ok",
    "message": "API Oceane Torres Photographie",
    "version": "1.0.0",
    ...
}
```

---

## Etape 1 : Créer le Static Site sur Render

### 1.1 Connexion et creation

1. Va sur [Render Dashboard](https://dashboard.render.com)
2. Clique sur **New** → **Static Site**
3. Connecte ton repository GitHub `m2-site-oceane-torres`

### 1.2 Configuration du service

| Parametre             | Valeur                                   |
|-----------------------|------------------------------------------|
| **Name**              | `oceane-torres-web`                      |
| **Branch**            | `main`                                   |
| **Root Directory**    | `web`                                    |
| **Build Command**     | `npm install && npm run build:prerender` |
| **Publish Directory** | `dist`                                   |

> **Important SEO** : La commande `build:prerender` genere des pages HTML statiques pour les routes publiques, permettant a Google d'indexer le contenu sans JavaScript.

### 1.3 Prerendering SEO (pages generées)

Le script de prerendering genere des pages HTML statiques pour les routes suivantes :

| Route          | Description           |
|----------------|-----------------------|
| `/`            | Page d'accueil        |
| `/portfolio`   | Portfolio photos      |
| `/prestations` | Services proposes     |
| `/bons`        | Bons cadeaux          |
| `/a-propos`    | Presentation          |
| `/contact`     | Formulaire de contact |
| `/evenements`  | Evenements            |

**Avantages SEO :**

- Google peut indexer le contenu sans executer JavaScript
- Meilleur temps de chargement initial (First Contentful Paint)
- Meilleur score Core Web Vitals
- Les meta tags et Open Graph sont presents dans le HTML initial

> **Note technique** : Le prerendering utilise Puppeteer pour generer les pages. Render supporte Puppeteer nativement.

### 1.4 Variables d'environnement

Dans Render → **Environment** → **Add Environment Variable** :

| Variable       | Valeur                                        |
|----------------|-----------------------------------------------|
| `VITE_API_URL` | `https://api.oceanetorresphotographie.fr/api` |

> **Important** : Le `/api` a la fin est necessaire car c'est le prefixe des routes Laravel.

### 1.5 Configuration pour Puppeteer (prerendering)

Pour que Puppeteer fonctionne sur Render, ajoute ces variables d'environnement :

| Variable                           | Valeur  |
|------------------------------------|---------|
| `PUPPETEER_SKIP_CHROMIUM_DOWNLOAD` | `false` |

Render installe automatiquement les dependances Chromium necessaires pour Puppeteer.

---

## Etape 2 : Configurer le domaine personnalise

### 2.1 Ajouter le domaine dans Render

1. Render → ton service → **Settings** → **Custom Domains**
2. Clique sur **Add Custom Domain**
3. Entre : `oceanetorresphotographie.fr`
4. Ajoute aussi : `www.oceanetorresphotographie.fr`

### 2.2 Configurer le DNS dans Cloudflare

Va dans **Cloudflare Dashboard** → **DNS** → **Records** et ajoute :

| Type  | Name  | Target                           | Proxy           |
|-------|-------|----------------------------------|-----------------|
| CNAME | `@`   | `oceane-torres-web.onrender.com` | DNS only (gris) |
| CNAME | `www` | `oceane-torres-web.onrender.com` | DNS only (gris) |

> **Important** : Desactive le proxy Cloudflare (icone grise) pour le frontend car Render gere le SSL.

### 2.3 Attendre la propagation

- Le certificat SSL sera automatiquement genere par Render
- La propagation DNS peut prendre quelques minutes a quelques heures

---

## Etape 3 : Configurer les redirections SPA

Le frontend est une Single Page Application (SPA). Toutes les routes doivent rediriger vers `index.html`.

### Option A : Fichier _redirects (recommande)

Le fichier `web/public/_redirects` doit contenir :

```
/*    /index.html   200
```

Ce fichier est deja present dans le projet.

### Option B : Via Render Dashboard

1. Render → ton service → **Redirects/Rewrites**
2. Ajoute une regle :
    - **Source** : `/*`
    - **Destination** : `/index.html`
    - **Action** : Rewrite

---

## Etape 4 : Deployer

### 4.1 Trigger le premier deploiement

1. Clique sur **Create Static Site** (ou **Deploy** si deja cree)
2. Render va :
    - Cloner le repository
    - Installer les dependances (`npm install`)
    - Builder le projet (`npm run build`)
    - Publier le dossier `dist`

### 4.2 Suivre le deploiement

- Va dans **Logs** pour suivre le build en temps reel
- Le build prend generalement 1-3 minutes

### 4.3 Verifier le deploiement

Une fois termine, verifie :

```bash
# Via l'URL Render
curl -I https://oceane-torres-web.onrender.com

# Via ton domaine (apres propagation DNS)
curl -I https://oceanetorresphotographie.fr
```

---

## Etape 5 : Verification complete

### Checklist post-deploiement

- [ ] `https://oceanetorresphotographie.fr` charge le frontend
- [ ] Les pages se chargent correctement (accueil, prestations, contact, etc.)
- [ ] L'API repond : `https://api.oceanetorresphotographie.fr/api/health`
- [ ] Les galeries publiques s'affichent
- [ ] Le login admin fonctionne (`/admin`)
- [ ] Upload de photos fonctionne (vers MinIO)
- [ ] Les images s'affichent correctement (signed URLs MinIO)

### Tests manuels

```bash
# Test frontend
curl -I https://oceanetorresphotographie.fr

# Test API health
curl https://api.oceanetorresphotographie.fr/api/health

# Test API database
curl https://api.oceanetorresphotographie.fr/api/health/database

# Test galeries publiques
curl https://api.oceanetorresphotographie.fr/api/galleries
```

---

## Deployements automatiques

Par defaut, Render declenche un nouveau deploiement a chaque push sur la branche `main`.

### Desactiver les deploiements automatiques

Si tu preferes deployer manuellement :

1. Render → ton service → **Settings**
2. **Build & Deploy** → **Auto-Deploy** → Desactiver

### Deployer manuellement

1. Render → ton service → **Manual Deploy** → **Deploy latest commit**

---

## Configuration DNS complete

Voici la configuration DNS complete dans Cloudflare pour tout le projet :

| Type  | Name  | Target                           | Proxy    |
|-------|-------|----------------------------------|----------|
| CNAME | `@`   | `oceane-torres-web.onrender.com` | DNS only |
| CNAME | `www` | `oceane-torres-web.onrender.com` | DNS only |
| CNAME | `api` | `<tunnel-id>.cfargotunnel.com`   | Proxied  |
| CNAME | `s3`  | `<tunnel-id>.cfargotunnel.com`   | Proxied  |

> **Note** : `api` et `s3` pointent vers le tunnel Cloudflare qui redirige vers ton NAS.

---

## Troubleshooting

### Le frontend ne charge pas

1. Verifier les logs de build dans Render
2. Verifier que `VITE_API_URL` est correctement configure
3. Verifier la propagation DNS : `nslookup oceanetorresphotographie.fr`

### Erreur CORS

Si tu vois des erreurs CORS dans la console du navigateur :

1. Verifie que l'API a les bons CORS configures dans `.env.prod` sur le NAS :
   ```env
   CORS_ALLOWED_ORIGINS=https://oceanetorresphotographie.fr,https://www.oceanetorresphotographie.fr
   SANCTUM_STATEFUL_DOMAINS=oceanetorresphotographie.fr,www.oceanetorresphotographie.fr
   ```

2. Redemarrer les containers sur le NAS :
   ```bash
   cd /volume1/docker/oceane-api
   sudo docker compose -f docker-compose.prod.yml restart
   ```

### Les images ne s'affichent pas

1. Verifier que MinIO est accessible : `curl -I https://s3.oceanetorresphotographie.fr/minio/health/live`
2. Verifier les credentials MinIO dans `.env.prod` sur le NAS
3. Verifier que le bucket `galleries` existe dans MinIO

### Page blanche ou erreur 404

1. Verifier que le fichier `_redirects` existe dans `web/public/`
2. Verifier que la regle de rewrite est configuree dans Render

### Build echoue

1. Verifier les logs de build dans Render
2. Tester le build en local :
   ```bash
   cd web
   npm install
   npm run build:prerender
   ```

### Prerendering echoue sur Render

Si le prerendering echoue avec une erreur Puppeteer :

1. Verifier que `PUPPETEER_SKIP_CHROMIUM_DOWNLOAD` n'est pas sur `true`
2. Essayer d'ajouter ces variables d'environnement :
   ```
   PUPPETEER_EXECUTABLE_PATH=/usr/bin/google-chrome-stable
   ```
3. En dernier recours, utiliser le build sans prerendering :
    - Changer Build Command en `npm install && npm run build`
    - Le SEO sera moins optimal mais le site fonctionnera

---

## Couts

| Service            | Plan         | Cout/mois              |
|--------------------|--------------|------------------------|
| Render Static Site | Free         | $0                     |
| Supabase           | Free         | $0                     |
| NAS UGREEN         | Auto-heberge | Electricite uniquement |
| Cloudflare         | Free         | $0                     |
| **Total**          |              | **$0/mois**            |

---

## Maintenance

### Mettre a jour le frontend

```bash
# Push sur main declenche automatiquement un deploiement
git push origin main
```

### Voir les logs

- Render Dashboard → ton service → **Logs**

### Vider le cache

Si tu as des problemes de cache, tu peux forcer un rebuild :

1. Render → ton service → **Manual Deploy** → **Clear build cache & deploy**
