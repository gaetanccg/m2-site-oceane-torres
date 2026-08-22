# Audit du projet « Océane Torres Photographie » — Document de référence pour refonte

> **Objet** : audit exhaustif du codebase existant (Laravel 12 + Vue 3) en vue d'une refonte complète,
> avec un niveau de détail suffisant pour (a) décider de ce qui est conservé/abandonné et
> (b) écrire un script de migration des données de la base actuelle vers la nouvelle structure.
>
> **Date de l'audit** : 2026-08-22 — dernier commit analysé : `d300806` (2026-08-21), branche `main`.
> **Version applicative** : `web/package.json` → `6.1.0`.

---

## Sommaire

| # | Section | Statut |
|---|---------|--------|
| 1 | Vue d'ensemble du projet | ✅ rédigée |
| 2 | Cartographie complète des fonctionnalités | ✅ rédigée |
| 3 | API Laravel (routes, controllers, models) | ✅ rédigée |
| 3bis | Schéma complet de la base de données | ✅ rédigée |
| 4 | Services externes et intégrations | ✅ rédigée |
| 5 | Traitement des photos et galeries | ✅ rédigée |
| 6 | Sécurité, sessions, comptes | ✅ rédigée |
| 7 | Commandes et facturation | ✅ rédigée |
| 8 | Jobs, queues, tâches planifiées | ✅ rédigée |
| 9 | Dette technique et risques | ✅ rédigée |
| 10 | Stratégie de récupération des données | ✅ rédigée |
| 11 | Synthèse pour la refonte | ✅ rédigée |
| 12 | **Seconde passe — ce que la première a manqué** | ✅ rédigée |
| 13 | **Troisième passe — le front (13 vues admin + public)** | ✅ rédigée |

---

> ### ⚠️ Note de lecture — les sections s'amendent mutuellement
>
> Cet audit a été rédigé section par section, et **chaque section a corrigé des affirmations des
> précédentes** au fur et à mesure que le code était instruit plus profondément. Les corrections sont
> systématiquement signalées dans une sous-section « §X.0 » en tête de section, et la valeur erronée
> a été amendée à sa source.
>
> Corrections notables : le driver d'image (GD → **Imagick**, §5.0.2), les drivers de session et de
> cache en production (database → **file**, §4.0), le nombre de tables (36 → **38**, §3bis.0), le
> nombre d'endpoints (~161 → **177**, §3.1), l'authentification (cookie seul → **hybride**, §2.0), le
> choix du pooler PostgreSQL (§5.0.1), et le contenu de la CI (§6.0).
>
> **Conséquence pratique** : ne jamais se fier à un commentaire du code ou à un README sans le
> vérifier — c'est le thème transverse de la §9.8.
>
> 🔴 **Les §12 et §13 sont des passes de relecture** qui instruit les zones traitées par échantillonnage
> lors de la première rédaction. Elle apporte **3 constats critiques nouveaux** — dont deux
> destructeurs de données — et amende deux recommandations. La **§13** instruit le front (26 629 lignes)
> et y trouve **une vulnérabilité XSS** qui invalide une affirmation des §6 et §9.
> **À lire avant d'agir sur les §5, §6, §9 et §10.**

---

# 1. Vue d'ensemble du projet

## 1.1. Nature du projet

Plateforme web complète pour une photographe professionnelle indépendante (Océane Torres, Loire /
Saint-Étienne / Lyon). Projet de fin d'année de Master 2 (auteur : Gaëtan Chollet), mais **déployé en
production réelle** (domaine `oceanetorresphotographie.fr`) avec un environnement de préproduction
distinct sur NAS (cf. `PREPROD.md`, `deploy/`).

Le périmètre fonctionnel dépasse le simple site vitrine : site public SEO, galeries privées clients
sécurisées, boutique en ligne (fichiers numériques + tirages physiques), réservations/disponibilités,
bons cadeaux, codes promo, module « photos scolaires » (sessions scolaires + envoi SMS en masse),
back-office admin complet, module RGPD et une couche de supervision/monitoring maison.

**Historique Git** : 377 commits, du 2025-11-12 au 2026-08-21 (~9 mois de développement actif).

## 1.2. Architecture générale

**Architecture découplée : API REST Laravel + SPA Vue 3 séparées**, dans un **monorepo** unique.

```
Navigateur
   │
   ├── SPA Vue 3 (statique, pré-rendue)  ──── déployée sur Render / Nginx
   │        │
   │        └── appels XHR vers VITE_API_URL (/api)
   │
   └── API Laravel 12 (JSON only)  ──── Nginx + PHP-FPM (Docker, NAS)
            ├── PostgreSQL (Supabase managé)
            ├── MinIO (stockage objet S3-compatible, self-hosted)
            ├── SumUp (paiements)
            ├── Brevo (SMTP + SMS)
            └── Sentry (erreurs back + front)
```

Caractéristiques structurantes :

- **Pas de multi-tenant** : un seul « propriétaire » métier (la photographe = compte admin unique en pratique),
  N clients. La séparation se fait par rôle (`users.role` ∈ admin/client) et par appartenance des ressources.
- **Authentification hybride** : Laravel Sanctum est configuré en mode **SPA / cookie de session**
  (`statefulApi()`, `EnsureFrontendRequestsAreStateful` en tête du groupe `api`, sessions en base via
  `SESSION_DRIVER=database`) — **mais** `AuthController::login/register` émet en plus un **token Bearer**
  (`createToken('auth-token')`) que le front stocke en `localStorage` et renvoie sur chaque requête
  authentifiée. Les deux mécanismes coexistent (cf. §2.0 et §6).
- **Aucune vue Blade côté public** : `routes/web.php` fait 7 lignes. Blade n'est utilisé que pour les
  **emails** (`resources/views/emails/*`) et la **facture PDF** (`resources/views/pdf/invoice.blade.php`).
- **Toutes les réponses API sont JSON**, avec un handler d'exceptions global dans `bootstrap/app.php`
  qui normalise le format (`{success: false, message: ...}`) et masque les erreurs techniques.
- **SEO** : la SPA est **pré-rendue** au build via Puppeteer (`web/scripts/prerender.js`, script
  `npm run build:prerender`) pour générer du HTML statique par route publique.

## 1.3. Stack technique — Backend (`api/`)

| Élément | Version / valeur | Source |
|---|---|---|
| PHP | `^8.2` requis ; **8.4** en image Docker (`php:8.4-fpm-alpine`) ; 8.4.14 en local | `api/composer.json`, `api/Dockerfile` |
| Laravel Framework | **v12.63.0** | `api/composer.lock` |
| Base de données | PostgreSQL (Supabase managé) — `DB_CONNECTION=pgsql` | `api/.env.example` |
| Auth | `laravel/sanctum` `^4.2` (mode SPA cookie) | `composer.json`, `bootstrap/app.php` |
| Stockage fichiers | `league/flysystem-aws-s3-v3` `^3.0` → disque `minio` (S3-compatible) | `config/filesystems.php` |
| Traitement image | `intervention/image` `^3.11` — **driver Imagick par défaut** (`USE_IMAGICK_DRIVER = true`), repli GD (cf. §5.0.2) | `composer.json`, `ImageProcessingService` |
| PDF | `barryvdh/laravel-dompdf` `^3.1` (factures) | `composer.json` |
| ZIP streaming | `maennchen/zipstream-php` `^3.2` (téléchargement de galerie) | `composer.json` |
| Paiement | `sumup/sumup-ecom-php-sdk` `^1.4` | `composer.json` |
| Monitoring | `sentry/sentry-laravel` `^4.27` | `composer.json` |
| REPL | `laravel/tinker` `^2.10.1` | `composer.json` |
| Tests | PHPUnit `^11.5.3`, Mockery, Faker, Collision | `composer.json` (require-dev) |
| Style | `laravel/pint` `^1.24` | `composer.json` (require-dev) |
| Dev tooling | `laravel/pail`, `laravel/sail` | `composer.json` (require-dev) |

**Configuration Laravel notable** (`api/config/`, 18 fichiers) : en plus des configs standard, trois
fichiers **métier custom** existent — `shop.php`, `invoice.php`, `sumup.php` — plus `supervision.php`
et `sentry.php`. Ces fichiers portent des constantes métier (tarifs, mentions de facture, seuils
d'alerte) qui devront être reprises dans la refonte.

Valeurs de config structurantes :

| Clé | Valeur | Impact refonte |
|---|---|---|
| `app.timezone` | `Europe/Paris` (via `APP_TIMEZONE`) | Les timestamps en base sont écrits par PHP avec ce fuseau — **à vérifier lors de la migration** |
| `app.locale` / fallback | `fr` / `en` | — |
| `app.cipher` | `AES-256-CBC` | **Aucune colonne chiffrée** dans le schéma (vérifié §6.0) : `APP_KEY` ne protège que cookies et sessions |
| `session.driver` | `database` en local / **`file` en production** (cf. §4.0) | Table `sessions` vide en prod |
| `cache.store` | `database` en local / **`file` en production** (cf. §4.0) | Tables vides en prod ; heartbeats en fichiers |
| `queue.connection` | `database` (tables `jobs`, `job_batches`, `failed_jobs`) | Tables régénérables |
| `filesystems.default` | `minio` | **Les fichiers vivent hors BDD — cf. section 5** |

## 1.4. Stack technique — Frontend (`web/`)

| Élément | Version | Source |
|---|---|---|
| Vue | `^3.4.38` (Composition API, `<script setup>`) | `web/package.json` |
| Vue Router | `^4.6.3` (history mode) | idem |
| Pinia | `^3.0.4` (stores : `auth`, `cart`, `consent`) | idem |
| Build | Vite `6.4.1` + `@vitejs/plugin-vue` `^5.1.3` | idem |
| Langage | TypeScript `^5.5.3` + `vue-tsc` (typecheck bloquant au build) | idem |
| CSS | TailwindCSS `^3.4.18` + PostCSS + Autoprefixer | idem |
| Monitoring | `@sentry/vue` `^10.69.0` (import **dynamique conditionnel** si DSN vide) | idem |
| Prerender SEO | `puppeteer` `^24.35.0` (`scripts/prerender.js`) | idem |
| Images (build) | `sharp` `^0.34.5` (`scripts/generate-thumbs.js`, `optimize-images.js`) | idem |
| Lint | ESLint 9 (flat config) + `eslint-plugin-vue` + `typescript-eslint` | idem |

> ⚠️ **Aucune librairie de composants UI** n'est installée malgré ce qu'annonce le README
> (« shadcn-vue ») : tous les composants sont écrits à la main (84 fichiers `.vue`).
> **Aucun framework de test front** (pas de Vitest, contrairement au README).

**Volumétrie front** : 84 composants `.vue`, 45 fichiers `.ts`, réparties en :

```
web/src/
├── views/          # 18 pages publiques/client + 14 pages admin + 2 pages compte
├── components/     # composants partagés (Navbar, Lightbox, MasonryGallery, PhotoCard…)
│   ├── admin/      # layout admin + PhotosManager + modales + ui/ (Button, DataTable, Modal…)
│   ├── auth/       # AuthModal
│   ├── cart/       # CartDrawer, CartItem, AddToCartButton, ShippingAddressFields
│   ├── icons/      # icônes SVG inline
│   └── ui/         # ConfirmDialog, ToastContainer
├── services/       # couche d'accès API (baseApi + 1 module par domaine)
│   ├── admin/      # 11 modules API admin (clients, galleries, orders, privacy…)
│   └── upload/     # chunkUploader + utils (upload de photos par chunks)
├── stores/         # Pinia : auth.ts, cart.ts, consent.ts (Consent Mode v2 / GA4)
├── composables/    # useChunkedUpload, useConfirm, useEta, useGtag, useProductTypes, useToast
├── router/         # routes + guards (requiresAdmin / requiresClientAuth)
├── types/          # types TS partagés (index, admin, account, upload)
├── utils/          # download, errorHandler, format, gallery, monitoring, authEvents
└── config/         # constants.ts, gallery.ts, gallery-manifest.json
```

**Routes front** (Vue Router, `web/src/router/index.ts`) :

| Zone | Routes |
|---|---|
| Public / vitrine | `/`, `/portfolio`, `/prestations`, `/bons`, `/a-propos`, `/contact`, `/mentions-legales`, `/cgv`, `/politique-confidentialite` |
| Galeries privées | `/gallery` (saisie code), `/gallery/:code`, `/gallery/download/:token` |
| Galeries événements (public) | `/evenements`, `/evenements/:id` |
| Boutique | `/panier`, `/checkout`, `/commande/:id`, `/commande/confirmation` (redirection callback SumUp) |
| Espace client | `/mon-compte`, `/mon-compte/commande/:id` (`requiresClientAuth`) |
| Admin (layout dédié `AdminRoot`) | `/admin`, `/admin/galleries`, `/clients`, `/prestations`, `/reservations`, `/gift-cards`, `/promo-codes`, `/events`, `/orders`, `/school-sessions`, `/school-sessions/:id`, `/privacy`, `/logs` (`requiresAdmin`) |
| 404 | `/:pathMatch(.*)*` |

Les routes sensibles portent `meta.robots = 'noindex, nofollow'`, les routes publiques portent
`title` + `description` exploités par le pré-rendu Puppeteer.

## 1.5. Structure des dossiers (racine du monorepo)

```
m2-site-oceane-torres/
├── api/                      # ★ Backend Laravel 12 (le cœur du projet)
├── web/                      # ★ Frontend Vue 3 SPA
├── deploy/                   # Compose + Nginx + scripts de déploiement preprod & prod
│   ├── docker-compose.prod.yml / .preprod.yml
│   ├── nginx.prod.conf / nginx.preprod.conf
│   ├── deploy.sh / deploy-preprod.sh
│   └── .env.prod / .env.preprod (+ .example)   ⚠️ fichiers .env réels présents sur disque
├── docker/                   # Config Docker de dev (nginx.conf, php.ini)
├── docs/                     # Documentation d'analyse interne (8 fichiers, cf. ci-dessous)
├── supabase/                 # Edge Function Deno `cleanup-gallery-files` + README
├── .github/workflows/ci.yml  # CI GitHub Actions
├── docker-compose.yml        # Stack de dev complète
├── Makefile                  # Raccourcis dev (up/down/migrate/test/lint…)
├── README.md                 # ⚠️ largement obsolète (cf. §1.8)
├── PREPROD.md                # Runbook préproduction
└── bootstrap/, database/, resources/, storage/   # ⚠️ RÉSIDUS d'un squelette Laravel à la racine
```

> ⚠️ **Résidus versionnés à la racine** : `bootstrap/cache/`, `database/database.sqlite`,
> `storage/framework/views/*.php`, `resources/js/` sont des restes d'un squelette Laravel
> initialement posé à la racine avant le déplacement dans `api/`. Ils sont **suivis par Git**
> (`git ls-files` les remonte) et sans aucun usage. À supprimer purement et simplement.

### Structure du backend (`api/app/` — 178 fichiers PHP)

```
api/app/
├── Console/Commands/         # 11 commandes artisan (dont 4 dans Supervision/)
├── Events/                   # 2 events (BookingRequested, ContactMessageSent)
├── Exceptions/               # BusinessException (exception métier à statut HTTP)
├── Helpers/                  # MimeTypes
├── Http/
│   ├── Controllers/Api/      # 20 controllers publics/client
│   │   └── Admin/            # 10 controllers admin
│   ├── Middleware/           # 3 : EnsureUserIsAdmin, EnsureHealthCheckToken, SecurityHeaders
│   ├── Requests/             # 52 FormRequest (dont 34 dans Admin/, 5 dans Auth/)
│   └── Resources/            # 4 API Resources (Gallery, Order, OrderItem, CalendarEvent)
├── Jobs/                     # 7 jobs (photo, thumbnails, exports RGPD/scolaire, SMS)
├── Listeners/                # 2 (SendBookingNotifications, SendContactEmails)
├── Mail/                     # 9 Mailables
├── Models/                   # 29 modèles Eloquent (+ Concerns/CastsBooleansForPostgres)
├── Observers/                # UserObserver
├── Policies/                 # 3 (Gallery, Order, Reservation)
├── Providers/                # AppServiceProvider (unique)
├── Rules/                    # SmsTemplateRule
├── Services/                 # 28 services (métier + Privacy/ + Supervision/Probes/)
└── Traits/                   # ClearsEventGalleriesCache, SyncsProductTypes
```

Volumétrie de référence pour la suite de l'audit :

| Élément | Nombre |
|---|---|
| Controllers | 27 fichiers (20 API + 10 Admin, dont `Controller.php` de base) |
| Modèles Eloquent | 29 |
| Migrations | **76** (33 `Schema::create` → **38 tables** créées, le reste = altérations) |
| Définitions de routes API | 156 lignes `Route::` + 5 `apiResource` → **~177 endpoints** (`routes/api.php`, 341 lignes) |
| FormRequests | 52 |
| Services | 28 |
| Jobs | 7 |
| Mailables | 9 |
| Commandes artisan | 11 |
| Middlewares custom | 3 |
| Tests PHPUnit | 34 fichiers (Feature + Unit) |
| Factories | 11 — **aucun seeder** hormis `DatabaseSeeder` (2 comptes + 5 prestations, cf. §3bis.0) |

### Conventions de code observées

- **Backend** : PSR-4 (`App\` → `app/`), Laravel Pint pour le style, `declare(strict_types)` non
  systématique. Controllers « fins » qui délèguent à des Services pour le métier lourd
  (`OrderService`, `CartService`, `PricingService`, `ImageProcessingService`, `SumUpService`…).
  Validation systématiquement via **FormRequest** dédiés (52 classes) plutôt qu'en inline.
  Commentaires en **anglais** dans le code PHP, mais messages utilisateur et docs en **français**.
- **Frontend** : Composition API + `<script setup lang="ts">`, indentation 4 espaces,
  alias `@` → `src/`, couche `services/` **censée** centraliser tous les appels HTTP — convention
  **non respectée** : 8 vues/composants appellent `fetch()` directement (cf. §2.0), stores Pinia pour
  l'état partagé. Commentaires en français.
- **Nommage BDD** : tables au pluriel en **anglais** (`galleries`, `photos`, `orders`, `clients`…),
  colonnes en `snake_case`. Le métier est en français mais le schéma est anglophone — sauf
  `prestations` et `factures`/`invoices` (à vérifier en §3bis).

## 1.6. Environnements et déploiement

| Environnement | Front | API | Base | Stockage |
|---|---|---|---|---|
| **Local (Docker)** | `frontend` Vite `:5173` | `nginx :8000` → `laravel` (php-fpm) + `queue` + `scheduler` | Supabase distant (ou `postgres-test` `:55432` en profil `test`) | MinIO distant |
| **Local (mails)** | — | Mailpit `:1025` (SMTP) / `:8025` (UI) | — | — |
| **Préprod** | Nginx statique | `preprod-api…:8081` (`deploy/docker-compose.preprod.yml`) | Supabase (projet dédié) | MinIO NAS |
| **Production** | Render (build `npm run build:prerender`) | NAS : `api-nginx` + `api-php` + `api-queue` + `api-scheduler` | Supabase | MinIO NAS (tunnel Cloudflare) |

**Services Docker de dev** (`docker-compose.yml`) : `frontend`, `laravel` (php-fpm), `queue` (worker),
`scheduler` (cron Laravel), `nginx`, `mailpit`, `postgres-test` (profil `test`, données en `tmpfs`).

**Production** (`deploy/docker-compose.prod.yml`) : mêmes rôles, containers `api-php`, `api-queue`,
`api-scheduler`, `api-nginx`. **Le front n'est pas conteneurisé en prod** — il est buildé et servi en
statique par Render.

**CI** (`.github/workflows/ci.yml`, sur `main` et `develop`) : 5 jobs — `frontend-lint` (ESLint) →
`frontend-build` (vue-tsc + vite + prerender + vérification des pages prérendues), `backend-lint` (Pint),
`backend-syntax` (`php -l`), `backend-tests` (PHPUnit sur Postgres 16). **Pas de build d'image Docker**
et **aucune analyse de sécurité** (cf. §6.13).

> ⚠️ **Point d'attention sécurité** : les fichiers `.env` **réels** sont présents sur le disque à
> `./.env`, `api/.env`, `web/.env`, `web/.env.preprod`, `deploy/.env.prod`, `deploy/.env.preprod`.
> Une mémoire projet signale par ailleurs que `api/.env` **local pointe sur la base et le mailer de
> PRODUCTION** — toute exécution de script/artisan en local touche donc les données réelles.
> À traiter en priorité dans la refonte (et vérifier qu'aucun n'est committé).

## 1.7. Documentation interne existante (`docs/`)

Ces documents sont une source précieuse pour la refonte — ils décrivent des décisions déjà arbitrées :

| Fichier | Contenu |
|---|---|
| `docs/README.md` | Index de la documentation |
| `docs/analyse-paiement-sumup.md` | Analyse du flux de paiement SumUp (à exploiter en §4/§7) |
| `docs/audit-supervision.md` | Audit de la couche supervision |
| `docs/supervision.md` | Doc fonctionnelle des sondes/alertes (§8) |
| `docs/deploy-improve-photo-upload.md` | Optimisations d'upload photo (§5) |
| `docs/feature-gift-code-promo.md` | Spécification bons cadeaux / codes promo (§2, §7) |
| `docs/plan-rgpd-admin.md` | Plan du module RGPD admin (§2, §6) |
| `docs/preprod-runbook.md` + `PREPROD.md` | Runbook de préproduction |

## 1.8. ⚠️ Décalage majeur entre le README et le code réel

Le `README.md` racine décrit une stack qui **ne correspond plus au code**. Ne pas s'y fier lors de la
refonte :

| README annonce | Réalité du code |
|---|---|
| Paiements **Stripe & PayPal** | **SumUp uniquement** (`sumup/sumup-ecom-php-sdk`, `config/sumup.php`, `SumUpService`, `SumUpPaymentController`) — aucune trace de Stripe/PayPal |
| Stockage **Supabase Storage** | **MinIO** S3-compatible (disque `minio`, `MinioStorageService`). Supabase ne sert plus que de **PostgreSQL managé** |
| UI **shadcn-vue** | Aucune dépendance UI — composants maison |
| Emails **Mailgun / MailHog** | **Brevo SMTP** en prod, **Mailpit** en local (+ Brevo API pour les SMS) |
| Tests **Vitest** côté Vue | Aucun test front |
| PHP 8.3 | PHP 8.4 dans l'image Docker (`^8.2` en contrainte Composer) |
| Endpoints `/api/payments/stripe/create-intent`, `/api/admin/factures` | À revérifier en §3 — la liste d'endpoints du README est partielle et datée |
| Tables « principales » listées | 38 tables réelles (dont `carts`, `cart_items`, `orders`, `order_items`, `gift_codes`, `school_sessions`, `privacy_*`, `availability_*`…) non mentionnées |

Une **Edge Function Supabase** (`supabase/functions/cleanup-gallery-files/index.ts`, Deno) existe
également — vestige de l'ancienne architecture Supabase Storage. Son usage réel sera vérifié en §4.

---
# 2. Cartographie complète des fonctionnalités

> **Méthode** : lecture statique croisée `routes/api.php` (161 définitions) ↔ controllers ↔ services ↔
> modèles ↔ code front (`web/src`, 84 `.vue` + 45 `.ts`). **Aucune exécution d'`artisan`, de `tinker` ni
> de requête SQL** : `api/.env` local pointe sur la base et le mailer de PRODUCTION (cf. §1.6).
> Les volumétries réelles de données seront traitées en §3bis / §10.
>
> **Convention de statut** utilisée dans tout ce document :
> ✅ **actif** (câblé bout en bout, utilisé en production) —
> 🟡 **partiel** (backend prêt, parcours utilisateur incomplet ou manuel) —
> 🔴 **mort** (code présent, aucun consommateur).

## 2.0. Corrections apportées à la §1

Deux affirmations de la §1 sont démenties par le code :

| §1 affirmait | Réalité vérifiée | Où |
|---|---|---|
| « Laravel Sanctum en mode SPA / cookie de session […] **pas de tokens Bearer côté front** » | **Auth hybride**. `AuthController::login/register` appellent `Auth::attempt()` (session cookie) **ET** `createToken('auth-token')`, et renvoient le token en clair. Le front le stocke en `localStorage` (`baseApi.getToken()` → clé `auth_token`) et l'envoie en `Authorization: Bearer` sur **toutes** les requêtes authentifiées, en plus du cookie de session et de `X-XSRF-TOKEN`. Les deux mécanismes coexistent sur chaque appel. | `api/app/Http/Controllers/Api/AuthController.php:57`, `:73`, `web/src/services/baseApi.ts:84`, `:100` |
| « couche `services/` centralisant tous les appels HTTP (**jamais de `fetch` dans les composants**) » | **Faux** : 8 vues/composants appellent `fetch()` directement en construisant l'URL depuis `API_CONFIG.baseUrl`, en contournant `BaseApiService` (donc sans gestion d'erreur normalisée, sans timeout, sans hook 401). | `GalleryAccess.vue:69`, `ProtectedGallery.vue:301`, `DownloadGallery.vue:202/214/269`, `Events.vue:299`, `EventGallery.vue:379`, `LikeButton.vue:70`, `OrderConfirmation.vue:489/535`, `account/OrderDetail.vue:207`, `admin/SchoolSessionDetail.vue:1114` |

Ces deux points sont repris en §6 (sécurité) et §9 (dette technique).

## 2.1. Carte des domaines fonctionnels

| # | Domaine | Statut | Tables principales | Enjeu migration |
|---|---|---|---|---|
| A | Site vitrine (portfolio, prestations, contact, pages légales) | ✅ | `prestations`, `contact_messages` | Portfolio **hors BDD** (fichiers statiques) |
| B | Galeries privées clients | ✅ | `galleries`, `photos`, `download_logs` | **Cœur du système** — fichiers sur MinIO |
| C | Galeries événements publiques | ✅ | `galleries` (`type=event`), `event_categories` | Hiérarchie parent/enfant à préserver |
| D | Boutique (panier, packs, promo, checkout, SumUp, factures) | ✅ | `carts`, `cart_items`, `orders`, `order_items`, `payments`, `invoices`, `gift_codes` | **Données comptables — rétention légale** |
| E | Espace client | ✅ | `users` | — |
| F | Comptes & authentification | ✅ | `users`, `personal_access_tokens`, `sessions` | Hash de mots de passe à conserver |
| G | Réservations & demandes | ✅ | `reservations`, `clients`, `client_forms` | — |
| H | Disponibilités (créneaux + patterns) | 🔴 | `availability_slots`, `availability_patterns` | **Aucune UI** — candidat abandon |
| I | Bons cadeaux | 🟡 | `gift_cards` | Vente en ligne jamais implémentée |
| J | Photos scolaires + SMS de masse | ✅ | `school_sessions`, `school_session_exports`, `photo_uploads` | **Contacts parents non persistés** |
| K | Back-office admin (13 écrans) | ✅ | — | — |
| L | RGPD (recherche, export, effacement, audit, rétention) | ✅ | `privacy_exports`, `privacy_audit_logs` | Journal d'audit = **preuve de conformité** |
| M | Supervision / monitoring maison | ✅ | `cache` (heartbeats), `jobs`, `failed_jobs` | Régénérable |
| N | Notifications in-app | ✅ | `notifications` | Une seule source (demandes de réservation) |

---

## 2.2. A — Site vitrine public ✅

**Pages** : `/` (Home), `/portfolio`, `/prestations`, `/bons`, `/a-propos`, `/contact`, `/mentions-legales`,
`/cgv`, `/politique-confidentialite`. Toutes pré-rendues au build (Puppeteer) avec `title`/`description`
par route.

**Portfolio — 100 % statique, hors base de données.** C'est un point structurant pour la refonte :

- Les images vivent dans `web/public/optimized/<Catégorie>/` et sont listées par
  `web/src/config/gallery-manifest.json` (généré le **2026-04-23** par `optimize-images.js`).
- 6 catégories ordonnées en dur : `Portraits`, `Sport`, `Animalier`, `Concert`, `Automobile`, `Entreprise`.
- Double format : **AVIF** primaire + **WebP** fallback, avec vignettes 400 px (`thumbs/`) pour la grille et
  full-res pour la lightbox.
- 7 **vidéos YouTube** déclarées en dur dans `web/src/config/gallery.ts` (tableau `youtubeVideos`).
- Les `alt` SEO sont générés depuis un préfixe par catégorie (`categoryAltPrefix`).
- Les descriptions longues de chaque catégorie sont du **texte éditorial en dur** dans
  `web/src/config/constants.ts` (`CATEGORY_DESCRIPTIONS`) — ~250 mots par catégorie.

> ⚠️ **Conséquence migration** : le portfolio public ne contient **aucune donnée à migrer depuis la base**,
> mais il faut récupérer (a) les fichiers image optimisés, (b) le contenu éditorial des descriptions,
> (c) la liste des IDs YouTube. Rien de tout cela n'est administrable : ajouter une photo au portfolio
> exige aujourd'hui un commit + un build. À arbitrer explicitement en §11.

**Prestations** ✅ (dynamiques, administrables) : `GET /prestations`, `GET /prestations/{id}`. Modèle riche —
`title`, `icon`, `description`, `features` (JSON), `price` **ou** `price_text` + `price_unit`, `duration`,
`category`, `background_image`, `background_opacity`, `disclaimer`, `is_active`, `sort_order`.

**Contact** ✅ : `POST /contact` → `ContactMessage` persisté (avec `gdpr_consent`, `gdpr_consent_at`,
`consent_ip`, `consent_user_agent`) → event `ContactMessageSent` → listener `SendContactEmails` →
2 mails (`ContactFormMail` à l'admin, `ContactConfirmationMail` au visiteur), avec traçage
`email_sent_to_admin` / `email_sent_to_user`.

**Autres briques** : `Navbar` + `Footer` + `SocialLinks` (Instagram / TikTok / LinkedIn en dur),
`CookieBanner` (cf. §2.13), `CopyrightNotice`, `CtaSection`, `Lightbox`, `MasonryGallery`.

Constantes métier en dur côté front (`constants.ts`) : email, téléphone `06 11 01 44 77`,
SIRET `993 948 587`, horaires `lundi-samedi 9h-19h`, zone de déplacement gratuite `20 min`. Ces valeurs
sont **dupliquées** avec `api/config/invoice.php` — divergence possible, à unifier dans la refonte.

---

## 2.3. B — Galeries privées clients ✅ (cœur du système)

### Trois chemins d'accès

| Chemin | Endpoint | Usage front |
|---|---|---|
| **Code de partage** (6 car. majuscules) | `GET /galleries/code/{code}` | `/gallery` (saisie du code) puis `/gallery/:code` |
| **Token d'accès** (64 car.) | `GET /galleries/token/{token}` | 🔴 aucun appel front |
| **Galerie téléchargeable** | `GET /galleries/download/{token}` | `/gallery/download/:token` |

Les deux secrets sont générés à la création (`Gallery::boot()`) et **régénérables** depuis l'admin
(`PUT .../regenerate-token`, `POST .../regenerate-code`). Le code évite la collision par boucle
`do…while` sur la table.

### Deux modes de galerie, distincts fonctionnellement

1. **Galerie « boutique »** (`/gallery/:code`) — les photos sont servies **filigranées** :
   `preview` (1200 px + filigrane) et `thumbnail` (400 px + filigrane fort). Le client sélectionne,
   ajoute au panier, paie, puis télécharge les HD achetées.
2. **Galerie « téléchargement »** (`/gallery/download/:token`) — les photos marquées
   `is_downloadable` sont servies **sans filigrane** (`thumbnail_clean`), téléchargeables une par une
   ou en **ZIP streamé** (`zipstream-php`, `GET /galleries/{id}/download-zip?token=…`).

Le même enregistrement `galleries` sert les deux modes ; c'est le flag `photos.is_downloadable` qui
décide, photo par photo.

### Pipeline de dérivés d'image

`photos` porte **7 colonnes de chemin** : `file_path`, `file_path_web`, `file_path_hd`,
`file_path_watermark`, `file_path_preview`, `file_path_thumbnail`, `file_path_thumbnail_clean`.
La résolution du « chemin original » suit une **chaîne de fallback** héritée de migrations successives
(`Photo::getResolvedStoragePathAttribute()`) :

```
file_path_hd  →  metadata['storage_path']  →  metadata['supabase_path']  →  file_path
```

> ⚠️ La présence de `metadata['supabase_path']` prouve qu'il **reste des photos issues de l'ancien
> stockage Supabase** dont le chemin n'a jamais été normalisé. À quantifier impérativement en §5/§10 :
> c'est une source de casse silencieuse lors de la migration des fichiers.

**Deux modes de service des images**, commutables sans redéploiement via `SHOP_SERVE_IMAGES_DIRECT` :

- **direct** (défaut) : URL signée MinIO valable 24 h, le navigateur tire les octets depuis MinIO ;
- **proxy** : `GET /api/images/{preview|thumbnail|clean|clean-thumb}/{photo}` → génération à la volée
  + streaming par PHP-FPM (rate-limité par `throttle:images` / `throttle:downloads`).

Un commentaire du code documente le compromis assumé : pas de cache des URL signées (un cache fichier
ajoutait ~19 s de latence sur une galerie de 200 photos via le NAS), donc **l'URL change à chaque
chargement** → aucun cache navigateur entre deux visites.

### Diffusion de l'accès au client

- **Email** : `POST /admin/galleries/{id}/send-email` → `GalleryAccessMail`.
- **SMS** : `POST /admin/galleries/{id}/send-sms` → Brevo. Le contenu est bâti par `SmsTemplateService`
  à partir d'un gabarit par galerie (`galleries.sms_template`) avec placeholders `{nom}`, `{lien}`,
  `{code}`, **accents systématiquement retirés** pour rester en GSM-7 (1 segment ≤ 160 car.).
- `BrevoSmsService::normalizePhone()` normalise les numéros FR (`06…`, `+33…`, `0033…`) → `+33…`.

### Statistiques et traçabilité

`galleries.views_count` / `last_viewed_at` (via `recordView()`), `photos.downloads_count`, et une table
`download_logs` (`photo_id`, `gallery_id`, `ip_address`, `user_agent`, `downloaded_at`, **sans
timestamps Eloquent**). Attributs calculés exposés à l'admin : `downloadable_photos_count`,
`liked_photos_count`, `total_downloads_count`, `downloaded_photos_count`, et un `download_status`
tri-état (`none` / `partial` / `complete`).

**Likes** : `POST /photos/{photo}/like` — booléen `is_liked` **global sur la photo**, pas par
utilisateur. Sert au tri admin (« photos aimées par la cliente »), pas à un usage social.

---

## 2.4. C — Galeries événements publiques ✅

Même table `galleries` avec `type = 'event'`, plus :

- `event_category_id` → `event_categories` (nom, slug, description, `sort_order`, réordonnables via
  `PUT /admin/event-categories/reorder`) ;
- **hiérarchie à deux niveaux** : `parent_id` + `children()`, exposée par
  `GET /admin/events/{gallery}/children` — une galerie événement peut contenir des sous-galeries ;
- `is_published` (brouillon / publié), `sort_order`, `event_date`, `event_link`,
  `thumbnail_photo_id` (couverture choisie, sinon première photo) ;
- mise en cache invalidée par le trait `ClearsEventGalleriesCache`.

Public : `GET /events` (paginé), `GET /events/{gallery}` → `/evenements`, `/evenements/:id`.

---

## 2.5. D — Boutique ✅ (le domaine le plus riche)

### Catalogue : 4 types de produits

Définis **en constante PHP** (`CartItem::PRODUCT_TYPES`), pas en base :

| `product_type` | Libellé | Prix par défaut | Tirage ? | Frais de port ? |
|---|---|---|---|---|
| `digital` | Fichier numérique | 13,00 € | non | non |
| `print_10x15` | Tirage 10x15 cm | 10,00 € | oui | **oui** |
| `print_15x20` | Tirage 15x20 cm | 15,00 € | oui | **oui** |
| `print_scolaire` | Tirage scolaire | 6,00 € | oui | **non** (remis à l'école) |

### Tarification à trois niveaux

1. **Défaut global** — la constante ci-dessus.
2. **Override par galerie** — table `gallery_product_types` (`is_enabled`, `price`,
   `requires_shipping` nullable = « hériter »). Règle importante : si une galerie a **au moins une**
   ligne configurée, tous les types non listés deviennent **désactivés** (`PricingService` ligne par
   ligne) — le comportement « tout activé » ne vaut que pour une galerie sans configuration.
3. **Paliers de quantité (packs)** — table `pack_tiers` (`min_quantity` ≥ 2, `unit_price`), **max 3
   paliers** par type. Le prix unitaire retenu est celui du plus grand palier atteint.

**Cumul cross-galerie des packs** — mécanisme non trivial à préserver : `GalleryProductType::offerSignature()`
calcule un `md5(product_type + prix effectif + paliers)`. Deux galeries différentes proposant la **même
offre** voient leurs quantités **cumulées** pour le calcul du palier (`CartService::buildPackGroups()` /
`recalculatePackPrices()`). Un client achetant 3 tirages dans 2 galeries d'un même événement bénéficie donc
du palier « 4+ ».

### Panier

Table `carts` (`user_id` **ou** `session_id` pour les invités, `guest_email`, `gift_code_id`, `status`,
`expires_at`) + `cart_items` (`photo_id`, `product_type`, `quantity`, `price`).

- **Invariant explicite** : `CartService::getOrCreateCart()` garantit **exactement un panier actif par
  utilisateur** — il fusionne le panier invité correspondant au `session_id` et expire les doublons.
  Le commentaire du code indique que sans cela le client pouvait avoir 2-3 paniers en parallèle et
  **payer un montant différent de celui affiché**.
- **Fusion à la connexion** : `POST /cart/merge` (`mergeGuestCart`).
- Endpoints : `GET /cart`, `POST /cart/add`, `PUT /cart/item/{item}/type`, `PUT .../quantity`,
  `DELETE /cart/item/{item}`, `DELETE /cart/clear`, `PUT /cart/email`.
- Expiration : 7 jours (`status → expired` par le scheduler quotidien 03:00).

### Codes promo (`gift_codes`) — à ne pas confondre avec les bons cadeaux

`POST /cart/gift-code` (rate-limité `throttle:gift-code`), `DELETE /cart/gift-code`.

- Deux types : `percent` (avec plafond optionnel `max_discount_amount`) et montant fixe ;
- fenêtre de validité `valid_from` / `valid_until`, `max_uses`, `is_active`, `note` ;
- **règle de quota** documentée dans le modèle : seules les commandes **payées** consomment `max_uses` —
  un panier abandonné ne « brûle » jamais un code (`paidCount()` vs `pendingCount()` informatif) ;
- alphabet sans caractères ambigus (`O`, `0`, `I`, `1`) car les codes sont saisis à la main ;
- **auto-nettoyage** : un code devenu invalide est retiré silencieusement du panier
  (`CartService`, ligne ~373) ;
- vérification finale **dans la transaction de checkout** sur un modèle verrouillé
  (`GiftCodeService::assertUsableForCheckout()` + `lockForUpdate`).

### Frais de port

Facturés **une seule fois** par commande dès qu'elle contient au moins un tirage éligible.
Montant : `galleries.shipping_fee` si défini, sinon `config('shop.shipping_fee_print')` = **2,00 €**.
`print_scolaire` est exempté par règle statique, surchargeable par galerie via
`gallery_product_types.requires_shipping`.

### Checkout et paiement SumUp

Parcours : `/panier` → `/checkout` → widget SumUp → `/commande/:id`.

- `POST /checkout` (`OrderService::createFromCart`) : invité **ou** connecté ; collecte
  `guest_email`, `guest_first_name/last_name`, adresse de livraison si tirages, et **acceptation des
  CGV horodatée** (`cgv_accepted`, `cgv_accepted_at`, `cgv_version`, `consent_ip`).
- `POST /checkout/confirm-free` : commande à 0 € (code promo couvrant tout) — le code
  **refuse tout total > 0** pour ne jamais court-circuiter un vrai paiement.
- Sécurité anti-rejeu : à toute évolution du panier, les checkouts SumUp antérieurs sont **désactivés**
  et les commandes passées en `expired` (`OrderService`, ligne ~164) — sinon un ancien widget resterait
  payable.
- **Callback SumUp** — point d'architecture délicat, déjà documenté dans `config/sumup.php` :
  SumUp utilise **une seule URL** (`return_url`) pour deux usages, `GET` = redirection navigateur après
  3DS, `POST` = webhook `CHECKOUT_STATUS_CHANGED`. L'URL pointe donc vers **le backend Laravel** et non
  la SPA statique (qui « avalait » le POST en renvoyant 200/HTML, laissant les commandes en `pending`).
  Le webhook n'est **pas signé** → rate-limit `throttle:sumup-webhook` (60/min/IP).
- **Réconciliation périodique** (`OrderService::reconcilePendingOrders`, toutes les 10 min) : filet de
  sécurité pour les webhooks définitivement perdus (retries SumUp à 1 min / 5 min / 20 min / 2 h).
  Fenêtre : commandes `pending` avec `sumup_checkout_id` créées entre 15 min et 24 h.
- `POST /payments/sumup/verify`, `POST /payments/sumup/cancel-checkout` (appelés par le front).
- Statuts `orders.status` : `pending`, `paid`, `failed`, `expired`.
  **Statut détaillé calculé** pour l'admin (`detailed_status`) : `payment_failed`, `shipped`, `to_ship`,
  `paid`, `checkout_initiated`, `payment_in_progress` (< 1 h), `payment_abandoned`.

### Numérotation et facturation

- `orders.order_number` : `OT-YYYY-NNNNN`, séquence annuelle, protégée par
  `pg_advisory_xact_lock(42)`.
- `invoices.invoice_number` : `F-YYYY-NNNNN`, protégée par `pg_advisory_xact_lock(43)`.
- PDF via `barryvdh/laravel-dompdf` (`resources/views/pdf/invoice.blade.php`), stocké sur MinIO,
  génération **idempotente** (`InvoiceService::generateForOrder`).
- Mentions légales de la facture en dur dans `api/config/invoice.php` (SIRET, `Lorette, 42420`,
  `Micro-entrepreneur`).
- La facture est **attachée à l'email** de confirmation (`OrderConfirmationMail::attachments()`,
  `Attachment::fromStorageDisk('minio', …)`).

> 🔴 Les deux endpoints de téléchargement de facture (`GET /orders/{order}/invoice` et
> `GET /admin/orders/{order}/invoice`) **ne sont appelés par aucun code front**. Aujourd'hui la facture
> ne parvient au client **que** par pièce jointe email, et la photographe n'a aucun bouton pour la
> récupérer depuis le back-office. Écart fonctionnel à corriger dans la refonte.

### Livraison des tirages

`orders.print_status` + `shipped_at`, marqué par `PUT /admin/orders/{order}/ship`.
Un mail `PrintOrderNotificationMail` prévient l'admin qu'une commande contient des tirages à expédier.
`Order` expose `hasPrintItems()`, `isFullyDigital()`, `printItems()`, `digitalItems()`.

### Livraison des fichiers numériques

Token de téléchargement stocké **dans `orders.metadata`** (JSON) avec expiration **7 jours**
(`generateDownloadToken()` / `isDownloadTokenValid()`). Endpoints : `GET /orders/{order}/download/{item}`,
`GET /orders/{order}/download-all`. Chaque `order_items` trace `is_downloaded` / `downloaded_at`.
Les `order_items` **dénormalisent** `photo_title` et `gallery_title` — la facture reste lisible même si la
photo est supprimée. Bon réflexe à conserver.

### Retrouver une commande sans compte

`POST /orders/by-email` — un invité retrouve ses commandes par email seul (pas de vérification par lien
signé). À examiner en §6.

### Analytics e-commerce

`useGtag()` envoie les événements GA4 (`add_to_cart`, `remove_from_cart`, …) **uniquement** si le
consentement `analytics` est accordé.

---

## 2.6. E — Espace client ✅ (volontairement minimal)

`/mon-compte` (`requiresClientAuth`) et `/mon-compte/commande/:id`. Un **seul** endpoint :
`GET /account/dashboard`, qui agrège galeries + réservations + commandes.
Composants dédiés : `GalleryCard`, `ReservationCard`.

> 🔴 Les endpoints `GET /my-galleries` et `GET /my-galleries/{gallery}/download` ne sont **jamais
> appelés** : le tableau de bord fournit déjà les galeries. Doublon à supprimer.

**Rattachement automatique de l'historique** — comportement clé pour la migration
(`AuthController::register`) : à l'inscription, les `galleries.assigned_email`, `orders.guest_email` et
`reservations.guest_email` correspondant à l'email et **sans `user_id`** sont réassignés au nouvel
utilisateur. Il existe donc en base deux façons de rattacher une ressource à une personne : par
`user_id`, ou par email « en attente ». **Tout script de migration doit gérer les deux.**

---

## 2.7. F — Comptes et authentification ✅

- `POST /auth/register` (rôle `client` forcé), `POST /auth/login`, `POST /auth/logout`,
  `GET /auth/user`, `PUT /auth/profile`.
- **Deux rôles seulement** : `users.role` ∈ `admin` / `client`. Pas de table de rôles, pas de
  permissions fines. `EnsureUserIsAdmin` (alias `admin`) garde tout le préfixe `/admin`.
- 3 Policies : `Gallery`, `Order`, `Reservation`.
- `UserObserver` + `RegisterRequest` tracent le consentement RGPD (`gdpr_consent_verified`,
  `consent_ip`).
- `users` porte aussi une adresse postale (`address_line1/2`, `postal_code`, `city`) réutilisée en
  livraison.

> 🔴 **Réinitialisation de mot de passe non finie** : `POST /auth/forgot-password` et
> `POST /auth/reset-password` existent côté API mais **aucune UI ne les appelle** (aucune occurrence de
> `forgot-password` ni de « mot de passe oublié » dans `web/src`). Un client qui perd son mot de passe
> est aujourd'hui bloqué. À traiter en priorité dans la refonte.

> 🔴 `PUT /auth/profile` : aucun appel front non plus.

---

## 2.8. G/H — Réservations, clients, disponibilités

### Demande de réservation publique ✅

`POST /booking-request` (sans authentification) ← `BookingRequestModal.vue`.
Champs : nom, email, téléphone, `prestation_id`, `date_preferences` (**texte libre**, pas un créneau),
message, consentement RGPD. Déclenche l'event `BookingRequested` → listener `SendBookingNotifications`
→ `NewReservationRequestMail`.

### Gestion admin ✅

`GET /admin/reservations`, `GET /admin/reservations/calendar` (→ `CalendarEvent` Resource, affiché par
`components/admin/ui/Calendar.vue`), `GET|PUT|DELETE /admin/reservations/{id}`,
`PUT /admin/reservations/{id}/status`.
Statuts : `pending`, `confirmed`, `cancelled`, `completed`.

`reservations` gère **invités et comptes** de front : `user_id` **ou** (`guest_name`, `guest_email`,
`guest_phone`), avec des accesseurs à cascade (`client_name`, `client_email`, `client_phone`) qui
tentent `user` → `clientForm` → `guest_*`. **Attention** : ces accesseurs dépendent de
`relationLoaded()` — ils renvoient les valeurs `guest_*` si la relation n'est pas eager-loadée, ce qui
peut produire des affichages différents selon l'appelant. À signaler en §9.

### Fiches clients ✅

`apiResource /admin/clients` + `GET /admin/clients/{client}/reservations` +
`POST /admin/clients/{client}/gdpr-export`.
`clients.source` ∈ `manual` / `reservation` / `contact`. Attributs calculés : `total_paid`
(somme des `payments` `completed` des réservations), `reservations_count`, `galleries_count`.

> ⚠️ Modélisation à revoir : `clients.galleries()` est un `HasManyThrough` **via `users`**, et
> `Gallery::getClientIdAttribute()` fait une requête `Client::where('user_id', …)` à chaque accès.
> Un client sans compte utilisateur n'a donc **aucune** galerie visible depuis sa fiche. La relation
> client ↔ galerie passe par un détour `users` au lieu d'être directe.

`client_forms` (`fullname`, `phone`, `requirements`, `message`) est identifié dans les types front
comme « anciennes reservations » (`types/admin.ts:89`) — **vestige** à confirmer en §3bis.

### 🔴 H — Module « Disponibilités » : mort

Le backend est complet — **2 tables**, **12 endpoints**, un modèle avec génération de créneaux :

- `availability_slots` : `date`, `start_time`, `end_time`, `duration_minutes`, `status`
  (`available`/`booked`), `reservation_id`, `notes` ;
- `availability_patterns` : `days_of_week` (JSON), plage horaire, `slot_duration_minutes`,
  `repeat_every_weeks`, `start_date`/`end_date`, `is_active`, avec
  `generateSlots(Carbon $untilDate)` qui matérialise les créneaux en évitant les doublons ;
- endpoints CRUD slots + CRUD patterns + `POST /admin/availability/patterns/{id}/generate` +
  `PUT .../toggle` + `GET /admin/availability/slots/available` ;
- `reservations.availability_slot_id` existe et `AvailabilitySlot::markAsBooked()` est implémenté.

**Aucune occurrence de `availability` dans tout `web/src`.** Il n'existe ni écran d'administration des
créneaux, ni sélection de créneau côté client : la demande publique se fait en **texte libre**
(`date_preferences`). C'est le plus gros bloc de code mort du projet.

> **Verdict proposé** : soit on implémente enfin la prise de rendez-vous sur créneaux (le backend est
> prêt, il manque l'UI), soit on abandonne les 2 tables + 12 endpoints. À trancher en §11 — c'est une
> décision produit, pas technique.

### 🔴 CRUD réservations côté client authentifié : mort

`GET|POST|PUT|DELETE /reservations` et `GET /reservations/{id}` (5 endpoints, `auth:sanctum`) : aucun
appel front. Le client passe par `/booking-request` (public) et voit ses réservations en lecture seule
via `/account/dashboard`.

---

## 2.9. I — Bons cadeaux 🟡 (vente en ligne jamais implémentée)

**Ce qui existe côté client** : `/bons` est une page **purement informative**. Les conditions annoncées
(montant libre ≥ 35 €, validité 1 an, utilisable en plusieurs fois, carte personnalisée) invitent
explicitement à **contacter la photographe par mail ou Instagram** — il n'y a **aucun tunnel d'achat**.

**Ce qui existe côté API** : table `gift_cards` complète (`code` 12 car., `amount`,
`remaining_amount`, `recipient_name`, `recipient_email`, `message`, `pdf_path`, `status`, `expires_at`,
`used_at`, `payment_id`), avec débit partiel (`use(float $amount)`) et scope `valid()`.

| Endpoint | Statut |
|---|---|
| `GET /admin/gift-cards`, `PUT /admin/gift-cards/{id}` | ✅ utilisés par `admin/GiftCards.vue` (liste, stats actives/utilisées/expirées, alerte « expire bientôt ») |
| `POST /gift-cards` (création, `auth:sanctum`) | 🔴 aucun appel front |
| `GET /gift-cards/{giftCard}` | 🔴 aucun appel front |
| `GET /gift-cards/validate/{code}` | 🔴 aucun appel front |
| `GET /gift-cards` (public, dans `services/api.ts:49`) | 🔴 **appelle une route qui n'existe pas** — l'index n'est déclaré que sous `/admin`. Méthode morte pointant vers un 404. |

Autres incohérences : `pdf_path` n'est **jamais alimenté** (aucun générateur de PDF de bon cadeau),
et un bon cadeau ne peut pas être utilisé pour payer une commande boutique (le panier n'accepte que
les `gift_codes`, jamais les `gift_cards`).

> **Verdict proposé** : en l'état, `gift_cards` est un **registre manuel** que la photographe remplit
> depuis l'admin après une vente conclue par mail. C'est peut-être exactement le besoin. À confirmer avec
> elle avant de développer un tunnel d'achat qui n'a jamais été demandé — mais la page publique promet
> « envoi par mail sous 24 h », ce qui suppose un processus manuel aujourd'hui non outillé.

---

## 2.10. J — Photos scolaires ✅ (le module le plus complexe)

Parcours complet, piloté depuis `/admin/school-sessions` et `/admin/school-sessions/:id`.

### 1. Création et import

`POST /admin/school-sessions` (titre, date, `product_types_config` JSON, `gallery_message`,
`sms_template`), puis `PUT /admin/school-sessions/{id}/upload` — upload d'**un ZIP** contenant toute la
session.

### 2. Extraction et création automatique des galeries

`POST /admin/school-sessions/{id}/process` → `ProcessSchoolSessionJob` (`tries=1`, `timeout=3600`) :

- `SchoolSessionService::extractAndParseZip()` déballe le ZIP et **déduit l'arborescence**
  `classe/enfant` ; `resolveRootDirectory()` gère le cas fréquent du ZIP à dossier racine unique ;
- les **photos communes à une classe** (racine du dossier classe) sont traitées **une seule fois**
  (`processSharedPhotos()`) puis rattachées à chaque galerie enfant — économie de traitement et de
  stockage significative ;
- une **galerie par enfant** est créée (`createChildGallery()`) avec `school_session_id`, `class_name`,
  code de partage propre, et les `product_types` issus de `product_types_config` ;
- chaque portrait part en `ProcessPhotoJob` (`tries=3`, `backoff` 10/30/60 s).

Statuts de session : `uploading` → `extracting` → `creating_galleries` → `processing_photos` →
`completed` / `failed`. Progression **live** calculée depuis le batch `photo_uploads`
(`PhotoUpload::getBatchStatus()`, agrégats SQL `COUNT(*) FILTER` — volontairement sans hydratation, la
liste complète étant désactivable pour les batchs de 1000+ items).

Reprise sur erreur : `POST /admin/school-sessions/{id}/retry-failed-photos`.

### 3. Diffusion aux parents — ⚠️ point critique pour la migration

`POST /admin/school-sessions/{id}/send-messages` avec `channel` ∈ `email` / `sms` et un tableau
`contacts[]` (`gallery_id`, `recipient_name`, `email`, `phone`).

Côté front (`SchoolSessionDetail.vue`) les contacts sont saisis par **import CSV** au format
`nom;email;telephone`, ou un par un manuellement. Ils vivent dans un `ref([])` Vue, sont validés
(`isContactValid`), envoyés, puis **perdus** : la liste est vidée après envoi.

> 🔴 **Aucune table ne stocke les contacts des parents.** Ni `school_session_contacts`, ni colonne
> dédiée sur `galleries` (seul `assigned_email` existe, alimenté ailleurs). Conséquences :
> 1. **Migration** : il n'y a **rien à migrer** côté contacts parents — c'est une bonne nouvelle RGPD,
>    mais cela signifie qu'aucun historique de diffusion n'est reconstituable.
> 2. **Fonctionnel** : impossible de savoir qui a été prévenu, ni de relancer les non-ouvreurs, ni de
>    réenvoyer un lien à un parent sans réimporter le CSV. La photographe doit **conserver le CSV
>    elle-même**, hors application.
> 3. À arbitrer explicitement en §11 (persister les contacts = nouveau traitement RGPD à déclarer).

Envoi : email → `GalleryAccessMail` en file ; SMS → accumulés puis **un seul** `DispatchSmsBatchJob`
(`timeout=300`, `tries=1`) qui espace les envois par délai progressif pour rester sous les limites de
débit Brevo, chaque SMS partant ensuite en `SendSchoolSessionSmsJob` (`tries=3`, backoff 10/30/60 s).
Un export CSV des liens par galerie est aussi proposé (`{session}_liens.csv`).

### 4. Commandes des parents

Les parents commandent depuis la galerie de leur enfant, avec le type `print_scolaire` (6 €, **sans
frais de port** — remise à l'école). `GET /admin/school-sessions/{id}/orders` agrège les commandes de la
session. Mail dédié : `SchoolOrderConfirmationMail` (avec facture en pièce jointe).

### 5. Clôture et export de production

- `POST .../close` / `POST .../reopen` (`closed_at`) — ferme les commandes de la session.
- `POST .../exports` → `GenerateSchoolSessionExportJob` (`timeout=3600`) → ZIP des photos **achetées**,
  organisées `classe/enfant`, avec un `_index.csv` récapitulatif ; option `include_digital`.
  Suivi par `school_session_exports` (statut, `total_items`/`processed_items`, `file_size_bytes`).
  Téléchargement : `GET /admin/school-session-exports/{export}/download`.
- `DELETE /admin/school-sessions/{id}` : `SchoolSessionService::deleteSessionFiles()` **doit** tourner
  avant le cascade delete SQL (les fichiers MinIO ne sont pas couverts par les FK).

---

## 2.11. K — Back-office administrateur ✅

13 routes sous le layout `AdminRoot` (`requiresAdmin`), sidebar `AdminSidebar`, en-tête `AdminHeader`
avec cloche de notifications.

| Écran | Route front | Couverture API |
|---|---|---|
| Tableau de bord | `/admin` | `GET /admin/dashboard`, `/admin/dashboard/stats`, agrège aussi bons cadeaux |
| Galeries clients | `/admin/galleries` | CRUD galeries, upload chunké, envoi email/SMS, régénération code/token |
| Galeries événements | `/admin/events` | CRUD events, catégories, réordonnancement, couverture, sous-galeries |
| Clients | `/admin/clients` | CRUD clients, réservations, export RGPD |
| Prestations | `/admin/prestations` | CRUD + `toggle` |
| Réservations | `/admin/reservations` | Liste, calendrier, statut, suppression |
| Commandes | `/admin/orders` | Liste, détail, lien de téléchargement, relance paiement, expédition, suppression |
| Bons cadeaux | `/admin/gift-cards` | Liste + édition |
| Codes promo | `/admin/promo-codes` | CRUD `gift-codes` + `toggle` + génération de code |
| Sessions scolaires | `/admin/school-sessions` (+ `/:id`) | Cycle complet (cf. §2.10) |
| RGPD | `/admin/privacy` | Recherche, audit, exports, effacement |
| Logs | `/admin/logs` | Lecture + téléchargement du `laravel.log` |
| — | — | 🔴 `apiResource /admin/users` (5 endpoints) : **aucun écran** — la gestion des comptes passe par `clients` |

**Upload de photos** — chaîne réelle : `useChunkedUpload` → `services/upload/chunkUploader.ts` →
`POST /admin/{galleries|events}/{id}/photos/async`, avec `GET /admin/upload-status` en polling.
Paramètres (`constants.ts` → `UPLOAD_CONFIG`) : lots de 10 fichiers, 30 Mo max par lot, 3 lots en
parallèle, 50 Mo max par fichier, timeout 5 min, 2 retries, polling 3 s.

> 🔴 **Code front mort et cassé** : `services/admin/baseAdmin.ts:42` et `:46`
> (`uploadPhotos` / `uploadEventPhotos`, exposés via `adminApi`) pointent vers
> `/admin/galleries/{id}/photos` et `/admin/events/{id}/photos` — **sans le suffixe `/async`**, donc
> vers des routes inexistantes. Ces méthodes ne sont appelées par personne (`PhotosManager.vue` a sa
> propre fonction locale du même nom). À supprimer.

> 🔴 **Endpoints admin sans consommateur** : `PUT /admin/photos/bulk-downloadable` et
> `PUT /admin/photos/sort-order` ne sont jamais appelés — `PhotosManager.vue:686` (`bulkSetDownloadable`)
> **boucle sur le toggle unitaire**, soit N requêtes HTTP là où l'endpoint bulk existe déjà.
> Également morts : `POST /admin/notifications`, `GET /admin/health`.

---

## 2.12. L — RGPD ✅ (module abouti)

Écran `/admin/privacy`, adossé à 4 services dans `app/Services/Privacy/`.

**Résolution de la personne concernée** — `PersonalDataLocator` prend une clé
(`email` | `phone` | `order_number`) et retrouve **toutes** les lignes rattachées en propageant les
ancres (emails, téléphones, `user_id`, `client_id`, `order_id`, `gallery_id`, `reservation_id`) à travers
`users`, `clients`, `orders`, `reservations`, `galleries`, `carts`, `contact_messages`, `gift_cards`,
`client_forms`. Il ne modifie jamais rien et sert les trois usages (consultation, export, effacement).

**Droit d'accès (art. 15)** — deux exports asynchrones (`GeneratePrivacyExportJob`, `timeout=3600`) :

- `POST /admin/privacy/export-all` : **toutes** les tables métier (les tables d'infrastructure —
  `sessions`, tokens, `cache`, `jobs` — sont volontairement exclues) ;
- `POST /admin/privacy/export-subject` : les données d'**une** personne.

Le résultat est un ZIP (un JSON par table + les **PDF de factures**) stocké sur le disque `local`,
suivi par `privacy_exports` (`type`, `subject_type`, `subject_value`, statut, progression,
`file_size_bytes`, `requested_by`).

**Droit à l'effacement (art. 17)** — `PersonalDataEraser` applique une **carte de traitement explicite**,
avec un aperçu avant action (`GET /admin/privacy/erasure/preview`) :

| Traitement | Cibles | Motif |
|---|---|---|
| **CONSERVER** | factures, paiements, commandes | obligation comptable (~10 ans) |
| **ANONYMISER** | `users` (compte), `gift_cards` (destinataire), `galleries.assigned_email` | garder l'objet, retirer l'identité |
| **SUPPRIMER** | `clients`, réservations, `client_forms`, paniers, messages de contact, `download_logs`, `photo_uploads`, notifications, sessions/tokens | aucune base légale de conservation |

Chaque opération est **gardée** : sans ancre pertinente, elle est ignorée — jamais de « suppression de
toute la table ». L'effacement tourne dans une transaction et renvoie les compteurs réels.

**Journal d'audit** — `privacy_audit_logs` (`actor_user_id`, `action`, `subject_type`, `subject_value`,
`affected` JSON, `ip_address`) alimenté par `PrivacyAuditLogger` sur **chaque** recherche / export /
effacement. C'est la preuve de conformité : `GET /admin/privacy/audit`.

**Rétention automatique** (`routes/console.php`) :

| Fréquence | Action |
|---|---|
| Quotidien 03:00 | paniers actifs > 7 j → `expired` ; commandes `pending` > 24 h → `expired` |
| Hebdo dimanche 04:00 | suppression paniers `expired` > 30 j ; **anonymisation** `guest_*` des commandes `expired`/`failed` > 6 mois ; suppression `contact_messages` > 12 mois ; suppression `download_logs` > 12 mois ; purge des tokens Sanctum (> 6 mois d'inactivité, ou > 3 mois si jamais utilisés) |
| Mensuel le 1er 05:00 | `privacy:purge-expired --years=10` — purge des données comptables au-delà de la rétention légale |

**Consentements tracés** : `contact_messages` (`gdpr_consent`, `gdpr_consent_at`, `consent_ip`,
`consent_user_agent`), `clients` (`gdpr_consent`, `gdpr_consent_at`), `orders`
(`cgv_accepted`, `cgv_accepted_at`, `cgv_version`, `consent_ip`), inscription utilisateur (via
`UserObserver`).

**Cookies / Consent Mode v2** (`web/src/stores/consent.ts`) : GA4 (`G-9BK5CTP2YR`) est chargé
immédiatement mais en `denied` par défaut sur les 4 signaux (`analytics_storage`, `ad_storage`,
`ad_user_data`, `ad_personalization`), `anonymize_ip: true`, `send_page_view: false`. Le refus déclenche
la **suppression active** des cookies de tracking (`_ga*`, `_gid`, `_gcl_au`, `_fbp`, `_fbc`, `__utm*`)
sur trois variantes de domaine dont `.oceanetorresphotographie.fr` en dur. Préférences versionnées
(`consentVersion: '1.0'`) en `localStorage` — un changement de version réaffiche le bandeau.

---

## 2.13. M — Supervision maison ✅

Couche complète, documentée dans `docs/supervision.md` et `docs/audit-supervision.md`.

**4 sondes** (`app/Services/Supervision/Probes/`) : `DatabaseProbe`, `StorageProbe` (MinIO, avec un
**objet témoin** dont la disparition est une alerte distincte), `QueueProbe`, `SchedulerProbe`.

**Endpoints de santé, 3 niveaux d'exposition** (tous sous `throttle:health`) :

| Endpoint | Protection |
|---|---|
| `GET /`, `GET /health`, `GET /health/live` | public |
| `GET /health/details`, `GET /health/database` | middleware `health.token` (`HEALTH_CHECK_TOKEN`) |
| `GET /admin/health` | `auth:sanctum` + `admin` — 🔴 non appelé par le front |

Une route `/health/tables` a été **supprimée** : elle publiait le schéma de la base (commentaire dans
`routes/api.php`).

**Tâches planifiées** : `supervision:heartbeat scheduler` (5 min), `supervision:alert` (15 min),
`supervision:report` (quotidien 08:00). Le heartbeat passe volontairement par `Schedule::command`
(process forké) et non `Schedule::call`, afin de **prouver que le fork de tâches fonctionne** — mécanisme
dont dépendent `privacy:purge-expired` et la réconciliation des paiements.

**Alertes** : `SupervisionAlertMail` / `SupervisionReportMail`, avec cooldown (60 min par défaut) et un
**catalogue de 14 motifs** (`AlertCatalog`) où chaque motif porte un libellé **et une procédure de
remédiation** rédigée (commandes `docker` exactes à lancer). C'est de la vraie ingénierie
d'exploitation, rare à ce niveau de projet.

**Seuils** (`config/supervision.php`) : DB lente 1000 ms, connexion lente 2500 ms, profondeur de file
100, plus vieux job en attente 15 min, worker silencieux 10 min, scheduler silencieux 120 min.

**Pings externes** : `pingOnSuccessIf()` vers healthchecks.io pour `rgpd_cleanup` et
`reconcile_orders` (« dead man's switch »).

**Sentry** back (`sentry/sentry-laravel`) et front (`@sentry/vue`, import dynamique si DSN vide).

**Visionneuse de logs** : `/admin/logs` → `GET /admin/logs` (filtrable), `GET /admin/logs/download`,
`DELETE /admin/logs` — lit le `laravel.log`.

---

## 2.14. N — Notifications in-app ✅ (mono-source)

Table `notifications` (`user_id`, `type`, `title`, `message`, `data` JSON, `read_at`) — implémentation
**maison**, pas les notifications Laravel.

**Une seule source de production** : `SendBookingNotifications` (listener de `BookingRequested`) crée une
notification `type = 'new_reservation'` **par administrateur** à chaque demande de réservation publique,
avec `data = {reservation_id, client_name, prestation}`. La création est encapsulée dans un `try/catch`
qui log sans faire échouer la demande.

**Consommation** : `AdminHeader.vue` (cloche + compteur de non-lus) via `GET /notifications`,
`PUT /notifications/{id}/read`, `PUT /notifications/read-all`.

🔴 Seul `POST /admin/notifications` (création manuelle) est mort — aucun appelant.
Aucun autre domaine ne produit de notification : ni commande payée, ni tirage à expédier, ni fin de
traitement de session scolaire. C'est un canal **sous-exploité** plutôt qu'inutilisé.

---

## 2.15. Emails et SMS — inventaire complet

| Mailable | Déclencheur | Destinataire | Pièce jointe |
|---|---|---|---|
| `ContactFormMail` | `POST /contact` (listener) | admin | — |
| `ContactConfirmationMail` | idem | visiteur | — |
| `NewReservationRequestMail` | `POST /booking-request` (listener) | admin | — |
| `GalleryAccessMail` | envoi manuel admin, ou diffusion session scolaire | client / parent | — |
| `OrderConfirmationMail` | commande payée | client | **facture PDF** (MinIO) |
| `SchoolOrderConfirmationMail` | commande scolaire payée | parent | **facture PDF** (MinIO) |
| `PrintOrderNotificationMail` | commande contenant des tirages | admin | — |
| `SupervisionAlertMail` | `supervision:alert` | `SUPERVISION_ALERT_EMAIL` | — |
| `SupervisionReportMail` | `supervision:report` (08:00) | idem | — |

**SMS** (Brevo API) : accès galerie unitaire (`send-sms`) et diffusion de masse scolaire
(`DispatchSmsBatchJob` → `SendSchoolSessionSmsJob`). Gabarits avec `{nom}` / `{lien}` / `{code}`,
accents retirés (GSM-7), validation par `SmsTemplateRule`.

**Transport** : Brevo SMTP en production, Mailpit en local. Tous les envois métier passent par
`Mail::queue()` (donc dépendants du worker).

---

## 2.16. Synthèse : conserver / abandonner / trancher

| Domaine | Verdict proposé | Justification |
|---|---|---|
| Galeries privées + pipeline d'images | **Conserver** (à réécrire) | Cœur du métier, complexité justifiée |
| Boutique + SumUp + facturation | **Conserver** | Fonctionne, données comptables, callback SumUp durement acquis |
| Packs cumulatifs cross-galerie | **Conserver** | Logique métier fine, non reconstituable sans le code |
| Photos scolaires | **Conserver** + **persister les contacts** | Module le plus rentable ; le trou « contacts non stockés » est un manque fonctionnel |
| RGPD (locator/export/eraser/audit) | **Conserver quasi tel quel** | Le mieux conçu du projet, conforme, testé (4 fichiers de tests) |
| Supervision | **Conserver le principe**, simplifier | Sur-dimensionné pour un mono-utilisateur, mais le catalogue de remédiation a de la valeur |
| Galeries événements + hiérarchie | **Conserver** | Utilisé, publié |
| Module Disponibilités (H) | **Trancher** — finir ou supprimer | 2 tables + 12 endpoints, zéro UI |
| Bons cadeaux (I) | **Trancher** avec la photographe | Registre manuel aujourd'hui ; la page publique promet un service non outillé |
| Réinitialisation de mot de passe | **Finir** (priorité) | API prête, aucune UI → clients bloqués |
| Téléchargement de facture (client + admin) | **Finir** | 2 endpoints prêts, aucun bouton |
| Notifications in-app | **Conserver et étendre** | Alimentées uniquement par les demandes de réservation ; les événements à forte valeur (commande payée, tirage à expédier, session scolaire traitée) n'en produisent pas |
| CRUD réservations client (5 endpoints) | **Supprimer** | Doublon du dashboard |
| `/my-galleries` (2 endpoints) | **Supprimer** | Doublon du dashboard |
| `apiResource /admin/users` (5 endpoints) | **Supprimer** | Aucun écran |
| `GET /galleries/{id}`, `/galleries/token/{token}`, `/galleries/{id}/download-file` | **Supprimer** | Sans appelant |
| `GET /photos/{photo}` | **Supprimer** | Sans appelant |
| `adminApi.uploadPhotos` / `uploadEventPhotos` | **Supprimer** | Pointent vers des routes inexistantes |
| Portfolio statique (manifest + éditorial en dur) | **Trancher** | Aujourd'hui non administrable : un ajout de photo = un commit + un build |
| Bearer token en `localStorage` (en plus du cookie) | **Supprimer** en refonte | Double mécanisme d'auth, surface XSS inutile (cf. §6) |

### Inventaire des endpoints API sans consommateur front

À confirmer en §3, mais utile dès maintenant pour dimensionner : **~33 endpoints sur ~161** n'ont aucun
appelant dans `web/src`.

| Groupe | Nb | Détail |
|---|---|---|
| Disponibilités | 12 | tout `/admin/availability/*` |
| Réservations client | 5 | `GET|POST /reservations`, `GET|PUT|DELETE /reservations/{id}` |
| Utilisateurs admin | 5 | `apiResource /admin/users` |
| Bons cadeaux | 3 | `POST /gift-cards`, `GET /gift-cards/{id}`, `GET /gift-cards/validate/{code}` |
| Mot de passe | 2 | `forgot-password`, `reset-password` |
| Galeries | 3 | `GET /galleries/{id}`, `/galleries/token/{token}`, `/galleries/{id}/download-file` |
| Mes galeries | 2 | `GET /my-galleries`, `GET /my-galleries/{id}/download` |
| Factures | 2 | `GET /orders/{id}/invoice`, `GET /admin/orders/{id}/invoice` |
| Photos (bulk) | 2 | `bulk-downloadable`, `sort-order` |
| Divers | 4 | `GET /photos/{photo}`, `PUT /auth/profile`, `POST /admin/notifications`, `GET /admin/health` |

> Ces endpoints ne sont pas « inutiles » par nature — certains sont des fonctionnalités **inachevées**
> (mot de passe, factures, disponibilités) et d'autres de vrais **doublons**. La distinction est faite
> colonne « Verdict » ci-dessus, et sera consolidée en §9 (dette) puis §11 (refonte).

---
# 3. API Laravel — routes, controllers, modèles

> **Méthode** : lecture statique de `api/routes/api.php` (341 lignes), `api/bootstrap/app.php`,
> `api/app/Http/**`, `api/app/Models/**`, `api/app/Providers/AppServiceProvider.php` et
> `api/config/**`. Toujours **sans exécution** (`php artisan route:list` est interdit ici : le `.env`
> local pointe la production).

## 3.1. Volumétrie réelle

| Mesure | Valeur |
|---|---|
| `routes/api.php` | 341 lignes |
| Définitions explicites (`Route::get/post/put/patch/delete`) | **156** |
| `Route::apiResource` | **5** (→ 21 endpoints générés) |
| **Total endpoints exposés** | **≈ 177** |
| Controllers | 26 (16 `Api/` + 10 `Api/Admin/`) + 1 classe de base vide |
| FormRequests | 52 |
| API Resources | **4** |
| Modèles Eloquent | 29 (+ 1 trait `Concerns/`) |
| Policies | 3 |
| Middlewares custom | 3 |
| Rate limiters | 5 |

> Le chiffre de « ~161 routes » avancé en §1 comptait les lignes `Route::` sans développer les
> `apiResource`. Le compte exact est **156 + 21 = 177**.

**Répartition par groupe** :

| Groupe | Endpoints | Middleware |
|---|---|---|
| Health check | 5 | `throttle:health` (+ `health.token` sur 2) |
| Public | 45 | aucun (+ `throttle:images`/`downloads`/`gift-code`/`sumup-webhook` selon les routes) |
| Authentifié | 18 | `auth:sanctum` |
| Admin | 88 explicites + 21 (`apiResource`) = **109** | `auth:sanctum` + `admin` |

**Détail des 5 `apiResource`** :

| Déclaration | Endpoints générés |
|---|---|
| `apiResource('users', UserController)` | 5 (index, store, show, update, destroy) |
| `apiResource('clients', ClientController)` | 5 |
| `apiResource('prestations', …)->except(['index','show'])` | 3 (store, update, destroy) |
| `apiResource('galleries', …)->except(['index','show'])` | 3 |
| `apiResource('gift-codes', GiftCodeController)` | 5 |

> ⚠️ Piège pour la refonte : `apiResource` **génère des routes implicites**. Le `GET /admin/galleries`
> et `GET /admin/galleries/{gallery}` visibles dans le fichier sont des déclarations **manuelles**
> pointant sur `adminIndex`/`adminShow`, précisément parce que l'`apiResource` en exclut `index`/`show`.
> Un `route:list` sur la nouvelle API sera nécessaire pour valider qu'aucune route n'a été perdue.

---

## 3.2. Couche transport

### 3.2.1. Pipeline de middleware (`bootstrap/app.php`)

```
GLOBAL          : HandleCors → SecurityHeaders
groupe api      : EnsureFrontendRequestsAreStateful  (prepend)  + statefulApi()
alias           : 'admin'        → EnsureUserIsAdmin
                  'health.token' → EnsureHealthCheckToken
trustProxies    : at: '*'                    ← Cloudflare Tunnel
CSRF            : validateCsrfTokens(except: ['api/*'])
health natif    : '/up'
events          : withEvents(discover: false)
```

Deux choix explicités par des commentaires du code, tous deux importants :

- **`withEvents(discover: false)`** — les listeners sont câblés à la main dans
  `AppServiceProvider::boot()`. Sans ce `false`, Laravel scanne **aussi** `app/Listeners` et enregistre
  chaque listener **deux fois** → « tous les mails partaient en double ». À reproduire (ou éviter par
  construction) dans la refonte.
- **`trustProxies(at: '*')`** — nécessaire derrière le tunnel Cloudflare, mais fait confiance à
  **n'importe quel** `X-Forwarded-For`. Comme les rate limiters (`->by($request->ip())`), les
  `download_logs.ip_address` et les `consent_ip` RGPD reposent tous sur cette IP, un client qui
  atteindrait le backend hors tunnel pourrait **usurper son IP** et contourner tous les throttles.
  À restreindre aux plages Cloudflare. → §6.

### 3.2.2. Rate limiting — 5 limiteurs (`AppServiceProvider::configureRateLimiting`)

| Limiteur | Débit | Clé | Routes protégées | Justification (commentaire du code) |
|---|---|---|---|---|
| `images` | **500/min** | IP | `/images/{preview,thumbnail,clean,clean-thumb}/{photo}` | galeries à plusieurs centaines de photos |
| `downloads` | 60/min | IP | `/images/download/{photo}` | téléchargements HD |
| `sumup-webhook` | 60/min | IP | `POST /payments/sumup/return` | webhook **non signé**, borne le spam par checkout id aléatoire |
| `gift-code` | 20/min | IP | `POST /cart/gift-code` | codes à 6 caractères → borne l'énumération |
| `health` | 60/min | IP | tout le groupe `/health*` | — |

> 🔴 **Absence notable** : **aucun rate limit sur l'authentification**. `POST /auth/login`,
> `/auth/register`, `/auth/forgot-password` et `POST /orders/by-email` sont hors throttle — pas même
> le `throttle:api` par défaut de Laravel, puisque le groupe `api` n'en déclare pas. Le
> bruteforce de mot de passe et l'énumération de comptes sont donc non bornés. À corriger en priorité.

### 3.2.3. CORS (`config/cors.php`)

```
paths                : ['api/*', 'sanctum/csrf-cookie']
allowed_methods      : GET, POST, PUT, DELETE, OPTIONS       ← pas de PATCH
allowed_origins      : env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,:3000,:4173')
allowed_headers      : ['*']
supports_credentials : true
max_age              : 0
```

`PATCH` est absent des méthodes autorisées — sans conséquence aujourd'hui (aucune route `PATCH`), mais
`apiResource` en génère normalement : les 5 `apiResource` exposent bien un `PATCH` côté Laravel qui
serait **bloqué par CORS**. Le front n'utilise que `PUT`, donc l'incohérence est masquée.
`max_age: 0` force un preflight `OPTIONS` sur chaque requête cross-origin — coût réseau inutile.

### 3.2.4. Authentification (`config/sanctum.php`, `config/auth.php`)

| Clé | Valeur | Remarque |
|---|---|---|
| `sanctum.guard` | `['web']` | session d'abord, token en repli |
| `sanctum.stateful` | `SANCTUM_STATEFUL_DOMAINS` (défaut localhost + app URL) | doit lister le domaine du SPA |
| `sanctum.expiration` | **43 200 min = 30 jours** | les tokens Bearer vivent 30 jours |
| `sanctum.token_prefix` | vide | pas de préfixe → **pas de détection par secret scanning** |
| `auth.guards` | `web` (session) uniquement | pas de guard `api` |

Le mécanisme réel est **hybride** (cf. §2.0) : session cookie **et** token Bearer 30 jours en
`localStorage`. Le nettoyage des tokens dormants est délégué au cron RGPD hebdomadaire (§2.12).

### 3.2.5. Handler d'exceptions global + `BusinessException`

Sur `api/*`, `bootstrap/app.php` normalise tout :

| Cas | Réponse |
|---|---|
| `BusinessException` | `{success: false, message: <message métier verbatim>}`, statut porté par l'exception (défaut 400) |
| `ModelNotFoundException` / `NotFoundHttpException` | 404 `« La ressource demandée n'existe pas ou a été supprimée. »` — **volontairement** pour ne pas fuiter le nom de classe du modèle |
| `ValidationException`, `AuthenticationException`, `AuthorizationException`, `HttpExceptionInterface` | passthrough (rendu Laravel natif) |
| tout le reste | log `Unhandled API exception` + 500 avec message générique — **aucun détail technique au client** |

`BusinessException` est un excellent pattern : une exception marqueur dont le message est **sûr à
afficher**. À conserver tel quel dans la refonte.

Sentry est branché en amont (`Integration::handles($exceptions)`).

### 3.2.6. En-têtes de sécurité (`SecurityHeaders`, global)

Posés sur **toutes** les réponses : `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
`X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`,
`Permissions-Policy: camera=(), microphone=(), geolocation=()`, plus
`Strict-Transport-Security: max-age=31536000; includeSubDomains` si `$request->isSecure()`.

> Manque : **aucune `Content-Security-Policy`**. Combiné au token Bearer stocké en `localStorage`
> (§2.0), c'est le trou le plus exploitable de la surface actuelle. `X-XSS-Protection` est par ailleurs
> obsolète (ignoré par les navigateurs modernes). → §6.

### 3.2.7. Jeton de supervision (`EnsureHealthCheckToken`)

Accepte le jeton via l'en-tête `X-Health-Token` **ou le paramètre d'URL `?token=`**, comparaison en
`hash_equals` (temps constant — bon réflexe). Refuse en 403 si `HEALTH_CHECK_TOKEN` n'est pas
configuré, ce qui est un défaut sûr (« fail closed »).

> Le repli sur `?token=` fait apparaître le secret dans les logs Nginx et l'historique du navigateur.
> À supprimer au profit du seul en-tête.

---

## 3.3. Inventaire complet des routes

### 3.3.1. Health check — 5 endpoints

| Méthode | URI | Action | Protection |
|---|---|---|---|
| GET | `/` | `HealthController@live` | `throttle:health` |
| GET | `/health` | `HealthController@index` | `throttle:health` |
| GET | `/health/live` | `HealthController@live` | `throttle:health` |
| GET | `/health/details` | `HealthController@details` | + `health.token` |
| GET | `/health/database` | `HealthController@database` | + `health.token` |

Une route `/health/tables` a été **supprimée** (elle publiait le schéma de la base) — le commentaire
subsiste dans `routes/api.php` comme garde-fou documentaire.

### 3.3.2. Public — 45 endpoints

**Authentification (4)** — `POST /auth/register`, `/auth/login`, `/auth/forgot-password`,
`/auth/reset-password` (les 2 derniers sans UI, cf. §2.7).

**Prestations (2)** — `GET /prestations`, `GET /prestations/{prestation}`.

**Galeries (7)**

| Méthode | URI | Action | Consommé ? |
|---|---|---|---|
| GET | `/galleries` | `index` | ✅ (`api.getGalleryItems`, en fait mort — le portfolio est statique) |
| GET | `/galleries/{gallery}` | `show` | 🔴 |
| GET | `/galleries/token/{token}` | `showByToken` | 🔴 |
| GET | `/galleries/code/{code}` | `showByShareCode` | ✅ |
| GET | `/galleries/download/{token}` | `showDownloadableByToken` | ✅ |
| GET | `/galleries/{gallery}/download-zip` | `downloadZip` | ✅ |
| GET | `/galleries/{gallery}/download-file` | `downloadFile` | 🔴 |

**Photos (3)** — `GET /photos/{photo}` 🔴, `POST /photos/{photo}/like` ✅, `GET /photos/{photo}/download` ✅.

**Proxy d'images (5)** — `GET /images/{preview|thumbnail|clean|clean-thumb}/{photo}`
(`throttle:images`) + `GET /images/download/{photo}` (`throttle:downloads`).

**Divers (4)** — `POST /contact`, `GET /events`, `GET /events/{gallery}`,
`GET /gift-cards/validate/{code}` 🔴, `POST /booking-request`.

**Panier (9)** — `GET /cart`, `POST /cart/add`, `PUT /cart/item/{item}/type`,
`PUT /cart/item/{item}/quantity`, `DELETE /cart/item/{item}`, `DELETE /cart/clear`,
`PUT /cart/email`, `POST /cart/gift-code` (`throttle:gift-code`), `DELETE /cart/gift-code`.

**Commandes (7)** — `POST /checkout`, `POST /checkout/confirm-free`, `GET /orders/{order}`,
`GET /orders/{order}/download/{item}`, `GET /orders/{order}/download-all`,
`GET /orders/{order}/invoice` 🔴, `POST /orders/by-email`.

**Paiement SumUp (4)** — `POST /payments/sumup/verify`, `POST /payments/sumup/cancel-checkout`,
`GET /payments/sumup/return` (redirection navigateur), `POST /payments/sumup/return`
(webhook, `throttle:sumup-webhook`).

> ⚠️ **8 endpoints publics manipulent des ressources nominatives sans authentification** :
> les 3 `download*` de galerie, les 2 `download` de commande, `GET /orders/{order}`,
> `POST /orders/by-email` et `GET /photos/{photo}/download`. Leur seule protection est un secret dans
> l'URL (token 64 car., code 6 car., ou UUID) — voir §3.5 pour le détail du contrôle d'accès réel.

### 3.3.3. Authentifié (`auth:sanctum`) — 18 endpoints

| Domaine | Endpoints | Consommé ? |
|---|---|---|
| Auth | `POST /auth/logout`, `GET /auth/user`, `PUT /auth/profile` | 2/3 (profile 🔴) |
| Réservations | `GET|POST /reservations`, `GET|PUT|DELETE /reservations/{reservation}` | 🔴 (5) — **et 3 lèvent une erreur 500, cf. §3.5** |
| Bons cadeaux | `POST /gift-cards`, `GET /gift-cards/{giftCard}` | 🔴 (2) |
| Mes galeries | `GET /my-galleries`, `GET /my-galleries/{gallery}/download` | 🔴 (2) |
| Notifications | `GET /notifications`, `PUT /notifications/{id}/read`, `PUT /notifications/read-all` | ✅ |
| Compte | `GET /account/dashboard` | ✅ |
| Panier | `POST /cart/merge` | ✅ |
| Commandes | `GET /orders` | ✅ |

**9 des 18 endpoints authentifiés sont morts.**

### 3.3.4. Admin (`auth:sanctum` + `admin`) — 109 endpoints

| Bloc | Nb | Notes |
|---|---|---|
| Dashboard | 2 | `index`, `stats` (cache 5 min) |
| Health | 1 | 🔴 doublon de `/health/details` |
| Users (`apiResource`) | 5 | 🔴 aucun écran |
| Clients (`apiResource` + 2) | 7 | dont `gdpr-export` |
| Privacy | 8 | cf. §2.12 |
| Logs | 3 | `index`, `download`, `clear` |
| Prestations | 5 | `adminIndex` + 3 (`apiResource`) + `toggle` |
| Réservations | 6 | dont `calendar` |
| **Availability** | **12** | 🔴 **module entier mort** (cf. §2.8) |
| Galeries | 11 | CRUD + photos async + regenerate token/code + send email/sms |
| Photos | 3 | `toggle-downloadable` ✅, `bulk-downloadable` 🔴, `sort-order` 🔴 |
| Event categories | 5 | dont `reorder` |
| Events | 8 | CRUD + thumbnail + children + photos async |
| Upload status | 1 | polling |
| Gift cards | 2 | `index`, `update` |
| Gift codes | 7 | `apiResource` (5) + `generate-code` + `toggle` |
| Notifications | 1 | 🔴 création jamais appelée |
| **School sessions** | **18** | le plus gros bloc |
| Orders | 7 | dont `retry-payment`, `ship`, `invoice` 🔴 |

---

## 3.4. Controllers

### 3.4.1. Inventaire par taille

| Controller | LOC | Endpoints | Délègue à |
|---|---|---|---|
| `Admin/SchoolSessionController` | 507 | 18 | `SchoolSessionService`, `SchoolSessionExportService`, `SmsTemplateService` |
| `GalleryController` | 505 | 18 | `SmsTemplateService`, `GalleryPolicy` (manuellement) |
| `OrderController` | 458 | 8 | `OrderService`, `CartService` |
| `EventGalleryController` | 389 | 9 | trait `SyncsProductTypes`, `ClearsEventGalleriesCache` |
| `PhotoController` | 375 | 9 | `ImageProcessingService`, `MinioStorageService` |
| `CartController` | 368 | 9 | `CartService` |
| `ImageProxyController` | 300 | 5 | `ImageProcessingService`, `MinioStorageService` |
| `SumUpPaymentController` | 290 | 4 | `SumUpService`, `OrderService` |
| `Admin/OrderController` | 248 | 7 | `OrderService`, `InvoiceService` |
| `ReservationController` | 213 | 11 | — (Eloquent direct) |
| `Admin/PrivacyController` | 208 | 8 | 4 services `Privacy/` |
| `AvailabilityController` | 199 | 12 | — (Eloquent direct) |
| `Admin/ClientController` | 162 | 7 | — |
| `Admin/GiftCodeController` | 160 | 7 | `GiftCodeService` |
| `AuthController` | 158 | 7 | — |
| `Admin/LogController` | 142 | 3 | — (accès disque) |
| `Admin/DashboardController` | 131 | 2 | — |
| `Admin/EventCategoryController` | 108 | 5 | — |
| `PrestationController` | 99 | 7 | — |
| `Admin/NotificationController` | 90 | 4 | — |
| `AccountController` | 89 | 1 | — |
| `Admin/UserController` | 87 | 5 | — |
| `GiftCardController` | 83 | 5 | — |
| `HealthController` | 82 | 4 | `HealthCheckService` |
| `BookingRequestController` | 76 | 1 | — |
| `ContactController` | 36 | 1 | — |
| `Controller` (base) | 8 | — | **classe abstraite vide** |

Total : **5 571 lignes** de controllers pour 4 941 lignes de services.

### 3.4.2. La convention « controller mixte public/admin »

Sept controllers servent **à la fois** le public et l'admin, avec une convention de nommage
`adminIndex` / `adminShow` / `adminUpdate` / `adminDestroy` :
`GalleryController`, `PhotoController`, `EventGalleryController`, `PrestationController`,
`ReservationController`, `GiftCardController`, `AvailabilityController`.

Conséquences observées :

- `GalleryController` fait **505 lignes et 18 endpoints** répartis sur 3 audiences (public par code,
  client authentifié, admin) — c'est le fichier le plus difficile à raisonner du projet ;
- `PrestationController` (dans `Api/`, donc « public ») importe
  `App\Http\Requests\Admin\StorePrestationRequest` et `Admin\UpdatePrestationRequest` : un controller
  public dépend de FormRequests admin. Même chose pour `ReservationController` avec
  `Admin\AdminUpdateReservationRequest` et `Admin\UpdateReservationStatusRequest` ;
- la séparation public/admin n'est **portée que par le fichier de routes**. Rien dans le code du
  controller ne rappelle qu'une méthode est privilégiée : une erreur de câblage de route exposerait
  directement une méthode admin.

> **Recommandation refonte** : séparer physiquement les controllers publics et admin, quitte à
> dupliquer un peu. La convention `adminXxx()` dans le même fichier est la principale source de
> confusion de cette couche.

### 3.4.3. Cohérence de la délégation aux services

La règle « controllers fins → services » annoncée en §1 est vraie **par domaine, pas globalement** :

| Bien délégué | Métier dans le controller |
|---|---|
Panier → `CartService` | `ReservationController` (213 l., Eloquent direct)
Commandes → `OrderService` | `AvailabilityController` (199 l., Eloquent direct)
Paiement → `SumUpService` | `Admin/ClientController`, `Admin/UserController`, `Admin/EventCategoryController`
RGPD → 4 services `Privacy/` | `Admin/LogController` (parsing de fichier de log dans le controller)
Sessions scolaires → `SchoolSessionService` | `Admin/DashboardController` (agrégats SQL dans le controller)
Images → `ImageProcessingService` | `PhotoController::processSinglePhoto` (méthode privée de 60 l.)

---

## 3.5. Contrôle d'accès — trois mécanismes qui se contredisent

C'est le point le plus fragile de l'API. **Quatre** dispositifs coexistent, et les Policies —
mécanisme censé être central — sont traitées de **trois façons différentes**.

### 3.5.1. Le middleware `admin`

`EnsureUserIsAdmin` : `$request->user()->isAdmin()` sinon 403. C'est **le seul** contrôle réellement
systématique du projet, appliqué à tout le préfixe `/admin`. Solide et lisible.

### 3.5.2. Les FormRequests n'autorisent rien

**52 FormRequests sur 52** ont un `authorize()` qui retourne `true`. Aucune vérification de propriété
n'est faite au niveau de la validation : elle est entièrement déléguée au middleware de route.
Choix défendable, mais il faut le savoir — un `StoreGalleryRequest` n'empêche personne de créer une
galerie, seule la route le fait.

### 3.5.3. Les 3 Policies, traitées de 3 manières incohérentes

| Policy | Comment elle est appelée | Verdict |
|---|---|---|
| `GalleryPolicy` | **Une seule fois**, `GalleryController:364` : `app(GalleryPolicy::class)->download(...)` dans un `abort_unless` — instanciée **à la main comme un service**, hors du Gate Laravel | Fonctionne, mais contourne la couche d'autorisation (pas d'événement, pas de middleware `can`) |
| `OrderPolicy` | **Jamais appelée.** Sa logique est **recopiée à la main** dans `OrderController::show` (l. 195-202) et `OrderService::getOrderForDownload` (l. 634-642) | 🔴 Policy morte + règle dupliquée en 3 endroits |
| `ReservationPolicy` | Via `$this->authorize()` — la manière canonique… **mais qui plante** (voir ci-dessous) | 🔴 Casse à l'exécution |

### 3.5.4. 🐛 Bug latent : `$this->authorize()` sur une classe de base vide

`app/Http/Controllers/Controller.php` est une **classe abstraite vide** (comportement par défaut de
Laravel 11/12, qui a retiré les traits historiques). Or :

- `ReservationController` appelle `$this->authorize(...)` aux lignes **53**, **100** et **152** ;
- ni ce controller ni la classe de base n'utilisent le trait `AuthorizesRequests` ;
- **`grep -rn "AuthorizesRequests" app/` → aucune occurrence.**

Ces trois appels lèvent donc un `Error: Call to undefined method …::authorize()`, capté par le handler
global et transformé en **HTTP 500** avec le message générique.

Routes concernées — les trois méthodes **client** (les variantes `adminShow`/`adminUpdate`/`adminDestroy`
n'appellent pas `authorize`) :

| Route | Méthode | Ligne |
|---|---|---|
| `GET /reservations/{reservation}` | `show` | 53 |
| `PUT /reservations/{reservation}` | `update` | 100 |
| `DELETE /reservations/{reservation}` | `destroy` | 152 |

**Pourquoi personne ne l'a vu** : ce sont exactement les 3 endpoints identifiés comme morts en §2.8
(aucun appel front), et **aucun test ne couvre les réservations** (`grep -rn "reservations" tests/`
→ rien). Le bug est une arme chargée, pas un incident en cours — mais il montre que la couche
d'autorisation n'est validée par rien.

### 3.5.5. La règle des « 30 minutes »

Écrite trois fois (`OrderPolicy:32` et `:59`, `OrderController:201`, `OrderService:642`), elle accorde
l'accès à **n'importe quelle commande créée depuis moins de 30 minutes** — et le téléchargement de
**n'importe quelle commande payée depuis moins de 30 minutes** — **sans aucune autre vérification**.

Le seul rempart est l'imprévisibilité de l'UUID de la commande. C'est une protection par obscurité
appliquée à des données nominatives (nom, email, adresse de livraison, photos achetées). Le besoin
fonctionnel derrière est réel — permettre à un invité de voir sa confirmation juste après paiement —
mais la bonne réponse est un token signé à courte durée, pas une fenêtre temporelle globale. → §6.

### 3.5.6. Récapitulatif des mécanismes par ressource

| Ressource | Mécanisme réel |
|---|---|
| Tout `/admin/*` | middleware `admin` ✅ |
| Galerie privée (lecture) | secret dans l'URL (`share_code` 6 car. ou `access_token` 64 car.), vérifié **inline** dans le controller |
| Galerie (ZIP) | `GalleryPolicy::download` appelée manuellement |
| Photo (téléchargement) | token de galerie en query string, vérifié inline |
| Commande (lecture) | 4 chemins inline : owner / email fourni / token de download / **< 30 min** |
| Commande (téléchargement) | 3 chemins dans `OrderService` : owner / token / **payé < 30 min** |
| Réservation (client) | `$this->authorize()` → **500** |
| Réservation (admin) | middleware `admin` seul |

---

## 3.6. Validation — 52 FormRequests

Répartition : **34** dans `Admin/` (dont 2 dans `Admin/Privacy/`), **5** dans `Auth/`, **13** à la
racine `Requests/`.

| Domaine | FormRequests |
|---|---|
| Galeries | `StoreGalleryRequest`, `UpdateGalleryRequest`, `SendAccessEmailRequest`, `SendAccessSmsRequest`, `SetThumbnailRequest` |
| Galeries événements | `StoreEventGalleryRequest`, `UpdateEventGalleryRequest`, `StoreEventCategoryRequest`, `UpdateEventCategoryRequest`, `ReorderEventCategoriesRequest` |
| Photos | `StoreAsyncPhotoRequest`, `BulkToggleDownloadableRequest`, `UpdateSortOrderRequest`, `UploadStatusRequest` |
| Panier / commande | `AddToCartRequest`, `UpdateCartItemTypeRequest`, `UpdateCartEmailRequest`, `ApplyGiftCodeRequest`, `CreateCheckoutRequest`, `GetOrdersByEmailRequest`, `OrderIdRequest` |
| Codes / bons | `StoreGiftCodeRequest`, `UpdateGiftCodeRequest`, `StoreGiftCardRequest`, `UpdateGiftCardRequest` |
| Réservations | `StoreReservationRequest`, `UpdateReservationRequest`, `AdminUpdateReservationRequest`, `UpdateReservationStatusRequest`, `StoreBookingRequest` |
| Disponibilités | `StoreAvailabilitySlotRequest`, `UpdateAvailabilitySlotRequest`, `StoreAvailabilityPatternRequest`, `UpdateAvailabilityPatternRequest`, `GenerateAvailabilitySlotsRequest` (🔴 module mort) |
| Sessions scolaires | `StoreSchoolSessionRequest`, `UpdateSchoolSessionRequest`, `SendSchoolSessionMessagesRequest` |
| Clients / users | `StoreClientRequest`, `UpdateClientRequest`, `StoreUserRequest`, `UpdateUserRequest` |
| Prestations | `StorePrestationRequest`, `UpdatePrestationRequest` |
| Auth | `LoginRequest`, `RegisterRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `UpdateProfileRequest` |
| Contact / RGPD | `SendContactRequest`, `Privacy/SearchPrivacyRequest`, `Privacy/ErasePrivacyRequest` |

**Règles de validation partagées** :

- trait `SyncsProductTypes::productTypeValidationRules()` — factorise la validation des types de
  produits et paliers (`in:digital,print_10x15,print_15x20,print_scolaire`, `tiers` max 3,
  `min_quantity` ≥ 2). Bon réflexe, utilisé par `GalleryController` et `EventGalleryController` ;
- `Rules/SmsTemplateRule` — impose qu'un gabarit SMS personnalisé contienne au moins `{code}` ou
  `{lien}`, sinon le destinataire ne peut pas atteindre la galerie. Petite règle, vraie valeur métier.

Les messages d'erreur sont en français, personnalisés via `messages()` — mais **inégalement** : certains
FormRequests en fournissent (`SendSchoolSessionMessagesRequest`), d'autres laissent les messages
Laravel par défaut. Quelques messages ont perdu leurs accents (« Le numero de telephone est requis »).

---

## 3.7. Formats de réponse — l'incohérence la plus coûteuse

**Il n'y a pas de convention.** `PrestationController`, à lui seul (99 lignes), produit **quatre
formes différentes** :

```php
index()      → { "prestations": [...] }                       // clé au pluriel, nu
adminIndex() → { "success": true, "data": [...] }             // enveloppe success/data
show()       → { "prestation": {...} }                        // clé au singulier
store()      → { "prestation": {...}, "message": "..." }      // + message
destroy()    → { "message": "..." }                           // message seul
```

À l'échelle de l'API : **97** occurrences de `'success' => true` et **38** de `'data' =>`, mélangées
avec des clés ad hoc (`prestations`, `prestation`, `order`, `uploads`, `galleries`, `batch_id`,
`received`, `status`…). Les réponses paginées, elles, renvoient **l'objet paginateur Laravel brut**
(`return response()->json($reservations)`), soit encore une autre forme (`data`, `current_page`,
`last_page`, `per_page`, `total`, `links`…).

**Pagination — 5 tailles différentes, rarement paramétrable** :

| Taille | Endpoints |
|---|---|
| 10 | `ReservationController::index` |
| 12 | `GalleryController::index`, `EventGalleryController::index` |
| 20 | 8 endpoints (galeries admin, events admin, réservations admin, users, gift-cards, clients, school-sessions…) |
| 50 | `AvailabilityController::indexSlots` |
| `per_page` accepté | seulement 4 : `Admin/OrderController`, `Admin/ClientController`, `Admin/GiftCodeController`, `Admin/PrivacyController` |

**Seulement 4 API Resources pour ~177 endpoints** : `GalleryResource`, `OrderResource`,
`OrderItemResource`, `CalendarEventResource`. Partout ailleurs, c'est de la sérialisation Eloquent
brute (donc **dépendante de `$fillable`/`$hidden`/`$appends`**) ou des tableaux construits à la main.
`Admin/OrderController::formatOrder()` est même une **méthode statique publique appelée depuis
`OrderController` (public)** pour formater une commande — une API Resource déguisée en méthode de
controller.

**Conséquences côté front**, observables directement :

- deux couches d'accès parallèles (`BaseApiService.request()` et `baseAdmin.adminRequest()`) avec des
  types de retour distincts (`ApiResponse<T>` vs `AdminApiResponse<T>` vs `AdminPaginatedResponse<T>`) ;
- des `types/` volumineux (`types/admin.ts` fait 600+ lignes) qui décrivent une forme par endpoint ;
- 8 composants qui contournent la couche services avec `fetch()` (§2.0) — précisément parce que la
  forme de réponse ne rentrait dans aucun helper.

> **Recommandation refonte** : une enveloppe unique (ou aucune), des API Resources systématiques, une
> pagination paramétrable partout. C'est le chantier avec le meilleur rapport bénéfice/effort de
> toute la refonte : il supprime mécaniquement une grande partie des types front et la tentation du
> `fetch()` sauvage.

---

## 3.8. Modèles Eloquent — 29 modèles

### 3.8.1. Conventions

| Convention | État |
|---|---|
| `HasUuids` | ✅ **29/29** — toutes les clés primaires sont des UUID |
| `HasFactory` | ❌ manquant sur 5 : `GalleryProductType`, `PackTier`, `Invoice`, `SchoolSession`, `SchoolSessionExport` |
| `casts()` (méthode, style L11+) | ✅ généralisé |
| `$fillable` | ✅ partout (pas de `$guarded = []`) |
| `CastsBooleansForPostgres` | sur 12 modèles (cf. §3.9) |
| `declare(strict_types=1)` | ❌ absent |
| Soft deletes | ❌ **aucun modèle** — toutes les suppressions sont définitives |

> L'absence de `SoftDeletes` est structurante : supprimer une galerie, une commande ou une session
> scolaire est **irréversible** et déclenche des cascades. À arbitrer en §11 (au minimum sur `orders`
> et `galleries`).

### 3.8.2. Carte des relations

```
User ──1:N── Gallery ──1:N── Photo ──1:N── DownloadLog
 │             │                │
 │             ├──1:N── GalleryProductType ──1:N── PackTier
 │             ├──N:1── EventCategory
 │             ├──N:1── SchoolSession ──1:N── SchoolSessionExport
 │             ├──N:1── Gallery (parent_id, auto-référence)
 │             ├──N:1── Photo (thumbnail_photo_id)
 │             └──1:N── PhotoUpload
 │
 ├──1:N── Reservation ──1:1── ClientForm
 │             ├──N:1── Prestation
 │             ├──N:1── AvailabilitySlot        (🔴 module mort)
 │             ├──N:1── Client
 │             └──1:N── Payment
 │
 ├──1:N── Cart ──1:N── CartItem ──N:1── Photo
 │           └──N:1── GiftCode
 │
 ├──1:N── Order ──1:N── OrderItem ──N:1── Photo
 │           ├──1:1── Invoice
 │           ├──1:1── Payment ──1:1── GiftCard
 │           ├──N:1── Cart
 │           └──N:1── GiftCode
 │
 ├──1:N── Notification
 └──1:1── Client   (via UserObserver)

Sans relation : ContactMessage, PrivacyExport (→ requester), PrivacyAuditLog (→ actor),
                AvailabilityPattern
```

### 3.8.3. `Gallery` — le modèle pivot (307 lignes)

Une **seule** table `galleries` porte **quatre concepts métier** distingués par `type` et des colonnes
optionnelles :

| Usage | Discriminant | Colonnes spécifiques |
|---|---|---|
| Galerie privée client | `type = 'private'` | `assigned_email`, `share_code`, `access_token`, `sms_template` |
| Galerie publique | `type = 'public'` | — |
| Galerie événement | `type = 'event'` | `event_category_id`, `parent_id`, `is_published`, `sort_order`, `event_link` |
| Galerie enfant (scolaire) | `school_session_id` non nul | `class_name`, `shipping_fee` |

C'est un **table-per-hierarchy** implicite. Économe, mais toute requête doit se souvenir du
discriminant — d'où les scopes `public()`, `private()`, `published()`, `topLevel()` et les
`where('type', 'event')` dispersés. À poser explicitement en §3bis/§11.

`Gallery` expose aussi **7 attributs calculés** dont plusieurs font une requête SQL chacun
(`total_likes`, `client_id`, `downloadable_photos_count`, `liked_photos_count`,
`total_downloads_count`, `downloaded_photos_count`, `download_status`). Ils ne sont pas dans
`$appends` (bon réflexe), mais `GalleryResource` doit compenser en pré-chargeant des `withCount`
/`withSum` — et son en-tête documente cette dépendance implicite (« Expects withCount(…) to be
loaded »). Fragile : oublier le `withCount` ne casse rien, ça renvoie simplement 0.

### 3.8.4. `Photo` (304 lignes) — 7 colonnes de chemin, 3 appends

`$appends = ['display_url', 'preview_url', 'thumbnail_url']` : **chaque photo sérialisée déclenche
jusqu'à 3 calculs de signature MinIO**. Sur une galerie de 200 photos, cela fait 600 signatures HMAC
par réponse JSON (calcul local, pas d'I/O — c'est justement pourquoi le cache a été retiré, cf. §2.3).
`display_url` est marqué `@deprecated` mais toujours dans `$appends`, et toujours exposé par
`OrderItemResource`.

`clean_thumbnail_url` n'est **pas** dans `$appends`, volontairement : le commentaire du code explique
que le dérivé sans filigrane fuiterait dans les galeries boutique. Bonne défense, mais elle repose sur
une omission — un `$appends` ajouté par inadvertance suffirait à la casser.

Hook `booted()` : invalidation du cache `photo_meta_{id}` sur `saved` et `deleted`.

### 3.8.5. Hooks `boot()` et génération d'identifiants

| Modèle | Hook | Effet |
|---|---|---|
| `Gallery` | `creating` | génère `access_token` (64 car.), `share_code` (6 car. unique), `type = 'private'` par défaut |
| `Order` | `creating` | génère `order_number` `OT-YYYY-NNNNN` sous `pg_advisory_xact_lock(42)` |
| `Invoice` | (statique) | `invoice_number` `F-YYYY-NNNNN` sous `pg_advisory_xact_lock(43)` |
| `GiftCard` | `creating` | code 12 car. unique + `remaining_amount = amount` |
| `GiftCode` | `creating`/`updating` | code 6 car. (alphabet sans `O/0/I/1`), normalisation en majuscules |

Les **advisory locks numérotés en dur (42, 43)** méritent un registre : deux verrous partageant un
numéro se bloqueraient mutuellement sans rapport métier. À documenter ou remplacer par une séquence
Postgres dans la refonte.

### 3.8.6. `UserObserver` — synchronisation `User → Client`

À la création d'un `User` non-admin, un `Client` est créé (ou rattaché s'il existe déjà par email).
À la mise à jour, `name`/`email`/`phone` sont resynchronisés.

> 🐛 **Bug de qualité de données** : le `Client` est créé avec
> `'source' => 'reservation'` **en dur**, avec le commentaire `// Source enum: reservation, manual,
> contact`. Or une inscription libre n'est **pas** une réservation. Tous les clients issus d'une
> inscription sont donc étiquetés `reservation`, ce qui rend les scopes `fromReservation()` /
> `manual()` / `fromContact()` — et toute statistique d'acquisition bâtie dessus — **faux**.
> À corriger, et **à nettoyer lors de la migration** : la colonne `clients.source` n'est pas fiable
> en l'état (→ §10).

---

## 3.9. 🔴 Le nœud PostgreSQL : `EMULATE_PREPARES` → booléens cassés

C'est le problème technique le plus profond de l'API, et il faut en comprendre la chaîne causale
complète car **elle contamine 12 modèles et 20 requêtes**.

**La cause** (`config/database.php`, connexion `pgsql`) :

```php
'options' => [
    // Emulate prepares to work with connection poolers (PgBouncer/Supabase)
    // in transaction/statement pooling mode
    PDO::ATTR_EMULATE_PREPARES  => true,
    PDO::ATTR_PERSISTENT        => false,
    // Stringify fetches to help with boolean handling
    PDO::ATTR_STRINGIFY_FETCHES => true,
],
```

Le pooler Supabase en mode transaction/statement ne supporte pas les *prepared statements* nommés →
`EMULATE_PREPARES = true`. Effet de bord : PDO envoie les booléens PHP comme `1`/`0`, que PostgreSQL
**refuse** pour une colonne `boolean`. Et `STRINGIFY_FETCHES` fait remonter les booléens en chaînes
`'t'`/`'f'`/`'true'`/`'false'` — or en PHP `(bool) 'false' === true`.

**La rustine** — `app/Models/Concerns/CastsBooleansForPostgres.php`, appliquée à **12 modèles** :

- `setAttribute()` écrit la **chaîne** `'true'`/`'false'` dans `$this->attributes` au lieu d'un booléen
  PHP, court-circuitant complètement le pipeline de casts d'Eloquent ;
- `castAttribute()` réinterprète les chaînes `'true'|'t'|'1'|'yes'` en booléen à la lecture.

**La conséquence en cascade** — un `where('is_published', true)` reste cassé, d'où **20 `whereRaw`**
dispersés dans les modèles :

| Occurrences | Expression |
|---|---|
| 5 | `whereRaw('is_downloadable = true')` |
| 4 | `whereRaw('is_video = false')` |
| 4 | `whereRaw('is_liked = true')` |
| 2 | `whereRaw('is_active = true')` |
| 1 chacun | `is_video = true`, `is_purchasable = true`, `is_processed = false`, `"is_published" = true`, `1 = 0` |

**Pourquoi c'est grave pour la refonte** :

1. `setAttribute()` surchargé signifie qu'un modèle en mémoire porte des `'true'` (chaîne) là où le
   reste du code attend un `bool`. Toute comparaison stricte (`=== true`) sur un attribut fraîchement
   assigné est fausse.
2. Les 20 `whereRaw` sont du SQL en dur : ils **ne survivront pas** à un changement de nom de colonne,
   et un `whereRaw` de plus avec de la donnée utilisateur serait une injection.
3. La rustine est **invisible** : rien n'échoue bruyamment si on l'oublie sur un nouveau modèle — le
   filtre renvoie simplement des résultats faux.
4. Un `whereRaw('1 = 0')` traîne comme garde « ne renvoie rien » — signe que la couche query builder
   n'est plus de confiance.

> **Verdict refonte** : traiter la cause, pas le symptôme. Deux options — utiliser la **connexion
> directe** (port 5432) ou le **session pooler** de Supabase, qui supportent les prepared statements
> nommés ; ou basculer sur un Postgres non poolé. Dans les deux cas, `EMULATE_PREPARES` et
> `STRINGIFY_FETCHES` disparaissent, et avec eux le trait `CastsBooleansForPostgres` **et les 20
> `whereRaw`**. C'est un gain net de robustesse pour un coût de configuration.
>
> ⚠️ **Impact migration** : à vérifier en §3bis/§10 — si des colonnes booléennes ont été écrites en
> `text` plutôt qu'en `boolean` par cette rustine, le script de migration doit normaliser.

---

## 3.10. Cache applicatif

Store : `database` (tables `cache`, `cache_locks`).

| Clé | TTL | Invalidation |
|---|---|---|
| `prestations_public` | 600 s | ✅ `Cache::forget` sur store/update/destroy/toggle |
| `public_galleries_page_{n}` | 300 s | ❌ aucune — expiration seule |
| `event_galleries_page_{n}` | 300 s | 🟡 trait `ClearsEventGalleriesCache` (voir ci-dessous) |
| `dashboard_stats` | 300 s | ❌ aucune |
| `photo_meta_{id}` | (ImageProxy) | ✅ hooks `saved`/`deleted` du modèle `Photo` |
| dérivés d'image générés à la volée | 3600 s | — |
| heartbeats supervision | `SUPERVISION_HEARTBEAT_TTL_MINUTES` (1440) | `HeartbeatService` |
| cooldown d'alertes | 60 min | `SupervisionAlertService` |

> 🐛 **`ClearsEventGalleriesCache` ne purge que les pages 1 à 10** :
> ```php
> for ($i = 1; $i <= 10; $i++) { Cache::forget("event_galleries_page_{$i}"); }
> ```
> La pagination des events est à 12 par page (`EventGalleryController::index`). Au-delà de
> **120 galeries événements publiées**, la page 11 et suivantes ne sont jamais invalidées — elles
> expirent seulement au bout de leur TTL de 300 s. Impact réel faible (5 minutes de retard), mais
> c'est un motif à ne pas reproduire : purger par tag ou par préfixe, pas par boucle bornée en dur.

---

## 3.11. Jobs, events, listeners, commandes

### 3.11.1. Les 7 jobs et leurs garanties

| Job | `tries` | `timeout` | `backoff` | Dispatché depuis |
|---|---|---|---|---|
| `ProcessPhotoJob` | 3 | 300 s | 10/30/60 | `SchoolSessionService:245`, `Admin/SchoolSessionController:270`, `RetryFailedPhotoUploads:68` |
| `GenerateCleanThumbnailJob` | 3 | 120 s | 10/30/60 | `PhotoController:143` et `:174`, `GenerateCleanThumbnails:67` |
| `SendSchoolSessionSmsJob` | 3 | 30 s | 10/30/60 | `DispatchSmsBatchJob`, **`GalleryController:255`** |
| `DispatchSmsBatchJob` | **1** | 300 s | — | `Admin/SchoolSessionController:356` |
| `ProcessSchoolSessionJob` | **1** | 3600 s | — | `Admin/SchoolSessionController:206` |
| `GenerateSchoolSessionExportJob` | **1** | 3600 s | — | `Admin/SchoolSessionController:436` |
| `GeneratePrivacyExportJob` | **1** | 3600 s | — | `Admin/PrivacyController:58` et `:84` |

Les jobs longs sont volontairement en `tries = 1` : rejouer un export ou une extraction de ZIP de 3600 s
serait pire que d'échouer proprement (le statut est persisté en base et l'admin peut relancer).

> **Deux incohérences de nommage à corriger** :
> 1. **`PhotoController::storeAsync` n'est pas asynchrone.** Malgré son nom et son URI
>    (`POST /admin/galleries/{gallery}/photos/async`), la méthode appelle `processSinglePhoto()`
>    **en ligne, dans la requête HTTP**, pour chaque fichier du lot. Aucun job n'est mis en file.
>    L'« async » désigne l'expérience navigateur (upload par lots de 10 + polling), pas le backend.
>    C'est d'ailleurs pourquoi `humanizeUploadError()` existe : le traitement peut saturer la mémoire
>    PHP **pendant** la requête, et il faut renvoyer un message actionnable. `ProcessPhotoJob` — le
>    vrai job — n'est utilisé que par le chemin scolaire et la commande de reprise.
> 2. **`SendSchoolSessionSmsJob` est utilisé hors scolaire** : `GalleryController::sendAccessSms`
>    (envoi d'accès à une galerie cliente ordinaire) le réutilise. Le nom induit en erreur ; à
>    renommer `SendSmsJob`.

### 3.11.2. Events et listeners

2 events, 2 listeners, câblés **explicitement** dans `AppServiceProvider::boot()` (cf. §3.2.1) :

| Event | Payload | Listener | Effets |
|---|---|---|---|
| `ContactMessageSent` | `ContactMessage`, `array` | `SendContactEmails` | `ContactFormMail` (admin) + `ContactConfirmationMail` (visiteur), avec traçage en base |
| `BookingRequested` | `Reservation`, `Prestation`, `array` | `SendBookingNotifications` | 1 `Notification` **par admin** + `NewReservationRequestMail` |

Les deux listeners sont **synchrones** (pas de `ShouldQueue`) : ils s'exécutent dans la requête HTTP.
`SendBookingNotifications` enveloppe chaque bloc dans un `try/catch` + `Log::error` pour qu'un échec
d'email ne fasse pas échouer la demande du visiteur — bon réflexe, mais l'échec est alors **silencieux
pour l'utilisateur**.

### 3.11.3. Les 11 commandes artisan

| Commande | Rôle | Type |
|---|---|---|
| `photos:generate-clean-thumbnails` | backfill des miniatures sans filigrane sur MinIO | **migration de données** |
| `photos:process-existing` | génère preview + thumbnail filigranés pour les photos existantes | **migration de données** |
| `photos:retry-failed` | re-dispatch `ProcessPhotoJob` pour les `PhotoUpload` en échec dont le fichier temporaire existe encore | exploitation |
| `storage:clean-orphans` | détecte (et supprime) les fichiers/dossiers MinIO non référencés en base | **exploitation / audit** |
| `orders:backfill-customer-info` | remplit `guest_email`/`guest_first_name`/`guest_last_name` quand `user_id` est présent mais les colonnes vides (`--dry-run`) | **migration de données** |
| `order:complete {order_number} {transaction_id}` | finalise manuellement une commande (paiement, facture, emails) | **rattrapage manuel** |
| `privacy:purge-expired {--years=10} {--dry-run}` | purge légale commandes/factures/paiements | RGPD |
| `supervision:heartbeat {component}` | écrit le heartbeat | supervision |
| `supervision:heartbeat:check` | sort en échec si le heartbeat est périmé (healthcheck Docker) | supervision |
| `supervision:alert` | vérifie les sondes, envoie l'alerte | supervision |
| `supervision:report` | rapport quotidien | supervision |

> **Signal important pour la §10** : quatre de ces commandes sont des **backfills** —
> `photos:process-existing`, `photos:generate-clean-thumbnails`, `orders:backfill-customer-info` et
> `storage:clean-orphans`. Leur existence prouve que le schéma a évolué **après** la mise en
> production, et que des lignes ont pu rester dans l'état antérieur si le backfill n'a pas été joué
> jusqu'au bout. Il faudra **vérifier en base** : photos avec `is_processed = false`,
> `file_path_thumbnail_clean` nul, commandes avec `user_id` mais `guest_email` vide, fichiers MinIO
> orphelins. C'est exactement le genre d'écart qui casse une migration.
>
> `order:complete` mérite aussi une note : son existence signifie que des commandes **ont dû être
> finalisées à la main** en production. À croiser avec les données réelles en §7.

---

## 3.12. Synthèse §3 — constats et priorités

| # | Constat | Gravité | Section |
|---|---|---|---|
| 1 | `EMULATE_PREPARES` → trait `CastsBooleansForPostgres` sur 12 modèles + 20 `whereRaw` | **Élevée** | §3.9 |
| 2 | Aucun rate limit sur `login`/`register`/`forgot-password`/`orders/by-email` | **Élevée** | §3.2.2 |
| 3 | Règle d'accès « < 30 minutes » sur les commandes, dupliquée 3× | **Élevée** | §3.5.5 |
| 4 | `$this->authorize()` sans `AuthorizesRequests` → 500 sur 3 routes | Moyenne (routes mortes) | §3.5.4 |
| 5 | Aucune `Content-Security-Policy` + token Bearer en `localStorage` | **Élevée** | §3.2.6 |
| 6 | 3 Policies, 3 traitements différents, 2 quasi mortes | Moyenne | §3.5.3 |
| 7 | Aucune convention de format de réponse (4 formes dans un seul controller) | Moyenne (coût de dev) | §3.7 |
| 8 | 4 API Resources pour ~177 endpoints | Moyenne | §3.7 |
| 9 | `trustProxies(at: '*')` → IP usurpable (throttles, logs RGPD) | Moyenne | §3.2.1 |
| 10 | `UserObserver` force `clients.source = 'reservation'` → colonne non fiable | Moyenne (données) | §3.8.6 |
| 11 | `storeAsync` traite en ligne malgré son nom | Faible (clarté) | §3.11.1 |
| 12 | `ClearsEventGalleriesCache` borné aux pages 1-10 | Faible | §3.10 |
| 13 | Aucun `SoftDeletes` : toute suppression est définitive | Moyenne | §3.8.1 |
| 14 | Controllers mixtes public/admin (`adminXxx()`) — 7 fichiers | Moyenne (lisibilité) | §3.4.2 |
| 15 | `PATCH` absent de CORS alors que `apiResource` en génère | Faible | §3.2.3 |
| 16 | Jeton de supervision accepté en query string | Faible | §3.2.7 |
| 17 | 4 commandes de backfill → états intermédiaires possibles en base | **À vérifier en §10** | §3.11.3 |
| 18 | Advisory locks numérotés en dur (42, 43) sans registre | Faible | §3.8.5 |

---
# 3bis. Schéma complet de la base de données

> **Méthode et limite majeure** : ce schéma est **reconstruit par lecture des 76 migrations**
> (`api/database/migrations/`), en accumulant les altérations dans l'ordre des noms de fichier.
> **Il n'a pas été confronté à la base réelle** — `api/.env` local pointe la production, donc aucun
> `\d+`, aucun `information_schema`, aucun `SELECT` n'a été exécuté.
>
> Conséquence : tout ce document est une **hypothèse très étayée**, pas un relevé. La §3bis.7 fournit
> les requêtes SQL exactes à jouer (sur une **copie**) pour la valider avant d'écrire le script de
> migration.

## 3bis.0. Corrections apportées aux sections précédentes

| Affirmation antérieure | Réalité vérifiée |
|---|---|
| §1 : « 33 `Schema::create` → **36 tables** créées » | **38 tables** créées par 33 `Schema::create` (plusieurs migrations créent 2 ou 3 tables : `users` en crée 3, `cache` 2, `jobs` 3) |
| §1 : « aucun seeder hormis `DatabaseSeeder` (vide ou quasi) » | `DatabaseSeeder` **n'est pas vide** : il crée 2 comptes et 5 prestations — dont un **admin avec mot de passe `admin123`** (voir §3bis.6, point 12) |

---

## 3bis.1. Les 38 tables, par catégorie

| Catégorie | Tables | Nb |
|---|---|---|
| **Identité & accès** | `users`, `sessions`, `password_reset_tokens`, `personal_access_tokens` | 4 |
| **Relation client** | `clients`, `client_forms` | 2 |
| **Catalogue & réservation** | `prestations`, `reservations` | 2 |
| **Disponibilités** 🔴 | `availability_slots`, `availability_patterns` | 2 |
| **Galeries & médias** | `galleries`, `photos`, `event_categories`, `download_logs`, `photo_uploads` | 5 |
| **Tarification** | `gallery_product_types`, `pack_tiers` | 2 |
| **Boutique** | `carts`, `cart_items`, `orders`, `order_items` | 4 |
| **Paiement & facturation** | `payments`, `invoices`, `gift_cards`, `gift_codes` | 4 |
| **Photos scolaires** | `school_sessions`, `school_session_exports` | 2 |
| **Communication** | `contact_messages`, `notifications` | 2 |
| **RGPD** | `privacy_exports`, `privacy_audit_logs` | 2 |
| **Infrastructure** (régénérable) | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | 5 |
| **🔴 MORT — Laravel Cashier** | `subscriptions`, `subscription_items` | 2 |

**Verdict migration en une ligne** : **21 tables** portent des données métier à migrer, **5** sont
régénérables (infra), **4** sont mortes ou quasi (`subscriptions`, `subscription_items`,
`availability_slots`, `availability_patterns`), et **8** sont des tables de support/journalisation
dont la reprise est à arbitrer (`download_logs`, `photo_uploads`, `privacy_audit_logs`, `sessions`,
`password_reset_tokens`, `personal_access_tokens`, `client_forms`, `notifications`).

---

## 3bis.2. Identité et accès

### `users`

| Colonne | Type | Contraintes | Note |
|---|---|---|---|
| `id` | uuid | **PK** | |
| `first_name` | varchar | NOT NULL | |
| `last_name` | varchar | NOT NULL | |
| `email` | varchar | **UNIQUE**, NOT NULL | |
| `phone` | varchar | nullable | |
| `address_line1` | varchar(255) | nullable | ajout 2026-04-21 |
| `address_line2` | varchar(255) | nullable | ajout 2026-04-21 |
| `postal_code` | varchar(10) | nullable | ajout 2026-04-21 |
| `city` | varchar(100) | nullable | ajout 2026-04-21 |
| `email_verified_at` | timestamp | nullable | **jamais renseigné** (pas de flux de vérification) |
| `password` | varchar | NOT NULL | bcrypt (`'password' => 'hashed'`) |
| `role` | enum → CHECK | `admin` \| `client`, défaut `client` | |
| `remember_token` | varchar(100) | nullable | |
| `stripe_id` | varchar | nullable, **INDEX** | 🔴 **Cashier — mort** |
| `pm_type` | varchar | nullable | 🔴 **Cashier — mort** |
| `pm_last_four` | varchar(4) | nullable | 🔴 **Cashier — mort** |
| `trial_ends_at` | timestamp | nullable | 🔴 **Cashier — mort** |
| `created_at` / `updated_at` | timestamp | | |

### `sessions`, `password_reset_tokens`, `personal_access_tokens`

| Table | Colonnes | Note migration |
|---|---|---|
| `sessions` | `id` (varchar PK), `user_id` (uuid nullable, INDEX), `ip_address` (45), `user_agent` (text), `payload` (longtext), `last_activity` (int, INDEX) | **Ne pas migrer** — régénérable, contient des données personnelles (IP/UA) |
| `password_reset_tokens` | `email` (varchar PK), `token`, `created_at` | **Ne pas migrer.** Table alimentée par `forgot-password`, endpoint **sans UI** (§2.7) → probablement vide |
| `personal_access_tokens` | `id` (bigint PK), `tokenable` (`uuidMorphs` → `tokenable_type` + `tokenable_id` uuid), `name` (text), `token` (varchar(64) UNIQUE), `abilities` (text), `last_used_at`, `expires_at` (INDEX), timestamps | **Ne pas migrer** — les tokens Bearer 30 j (§3.2.4) se régénèrent à la connexion. Purgés par le cron RGPD hebdomadaire |

> ✅ `uuidMorphs` est correct ici : `tokenable_id` est bien un `uuid`, cohérent avec `users.id`.
> C'est précisément ce que les tables Cashier ont raté (§3bis.5).

---

## 3bis.3. Relation client

### `clients`

| Colonne | Type | Contraintes | Note |
|---|---|---|---|
| `id` | uuid | **PK** | |
| `user_id` | uuid | nullable, **FK** `users` → **SET NULL** | null = client sans compte |
| `name` | varchar | NOT NULL | |
| `email` | varchar | **UNIQUE**, NOT NULL | ⚠️ voir ci-dessous |
| `phone` | varchar | nullable | |
| `notes` | text | nullable | note interne admin |
| `source` | enum → CHECK | `reservation` \| `manual` \| `contact`, défaut `manual` | 🐛 **non fiable** (§3.8.6) |
| `gdpr_consent` | boolean | défaut `false` | |
| `gdpr_consent_at` | timestamp | nullable | |
| `created_at` / `updated_at` | timestamp | | |

Index : `source`, `created_at`.

> ⚠️ **`clients.email` est UNIQUE** — contrainte structurante, à ne pas perdre de vue lors de la
> migration : une même personne ne peut avoir qu'une fiche client. C'est ce qui rend
> `UserObserver` capable de « rattacher le client existant » plutôt que d'en créer un doublon.
> Corollaire : si la nouvelle structure sépare « prospect » et « client », la déduplication par email
> devra être rejouée explicitement.

> 🐛 **`clients.source` est faux pour la quasi-totalité des lignes.** Trois écritures indépendantes
> forcent `'reservation'` en dur :
> `UserObserver::created` (§3.8.6), la migration `2026_01_21_100002_migrate_existing_clients` (pour
> les users **et** pour les invités), et `2026_01_22_100000_sync_existing_users_to_clients`.
> Seuls les clients créés à la main depuis l'admin portent `manual`, et **rien n'écrit jamais
> `contact`**. La valeur `contact` de l'enum est morte.

### `client_forms`

| Colonne | Type | Contraintes |
|---|---|---|
| `id` | uuid | **PK** |
| `reservation_id` | uuid | **FK** `reservations` → **CASCADE** |
| `fullname` | varchar | NOT NULL |
| `phone` | varchar | nullable |
| `requirements` | text | nullable |
| `message` | text | nullable |
| timestamps | | |

Table créée en 2024, jamais altérée, identifiée côté front comme « anciennes reservations »
(`types/admin.ts:89`). **Rien dans le code ne crée de `ClientForm`** — seule la lecture existe (les
accesseurs en cascade de `Reservation`, §3.8). C'est un **vestige** : à quantifier en §10, puis très
probablement à abandonner en absorbant `fullname`/`phone` dans `reservations.guest_*`.

---

## 3bis.4. Catalogue, réservations, disponibilités

### `prestations`

| Colonne | Type | Contraintes | Note |
|---|---|---|---|
| `id` | uuid | **PK** | |
| `title` | varchar | NOT NULL | |
| `icon` | varchar | nullable | `portrait`, `sport`, `animal`, `moto`, `entreprise`, `video` (commentaire SQL) |
| `description` | text | nullable | |
| `features` | **json** | nullable | liste de caractéristiques |
| `price` | decimal(10,2) | **NOT NULL** | ⚠️ obligatoire même quand `price_text` = « sur devis » |
| `price_text` | varchar | nullable | prix affiché (ex. « 50 € », « sur devis ») |
| `price_unit` | varchar | nullable | ex. « À partir de » |
| `duration` | integer | nullable | minutes |
| `category` | varchar | nullable | texte libre, **pas de table de référence** |
| `background_image` | varchar | nullable | URL |
| `background_opacity` | decimal(3,2) | défaut `0.15` | |
| `disclaimer` | text | nullable | |
| `is_active` | boolean | défaut `true` | |
| `sort_order` | integer | défaut `0` | |
| timestamps | | | |

**Triple représentation du prix** (`price` numérique obligatoire + `price_text` libre + `price_unit`) :
c'est `price_text` qui est affiché quand il existe. À simplifier dans la refonte (`price` nullable +
`price_display`).

### `reservations`

| Colonne | Type | Contraintes | Note |
|---|---|---|---|
| `id` | uuid | **PK** | |
| `user_id` | uuid | **nullable**, FK `users` → CASCADE | rendu nullable en 2026-01-17 (était NOT NULL) |
| `client_id` | uuid | nullable, FK `clients` → SET NULL | ajout 2026-01-21 |
| `guest_name` | varchar | nullable | |
| `guest_email` | varchar | nullable | |
| `guest_phone` | varchar | nullable | |
| `prestation_id` | uuid | FK `prestations` → **CASCADE** | ⚠️ supprimer une prestation supprime ses réservations |
| `date` | datetime | **nullable** | rendu nullable en 2026-01-17 (était NOT NULL) |
| `status` | enum → CHECK | `pending`\|`confirmed`\|`cancelled`\|`completed`, défaut `pending` | |
| `notes` | text | nullable | interne admin |
| `message` | text | nullable | message du client |
| `date_preferences` | text | nullable | **texte libre** — remplace de fait le choix de créneau |
| `availability_slot_id` | uuid | nullable, **INDEX mais AUCUNE FK** | ⚠️ |
| timestamps | | | |

Index : `status`, `(status, date)`, `created_at`, `availability_slot_id`.

> ⚠️ **`prestation_id` en CASCADE** est dangereux : supprimer une prestation efface silencieusement
> l'historique des réservations qui la référencent. Aucune UI ne prévient. À passer en `RESTRICT` ou
> `SET NULL` dans la refonte.
>
> ⚠️ **`availability_slot_id` n'a pas de contrainte de clé étrangère** (seul un index). Des valeurs
> orphelines sont possibles sans que la base ne s'en plaigne. Sans conséquence aujourd'hui (module
> mort), mais à vérifier avant migration.

### `availability_slots` / `availability_patterns` 🔴

| `availability_slots` | Type |
|---|---|
| `id` | uuid **PK** |
| `date` | date |
| `start_time` / `end_time` | time |
| `duration_minutes` | integer, défaut 60 |
| `status` | enum → CHECK `available`\|`booked`\|**`unavailable`** (défaut `available`) |
| `reservation_id` | uuid nullable, **INDEX mais AUCUNE FK** |
| `notes` | text nullable |
| timestamps | |

Index : `(date, status)`, `reservation_id`.

| `availability_patterns` | Type |
|---|---|
| `id` | uuid **PK** |
| `name` | varchar |
| `days_of_week` | **json** — `[1..7]`, 1 = lundi |
| `start_time` / `end_time` | time |
| `slot_duration_minutes` | integer, défaut 60 |
| `repeat_every_weeks` | integer, défaut 1 |
| `start_date` | date |
| `end_date` | date nullable |
| `is_active` | boolean, défaut true |
| timestamps | |

> Le statut `unavailable` existe en base mais **le modèle ne l'utilise jamais** (`scopeAvailable`,
> `scopeBooked` seulement). Module entièrement mort (§2.8) : ces 2 tables sont très probablement
> **vides** en production. À confirmer par un simple `COUNT(*)` (§3bis.7) — si elles le sont, la
> décision « finir ou supprimer » devient purement produit, sans enjeu de reprise de données.

---

## 3bis.5. Galeries et médias

### `galleries` — la table pivot

Reconstruite à partir de **13 migrations** (1 création + 12 altérations). État final :

| Colonne | Type | Contraintes | Ajoutée |
|---|---|---|---|
| `id` | uuid | **PK** | création |
| `user_id` | uuid | nullable, FK `users` → SET NULL | création |
| `assigned_email` | varchar | nullable, **INDEX** | 2026-01-22 |
| `title` | varchar | NOT NULL | création |
| `description` | text | nullable | création |
| `event_date` | date | nullable | 2026-01-17 |
| `event_link` | varchar | nullable | 2026-01-17 |
| `thumbnail_photo_id` | uuid | nullable, FK `photos` → SET NULL | 2026-02-04 |
| `type` | varchar + **CHECK** | `public`\|`private`\|`event`, défaut `private` | création (+ CHECK réécrit en SQL brut 2026-01-17) |
| `event_category_id` | uuid | nullable, FK `event_categories` → SET NULL | 2026-02-23 |
| `sort_order` | integer | défaut `0` | 2026-02-23 |
| `is_published` | boolean | défaut `true` | 2026-04-05 |
| `parent_id` | uuid | nullable, FK `galleries` → **CASCADE** | 2026-02-24 |
| `school_session_id` | uuid | nullable, FK `school_sessions` → **CASCADE** | 2026-04-26 |
| `class_name` | varchar | nullable | 2026-05-01 |
| `shipping_fee` | decimal(8,2) | nullable (null → `config('shop.shipping_fee_print')`) | 2026-06-19 |
| `access_token` | varchar | **UNIQUE**, nullable | création |
| `share_code` | varchar(6) | **UNIQUE**, nullable | création |
| `sms_template` | text | nullable (null → gabarit par défaut) | 2026-05-18 |
| `views_count` | unsigned integer | défaut `0` | création |
| `last_viewed_at` | timestamp | nullable | création |
| timestamps | | | création |

**Colonnes supprimées** (migration `2026_01_17_131837_simplify_galleries_table`) :
`expiration_at`, `cover_image`, `is_active`.

Index : `assigned_email`, `type`, `(type, created_at)`.

> ⚠️ **Le CHECK de `type` a été réécrit en SQL brut**, pas via le Schema builder :
> ```sql
> ALTER TABLE galleries DROP CONSTRAINT IF EXISTS galleries_type_check;
> ALTER TABLE galleries ADD CONSTRAINT galleries_type_check
>   CHECK (type::text = ANY (ARRAY['public'::text,'private'::text,'event'::text]));
> ```
> Le nom de contrainte `galleries_type_check` est **une convention Postgres/Laravel**, pas une
> garantie. Sur la base de production, le nom réel peut différer. À vérifier (§3bis.7) avant tout
> script qui toucherait à cette colonne.

> ⚠️ **`parent_id` en CASCADE auto-référentiel** : supprimer une galerie événement parente supprime
> toutes ses sous-galeries — **et par cascade toutes leurs photos, `download_logs` et
> `photo_uploads`**. Idem pour `school_session_id` : supprimer une session scolaire efface toutes les
> galeries des enfants. C'est voulu (`SchoolSessionService::deleteSessionFiles()` nettoie MinIO
> **avant** le delete SQL), mais **aucun `SoftDeletes`** ne protège d'une fausse manœuvre.

### `photos`

Reconstruite à partir de **5 migrations**. État final :

| Colonne | Type | Contraintes | Ajoutée |
|---|---|---|---|
| `id` | uuid | **PK** | création |
| `gallery_id` | uuid | FK `galleries` → **CASCADE** | création |
| `file_path` | varchar | **NOT NULL** | création |
| `file_path_web` | varchar | nullable — *« Optimized web version »* | création |
| `file_path_hd` | varchar | nullable — *« HD version for download »* | création |
| `file_path_watermark` | varchar | nullable — *« Watermarked version »* | création |
| `file_path_preview` | varchar | nullable | 2026-02-02 |
| `file_path_thumbnail` | varchar | nullable | 2026-02-02 |
| `file_path_thumbnail_clean` | varchar | nullable | 2026-07-08 |
| `is_processed` | boolean | défaut `false` | 2026-02-02 |
| `is_video` | boolean | défaut `false` | création |
| `title` | varchar | nullable | création |
| `description` | text | nullable | création |
| `sort_order` | integer | défaut `0` | création |
| `is_liked` | boolean | défaut `false` | création |
| `is_downloadable` | boolean | défaut `false` | création |
| `price` | decimal(10,2) | nullable | 2026-01-29 |
| `is_purchasable` | boolean | défaut `true` | 2026-01-29 |
| `downloads_count` | unsigned integer | défaut `0` | 2025-01-23 |
| `metadata` | **json** | nullable — *« EXIF data, dimensions, etc. »* | création |
| timestamps | | | création |

Index : `is_downloadable`, `is_liked`, `(gallery_id, sort_order)`.

> 🔴 **Deux colonnes de chemin sont mortes** : `file_path_web` et `file_path_watermark` sont dans le
> `$fillable` du modèle mais **jamais écrites** — `PhotoController::processSinglePhoto` ne renseigne
> que `file_path`, `file_path_hd`, `file_path_preview`, `file_path_thumbnail`.
> `file_path_web` n'est lu que par l'accesseur `web_url` (lui-même sans appelant).
> À confirmer par `COUNT(*) WHERE file_path_web IS NOT NULL` (§3bis.7) puis supprimer.

> ⚠️ **`metadata` (json) est un schéma caché.** On y trouve au minimum : `original_filename`, `size`,
> `mime_type`, `storage_path`, et — pour les photos héritées — **`supabase_path`**. La chaîne de
> résolution du fichier original (§2.3) en dépend :
> `file_path_hd → metadata->storage_path → metadata->supabase_path → file_path`.
> **C'est le point de rupture le plus probable de la migration des fichiers.** Requêtes de
> quantification en §3bis.7.

### `event_categories`

`id` (uuid PK), `name` (varchar), `slug` (varchar **UNIQUE**), `description` (text nullable),
`sort_order` (integer, défaut 0), timestamps. Jamais altérée.

### `download_logs`

| Colonne | Type | Contraintes |
|---|---|---|
| `id` | uuid | **PK** |
| `photo_id` | uuid | FK `photos` → **CASCADE** |
| `gallery_id` | uuid | FK `galleries` → **CASCADE** |
| `ip_address` | varchar(45) | nullable |
| `user_agent` | varchar | nullable |
| `downloaded_at` | timestamp | **NOT NULL** |

Index : `(gallery_id, downloaded_at)`, `(photo_id, downloaded_at)`.
**Pas de `created_at`/`updated_at`** (`public $timestamps = false` dans le modèle).

> **Données personnelles** (IP + user agent), purgées à 12 mois par le cron RGPD (§2.12).
> **Décision de migration à prendre** : reprendre l'historique (utile pour `downloads_count`) ou
> repartir de zéro (moins de données personnelles à justifier). Recommandation : ne pas migrer, et
> conserver seulement les compteurs agrégés déjà présents sur `photos.downloads_count`.

### `photo_uploads`

| Colonne | Type | Contraintes |
|---|---|---|
| `id` | uuid | **PK** |
| `batch_id` | varchar | **INDEX** |
| `gallery_id` | uuid | FK `galleries` → **CASCADE** |
| `original_filename` | varchar | NOT NULL |
| `status` | enum → CHECK | `pending`\|`uploading`\|`processing`\|`completed`\|`failed`, défaut `pending` |
| `error_message` | text | nullable |
| `photo_id` | uuid | nullable, **AUCUNE FK** |
| `completed_at` | timestamp | nullable |
| timestamps | | |

Index : `batch_id`, `(batch_id, status)`.

Table de **suivi de traitement**, pas de données métier : ne pas migrer (sauf pour diagnostiquer les
échecs en cours). `photo_id` sans FK → orphelins possibles après suppression de photos.

---

## 3bis.6. Tarification

### `gallery_product_types`

| Colonne | Type | Contraintes |
|---|---|---|
| `id` | uuid | **PK** |
| `gallery_id` | uuid | FK `galleries` → **CASCADE** |
| `product_type` | varchar | NOT NULL — *« digital, print_10x15, print_15x20 »* (commentaire **obsolète** : `print_scolaire` manque) |
| `is_enabled` | boolean | défaut `true` |
| `price` | decimal(**8**,2) | nullable (null → constante `CartItem::PRODUCT_TYPES`) |
| `requires_shipping` | boolean | **nullable** (null → règle statique) — ajout 2026-05-18 |
| timestamps | | |

**UNIQUE** `(gallery_id, product_type)`.

> ⚠️ **`product_type` est un `varchar` libre, sans CHECK ni table de référence.** La seule contrainte
> est la validation applicative (`in:digital,print_10x15,print_15x20,print_scolaire` dans le trait
> `SyncsProductTypes`). Rien n'empêche une valeur arbitraire en base. À vérifier avant migration :
> `SELECT DISTINCT product_type` sur les 3 tables concernées (`gallery_product_types`, `cart_items`,
> `order_items`).

> ⚠️ **`requires_shipping` nullable est un booléen à trois états** : `true` / `false` / `null`
> (= « hériter de la règle statique »). Un script de migration qui normaliserait les null en `false`
> **changerait la facturation** des frais de port. À traiter explicitement.

### `pack_tiers`

`id` (uuid PK), `gallery_product_type_id` (uuid, FK → **CASCADE**), `min_quantity` (integer),
`unit_price` (decimal(8,2)), timestamps. **UNIQUE** `(gallery_product_type_id, min_quantity)`.

Pas de CHECK sur `min_quantity >= 2` (seule la validation applicative l'impose), ni de limite à
3 paliers (idem, applicative).

---

## 3bis.7. Boutique

### `carts`

`id` (uuid PK), `user_id` (uuid nullable, FK `users` → **CASCADE**), `session_id` (varchar nullable),
`guest_email` (varchar nullable), `gift_code_id` (uuid nullable, FK `gift_codes` → **SET NULL**),
`status` (enum → CHECK `active`\|`converted`\|`expired`, défaut `active`), `expires_at` (timestamp
nullable), timestamps.

Index : `(session_id, status)`, `(user_id, status)`.

> **Aucune contrainte n'impose « un seul panier actif par utilisateur »** — c'est
> `CartService::getOrCreateCart()` qui le garantit applicativement (§2.5), précisément parce que
> l'invariant avait été violé en production. Une **contrainte unique partielle** serait la bonne
> réponse dans la refonte :
> `CREATE UNIQUE INDEX ON carts (user_id) WHERE status = 'active' AND user_id IS NOT NULL;`

### `cart_items`

`id` (uuid PK), `cart_id` (uuid, FK → **CASCADE**), `photo_id` (uuid, FK `photos` → **CASCADE**),
`product_type` (varchar, défaut `digital`), `quantity` (unsigned integer, défaut 1),
`price` (decimal(10,2)), timestamps.

**UNIQUE** `(cart_id, photo_id, product_type)` — la contrainte initiale `(cart_id, photo_id)` a été
**supprimée puis remplacée** lors de l'ajout des variantes produit (2026-01-29).

### `orders`

Reconstruite à partir de **6 migrations**. État final :

| Colonne | Type | Contraintes | Ajoutée |
|---|---|---|---|
| `id` | uuid | **PK** | création |
| `user_id` | uuid | nullable, FK `users` → SET NULL | création |
| `cart_id` | uuid | nullable, FK `carts` → SET NULL | création |
| `gift_code_id` | uuid | nullable, FK `gift_codes` → SET NULL | 2026-06-03 |
| `gift_code` | varchar(24) | nullable — **snapshot du code (audit)** | 2026-06-03 |
| `guest_email` | varchar | nullable | création |
| `guest_first_name` | varchar | nullable | 2026-03-31 |
| `guest_last_name` | varchar | nullable | 2026-03-31 |
| `shipping_phone` | varchar(20) | nullable | 2026-04-21 |
| `shipping_address_line1` | varchar(255) | nullable | 2026-04-21 |
| `shipping_address_line2` | varchar(255) | nullable | 2026-04-21 |
| `shipping_postal_code` | varchar(10) | nullable | 2026-04-21 |
| `shipping_city` | varchar(100) | nullable | 2026-04-21 |
| `shipping_country` | varchar(2) | défaut `'FR'` | 2026-04-21 |
| `order_number` | varchar | **UNIQUE**, NOT NULL, **INDEX** | création |
| `subtotal` | decimal(10,2) | NOT NULL | création |
| `discount_amount` | decimal(10,2) | défaut `0` | 2026-06-03 |
| `shipping_fee` | decimal(10,2) | défaut `0` | 2026-04-21 |
| `total` | decimal(10,2) | NOT NULL | création |
| `currency` | varchar(3) | défaut `'EUR'` | création |
| `status` | enum → CHECK | `pending`\|`paid`\|`failed`\|**`refunded`**\|`expired`, défaut `pending`, **INDEX** | création |
| `print_status` | varchar | nullable (`'shipped'` ou null) | 2026-02-06 |
| `shipped_at` | timestamp | nullable | 2026-02-06 |
| `sumup_checkout_id` | varchar | nullable, **INDEX** | création |
| `sumup_transaction_id` | varchar | nullable | création |
| `metadata` | **json** | nullable | création |
| `cgv_accepted` | boolean | défaut `false` | 2026-02-04 |
| `cgv_accepted_at` | timestamp | nullable | 2026-02-04 |
| `cgv_version` | varchar(20) | défaut `'1.0'` | 2026-02-04 |
| `consent_ip` | varchar(45) | nullable | 2026-02-04 |
| `paid_at` | timestamp | nullable | création |
| timestamps | | | création |

**Colonne supprimée** : `guest_name` (scindée en `guest_first_name`/`guest_last_name`, 2026-03-31).

> 🔴 **`status = 'refunded'` est mort** : la valeur existe dans le CHECK, mais **aucun code ne
> l'écrit ni ne la lit** (le modèle ne connaît que `pending`/`paid`/`failed`/`expired`, et
> `detailed_status` ne la mentionne pas). Un remboursement n'est donc **pas représentable** dans le
> système actuel — à traiter comme un manque fonctionnel en §7, pas seulement comme du schéma mort.

> ⚠️ **`orders.metadata` porte le token de téléchargement** (`download_token`,
> `download_token_expires_at`) — donc un **secret d'accès dans une colonne JSON**, non indexé, non
> chiffré. C'est aussi là que le contrôle d'accès aux fichiers achetés se joue (§3.5). À sortir dans
> une colonne dédiée (ou une table de tokens) dans la refonte.

> ⚠️ **La scission `guest_name` → `guest_first_name` + `guest_last_name`** a été faite par un
> `explode(' ', $name, 2)` : « Marie Claire Dupont » est devenu prénom = « Marie », nom = « Claire
> Dupont ». Les données antérieures à 2026-03-31 sont donc **approximatives par construction** — à
> ne pas « re-corriger » aveuglément lors de la migration.

### `order_items`

`id` (uuid PK), `order_id` (uuid, FK → **CASCADE**), `photo_id` (uuid nullable, FK `photos` →
**SET NULL**), `product_type` (varchar, défaut `digital`), `quantity` (unsigned integer, défaut 1),
`photo_title` (varchar nullable), `gallery_title` (varchar nullable), `price` (decimal(10,2)),
`is_downloaded` (boolean, défaut false), `downloaded_at` (timestamp nullable), timestamps.

> ✅ **Bonne conception** : `photo_id` en `SET NULL` + dénormalisation de `photo_title` et
> `gallery_title`. Une facture reste lisible après suppression de la photo. **À conserver.**
> Contrepartie : `order_items.photo_id = NULL` signifie « photo supprimée », pas « erreur » — le
> script de migration ne doit pas les rejeter.
>
> Contrairement à `cart_items`, **aucune contrainte UNIQUE** sur `(order_id, photo_id, product_type)`.

---

## 3bis.8. Paiement, facturation, codes

### `payments`

`id` (uuid PK), `reservation_id` (uuid nullable, FK → SET NULL), `order_id` (uuid nullable, FK
`orders` → SET NULL, ajout 2026-01-29), `user_id` (uuid nullable, FK → SET NULL),
`provider` (varchar NOT NULL — *commentaire : « stripe, paypal, etc. »*), `provider_payment_id`
(varchar nullable), `amount` (decimal(10,2)), `currency` (varchar(3), défaut EUR),
`status` (enum → CHECK `pending`\|`completed`\|`failed`\|`refunded`, défaut `pending`),
`type` (varchar nullable — *« deposit, full, gift_card »*), `metadata` (json nullable), timestamps.

Index : `status`, `(status, created_at)`.

> Table **polyvalente et floue** : elle peut pointer une réservation **ou** une commande, avec un
> `provider` libre dont le commentaire mentionne encore Stripe/PayPal. `Client::getTotalPaidAttribute()`
> ne compte que les paiements liés aux **réservations** avec `status = 'completed'`.
> À vérifier en §7 : quelles valeurs de `provider` et de `type` existent réellement, et si des
> paiements de commande y sont écrits (le flux SumUp passe surtout par `orders.sumup_*`).

### `invoices`

`id` (uuid PK), `order_id` (uuid, FK `orders` → **CASCADE**, **INDEX**),
`invoice_number` (varchar **UNIQUE**), `file_path` (varchar nullable), `generated_at` (timestamp
nullable), timestamps.

> ⚠️ **`order_id` en CASCADE sur une pièce comptable.** `DELETE /admin/orders/{order}` existe
> (§2.5) : supprimer une commande **détruit sa facture**, alors que l'obligation légale est de la
> conserver ~10 ans — c'est même la règle « CONSERVER » de `PersonalDataEraser` (§2.12).
> **Contradiction directe entre le schéma et la politique RGPD/comptable du projet.**
> À corriger en priorité : `RESTRICT` + archivage, ou `SoftDeletes` sur `orders`.

### `gift_cards`

`id` (uuid PK), `payment_id` (uuid nullable, **AUCUNE FK**), `code` (varchar **UNIQUE**),
`amount` (decimal(10,2)), `remaining_amount` (decimal(10,2) **NOT NULL**),
`recipient_name` / `recipient_email` (varchar nullable), `message` (text nullable),
`pdf_path` (varchar nullable — 🔴 **jamais alimenté**, §2.9),
`status` (enum → CHECK `active`\|`used`\|`expired`\|`cancelled`, défaut `active`),
`expires_at` / `used_at` (timestamp nullable), timestamps.

> `payment_id` sans FK, et **aucun code ne relie réellement un `gift_card` à un `payment`** (le
> `store` est mort, §2.9). La valeur d'enum `cancelled` n'est jamais écrite non plus.

### `gift_codes`

`id` (uuid PK), `code` (varchar(24) **UNIQUE**, **INDEX**, stocké en MAJUSCULES),
`type` (enum → CHECK `fixed`\|`percent`, défaut `fixed`),
`value` (decimal(10,2) — euros si `fixed`, 0-100 si `percent`),
`max_discount_amount` (decimal(10,2) nullable — plafond, `percent` uniquement),
`valid_from` / `valid_until` (timestamp nullable),
`max_uses` (unsigned integer nullable, **défaut 1**, null = illimité),
`is_active` (boolean, défaut true, **INDEX**), `note` (text nullable), timestamps.

> ⚠️ **`max_uses` n'est pas décrémenté en base** : le quota est calculé à la volée par
> `paidCount()` = `COUNT(orders WHERE gift_code_id = … AND status = 'paid')` (§2.5). C'est robuste
> (pas de compteur à re-synchroniser) mais **couplé à `orders`** : si la migration filtre ou
> anonymise des commandes, les quotas de codes promo changent silencieusement. À vérifier en §10.
>
> `code` est un `varchar(24)` alors que les codes générés font 6 caractères — dimensionnement large
> pour accepter des codes saisis à la main. Le snapshot `orders.gift_code` est aussi en varchar(24).

---

## 3bis.9. Photos scolaires

### `school_sessions`

`id` (uuid PK), `title` (varchar), `event_date` (date nullable),
`status` (**varchar** NOT NULL, défaut `'uploading'` — *pas un enum*),
`total_galleries` / `total_photos` / `processed_photos` (unsigned integer, défaut 0),
`batch_id` (varchar nullable, **INDEX**), `zip_path` (varchar nullable),
`error_message` (text nullable), `product_types_config` (**json** nullable),
`sms_template` (text nullable, 2026-05-18), `gallery_message` (text nullable, 2026-05-01),
`closed_at` (timestamp nullable, 2026-05-01), timestamps.

> `status` est un **varchar libre** (contrairement à `photo_uploads.status` qui est un enum). Les
> 6 valeurs (`uploading`, `extracting`, `creating_galleries`, `processing_photos`, `completed`,
> `failed`) ne vivent que dans la constante PHP `SchoolSession::STATUSES`. Aucune contrainte en base.
>
> **`product_types_config` (json) est un schéma caché de plus** : il reproduit la structure
> `gallery_product_types` + `pack_tiers` sous forme JSON, appliquée à chaque galerie enfant à la
> création (`SchoolSessionService::syncProductTypesFromConfig`). Deux représentations du même
> concept — une relationnelle, une JSON — à unifier dans la refonte.

### `school_session_exports`

`id` (uuid PK), `school_session_id` (uuid, FK → **CASCADE**, **INDEX**),
`status` (varchar, défaut `pending`), `include_digital` (boolean, défaut false),
`file_path` (varchar nullable), `file_size_bytes` (unsigned bigint nullable),
`total_items` / `processed_items` (unsigned integer, défaut 0), `error_message` (text nullable),
timestamps.

Table de suivi de job : **ne pas migrer** (les fichiers ZIP vivent sur le disque `local`, dans
`storage/app/private/exports/` — donc **persistants via le bind mount**, cf. correction §10.0, et
régénérables à la demande).

---

## 3bis.10. Communication et RGPD

### `contact_messages`

`id` (uuid PK), `name` (varchar), `email` (varchar, **INDEX**), `phone` (varchar(30) nullable),
`subject` (varchar), `message` (text), `gdpr_consent` (boolean, défaut false),
`gdpr_consent_at` (timestamp nullable), `consent_ip` (varchar(45) nullable),
`consent_user_agent` (varchar nullable), `email_sent_to_admin` (boolean, défaut false),
`email_sent_to_user` (boolean, défaut false), timestamps (`created_at` **INDEX**).

Purgée à 12 mois par le cron RGPD. **Traçabilité du consentement complète** — bon modèle à reprendre.

### `notifications`

`id` (uuid PK), `user_id` (uuid, FK `users` → **CASCADE**), `type` (varchar),
`title` (varchar nullable, ajout 2025-01-24), `message` (text), `data` (json nullable),
`read_at` (timestamp nullable), timestamps.

Index : `(user_id, read_at)`, `created_at`.

> Implémentation **maison** — à ne pas confondre avec la table `notifications` de Laravel
> (`notifiable_type`/`notifiable_id`/`data`). Le modèle `User` utilise pourtant le trait `Notifiable`,
> qui définit une relation `notifications()`… **surchargée** dans le modèle par un `hasMany` maison.
> Piège si la refonte adopte les notifications natives : **le nom de table entre en collision.**
>
> `type` est un varchar libre : la seule valeur écrite est `'new_reservation'` (§2.14).

### `privacy_audit_logs`

`id` (uuid PK), `actor_user_id` (uuid nullable, FK `users` → SET NULL),
`action` (varchar, **INDEX** — `search`\|`export`\|`erasure`), `subject_type` (varchar nullable —
`email`\|`phone`\|`order_number`), `subject_value` (varchar nullable), `affected` (json nullable),
`ip_address` (varchar(45) nullable), timestamps (`created_at` **INDEX**).

> ⚠️ **À migrer impérativement.** C'est la **preuve de conformité RGPD** : un journal d'audit qui perd
> son historique perd sa raison d'être. Et il contient lui-même des données personnelles
> (`subject_value` = un email ou un téléphone), ce qui est normal et légitime, mais à documenter.

### `privacy_exports`

`id` (uuid PK), `type` (varchar, défaut `global`, **INDEX**), `subject_type` / `subject_value`
(varchar nullable), `status` (varchar, défaut `pending`, **INDEX**),
`total_items` / `processed_items` (integer, défaut 0), `file_path` (varchar nullable),
`file_size_bytes` (bigint nullable), `error_message` (text nullable),
`requested_by` (uuid nullable, FK `users` → SET NULL), timestamps (`created_at` **INDEX**).

Table de suivi + ZIP sur disque `local` : ne pas migrer les fichiers, éventuellement l'historique.

---

## 3bis.11. Infrastructure — régénérable, à ne pas migrer

| Table | Colonnes | PK |
|---|---|---|
| `cache` | `key`, `value` (mediumtext), `expiration` (int) | `key` |
| `cache_locks` | `key`, `owner`, `expiration` | `key` |
| `jobs` | `id` (bigint), `queue` (INDEX), `payload` (longtext), `attempts` (tinyint), `reserved_at`, `available_at`, `created_at` | `id` |
| `job_batches` | `id` (varchar), `name`, `total_jobs`, `pending_jobs`, `failed_jobs`, `failed_job_ids`, `options`, `cancelled_at`, `created_at`, `finished_at` | `id` |
| `failed_jobs` | `id` (bigint), `uuid` (UNIQUE), `connection`, `queue`, `payload`, `exception`, `failed_at` | `id` |

> **Avant migration** : vider la queue (`jobs` à 0) et **lire `failed_jobs`** — c'est le seul endroit
> où l'on verra quels traitements photo/export ont définitivement échoué en production. À faire
> **avant** toute bascule.
>
> Note : `cache` contient les **heartbeats de supervision** et les **cooldowns d'alerte** (§2.13).
> Vider le cache déclenche mécaniquement des alertes « composant silencieux » — prévoir la fenêtre.

---

## 3bis.12. 🔴 Schéma mort : les vestiges Laravel Cashier (Stripe)

Six migrations datées **2025-12-18** installent Laravel Cashier :

| Migration | Effet |
|---|---|
| `create_customer_columns` | ajoute `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at` à `users` |
| `create_subscriptions_table` | crée `subscriptions` |
| `create_subscription_items_table` | crée `subscription_items` |
| `add_meter_id_to_subscription_items_table` | ajoute `meter_id` |
| `add_meter_event_name_to_subscription_items_table` | ajoute `meter_event_name` |

**Rien de tout cela n'est utilisé** : `composer.json` ne contient pas `laravel/cashier`, aucun modèle
`Subscription` n'existe, aucune référence à `stripe` dans `app/` hors le commentaire de
`payments.provider`. C'est **la trace concrète de la stack Stripe annoncée par le README** (§1.8),
abandonnée au profit de SumUp.

**Et ces tables sont structurellement cassées** :

```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();                      // bigint auto-increment
    $table->foreignId('user_id');      // bigint  ← users.id est un UUID !
    ...
```

`subscriptions.user_id` est un **bigint** alors que `users.id` est un **uuid**. La colonne ne peut
donc **jamais** référencer un utilisateur (il n'y a d'ailleurs aucune contrainte FK déclarée, ce qui
est la seule raison pour laquelle la migration passe). Ces deux tables sont vides et inutilisables.

**Verdict** : supprimer les 2 tables et les 4 colonnes de `users`. Aucun risque, aucune donnée.
C'est le nettoyage le plus simple et le plus sûr de tout le projet.

---

## 3bis.13. Vue d'ensemble des contraintes

### Clés étrangères — 33 déclarées

| Comportement | Nb | Tables concernées |
|---|---|---|
| **CASCADE** | 18 | `photos.gallery_id`, `galleries.parent_id`, `galleries.school_session_id`, `cart_items.cart_id`, `cart_items.photo_id`, `order_items.order_id`, `invoices.order_id`, `download_logs.*`, `photo_uploads.gallery_id`, `gallery_product_types.gallery_id`, `pack_tiers.gallery_product_type_id`, `school_session_exports.school_session_id`, `client_forms.reservation_id`, `notifications.user_id`, `reservations.user_id`, `reservations.prestation_id`, `carts.user_id` |
| **SET NULL** | 15 | `galleries.user_id`, `galleries.thumbnail_photo_id`, `galleries.event_category_id`, `clients.user_id`, `reservations.client_id`, `orders.user_id`, `orders.cart_id`, `orders.gift_code_id`, `carts.gift_code_id`, `order_items.photo_id`, `payments.*` (3), `privacy_audit_logs.actor_user_id`, `privacy_exports.requested_by` |

**Colonnes de type FK SANS contrainte** (orphelins possibles) :
`availability_slots.reservation_id`, `reservations.availability_slot_id`, `photo_uploads.photo_id`,
`gift_cards.payment_id`, `subscriptions.user_id`, `subscription_items.subscription_id`.

### Chaînes de suppression en cascade — à connaître avant tout `DELETE`

```
DELETE galleries (parent événement)
  └→ galleries (enfants, parent_id CASCADE)
       └→ photos CASCADE
            ├→ cart_items CASCADE
            └→ download_logs CASCADE
       ├→ gallery_product_types CASCADE → pack_tiers CASCADE
       ├→ download_logs CASCADE
       └→ photo_uploads CASCADE

DELETE school_sessions
  └→ galleries CASCADE  (→ toute la chaîne ci-dessus)
  └→ school_session_exports CASCADE

DELETE orders
  ├→ order_items CASCADE
  └→ invoices CASCADE          ⚠️ pièce comptable détruite

DELETE prestations
  └→ reservations CASCADE      ⚠️ historique détruit
       └→ client_forms CASCADE
```

> **Deux cascades sont des bugs de conception** : `orders → invoices` (contredit la rétention légale)
> et `prestations → reservations` (perte d'historique sur une simple suppression de catalogue).
> Aucune n'est protégée par un `SoftDeletes`, aucune n'affiche d'avertissement dans l'UI.

### Les 11 enums → CHECK constraints Postgres

`$table->enum()` produit en Postgres un `varchar` + une contrainte `CHECK`, dont **le nom est généré**
(`<table>_<colonne>_check`).

| Table.colonne | Valeurs | Valeurs mortes |
|---|---|---|
| `users.role` | `admin`, `client` | — |
| `galleries.type` | `public`, `private`, `event` | — (CHECK réécrit à la main) |
| `clients.source` | `reservation`, `manual`, `contact` | 🔴 `contact` jamais écrit |
| `reservations.status` | `pending`, `confirmed`, `cancelled`, `completed` | — |
| `availability_slots.status` | `available`, `booked`, `unavailable` | 🔴 `unavailable` jamais utilisé |
| `photo_uploads.status` | `pending`, `uploading`, `processing`, `completed`, `failed` | — |
| `carts.status` | `active`, `converted`, `expired` | — |
| `orders.status` | `pending`, `paid`, `failed`, `refunded`, `expired` | 🔴 `refunded` jamais écrit |
| `payments.status` | `pending`, `completed`, `failed`, `refunded` | à vérifier (§7) |
| `gift_cards.status` | `active`, `used`, `expired`, `cancelled` | 🔴 `cancelled` jamais écrit |
| `gift_codes.type` | `fixed`, `percent` | — |

> ⚠️ **Piège de migration** : `ALTER TYPE`/`ALTER TABLE … DROP CONSTRAINT` exige le **nom exact** de
> la contrainte, qui peut différer de la convention sur une base ayant vécu. À relever avant d'écrire
> le script (requête fournie en §3bis.14).
>
> Les statuts stockés en **varchar libre** (`school_sessions.status`, `school_session_exports.status`,
> `privacy_exports.status`, `orders.print_status`, `notifications.type`, `payments.type`,
> `gallery_product_types.product_type`) n'ont **aucune** contrainte : leurs valeurs réelles doivent
> être relevées, pas supposées.

### Contraintes UNIQUE

| Table | Colonnes |
|---|---|
| `users` | `email` |
| `clients` | `email` |
| `galleries` | `access_token`, `share_code` |
| `event_categories` | `slug` |
| `orders` | `order_number` |
| `invoices` | `invoice_number` |
| `gift_cards` | `code` |
| `gift_codes` | `code` |
| `personal_access_tokens` | `token` |
| `failed_jobs` | `uuid` |
| `cart_items` | `(cart_id, photo_id, product_type)` |
| `gallery_product_types` | `(gallery_id, product_type)` |
| `pack_tiers` | `(gallery_product_type_id, min_quantity)` |
| `subscriptions` / `subscription_items` | `stripe_id` 🔴 |

### Précisions décimales — incohérentes

| Précision | Colonnes |
|---|---|
| `decimal(10,2)` | `prestations.price`, `photos.price`, `cart_items.price`, `order_items.price`, `orders.{subtotal,total,shipping_fee,discount_amount}`, `payments.amount`, `gift_cards.{amount,remaining_amount}`, `gift_codes.{value,max_discount_amount}` |
| `decimal(8,2)` | `galleries.shipping_fee`, `gallery_product_types.price`, `pack_tiers.unit_price` |
| `decimal(3,2)` | `prestations.background_opacity` |

Sans impact fonctionnel (les montants restent très en dessous de 10⁶), mais à uniformiser.

### Colonnes JSON — 8 schémas implicites

| Colonne | Contenu réel |
|---|---|
| `photos.metadata` | `original_filename`, `size`, `mime_type`, `storage_path`, **`supabase_path`** (hérité) |
| `orders.metadata` | `download_token`, `download_token_expires_at` |
| `payments.metadata` | à relever |
| `notifications.data` | `reservation_id`, `client_name`, `prestation` |
| `prestations.features` | tableau de chaînes |
| `school_sessions.product_types_config` | miroir JSON de `gallery_product_types` + `pack_tiers` |
| `availability_patterns.days_of_week` | tableau d'entiers 1-7 |
| `privacy_audit_logs.affected` | compteurs par table |

> Ces 8 colonnes sont **le principal angle mort du schéma** : aucune contrainte, aucune
> documentation, et deux d'entre elles portent de la logique critique (résolution des fichiers,
> token d'accès). Chacune doit être inspectée en base (§3bis.14) avant migration.

---

## 3bis.14. ⚠️ Rejouer les 76 migrations ne reproduit pas la base de production

C'est le point le plus important de cette section pour la §10.

**Preuve dans le code** — la migration `2025_01_24_000002_add_performance_indexes` porte ce
commentaire et ce garde-fou :

```php
// Sur une installation fraîche, cette migration (2025) s'exécute AVANT
// la création de la table clients (2026) : on saute alors ce bloc, les
// index étant créés directement dans create_clients_table. Sur les bases
// déjà migrées où clients existait, le bloc s'applique normalement.
if (Schema::hasTable('clients')) { … }
```

Autrement dit : **le chemin « installation fraîche » et le chemin « base de production » divergent**,
et le code le sait. Laravel exécute les migrations dans l'ordre **des noms de fichier**, pas dans
l'ordre où elles ont été écrites. Or plusieurs migrations datées 2024/2025 ont été ajoutées ou
modifiées après des migrations datées 2026.

Autres sources de divergence identifiées :

1. **Migrations de données non idempotentes** — `migrate_existing_clients` et
   `sync_existing_users_to_clients` créent des lignes `clients` à partir de l'état de `users` et
   `reservations` **au moment où elles tournent**. Sur une base fraîche (vide), elles ne créent rien.
   Le contenu de `clients` en production est donc **le produit d'une histoire**, pas d'un schéma.
2. **Migration destructive de données** — `split_guest_name_into_first_and_last_name_on_orders`
   fait un `explode()` puis `dropColumn('guest_name')`. L'information originale est **perdue**.
3. **Garde-fous `hasColumn`** — `add_message_to_reservations` et `add_note_to_gift_codes` sont
   conditionnées par `Schema::hasColumn()`, ce qui signale que la colonne existait déjà sur certaines
   bases (ajoutée hors migration ?).
4. **SQL brut** — le CHECK de `galleries.type` a été posé par `DB::statement()`, donc avec un nom de
   contrainte qui dépend de ce qui existait avant.
5. **Rustine booléenne** — si `CastsBooleansForPostgres` (§3.9) a écrit des chaînes `'true'`/`'false'`
   dans des colonnes créées en `boolean`, Postgres les a converties ; mais si une colonne a été créée
   ailleurs en `text`, elle contient littéralement `'true'`. **À vérifier colonne par colonne.**

> **Conséquence opérationnelle** : la source de vérité pour le script de migration est **un dump de la
> base de production**, pas le dossier `migrations/`. Les migrations servent à comprendre l'intention ;
> le dump seul dit ce qui est.

### Requêtes de vérification à jouer sur une COPIE de la production

> ⚠️ **Ne jamais exécuter ceci depuis l'environnement local** tant que `api/.env` pointe la
> production. Travailler sur un dump restauré ailleurs.

**A. Structure réelle**

```sql
-- Toutes les colonnes, types, nullabilité, défauts
SELECT table_name, column_name, data_type, is_nullable, column_default,
       character_maximum_length, numeric_precision, numeric_scale
FROM information_schema.columns
WHERE table_schema = 'public'
ORDER BY table_name, ordinal_position;

-- Noms RÉELS des contraintes CHECK (enums) — indispensable avant tout ALTER
SELECT conrelid::regclass AS tbl, conname, pg_get_constraintdef(oid)
FROM pg_constraint WHERE contype = 'c' ORDER BY 1, 2;

-- Clés étrangères et comportements ON DELETE réels
SELECT conrelid::regclass AS tbl, conname, pg_get_constraintdef(oid)
FROM pg_constraint WHERE contype = 'f' ORDER BY 1;

-- Index réellement présents
SELECT tablename, indexname, indexdef FROM pg_indexes
WHERE schemaname = 'public' ORDER BY tablename;

-- Migrations effectivement appliquées (à diff avec le dossier migrations/)
SELECT batch, migration FROM migrations ORDER BY id;
```

**B. Volumétrie et tables mortes**

```sql
SELECT 'subscriptions' t, COUNT(*) FROM subscriptions
UNION ALL SELECT 'subscription_items', COUNT(*) FROM subscription_items
UNION ALL SELECT 'availability_slots', COUNT(*) FROM availability_slots
UNION ALL SELECT 'availability_patterns', COUNT(*) FROM availability_patterns
UNION ALL SELECT 'client_forms', COUNT(*) FROM client_forms
UNION ALL SELECT 'gift_cards', COUNT(*) FROM gift_cards
UNION ALL SELECT 'password_reset_tokens', COUNT(*) FROM password_reset_tokens
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications
UNION ALL SELECT 'jobs', COUNT(*) FROM jobs
UNION ALL SELECT 'failed_jobs', COUNT(*) FROM failed_jobs;

-- Colonnes Cashier : réellement vides ?
SELECT COUNT(*) FROM users WHERE stripe_id IS NOT NULL OR pm_type IS NOT NULL;
```

**C. Le point critique — chemins de fichiers des photos**

```sql
SELECT COUNT(*) total,
       COUNT(*) FILTER (WHERE is_processed = false)                    AS non_traitees,
       COUNT(*) FILTER (WHERE file_path_hd IS NULL)                    AS sans_hd,
       COUNT(*) FILTER (WHERE file_path_preview IS NULL)               AS sans_preview,
       COUNT(*) FILTER (WHERE file_path_thumbnail_clean IS NULL)       AS sans_thumb_clean,
       COUNT(*) FILTER (WHERE file_path_web IS NOT NULL)               AS avec_web_mort,
       COUNT(*) FILTER (WHERE file_path_watermark IS NOT NULL)         AS avec_watermark_mort,
       COUNT(*) FILTER (WHERE metadata ? 'supabase_path')              AS heritage_supabase,
       COUNT(*) FILTER (WHERE metadata ? 'storage_path')               AS avec_storage_path,
       COUNT(*) FILTER (WHERE is_video = true)                         AS videos
FROM photos;

-- Les photos dont le chemin ne se résout QUE par le fallback le plus faible
SELECT id, gallery_id, title, file_path
FROM photos
WHERE file_path_hd IS NULL
  AND NOT (metadata ? 'storage_path')
  AND NOT (metadata ? 'supabase_path');

-- Chemins Supabase absolus (http…) restants
SELECT COUNT(*) FROM photos WHERE file_path LIKE 'http%';
```

**D. Valeurs réelles des colonnes non contraintes**

```sql
SELECT DISTINCT product_type FROM gallery_product_types
UNION SELECT DISTINCT product_type FROM cart_items
UNION SELECT DISTINCT product_type FROM order_items;

SELECT DISTINCT status FROM school_sessions;
SELECT DISTINCT print_status FROM orders;
SELECT DISTINCT provider, type, status FROM payments;
SELECT DISTINCT type FROM notifications;
SELECT DISTINCT source, COUNT(*) FROM clients GROUP BY source;
SELECT DISTINCT status, COUNT(*) FROM orders GROUP BY status;   -- 'refunded' existe-t-il ?
```

**E. Intégrité — orphelins des FK manquantes**

```sql
SELECT COUNT(*) FROM reservations r
  WHERE r.availability_slot_id IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM availability_slots s WHERE s.id = r.availability_slot_id);

SELECT COUNT(*) FROM availability_slots s
  WHERE s.reservation_id IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM reservations r WHERE r.id = s.reservation_id);

SELECT COUNT(*) FROM photo_uploads u
  WHERE u.photo_id IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM photos p WHERE p.id = u.photo_id);

SELECT COUNT(*) FROM gift_cards g
  WHERE g.payment_id IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.id = g.payment_id);
```

**F. Cohérence métier (backfills incomplets, cf. §3.11.3)**

```sql
-- orders:backfill-customer-info a-t-elle été jouée jusqu'au bout ?
SELECT COUNT(*) FROM orders WHERE user_id IS NOT NULL AND guest_email IS NULL;

-- Commandes payées sans facture
SELECT COUNT(*) FROM orders o WHERE o.status = 'paid'
  AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.order_id = o.id);

-- Factures sans fichier généré
SELECT COUNT(*) FROM invoices WHERE file_path IS NULL OR generated_at IS NULL;

-- Commandes bloquées en pending avec un checkout SumUp
SELECT status, COUNT(*) FROM orders
  WHERE sumup_checkout_id IS NOT NULL GROUP BY status;

-- Paniers actifs multiples pour un même utilisateur (invariant applicatif)
SELECT user_id, COUNT(*) FROM carts
  WHERE status = 'active' AND user_id IS NOT NULL
  GROUP BY user_id HAVING COUNT(*) > 1;

-- Galeries sans photo / photos sans galerie accessible
SELECT COUNT(*) FROM galleries g
  WHERE NOT EXISTS (SELECT 1 FROM photos p WHERE p.gallery_id = g.id);

-- Cohérence des totaux de commande
SELECT COUNT(*) FROM orders
  WHERE ABS(total - (subtotal - discount_amount + shipping_fee)) > 0.01;
```

**G. Le compte admin du seeder existe-t-il en production ?**

```sql
SELECT id, email, role, created_at FROM users
WHERE email IN ('admin@oceanetorres.fr', 'client@test.com');
```

---

## 3bis.15. Synthèse §3bis — risques classés

| # | Risque / constat | Gravité | Action |
|---|---|---|---|
| 1 | **`photos.metadata->supabase_path`** dans la chaîne de résolution des fichiers | **Critique** | Quantifier (req. C) avant toute migration de fichiers |
| 2 | **Rejouer les migrations ≠ schéma de production** (garde `hasTable`, migrations de données, SQL brut) | **Critique** | La source de vérité est un **dump**, pas `migrations/` |
| 3 | **`invoices.order_id` en CASCADE** — supprimer une commande détruit sa facture | **Critique** | Contredit la rétention légale et `PersonalDataEraser`. Passer en RESTRICT |
| 4 | **`orders.metadata` porte le token d'accès aux fichiers achetés** | Élevée | Extraire en colonne/table dédiée |
| 5 | **Aucun `SoftDeletes`** + 18 FK en CASCADE, dont 2 chaînes destructrices | Élevée | Ajouter au moins sur `orders` et `galleries` |
| 6 | **`prestations → reservations` en CASCADE** | Élevée | Perte d'historique sur suppression de catalogue |
| 7 | **8 colonnes JSON** sans schéma ni contrainte, dont 2 critiques | Élevée | Inspecter chacune (req. D), typer dans la refonte |
| 8 | **`clients.source` non fiable** (3 écritures forcent `reservation`) | Moyenne | Ne pas migrer telle quelle ; recalculer ou abandonner |
| 9 | **Booléens possiblement stockés en texte** (rustine §3.9) | Moyenne | Vérifier `data_type` colonne par colonne (req. A) |
| 10 | **6 colonnes de type FK sans contrainte** → orphelins possibles | Moyenne | Contrôler (req. E) |
| 11 | **`galleries` = 4 concepts dans une table** (table-per-hierarchy implicite) | Moyenne | Décision de modélisation en §11 |
| 12 | **Seeder avec `admin123`** — vérifier que ce compte n'existe pas en prod | **Élevée si présent** | Req. G, immédiatement |
| 13 | **`orders.status = 'refunded'` mort** → remboursement non représentable | Moyenne | Manque fonctionnel, cf. §7 |
| 14 | **Tables Cashier/Stripe mortes et cassées** (`user_id` bigint vs uuid) | Faible | Supprimer — aucun risque |
| 15 | 4 autres valeurs d'enum jamais écrites (`contact`, `unavailable`, `cancelled`) | Faible | Nettoyer |
| 16 | **`file_path_web` / `file_path_watermark`** jamais écrites | Faible | Quantifier puis supprimer |
| 17 | `max_uses` des codes promo calculé depuis `orders` | Moyenne | Filtrer/anonymiser des commandes change les quotas |
| 18 | **Pas de contrainte « 1 panier actif par user »** (invariant applicatif seul) | Faible | Index unique partiel dans la refonte |
| 19 | Précisions décimales incohérentes (`10,2` vs `8,2`) | Faible | Uniformiser |
| 20 | Collision de nom : table `notifications` maison vs Laravel natif | Faible | Renommer si la refonte adopte le natif |

---
# 4. Services externes et intégrations

> **Méthode** : lecture de `api/config/{filesystems,mail,services,sentry,database,sumup}.php`, des
> clients d'API (`MinioStorageService`, `SumUpService`, `BrevoSmsService`), des templates
> `api/.env.example`, `web/.env.example`, `deploy/.env.prod.example`, de l'infra
> (`deploy/docker-compose.prod.yml`, `nginx.prod.conf`, `deploy.sh`, `api/Dockerfile`), du front
> (`web/index.html`, `Checkout.vue`, `stores/consent.ts`) et de `docs/analyse-paiement-sumup.md`.
>
> **Les fichiers `.env` réels n'ont pas été ouverts** — seuls les `.example` ont été lus. Aucun secret
> ne figure dans ce document.

## 4.0. Corrections apportées aux sections précédentes

| Affirmation antérieure | Réalité en production (`deploy/.env.prod.example`) |
|---|---|
| §1 : « Les sessions sont stockées en base (`SESSION_DRIVER=database`) » | **`SESSION_DRIVER=file`** en production (+ `SESSION_ENCRYPT=true`, `SESSION_DOMAIN=.oceanetorresphotographie.fr`, `SESSION_SAME_SITE=lax`, `SESSION_SECURE_COOKIE=true`). Le driver `database` n'est utilisé qu'en local |
| §1 : « `cache.store` = `database` (tables `cache`, `cache_locks`) » | **`CACHE_STORE=file`** en production. Les tables `cache`/`cache_locks` ne servent donc **qu'en local** |
| §1 : `DB_PORT` non précisé | **`DB_PORT=6543`** = **Transaction Pooler** Supabase. C'est la cause racine documentée du problème `EMULATE_PREPARES` (§3.9) |

> ⚠️ **Conséquence directe pour la §3bis** : les tables `sessions`, `cache` et `cache_locks` sont
> **vides en production**. Elles n'ont donc rien à migrer — mais surtout, la supervision (heartbeats,
> cooldowns d'alerte) vit dans `storage/framework/cache`, **pas en base**. Voir §4.7 pour le couplage
> caché que cela crée entre les 4 conteneurs.

---

## 4.1. Carte des dépendances externes

| Service | Rôle | Criticité | Mode de défaillance | Sonde ? |
|---|---|---|---|---|
| **Supabase** (PostgreSQL managé) | Base de données unique | 🔴 **Vitale** | Site totalement HS | ✅ `DatabaseProbe` |
| **MinIO** (self-hosted, NAS) | Toutes les photos, factures PDF | 🔴 **Vitale** | Galeries et achats inutilisables | ✅ `StorageProbe` |
| **Cloudflare Tunnel** | Exposition de l'API et de MinIO | 🔴 **Vitale** | API injoignable | ❌ (indirect) |
| **SumUp** | Paiements CB | 🟠 Élevée | Aucun achat possible | ❌ |
| **Brevo SMTP** | Tous les emails transactionnels | 🟠 Élevée | Accès galerie, confirmations, factures perdus | ❌ |
| **Brevo API SMS** | Diffusion scolaire, accès galerie | 🟡 Moyenne | Repli manuel sur email | ❌ |
| **Render** | Hébergement du SPA statique | 🟠 Élevée | Site vitrine HS (API intacte) | ❌ |
| **Sentry** | Erreurs back + front | 🟢 Faible | Perte de visibilité | ❌ |
| **healthchecks.io** | Dead man's switch des crons | 🟢 Faible | Perte d'alerte | n/a |
| **Google Analytics 4** | Mesure d'audience | 🟢 Faible | Perte de statistiques | ❌ |
| **YouTube** | 7 vidéos du portfolio | 🟢 Faible | Vignettes/lecteur cassés | ❌ |
| **Supabase Edge Functions** | 🔴 **orpheline** | — | aucun | — |

**Cinq dépendances vitales ou élevées ne sont pas supervisées** : Cloudflare, SumUp, Brevo (×2),
Render. Une panne SumUp ou Brevo est aujourd'hui découverte par une cliente, pas par une alerte.

---

## 4.2. Supabase — PostgreSQL managé, rien de plus

**Ce qui est réellement utilisé** : uniquement la base PostgreSQL, via `DB_CONNECTION=pgsql`.
Ni Auth, ni Storage, ni Realtime, ni les API REST/GraphQL de Supabase.

```
DB_HOST     = <pooler URL Supabase>
DB_PORT     = 6543        ← Transaction Pooler (5432 = Session Pooler)
DB_DATABASE = postgres
```

Le choix du port est **argumenté dans le template**, et le raisonnement est juste :

> *« Port : 6543 = Transaction Pooler, 5432 = Session Pooler. On veut 6543 — PDO n'est pas persistant
> (`config/database.php`), donc chaque requête HTTP ouvre sa connexion : le mode transaction les rend
> légères, le mode session ouvre un backend PostgreSQL dédié à chaque fois. Compatible ici parce que
> `PDO::ATTR_EMULATE_PREPARES` est activé et que les deux verrous consultatifs du code sont des
> `pg_advisory_xact_lock` pris dans une transaction explicite. »*

> **C'est le chaînon manquant de la §3.9.** Le raisonnement se tient techniquement, mais il assume
> `EMULATE_PREPARES` — et donc, en cascade, le trait `CastsBooleansForPostgres` sur 12 modèles et les
> 20 `whereRaw`. Le coût de ce choix d'infrastructure est payé dans le code applicatif, à un endroit
> où personne ne fait le lien.
>
> **Arbitrage à poser explicitement en §11** : le pooler transaction (6543) économise des backends
> Postgres au prix de la couche booléenne ; le pooler session (5432) rendrait les prepared statements
> nommés et supprimerait la rustine.
>
> ⚠️ **AMENDÉ EN §5.0.1** : le pooler session a été **essayé en production et a saturé**
> (`EMAXCONNSESSION`, ~5000×/jour — cf. `docs/deploy-improve-photo-upload.md`). Ce n'est donc pas une
> option gratuite. Voir §5.0.1 pour les trois pistes réelles.

### 🔴 L'Edge Function `cleanup-gallery-files` est totalement orpheline

`supabase/functions/cleanup-gallery-files/index.ts` (Deno, ~85 lignes) supprime les fichiers d'une
galerie dans le **bucket Supabase Storage** `galleries`.

Vérifications faites :

| Contrôle | Résultat |
|---|---|
| Appel depuis l'API Laravel | **aucun** |
| Appel depuis le front | **aucun** |
| Référence dans les compose / scripts de déploiement | **aucune** |
| `SUPABASE_URL` / `SUPABASE_SERVICE_ROLE_KEY` dans les `.env.example` | **absentes** |
| Bucket Supabase Storage `galleries` | remplacé par MinIO (§4.3) |

C'est un **vestige mort de l'architecture Supabase Storage**, cohérent avec `metadata->supabase_path`
encore présent dans `photos` (§3bis.5). Le `supabase/README.md` documente d'ailleurs toujours des
buckets qui n'existent plus (`public-portfolio`, `private-portfolio`) et des variables
`SUPABASE_KEY`/`SUPABASE_SERVICE_KEY` qui ne sont lues nulle part.

**Verdict** : supprimer `supabase/functions/` et réécrire `supabase/README.md` pour ne documenter que
la connexion PostgreSQL. Aucun risque.

### 🔴 Aucune stratégie de sauvegarde

`grep -rniE "backup|pg_dump|sauvegarde|snapshot|restore"` sur `deploy/`, `docs/`, `Makefile` et
`PREPROD.md` ne retourne **rien** (hors les « snapshots » de codes promo, sans rapport).

Il n'existe donc **aucune procédure documentée** de sauvegarde ou de restauration, ni pour la base
Supabase, ni pour MinIO. La seule protection est celle du plan Supabase (rétention automatique, dont
la durée dépend du plan — non documentée ici) et, pour MinIO, la politique RAID/snapshot du NAS — non
documentée non plus.

> ⚠️ **Impact direct sur la refonte** : la §3bis.14 conclut que la source de vérité du script de
> migration est **un dump de production**. Or il n'existe aucun processus établi pour en produire un.
> **Première action concrète de la phase de migration : mettre en place et tester un
> `pg_dump` + une copie MinIO, avec une restauration vérifiée sur un environnement séparé.**
> C'est aussi ce qui permettra de jouer les requêtes de la §3bis.14 sans toucher la production.

---

## 4.3. MinIO — stockage objet auto-hébergé

### Configuration

`config/filesystems.php` déclare 4 disques : `local`, `public`, `s3` (générique, **inutilisé**) et
`minio`. Le disque par défaut est `minio` (`FILESYSTEM_DISK=minio`).

```php
'minio' => [
    'driver'                  => 's3',
    'key'                     => env('MINIO_ACCESS_KEY'),
    'secret'                  => env('MINIO_SECRET_KEY'),
    'region'                  => env('MINIO_REGION', 'us-east-1'),
    'bucket'                  => env('MINIO_BUCKET', 'galleries'),
    'endpoint'                => env('MINIO_ENDPOINT'),
    'temporary_url'           => env('MINIO_PUBLIC_URL'),      // ← clé du montage
    'use_path_style_endpoint' => env('MINIO_USE_PATH_STYLE', true),
],
```

### Le montage « dual endpoint » — une vraie bonne idée

En production :

```
MINIO_ENDPOINT   = http://host.docker.internal:9000        ← appels API serveur→MinIO
MINIO_PUBLIC_URL = https://s3.oceanetorresphotographie.fr  ← réécriture des URL signées
```

MinIO tourne dans un **compose séparé** sur le NAS et publie les ports 9000/9001 sur l'hôte. Les trois
conteneurs PHP déclarent `extra_hosts: host.docker.internal:host-gateway`, ce qui leur permet
d'atteindre MinIO **en local, sans traverser le tunnel Cloudflare** — le commentaire chiffre le gain à
**100 à 300 ms par PUT**. Pendant ce temps, `temporary_url` garantit que les URL signées renvoyées au
navigateur pointent vers le domaine public.

> ✅ **À conserver dans la refonte.** C'est une optimisation non triviale, correctement documentée, qui
> résout un vrai problème (le tunnel en boucle locale). Elle explique aussi pourquoi
> `MINIO_PUBLIC_URL` est facultatif en local.

### `MinioStorageService` — 7 méthodes, 3 faiblesses

| Méthode | Rôle | Remarque |
|---|---|---|
| `uploadPhoto()` | upload direct (vidéos et fallback) | ⚠️ `file_get_contents($file->getRealPath())` → **charge tout le fichier en mémoire PHP** |
| `getSignedUrl($path, $expiresIn = 3600)` | URL signée | appelée à **86 400 s (24 h)** par `Photo` ; défaut 3600 s |
| `deletePhoto()` | suppression unitaire | |
| `deleteGalleryFolder()` | purge d'une galerie | ⚠️ heuristique — voir ci-dessous |
| `listFiles()` | listing non récursif | |
| `downloadPhoto()` | copie vers un fichier temporaire | ⚠️ `get()` puis `file_put_contents` → tout en mémoire |
| `getFileContent()` | contenu brut | ⚠️ idem |

**Faiblesse 1 — chargement en mémoire.** `uploadPhoto`, `downloadPhoto` et `getFileContent` lisent le
fichier entier en RAM. Sur des originaux de 20-35 Mo et un `memory_limit` PHP borné, c'est la cause
probable des erreurs « Image trop volumineuse » que `humanizeUploadError()` traduit (§3.11.1).
`ImageProcessingService` a d'ailleurs été corrigé pour utiliser `putFileAs` en streaming
(« évite de charger les 20-35 Mo en mémoire PHP, multipart S3 au-delà de 5 Mo ») — **mais
`MinioStorageService` n'a pas reçu le même traitement.** Incohérence à corriger.

**Faiblesse 2 — `deleteGalleryFolder()` devine l'arborescence.** Après avoir supprimé les fichiers
retournés par `allFiles()`, la méthode tente `deleteDirectory()` sur une liste **codée en dur** :
`['original', 'preview', 'thumbnail']`. Or les dérivés vivent aussi dans `thumbnail-clean/`
(cf. `Photo::cleanThumbnailStoragePath()` → `{gallery_id}/thumbnail-clean/…`). Le sous-dossier n'est
pas dans la liste. Sans conséquence réelle (S3 n'a pas de vrais répertoires, et `allFiles()` est
récursif donc les fichiers sont bien supprimés), mais c'est un symptôme : **la convention de
nommage des chemins n'est centralisée nulle part.**

**Faiblesse 3 — aucune vérification post-suppression.** Toutes les méthodes attrapent l'exception,
loguent et renvoient `false`/`null`. Un échec de suppression est donc silencieux côté appelant. C'est
précisément pourquoi la commande `storage:clean-orphans` existe (§3.11.3) — un ramasse-miettes qui
compense l'absence de garantie transactionnelle entre la base et l'objet.

### Convention de chemins observée

```
{gallery_id}/{uuid}.{ext}                      ← original / vidéo (uploadPhoto)
{gallery_id}/original/…                        ← original (ImageProcessingService)
{gallery_id}/preview/…                         ← 1200 px + filigrane
{gallery_id}/thumbnail/…                       ← 400 px + filigrane fort
{gallery_id}/thumbnail-clean/{basename}        ← 400 px sans filigrane
```

Plus les **factures PDF**, stockées sur le même disque `minio` (`InvoiceService`, puis
`Attachment::fromStorageDisk('minio', …)`).

> ⚠️ **Le bucket `galleries` mélange donc les photos et les pièces comptables.** Une purge de galerie
> mal ciblée pourrait toucher des factures. À séparer en deux buckets dans la refonte (`photos` et
> `invoices`), avec des politiques de rétention distinctes.

### Sonde

`StorageProbe` teste soit l'existence d'un **objet témoin** (`SUPERVISION_STORAGE_WITNESS`), soit — si
la variable est vide — un simple `files('.supervision')` sur un préfixe inexistant, pour vérifier que
l'appel S3 aboutit. Deux motifs d'alerte distincts : `storage_unreachable` et
`storage_witness_missing`.

> `SUPERVISION_STORAGE_WITNESS=` est **vide dans le template de production** : le mode « objet
> témoin » n'est donc pas activé. La sonde valide la connectivité, pas l'intégrité du bucket.
> Créer l'objet témoin est une action à coût nul.

---

## 4.4. SumUp — paiements

### Configuration et surface d'API réellement utilisée

`config/sumup.php` : `api_key`, `public_key`, `merchant_code`, `environment` (`SUMUP_ENV=live` en
prod), `api_url` fixe `https://api.sumup.com`, devise `EUR`, locale `fr-FR`.
Le mode sandbox/production est en réalité déterminé par **le préfixe de la clé** (`sk_test_` vs
`sk_live_`), non par `SUMUP_ENV` — le commentaire du fichier le précise.

`SumUpService` (171 lignes) expose 6 méthodes ; **3 sont mortes** :

| Méthode | Endpoint SumUp | Appels dans `app/` |
|---|---|---|
| `createCheckout()` | `POST /v0.1/checkouts` | 2 ✅ |
| `getCheckout()` | `GET /v0.1/checkouts/{id}` | 4 ✅ |
| `deactivateCheckout()` | `DELETE /v0.1/checkouts/{id}` | 7 ✅ |
| `completeCheckout()` | (wrapper de `getCheckout`) | **0** 🔴 |
| `processCheckout()` | `PUT /v0.1/checkouts/{id}` (carte côté serveur) | **0** 🔴 |
| `getPaymentMethods()` | `GET /v0.1/merchants/{code}/payment-methods` | **0** 🔴 |

> `processCheckout()` accepte des **données de carte brutes** et son propre commentaire recommande de
> ne pas l'utiliser (« For PCI compliance, prefer using the SumUp Card Widget on frontend »).
> **À supprimer sans hésiter** : c'est du code mort qui, s'il était un jour appelé, ferait transiter
> des PAN par le serveur et ferait entrer le projet dans le périmètre PCI-DSS.

Le constructeur **échoue au démarrage** (`throw new \RuntimeException`) si `SUMUP_API_KEY`,
`SUMUP_MERCHANT_CODE` ou l'URL de retour manquent — bon réflexe de « fail fast ».

`checkout_reference` = `{order_uuid}_{Str::random(8)}` → **45 caractères**. Le suffixe aléatoire est
nécessaire car *« SumUp rejette les références en doublon, même pour des checkouts FAILED »*. La doc
SumUp est muette sur la limite de longueur ; ça passe en pratique.

### Le point d'architecture délicat : une URL, deux usages

Déjà décrit en §2.5, il mérite d'être re-posé ici comme contrainte d'intégration :

```
return_url = {APP_URL}/api/payments/sumup/return?order={order_id}

  GET  → redirection navigateur après 3DS (SumUp ajoute ?checkout_id=…)
  POST → webhook CHECKOUT_STATUS_CHANGED  { event_type, id }
```

SumUp **ne propose aucun webhook configurable au dashboard** : le webhook part sur le `return_url` du
checkout. L'URL doit donc pointer vers le backend Laravel et **surtout pas** vers le SPA statique —
lequel « avalait silencieusement le POST en renvoyant 200/HTML », laissant les commandes en `pending`.
C'est un bug réel corrigé en production (commit `8f1e5b9`, cf. `docs/analyse-paiement-sumup.md`).

### L'invariant de sécurité — le point fort de l'intégration

Ni le webhook ni le polling ne font confiance au statut annoncé : ils rappellent systématiquement
`getCheckout()` pour recaler le statut réel côté SumUp. La doc SumUp est citée verbatim dans
`docs/analyse-paiement-sumup.md` :

> *« Your application must **always** verify if the event really took place, by calling a relevant
> SumUp's API. »*

**Conséquence : il est impossible d'injecter un faux `PAID`.** L'absence de signature du webhook et
le rate-limit à 60/min deviennent secondaires face à cet invariant. C'est la bonne conception, à
reprendre telle quelle.

Traitement des erreurs du webhook, également correct :

| Situation | Réponse | Raison |
|---|---|---|
| Payload sans `id` | **200** `{received:true}` | non retry-able ; la doc annonce « New events may be introduced at any time » |
| Commande introuvable | **200** | non retry-able (annulée, expirée, supprimée) |
| Commande déjà payée | **200** | idempotence |
| Erreur présumée transitoire | **503** | pour bénéficier des retries SumUp (1 min / 5 min / 20 min / 2 h) |

Et un **triple filet de sécurité** : webhook → retour navigateur `GET` (qui vérifie avant de
rediriger) → réconciliation planifiée toutes les 10 min (§2.5).

### Le SDK côté navigateur

`Checkout.vue` charge dynamiquement `https://gateway.sumup.com/gateway/ecom/card/v2/sdk.js` puis monte
`window.SumUpCard.mount({...})`. Les événements réellement exposés ont été **vérifiés dans le bundle**
et sont documentés dans le code : `loaded`, `sent`, `invalid`, `auth-screen`, `success`, `fail`,
`error` — avec l'avertissement que le nom est `auth-screen` et **non** `auth-screen-displayed`
(source d'un bug corrigé, §3.0 du doc SumUp).

Deux mécanismes défensifs côté front, tous deux justes :

1. **`success` n'est pas cru** — la doc SumUp précise qu'il ne garantit pas `PAID` ; le front rappelle
   `POST /payments/sumup/verify`.
2. **Watchdog `AUTH_STUCK_TIMEOUT_MS` (~45 s)** — armé dès `auth-screen` ou `sent`. Si aucune réponse
   finale n'arrive, le front déclenche une vérification serveur, pour les cas de `postMessage` cassé
   (navigateurs in-app Instagram/TikTok/Facebook), popup fermée, etc.

### Limites connues, déjà documentées par le projet

- **`consent_ip = 172.19.0.1`** — voir §4.7, c'est le point le plus grave.
- **Navigateurs in-app** — `postMessage` cross-origin parfois cassé, widget figé.
- **Watchdog à 45 s** — possiblement trop court pour un 3DS par SMS (60-90 s).
- **`personal_details` incomplets** — ni téléphone ni adresse de facturation envoyés à SumUp ; effet
  sur le scoring 3DS/SCA non mesuré.
- **Pas de remboursement** — cohérent avec `orders.status = 'refunded'` mort (§3bis.7). Aucune
  intégration de l'API de remboursement SumUp.

Le document fournit aussi une **méthode de diagnostic** exploitable en §7 : sur une commande `failed`,
`sumup_transaction_id IS NULL` signifie un refus émetteur immédiat (rien à corriger côté code), et le
delta `updated_at − created_at` discrimine refus immédiat (< 1 min), 3DS abandonné (5-15 min) et
checkout expiré (> 1 h). Requête SQL fournie.

---

## 4.5. Brevo — deux intégrations distinctes

### 4.5.1. Emails, via SMTP standard

```
MAIL_MAILER     = smtp
MAIL_HOST       = smtp-relay.brevo.com
MAIL_PORT       = 587
MAIL_ENCRYPTION = tls
MAIL_FROM_ADDRESS = noreply@oceanetorresphotographie.fr
MAIL_ADMIN_EMAIL  = oceanetorresphotographie@gmail.com
```

Aucun SDK, aucun package : c'est le mailer SMTP natif de Laravel. En local, Mailpit (`:1025`).
`config/mail.php` ajoute une clé **custom `admin_email`** (défaut en dur :
`oceanetorresphotographie@gmail.com`), lue par `SendBookingNotifications`.

> ⚠️ Deux adresses distinctes coexistent en dur dans le code et la config : `MAIL_FROM_ADDRESS`
> (`noreply@…fr`) et `mail.admin_email` (Gmail). L'adresse Gmail apparaît aussi comme valeur par
> défaut dans `config/invoice.php` et dans `web/src/config/constants.ts`. **Trois sources de vérité**
> pour l'adresse de contact — à centraliser.

**Aucune trace de délivrabilité.** `Mail::queue()` est utilisé partout, mais :
- aucun webhook Brevo n'est configuré (pas de route, pas de table) ;
- rien ne journalise les bounces, les plaintes ou les non-ouvertures ;
- seul `contact_messages` porte deux booléens `email_sent_to_admin` / `email_sent_to_user`, qui
  attestent d'une **mise en file**, pas d'une remise.

C'est un angle mort réel : un email d'accès à une galerie qui n'arrive jamais est invisible côté
application. Le mailer étant `Mail::queue()`, un échec SMTP part en `failed_jobs` — que **rien ne
surveille** hors l'alerte générique `queue_failed_jobs` (§2.13).

### 4.5.2. SMS, via l'API HTTP Brevo

`BrevoSmsService` (102 lignes) — appel direct, sans SDK :

```
POST https://api.brevo.com/v3/transactionalSMS/sms
En-tête : api-key: <BREVO_API_KEY>
Corps   : { type: 'transactional', unicodeEnabled: false, sender, recipient, content }
Timeout : 15 s
```

Trois contraintes Brevo gérées explicitement, toutes pertinentes :

1. **Expéditeur alphanumérique ≤ 11 caractères** — validé en amont (`preg_match('/^[A-Za-z0-9]+$/')`
   + `strlen`), avec un « fail-fast » commenté : *« pour éviter des appels API et des retries
   gaspillés »*. Défaut : `OceanePhoto` (11 caractères — exactement à la limite).
2. **`unicodeEnabled: false`** + suppression systématique des accents par `SmsTemplateService` →
   encodage **GSM-7**, donc **1 segment jusqu'à 160 caractères** au lieu de 70 en UCS-2. C'est une
   optimisation de coût directe (un SMS facturé au lieu de trois).
3. **Normalisation des numéros français** — `normalizePhone()` accepte `+33…`, `0033…`, `33…`, `06…`
   avec séparateurs quelconques, et renvoie `null` si invalide.

**Limites** :
- `normalizePhone()` est **franco-centrée** : un `+41…` passe (longueur ≥ 10) mais un `07…` suisse à
  10 chiffres devient `+337…` — faux. Sans impact aujourd'hui (clientèle Loire/Lyon), à savoir.
- **Aucun suivi de délivrabilité SMS** non plus : `send()` renvoie un booléen, et le seul retour
  utilisateur est le compteur « N SMS mis en file d'envoi ». Un numéro invalide ou un SMS non délivré
  n'est jamais remonté à l'admin. Combiné à l'absence de persistance des contacts parents (§2.10),
  **il est impossible de savoir qui n'a pas reçu son lien de galerie.**
- Un seul `BREVO_API_KEY` sert l'API SMS ; le SMTP a ses propres identifiants. Deux secrets Brevo
  distincts.

---

## 4.6. Sentry — back et front

| | Backend | Frontend |
|---|---|---|
| Package | `sentry/sentry-laravel` `^4.27` | `@sentry/vue` `^10.69.0` |
| DSN | `SENTRY_LARAVEL_DSN` ou `SENTRY_DSN` | `VITE_SENTRY_DSN` |
| Branchement | `Integration::handles($exceptions)` dans `bootstrap/app.php` | **import dynamique conditionnel** — si le DSN est vide, le SDK n'est même pas chargé |
| Release | `SENTRY_RELEASE` ou `APP_VERSION` (défaut `dev`) | déduite de `package.json` |
| `traces_sample_rate` | `0` (défaut et en prod) | `0` |
| `profiles_sample_rate` | `0` | — |
| `send_default_pii` | **`false`** ✅ | — |

`enable_logs` et `enable_metrics` sont à `false`. La configuration est **sobre et volontairement
gratuite** : erreurs uniquement, pas de tracing, pas de profiling — cohérent avec un plan gratuit.

> ✅ `send_default_pii = false` est le bon défaut sur un projet qui manipule des données de clients.
> ✅ L'import dynamique côté front (DSN vide → SDK non chargé) évite d'alourdir le bundle en local.
>
> ⚠️ `APP_VERSION=2.3.0` dans `deploy/.env.prod.example` alors que `web/package.json` est en
> **`6.1.0`**. Front et back sont versionnés indépendamment, et le template de prod n'a pas suivi.
> Les releases Sentry côté API sont donc étiquetées avec une version qui ne correspond à rien.

---

## 4.7. Infrastructure : Cloudflare Tunnel, Nginx, Docker, Render

### Topologie de production

```
Navigateur
   │
   ├──► Render (SPA statique, build `npm run build:prerender`)
   │        oceanetorresphotographie.fr
   │
   ├──► Cloudflare Tunnel ──► NAS:8080 ──► api-nginx ──► api-php (php-fpm:9000)
   │        api.oceanetorresphotographie.fr                    │
   │                                                            ├──► Supabase (pooler 6543)
   │                                                            └──► MinIO (host.docker.internal:9000)
   │
   └──► Cloudflare Tunnel ──► NAS:9000 (MinIO)
            s3.oceanetorresphotographie.fr        ← URL signées servies au navigateur

Conteneurs : api-php · api-queue · api-scheduler · api-nginx   (compose `oceane-api-prod`)
MinIO : compose SÉPARÉ sur le NAS
```

### 🔴 Le problème le plus grave : `consent_ip` est l'IP de Docker

`docs/analyse-paiement-sumup.md` le documente noir sur blanc :

> *« **`consent_ip = 172.19.0.1`** (IP réseau Docker interne) au lieu de l'IP cliente réelle → la
> chaîne Cloudflare Tunnel → nginx → php-fpm ne remonte pas `CF-Connecting-IP` / `X-Forwarded-For`
> jusqu'à `request->ip()`. Impact RGPD (preuve d'acceptation CGV), pas paiement. »*

C'est la **confirmation en production** du risque que j'ai signalé en §3.2.1 à propos de
`trustProxies(at: '*')` — sauf que le symptôme est l'inverse de celui attendu : l'IP n'est pas
usurpée, elle est simplement **perdue**. `nginx.prod.conf` ne pose aucun
`proxy_set_header X-Real-IP` / `X-Forwarded-For`, et le tunnel Cloudflare n'est pas configuré pour les
transmettre jusqu'à PHP-FPM.

**Portée réelle, plus large que ce que le doc SumUp indique** — toutes ces colonnes sont concernées :

| Colonne | Usage | État |
|---|---|---|
| `orders.consent_ip` | **preuve d'acceptation des CGV** | 🔴 inutilisable |
| `contact_messages.consent_ip` | preuve de consentement RGPD | 🔴 inutilisable |
| `download_logs.ip_address` | traçabilité des téléchargements | 🔴 inutilisable |
| `privacy_audit_logs.ip_address` | **journal d'audit RGPD** | 🔴 inutilisable |
| `sessions.ip_address` | — | 🔴 inutilisable |
| **Tous les rate limiters** (`->by($request->ip())`) | 5 limiteurs (§3.2.2) | 🔴 **une seule clé pour tout le trafic** |

> Ce dernier point est le plus sérieux et n'est signalé nulle part : si `$request->ip()` renvoie
> toujours `172.19.0.1`, alors **les 5 rate limiters comptent tout le monde ensemble**. Le
> `throttle:images` à 500/min devient une limite globale partagée par tous les visiteurs — un seul
> client chargeant une galerie de 200 photos peut consommer 40 % du quota et faire ralentir les
> autres. Et le `throttle:gift-code` à 20/min bloquerait tous les clients dès qu'un seul essaie
> quelques codes. **À vérifier en priorité dans les logs Nginx.**
>
> **Correctif** : transmettre `CF-Connecting-IP` (Cloudflare) → `X-Forwarded-For` dans
> `nginx.prod.conf`, et restreindre `trustProxies` aux plages Cloudflare + réseau Docker au lieu de
> `'*'`. C'est un correctif de quelques lignes avec un impact RGPD **et** de disponibilité.

### ⚠️ La production tourne sur la cible Docker `development`

`api/Dockerfile` définit trois étages : `base`, `development` (php-fpm) et `production`
(php-cli + `artisan serve`, prévu pour Render). Or `deploy/docker-compose.prod.yml` construit les
**trois** services PHP avec :

```yaml
build:
  context: ./api
  target: development     # ← pour api-php, api-queue ET api-scheduler
```

Ce n'est pas absurde — l'étage `production` utilise `php artisan serve`, inadapté à un vrai serveur, et
l'étage `development` fournit php-fpm. Mais les conséquences sont réelles :

1. L'image contient les **dépendances de développement** (`composer install` sans `--no-dev` dans
   l'étage `development`) : PHPUnit, Mockery, Faker, Pail, Sail sont présents dans l'image de prod.
   Le `composer install --no-dev` du `command:` les retire du `vendor/` **monté**, pas de l'image.
2. Le volume `./api:/var/www` **masque entièrement le code de l'image**. Le `deploy.sh` le dit
   lui-même : *« le compose monte ./api sur /var/www, donc les couches applicatives de l'image sont de
   toute façon masquées au runtime »*. Le build ne sert donc qu'à fournir PHP et ses extensions —
   c'est un « Docker as runtime », pas un artefact immuable.
3. **Il n'y a pas d'image versionnée déployable.** Un rollback consiste à faire un `git checkout` sur
   le NAS, pas à repointer un tag d'image. Aucune reproductibilité.

### ⚠️ `php artisan migrate --force` au démarrage du conteneur

Le `command:` de `api-php` enchaîne : `composer install --no-dev` → **`php artisan migrate --force`** →
`storage:link` → `config:cache` → `route:cache` → `view:cache` → `php-fpm`.

Les migrations tournent donc **automatiquement à chaque redémarrage** du conteneur, sans intervention.
Combiné à l'absence de sauvegarde (§4.2), c'est le scénario le plus risqué de toute l'infrastructure :
un redémarrage après un `git pull` embarquant une migration destructive s'applique sans filet.
`api-queue` et `api-scheduler` attendent `service_healthy` sur `laravel`, ce qui évite au moins les
migrations concurrentes.

### ⚠️ En-têtes de sécurité en double, avec conflit

| En-tête | Middleware Laravel `SecurityHeaders` | `nginx.prod.conf` |
|---|---|---|
| `X-Frame-Options` | **`DENY`** | **`SAMEORIGIN`** |
| `X-Content-Type-Options` | `nosniff` | `nosniff` |
| `X-XSS-Protection` | `1; mode=block` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | ✅ | ❌ |
| `Strict-Transport-Security` | ✅ (si HTTPS) | ❌ |
| **`Content-Security-Policy`** | ❌ | ❌ |

Les réponses PHP portent donc **deux valeurs contradictoires** de `X-Frame-Options`. Les navigateurs
en présence de valeurs multiples appliquent en général la plus restrictive ou ignorent l'en-tête —
comportement non déterministe. Et `Content-Security-Policy` reste absente des deux côtés (§3.2.6).

**À faire** : choisir **une seule** couche (le middleware, qui couvre aussi les erreurs Laravel) et
retirer les `add_header` de Nginx.

### Réglages Nginx pertinents

`client_max_body_size 100M`, `client_body_timeout 600s`, `send_timeout 600s`,
`fastcgi_read_timeout 600` — calibrés pour *« les uploads de 20-35 Mo sur connexions lentes (mobile,
rural) »*. Cohérent avec `UPLOAD_CONFIG` côté front (30 Mo/lot, timeout 5 min).
Gzip actif, mais **pas Brotli**. Cache immuable 1 an sur les assets statiques.

### Healthchecks Docker — bien pensés

| Conteneur | Test |
|---|---|
| `api-php` | `cgi-fcgi` sur `/ping` php-fpm, avec repli `fsockopen(127.0.0.1, 9000)` |
| `api-queue` | **`php artisan supervision:heartbeat:check queue --max-age=600`** |
| `api-scheduler` | **`php artisan supervision:heartbeat:check scheduler --max-age=900`** |
| `api-nginx` | `wget /api/health/live \| grep '"status":"ok"'` |

Brancher le healthcheck Docker sur le heartbeat applicatif est élégant : Docker redémarre le worker
si le heartbeat est périmé, **avant** que l'alerte email ne se déclenche. Double filet.

### 🔴 Couplage caché : le cache fichier partagé entre 4 conteneurs

`CACHE_STORE=file` en production, et les heartbeats de supervision sont écrits dans le cache
(`HeartbeatService` → `Cache::put("supervision:{$component}:heartbeat")`).

- `api-scheduler` **écrit** son heartbeat ;
- `api-queue` écrit le sien (via l'event `Looping`) ;
- `api-php` **les lit** pour servir `/health/details` ;
- le healthcheck Docker de chaque conteneur lit le sien.

Cela ne fonctionne **que** parce que les quatre conteneurs bind-montent le même `./api:/var/www` et
partagent donc `storage/framework/cache`. **Rien ne documente cette dépendance.** Toute évolution vers
des conteneurs sans volume partagé (Kubernetes, Render, images immuables) casse silencieusement la
supervision — les sondes rapporteraient « composant silencieux » alors que tout tourne.

> **Verdict refonte** : passer le cache en Redis (ou en base). Cela supprime le couplage, rend les
> conteneurs sans état et débloque au passage le cache d'URL signées MinIO que l'équipe a dû
> abandonner faute de store rapide (§2.3 : *« tant qu'on n'a pas de cache rapide (Redis) »*).
> **Un seul changement d'infrastructure résout trois problèmes.**

### Render — dépendance non versionnée

Le SPA est buildé et servi par Render (`npm run build:prerender`). **Il n'existe aucun `render.yaml`,
`vercel.json` ni `netlify.toml` dans le dépôt** : toute la configuration (variables d'environnement
dont `VITE_API_URL` et `VITE_SENTRY_DSN`, commande de build, redirections SPA, domaine) vit
**uniquement dans le tableau de bord Render**.

> ⚠️ Configuration d'hébergement **non reproductible et non auditée**. À récupérer et versionner
> (fichier `render.yaml` ou équivalent) avant toute refonte — sinon le déploiement du front n'est
> reconstituable que de mémoire.

### Gestion des secrets — correcte sur le principe

`.gitignore` couvre `.env`, `.env.*`, avec exception explicite pour les `.example` et interdiction
nommée de `/deploy/.env.prod` et `/deploy/.env.preprod`. Les templates utilisent des marqueurs
`<SECRET: …>` explicites.

Les fichiers réels **sont bien présents sur le disque** (`deploy/.env.prod`, `deploy/.env.preprod`,
`api/.env`, `web/.env`, `web/.env.preprod`, `./.env`) mais non versionnés. Le risque documenté en §1.6
reste entier : **`api/.env` local pointe la base et le mailer de PRODUCTION.**

---

## 4.8. healthchecks.io — dead man's switch

Deux tâches planifiées émettent un ping de succès :

```php
->pingOnSuccessIf((bool) config('supervision.healthchecks.reconcile_orders_url'), …)  // 10 min
->pingOnSuccessIf((bool) config('supervision.healthchecks.rgpd_cleanup_url'), …)      // hebdo
```

> Les deux URL (`HEALTHCHECKS_RGPD_URL`, `HEALTHCHECKS_RECONCILE_URL`) sont **vides dans le template
> de production**. Commentaire du template : *« Vides = aucun ping émis. »*
>
> Le mécanisme est donc **implémenté mais non activé**. C'est dommage : ce sont précisément les deux
> tâches dont le silence est invisible (la réconciliation des paiements et la purge RGPD), et le
> heartbeat interne ne couvre pas leur non-exécution — il prouve que le scheduler tourne, pas que
> *ces* tâches réussissent. Activation à coût nul, gain réel.

---

## 4.9. Services tiers côté navigateur

| Service | Chargement | Consentement |
|---|---|---|
| **Google Analytics 4** (`G-9BK5CTP2YR`) | `googletagmanager.com/gtag/js` injecté par `stores/consent.ts` | ✅ Consent Mode v2, 4 signaux en `denied` par défaut, `anonymize_ip`, `send_page_view: false` |
| **SumUp Card Widget** | `gateway.sumup.com/…/sdk.js`, injecté à la demande dans `Checkout.vue` | fonctionnel (pas de consentement requis) |
| **YouTube** | vignettes `img.youtube.com`, lecteur `youtube.com/embed/…?autoplay=1&rel=0` dans la Lightbox | 🔴 **aucun consentement** — voir ci-dessous |
| Annuaires (`annuaire-photographe.fr`, `missionphotographe.com`) | liens sortants du footer, `rel="noopener"` | n/a |

`index.html` ne contient **aucun script tiers en dur** — les commentaires GTM confirment la démarche
(« chargé dynamiquement après consentement »). Le SEO est en revanche très travaillé : 3 blocs
JSON-LD (`LocalBusiness`+`ProfessionalService`, `Person`, `WebSite`), Open Graph, Twitter Card,
géolocalisation, canonical, et 5 `<link rel="preload">` sur les images critiques.

> ⚠️ **Angle mort RGPD** : l'iframe YouTube est montée **dès l'ouverture de la lightbox sur une
> vidéo**, sans attendre le consentement. `youtube.com/embed` dépose des cookies de suivi
> (`youtube.com`, `google.com`), et le domaine `youtube-nocookie.com` **n'est pas utilisé**.
> Le store de consentement purge bien `_ga*`, `_gcl_au`, `_fbp`… mais ne couvre pas les cookies
> YouTube. Correctif simple : `youtube-nocookie.com` + affichage de la vignette avec clic-pour-charger.
>
> ⚠️ **Aucune de ces origines n'est déclarée dans une CSP** — il n'y a pas de CSP (§4.7). En poser une
> demandera d'autoriser explicitement `googletagmanager.com`, `google-analytics.com`,
> `gateway.sumup.com`, `youtube.com`, `img.youtube.com` et `s3.oceanetorresphotographie.fr`.

---

## 4.10. Inventaire des secrets et de leur portée

| Secret | Portée | Rotation possible sans casse ? |
|---|---|---|
| `APP_KEY` | chiffrement des cookies/sessions **et** de toute colonne chiffrée | ⚠️ à vérifier en §6 — aucune colonne chiffrée identifiée à ce stade, donc rotation *a priori* sûre (déconnecte tout le monde) |
| `DB_PASSWORD` (Supabase) | base entière | ✅ |
| `MINIO_ACCESS_KEY` / `MINIO_SECRET_KEY` | bucket entier (photos **et** factures) | ⚠️ invalide toutes les URL signées en circulation (24 h) |
| `SUMUP_API_KEY` | paiements en production | ✅ |
| `SUMUP_PUBLIC_KEY`, `SUMUP_MERCHANT_CODE` | identifiants publics | ✅ |
| `MAIL_USERNAME` / `MAIL_PASSWORD` (Brevo SMTP) | envoi d'emails | ✅ |
| `BREVO_API_KEY` | envoi de SMS | ✅ |
| `HEALTH_CHECK_TOKEN` | `/health/details`, `/health/database` | ✅ — mais transmissible en query string (§3.2.7) |
| `SENTRY_DSN` / `VITE_SENTRY_DSN` | remontée d'erreurs (DSN front = public par nature) | ✅ |
| `GA4_ID` (`G-9BK5CTP2YR`) | **en dur dans `stores/consent.ts`** | public par nature |

> **Aucun gestionnaire de secrets** : tout vit dans des fichiers `.env` sur le NAS et dans le tableau
> de bord Render. Acceptable à cette échelle, mais **il n'existe aucune procédure de rotation
> documentée**, et le secret le plus sensible à faire tourner (`MINIO_SECRET_KEY`) invalide au passage
> toutes les URL signées servies aux clientes.

---

## 4.11. Points de défaillance unique et modes de dégradation

| Panne | Effet observable | Détection actuelle |
|---|---|---|
| **Supabase HS** | Site entier HS (API 500) | ✅ `DatabaseProbe` + alerte email |
| **NAS éteint / MinIO HS** | Photos et factures inaccessibles ; site vitrine OK (portfolio statique) | ✅ `StorageProbe` |
| **Tunnel Cloudflare coupé** | API injoignable → SPA affiché mais non fonctionnel | ❌ aucune |
| **`api-queue` arrêté** | Aucun email ni SMS envoyé, aucune photo scolaire traitée — **silencieusement** | ✅ heartbeat + healthcheck Docker |
| **`api-scheduler` arrêté** | Plus de réconciliation SumUp ni de purge RGPD | ✅ heartbeat |
| **SumUp HS** | Checkout impossible | ❌ aucune |
| **Brevo SMTP HS** | Emails en `failed_jobs` | 🟡 alerte générique `queue_failed_jobs` |
| **Brevo SMS HS** | SMS perdus, compteur affiche « mis en file » | ❌ aucune |
| **Render HS** | Site vitrine HS ; API et galeries directes intactes | ❌ aucune |
| **Sentry HS** | Aucun impact | n/a |

**Le mode de dégradation le plus dangereux** reste `api-queue` : tout le transactionnel (emails
d'accès aux galeries, confirmations de commande avec facture, SMS scolaires) passe par la file. Le
heartbeat le couvre — c'est manifestement pourquoi il a été construit.

**Le point aveugle le plus large** : Cloudflare + Render. Les deux façades publiques du service ne
sont surveillées par rien. Un simple check externe (UptimeRobot, ou healthchecks.io déjà en place)
sur `https://oceanetorresphotographie.fr` et `https://api.oceanetorresphotographie.fr/api/health/live`
comblerait ce trou pour un coût nul.

---

## 4.12. Synthèse §4 — constats classés

| # | Constat | Gravité | Action |
|---|---|---|---|
| 1 | **`$request->ip()` = `172.19.0.1`** → consentements CGV/RGPD sans valeur probante **et 5 rate limiters réduits à une clé unique** | **Critique** | Propager `CF-Connecting-IP` dans Nginx + restreindre `trustProxies` |
| 2 | **Aucune sauvegarde** (ni base, ni MinIO) — et la migration en exige un dump | **Critique** | Mettre en place `pg_dump` + copie MinIO **avant tout** |
| 3 | **`migrate --force` à chaque démarrage** de conteneur, sans filet | **Critique** | Sortir les migrations du `command:`, les jouer explicitement |
| 4 | **Photos et factures dans le même bucket** | Élevée | Séparer les buckets et les rétentions |
| 5 | **Cache fichier partagé par bind mount** → supervision couplée à la topologie | Élevée | Redis (débloque aussi le cache d'URL signées, §2.3) |
| 6 | **`X-Frame-Options` contradictoire** (Nginx `SAMEORIGIN` vs Laravel `DENY`), pas de CSP | Élevée | Une seule couche + poser une CSP |
| 7 | **Prod buildée sur la cible `development`**, code monté en volume, aucune image versionnée | Élevée | Étage `production` php-fpm + image taguée |
| 8 | **Zéro suivi de délivrabilité** email et SMS | Élevée | Webhooks Brevo + persistance des envois |
| 9 | **Render non versionné** (aucun `render.yaml`) | Moyenne | Récupérer et versionner la config |
| 10 | **Cloudflare et Render non supervisés** | Moyenne | Check externe sur les 2 façades |
| 11 | **healthchecks.io implémenté mais désactivé** (URL vides) | Moyenne | Activer — coût nul |
| 12 | **Objet témoin MinIO non configuré** (`SUPERVISION_STORAGE_WITNESS` vide) | Faible | Créer l'objet — coût nul |
| 13 | **Pooler transaction (6543)** → cause racine de la rustine booléenne | Élevée | Arbitrer session pooler vs rustine (§11) |
| 14 | **Edge Function Supabase orpheline** + `supabase/README.md` obsolète | Faible | Supprimer |
| 15 | **`SumUpService::processCheckout()`** mort et manipulant des données de carte | Moyenne | Supprimer (risque PCI-DSS latent) |
| 16 | **`MinioStorageService` charge les fichiers en RAM** (3 méthodes) | Moyenne | Streaming, comme `ImageProcessingService` |
| 17 | **Iframe YouTube sans consentement**, pas de `youtube-nocookie` | Moyenne | Clic-pour-charger + domaine nocookie |
| 18 | **3 sources de vérité** pour l'adresse de contact | Faible | Centraliser |
| 19 | **`APP_VERSION=2.3.0`** vs front `6.1.0` → releases Sentry fausses | Faible | Aligner le versionnage |
| 20 | **Aucune procédure de rotation de secrets** ; `MINIO_SECRET_KEY` casse les URL signées | Moyenne | Documenter |
| 21 | `normalizePhone()` franco-centrée (faux positifs hors France) | Faible | Utiliser une lib E.164 |
| 22 | **Pas de remboursement SumUp** (cohérent avec `status='refunded'` mort) | Moyenne | Manque fonctionnel, cf. §7 |

---
# 5. Traitement des photos et galeries

> **Méthode** : lecture de `ImageProcessingService` (534 l.), `ImageProxyController` (300 l.),
> `PhotoController`, `GalleryController::downloadZip/downloadFile`, `ProcessPhotoJob`,
> `GenerateCleanThumbnailJob`, `MinioStorageService`, la chaîne d'upload front
> (`chunkUploader.ts`, `uploadUtils.ts`, `UPLOAD_CONFIG`), les 4 commandes de maintenance et
> `docs/deploy-improve-photo-upload.md`.

## 5.0. ⚠️ Corrections importantes aux sections précédentes

### 5.0.1. Le choix du pooler Supabase — ma recommandation en §4.2 était fausse

`docs/deploy-improve-photo-upload.md` documente un **incident de production** que je n'avais pas
lorsque j'ai rédigé la §4.2 :

| Variable | Valeur | Pourquoi (verbatim du doc) |
|---|---|---|
| `DB_PORT` | **`6543`** | *« Transaction mode → pool ~200 connexions au lieu de ~15 »* |

> *« **Symptôme si `DB_PORT=5432` (session pooler)** : erreurs `EMAXCONNSESSION` en rafale (le pool de
> 15 connexions sature), observées jusqu'à **~5000×/jour sous charge**. »*

**Correction** : j'écrivais en §4.2 que *« le pooler session est très probablement le bon choix »*.
C'est démenti par la production. Le pooler session a **effectivement été essayé et a saturé** — ce
n'est pas une option gratuite. Le passage au pooler transaction (et donc `EMULATE_PREPARES`, et donc
le trait `CastsBooleansForPostgres` + les 20 `whereRaw`) est la **conséquence d'un incident réel**,
pas d'une préférence.

**Reformulation de l'arbitrage pour la §11** — il reste trois pistes, aucune n'est le simple
« repasser en 5432 » :

1. **Rester en pooler transaction et corriger la rustine correctement.** Hypothèse à tester : le vrai
   coupable des lectures est `PDO::ATTR_STRINGIFY_FETCHES => true` (qui fait remonter les booléens en
   chaînes), pas `EMULATE_PREPARES` seul. De nombreux déploiements Laravel + PgBouncer en mode
   transaction fonctionnent sans trait maison. Il est plausible que retirer `STRINGIFY_FETCHES` et
   s'appuyer sur les casts Eloquent standards suffise — **à vérifier expérimentalement**, c'est la
   piste la moins coûteuse.
2. **Réduire le besoin de connexions** : PHP-FPM avec moins de workers, ou connexions persistantes
   (`PDO::ATTR_PERSISTENT`, aujourd'hui explicitement à `false`), ce qui rendrait le pooler session
   tenable.
3. **Sortir du pooler** : Postgres auto-hébergé sur le NAS (aux côtés de MinIO), ou connexion directe
   Supabase. Supprime le problème à la racine mais déplace la charge d'exploitation.

Ce document doit rester une source fiable : la §4.2 est amendée en conséquence.

### 5.0.2. Le driver d'image est Imagick, pas GD

| Affirmation antérieure | Réalité |
|---|---|
| §1 : `intervention/image` *« driver GD par défaut, Imagick installé en option »* | `ImageProcessingService::USE_IMAGICK_DRIVER = **true**` → **Imagick est le driver par défaut** dès que la classe existe, avec repli automatique sur GD sinon. Le commentaire du `Dockerfile` (*« Runtime driver […] default: GD »*) est **périmé** |

Le Dockerfile installe Imagick dans un bloc tolérant à l'échec (`|| echo "Imagick installation
skipped"`), et `pecl install imagick` n'est pas épinglé (le `deploy.sh` le signale). **Conséquence
non documentée : le driver effectif en production dépend du succès d'un build non déterministe.**
Deux déploiements du même commit peuvent traiter les images avec deux moteurs différents — donc avec
un rendu de filigrane et des performances différents. À figer.

### 5.0.3. Les tailles de dérivés annoncées dans le code sont fausses

| Source | Preview | Thumbnail |
|---|---|---|
| `ImageProcessingService` (constantes réelles) | **2560 px** (qualité 93) | **600 px** (qualité 90) |
| Commentaires du modèle `Photo` | *« 1200px + watermark »* | *« 400px + strong watermark »* |

Les commentaires du modèle datent d'une version antérieure. L'écart est significatif : une preview de
2560 px de large en JPEG q93 pèse typiquement **600 Ko à 1,5 Mo**, contre ~200 Ko à 1200 px. Cela
change tout le calcul de bande passante de la §5.13.

---

## 5.1. Vue d'ensemble du pipeline

```
                    ┌─────────────── 3 CHEMINS D'ENTRÉE ───────────────┐
                    │                                                   │
   Admin (galeries)          Sessions scolaires            Commandes artisan
   upload chunké                 ZIP unique                  (backfill)
        │                            │                            │
        │ POST .../photos/async      │ ProcessSchoolSessionJob     │ photos:process-existing
        │ TRAITEMENT EN LIGNE        │ → ProcessPhotoJob (queue)   │ photos:generate-clean-thumbnails
        │ (malgré le nom « async »)  │                             │
        └────────────┬───────────────┴─────────────────────────────┘
                     ▼
        ImageProcessingService::processUploadedPhoto()
                     │
      ┌──────────────┼──────────────────────────────┐
      │              │                              │
   ORIGINAL      1 décodage → 1 buffer          (si is_downloadable)
   streamé       scaleDown 2560 px                GenerateCleanThumbnailJob
   putFileAs     + AlignRotation                        │
      │              │                                  ▼
      │       ┌──────┴──────┐                    thumbnail-clean/ (600 px, SANS filigrane)
      │       │ clone       │
      │       ▼             ▼
      │   preview       thumbnail
      │   2560 px       600 px
      │   + filigrane   + filigrane
      ▼       ▼             ▼
   ═══════════ MinIO (bucket « galleries ») ═══════════
   {gallery_id}/original/ · preview/ · thumbnail/ · thumbnail-clean/
                     │
      ┌──────────────┴───────────────┐
      │                              │
  MODE DIRECT (défaut)          MODE PROXY (repli)
  URL signée MinIO 24 h         /api/images/{version}/{id}
  navigateur ⟶ MinIO            génération à la volée + cache 1 h
                                streaming par PHP-FPM
```

## 5.2. Les trois chemins d'entrée

### 5.2.1. Upload administrateur — chunké côté navigateur, **synchrone** côté serveur

| Étape | Détail |
|---|---|
| Découpage | `buildChunks()` respecte **deux** limites : ≤ 10 fichiers **et** ≤ 30 Mo par lot. Un fichier dépassant 30 Mo obtient son propre lot |
| Transport | `XMLHttpRequest` (pas `fetch`) — choisi pour `xhr.upload.onprogress`, seul moyen d'avoir une progression d'upload réelle |
| Parallélisme | 3 lots simultanés (`concurrentChunks: 3`) |
| Timeout | 300 s par lot ; 2 retries, **uniquement** sur `Upload timeout` et `Network error` |
| Annulation | tableau `activeXHRs` → `abort()` sur tous |
| Endpoint | `POST /api/admin/{galleries\|events}/{id}/photos/async` |
| Validation serveur | `photos` : array, 1 à **15** ; `photos.*` : `mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi`, `max:51200` (**50 Mo**) ; `batch_id` requis |
| Traitement | ⚠️ **`processSinglePhoto()` en ligne, dans la requête HTTP**, fichier par fichier |
| Suivi | une ligne `photo_uploads` par fichier + polling `GET /admin/upload-status` toutes les 3 s |

> Le front limite à 10 fichiers/lot, le serveur en accepte 15 : le front est plus strict, donc pas de
> problème — mais les deux valeurs devraient être une seule source de vérité.
>
> **Le traitement en ligne est le choix structurant de ce chemin** (§3.11.1). Il explique tout le
> reste : les timeouts Nginx à 600 s, `client_max_body_size 100M`, les 3 lots en parallèle (pour
> occuper 3 workers PHP-FPM), et `humanizeUploadError()` qui traduit un OOM PHP en message
> utilisateur. Un lot de 10 photos de 30 Mo occupe **un worker PHP-FPM pendant tout le traitement** —
> avec 3 lots simultanés, 3 workers sont bloqués. Sur un NAS, c'est vraisemblablement l'essentiel du
> pool.

### 5.2.2. Sessions scolaires — le seul chemin réellement asynchrone

Le ZIP est extrait sur le **disque `local`**, puis un `ProcessPhotoJob` est mis en file par photo.

`ProcessPhotoJob` (`tries=3`, `timeout=300`, `backoff` 10/30/60) contient deux subtilités qui méritent
d'être conservées :

1. **Attente du fichier temporaire** — `waitForTempFile()` attend l'apparition du fichier avec un
   délai qui dépend de l'environnement :
   ```php
   // Prod (NAS FS natif) : sync immédiate cross-container. Local (macOS virtio-fs) : marge.
   $waitSeconds = app()->environment('local') ? 30 : 3;
   ```
   C'est la trace d'un vrai problème : le conteneur qui écrit le fichier et celui qui le traite sont
   différents, et la synchronisation du système de fichiers partagé n'est pas instantanée sur macOS.
2. **Discipline de reprise rigoureuse** — sur échec, le job **ne nettoie pas** le fichier temporaire
   et **ne marque pas** l'upload en échec ; il relance l'exception. Seul `failed()` (après épuisement
   des tentatives) marque l'échec et nettoie. Commentaire du code : *« NE PAS cleanup le temp file :
   la queue va retry. »* C'est exactement la bonne façon de faire, et c'est ce qui rend
   `photos:retry-failed` possible.

### 5.2.3. Commandes de backfill

`photos:process-existing` et `photos:generate-clean-thumbnails` régénèrent les dérivés pour les photos
qui en manquent (cf. §5.14).

---

## 5.3. Paramètres de traitement

| Constante | Valeur | Commentaire |
|---|---|---|
| `PREVIEW_MAX_WIDTH` | **2560** px | largeur max de la preview filigranée |
| `THUMBNAIL_MAX_WIDTH` | **600** px | vignette filigranée |
| `PREVIEW_QUALITY` | 93 | élevé |
| `THUMBNAIL_QUALITY` | 90 | élevé pour une vignette |
| `WATERMARK_TEXT` | `©Oceane Torres` | **sans accent** sur le « é » |
| `PREVIEW_CENTRAL_OPACITY` | 0.5 | |
| `THUMBNAIL_CENTRAL_OPACITY` | 0.5 | |
| `PREVIEW_GRID_OPACITY` | **0.8** | grille très marquée |
| `THUMBNAIL_GRID_OPACITY` | 0.7 | |
| `WATERMARK_FONT` | `fonts/Amsterdam.ttf` | présent dans `api/storage/app/fonts/` ✅ |
| `USE_IMAGICK_DRIVER` | **`true`** | cf. §5.0.2 |
| `USE_WATERMARK_LAYER_CACHE` | **`false`** | optimisation désactivée (cf. §5.12) |

> Tous ces réglages sont des **constantes PHP privées**. Changer l'opacité du filigrane ou la taille
> des previews exige un déploiement. Pour un paramètre esthétique que la photographe pourrait vouloir
> ajuster, c'est un candidat naturel à la configuration (`config/images.php`).
>
> ⚠️ `WATERMARK_TEXT = '©Oceane Torres'` — l'accent manquant est **probablement volontaire** (même
> logique que le GSM-7 des SMS : éviter les problèmes d'encodage de police), mais le résultat est un
> filigrane orthographiquement faux sur toutes les photos vendues. À confirmer avec la photographe.

---

## 5.4. Le filigrane

`drawWatermarkOn()` dessine, sur **chaque** dérivé :

- une **grille 4 colonnes × 5 lignes = 20 occurrences** du texte, inclinées à −30°, taille
  `min(w,h) × 0,04` (plancher 10 px), couleur `rgba(50,50,50,opacité)` ;
- un **texte central** horizontal, taille `min(w,h) × 0,15`, **plafonné** pour ne pas déborder :
  ```php
  $maxFontForWidth = (int) ($width * 0.80 / (0.6 * mb_strlen($watermarkText)));
  ```
  (approximation de la largeur du texte, plancher 24 px).

Soit **21 rendus de texte par dérivé**, donc **42 par photo** (preview + thumbnail).

**Ordre des opérations — un détail qui a été appris à ses dépens** :

```php
// Branch BEFORE watermarking — sinon le watermark dimensionné pour 2560px se réduit
// à ~150px sur le thumbnail et devient illisible.
$thumbnail = clone $preview;
$thumbnail->scaleDown(width: self::THUMBNAIL_MAX_WIDTH);
```

La vignette est clonée **avant** l'application du filigrane, puis filigranée séparément avec ses
propres opacités. Sans cela, réduire une preview déjà filigranée écraserait le filigrane.

**Résolution de la police** — `getFontPath()` teste, dans l'ordre : la police custom
(`storage/app/fonts/Amsterdam.ttf`, **présente dans le dépôt** ✅), puis 10 chemins système Linux
(Alpine `ttf-dejavu`, Debian, Liberation, FreeSans), puis 3 chemins macOS. En dernier recours :
`Log::warning('No watermark font found! Watermarks will be tiny.')` et `null` — le filigrane est alors
dessiné avec la police bitmap intégrée, minuscule.

> C'est un **échec silencieux à fort impact commercial** : si la police disparaît (image reconstruite
> sans `ttf-dejavu`, volume mal monté), les photos continuent d'être générées avec un filigrane
> illisible — donc **quasiment protégées de rien**, sans qu'aucune alerte ne se déclenche. Cela
> mériterait une sonde ou, mieux, un échec franc.

---

## 5.5. Les quatre optimisations mémoire — la partie la mieux travaillée du projet

Chacune est documentée par un commentaire expliquant le problème résolu. Toutes sont à conserver.

| # | Optimisation | Gain documenté |
|---|---|---|
| 1 | **Un seul décodage** — `generatePreviewAndThumbnail()` décode la source une fois, dérive preview **et** vignette du même buffer | *« Memory peak per photo ~17 MB instead of ~260 MB on a 7000×4600 source (was decoding twice) »* |
| 2 | **Réduire avant de tourner** — `scaleDownPreservingOrientation()` teste l'orientation EXIF et réduit sur la bonne dimension **avant** `AlignRotationModifier` | *« l'inverse force GD à allouer 3× le full-size et déclenche OOM sur les portraits »* |
| 3 | **Upload de l'original en flux** — `putFileAs()` au lieu de `put(file_get_contents(...))` | *« évite de charger les 20-35 MB en mémoire PHP. Multipart S3 au-delà de 5 MB »* |
| 4 | **Libération explicite** — `unset($preview, $previewContent)` puis `unset($thumbnail, $thumbnailContent)` entre les deux uploads | évite de tenir les deux dérivés encodés simultanément |

Le facteur ~15× de l'optimisation n°1 (260 Mo → 17 Mo) est ce qui rend le traitement en ligne
(§5.2.1) viable du tout.

> ⚠️ **Incohérence** : `MinioStorageService::uploadPhoto()`, `downloadPhoto()` et `getFileContent()`
> chargent toujours le fichier entier en RAM (§4.3). L'optimisation n°3 n'a été appliquée qu'à
> `ImageProcessingService`. Or `uploadPhoto()` est le chemin des **vidéos** — jusqu'à 50 Mo d'un coup
> en mémoire PHP.

---

## 5.6. Dérivés et arborescence de stockage

| Dérivé | Chemin MinIO | Filigrane | Écrit par | Colonne |
|---|---|---|---|---|
| Original | `{gallery_id}/original/{uuid}.{ext}` | non | `uploadOriginalStream()` | `file_path`, `file_path_hd` |
| Preview | `{gallery_id}/preview/{uuid}.{ext}` | ✅ (0.5 / 0.8) | `generatePreviewAndThumbnail()` | `file_path_preview` |
| Vignette | `{gallery_id}/thumbnail/{uuid}.{ext}` | ✅ (0.5 / 0.7) | idem | `file_path_thumbnail` |
| Vignette *clean* | `{gallery_id}/thumbnail-clean/{basename}` | ❌ | `GenerateCleanThumbnailJob` | `file_path_thumbnail_clean` |
| Vidéo | `{gallery_id}/{uuid}.{ext}` | ❌ | `MinioStorageService::uploadPhoto()` | `file_path` seul |
| Facture PDF | (même bucket, cf. §4.3) | — | `InvoiceService` | `invoices.file_path` |

Le chemin de la vignette clean est **déterministe et idempotent**, par construction :

```php
public function cleanThumbnailStoragePath(): string {
    $source = $this->file_path_thumbnail ?: $this->resolved_storage_path;
    return $this->gallery_id.'/thumbnail-clean/'.basename($source);
}
```

Réutiliser le `basename` du dérivé filigrané garantit que le backfill ne crée pas de doublons —
commentaire explicite du code. ✅

**Génération de la vignette clean** — déclenchée à trois endroits, jamais à l'upload :
`PhotoController::toggleDownloadable` (l. 143), `bulkToggleDownloadable` (l. 174) et la commande
`photos:generate-clean-thumbnails`. Le job est **idempotent et défensif** : il sort immédiatement si
la photo a disparu, est une vidéo, n'est plus téléchargeable, ou a déjà sa vignette.

> ⚠️ **`deletePhotoVersions()` ne supprime que 3 dérivés** (`hdPath`, `previewPath`,
> `thumbnailPath`). La **vignette clean n'est jamais supprimée** — ni ici, ni dans
> `MinioStorageService::deleteGalleryFolder()` dont la liste de sous-dossiers codée en dur
> (`original`, `preview`, `thumbnail`) omet `thumbnail-clean` (§4.3).
> Résultat : **des dérivés SANS FILIGRANE survivent à la suppression de la photo** sur MinIO.
> Ils restent accessibles à quiconque possède l'URL signée jusqu'à son expiration, et deviennent des
> orphelins permanents ensuite. `storage:clean-orphans` est le seul recours.

---

## 5.7. Service des images : deux modes

### Mode direct (défaut, `SHOP_SERVE_IMAGES_DIRECT=true`)

Les dérivés **déjà traités** sont servis par une **URL signée MinIO valable 24 h**, générée dans
l'accesseur du modèle. Le navigateur tire les octets depuis MinIO, sans passer par PHP-FPM ni le
tunnel Cloudflare (§4.3).

Le commentaire du modèle `Photo` documente un compromis assumé, et il est instructif :

> *« PAS de cache ici — un cache fichier ajoutait 400 accès au disque du NAS (2 par photo × 200) à la
> réponse JSON, soit ~19 s de latence. Conséquence : l'URL change à chaque chargement (pas de cache
> navigateur entre 2 visites) ; c'est un compromis assumé tant qu'on n'a pas de cache rapide
> (Redis). »*

Autrement dit : **la signature est recalculée à chaque réponse JSON**, ce qui interdit tout cache
navigateur inter-visites. C'est le troisième problème que Redis résoudrait (cf. §4.7).

### Mode proxy (repli)

`GET /api/images/{preview|thumbnail|clean|clean-thumb}/{photo}` — génération à la volée avec cache 1 h,
puis streaming par PHP-FPM. Utilisé quand la photo n'est pas encore traitée, si la signature échoue,
ou si le mode direct est désactivé. `throttle:images` = 500/min.

**Optimisation notable** : `findPhotoCached()` met en cache 1 h la recherche `Photo::with('gallery')`,
*« avoids hammering the DB pool when many photos load in parallel from a gallery view »* —
c'est-à-dire pour éviter de saturer le pooler Supabase quand 200 vignettes se chargent en parallèle.
Invalidation propre par les hooks `saved`/`deleted` du modèle. ✅

---

## 5.8. Les cinq chemins de téléchargement

| Chemin | Route | Contrôle d'accès | Livraison |
|---|---|---|---|
| Photo d'une galerie téléchargeable | `GET /photos/{photo}/download?token=` | token de galerie + `is_downloadable` | **URL signée 300 s** (JSON) |
| Idem, mode compat WebView | `…&direct=1` | idem | **streaming PHP** (tout en RAM) |
| ZIP de galerie | `GET /galleries/{id}/download-zip?token=` | `GalleryPolicy::download()` | **streamé** ✅ |
| Photo achetée (HD) | `GET /images/download/{photo}?token=&order=` | commande `paid` + token valide + photo dans la commande | streaming PHP |
| 🔴 « fichier temporaire » | `GET /galleries/{id}/download-file?file=` | **AUCUN** | voir §5.9 |

Le mode `direct=1` est justifié par un commentaire : *« le mode signed URL ne fonctionne pas dans
certaines WebView »* — même famille de problème que les navigateurs in-app de SumUp (§4.4).

**Le ZIP est le seul téléchargement correctement streamé.** `downloadZip()` utilise
`maennchen/zipstream-php` avec `CompressionMethod::STORE` (pas de compression — inutile sur des JPEG,
et cela évite de bufferiser), `addFileFromStream()` par photo, `X-Accel-Buffering: no`, déduplication
des noms d'entrée, et **plafond à 500 photos**. Chaque photo est journalisée (`recordDownload`).
C'est du bon travail.

> ⚠️ Le plafond de 500 est **silencieux** : une galerie de 600 photos téléchargeables produit un ZIP
> de 500 photos sans le moindre avertissement. À rendre explicite.

---

## 5.9. 🔴 Vulnérabilité — `downloadFile()` : lecture et suppression de fichier arbitraires, sans authentification

`GalleryController::downloadFile()`, ligne 419 — **méthode intégrale** :

```php
public function downloadFile(Gallery $gallery, Request $request)
{
    $filename = $request->query('file');
    $filePath = storage_path('app/temp/'.$filename);

    if (! file_exists($filePath)) {
        return response()->json(['message' => 'Fichier non trouvé.'], 404);
    }

    return response()->download($filePath)->deleteFileAfterSend(true);
}
```

Route (`routes/api.php:75`), dans le groupe **Public**, sans aucun middleware :

```php
Route::get('/galleries/{gallery}/download-file', [GalleryController::class, 'downloadFile']);
```

**Quatre défauts cumulés** :

1. **Aucun contrôle d'accès.** Ni token, ni policy, ni authentification. Le paramètre `$gallery` est
   injecté par le route binding **et jamais utilisé** — n'importe quel UUID de galerie existante suffit
   à atteindre la méthode.
2. **Traversée de répertoire.** `$filename` provient directement de la query string et est concaténé
   sans normalisation ni validation. `?file=../../../.env` résout
   `storage/app/temp/../../../.env` = **`api/.env`** — soit les identifiants Supabase, les clés MinIO,
   la clé API SumUp, les identifiants SMTP Brevo, `APP_KEY`.
3. **Suppression arbitraire.** `deleteFileAfterSend(true)` **supprime le fichier après l'envoi**. La
   même requête qui exfiltre `api/.env` le supprime — ce qui met l'API hors service au prochain
   redémarrage. C'est donc aussi une primitive de destruction, utilisable sur `composer.json`,
   `routes/api.php`, la police du filigrane, n'importe quel fichier lisible par le process PHP.
4. **La fonctionnalité n'existe plus.** Rien dans le code n'écrit dans `storage/app/temp` — cette
   méthode est le lecteur orphelin d'une ancienne implémentation « ZIP sur disque », remplacée par le
   ZIP streamé. `storage/app/temp/` contient d'ailleurs encore **deux ZIP de 73 Mo** datés de février,
   vestiges de cette époque.

**Facteurs atténuants** : il faut connaître un UUID de galerie valide (non énumérable en pratique), et
l'endpoint n'est appelé par aucun code front — donc probablement inconnu des scanners automatiques.
Mais c'est de l'obscurité, pas une protection : la route est publique et documentée dans le dépôt.

> **Action recommandée : supprimer la route et la méthode.** Pas de correctif, pas de garde-fou —
> la fonctionnalité est morte, le code doit disparaître. C'est un `git rm` de 13 lignes.
> À traiter **avant** la refonte, sur la production actuelle, sans attendre. Et purger
> `storage/app/temp/`.
>
> Cette découverte sera reprise en §6 comme constat de sécurité de plus haute gravité.

---

## 5.10. Contrôle d'accès effectif sur les images

| Endpoint | Contrôle |
|---|---|
| `GET /images/preview/{photo}` | 🔓 **aucun** |
| `GET /images/thumbnail/{photo}` | 🔓 **aucun** |
| `GET /images/clean/{photo}` | token de galerie + `is_downloadable` ✅ |
| `GET /images/clean-thumb/{photo}` | token de galerie + `is_downloadable` ✅ |
| `GET /images/download/{photo}` | commande payée + token + photo dans la commande ✅ |
| URL signées MinIO (mode direct) | possession de l'URL, 24 h |

**`preview` et `thumbnail` sont volontairement ouverts** : n'importe qui connaissant un UUID de photo
obtient la version filigranée, sans le code de partage de la galerie. C'est un choix défendable — **le
filigrane *est* la protection** — mais il a deux conséquences à assumer explicitement :

1. La confidentialité d'une galerie privée repose uniquement sur la non-divulgation des UUID. Or les
   UUID des photos **sont exposés dans le JSON** de toute galerie ouverte avec un code de partage.
   Quelqu'un ayant eu accès une fois à une galerie conserve un accès permanent aux previews, même
   après régénération du code de partage (`regenerateShareCode`). **La régénération du code ne révoque
   donc pas l'accès aux images**, contrairement à ce que l'on attendrait de cette fonctionnalité.
2. Le mode direct sert des URL signées **24 h** ; la révocation y est encore plus lente.

> À arbitrer en §11 : soit on assume (le filigrane suffit), soit les previews passent derrière le
> token de galerie comme `clean`. La deuxième option est cohérente avec l'existence du bouton
> « régénérer le code ».

---

## 5.11. ⚠️ Le cache d'images stocke des binaires multi-Mo

`ImageProxyController` met en cache **le contenu binaire des images générées** :

```php
Cache::remember("image_clean_{$photo->id}",       3600, fn() => …generateCleanPreviewOnTheFly(…));
Cache::remember("image_clean_thumb_{$photo->id}", 3600, fn() => …);
Cache::remember("image_{$version}_{$photo->id}",  3600, fn() => …);
```

Or le store de cache est (§4.0) :

| Environnement | Store | Conséquence |
|---|---|---|
| **Production** | `file` → `storage/framework/cache` | previews de 2560 px q93 (**600 Ko – 1,5 Mo** chacune) écrites sur le disque du NAS |
| **Local** | `database` → table `cache` | **binaires multi-Mo insérés dans PostgreSQL** (colonne `mediumText`) |

**En production** : une galerie de 200 photos servie en mode proxy (photos non traitées, ou mode
direct désactivé) peut écrire **~200 Mo** dans le cache fichier, sans limite globale. Le cache
fichier Laravel **n'a pas de purge par taille** — seule l'expiration à 1 h libère l'espace, à
condition que quelque chose relise ou nettoie les entrées. Sur un NAS, c'est un risque réel de
saturation, et le même volume que celui qui porte le code, les logs et les fichiers temporaires.

**En local** : c'est encore pire conceptuellement — les binaires partent dans Postgres. Et comme
`api/.env` local pointe la **base de production** (§1.6), un développeur affichant une galerie en
mode proxy en local **écrit des images dans la table `cache` de la production**.

> **Correctif** : ne pas mettre les binaires en cache applicatif. Persister le dérivé sur MinIO (comme
> le fait déjà `generateAndStoreCleanThumbnail`) et servir une URL signée. Le mode proxy devient alors
> un générateur idempotent, pas un cache.

---

## 5.12. Code mort et incohérences du pipeline

| Élément | État |
|---|---|
| `USE_WATERMARK_LAYER_CACHE = false` + `getOrBuildWatermarkLayer()` + `buildWatermarkLayer()` + `$watermarkLayerCache` + `WATERMARK_LAYER_CACHE_LIMIT` | 🔴 **~45 lignes de code mort.** Optimisation écrite puis désactivée : *« GD's transparent-canvas behavior is unreliable; enable once visual output has been validated »*. Comme Imagick est désormais le driver par défaut (§5.0.2), la raison de la désactivation ne tient peut-être plus — **à retester, cela diviserait par 21 le nombre de rendus de texte** |
| `generateCleanPreviewOnTheFly()` | ✅ utilisée (route `clean`) |
| `Photo::web_url` / `file_path_web` | 🔴 jamais écrit (§3bis.5) |
| `file_path_watermark` | 🔴 jamais écrit |
| `Photo::display_url` | ⚠️ `@deprecated` mais toujours dans `$appends` et exposé par `OrderItemResource` |
| `MinioStorageService::listFiles()` | 🔴 aucun appelant hors `storage:clean-orphans` |
| `GalleryController::downloadFile()` | 🔴 **mort et vulnérable** (§5.9) |
| `storage/app/temp/*.zip` | 🔴 2 fichiers de 73 Mo, vestiges |
| Commentaires « 1200px / 400px » du modèle `Photo` | 🔴 périmés (§5.0.3) |
| Commentaire `Dockerfile` « default: GD » | 🔴 périmé (§5.0.2) |
| Commentaire `gallery_product_types.product_type` | 🔴 périmé (`print_scolaire` manquant, §3bis.6) |

---

## 5.13. Budget réel d'une galerie de 200 photos

Reconstitution à partir des constantes et des chemins de code, **à confirmer par mesure**.

### Traitement (upload admin, 200 photos de 25 Mo)

| Poste | Estimation |
|---|---|
| Lots | 200 / 10 = **20 lots** (limite 30 Mo → probablement ~34 lots en pratique, car 10 × 25 Mo = 250 Mo > 30 Mo → **~1 à 2 photos par lot**) |
| Requêtes HTTP | **~100 à 200** (une par lot, 3 en parallèle) |
| Pic mémoire par photo | ~17 Mo (optimisation n°1) |
| Rendus de texte | 200 × 42 = **8 400** |
| Écritures MinIO | 200 × 3 = **600** (original + preview + vignette) |
| Lignes en base | 200 `photos` + 200 `photo_uploads` |

> ⚠️ **Le calcul des lots révèle une incohérence de dimensionnement.** `maxChunkBytes: 30 Mo` avec des
> originaux de 20-35 Mo signifie que **la limite d'octets domine toujours** : un fichier de 35 Mo part
> seul dans son lot (la fonction le permet explicitement), et un lot ne contient jamais les 10 fichiers
> prévus. La limite `chunkSize: 10` est donc **inopérante** dans le cas réel d'usage (photos plein
> format). Ce n'est pas un bug — le comportement est correct — mais le paramètre annoncé ne décrit pas
> ce qui se passe.

### Affichage (galerie protégée, mode direct)

| Poste | Estimation |
|---|---|
| Requêtes API | **1** (aucune pagination — `ProtectedGallery.vue` charge toutes les photos) |
| Signatures HMAC par réponse | 200 × 2 (`preview_url` + `thumbnail_url`) = **400**, recalculées à **chaque** chargement |
| Poids du JSON | 200 photos × (2 URL signées ~500 caractères + métadonnées) ≈ **250-400 Ko** |
| Requêtes images | 200 vignettes (600 px, ~60-100 Ko) = **12-20 Mo**, `loading="lazy"` |
| Ouverture d'une preview | 1 × 2560 px q93 ≈ **600 Ko – 1,5 Mo** |

Le front fait ce qu'il faut : `loading="lazy"` + `decoding="async"` partout, et `MasonryGallery`
passe les premières images en `loading="eager"` + `fetchpriority="high"`. Mais **l'absence de
pagination** signifie que le JSON initial porte les 200 entrées.

### Affichage en mode proxy (repli — photos non traitées)

| Poste | Estimation |
|---|---|
| Requêtes PHP-FPM | **200** (une par vignette), `throttle:images` = 500/min |
| Décodages Imagick | 200 (au premier passage), puis cache 1 h |
| Écriture cache | 200 × ~80 Ko ≈ **16 Mo** (vignettes) ou ~200 Mo (previews) — §5.11 |

C'est ce mode que le passage au mode direct visait à éviter, et le gain est manifeste.

---

## 5.14. Commandes de maintenance

| Commande | Rôle | Options |
|---|---|---|
| `photos:process-existing` | génère preview + vignette filigranées pour les photos existantes | — |
| `photos:generate-clean-thumbnails` | backfill des vignettes clean sur MinIO pour les photos téléchargeables qui en manquent | — |
| `photos:retry-failed` | re-dispatch `ProcessPhotoJob` pour les `PhotoUpload` en échec **dont le fichier temporaire existe encore** | — |
| `storage:clean-orphans` | détecte les fichiers et dossiers MinIO non référencés en base | `--delete` (dry-run par défaut ✅), `--gallery=` |

> ✅ `storage:clean-orphans` est en **dry-run par défaut** — le bon réflexe pour une commande
> destructive.
>
> ⚠️ `photos:retry-failed` ne peut rien faire si le fichier temporaire a été nettoyé. Le fichier
> n'étant conservé que le temps des 3 tentatives, la fenêtre de reprise est courte : les échecs
> anciens sont définitivement perdus, et la seule trace restante est
> `photo_uploads.error_message`.
>
> ⚠️ **Aucune de ces commandes n'est planifiée.** Elles sont toutes manuelles. Or `storage:clean-orphans`
> est le seul mécanisme qui rattrape les fuites de fichiers (§5.6 : vignettes clean jamais supprimées ;
> §4.3 : suppressions silencieusement échouées). Sans exécution régulière, le bucket accumule
> indéfiniment.
>
> 🔴 **AMENDÉ EN §12.2 — NE PAS PLANIFIER EN L'ÉTAT.** La liste de chemins référencés de cette commande
> est **incomplète** : elle omet `file_path_thumbnail_clean`, `metadata->storage_path` et
> `metadata->supabase_path`. Avec `--delete`, elle supprimerait **toutes les vignettes sans filigrane**
> et **les originaux HD des photos d'ère Supabase**. La corriger et la tester d'abord ; ne la planifier
> qu'en dry-run.

**Requêtes de quantification** (à joindre à celles de la §3bis.14) :

```sql
-- Travail restant pour chaque backfill
SELECT COUNT(*) FROM photos WHERE is_processed = false;                      -- process-existing
SELECT COUNT(*) FROM photos
  WHERE is_downloadable = true AND file_path_thumbnail_clean IS NULL
    AND is_video = false;                                                    -- generate-clean-thumbnails
SELECT status, COUNT(*) FROM photo_uploads GROUP BY status;                   -- retry-failed
SELECT error_message, COUNT(*) FROM photo_uploads
  WHERE status = 'failed' GROUP BY error_message ORDER BY 2 DESC;             -- causes réelles d'échec
```

Cette dernière requête est particulièrement utile : `humanizeUploadError()` produit des messages
normalisés (mémoire, format, etc.), donc leur distribution dira **si le traitement en ligne est le
goulot** ou si le problème est ailleurs.

---

## 5.15. Synthèse §5 — constats classés

| # | Constat | Gravité | Section |
|---|---|---|---|
| 1 | **`downloadFile()`** : traversée de répertoire + suppression arbitraire, route publique sans auth | 🔴 **Critique** | §5.9 |
| 2 | **Binaires d'images en cache applicatif** (fichier NAS en prod, table `cache` Postgres en local) | Élevée | §5.11 |
| 3 | **Vignettes clean jamais supprimées** → dérivés SANS filigrane survivant à la suppression | Élevée | §5.6 |
| 4 | **Driver d'image non déterministe** (`pecl install imagick` non épinglé, échec toléré) | Élevée | §5.0.2 |
| 5 | **Traitement en ligne** dans la requête HTTP → workers PHP-FPM bloqués | Élevée | §5.2.1 |
| 6 | **Régénérer le code de partage ne révoque pas l'accès aux previews** | Moyenne | §5.10 |
| 7 | **Police de filigrane : échec silencieux** → filigrane illisible sans alerte | Moyenne | §5.4 |
| 8 | **URL signées recalculées à chaque réponse** → aucun cache navigateur | Moyenne | §5.7 |
| 9 | **Aucune pagination** des photos de galerie | Moyenne | §5.13 |
| 10 | **Plafond ZIP de 500 photos silencieux** | Moyenne | §5.8 |
| 11 | **`storage:clean-orphans` non planifiée** alors qu'elle rattrape les fuites | Moyenne | §5.14 |
| 12 | **`MinioStorageService` charge en RAM** (vidéos jusqu'à 50 Mo) | Moyenne | §5.5 |
| 13 | **~45 lignes de cache de filigrane mort** — à retester sous Imagick (÷21 rendus) | Faible | §5.12 |
| 14 | **`maxChunkBytes` rend `chunkSize` inopérant** sur des originaux plein format | Faible | §5.13 |
| 15 | **Paramètres de traitement en constantes PHP** (opacité, tailles, texte) | Faible | §5.3 |
| 16 | **`WATERMARK_TEXT` sans accent** (« Oceane ») sur toutes les photos vendues | Faible | §5.3 |
| 17 | **Commentaires périmés** (1200/400 px, driver GD, `product_type`) | Faible | §5.12 |
| 18 | **2 ZIP de 73 Mo** oubliés dans `storage/app/temp/` | Faible | §5.9 |

**Le pipeline photo est, techniquement, la partie la plus soignée du projet** : les quatre
optimisations mémoire de la §5.5, le clonage avant filigrane, le chemin déterministe des vignettes
clean, la discipline de reprise de `ProcessPhotoJob`, le ZIP streamé et le double mode de service
témoignent d'un travail d'ingénierie réel, documenté par des commentaires qui expliquent le
*pourquoi*. Les problèmes relevés sont périphériques — sauf `downloadFile()`, qui est un vestige
oublié dans un coin.

---
# 6. Sécurité, sessions, comptes

> **Méthode** : revue ciblée de `AuthController`, `bootstrap/app.php`, `config/{session,sanctum,auth}.php`,
> des 3 middlewares, des FormRequests d'authentification, du store Pinia `auth.ts`, de
> `deploy/docker/{php.ini,www.conf}`, `nginx.prod.conf`, `.github/workflows/ci.yml`, et vérification
> ciblée du code de `laravel/sanctum` dans `vendor/`.
>
> **Périmètre** : audit de code en boîte blanche, **sans test d'intrusion**. Les vulnérabilités
> décrites sont déduites du code ; celles marquées « à confirmer » nécessitent une vérification
> dynamique sur un environnement de test.

## 6.0. Corrections aux sections précédentes

| Affirmation antérieure | Réalité |
|---|---|
| §1.6 : « CI : […] tests backend PHPUnit, Pint, **build image Docker** » | `.github/workflows/ci.yml` contient 5 jobs : `frontend-lint`, `frontend-build`, `backend-lint` (Pint), `backend-syntax` (`php -l`), `backend-tests` (PHPUnit sur Postgres 16). **Aucun build d'image Docker**, et **aucune analyse de sécurité** (cf. §6.13) |
| §4.10 : « `APP_KEY` […] ⚠️ à vérifier en §6 » | ✅ **Résolu** : `grep -rn "encrypted\|Crypt::\|encrypt("` sur `app/` et `config/` ne retourne **aucune colonne chiffrée**. `APP_KEY` ne protège que les cookies et les sessions (`SESSION_ENCRYPT=true`). Sa rotation est donc **sans risque de perte de données** — elle déconnecte simplement tout le monde |

---

## 6.1. Tableau de bord

Classement par **gravité × exploitabilité réelle**.

| # | Vulnérabilité | Gravité | Auth requise | Exploitabilité | §  |
|---|---|---|---|---|---|
| **V1** | `downloadFile()` : lecture + suppression de fichier arbitraires | 🔴 **Critique** | aucune | UUID de galerie requis | §6.10.1 |
| **V2** | **La déconnexion ne révoque rien** (ni session, ni token) | 🔴 **Critique** | — | systématique | §6.2.2 |
| **V3** | Token Bearer 30 j en `localStorage` + **aucune CSP** | 🔴 Élevée | — | via une XSS | §6.8 |
| **V4** | Aucun rate limit sur `login` / `register` / `forgot-password` / `orders/by-email` | 🔴 Élevée | aucune | directe | §6.7 |
| **V5** | Codes de partage de galerie (6 car.) **sans rate limit** | 🔴 Élevée | aucune | directe | §6.6 |
| **V6** | Accès à toute commande créée depuis < 30 min | 🟠 Moyenne | aucune | UUID requis | §6.5 |
| **V7** | `preview`/`thumbnail` sans contrôle → régénérer le code ne révoque rien | 🟠 Moyenne | aucune | UUID requis | §6.6 |
| **V8** | `$request->ip()` constant → **tous les rate limiters inopérants** + traces RGPD sans valeur | 🔴 Élevée | — | systématique | §6.7 / §6.12 |
| **V9** | Énumération de comptes (`register`, `forgot-password`) | 🟠 Moyenne | aucune | directe | §6.2.4 |
| **V10** | `POST /orders/by-email` : historique de commandes sur simple email | 🟠 Moyenne | aucune | directe | §6.5 |
| **V11** | ZIP scolaire : pas de garde anti-bombe, extraction non durcie | 🟠 Moyenne | **admin** | faible | §6.10.2 |
| **V12** | Écriture à offset arbitraire (upload chunké ZIP) | 🟡 Faible | **admin** | faible | §6.10.3 |
| **V13** | Politique de mot de passe = **8 caractères, rien d'autre** | 🟠 Moyenne | — | directe | §6.2.3 |
| **V14** | Jeton de supervision accepté en query string | 🟡 Faible | — | fuite par logs | §6.11 |
| **V15** | Compte admin du seeder (`admin123`) | 🔴 Élevée **si présent** | — | directe | §6.14 |
| **V16** | Aucune analyse de dépendances (ni CI, ni Dependabot) | 🟠 Moyenne | — | — | §6.13 |
| **V17** | Cookie de session sur `.oceanetorresphotographie.fr` (tous sous-domaines, dont `s3.`) | 🟡 Faible | — | — | §6.3 |
| **V18** | Aucun 2FA, aucune vérification d'email | 🟠 Moyenne | — | — | §6.2.5 |

**Bonne nouvelle** : **aucune injection SQL exploitable** (§6.9), **aucune colonne chiffrée à risque**,
**aucun secret versionné**, et l'invariant de sécurité du paiement est solide (§4.4).

---

## 6.2. Authentification

### 6.2.1. Le mécanisme hybride, en détail

```
1. GET  /sanctum/csrf-cookie          → dépose XSRF-TOKEN + cookie de session
2. POST /api/auth/login               → Auth::attempt()  → session authentifiée
                                      → createToken()    → renvoie un token Bearer en clair
3. Le front stocke le token en localStorage (clé « auth_token »)
4. Chaque requête authentifiée porte SIMULTANÉMENT :
      Cookie: oceane_session=…  (+ XSRF-TOKEN)
      Authorization: Bearer <token>
      X-XSRF-TOKEN: <valeur du cookie>
      credentials: 'include'
```

`sanctum.guard = ['web']` : Sanctum essaie **d'abord** le guard de session, et ne se rabat sur le
token Bearer qu'en cas d'échec. Comme le cookie de session est toujours présent après un login,
**c'est le chemin session qui authentifie en pratique** — le token Bearer est un doublon inutilisé…
sauf pour les conséquences décrites ci-dessous.

`sanctum.expiration = 43 200` minutes = **30 jours**. `sanctum.token_prefix` vide → les tokens ne sont
**pas détectables par le secret scanning** de GitHub s'ils fuitaient dans un commit ou un ticket.

### 6.2.2. 🔴 V2 — La déconnexion ne révoque rien

`AuthController::logout()`, intégralement :

```php
public function logout(Request $request): JsonResponse
{
    $request->user()->currentAccessToken()->delete();

    return response()->json([...'message' => 'Déconnexion réussie.']);
}
```

**Le problème** : quand la requête est authentifiée **par la session** (le cas normal, cf. §6.2.1),
`currentAccessToken()` renvoie un `Laravel\Sanctum\TransientToken`. Or vérification faite dans
`vendor/laravel/sanctum/src/TransientToken.php`, cette classe expose **exactement deux méthodes** :

```php
class TransientToken implements HasAbilities
{
    public function can($ability)  { return true; }
    public function cant($ability) { return false; }
}
```

**Il n'y a pas de méthode `delete()`.** L'appel lève donc
`Error: Call to undefined method Laravel\Sanctum\TransientToken::delete()`, capté par le handler
global (§3.2.5) et transformé en **HTTP 500**.

**Et le bug est masqué côté front** — `web/src/stores/auth.ts:157` :

```js
async function logout(): Promise<void> {
    try {
        await authApi.logout()
    } catch {
        // Ignorer les erreurs de logout API
    } finally {
        clearSession()   // token.value = null ; localStorage.removeItem('auth_token')
    }
}
```

Le commentaire « Ignorer les erreurs de logout API » est la trace de quelqu'un qui a vu l'erreur et
l'a contournée dans le navigateur.

**Conséquence effective de « se déconnecter »** :

| Élément | Révoqué ? |
|---|---|
| Token en `localStorage` | ✅ supprimé du navigateur |
| **Token en base** (`personal_access_tokens`) | ❌ **toujours valide 30 jours** |
| **Session serveur** | ❌ **toujours valide** (aucun `Auth::logout()`, aucun `session()->invalidate()`) |
| **Cookie de session du navigateur** | ❌ **non expiré** |

Autrement dit : **la déconnexion est purement cosmétique.** Un cookie de session capturé (ou un
navigateur partagé où l'on a « fermé la session ») reste exploitable. Et même si le chemin Bearer
était emprunté, `logout()` ne détruirait que le token, jamais la session.

> **Correctif** (quelques lignes) :
> ```php
> public function logout(Request $request): JsonResponse
> {
>     $token = $request->user()->currentAccessToken();
>     if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
>         $token->delete();
>     }
>     Auth::guard('web')->logout();
>     $request->session()->invalidate();
>     $request->session()->regenerateToken();
>     return response()->json([...]);
> }
> ```
> Et **retirer le `catch {}` silencieux** côté front, qui a masqué le problème pendant toute la vie du
> projet. À vérifier dynamiquement, mais le code de Sanctum ne laisse aucune échappatoire.

### 6.2.3. V13 — Politique de mot de passe

`RegisterRequest` et `ResetPasswordRequest` utilisent `PasswordRule::defaults()`. Or
`Password::defaults(...)` **n'est configuré nulle part** (`grep` sur `app/` : aucune occurrence hors
les deux FormRequests). Le défaut de Laravel est donc appliqué : **`min(8)` et rien d'autre**.

| Contrôle | État |
|---|---|
| Longueur minimale 8 | ✅ |
| Complexité (majuscule, chiffre, symbole) | ❌ |
| **Vérification contre les fuites** (`->uncompromised()`, API HaveIBeenPwned) | ❌ |
| Interdiction des mots de passe courants | ❌ |
| `BCRYPT_ROUNDS` | **12** ✅ (au-dessus du défaut de 10) |
| Hachage | bcrypt via cast `'password' => 'hashed'` ✅ |

`password123` est donc un mot de passe accepté. Combiné à **l'absence totale de rate limit sur
`/auth/login`** (V4), c'est la combinaison la plus directement exploitable de tout l'audit.

> **Correctif** : dans `AppServiceProvider::boot()`,
> `Password::defaults(fn () => Password::min(12)->uncompromised());`

### 6.2.4. V9 — Énumération de comptes

| Endpoint | Fuite |
|---|---|
| `POST /auth/register` | `'email' => [… 'unique:users']` → l'erreur de validation révèle qu'un email **est déjà inscrit** |
| `POST /auth/forgot-password` | `Password::sendResetLink()` puis `throw ValidationException::withMessages(['email' => [__($status)]])` → renvoie `passwords.user` (« Aucun utilisateur… ») si l'email est inconnu |
| `POST /auth/login` | ✅ message unique et générique : « Les identifiants fournis sont incorrects. » |

`login` est correct ; `register` et `forgot-password` permettent de tester l'existence d'un compte, et
aucun des trois n'est limité en débit. Le mot de passe oublié devrait toujours répondre le même
message de succès, quoi qu'il arrive.

### 6.2.5. V18 — Ce qui n'existe pas

| Fonction | État |
|---|---|
| Double authentification (2FA/TOTP) | ❌ — **y compris pour le compte admin**, qui donne accès à toutes les données clients, aux exports RGPD et à la suppression de commandes |
| Vérification d'adresse email | ❌ — la colonne `email_verified_at` existe et n'est **jamais renseignée** (§3bis.2) |
| Journal des connexions | ❌ — aucune trace d'authentification (réussie ou échouée) |
| Verrouillage après N échecs | ❌ |
| Notification de connexion inhabituelle | ❌ |
| Expiration de session sur inactivité | 🟡 `SESSION_LIFETIME=120` min (Laravel), mais le token Bearer vit 30 jours |

> **Le point le plus disproportionné** : un unique compte administrateur, protégé par un mot de passe
> de 8 caractères minimum, sans 2FA, sans rate limit et sans journal de connexion, contrôle
> l'intégralité des données personnelles de la clientèle. À l'échelle du projet, activer un TOTP sur
> ce seul compte serait le meilleur rapport sécurité/effort de toute la refonte.

---

## 6.3. Sessions et cookies

| Paramètre | Production | Commentaire |
|---|---|---|
| `SESSION_DRIVER` | **`file`** | §4.0 — la table `sessions` est vide en prod |
| `SESSION_ENCRYPT` | **`true`** ✅ | le payload de session est chiffré avec `APP_KEY` |
| `SESSION_LIFETIME` | 120 min | |
| `SESSION_SECURE_COOKIE` | **`true`** ✅ | |
| `SESSION_SAME_SITE` | `lax` | acceptable ; `strict` casserait le retour SumUp en GET |
| `SESSION_DOMAIN` | **`.oceanetorresphotographie.fr`** | ⚠️ V17 — voir ci-dessous |
| `HttpOnly` | ✅ (défaut Laravel) | |

> **V17 — Portée du cookie trop large.** `SESSION_DOMAIN=.oceanetorresphotographie.fr` envoie le
> cookie de session à **tous les sous-domaines**, y compris `api.` (nécessaire), mais aussi
> **`s3.oceanetorresphotographie.fr` (MinIO)** et tout futur sous-domaine. Chaque requête d'image
> vers MinIO transporte donc le cookie de session chiffré — inutilement, et vers un service tiers
> auto-hébergé qui le journalise potentiellement. Comme MinIO sert **des centaines de requêtes par
> galerie**, c'est aussi du volume réseau gaspillé.
>
> **Correctif** : `SESSION_DOMAIN` sur le domaine exact de l'API, et `SANCTUM_STATEFUL_DOMAINS` déjà
> correctement limité aux deux hôtes utiles.

**Note sur `php.ini`** — les directives `[Session]` (`session.save_path = /tmp`,
`session.cookie_secure = 0`, `session.use_strict_mode = 1`) sont **inertes** : Laravel gère ses
sessions lui-même (driver `file` → `storage/framework/sessions`), sans passer par le gestionnaire
natif de PHP. Elles sont trompeuses — notamment `cookie_secure = 0` qui pourrait faire croire à un
cookie non sécurisé alors que Laravel force `Secure`. À supprimer pour éviter la confusion.

---

## 6.4. CSRF

```php
$middleware->validateCsrfTokens(except: ['api/*']);
$middleware->statefulApi();
$middleware->api(prepend: [EnsureFrontendRequestsAreStateful::class]);
```

La protection CSRF native est **désactivée sur tout `api/*`**, ce qui est le fonctionnement attendu
d'une API : c'est `EnsureFrontendRequestsAreStateful` qui, pour les domaines déclarés dans
`sanctum.stateful`, réactive la vérification du token via l'en-tête `X-XSRF-TOKEN`. Le front récupère
bien le cookie (`getCsrfCookie()`) et renvoie l'en-tête (`authHeaders()`). ✅

**Analyse du risque résiduel** :

- Le front envoie `X-XSRF-TOKEN` **manuellement** depuis le cookie, ce qui est correct.
- `SameSite=lax` bloque les requêtes cross-site `POST`, ce qui constitue une seconde ligne de défense.
- ⚠️ Mais **CORS autorise les credentials** (`supports_credentials: true`) avec
  `allowed_headers: ['*']`. La liste d'origines est heureusement stricte en production
  (`CORS_ALLOWED_ORIGINS=https://oceanetorresphotographie.fr`). **Le jour où un joker ou un
  sous-domaine dynamique y serait ajouté, la combinaison `credentials + headers:*` deviendrait
  exploitable.** À figer comme invariant.
- ⚠️ `allowed_methods` n'inclut pas `PATCH` alors que les `apiResource` en génèrent (§3.2.3).

---

## 6.5. Autorisation — récapitulatif consolidé

Détail complet en §3.5. Synthèse des risques :

| Constat | Gravité |
|---|---|
| **52/52 FormRequests** ont `authorize() { return true; }` — tout repose sur le middleware de route | Structurel |
| `GalleryPolicy` appelée **une seule fois**, hors du Gate (`app(…)->download()`) | Moyenne |
| `OrderPolicy` **jamais appelée** ; sa logique est recopiée en 2 endroits | Moyenne |
| `ReservationPolicy` appelée correctement… mais `$this->authorize()` **lève une erreur** (pas de trait `AuthorizesRequests`) → 500 sur 3 routes | Moyenne (routes mortes) |
| **V6 — la règle des « 30 minutes »** : accès accordé à *toute* commande créée depuis moins de 30 min, et téléchargement de *toute* commande payée depuis moins de 30 min | 🟠 **Moyenne** |
| **V10 — `POST /orders/by-email`** : renvoie l'historique de commandes d'une personne sur la seule connaissance de son email, sans vérification ni lien signé, **sans rate limit** | 🟠 **Moyenne** |

> **Sur V6** : le besoin fonctionnel est légitime (un invité doit voir sa confirmation juste après
> paiement). La bonne réponse est un **token signé à courte durée** émis au moment du checkout, pas
> une fenêtre temporelle globale. En l'état, un attaquant qui devine un UUID de commande récente
> obtient nom, email, adresse de livraison et liste des photos achetées. L'UUID rend l'attaque
> impraticable en masse — mais c'est de l'obscurité, et cela couvre mal le cas d'un UUID divulgué
> (lien partagé, historique de navigateur, capture d'écran).
>
> **Sur V10** : couplé à V4 (pas de rate limit), l'endpoint permet de tester en masse quels emails ont
> passé commande — et d'en obtenir le détail. C'est le pire des deux mondes : énumération **et**
> divulgation.

---

## 6.6. Les secrets d'accès par URL

Le modèle d'accès aux galeries repose entièrement sur des secrets dans l'URL. Inventaire :

| Secret | Longueur | Espace | Génération | Rate limit |
|---|---|---|---|---|
| `galleries.share_code` | **6** car. | ~36⁶ ≈ 2,2 × 10⁹ | `strtoupper(Str::random(6))` | 🔴 **AUCUN** |
| `galleries.access_token` | 64 car. | ~62⁶⁴ | `Str::random(64)` | 🔴 aucun (mais inatteignable) |
| `orders.metadata.download_token` | 64 car. | ~62⁶⁴ | `Str::random(64)` | aucun (inatteignable) |
| `gift_codes.code` | 6 car. | 32⁶ ≈ 10⁹ | alphabet sans `O/0/I/1` | ✅ `throttle:gift-code` 20/min |
| URL signées MinIO | HMAC | — | AWS SigV4, 24 h | n/a |

### 🔴 V5 — Le code de partage est énumérable

`GET /galleries/code/{code}` (`routes/api.php:19`) est **public et sans aucun throttle**.

Deux observations :

1. **La distribution n'est pas uniforme.** `Str::random()` tire dans `[A-Za-z0-9]` (62 caractères) ;
   le `strtoupper()` replie les 26 minuscules sur les majuscules. Chaque lettre a donc **deux fois
   plus de chances** qu'un chiffre. L'entropie réelle tombe à ≈ 5,12 bits/caractère au lieu de
   log₂(36) ≈ 5,17 — soit **≈ 30,7 bits** sur 6 caractères. L'écart est marginal ; ce n'est pas le
   problème principal.
2. **Le problème principal est l'absence de limite de débit.** Ce qui compte n'est pas de trouver *un
   code précis*, mais **n'importe quelle galerie** : le nombre d'essais attendu est
   `36⁶ / N_galeries`. Avec 1 000 galeries, cela fait **~2,2 millions d'essais** — soit environ
   **12 heures à 50 requêtes/seconde**, sur un endpoint public qui ne compte rien.

Et chaque code trouvé livre le contenu complet de la galerie : titre, date, email assigné, **et les
UUID de toutes les photos** — donc, via V7, un accès permanent aux previews.

> **Correctifs, par ordre de coût croissant** :
> 1. **Immédiat** : ajouter `throttle` sur `/galleries/code/{code}` (par ex. 10/min/IP) — **une ligne**.
>    ⚠️ Sans effet tant que V8 n'est pas corrigé (l'IP est constante) : les deux correctifs vont
>    ensemble.
> 2. Allonger les nouveaux codes à 8-10 caractères (36⁸ ≈ 2,8 × 10¹²), en gardant la compatibilité des
>    codes existants.
> 3. Journaliser et alerter sur les rafales de codes invalides.

### V7 — Régénérer le code de partage ne révoque pas l'accès

Rappel de la §5.10 : `GET /images/preview/{photo}` et `/images/thumbnail/{photo}` n'ont **aucun
contrôle d'accès**. Le filigrane *est* la protection. Mais comme les UUID des photos sont exposés dans
le JSON de la galerie, quiconque y a eu accès une fois conserve un accès **permanent** aux previews —
y compris après `POST /admin/galleries/{id}/regenerate-code`.

Le bouton « régénérer le code » suggère une révocation qu'il n'opère pas. C'est un écart entre la
promesse de l'interface et le comportement réel, qu'il faut soit corriger, soit documenter auprès de
la photographe.

---

## 6.7. Rate limiting : l'état réel

### Ce qui est configuré

| Limiteur | Débit | Routes |
|---|---|---|
| `images` | 500/min | 4 routes proxy d'images |
| `downloads` | 60/min | `/images/download/{photo}` |
| `sumup-webhook` | 60/min | `POST /payments/sumup/return` |
| `gift-code` | 20/min | `POST /cart/gift-code` |
| `health` | 60/min | `/health*` |

### 🔴 V4 — Ce qui n'est pas protégé

Le groupe `api` **ne déclare aucun `throttle:api` par défaut**. Les routes suivantes sont donc
**totalement non limitées** :

| Route | Risque |
|---|---|
| `POST /auth/login` | **bruteforce de mot de passe** (aggravé par V13 : 8 caractères suffisent) |
| `POST /auth/register` | énumération de comptes + création massive |
| `POST /auth/forgot-password` | énumération + **inondation de la boîte email** d'un tiers (et consommation du quota Brevo) |
| `POST /orders/by-email` | énumération + divulgation d'historique (V10) |
| `GET /galleries/code/{code}` | **énumération de galeries** (V5) |
| `POST /contact` | spam du formulaire → emails + lignes en base |
| `POST /booking-request` | spam → emails + `reservations` + `notifications` |
| `POST /cart/add` | création massive de paniers |
| `POST /checkout` | création massive de commandes + **appels à l'API SumUp** |

Aucun CAPTCHA nulle part, aucun honeypot sur les formulaires publics.

### 🔴 V8 — Et les limiteurs existants sont probablement inopérants

Les cinq limiteurs utilisent tous `->by($request->ip())`. Or `docs/analyse-paiement-sumup.md`
documente que `$request->ip()` renvoie **`172.19.0.1`** (la passerelle Docker) au lieu de l'IP réelle,
parce que la chaîne Cloudflare Tunnel → Nginx → PHP-FPM ne propage pas `CF-Connecting-IP` /
`X-Forwarded-For` (§4.7).

**Si cela se confirme, les cinq limiteurs partagent une clé unique pour l'ensemble du trafic** :

- `throttle:images` à 500/min devient une **limite globale** — une seule cliente ouvrant une galerie
  de 200 photos consomme 40 % du quota, et la 501ᵉ image de la minute renvoie **429 à tout le monde** ;
- `throttle:gift-code` à 20/min : un client testant 20 codes **bloque tous les autres** pendant une
  minute ;
- `throttle:downloads` à 60/min : deux clientes téléchargeant leurs photos simultanément se bloquent
  mutuellement.

> C'est simultanément un problème de **sécurité** (les protections ne protègent pas) et de
> **disponibilité** (les clients légitimes se bloquent entre eux). **À vérifier en priorité absolue** :
> chercher des `429` dans les logs Nginx, et confirmer la valeur de `request->ip()`.
>
> `trustProxies(at: '*')` est par ailleurs à restreindre : en l'état, si le backend devenait
> joignable hors tunnel, n'importe qui pourrait **usurper son IP** via un en-tête forgé.

---

## 6.8. V3 — XSS, CSP et stockage du token

### L'absence de CSP

| Couche | `Content-Security-Policy` |
|---|---|
| Middleware `SecurityHeaders` | ❌ |
| `nginx.prod.conf` | ❌ |
| Render (front) | ❌ (aucune config versionnée, §4.7) |

Aucune CSP nulle part. Et les en-têtes posés en double se contredisent : Nginx envoie
`X-Frame-Options: SAMEORIGIN` là où le middleware envoie `DENY` (§4.7). `X-XSS-Protection` est présent
mais **obsolète** (ignoré par les navigateurs modernes).

### La chaîne d'exploitation

```
XSS (front Vue)  →  lecture de localStorage['auth_token']
                 →  token Bearer valable 30 jours, exfiltrable
                 →  et que la « déconnexion » ne révoque jamais (V2)
```

Les trois faiblesses se composent : **un token à longue durée, dans un stockage lisible par
JavaScript, sans CSP pour empêcher l'exfiltration, et sans révocation possible côté serveur.**

### Surface XSS réelle

| Point | Évaluation |
|---|---|
| Vue 3 échappe par défaut les interpolations | ✅ |
| `v-html` | 🔴 **1 occurrence** — `OrderConfirmation.vue:42`, avec une donnée utilisateur : **sink XSS** (corrigé en §13.1) |
| Contenus administrables réinjectés (titres de galerie, `prestations.description`, `disclaimer`, notes) | rendus par interpolation → échappés |
| `iframe` YouTube (`youtube.com/embed/${id}`) | l'ID vient d'une constante en dur ✅ |
| SDK SumUp chargé depuis un CDN | script tiers **non intègre** (pas de `integrity`) |
| Origines tierces à autoriser dans une future CSP | `googletagmanager.com`, `google-analytics.com`, `gateway.sumup.com`, `youtube.com`, `img.youtube.com`, `s3.oceanetorresphotographie.fr` |

> **Correctifs, dans l'ordre** :
> 1. Supprimer le token Bearer — il est **inutile** (l'authentification passe par la session, §6.2.1).
>    C'est le correctif le plus efficace : il supprime la cible sans rien casser.
> 2. Poser une CSP (`default-src 'self'` + les 6 origines ci-dessus).
> 3. Retirer les `add_header` de Nginx pour éliminer la contradiction.

---

## 6.9. Surface d'injection SQL — saine

Revue systématique des constructions SQL brutes :

| Emplacement | Verdict |
|---|---|
| **20 `whereRaw`** (`is_downloadable = true`, etc.) | ✅ **constantes littérales**, aucune interpolation de variable |
| `DB::statement('SELECT pg_advisory_xact_lock(42)')` / `(43)` | ✅ littéraux |
| `PhotoUpload::getBatchStatus` — `selectRaw("COUNT(*) FILTER (WHERE status = 'completed')")` | ✅ littéraux |
| `DashboardController` — `selectRaw` avec `?` et bindings `[$startOfMonth]` | ✅ **paramétré correctement** |
| `Order::generateOrderNumber` — `orderByRaw("CAST(SUBSTRING(order_number FROM 'OT-\\d{4}-(\\d+)') AS INTEGER) …")` | ✅ littéral |
| `Client::scopeSearch` — `where('name', 'ilike', "%{$search}%")` | ✅ le builder paramètre la valeur |
| `EventCategoryController::reorder` — `DB::raw("CASE id {$caseSql} END", $bindings)` | ✅ **pas d'injection** : `$caseSql` est construit à partir de `'WHEN ? THEN ?'` littéraux, les valeurs restant dans `$bindings`. **Mais c'est un bug** — voir ci-dessous |

**Conclusion : aucune injection SQL identifiée.** Les `whereRaw` sont une dette de maintenabilité
(§3.9), pas une faille.

> 🐛 **Bug découvert au passage** (à reprendre en §9) : `EventCategoryController::reorder` passe des
> bindings en second argument de `DB::raw()`. Or `PhotoController::updateSortOrder` porte ce
> commentaire, écrit après avoir corrigé exactement le même bug :
>
> > *« NB : `DB::raw()` n'accepte PAS de bindings en 2e argument (ils étaient silencieusement ignorés
> > → SQLSTATE[HY093], placeholders non liés et décalage des paramètres). »*
>
> Le correctif (boucle d'`update` dans une transaction) a été appliqué à `PhotoController` **mais pas
> à `EventCategoryController`**. Or `PUT /admin/event-categories/reorder` **est appelé par le front**
> (§2.16). Le réordonnancement des catégories d'événements est donc très probablement **cassé en
> production** (SQLSTATE[HY093] → 500). À confirmer par un test.

---

## 6.10. Manipulation de fichiers

### 6.10.1. 🔴 V1 — `downloadFile()` : lecture et suppression arbitraires

Décrite en détail en §5.9. Rappel de la gravité :

- route **publique**, sans authentification ni token ;
- `?file=../../../.env` → exfiltration de `api/.env` (Supabase, MinIO, SumUp, Brevo, `APP_KEY`) ;
- `deleteFileAfterSend(true)` → **la même requête supprime le fichier** ;
- la fonctionnalité est **morte** (rien n'écrit dans `storage/app/temp`).

**Action : supprimer la route et la méthode (13 lignes), sur la production actuelle, sans attendre la
refonte.** Puis purger `storage/app/temp/` (2 ZIP de 73 Mo).

Aucune autre traversée de répertoire n'a été trouvée : `LogController` construit ses chemins à partir
de `now()->format()` et d'un `glob()` (✅), `SchoolSessionService` à partir de `$session->id` (UUID
validé par le route binding, ✅), `ImageProcessingService` depuis une constante (✅).

### 6.10.2. V11 — Extraction du ZIP scolaire

`SchoolSessionService::extractAndParseZip()` :

```php
$zip = new \ZipArchive;
$zip->open($zipFullPath);
$zip->extractTo($extractDir);       // ← aucune inspection des entrées
```

Deux durcissements manquants (les deux exigent un accès **administrateur**, donc exploitabilité
faible — mais un ZIP est un fichier fourni de l'extérieur, potentiellement reçu par email) :

1. **Aucune protection anti-bombe.** Pas de contrôle du nombre d'entrées, ni du ratio de compression,
   ni de la taille décompressée cumulée. Un ZIP de 100 Mo (la limite d'upload) peut se décompresser en
   plusieurs dizaines de Go et **remplir le volume du NAS** — celui qui porte aussi le code, les logs
   et le cache d'images (§5.11).
2. **Extraction non durcie.** `ZipArchive::extractTo()` filtre certaines entrées malveillantes selon
   la version de PHP et de libzip, mais **le code ne vérifie rien lui-même**. Il faudrait itérer sur
   `$zip->numFiles`, valider chaque `getNameIndex()` (pas de `..`, pas de chemin absolu, extension
   attendue) et extraire sélectivement.

> **Correctif** : boucle de validation avant extraction + plafonds (nombre d'entrées, taille
> décompressée totale, ratio). ~20 lignes, et cela protège aussi contre le ZIP simplement corrompu.

### 6.10.3. V12 — Écriture à offset arbitraire

`SchoolSessionController::upload()` assemble le ZIP par morceaux :

```php
'offset' => ['required', 'integer', 'min:0'],   // seule contrainte
...
fseek($handle, $offset);
fwrite($handle, file_get_contents($chunk->getRealPath()));
```

`offset` est contrôlé par le client sans borne supérieure. Un `offset` de 10¹² crée un **fichier
creux** — inoffensif sur la plupart des systèmes de fichiers, mais susceptible de saturer le quota ou
de faire échouer la sauvegarde. Accès administrateur requis, donc gravité faible.

✅ **Points positifs de cette méthode** : le paramètre `filename` est validé **et jamais utilisé** (le
chemin est dérivé de `$schoolSession->id`) — donc pas de traversée ; `flock(LOCK_EX)` protège les
écritures concurrentes ; et `fseek`+`fwrite` (plutôt qu'un append) rend les retours en arrière
**idempotents**, avec un commentaire qui l'explique.

> **Correctif** : borner `offset` à `total_chunks × taille_max_chunk`, et vérifier la taille finale.

### 6.10.4. Uploads de photos

| Contrôle | État |
|---|---|
| Extensions/MIME | ✅ `mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi` (Laravel vérifie le **type réel**, pas l'extension) |
| Taille par fichier | ✅ `max:51200` (50 Mo) |
| Nombre par requête | ✅ `max:15` (+ `max_file_uploads = 20` dans `php.ini`) |
| Nom de fichier stocké | ✅ **UUID régénéré**, l'original n'est conservé que dans `metadata` |
| Chemin | ✅ dérivé de `$gallery->id` |
| Exécution | ✅ MinIO n'exécute rien ; les fichiers ne sont jamais servis depuis le webroot |

C'est correct. Réserve : `mp4/mov/avi` sont acceptés et **jamais transcodés ni validés au-delà du
MIME** — un fichier vidéo est stocké tel quel et servi par URL signée. Un conteneur malveillant ne
menace pas le serveur, mais est servi au navigateur du visiteur sans réencodage.

---

## 6.11. Exposition d'informations

| Surface | Évaluation |
|---|---|
| Messages d'erreur API | ✅ **Bien fait.** Le handler global masque tout détail technique ; `ModelNotFoundException` est explicitement neutralisée pour ne pas fuiter le nom de classe du modèle (§3.2.5) |
| `display_errors` | ✅ `Off` en prod et en dev |
| `APP_DEBUG` | ✅ `false` en prod |
| `/health` (public) | `publicPayload()` — statut agrégé + version applicative. Fuite mineure : la version permet de cibler des CVE connues |
| `/health/details`, `/health/database` | protégés par `health.token` ✅ ; la route `/health/tables` qui publiait le schéma a été **supprimée** ✅ |
| **V14 — jeton de supervision** | accepté via `?token=` en plus de l'en-tête → apparaît dans les **logs Nginx** et l'historique du navigateur. `hash_equals` est bien utilisé ✅ |
| `/fpm-status`, `/ping` | ✅ **non exposés** : `nginx.prod.conf` ne route que `\.php$` vers FPM ; ces chemins tombent sur `index.php` → 404 Laravel |
| Nginx | ✅ `deny all` sur `\.ht`, `\.env`, `\.git` |
| `server_tokens` | ⚠️ non désactivé → la version de Nginx est annoncée |
| **Logs applicatifs** | contiennent des données personnelles : `BrevoSmsService` journalise les **numéros de téléphone** invalides, plusieurs `Log::error` incluent des **emails**, le webhook SumUp journalise le **payload complet** (sans données de carte ✅) |
| **`/admin/logs`** | expose `laravel.log` en lecture **et en téléchargement** à l'admin. Rétention `LOG_DAILY_DAYS=30`. Ces logs sont des données personnelles au sens du RGPD et **ne sont pas couverts par le module d'effacement** (§6.12) |

---

## 6.12. RGPD : ce qui est solide, ce qui est cassé

### Solide — et rare à ce niveau de projet

| Élément | Évaluation |
|---|---|
| Résolveur de personne concernée (`PersonalDataLocator`) sur 9 tables | ✅ |
| Export art. 15 (global et ciblé), asynchrone | ✅ |
| Effacement art. 17 avec **carte de traitement explicite** (CONSERVER / ANONYMISER / SUPPRIMER) et aperçu préalable | ✅ |
| Opérations **gardées** : sans ancre pertinente, l'opération est ignorée (jamais de purge de table) | ✅ |
| **Journal d'audit** de toute recherche / export / effacement | ✅ |
| Rétention automatisée (7 j / 24 h / 30 j / 6 mois / 12 mois / 10 ans) | ✅ |
| Consentements horodatés (contact, CGV, inscription, client) | ✅ |
| Consent Mode v2, GA4 en `denied` par défaut, purge active des cookies | ✅ |
| `send_default_pii = false` sur Sentry | ✅ |

### Cassé ou incomplet

| # | Problème | Impact |
|---|---|---|
| 1 | **V8 — toutes les IP valent `172.19.0.1`** : `orders.consent_ip`, `contact_messages.consent_ip`, `privacy_audit_logs.ip_address`, `download_logs.ip_address` | 🔴 **La preuve d'acceptation des CGV et le journal d'audit RGPD n'ont aucune valeur probante.** C'est le point RGPD le plus grave |
| 2 | **`invoices.order_id` en CASCADE** — supprimer une commande détruit sa facture | 🔴 Contredit la rétention légale **et** la règle « CONSERVER » de `PersonalDataEraser` (§3bis.8) |
| 3 | **Contacts scolaires non persistés** (§2.10) | 🟢 *Favorable* au RGPD (minimisation), mais aucun historique de diffusion |
| 4 | **`laravel.log` hors périmètre d'effacement** — contient emails et téléphones | 🟠 Une demande d'effacement ne les couvre pas |
| 5 | **`iframe` YouTube sans consentement**, pas de `youtube-nocookie` | 🟠 Dépôt de cookies tiers avant consentement |
| 6 | **Aucun registre des traitements** dans le dépôt | 🟠 Obligation art. 30 (allégée pour une TPE, mais utile) |
| 7 | **Portabilité (art. 20)** : l'export produit un ZIP de JSON — techniquement conforme | 🟢 |
| 8 | Aucune procédure documentée en cas de **violation de données** (art. 33, notification 72 h) | 🟠 |

> Le module RGPD est **la partie la mieux conçue du projet** — et son maillon faible est une ligne de
> configuration Nginx. Corriger V8 restaure la valeur probante de tout l'édifice.

---

## 6.13. V16 — Chaîne d'approvisionnement et CI

`.github/workflows/ci.yml` — 5 jobs : `frontend-lint` (ESLint), `frontend-build` (vue-tsc + vite +
prerender, **avec vérification que les 7 pages prérendues existent** ✅), `backend-lint` (Pint),
`backend-syntax` (`php -l`), `backend-tests` (PHPUnit sur **Postgres 16** — le commentaire explique
que SQLite est exclu à cause du SQL Postgres-spécifique ✅).

**Ce qui manque entièrement** :

| Contrôle | État |
|---|---|
| `composer audit` | ❌ |
| `npm audit` | ❌ |
| Dependabot / Renovate | ❌ (`.github/` ne contient que `workflows/`) |
| Analyse statique de sécurité (CodeQL, Semgrep, Snyk) | ❌ |
| Détection de secrets (gitleaks, trufflehog) | ❌ |
| Scan de l'image Docker (Trivy) | ❌ |
| Vérification des licences | ❌ |

Le projet dépend de **~90 paquets Composer** et de **Vue 3 + Vite + Puppeteer + Sharp** côté front.
Aucune veille de vulnérabilité n'est en place ; aucune alerte n'arrivera lors de la prochaine CVE
Laravel ou Intervention.

> **Correctif à coût quasi nul** : ajouter `composer audit` et `npm audit --audit-level=high` comme
> deux étapes de CI, plus un `.github/dependabot.yml` (10 lignes). C'est le meilleur rapport
> effort/bénéfice de toute cette section.

**Point positif** : les `.env` sont correctement exclus par `.gitignore`, avec exceptions nommées pour
les seuls `.example`, et les templates utilisent des marqueurs `<SECRET: …>` explicites (§4.7).
**Aucun secret versionné n'a été trouvé.**

---

## 6.14. Comptes : inventaire et hygiène

| Aspect | État |
|---|---|
| Rôles | 2 (`admin`, `client`), colonne `users.role`, pas de table de permissions |
| Nombre d'admins | 1 en pratique — **à confirmer en base** |
| Création d'admin | uniquement par `DatabaseSeeder` ou modification directe en base (`apiResource /admin/users` existe mais **n'a aucun écran**, §2.11) |
| Suspension / désactivation de compte | ❌ inexistante |
| Expiration de mot de passe | ❌ |
| Journal d'accès admin | ❌ (seul le journal RGPD trace les actions *privacy*) |

### 🔴 V15 — Le compte du seeder

`DatabaseSeeder` crée deux comptes en dur :

| Email | Mot de passe | Rôle |
|---|---|---|
| `admin@oceanetorres.fr` | **`admin123`** | **admin** |
| `client@test.com` | `client123` | client |

Si ce seeder a été exécuté une seule fois sur la base de production, **il existe un compte
administrateur avec le mot de passe `admin123`** — et l'absence de rate limit sur `/auth/login` (V4)
rend sa découverte triviale.

> **Action immédiate, avant tout le reste** :
> ```sql
> SELECT id, email, role, created_at, updated_at
> FROM users
> WHERE email IN ('admin@oceanetorres.fr', 'client@test.com');
>
> -- Et, plus largement, l'inventaire des comptes privilégiés :
> SELECT id, email, role, created_at, email_verified_at FROM users WHERE role = 'admin';
> ```
> (Déjà présente en §3bis.14-G ; elle remonte ici au rang de priorité 1.)
>
> Et **retirer les mots de passe en dur du seeder** : les tirer d'une variable d'environnement, ou
> refuser de tourner si `APP_ENV === 'production'`.

---

## 6.15. Plan d'action priorisé

### Priorité 1 — à faire sur la production actuelle, sans attendre la refonte

| # | Action | Effort |
|---|---|---|
| 1 | **Vérifier l'existence du compte `admin@oceanetorres.fr`** et le supprimer / changer son mot de passe | 5 min |
| 2 | **Supprimer la route et la méthode `downloadFile()`** (V1) + purger `storage/app/temp/` | 15 min |
| 3 | **Propager `CF-Connecting-IP`** dans Nginx + restreindre `trustProxies` (V8) — débloque tous les rate limiters et restaure les preuves RGPD | 1 h |
| 4 | **Ajouter des rate limits** sur `login`, `register`, `forgot-password`, `orders/by-email`, `galleries/code`, `contact`, `booking-request` (V4, V5) | 1 h |
| 5 | **Corriger `logout()`** : invalider la session + le token, et retirer le `catch {}` du front (V2) | 30 min |
| 6 | **Durcir la politique de mot de passe** : `Password::min(12)->uncompromised()` (V13) | 15 min |
| 7 | **Retirer les mots de passe en dur du seeder** (V15) | 15 min |

### Priorité 2 — avant ou pendant la refonte

| # | Action |
|---|---|
| 8 | **Supprimer le token Bearer** (inutile, §6.2.1) et poser une **CSP** (V3) |
| 9 | Remplacer la règle des « 30 minutes » par un **token signé court** (V6) |
| 10 | Sécuriser `POST /orders/by-email` (lien signé par email au lieu d'une réponse directe) (V10) |
| 11 | **Activer un TOTP sur le compte admin** (V18) |
| 12 | Ajouter `composer audit`, `npm audit` et Dependabot à la CI (V16) |
| 13 | Durcir l'extraction ZIP (plafonds + validation des entrées) (V11) |
| 14 | Passer `invoices.order_id` en `RESTRICT` + `SoftDeletes` sur `orders` (RGPD n°2) |
| 15 | Réduire `SESSION_DOMAIN` au domaine exact de l'API (V17) |
| 16 | Uniformiser les en-têtes de sécurité (une seule couche) et retirer `X-XSS-Protection` |
| 17 | Décider pour `preview`/`thumbnail` : soit token de galerie, soit documenter la non-révocation (V7) |
| 18 | Corriger `EventCategoryController::reorder` (bug `DB::raw` + bindings) |
| 19 | Uniformiser les messages de `forgot-password` (anti-énumération) (V9) |
| 20 | `youtube-nocookie` + clic-pour-charger (RGPD n°5) |

### Priorité 3 — hygiène

| # | Action |
|---|---|
| 21 | Jeton de supervision : en-tête uniquement, plus de query string (V14) |
| 22 | Borner l'`offset` de l'upload chunké (V12) |
| 23 | `server_tokens off` dans Nginx |
| 24 | Supprimer les directives `[Session]` inertes de `php.ini` |
| 25 | Journal des connexions (réussies et échouées) |
| 26 | Registre des traitements + procédure de notification de violation (RGPD n°6, n°8) |
| 27 | Figer `pecl install imagick` (le driver d'image ne doit pas dépendre d'un build non déterministe, §5.0.2) |

---

## 6.16. Synthèse

Le projet présente un profil de sécurité **contrasté et cohérent avec son histoire** : les zones qui
ont fait l'objet d'un incident en production sont solides et documentées (l'invariant de vérification
SumUp, la gestion des secrets, le handler d'exceptions, le module RGPD), tandis que les zones jamais
éprouvées portent des faiblesses structurelles (authentification, limitation de débit, code mort
oublié).

**Trois constats se composent en une chaîne exploitable** et devraient être traités ensemble :
mot de passe à 8 caractères (V13) + aucun rate limit sur le login (V4) + aucun 2FA sur l'unique compte
admin (V18) — le tout aggravé par l'éventuelle survivance du compte `admin123` (V15).

**Deux constats sont des vestiges** dont la suppression est gratuite : `downloadFile()` (V1) et le
token Bearer (V3).

**Un seul constat est à la fois un problème de sécurité, de conformité et de disponibilité** : la
perte de l'IP cliente (V8). C'est le correctif à plus fort effet de levier de tout l'audit —
il restaure les rate limiters, la valeur probante des consentements CGV et l'utilité du journal
d'audit RGPD, pour quelques lignes de configuration Nginx.

---
# 7. Commandes et facturation

> **Méthode** : lecture intégrale de `OrderService` (652 l.), `InvoiceService`, `GiftCodeService`,
> `CartService::getCartSummary`, `Admin/OrderController`, `CreateCheckoutRequest`,
> `resources/views/pdf/invoice.blade.php` et les 3 gabarits d'email de commande.
>
> **Limite** : aucune donnée réelle n'a été consultée. Les volumétries, taux d'échec et anomalies
> effectives restent à mesurer — les requêtes sont fournies en §7.14.

## 7.1. Cycle de vie d'une commande

```
                    ┌──────────── panier actif ────────────┐
                    │                                       │
              POST /checkout                        (total = 0 €)
                    │                                       │
     expireAllPendingOrders()                     POST /checkout/confirm-free
     → anciens orders → `expired`                          │
     → checkouts SumUp désactivés                          │
                    │                                       │
                    ▼                                       ▼
            ┌───────────────┐                      completeFreeOrder()
            │   PENDING     │                      (refuse si total > 0)
            │ order_number  │                              │
            │ OT-YYYY-NNNNN │                              │
            └───────┬───────┘                              │
                    │ initiatePayment()                    │
                    │ → SumUp createCheckout               │
                    │ → Payment (pending)                  │
                    ▼                                       │
         ┌──────────────────────┐                          │
         │  widget SumUp (SDK)  │                          │
         └──────┬────────┬──────┘                          │
                │        │                                  │
      3 CHEMINS DE CONFIRMATION (tous → getCheckout())      │
                │        │                                  │
   ① webhook POST /return                                   │
   ② GET /return (retour navigateur)                        │
   ③ POST /payments/sumup/verify (polling front)            │
   ④ reconcilePendingOrders() — cron 10 min                 │
                │        │                                  │
                ▼        ▼                                  ▼
            ┌───────────────┐                    ┌──────────────────┐
            │     PAID      │◄───────────────────┤  completeOrder() │
            └───────┬───────┘                    └──────────────────┘
                    │   • markAsPaid + paid_at
                    │   • Payment → completed
                    │   • Cart → converted
                    │   • generateDownloadToken (7 j)
                    │   ── hors transaction ──
                    │   • generateForOrder() → facture PDF
                    │   • OrderConfirmationMail (+ facture en PJ)
                    │   • PrintOrderNotificationMail (si tirages non scolaires)
                    │
        ┌───────────┴────────────┐
        │ tirages ?              │
        ▼                        ▼
  print_status = null      100 % numérique
  → « à expédier »         → terminé
        │
   PUT /ship
        ▼
  print_status = 'shipped' + shipped_at

  Autres transitions :
    FAILED   ← webhook FAILED, ou reconciliation
    EXPIRED  ← nouveau checkout (expireAllPendingOrders), annulation
               explicite, ou cron quotidien 03:00 (> 24 h)
    REFUNDED ← 🔴 valeur d'enum morte, jamais écrite
```

**Statuts effectivement utilisés** : `pending`, `paid`, `failed`, `expired`.
**`refunded` existe en base et n'est jamais écrit** (§3bis.7).

**Statut détaillé calculé** pour l'admin (`Order::getDetailedStatusAttribute`) — bonne idée, il
distingue des situations que `status` seul confond :

| `detailed_status` | Condition |
|---|---|
| `payment_failed` | `status = failed` |
| `shipped` | payé, tirages, `print_status = 'shipped'` |
| `to_ship` | payé, tirages, non expédié |
| `paid` | payé, 100 % numérique |
| `checkout_initiated` | pending **sans** `sumup_checkout_id` |
| `payment_in_progress` | pending avec checkout, créé il y a < 1 h |
| `payment_abandoned` | pending avec checkout, créé il y a > 1 h |

---

## 7.2. Le calcul du prix — cinq étapes

C'est la partie la plus subtile du domaine. Le prix unitaire d'un article est résolu par une cascade
à cinq niveaux :

```
1. buildPackGroups()      → regroupe les articles par « offer signature »
                            md5(product_type + prix effectif + paliers)
                            ⇒ CUMUL CROSS-GALERIE des quantités
2. resolvePackPrice()     → palier atteint pour la quantité cumulée du groupe
3. getPriceForProductType() → sinon, prix du gallery_product_type
4. CartItem::PRODUCT_TYPES  → sinon, constante PHP
5. validateCartItems()    → si le prix stocké diffère du prix courant,
                            l'article est RÉÉCRIT au prix courant
```

L'étape 5 mérite d'être soulignée : au moment du checkout, `validateCartItems()` **recalcule et
réécrit** le prix de chaque `cart_item`. Un panier laissé ouvert pendant que l'admin change les tarifs
sera facturé **au nouveau prix**, sans avertir le client. C'est un choix défendable (on ne veut pas
honorer un prix périmé), mais il est **silencieux** : aucune notification, aucun écran de confirmation
« le prix a changé ». À arbitrer en §11.

**Le sous-total** est calculé ainsi :

```php
// sum('price') ignorait la quantité → sous-facturation SumUp.
$subtotal = collect($validItems)->sum(fn ($item) => (float) $item->price * (int) ($item->quantity ?? 1));
```

Le commentaire documente un **bug de sous-facturation corrigé** : avant l'ajout des quantités
(migration du 2026-05-09), le total envoyé à SumUp ignorait la quantité. Ce correctif est le point de
départ du problème décrit en §7.6.

**Formule finale** : `total = (subtotal − discount) + shipping_fee`.

---

## 7.3. `createFromCart()` — inventaire des garde-fous

| Garde-fou | Rôle |
|---|---|
`expireAllPendingOrders()` **avant** toute création | *« chaque tentative repart d'un order vierge — pas de réutilisation pour éviter les stale states (widget figé sur ancien prix) »*
Toute la création dans une `DB::transaction` | atomicité commande + articles
`if ($cart->items->isEmpty())` → `BusinessException` 400 | panier vide
`validateCartItems()` ignore les photos supprimées ou `is_purchasable = false` | avec `Log::warning`
`if (empty($validItems))` → 400 | panier sans article valide
`GiftCode::lockForUpdate()` + `assertUsableForCheckout()` | revalidation du code **sous verrou de ligne**, contre la course avec le webhook
`if ($requiresShipping && empty($shipping_address_line1))` → 422 | *« la FormRequest valide déjà, defense in depth »*
`shipping_country` par défaut `'FR'` | la colonne est NOT NULL
Race guard dans `expireAllPendingOrders` | `->where('status','pending')` sur l'`update` : *« sans cette clause, on écraserait silencieusement un paiement valide en `expired` »*
`persistShippingAddressOnUser()` | mémorise l'adresse sur le compte pour la prochaine fois

C'est un niveau de défense **inhabituellement élevé** pour un projet de cette taille, et chaque
garde-fou porte la trace d'un incident réel.

### Points d'attention

**1. Le consentement CGV n'est pas celui du client.** La `CreateCheckoutRequest` valide bien
`'cgv_accepted' => ['required','accepted']`, mais `OrderService` écrit ensuite des valeurs **en dur** :

```php
'cgv_accepted'    => true,
'cgv_accepted_at' => now(),
'cgv_version'     => '1.0',       // ← constante, jamais lue depuis la requête ni la config
'consent_ip'      => $consentIp,  // ← vaut 172.19.0.1 (§6.12)
```

`cgv_version` est un littéral `'1.0'` : **si les CGV évoluent, toutes les commandes continueront de
déclarer la version 1.0**. La colonne existe précisément pour tracer quelle version a été acceptée —
elle est donc inopérante. Combiné à `consent_ip` inexploitable, **la preuve d'acceptation des CGV n'a
aucune valeur** en l'état.

**2. `guest_email` est renseigné même pour un utilisateur connecté** :
`$guestEmail ?? $user?->email ?? $cart->guest_email`. Cohérent avec la commande
`orders:backfill-customer-info` (§3.11.3), mais la colonne s'appelle `guest_*` alors qu'elle contient
aussi les données des clients authentifiés. Nommage trompeur pour un futur script de migration.

**3. `validateCartItems()` ne vérifie pas que la photo est toujours accessible** — seulement
`is_purchasable`. Une photo dont la galerie a été supprimée fait échouer `$item->photo->gallery?->title`
(null-safe, donc OK) mais l'article reste facturable.

---

## 7.4. `initiatePayment()` — la réutilisation de checkout

Logique la plus dense du service. Si la commande porte déjà un `sumup_checkout_id`, le code interroge
SumUp :

| Statut SumUp | Action |
|---|---|
| `PENDING` / `NEW` | **réutilise** le checkout existant (évite d'en créer un doublon) |
| `PAID` | `completeOrder()` puis `BusinessException('Cette commande a déjà été payée.', 409)` |
| autre (`FAILED`…) | `deactivateCheckout()` (échec toléré), `sumup_checkout_id = null`, création d'un nouveau |
| appel en erreur | log + création d'un nouveau checkout |

Puis un `$order->refresh()` + re-vérification `isPaid()` avant de créer le checkout — **double
protection contre la course** avec un webhook arrivé entre-temps.

Un enregistrement `payments` est créé ou mis à jour (`provider = 'sumup'`,
`type = 'photo_purchase'`, `status = 'pending'`).

> ⚠️ **`payments.type = 'photo_purchase'`** n'est pas dans les valeurs documentées par la migration
> (*« deposit, full, gift_card »*). La colonne est un varchar libre, donc rien ne casse — mais le
> commentaire du schéma est faux (§3bis.8). À relever dans les valeurs réelles (§7.14).

---

## 7.5. `completeOrder()` — transaction et effets de bord

**Dans la transaction**, sous `Order::lockForUpdate()` :

1. si déjà `paid` → retour immédiat (idempotence) ;
2. si ni `pending` ni `failed` → `BusinessException` 409 (état inattendu) ;
3. `markAsPaid($transactionId)` + `paid_at` ;
4. `Payment` → `completed` (également `lockForUpdate`) ;
5. `Cart` → `converted` ;
6. `generateDownloadToken()` (7 jours).

**Hors transaction**, et **uniquement si cette requête a effectivement complété la commande**
(drapeau `$justCompleted`) :

7. `generateForOrder()` → facture PDF ;
8. `OrderConfirmationMail` (ou `SchoolOrderConfirmationMail`) avec la facture en pièce jointe ;
9. `PrintOrderNotificationMail` si tirages non scolaires.

> ✅ Le drapeau `$justCompleted` est exactement le bon mécanisme : *« sinon double email quand webhook
> + polling se déclenchent ensemble »*. Sortir les effets de bord de la transaction évite aussi de
> tenir un verrou pendant la génération d'un PDF et l'envoi d'emails.
>
> ⚠️ **Mais si `generateForOrder()` échoue, l'exception est avalée** (`Log::error`) et l'email part
> **sans facture** (`$invoice` reste `null`). Le client reçoit une confirmation sans document
> comptable, et rien ne signale l'anomalie à la photographe. C'est ce que la requête
> `invoices.file_path IS NULL` de la §3bis.14-F cherche à quantifier.

**Chemin sandbox** — garde-fou correct :

```php
if (config('sumup.environment') === 'sandbox' && app()->environment('local', 'testing')) {
    return $this->completeOrder($order, 'sandbox_'.time());
}
```

Double condition (config **et** environnement applicatif) : *« Sandbox SumUp ne passe jamais en PAID →
auto-complete. Double-check env pour ne pas finaliser des commandes gratuites en prod. »* En
production `SUMUP_ENV=live` **et** `APP_ENV=production`, donc doublement inaccessible. ✅

---

## 7.6. 🔴 La quantité est absente de tous les documents de vente

C'est le constat le plus significatif de cette section.

La quantité a été ajoutée aux articles le **2026-05-09** (`add_quantity_to_cart_and_order_items`).
Toutes les couches de **calcul** ont été mises à jour :

| Couche | Quantité prise en compte ? |
|---|---|
| `OrderService::createFromCart` — sous-total | ✅ `price × quantity` (bug de sous-facturation corrigé) |
| `CartService::getCartSummary` — panier | ✅ `line_total`, `items_count` |
| `OrderResource` (API) | ✅ champ `quantity` exposé |
| Affichage du panier (front) | ✅ |

Mais **aucun des quatre documents générés** n'a suivi :

| Document | Fichier | Affiche la quantité ? | Prix affiché |
|---|---|---|---|
| **Facture PDF** | `resources/views/pdf/invoice.blade.php` | ❌ | `{{ number_format($item->price, 2) }}` → **prix unitaire** |
| Email de confirmation | `emails/order-confirmation.blade.php` | ❌ | prix unitaire |
| Email confirmation scolaire | `emails/school-order-confirmation.blade.php` | ❌ | prix unitaire |
| **Email de notification tirages (admin)** | `emails/print-order-notification.blade.php` | ❌ | prix unitaire |

### Conséquence n°1 — la facture ne s'additionne pas

Pour une commande de 3 tirages 10×15 à 10 € :

```
Désignation                          Prix TTC
─────────────────────────────────────────────
Photo « portrait-01 »
  Tirage 10x15 cm                     10,00 €     ← ligne unique, quantité invisible
─────────────────────────────────────────────
Sous-total                            30,00 €     ← calculé avec la quantité
Total TTC                             30,00 €
```

**Les lignes ne totalisent pas le sous-total.** Sur un document comptable, c'est un défaut de forme
directement visible par le client, et un problème en cas de contrôle : l'article 242 nonies A du CGI
impose la mention de **la quantité et du prix unitaire** pour chaque bien livré.

### Conséquence n°2 — plus grave — la photographe imprime le mauvais nombre de tirages

`PrintOrderNotificationMail` est **l'email opérationnel** : c'est lui qui dit quoi imprimer et
expédier. Il liste les articles de tirage **sans leur quantité**. Face à une commande de 5 exemplaires
de la même photo, l'email affiche une ligne unique à 10 €.

> Ce n'est pas un défaut cosmétique : c'est une **erreur de production** systématique dès qu'un client
> commande plusieurs exemplaires. Il est probable que la photographe compense en consultant le
> back-office (où `OrderResource` expose bien la quantité), mais l'email — qui est le déclencheur du
> travail — est faux.

### Correctif

Ajouter la quantité et le total de ligne dans les 4 gabarits :

```blade
{{ $item->photo_title }}
<br><span class="product-type">{{ $item->getProductTypeLabel() }}</span>
@if($item->quantity > 1)
    <br><span>{{ $item->quantity }} × {{ number_format($item->price, 2, ',', ' ') }} €</span>
@endif
...
<td>{{ number_format($item->price * $item->quantity, 2, ',', ' ') }} €</td>
```

**À traiter en priorité 1**, avec les correctifs de sécurité de la §6.15 : l'impact est immédiat et
le correctif est trivial. Et **à vérifier sur les commandes passées** (§7.14) : combien de factures
déjà émises sont concernées ?

---

## 7.7. Facturation : numérotation, génération, stockage

| Aspect | Implémentation |
|---|---|
| Numérotation | `F-YYYY-NNNNN`, séquence annuelle |
| Concurrence | `pg_advisory_xact_lock(43)` dans une transaction |
| Idempotence | ✅ `generateForOrder()` renvoie la facture existante si elle existe |
| Déclenchement | uniquement depuis `completeOrder()` → **jamais de facture pour une commande non payée** ✅ |
| Génération | `barryvdh/laravel-dompdf`, vue `pdf.invoice` |
| Stockage | MinIO, `invoices/{order_id}/{invoice_number}.pdf` |
| Diffusion | **pièce jointe** de l'email de confirmation |
| Téléchargement | `GET /admin/orders/{id}/invoice` → URL signée 1 h (**régénère la facture si absente**) |

> ⚠️ **La numérotation est attribuée avant la génération du PDF.** La ligne `invoices` est créée dans
> une transaction, puis le PDF est produit dans un `try/catch` qui **avale l'erreur**. En cas d'échec :
> une facture numérotée existe **sans document** (`file_path = null`). Pas de trou dans la séquence
> (bon point pour la conformité), mais un numéro « fantôme ».
>
> `Admin/OrderController::downloadInvoice()` **rattrape** ce cas en régénérant à la demande — bon
> réflexe. Mais **cet endpoint n'est appelé par aucun code front** (§2.5) : la fonction de rattrapage
> existe et n'est pas accessible.

> ⚠️ **Rappel §3bis.8 — `invoices.order_id` est en `CASCADE`.** Et `Admin/OrderController::destroy()`
> est exposé (`DELETE /admin/orders/{id}`, **utilisé par le front**) :
> ```php
> $order->items()->delete();
> $order->payment?->delete();
> $order->delete();          // → la facture part en CASCADE
> ```
> **Un clic dans le back-office détruit définitivement une facture payée**, sans confirmation
> comptable, sans archivage, sans `SoftDeletes`. Cela contredit à la fois l'obligation de
> conservation (~10 ans) et la règle « CONSERVER » de `PersonalDataEraser` (§2.12).
> **C'est le défaut de conception le plus grave du domaine facturation.**

---

## 7.8. Conformité de la facture

La vue `pdf/invoice.blade.php` produit un document propre et lisible. Analyse des mentions
obligatoires (facture B2C, micro-entrepreneur en franchise de TVA) :

| Mention | État |
|---|---|
| Numéro de facture unique et séquentiel | ✅ `F-YYYY-NNNNN` |
| Date d'émission | ✅ |
| Identité du vendeur (dénomination, adresse) | ✅ via `config/invoice.php` |
| **SIRET** | ✅ (affiché deux fois) |
| Statut juridique | ✅ « Micro-entrepreneur » |
| **« TVA non applicable, art. 293 B du CGI »** | ✅ — mention correcte et obligatoire |
| Identité du client (nom) | ✅ |
| **Adresse du client** | ❌ **absente** — seuls le nom et l'email figurent, alors que `orders.shipping_*` est renseigné pour les tirages |
| **Quantité par article** | ❌ **absente** (§7.6) |
| **Prix unitaire par article** | 🟡 affiché, mais **étiqueté comme le montant de la ligne** — ambigu |
| Dénomination précise des biens | ✅ titre de la photo + libellé du produit + galerie |
| Total à payer | ✅ « Total TTC » |
| Remise détaillée | ✅ ligne « Remise (code XXX) » |
| Frais de port | ✅ ligne distincte |
| **Date de la vente / de la livraison** | 🟡 la date de commande figure ; la date de livraison n'est pas distinguée |
| **Conditions et date de règlement** | ❌ absentes |
| Référence de paiement | ✅ « Carte bancaire via SumUp » + `sumup_transaction_id` |

> **Deux manques à corriger** en priorité : la **quantité** (§7.6) et l'**adresse du client**.
> Les données existent en base ; c'est une modification de gabarit.
>
> **Remarque de forme** : l'en-tête de colonne « Prix TTC » et le libellé « Total TTC » sont
> impropres pour une activité en franchise de TVA — il n'y a pas de TVA, donc pas de « toutes taxes
> comprises ». « Montant » / « Total » suffirait et éviterait une contradiction avec la mention
> art. 293 B affichée juste en dessous.
>
> **Point positif notable** : la facture est **auto-portante**. Grâce à la dénormalisation
> `order_items.photo_title` / `gallery_title` (§3bis.7), elle reste lisible après suppression de la
> photo ou de la galerie. Bon réflexe, à conserver.

---

## 7.9. Ce qui n'existe pas

| Fonction | État | Impact |
|---|---|---|
| **Remboursement** | ❌ aucune intégration de l'API de remboursement SumUp ; `orders.status = 'refunded'` jamais écrit | Un remboursement se fait **hors application** (dashboard SumUp) et **ne laisse aucune trace en base**. La comptabilité applicative est alors fausse |
| **Avoir / note de crédit** | ❌ | Corollaire du précédent : impossible d'annuler comptablement une facture émise |
| **Annulation d'une commande payée** | ❌ | Seule option : `DELETE` (destructif, §7.7) |
| **Modification d'une commande** | ❌ | Aucun endpoint |
| **Paiement partiel / acompte** | ❌ | `payments.type` prévoit `deposit` — jamais utilisé |
| **Bon cadeau comme moyen de paiement** | ❌ | `gift_cards` n'est pas accepté au panier (§2.9) |
| **Relance de panier abandonné** | ❌ | Les paniers expirent silencieusement à 7 j |
| **Relance de commande impayée** | 🟡 `retryPayment` existe (admin) mais **vérifie** le paiement, il ne relance pas le client |
| **Export comptable** (CSV/FEC) | ❌ | Aucun export des ventes ; seul l'export RGPD global produit du JSON |
| **Tableau de bord chiffre d'affaires** | 🟡 `Admin/DashboardController` agrège les `payments`, pas les `orders` |

> **Le trou le plus structurant est le remboursement.** Une activité de vente en ligne sans mécanisme
> d'avoir ne peut pas tenir une comptabilité juste dans l'application : dès le premier geste
> commercial, la base et la réalité divergent. À arbitrer en §11 — au minimum un statut `refunded`
> écrit à la main depuis l'admin, avec un avoir numéroté.
>
> **L'export comptable** est le second manque pratique : la photographe doit aujourd'hui reconstituer
> son chiffre d'affaires depuis le dashboard SumUp ou en interrogeant la base.

---

## 7.10. Frais de port : la règle du maximum

```php
// Per-gallery fee; take the highest among shipping items to avoid undercharging mixed carts.
$shippingFee = $requiresShipping
    ? (float) $shippingItems->map(fn ($item) => $item->photo?->gallery
        ? $item->photo->gallery->getShippingFee()
        : (float) config('shop.shipping_fee_print', 0))->max()
    : 0.0;
```

Règle : **un seul frais de port par commande**, égal au **plus élevé** des frais des galeries
concernées. Défaut global : `SHOP_SHIPPING_FEE_PRINT = 2,00 €` ; surcharge possible par galerie
(`galleries.shipping_fee`).

`print_scolaire` est exempté (remise à l'école), avec surcharge possible par
`gallery_product_types.requires_shipping` (booléen à trois états, §3bis.6).

> ✅ La règle du max est cohérente (elle évite de sous-facturer un panier mixte) et **identique
> dans `CartService::getCartSummary` et `OrderService::createFromCart`** — donc le panier affiché
> correspond au montant facturé. C'est important et c'est correct.
>
> ⚠️ Mais la logique est **dupliquée** dans les deux services (le même bloc de 8 lignes, deux fois).
> Toute évolution doit être faite aux deux endroits, sinon l'affichage et la facturation divergent.
> À factoriser dans `PricingService`.

---

## 7.11. Codes promo : le quota et sa fragilité

| Aspect | Implémentation |
|---|---|
| Résolution | `strtoupper(trim($code))` puis recherche exacte |
| Validation | actif, `valid_from`/`valid_until`, quota `max_uses` |
| **Quota** | `paidCount()` = `COUNT(orders WHERE gift_code_id = … AND status = 'paid')` |
| Verrou au checkout | ✅ `lockForUpdate()` + `assertUsableForCheckout()` **dans la transaction** |
| Auto-nettoyage | un code devenu invalide est retiré silencieusement du panier |
| Snapshot | `orders.gift_code` (texte) + `orders.discount_amount` conservés |
| Rate limit | ✅ `throttle:gift-code` 20/min (le seul limiteur pertinent du projet) |

> ✅ **Le calcul du quota à la volée est le bon choix** : pas de compteur à resynchroniser, pas de
> dérive possible. Et le snapshot sur la commande garantit que la facture reste juste même si le code
> est modifié ou supprimé (`nullOnDelete`).
>
> ⚠️ **Mais le quota est couplé à la table `orders`.** Trois conséquences :
> 1. Toute suppression de commande (§7.7) **libère** une utilisation du code ;
> 2. l'anonymisation RGPD des commandes `expired`/`failed` est sans effet (elles ne comptent pas),
>    mais une purge des commandes `paid` au bout de 10 ans **réinitialiserait** les quotas ;
> 3. le script de migration doit **conserver `gift_code_id` sur les commandes payées**, sinon les
>    quotas des codes encore actifs sautent (§10).
>
> ⚠️ La validation ne vérifie **pas** de montant minimum de commande, ni de restriction par galerie ou
> par produit. Un code « −50 % » s'applique à n'importe quel panier, y compris un panier à 6 € de
> tirage scolaire. À confirmer comme voulu.

---

## 7.12. Vue administrateur des commandes

| Endpoint | Fonction | Consommé ? |
|---|---|---|
| `GET /admin/orders` | liste paginée (`per_page` accepté ✅), filtres `status` et `search` (n° de commande, email invité, email du compte) | ✅ |
| `GET /admin/orders/{id}` | détail + user + payment + identifiants SumUp | ✅ |
| `GET /admin/orders/{id}/download-link` | **régénère** le token (7 j) et renvoie le lien client — pour le support | ✅ |
| `POST /admin/orders/{id}/retry-payment` | revérifie le statut auprès de SumUp et complète si `PAID` | ✅ |
| `PUT /admin/orders/{id}/ship` | marque les tirages expédiés (refuse si non payé ou sans tirage ✅) | ✅ |
| `GET /admin/orders/{id}/invoice` | URL signée, **régénère la facture si absente** | 🔴 **non appelé** |
| `DELETE /admin/orders/{id}` | suppression | ✅ ⚠️ (§7.7) |

**Filtre notable** : `index()` exclut les commandes scolaires —
`whereDoesntHave('items.photo.gallery', fn ($g) => $g->whereNotNull('school_session_id'))` — car elles
ont leur propre écran (`/admin/school-sessions/{id}`).

> ⚠️ **Angle mort sur les commandes mixtes.** L'exclusion se fait dès qu'**au moins un** article vient
> d'une galerie scolaire, alors que `OrderService::isSchoolOrder()` exige que **tous** les articles
> soient `print_scolaire`. Une commande mêlant un tirage scolaire et une photo d'une autre galerie
> serait donc :
> - **absente** de la liste `/admin/orders` (exclue par le `whereDoesntHave`) ;
> - **absente** de l'écran de session scolaire, qui liste par session ;
> - traitée comme une commande **non scolaire** pour les emails (`isSchoolOrder()` = false → mail
>   standard + notification tirages).
>
> Une telle commande deviendrait **invisible dans le back-office**. Est-ce possible en pratique ? Le
> panier accepte des articles de galeries différentes (le cumul cross-galerie des packs en dépend,
> §7.2), donc **oui, techniquement**. À vérifier en base (§7.14) et à corriger : le filtre devrait
> être symétrique de `isSchoolOrder()`.

---

## 7.13. Le cas scolaire

| Aspect | Traitement |
|---|---|
| Détection | `isSchoolOrder()` = **tous** les articles en `print_scolaire` |
| Email | `SchoolOrderConfirmationMail` (avec facture en PJ) au lieu de `OrderConfirmationMail` |
| Notification tirages | **supprimée** — *« les school orders ont leur propre vue admin »* |
| Frais de port | aucun (remise à l'école) |
| Adresse de livraison | non demandée |
| Suivi de production | `GET /admin/school-sessions/{id}/orders` + export ZIP par classe/enfant avec `_index.csv` |
| Clôture | `closed_at` sur la session bloque les nouvelles commandes |

Le circuit est cohérent : les commandes scolaires ne polluent pas la liste générale, et l'export ZIP
remplace la notification unitaire. **Mais il repose sur deux définitions différentes du « scolaire »**
(§7.12), et le manque de quantité sur l'email de confirmation (§7.6) touche aussi les parents.

---

## 7.14. Requêtes de diagnostic

À jouer sur une **copie** de la production, en complément de la §3bis.14.

### A. Photographie de l'activité commerciale

```sql
-- Volumétrie et répartition par statut
SELECT status, COUNT(*) AS n, SUM(total) AS montant, MIN(created_at), MAX(created_at)
FROM orders GROUP BY status ORDER BY n DESC;

-- Chiffre d'affaires par mois (commandes payées)
SELECT date_trunc('month', paid_at) AS mois, COUNT(*) AS commandes,
       SUM(subtotal) AS sous_total, SUM(discount_amount) AS remises,
       SUM(shipping_fee) AS port, SUM(total) AS ca
FROM orders WHERE status = 'paid' GROUP BY 1 ORDER BY 1;

-- Mix produit
SELECT product_type, COUNT(*) AS lignes, SUM(quantity) AS unites,
       SUM(price * quantity) AS montant
FROM order_items oi JOIN orders o ON o.id = oi.order_id
WHERE o.status = 'paid' GROUP BY 1 ORDER BY 4 DESC;
```

### B. 🔴 Portée du bug de quantité (§7.6)

```sql
-- Combien de commandes payées comportent au moins un article en quantité > 1 ?
-- → ce sont les factures déjà émises dont les lignes ne totalisent pas le sous-total
SELECT COUNT(DISTINCT o.id) AS commandes_touchees,
       MIN(o.created_at) AS premiere, MAX(o.created_at) AS derniere
FROM orders o JOIN order_items oi ON oi.order_id = o.id
WHERE o.status = 'paid' AND oi.quantity > 1;

-- Détail, avec le numéro de facture concerné
SELECT o.order_number, i.invoice_number, o.paid_at, o.subtotal,
       SUM(oi.price)                  AS somme_lignes_facture,
       SUM(oi.price * oi.quantity)    AS somme_reelle
FROM orders o
JOIN order_items oi ON oi.order_id = o.id
LEFT JOIN invoices i ON i.order_id = o.id
WHERE o.status = 'paid'
GROUP BY o.id, o.order_number, i.invoice_number, o.paid_at, o.subtotal
HAVING SUM(oi.price) <> SUM(oi.price * oi.quantity)
ORDER BY o.paid_at DESC;
```

### C. Cohérence comptable

```sql
-- Totaux incohérents
SELECT COUNT(*) FROM orders
WHERE ABS(total - (subtotal - discount_amount + shipping_fee)) > 0.01;

-- Sous-total incohérent avec les lignes (doit être vide APRÈS correction du calcul)
SELECT o.order_number, o.subtotal, SUM(oi.price * oi.quantity) AS calcule
FROM orders o JOIN order_items oi ON oi.order_id = o.id
GROUP BY o.id, o.order_number, o.subtotal
HAVING ABS(o.subtotal - SUM(oi.price * oi.quantity)) > 0.01;

-- Commandes payées sans facture / factures sans PDF
SELECT COUNT(*) FROM orders o WHERE o.status = 'paid'
  AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.order_id = o.id);
SELECT COUNT(*) FROM invoices WHERE file_path IS NULL OR generated_at IS NULL;

-- Continuité de la séquence de facturation (aucun trou attendu)
WITH n AS (
  SELECT invoice_number,
         CAST(SUBSTRING(invoice_number FROM 'F-\d{4}-(\d+)') AS INTEGER) AS seq,
         SUBSTRING(invoice_number FROM 'F-(\d{4})') AS annee
  FROM invoices
)
SELECT annee, MIN(seq), MAX(seq), COUNT(*), MAX(seq) - MIN(seq) + 1 - COUNT(*) AS trous
FROM n GROUP BY annee ORDER BY annee;
```

### D. Santé du tunnel de paiement (méthode de `docs/analyse-paiement-sumup.md`)

```sql
-- Taux de conversion réel
SELECT COUNT(*) FILTER (WHERE status = 'paid')    AS payees,
       COUNT(*) FILTER (WHERE status = 'failed')  AS echouees,
       COUNT(*) FILTER (WHERE status = 'expired') AS abandonnees,
       COUNT(*) FILTER (WHERE status = 'pending') AS en_cours,
       ROUND(100.0 * COUNT(*) FILTER (WHERE status = 'paid') / NULLIF(COUNT(*), 0), 1) AS taux_pct
FROM orders;

-- Cause des échecs : NULL transaction_id = refus émetteur immédiat
SELECT sumup_transaction_id IS NULL AS refus_immediat, COUNT(*)
FROM orders WHERE status = 'failed' GROUP BY 1;

-- Discrimination par délai de résolution (< 1 min = decline, 5-15 min = 3DS abandonné, > 1 h = expiré)
SELECT CASE
         WHEN EXTRACT(EPOCH FROM (updated_at - created_at)) < 60   THEN '< 1 min (decline)'
         WHEN EXTRACT(EPOCH FROM (updated_at - created_at)) < 900  THEN '1-15 min (3DS)'
         WHEN EXTRACT(EPOCH FROM (updated_at - created_at)) < 3600 THEN '15-60 min'
         ELSE '> 1 h (expiré)' END AS fenetre,
       COUNT(*)
FROM orders WHERE status = 'failed' GROUP BY 1 ORDER BY 1;

-- Efficacité de la réconciliation : commandes payées bien après leur création
SELECT COUNT(*) FROM orders
WHERE status = 'paid' AND paid_at - created_at > interval '15 minutes';
```

### E. Anomalies structurelles relevées dans cette section

```sql
-- Commandes MIXTES scolaire / non-scolaire → invisibles dans le back-office (§7.12)
SELECT o.id, o.order_number, o.status, o.created_at
FROM orders o
WHERE EXISTS (
        SELECT 1 FROM order_items oi JOIN photos p ON p.id = oi.photo_id
        JOIN galleries g ON g.id = p.gallery_id
        WHERE oi.order_id = o.id AND g.school_session_id IS NOT NULL)
  AND EXISTS (
        SELECT 1 FROM order_items oi2 WHERE oi2.order_id = o.id
          AND oi2.product_type <> 'print_scolaire');

-- Valeurs réelles de payments (le commentaire du schéma est faux, §7.4)
SELECT provider, type, status, COUNT(*) FROM payments GROUP BY 1,2,3 ORDER BY 4 DESC;

-- Paiements orphelins / doublons
SELECT order_id, COUNT(*) FROM payments
WHERE order_id IS NOT NULL GROUP BY order_id HAVING COUNT(*) > 1;

-- Tirages payés jamais expédiés (backlog de production)
SELECT o.order_number, o.paid_at, o.shipping_city
FROM orders o
WHERE o.status = 'paid' AND o.print_status IS DISTINCT FROM 'shipped'
  AND EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id
                AND oi.product_type LIKE 'print_%')
ORDER BY o.paid_at;

-- Utilisation réelle des codes promo vs quota
SELECT gc.code, gc.type, gc.value, gc.max_uses,
       COUNT(*) FILTER (WHERE o.status = 'paid') AS utilisations_payees,
       SUM(o.discount_amount) FILTER (WHERE o.status = 'paid') AS remise_totale
FROM gift_codes gc LEFT JOIN orders o ON o.gift_code_id = gc.id
GROUP BY gc.id, gc.code, gc.type, gc.value, gc.max_uses ORDER BY 5 DESC NULLS LAST;

-- Commandes gratuites (chemin confirm-free)
SELECT COUNT(*) FROM orders WHERE status = 'paid' AND total = 0;
```

---

## 7.15. Synthèse §7 — constats classés

| # | Constat | Gravité | Section |
|---|---|---|---|
| 1 | **Quantité absente de la facture PDF et des 3 emails** → factures qui ne s'additionnent pas, et **mauvais nombre de tirages imprimés** | 🔴 **Élevée** | §7.6 |
| 2 | **`DELETE /admin/orders/{id}` détruit la facture par CASCADE** — un clic, aucune confirmation | 🔴 **Élevée** | §7.7 |
| 3 | **Aucun mécanisme de remboursement ni d'avoir** → comptabilité applicative divergente dès le premier geste commercial | 🔴 Élevée | §7.9 |
| 4 | **`cgv_version` en dur à `'1.0'`** + `consent_ip` inexploitable → preuve d'acceptation des CGV sans valeur | 🟠 Moyenne | §7.3 |
| 5 | **Adresse du client absente de la facture** (mention obligatoire) | 🟠 Moyenne | §7.8 |
| 6 | **Commandes mixtes scolaire/non-scolaire invisibles** dans le back-office | 🟠 Moyenne | §7.12 |
| 7 | **Échec de génération de facture avalé** → email sans facture, aucune alerte | 🟠 Moyenne | §7.5 |
| 8 | **`GET /admin/orders/{id}/invoice` non appelé** → la fonction de rattrapage est inaccessible | 🟠 Moyenne | §7.7 |
| 9 | **Aucun export comptable** (CSV/FEC) | 🟠 Moyenne | §7.9 |
| 10 | **Réécriture silencieuse des prix** au checkout si le tarif a changé | 🟡 Faible | §7.2 |
| 11 | **Logique des frais de port dupliquée** dans 2 services | 🟡 Faible | §7.10 |
| 12 | **Quota des codes promo couplé à `orders`** → suppression/purge libère des utilisations | 🟡 Faible | §7.11 |
| 13 | Libellés « Prix TTC » / « Total TTC » impropres en franchise de TVA | 🟡 Faible | §7.8 |
| 14 | `payments.type = 'photo_purchase'` hors valeurs documentées | 🟡 Faible | §7.4 |
| 15 | Aucune contrainte de montant minimum ni de portée sur les codes promo | 🟡 Faible | §7.11 |
| 16 | Aucune relance de panier abandonné ni de commande impayée | 🟡 Faible | §7.9 |

**Ce qui est solide et à reprendre tel quel** : la machine à états et ses quatre chemins de
confirmation redondants, l'idempotence de `completeOrder()` sous verrou avec le drapeau
`$justCompleted`, `expireAllPendingOrders()` et son garde-fou anti-race, la revalidation du code promo
sous `lockForUpdate`, le refus explicite de `completeFreeOrder()` sur un total > 0, la double garde du
chemin sandbox, la numérotation sous verrou consultatif, l'idempotence de la facturation, et la
dénormalisation qui rend la facture auto-portante.

**Le domaine paiement est le mieux durci du projet** — parce qu'il a échoué en production et qu'on l'a
réparé, comme en témoignent les commentaires. **Le domaine facturation, lui, n'a jamais été
éprouvé** : c'est là que se concentrent les trois constats les plus graves de la section.

---
# 8. Jobs, files d'attente et tâches planifiées

> **Méthode** : lecture des 7 jobs, de `routes/console.php`, de `config/queue.php`, des 4 sondes, de
> `HeartbeatService`, `SupervisionAlertService`, `AlertCatalog`, des 4 commandes de supervision, des
> healthchecks Docker — et **des deux documents internes `docs/supervision.md` (655 lignes) et
> `docs/audit-supervision.md`**, qui se sont révélés être la meilleure source de cette section.

## 8.0. Note préliminaire : cette section s'appuie sur une documentation de qualité

Contrairement aux autres domaines, la supervision est **documentée par le projet lui-même**, avec une
rigueur inhabituelle : un audit initial (`audit-supervision.md`) qui liste 5 angles morts par gravité,
puis un document de conception (`supervision.md`) qui justifie chaque choix d'implémentation, décrit
**deux incidents de production datés** et énumère ce qui reste à faire.

Plusieurs constats de mes sections précédentes y trouvent leur explication :

| Constat antérieur | Explication trouvée ici |
|---|---|
| §4.7 : « `schedule:clear-cache` avant `schedule:work` » sans justification | Prévention d'un **incident réel du 20/08/2026** — mutex `withoutOverlapping` coincé (§8.11) |
| §4.7 : couplage caché du cache fichier par bind mount | **Documenté** : *« le cache fichier est partagé entre conteneurs via le bind mount `./api:/var/www` — c'est ce qui rend les heartbeats en cache lisibles par le health »* |
| §4.8 : healthchecks.io implémenté mais désactivé | **Volontaire** : c'est une action manuelle listée au §11 de `supervision.md`, non encore réalisée |
| §4.11 : « Cloudflare et Render non supervisés » | **Confirmé et documenté** : les sondes externes (UptimeRobot) sont spécifiées mais pas créées |
| §4.6 : Sentry sobre, `traces_sample_rate = 0` | Choix assumé de rester dans les plans gratuits |

---

## 8.1. Architecture d'exécution asynchrone

```
┌─────────────────────────────────────────────────────────────────────┐
│  api-php (php-fpm, 30 workers)                                      │
│    dispatch() → table `jobs`  (driver: database, queue: default)     │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│  api-queue  ─ UN SEUL processus                                     │
│  php artisan queue:work --sleep=3 --tries=3                         │
│                         --max-time=3600 --timeout=3600 --memory=2048│
│                                                                     │
│  → event Looping ⇒ heartbeat « queue » (max 1 écriture/min)         │
│  → échec définitif ⇒ table `failed_jobs`                            │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│  api-scheduler                                                      │
│  schedule:clear-cache && supervision:heartbeat scheduler            │
│                       && schedule:work                              │
│  → 6 tâches planifiées (§8.5)                                       │
└─────────────────────────────────────────────────────────────────────┘

Healthchecks Docker :
  api-queue     → supervision:heartbeat:check queue     --max-age=600
  api-scheduler → supervision:heartbeat:check scheduler --max-age=900
```

## 8.2. Configuration de la file

| Clé | Valeur | Commentaire |
|---|---|---|
| `QUEUE_CONNECTION` | `database` | *« et non sync : c'est cette file que consomme le conteneur api-queue »* |
| Table | `jobs` | + `job_batches` (**inutilisée** : aucun `Bus::batch()` dans le code) |
| Queue | `default` (unique) | §8.3 |
| `retry_after` | **7200 s** (2 h) | correctement supérieur au plus long `timeout` (3600 s) ✅ |
| Échecs | table `failed_jobs` | |

**Options du worker** :

| Option | Valeur | Effet |
|---|---|---|
| `--sleep=3` | 3 s | attente entre deux sondages quand la file est vide |
| `--tries=3` | 3 | **surchargé par la propriété `$tries` de chaque job** (qui prime) |
| `--max-time=3600` | 1 h | le worker se termine au bout d'une heure ; Docker le relance (`restart: unless-stopped`) — bonne parade aux fuites mémoire |
| `--timeout=3600` | 1 h | surchargé par `$timeout` de chaque job |
| `--memory=2048` | 2 Go | aligné sur `memory_limit` de `php.ini` |

> ⚠️ **`--memory=2048` est égal au `memory_limit` de PHP.** Le worker ne se termine donc que
> lorsqu'il a *atteint* la limite du process — au lieu de s'arrêter proprement avant. Une valeur
> inférieure (par ex. `--memory=1024`) redémarrerait le worker avant l'OOM plutôt qu'après. Avec un
> pic mémoire de 200-300 Mo par photo (commentaire de `storeAsync`, §5.13), la marge est confortable,
> mais le réglage est à l'envers de son intention.

---

## 8.3. 🔴 Une seule file, un seul worker : le problème de tête de ligne

**Tous les jobs partagent la queue `default`, consommée par un unique processus.** Or leurs
caractéristiques temporelles sont radicalement différentes :

| Job | `timeout` | Nature |
|---|---|---|
| `SendSchoolSessionSmsJob` | 30 s | temps réel — un parent attend son lien |
| Envoi d'emails (`Mail::queue`) | défaut | temps réel — confirmation de commande, accès galerie |
| `GenerateCleanThumbnailJob` | 120 s | différé |
| `ProcessPhotoJob` | 300 s | différé |
| `DispatchSmsBatchJob` | 300 s | orchestration |
| `ProcessSchoolSessionJob` | **3600 s** | traitement lourd |
| `GenerateSchoolSessionExportJob` | **3600 s** | traitement lourd |
| `GeneratePrivacyExportJob` | **3600 s** | traitement lourd |

**Conséquence directe** : lorsqu'un export scolaire ou un export RGPD démarre, il occupe le worker
**jusqu'à une heure**. Pendant tout ce temps :

- aucun email de confirmation de commande ne part — donc **aucune facture n'atteint le client** ;
- aucun email d'accès à une galerie ne part ;
- aucun SMS ne part ;
- les photos uploadées sur une session scolaire ne sont pas traitées.

Le scénario est loin d'être théorique : la photographe clôture une session scolaire, lance l'export
ZIP (précisément le moment où les commandes de la session arrivent), et **les confirmations de
paiement restent en file pendant l'export**. Le client a payé, il ne reçoit rien.

Pire : `ProcessSchoolSessionJob` (extraction du ZIP, création des galeries) est **suivi** par N
`ProcessPhotoJob`. Sur une session de 300 photos, la file contient 300 jobs de 300 s max, traités
**séquentiellement par un seul worker**. Même à 5 s par photo, cela fait 25 minutes pendant lesquelles
tout le transactionnel attend.

> **Correctif — le plus rentable de cette section** :
> 1. **Séparer les files par priorité** :
>    ```
>    queue:work --queue=realtime,default,heavy
>    ```
>    avec `Mail::queue(...)->onQueue('realtime')`, les SMS sur `realtime`, et les 3 exports +
>    `ProcessPhotoJob` sur `heavy`. Laravel traite les files dans l'ordre déclaré.
> 2. **Ou** lancer un second conteneur worker dédié aux jobs longs
>    (`queue:work --queue=heavy`), en laissant `api-queue` sur `realtime,default`.
>
> La première option ne coûte qu'un changement de commande et quelques `onQueue()`. **C'est le
> correctif à plus fort impact utilisateur de tout l'audit** après ceux de la §6.

> Note : `job_batches` existe (créée par la migration standard) mais **aucun `Bus::batch()` n'est
> utilisé** — le suivi de lot passe par la table métier `photo_uploads` et sa colonne `batch_id`
> (§3bis.5). Choix cohérent (le suivi métier survit à la purge de la file), mais la table est morte.

---

## 8.4. Les 7 jobs

| Job | `tries` | `timeout` | `backoff` | `maxExceptions` | Dispatché par |
|---|---|---|---|---|---|
| `ProcessPhotoJob` | 3 | 300 s | 10/30/60 | — | `SchoolSessionService`, `SchoolSessionController`, `photos:retry-failed` |
| `GenerateCleanThumbnailJob` | 3 | 120 s | 10/30/60 | — | `PhotoController` (×2), `photos:generate-clean-thumbnails` |
| `SendSchoolSessionSmsJob` | 3 | 30 s | 10/30/60 | — | `DispatchSmsBatchJob`, **`GalleryController::sendAccessSms`** |
| `DispatchSmsBatchJob` | 1 | 300 s | — | — | `SchoolSessionController::sendMessages` |
| `ProcessSchoolSessionJob` | 1 | 3600 s | — | 1 | `SchoolSessionController::process` |
| `GenerateSchoolSessionExportJob` | 1 | 3600 s | — | 1 | `SchoolSessionController::createExport` |
| `GeneratePrivacyExportJob` | 1 | 3600 s | — | 1 | `PrivacyController` (×2) |

### Ce qui est bien fait

**`tries = 1` sur les jobs longs, avec état persisté.** Rejouer une extraction de ZIP ou un export de
3600 s serait pire que d'échouer proprement. Les trois jobs longs écrivent leur statut en base
(`school_sessions.status`, `school_session_exports.status`, `privacy_exports.status`) **et**
implémentent `failed()` pour marquer l'échec de façon idempotente (`if ($x->status !== 'failed')`).
L'administrateur voit l'échec dans l'interface et peut relancer. ✅

**Discipline de reprise de `ProcessPhotoJob`** (§5.2.2) : sur échec, ni nettoyage du fichier
temporaire, ni `markAsFailed` — seul `failed()` le fait, après épuisement des tentatives. C'est
exactement le bon patron. ✅

**`DispatchSmsBatchJob` : un orchestrateur plutôt qu'une boucle.** Il re-dispatche N
`SendSchoolSessionSmsJob` avec un délai progressif (`intdiv($i, $perSecond)`, 10/s par défaut) pour
rester sous les limites Brevo et éviter un timeout HTTP sur l'endpoint appelant. Élégant, et le
`->delay()` fait que ces jobs n'apparaissent pas comme « en attente depuis trop longtemps » pour la
sonde (qui filtre sur `available_at <= now()`). ✅

**`GenerateCleanThumbnailJob` est défensif** : sort immédiatement si la photo a disparu, n'est plus
téléchargeable, est une vidéo, ou a déjà sa vignette. Idempotent. ✅

### Les points faibles

| # | Constat |
|---|---|
| 1 | **`SendSchoolSessionSmsJob` retente sur des échecs permanents.** `BrevoSmsService::send()` renvoie `false` aussi bien pour une API indisponible que pour **un numéro invalide** ou un expéditeur mal configuré. Le job relance donc 3 fois (10 s, 30 s, 60 s) pour un numéro qui ne sera jamais valide, puis pollue `failed_jobs`. Il faudrait distinguer les causes (le service log déjà `invalid phone number` en `warning`) et ne retenter que le transitoire |
| 2 | **`ProcessSchoolSessionJob` avale son exception.** Le `catch (\Throwable)` marque la session `failed` et **ne relance pas** — donc `failed()` n'est jamais appelé et **le job apparaît comme réussi** dans la file. Cohérent avec `tries = 1`, mais cela signifie qu'**une session scolaire en échec n'apparaît jamais dans `failed_jobs`** : l'alerte `queue_failed_jobs` ne se déclenchera pas. La seule trace est le statut en base et les logs |
| 3 | **Même schéma pour les 2 jobs d'export** : le `catch` marque `failed` en base sans relancer. Les échecs d'export RGPD — avec un délai réglementaire à la clé — sont donc **invisibles pour la supervision** |
| 4 | **Aucun job n'est idempotent sur ses effets de bord MinIO.** Un `ProcessPhotoJob` qui échoue *après* l'upload des dérivés mais *avant* la création de la ligne `photos` laisse des fichiers orphelins (rattrapés seulement par `storage:clean-orphans`, non planifiée, §5.14) |
| 5 | **`SendSchoolSessionSmsJob` est utilisé hors scolaire** (§3.11.1) — nommage trompeur |
| 6 | **Aucun job n'est mis en file avec `afterCommit()`** et `after_commit` est à `false` dans la config. Les dispatch se font hors transaction dans le code lu, donc pas de bug constaté — mais la garantie n'est pas structurelle |

> **Le point n°2/n°3 est important** : le mécanisme d'alerte le plus visible (`failed_jobs > 0`) ne
> couvre **aucun des trois traitements les plus longs et les plus critiques**. Un export RGPD perdu
> — cas explicitement cité comme angle morté n°3 dans `audit-supervision.md` — reste silencieux, alors
> même que le dispositif était censé le couvrir. Il faudrait soit relancer l'exception après avoir
> marqué le statut, soit ajouter une sonde sur les lignes `*_exports` en statut `failed`.

---

## 8.5. Les 6 tâches planifiées

| Tâche | Fréquence | Mécanisme | Verrou | Ping externe |
|---|---|---|---|---|
| Réconciliation des commandes SumUp | **/10 min** | `Schedule::call` | `withoutOverlapping(10)` | ✅ `HEALTHCHECKS_RECONCILE_URL` |
| Nettoyage paniers / commandes abandonnées | quotidien **03:00** | `Schedule::call` | — | — |
| Purge RGPD hebdomadaire | dimanche **04:00** | `Schedule::call` | — | ✅ `HEALTHCHECKS_RGPD_URL` |
| `privacy:purge-expired --years=10` | 1er du mois **05:00** | `Schedule::command` | `withoutOverlapping()` | — |
| `supervision:heartbeat scheduler` | **/5 min** | `Schedule::command` | `withoutOverlapping(10)` | — |
| `supervision:alert` | **/15 min** | `Schedule::command` | `withoutOverlapping(30)` | — |
| `supervision:report` | quotidien **08:00** | `Schedule::command` | `withoutOverlapping(60)` | — |

(7 entrées : la réconciliation, 2 nettoyages, la purge légale, et 3 tâches de supervision.)

**Deux choix de conception explicités et justes** :

1. **Le heartbeat passe par `Schedule::command`, pas `Schedule::call`** :
   > *« Le heartbeat prouve ainsi que `schedule:run` tourne **et** que le fork de tâches fonctionne —
   > mécanisme dont dépendent `privacy:purge-expired` et la réconciliation des paiements. Un
   > `Schedule::call` aurait continué à écrire un heartbeat vert alors que les tâches forkées
   > échouaient. »*

   C'est un raisonnement d'une finesse remarquable : le heartbeat teste le **mécanisme** dont
   dépendent les tâches critiques, pas seulement la boucle du scheduler.

2. **`withoutOverlapping()` avec une expiration courte et explicite** (10, 30, 60 min) au lieu du
   défaut de **1440 minutes** — voir l'incident du §8.11.

> ⚠️ **Deux tâches n'ont ni verrou ni ping** : le nettoyage quotidien de 03:00 et la purge RGPD
> hebdomadaire de 04:00 (celle-ci a un ping mais pas de verrou). Elles font des `UPDATE`/`DELETE` en
> masse ; un chevauchement serait sans dommage (idempotent), mais un `withoutOverlapping` coûte une
> ligne.
>
> ⚠️ **`privacy:purge-expired` n'a pas de ping healthchecks.io** alors que c'est une tâche
> **mensuelle** — donc celle dont le silence est le plus difficile à remarquer. Une purge légale non
> exécutée pendant six mois passerait totalement inaperçue.

---

## 8.6. Le dispositif de supervision : trois niveaux

```
NIVEAU 1 — /api/health (public)               → statut agrégé + version
NIVEAU 2 — /api/health/details (token)        → détail des 4 sondes + mesures
NIVEAU 3 — /api/admin/health (auth + admin)   → idem, 🔴 non appelé par le front
```

Une route `/health/tables` **a été supprimée** : elle publiait le schéma de la base.
`/api/health` renvoie **503** si une sonde est dégradée — ce qui permet à une sonde externe de
détecter la dégradation par simple code HTTP.

**Pas de cache du résultat des sondes**, choix assumé et justifié :
> *« `/api/health` exécute réellement les sondes à chaque appel (1 requête SQL + 1 appel S3, ~20 à
> 50 ms) : un état de santé mis en cache peut mentir au pire moment. L'abus est borné par un rate
> limit de 60 req/min/IP. »*

---

## 8.7. Les 4 sondes

### `DatabaseProbe`

Chronomètre **séparément** l'ouverture de connexion et le `SELECT 1`, avec deux seuils distincts
(`database_connect_slow_ms = 2500`, `database_slow_ms = 1000`) et deux motifs d'alerte distincts.

> Le raisonnement (issu d'un incident, §8.11) : `PDO::ATTR_PERSISTENT = false` et `supervision:alert`
> tourne dans un process forké, donc la connexion est toujours neuve. Mélanger les deux coûts
> facturerait à la base le DNS, la poignée de main TLS et celle du pooler.
>
> **Corollaire relevé par la doc et important pour la refonte** : *« comme rien n'est persistant,
> `connect_time_ms` est un coût que **chaque requête HTTP** paie aussi, ce qui en fait un indicateur
> de performance à part entière »*. À croiser avec le choix du pooler transaction (§5.0.1).

### `StorageProbe`

Objet témoin (`fileExists`) si `SUPERVISION_STORAGE_WITNESS` est renseigné, sinon simple `files()` sur
un préfixe inexistant pour valider que l'appel S3 aboutit. Deux motifs : `storage_unreachable`,
`storage_witness_missing`.
🔴 **La variable est vide en production** → mode « listing » seulement (§4.3).

### `QueueProbe` — la plus complète, 4 indicateurs

| Indicateur | Seuil | Motif |
|---|---|---|
| `failed_jobs` sur 24 h | > 0 | `queue_failed_jobs` |
| Profondeur (`reserved_at IS NULL`) | > 100 | `queue_depth` |
| Âge du plus ancien job **disponible** (`available_at <= now()`) | > 15 min | `queue_stalled` |
| Fraîcheur du heartbeat worker | > 10 min → dégradé ; absent → **down** | `queue_worker_stale` / `queue_worker_missing` |

Le filtre `available_at <= now()` est correct : il évite de compter comme « bloqués » les SMS
volontairement retardés par `DispatchSmsBatchJob`. ✅

### `SchedulerProbe`

Uniquement la fraîcheur du heartbeat : > 120 min → dégradé, absent → **down**.

**Heartbeat du worker sur l'événement `Looping`, pas `JobProcessed`** — choix justifié :
> *« `Looping` est émis à chaque tour de boucle, file vide comprise. Avec `JobProcessed`, un site à
> quelques jobs par jour aurait signalé une fausse panne dès que la file est calme. »*

Avec écriture limitée à **une fois par minute** (`touchThrottled`) pour ne pas marteler le cache.

---

## 8.8. Alertes

| Aspect | Implémentation |
|---|---|
| Déclenchement | `supervision:alert` toutes les 15 min |
| Condition | `! $snapshot->isHealthy()` |
| Anti-spam | **cooldown par motif** (60 min), clé `supervision:alert:{reason}` en cache |
| Destinataire | `SUPERVISION_ALERT_EMAIL` → repli `mail.admin_email` → repli `mail.from.address` ✅ |
| Contenu | `SupervisionAlertMail` + `Log::error` avec le détail des sondes |
| Rapport quotidien | `supervision:report` à 08:00, `SupervisionReportMail` |
| Catalogue | **14 motifs**, chacun avec un libellé **et une procédure de remédiation** |

Le cooldown **par motif** (et non global) est le bon grain : une base lente n'empêche pas d'être
alerté d'un worker mort.

Le `AlertCatalog` est remarquable : chaque motif porte les commandes `docker` exactes à lancer. Il
contient même une note de diagnostic sur le piège du mutex (§8.11).

> ⚠️ `SUPERVISION_ALERT_EMAIL=` est **vide** dans le template de production. Le repli fonctionne
> (`mail.admin_email` = l'adresse Gmail de la photographe), donc les alertes techniques arrivent
> **dans la boîte de la photographe**, mêlées aux demandes de réservation. Une adresse dédiée serait
> préférable.
>
> ⚠️ Les alertes passent par `Mail::to()->send()` (**synchrone**), pas `queue()` — choix correct :
> une alerte ne doit pas dépendre de la file, qui est précisément l'un des composants surveillés.

---

## 8.9. Le point aveugle structurel : « qui prévient de la mort du gardien ? »

Le document le formule exactement :

> *« Le système d'alerte interne (emails) est porté par le scheduler. **Si le scheduler meurt, il ne
> peut pas prévenir de sa propre mort.** C'est le classique problème de l'"homme mort". »*

Trois filets indépendants sont **spécifiés**, chacun hébergé hors du NAS :

| # | Filet | Détecte | État |
|---|---|---|---|
| 1 | **UptimeRobot** sur `/api/health` (mot-clé `"status":"ok"`, 5 min) | la sonde `scheduler` fait passer l'endpoint en 503 | 🔴 **non créé** |
| 2 | **healthchecks.io** (ping en fin de tâche) | **l'absence d'exécution** — le seul mécanisme qui le fait | 🔴 **non configuré** (URL vides) |
| 3 | **Rapport quotidien de 08:00** | s'il n'arrive plus, quelque chose ne tourne plus | ✅ actif |

Et symétriquement, ce que les sondes externes ne verraient pas : un job en échec, une file engorgée,
un worker figé — *« autant de pannes invisibles de l'extérieur, où l'API répond parfaitement »*.

**Partie complémentaire par les healthchecks Docker** — l'idée est excellente : le healthcheck de
`api-queue` et `api-scheduler` **est** la vérification du heartbeat applicatif
(`supervision:heartbeat:check`). Docker redémarre donc le conteneur si le heartbeat est périmé,
**avant** que l'alerte email ne parte. Double filet, sans code supplémentaire.

---

## 8.10. 🔴 Ce qui est documenté mais non activé

`docs/supervision.md` §11 est explicite :

> *« Cible : **la preprod**. Le code est en place et testé ; **rien de ce qui suit n'est fait par le
> déploiement**. Tant que ces points sont ouverts, le dispositif tourne en mode dégradé (alertes email
> fonctionnelles, mais aucune sonde externe et aucune remontée d'erreurs). »*

État en **production**, déduit de `deploy/.env.prod.example` :

| Élément | État | Conséquence |
|---|---|---|
| Alertes email internes | ✅ actives (repli destinataire) | fonctionne |
| Sentry API | ✅ `SENTRY_DSN=<SECRET>` | fonctionne |
| Sentry front | 🟡 variable Render, non versionnée (§4.7) | **non vérifiable** |
| `HEALTH_CHECK_TOKEN` | 🟡 `<SECRET>` dans le template | à confirmer en prod |
| **`HEALTHCHECKS_RGPD_URL`** | 🔴 **vide** | aucun ping → **une purge RGPD non exécutée est indétectable** |
| **`HEALTHCHECKS_RECONCILE_URL`** | 🔴 **vide** | aucun ping → **une réconciliation SumUp arrêtée est indétectable** |
| **UptimeRobot** | 🔴 non créé | la mort du NAS, du tunnel ou du scheduler n'alerte personne |
| **`SUPERVISION_STORAGE_WITNESS`** | 🔴 vide | la sonde stockage ne teste pas l'intégrité |
| `APP_VERSION` | 🟡 `2.3.0` alors que le front est en `6.1.0` (§4.6) | releases Sentry mal étiquetées |

> **Le paradoxe de cette section** : le projet a construit un dispositif de supervision soigné,
> documenté et testé — **et les trois filets censés couvrir son angle mort structurel ne sont pas
> branchés.** Le document le dit lui-même. Ce ne sont pas des développements : ce sont **quatre
> créations de compte gratuites et cinq lignes d'`.env`**.
>
> C'est, en rapport bénéfice/effort, l'action la plus rentable de tout l'audit après les correctifs
> de sécurité de la §6.15.

---

## 8.11. Deux incidents de production instructifs (20/08/2026)

Le document les relate avec leur diagnostic complet. Ils méritent d'être conservés dans le dossier de
refonte : ce sont des pièges génériques.

### Incident 1 — Le mutex `withoutOverlapping` coincé

**Symptôme** : alerte « scheduler silencieux » émise par un scheduler parfaitement vivant.

**Enchaînement** : le `down` d'un déploiement a tué un `supervision:heartbeat` en plein vol → le mutex
est resté posé → le heartbeat a disparu des exécutions pendant que `supervision:alert` continuait à
tourner.

**Deux pièges de diagnostic documentés** :

1. **L'expiration par défaut de `withoutOverlapping` est de 1440 minutes** (24 h de blocage). Les
   tâches de supervision fixent désormais une valeur explicite et courte (10, 30, 60).
2. **`Cache::has($event->mutexName())` ne détecte rien.** Dès que le store implémente `LockProvider`
   — ce que fait `FileStore` — le mutex passe par un verrou atomique et `FileStore::lock()` **préfixe
   la clé** en `file-store-lock:`. Le seul test valide est `$event->mutex->exists($event)`.

**Et le symptôme est silencieux** : `withoutOverlapping` s'implémente par un `skip()`, donc la tâche
est écartée **avant** exécution → `schedule:work` n'émet **aucune ligne**, ni succès, ni échec, ni
mention du skip. *« Le symptôme est une tâche qui n'existe plus dans les logs, pas une erreur. »*

**Prévention retenue** : `schedule:clear-cache` dans la commande du conteneur, avant `schedule:work`
— au démarrage, aucun `schedule:run` ne peut être légitimement en cours, le nettoyage est donc sans
risque. **C'est l'explication de la ligne que je relevais sans la comprendre en §4.7.**

### Incident 2 — La fausse alerte « base lente »

**Symptôme** : « la base répond en 1490 ms » alors qu'elle répondait en quelques millisecondes.
L'action recommandée par l'alerte envoyait chercher des requêtes lentes inexistantes.

**Cause** : la sonde mesurait l'ouverture de connexion **et** le `SELECT` ensemble. Comme
`PDO::ATTR_PERSISTENT = false` et que la commande tourne dans un process forké, la connexion est
toujours neuve : le DNS, le TLS et la poignée de main du pooler étaient facturés à la base.

**Correctif** : deux mesures distinctes, deux seuils, deux motifs d'alerte.

> Ces deux incidents partagent une leçon : **une alerte qui désigne la mauvaise cause est pire qu'une
> absence d'alerte**, parce qu'elle envoie chercher au mauvais endroit. C'est ce qui explique la
> présence d'un champ `action` dans `AlertCatalog` — et la qualité de sa rédaction.

---

## 8.12. Ce que le dispositif ne couvre pas

Le document énumère lui-même ses limites (§10) :

| Non couvert | Commentaire, croisé avec mes autres constats |
|---|---|
| **Saturation disque du NAS** | 🔴 **Aggravé** : le cache d'images peut écrire ~200 Mo par galerie servie en mode proxy (§5.11), les ZIP d'export vivent sur le disque `local`, et `storage/app/temp` contient déjà 146 Mo de vestiges (§5.9). C'est le risque non supervisé le plus concret |
| Dépassement de quota Supabase | visible seulement dans leur console |
| Expiration du certificat Cloudflare | géré par eux |
| Performance perçue côté client | aucun RUM |
| **Délivrabilité email et SMS** | §4.5 — aucun webhook Brevo, aucune trace de bounce |
| **Panne SumUp** | §4.11 — aucune sonde |
| **Erreurs front non exhaustives** | voir ci-dessous |

**Sur Sentry front** — limite bien analysée et assumée :
> *« Les domaines d'ingestion de Sentry (`*.ingest.sentry.io`) figurent dans les listes anti-traceurs
> courantes (EasyPrivacy, uBlock Origin, AdGuard, Ghostery, Brave Shields). Chez ces visiteurs,
> l'erreur est bien capturée par le SDK mais l'envoi est bloqué par le navigateur […]. Selon les
> publics, **20 à 40 % des visiteurs** sont concernés. »*
>
> Conséquence explicitée : *« le monitoring front voit une majorité des erreurs, pas leur totalité »*.
> La parade (option `tunnel` du SDK) est **volontairement non implémentée** — elle ajouterait une
> surface à sécuriser pour un gain partiel. Décision documentée et défendable ; à reconsidérer si une
> régression front passe inaperçue.

---

## 8.13. Modes de défaillance — synthèse

| Panne | Détectée par | Délai | Alerté ? |
|---|---|---|---|
| `api-queue` arrêté | heartbeat + healthcheck Docker | ≤ 10 min | ✅ (+ redémarrage auto) |
| Worker figé (process vivant, boucle bloquée) | heartbeat périmé | ≤ 10 min | ✅ |
| `api-scheduler` arrêté | heartbeat | ≤ 120 min | ✅ (+ redémarrage auto) |
| **Scheduler + NAS morts** | — | **jamais** | 🔴 (UptimeRobot non créé) |
| Job en échec définitif | `failed_jobs` sur 24 h | ≤ 15 min | ✅ |
| **Export RGPD / scolaire / session en échec** | **rien** (§8.4 n°2-3) | — | 🔴 statut en base seulement |
| File engorgée (> 100) | `QueueProbe` | ≤ 15 min | ✅ |
| Job bloqué en attente (> 15 min) | `QueueProbe` | ≤ 15 min | ✅ |
| **Tête de ligne (export bloquant les emails)** | `queue_stalled` **si** > 15 min | ≤ 15 min | 🟡 alerte tardive, cause non désignée |
| Base injoignable / lente | `DatabaseProbe` | ≤ 15 min | ✅ |
| MinIO injoignable | `StorageProbe` | ≤ 15 min | ✅ |
| **Purge RGPD non exécutée** | healthchecks.io | — | 🔴 **non configuré** |
| **Réconciliation SumUp arrêtée** | healthchecks.io | — | 🔴 **non configuré** |
| **`privacy:purge-expired` mensuel non exécuté** | rien (pas de ping prévu) | — | 🔴 |
| **Disque NAS plein** | rien | — | 🔴 |
| Email/SMS non délivré | rien | — | 🔴 |

---

## 8.14. Commandes et requêtes de diagnostic

### Commandes d'exploitation (issues de `docs/supervision.md` §8)

```bash
# État détaillé des sondes (depuis le NAS)
docker exec api-php php artisan tinker --execute="…"   # ou :
curl -s -H "X-Health-Token: $TOKEN" https://api.oceanetorresphotographie.fr/api/health/details | jq

# Heartbeats
docker exec api-php php artisan supervision:heartbeat:check queue
docker exec api-php php artisan supervision:heartbeat:check scheduler

# Rapport de santé à la demande
docker exec api-php php artisan supervision:report

# Alerte à la demande (utile pour tester le canal)
docker exec api-php php artisan supervision:alert

# Santé des conteneurs
docker ps --format 'table {{.Names}}\t{{.Status}}'

# File d'attente
docker exec api-php php artisan queue:failed
docker exec api-php php artisan queue:monitor database:default
```

### Requêtes à jouer sur une copie de la production

```sql
-- État de la file
SELECT COUNT(*) FILTER (WHERE reserved_at IS NULL)     AS en_attente,
       COUNT(*) FILTER (WHERE reserved_at IS NOT NULL) AS reserves,
       MIN(created_at) AS plus_ancien_ts
FROM jobs;

-- Composition de la file par type de job (payload JSON)
SELECT payload::json->>'displayName' AS job, COUNT(*)
FROM jobs GROUP BY 1 ORDER BY 2 DESC;

-- Échecs : quels jobs, quelles causes, sur quelle période ?
SELECT payload::json->>'displayName' AS job,
       COUNT(*) AS n, MIN(failed_at) AS premier, MAX(failed_at) AS dernier
FROM failed_jobs GROUP BY 1 ORDER BY 2 DESC;

-- Extrait des messages d'exception (les 20 plus récents)
SELECT failed_at, payload::json->>'displayName' AS job,
       LEFT(exception, 200) AS extrait
FROM failed_jobs ORDER BY failed_at DESC LIMIT 20;

-- 🔴 Les échecs INVISIBLES pour la supervision (§8.4 n°2-3)
SELECT 'school_sessions' t, status, COUNT(*), MAX(updated_at) FROM school_sessions
  WHERE status = 'failed' GROUP BY 1,2
UNION ALL SELECT 'school_session_exports', status, COUNT(*), MAX(updated_at)
  FROM school_session_exports WHERE status = 'failed' GROUP BY 1,2
UNION ALL SELECT 'privacy_exports', status, COUNT(*), MAX(updated_at)
  FROM privacy_exports WHERE status = 'failed' GROUP BY 1,2;

-- Sessions scolaires bloquées en cours de traitement (jamais terminées)
SELECT id, title, status, total_photos, processed_photos, updated_at
FROM school_sessions
WHERE status IN ('uploading','extracting','creating_galleries','processing_photos')
  AND updated_at < now() - interval '2 hours';

-- Uploads photo jamais aboutis
SELECT status, COUNT(*) FROM photo_uploads GROUP BY 1;
SELECT batch_id, COUNT(*) FILTER (WHERE status = 'failed') AS echecs, COUNT(*) AS total
FROM photo_uploads GROUP BY batch_id HAVING COUNT(*) FILTER (WHERE status = 'failed') > 0;

-- Efficacité de la réconciliation (preuve que le scheduler tourne)
SELECT COUNT(*) FROM orders
WHERE status = 'paid' AND paid_at - created_at > interval '15 minutes';

-- Preuve que la purge RGPD tourne : reste-t-il des données au-delà des rétentions ?
SELECT COUNT(*) FROM contact_messages WHERE created_at < now() - interval '12 months';
SELECT COUNT(*) FROM download_logs   WHERE downloaded_at < now() - interval '12 months';
SELECT COUNT(*) FROM carts WHERE status = 'expired' AND updated_at < now() - interval '30 days';
```

> La dernière série est un **test indirect mais décisif** : si ces compteurs sont non nuls, la purge
> RGPD hebdomadaire n'a pas tourné — ce que précisément aucun mécanisme ne signale aujourd'hui
> (§8.10).

---

## 8.15. Synthèse §8 — constats classés

| # | Constat | Gravité | Section |
|---|---|---|---|
| 1 | **Une seule file, un seul worker** : un export de 3600 s bloque tous les emails et SMS | 🔴 **Élevée** | §8.3 |
| 2 | **Les 3 jobs longs avalent leur exception** → échecs d'export RGPD/scolaire **invisibles** pour la supervision | 🔴 Élevée | §8.4 |
| 3 | **Les 3 filets externes ne sont pas branchés** (UptimeRobot, 2× healthchecks.io) alors qu'ils sont spécifiés | 🔴 Élevée | §8.10 |
| 4 | **Saturation disque du NAS non supervisée**, aggravée par le cache d'images (§5.11) | 🟠 Moyenne | §8.12 |
| 5 | **`privacy:purge-expired` mensuel sans ping** → silence indétectable | 🟠 Moyenne | §8.5 |
| 6 | `SendSchoolSessionSmsJob` retente sur des échecs permanents (numéro invalide) | 🟠 Moyenne | §8.4 |
| 7 | Jobs non idempotents sur MinIO → fichiers orphelins, rattrapés par une commande non planifiée | 🟠 Moyenne | §8.4 |
| 8 | `SUPERVISION_STORAGE_WITNESS` vide → intégrité du bucket non testée | 🟡 Faible | §8.10 |
| 9 | `--memory=2048` = `memory_limit` → le worker redémarre *après* l'OOM, pas avant | 🟡 Faible | §8.2 |
| 10 | Alertes techniques dans la boîte Gmail de la photographe | 🟡 Faible | §8.8 |
| 11 | 2 tâches planifiées sans `withoutOverlapping` | 🟡 Faible | §8.5 |
| 12 | `job_batches` créée et inutilisée | 🟡 Faible | §8.2 |
| 13 | `SendSchoolSessionSmsJob` mal nommé (utilisé hors scolaire) | 🟡 Faible | §8.4 |
| 14 | Sentry front aveugle sur 20-40 % des visiteurs | 🟢 assumé | §8.12 |

**Ce qui est exemplaire et doit être repris tel quel** : le heartbeat par `Schedule::command` (qui
teste le mécanisme dont dépendent les tâches critiques, pas seulement la boucle), le heartbeat worker
sur `Looping` plutôt que `JobProcessed`, le healthcheck Docker branché sur le heartbeat applicatif, le
cooldown d'alerte par motif, la séparation des mesures de latence base, l'absence de cache sur les
sondes, le catalogue de 14 motifs avec procédure de remédiation, et la discipline de reprise de
`ProcessPhotoJob`.

**Le contraste de cette section** : le dispositif de supervision est **la brique la plus mûre du
projet** sur le plan de la conception — et **la moins mise en service**. Trois créations de compte
gratuites et cinq lignes d'`.env` séparent un système bien conçu d'un système effectivement opérant.
Quant à la file d'attente, elle porte un défaut d'architecture (une seule voie pour du temps réel et
du batch) dont l'utilisateur final paie le prix sans que personne ne le voie.

---
# 9. Dette technique et risques

> **Méthode** : consolidation des constats des §2 à §8, complétée par des mesures nouvelles —
> volumétrie et qualité du front (26 629 lignes), cartographie de la couverture de tests
> (226 méthodes, 3 803 lignes), inventaire chiffré du code mort, recherche des marqueurs de dette et
> des duplications.
>
> **Cotation** : la gravité est évaluée selon **l'impact × la probabilité d'occurrence × le coût de
> correction après refonte**. Un défaut peu grave mais impossible à corriger plus tard pèse plus
> qu'un défaut visible mais trivial à réparer.

---

## 9.1. D'abord, ce qui va bien — et qu'il ne faut pas casser

Un audit qui ne liste que des problèmes donne une image fausse. Ce projet porte des choix
d'ingénierie que beaucoup de codebases comparables n'ont pas, et **la refonte doit les reprendre
explicitement** plutôt que les redécouvrir.

| Actif | Où | Pourquoi c'est précieux |
|---|---|---|
| **Optimisations mémoire du pipeline image** | §5.5 | 4 optimisations, facteur ~15 sur le pic mémoire, chacune documentée par le problème résolu |
| **Invariant de sécurité du paiement** | §4.4 | Le statut est **toujours** revérifié auprès de SumUp — impossible d'injecter un faux `PAID` |
| **Idempotence de `completeOrder`** | §7.5 | Verrou + drapeau `$justCompleted` + effets de bord hors transaction |
| **Triple filet de confirmation de paiement** | §7.1 | Webhook + retour navigateur + polling + réconciliation cron |
| **Module RGPD complet** | §2.12, §6.12 | Locator, export, effacement avec carte de traitement, journal d'audit |
| **Conception de la supervision** | §8.6-8.9 | Heartbeat testant le *mécanisme* de fork, healthchecks Docker branchés sur le heartbeat applicatif, cooldown par motif |
| **`BusinessException`** | §3.2.5 | Exception marqueur dont le message est sûr à afficher — patron simple et efficace |
| **Handler d'exceptions global** | §3.2.5 | Aucune fuite technique, `ModelNotFoundException` explicitement neutralisée |
| **Dénormalisation des `order_items`** | §3bis.7 | La facture reste lisible après suppression de la photo |
| **Montage « dual endpoint » MinIO** | §4.3 | Contourne le tunnel Cloudflare en boucle locale, 100-300 ms par PUT |
| **Optimisation GSM-7 des SMS** | §4.5 | 1 segment au lieu de 3 — économie directe |
| **Cumul cross-galerie des packs** | §7.2 | Logique métier fine, non reconstituable sans le code |
| **TypeScript strict côté front** | §9.8 | `strict: true`, aucun `any`, aucun `@ts-ignore` |
| **Commentaires « pourquoi », pas « quoi »** | partout | Voir §9.9 |

**Le trait le plus remarquable du codebase** : les commentaires expliquent des **décisions**, pas du
code. Une dizaine d'entre eux documentent un incident de production et sa cause. Exemples :

> *« sum('price') ignorait la quantité → sous-facturation SumUp. »*
> *« l'inverse force GD à allouer 3× le full-size et déclenche OOM sur les portraits. »*
> *« Sans discover: false, Laravel enregistre une seconde fois chaque listener → tous les mails
> partaient en double. »*
> *« Sans la clause where('status','pending'), on écraserait silencieusement un paiement valide. »*

C'est une forme de documentation rare et de grande valeur. **À préserver dans la refonte** : ces
commentaires sont la mémoire des pannes.

---

## 9.2. Les cinq dettes structurelles

Celles qui ne se corrigent pas par un correctif ponctuel, et qui doivent être arbitrées **avant** de
commencer la refonte.

### D1 — La couche booléenne PostgreSQL

**Chaîne** : pooler transaction Supabase (imposé par un incident `EMAXCONNSESSION`, §5.0.1) →
`EMULATE_PREPARES` + `STRINGIFY_FETCHES` → booléens cassés → trait `CastsBooleansForPostgres` sur
**12 modèles** → **20 `whereRaw`**.

**Pourquoi c'est structurel** : le trait surcharge `setAttribute()` pour écrire la **chaîne**
`'true'` dans `$attributes`, court-circuitant le pipeline de casts d'Eloquent. Tout code qui compare
strictement un booléen fraîchement assigné est faux. Et rien n'échoue bruyamment si on oublie le trait
sur un nouveau modèle — le filtre renvoie simplement des résultats faux.

**Décision à prendre** : tester si retirer `STRINGIFY_FETCHES` suffit (piste la moins coûteuse),
réduire le besoin de connexions, ou sortir du pooler. → §5.0.1.

### D2 — `galleries` : quatre concepts dans une table

Une seule table porte les galeries privées, publiques, événements et scolaires, discriminées par
`type` + colonnes optionnelles (`parent_id`, `school_session_id`, `event_category_id`, `class_name`,
`is_published`, `shipping_fee`…). C'est un *table-per-hierarchy* implicite.

**Coût observé** : `GalleryController` fait **505 lignes et 18 endpoints** pour trois audiences ;
chaque requête doit se souvenir du discriminant, d'où des `where('type', 'event')` dispersés et
5 scopes de filtrage.

**Décision** : conserver (avec un discriminant explicite et des scopes obligatoires) ou séparer en
plusieurs tables/agrégats. → §11.

### D3 — Une seule file d'attente pour du temps réel et du batch

Un export de 3600 s bloque les confirmations de commande et les SMS (§8.3). L'utilisateur final paie
le prix sans que personne ne le voie.

**Coût de correction maintenant** : un changement de commande + quelques `onQueue()`.
**Coût plus tard** : identique — mais chaque mois d'attente produit des clients qui paient sans
recevoir leur facture.

### D4 — Aucune convention de format de réponse API

97 `'success' => true`, 38 `'data' =>`, des clés ad hoc, des paginateurs bruts, **4 API Resources pour
177 endpoints** (§3.7). Conséquences mesurables côté front : deux couches d'accès parallèles,
**1 000 lignes de types** (dont 679 pour `types/admin.ts`), et 8 `fetch()` sauvages qui contournent la
couche services précisément parce que la forme de réponse ne rentrait dans aucun helper.

**C'est le chantier au meilleur rapport bénéfice/effort de la refonte** : il supprime mécaniquement
une grande partie du code front.

### D5 — Le contrôle d'accès n'a pas de couche

Quatre dispositifs qui se contredisent (§3.5, §6.5) : middleware `admin` (le seul systématique),
52 FormRequests dont `authorize()` renvoie toujours `true`, 3 Policies traitées de 3 façons
différentes (dont une qui plante à l'exécution), et des vérifications inline dupliquées — la règle
des « 30 minutes » écrite **4 fois**.

**Pourquoi c'est structurel** : il n'existe aucun endroit où lire « qui a le droit de quoi ». Chaque
nouvel endpoint réinvente sa règle.

---

## 9.3. Registre consolidé de la dette

Cotation : 🔴 critique · 🟠 élevée · 🟡 moyenne · 🟢 faible.
« Corriger avant ? » = doit être traité **sur la production actuelle**, sans attendre la refonte.

| # | Dette | Cot. | Avant ? | § |
|---|---|---|---|---|
| 1 | `downloadFile()` — lecture + suppression de fichier arbitraires, route publique | 🔴 | **Oui** | §5.9, §6.10.1 |
| 2 | Compte admin du seeder (`admin123`) possiblement en production | 🔴 | **Oui** | §6.14 |
| 3 | La déconnexion ne révoque ni session ni token | 🔴 | **Oui** | §6.2.2 |
| 4 | `$request->ip()` constant → rate limiters inopérants + preuves RGPD sans valeur | 🔴 | **Oui** | §4.7, §6.7 |
| 5 | Aucun rate limit sur `login`/`register`/`forgot-password`/`orders-by-email`/`galleries/code` | 🔴 | **Oui** | §6.7 |
| 6 | Quantité absente de la facture et des 3 emails → mauvais nombre de tirages imprimés | 🔴 | **Oui** | §7.6 |
| 7 | Aucune sauvegarde (base ni MinIO) — et la migration exige un dump | 🔴 | **Oui** | §4.2 |
| 8 | `migrate --force` au démarrage de conteneur, sans filet | 🔴 | **Oui** | §4.7 |
| 9 | `DELETE /admin/orders` détruit la facture par CASCADE | 🔴 | Oui | §7.7, §3bis.8 |
| 10 | Une seule file : les exports bloquent le transactionnel | 🔴 | Oui | §8.3 (D3) |
| 11 | Les 3 jobs longs avalent leur exception → échecs d'export invisibles | 🔴 | Oui | §8.4 |
| 12 | Les 3 filets de supervision externes non branchés | 🔴 | Oui | §8.10 |
| 13 | Token Bearer 30 j en `localStorage` + aucune CSP | 🟠 | Oui | §6.8 |
| 14 | Couche booléenne PostgreSQL (12 modèles, 20 `whereRaw`) | 🟠 | Non | §3.9 (D1) |
| 15 | Aucun `SoftDeletes` + 18 FK en CASCADE, dont 2 chaînes destructrices | 🟠 | Non | §3bis.13 |
| 16 | Aucun mécanisme de remboursement / avoir | 🟠 | Non | §7.9 |
| 17 | Binaires d'images en cache applicatif (disque NAS / table `cache`) | 🟠 | Oui | §5.11 |
| 18 | Vignettes sans filigrane survivant à la suppression | 🟠 | Oui | §5.6 |
| 19 | Driver d'image non déterministe (`pecl imagick` non épinglé) | 🟠 | Oui | §5.0.2 |
| 20 | Aucune convention de format de réponse (4 Resources / 177 endpoints) | 🟠 | Non | §3.7 (D4) |
| 21 | Contrôle d'accès sans couche ; règle « 30 min » ×4 | 🟠 | Non | §3.5 (D5) |
| 22 | Prod buildée sur la cible Docker `development`, aucune image versionnée | 🟠 | Non | §4.7 |
| 23 | Photos et factures dans le même bucket MinIO | 🟠 | Non | §4.3 |
| 24 | Cache fichier partagé par bind mount → supervision couplée à la topologie | 🟠 | Non | §4.7 |
| 25 | Zéro suivi de délivrabilité email et SMS | 🟠 | Non | §4.5 |
| 26 | Aucune analyse de dépendances (CI ni Dependabot) | 🟠 | Oui | §6.13 |
| 27 | Politique de mot de passe = 8 caractères, rien d'autre | 🟠 | **Oui** | §6.2.3 |
| 28 | Aucun 2FA sur l'unique compte admin | 🟠 | Non | §6.2.5 |
| 29 | Contacts scolaires non persistés → aucun historique de diffusion | 🟠 | Non | §2.10 |
| 30 | Traitement photo en ligne → workers PHP-FPM bloqués | 🟠 | Non | §5.2.1 |
| 31 | `clients.source` non fiable (3 écritures forcent `reservation`) | 🟡 | Non | §3.8.6 |
| 32 | `cgv_version` en dur → traçabilité des CGV inopérante | 🟡 | Oui | §7.3 |
| 33 | Adresse client absente de la facture (mention obligatoire) | 🟡 | Oui | §7.8 |
| 34 | Régénérer le code de partage ne révoque pas l'accès aux previews | 🟡 | Non | §5.10, §6.6 |
| 35 | Commandes mixtes scolaire/non-scolaire invisibles en admin | 🟡 | Oui | §7.12 |
| 36 | `EventCategoryController::reorder` cassé (`DB::raw` + bindings) | 🟡 | Oui | §6.9 |
| 37 | `$this->authorize()` sans `AuthorizesRequests` → 500 sur 3 routes | 🟡 | Oui | §3.5.4 |
| 38 | 8 colonnes JSON sans schéma, dont 2 critiques | 🟡 | Non | §3bis.13 |
| 39 | Contrôleurs mixtes public/admin (7 fichiers, convention `adminXxx()`) | 🟡 | Non | §3.4.2 |
| 40 | Portfolio non administrable (commit + build pour ajouter une photo) | 🟡 | Non | §2.2 |
| 41 | Aucune pagination des photos de galerie | 🟡 | Non | §5.13 |
| 42 | Plafond ZIP de 500 photos silencieux | 🟡 | Oui | §5.8 |
| 43 | `storage:clean-orphans` non planifiée | 🟡 | Oui | §5.14 |
| 44 | Saturation disque du NAS non supervisée | 🟡 | Oui | §8.12 |
| 45 | Logique des frais de port dupliquée ×3 | 🟡 | Non | §9.5 |
| 46 | `SESSION_DOMAIN` sur tous les sous-domaines (dont `s3.`) | 🟡 | Oui | §6.3 |
| 47 | Extraction ZIP non durcie (bombe, entrées non validées) | 🟡 | Non | §6.10.2 |
| 48 | Aucun export comptable (CSV/FEC) | 🟡 | Non | §7.9 |
| 49 | Précisions décimales incohérentes (`10,2` vs `8,2`) | 🟢 | Non | §3bis.13 |
| 50 | Advisory locks numérotés en dur (42, 43) sans registre | 🟢 | Non | §3.8.5 |
| 51 | `ClearsEventGalleriesCache` borné aux pages 1-10 | 🟢 | Non | §3.10 |
| 52 | Trois sources de vérité pour l'adresse de contact / le SIRET | 🟢 | Non | §9.5 |
| 53 | `APP_VERSION=2.3.0` vs front `6.1.0` → releases Sentry fausses | 🟢 | Oui | §4.6 |
| 54 | En-têtes de sécurité en double et contradictoires | 🟢 | Oui | §4.7 |
| 55 | Directives `[Session]` inertes dans `php.ini` | 🟢 | Non | §6.3 |
| 56 | Render non versionné (aucun `render.yaml`) | 🟡 | Oui | §4.7 |

**56 items** : 12 critiques, 18 élevés, 21 moyens, 5 faibles. **22 sont marqués « à corriger avant »**
— dont 7 tiennent en moins de 3 heures cumulées (§6.15, priorité 1).

---

## 9.4. Inventaire du code mort, chiffré

### Backend — ~930 lignes supprimables sans aucun risque

| Élément | LOC | Justification |
|---|---|---|
| `AvailabilityController` | 199 | 12 endpoints, zéro appel front |
| `AvailabilitySlot` + `AvailabilityPattern` | 217 | idem |
| 5 FormRequests `*Availability*` | 127 | idem |
| 6 migrations Cashier/Stripe | 200 | tables vides **et cassées** (`user_id` bigint vs uuid) |
| `OrderPolicy` | 65 | jamais appelée, logique recopiée ailleurs |
| `ReservationPolicy` | 37 | appelée par 3 routes mortes qui plantent |
| Edge Function Supabase | 82 | orpheline, zéro référence |
| Cache de filigrane désactivé | ~45 | `USE_WATERMARK_LAYER_CACHE = false` |
| `GalleryController::downloadFile` | 13 | 🔴 **et vulnérable** |
| `SumUpService` : `processCheckout`, `completeCheckout`, `getPaymentMethods` | ~60 | dont une méthode manipulant des données de carte |
| **Total** | **≈ 1 045** | |

Plus **33 routes sans consommateur** (§2.16) et les colonnes mortes : `users.stripe_id`/`pm_type`/
`pm_last_four`/`trial_ends_at`, `photos.file_path_web`/`file_path_watermark`, `gift_cards.pdf_path`,
et 5 valeurs d'enum jamais écrites.

### Front — ~10 lignes, mais cassées

`adminApi.uploadPhotos` / `uploadEventPhotos` (§2.11) pointent vers des routes **inexistantes**
(`/photos` au lieu de `/photos/async`) et n'ont aucun appelant. `api.getGalleryItems()` et
`api.getGiftCards()` sont morts, ce dernier appelant une route qui n'existe pas publiquement.

### Fichiers et dossiers

| Élément | Taille | Nature |
|---|---|---|
| `api/storage/app/temp/*.zip` | **140 Mo** | 2 vestiges de février, plus aucun code n'écrit là |
| `bootstrap/`, `database/`, `storage/` à la racine | ~1 Mo | squelette Laravel résiduel, **suivi par Git** |
| `resources/` à la racine | 0 o | dossier vide versionné |

> **Le nettoyage complet représente ~1 050 lignes de PHP, 33 routes, 2 tables, ~15 colonnes et
> 141 Mo de fichiers.** Aucune de ces suppressions ne présente de risque fonctionnel — c'est le
> premier lot de travail de la refonte, et il réduit d'emblée la surface à réécrire.

---

## 9.5. Duplications

| Duplication | Occurrences | Emplacements |
|---|---|---|
| **Règle d'accès « 30 minutes »** | **4** | `OrderPolicy:32`, `OrderPolicy:59`, `OrderController:201`, `OrderService:642` |
| **Bloc de calcul des frais de port** (filtre + `max()`) | **3** | `CreateCheckoutRequest:91`, `OrderService:75`, `CartService:342` |
| Repli `config('app.frontend_url', 'https://oceanetorresphotographie.fr')` | 5 | 5 contrôleurs — le domaine de production est en dur comme valeur par défaut |
| Adresse email de contact | 4 | `config/mail.php`, `config/invoice.php`, `constants.ts`, 2 vues légales |
| SIRET | 3 | `config/invoice.php`, `constants.ts`, `PolitiqueConfidentialite.vue` |
| Domaine de production | 9 | back **et** front, en dur |
| Boilerplate CRUD admin | 13 vues | voir ci-dessous |

**Sur les frais de port** : la règle *par type de produit* est bien factorisée
(`Gallery::getRequiresShippingForProductType`), mais le bloc qui **filtre les articles et prend le
maximum** est recopié trois fois. Les trois copies sont aujourd'hui identiques — donc le panier
affiché correspond au montant facturé. Une divergence entre deux d'entre elles produirait exactement
le bug le plus difficile à diagnostiquer : un écart entre le prix affiché et le prix débité.

**Boilerplate admin** : les 13 vues d'administration totalisent **7 072 lignes** et répètent le même
schéma (état de chargement, modale de formulaire, modale de confirmation, toast, appel API,
rechargement). `EventGalleries.vue` fait **1 029 lignes** et `SchoolSessionDetail.vue` **1 185**.
Aucune abstraction de type « ressource administrable » n'existe — chaque écran est écrit à la main.

---

## 9.6. Couverture de tests : une carte très parlante

**226 méthodes de test, 3 803 lignes, 34 fichiers.** C'est une vraie suite — pas un alibi. Mais sa
répartition raconte l'histoire du projet.

| Domaine testé | Fichiers | Lignes |
|---|---|---|
| Paiement | 6 | 596 |
| Téléchargement | 5 | 514 |
| Upload | 4 | 407 |
| RGPD + logs (Admin) | 4 | 629 |
| Supervision | 3 | 591 |
| Unitaires (services, jobs, modèles) | 8 | 785 |
| Contact + smoke | 2 | ~280 |

### Ce qui n'a AUCUN test

| Domaine | Volume non couvert |
|---|---|
| **Sessions scolaires** | le module le plus complexe : 18 endpoints, contrôleur de 507 l., service de 420 l., 3 jobs |
| **Panier** | `CartController` (368 l.) + `CartService` (**469 l.**), 9 endpoints — dont le cumul cross-galerie des packs et l'invariant « un panier actif par user » |
| **Authentification** | login, register, logout, mot de passe oublié — **y compris les 3 bugs trouvés en §6** |
| **Réservations** | 11 endpoints — **y compris les 3 routes qui lèvent une erreur 500** |
| **Disponibilités** | 12 endpoints (module mort) |
| **Galeries (CRUD admin)** | création, mise à jour, envoi email/SMS, régénération de code |
| **Galeries événements** | 9 endpoints, hiérarchie parent/enfant |
| **Facturation** | `InvoiceService` — **y compris le bug de quantité de la §7.6** |
| **Tarification** | `PricingService` — la cascade à 5 niveaux |
| **Clients, prestations, notifications, dashboard** | ~20 endpoints |
| **Front** | **zéro test** (pas de Vitest, malgré ce qu'annonce le README) |

> **La corrélation est frappante et instructive** : la surface testée est **exactement** celle qui a
> cassé en production (paiement, téléchargement, upload) plus le module RGPD (écrit avec un cahier des
> charges). La surface non testée est tout le reste — et **c'est là que se trouvent la quasi-totalité
> des bugs que cet audit a découverts** :
>
> | Bug trouvé | Domaine | Testé ? |
> |---|---|---|
> | Quantité absente de la facture (§7.6) | facturation | ❌ |
> | `logout()` ne révoque rien (§6.2.2) | authentification | ❌ |
> | `$this->authorize()` → 500 (§3.5.4) | réservations | ❌ |
> | `reorder` cassé (§6.9) | catégories d'événements | ❌ |
> | `downloadFile()` vulnérable (§5.9) | galeries | ❌ |
> | Commandes mixtes invisibles (§7.12) | commandes admin | ❌ (le domaine paiement l'est, pas la vue admin) |
>
> **Aucun de ces six bugs n'aurait survécu à un test.** C'est l'argument le plus concret en faveur
> d'une couverture de tests dans la refonte — et il indique aussi **par où commencer** : panier,
> facturation, authentification, sessions scolaires.

**Points positifs de la suite existante** : elle tourne sur **Postgres 16** en CI (SQLite exclu à
raison — le code utilise `pg_advisory_xact_lock`, `FILTER (WHERE)` et les scopes `whereRaw`), le
`phpunit.xml` est soigneusement commenté (il documente notamment l'auto-complétion des commandes en
sandbox et le fait que le webhook exige un `Http::fake()`), et un trait `CreatesShopData` factorise la
préparation des données de boutique.

---

## 9.7. Indicateurs de qualité

### Volumétrie

| | Backend | Frontend |
|---|---|---|
| Fichiers | 178 PHP | 84 `.vue` + 45 `.ts` |
| Lignes | ~14 000 (app) + 3 803 (tests) | 20 945 (`.vue`) + 5 684 (`.ts`) |
| Contrôleurs / vues | 5 571 l. | 13 vues admin = 7 072 l. |
| Services | 4 941 l. | 1 000 l. de types |
| Plus gros fichier | `Admin/SchoolSessionController` 507 l. | `SchoolSessionDetail.vue` **1 185 l.** |

**Total ≈ 45 000 lignes** hors dépendances, migrations et configuration.

### Signaux positifs

| Indicateur | Valeur |
|---|---|
| Marqueurs de dette (`TODO`/`FIXME`/`HACK`) | **1 TODO, 0 FIXME, 0 HACK** |
| `@deprecated` | 1 (`Photo::display_url`) |
| TypeScript | `strict: true`, `noUnusedLocals`, `noUnusedParameters`, `noFallthroughCasesInSwitch` |
| `any` / `@ts-ignore` dans le front | **0** |
| `eslint-disable` | 4, tous **justifiés par un commentaire** (dont 3 pour `prefer-rest-params`, requis par `gtag.js`) |
| `v-html` | **1** — sink XSS, cf. §13.1 |
| Injection SQL | **aucune** (§6.9) |
| Secrets versionnés | **aucun** |
| Style backend | Laravel Pint, vérifié en CI |
| Validation | 52 FormRequests dédiés, **aucune validation inline** |

> **L'absence quasi totale de marqueurs de dette est ambiguë.** Elle peut signifier « peu de dette
> connue » ou « dette non annotée ». Au vu de l'audit, c'est la seconde : la dette existe mais n'est
> pas signalée dans le code — elle est soit documentée ailleurs (`docs/`), soit inconnue. Un `TODO`
> aurait dû exister sur `EventCategoryController::reorder`, sur les gabarits de facture, sur
> `downloadFile`.

### Signaux négatifs

| Indicateur | Valeur |
|---|---|
| `declare(strict_types=1)` | **absent** de tout le backend |
| Analyse statique PHP (PHPStan / Larastan) | **absente** |
| Couverture de tests mesurée | **jamais calculée en CI** (`coverage: none`) — mais la cible `make test-coverage` existe (§12.8) |
| Tests front | **0** |
| Analyse de sécurité en CI | **aucune** (§6.13) |
| Fichiers > 500 lignes | 9 backend, 11 front |

---

## 9.8. La dérive documentaire

Un thème transverse mérite d'être isolé, parce qu'il a un coût direct sur la refonte : **une partie
notable de la documentation et des commentaires décrit un état antérieur du code.**

| Source | Affirmation | Réalité | § |
|---|---|---|---|
| `README.md` | Stripe & PayPal, Supabase Storage, shadcn-vue, Vitest, Mailgun, PHP 8.3 | SumUp, MinIO, composants maison, aucun test front, Brevo, PHP 8.4 | §1.8 |
| `supabase/README.md` | buckets `public-portfolio`, `private-galleries`, `SUPABASE_KEY` | tout est remplacé par MinIO ; les variables ne sont lues nulle part | §4.2 |
| `Dockerfile` | *« Runtime driver […] default: GD »* | `USE_IMAGICK_DRIVER = true` | §5.0.2 |
| Modèle `Photo` | *« preview 1200px »*, *« thumbnail 400px »* | 2560 px et 600 px | §5.0.3 |
| Migration `gallery_product_types` | *« digital, print_10x15, print_15x20 »* | `print_scolaire` manque | §3bis.6 |
| Migration `payments` | *« stripe, paypal »*, *« deposit, full, gift_card »* | `sumup`, `photo_purchase` | §7.4 |
| `.env.prod.example` | `APP_VERSION=2.3.0` | front en `6.1.0` | §4.6 |
| §1 de cet audit | `SESSION_DRIVER=database`, `cache=database`, 36 tables, CI avec build Docker | `file`, `file`, 38 tables, pas de build Docker | §4.0, §3bis.0, §6.0 |

**Contre-exemple notable** : `docs/supervision.md`, `docs/audit-supervision.md` et
`docs/analyse-paiement-sumup.md` sont **à jour, précis et honnêtes** — ils listent même ce qui n'est
pas fait. La dérive touche les documents « d'accueil » (README) et les commentaires anciens, pas les
documents de conception récents.

> **Conséquence pour la refonte** : **ne jamais se fier à un commentaire ou à un README sans le
> vérifier dans le code.** Cet audit a dû corriger ses propres sections quatre fois pour cette raison.
> Et à l'inverse : les trois documents de `docs/` cités sont des sources fiables, à reprendre.

---

## 9.9. Risques humains et opérationnels

| # | Risque | Évaluation |
|---|---|---|
| R1 | **Facteur bus = 1.** Un seul développeur a écrit les 45 000 lignes. Toute la connaissance des incidents, des arbitrages et des pièges d'exploitation est dans sa tête et dans les commentaires | 🔴 Le projet est **en production réelle** pour une cliente qui en dépend commercialement |
| R2 | **`api/.env` local pointe la base et le mailer de PRODUCTION.** Toute commande artisan, tout tinker, tout test mal configuré en local touche les données réelles — et peut envoyer de vrais emails à de vraies clientes | 🔴 C'est la raison pour laquelle **tout cet audit a été fait en lecture statique** |
| R3 | **Aucune sauvegarde vérifiée** (§4.2). Pas de `pg_dump`, pas de copie MinIO, aucune restauration testée | 🔴 |
| R4 | **Déploiement non reproductible** : code monté en volume, image non versionnée, `migrate --force` au démarrage, config Render hors dépôt. Un rollback est un `git checkout` sur le NAS | 🟠 |
| R5 | **Infrastructure auto-hébergée sur un NAS domestique** : MinIO (toutes les photos et factures), les 4 conteneurs, les logs, les exports. Panne matérielle = perte de données si R3 n'est pas résolu | 🟠 |
| R6 | **Alertes techniques dans la boîte Gmail personnelle** de la photographe, mêlées aux demandes clients | 🟡 |
| R7 | **Aucune procédure documentée** : pas de runbook d'incident, pas de plan de reprise, pas de registre des traitements RGPD, pas de procédure de notification de violation | 🟡 Partiellement compensé par `AlertCatalog` (14 procédures de remédiation) et `PREPROD.md` |
| R8 | **Dépendances sans veille** : ~90 paquets Composer, aucune alerte de vulnérabilité | 🟠 |
| R9 | **Un seul compte admin**, sans 2FA, sans journal de connexion | 🟠 |

> **R1 + R2 + R3 forment le risque composé le plus sérieux du projet** : une seule personne, un
> environnement de développement branché sur la production, et aucune sauvegarde. Une fausse manœuvre
> en local — un `migrate:fresh`, un seeder, un `DELETE` mal ciblé — est irrécupérable.
>
> **Action prioritaire absolue, avant même les correctifs de sécurité** : mettre en place un
> `pg_dump` + une copie MinIO, tester la restauration, **puis** repointer `api/.env` local sur une
> base locale.

---

## 9.10. Risques spécifiques à la refonte

Ceux que la §10 devra traiter en détail, listés ici pour mémoire.

| # | Risque | Origine |
|---|---|---|
| M1 | **Rejouer les migrations ne reproduit pas le schéma de production** (garde `hasTable`, migrations de données, SQL brut) | §3bis.14 |
| M2 | **`photos.metadata->supabase_path`** dans la chaîne de résolution des fichiers → risque de casse silencieuse à la migration des objets | §3bis.5 |
| M3 | **4 commandes de backfill** → des lignes peuvent être restées dans un état intermédiaire | §3.11.3 |
| M4 | **`clients.source` non fiable** ; **`guest_name` scindé par `explode()`** (information perdue) | §3.8.6, §3bis.7 |
| M5 | **Booléens possiblement stockés en texte** par la rustine | §3.9 |
| M6 | **8 colonnes JSON** sans schéma, dont 2 portent de la logique critique | §3bis.13 |
| M7 | **Quotas de codes promo couplés à `orders`** : filtrer ou anonymiser des commandes change les quotas | §7.11 |
| M8 | **Rétention légale** : les factures et paiements doivent survivre à la migration (~10 ans) | §7.7 |
| M9 | **Journal d'audit RGPD** : preuve de conformité, à migrer intégralement | §3bis.10 |
| M10 | **Le portfolio n'est pas en base** : assets, contenu éditorial et IDs YouTube à récupérer séparément | §2.2 |
| M11 | **Aucun historique Git dans cette copie** (1 commit squashé) : pas d'archéologie possible depuis ce répertoire | §1 (note initiale) |
| M12 | **Décisions produit en attente** : disponibilités, bons cadeaux, portfolio administrable, remboursement | §2.16 |

---

## 9.11. Les 15 actions les plus rentables

Classées par **rapport bénéfice / effort**, tous domaines confondus.

| # | Action | Effort | Bénéfice |
|---|---|---|---|
| 1 | **`pg_dump` + copie MinIO + restauration testée** | 1 j | Supprime R3 ; **prérequis de toute la migration** |
| 2 | **Repointer `api/.env` local sur une base locale** | 2 h | Supprime R2 |
| 3 | **Vérifier/supprimer le compte `admin123`** | 5 min | Supprime une prise de contrôle triviale |
| 4 | **Supprimer `downloadFile()`** (13 lignes) | 15 min | Supprime la vulnérabilité la plus grave |
| 5 | **Propager `CF-Connecting-IP` + restreindre `trustProxies`** | 1 h | Restaure **5 rate limiters + les preuves RGPD** |
| 6 | **Ajouter les rate limits manquants** (7 routes) | 1 h | Ferme bruteforce et énumération |
| 7 | **Corriger `logout()`** + retirer le `catch {}` front | 30 min | La déconnexion révoque enfin |
| 8 | **Ajouter la quantité aux 4 gabarits** | 1 h | Corrige factures **et erreurs d'impression** |
| 9 | **Brancher UptimeRobot + 2 checks healthchecks.io** | 2 h | Ferme l'angle mort structurel de la supervision |
| 10 | **Séparer les files par priorité** (`--queue=realtime,default,heavy`) | 2 h | Les clients reçoivent leur facture pendant un export |
| 11 | **`composer audit` + `npm audit` + Dependabot** | 1 h | Veille de vulnérabilités |
| 12 | **`Password::min(12)->uncompromised()`** | 15 min | Ferme la chaîne bruteforce |
| 13 | **`invoices.order_id` en RESTRICT** + confirmation UI | 2 h | Une facture ne peut plus être détruite d'un clic |
| 14 | **Supprimer les ~1 050 lignes de code mort + 140 Mo** | 1 j | Réduit la surface à réécrire d'emblée |
| 15 | **Corriger** `storage:clean-orphans` (liste de références incomplète, §12.2) + test, **puis** le planifier en dry-run | 1 h 30 | Visibilité sur les fuites — ⚠️ destructeur en l'état |

**Cumul des actions 3 à 8, 12 et 15 : environ 5 heures**, et elles couvrent 8 des 12 constats
critiques. Les actions 1 et 2 sont des prérequis d'un jour et demi qui débloquent tout le reste,
y compris la possibilité de jouer les requêtes de diagnostic des §3bis, §5, §7 et §8.

---

## 9.12. Synthèse

**Ce projet n'est pas un projet mal fait.** C'est un projet **inégalement fini**, écrit par une
personne seule sur neuf mois, où la qualité suit une règle simple et lisible : **ce qui a cassé en
production est solide et documenté ; ce qui n'a jamais cassé porte les défauts.**

Les zones éprouvées — paiement SumUp, pipeline photo, RGPD, conception de la supervision — atteignent
un niveau que l'on ne trouve pas souvent à cette échelle : invariants explicites, garde-fous
anti-race, optimisations mesurées, commentaires qui expliquent les incidents. Les zones non éprouvées
— facturation, authentification, panier, sessions scolaires, file d'attente — concentrent la totalité
des bugs découverts par cet audit, et **aucun d'eux n'aurait survécu à un test**.

Les trois décisions à prendre avant d'écrire la première ligne de la refonte :

1. **Le pooler PostgreSQL** (D1) — il détermine si la nouvelle base est propre ou reprend la couche booléenne.
2. **La modélisation de `galleries`** (D2) — quatre concepts, une ou plusieurs tables.
3. **Le périmètre fonctionnel** : disponibilités, bons cadeaux, remboursement, portfolio administrable
   — quatre décisions produit qui changent l'ampleur du chantier (§2.16).

Et une action à mener **avant tout le reste, indépendamment de la refonte** : une sauvegarde
vérifiée. Aujourd'hui, une seule fausse manœuvre en local suffirait à détruire des données que rien
ne permettrait de restaurer.

---
# 10. Stratégie de récupération des données

> **Objet** : produire le plan opérationnel qui permettra d'écrire le script de migration de la base
> et des fichiers actuels vers la nouvelle structure. Cette section est un **mode opératoire**, pas une
> analyse : elle suppose acquis les constats des §3bis, §5, §7 et §8.
>
> **Rappel de la contrainte de méthode** : aucune donnée réelle n'a été consultée pendant tout cet
> audit. **Toutes les décisions ci-dessous doivent être confirmées par le relevé de la phase 1**
> (§10.3) avant d'écrire une ligne de script.

## 10.0. Corrections aux sections précédentes

| Affirmation antérieure | Réalité |
|---|---|
| §3bis.9 : « les ZIP d'export vivent sur le disque `local` — donc **perdus au redéploiement de conteneur** » | **Faux.** Les exports vont dans `storage/app/private/exports/...`, à l'intérieur du bind mount `./api:/var/www`. Ils **persistent sur le disque du NAS**. Ils s'accumulent d'ailleurs sans purge → contribuent au risque de saturation (§8.12) |
| §3bis.7, relation `Cart` ↔ `Order` | 🐛 **Relation cassée** : `Cart::order()` déclare un `belongsTo(Order::class)` alors que la table `carts` **n'a pas de colonne `order_id`**. L'accès renvoie silencieusement `null`. La relation utile existe dans l'autre sens (`orders.cart_id`). À supprimer — ajouter au code mort de la §9.4 |

---

## 10.1. Cinq principes directeurs

| # | Principe | Pourquoi |
|---|---|---|
| **P1** | **La source de vérité est un dump de production, jamais le dossier `migrations/`** | Rejouer les 76 migrations ne reproduit pas le schéma réel (§3bis.14) |
| **P2** | **Migrer les données, pas le schéma** | Le schéma actuel porte 5 dettes structurelles (§9.2). Un `pg_dump && pg_restore` reconduirait la couche booléenne, les CASCADE destructrices et les 4 concepts dans `galleries` |
| **P3** | **Rien n'est supprimé de l'ancienne base** | L'ancienne base reste en lecture seule et intacte, indéfiniment. C'est le seul vrai filet |
| **P4** | **Chaque ligne migrée conserve son identifiant d'origine** | Les UUID sont préservés tels quels. Cela rend la réconciliation triviale et la reprise idempotente |
| **P5** | **Le script est rejouable et vérifiable** | Idempotent (`ON CONFLICT DO NOTHING` ou upsert sur l'UUID), avec un rapport de comptage à chaque étape |

**Sur P4** — c'est la décision technique la plus importante de cette section. Conserver les UUID
d'origine apporte quatre bénéfices immédiats :

1. **les chemins MinIO restent valides** (`{gallery_id}/preview/{uuid}.jpg`) → **aucun fichier à
   déplacer ni à renommer** ;
2. les `share_code` et `access_token` déjà communiqués aux clientes continuent de fonctionner ;
3. les liens de facture, les tokens de téléchargement en circulation et les URL de galerie envoyées
   par email ou SMS survivent à la bascule ;
4. la réconciliation se réduit à des `COUNT` et des anti-jointures par UUID.

Renoncer à P4 imposerait de réécrire **toute l'arborescence MinIO** (plusieurs dizaines de Go) et de
réémettre tous les liens d'accès. **À ne faire sous aucun prétexte.**

---

## 10.2. Phase 0 — Prérequis absolus

**Rien ne commence avant que ces quatre points soient faits et vérifiés.**

| # | Action | Vérification d'acceptation |
|---|---|---|
| 0.1 | **`pg_dump` complet de la production** (format `custom`, avec `--no-owner --no-privileges`) | `pg_restore --list` liste les 38 tables ; taille cohérente |
| 0.2 | **Restauration testée** sur une base séparée (poste de dev ou conteneur local) | `SELECT count(*)` sur 5 tables témoins = valeurs du dump |
| 0.3 | **Copie complète du bucket MinIO** (`mc mirror` ou `rclone sync`) vers un stockage distinct | nombre d'objets et volume total identiques à la source |
| 0.4 | **Repointer `api/.env` local** sur la base restaurée, plus jamais sur la production | `config('database.connections.pgsql.host')` ≠ hôte de prod ; `MAIL_MAILER=log` ou Mailpit |

> ⚠️ **0.4 est un prérequis de sécurité, pas de confort.** Tant que `api/.env` local pointe la
> production (§9.9-R2), un `php artisan migrate:fresh` ou un seeder lancé par réflexe détruit les
> données réelles. C'est aussi ce qui a interdit à cet audit d'exécuter la moindre requête.

**Commandes de référence** (à adapter aux identifiants réels, jamais commitées) :

```bash
# 0.1 — dump
pg_dump --format=custom --no-owner --no-privileges \
        --file=oceane-prod-$(date +%F).dump "$PROD_DSN"

# 0.2 — restauration de contrôle
createdb oceane_audit
pg_restore --dbname=oceane_audit --no-owner --no-privileges oceane-prod-*.dump
psql oceane_audit -c "SELECT count(*) FROM orders;"

# 0.3 — copie objet
mc alias set src https://s3.oceanetorresphotographie.fr "$KEY" "$SECRET"
mc mirror --preserve src/galleries ./minio-backup/galleries
mc ls --recursive src/galleries | wc -l    # à comparer avec la copie
```

**Cadence recommandée** : ces sauvegardes doivent devenir **récurrentes** (§9.11 action n°1), pas
ponctuelles. Un `pg_dump` quotidien avec rétention 30 jours et un `mc mirror` hebdomadaire suffisent à
cette échelle.

---

## 10.3. Phase 1 — Relevé de l'existant

À jouer **sur la copie restaurée** (0.2), jamais sur la production. Les requêtes sont déjà écrites
dans les sections précédentes ; ce tableau les rassemble et fixe **la décision que chaque résultat
conditionne**.

| Bloc | Requêtes | Décision conditionnée |
|---|---|---|
| **Structure réelle** | §3bis.14-A | Le schéma cible ; les noms **réels** des contraintes CHECK ; **le type réel des colonnes booléennes** (D1) |
| **Volumétrie & tables mortes** | §3bis.14-B | Confirmer que `subscriptions`, `subscription_items`, `availability_*`, `client_forms` sont vides → suppression sans arbitrage |
| **🔴 Chemins des photos** | §3bis.14-C | **Le point le plus critique** : combien de photos ne se résolvent que par `metadata->supabase_path` ou par `file_path` seul (§10.8) |
| **Valeurs non contraintes** | §3bis.14-D | Les enums cibles réels (`product_type`, `status`, `provider`, `type`, `print_status`) |
| **Orphelins** | §3bis.14-E | Les 6 colonnes de type FK sans contrainte |
| **Backfills incomplets** | §3bis.14-F | Faut-il rejouer `photos:process-existing`, `orders:backfill-customer-info` **avant** de migrer |
| **Compte admin du seeder** | §3bis.14-G | Action de sécurité immédiate (§6.14) |
| **Travail restant des backfills photo** | §5.14 | Nombre de photos non traitées / sans vignette clean |
| **Activité commerciale** | §7.14-A | Volumétrie des commandes, CA, mix produit |
| **🔴 Portée du bug de quantité** | §7.14-B | Quelles **factures déjà émises** sont fausses (§7.6) |
| **Cohérence comptable** | §7.14-C | Totaux, factures manquantes, **continuité de la séquence** |
| **Santé du tunnel de paiement** | §7.14-D | Taux de conversion, causes d'échec |
| **Anomalies structurelles** | §7.14-E | Commandes mixtes invisibles, doublons de paiement, backlog d'expédition |
| **État de la file & échecs** | §8.14 | Vider la file avant bascule ; **lire `failed_jobs`** |
| **Preuve que la purge RGPD tourne** | §8.14 | Reste-t-il des données au-delà des rétentions |

**Livrable de la phase 1** : un document de relevé daté, avec les résultats bruts. C'est lui, et non
le présent audit, qui servira de référence au script.

**Une requête supplémentaire, spécifique à la migration** — la carte des identités :

```sql
-- Combien de personnes distinctes, et par quelle ancre sont-elles rattachées ?
SELECT
  (SELECT count(*) FROM users)                                            AS users,
  (SELECT count(*) FROM clients)                                          AS clients,
  (SELECT count(*) FROM clients WHERE user_id IS NOT NULL)                AS clients_avec_compte,
  (SELECT count(*) FROM clients WHERE user_id IS NULL)                    AS clients_sans_compte,
  (SELECT count(DISTINCT lower(guest_email)) FROM orders
     WHERE guest_email IS NOT NULL)                                       AS emails_commandes,
  (SELECT count(*) FROM orders WHERE user_id IS NULL
     AND guest_email IS NOT NULL)                                         AS commandes_invite,
  (SELECT count(*) FROM galleries WHERE user_id IS NULL
     AND assigned_email IS NOT NULL)                                      AS galeries_en_attente,
  (SELECT count(*) FROM reservations WHERE user_id IS NULL
     AND guest_email IS NOT NULL)                                         AS reservations_invite;

-- Emails présents dans orders/galleries/reservations SANS user ni client correspondant
SELECT DISTINCT lower(e.email) AS email_orphelin FROM (
  SELECT guest_email AS email FROM orders      WHERE guest_email    IS NOT NULL
  UNION SELECT assigned_email FROM galleries   WHERE assigned_email IS NOT NULL
  UNION SELECT guest_email    FROM reservations WHERE guest_email   IS NOT NULL
  UNION SELECT email          FROM contact_messages
) e
WHERE NOT EXISTS (SELECT 1 FROM users   u WHERE lower(u.email) = lower(e.email))
  AND NOT EXISTS (SELECT 1 FROM clients c WHERE lower(c.email) = lower(e.email))
ORDER BY 1;
```

---

## 10.4. Classification des 38 tables

| Table | Décision | Justification |
|---|---|---|
**À MIGRER — cœur métier (13)** | | |
| `users` | ✅ **migrer** | Conserver le hash bcrypt tel quel (rounds 12) → les mots de passe continuent de fonctionner. **Retirer les 4 colonnes Cashier** |
| `clients` | ✅ migrer, **nettoyer** | `source` non fiable (§10.7-R1) |
| `galleries` | ✅ **migrer** | Cœur du système. Décision de modélisation en §11 (D2). **UUID + `share_code` + `access_token` préservés** |
| `photos` | ✅ **migrer** | **Le point critique** : normaliser les chemins (§10.8) |
| `event_categories` | ✅ migrer | |
| `gallery_product_types` | ✅ migrer | Attention au booléen à 3 états `requires_shipping` (§10.7-R4) |
| `pack_tiers` | ✅ migrer | |
| `prestations` | ✅ migrer | Simplifier la triple représentation du prix (§10.7-R6) |
| `reservations` | ✅ migrer | Absorber `client_forms` (§10.7-R3) |
| `orders` | ✅ **migrer intégralement** | **Rétention légale ~10 ans** — aucune ligne écartée |
| `order_items` | ✅ **migrer intégralement** | idem. `photo_id = NULL` = photo supprimée, **pas une erreur** |
| `invoices` | ✅ **migrer intégralement** | idem. Conserver `invoice_number` **à l'identique** (séquence légale) |
| `payments` | ✅ **migrer intégralement** | idem |
**À MIGRER — support (5)** | | |
| `gift_codes` | ✅ migrer | ⚠️ Les quotas dépendent de `orders.gift_code_id` → **migrer les deux ensemble** (§10.7-R7) |
| `gift_cards` | ✅ migrer | Registre manuel (§2.9). Décision produit en §11 |
| `school_sessions` | ✅ migrer | Sans `zip_path` ni `batch_id` (transitoires) |
| `privacy_audit_logs` | ✅ **migrer intégralement** | **Preuve de conformité RGPD** — perdre l'historique lui ôte sa raison d'être |
| `contact_messages` | 🟡 migrer les < 12 mois | Rétention déjà appliquée par le cron |
**À NE PAS MIGRER — régénérable (8)** | | |
| `cache`, `cache_locks` | ❌ | Vides en production (`CACHE_STORE=file`, §4.0) |
| `sessions` | ❌ | Vide en production (`SESSION_DRIVER=file`). Données personnelles (IP/UA) |
| `jobs`, `job_batches`, `failed_jobs` | ❌ | **Vider la file avant bascule** ; `failed_jobs` à **lire** puis abandonner |
| `password_reset_tokens` | ❌ | Probablement vide (endpoint sans UI, §2.7) |
| `personal_access_tokens` | ❌ | Se régénèrent à la connexion. Et le token Bearer est à supprimer (§6.8) |
**À NE PAS MIGRER — transitoire (3)** | | |
| `photo_uploads` | ❌ | Suivi de traitement. **Lire les `failed` avant** (§5.14) |
| `school_session_exports` | ❌ | Suivi de job ; les ZIP sont régénérables à la demande |
| `privacy_exports` | 🟡 | Suivi de job. **Migrer l'historique** (trace des demandes RGPD honorées) sans les fichiers |
**À ABANDONNER (4)** | | |
| `subscriptions`, `subscription_items` | ❌ **supprimer** | Cashier mort **et cassé** (`user_id` bigint vs uuid) |
| `availability_slots`, `availability_patterns` | ⚖️ **arbitrer** | Module mort. Si les tables sont vides (à confirmer §10.3) → suppression sans enjeu |
**À ABSORBER (1)** | | |
| `client_forms` | ⚖️ | Vestige. Absorber `fullname`/`phone` dans `reservations.guest_*` si non vide |

**Récapitulatif** : **18 tables à migrer**, 11 à ne pas migrer, 4 à abandonner, 1 à absorber,
4 à arbitrer.

---

## 10.5. Les contraintes non négociables

| # | Contrainte | Conséquence sur le script |
|---|---|---|
| **C1** | **Rétention comptable ~10 ans** (Code de commerce) sur `orders`, `order_items`, `invoices`, `payments` | Migration **intégrale**, aucun filtre de date, aucune anonymisation |
| **C2** | **Continuité de la séquence de facturation** | `invoices.invoice_number` conservé **à l'identique**. La nouvelle séquence doit **reprendre au maximum existant**, jamais repartir de 1 |
| **C3** | **Numéros de commande** `OT-YYYY-NNNNN` | Idem : le générateur doit reprendre la suite |
| **C4** | **Journal d'audit RGPD** | `privacy_audit_logs` migré intégralement — c'est la preuve de conformité |
| **C5** | **Consentements horodatés** | `cgv_accepted_at`, `gdpr_consent_at`, `contact_messages.gdpr_consent_*` conservés tels quels. ⚠️ Les `consent_ip` sont **tous à `172.19.0.1`** (§6.12) : les migrer **sans les corriger** — inventer une IP serait pire |
| **C6** | **Fuseau horaire** | Tous les timestamps ont été écrits par PHP en `Europe/Paris` (§1.3). Vérifier le type réel des colonnes (`timestamp` vs `timestamptz`) au relevé §10.3-A et **ne pas décaler** |
| **C7** | **Hashes de mots de passe** | Migrés tels quels. Ne **jamais** régénérer : les clientes seraient déconnectées et devraient utiliser un « mot de passe oublié » qui **n'a pas d'interface** (§6.2.5) |

> **Sur C2/C3** : c'est un piège classique. Si la nouvelle application régénère les numéros à partir de
> 1, on obtient soit une collision sur la contrainte `UNIQUE`, soit deux factures portant le même
> numéro à deux exercices différents. Le script doit **initialiser les séquences** :
> ```sql
> -- après migration, avant toute nouvelle facture
> SELECT max(CAST(SUBSTRING(invoice_number FROM 'F-\d{4}-(\d+)') AS INTEGER))
> FROM invoices WHERE invoice_number LIKE 'F-' || date_part('year', now()) || '-%';
> ```

---

## 10.6. Résolution d'identité — le problème central

C'est la difficulté conceptuelle de cette migration. **Une même personne physique peut être présente
sous cinq formes**, sans clé commune :

```
                     ┌─── users (compte, email UNIQUE)
                     │
  Une personne  ─────┼─── clients (fiche, email UNIQUE, user_id nullable)
                     │
                     ├─── orders.guest_email        (+ guest_first_name / guest_last_name)
                     ├─── galleries.assigned_email  (galerie « en attente de compte »)
                     ├─── reservations.guest_email  (+ guest_name / guest_phone)
                     └─── contact_messages.email
```

**Deux mécanismes de rattachement coexistent déjà dans le code** :

1. **Par `user_id`** — quand la personne a un compte ;
2. **Par email « en attente »** — `AuthController::register()` réassigne, à l'inscription, les
   `galleries.assigned_email`, `orders.guest_email` et `reservations.guest_email` correspondants et
   **sans `user_id`** (§2.6).

Et `UserObserver` crée ou rattache un `Client` par email à chaque création de `User` (§3.8.6).

### Stratégie recommandée

**Ne pas chercher à fusionner les identités pendant la migration.** Reproduire les deux mécanismes,
et rendre le rattachement explicite dans le nouveau modèle :

| Étape | Action |
|---|---|
| 1 | Migrer `users` **tel quel** (l'email est déjà unique) |
| 2 | Migrer `clients` **tel quel**, en conservant `user_id` — l'email est déjà unique, donc aucune déduplication n'est nécessaire |
| 3 | Migrer `orders`, `galleries`, `reservations` en conservant **à la fois** `user_id` et les colonnes email |
| 4 | **Ne pas** normaliser les emails (`lower()`) sur les colonnes existantes : cela pourrait créer des collisions sur `clients.email UNIQUE`. Normaliser uniquement **à la comparaison** |
| 5 | Reproduire le rattachement à l'inscription dans la nouvelle application |
| 6 | Produire un **rapport des emails orphelins** (requête §10.3) — ni `users`, ni `clients` : ce sont des personnes connues du système sans fiche. À arbitrer avec la photographe |

> **Piège à éviter** : créer un `Client` pour chaque `guest_email` trouvé dans `orders`. Cela
> paraîtrait « propre » mais **fabriquerait des fiches clients qui n'ont jamais existé**, avec
> `gdpr_consent = false` — donc des données personnelles conservées sans base légale. Les deux
> migrations de données historiques du projet l'ont fait pour les réservations
> (`migrate_existing_clients`, §3bis.14) ; c'est précisément l'une des raisons pour lesquelles
> `clients.source` est faux.

---

## 10.7. Règles de nettoyage, par domaine

| # | Règle | Détail |
|---|---|---|
| **R1** | **`clients.source` : ne pas migrer la valeur** | Trois écritures forcent `'reservation'` en dur (§3.8.6) et `'contact'` n'est **jamais** écrit. Deux options : (a) recalculer — `reservation` si la personne a une réservation, `contact` si elle n'a qu'un message de contact, sinon `manual` ; (b) abandonner la colonne. **Recommandation : (a)**, avec le calcul documenté |
| **R2** | **`guest_first_name` / `guest_last_name` : ne pas « re-corriger »** | La scission historique par `explode(' ', $name, 2)` a produit des découpages approximatifs (« Marie Claire Dupont » → « Marie » / « Claire Dupont »). L'original est **perdu** (colonne supprimée). Toute tentative de correction automatique produirait d'autres erreurs. **Migrer tel quel** |
| **R3** | **`client_forms` : absorber** | Si non vide : reporter `fullname` → `reservations.guest_name` (si vide) et `phone` → `reservations.guest_phone` (si vide), conserver `requirements` dans un champ de notes. Puis abandonner la table |
| **R4** | **Booléens à trois états : préserver le `NULL`** | `gallery_product_types.requires_shipping` : `NULL` signifie « hériter de la règle statique ». **Normaliser les `NULL` en `false` changerait la facturation des frais de port** (§3bis.6). Idem pour `galleries.shipping_fee` (`NULL` → défaut de config) et `photos.price` |
| **R5** | **Booléens stockés en texte : normaliser** | Si le relevé §10.3-A révèle des colonnes `text` contenant `'true'`/`'false'` (séquelle de `CastsBooleansForPostgres`, §3.9), les convertir en `boolean` natif au moment de l'insertion. **C'est la seule transformation de type indispensable** |
| **R6** | **`prestations` : simplifier le prix** | `price` (NOT NULL) + `price_text` + `price_unit` → `price` **nullable** + `price_display`. Règle : si `price_text` est renseigné, il devient `price_display` ; `price` numérique conservé pour le tri et les statistiques |
| **R7** | **Codes promo : migrer avec les commandes** | Les quotas se calculent depuis `COUNT(orders WHERE gift_code_id = … AND status = 'paid')`. Migrer `gift_codes` sans `orders.gift_code_id` **réinitialiserait tous les quotas** (§7.11) |
| **R8** | **`orders.metadata` : extraire le token** | `download_token` + `download_token_expires_at` sortent du JSON vers des colonnes dédiées (ou une table de tokens), cf. §3bis.15-4. Les tokens expirés (> 7 j) ne sont pas migrés |
| **R9** | **Colonnes mortes : ne pas créer** | `users.stripe_id`/`pm_type`/`pm_last_four`/`trial_ends_at`, `photos.file_path_web`/`file_path_watermark`, `gift_cards.pdf_path` — à confirmer vides au relevé §10.3-C |
| **R10** | **Valeurs d'enum mortes : ne pas reprendre** | `clients.source='contact'`, `availability_slots.status='unavailable'`, `gift_cards.status='cancelled'`. ⚠️ **Exception : `orders.status='refunded'` doit être CONSERVÉ** — c'est un manque fonctionnel à combler (§7.9), pas une valeur morte à supprimer |
| **R11** | **`photos.metadata` : conserver, mais documenter** | Après normalisation des chemins (§10.8), `metadata` ne doit plus contenir que `original_filename`, `size`, `mime_type`. Les clés `storage_path` et `supabase_path` deviennent inutiles — **mais ne les supprimer qu'après validation de la migration des fichiers** |
| **R12** | **`school_sessions` : ne pas migrer les transitoires** | `zip_path`, `batch_id`, `error_message`, `processed_photos` sont des états de traitement. `product_types_config` (JSON) est en revanche à **convertir en lignes relationnelles** si la refonte unifie les deux représentations (§3bis.9) |

---

## 10.8. 🔴 La migration des fichiers MinIO

**C'est le risque n°1 de toute l'opération.** Grâce au principe P4 (UUID préservés), **aucun fichier
n'a besoin d'être déplacé**. Mais les **références en base** doivent être normalisées.

### Le problème

`Photo::getResolvedStoragePathAttribute()` résout le fichier original par une cascade :

```
file_path_hd  →  metadata->'storage_path'  →  metadata->'supabase_path'  →  file_path
```

Quatre populations coexistent donc en base, avec des risques différents :

| Population | Résolution | Risque |
|---|---|---|
| A | `file_path_hd` renseigné | ✅ aucun — cas normal |
| B | `file_path_hd` NULL, `metadata->storage_path` présent | 🟡 à normaliser |
| C | `file_path_hd` NULL, `metadata->supabase_path` présent | 🔴 **héritage Supabase Storage** — le fichier peut ne plus exister |
| D | Rien que `file_path`, potentiellement une URL absolue `http…` | 🔴 **le plus dangereux** |

### Procédure

**Étape 1 — Quantifier** (requêtes §3bis.14-C). Cette étape **conditionne tout le reste**.

**Étape 2 — Inventorier les objets réellement présents** :

```bash
mc ls --recursive src/galleries > /tmp/minio-inventory.txt
wc -l /tmp/minio-inventory.txt
```

**Étape 3 — Réconcilier base ↔ objets.** Charger l'inventaire dans une table temporaire et croiser :

```sql
CREATE TEMP TABLE minio_objects (path text PRIMARY KEY);
-- \copy minio_objects FROM '/tmp/minio-paths.txt'

-- Photos dont le chemin résolu N'EXISTE PAS sur MinIO → à traiter à la main
SELECT p.id, p.gallery_id, p.title,
       coalesce(p.file_path_hd,
                p.metadata->>'storage_path',
                p.metadata->>'supabase_path',
                p.file_path) AS chemin_resolu
FROM photos p
WHERE NOT EXISTS (
  SELECT 1 FROM minio_objects m
  WHERE m.path = coalesce(p.file_path_hd, p.metadata->>'storage_path',
                          p.metadata->>'supabase_path', p.file_path));

-- Objets MinIO non référencés en base → orphelins (cf. storage:clean-orphans)
SELECT m.path FROM minio_objects m
WHERE NOT EXISTS (
  SELECT 1 FROM photos p WHERE m.path IN (
    p.file_path, p.file_path_hd, p.file_path_preview,
    p.file_path_thumbnail, p.file_path_thumbnail_clean,
    p.metadata->>'storage_path', p.metadata->>'supabase_path'))
AND NOT EXISTS (SELECT 1 FROM invoices i WHERE m.path = i.file_path);
```

**Étape 4 — Normaliser en base.** Écrire une seule colonne de chemin par dérivé, avec le chemin
résolu :

```sql
UPDATE photos SET file_path_hd = coalesce(
        file_path_hd, metadata->>'storage_path', metadata->>'supabase_path', file_path)
WHERE file_path_hd IS NULL;
```

**Étape 5 — Décider pour les photos irrécuperables** (populations C et D dont le fichier est absent).
Trois options, à arbitrer avec la photographe selon le volume :

| Option | Quand |
|---|---|
| Marquer `is_processed = false` et rejouer `photos:process-existing` | si l'original existe sous un autre chemin |
| Marquer la photo comme indisponible et la retirer des galeries | si le fichier a réellement disparu |
| **Ne rien faire** et documenter | si le volume est négligeable et les galeries anciennes |

> ⚠️ **Ne jamais supprimer les lignes `photos` référencées par un `order_items`** : cela casserait la
> traçabilité d'une commande payée. La dénormalisation (`photo_title`, `gallery_title`) protège la
> facture, mais `order_items.photo_id` doit rester valide ou passer explicitement à `NULL`.

### Les factures PDF

`invoices.file_path` pointe le même bucket (§4.3). **Vérifier leur présence est une obligation
légale** :

```sql
-- Factures dont le PDF est absent du bucket (à régénérer avant bascule)
SELECT i.invoice_number, i.file_path, o.order_number, o.paid_at
FROM invoices i JOIN orders o ON o.id = i.order_id
WHERE i.file_path IS NULL
   OR NOT EXISTS (SELECT 1 FROM minio_objects m WHERE m.path = i.file_path);
```

`Admin/OrderController::downloadInvoice()` sait régénérer une facture manquante (§7.7) — mais l'endpoint
n'est appelé par personne. Une commande artisan de régénération en masse serait à écrire pour ce cas.

**Recommandation forte** : profiter de la refonte pour **séparer les buckets** (`photos` et
`invoices`, §4.3), avec des politiques de rétention distinctes. Le déplacement des factures est peu
volumineux et se fait une fois.

---

## 10.9. Ordre de migration

Dicté par les dépendances de clés étrangères.

```
 1. users                       (aucune dépendance)
 2. clients                     → users
 3. prestations                 (aucune)
 4. event_categories            (aucune)
 5. school_sessions             (aucune)
 6. galleries                   → users, event_categories, school_sessions
 7. galleries (2ᵈ passe)        → parent_id, thumbnail_photo_id   ⚠️ voir ci-dessous
 8. photos                      → galleries
 9. galleries (3ᵉ passe)        → thumbnail_photo_id              ⚠️
10. gallery_product_types       → galleries
11. pack_tiers                  → gallery_product_types
12. gift_codes                  (aucune)
13. reservations                → users, clients, prestations
14. carts + cart_items          → users, gift_codes, photos       (optionnel, §10.10)
15. orders                      → users, carts, gift_codes
16. order_items                 → orders, photos
17. invoices                    → orders
18. payments                    → orders, reservations, users
19. gift_cards                  → payments
20. contact_messages            (aucune)
21. privacy_audit_logs          → users
22. privacy_exports             → users
```

> ⚠️ **`galleries` a deux références circulaires** qui imposent trois passes :
> - **`parent_id` → `galleries`** (auto-référence) : insérer d'abord les galeries `parent_id IS NULL`,
>   puis les enfants. Ou insérer tout avec `parent_id = NULL`, puis un `UPDATE`.
> - **`thumbnail_photo_id` → `photos`**, alors que `photos.gallery_id → galleries`. Il faut donc :
>   insérer les galeries **sans** `thumbnail_photo_id`, insérer les photos, **puis** un `UPDATE` des
>   `thumbnail_photo_id`.
>
> C'est le seul point de complexité de l'ordonnancement, et il est facile à oublier.

---

## 10.10. Squelette du script

**Forme recommandée** : une commande artisan dans la **nouvelle** application, lisant l'ancienne base
via une **seconde connexion Laravel** (`legacy`), plutôt qu'un script SQL. Trois raisons : la
transformation des booléens et du JSON est plus simple en PHP, le rapport de comptage est intégré, et
la reprise après interruption est naturelle.

```php
// config/database.php de la NOUVELLE application
'legacy' => [
    'driver' => 'pgsql',
    'host'   => env('LEGACY_DB_HOST'),      // la COPIE restaurée, jamais la prod
    // …
    'options' => [],                        // ⚠️ PAS d'EMULATE_PREPARES ici
],
```

```php
class MigrateLegacyData extends Command
{
    protected $signature = 'legacy:migrate
        {--step=* : Étapes à jouer (défaut : toutes, dans l\'ordre)}
        {--dry-run : Compte sans écrire}
        {--chunk=500}';

    /** Ordre imposé par les FK — cf. §10.9 */
    private const STEPS = [
        'users', 'clients', 'prestations', 'event_categories', 'school_sessions',
        'galleries', 'photos', 'galleries_thumbnails',   // 3ᵉ passe
        'gallery_product_types', 'pack_tiers', 'gift_codes',
        'reservations', 'orders', 'order_items', 'invoices', 'payments',
        'gift_cards', 'contact_messages', 'privacy_audit_logs', 'privacy_exports',
    ];

    public function handle(): int
    {
        $report = [];

        foreach ($this->resolveSteps() as $step) {
            $this->info("── {$step}");
            $before = $this->targetCount($step);
            $this->{'migrate'.Str::studly($step)}();          // idempotent
            $report[$step] = [
                'source' => $this->legacyCount($step),
                'cible'  => $this->targetCount($step),
                'insérés'=> $this->targetCount($step) - $before,
            ];
        }

        $this->table(['Étape', 'Source', 'Cible', 'Insérés'], /* … */);
        return self::SUCCESS;
    }
}
```

**Trois invariants à respecter dans chaque méthode `migrateXxx()`** :

```php
// 1. Idempotence par UUID d'origine (P4/P5)
DB::table('photos')->upsert($rows, ['id'], [/* colonnes à mettre à jour */]);

// 2. Traitement par lots — les tables volumineuses ne tiennent pas en mémoire
DB::connection('legacy')->table('photos')->orderBy('id')->chunkById(500, function ($rows) { … });

// 3. Normalisation explicite des booléens (R5)
$isDownloadable = in_array(strtolower((string) $row->is_downloadable),
                           ['true', 't', '1', 'yes'], true);
```

> **Ne pas utiliser les modèles Eloquent de l'ancienne application** pour lire : ils embarquent le
> trait `CastsBooleansForPostgres`, les accesseurs, les `$appends` (qui déclencheraient des
> signatures MinIO !) et les hooks `boot()` (qui régénéreraient des `share_code`). Lire en **query
> builder brut** sur la connexion `legacy`.

---

## 10.11. Plan de validation

À jouer après chaque exécution, avant toute décision de bascule.

### Niveau 1 — Comptages

```sql
-- Pour chaque table migrée : source vs cible
-- (le script produit ce tableau automatiquement)
```

Critère d'acceptation : **égalité stricte** pour les 13 tables du cœur métier, écarts **expliqués et
documentés** pour les autres (ex. `contact_messages` filtré à 12 mois).

### Niveau 2 — Cohérence référentielle

```sql
-- Aucun orphelin dans la nouvelle base
SELECT 'order_items sans order' AS ctl, count(*) FROM order_items oi
  WHERE NOT EXISTS (SELECT 1 FROM orders o WHERE o.id = oi.order_id)
UNION ALL SELECT 'photos sans galerie', count(*) FROM photos p
  WHERE NOT EXISTS (SELECT 1 FROM galleries g WHERE g.id = p.gallery_id)
UNION ALL SELECT 'invoices sans order', count(*) FROM invoices i
  WHERE NOT EXISTS (SELECT 1 FROM orders o WHERE o.id = i.order_id)
UNION ALL SELECT 'galleries parent inexistant', count(*) FROM galleries g
  WHERE g.parent_id IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM galleries p WHERE p.id = g.parent_id)
UNION ALL SELECT 'thumbnail inexistant', count(*) FROM galleries g
  WHERE g.thumbnail_photo_id IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM photos p WHERE p.id = g.thumbnail_photo_id);
```

### Niveau 3 — Invariants métier (les plus importants)

```sql
-- Somme du CA identique de part et d'autre
SELECT sum(total) FROM orders WHERE status = 'paid';            -- cible
-- vs la même requête sur la base legacy → écart attendu : 0,00 €

-- Continuité de la facturation : même min, même max, même compte, aucun trou nouveau
-- (requête de la §7.14-C, à jouer sur les deux bases)

-- Les codes promo ont le même nombre d'utilisations payées (R7)
SELECT gc.code, count(*) FILTER (WHERE o.status = 'paid')
FROM gift_codes gc LEFT JOIN orders o ON o.gift_code_id = gc.id
GROUP BY gc.code ORDER BY gc.code;

-- Toutes les galeries conservent leur code de partage et leur token
SELECT count(*) FROM galleries WHERE share_code IS NULL OR access_token IS NULL;
-- attendu : identique à la source

-- Les hashes de mot de passe sont intacts
SELECT count(*) FROM users WHERE password NOT LIKE '$2y$%';     -- attendu : 0
```

### Niveau 4 — Fichiers

```sql
-- Toute photo migrée doit avoir un chemin résolu présent sur MinIO
-- (requête §10.8 étape 3, rejouée sur la nouvelle base) → attendu : 0

-- Toute facture doit avoir son PDF → attendu : 0
```

### Niveau 5 — Fonctionnel, à la main

| Contrôle | Comment |
|---|---|
| Une galerie cliente s'ouvre avec son **ancien** code de partage | saisir un `share_code` réel |
| Les vignettes et previews s'affichent | ouvrir une galerie de 50+ photos |
| Une galerie téléchargeable produit son ZIP | bouton de téléchargement |
| Une facture existante se télécharge | via l'admin |
| Un compte client existant se connecte avec **son mot de passe actuel** | ⚠️ contrôle décisif (C7) |
| Le tableau de bord client affiche l'historique complet | commandes, galeries, réservations |
| Une commande de test aboutit de bout en bout | en sandbox SumUp |

---

## 10.12. Stratégie de bascule

**Recommandation : bascule unique avec fenêtre courte**, pas de double écriture.

Justification : le trafic est faible (photographe indépendante), il n'y a **aucun besoin de continuité
de service à la minute**, et une synchronisation bidirectionnelle sur 18 tables coûterait plus cher en
risque qu'une interruption d'une heure.

### Déroulé

| # | Étape | Durée |
|---|---|---|
| 1 | **Répétition générale** sur la copie restaurée, validation niveaux 1-5 | plusieurs itérations |
| 2 | Choisir une fenêtre creuse (hors campagne scolaire, hors envoi de galeries) | — |
| 3 | **Annoncer** à la photographe : ne rien faire dans l'admin pendant la fenêtre | — |
| 4 | **Vider la file d'attente** : attendre `jobs` à 0, lire `failed_jobs` | 15 min |
| 5 | **Page de maintenance** sur le front + mode maintenance API | 5 min |
| 6 | **`pg_dump` final** (frais, post-arrêt d'écriture) | 10 min |
| 7 | **Exécuter la migration** sur la nouvelle base | 30-60 min |
| 8 | **Validation niveaux 1-4** (automatisée) | 10 min |
| 9 | **Validation niveau 5** (manuelle, la liste ci-dessus) | 20 min |
| 10 | Basculer le DNS / la configuration front vers la nouvelle API | 5 min |
| 11 | **Surveillance rapprochée** : logs, Sentry, `/health`, premiers paiements | 48 h |

**Critère de retour arrière** : si la validation de l'étape 8 ou 9 échoue, on remet l'ancienne
configuration. **L'ancienne base n'a pas été touchée** (P3) — le retour arrière est un changement de
configuration, pas une restauration.

**Point de non-retour** : la première **écriture** dans la nouvelle base après la bascule (une
commande, une inscription). Au-delà, un retour arrière perdrait cette donnée. D'où l'importance de la
surveillance des 48 h.

---

## 10.13. Ce qu'il ne faut pas faire

| ❌ | Pourquoi |
|---|---|
| **`pg_dump` + `pg_restore` vers la nouvelle base** | Reconduirait les 5 dettes structurelles (§9.2), les 18 CASCADE, la couche booléenne. P2 |
| **Régénérer les UUID** | Casserait tous les chemins MinIO, tous les codes de partage, tous les liens envoyés. P4 |
| **Régénérer les numéros de facture ou de commande** | Rupture de séquence légale, collisions. C2/C3 |
| **Re-hasher les mots de passe** | Déconnecterait toutes les clientes, vers un « mot de passe oublié » **sans interface**. C7 |
| **Normaliser les `NULL` des booléens à 3 états** | Changerait la facturation des frais de port. R4 |
| **Créer un `Client` par `guest_email`** | Fabriquerait des fiches sans consentement. §10.6 |
| **Corriger les `consent_ip`** | Inventer une preuve est pire que l'absence de preuve. C5 |
| **« Réparer » les `guest_first_name`** | L'original est perdu, toute heuristique ajouterait des erreurs. R2 |
| **Filtrer ou anonymiser `orders`/`invoices`/`payments`** | Rétention légale. C1 |
| **Lire l'ancienne base avec les modèles Eloquent existants** | Trait booléen, `$appends` (signatures MinIO !), hooks `boot()` régénérant les codes. §10.10 |
| **Migrer directement depuis la production** | Toujours depuis une copie restaurée. Phase 0 |
| **Supprimer `metadata->supabase_path` avant validation** | C'est le dernier recours de résolution des fichiers. R11 |
| **Commencer avant la phase 0** | Sans sauvegarde vérifiée, aucune erreur n'est réversible |

---

## 10.14. Estimation et checklist

### Charge estimée

| Phase | Charge |
|---|---|
| Phase 0 — sauvegardes et environnement | **1,5 j** |
| Phase 1 — relevé de l'existant et document de référence | **1 j** |
| Nettoyage préalable (code mort, 140 Mo, backfills à rejouer) | **1 j** |
| Écriture du script (20 étapes, ~1 500 lignes) | **4-6 j** |
| Réconciliation base ↔ MinIO (§10.8) | **1-2 j** |
| Requêtes de validation (niveaux 1-4) | **1 j** |
| Répétitions générales (3 itérations minimum) | **2 j** |
| Bascule + surveillance | **1 j + 48 h** |
| **Total** | **≈ 13 à 16 jours** |

**Aléa principal** : le résultat de la requête §3bis.14-C. Si un volume important de photos ne se
résout que par `supabase_path` ou par `file_path` seul, la réconciliation des fichiers peut doubler.

### Checklist d'exécution

```
PHASE 0 — Prérequis
[ ] pg_dump de production, format custom
[ ] Restauration testée sur une base séparée
[ ] Copie MinIO (mc mirror) + comparaison du nombre d'objets
[ ] api/.env local repointé sur la copie (plus jamais la prod)
[ ] Sauvegardes récurrentes mises en place (quotidien / hebdo)

PHASE 1 — Relevé  (toutes les requêtes sur la COPIE)
[ ] §3bis.14-A  structure réelle, contraintes, TYPE des booléens
[ ] §3bis.14-B  volumétrie, tables mortes confirmées vides
[ ] §3bis.14-C  🔴 chemins des photos — LE point bloquant
[ ] §3bis.14-D  valeurs réelles des colonnes non contraintes
[ ] §3bis.14-E  orphelins des 6 FK manquantes
[ ] §3bis.14-F  backfills incomplets
[ ] §3bis.14-G  🔴 compte admin du seeder → action de sécurité immédiate
[ ] §5.14       travail restant des backfills photo
[ ] §7.14 A-E   activité commerciale, bug de quantité, cohérence comptable
[ ] §8.14       file, failed_jobs, preuve que la purge RGPD tourne
[ ] §10.3       carte des identités + emails orphelins
[ ] Document de relevé daté, rédigé et validé

DÉCISIONS À ARBITRER (§11)
[ ] Pooler PostgreSQL — D1
[ ] Modélisation de galleries — D2
[ ] Disponibilités : finir ou supprimer
[ ] Bons cadeaux : registre manuel ou tunnel d'achat
[ ] Remboursement / avoir : à implémenter ?
[ ] Portfolio administrable ?
[ ] Contacts scolaires : persister ?
[ ] clients.source : recalculer ou abandonner

PRÉPARATION
[ ] Connexion legacy configurée (sans EMULATE_PREPARES)
[ ] Script legacy:migrate écrit, 20 étapes, --dry-run fonctionnel
[ ] Requêtes de validation niveaux 1-4 écrites
[ ] Buckets séparés (photos / invoices) décidés
[ ] Séquences invoice_number / order_number initialisées au max existant

RÉPÉTITIONS
[ ] Itération 1 : comptages OK
[ ] Itération 2 : cohérence référentielle OK
[ ] Itération 3 : invariants métier OK + validation manuelle niveau 5
[ ] Écarts résiduels documentés et acceptés

BASCULE
[ ] Fenêtre choisie et annoncée
[ ] File vidée, failed_jobs lus
[ ] Mode maintenance activé
[ ] pg_dump final
[ ] Migration exécutée
[ ] Validations 1-4 automatisées : OK
[ ] Validation 5 manuelle : OK  (dont connexion avec un mot de passe existant)
[ ] DNS / config front basculés
[ ] Surveillance 48 h : logs, Sentry, /health, premiers paiements
[ ] Ancienne base conservée en lecture seule, indéfiniment
```

---
# 11. Synthèse pour la refonte

> **Objet** : convertir les dix sections précédentes en décisions. Cette section est la seule qui
> **prend parti** — les précédentes décrivent, celle-ci recommande. Chaque recommandation renvoie aux
> constats qui la fondent, pour qu'elle puisse être contestée sur pièces.

## 11.1. Verdict sur l'existant

**Ce projet fonctionne, en production, pour une cliente qui en dépend commercialement.** C'est le
premier fait à poser, parce qu'il conditionne tout le reste : on ne refond pas un prototype, on refond
un outil de travail.

Sa qualité suit une règle unique et lisible, établie à la §9.12 : **ce qui a cassé en production est
solide et documenté ; ce qui n'a jamais cassé porte les défauts.**

| Éprouvé en production → solide | Jamais éprouvé → fragile |
|---|---|
| Paiement SumUp (invariant de vérification, triple filet, idempotence) | **Facturation** (quantité absente, CASCADE destructrice, pas d'avoir) |
| Pipeline photo (4 optimisations mémoire, facteur ~15) | **Authentification** (déconnexion inopérante, 8 caractères, pas de 2FA) |
| Module RGPD (locator, export, effacement, audit) | **Panier** (469 lignes de service, zéro test) |
| Conception de la supervision (heartbeat, sondes, catalogue) | **File d'attente** (une seule voie pour temps réel et batch) |
| Upload chunké et sa gestion d'erreur | **Sessions scolaires** (module le plus complexe, zéro test) |

Et la corrélation la plus instructive de tout l'audit (§9.6) : **les six bugs que j'ai découverts sont
tous dans des domaines sans aucun test**, et **aucun n'aurait survécu à un test**.

**Chiffres de référence** : ~45 000 lignes hors dépendances · 177 endpoints · 38 tables · 29 modèles ·
226 méthodes de test couvrant 5 domaines sur 15 · 56 items de dette dont 12 critiques · ~1 050 lignes
de code mort · 9 mois de développement par une seule personne.

---

## 11.2. Les huit décisions à arbitrer

Aucune ligne de la refonte ne devrait être écrite avant que ces huit points soient tranchés. Pour
chacun : l'enjeu, les options, ma recommandation.

### D1 — Le pooler PostgreSQL 🔴 *technique, bloquante*

**Enjeu** : le pooler transaction (port 6543) impose `EMULATE_PREPARES`, qui casse les booléens, ce
qui a produit le trait `CastsBooleansForPostgres` sur 12 modèles et 20 `whereRaw` (§3.9). Le pooler
session (5432) a été essayé et **a saturé en production** (`EMAXCONNSESSION`, ~5000×/jour, §5.0.1).

| Option | Coût | Risque |
|---|---|---|
| **A.** Rester en 6543 et tester si retirer `STRINGIFY_FETCHES` suffit | 1 j de test | Faible — réversible |
| B. Réduire le besoin de connexions (moins de workers FPM, PDO persistant) puis passer en 5432 | 2-3 j | Moyen — retour possible du `EMAXCONNSESSION` |
| C. Postgres auto-hébergé sur le NAS | 3-5 j | Élevé — charge d'exploitation, sauvegardes à faire soi-même |

> **Recommandation : A, puis B si A échoue.** L'hypothèse à tester est simple et n'a jamais été
> vérifiée : c'est probablement `STRINGIFY_FETCHES` (qui fait remonter les booléens en chaînes) et non
> `EMULATE_PREPARES` seul qui casse la lecture. De nombreux déploiements Laravel + PgBouncer en mode
> transaction fonctionnent sans trait maison. **Un jour de test peut supprimer 12 traits et 20
> `whereRaw`** — c'est le meilleur retour sur investissement technique du projet.
>
> C est à écarter : elle transfère la responsabilité des sauvegardes à un NAS domestique, alors que
> l'absence de sauvegarde est déjà le risque n°3 (§9.9).

### D2 — La modélisation de `galleries` 🟠 *technique, structurante*

**Enjeu** : une table porte quatre concepts (privée, publique, événement, scolaire), d'où un
contrôleur de 505 lignes pour trois audiences (§3.4.2).

| Option | Conséquence |
|---|---|
| **A.** Conserver la table unique, mais avec un discriminant explicite, des scopes obligatoires et des contrôleurs séparés par audience | Migration triviale (P4 préservé), lisibilité fortement améliorée |
| B. Séparer en `private_galleries` / `event_galleries` / `school_galleries` | Migration plus lourde, duplication du code photo, perd le cumul cross-galerie des packs |

> **Recommandation : A.** Le problème réel n'est pas la table, c'est le **contrôleur mixte**. Séparer
> `GalleryController` en trois (public par code / client authentifié / admin) résout 80 % de la gêne
> pour 20 % du coût, et préserve l'UUID unique dont dépendent les chemins MinIO et le cumul de packs.

### D3 — Le module « Disponibilités » ⚖️ *produit*

**Enjeu** : 543 lignes, 2 tables, 12 endpoints, **zéro interface** (§2.8). La demande de réservation
se fait en texte libre.

> **Recommandation : supprimer, sauf demande explicite de la photographe.** Neuf mois se sont écoulés
> sans que l'interface soit écrite — c'est un signal. Et si le besoin réapparaît, réimplémenter une
> prise de rendez-vous sur créneaux est un chantier de quelques jours, sans doute mieux fait avec le
> recul de l'usage réel. **À confirmer par un `COUNT(*)` sur les deux tables** (§10.3) : si elles sont
> vides, la décision est sans enjeu de données.

### D4 — Les bons cadeaux 🟡 *produit*

**Enjeu** : la page publique promet « envoi par mail sous 24 h » et invite à contacter la photographe.
Côté API, `gift_cards` est un **registre manuel** ; `pdf_path` n'est jamais alimenté, et un bon cadeau
ne peut pas payer une commande (§2.9).

| Option | Coût |
|---|---|
| **A.** Assumer le registre manuel, et outiller ce qui manque : génération du PDF de la carte + envoi email depuis l'admin | 2-3 j |
| B. Tunnel d'achat complet (paiement, génération, utilisation au panier) | 8-10 j |
| C. Retirer la page publique | 0,5 j |

> **Recommandation : A.** La photographe vend déjà des bons par mail — le processus existe, il n'est
> pas outillé. Générer le PDF et l'envoyer d'un clic depuis l'admin comble exactement l'écart entre la
> promesse et la réalité. B est un investissement à ne consentir que si le volume le justifie, ce que
> le relevé §10.3 dira.

### D5 — Remboursement et avoir 🟠 *produit + comptable*

**Enjeu** : aucun mécanisme. `orders.status = 'refunded'` existe et n'est jamais écrit. Un
remboursement se fait dans le dashboard SumUp et **ne laisse aucune trace en base** (§7.9). Dès le
premier geste commercial, la comptabilité applicative diverge de la réalité.

| Option | Coût |
|---|---|
| **A.** Remboursement **déclaratif** : l'admin marque la commande `refunded`, génère un avoir numéroté (séquence `AV-YYYY-NNNNN`), le remboursement bancaire restant fait dans SumUp | 3-4 j |
| B. Intégration de l'API de remboursement SumUp | 6-8 j |

> **Recommandation : A.** L'essentiel n'est pas d'automatiser le virement, c'est que **la base sache
> qu'un remboursement a eu lieu** et qu'un document comptable l'atteste. L'option A résout le problème
> comptable pour la moitié du prix. B peut venir plus tard.

### D6 — Le portfolio administrable ⚖️ *produit*

**Enjeu** : le portfolio public est entièrement hors base — manifest JSON généré le 2026-04-23, images
dans `public/optimized/`, 6 catégories et 7 IDs YouTube en dur, ~250 mots d'éditorial par catégorie
dans `constants.ts`. **Ajouter une photo = un commit + un build** (§2.2).

| Option | Coût |
|---|---|
| A. Statu quo | 0 |
| **B.** Portfolio en base, servi par l'API, avec l'admin existant | 4-5 j |
| C. Statu quo + script de génération simplifié | 1 j |

> **Recommandation : B, mais en dernière position du chantier.** C'est la fonctionnalité qui rendrait
> la photographe autonome sur sa vitrine — la plus visible de son point de vue. Mais elle n'est ni
> urgente ni risquée, et elle bénéficiera d'un pipeline photo déjà refait. **À arbitrer avec elle** :
> combien de fois par an veut-elle changer son portfolio ? Si la réponse est « deux ou trois »,
> l'option C suffit.

### D7 — Les contacts scolaires 🟠 *produit + RGPD*

**Enjeu** : les contacts des parents (CSV `nom;email;telephone`) vivent dans la mémoire du navigateur
et sont perdus après envoi. Impossible de savoir qui a été prévenu, de relancer, ou de réenvoyer un
lien (§2.10).

> **Recommandation : persister, avec une rétention courte.** Une table `school_session_contacts`
> (session, galerie, nom, email, téléphone, `sent_at`, `channel`), purgée automatiquement **à la
> clôture de la session + 3 mois**. Cela transforme un manque opérationnel réel en fonctionnalité, et
> reste conforme au principe de minimisation dès lors que la rétention est bornée et documentée.
> **C'est un nouveau traitement à inscrire au registre RGPD** (§6.12-6).

### D8 — Le périmètre de l'authentification 🔴 *technique + sécurité*

**Enjeu** : mécanisme hybride session + token Bearer 30 jours en `localStorage`, déconnexion
inopérante, mot de passe à 8 caractères, aucun 2FA sur l'unique compte admin (§6.2).

> **Recommandation, sans alternative** :
> 1. **supprimer le token Bearer** — il est inutile, l'authentification passe déjà par la session
>    (§6.2.1). Cela supprime la cible d'une XSS sans rien casser ;
> 2. session cookie seule, `SESSION_DOMAIN` sur le domaine exact ;
> 3. `Password::min(12)->uncompromised()` ;
> 4. **TOTP sur le compte admin** — meilleur rapport sécurité/effort de tout le projet ;
> 5. finir la réinitialisation de mot de passe (l'API existe, l'interface manque) ;
> 6. journal des connexions.

---

## 11.3. Refondre ou restructurer ? Le point le plus important de cette section

L'audit a été commandé « en vue d'une refonte complète ». Ce que les dix sections précédentes montrent
mérite d'être posé franchement avant de s'engager.

| Option | Coût estimé | Ce qu'on gagne | Ce qu'on perd |
|---|---|---|---|
| **1. Réécriture complète** (nouveau dépôt, nouvelle stack) | **50-70 j** | Architecture propre, dette à zéro | **Neuf mois d'apprentissage des pannes réelles** : invariant SumUp, optimisations mémoire, garde-fous anti-race, seuils de supervision, catalogue de remédiation. Et une longue période où deux systèmes coexistent |
| **2. Restructuration ciblée** (même dépôt, refonte par domaine) | **30-40 j** | Les 5 dettes structurelles traitées, les 12 constats critiques corrigés, tests sur les domaines découverts | Le code garde des traces de son histoire |
| **3. Durcissement incrémental** (correctifs seuls) | **10-15 j** | Les 12 constats critiques corrigés | Les 5 dettes structurelles restent ; la dette continue de croître |

> **Recommandation : l'option 2, la restructuration ciblée.**
>
> Le raisonnement est le suivant. Les parties solides du projet — paiement, pipeline image, RGPD,
> conception de la supervision — représentent environ **40 % du code** et **la quasi-totalité du savoir
> acquis par les pannes**. Une réécriture complète les jetterait, et la probabilité de reproduire de
> mémoire les 20 garde-fous documentés (§11.7) est faible. Ce sont précisément les zones où une
> régression coûte de l'argent réel à la photographe.
>
> À l'inverse, les zones fragiles — facturation, authentification, panier, file d'attente, sessions
> scolaires — **doivent effectivement être réécrites**, pas rafistolées : elles n'ont pas de tests, pas
> de couche d'autorisation, pas de convention de réponse.
>
> La restructuration ciblée fait les deux : elle réécrit domaine par domaine ce qui doit l'être, en
> conservant et en documentant ce qui a fait ses preuves. Elle a aussi un avantage décisif dans ce
> contexte : **elle est livrable par incréments**, donc la photographe n'attend pas six mois avant de
> voir un bénéfice, et il n'y a **jamais deux systèmes à maintenir en parallèle**.
>
> Si le choix se porte malgré tout sur la réécriture complète — ce qui peut se justifier pour un
> projet de fin d'études, où la démonstration de compétence compte autant que le résultat — alors la
> §11.7 devient le livrable le plus important de cet audit : c'est la liste des choses à ne pas
> réapprendre par l'échec.

---

## 11.4. Architecture cible recommandée

Peu de changements de fond : la stack est adaptée au besoin. Les évolutions portent sur les points où
l'audit a montré un coût réel.

| Couche | Aujourd'hui | Cible | Pourquoi |
|---|---|---|---|
| API | Laravel 12 | **Laravel 12, conservé** | Adapté, maîtrisé, à jour |
| Base | Supabase, pooler transaction | **Idem + D1 tranché** | §11.2-D1 |
| **Cache / sessions** | fichier, partagé par bind mount | **Redis** | Débloque 3 problèmes d'un coup : couplage caché de la supervision (§4.7), cache des URL signées MinIO (§2.3), sessions sans état |
| **File d'attente** | 1 file, 1 worker | **3 files priorisées** (`realtime`, `default`, `heavy`) | §8.3 — les clients cessent d'attendre leur facture derrière un export |
| Stockage | MinIO, 1 bucket | **MinIO, 2 buckets** (`photos`, `invoices`) | Rétentions distinctes, pas de purge croisée (§4.3) |
| Front | Vue 3 + Vite + Tailwind | **Idem, conservé** | TypeScript strict, zéro `any` — la base est saine (§9.7) |
| **Réponses API** | aucune convention | **Enveloppe unique + API Resources systématiques** | Supprime mécaniquement une grande part des 1 000 lignes de types front (§3.7) |
| **Autorisation** | 4 mécanismes contradictoires | **Policies via le Gate, systématiquement** | §3.5 — un seul endroit où lire « qui a le droit de quoi » |
| Paiement | SumUp | **SumUp, conservé** + avoirs (D5) | L'invariant de vérification est juste |
| Emails / SMS | Brevo | **Idem + webhooks de délivrabilité** | §4.5 — aujourd'hui zéro traçabilité |
| Supervision | 4 sondes, alertes email | **Idem + les 3 filets externes branchés** | §8.10 — gratuit, non fait |
| **Déploiement** | cible `development`, code monté, `migrate` au boot | **Image versionnée, migrations explicites** | §4.7 — rollback par tag, pas par `git checkout` |
| **Tests** | 226 méthodes, 5 domaines | **+ panier, facturation, auth, scolaire** · Vitest sur le front | §9.6 — là où sont les bugs |
| **CI** | 5 jobs | **+ `composer audit`, `npm audit`, Dependabot** | §6.13 |

**Ce qui n'est pas recommandé** : changer de framework, changer de base, quitter MinIO, quitter SumUp,
introduire un ORM différent, passer à une architecture microservices. Aucun de ces changements ne
répond à un problème identifié par l'audit, et chacun ajouterait un risque sans bénéfice mesurable à
cette échelle.

---

## 11.5. Conserver / réécrire / abandonner

| Domaine | Décision | Justification |
|---|---|---|
| **Pipeline photo** (traitement, dérivés, filigrane) | **Conserver**, corriger 3 points | Les 4 optimisations mémoire sont irremplaçables (§5.5). Corriger : cache d'images en binaire (§5.11), vignettes clean non supprimées (§5.6), driver non déterministe (§5.0.2) |
| **Paiement SumUp** | **Conserver quasi tel quel** | Invariant de vérification, triple filet, idempotence (§7.5) |
| **Module RGPD** | **Conserver** | Le mieux conçu du projet. Corriger `consent_ip` et la CASCADE des factures |
| **Supervision** | **Conserver la conception**, **brancher** les filets | §8.10 |
| **Galeries privées** | **Restructurer** (D2) | Contrôleur mixte à séparer |
| **Galeries événements** | Conserver | Utilisé, publié, hiérarchie fonctionnelle |
| **Sessions scolaires** | **Réécrire avec des tests** + persister les contacts (D7) | Module le plus complexe, zéro test |
| **Panier / tarification** | **Réécrire avec des tests** | 469 lignes non testées, cumul cross-galerie à préserver |
| **Facturation** | **Réécrire** | 3 constats critiques (§7.15) |
| **Authentification** | **Réécrire** (D8) | 3 bugs, aucun test |
| **Réservations** | Réparer + tester | 3 routes en erreur 500 |
| **Portfolio** | Arbitrer (D6) | |
| **Bons cadeaux** | Outiller (D4) | |
| **Disponibilités** | **Abandonner** (D3) | 543 lignes, zéro interface |
| **Tables Cashier/Stripe** | **Abandonner** | Mortes et cassées |
| **Edge Function Supabase** | **Abandonner** | Orpheline |
| **`downloadFile()`** | **Abandonner immédiatement** | Vulnérable (§5.9) |
| **Token Bearer** | **Abandonner** | Inutile, surface XSS (§6.8) |
| **33 routes sans consommateur** | Abandonner / finir selon le cas | §2.16 |

---

## 11.6. Trajectoire proposée

Six phases, livrables indépendamment, dans un ordre qui fait décroître le risque avant d'ajouter de la
valeur.

### Phase 0 — Sécuriser l'existant · ~3 jours · **à faire maintenant**

Indépendante de toute décision de refonte. Détail en §6.15 et §9.11.

```
[ ] Sauvegardes (pg_dump + mc mirror) + restauration testée      ← prérequis absolu
[ ] api/.env local repointé sur une base locale
[ ] Vérifier / supprimer le compte admin du seeder
[ ] Supprimer downloadFile() + purger storage/app/temp (140 Mo)
[ ] Propager CF-Connecting-IP + restreindre trustProxies
[ ] Rate limits sur les 7 routes exposées
[ ] Corriger logout()
[ ] Password::min(12)->uncompromised()
[ ] Ajouter la quantité aux 4 gabarits (facture + 3 emails)
[ ] Brancher UptimeRobot + 2 checks healthchecks.io
[ ] composer audit / npm audit / Dependabot
```

> **Cette phase seule corrige 8 des 12 constats critiques**, dont une erreur de production
> quotidienne (le mauvais nombre de tirages imprimés) et une vulnérabilité exploitable.

### Phase 1 — Relever et décider · ~2,5 jours

```
[ ] Phase 1 de la §10.3 : toutes les requêtes, sur la copie restaurée
[ ] Document de relevé daté
[ ] Les 8 décisions de la §11.2, arbitrées et écrites — dont 5 avec la photographe
[ ] Test D1 : retirer STRINGIFY_FETCHES, mesurer
```

### Phase 2 — Nettoyer et poser les fondations · ~8 jours

```
[ ] Supprimer les ~1 050 lignes de code mort et les 4 tables mortes
[ ] Redis (cache + sessions) → débloque supervision, URL signées, sans-état
[ ] 3 files priorisées → les factures cessent d'attendre derrière les exports
[ ] Enveloppe de réponse unique + API Resources systématiques
[ ] Policies via le Gate, sur tous les domaines
[ ] Image Docker versionnée, migrations sorties du boot
[ ] Vitest sur le front
```

### Phase 3 — Réécrire les domaines fragiles · ~15 jours

Dans cet ordre, du plus risqué au moins risqué :

```
[ ] Facturation (avoirs D5, quantité, RESTRICT, adresse client, export comptable)
[ ] Authentification (D8)
[ ] Panier / tarification (avec tests du cumul cross-galerie)
[ ] Sessions scolaires (avec tests, contacts persistés D7)
[ ] Réservations (réparer les 3 routes, tester)
```

### Phase 4 — Migrer · ~13 à 16 jours

Le plan complet est en §10. Phases 0 et 1 déjà faites à ce stade.

### Phase 5 — Ajouter de la valeur · ~8 jours

```
[ ] Portfolio administrable (D6)
[ ] Bons cadeaux outillés (D4)
[ ] Webhooks de délivrabilité Brevo
[ ] Export comptable (CSV/FEC)
[ ] Relance de panier abandonné
```

---

## 11.7. Les invariants à ne pas perdre

**Si un seul tableau de cet audit devait survivre, c'est celui-ci.** Chaque ligne est un garde-fou
écrit après un incident réel, documenté par un commentaire du code ou par `docs/`. Les réapprendre par
l'échec coûterait cher — en argent pour la photographe, en confiance pour ses clientes.

| # | Invariant | Ce qui arrive sans lui | § |
|---|---|---|---|
| 1 | Ne **jamais** croire le statut de paiement annoncé par le client ; rappeler `getCheckout()` | Injection d'un faux `PAID` | §4.4 |
| 2 | `return_url` SumUp doit pointer le **backend**, jamais le SPA | Le SPA avale le webhook POST, commandes bloquées en `pending` | §4.4 |
| 3 | Le webhook renvoie **503** sur erreur transitoire (pour bénéficier des retries), **200** sur erreur définitive | Retries perdus ou spam infini | §4.4 |
| 4 | Drapeau `$justCompleted` avant les effets de bord de `completeOrder` | Double email quand webhook et polling se croisent | §7.5 |
| 5 | `where('status','pending')` sur l'`update` d'expiration | Un paiement valide écrasé en `expired` | §7.3 |
| 6 | Expirer les anciens checkouts avant d'en créer un nouveau | Un widget figé sur un ancien prix reste payable | §7.3 |
| 7 | `completeFreeOrder` refuse tout total > 0 | Court-circuit d'un vrai paiement | §7.5 |
| 8 | Revalider le code promo sous `lockForUpdate` **dans** la transaction | Dépassement de quota par course | §7.11 |
| 9 | Le sous-total multiplie par la quantité | **Sous-facturation SumUp** | §7.2 |
| 10 | Un seul décodage d'image pour dériver preview + vignette | Pic mémoire ×15 (260 Mo au lieu de 17) | §5.5 |
| 11 | Réduire **avant** de tourner selon l'EXIF | GD alloue 3× le full-size → OOM sur les portraits | §5.5 |
| 12 | Cloner la vignette **avant** d'appliquer le filigrane | Filigrane illisible à 600 px | §5.4 |
| 13 | Upload de l'original en flux (`putFileAs`), jamais `file_get_contents` | 20-35 Mo en mémoire PHP | §5.5 |
| 14 | `withEvents(discover: false)` + listeners câblés à la main | **Tous les emails partent en double** | §3.2.1 |
| 15 | `ProcessPhotoJob` : sur échec, ne pas nettoyer, ne pas marquer ; laisser `failed()` le faire | Reprise impossible | §5.2.2 |
| 16 | Heartbeat du scheduler par `Schedule::command` (process forké) | Heartbeat vert alors que les tâches forkées échouent | §8.5 |
| 17 | Heartbeat du worker sur `Looping`, pas `JobProcessed` | Fausse panne dès que la file est calme | §8.7 |
| 18 | `withoutOverlapping` avec expiration **courte et explicite** | 24 h de blocage silencieux ; et `Cache::has(mutexName())` ne détecte rien | §8.11 |
| 19 | Chronométrer l'ouverture de connexion **séparément** du `SELECT` | Fausse alerte « base lente » à 1490 ms | §8.11 |
| 20 | Ne pas mettre le résultat des sondes en cache | Un état de santé caché ment au pire moment | §8.6 |
| 21 | `unicodeEnabled: false` + suppression des accents | 3 SMS facturés au lieu d'1 | §4.5 |
| 22 | Dual endpoint MinIO (interne pour l'API, public pour les URL signées) | 100-300 ms par PUT à travers le tunnel | §4.3 |
| 23 | Dénormaliser `photo_title` / `gallery_title` sur `order_items` | Facture illisible après suppression de la photo | §3bis.7 |
| 24 | Un seul panier actif par utilisateur (invariant applicatif) | Le client paie un montant ≠ de celui affiché | §2.5 |
| 25 | Chemin déterministe des vignettes clean (même `basename`) | Doublons à chaque backfill | §5.6 |

---

## 11.8. Actions immédiates, indépendamment de toute décision

Si une seule chose devait être faite après la lecture de cet audit :

> **Mettre en place une sauvegarde vérifiée de la base et de MinIO, puis repointer `api/.env` local
> sur une base locale.**

Aujourd'hui, un seul développeur travaille avec un environnement branché sur la base et le mailer de
**production**, sans aucune sauvegarde. Un `migrate:fresh` réflexe, un seeder, un `DELETE` mal ciblé
détruirait des données irrécupérables — les galeries de clientes, les commandes payées, les factures.
C'est la raison pour laquelle **cet audit entier a été réalisé sans exécuter une seule requête**.

Vient ensuite le reste de la phase 0 : environ 3 jours qui corrigent 8 des 12 constats critiques.

---

## 11.9. Estimation globale

| Phase | Charge | Cumul |
|---|---|---|
| 0 — Sécuriser l'existant | 3 j | 3 j |
| 1 — Relever et décider | 2,5 j | 5,5 j |
| 2 — Nettoyer et fonder | 8 j | 13,5 j |
| 3 — Réécrire les domaines fragiles | 15 j | 28,5 j |
| 4 — Migrer | 13-16 j | 42-45 j |
| 5 — Ajouter de la valeur | 8 j | **50-53 j** |

**Aléas principaux** (par ordre d'impact) :

1. **le volume de photos à chemin non résolu** (§10.8) — peut doubler la charge de la phase 4 ;
2. **le résultat du test D1** — si l'hypothèse `STRINGIFY_FETCHES` échoue, +2 à 5 j ;
3. **les décisions produit** (D4, D5, D6) — de 0,5 j à 10 j chacune selon l'arbitrage ;
4. **la disponibilité de la photographe** pour les 5 décisions qui la concernent.

**Jalon utile** : à l'issue de la **phase 2** (13,5 jours), le projet a perdu sa dette structurelle
majeure et gagné ses fondations, sans qu'aucune fonctionnalité n'ait changé. C'est un bon point de
contrôle pour décider de continuer ou de s'arrêter là.

---

## 11.10. Ce que cet audit ne peut pas dire

Par honnêteté méthodologique, et parce que ces limites doivent être levées avant d'engager les
chiffres ci-dessus.

| Limite | Comment la lever |
|---|---|
| **Aucune donnée réelle n'a été consultée.** Toutes les volumétries, tous les taux, toutes les anomalies effectives sont des hypothèses | Phase 1 (§10.3) |
| **Le schéma est reconstruit depuis les migrations**, pas relevé en base — et les deux divergent (§3bis.14) | Requêtes §3bis.14-A sur un dump |
| **Aucun test d'intrusion.** Les vulnérabilités sont déduites du code | Vérification dynamique sur un environnement de test |
| **Aucune mesure de performance.** Les budgets de la §5.13 sont des estimations à partir des constantes | Instrumentation ou test de charge |
| **L'historique Git est inaccessible depuis cette copie** (1 commit squashé) | Travailler depuis le dépôt d'origine pour l'archéologie |
| **Le besoin métier réel n'a pas été recueilli.** Je déduis les intentions du code | Entretien avec la photographe — indispensable pour D3, D4, D5, D6, D7 |
| **La configuration Render n'est pas dans le dépôt** | La récupérer et la versionner (§4.7) |
| **Trois bugs sont « à confirmer par un test »** : `logout()` (§6.2.2), `authorize()` (§3.5.4), `reorder` (§6.9) | Trois tests unitaires, 30 minutes |

---

## 11.11. Mot de la fin

Un audit produit naturellement une liste de problèmes, et 56 items de dette peuvent donner une
impression sévère. Elle serait fausse.

Ce codebase porte des choix d'ingénierie que l'on ne trouve pas souvent à cette échelle : un invariant
de sécurité de paiement correctement posé, des optimisations mémoire mesurées et expliquées, un module
RGPD conforme et testé, une supervision conçue autour de son propre angle mort. Et surtout, une
pratique rare : **des commentaires qui expliquent des décisions et des incidents, pas du code**. Les
25 invariants de la §11.7 n'ont pas été déduits par analyse — ils étaient écrits dans le code, par
quelqu'un qui avait compris pourquoi ça avait cassé.

Les défauts relevés ne sont pas des défauts de compétence. Ce sont les défauts d'un projet
**inégalement fini** : une personne seule, neuf mois, une mise en production réelle, et des priorités
dictées par ce qui tombait en panne. Les zones qui n'ont jamais fait de bruit n'ont jamais été
durcies — et c'est exactement là que cet audit a trouvé six bugs, tous dans des domaines sans test.

La refonte n'a donc pas à repartir de zéro. Elle a à **finir ce qui n'a pas été fini**, en gardant ce
qui a été appris.

---

*Fin du document d'audit. Dernière mise à jour : 2026-08-22 — 11 sections rédigées.*
# 12. Seconde passe — ce que la première a manqué

> **Objet** : relecture critique de l'audit, section par section, en instruisant les zones qui
> n'avaient été traitées **que par échantillonnage**. Les 11 sections précédentes ont été écrites en
> lisant ~70 % du code applicatif ; cette passe couvre le reste : les 6 contrôleurs jamais ouverts,
> `CartService` et `SchoolSessionExportService` intégralement, la commande `storage:clean-orphans`, les
> FormRequests restants, le `Makefile`, le compose de développement, les gabarits d'email et les
> utilitaires front.
>
> **Résultat** : **3 constats critiques nouveaux**, dont deux destructeurs de données, plus 12 constats
> mineurs et 8 points positifs qui avaient été manqués.

## 12.1. 🔴 NOUVEAU — `make fresh` détruit la base de production

C'est la découverte la plus grave de cette seconde passe, et elle transforme le risque R2 (§9.9) d'un
danger théorique en **une commande unique, documentée dans l'aide**.

`Makefile` :

```makefile
seed: ## Run database seeders
	docker-compose exec laravel php artisan db:seed

fresh: ## Fresh migrate with seeders
	docker-compose exec laravel php artisan migrate:fresh --seed
```

**Chaîne complète** :

1. `docker-compose.yml` (dev) alimente le conteneur `laravel` avec `DB_HOST=${DB_HOST_DOCKER}`, lu
   depuis le `.env` **racine** ;
2. le compose documente lui-même que la base applicative de développement est **Supabase**, pas le
   conteneur local : *« Isolé derrière le profil `test` : ne démarre PAS avec `docker compose up`
   (l'app applicative utilise Supabase, pas ce conteneur). »* — il n'existe donc **aucune option de
   base locale** en dehors de la base de test ;
3. une mémoire projet établit que `api/.env` local pointe la base et le mailer de **production**.

**Donc `make fresh` exécute `migrate:fresh --seed` sur la base de production.** Cela :

- **`DROP` toutes les tables** — galeries, photos, commandes payées, factures, journal d'audit RGPD ;
- puis les recrée vides ;
- puis exécute `DatabaseSeeder`, qui crée le compte **`admin@oceanetorres.fr` / `admin123`** (§6.14).

Aucun garde-fou : pas de vérification d'`APP_ENV`, pas de confirmation interactive, pas de
`--pretend`. Et la cible est listée dans `make help`, **deux lignes au-dessus de `make test`**.

> **Correctifs, par ordre d'urgence** :
> 1. **Supprimer les cibles `fresh` et `seed`** du Makefile, ou les faire échouer si
>    `APP_ENV !== 'local'` **et** si l'hôte de base contient le domaine de production ;
> 2. ajouter un garde dans `DatabaseSeeder::run()` : `abort_if(app()->environment('production'))` ;
> 3. **fournir une base de développement locale** — c'est le correctif de fond. Le conteneur
>    `postgres-test` existe déjà : il suffit d'un second service `postgres-dev` sans profil, et de
>    pointer le `.env` racine dessus.
>
> Ce constat rejoint et **aggrave** l'action n°2 de la §9.11 (« repointer `api/.env` local ») : le
> problème n'est pas seulement le fichier `.env`, c'est qu'**il n'existe pas d'alternative locale**.

---

## 12.2. 🔴 NOUVEAU — `storage:clean-orphans --delete` supprimerait des fichiers référencés

La §5.14 recommandait de **planifier** cette commande. **Cette recommandation était dangereuse** et
doit être amendée.

`CleanOrphanFiles` construit sa liste de chemins référencés à partir de **six colonnes** :

```php
Photo::query()->select([
    'id', 'file_path', 'file_path_hd', 'file_path_preview',
    'file_path_thumbnail', 'file_path_web', 'file_path_watermark',
])
```

**Trois sources de référence sont absentes** :

| Absent | Conséquence |
|---|---|
| **`file_path_thumbnail_clean`** | La colonne a été ajoutée le **2026-07-08**, après l'écriture de la commande. **Toutes les vignettes sans filigrane** (`{gallery_id}/thumbnail-clean/…`) sont donc classées « orphelines » — dans toutes les galeries existantes |
| **`metadata->'storage_path'`** | Pour les photos dont `file_path_hd` est nul, l'original n'est référencé que par le JSON → classé orphelin |
| **`metadata->'supabase_path'`** | Idem, pour les photos héritées de l'ère Supabase Storage → **l'original HD est classé orphelin** |

Avec `--delete`, la commande supprimerait donc :

- **toutes les vignettes clean** — récupérable par `photos:generate-clean-thumbnails`, mais entre-temps
  `file_path_thumbnail_clean` pointe un fichier absent → **images cassées dans toutes les galeries
  téléchargeables** ;
- **les originaux HD des photos d'ère Supabase** — **irrécupérable**. Et ce sont précisément les photos
  que des clientes ont pu acheter : `order_items` les référence, la facture les mentionne, et le
  fichier n'existe plus.

**Ce qui limite les dégâts aujourd'hui** (et ce qui est bien fait) :

| Garde-fou | État |
|---|---|
| Dry-run par défaut (`--delete` explicite) | ✅ |
| `confirm()` interactif avant suppression | ✅ — donc **impossible depuis un cron** |
| Préfixes `invoices/` et `school-sessions/` ignorés | ✅ — le code sait que le bucket est multi-usage |
| Aucun test | ❌ `grep -rln "CleanOrphan" tests/` → rien |

> **Amendement à la §5.14** : ne **pas** planifier cette commande. La corriger d'abord :
> ```php
> $referencedPaths->push($photo->file_path_thumbnail_clean);
> $referencedPaths->push($photo->metadata['storage_path']  ?? null);
> $referencedPaths->push($photo->metadata['supabase_path'] ?? null);
> ```
> (le `select()` doit aussi inclure `file_path_thumbnail_clean` et `metadata`), écrire un test, puis la
> planifier **en dry-run** avec rapport — jamais en `--delete` automatique.

---

## 12.3. 🔴 NOUVEAU — `DELETE /admin/clients/{id}` : anonymise des commandes payées, puis échoue

`Admin/ClientController::destroy()` — contrôleur jamais ouvert lors de la première passe. La méthode
est appelée par le front (`clientApi.deleteClient`).

```php
public function destroy(Client $client): JsonResponse
{
    $email = $client->email;

    $client->reservations()->update([...]);                    // 1
    Order::where('guest_email', $email)->update([              // 2
        'guest_email' => null, 'guest_first_name' => 'Supprimé', 'guest_last_name' => null,
    ]);
    ContactMessage::where('email', $email)->update([           // 3
        'name' => 'Supprimé', 'email' => null, 'phone' => null,
    ]);
    // …
    $client->delete();                                          // 4
}
```

**Quatre défauts cumulés** :

1. **Elle anonymise les commandes PAYÉES.** Le filtre est `where('guest_email', $email)` — sans aucune
   condition sur le statut. Cela viole **directement** la rétention comptable (§10.5-C1) **et** la
   règle « CONSERVER » que `PersonalDataEraser` applique par ailleurs scrupuleusement. Après passage,
   la facture d'une commande payée n'a plus ni email ni nom de client — pour un document à conserver
   dix ans.

2. **Elle plante en cours de route.** `contact_messages.email` est déclarée
   `$table->string('email');` — donc **NOT NULL**, et aucune migration ultérieure ne la rend nullable
   (vérifié). L'`update(['email' => null])` lève une violation de contrainte → **HTTP 500**.

3. **Il n'y a pas de transaction.** Les étapes 1 et 2 sont donc **committées** avant l'échec de
   l'étape 3. Résultat : les réservations et **les commandes payées sont anonymisées de façon
   irréversible**, le client existe toujours, et l'admin voit une erreur générique. État partiel,
   incohérent, sans retour possible.

4. **Aucune trace d'audit.** Contrairement au chemin `PrivacyController` → `PrivacyAuditLogger`, cette
   suppression n'écrit **rien** dans `privacy_audit_logs`. Et aucune confirmation n'est demandée —
   là où `ErasePrivacyRequest` exige de **retaper la valeur ciblée** (`'confirm' => ['required',
   'same:value']`).

**Le fond du problème** : **il existe deux implémentations concurrentes de l'effacement RGPD**, écrites
à des époques différentes, avec des politiques opposées.

| | `Admin/PrivacyController` → `PersonalDataEraser` | `Admin/ClientController::destroy` |
|---|---|---|
| Transaction | ✅ | ❌ |
| Commandes / factures | **CONSERVÉES** (rétention légale) | **anonymisées** |
| Confirmation | saisie de la valeur exacte | aucune |
| Journal d'audit | ✅ | ❌ |
| Anonymise le `user` | ✅ | ❌ |
| Fichiers MinIO | PDF de bons cadeaux supprimés | ❌ |
| Fonctionne | ✅ | ❌ 500 dès qu'il existe un message de contact |

> **Correctif : supprimer `ClientController::destroy()` et renvoyer l'admin vers l'écran RGPD.** Le
> module `Privacy` fait déjà le travail, correctement. Deux chemins d'effacement aux politiques
> contradictoires est un risque de conformité en soi.
>
> **À vérifier en base** : cette méthode a-t-elle déjà tourné ? Requête :
> ```sql
> SELECT count(*) FROM orders
> WHERE guest_first_name = 'Supprimé' AND status = 'paid';
> ```
> Un résultat non nul signifie que des commandes payées ont déjà perdu l'identité de leur client.
> **À ajouter à la phase 1 de la §10.3.**

---

## 12.4. 🟠 NOUVEAU — L'export scolaire peut omettre silencieusement des photos achetées

`SchoolSessionExportService:87` :

```php
$sourcePath = $photo->file_path_hd ?? $photo->file_path;
```

Ce raccourci **contourne la cascade de résolution** `Photo::resolved_storage_path`
(`file_path_hd → metadata->storage_path → metadata->supabase_path → file_path`, §5.6).

J'ai vérifié la cohérence de l'ensemble du code sur ce point :

| Résolution | Occurrences |
|---|---|
| Cascade complète (`resolved_storage_path`) | **14 sites** — proxy d'images, téléchargements, ZIP de galerie, jobs, commandes de backfill |
| **Raccourci `file_path_hd ?? file_path`** | **2 sites** : `SchoolSessionExportService:87` et `Photo::getDownloadUrlAttribute()` |

**Conséquence pour l'export scolaire** : pour une photo d'ère Supabase, `getFileContent()` renvoie
`null`, le code émet un `Log::warning`, incrémente `processed_items` et **passe à la suivante**. Le ZIP
de production est généré sans erreur, avec une photo en moins — et **le parent l'a payée**.

`Photo::getDownloadUrlAttribute()` porte le même raccourci mais **n'a aucun appelant** : c'est du code
mort avec un bug latent (à ajouter à l'inventaire de la §9.4).

> **Correctif** : `$sourcePath = $photo->resolved_storage_path;` — une ligne. Et ajouter un compteur
> d'omissions au rapport d'export, pour que le silence devienne visible.

---

## 12.5. 🟠 NOUVEAU — L'anti-bruteforce des codes promo est un déni de service

`CartController::applyGiftCode` implémente une **seconde couche** d'anti-bruteforce, en plus du
middleware `throttle:gift-code` — je l'avais manquée en §6.7. Elle est bien conçue dans son principe :
seuls les **échecs** comptent (`RateLimiter::hit` sur code inexistant ou invalide,
`RateLimiter::clear` sur succès), 10 échecs, fenêtre de 600 s.

**Mais la clé est `'gift-code-failures:'.sha1($request->ip())`** — et `$request->ip()` renvoie
`172.19.0.1` pour **tout le trafic** (§6.7-V8).

**Conséquence** : les 10 échecs sont comptés **globalement**. Dix saisies erronées par n'importe qui —
ou une seule personne qui tâtonne — **bloquent la fonction « code promo » pour tous les clients**
pendant 10 minutes, avec le message « Trop de tentatives ». Et comme `RateLimiter::clear()` n'est
appelé qu'en cas de succès, un client légitime qui n'a pas de code valide ne peut pas débloquer la
situation.

> C'est une **amplification de V8 que je n'avais pas identifiée** : le problème d'IP ne rend pas
> seulement les protections inopérantes, il transforme deux d'entre elles (celle-ci et
> `throttle:gift-code`) en **mécanisme de déni de service contre les clients légitimes**. Cela renforce
> encore la priorité du correctif `CF-Connecting-IP`.

---

## 12.6. Douze constats mineurs nouveaux

| # | Constat | Cot. | Où |
|---|---|---|---|
| 1 | **`CartService::addItem` n'écrête pas le total.** `$existing->increment('quantity', $quantity)` applique le plafond de 50 à l'incrément, pas au cumul. Appeler `POST /cart/add` dix fois avec `quantity=50` donne 500 exemplaires. `setItemQuantity` écrête bien, lui. Combiné à l'absence de rate limit sur `/cart/add` (§6.7) | 🟡 | `CartService:118` |
| 2 | **`UpdateUserRequest` autorise le changement de `role` sans garde-fou.** `destroy()` protège le dernier administrateur (`count() <= 1`), mais `update()` ne le fait pas : un admin peut se rétrograder en `client` et **verrouiller l'accès à l'administration** | 🟡 | `UpdateUserRequest:24` |
| 3 | **`StoreUserRequest` : mot de passe `min:8` seulement**, sans `confirmed` ni `PasswordRule::defaults()`. Les comptes créés par l'admin ont donc une règle **encore plus faible** que ceux créés par inscription (§6.2.3) | 🟡 | `StoreUserRequest:22` |
| 4 | **`UpdateProfileRequest` autorise le changement d'email** sans confirmation par mot de passe ni revérification de la nouvelle adresse. Endpoint mort aujourd'hui (§2.7) — mais si ranimé, c'est un vecteur de prise de contrôle après vol de session | 🟡 | `UpdateProfileRequest:21` |
| 5 | **`AccountController::dashboard` expose `download_token`** en clair dans la réponse, pour chaque commande. Destinataire légitime, mais le secret circule dans un payload de plus, mis en cache par le navigateur | 🟡 | `AccountController:64` |
| 6 | **`BookingRequestController` écrase `gdpr_consent_at`** sur un client existant qui avait déjà consenti à une autre date → **perte de la date de consentement d'origine**, qui est précisément la preuve à conserver | 🟡 | `BookingRequestController:38` |
| 7 | **`Photo::getDownloadUrlAttribute()`** : raccourci de résolution + aucun appelant → code mort à bug latent | 🟡 | `Photo:232` |
| 8 | **`session_id` du panier accepté en paramètre de requête** (`$request->input('session_id')`) en plus de l'en-tête `X-Cart-Session`. Le secret peut donc fuiter dans les logs Nginx et les référents. C'est bien un UUID généré côté serveur (non devinable) ✅, mais il donne accès au panier **et au `guest_email`** | 🟡 | 12 sites |
| 9 | **`PersonalDataEraser` : compteur de PDF faussé.** `$summary['gift_card_pdfs_deleted']++` est incrémenté même quand le fichier n'existait pas — le rapport d'effacement surestime le nombre de fichiers supprimés | 🟢 | `PersonalDataEraser:216` |
| 10 | **Le repli `productTypes` du store panier omet `print_scolaire`** — utilisé seulement si le serveur ne renvoie pas la liste, donc sans impact constaté | 🟢 | `stores/cart.ts:34` |
| 11 | **Le `Makefile` annonce « MailHog »** alors que le projet utilise Mailpit — dérive documentaire de plus (§9.8) | 🟢 | `Makefile:42` |
| 12 | **`/gallery` figure dans les routes pré-rendues** alors que la zone galerie est marquée `noindex` côté routeur. Sans impact (c'est le formulaire de saisie du code, pas une galerie), mais incohérent | 🟢 | `prerender.js:27` |

---

## 12.7. Huit points positifs manqués en première passe

Par souci d'équilibre — la première passe a sous-estimé la qualité de plusieurs zones.

| # | Point | Où |
|---|---|---|
| 1 | **Sentry front nettoie activement les données sensibles** : `beforeSend` supprime `event.user` **et retire la query string des URL** (`stripQuery`), `beforeBreadcrumb` supprime les breadcrumbs de console et nettoie les URL. C'est important : les tokens de galerie et de téléchargement circulent en query string — sans ce nettoyage, ils partiraient chez Sentry | `utils/monitoring.ts` |
| 2 | **`ErasePrivacyRequest` exige une confirmation retapée** : `'confirm' => ['required', 'same:value']`. L'admin doit ressaisir exactement l'email ou le numéro ciblé. Excellent garde-fou sur une action irréversible | `ErasePrivacyRequest:24` |
| 3 | **`UserController::destroy` protège le dernier administrateur** — refuse la suppression si `count(admin) <= 1` | `UserController:78` |
| 4 | **`GiftCodeController::destroy` impose une suppression en deux temps** : un code actif ne peut pas être supprimé, il faut le désactiver d'abord. Conforme à la spécification de `docs/feature-gift-code-promo.md` | `GiftCodeController` |
| 5 | **`CartService::addItem` est bien gardé** : vérifie `is_purchasable`, **refuse si la session scolaire est clôturée**, refuse si le type de produit n'est pas disponible pour la galerie, écrête la quantité | `CartService:85` |
| 6 | **`CartController::resolveUser` corrige une asymétrie subtile**, documentée : *« Sur ces routes publiques, `$request->user()` (guard `web`) ignore le Bearer token alors que le checkout résout via sanctum. Sans cette symétrie, un client connecté manipule un panier session que le checkout fusionne ailleurs → totaux divergents. »* | `CartController:42` |
| 7 | **`CleanOrphanFiles` sait que le bucket est multi-usage** : les préfixes `invoices/` et `school-sessions/` sont explicitement exclus, avec un commentaire. Dry-run par défaut et confirmation interactive | `CleanOrphanFiles:78` |
| 8 | **Les tests sont de bonne qualité, pas des tests d'alibi.** `SumUpWebhookTest` vérifie le **contrat** du webhook : `Http::assertNothingSent()` quand SumUp ne doit pas être appelé, `503` sur erreur transitoire, idempotence sur commande déjà payée, statut inchangé sur `PENDING`. `Http::fake`/`Mail::fake`/`Storage::fake` sont utilisés dans 10 fichiers | `tests/Feature/Payment/` |

---

## 12.8. Corrections apportées aux sections précédentes

| Section | Correction |
|---|---|
| **§5.14** | ⚠️ La recommandation « planifier `storage:clean-orphans` en dry-run hebdomadaire » était **dangereuse en l'état** : la liste de références est incomplète (§12.2). À corriger **avant** toute planification, et jamais en `--delete` automatique |
| **§9.11 action n°15** | Idem — l'action « planifier `storage:clean-orphans` » devient « **corriger** `storage:clean-orphans`, écrire un test, **puis** le planifier en dry-run » |
| **§9.7** | « Couverture de tests : **jamais calculée** » → une cible `make test-coverage` existe (`php artisan test --coverage`) ; c'est la **CI** qui utilise `coverage: none`. La capacité existe, elle n'est pas exploitée |
| **§2.12 / §6.12** | Le module RGPD n'est pas la seule implémentation de l'effacement : `ClientController::destroy` en est une seconde, **aux politiques opposées** (§12.3) |
| **§6.7** | Il existe une **seconde couche** d'anti-bruteforce sur les codes promo, applicative — et elle est convertie en déni de service par le problème d'IP (§12.5) |
| **§9.4** | Ajouter au code mort : `Photo::getDownloadUrlAttribute()`, `Cart::order()` (relation cassée, §10.0), et `ClientController::destroy()` si le correctif retenu est la suppression |
| **§9.9-R2** | Le risque n'est pas seulement « `api/.env` pointe la prod » : **il n'existe aucune base de développement locale** (le compose le documente), et `make fresh` en fait une commande à un mot (§12.1) |
| **§10.3** | Ajouter au relevé : `SELECT count(*) FROM orders WHERE guest_first_name = 'Supprimé' AND status = 'paid'` — trace d'un passage de `ClientController::destroy` |

---

## 12.9. Registre de dette mis à jour

Les 56 items de la §9.3 deviennent **59**, avec trois nouveaux critiques :

| # | Dette | Cot. | Avant ? |
|---|---|---|---|
| **57** | **`make fresh` / `make seed` exécutés sur la production** ; aucune base de dev locale | 🔴 | **Oui** |
| **58** | **`storage:clean-orphans` supprimerait les vignettes clean et les originaux d'ère Supabase** | 🔴 | **Oui** |
| **59** | **`ClientController::destroy` anonymise des commandes payées puis échoue, sans transaction ni audit** | 🔴 | **Oui** |
| 60 | Export scolaire : omission silencieuse de photos achetées (raccourci de chemin) | 🟠 | Oui |
| 61 | Anti-bruteforce des codes promo → déni de service (amplification de V8) | 🟠 | Oui |

**Nouveau total : 61 items — 15 critiques, 20 élevés.**

### Les 15 actions les plus rentables, révisées

Insertions dans le classement de la §9.11 :

| Rang | Action | Effort |
|---|---|---|
| **0** | **Supprimer `make fresh` et `make seed`** + garde `abort_if(production)` dans `DatabaseSeeder` | **10 min** |
| 1 | Sauvegardes + restauration testée | 1 j |
| **1bis** | **Créer un service `postgres-dev`** dans le compose et y pointer le `.env` racine | **2 h** |
| 2 | Repointer `api/.env` local | 2 h |
| **2bis** | **Corriger la liste de références de `storage:clean-orphans`** (3 lignes) + test | **1 h** |
| **2ter** | **Supprimer `ClientController::destroy()`** et renvoyer vers l'écran RGPD | **30 min** |
| 3 | Vérifier / supprimer le compte `admin123` | 5 min |
| 4 | Supprimer `downloadFile()` | 15 min |
| 5 | Propager `CF-Connecting-IP` | 1 h |
| … | *(suite inchangée)* | |
| **8bis** | **`SchoolSessionExportService` : `resolved_storage_path`** (1 ligne) | **10 min** |

> **L'action n°0 coûte dix minutes et supprime le seul risque de destruction totale du projet.**
> Elle passe devant les sauvegardes, parce qu'elle est plus rapide à faire que la phrase qui la décrit.

---

## 12.10. Bilan de la seconde passe

**Ce que la première passe a bien couvert** : l'architecture, le schéma, le tunnel de paiement, le
pipeline image, la supervision, la sécurité de la couche transport. Les constats de ces domaines
tiennent.

**Ce qu'elle a manqué, et pourquoi** : les six contrôleurs jamais ouverts
(`AccountController`, `BookingRequestController`, `ContactController`, `Admin/ClientController`,
`Admin/UserController`, `Admin/NotificationController`), la commande `storage:clean-orphans`, le
`Makefile` et le compose de développement. J'avais priorisé les fichiers volumineux et les chemins
critiques évidents — or **deux des trois constats critiques de cette passe sont dans des fichiers
courts et peu spectaculaires** : un `Makefile` de 140 lignes et une méthode de contrôleur de 40
lignes.

**La leçon méthodologique** : dans ce projet, la gravité ne corrèle pas avec la taille. `downloadFile()`
faisait 13 lignes, `make fresh` en fait 2, `ClientController::destroy` en fait 40. Les gros fichiers
(`OrderService`, `ImageProcessingService`, `SchoolSessionController`) sont ceux qui ont été relus et
durcis ; **les petits fichiers utilitaires sont ceux que personne ne relit.**

**Ce qui reste non instruit après deux passes**, et devrait l'être avant la refonte :

| Zone | Volume | Pourquoi ça compte |
|---|---|---|
| Les 13 vues d'administration | 7 072 lignes | Boilerplate répété ; bugs d'affichage possibles (ex. celui de §7.6 y serait visible) |
| `Checkout.vue`, `OrderConfirmation.vue` | 1 407 lignes | Le tunnel de paiement côté navigateur — polling, watchdog, gestion d'erreur |
| `EventGalleries.vue`, `SchoolSessionDetail.vue` | 2 214 lignes | Les deux écrans les plus complexes |
| `MasonryGallery.vue`, `Lightbox.vue` | 1 082 lignes | Affichage des galeries, chargement des images |
| `PersonalDataLocator` | 443 lignes | Le résolveur RGPD — une omission de table y serait une non-conformité |
| Les 45 FormRequests non lus | ~1 200 lignes | Bornes de validation |
| Les 34 fichiers de test | 3 803 lignes | Ce qu'ils **n'assertent pas** |

> **Recommandation méthodologique pour la suite** : plutôt qu'une troisième passe manuelle, deux
> outils donneraient plus en moins de temps — **PHPStan/Larastan au niveau 5+** (qui aurait trouvé
> `$this->authorize()` sans trait, `Cart::order()` sans colonne, et les accès à des colonnes
> inexistantes) et **une mesure de couverture** (`make test-coverage` existe déjà) pour confirmer la
> carte des zones non testées de la §9.6.

---

*Fin de la seconde passe. Document d'audit : 12 sections.*
# 13. Troisième passe — le front (13 vues admin + public)

> **Périmètre** : les 26 629 lignes du front, restées largement non instruites après les deux
> premières passes. 13 vues d'administration (7 072 l.), les vues publiques critiques
> (`Checkout.vue` 836 l., `OrderConfirmation.vue` 571 l.), les composants d'affichage
> (`MasonryGallery` 631 l., `Lightbox` 451 l., `PhotosManager` 754 l.), le `DataTable` partagé, les
> stores et les utilitaires.
>
> **Résultat** : **1 vulnérabilité** (qui invalide une affirmation des §6 et §9), **3 constats élevés**
> dont deux d'interface trompeuse, 2 constats d'accessibilité, 8 constats mineurs — et la confirmation
> que les zones critiques du tunnel de paiement sont, là aussi, les mieux écrites.

## 13.0. ⚠️ Correction — `v-html` existe

| Affirmation antérieure | Réalité |
|---|---|
| §6.8 : « `v-html` → ✅ **aucune occurrence** dans `web/src` » | **Faux.** Il y a **une** occurrence : `views/OrderConfirmation.vue:42` |
| §9.7 : « `v-html` \| **0** » | Idem |

C'était une recherche insuffisamment précise de ma part. L'occurrence unique est un sink XSS — §13.1.

---

## 13.1. 🔴 NOUVEAU — Injection HTML dans la page de confirmation de commande

`views/OrderConfirmation.vue` :

```vue
<p class="text-sm text-gray-600 mt-1" v-html="confirmationMessage"></p>
```

```js
const confirmationMessage = computed(() => {
    if (!order.value) return ''
    switch (orderType.value) {
        case 'digital':
            return `Un email avec votre lien de téléchargement a été envoyé à
                    <strong>${order.value.customer_email}</strong>.<br>Merci de conserver cet email.`
        // … 3 autres branches, toutes construites de la même façon
    }
})
```

**`customer_email` est une donnée fournie par l'utilisateur**, interpolée sans échappement dans une
chaîne HTML rendue par `v-html`. Sa provenance (`OrderResource`) est
`$this->user?->email ?? $this->guest_email ?? ''`, c'est-à-dire :

- soit `orders.guest_email`, alimenté par `CreateCheckoutRequest` (`['required', 'email']`) ;
- soit `users.email`, alimenté par `RegisterRequest` (`['required','string','email','max:255','unique:users']`).

**La règle `email` de Laravel n'est pas un assainisseur.** Elle délègue à `egulias/email-validator` en
validation RFC, laquelle accepte les **parties locales entre guillemets** — où `<`, `>`, `=`, `/` et
l'espace sont des caractères licites. Une adresse de la forme
`"<img src=x onerror=…>"@exemple.com` est syntaxiquement valide au sens RFC 5322 et franchirait donc
la validation. *(À confirmer par un test — mais le principe ne dépend pas du résultat : interpoler une
donnée utilisateur dans du `v-html` est non sûr indépendamment de la validation en amont.)*

**Portée réaliste** : dans le flux normal, c'est un **self-XSS** — la victime est l'auteur de la
commande, qui consulte sa propre page de confirmation. Deux facteurs élargissent néanmoins la surface :

1. `GET /orders/{order}` est accessible à **quiconque** pendant 30 minutes après création (§6.5-V6) —
   donc un lien de confirmation forgé et partagé dans cette fenêtre exécute le script chez un tiers ;
2. **il n'y a aucune CSP** (§6.8), et le token Bearer vit 30 jours en `localStorage` — donc une XSS
   réussie donne un accès durable au compte.

> **Correctif : supprimer le `v-html`.** Il ne sert qu'à produire un `<strong>` et un `<br>` — deux
> éléments qui se font en balisage de template :
> ```vue
> <p class="text-sm text-gray-600 mt-1">
>     {{ confirmationIntro }} <strong>{{ order.customer_email }}</strong>.<br>
>     Merci de conserver cet email.
> </p>
> ```
> Cinq minutes, et le sink disparaît. À placer en **priorité 1** de la §6.15.

---

## 13.2. 🟠 NOUVEAU — Le tableau de bord admin : six requêtes de 100 lignes pour des compteurs faux

`views/admin/Dashboard.vue:433` charge **six listes paginées** en parallèle :

```js
const [reservationsResponse, clientsResponse, galleriesResponse,
       eventsResponse, ordersResponse, giftCardsResponse] = await Promise.all([
    adminApi.getReservations(1, 100), adminApi.getClients(1, 100),
    adminApi.getGalleries(1, 100),    adminApi.getEventGalleries(1, 100),
    adminApi.getOrders(1, 100),       adminApi.getGiftCards(1, 100),
])
```

### Trois problèmes distincts

**1. Deux endpoints dédiés existent et ne sont jamais appelés.**
`dashboardApi.ts` expose `getDashboardStats()` → `GET /admin/dashboard/stats` et
`getRecentActivity()` → `GET /admin/dashboard`. `grep` sur la vue : **aucun appel**. Or
`Admin/DashboardController::stats()` fait exactement le bon travail — agrégats SQL conditionnels en
une requête (`selectRaw("COUNT(CASE WHEN status = 'pending' THEN 1 END)")`) et **cache de 5 minutes**.
Les deux routes rejoignent donc les 33 endpoints sans consommateur de la §2.16 — mais ce sont les
seules dont le remplacement est **moins bon que l'original**.

**2. Les compteurs sont faux au-delà de 100 lignes.** Seuls les `total` viennent de la pagination.
Tout le reste est calculé côté navigateur sur l'échantillon :

```js
stats.reservations.pending   = reservations.filter(r => r.status === 'pending').length
stats.reservations.thisMonth = reservations.filter(r => isThisMonth(r.created_at)).length
stats.orders.paid            = orders.filter(o => o.status === 'paid').length
stats.giftCards.active       = giftCards.filter(g => g.status === 'active').length
```

Les listes étant triées `latest()`, l'échantillon est celui des **100 plus récentes**. Dès la 101ᵉ
réservation, une demande `pending` ancienne **disparaît du compteur « en attente »** — c'est-à-dire
précisément l'indicateur sur lequel la photographe s'appuie pour savoir ce qu'il lui reste à traiter.

**3. Le coût de chargement est disproportionné.** `getOrders(1, 100)` déclenche
`Admin/OrderController::index`, qui charge `with('items.photo', 'user')` puis sérialise via
`OrderResource` → `OrderItemResource` → `whenLoaded('photo')` → `preview_url` **et**
`thumbnail_url`. Or ces deux accesseurs calculent chacun **une signature HMAC MinIO** (§5.7).

Pour 100 commandes de ~3 articles : **~600 signatures** calculées pour afficher un chiffre. Auxquelles
s'ajoutent `getGalleries(1, 100)` et `getEventGalleries(1, 100)` avec leurs `cover_photo`.

> **C'est l'écran le plus coûteux du back-office, et il est le premier ouvert à chaque session.**
> Correctif : appeler `getDashboardStats()` et `getRecentActivity()`. Le code serveur existe, est
> caché, et donne des chiffres justes. **Deux heures de travail pour supprimer 6 requêtes lourdes et
> corriger 7 compteurs.**

---

## 13.3. 🟠 NOUVEAU — Statistiques calculées sur la page courante

Même défaut, en pire, sur deux écrans où le symptôme est visible à l'œil nu.

`views/admin/GiftCards.vue` charge `getGiftCards(currentPage, 20)` puis :

```js
const stats = computed(() => {
    const active = giftCards.value.filter(g => g.status === 'active')
    return {
        active: active.length,
        totalValue: active.reduce((sum, g) => sum + g.remaining_amount, 0),
        used:    giftCards.value.filter(g => g.status === 'used').length,
        expired: giftCards.value.filter(g => g.status === 'expired').length,
    }
})
```

`giftCards.value` ne contient que **les 20 lignes de la page affichée**. Les cartouches « X bons
actifs » et surtout **« valeur totale en circulation »** décrivent donc la page, pas le portefeuille.
Et **le chiffre change quand on tourne la page** — ce qui rend le défaut immédiatement perceptible sans
l'expliquer.

`views/admin/PromoCodes.vue:344` a exactement le même schéma (`active`, `inactive`, `spent`).

> Sur `GiftCards`, la « valeur totale » est un indicateur **financier** : c'est l'encours de bons
> cadeaux non consommés, donc un engagement de la photographe envers ses clientes. L'afficher faux
> n'est pas un détail cosmétique.

---

## 13.4. 🟠 NOUVEAU — Le tri des tableaux est purement décoratif

`components/admin/ui/DataTable.vue` implémente une interaction de tri complète : classe
`cursor-pointer`, `hover:bg-gray-100`, **flèche qui pivote** selon `sortOrder`, et émission d'un
événement typé :

```js
sort: [key: string, order: 'asc' | 'desc']
```

**Huit colonnes sont déclarées `sortable: true`** dans 5 vues :
`SchoolSessions` (`event_date`, `created_at`), `PromoCodes` (`created_at`),
`GiftCards` (`expires_at`, `created_at`), `Clients` (`name`, `created_at`), `Orders` (`created_at`).

**Aucun parent n'écoute `@sort`** — `grep -rn "@sort"` sur tout `web/src` ne retourne rien. Et **aucun
endpoint n'accepte de paramètre de tri** (§3.7 : seul `per_page`, sur 4 endpoints).

> Résultat : l'utilisatrice clique sur « Date », la flèche s'inverse, **et rien ne bouge**. Un contrôle
> qui mime une fonctionnalité inexistante est plus dommageable qu'une absence de contrôle : il fait
> douter de la fiabilité de tout l'écran.
>
> Deux correctifs possibles : retirer `sortable: true` des 8 colonnes (10 minutes), ou brancher le tri
> — ce qui suppose d'ajouter `sort_by`/`sort_dir` aux endpoints concernés, et rejoint le chantier
> « convention de réponse API » de la §11.4.

---

## 13.5. 🟡 NOUVEAU — Accessibilité : les libellés ne sont pas associés aux champs

Mesure sur l'ensemble du front : **63 balises `<label>` sur 69 n'ont pas d'attribut `for`**.
Le détail sur les formulaires **publics** :

| Formulaire | `<label>` | avec `for` | `id=` |
|---|---|---|---|
| `Contact.vue` | 6 | **5** ✅ | 5 |
| `Checkout.vue` (livraison) | 4 | **0** | 1 |
| `BookingRequestModal.vue` | 7 | **0** | 0 |
| `AuthModal.vue` (connexion + inscription) | 9 | **0** | 0 |
| `GalleryAccess.vue` (saisie du code) | **0** | — | 0 |

Conséquences concrètes :

- un lecteur d'écran n'annonce pas le libellé au focus du champ — les formulaires de **connexion**,
  de **livraison** et de **demande de réservation** sont donc difficilement utilisables ;
- cliquer sur le libellé ne place pas le curseur dans le champ (confort perdu, notamment sur mobile) ;
- `GalleryAccess.vue` n'a **aucun** libellé pour le champ de saisie du code d'accès.

Par ailleurs : **9 `aria-label` seulement** sur 84 composants, et **5 `<div @click>`** sans `role` ni
`tabindex` — donc des contrôles inatteignables au clavier.

> `Contact.vue` montre que la pratique est connue (5 associations sur 6). C'est donc une **irrégularité
> d'application**, pas une méconnaissance — ce qui la rend facile à corriger. Pour un site
> professionnel, c'est aussi un enjeu de conformité (WCAG / RGAA).

---

## 13.6. 🟡 NOUVEAU — Aucun attribut `autocomplete` : friction directe dans le tunnel de paiement

```
grep -c autocomplete  →  Checkout.vue : 0 · AuthModal.vue : 0 · Contact.vue : 0
```

**Zéro occurrence dans tout le front.** Aucun champ ne porte `autocomplete="email"`,
`"current-password"`, `"new-password"`, `"name"`, `"tel"`, `"street-address"`, `"postal-code"` ou
`"address-level2"`.

Conséquences :

- les **gestionnaires de mots de passe** ne reconnaissent pas les champs de connexion et d'inscription
  (remplissage manuel, ou refus d'enregistrer) ;
- l'**autoremplissage d'adresse** du navigateur ne fonctionne pas sur le formulaire de livraison — la
  cliente ressaisit son adresse complète à chaque commande de tirages ;
- sur mobile, le clavier n'est pas adapté et l'autoremplissage natif (iOS/Android) est inopérant.

> C'est un défaut de **conversion** sur la seule page qui génère du chiffre d'affaires, et le correctif
> est un attribut par champ — moins d'une heure pour l'ensemble du tunnel. À rapprocher des
> constats §4.4 sur les navigateurs in-app : le checkout est déjà le point de friction identifié du
> parcours.

---

## 13.7. Huit constats mineurs

| # | Constat | Cot. | Où |
|---|---|---|---|
| 1 | **Timers de recherche non nettoyés au démontage.** `Orders.vue:573` (`searchTimeout`) et `PromoCodes.vue:518` (`searchDebounce`) n'ont **aucun `onUnmounted`** — un timer qui se déclenche après démontage relance un `fetch` sur un composant détruit. `Clients.vue`, `Privacy.vue` et `SchoolSessionDetail.vue` le font correctement : c'est une **incohérence**, pas une méconnaissance | 🟡 | 2 vues |
| 2 | **Blobs construits sans vérifier la réponse.** 9 des 11 `fetch()` directs vérifient bien `response.ok` ✅ ; les 2-3 exceptions sont les `fetch(response.download_url)` suivis d'un `.blob()` (`OrderConfirmation:489`, `DownloadGallery:214`, `account/OrderDetail:207`). Si MinIO renvoie une erreur, le fichier téléchargé contient **le XML d'erreur S3** au lieu de la photo, sous le nom attendu | 🟡 | 3 sites |
| 3 | **`Galleries.vue:572` charge 100 clients** pour peupler un `<select>` d'assignation → plafond silencieux au-delà de 100 clients | 🟡 | 1 vue |
| 4 | **`AdminHeader.vue` avale 3 erreurs** (`catch {}` × 3) sur le chargement et le marquage des notifications → la cloche peut rester vide sans que rien ne le signale | 🟢 | `AdminHeader:96,105,115` |
| 5 | **`PhotosManager.vue` avale 5 erreurs**, dont un `catch { // ignore }` explicite ligne 503 | 🟢 | `PhotosManager` |
| 6 | **`Cart.vue` n'affiche aucun état d'erreur inline** — l'erreur du store passe par un toast (`watch` ligne 313), donc elle disparaît. Sur un panier vide après échec de chargement, l'utilisateur ne voit pas de cause | 🟢 | `Cart.vue:313` |
| 7 | **Aucun état « réessayer »** sur les 5 vues publiques de galerie/événement : en cas d'échec réseau, l'utilisateur doit recharger la page à la main | 🟢 | 5 vues |
| 8 | **`SchoolSessions.vue:330` calcule les prix de palier par division** : `+(price3 / 3).toFixed(2)` → un prix de pack de 20 € pour 3 donne 6,67 €/unité, soit **20,01 €** facturés. Écart d'un centime sur les paliers non divisibles | 🟢 | `SchoolSessions:330` |

---

## 13.8. Sept points positifs

Le front est meilleur que sa volumétrie ne le laisse craindre, et deux composants sont remarquables.

| # | Point | Où |
|---|---|---|
| 1 | **`Checkout.vue` est la vue la mieux écrite du projet.** Drapeau `isUnmounted` vérifié avant **chaque** mutation asynchrone, `pollTimeoutId` nettoyé, polling borné (`attempts >= 10`), watchdog 45 s sur `auth-screen`/`sent`, et des commentaires qui expliquent les pièges : *« isPaymentProcessing reste true → onUnmounted n'annulera pas la commande payée »*. La règle « ce qui a cassé est solide » vaut aussi côté navigateur | `Checkout.vue` |
| 2 | **`MasonryGallery.vue` est sérieusement optimisé** : `priorityCount` calculé depuis le nombre de colonnes, `loading` eager/lazy et `fetchpriority` conditionnels sur les premières images, placeholder blur-up, répartition en colonnes DOM en round-robin, préchargement par lots, et resize **debounced via `requestAnimationFrame`** | `MasonryGallery.vue` |
| 3 | **`EventGalleries.vue` exige une confirmation retapée** avant suppression (`deleteConfirmInput` + `deleteConfirmMatches`) — la suppression cascade sur les sous-galeries et leurs photos (§3bis.13), le garde-fou est au bon endroit | `EventGalleries.vue:703` |
| 4 | **Débounce de recherche présent** dans les 3 vues à recherche serveur (Clients, Orders, PromoCodes), avec remise à la page 1 | 3 vues |
| 5 | **`SchoolSessionDetail.vue` gère proprement deux pollings concurrents** (traitement + export), tous deux arrêtés dans `onUnmounted` | `SchoolSessionDetail:1181` |
| 6 | **La quantité est correctement affichée côté admin** : `Orders.vue:327` calcule `price × quantity` et affiche le détail `prix × qté` quand > 1 ; `SchoolSessionDetail.vue:550` affiche `× {{ item.quantity }}`. **Cela confirme le diagnostic de la §7.6** : le bug est bien circonscrit aux 4 gabarits PDF/email, la chaîne applicative est juste | 2 vues |
| 7 | **Toutes les `<img>` ont un `alt`** (8/8), et le SEO du `index.html` est très travaillé (3 blocs JSON-LD, Open Graph, preloads) | global |

---

## 13.9. Corrections aux sections précédentes

| Section | Correction |
|---|---|
| **§6.8** | « `v-html` → aucune occurrence » → **1 occurrence, et c'est un sink XSS** (§13.1). L'affirmation « surface XSS réelle : faible » doit être révisée |
| **§9.7** | Ligne « `v-html` \| 0 » → **1** |
| **§2.16 / §3.7** | Ajouter aux endpoints sans consommateur : `GET /admin/dashboard` et `GET /admin/dashboard/stats` — avec la nuance qu'ils sont **remplacés par quelque chose de moins bon** (§13.2) |
| **§9.4** | Ajouter au code mort front : l'événement `@sort` du `DataTable` et les 8 déclarations `sortable: true` |
| **§9.6** | La table « bugs trouvés vs domaines testés » gagne 4 lignes : XSS `v-html`, compteurs du dashboard, stats sur page courante, tri décoratif — **tous dans le front, qui n'a aucun test** |
| **§11.4** | Le chantier « convention de réponse API » doit inclure `sort_by`/`sort_dir`, sans quoi le tri restera impossible à brancher |

---

## 13.10. Registre et bilan

Les 61 items de la §12.9 deviennent **68** :

| # | Dette | Cot. | Avant ? |
|---|---|---|---|
| **62** | **XSS : `v-html` avec `customer_email`** (`OrderConfirmation.vue:42`) | 🔴 | **Oui** |
| 63 | Dashboard admin : 6 requêtes de 100 lignes, ~600 signatures MinIO, 7 compteurs faux | 🟠 | Oui |
| 64 | Stats calculées sur la page courante (`GiftCards`, `PromoCodes`) — dont un encours financier | 🟠 | Oui |
| 65 | Tri du `DataTable` décoratif (8 colonnes, aucun consommateur) | 🟠 | Oui |
| 66 | Libellés de formulaire non associés (63/69 sans `for`), 5 `div @click` non focalisables | 🟡 | Non |
| 67 | Aucun `autocomplete` → friction dans le tunnel de paiement | 🟡 | Oui |
| 68 | Timers de recherche non nettoyés (`Orders`, `PromoCodes`) | 🟢 | Non |

**Nouveau total : 68 items — 16 critiques, 24 élevés.**

### Insertions dans le classement des actions les plus rentables

| Rang | Action | Effort |
|---|---|---|
| **4bis** | **Supprimer le `v-html` de `OrderConfirmation.vue`** (balisage de template) | **5 min** |
| **8ter** | **Brancher le dashboard sur `getDashboardStats()`** — supprime 6 requêtes lourdes, corrige 7 compteurs | **2 h** |
| **8quater** | **Retirer `sortable: true`** des 8 colonnes (ou brancher le tri) | **10 min** |
| **12bis** | **Ajouter les `autocomplete`** au tunnel et aux formulaires d'auth | **1 h** |
| **12ter** | **Associer les libellés** (`for`/`id`) sur les 5 formulaires publics | **2 h** |

### Bilan des trois passes

| Passe | Périmètre | Constats critiques trouvés |
|---|---|---|
| 1 (§1-11) | Architecture, API, schéma, services, sécurité, jobs | 12 |
| 2 (§12) | Contrôleurs non lus, commandes, Makefile, compose | **+3** |
| 3 (§13) | Front : 13 vues admin + vues publiques | **+1** |

**Ce que la troisième passe confirme** : le front n'est pas la zone la plus dégradée du projet. Sa
qualité technique est réelle (TypeScript strict, aucun `any`, composants critiques bien écrits), et le
défaut dominant n'est pas la correction du code mais **la véracité de ce qu'il affiche** — trois des
quatre constats élevés sont des interfaces qui montrent des chiffres faux ou des contrôles inopérants.

C'est cohérent avec la §9.6 : **le front n'a aucun test**, et un compteur faux ou une flèche de tri
morte ne provoque jamais d'erreur — donc jamais d'incident, donc jamais de correction. La règle du
projet se vérifie une troisième fois, sous une autre forme : *ce qui ne casse pas bruyamment n'est
jamais réparé.*

**Ce qui reste non instruit après trois passes** : `PersonalDataLocator` (443 l., le résolveur RGPD —
une omission de table y serait une non-conformité), les 45 FormRequests non lus, `Lightbox.vue`, les
templates complets des 13 vues admin (j'ai lu les scripts, pas tout le balisage), et **ce que les
34 fichiers de test n'assertent pas**. Ma recommandation de la §12.10 reste la même : **PHPStan niveau
5+ et une mesure de couverture** rendront plus, plus vite, qu'une quatrième passe manuelle.

---

*Fin de la troisième passe. Document d'audit : 13 sections.*
