# Rapport d'Audit de Code — Oceane Torres Photographie

**Date :** 30 mars 2026
**Auditeur :** Claude (audit automatisé)
**Branche :** `refacto`
**Version projet :** 2.2.1

---

## Phase 1 — Reconnaissance

### Stack technique

| Couche | Technologie | Version |
|--------|------------|---------|
| Backend | Laravel (PHP) | 12.x (PHP ^8.2, runtime 8.4) |
| Frontend | Vue 3 + TypeScript | Vue 3.4, TS 5.5 |
| State | Pinia | 3.0 |
| Routing | Vue Router | 4.6 |
| Style | Tailwind CSS | 3.4 |
| Build | Vite | 6.4 |
| BDD | PostgreSQL (Supabase) | — |
| Stockage | MinIO (S3-compatible) | — |
| Paiement | SumUp | — |
| Auth | Laravel Sanctum | 4.2 |
| CI/CD | GitHub Actions | ubuntu-latest |
| Docker | docker-compose (dev) | PHP 8.4, Node 20 |

### Architecture

- **Monorepo** : `/api` (Laravel) + `/web` (Vue SPA)
- **API REST** classique (pas Inertia, pas Livewire)
- Frontend consomme l'API via fetch avec Bearer tokens (Sanctum)
- Prerendering statique via Puppeteer pour le SEO
- Déploiement sur Render (build `npm run build:prerender`)

### Structure

```
/
├── api/                    # Laravel 12
│   ├── app/
│   │   ├── Console/Commands/   (3 commandes)
│   │   ├── Http/Controllers/Api/  (17 controllers + 5 admin)
│   │   ├── Http/Middleware/    (1 : EnsureUserIsAdmin)
│   │   ├── Jobs/              (1 : ProcessPhotoJob)
│   │   ├── Mail/              (6 classes)
│   │   ├── Models/            (24 modèles)
│   │   ├── Observers/         (1 : UserObserver)
│   │   ├── Providers/         (1 : AppServiceProvider)
│   │   └── Services/          (6 services)
│   ├── database/migrations/   (39 migrations)
│   ├── resources/views/       (emails, PDF, vendor)
│   └── routes/                (api.php, web.php, console.php)
├── web/                    # Vue 3 SPA
│   ├── src/
│   │   ├── components/        (40+ composants)
│   │   ├── composables/       (3)
│   │   ├── config/            (2)
│   │   ├── router/            (35+ routes)
│   │   ├── services/          (5 services API)
│   │   ├── stores/            (3 : auth, cart, consent)
│   │   ├── types/             (4 fichiers)
│   │   ├── utils/             (4)
│   │   └── views/             (20+ vues)
│   └── scripts/               (prerender.js, optimize-images.js)
├── docker/
└── .github/workflows/ci.yml
```

### Tests

- **Backend** : PHPUnit configuré, mais seulement les 2 tests d'exemple par défaut (`ExampleTest.php` feature + unit). Couverture : **~0%**.
- **Frontend** : Aucun framework de test configuré (ni Vitest, ni Cypress). Couverture : **0%**.

### Éléments manquants notables

- Aucun `FormRequest` (0 fichiers)
- Aucune `Policy` (0 fichiers)
- Aucune `API Resource` (0 fichiers)
- Aucun `Event` / `Listener` (0 fichiers)
- Aucun `Repository`

---

## Phase 2 — Résumé exécutif

### Problèmes par sévérité

| Sévérité | Nombre |
|----------|--------|
| 🔴 Critique | 6 |
| 🟠 Important | 12 |
| 🟡 Mineur | 15 |
| ⚪ Cosmétique | 5 |
| **Total** | **38** |

### Score de santé global : **5.5 / 10**

Le code fonctionne et le projet est livré, mais l'architecture souffre d'un manque de séparation des responsabilités (controllers fat, pas de FormRequest/Resource/Policy), de duplication significative, et de problèmes de performance backend (N+1) et frontend (images non optimisées).

### Top 5 Quick Wins (impact élevé, risque faible)

1. Supprimer les dépendances mortes (`laravel/cashier`, `srmklive/paypal`, `@vueuse/core`)
2. Supprimer `PaymentController.php` (entièrement mort)
3. Utiliser l'image hero optimisée (webp) au lieu du PNG de 657 KB
4. Extraire `formatPrice()` dupliqué 6 fois en une seule utility
5. Ajouter `loading="lazy"` sur les images sous le fold

### Top 5 Problèmes critiques

1. N+1 sur l'attribut `client_id` du modèle Gallery (query par gallery sérialisée)
2. N+1 sur `download_status` accessor (2-3 queries par gallery dans les listes admin)
3. 241 MB d'images non optimisées dans `web/public/images/`
4. `GalleryController.php` à 877 lignes (4.4x le seuil)
5. Aucune validation via FormRequest (43 validations inline dans les controllers)

---

## Phase 3 — Problèmes détaillés par catégorie

---

### 1. Code mort & inutilisé

#### 1.1 🔴 PaymentController entièrement mort
- **Fichier :** `api/app/Http/Controllers/Api/PaymentController.php`
- `handleStripeWebhook()` (l.18) et `handlePayPalWebhook()` (l.27) ne sont mappés à aucune route
- Le controller n'est pas importé dans `routes/api.php`
- **Recommandation :** Supprimer le fichier
- **Risque de régression :** Aucun

#### 1.2 🔴 Dépendances Stripe & PayPal inutilisées
- **Fichier :** `api/composer.json`
- `laravel/cashier` (^16.1) — aucun import nulle part dans `app/`
- `srmklive/paypal` (^3.0) — aucun import nulle part dans `app/`
- Seuls vestiges : vues vendor Cashier (`api/resources/views/vendor/cashier/`)
- **Recommandation :** `composer remove laravel/cashier srmklive/paypal`, supprimer `resources/views/vendor/cashier/`
- **Risque de régression :** Aucun

#### 1.3 🟠 `@vueuse/core` jamais importé
- **Fichier :** `web/package.json` (l.17)
- Aucun `import` de `@vueuse/core` dans `web/src/`
- **Recommandation :** `npm uninstall @vueuse/core`
- **Risque de régression :** Aucun

#### 1.4 🟡 Composant `CartIcon.vue` jamais utilisé
- **Fichier :** `web/src/components/cart/CartIcon.vue`
- Jamais importé ni référencé dans aucun fichier
- **Recommandation :** Supprimer le fichier
- **Risque de régression :** Aucun

#### 1.5 🟡 Constantes exportées jamais importées
- **Fichier :** `web/src/config/constants.ts`
  - `COLORS` (l.12) — jamais importé
  - `PORTFOLIO_CATEGORIES` (l.68) — jamais importé
- **Recommandation :** Supprimer ces exports
- **Risque de régression :** Aucun

#### 1.6 🟡 Types TypeScript jamais utilisés
- `web/src/types/index.ts` l.63 : `PrestationMini` — jamais importé
- `web/src/types/admin.ts` l.334 : `BookingRequestData` — jamais importé
- **Recommandation :** Supprimer ces interfaces
- **Risque de régression :** Aucun

#### 1.7 🟡 Variables d'environnement sans effet
- **Fichier :** `api/.env.example`

| Variable | Raison |
|----------|--------|
| `APP_TIMEZONE` | `config/app.php` l.70 hardcode `'UTC'` au lieu de `env('APP_TIMEZONE')` |
| `MINIO_USE_PATH_STYLE` | `config/filesystems.php` l.70 hardcode `true` au lieu de lire l'env |
| `DB_HOST_DOCKER`, `DB_PORT_DOCKER`, `DB_USERNAME_DOCKER` | Non référencés dans aucun config |
| `BROADCAST_CONNECTION` | Pas de `config/broadcasting.php` publié |
| `MAIL_ENCRYPTION` | Laravel 12 utilise `MAIL_SCHEME` à la place |

- **Recommandation :** Corriger les hardcodes ou supprimer les variables inutiles
- **Risque de régression :** Faible

#### 1.8 ⚪ Entrées par défaut inutilisées dans `config/services.php`
- Postmark, Resend, SES, Slack — jamais configurés ni utilisés
- **Recommandation :** Supprimer ces entrées
- **Risque de régression :** Aucun

---

### 2. Code redondant & dupliqué

#### 2.1 🟠 `clearEventGalleriesCache()` dupliqué dans 2 controllers
- `api/app/Http/Controllers/Api/GalleryController.php` l.870
- `api/app/Http/Controllers/Api/PhotoController.php` l.378
- Code identique : boucle `for ($i = 1; $i <= 10; ...)` avec `Cache::forget()`
- **Recommandation :** Extraire dans un trait ou un service partagé
- **Risque de régression :** Faible

#### 2.2 🟠 `MinioStorageService` instancié via `new` 13+ fois au lieu du DI
- `GalleryController.php` (l.181, 333, 727)
- `PhotoController.php` (l.41, 52, 124, 184)
- `OrderController.php` (l.202, 243)
- `ImageProxyController.php` (l.23)
- `InvoiceService.php` (l.70)
- `ImageProcessingService.php` (l.51)
- `ProcessPhotoJob.php` (l.123)
- `Photo.php` modèle (l.107) — **un modèle ne devrait jamais instancier un service**
- **Recommandation :** Injecter via le constructeur (comme fait correctement pour `CartService`, `OrderService`, `SumUpService`)
- **Risque de régression :** Faible

#### 2.3 🟠 Résolution du chemin de stockage photo dupliquée 8+ fois avec variantes incohérentes
- Différentes combinaisons de `file_path_hd`, `metadata['storage_path']`, `metadata['supabase_path']`, `file_path` selon les fichiers
- **Fichiers :** `ImageProxyController` (l.69, 120, 168), `OrderController` (l.199, 257), `PhotoController` (l.129, 185), `GalleryController` (l.349), `Photo.php` (l.98)
- **Recommandation :** Ajouter un accessor `$photo->resolved_storage_path` sur le modèle Photo et l'utiliser partout
- **Risque de régression :** Moyen

#### 2.4 🟠 Résolution MIME type dupliquée dans 3 fichiers
- `ImageProxyController.php` (l.231)
- `ImageProcessingService.php` (l.451)
- `PhotoController.php` (l.204)
- Même bloc `match(png, gif, webp, default jpeg)`
- **Recommandation :** Extraire dans un helper partagé
- **Risque de régression :** Faible

#### 2.5 🟠 Règles de validation `product_types` dupliquées 4 fois dans GalleryController
- `store()` (l.88-95), `update()` (l.133-140), `storeEvent()` (l.614-621), `updateEvent()` (l.688-696)
- **Recommandation :** Extraire dans une méthode ou un FormRequest
- **Risque de régression :** Faible

#### 2.6 🟠 4 méthodes `request()` quasi-identiques dans les services API frontend
- `api.ts`, `authApi.ts`, `adminApi.ts`, `cartApi.ts` — chacun définit sa propre classe d'erreur et méthode `request()` avec AbortController, timeout, headers, fetch
- `getToken()` / `getXsrfToken()` dupliqués dans 3 services
- **Recommandation :** Créer un `BaseApiService` avec la logique commune
- **Risque de régression :** Faible

#### 2.7 🟡 `formatPrice()` dupliqué 6 fois dans le frontend
- `OrderConfirmation.vue` (l.296), `Cart.vue` (l.205), `Prestations.vue` (l.162), `Checkout.vue` (l.248), `CartItem.vue` (l.104), `CartDrawer.vue` (l.138)
- **Recommandation :** Extraire dans `web/src/utils/format.ts`
- **Risque de régression :** Aucun

#### 2.8 🟡 Pattern cart store dupliqué 5 fois
- `web/src/stores/cart.ts` : `addItem`, `updateItemType`, `removeItem`, `clearCart`, `refresh` suivent le même pattern waitForInit → isLoading → try/catch → finally
- **Recommandation :** Créer un wrapper `cartAction(fn)`
- **Risque de régression :** Faible

#### 2.9 🟡 `uploadPhotos()` et `uploadEventPhotos()` quasi-identiques
- `web/src/services/adminApi.ts` (l.405 et l.477) — seul l'URL diffère
- **Recommandation :** Extraire `uploadToEndpoint(path, files)`
- **Risque de régression :** Faible

---

### 3. Fichiers trop gros à découper

#### 3.1 🔴 `GalleryController.php` — 877 lignes (seuil : 200)

Gère : galleries privées, galleries événements (public + admin), thumbnails, ZIP, partage, product types.

**Plan de découpage :**
- `PrivateGalleryController` : store, update, destroy, show, myGalleries, adminIndex, adminShow
- `EventGalleryController` : eventIndex, eventShow, storeEvent, updateEvent, destroyEvent, setEventThumbnail, adminEventIndex, adminEventShow, adminEventChildren
- `GalleryDownloadController` : downloadZip, downloadFile
- Déplacer `syncProductTypes` dans un `GalleryService`

**Risque de régression :** Moyen (routes à re-mapper)

#### 3.2 🟠 `OrderController.php` — 559 lignes
**Plan de découpage :**
- `OrderController` : createFromCart, show, index, getByEmail
- `OrderDownloadController` : downloadPhoto, downloadAll, downloadInvoice
- `AdminOrderController` : adminIndex, adminShow, adminDestroy, adminMarkShipped

#### 3.3 🟠 `OrderService.php` — 516 lignes
- `createFromCart()` (105 lignes, 4+ niveaux d'imbrication) → extraire `findReusablePendingOrder()`, `resolveItemPrices()`, `validateCartItems()`
- `initiatePayment()` (100 lignes) → extraire `tryReuseExistingCheckout()`

#### 3.4 🟠 `ImageProcessingService.php` — 479 lignes
- Acceptable pour du traitement d'image, mais `applyWatermark()` pourrait être scindé en `applyGridWatermark()` + `applyCentralWatermark()`

#### 3.5 🟠 `CartService.php` — 420 lignes
- `getCartSummary()` (70+ lignes) → extraire la logique de calcul

#### 3.6 🔴 `EventGalleries.vue` — 1782 lignes (seuil : 300)
#### 3.7 🔴 `Galleries.vue` — 1394 lignes

Ces deux fichiers partagent des centaines de lignes de template et logique dupliquées.

**Plan de découpage commun :**
- `ProductTypesEditor.vue` (~80 lignes de formulaire dupliqué)
- `PhotoUploadZone.vue` (~30 lignes d'upload drag-and-drop dupliqué)
- `PhotoGridManager.vue` (grille + sélection + actions bulk, ~300 lignes dupliquées)
- Composable `useGalleryPhotos()` pour la logique partagée

#### 3.8 🟡 Autres fichiers frontend volumineux

| Fichier | Lignes |
|---------|--------|
| `Clients.vue` | 666 |
| `Reservations.vue` | 628 |
| `MasonryGallery.vue` | 573 |
| `admin/Prestations.vue` | 566 |
| `admin/Dashboard.vue` | 496 |
| `admin/Orders.vue` | 490 |
| `OrderConfirmation.vue` | 486 |
| `Contact.vue` | 462 |
| `Checkout.vue` | 435 |
| `adminApi.ts` | 645 |
| `uploadService.ts` | 503 |

#### 3.9 🟡 `adminApi.ts` — 645 lignes
- **Recommandation :** Découper par domaine : `adminReservationApi.ts`, `adminGalleryApi.ts`, `adminOrderApi.ts`, etc.

---

### 4. Non-respect des design patterns Laravel

#### 4.1 🔴 Aucun FormRequest (43 validations inline)
- 43 occurrences de `$request->validate()` directement dans les controllers
- **Fichiers principaux :** AuthController (5), GalleryController (6), AvailabilityController (5), PhotoController (5), OrderController (2), CartController (3), ReservationController (4)
- **Recommandation :** Créer des FormRequest dédiés (`StoreGalleryRequest`, `CreateOrderRequest`, `RegisterRequest`, etc.)
- **Risque de régression :** Faible

#### 4.2 🔴 Aucune API Resource
- Tous les controllers retournent des données brutes via `response()->json()`
- `OrderController` a un `formatOrder()` privé de 30 lignes (l.526-558) — exactement ce qu'une `OrderResource` ferait
- Risque d'exposition de données sensibles (le modèle `User` est retourné brut dans `AuthController`)
- Format de réponse incohérent : `{ success, data }` vs `{ success, order }` vs `{ gallery }` vs données paginées brutes
- **Recommandation :** Créer `OrderResource`, `GalleryResource`, `UserResource`, `ReservationResource`, etc.
- **Risque de régression :** Moyen (le frontend s'attend aux formats actuels)

#### 4.3 🟠 Aucune Policy
- Pas de `app/Policies/`
- Checks d'autorisation dispersés dans les controllers :
  - `OrderController::show()` (l.118-135) : 4 conditions manuelles d'accès
  - `ReservationController::destroy()` (l.160) : vérifie le statut mais pas la propriété
- **Recommandation :** Créer `OrderPolicy`, `ReservationPolicy`, `GalleryPolicy`
- **Risque de régression :** Faible

#### 4.4 🟠 Logique métier dans les controllers
- `GalleryController` : `syncProductTypes()`, ZIP download, contraintes parent gallery
- `PhotoController::store()` : orchestration du traitement d'image, gestion vidéo, upload
- `ContactController::send()` : envoi d'emails inline au lieu d'Events/Listeners
- `BookingRequestController::notifyAdmin()` : création de notifications et envoi d'emails inline
- **Recommandation :** Extraire dans des services dédiés
- **Risque de régression :** Moyen

#### 4.5 🟠 Aucun Event/Listener
- Ni `Events/` ni `Listeners/` n'existent
- Les effets de bord (emails, notifications) sont exécutés inline
- **Recommandation :** `OrderPaid` → `GenerateInvoice` + `SendConfirmation` ; `ContactMessageCreated` → `NotifyAdmin` + `SendConfirmation`
- **Risque de régression :** Faible

#### 4.6 🟡 Fat Model : Gallery (353 lignes)
- Contient de la logique métier de pricing : `getAvailableProductTypes()`, `getPackPricing()`, `resolvePackPrice()`, `getPriceForProductType()`
- **Recommandation :** Extraire dans un `PricingService`
- **Risque de régression :** Moyen

---

### 5. Non-respect des design patterns Vue

#### 5.1 🟠 EventGalleries.vue et Galleries.vue — duplication massive
- Voir section 3.6/3.7 pour le plan de découpage
- Ces deux composants gèrent état + logique + rendu sans séparation

#### 5.2 🟡 Manipulation DOM directe
- `OrderConfirmation.vue` (l.409-415, 463-471) : `document.createElement('a')` pour les downloads
- `Checkout.vue` (l.263-269) : création de `<script>` pour le SDK SumUp
- `Events.vue` (l.286) : `document.getElementById(id)?.scrollIntoView()`
- **Recommandation :** Extraire un composable `useDownload()` ; utiliser des template refs

#### 5.3 ⚪ Stores bien utilisés
- Pas de props drilling excessif détecté
- Les watchers sont correctement utilisés pour des effets de bord

---

### 6. Complexité inutile

#### 6.1 🟠 `OrderService::createFromCart()` — 105 lignes, 4+ niveaux d'imbrication
- `foreach` dans `DB::transaction` dans la méthode
- **Recommandation :** Extraire `findReusablePendingOrder()`, `resolveItemPrices()`, `validateCartItems()`

#### 6.2 🟠 `OrderService::initiatePayment()` — 100 lignes, 5 niveaux d'imbrication
- try-catch dans if-else dans try-catch
- **Recommandation :** Extraire `tryReuseExistingCheckout()`

#### 6.3 🟡 `OrderController::show()` — 4 niveaux de checks d'accès imbriqués (l.108-154)
- **Recommandation :** Utiliser des early returns ou une Policy

#### 6.4 🟡 `uploadChunked()` dans `uploadService.ts` — 95 lignes avec nesting
- **Recommandation :** Extraire le corps de la boucle interne

#### 6.5 🟡 `CartService::getCartSummary()` — 70+ lignes avec map imbriqué
- **Recommandation :** Scinder en sous-méthodes

---

### 7. Commentaires & documentation

#### 7.1 🟡 TODO incomplet
- `api/app/Http/Controllers/Api/GiftCardController.php` l.43 : `// TODO: Generate PDF and send email`
- Fonctionnalité de génération de PDF pour les cartes cadeaux non implémentée
- **Recommandation :** Implémenter ou documenter comme limitation connue

#### 7.2 ⚪ Documentation des services
- Les services ont des docblocks basiques (résumés d'une ligne)
- Acceptable pour ce niveau de projet

---

### 8. Dépendances & configuration

#### 8.1 🔴 Packages PHP morts (déjà couvert en 1.2)
- `laravel/cashier` (^16.1)
- `srmklive/paypal` (^3.0)

#### 8.2 🟠 Package frontend mort (déjà couvert en 1.3)
- `@vueuse/core` (^14.1.0)

#### 8.3 ⚪ Vues vendor Cashier orphelines
- `api/resources/views/vendor/cashier/invoice.blade.php`
- `api/resources/views/vendor/cashier/payment.blade.php`
- **Recommandation :** Supprimer avec le package Cashier

---

### 9. Performance

#### 9.1 🔴 N+1 : attribut `client_id` sur Gallery (accessor avec query)
- **Fichier :** `api/app/Models/Gallery.php` l.164-172
- `$appends = ['client_id']` force `getClientIdAttribute()` → `Client::where('user_id', ...)->value('id')` pour **chaque Gallery sérialisée**
- Chaque endpoint listant des galleries (admin, events, etc.) déclenche N queries supplémentaires
- **Recommandation :** Retirer de `$appends`, eager-loader la relation client, ou utiliser un join
- **Risque de régression :** Moyen

#### 9.2 🔴 N+1 : `download_status` accessor (2-3 queries par gallery)
- **Fichier :** `api/app/Models/Gallery.php` l.330-352
- `getDownloadStatusAttribute()` exécute 2-3 `count()` queries
- Appelé dans `adminIndex()` via `transform()` sur chaque gallery
- **Recommandation :** Utiliser `withCount` dans le controller et calculer le statut à partir des counts
- **Risque de régression :** Moyen

#### 9.3 🟠 Triple chargement de relations dans CartService
- **Fichier :** `api/app/Services/CartService.php`
- `getCartSummary()` appelle `recalculatePackPrices()` → `$cart->load(...)`, puis `buildPackGroups()` → `$cart->load(...)` à nouveau, puis recharge une 3e fois
- **Recommandation :** Charger une seule fois au début de `getCartSummary()`
- **Risque de régression :** Faible

#### 9.4 🟠 Pagination manquante sur plusieurs endpoints

| Fichier | Ligne | Endpoint | Risque |
|---------|-------|----------|--------|
| `GalleryController.php` | 74 | `myGalleries()` | Toutes les galleries + photos |
| `ReservationController.php` | 220 | `calendar()` | Toutes les réservations |
| `AvailabilityController.php` | 57 | `availableSlots()` | Tous les créneaux |
| `AccountController.php` | 29, 47 | `dashboard()` | Toutes les galleries/réservations user |
| `OrderService.php` | 423, 433 | `getOrdersForUser/Email()` | Toutes les commandes |
| `PrestationController.php` | 31 | `adminIndex()` | Toutes les prestations |

- **Recommandation :** Ajouter de la pagination ou au minimum un `->limit()`
- **Risque de régression :** Faible

#### 9.5 🟠 Queries UPDATE en boucle

| Fichier | Méthode | Impact |
|---------|---------|--------|
| `PhotoController.php` l.277-280 | `updateSortOrder()` | N UPDATE par photo |
| `EventCategoryController.php` l.97-99 | `reorder()` | N UPDATE par catégorie |
| `CartService.php` l.392-408 | `recalculatePackPrices()` | N UPDATE par item modifié |
| `BookingRequestController.php` l.122-138 | `notifyAdmin()` | N INSERT par admin |

- **Recommandation :** Utiliser `upsert()`, `insert()` batch, ou `CASE/WHEN` SQL
- **Risque de régression :** Faible

#### 9.6 🔴 241 MB d'images non optimisées dans `web/public/images/`
- 128 fichiers JPG/PNG non compressés
- Pires cas : `Sport/3.jpg` (13 MB), `Animalier/13.jpg` (10 MB), `Animalier/3.jpg` (9.5 MB)
- Le dossier `/optimized/` (41 MB, webp/avif) existe mais les originaux sont aussi déployés
- **Recommandation :** Supprimer les originaux du déploiement, s'assurer que tout le code référence les versions optimisées
- **Risque de régression :** Moyen (vérifier toutes les références)

#### 9.7 🟠 Image hero servie en PNG non optimisé
- **Fichier :** `web/src/views/Home.vue` l.122
- `const heroImage = '/images/hero.png'` → 657 KB
- Version optimisée disponible : `/optimized/hero.webp` (28 KB), `/optimized/hero.avif` (47 KB) — réduction de 95%
- **Recommandation :** Utiliser un `<picture>` avec fallback
- **Risque de régression :** Aucun

#### 9.8 🟡 `loading="lazy"` manquant sur les images

| Fichier | Contexte |
|---------|----------|
| `PhotoCard.vue` l.27 | Toutes les photos de gallery |
| `Cart.vue` l.57 | Thumbnails panier |
| `Checkout.vue` l.166 | Thumbnails checkout |
| `OrderConfirmation.vue` l.74 | Thumbnails commande |
| `admin/Galleries.vue` | Photos admin |
| `admin/EventGalleries.vue` | Photos événements admin |

- **Recommandation :** Ajouter `loading="lazy"` sur toutes les images sous le fold

#### 9.9 🟡 Handler resize sans debounce
- `MasonryGallery.vue` l.421 : `window.addEventListener('resize', updateColumnCount)` sans debounce
- `Navbar.vue` l.284 : `window.addEventListener('scroll', handleScroll)` sans throttle
- **Recommandation :** Ajouter debounce/throttle via `requestAnimationFrame`

#### 9.10 🟡 `adminEventIndex()` charge toutes les photos
- **Fichier :** `GalleryController.php` l.539
- `with(['photos', ...])` charge TOUTES les photos mais n'utilise que `$gallery->photos->first()` pour la cover
- **Recommandation :** Charger uniquement `thumbnailPhoto` + `withCount('photos')`

#### 9.11 ⚪ Pas de stratégie de chunks Vite
- **Fichier :** `web/vite.config.ts`
- Pas de `manualChunks` configuré — tout le vendor dans un seul chunk
- **Recommandation :** Séparer `vue`, `vue-router`, `pinia` pour optimiser le cache navigateur

---

## Plan d'action priorisé

### Etape 1 : Suppressions sans risque (code mort, imports inutiles) -- FAIT
*Risque : Aucun | Impact : Nettoyage immediat*

1. ~~Supprimer `api/app/Http/Controllers/Api/PaymentController.php`~~
2. ~~`composer remove laravel/cashier srmklive/paypal`~~ (5 packages retires dont stripe-php, moneyphp/money)
3. ~~Supprimer `api/resources/views/vendor/cashier/`~~
4. ~~`npm uninstall @vueuse/core` (dans `/web`)~~
5. ~~Supprimer `web/src/components/cart/CartIcon.vue`~~
6. ~~Supprimer les exports morts : `COLORS`, `PORTFOLIO_CATEGORIES` dans `constants.ts`~~
7. ~~Supprimer les types morts : `PrestationMini`, `BookingRequestData`~~
8. ~~Nettoyer `config/services.php` (retirer Postmark, Resend, SES, Slack)~~
9. ~~Nettoyer `.env.example` (retirer `DB_*_DOCKER`, `BROADCAST_CONNECTION`, `MAIL_ENCRYPTION`)~~

### Etape 2 : Corrections rapides a faible risque -- FAIT
*Risque : Faible | Impact : Performance & qualite*

1. ~~Extraire `formatPrice()` dans `web/src/utils/format.ts`~~ (5 fichiers mis a jour)
2. ~~Changer hero image PNG -> WebP dans `Home.vue`~~ (657 KB -> 28 KB)
3. Ajouter `loading="lazy"` sur les `<img>` sous le fold -- **RESTE A FAIRE (etape 3)**
4. ~~Corriger `config/app.php` : `env('APP_TIMEZONE', 'UTC')`~~
5. ~~Corriger `config/filesystems.php` : `env('MINIO_USE_PATH_STYLE', true)`~~
6. ~~Extraire `clearEventGalleriesCache()` dans `app/Traits/ClearsEventGalleriesCache.php`~~
7. ~~Extraire la resolution MIME type dans `app/Helpers/MimeTypes.php`~~ (3 fichiers mis a jour)

### Etape 3 : Refactoring structurel (découpage de fichiers) -- FAIT
*Risque : Moyen | Impact : Maintenabilité*

**Backend :**
1. ~~Découper `GalleryController.php`~~ : extrait `EventGalleryController` (385 lignes) — GalleryController passe de 877 a 420 lignes (-52%)
2. ~~Découper `OrderController.php`~~ : extrait `Admin/OrderController` (196 lignes) — OrderController passe de 559 a 334 lignes (-40%)
3. ~~Extraire `SyncsProductTypes` trait~~ : regles de validation + sync product types partagees (eliminee 4x duplication)
4. ~~Ajouter accessor `$photo->resolved_storage_path`~~ — remplace 8 variantes incohérentes dans 8 fichiers
5. ~~Passer `MinioStorageService` en DI~~ — 13 `new MinioStorageService` remplaces par DI ou `app()` container
6. ~~Extraire sous-methodes `OrderService::createFromCart()`~~ — 105 lignes -> 4 methodes claires (`findReusablePendingOrder`, `calculateCartTotal`, `resolvePackPrices`, `validateCartItems`)

**Frontend :**
7. ~~Creer `BaseApiService`~~ — logique `request()` partagee entre 4 services (elimine ~200 lignes dupliquees)
8. ~~Refactorer les 4 services API~~ : api.ts (153->91), authApi.ts (149->59), adminApi.ts (646->495), cartApi.ts (335->268)
9. ~~Extraire `useProductTypes` composable~~ — 110 lignes de logique identique extraites de Galleries.vue et EventGalleries.vue (Galleries 1394->1290, EventGalleries 1782->1678)

### Etape 4 : Optimisations de performance -- FAIT
*Risque : Moyen | Impact : Performance critique*

1. ~~**Corriger le N+1 `client_id`**~~ : retiré de `$appends`, batch-load via `Client::whereIn()` dans adminIndex (1 query au lieu de N)
2. ~~**Corriger le N+1 `download_status`**~~ : calculé a partir des `withCount` déja presents (0 query supplémentaire au lieu de 2-3 par gallery)
3. ~~**Audit images**~~ : `persona_2.jpg` supprimé (non référencé). Les originaux `/images/` servent de source pour `optimize-images.js` — seul `hero.png` est encore référencé directement
4. ~~Corriger le triple chargement de relations dans `CartService`~~ : `load()` -> `loadMissing()` (1 chargement au lieu de 3)
5. ~~Batch UPDATE~~ : `updateSortOrder` et `reorder` utilisent CASE/WHEN SQL (1 query au lieu de N)
6. ~~Pagination~~ : `myGalleries()` limité a 50, `getOrdersForUser/Email()` limité a 100
7. ~~Limiter `adminEventIndex()` + `children()`~~ : `photos` limité a 1 au lieu de toutes les photos
8. ~~Ajouter debounce/throttle~~ : `requestAnimationFrame` sur resize (MasonryGallery) et scroll (Navbar)
9. ~~Configurer `manualChunks` Vite~~ : vendor-vue séparé (vue, vue-router, pinia)
10. ~~Supprimer cache check dupliqué `PhotoCard`~~ : retrait du `new Image()` dans `onMounted` + ajout `loading="lazy"`

### Etape 5 : Améliorations d'architecture (design patterns) -- FAIT
*Risque : Moyen-Élevé | Impact : Qualité long terme*

**FormRequests (19 classes creees, ~30 validations inline remplacees) :**
- `Auth/` : RegisterRequest, LoginRequest, UpdateProfileRequest, ResetPasswordRequest
- `Admin/` : StorePrestationRequest, UpdatePrestationRequest, StoreUserRequest, UpdateUserRequest, StoreClientRequest, UpdateClientRequest
- Racine : SendContactRequest, StoreBookingRequest, CreateCheckoutRequest, AddToCartRequest, StoreGiftCardRequest, StoreReservationRequest, StorePhotoRequest, StoreAsyncPhotoRequest, UpdateSortOrderRequest

**API Resources :**
- ~~`OrderResource` + `OrderItemResource`~~ : remplace `formatOrder()` (30 lignes de mapping manuel)

**Policies :**
- ~~`OrderPolicy`~~ (view + download) — auto-decouverte Laravel

**Events/Listeners :**
- ~~`ContactMessageSent` -> `SendContactEmails`~~ : decouple l'envoi d'emails du ContactController
- ~~`BookingRequested` -> `SendBookingNotifications`~~ : decouple notifications et emails du BookingRequestController

**PricingService :**
- ~~Extraire la logique de pricing~~ : `getAvailableProductTypes`, `getPackPricing`, `resolvePackPrice`, `getPriceForProductType` extraits de Gallery vers PricingService. Les methodes du modele restent comme proxy.

**Reste :**
- Ajouter des tests (PHPUnit, Vitest) — uniquement additif, pas de regression
- ~12 validations inline mineures restantes (1-2 regles simples, ne justifient pas un FormRequest)

---

## Fichiers à supprimer

| Fichier | Justification |
|---------|--------------|
| `api/app/Http/Controllers/Api/PaymentController.php` | Entièrement mort, aucune route, code deprecated |
| `api/resources/views/vendor/cashier/invoice.blade.php` | Vestige de Stripe Cashier, non utilisé |
| `api/resources/views/vendor/cashier/payment.blade.php` | Vestige de Stripe Cashier, non utilisé |
| `web/src/components/cart/CartIcon.vue` | Jamais importé ni référencé |

---

## Dépendances à retirer

### Backend (`composer.json`)

| Package | Raison |
|---------|--------|
| `laravel/cashier` (^16.1) | Migration complète vers SumUp, aucun import dans le code |
| `srmklive/paypal` (^3.0) | Migration complète vers SumUp, aucun import dans le code |

### Frontend (`package.json`)

| Package | Raison |
|---------|--------|
| `@vueuse/core` (^14.1.0) | Aucun import dans `web/src/`, jamais utilisé |

---

## À vérifier manuellement

Ces éléments n'ont pas pu être confirmés avec certitude :

1. **Images dans `web/public/images/`** : Vérifier manuellement quelles images sont encore référencées dans le code ou les données en base avant de supprimer les originaux non optimisés
2. **`PORTFOLIO_CATEGORIES` dans `constants.ts`** : Bien que non importé, vérifier si ce n'était pas prévu pour une feature en cours de développement
3. **Vendor Cashier views** : Confirmer qu'aucune route web ne les sert (vérification faite : `routes/web.php` ne les référence pas, mais vérifier les packages auto-registered)
4. **`GiftCardController` TODO** : Déterminer si la génération de PDF pour les cartes cadeaux est une feature attendue ou abandonnée
5. **Variables `DB_*_DOCKER`** dans `.env.example` : Elles semblent documentaires pour docker-compose — confirmer si elles doivent rester comme documentation

---

*Rapport généré le 30 mars 2026. Aucune modification n'a été apportée au code.*
