# Codes Promo (Gift Codes) — guide technique

> **Livré** (branche d'origine `feature/gift-code`). Ce document décrit la conception,
> les décisions et le **pourquoi** de la fonctionnalité, ainsi que sa surface technique.
>
> Objectif : l'admin crée/gère des **codes promo** (montant fixe ou pourcentage), le
> client les applique dans son panier avant la génération du checkout SumUp.
> Contraintes de conception : **robuste, sécurisé, et ne rien casser** (le flux Bons
> Cadeaux existant n'est pas touché — voir §0).

---

## 0. Avertissement de nommage — ne pas confondre deux concepts

Le codebase contient **déjà** une notion proche qu'il ne faut **surtout pas** modifier :

| Concept existant | Table | Modèle | Menu admin | Nature |
|---|---|---|---|---|
| **Bons Cadeaux** (existant, NE PAS TOUCHER) | `gift_cards` | `GiftCard` | « Bons Cadeaux » | Carte prépayée : on **achète** un montant, débité à l'usage (`remaining_amount`). C'est un moyen de paiement / valeur stockée. |
| **Codes Promo** (NOUVEAU, cette feature) | `gift_codes` | `GiftCode` | « Codes Promo » | Code de **réduction** à montant fixe appliqué au panier avant paiement. |

➡️ Cette feature crée une table **dédiée** `gift_codes` (pluriel, conforme à la convention Laravel de toutes les tables du projet — `carts`, `orders`, `gift_cards`…). Le modèle est `GiftCode`. **Aucune ligne du flux `GiftCard` / Bons Cadeaux n'est touchée.**

---

## 1. Décisions de conception (verrouillées)

1. **Type de remise** : deux types gérés dès maintenant via une colonne `type` :
   - `fixed` → montant fixe en euros (`value` = euros) ;
   - `percent` → pourcentage du sous-total (`value` = 0–100), avec plafond euro optionnel `max_discount_amount`.
2. **Application de la remise** : la remise s'applique au **sous-total** (`subtotal`), **jamais aux frais de port** (`shipping_fee`). Calcul (méthode `GiftCode::effectiveDiscount($subtotal)`) :
   ```
   fixed   : discount = min(value, subtotal)
   percent : discount = round(subtotal * value / 100, 2)
             discount = min(discount, max_discount_amount ?? discount)   // plafond optionnel
             discount = min(discount, subtotal)                          // bornage final
   total   = (subtotal - discount) + shipping_fee
   ```
   La remise effective est **recalculée à chaque lecture du panier** contre le sous-total courant (donc auto-ajustée si le panier change).
3. **Stockage du code appliqué** : référence stockée **côté serveur sur le panier** (`carts.gift_code_id`). Le client n'envoie **jamais** le montant de la remise — il envoie seulement le code à appliquer. Tout le calcul est serveur.
4. **Source de vérité du prix** : `SumUpService::createCheckout()` utilise déjà `$order->total`. Comme on réduit `total` côté serveur dans `OrderService::createFromCart()`, **SumUp reçoit automatiquement le bon montant**. Aucun changement de `SumUpService`.
5. **Comptage des utilisations (« utilisé » = payé uniquement)** : un code n'est consommé que par les commandes **`paid`**. Une commande `pending` (panier en cours / abandonné), `failed` ou `expired` ne consomme **jamais** le code. Le quota `max_uses` et le statut « épuisé » se basent donc sur `paidCount()`. Source de vérité unique = relation `orders` (statut `paid`) → pas de compteur dénormalisé qui dérive. Les commandes `pending` sont affichées séparément côté admin à titre informatif (« en cours »).
   - *Limite résiduelle de concurrence assumée* : comme un panier `pending` ne réserve pas le code, deux paiements quasi-simultanés sur un code à usage unique pourraient aboutir tous deux (dépassement de 1). Risque négligeable sur ce volume ; choix produit explicite (cf. §5).
6. **Tracking admin « utilisé ou non + lien commande »** : via la relation `GiftCode hasMany Order` (sur `orders.gift_code_id`). L'admin voit la liste des commandes ayant consommé le code, avec lien direct.
7. **Code** : 6 caractères alphanumériques majuscules générés automatiquement, **ou** code custom (max 24 car.). Unicité insensible à la casse (stockage en majuscules).

---

## 2. Schéma de base de données

### 2.1 Nouvelle migration — `gift_codes`
`api/database/migrations/2026_06_03_000001_create_gift_codes_table.php`

```php
Schema::create('gift_codes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('code', 24)->unique();                       // stocké en MAJUSCULES
    $table->enum('type', ['fixed', 'percent'])->default('fixed');
    $table->decimal('value', 10, 2);                            // euros si fixed ; 0–100 si percent
    $table->decimal('max_discount_amount', 10, 2)->nullable();  // plafond € (percent uniquement)
    $table->timestamp('valid_from')->nullable();                // début de validité (null = immédiat)
    $table->timestamp('valid_until')->nullable();               // fin de validité (null = illimité)
    $table->unsignedInteger('max_uses')->nullable()->default(1);  // défaut 1 ; null = illimité
    $table->boolean('is_active')->default(true);                // interrupteur manuel admin
    $table->text('note')->nullable();                           // note interne admin (jamais exposée au client)
    $table->timestamps();

    $table->index('code');
    $table->index('is_active');
});
```

### 2.2 Nouvelle migration — colonne sur `carts`
`api/database/migrations/2026_06_03_000002_add_gift_code_to_carts_table.php`

```php
Schema::table('carts', function (Blueprint $table) {
    $table->foreignUuid('gift_code_id')->nullable()->after('guest_email')
          ->constrained('gift_codes')->nullOnDelete();
});
```

### 2.3 Nouvelle migration — colonnes sur `orders` (snapshot)
`api/database/migrations/2026_06_03_000003_add_gift_code_to_orders_table.php`

```php
Schema::table('orders', function (Blueprint $table) {
    $table->foreignUuid('gift_code_id')->nullable()->after('cart_id')
          ->constrained('gift_codes')->nullOnDelete();
    $table->string('gift_code', 24)->nullable()->after('gift_code_id'); // snapshot du code (audit)
    $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal');
});
```

> **Pourquoi un snapshot `gift_code` (string) + `discount_amount` sur l'order** : la facture et l'historique doivent rester corrects même si l'admin supprime/modifie le code après coup. `nullOnDelete` garde la commande valide ; le snapshot conserve la trace.

> ⚠️ **Compatibilité facture** : `subtotal + shipping_fee` ne sera plus toujours égal à `total`. Vérifier `InvoiceService` (§7) pour afficher la ligne remise.

---

## 3. Backend Laravel

### 3.1 Modèle `GiftCode`
`api/app/Models/GiftCode.php` (calqué sur `GiftCard.php` : `HasUuids`, `boot` pour auto-code).

Éléments clés :
- `fillable` : `code, type, value, max_discount_amount, valid_from, valid_until, max_uses, is_active, note`.
- `casts` : `value => decimal:2`, `max_discount_amount => decimal:2`, `valid_from/valid_until => datetime`, `max_uses => integer`, `is_active => boolean`.
- `boot()::creating` → si `code` vide, `generateUniqueCode(6)`. Normaliser systématiquement en **majuscules**.
- `static generateUniqueCode(int $len = 6): string` : alphabet **sans caractères ambigus** (exclure `O 0 I 1` — alphabet conseillé `ABCDEFGHJKLMNPQRSTUVWXYZ23456789`), boucle jusqu'à unicité.
- `orders(): HasMany` (sur `gift_code_id`).
- `paidCount(): int` → `orders()->where('status','paid')->count()` — base du quota et du statut « utilisé/épuisé ».
- `pendingCount(): int` → `orders()->where('status','pending')->count()` — **informatif** (admin « en cours »), hors quota.
- `effectiveDiscount(float $subtotal): float` → applique la formule `fixed`/`percent` du §1 (avec plafond `max_discount_amount` et bornage à `subtotal`).

> La validation de disponibilité d'un code (actif / fenêtre de validité / quota `paidCount < max_uses`) vit dans **`GiftCodeService::validationError()`** — source unique, réutilisée par `preview()` (panier) et `assertUsableForCheckout()` (checkout). Pas de méthode `isUsable()` dupliquée sur le modèle.

### 3.2 Service de validation — `GiftCodeService`
`api/app/Services/GiftCodeService.php` (nouveau, pour centraliser la logique et la réutiliser panier + checkout).

```php
class GiftCodeService
{
    /** Résout un code saisi → modèle ou null, avec raison d'échec. Lecture seule. */
    public function resolve(string $rawCode): ?GiftCode;       // normalise MAJ + trim

    /** Valide pour un sous-total donné (hors verrou) → array{valid:bool, reason:?string, discount:float}. */
    public function preview(GiftCode $code, float $subtotal): array;

    /** Validation FINALE sous verrou, à appeler DANS la transaction de checkout. */
    public function assertUsableForCheckout(GiftCode $code, float $subtotal): float; // retourne le discount, throw BusinessException sinon
}
```

Messages d'erreur métier (via `BusinessException`, déjà utilisée partout) :
- code introuvable → « Ce code promo n'existe pas. »
- inactif / hors période → « Ce code promo n'est plus valide. »
- quota atteint → « Ce code promo a atteint son nombre maximum d'utilisations. »

### 3.3 Application/retrait côté panier

**Routes** (`routes/api.php`, dans le groupe `prefix('cart')` public) :
```php
Route::post('/gift-code', [CartController::class, 'applyGiftCode']);
Route::delete('/gift-code', [CartController::class, 'removeGiftCode']);
```

**`CartController`** :
- `applyGiftCode(ApplyGiftCodeRequest $request)` :
  1. `getOrCreateCart()` (même pattern que les autres méthodes cart).
  2. `GiftCodeService::resolve()` → 404 métier si introuvable.
  3. `preview()` sur le sous-total courant → si invalide, renvoyer l'erreur **sans** modifier le panier.
  4. `cart->update(['gift_code_id' => $code->id])`.
  5. Renvoyer `CartResponse` standard (résumé via `getCartSummary`, qui inclut désormais la remise).
- `removeGiftCode()` : `cart->update(['gift_code_id' => null])` → renvoyer le résumé.

**FormRequest** `api/app/Http/Requests/ApplyGiftCodeRequest.php` : `code => ['required','string','max:24']`.

### 3.4 Calcul serveur — `CartService::getCartSummary()` et `OrderService::createFromCart()`

**`CartService::getCartSummary()`** (`api/app/Services/CartService.php`) — ajout après le calcul de `$subtotal` / `$shippingFee` :
```php
$discount = 0.0;
$giftCode = null;
if ($cart->gift_code_id) {
    $code = GiftCode::find($cart->gift_code_id);
    // Auto-nettoyage : si le code n'est plus utilisable, on le retire silencieusement du panier.
    if ($code && app(GiftCodeService::class)->preview($code, $subtotal)['valid']) {
        $discount = $code->effectiveDiscount($subtotal);
        $giftCode = [
            'code'  => $code->code,
            'type'  => $code->type,            // 'fixed' | 'percent'
            'value' => (float) $code->value,   // euros ou %
        ];
    } else {
        $cart->update(['gift_code_id' => null]);
    }
}
// ... dans le tableau retourné :
'discount_amount' => $discount,
'gift_code'       => $giftCode,                 // null si aucun
'total'           => ($subtotal - $discount) + $shippingFee,
```

**`OrderService::createFromCart()`** (`api/app/Services/OrderService.php`) — c'est le **point de sécurité critique**. Dans la `DB::transaction` existante, après calcul de `$subtotal`/`$shippingFee` et **avant** `Order::create` :
```php
$discount = 0.0;
$giftCode = null;
if ($cart->gift_code_id) {
    // Verrou de ligne : sérialise les checkouts concurrents sur le même code.
    $giftCode = GiftCode::lockForUpdate()->find($cart->gift_code_id);
    if ($giftCode) {
        // throw BusinessException si quota atteint / hors période / inactif
        $discount = $this->giftCodeService->assertUsableForCheckout($giftCode, $subtotal);
    }
}
// Order::create([... ]) avec en plus :
'gift_code_id'    => $giftCode?->id,
'gift_code'       => $giftCode?->code,
'discount_amount' => $discount,
'subtotal'        => $subtotal,
'total'           => ($subtotal - $discount) + $shippingFee,
```

> Comme le quota ne compte que les commandes `paid`, une commande `pending` (même non expirée) ne bloque jamais le code. `expireAllPendingOrders()` reste utile pour invalider les anciens checkouts SumUp, mais n'a plus d'effet sur le quota.

> Injecter `GiftCodeService` dans le constructeur de `OrderService` (à côté de `SumUpService`/`InvoiceService`).

### 3.5 Cas du total à 0 € (remise ≥ sous-total, commande 100 % numérique)

SumUp **ne peut pas** créer un checkout à 0 €. **Décision retenue : flux « commande gratuite » en DEUX TEMPS avec page de double confirmation** (pas de finalisation instantanée — celle-ci créait des incohérences front/serveur : panier converti pendant que le front bloquait sur ses garde-fous).

1. **`POST /checkout`** (`OrderController::createFromCart`) : si `$order->total <= 0` → **ne pas** appeler `initiatePayment()`, **ne pas** finaliser. La commande reste **`pending`** (panier non converti, code non consommé — quota = `paid`). Réponse : `payment: { free: true, order_id, order_number }`.
2. **Front** : à la place du widget SumUp, l'étape 2 affiche un **écran de double confirmation** (« Votre code promo couvre l'intégralité de la commande — Montant à payer : 0,00 € ») avec un bouton **Confirmer ma commande**. « Modifier mes informations » / départ de la page → `cancelCheckout` classique (l'order pending passe `expired`, rien n'est consommé).
3. **`POST /checkout/confirm-free`** (`OrderController::confirmFreeOrder`, body `order_id`, validé par `OrderIdRequest`) : idempotent (déjà `paid` → succès), refuse un order non-`pending`, puis `OrderService::completeFreeOrder($order)` — réutilise `completeOrder()` (marque `paid`, `markAsConverted`, `generateDownloadToken`, facture, email) **sans** transaction SumUp, `transaction_id` = `free_<order_id>`.
4. **Front** : succès → toast + clear panier (best-effort) + redirection `/commande/{id}`.

**Garde-fous de sécurité (ne pas être exploitable)** — le total 0 € est entièrement dérivé serveur, donc non manipulable, mais on durcit :
- Le total `<= 0` est **recalculé serveur** dans la même transaction que la validation du code sous verrou (§3.4) — le client ne décide jamais qu'une commande est gratuite.
- `completeFreeOrder()` **revérifie** `$order->total <= 0` et `$order->isPending()` avant de finaliser (idempotent, `lockForUpdate`, refuse un order déjà payé) — comme `completeOrder()`.
- La remise ayant rendu le total nul **consomme une utilisation du code** (la commande est marquée `paid` par `completeFreeOrder` → comptée dans `paidCount`) → un code `max_uses` limité ne peut pas générer des commandes gratuites en boucle.
- `expireAllPendingOrders()` + le contrôle de quota sous verrou empêchent la création en masse de commandes gratuites concurrentes.
- Une commande gratuite reste soumise au même `download_token` / contrôle d'accès que les commandes payées (aucun bypass de téléchargement).

### 3.6 Admin — CRUD

**Routes** (`routes/api.php`, groupe `['auth:sanctum','admin']->prefix('admin')`) :
```php
// Codes Promo
Route::apiResource('gift-codes', \App\Http\Controllers\Api\Admin\GiftCodeController::class);
Route::get('/gift-codes/generate-code', [GiftCodeController::class, 'generateCode']); // helper code aléatoire
```

**`api/app/Http/Controllers/Api/Admin/GiftCodeController.php`** (calqué sur `ClientController`) :
- `index` : pagination + recherche par `code` + filtre statut. Pour chaque code, exposer `pending_count` (informatif), `paid_count` (= utilisations), `max_uses`, statut calculé (basé sur `paid_count`).
- `store` : crée le code (auto-génère si vide).
- `show` : code + liste des commandes consommatrices (id, order_number, total, status, created_at) pour le lien admin.
- `update` : modifie `type`, `value`, `max_discount_amount`, période, `max_uses`, `is_active`, et le `code` lui-même.
- `destroy` : **suppression en deux temps (décision retenue)** — on ne peut supprimer **que** un code déjà désactivé. Si `is_active === true`, renvoyer une `BusinessException` (« Désactivez le code avant de le supprimer. »). L'UI propose donc : *Désactiver* d'abord, puis *Supprimer* devient possible. Grâce à `nullOnDelete` sur `orders`/`carts`, les commandes passées restent intactes (snapshot `gift_code` + `discount_amount` conservé).
  - Prévoir une route/action **toggle** `PUT /admin/gift-codes/{giftCode}/toggle` (ou via `update` avec `is_active`) pour activer/désactiver.
- `generateCode` : renvoie un code 6 car. unique non persisté (pour pré-remplir le formulaire de création).

**FormRequests** `api/app/Http/Requests/Admin/StoreGiftCodeRequest.php` & `UpdateGiftCodeRequest.php` :
```php
'code'                => ['nullable','string','max:24','regex:/^[A-Z0-9-]+$/i', Rule::unique('gift_codes','code')->ignore($this->giftCode)],
'type'                => ['required', Rule::in(['fixed','percent'])],
// value : euros si fixed (min 0.01), pourcentage si percent (1–100)
'value'               => ['required','numeric','min:0.01', Rule::when($this->input('type') === 'percent', ['max:100'], ['max:100000'])],
'max_discount_amount' => ['nullable','numeric','min:0.01', Rule::prohibitedIf($this->input('type') !== 'percent')], // plafond € réservé au percent
'valid_from'          => ['nullable','date'],
'valid_until'         => ['nullable','date','after_or_equal:valid_from'],
'max_uses'            => ['nullable','integer','min:1'],
'is_active'           => ['sometimes','boolean'],
'note'                => ['nullable','string','max:2000'], // note interne admin, jamais exposée au client
```
> Normaliser `code` en MAJUSCULES dans `prepareForValidation()` ou le modèle.

---

## 4. Frontend Vue

### 4.1 Service `cartApi.ts`
`web/src/services/cartApi.ts` :
- Étendre l'interface `Cart` : `discount_amount?: number`, `gift_code?: { code: string; type: 'fixed' | 'percent'; value: number } | null`.
- Étendre `CheckoutResponse.order` : `discount_amount`, `gift_code`.
- Ajouter :
```ts
async applyGiftCode(code: string): Promise<CartResponse> {
    return this.cartRequest('/cart/gift-code', { method: 'POST', body: JSON.stringify({ code }) })
}
async removeGiftCode(): Promise<CartResponse> {
    return this.cartRequest('/cart/gift-code', { method: 'DELETE' })
}
```

### 4.2 Store `stores/cart.ts`
- Getters : `discount` (= `cart.discount_amount`), `appliedCode` (= `cart.gift_code`). `total` vient déjà du serveur (inchangé).
- Actions : `applyGiftCode(code)`, `removeGiftCode()` → appellent `cartApi`, mettent à jour `cart`, gèrent l'erreur (message renvoyé par l'API). Suivre exactement le pattern `addItem`/`setItemQuantity` (gestion `error`, pas de double init).

### 4.3 Vue `Cart.vue`
`web/src/views/Cart.vue` :
- Bloc « Code promo » dans le récapitulatif : `input` + **petit bouton icône (➔)** accolé au champ pour valider la saisie ; si un code est actif, afficher le code + montant + bouton **Retirer**.
- **Loader local** (`promoLoading`) : petit spinner qui tourne **dans le champ** (à droite) pendant l'appel réseau — le champ n'est pas grisé ; bouton désactivé le temps de l'appel. ⚠️ Piège CSS : `animate-spin` anime `transform`, donc ne jamais combiner avec `-translate-y-1/2` sur le même élément (la rotation est écrasée → spinner figé) ; le centrage se fait via un wrapper `inset-y-0 flex items-center`.
- Afficher une ligne **Remise** (`-X,XX €`) entre sous-total et total quand `discount > 0`.
- Messages d'erreur inline (code invalide/expiré/épuisé) via le watcher `cartStore.error` → toast.

### 4.4 Vue `Checkout.vue`
`web/src/views/Checkout.vue` :
- Afficher la ligne remise dans le récap.
- **Aucun changement de logique de sécurité nécessaire** : le code est déjà stocké sur le panier serveur ; `createOrder()` n'a **pas** besoin d'envoyer le code. Le garde-fou existant comparant `orderResponse.order.total` à `cartStore.total` continue de fonctionner (les deux incluent la remise) — **pour les commandes payantes uniquement**.
- Cas « commande gratuite » : si `payment.free === true`, afficher l'**écran de double confirmation** (étape 2, à la place du widget SumUp) **avant le garde-fou de totaux** — cet écran affiche lui-même le montant 0 €, il est donc auto-validant. Le clic « Confirmer ma commande » appelle `POST /checkout/confirm-free` puis redirige vers `/commande/{id}`. Le garde-fou de totaux ne concerne que les commandes payantes (montage du widget SumUp). *(Historique : la finalisation instantanée côté serveur bloquait l'utilisateur sur un panier vide avec « Panier modifié » — remplacée par ce flux en deux temps.)*

### 4.5 Admin — menu, route, vue, service

- **Menu** : `web/src/components/admin/AdminSidebar.vue` → ajouter dans `menuItems` :
  ```ts
  { path: '/admin/promo-codes', label: 'Codes Promo', icon: TicketIcon },
  ```
  (créer/réutiliser une icône dans `web/src/components/icons/` ; `GiftIcon` est déjà pris par les Bons Cadeaux → en choisir une distincte, ex. ticket/tag.)
- **Router** : `web/src/router/index.ts` → enfant sous `/admin` :
  ```ts
  { path: 'promo-codes', name: 'admin-promo-codes', component: AdminPromoCodes, meta: { title: 'Codes Promo' } }
  ```
- **Vue** : `web/src/views/admin/PromoCodes.vue` — calquée sur `web/src/views/admin/GiftCards.vue` (table, stats, modales create/edit/detail). Le formulaire propose :
  - sélecteur **Type** (`Montant fixe (€)` / `Pourcentage (%)`) ;
  - champ **Valeur** dont le suffixe (€ ou %) et les bornes suivent le type ;
  - champ **Plafond de remise (€)** affiché **uniquement** si type = pourcentage ;
  - bouton **Générer** un code (appelle `generateCode`) + saisie d'un code custom ;
  - période de validité, nb max d'utilisations, interrupteur actif.
  - boutons **Désactiver** puis **Supprimer** (supprimer désactivé si le code est inactif, cf. §3.6).
  - La modale détail liste les commandes liées, chacune cliquable vers `/admin/orders?search=<order_number>` — `Orders.vue` initialise sa recherche depuis `route.query.search` → la liste s'ouvre directement filtrée sur la commande (tracking « utilisé » avec lien vers la commande).
- **Service** : `web/src/services/admin/giftCodeApi.ts` (extends `BaseAdminService`) avec `getGiftCodes`, `getGiftCode`, `createGiftCode`, `updateGiftCode`, `toggleGiftCode`, `deleteGiftCode`, `generateCode` ; exposer via la façade `web/src/services/adminApi.ts`.
- **Types** : `web/src/types/admin.ts` → `AdminGiftCode` (id, code, type, value, max_discount_amount, valid_from, valid_until, max_uses, is_active, note, pending_count, paid_count, status, created_at) + type commande liée.
  - **Note interne** : champ `textarea` dans le formulaire create/edit + affichée dans la modale détail. Exposée uniquement par les endpoints `/admin/gift-codes/*` — **jamais** dans le résumé panier ni dans `OrderResource` → aucun risque de fuite côté client.

---

## 5. Sécurité & robustesse (checklist)

- [x] **Calcul 100 % serveur** : le client n'envoie que le code, jamais le montant. Remise et total recalculés serveur.
- [x] **Quota basé sur les commandes `paid` uniquement** : un panier `pending`/`failed`/`expired` ne consomme jamais le code. Re-validation au checkout (verrou de ligne pour la course webhook). *Limite assumée* : pas de réservation → dépassement possible de 1 sur paiements quasi-simultanés (choix produit, volume faible).
- [x] **SumUp reçoit `order->total` réduit** → impossible de payer le prix plein/réduit incohérent (montant figé serveur dans le checkout SumUp).
- [x] **Snapshot** `gift_code` + `discount_amount` sur l'order → facture/historique stables même après modif/suppression du code.
- [x] **Bornage** : remise toujours `<= subtotal` (et `<= max_discount_amount` en percent) → total jamais négatif ; remise jamais sur le port.
- [x] **Auto-nettoyage panier** : code devenu invalide retiré silencieusement à la lecture du panier (pas de checkout sur code périmé).
- [x] **Anti brute-force à deux couches** sur `POST /cart/gift-code` :
  1. **Throttle global** `throttle:gift-code` (20 req/min/IP, `AppServiceProvider`) — borne le débit brut, requêtes légitimes comprises.
  2. **Lockout sur échecs** (`CartController::applyGiftCode`) : compteur `RateLimiter` par IP qui n'incrémente que sur **tentative échouée** (code inexistant / invalide). Au-delà de **10 échecs**, blocage **10 min** (réponse 429 avec délai restant) + `Log::warning` pour monitoring. Le compteur est **remis à zéro** sur application réussie → un client légitime n'est jamais pénalisé.
  - Ordre de grandeur : codes générés = 6 car. sur alphabet de 32 → ~1,07 milliard de combinaisons ; à ~10 essais/10 min/IP, l'énumération est impraticable. Le vrai risque résiduel est le **code custom devinable** (ex. `NOEL2026`) face à une attaque par dictionnaire distribuée — le lockout par échec le couvre aussi par IP.
- [x] **Codes non ambigus & assez longs** : 6 car. alphanumériques majuscules, alphabet sans `O/0/I/1` (`ABCDEFGHJKLMNPQRSTUVWXYZ23456789`).
- [x] **Tests** (§6) sur le quota et le calcul.

---

## 6. Tests (recommandés — « robuste et sécurisé »)

> `phpunit.xml` est présent ; le dossier `tests/` est à (re)créer si absent. Cibler en priorité la logique critique.

Feature/Unit tests Laravel à écrire :
1. Application d'un code valide → `discount` correct dans le résumé panier.
2. Code expiré / inactif / hors période → refus + message.
3. `max_uses` basé sur le **payé** : un code `max_uses=1` avec 1 commande `paid` est refusé ; avec seulement une commande `pending`/`failed`/`expired` il reste **utilisable** (le panier abandonné ne brûle pas le code).
4. Statut admin : `paid_count` = utilisations ; `pending_count` affiché « en cours » mais sans impact quota/statut.
5. Calcul `fixed` et `percent` (avec/ sans plafond `max_discount_amount`) ; bornage `discount <= subtotal` (remise > panier).
6. Total à 0 € → chemin commande gratuite (ou refus selon §10).
7. SumUp reçoit bien le `total` réduit (mock `SumUpService`).
8. Suppression du code après commande → commande/facture intactes (snapshot).
9. Merge panier invité→user conserve `gift_code_id` (§8).
10. Anti brute-force : 10 tentatives échouées sur `POST /cart/gift-code` → 429 ; une application réussie remet le compteur à zéro ; un code valide reste applicable après des échecs < seuil.

Lint avant push : `vendor/bin/pint` (PHP) + `npm run lint` & `npm run typecheck` (front).

---

## 7. Points de compatibilité à vérifier (NE RIEN CASSER)

- **🐛 Bug pré-existant corrigé — asymétrie de résolution utilisateur panier/checkout** : les routes panier (publiques) utilisaient `$request->user()` (guard par défaut `web` → Bearer token **ignoré** → toujours « invité »), alors que `POST /checkout` résout via `Auth::guard('sanctum')`. Pour un client **connecté**, l'affichage panier travaillait donc sur un panier *session* que le checkout fusionnait dans son panier *utilisateur* (avec d'éventuels restes) avant de l'expirer → totaux divergents (« Panier modifié ») puis panier introuvable au refresh (« panier vide »). **Fix** : `CartController::resolveUser()` (`$request->user() ?? Auth::guard('sanctum')->user()`) utilisé par toutes les méthodes panier → affichage et checkout opèrent sur le **même** panier.

- **`InvoiceService`** (`api/app/Services/InvoiceService.php`) : afficher la ligne remise et utiliser `total` (pas `subtotal+shipping`). **À auditer/adapter** — sinon facture fausse.
- **`AdminOrderController::formatOrder()`** : ajouter `discount_amount` + `gift_code` au payload (utilisé par `OrderController::show`, `index`, `getByEmail` et le front). Ajout **additif**, non cassant.
- **Garde-fou total `Checkout.vue`** : OK, fonctionne tel quel (cf. §4.4).
- **`Cart::getTotalAttribute()`** (legacy, `sum('price')`, ignore quantité) : non utilisé par le flux réel (`getCartSummary` fait foi). Ne pas s'appuyer dessus ; ne pas le « corriger » dans cette PR.
- **Pack pricing** : indépendant — la remise s'applique après le `subtotal` déjà calculé avec paliers. Aucune interférence.
- **Emails de confirmation / notifications print** : vérifier l'affichage du total/remise dans les templates Mail si le détail prix y figure.
- **Bons Cadeaux (`GiftCard`)** : strictement non touchés.

---

## 8. Merge panier invité → utilisateur

`CartService::mergeGuestCart()` doit **reporter** `gift_code_id` du panier invité vers le panier user si ce dernier n'en a pas (sinon le code appliqué avant login est perdu). Ajout d'une ligne après la fusion des items :
```php
if ($guestCart->gift_code_id && ! $userCart->gift_code_id) {
    $userCart->update(['gift_code_id' => $guestCart->gift_code_id]);
}
```
Ajouter `gift_code_id` au `$fillable` de `Cart`.

---

## 9. Extensibilité (hors scope, à garder en tête)

- `min_order_amount` (montant minimum de commande pour appliquer le code).
- Restriction par galerie / type de produit.
- Limite « 1 par client » (via `user_id` / `guest_email` sur les redemptions).

Ces points ne sont **pas** implémentés maintenant ; le schéma proposé les accueille sans migration cassante.

---

## 10. Décisions validées

1. **Total à 0 €** → chemin **« commande gratuite » sécurisé** (§3.5). ✅
2. **Suppression d'un code** → **désactivation d'abord**, suppression possible uniquement sur un code désactivé (§3.6). ✅
3. **Format du code généré** → **sans caractères ambigus** (`O 0 I 1`) ; alphabet `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (§3.1). ✅
4. **Type de remise** → `fixed` (montant €) **et** `percent` (%) gérés dès maintenant, plafond € optionnel pour le percent (§1, §2.1). ✅
5. **Nombre max d'utilisations** → **défaut = 1** (DB `default(1)` + formulaire admin pré-rempli à 1). L'illimité reste possible en vidant le champ (`null`). ✅

---

## 11. Plan d'exécution (suivi d'avancement)

- [x] **1. Migrations** (§2) — `gift_codes`, `carts.gift_code_id`, `orders.gift_code_id/gift_code/discount_amount`.
- [x] **2. Modèle `GiftCode`** + `GiftCodeService` (§3.1, §3.2) + relations `Order->giftCode` / `Cart->giftCode`, fillables.
- [x] **3. Calcul serveur** : `getCartSummary` + `createFromCart` (injection service, verrou) + cas total 0 € `completeFreeOrder` (§3.4, §3.5).
- [x] **4. Routes & `CartController`** apply/remove + `ApplyGiftCodeRequest` + **throttle `gift-code`** (§3.3, §5).
- [x] **5. Admin backend** : `GiftCodeController` CRUD + toggle + generate-code + FormRequests + routes (§3.6).
- [x] **6. `OrderResource` (formatOrder) + facture** (ligne remise dans `invoice.blade.php`) (§7).
- [x] **9. Merge panier** : report `gift_code_id` invité→user (§8).
- [x] **7. Frontend client** : `cartApi`, store, `Cart.vue`, `Checkout.vue` (§4.1–4.4) — **typecheck + lint OK**.
- [x] **8. Frontend admin** : menu (`TicketIcon`), route `promo-codes`, vue `PromoCodes.vue`, service `giftCodeApi`, types, badges de statut (§4.5) — **typecheck + lint OK**.
- [x] **10a. Test unitaire** du calcul de remise `effectiveDiscount` (`tests/Unit/GiftCodeDiscountTest.php`).
- [x] **10b. Pint + suite Feature** (base Postgres de test dédiée, cf. README).
- [x] **11. Vérif manuelle** du flux complet (création code admin → application panier → checkout SumUp sandbox → commande payée → tracking admin avec lien commande).

> **Tests & spécificités Postgres** : le code de commande utilise des fonctionnalités
> propres à PostgreSQL (`pg_advisory_xact_lock` dans `Order::generateOrderNumber`,
> `whereYear`, regex `SUBSTRING`) **incompatibles avec SQLite**. La suite tourne donc
> sur une base Postgres de test dédiée (voir la section « Tests » du `README`). Le test
> unitaire de calcul de remise (sécurité-critique) ne touche pas la DB.
>
> Commandes de validation :
> ```bash
> cd api && composer install && php artisan migrate && vendor/bin/pint && php artisan test
> ```

---

## 12. Récapitulatif des fichiers touchés

**Backend (nouveaux)**
- `database/migrations/2026_06_03_000001_create_gift_codes_table.php`
- `database/migrations/2026_06_03_000002_add_gift_code_to_carts_table.php`
- `database/migrations/2026_06_03_000003_add_gift_code_to_orders_table.php`
- `database/migrations/2026_06_03_000004_add_note_to_gift_codes_table.php` (ALTER idempotent — backfill `note` sur les bases ayant migré 000001 avant l'ajout du champ)
- `app/Models/GiftCode.php`
- `app/Services/GiftCodeService.php`
- `app/Http/Controllers/Api/Admin/GiftCodeController.php`
- `app/Http/Requests/ApplyGiftCodeRequest.php`
- `app/Http/Requests/Admin/StoreGiftCodeRequest.php`
- `app/Http/Requests/Admin/UpdateGiftCodeRequest.php`

**Backend (modifiés)**
- `app/Models/Cart.php` (fillable + relation `giftCode`)
- `app/Models/Order.php` (fillable + relation `giftCode`)
- `app/Services/CartService.php` (`getCartSummary` + `mergeGuestCart`)
- `app/Services/OrderService.php` (constructeur + `createFromCart` + éventuel `completeFreeOrder`)
- `app/Http/Controllers/Api/CartController.php` (apply/remove)
- `app/Http/Controllers/Api/OrderController.php` (cas total 0 €)
- `app/Http/Controllers/Api/Admin/OrderController.php` (`formatOrder`)
- `app/Services/InvoiceService.php` (ligne remise)
- `routes/api.php` (routes cart + admin + throttle)

**Frontend (nouveaux)**
- `web/src/views/admin/PromoCodes.vue`
- `web/src/services/admin/giftCodeApi.ts`
- `web/src/components/icons/TicketIcon.vue` (ou équivalent)

**Frontend (modifiés)**
- `web/src/services/cartApi.ts`
- `web/src/services/adminApi.ts`
- `web/src/stores/cart.ts`
- `web/src/views/Cart.vue`
- `web/src/views/Checkout.vue`
- `web/src/components/admin/AdminSidebar.vue`
- `web/src/router/index.ts`
- `web/src/types/admin.ts`
