# Environnement de PREPROD — guide complet

Preprod **iso-prod** : mêmes images Docker, même code, mais configuration isolée.
Elle tourne **en parallèle** de la prod sur le même NAS UGREEN, avec un site Render
dédié pour le front.

- **Front** : `https://preprod.oceanetorresphotographie.fr` (Render, branche `develop`)
- **API** : `https://preprod-api.oceanetorresphotographie.fr` (NAS, port `8081`)

---

## 1. Ce qui change vs la prod

| Élément            | Prod                                   | Preprod                                       |
|--------------------|----------------------------------------|-----------------------------------------------|
| Branche git        | `main`                                 | `develop`                                     |
| Front (Render)     | `oceanetorresphotographie.fr`          | `preprod.oceanetorresphotographie.fr`         |
| API                | `api.oceanetorresphotographie.fr`      | `preprod-api.oceanetorresphotographie.fr`     |
| Port Nginx (NAS)   | `8080`                                 | `8081`                                        |
| Containers         | `api-php`, `api-queue`, …              | `api-php-preprod`, `api-queue-preprod`, …     |
| Projet compose     | `oceane-api-prod`                      | `oceane-api-preprod`                          |
| Réseau Docker      | `oceane-network`                       | `oceane-network-preprod`                      |
| Base de données    | Supabase prod (`ekhlybdoblhjigpnpbff`) | **Supabase dédié preprod** (à créer)          |
| Bucket MinIO       | `galleries`                            | **`galleries-preprod`** (même serveur MinIO)  |
| Emails             | Brevo prod                             | Brevo (partagé par défaut, dédié recommandé)  |
| SumUp              | `live` (`sup_sk_…` marchand prod)      | **`sandbox`** (clés déjà dans `api/.env`)     |
| `APP_ENV`          | `production`                           | `production` (iso-prod)                       |
| `LOG_LEVEL`        | `error`                                | `debug`                                       |

> **Paiements** : la preprod est en `APP_ENV=production`, donc le raccourci
> « sandbox auto-complete » d'`OrderService` (qui exige `APP_ENV ∈ {local, testing}`)
> **ne s'active pas**. Les paiements passent par le **vrai flux SumUp sandbox**
> (widget + `getCheckout`), au plus proche du comportement prod.

---

## 2. Fichiers du projet

Déjà présents dans le repo (générés pour la preprod) :

```
deploy/
├── .env.preprod.example        # Template preprod (commité)
├── .env.preprod                # Secrets preprod, pré-remplis (gitignored)
├── docker-compose.preprod.yml  # Compose preprod (commité)
├── nginx.preprod.conf          # Nginx preprod (commité)
└── deploy-preprod.sh           # Script de déploiement preprod (commité)
web/
└── .env.preprod                # VITE_API_URL preprod (gitignored)
PREPROD.md                      # Ce guide
```

`deploy/docker/php.ini` et `deploy/docker/www.conf` sont **partagés** avec la prod.

Le fichier `deploy/.env.preprod` est **déjà pré-rempli** au maximum (MinIO, SumUp
sandbox, Brevo, APP_KEY). Seules deux valeurs restent à compléter, marquées
`⚠️ À REMPLACER` dedans : les identifiants **Supabase preprod**.

---

## 3. Prérequis (une seule fois)

### 3.1 Base Supabase dédiée
Projet preprod déjà créé : **`koimluuhjlhslwlxiesb`** (eu-central-1). Les valeurs
sont pré-remplies dans `deploy/.env.preprod` ; il ne reste qu'à **coller le mot
de passe** du projet :

```env
DB_HOST=aws-0-eu-central-1.pooler.supabase.com
DB_PORT=6543                       # transaction pooler (comme la prod) ; 5432 = session pooler
DB_DATABASE=postgres
DB_USERNAME=postgres.koimluuhjlhslwlxiesb
DB_PASSWORD="<mot de passe du projet Supabase preprod>"   # ⚠️ à compléter
```

> Les clés API Supabase (`SUPABASE_URL`, `*_KEY`, JWKS) ne sont **pas** utilisées
> par l'app : connexion Postgres directe uniquement. Ne rien ajouter d'autre.

### 3.2 Bucket MinIO preprod
Même serveur MinIO que la prod, bucket séparé :

```bash
# Sur le NAS, si le client mc est configuré (alias 'local') :
mc mb local/galleries-preprod
# Sinon via la console MinIO (http://<nas>:9001) → Buckets → Create Bucket.
```

### 3.3 (Recommandé) Brevo dédié
`deploy/.env.preprod` réutilise par défaut les identifiants Brevo de prod pour
fonctionner tout de suite. Pour éviter de polluer les stats d'envoi prod et tout
risque d'email à de vrais clients, créer un compte/expéditeur Brevo dédié et
remplacer `MAIL_USERNAME` / `MAIL_PASSWORD` / `BREVO_API_KEY`.

### 3.4 SumUp sandbox
Déjà rempli (clés sandbox reprises de `api/.env`). Rien à faire sauf si tu veux
un marchand sandbox différent.

### 3.5 DNS Cloudflare
| Type  | Name          | Target                                    | Proxy    |
|-------|---------------|-------------------------------------------|----------|
| CNAME | `preprod`     | `oceane-torres-web-preprod.onrender.com`  | DNS only |
| CNAME | `preprod-api` | `<tunnel-id>.cfargotunnel.com`            | Proxied  |

> Sous-domaines **de niveau 1** volontairement (`preprod-api`, pas `api.preprod`) :
> le SSL Universal gratuit de Cloudflare ne couvre qu'`*.oceanetorresphotographie.fr`.
> Un `api.preprod.…` (niveau 2) déclencherait un `handshake failure` faute de
> certificat, sauf à payer l'Advanced Certificate Manager.

### 3.6 Tunnel Cloudflare (config du tunnel, sur le NAS)
Ajouter une entrée d'ingress :

```yaml
- hostname: preprod-api.oceanetorresphotographie.fr
  service: http://localhost:8081
```
Puis redémarrer `cloudflared`.

---

## 4. Déploiement de l'API (NAS)

La preprod suit `develop`, la prod suit `main` → **clone distinct** obligatoire.

```bash
# 1. SSH sur le NAS
ssh Gaetan-Admin@192.168.1.49

# 2. Cloner dans un dossier DISTINCT de la prod, sur develop
cd /volume1/docker
git clone https://github.com/gaetanccg/m2-site-oceane-torres.git oceane-api-preprod
cd oceane-api-preprod
git checkout develop

# 3. Récupérer les secrets preprod
#    (deploy/.env.preprod est gitignored : le copier depuis ta machine via scp,
#     ou repartir du template et le compléter)
cp deploy/.env.preprod.example deploy/.env.preprod   # si pas déjà copié
#    -> compléter DB_USERNAME / DB_PASSWORD (Supabase preprod)

# 4. Premier déploiement (pas de git pull, on vient de cloner)
chmod +x deploy/deploy-preprod.sh
./deploy/deploy-preprod.sh --no-pull
```

### Mises à jour courantes
```bash
ssh Gaetan-Admin@192.168.1.49
cd /volume1/docker/oceane-api-preprod
./deploy/deploy-preprod.sh          # git pull origin develop + build + migrate + cache
```
Options : `--no-pull`, `--no-build` (comme le script prod).

### Vérifier
```bash
curl http://localhost:8081/api/prestations
curl https://preprod-api.oceanetorresphotographie.fr/api/health
```

---

## 5. Déploiement du front (Render)

Créer un **second Static Site** Render, identique à la prod sauf :

| Paramètre             | Valeur                                    |
|-----------------------|-------------------------------------------|
| **Name**              | `oceane-torres-web-preprod`               |
| **Branch**            | `develop`                                 |
| **Root Directory**    | `web`                                     |
| **Build Command**     | `npm install && npm run build:prerender`  |
| **Publish Directory** | `dist`                                    |

Variables d'environnement (Render → Environment) :

| Variable                           | Valeur                                                  |
|------------------------------------|---------------------------------------------------------|
| `VITE_API_URL`                     | `https://preprod-api.oceanetorresphotographie.fr/api`   |
| `PUPPETEER_SKIP_CHROMIUM_DOWNLOAD` | `false`                                                 |

Custom domain : `preprod.oceanetorresphotographie.fr`.

> Build preprod **en local** :
> `VITE_API_URL=https://preprod-api.oceanetorresphotographie.fr/api npm run build:prerender`

---

## 6. Isolation garantie

- **Ports** : 8081 (preprod) vs 8080 (prod) → pas de collision.
- **Containers / réseau / projet compose** : suffixés `-preprod` → coexistent.
- **DB** : projet Supabase distinct.
- **Stockage** : bucket `galleries-preprod` (chemins isolés par bucket, mêmes credentials MinIO).
- **Cookies** : front et API partagent le parent `.oceanetorresphotographie.fr` (comme en prod) ; l'isolation vs prod vient du nom de cookie distinct `SESSION_COOKIE=oceane_preprod_session`.
- **Paiements** : SumUp sandbox → aucun débit réel.
- **Emails** : dédiés recommandés (sinon partagés avec la prod).

---

## 7. Checklist de mise en route

- [ ] Projet Supabase preprod créé, `DB_USERNAME`/`DB_PASSWORD` remplis dans `deploy/.env.preprod`
- [ ] Bucket MinIO `galleries-preprod` créé
- [ ] (Optionnel) Compte/expéditeur Brevo dédié configuré
- [ ] DNS Cloudflare `preprod` + `preprod-api` ajoutés
- [ ] Ingress tunnel `preprod-api → localhost:8081` ajouté et `cloudflared` redémarré
- [ ] Repo cloné sur le NAS dans `oceane-api-preprod`, branche `develop`
- [ ] `./deploy/deploy-preprod.sh --no-pull` exécuté avec succès
- [ ] `curl http://localhost:8081/api/prestations` répond
- [ ] Static Site Render preprod créé (branche `develop`, `VITE_API_URL` preprod)
- [ ] Domaine `preprod.oceanetorresphotographie.fr` accessible

---

## 8. Troubleshooting

**Conflit de port** : `sudo lsof -i :8081` — vérifier qu'aucun autre service n'écoute.

**Les deux stacks se marchent dessus** : preprod doit tourner depuis
`/volume1/docker/oceane-api-preprod` et prod depuis `/volume1/docker/oceane-api`.
Ne jamais lancer les deux compose depuis le même dossier.

**Migrations** : `docker exec api-php-preprod php artisan migrate:status`

**Logs** :
```bash
docker exec api-php-preprod tail -f storage/logs/laravel.log
docker logs api-nginx-preprod --tail 50
```

**CORS** : après modif de `deploy/.env.preprod`, vider le cache config :
```bash
docker exec api-php-preprod php artisan config:cache
docker compose --project-directory . -f deploy/docker-compose.preprod.yml restart
```

**Images qui ne s'affichent pas** : vérifier que le bucket `galleries-preprod`
existe et que `MINIO_PUBLIC_URL=https://s3.oceanetorresphotographie.fr`.
