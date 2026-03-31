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

| Categorie | Fichiers | Lignes |
|-----------|----------|--------|
| Controllers API | 16 | 3 320 |
| Controllers Admin | 6 | 724 |
| **Total Controllers** | **22** | **4 044** |
| Services | 7 | 1 939 |
| FormRequests | 19 | ~500 |
| API Resources | 2 | ~50 |
| Policies | 1 | ~60 |
| Events | 2 | ~30 |
| Listeners | 2 | ~100 |
| Traits | 2 | ~60 |
| Helpers | 1 | ~20 |

### Frontend (web/src/)

| Categorie | Fichiers | Lignes |
|-----------|----------|--------|
| Services API | 6 | 1 526 |
| Composables | 4 | 410 |
| Utilitaires | 5 | ~120 |
| admin/Galleries.vue | 1 | 1 290 |
| admin/EventGalleries.vue | 1 | 1 678 |

---

## Plan d'action — Prochaines etapes

Les items ci-dessous combinent les restes du refactoring technique et la todolist fonctionnelle du projet. Ils sont organises en phases d'implementation logiques.

**Dependances inter-phases :**
- **B4 → B1** : l'envoi des infos client a SumUp necessite les champs first_name/last_name ajoutes en B1
- **C → B + D** : l'audit securite/RGPD doit etre re-verifie une fois les Phases B et D terminees (nouveaux champs, nouveaux endpoints, droit a l'oubli etendu aux commandes)
- **D1 → B1** : le rattachement d'historique client beneficie d'avoir le schema Order finalise (guest_first_name/last_name)
- **G3 → H3** : la strategie de prerendering depend de la decision Nuxt — si Nuxt est adopte, Puppeteer/vite-ssg deviennent obsoletes
- **G2 ↔ A** : les Core Web Vitals sont directement lies aux optimisations de la Phase A

**Phases totalement independantes :** A, E, F
**Ordre recommande :** A → B → D → C → E/F (parallele) → G → H

---

### Phase A — Performance & chargement (OBJECTIF PRINCIPAL) -- FAIT

> Objectif : photos qui chargent vite, ajouts panier instantanes, navigation fluide.

#### A1. Bug panier : ajout bloque par le chargement des photos -- CORRIGE
- [x] **Cause identifiee** : `addItem()` faisait `await waitForInit()` qui bloquait tant que `cartApi.getCart()` n'avait pas fini. L'utilisateur percevait le bouton comme bloque par les photos, mais c'etait l'init cart qui bloquait.
- [x] **Fix** : retire le `await waitForInit()` de `addItem()`. Le backend `getOrCreateCart()` cree le panier automatiquement. L'ajout est maintenant instantane.

#### A2. Audit global des temps de chargement -- FAIT
- [x] Audit backend : eager-loads verifies sur tous les endpoints publics, confirmes OK
- [x] Audit frontend : code-splitting par route en place (29/30 routes lazy-loaded), MasonryGallery a deja un bon systeme de priorite de chargement (`fetchpriority="high"` + `loading="eager"` pour les premieres images)

#### A3. Optimisation backend (requetes & cache) -- CORRIGE
- [x] **Cache galleries publiques** : ajoute `Cache::remember("public_galleries_page_{$page}", 300, ...)` sur `GalleryController::index()`
- [x] **Double recalcul panier elimine** : `addItem()`, `updateItemType()`, `removeItem()` appelaient `recalculatePackPrices()` en interne, puis `getCartSummary()` le re-faisait. Retire les appels redondants dans les methodes de mutation — `getCartSummary()` est le seul point de recalcul.
- [x] **Limite downloadZip** : ajoute `->limit(500)` pour eviter les OOM sur les tres grosses galeries

#### A4. Optimisation frontend (images & assets) -- FAIT (partiel)
- [x] **`decoding="async"`** ajoute sur PhotoCard.vue (en plus du `loading="lazy"` deja present)
- [x] MasonryGallery a deja un excellent systeme : `fetchpriority`, `loading="lazy"/"eager"`, preload par lots, blur-up placeholders
- [ ] **Reste a faire** : `srcset` / `<picture>` pour servir des tailles adaptees (mobile vs desktop) — necessite des changements sur l'image proxy backend
- [ ] **Reste a faire** : images originales `/public/images/` (241 MB) — envisager `.gitignore`

#### A5. Optimisation frontend (JS & rendu) -- FAIT
- [x] Code-splitting par route OK (29/30 routes lazy-loaded)
- [x] Chunk `vendor-vue` separe (fait dans le refactoring precedent)
- [x] MasonryGallery : priority loading, intersection observer natif via `loading="lazy"`
- [x] Computed chains (sortedPhotos → lightboxImages) : verifiees OK — Vue les memoize correctement, le cout est uniquement au premier calcul

---

### Phase B — Commandes & paiement

#### B1. Formulaire de commande : nom + prenom obligatoires
- [ ] Separer en deux champs `guest_first_name` et `guest_last_name` dans le checkout
- [ ] Mettre a jour `CreateCheckoutRequest`, `OrderService::createFromCart()`, le modele Order, et Checkout.vue
- [ ] Migration BDD (split de la colonne `guest_name`)

#### B2. Admin — Copier le lien de commande
- [ ] Bouton "Copier le lien" dans la vue admin Orders → copie `{frontend}/commande/{id}?token={download_token}`
- [ ] Le download_token est deja dans `order.metadata`

#### B3. Admin — Re-trigger la verification de paiement
- [ ] Endpoint `POST /admin/orders/{order}/retry-payment` → appelle `OrderService::verifyAndUpdateOrder()`
- [ ] Si paiement confirme sur SumUp : generer token download, PDF facture, envoyer email confirmation
- [ ] Bouton correspondant dans la vue admin Orders

#### B4. Envoyer les infos client a SumUp *(necessite B1)*
- [ ] Modifier `SumUpService::createCheckout()` pour inclure `customer.email`, `customer.first_name`, `customer.last_name` dans le payload
- [ ] Necessite que les infos client soient disponibles dans l'Order au moment du checkout

---

### Phase C — Securite & RGPD *(re-verifier apres Phases B et D)*

#### C1. Audit de securite des formulaires
- [ ] Verifier que toutes les entrees passent par des FormRequests (19 crees, ~12 inline restantes)
- [ ] Verifier les `DB::raw()` avec entrees non sanitisees
- [ ] Verifier les en-tetes HTTP de securite (CSP, X-Frame-Options, X-Content-Type-Options)
- [ ] Verifier la config Sanctum (domaines stateful, CORS, expiration tokens)

#### C2. Audit RGPD
- [ ] Verifier consentement explicite sur tous les formulaires publics (deja en place via `gdpr_consent`)
- [ ] Verifier l'export GDPR (`ClientController::gdprExport()`) — complet ?
- [ ] Verifier le droit a l'oubli pour les commandes (pas seulement les reservations)
- [ ] Verifier la duree de retention des donnees (logs, sessions, tokens)
- [ ] Verifier que les cookies ne sont deposes qu'apres consentement

#### C3. Policies Supabase
- [ ] Configurer les Row Level Security (RLS) policies sur Supabase
- [ ] Defense en profondeur meme si l'API Laravel est le seul point d'acces

---

### Phase D — Compte client

#### D1. Rattachement automatique de l'historique *(beneficie de B1 fait en amont)*
- [ ] A la creation de compte, rattacher : commandes guest (`orders.guest_email`), galeries (`galleries.assigned_email`), reservations guest (`reservations.guest_email`)
- [ ] La logique existe partiellement dans `AuthController::register()` (galeries). L'etendre aux commandes et reservations

#### D2. Interface compte client
- [ ] Verifier que `/mon-compte` affiche achats, galeries, reservations
- [ ] Permettre le re-telechargement des photos achetees depuis le dashboard
- [ ] UX : achat en guest → creation de compte → tout se retrouve automatiquement

---

### Phase E — Cookies & consentement

#### E1. Consentement YouTube
- [ ] Bloquer les iframes YouTube tant que le consentement marketing n'est pas donne (placeholder + bouton)
- [ ] Le `CookieBanner.vue` gere deja analytics/marketing — verifier que le toggle bloque bien YouTube

#### E2. Revocation du consentement
- [ ] Verifier que "Modifier mes preferences" permet de revoquer
- [ ] Quand l'utilisateur refuse apres coup : supprimer cookies GA4, desactiver tracking, supprimer cookies tiers
- [ ] Tester le flow complet : accepter → naviguer → refuser → verifier arret tracking

---

### Phase F — Admin & fonctionnalites

#### F1. Publier/depublier les evenements
- [ ] Champ `is_published` (boolean, default true) sur Gallery (type event)
- [ ] Migration + modifier `EventGalleryController::index()` (public) pour filtrer
- [ ] Toggle dans l'admin EventGalleries

#### F2. Galerie parent : mode creation simplifie
- [ ] Option "Galerie parent" (checkbox) dans le formulaire de creation de galerie evenement
- [ ] Quand coche : rendre optionnels les champs non necessaires (photos, product types, etc.)
- [ ] Backend : valider que les champs obligatoires dependent du flag `is_parent`

---

### Phase G — SEO & prerendering

#### G1. Audit du prerendering actuel
- [ ] Verifier que toutes les pages publiques sont prerendues
- [ ] Verifier les balises meta (title, description, og:image) sur chaque page
- [ ] Verifier le sitemap.xml
- [ ] Tester avec Google Search Console

#### G2. Amelioration SEO technique
- [ ] Donnees structurees JSON-LD (schema.org/Service pour prestations, schema.org/Event pour evenements)
- [ ] Core Web Vitals (lie a la Phase A)

#### G3. Strategie de prerendering *(depend de la decision H3 — Nuxt ou non)*
- [ ] Evaluer des alternatives a Puppeteer : si migration vers Nuxt (Phase H), le SSR natif remplace le prerendering
- [ ] Sinon, evaluer `vite-ssg` (static site generation native Vite) comme alternative plus legere a Puppeteer

---

### Phase H — Infrastructure & deploiement (long terme)

#### H1. Migration Render → Vercel
- [ ] Vercel pour le frontend (SPA/SSR), backend Laravel sur un autre hebergeur (NAS, Render, Railway)
- [ ] Separer clairement le deploiement frontend et backend
- [ ] Adapter scripts de build et variables d'environnement

#### H2. Deploiement sur NAS
- [ ] Configuration Docker pour le NAS (docker-compose existe deja)
- [ ] Reverse proxy (Nginx/Traefik) + certificats SSL (Let's Encrypt)

#### H3. Evaluation SSR / Nuxt
- [ ] Evaluer le benefice : le prerendering couvre le SEO des pages statiques, le SSR apporterait un gain sur les pages dynamiques (galeries, evenements)
- [ ] Migration Vue SPA → Nuxt = refactoring majeur (routeur, layouts, composables serveur). Projet a part entiere
- [ ] Si Nuxt : le prerendering Puppeteer et le questionnement `vite-ssg` deviennent obsoletes (SSR natif)

---

### Reste technique du refactoring (priorite basse)

- [ ] **Tests** : 0% de couverture. PHPUnit pour services critiques, Vitest pour composables frontend
- [ ] **~12 validations inline restantes** : petites validations (1-2 regles) dans CartController, GiftCardController, SumUpPaymentController, ReservationController, AvailabilityController
- [ ] **Galleries.vue (1290 lignes) et EventGalleries.vue (1678 lignes)** : extraire PhotoUploadZone, PhotoGridManager, DeletePhotoModal
- [ ] **adminApi.ts (495 lignes)** : decouper par domaine
- [ ] **API Resources supplementaires** : GalleryResource, UserResource, ReservationResource
- [ ] **Policies supplementaires** : GalleryPolicy, ReservationPolicy
- [ ] **uploadService.ts (502 lignes)** : refactoring possible

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

---

*Rapport genere le 31 mars 2026.*
