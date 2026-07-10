# Plan de tests — parties critiques (paiement, téléchargement, upload)

> Objectif : couvrir en tests **uniquement les parties critiques** de l'API Laravel,
> dans cet ordre de priorité : **1. Paiement → 2. Téléchargement → 3. Upload**.
>
> Approche retenue :
> - **Base de test : Postgres dédié (Docker)** — le code contient du SQL Postgres non
>   portable (voir §1). On teste sur le même moteur que la prod.
> - **Tests Feature (HTTP) en priorité + tests Unit ciblés** sur la logique fine.
> - Framework : **PHPUnit** (déjà en place), pas de Pest.
> - **SumUp reste en SANDBOX** pour les tests (décision produit) — voir §3.

---

## ✅ État d'avancement — **143 tests, 327 assertions, tous verts**

Répartition : **5** fondations · **58** paiement · **41** téléchargement · **33** upload ·
**6** préexistant (`GiftCodeDiscountTest`).

**Lancer :** `make test` (démarre la base de test puis exécute PHPUnit), ou
`make test-db-up` puis `cd api && php artisan test`.

### Vue d'ensemble par chantier

| Chantier | Tests | Type | Fichiers |
|---|---:|---|---|
| Fondations | 5 | Feature | `SmokeTest` (connexion pgsql, factories, génération n° commande) |
| **1. Paiement** | **58** | 34 Feature + 24 Unit | `Feature/Payment/*` + `Unit/Services/{OrderService,SumUpService,GiftCodeService}Test` |
| **2. Téléchargement** | **41** | 30 Feature + 11 Unit | `Feature/Download/*` + `Unit/Jobs/GenerateCleanThumbnailJobTest` + `Unit/Models/PhotoTest` |
| **3. Upload** | **33** | 21 Feature + 12 Unit | `Feature/Upload/*` + `Unit/PhotoController/HumanizeUploadErrorTest` + `Unit/Jobs/ProcessPhotoJobTest` |
| Préexistant | 6 | Unit | `Unit/GiftCodeDiscountTest` (calcul pur de remise) |

### 1. Paiement — 58 tests

| Fichier | Tests | Ce qui est couvert |
|---|---:|---|
| `Feature/Payment/CheckoutTest` | 9 | `POST /checkout` : panier vide (400), commande payante + init SumUp, commande **gratuite** (code cadeau couvrant tout → pas de SumUp), tirage sans adresse (422), CGV requises, e-mail invité requis, expiration des pending précédents, **frais de port = max multi-galeries**, **re-sync du prix obsolète** |
| `Feature/Payment/SumUpWebhookTest` | 7 | Webhook `POST /payments/sumup/return` : `id` manquant → ack, commande inconnue → ack, déjà payée → ack, `PAID` → complétée, `FAILED` → échouée, `PENDING` → intacte, erreur → **503** (retry). Ne fait jamais confiance au payload |
| `Feature/Payment/OrderAccessTest` | 6 | `GET /orders/{order}` : accès owner / e-mail invité / token / fenêtre 30 min, refus (403), introuvable (404) |
| `Feature/Payment/ConfirmFreeOrderTest` | 5 | `POST /checkout/confirm-free` : confirmation, idempotence, refus si total > 0, refus si non-pending, validation |
| `Feature/Payment/VerifyPaymentTest` | 4 | `POST /payments/sumup/verify` : déjà payée, sans checkout, **auto-complétion sandbox**, validation |
| `Feature/Payment/CancelCheckoutTest` | 3 | `POST /payments/sumup/cancel-checkout` : annulation, non-pending (400), race webhook payée → **409** |
| `Unit/Services/OrderServiceTest` | 12 | `initiatePayment` (non-pending, réutilisation checkout, déjà payé → 409, un seul Payment), `completeOrder` (idempotence, état inattendu, **mail confirmation + notif print**, **cas school → SchoolOrderConfirmationMail sans notif print**), `completeFreeOrder`, `verifyAndUpdateOrder` **hors sandbox** (PAID → complète, sinon inchangé) |
| `Unit/Services/GiftCodeServiceTest` | 6 | `assertUsableForCheckout` : code valide, inactif/expiré/pas-encore-valide (422), quota `max_uses` sur commandes **payées** uniquement |
| `Unit/Services/SumUpServiceTest` | 6 | Constructeur (config manquante), `createCheckout` (persiste l'id / échec), `deactivateCheckout` (bool, ne throw pas) |

### 2. Téléchargement — 41 tests

| Fichier | Tests | Ce qui est couvert |
|---|---:|---|
| `Feature/Download/GalleryZipTest` | 11 | ZIP streamé `download-zip` : accès (privé sans/avec token, public, **event publié**, **propriétaire**), 404 si vide, seules les photos téléchargeables loggées, photo sans fichier ignorée, **plafond 500**, **noms d'entrées uniques** (titres en collision) |
| `Feature/Download/ImageProxyDownloadTest` | 6 | HD verrouillé par achat `images/download/{photo}` : token/order requis, non payée, token invalide, photo hors commande, succès + `markAsDownloaded`, fichier absent (404) |
| `Feature/Download/PhotoDownloadTest` | 6 | `photos/{photo}/download` : galerie inaccessible (403), non téléchargeable (403), mode direct (octets / 500), mode URL signée (/ 500), logging |
| `Feature/Download/OrderDownloadTest` | 4 | `orders/{order}/download*` : URL signée + `markAsDownloaded`, non payée (403), **cap 50** photos, ZIP complet |
| `Feature/Download/DownloadableListingTest` | 3 | `galleries/download/{token}` : token invalide (404), listing mode download + `clean_thumbnail_url`, `recordView` |
| `Unit/Jobs/GenerateCleanThumbnailJobTest` | 6 | Skips (photo absente / vidéo / non-téléchargeable / déjà générée), succès, échec → throw (retry) |
| `Unit/Models/PhotoTest` | 5 | `resolved_storage_path` (fallback), `cleanThumbnailStoragePath`, `recordDownload` (compteur + `DownloadLog`, troncature UA) |

### 3. Upload — 33 tests

| Fichier | Tests | Ce qui est couvert |
|---|---:|---|
| `Feature/Upload/StoreAsyncPhotoTest` | 7 | `storeAsync` : auth admin requise, non-admin (403), galerie parente (422), image OK, vidéo OK, **succès partiel** (un fichier échoue sans bloquer le batch), **galerie event via `/admin/events` + cache vidé** |
| `Feature/Upload/PhotoManagementTest` | 6 | `toggle-downloadable` (dispatch job clean-thumbnail), off (pas de job), vidéo (pas de job), `bulk-downloadable`, tri `sort-order`, suppression |
| `Feature/Upload/UploadValidationTest` | 5 | `StoreAsyncPhotoRequest` : `batch_id` requis, ≥1 photo, max 15, mime interdit, > 50 Mo |
| `Feature/Upload/UploadStatusTest` | 3 | `upload-status` : agrégat (`FILTER (WHERE)` pgsql), batch complet, batch inexistant |
| `Unit/PhotoController/HumanizeUploadErrorTest` | 7 | Mapping message technique → message UI (mémoire, stockage, mime, corruption, galerie, permission, défaut) |
| `Unit/Jobs/ProcessPhotoJobTest` | 5 | Chemin queue scolaire : upload absent, galerie absente → failed, échec transient → retry sans markAsFailed, succès, `failed()` → markAsFailed + cleanup |

### Fondations mises en place

- **Base de test** : service Docker `postgres-test` (profil `test`, `tmpfs`, port `55432`),
  `api/phpunit.xml` reconfiguré sur pgsql, cibles `make test` / `test-db-up` / `test-db-down`.
- **11 factories** créées/corrigées : `UserFactory` (était cassée), `Gallery`, `Photo`,
  `Order`, `OrderItem`, `Cart`, `CartItem`, `GiftCode`, `Payment`, `DownloadLog`, `PhotoUpload`.
- **Helpers** : `tests/TestCase.php` (`actingAsAdmin`, `fakeMinio`, `fakeSumUp`),
  trait `tests/Concerns/CreatesShopData` (paniers / galeries / photos).
- **Mocks** : SumUp via `Http::fake()`, stockage via `Storage::fake('minio')`,
  traitement d'image et jobs via mocks de conteneur, `Mail::fake()` / `Queue::fake()`.

### 🐞 Bugs de production trouvés ET corrigés grâce aux tests
Tous liés à Postgres (invisibles sur SQLite, donc jamais détectés avant) :
1. **Migrations `migrate:fresh` cassé** — `add_performance_indexes` (2025) indexe `clients`
   créée en 2026. Index déplacés dans `create_clients_table` + garde `Schema::hasTable`.
2. **`PhotoController::updateSortOrder`** — `DB::raw($sql, $bindings)` : le 2ᵉ argument est
   ignoré → `SQLSTATE[HY093]`, tri des photos **totalement cassé**. Remplacé par une boucle
   d'`update` en transaction.
3. **`PhotoController::bulkToggleDownloadable`** — mass-update d'un booléen PHP contourne le
   trait de cast → `column is of type boolean but expression is of type integer`. Passe
   désormais `'true'/'false'`.
4. **`PhotoUpload::getBatchStatus`** — `$counts->total === 0` (Postgres renvoie une chaîne) →
   `found` jamais `false` pour un batch inexistant. Corrigé par cast `(int)`.

### ⚠️ Anomalies signalées, NON corrigées (hors périmètre, à décider)
- Route morte `GET /my-galleries/{gallery}/download` → `GalleryController@downloadPhotos` (méthode inexistante).
- `GalleryController@downloadFile` sert `?file=` sans garde anti path-traversal (à voir en revue sécurité).
- `Photo` ne caste pas `sort_order` en `int` (incohérent avec `Gallery`) — cosmétique.

---

## 0. État des lieux

| Élément | État actuel |
|---|---|
| Framework de test | PHPUnit 11 configuré (`api/phpunit.xml`) |
| Tests existants | 1 seul : `tests/Unit/GiftCodeDiscountTest.php` (test pur, sans DB) |
| Base configurée en test | SQLite `:memory:` ❌ (incompatible avec le SQL Postgres du code) |
| Factories | **Seulement `UserFactory`** — 18 autres modèles utilisent `HasFactory` sans factory |
| Commande de test | `make test` → `php artisan test` (déjà présente) |
| CI | Aucune (pas de `.github/workflows`) |
| SumUp | Facade `Http` isolée dans `SumUpService` → mockable via `Http::fake()` ou mock du service |
| Stockage | Disque `minio` (driver s3) isolé dans `MinioStorageService` → `Storage::fake('minio')` |
| Images | `ImageProcessingService` (Imagick/GD) → à **mocker** dans les tests HTTP |
| Jobs | `GenerateCleanThumbnailJob`, `ProcessPhotoJob` → `Queue::fake()` / test direct de `handle()` |

---

## 1. ⚠️ Blocage technique : SQL spécifique Postgres

Plusieurs zones critiques utilisent du SQL que **SQLite ne sait pas exécuter**. C'est la
raison pour laquelle on teste sur Postgres et non sur SQLite en mémoire :

| Fichier | Ligne | SQL Postgres |
|---|---|---|
| `app/Models/Order.php` | `generateOrderNumber()` | `SELECT pg_advisory_xact_lock(42)` |
| `app/Models/PhotoUpload.php` | `getBatchStatus()` | `COUNT(...) FILTER (WHERE ...)` |
| `app/Models/Photo.php` | scopes | `whereRaw` sur booléens |
| `app/Models/Concerns/CastsBooleansForPostgres.php` | trait | casts booléens spécifiques PG |

➡️ **Décision : suite de tests sur Postgres dédié (Docker). Aucun refactor du code de production.**

---

## 2. Fondations à mettre en place (prérequis, à faire en premier)

Ces briques sont partagées par les 3 chantiers. **À réaliser avant d'écrire le moindre test métier.**

### 2.1 Base de données de test Postgres — ✅ **Réalisé**
- [x] Service `postgres-test` dans `docker-compose.yml` (`postgres:16-alpine`, profil `test`,
  port `55432`, base `testing`, `tmpfs`).
- [x] Surcharge de la connexion dans `phpunit.xml` (pas de `.env.testing`) ; SQLite retiré.
- [x] `RefreshDatabase` sur Postgres.
- [x] Cibles Makefile `test-db-up` / `test-db-down` + `make test` qui démarre la base.

Détail (pour mémoire) — surcharge `phpunit.xml` :
  ```
  DB_CONNECTION=pgsql
  DB_HOST=127.0.0.1
  DB_PORT=<port du service test>
  DB_DATABASE=testing
  DB_USERNAME=postgres
  DB_PASSWORD=postgres

  SUMUP_ENV=sandbox           # décision : on reste en sandbox (voir §3)
  SUMUP_API_KEY=test_sumup_key
  SUMUP_MERCHANT_CODE=TEST_MERCHANT
  APP_URL=http://localhost
  FILESYSTEM_DISK=minio
  ```

### 2.2 Factories manquantes (bloquant pour tout test DB) — ✅ **Réalisé**
Créées sous `api/database/factories/` (+ `UserFactory` corrigée, elle était cassée) :
- [x] `GalleryFactory`, `PhotoFactory`, `OrderFactory`, `OrderItemFactory`, `CartFactory`,
  `CartItemFactory`, `GiftCodeFactory`, `PaymentFactory`, `DownloadLogFactory`, `PhotoUploadFactory`
- [x] Traits `HasFactory` ajoutés à `PhotoUpload` et `DownloadLog` (manquants).

Points d'attention :
- `Order` : PK UUID + `order_number` auto-généré en `boot()` (Postgres). La factory doit
  laisser `boot()` faire son travail ; possibilité d'états `pending` / `paid` / `failed`.
- `Photo` : la sérialisation JSON **appelle MinIO** (URLs signées via attributs `display_url`
  / `preview_url` / `thumbnail_url`). ⇒ Toujours activer `Storage::fake('minio')` dans les
  tests qui renvoient des photos. Prévoir des états `downloadable`, `video`, `processed`.
- `Gallery` : états `public`, `private` (avec `access_token`), `event` (`is_published`).

### 2.3 Base de `TestCase` — ✅ **Réalisé**
- [x] `tests/TestCase.php` : helpers `actingAsAdmin()`, `actingAsClient()`, `fakeMinio()`, `fakeSumUp(...)`.
- [x] Trait `tests/Concerns/CreatesShopData` (paniers / galeries / photos + attache code cadeau).

---

## 3. Chantier 1 — PAIEMENT (priorité 1)

### Boundary externe
Tous les appels HTTP SumUp sont isolés dans `app/Services/SumUpService.php` (facade `Http`).
Deux stratégies de mock au choix selon le test :
- **`Http::fake([...])`** → teste `SumUpService` réel de bout en bout (recommandé pour les
  Feature tests réalistes).
- **`$this->mock(SumUpService::class)`** → contrôle direct de `createCheckout` / `getCheckout`
  / `deactivateCheckout` (recommandé pour isoler `OrderService` / contrôleurs).

Toujours : `Mail::fake()` + `Queue::fake()` (effets de bord de complétion).

### ⚠️ Sandbox (décision : on reste en sandbox)
`OrderService::verifyAndUpdateOrder()` **auto-complète** la commande si
`sumup.environment === 'sandbox'` ET `app.env ∈ {local, testing}`.
➡️ `phpunit.xml` fixe `SUMUP_ENV=sandbox` : les tests de `verify` valident donc ce
comportement d'auto-complétion (identique au dev). Le **webhook** n'emprunte PAS ce
raccourci (il appelle toujours `getCheckout()`), il est donc testé avec `Http::fake()`.

### Tests Feature (HTTP)

**`tests/Feature/Payment/CheckoutTest.php`** — `POST /checkout` (`OrderController@createFromCart`) — 7 tests
- [x] panier vide → 400
- [x] panier valide payant → crée `Order` (pending) + renvoie données de paiement SumUp
- [x] `total <= 0` (code cadeau couvre tout) → réponse `payment.free = true`, **aucun** appel SumUp, commande reste pending
- [x] article print nécessitant livraison sans adresse → 422
- [x] expiration des anciennes commandes pending du même panier/utilisateur
- [x] CGV requises (422) · e-mail invité requis (422)
- [x] frais de port = **max** des frais parmi les galeries (panier mixte)
- [x] re-synchronisation du prix si le prix a changé pendant la mise au panier

**`tests/Feature/Payment/ConfirmFreeOrderTest.php`** — `POST /checkout/confirm-free` — 5 tests
- [x] commande gratuite pending → complétée (idempotent si déjà payée)
- [x] commande avec `total > 0` → 400
- [x] commande non pending → 400
- [x] validation `order_id` (uuid / exists)

**`tests/Feature/Payment/SumUpWebhookTest.php`** — `POST /payments/sumup/return` — 7 tests
> Le webhook ne fait **jamais** confiance au payload : il ré-interroge `getCheckout`.
- [x] `id` manquant → 200 `{received:true}` (non-retry)
- [x] commande inconnue → 200 ack
- [x] commande déjà payée → 200 ack (idempotent)
- [x] statut vérifié `PAID` → `completeOrder` (commande payée, transaction id posé)
- [x] statut `FAILED` → `handleFailedPayment`
- [x] statut `PENDING`/`EXPIRED` → no-op
- [x] exception interne → **503** (déclenche le retry SumUp)

**`tests/Feature/Payment/VerifyPaymentTest.php`** — `POST /payments/sumup/verify` — 4 tests
- [x] commande déjà payée → `status: paid` (court-circuit)
- [x] sans `sumup_checkout_id` → `status: pending`
- [x] délègue à `verifyAndUpdateOrder` (auto-complétion **sandbox**) · validation `order_id`

**`tests/Feature/Payment/CancelCheckoutTest.php`** — `POST /payments/sumup/cancel-checkout` — 3 tests
- [x] commande non pending → 400
- [x] annulation OK → `expired` + `sumup_checkout_id` null, `deactivateCheckout` appelé
- [x] course webhook (payée entre-temps) → 409 `already_paid`

**`tests/Feature/Payment/OrderAccessTest.php`** — `GET /orders/{order}` (`show`) — 6 tests
- [x] propriétaire authentifié / `guest_email` correct / token valide → accès
- [x] **fenêtre de 30 min** après création → accès (edge case sécurité)
- [x] sinon → 403 ; introuvable → 404

### Tests Unit ciblés

**`tests/Unit/Services/OrderServiceTest.php`** (mock `SumUpService`) — 8 tests
- [x] `initiatePayment` : réutilise checkout `PENDING` ; `PAID` existant → complète + 409 ; non pending → 400 ; crée/maj **une seule** ligne `Payment`
- [x] `completeOrder` : idempotence (lock + short-circuit `isPaid`) ; état inattendu → 409
- [x] `completeFreeOrder` : rejette `total > 0` (400) ; complète un total 0
- [x] effets de bord : commande standard → `OrderConfirmationMail` + `PrintOrderNotificationMail` ; commande **school** → `SchoolOrderConfirmationMail` sans notif print
- [x] `verifyAndUpdateOrder` en **unit** hors sandbox : `PAID` → complète, sinon inchangé (ne marque pas failed)

**`tests/Unit/Services/SumUpServiceTest.php`** (`Http::fake()`) — 6 tests
- [x] constructeur throw si config manquante (api_key, merchant_code)
- [x] `createCheckout` : écrit `sumup_checkout_id` seulement sur 2xx ; throw sur échec ; montant correct
- [x] `deactivateCheckout` : renvoie un bool, ne throw jamais

**`tests/Unit/Services/GiftCodeServiceTest.php`** — 6 tests
- [x] `assertUsableForCheckout` : throw `BusinessException(422)` si invalide (inactif / hors dates / pas-encore-valide), sinon renvoie la remise
- [x] `max_uses` / `paidCount` ne compte que les commandes `paid`

> Le calcul de remise pur (`GiftCode::effectiveDiscount`) est **déjà couvert** par
> `tests/Unit/GiftCodeDiscountTest.php`.

---

## 4. Chantier 2 — TÉLÉCHARGEMENT (priorité 2)

### Boundary externe
Lecture stockage via disque `minio` (`MinioStorageService`) → `Storage::fake('minio')`
couvre `readStream`, `get`, `temporaryUrl`, etc. Pour simuler un **échec** de stockage
(retours null → 500/404), **mocker `MinioStorageService`** (il avale les exceptions).
`ImageProxyController` injecte `ImageProcessingService` → le **mocker** (pas d'Imagick réel).

### ⚠️ Deux systèmes de contrôle d'accès différents (à tester distinctement)
- **ZIP galerie** (`GalleryController@downloadZip`) → `GalleryPolicy@download`
  (public **OU** event publié **OU** token **OU** propriétaire/assigné).
- **Photo seule** (`PhotoController@download`) et **images clean** (`ImageProxyController`)
  → `Gallery::isAccessible($token)` **uniquement** (public OU token exact) **+** `is_downloadable`.
  Plus strict : une galerie `event` n'est **pas** ouverte ici.

### Tests Feature (HTTP)

**`tests/Feature/Download/GalleryZipTest.php`** — `GET /galleries/{gallery}/download-zip` — 7 tests
- [x] galerie privée sans token / mauvais token → 403
- [x] token valide / public → 200, `Content-Type: application/zip`
- [x] ensemble downloadable vide → 404
- [x] seules les photos `is_downloadable` sont incluses
- [x] photo dont `readStream` renvoie null → **ignorée** (pas de `recordDownload` pour elle)
- [x] **logging** : nb de `DownloadLog` == nb de photos réellement streamées
- [x] accès **event publié** / **propriétaire** (autres branches de `GalleryPolicy`)
- [x] plafond de **500** photos · noms d'entrées uniques (collisions de titres)

**`tests/Feature/Download/PhotoDownloadTest.php`** — `GET /photos/{photo}/download` — 6 tests
- [x] galerie non accessible (token) → 403 (avant tout accès stockage)
- [x] photo `!is_downloadable` → 403
- [x] `?direct=1` → octets bruts ; stockage null → 500
- [x] défaut → JSON `{download_url}` (URL signée) ; stockage null → 500
- [x] `recordDownload` bien exécuté (compteur + `DownloadLog`)

**`tests/Feature/Download/ImageProxyDownloadTest.php`** — `GET /images/download/{photo}` (HD, gated par achat) — 6 tests
- [x] `token` ou `order` manquant → 403
- [x] commande non `paid` → 403 ; token invalide → 403 ; photo hors commande → 403
- [x] cas nominal → octets HD + `OrderItem::markAsDownloaded()`
- [x] stockage null → 404

**`tests/Feature/Download/OrderDownloadTest.php`** — `OrderController` — 4 tests
- [x] `GET /orders/{order}/download/{item}` : gate `getOrderForDownload`, URL signée, `markAsDownloaded`
- [x] `GET /orders/{order}/download-all` : plafond **50** items → 400 au-delà ; ZIP construit + items marqués
- [x] `getOrderForDownload` : non payée → 403 ; accès via `paid_at` < 30 min

**`tests/Feature/Download/DownloadableListingTest.php`** — `GET /galleries/download/{token}` — 3 tests
- [x] token invalide → 404 ; token valide → `mode=download` + `clean_thumbnail_url` exposé ; `recordView()` déclenché

> **À signaler (hors périmètre test, bug potentiel) :** la route `GET /my-galleries/{gallery}/download`
> pointe vers `GalleryController@downloadPhotos` qui **n'existe pas** → route morte (500).
> `GalleryController@downloadFile` sert un fichier depuis `?file=` **sans garde anti path-traversal**
> (à mentionner en revue sécurité, pas forcément à tester ici).

### Tests Unit ciblés

**`tests/Unit/Jobs/GenerateCleanThumbnailJobTest.php`** — 6 tests
- [x] early-return si photo absente / vidéo / non downloadable / thumb clean déjà présente
- [x] échec génération → throw (retry) ; succès → `update(file_path_thumbnail_clean)`

**`tests/Unit/Models/PhotoTest.php`** — 5 tests
- [x] `getResolvedStoragePathAttribute` : ordre de fallback (`file_path_hd` → `metadata[storage_path]` → `file_path`)
- [x] `recordDownload` : incrémente `downloads_count` **et** crée un `DownloadLog` (troncature UA)
- [x] `cleanThumbnailStoragePath` : chemin attendu

---

## 5. Chantier 3 — UPLOAD (priorité 3)

### Boundary externe
`ImageProcessingService::processUploadedPhoto` (image) et `MinioStorageService::uploadPhoto`
(vidéo) sont les deux points à contrôler. En Feature test, **mocker `ImageProcessingService`**
(pas d'Imagick réel) ; `Storage::fake('minio')` pour le stockage. Auth admin obligatoire
(`Sanctum::actingAs($admin)` + middleware `admin`).

### Tests Feature (HTTP)

**`tests/Feature/Upload/StoreAsyncPhotoTest.php`** — `POST /admin/galleries/{gallery}/photos/async` — 6 tests
- [x] non authentifié → 401 · non admin → 403
- [x] galerie **parente** (a des enfants) → 422
- [x] upload image OK → `Photo` créée (`is_processed=true`, `is_downloadable=false`), `PhotoUpload` `completed`
- [x] upload vidéo OK → `Photo` `is_video=true` via `MinioStorageService::uploadPhoto`
- [x] **succès partiel** : un fichier échoue → `PhotoUpload` `failed` avec `error_message`, les autres passent, réponse 200
- [x] galerie `event` → cache event vidé · même endpoint via `POST /admin/events/{gallery}/photos/async`

**`tests/Feature/Upload/UploadValidationTest.php`** — `StoreAsyncPhotoRequest` — 5 tests
- [x] `photos` requis, min 1, **max 15** → 422 au-delà
- [x] mime interdit (ex. `.txt`) → 422
- [x] fichier > **50 Mo** (`max:51200` Ko) → 422
- [x] `batch_id` manquant → 422

**`tests/Feature/Upload/UploadStatusTest.php`** — `GET /admin/upload-status` — 3 tests
- [x] agrégat correct (`total`, `completed`, `failed`, `processing`, `progress`, `is_complete`)
  → **valide le SQL `FILTER (WHERE)` sur Postgres**
- [x] batch complet → `is_complete=true` · `batch_id` inexistant → `found=false`

**`tests/Feature/Upload/PhotoManagementTest.php`** — 6 tests
- [x] `PUT /admin/photos/{photo}/toggle-downloadable` : bascule + dispatch `GenerateCleanThumbnailJob` (+ pas de dispatch si off / vidéo) — `Queue::fake()`
- [x] `PUT /admin/photos/bulk-downloadable` : mass update + un job par photo éligible + `updated_count`
- [x] `PUT /admin/photos/sort-order` : l'ordre persiste réellement
- [x] `DELETE /admin/photos/{photo}` : suppression + `MinioStorageService::deletePhoto`

### Tests Unit ciblés

**`tests/Unit/PhotoController/HumanizeUploadErrorTest.php`** (logique pure `match`) — 7 tests
- [x] mapping des messages : mémoire / minio|s3|curl / mime|format / decode|corrupt / gallery / permission → défaut

**`tests/Unit/Jobs/ProcessPhotoJobTest.php`** (chemin queue — sessions scolaires) — 5 tests
- [x] `PhotoUpload` absent → return silencieux (pas de fail)
- [x] `Gallery` absente → `markAsFailed` + cleanup temp
- [x] traitement renvoie null → throw (retry), **sans** `markAsFailed` ni cleanup (laisse retenter)
- [x] `failed()` (retries épuisés) → `markAsFailed` + cleanup
- [x] cas nominal image → `Photo` créée + `markAsCompleted` + cleanup

**`tests/Unit/Services/ImageProcessingServiceTest.php`** (optionnel, plus lourd — Imagick/GD réels)
- [ ] `processUploadedPhoto` renvoie un array de chemins sur image valide, `null` sur exception — *non fait (optionnel)*
- [ ] `generateAndStoreCleanThumbnail` renvoie `false` si génération null, sinon upload + `true` — *non fait (optionnel)*
> ⚠️ Volontairement non réalisé : le comportement est **déjà couvert indirectement** par les
> Feature tests (service mocké). Nécessiterait une vraie image de fixture + une police TTF.

---

## 6. Ordre d'exécution — ✅ **Terminé** (les 4 étapes)

- [x] **Fondations** (§2) : Postgres de test + `phpunit.xml` + factories + `TestCase` + `SmokeTest`.
- [x] **Paiement** (§3) : Unit `OrderService`/`SumUpService`/`GiftCodeService` + Feature checkout → webhook → verify → cancel → access.
- [x] **Téléchargement** (§4) : Feature ZIP → photo → proxy HD → order download + Unit job/modèle.
- [x] **Upload** (§5) : Feature storeAsync → validation → status → gestion photos + Unit `humanizeUploadError`/`ProcessPhotoJob`.

`make test` reste **vert** (132 tests).

## 7. Optionnel (hors périmètre immédiat, à proposer plus tard)
- [ ] CI GitHub Actions (`.github/workflows/tests.yml`) : Postgres service + `php artisan test`.
- [ ] Couverture (`make test-coverage`) et seuil minimal sur les 3 zones critiques.
- [ ] Traiter les 2 anomalies repérées : route morte `downloadPhotos`, path-traversal `downloadFile`.
- [ ] Tests directs (lourds) de `ImageProcessingService` avec Imagick/GD réels (§5).
