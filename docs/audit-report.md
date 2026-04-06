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
| **F** Admin + UX       | A FAIRE              | -                         |
| **G** SEO/Prerendering | A FAIRE              | -                         |
| **H** Infrastructure   | A FAIRE (long terme) | -                         |
| **Tech** Refactoring   | Partiel              | 5 items                   |

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

### Phase F — Admin & UX

#### F1. Publier/depublier les evenements

- [ ] Champ `is_published` (boolean, default true) sur Gallery (type event)
- [ ] Migration + modifier `EventGalleryController::index()` (public) pour filtrer
- [ ] Toggle dans l'admin EventGalleries

#### F2. Galerie parent : mode creation simplifie

- [ ] Option "Galerie parent" (checkbox) dans le formulaire de creation
- [ ] Rendre optionnels les champs non necessaires (photos, product types)
- [ ] Backend : validation conditionnelle selon le flag `is_parent`

#### F3. Inciter a la creation de compte

- [ ] Apres une commande (page OrderConfirmation) : afficher un encart "Creez votre compte pour retrouver vos achats"
- [ ] Lien vers la creation de compte avec pre-remplissage de l'email si commande guest
- [ ] Benefices mis en avant : historique commandes, re-telechargement, galeries privees

---

### Phase G — Performance, SEO & prerendering

*OBJECTIF PRINCIPAL : temps de chargement rapides, photos qui s'affichent bien, panier instantane.*

#### G1. Audit Lighthouse / Core Web Vitals

- [ ] Audit Lighthouse sur les pages cles (accueil, portfolio, evenements, galerie, checkout)
- [ ] Identifier les goulots : LCP, CLS, TBT
- [ ] Evaluer le besoin d'images responsives `srcset` / `<picture>` (lie a A4)
- [ ] Optimiser le chargement des images selon les resultats

#### G2. Audit du prerendering actuel

- [ ] Verifier pages prerendues, balises meta, sitemap.xml
- [ ] Tester avec Google Search Console

#### G3. Amelioration SEO technique

- [ ] Donnees structurees JSON-LD (schema.org/Service, schema.org/Event)
- [ ] Corriger les problemes identifies par Lighthouse/Search Console

#### G4. Strategie de prerendering *(depend de H4 — Nuxt ou non)*

- [ ] Si Nuxt → SSR natif remplace Puppeteer
- [ ] Sinon → evaluer `vite-ssg` comme alternative legere

---

### Phase H — Infrastructure & deploiement (long terme)

#### H1. Migration Render → Vercel

- [ ] Frontend sur Vercel, backend sur NAS/Render/Railway

#### H2. Restructuration deploiement NAS — rendre le repo autonome

**Situation actuelle :**

- Le repo contient deja un dossier `deploy/` avec des templates prets a copier : `docker-compose.prod.deploy.yml`, `nginx.prod.deploy.conf`, `deploy.sh.deploy`
- Le dossier `docker/` (racine monorepo) contient `nginx.conf`, `php.ini`, `www.conf`
- Le `api/Dockerfile` existe, le `api/.gitignore` ignore deja `.env`, `.env.production`
- Sur le NAS, la structure cible est `/volume1/docker/oceane-api/` avec `api/` en sous-dossier et les configs a cote

**Probleme :** les fichiers de `deploy/` doivent etre copies manuellement via SCP. Un `git clone` seul ne suffit pas.

**Objectif :** qu'un `git pull` + un seul script suffise, les secrets restant hors versioning.

**Ce qui existe deja :**

```
deploy/
├── README.md                      # doc de deploiement
├── SSH-COMMANDS.md                # commandes SSH
├── deploy.sh.deploy               # script deploiement (hardcode /volume1/docker/oceane-api)
├── docker-compose.prod.deploy.yml # compose prod (paths: ./api, ./docker, .env.prod)
└── nginx.prod.deploy.conf         # nginx prod

docker/                            # configs PHP/Nginx dev
├── nginx.conf
├── php.ini
└── www.conf

api/Dockerfile                     # image Laravel
docker-compose.yml                 # dev local (racine monorepo)
```

**Le compose prod reference `./api` comme sous-dossier** — il est prevu pour tourner depuis la racine NAS, pas depuis `/api`. Donc la bonne approche est de garder cette structure et de l'integrer proprement au repo.

**Actions a realiser :**

- [ ] Creer `deploy/nginx.prod.conf` versionne (renommer depuis `nginx.prod.deploy.conf`, retirer le suffixe `.deploy`)
- [ ] Creer `deploy/docker-compose.prod.yml` versionne (renommer depuis `.deploy.yml`)
- [ ] Creer `deploy/deploy.sh` versionne — adapter pour accepter un path configurable au lieu de hardcoder `/volume1/docker/oceane-api`
- [ ] Creer `deploy/.env.prod.example` commite (sans secrets), documente toutes les variables
- [ ] Ajouter `deploy/.env.prod` au `.gitignore` (fichier reel avec secrets, jamais commite)
- [ ] Copier `docker/php.ini` et `docker/www.conf` dans `deploy/docker/` pour que tout soit au meme endroit
- [ ] Mettre a jour `deploy/README.md` avec le nouveau flow simplifie :
  ```
  1. git clone → cd deploy/
  2. cp .env.prod.example .env.prod → remplir les secrets
  3. ./deploy.sh
  ```
- [ ] Ajouter `git pull` au debut de `deploy.sh` pour qu'un re-deploiement soit un seul `./deploy.sh`

#### H3. Certificats SSL + reverse proxy

- [ ] Configurer Let's Encrypt sur le NAS (Nginx ou Traefik)
- [ ] S'assurer que le HSTS header (deja en place via middleware SecurityHeaders) fonctionne en prod

#### H4. Evaluation SSR / Nuxt

- [ ] Evaluer benefice vs cout de migration Vue SPA → Nuxt (ou alternative : vite-ssg, Astro)
- [ ] Si Nuxt adopte → G4 devient obsolete, SSR natif remplace Puppeteer

---

### Reste technique du refactoring (priorite basse)

- [ ] **Tests** : 0% de couverture. PHPUnit pour services critiques, Vitest pour composables
- [ ] **Galleries.vue (1290) et EventGalleries.vue (1678)** : extraire PhotoUploadZone, PhotoGridManager, DeletePhotoModal
- [ ] **adminApi.ts (495)** : decouper par domaine
- [ ] **API Resources supplementaires** : GalleryResource, UserResource, ReservationResource
- [ ] **Policies supplementaires** : GalleryPolicy, ReservationPolicy
- [ ] **uploadService.ts (502)** : refactoring possible

---

## Score de sante

| Critere                    | Avant                                                 | Apres                                            |
|----------------------------|-------------------------------------------------------|--------------------------------------------------|
| Code mort                  | Stripe/PayPal/CartIcon/types                          | Nettoye                                          |
| Duplication                | ~400+ lignes                                          | Centralise (traits, composables, BaseApiService) |
| Fichiers geants            | GalleryController 877, OrderController 559            | 440 et 319                                       |
| Validation                 | 0 FormRequest, 43+ inline                             | 44 FormRequests, 0 inline                        |
| Autorisation               | 0 Policy                                              | 1 Policy (OrderPolicy)                           |
| Separation responsabilites | Emails inline, pricing dans modele                    | Events/Listeners, PricingService                 |
| Performance N+1            | 2 critiques                                           | Corriges (batch, withCount)                      |
| Securite                   | SQL injection, pas de headers, tokens sans expiration | Corrige (bindings, middleware, 30j)              |
| RGPD                       | Droit oubli partiel, pas de retention                 | Etendu (commandes/contacts), retention hebdo     |
| Compte client              | Pas de commandes, rattachement partiel                | Dashboard complet, rattachement auto             |
| Commandes                  | 1 champ nom, pas de retry admin                       | Nom+prenom, copier lien, retry, infos SumUp      |

**Score estime : 5.5/10 → 8/10**

---

*Rapport mis a jour le 5 avril 2026.*
