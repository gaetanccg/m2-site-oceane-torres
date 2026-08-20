# Workflow de paiement SumUp — guide technique

Comment fonctionne le paiement par carte (widget SumUp), quelles décisions
d'architecture ont été prises et **pourquoi**, et les pièges de l'intégration SDK /
webhook. Sert de référence pour comprendre et faire évoluer le flux de checkout.

---

## 1. Architecture globale

```
[Vue Checkout.vue]
    │  POST /api/checkout        (createFromCart + initiatePayment)
    ▼
[OrderController::createFromCart]
    │  → expireAllPendingOrders → Order::create → SumUp::createCheckout → Payment::create
    ▼
[SumUp Card Widget (frontend)]
    │  Saisie carte → 3DS éventuel
    ▼
┌─────────────────────────────────────────────────────────────┐
│ 3 chemins de finalisation possibles, tous idempotents :      │
│                                                              │
│ A. Widget postMessage → onResponse('success')                │
│    → POST /api/payments/sumup/verify (polling frontend)      │
│                                                              │
│ B. Redirection navigateur après 3DS                          │
│    → GET  /api/payments/sumup/return  → browserReturn()      │
│                                                              │
│ C. Webhook SumUp                                             │
│    → POST /api/payments/sumup/return  → webhook()            │
└─────────────────────────────────────────────────────────────┘
    │  Tous appellent verifyAndUpdateOrder ou completeOrder
    ▼
[OrderService::completeOrder] — DB transaction + lockForUpdate
    → markAsPaid, Payment.completed, Cart.converted, downloadToken
    → (hors transaction) InvoiceService + Mail::queue
```

### Fichiers clés

| Rôle                      | Fichier                                              |
|---------------------------|------------------------------------------------------|
| API SumUp (wrapper HTTP)  | `api/app/Services/SumUpService.php`                  |
| Controller paiement       | `api/app/Http/Controllers/Api/SumUpPaymentController.php` |
| Orchestrateur order       | `api/app/Services/OrderService.php`                  |
| Création order depuis cart| `api/app/Http/Controllers/Api/OrderController.php`   |
| Modèle Order              | `api/app/Models/Order.php`                           |
| Modèle Payment            | `api/app/Models/Payment.php`                         |
| Config SumUp              | `api/config/sumup.php`                               |
| Frontend checkout         | `web/src/views/Checkout.vue`                         |
| Frontend confirmation     | `web/src/views/OrderConfirmation.vue`                |
| Frontend services         | `web/src/services/cartApi.ts`                        |

### Statuts métier

- `Order.status` : `pending` → `paid` | `failed` | `refunded` | `expired`
- `Payment.status` : `pending` → `completed` | `failed` | `refunded`
- SumUp checkout : `PENDING` | `PAID` | `FAILED` | `EXPIRED`

---

## 2. Ce qui est correctement implémenté (et pourquoi)

- **Webhook + redirection unifiés** sur la même URL `POST /api/payments/sumup/return`.
  Auparavant `return_url` pointait sur le SPA statique, qui avalait silencieusement le
  POST du webhook → les commandes dépendant du webhook restaient `pending`. Corrigé par
  le commit `8f1e5b9` (27 mai 2026).
- **`completeOrder` idempotent** : `DB::transaction` + `lockForUpdate` — pas de double
  exécution si webhook et polling arrivent en parallèle.
- **Emails envoyés uniquement si `$justCompleted`** dans le thread courant → pas de
  doublon de mail de confirmation.
- **Le webhook ne fait jamais confiance au payload** : il recale toujours le statut via
  `getCheckout()` côté SumUp. C'est la sécurité principale (voir §3.4).
- **`checkout_reference` unique par tentative** (`order->id . '_' . Str::random(8)`) :
  contourne le refus SumUp des références dupliquées.
- **`expireAllPendingOrders` au début de chaque checkout** : empêche qu'un ancien widget
  figé sur un panier obsolète soit payé.
- **Frontend : watchdog + bouton manuel** pour les cas où le widget reste figé après 3DS
  (in-app browsers, postMessage cross-origin cassé).
- **CSRF désactivé sur `api/*`** (`bootstrap/app.php`) → le webhook POST arrive bien.
- **Sync forcé du cart** au mount du Checkout → l'utilisatrice ne voit pas un montant
  différent de ce qui sera facturé.

---

## 3. Problèmes traités (problème → correctif → pourquoi)

### 3.0 — Events SDK mal nommés / non écoutés

**Problème** : le code écoutait `auth-screen-displayed`, un event **qui n'existe pas**
dans le SDK. Les events `fail` et `invalid` n'étaient pas gérés → après un échec
utilisateur, le widget restait figé sans message ; l'utilisatrice abandonnait, le
checkout expirait, le webhook marquait `FAILED`.

**Correctif** (`web/src/views/Checkout.vue`) :
- `auth-screen-displayed` → `auth-screen` (le watchdog 3DS s'arme réellement) ;
- handler `fail` : affiche `body.message` et libère `isPaymentProcessing` (retry sans
  recharger) ;
- handler `invalid` : libère `isPaymentProcessing` (le widget affiche déjà l'erreur inline).

**Les 7 vrais events du SDK** (doc officielle + inspection du bundle
`gateway.sumup.com/gateway/ecom/card/v2/sdk.js`) :

| Event         | Body                                              | Rôle                                  |
|---------------|---------------------------------------------------|---------------------------------------|
| `loaded`      | —                                                 | widget monté                          |
| `sent`        | 4 derniers chiffres + scheme                      | submit envoyé (arme le watchdog)      |
| `invalid`     | `{message, error_code, field}`                    | validation locale (ex. CVV)           |
| `auth-screen` | `{iframe_redirect, next_step, …}`                 | 3DS / SCA affiché                     |
| `success`     | réponse endpoint                                  | ⚠️ **ne garantit PAS** que c'est PAID |
| `fail`        | `{message}`                                       | timeout / annulation utilisateur      |
| `error`       | message d'erreur                                  | erreur serveur                        |

> `auth-screen-displayed` **n'existe pas** dans le SDK. `success` ne garantit pas le
> paiement : toujours revérifier côté serveur via `getCheckout()`.

### 3.1 — Race entre webhook `PAID` et `expireAllPendingOrders`

**Problème** : `expireAllPendingOrders` lisait les orders `pending` (T1), désactivait
leurs checkouts SumUp (T2), puis faisait un `update(status=expired)` global (T3). Si un
webhook `PAID` passait un order à `paid` entre T1 et T3, le T3 **écrasait** `paid` en
`expired` (le `lockForUpdate` de `completeOrder` ne protège que sa propre transaction).

**Correctif** : ajouter `->where('status', 'pending')` à l'`update()` final + logguer le
nombre de lignes réellement affectées. Même classe de bug corrigée dans
`cancelCheckout` (fenêtre encore plus large à cause du round-trip `deactivateCheckout`) :
si `affected === 0`, on rafraîchit l'order et on renvoie un `409 already_paid` explicite
plutôt que de mentir au front.

### 3.2 — Le webhook acquittait toujours en 200

**Problème** : sur exception (timeout API SumUp, deadlock DB), le webhook loggait et
renvoyait **200**. SumUp ne retentait donc pas → la commande restait `pending`
indéfiniment.

**Correctif en deux temps** :
1. **Webhook** : distinguer les cas. Idempotents / non-rejouables → 200 (`received: true`) :
   payload sans `id`, order introuvable, order déjà `paid`. Toute **exception transiente**
   → **503** (`{received: false, error: 'transient'}`) pour déclencher les retries SumUp.
2. **Réconciliation périodique** (`OrderService::reconcilePendingOrders`, planifiée toutes
   les 10 min) : scrute les orders `pending` avec `sumup_checkout_id` créées entre 15 min
   et 24 h, appelle `getCheckout()` et applique `PAID` → `completeOrder`, `FAILED` →
   `handleFailedPayment` (garde `isPending`). Filet de sécurité si un webhook est perdu.

### 3.5 — Commentaire `.env.prod` périmé (sandbox vs live)

Commentaire ambigu remplacé : les clés de prod sont **live**. Plus de confusion sur le
statut sandbox/live.

### 3.6 — Code mort de re-création de checkout

`cartApi.createSumUpCheckout` / `getSumUpConfig` n'étaient plus appelés dans `web/src/`
(seulement dans le bundle compilé `dist/`). Le contrôleur associé re-créait un checkout
sans désactiver l'ancien ni mettre à jour `payments`. Supprimé côté front (type
`SumUpConfig`, 2 méthodes), back (`getConfig`, `createCheckout`) et routes
(`GET /payments/sumup/config`, `POST /payments/sumup/create-checkout`). Les méthodes
utilisées (`callback`, `verifyPayment`, `cancelCheckout`, `browserReturn`, `webhook`)
restent intactes.

### 3.8 — Pas de signature webhook → rate-limit

**Constat** : la doc SumUp ne documente **aucun** mécanisme HMAC/signature. Le webhook
est public et non signé.

**Mitigation** : rate-limiter `throttle:sumup-webhook` (60/min/IP) sur
`POST /payments/sumup/return`. Large pour le trafic légitime (+ retries), bloque le spam
massif depuis une IP. Ne protège pas d'une attaque distribuée — mais le re-check
`getCheckout()` côté serveur empêche de toute façon d'injecter un faux `PAID`.

### 3.9 — Parsing du payload webhook (confirmé OK)

Payload `{event_type: "CHECKOUT_STATUS_CHANGED", id: "<checkout_id>"}`. Le code lit
`$payload['id'] ?? $payload['checkout_id']` — correct, et robuste à un futur event sans
cette clé (court-circuit sur `id` manquant → 200).

---

## 4. Sécurité — l'invariant central

Le webhook et le polling **ne font jamais confiance** au statut annoncé côté client. Ils
rappellent systématiquement `SumUpService::getCheckout()` pour recaler le statut réel.
Conséquence : impossible d'injecter un faux `PAID`. Toute la surface (rate-limit, absence
de signature) est secondaire face à cet invariant.

Documentation SumUp (verbatim) :

> « Your application must **always** verify if the event really took place, by calling a
> relevant SumUp's API. »
>
> « If your server returns a response other than 2xx, SumUp will retry sending the webhook
> with the following intervals: 1 minute, 5 minutes, 20 minutes, 2 hours. »
>
> « New events may be introduced at any time, without prior notice. »

---

## 5. Diagnostiquer un taux d'échec

La table `orders` permet de distinguer les causes d'échec sans instrumentation
supplémentaire.

- **`sumup_transaction_id IS NULL`** sur un order `failed` → **aucune** transaction n'a
  été créée côté SumUp : decline émetteur/scheme immédiat (carte invalide, CVV, fonds,
  refus fraude). Rien à corriger côté code.
- **Delta `updated_at − created_at`** :
  - **< 1 min** → decline immédiat (émetteur).
  - **5–15 min** → 3DS abandonné ou widget figé (cible du correctif §3.0).
  - **> 1 h** → checkout expiré (jamais payé).

```sql
SELECT status,
       EXTRACT(EPOCH FROM (updated_at - created_at)) AS seconds_to_resolution,
       count(*)
FROM orders
WHERE status = 'failed'
  AND created_at > '2026-05-27'   -- après le fix return_url (8f1e5b9)
GROUP BY 1, 2
ORDER BY 2;
```

Compléter par le **dashboard SumUp** (motif exact : decline reason / status code) et le
**user-agent** des orders `failed` (part d'in-app browsers, cf. §6).

---

## 6. Limites connues & pistes

- **`checkout_reference`** = UUID (36) + `_` + 8 = **45 caractères**, dont les `-` du
  UUID. La doc SumUp est muette sur la limite/les caractères ; nos paiements réussissent
  donc c'est accepté en pratique. Pour lever tout doute : format strict alphanumérique
  (`Str::lower(Str::random(20))`). À ne faire que si des `SumUp checkout creation failed`
  apparaissent en logs.
- **`consent_ip = 172.19.0.1`** (IP réseau Docker interne) au lieu de l'IP cliente réelle
  → la chaîne Cloudflare Tunnel → nginx → php-fpm ne remonte pas `CF-Connecting-IP` /
  `X-Forwarded-For` jusqu'à `request->ip()`. Impact RGPD (preuve d'acceptation CGV), pas
  paiement. Correctif = lire `CF-Connecting-IP` (config proxies Laravel / nginx), PR
  dédiée avec tests.
- **In-app browsers** (Instagram/TikTok/Facebook) : postMessage cross-origin parfois
  cassé → widget figé. Piste : détecter le user-agent et inviter à « ouvrir dans
  Safari/Chrome ».
- **Watchdog `AUTH_STUCK_TIMEOUT_MS`** (~45 s) : potentiellement court pour un 3DS SMS
  (60–90 s). Si des redirections prématurées vers `/commande/{id}` sont signalées en plein
  3DS, passer à 90 s.
- **`personal_details` SumUp incomplets** : on n'envoie ni téléphone ni adresse de
  facturation. Effet sur le scoring 3DS/SCA non documenté ; à tester si on veut améliorer
  le taux de conversion.

---

## 7. Points de surveillance en prod (logs Laravel)

- `SumUp browser return` — vérifier que `?order=X&checkout_id=Y` arrive concaténé
  correctement (comportement `return_url` non documenté par SumUp).
- `SumUp webhook processing error — returning 503 for retry` — fréquence anormale =
  vraie cause à traiter.
- `Reconciliation completed` — si on récupère régulièrement des `PAID`/`FAILED`, c'est
  que des webhooks sont perdus → investiguer.
- `SumUp checkout creation failed` — piste `checkout_reference` (§6).
