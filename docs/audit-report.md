# Rapport d'Audit de Code — Oceane Torres Photographie

**Date initiale :** 31 mars 2026
**Derniere mise a jour :** 5 avril 2026
**Branche :** `refacto`
**Version projet :** 2.2.1

---

## Stack technique

| Couche   | Technologie           | Version                      |
|----------|-----------------------|------------------------------|
| Backend  | Laravel (PHP)         | 12.x (PHP ^8.2, runtime 8.4) |
| Frontend | Vue 3 + TypeScript    | Vue 3.4, TS 5.5              |
| State    | Pinia                 | 3.0                          |
| Routing  | Vue Router            | 4.6                          |
| Style    | Tailwind CSS          | 3.4                          |
| Build    | Vite                  | 6.4                          |
| BDD      | PostgreSQL (Supabase) | -                            |
| Stockage | MinIO (S3-compatible) | -                            |
| Paiement | SumUp                 | -                            |
| Auth     | Laravel Sanctum       | 4.2                          |

**Architecture :** Monorepo `/api` (Laravel REST API) + `/web` (Vue SPA) avec prerendering Puppeteer.

---

## Avancement des phases

| Phase                  | Statut               | Reste a faire          |
|------------------------|----------------------|------------------------|
| **A** Performance      | FAIT                 | Audit Lighthouse a faire  |
| **B** Commandes        | FAIT                 | -                         |
| **C** Securite/RGPD    | FAIT                 | RLS Supabase (manuel)     |
| **D** Compte client    | FAIT                 | -                         |
| **E** Cookies          | FAIT                 | -                         |
| **F** Admin + UX       | FAIT                 | -                         |
| **G** Performance/SEO  | FAIT                 | Prerender a executer      |
| **H** Infrastructure   | FAIT                 | -                         |
| **Tech** Refactoring   | Partiel              | 6 items (R1→R6)           |

---

## Phases terminees — Detail

### Phase A — Performance & chargement -- FAIT

- [x] Bug panier corrige (waitForInit retire de addItem)
- [x] Cache galleries publiques (5min)
- [x] Double recalcul panier elimine
- [x] Limite downloadZip (500 photos)
- [x] `decoding="async"` + `loading="lazy"` sur PhotoCard
- [x] Code-splitting route OK, vendor-vue separe

### Phase B — Commandes & paiement -- FAIT

- [x] Nom + prenom obligatoires (migration, FormRequest, frontend)
- [x] Admin : copier lien commande (regenere le token)
- [x] Admin : re-trigger verification paiement SumUp
- [x] Infos client envoyees a SumUp (email, first/last name)

### Phase C — Securite & RGPD -- FAIT

- [x] SQL injection corrigee (2 failles CASE/WHEN → bindings parametrises)
- [x] Token Sanctum : expiration 30 jours
- [x] Middleware SecurityHeaders global
- [x] CORS methods restreint
- [x] Droit a l'oubli etendu (commandes, contacts, download logs)
- [x] Tache hebdomadaire retention RGPD (console.php)

### Phase D — Compte client -- FAIT

- [x] Rattachement auto historique (commandes + reservations + galeries par email)
- [x] Onglet "Mes achats" dans le dashboard client
- [x] Bouton "Voir / Telecharger" sur commandes payees

### Phase E — Cookies & consentement -- FAIT

- [x] Iframe YouTube bloquee sans consentement marketing (placeholder + bouton "Accepter et regarder")
- [x] Flow de revocation verifie et corrige (accepter → refuser → arret effectif)
- [x] Bug corrige : suppression cookies marketing independante du choix analytics
- [x] Bug corrige : pas de page_view envoye quand analytics desactive
- [x] Bouton "Cookies" dans le footer pour modifier ses preferences

---

## Reste a faire des phases terminees — TOUT FAIT

- [x] **C2.** Bouton "Cookies" dans le footer
- [x] **C2.** Export GDPR enrichi (commandes, contacts, download logs)
- [x] **C1.** 27 validations inline → 25 FormRequest classes (0 inline restant)
- [x] **D2.** Page detail commande client `/mon-compte/commande/:id` + telechargement individuel

### Reporte / hors scope

- **C3. Policies RLS Supabase** — Action manuelle sur le dashboard Supabase, hors code
- **A4. Images responsives** — A evaluer lors de l'audit Lighthouse (Phase G)
- **A4. Images originales (241 MB)** — Envisager `.gitignore` ou stockage externe, pas bloquant

---

## Audit tracking & analytics

### Etat actuel du tracking

| Fonctionnalite                    | Statut          | Detail                                                                                                               |
|-----------------------------------|-----------------|----------------------------------------------------------------------------------------------------------------------|
| Vues galeries                     | OK              | `recordView()` sur toutes les methodes : show, showByToken, showByShareCode, showDownloadableByToken                 |
| Telechargements photos (gratuits) | OK              | `recordDownload()` appele dans PhotoController et GalleryController (ZIP)                                            |
| Telechargements photos (achats)   | OK              | `recordDownload()` ajoute dans OrderController::downloadPhoto() et downloadAll()                                     |
| Likes photos                      | Suffisant       | Boolean `is_liked` par photo — suffisant pour le besoin (favoris photographe)                                        |
| Commandes                         | OK              | Order, OrderItem, Payment en BDD + evenement GA4 `purchase`                                                          |
| Revenus dashboard                 | Partiel         | Total et mensuel OK. **Manque** : revenu par galerie, par type de produit                                            |
| Panier                            | OK              | Status BDD + evenements GA4 `add_to_cart`, `remove_from_cart`                                                         |
| GA4                               | OK              | `page_view` + e-commerce complet : `add_to_cart`, `remove_from_cart`, `begin_checkout`, `purchase`                   |
| Stats admin                       | Partiel         | Reservations, paiements, revenus mensuels. **Manque** : revenu par galerie/produit, taux conversion panier           |

### Plan de correction tracking (a integrer dans les prochaines phases)

#### ~~T1. Corriger le tracking des telechargements d'achats~~ FAIT

- [x] Dans `OrderController::downloadPhoto()` : `$photo->recordDownload($request->ip(), $request->userAgent())` ajoute
- [x] Dans `OrderController::downloadAll()` : `$photo->recordDownload($request->ip(), $request->userAgent())` ajoute
- [x] Resultat : `photo.downloads_count` et `download_logs` refletent TOUS les telechargements (gratuits + achats)

#### ~~T2. Corriger les vues galeries manquantes~~ FAIT

- [x] Dans `GalleryController::show()` : `$gallery->recordView()` ajoute pour les galeries publiques
- [x] Dans `GalleryController::showByToken()` : `$gallery->recordView()` ajoute pour les galeries privees par token

#### ~~T3. Evenements GA4 e-commerce~~ FAIT

- [x] `add_to_cart` : envoye depuis `AddToCartButton.vue` apres ajout reussi
- [x] `remove_from_cart` : envoye depuis `Cart.vue` apres suppression reussie
- [x] `begin_checkout` : envoye depuis `Checkout.vue` au montage du composant
- [x] `purchase` : envoye depuis `OrderConfirmation.vue` quand la commande est payee (une seule fois par chargement)
- [x] Consentement respecte : composable `useGtag.ts` verifie `analyticsEnabled` avant chaque envoi

#### T4. Stats admin complementaires (priorite basse)

- [ ] Revenu par galerie / par type de produit (digital vs print)
- [ ] Taux de conversion panier → commande payee

~~#### T5. Amelioration du systeme de likes~~ RETIRE — systeme actuel suffisant pour le besoin

---

## Phases restantes a implementer

### Phase E — Cookies & consentement -- FAIT

#### E1. Consentement YouTube

- [x] Bloquer les iframes YouTube tant que le consentement marketing n'est pas donne
- [x] Afficher un placeholder avec bouton "Accepter et regarder" dans `Lightbox.vue`
- [x] Bouton accepte le marketing et charge l'iframe immediatement

#### E2. Revocation du consentement

- [x] Verifier le flow : accepter → naviguer → refuser → verifier arret tracking
- [x] Supprimer cookies GA4, desactiver tracking, supprimer cookies tiers
- [x] Bug corrige : `savePreferences()` supprime maintenant les cookies marketing meme si analytics reste actif
- [x] Bug corrige : `sendPageViewAfterConsent()` n'est plus envoye quand analytics est desactive

---

### Phase F — Admin & UX -- FAIT

#### F1. Publier/depublier les evenements

- [x] Migration `is_published` (boolean, default true) sur galleries
- [x] Filtre `where('is_published', true)` dans `EventGalleryController::index()` et `show()`
- [x] Enfants filtres par `is_published` dans la vue parent
- [x] Toggle publier/depublier dans l'admin EventGalleries (icone oeil, badge "Brouillon")
- [x] `is_published` ajoute aux FormRequests et types TypeScript

#### F2. Galerie parent : mode creation simplifie

- [x] Checkbox "Galerie parent" dans le formulaire de creation (top-level uniquement)
- [x] Section product types masquee en mode parent (pas de photos directes)
- [x] Mode parent detecte automatiquement en edition (si `children_count > 0`)

#### F3. Inciter a la creation de compte

- [x] Encart post-commande dans `OrderConfirmation.vue` (guests uniquement)
- [x] Lien vers creation de compte avec pre-remplissage email
- [x] Benefices mis en avant : historique, re-telechargement, galeries privees

---

### Phase G — Performance, SEO & prerendering -- FAIT

*OBJECTIF PRINCIPAL : temps de chargement rapides, photos qui s'affichent bien, panier instantane.*

#### G1. Performance / Core Web Vitals

- [x] Hero image converti en `<picture>` AVIF/WebP/PNG (LCP -300-500ms)
- [x] Preload hero corrige (AVIF au lieu du PNG inutilise)
- [x] `aspect-ratio` sur MasonryGallery et PhotoCard (CLS -0.10+)
- [x] `width`/`height` sur persona image (CLS)
- [x] Lightbox thumbnails utilisent `thumbnailUrl` au lieu du full-res (bande passante -80%)
- [x] Navbar hauteur fixe `h-20` + transition limitee aux couleurs (CLS -0.05)
- [x] Spacer `h-20` dans App.vue pour le contenu sous la navbar fixe

#### G2. SEO technique

- [x] Meta tags OG (og:title, og:url) et Twitter (twitter:title, twitter:description) mis a jour dynamiquement par route
- [x] Accents francais corriges dans les descriptions des routes evenements
- [x] Sitemap complete : `/politique-confidentialite` ajoute
- [x] Page 404 creee (`NotFound.vue`) au lieu de la redirection silencieuse
- [x] JSON-LD deja complet (LocalBusiness, Person, WebSite, OfferCatalog)

#### G3. Prerendering

- [x] Script Puppeteer existant et fonctionnel (11 routes)
- [ ] **A EXECUTER** : `npm run build:prerender` avant deploiement

#### G4. Strategie de prerendering *(depend de H4 — Nuxt ou non)*

- [ ] Si Nuxt → SSR natif remplace Puppeteer
- [ ] Sinon → evaluer `vite-ssg` comme alternative legere

---

### Phase H — Infrastructure & deploiement

#### H1. Fix images portfolio Chrome/Opera -- FAIT

- [x] Portfolio passe en AVIF primary + WebP fallback (gallery.ts : `previewUrl` = AVIF, `url` = WebP)
- [x] Fallback chain MasonryGallery gere automatiquement AVIF → WebP si AVIF echoue
- [x] Lightbox utilise aussi AVIF en priorite via `previewUrl`
- [x] Resultat estime : ~30% de bande passante en moins, meme qualite visuelle

#### H2. Restructuration deploiement NAS -- FAIT

- [x] `deploy/` versionne dans git (seuls `.env.prod` et `.env.deploy` ignores)
- [x] `deploy/docker-compose.prod.yml` : paths relatifs depuis la racine du repo (`./api`, `./deploy/`)
- [x] `deploy/nginx.prod.conf` : config Nginx production versionnee
- [x] `deploy/deploy.sh` : script avec `git pull` + build + migrate + cache (options `--no-pull`, `--no-build`)
- [x] `deploy/.env.prod.example` : template documente sans secrets
- [x] `deploy/docker/php.ini` + `deploy/docker/www.conf` : configs PHP versionnees
- [x] `deploy/README.md` : nouveau flow simplifie

**Nouveau workflow deploiement :**
```
Premier deploiement : git clone → cp .env.prod.example .env.prod → remplir → ./deploy/deploy.sh --no-pull
Mises a jour :        ./deploy/deploy.sh
```

#### ~~H3. Certificats SSL + reverse proxy~~ RETIRE

- Cloudflare Tunnel gere deja le SSL automatiquement
- HSTS header deja en place dans le middleware SecurityHeaders Laravel
- Rien a faire

#### ~~H4. Evaluation SSR / Nuxt~~ RETIRE

- Ratio cout/benefice trop faible pour un site de photographe (~10 pages statiques)
- Le prerendering Puppeteer + les fixes SEO (Phase G) couvrent le besoin
- Alternative `vite-ssg` disponible si besoin futur

---

### Reste technique du refactoring (priorise)

#### ~~R1. Policies supplementaires~~ FAIT

- [x] `GalleryPolicy` : view (public/event/token/owner), download (delegue a view)
- [x] `ReservationPolicy` : view, update, delete (ownership + business rule confirmed)
- [x] **Bug securite corrige** : ReservationController show/update/destroy n'avaient aucune verification de propriete — tout utilisateur connecte pouvait acceder aux reservations des autres
- [x] GalleryController::downloadZip utilise maintenant GalleryPolicy::download

#### ~~R2. Decouper adminApi.ts~~ FAIT

- [x] 505 lignes → 7 sous-services dans `services/admin/` (baseAdmin, dashboard, reservation, client, prestation, gallery, order)
- [x] Plus gros fichier : `galleryApi.ts` (146 lignes)
- [x] Facade `adminApi.ts` (97 lignes) re-exporte tout — zero import casse

#### R3. Extraire sous-composants des vues admin *(priorite 3 — maintenabilite, ~2h)*

- [ ] `Galleries.vue` (1290 lignes) : extraire PhotoUploadZone, GalleryFormModal, GalleryDrillDown
- [ ] `EventGalleries.vue` (1732 lignes) : extraire EventGalleryCard, EventGalleryFormModal, CategoryManager
- [ ] Objectif : chaque fichier < 500 lignes

#### R4. API Resources supplementaires *(priorite 4 — coherence, ~30min)*

- [ ] `GalleryResource` : remplacer le formatage inline dans GalleryController et EventGalleryController
- [ ] `ReservationResource` : remplacer le formatage inline dans ReservationController
- [ ] Existant : OrderResource, OrderItemResource (deja faits)

#### R5. uploadService.ts *(priorite 5 — optionnel, ~1h)*

- [ ] Fichier actuel : 502 lignes — complexite justifiee (chunked upload, retry, progress)
- [ ] Refactoring possible : extraire chunk builder et retry logic en utils separees
- [ ] **Non prioritaire** : le service fonctionne correctement

#### R6. Tests *(priorite 6 — long terme, progressif)*

- [ ] 0% de couverture actuelle (3 fichiers test vides)
- [ ] Backend : PHPUnit pour OrderService, CartService, PricingService, ImageProcessingService
- [ ] Frontend : Vitest pour useGtag, useProductTypes, useConfirm, consent store
- [ ] A faire progressivement, pas en un bloc

---

## Score de sante

| Critere                    | Avant                                                 | Apres                                            |
|----------------------------|-------------------------------------------------------|--------------------------------------------------|
| Code mort                  | Stripe/PayPal/CartIcon/types                          | Nettoye                                          |
| Duplication                | ~400+ lignes                                          | Centralise (traits, composables, BaseApiService) |
| Fichiers geants            | GalleryController 877, OrderController 559            | 440 et 319                                       |
| Validation                 | 0 FormRequest, 43+ inline                             | 44 FormRequests, 0 inline                        |
| Autorisation               | 0 Policy                                              | 3 Policies (Order, Gallery, Reservation)         |
| Separation responsabilites | Emails inline, pricing dans modele                    | Events/Listeners, PricingService                 |
| Performance N+1            | 2 critiques                                           | Corriges (batch, withCount)                      |
| Securite                   | SQL injection, pas de headers, tokens sans expiration | Corrige (bindings, middleware, 30j)              |
| RGPD                       | Droit oubli partiel, pas de retention                 | Etendu (commandes/contacts), retention hebdo     |
| Compte client              | Pas de commandes, rattachement partiel                | Dashboard complet, rattachement auto             |
| Commandes                  | 1 champ nom, pas de retry admin                       | Nom+prenom, copier lien, retry, infos SumUp      |

**Score estime : 5.5/10 → 8/10**

---

*Rapport mis a jour le 5 avril 2026.*
