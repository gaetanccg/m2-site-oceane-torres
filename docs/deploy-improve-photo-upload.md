# Déploiement sur le NAS — configuration & pièges

Guide de déploiement de l'API sur le NAS (prod), écrit à partir de la mise en
production de la branche `improve/photo-upload` (PR #101 : refonte upload/traitement
photo + durcissement paiement). Les pièges décrits ici sont **génériques** et
s'appliquent à tout déploiement touchant la configuration (`.env`, base de données,
workers).

> Placeholders : `<supabase-pooler-host>` = hôte du pooler Supabase, `<repo>` =
> chemin du clone sur le NAS. À remplacer par les valeurs réelles (jamais commitées).

---

## 1. Le piège n°1 : le pooler Postgres (`DB_PORT`)

| Variable  | Valeur                     | Pourquoi                                                        |
|-----------|----------------------------|----------------------------------------------------------------|
| `DB_HOST` | `<supabase-pooler-host>`   | Pooler Supabase (pas la connexion directe)                     |
| `DB_PORT` | **`6543`**                 | **Transaction mode** → pool ~200 connexions au lieu de ~15     |

**Symptôme si `DB_PORT=5432` (session pooler)** : erreurs `EMAXCONNSESSION` en rafale
(le pool de 15 connexions sature), observées jusqu'à ~5000×/jour sous charge.

**Fix** : basculer sur le **transaction pooler** (`6543`). Aucun changement de code :
`api/config/database.php` définit déjà `PDO::ATTR_EMULATE_PREPARES => true`, requis
par le transaction mode (les prepared statements côté serveur y sont incompatibles).

En cas de souci sur `6543` (peu probable), revenir à `5432` suffit — c'est un simple
changement d'`.env` + rechargement de config, sans toucher au code.

---

## 2. Le piège n°2 : les workers gardent l'ancienne config

`queue` et `scheduler` sont des process **long-running**. Un `restart` du seul
container `laravel` les laisse tourner avec l'**ancienne** config en mémoire →
bugs silencieux (SMS/emails/jobs qui partent avec d'anciennes valeurs).

**Règle** : après toute modif d'`.env`, invalider le cache **puis** redémarrer
**tous** les services qui lisent la config.

```bash
cd <repo>
git pull origin main

# 1. Invalider le config cache figé (sinon les valeurs .env restent bloquées)
docker compose -f deploy/docker-compose.prod.yml exec laravel php artisan config:clear

# 2. Redémarrer TOUS les services concernés (code + env + config:cache)
docker compose -f deploy/docker-compose.prod.yml restart laravel queue scheduler
```

---

## 3. Vérifications post-déploiement

```bash
# Port DB effectivement pris en compte (attendu : 6543)
docker compose -f deploy/docker-compose.prod.yml exec laravel \
    php artisan tinker --execute="echo config('database.connections.pgsql.port');"

# Expéditeur SMS Brevo (attendu : OceanePhoto — sans espace, 11 chars alphanum max)
docker compose -f deploy/docker-compose.prod.yml exec queue \
    php artisan tinker --execute="echo config('services.brevo.sms_sender');"

# Logs propres : pas de EMAXCONNSESSION, pas de "invalid sender name"
docker compose -f deploy/docker-compose.prod.yml exec laravel \
    tail -f /var/www/storage/logs/laravel.log
```

### Tests fonctionnels rapides

- [ ] Galerie scolaire 50+ photos → toutes les vignettes chargent (pas d'erreur DB)
- [ ] Compteur de likes visible quand au moins une photo est likée
- [ ] SMS de test → sender `OceanePhoto` ; email de test → from `contact@…` (pas un Gmail)
- [ ] Édition du titre d'une photo en admin → reflété immédiatement (invalidation du
      cache `photo_meta_{id}`)

---

## 4. Ce qu'apportait la PR #101 (contexte)

- **Refonte upload/traitement photo** (`d7c769a`) — pipeline upload/processing revu +
  durcissement du workflow paiement (cf. [analyse-paiement-sumup.md](analyse-paiement-sumup.md)).
- **Cache des métadonnées photo** (`110d355`) — `ImageProxyController` met en cache la
  lookup `Photo` 1 h ; invalidation automatique via events `saved`/`deleted` sur le
  modèle `Photo`. Réduit fortement la charge DB à l'affichage des galeries.
- **Compteur de likes** (`22a54af`) — indicateur en haut des galeries protégées.

---

## 5. Rollback

```bash
git reset --hard HEAD~1
docker compose -f deploy/docker-compose.prod.yml exec laravel php artisan config:clear
docker compose -f deploy/docker-compose.prod.yml restart laravel queue scheduler
```
