# Preprod — pièges rencontrés & exploitation

Complément opérationnel de [`PREPROD.md`](../PREPROD.md) (racine), qui décrit la mise
en place de référence. Ce document-ci concentre **les problèmes réellement rencontrés
au premier déploiement et leur cause** (le « pourquoi »), plus les commandes
d'exploitation courantes.

> Placeholders : `<NAS_IP>`, `<NAS_USER>`, `<TUNNEL_ID>` masquent l'infra. Chemins NAS
> donnés à titre d'exemple (`/volume1/docker/...`, convention Synology/UGREEN).

Cibles :
- Front : `https://preprod.oceanetorresphotographie.fr` (Render, branche `develop`)
- API : `https://preprod-api.oceanetorresphotographie.fr` (NAS, port `8081`)

---

## 1. Pièges rencontrés (et pourquoi)

### 1.1 SSL — `handshake failure` sur un sous-domaine de niveau 2
**Symptôme** : `curl https://api.preprod.oceanetorresphotographie.fr/...` →
`sslv3 alert handshake failure`.
**Cause** : le certificat **Universal SSL gratuit** de Cloudflare ne couvre que
`*.oceanetorresphotographie.fr` (**un seul niveau**). `api.preprod.…` est un niveau 2,
non couvert → pas de certificat présenté.
**Fix** : utiliser un sous-domaine **de niveau 1** : `preprod-api.oceanetorresphotographie.fr`
(et non `api.preprod.…`). L'alternative payante serait l'Advanced Certificate Manager.

### 1.2 Tunnel Cloudflare — éditer `config.yml` ne fait rien
**Symptôme** : `Could not resolve host` / ingress ignoré malgré l'édition de
`/etc/cloudflared/config.yml` sur le NAS.
**Cause** : le tunnel (`<TUNNEL_ID>`) est **remotely-managed** : `cloudflared` récupère
son ingress depuis le **dashboard Cloudflare**, pas depuis le fichier local (signe dans
les logs : `Updated to new configuration … version=N`).
**Fix** : ajouter le Public Hostname dans **Zero Trust → Networks → Tunnels → ce tunnel →
Public Hostnames** : `preprod-api` · `oceanetorresphotographie.fr` · `HTTP` →
`localhost:8081`. L'edge applique automatiquement, sans restart.

### 1.3 Docker Compose V2 — chemins résolus depuis le dossier du compose
**Symptôme** : `unable to prepare context: path .../deploy/api not found`.
**Cause** : Compose V2 résout les chemins relatifs **depuis le dossier du fichier
compose** (`deploy/`), pas depuis la racine du repo.
**Fix** : `--project-directory` sur la racine du repo. C'est ce que fait
`deploy/deploy-preprod.sh` :
```bash
DC=(docker compose --project-directory "$REPO_ROOT" -f "$COMPOSE_FILE")
```

### 1.4 `bootstrap/cache` absent au build puis au runtime
**Symptôme (build)** : `bootstrap/cache directory must be present and writable`.
**Symptôme (runtime)** : `chmod: /var/www/bootstrap/cache: No such file or directory`.
**Cause** : `bootstrap/cache` n'est pas versionné ; et au runtime le volume monté
masque le dossier créé dans l'image.
**Fix** : `mkdir -p bootstrap/cache` dans le `Dockerfile` (avant composer) **et**
`mkdir -p /var/www/bootstrap/cache` dans la `command` des 3 services du compose.

### 1.5 MinIO — `403 SignatureDoesNotMatch`
**Cause** : `MINIO_ENDPOINT` pointait sur `host.docker.internal:9000` → la signature
SigV4 signe le mauvais `host`. Les URLs présignées incluent le header `host` dans la
signature ; s'il diffère de l'hôte réellement appelé, la signature est rejetée.
**Fix** : `MINIO_ENDPOINT=https://s3.oceanetorresphotographie.fr` (l'URL publique
réellement utilisée par le client).

### 1.6 MinIO — `403 AccessDenied` et objet inexistant
**Cause** : la clé d'accès MinIO était **scopée au seul bucket `galleries`**. Les
uploads vers `galleries-preprod` échouaient silencieusement (le disk Laravel est en
`'throw' => false`).
**Fix** : créer une **clé d'accès dédiée** scopée à `galleries-preprod` via la console
MinIO, puis re-uploader.

### 1.7 `.env` non appliqué après `restart`
**Cause** : `docker compose restart` **ne recharge pas** `env_file`.
**Fix** : `up -d --force-recreate` (ou `config:cache` dans le container puis restart).

---

## 2. Exploitation

```bash
ssh <NAS_USER>@<NAS_IP>
cd /volume1/docker/oceane-api-preprod    # dossier DISTINCT de la prod

# État des containers (--project-directory . depuis la racine du repo)
docker compose --project-directory . -f deploy/docker-compose.preprod.yml ps

# Logs
docker exec api-php-preprod tail -f storage/logs/laravel.log
docker logs api-nginx-preprod --tail 50

# Artisan
docker exec api-php-preprod php artisan migrate:status
docker exec api-php-preprod php artisan tinker

# Redéploiement (met à jour develop + build + migrate + cache)
./deploy/deploy-preprod.sh
```

### Après toute modif de `deploy/.env.preprod`
```bash
docker exec api-php-preprod php artisan config:cache
# --project-directory . : sinon Compose V2 résout les chemins depuis deploy/
docker compose --project-directory . -f deploy/docker-compose.preprod.yml up -d --force-recreate
```

---

## 3. Points de vigilance

- **Isolation prod/preprod** : preprod tourne depuis `/volume1/docker/oceane-api-preprod`,
  prod depuis `/volume1/docker/oceane-api`. Ne jamais lancer les deux compose depuis le
  même dossier.
- **Port 8081** : si occupé → `sudo lsof -i :8081`.
- **Port DB** : `6543` (transaction pooler). En cas d'erreur de connexion Supabase,
  basculer sur `5432` (session pooler) dans `deploy/.env.preprod`.
- **Emails** : Brevo partagé avec la prod par défaut → éviter d'envoyer des mails de
  test vers de vraies adresses tant qu'un compte/expéditeur dédié n'est pas configuré.
- **Paiements** : SumUp **sandbox** → cartes de test uniquement, aucun débit réel.
  `APP_ENV=production` ⇒ pas d'auto-complétion : le paiement doit réellement aboutir
  côté sandbox (au plus proche de la prod).
