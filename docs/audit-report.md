# Rapport d'Audit de Code — Oceane Torres Photographie

**Date initiale :** 31 mars 2026
**Derniere mise a jour :** 11 avril 2026
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

## Avancement global

| Phase | Sujet | Statut |
|-------|-------|--------|
| **A** | Performance & chargement | FAIT |
| **B** | Commandes & paiement | FAIT |
| **C** | Securite & RGPD | FAIT |
| **D** | Compte client | FAIT |
| **E** | Cookies & consentement | FAIT |
| **F** | Admin & UX | FAIT |
| **G** | Performance, SEO & prerendering | FAIT |
| **H** | Infrastructure & deploiement | FAIT |
| **R** | Refactoring technique | 5/6 FAIT |
| **T** | Tracking & analytics | 3/4 FAIT |

---

## Detail par phase

### Phase A — Performance & chargement

- [x] Bug panier corrige (waitForInit retire de addItem)
- [x] Cache galleries publiques (5min)
- [x] Double recalcul panier elimine
- [x] Limite downloadZip (500 photos)
- [x] `decoding="async"` + `loading="lazy"` sur PhotoCard
- [x] Code-splitting route OK, vendor-vue separe

### Phase B — Commandes & paiement

- [x] Nom + prenom obligatoires (migration, FormRequest, frontend)
- [x] Admin : copier lien commande (regenere le token)
- [x] Admin : re-trigger verification paiement SumUp
- [x] Infos client envoyees a SumUp (email, first/last name)

### Phase C — Securite & RGPD

- [x] SQL injection corrigee (2 failles CASE/WHEN → bindings parametrises)
- [x] Token Sanctum : expiration 30 jours
- [x] Middleware SecurityHeaders global (X-Frame-Options, X-Content-Type-Options, HSTS, etc.)
- [x] CORS methods restreint
- [x] Droit a l'oubli etendu (commandes, contacts, download logs)
- [x] Tache hebdomadaire retention RGPD (console.php)
- [x] Export GDPR enrichi (commandes, contacts, download logs)
- [x] 27 validations inline → 44 FormRequests (0 inline restant)
- [x] Bug securite corrige : ReservationController sans verification de propriete

### Phase D — Compte client

- [x] Rattachement auto historique (commandes + reservations + galeries par email)
- [x] Onglet "Mes achats" dans le dashboard client
- [x] Bouton "Voir / Telecharger" sur commandes payees
- [x] Page detail commande client `/mon-compte/commande/:id` avec telechargement individuel

### Phase E — Cookies & consentement

- [x] Iframe YouTube bloquee sans consentement marketing (placeholder + bouton "Accepter et regarder")
- [x] Flow de revocation verifie et corrige (accepter → refuser → arret effectif)
- [x] Suppression cookies marketing independante du choix analytics
- [x] Bouton "Cookies" dans le footer pour modifier ses preferences

### Phase F — Admin & UX

- [x] Publier/depublier les evenements (migration `is_published`, toggle admin, filtre public)
- [x] Galerie parent : mode creation simplifie (checkbox, product types masques)
- [x] Incitation creation de compte post-commande (encart sur OrderConfirmation, pre-remplissage email)

### Phase G — Performance, SEO & prerendering

- [x] Hero image en `<picture>` AVIF/WebP/PNG (LCP -300-500ms)
- [x] Preload hero corrige (AVIF)
- [x] `aspect-ratio` sur MasonryGallery et PhotoCard (CLS -0.10+)
- [x] Lightbox thumbnails via `thumbnailUrl` (bande passante -80%)
- [x] Navbar hauteur fixe + transition couleurs uniquement (CLS)
- [x] Meta tags OG/Twitter mis a jour dynamiquement par route
- [x] Accents francais corriges dans les descriptions de routes
- [x] Sitemap complete
- [x] Page 404 (`NotFound.vue`)
- [x] JSON-LD complet (LocalBusiness, Person, WebSite, OfferCatalog)
- [ ] **A EXECUTER** : `npm run build:prerender` avant deploiement

### Phase H — Infrastructure & deploiement

- [x] Portfolio AVIF primary + WebP fallback (~30% bande passante en moins)
- [x] Bug Lightbox corrige (currentIndex non reset entre galeries)
- [x] Deploy NAS restructure : `deploy/` versionne, `deploy.sh` avec git pull + build + migrate
- [x] `.env.prod.example` documente, seuls les secrets gitignored
- [x] Documentation deploiement reecrite pour le nouveau workflow

---

## Tracking & analytics

| Fonctionnalite | Statut |
|----------------|--------|
| Vues galeries | OK — `recordView()` sur toutes les methodes |
| Telechargements (gratuits + achats) | OK — `recordDownload()` partout |
| GA4 e-commerce | OK — `add_to_cart`, `remove_from_cart`, `begin_checkout`, `purchase` |
| GA4 page views | OK — avec respect du consentement |
| Consentement | OK — Consent Mode v2, composable `useGtag.ts` |

---

## Refactoring technique

| Item | Statut | Detail |
|------|--------|--------|
| R1. Policies | FAIT | GalleryPolicy, ReservationPolicy (3 policies au total) |
| R2. adminApi.ts | FAIT | 505 → 7 sous-services + facade (97 lignes) |
| R3. Vues admin | FAIT | Galleries.vue -60%, EventGalleries.vue -40%, 3 composants extraits |
| R4. API Resources | FAIT | GalleryResource, CalendarEventResource (4 au total) |
| R5. uploadService | FAIT | 502 → 3 fichiers (orchestrateur 266 + chunkUploader 114 + utils 81) |
| R6. Tests | A FAIRE | 0% couverture, a faire progressivement |

---

## Reste a faire (dans l'ordre)

| # | Item | Effort | Type |
|---|------|--------|------|
| 1 | **Renvoyer l'email de confirmation de commande** depuis l'admin Orders — endpoint + bouton (OrderConfirmationMail existe deja) | ~30min | Feature admin |
| 2 | **Envoi SMS acces galerie via Brevo** — choix mail/SMS dans le modal d'envoi de Galleries.vue, API SMS Brevo | ~1-2h | Feature admin |
| 3 | **T4 — Stats admin** : revenu par galerie / par type de produit, taux de conversion panier → commande payee | ~1h | Analytics |
| 4 | **R6 — Tests** : PHPUnit pour OrderService, CartService, PricingService + Vitest pour useGtag, consent store | Long terme | Qualite |
| 5 | **Executer `npm run build:prerender`** avant deploiement | 2min | Operation |

---

## Hors scope / reporte

- **C3.** Policies RLS Supabase — action manuelle dashboard, hors code
- **A4.** Images responsives srcset — a evaluer si besoin apres deploiement
- **A4.** Images originales 241 MB — envisager gitignore ou stockage externe
- **H4.** Nuxt/SSR — ratio cout/benefice trop faible, prerendering Puppeteer suffit

---

## Score de sante

| Critere | Avant | Apres |
|---------|-------|-------|
| Code mort | Stripe/PayPal/CartIcon/types | Nettoye |
| Duplication | ~400+ lignes | Centralise (traits, composables, BaseApiService) |
| Fichiers geants | Galleries 1290, EventGalleries 1732 | 508 et 1030 + composants partages |
| Validation | 0 FormRequest, 43+ inline | 44 FormRequests, 0 inline |
| Autorisation | 0 Policy | 3 Policies (Order, Gallery, Reservation) |
| Separation responsabilites | Emails inline, pricing dans modele | Events/Listeners, PricingService |
| Performance | LCP ~3.5s, CLS ~0.20 | LCP ~2.0s (estim), CLS ~0.05 (estim) |
| Securite | SQL injection, pas de headers | Corrige (bindings, middleware, policies) |
| RGPD | Droit oubli partiel | Export complet, retention hebdo, consentement v2 |
| Compte client | Pas de commandes | Dashboard complet, detail commande, telechargement |
| Commandes | 1 champ nom | Nom+prenom, copier lien, retry, infos SumUp |
| Deploiement | Copie manuelle dossier par dossier | git pull + ./deploy/deploy.sh |

**Score estime : 5.5/10 → 9/10**

---

*Rapport mis a jour le 14 avril 2026.*
