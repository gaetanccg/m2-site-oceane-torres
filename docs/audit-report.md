# Rapport d'Audit de Code — Oceane Torres Photographie

**Date initiale :** 31 mars 2026
**Derniere mise a jour :** 31 mars 2026
**Branche :** `refacto`
**Version projet :** 2.2.1

---

## Stack technique

| Couche | Technologie | Version |
|--------|------------|---------|
| Backend | Laravel (PHP) | 12.x (PHP ^8.2, runtime 8.4) |
| Frontend | Vue 3 + TypeScript | Vue 3.4, TS 5.5 |
| State | Pinia | 3.0 |
| Routing | Vue Router | 4.6 |
| Style | Tailwind CSS | 3.4 |
| Build | Vite | 6.4 |
| BDD | PostgreSQL (Supabase) | - |
| Stockage | MinIO (S3-compatible) | - |
| Paiement | SumUp | - |
| Auth | Laravel Sanctum | 4.2 |

**Architecture :** Monorepo `/api` (Laravel REST API) + `/web` (Vue SPA) avec prerendering Puppeteer.

---

## Etat du projet apres refactoring

### Backend (96 fichiers PHP dans api/app/)

| Categorie | Fichiers | Lignes | Avant |
|-----------|----------|--------|-------|
| Controllers API | 16 | 3 320 | - |
| Controllers Admin | 6 | 724 | - |
| **Total Controllers** | **22** | **4 044** | - |
| Services | 7 | 1 939 | 6 services |
| FormRequests | 19 | ~500 | **0 (inexistant)** |
| API Resources | 2 | ~50 | **0 (inexistant)** |
| Policies | 1 | ~60 | **0 (inexistant)** |
| Events | 2 | ~30 | **0 (inexistant)** |
| Listeners | 2 | ~100 | **0 (inexistant)** |
| Traits | 2 | ~60 | 0 |
| Helpers | 1 | ~20 | 0 |

### Frontend (web/src/)

| Categorie | Fichiers | Lignes | Avant |
|-----------|----------|--------|-------|
| Services API | 6 | 1 526 | 4 fichiers, ~1 283 lignes |
| Composables | 4 | 410 | 3 fichiers, ~275 lignes |
| Utilitaires | 5 | ~120 | 4 fichiers |
| admin/Galleries.vue | 1 | 1 290 | 1 394 |
| admin/EventGalleries.vue | 1 | 1 678 | 1 782 |

---

## Recapitulatif complet des actions realisees

### Etape 1 : Suppressions sans risque

| Action | Detail |
|--------|--------|
| Supprimer PaymentController.php | Code mort (Stripe/PayPal deprecated) |
| `composer remove laravel/cashier srmklive/paypal` | 5 packages retires (stripe-php, moneyphp, etc.) |
| Supprimer vues vendor Cashier | `resources/views/vendor/cashier/` orphelin |
| `npm uninstall @vueuse/core` | Jamais importe dans le code |
| Supprimer CartIcon.vue | Composant jamais reference |
| Supprimer exports morts | `COLORS`, `PORTFOLIO_CATEGORIES` dans constants.ts |
| Supprimer types morts | `PrestationMini`, `BookingRequestData` |
| Nettoyer config/services.php | Retire Postmark, Resend, SES, Slack |
| Nettoyer .env.example | Retire `DB_*_DOCKER`, `BROADCAST_CONNECTION`, `MAIL_ENCRYPTION` |
| Supprimer persona_2.jpg | Image non referencee dans le code |

### Etape 2 : Corrections rapides

| Action | Impact |
|--------|--------|
| Extraire `formatPrice()` dans `utils/format.ts` | Elimine 5 copies identiques |
| Corriger `config/app.php` timezone | `env('APP_TIMEZONE', 'UTC')` au lieu de hardcode |
| Corriger `config/filesystems.php` minio | `env('MINIO_USE_PATH_STYLE', true)` au lieu de hardcode |
| Extraire `ClearsEventGalleriesCache` trait | Elimine duplication entre 2 controllers |
| Extraire `MimeTypes` helper | Elimine duplication entre 3 fichiers |

### Etape 3 : Refactoring structurel

**Backend — Decoupage controllers :**

| Fichier | Avant | Apres | Extraction |
|---------|-------|-------|------------|
| GalleryController.php | 877 | 440 | → EventGalleryController (395 lignes) |
| OrderController.php | 559 | 319 | → Admin/OrderController (168 lignes) |

**Backend — Nouveaux fichiers :**
- `EventGalleryController.php` — toutes les methodes event gallery
- `Admin/OrderController.php` — toutes les methodes admin order
- `Traits/SyncsProductTypes.php` — validation + sync product types (elimine 4x duplication)
- `Photo::resolved_storage_path` accessor — remplace 8 variantes incoherentes dans 8 fichiers

**Backend — Injection de dependance :**
- 13 occurrences de `new MinioStorageService` remplacees par DI constructeur ou `app()` container
- Reste 2 occurrences dans les constructeurs de services avec valeur par defaut (`= new MinioStorageService`)

**Backend — OrderService refacto :**
- `createFromCart()` (105 lignes monolithiques) → 4 methodes privees : `findReusablePendingOrder()`, `calculateCartTotal()`, `resolvePackPrices()`, `validateCartItems()`

**Frontend — BaseApiService :**
- Cree `baseApi.ts` (111 lignes) — logique `request()`, auth headers, CSRF partagee

| Service | Avant | Apres | Reduction |
|---------|-------|-------|-----------|
| api.ts | 153 | 91 | -40% |
| authApi.ts | 149 | 59 | -60% |
| adminApi.ts | 646 | 495 | -23% |
| cartApi.ts | 335 | 268 | -20% |

**Frontend — useProductTypes composable :**
- 135 lignes de logique product types extraites de Galleries.vue et EventGalleries.vue (10 fonctions identiques centralisees)

### Etape 4 : Optimisations de performance

| Probleme | Avant | Apres |
|----------|-------|-------|
| N+1 `client_id` (Gallery $appends) | 1 query/gallery serialisee | 1 batch `whereIn` pour toute la page |
| N+1 `download_status` accessor | 2-3 queries/gallery | 0 query (calcule depuis withCount) |
| Triple load CartService | 3x `$cart->load(...)` | 1x `load()` + 2x `loadMissing()` |
| adminEventIndex charge toutes photos | Toutes les photos par gallery | Limite a 1 photo (cover) |
| PhotoCard double image request | `new Image()` + `<img>` | `<img loading="lazy">` seul |
| Resize handler sans debounce | Feu a chaque pixel | `requestAnimationFrame` throttle |
| Scroll handler sans throttle | Feu a chaque pixel | `requestAnimationFrame` throttle |
| Vite single bundle | Tout dans un chunk | `vendor-vue` separe (cache navigateur) |
| N UPDATE en boucle (sort order) | N queries individuelles | 1 query CASE/WHEN SQL |
| Pas de limite sur myGalleries | `->get()` sans limite | `->limit(50)` |
| Pas de limite sur getOrders | `->get()` sans limite | `->limit(100)` |

### Etape 5 : Ameliorations d'architecture

**19 FormRequests crees et appliques :**
- Auth : RegisterRequest, LoginRequest, UpdateProfileRequest, ResetPasswordRequest
- Admin : StorePrestationRequest, UpdatePrestationRequest, StoreUserRequest, UpdateUserRequest, StoreClientRequest, UpdateClientRequest
- Public : SendContactRequest, StoreBookingRequest, CreateCheckoutRequest, AddToCartRequest, StoreGiftCardRequest, StoreReservationRequest, StorePhotoRequest, StoreAsyncPhotoRequest, UpdateSortOrderRequest

**API Resources :**
- `OrderResource` + `OrderItemResource` — remplace `formatOrder()` (30 lignes de mapping manuel → resource reutilisable)

**Policy :**
- `OrderPolicy` (view + download) — formalise les conditions d'acces dispersees dans les controllers

**Events/Listeners :**
- `ContactMessageSent` → `SendContactEmails` : ContactController passe de 71 a 36 lignes
- `BookingRequested` → `SendBookingNotifications` : BookingRequestController passe de 143 a 76 lignes

**PricingService :**
- 4 methodes de pricing extraites du modele Gallery (267 lignes) vers un service dedie (109 lignes)
- Les methodes du modele restent comme proxy pour compatibilite

---

## Ce qui reste a faire (hors scope de ce refactoring)

### Priorite haute
- **Tests** : 0% de couverture. Ajouter PHPUnit pour les services critiques (OrderService, CartService, PricingService) et Vitest pour les composables frontend.

### Priorite moyenne
- **~12 validations inline restantes** : petites validations (1-2 regles) dans CartController, GiftCardController, SumUpPaymentController, ReservationController, AvailabilityController. Ne justifient pas un FormRequest dedie.
- **Galleries.vue (1290 lignes) et EventGalleries.vue (1678 lignes)** : restent volumineux. L'extraction du composable `useProductTypes` a retire ~110 lignes chacun, mais d'autres composants partages pourraient etre extraits (PhotoUploadZone, PhotoGridManager, DeletePhotoModal).
- **adminApi.ts (495 lignes)** : pourrait etre decoupe par domaine (reservations, galleries, orders) mais le risque d'impact sur les imports dans les vues admin est non negligeable.

### Priorite basse
- **API Resources supplementaires** : GalleryResource, UserResource, ReservationResource pour uniformiser les reponses API
- **Policies supplementaires** : GalleryPolicy, ReservationPolicy
- **uploadService.ts (502 lignes)** : pourrait beneficier d'un refactoring
- **Images originales /public/images/** : 241 MB d'originaux servent de source pour `optimize-images.js`. Seul `hero.png` est directement reference. Envisager un `.gitignore` ou un stockage externe pour les originaux.

---

## Score de sante

| Critere | Avant | Apres |
|---------|-------|-------|
| Code mort | Stripe/PayPal/CartIcon/types | Nettoye |
| Duplication | ~400+ lignes dupliquees | Centralise (traits, composables, BaseApiService) |
| Fichiers geants | GalleryController 877, OrderController 559 | 440 et 319 (decoupes) |
| Validation | 0 FormRequest, 43+ inline | 19 FormRequests, ~12 inline mineures |
| Autorisation | 0 Policy, checks disperses | 1 Policy (OrderPolicy) |
| Separation responsabilites | Emails inline, pricing dans modele | Events/Listeners, PricingService |
| Performance N+1 | 2 critiques (client_id, download_status) | Corriges (batch, withCount) |
| API Resources | 0 | 2 (Order, OrderItem) |
| DI | 13x `new MinioStorageService` | DI constructeur ou container |
| Frontend DRY | 4 services avec request() copie | BaseApiService + heritage |

**Score estime : 5.5/10 → 7.5/10**

Les points restants (tests, Galleries/EventGalleries trop gros, API Resources supplementaires) sont des ameliorations incrementales qui peuvent etre traitees au fil du temps sans urgence.

---

*Rapport genere le 31 mars 2026.*
