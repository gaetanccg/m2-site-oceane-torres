# Rapport d'Audit de Code — Oceane Torres Photographie

**Date initiale :** 31 mars 2026
**Derniere mise a jour :** 1er avril 2026
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
| **A** Performance      | FAIT                 | 2 items basse priorite |
| **B** Commandes        | FAIT                 | -                      |
| **C** Securite/RGPD    | FAIT                 | 3 items                |
| **D** Compte client    | FAIT                 | 1 item UX              |
| **E** Cookies          | A FAIRE              | -                      |
| **F** Admin features   | A FAIRE              | -                      |
| **G** SEO/Prerendering | A FAIRE              | -                      |
| **H** Infrastructure   | A FAIRE (long terme) | -                      |
| **Tech** Refactoring   | Partiel              | 7 items                |

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

---

## Reste a faire des phases terminees (priorise)

### Priorite haute

**C2. Bouton "Modifier mes preferences cookies" dans le footer**

- Actuellement l'utilisateur ne peut pas revoquer ses preferences apres avoir accepte
- Le store `consent.ts` a deja `revokeConsent()` — il manque juste le bouton d'acces
- Impacte la conformite RGPD (droit de retrait du consentement)

**C2. Enrichir l'export GDPR**

- `ClientController::gdprExport()` n'inclut pas les commandes, messages de contact, ni download logs
- A completer pour conformite Article 15 RGPD (droit d'acces)

### Priorite moyenne

**C1. ~27 validations inline restantes**

- Petites validations (1-2 regles) dans CartController, GiftCardController, SumUpPaymentController, ReservationController, AvailabilityController, GalleryController, EventGalleryController
- Fonctionnent correctement mais ne suivent pas le pattern FormRequest du reste du projet

**C3. Policies RLS Supabase**

- Action manuelle sur le dashboard Supabase
- Defense en profondeur (l'API Laravel est le seul point d'acces)

### Priorite basse

**A4. Images responsives `srcset` / `<picture>`**

- Necessite des changements sur l'image proxy backend pour servir differentes tailles
- Gain de perf sur mobile mais pas critique

**A4. Images originales `/public/images/` (241 MB)**

- Seul `hero.png` est directement reference
- Les originaux servent de source pour `optimize-images.js`
- Envisager `.gitignore` ou stockage externe

**D2. UX compte client**

- Re-telechargement individuel de photos depuis le dashboard
- Page de detail commande cote client (actuellement redirige vers la page commande standard)

---

## Audit tracking & analytics

### Etat actuel du tracking

| Fonctionnalite                    | Statut          | Detail                                                                                                               |
|-----------------------------------|-----------------|----------------------------------------------------------------------------------------------------------------------|
| Vues galeries                     | Partiel         | OK sur share code, download, events. **Manque** sur galeries publiques directes et acces par token                   |
| Telechargements photos (gratuits) | OK              | `recordDownload()` appele dans PhotoController et GalleryController (ZIP)                                            |
| Telechargements photos (achats)   | **MANQUE**      | OrderController marque `item.is_downloaded` mais n'incremente PAS `photo.downloads_count` ni ne cree de DownloadLog  |
| Likes photos                      | Limite          | Boolean `is_liked` par photo (pas par utilisateur, pas de compteur, pas de logs)                                     |
| Commandes                         | OK en BDD       | Order, OrderItem, Payment — tout est enregistre. **Manque** : evenements GA4                                         |
| Revenus dashboard                 | Partiel         | Total et mensuel OK. **Manque** : revenu par galerie, par type de produit, par photo                                 |
| Panier                            | Minimal         | Status active/converted/expired en BDD. **Manque** : evenements GA4, taux d'abandon                                  |
| GA4                               | Page views seul | `page_view` envoye a chaque navigation. **Aucun** evenement e-commerce (add_to_cart, purchase)                       |
| Stats admin                       | Partiel         | Reservations, paiements, revenus mensuels. **Manque** : photos populaires, galeries les plus vues, funnel conversion |

### Plan de correction tracking (a integrer dans les prochaines phases)

#### T1. Corriger le tracking des telechargements d'achats (priorite haute)

- [ ] Dans `OrderController::downloadPhoto()` : ajouter `$photo->recordDownload($request->ip(), $request->userAgent())` apres `$item->markAsDownloaded()`
- [ ] Dans `OrderController::downloadAll()` : ajouter `$photo->recordDownload($request->ip(), $request->userAgent())` apres chaque `$item->markAsDownloaded()`
- [ ] Resultat : `photo.downloads_count` et `download_logs` refletent TOUS les telechargements (gratuits + achats)

#### T2. Corriger les vues galeries manquantes (priorite haute)

- [ ] Dans `GalleryController::show()` : ajouter `$gallery->recordView()` pour les galeries publiques
- [ ] Dans `GalleryController::showByToken()` : ajouter `$gallery->recordView()` pour les galeries privees par token

#### T3. Evenements GA4 e-commerce (priorite haute — impact revenus)

- [ ] `add_to_cart` : envoyer depuis le frontend quand `cartStore.addItem()` reussit (item_id, item_name, price, currency)
- [ ] `remove_from_cart` : envoyer quand un item est retire
- [ ] `purchase` : envoyer depuis OrderConfirmation.vue quand la commande est payee (transaction_id, value, currency, items)
- [ ] `begin_checkout` : envoyer quand l'utilisateur arrive sur Checkout.vue
- [ ] Respecter le consentement : ne pas envoyer si analytics non accepte

#### T4. Analytics admin enrichies (priorite moyenne)

- [ ] Endpoint ou section dashboard : top 10 photos les plus telechargees
- [ ] Endpoint ou section dashboard : top 10 galeries les plus vues
- [ ] Endpoint ou section dashboard : revenu par galerie / par type de produit (digital vs print)
- [ ] Endpoint ou section dashboard : taux de conversion panier → commande payee

#### T5. Amelioration du systeme de likes (priorite basse)

- [ ] Le systeme actuel est un boolean par photo (pas par utilisateur) — suffisant pour le besoin actuel (marquer les favoris du photographe)
- [ ] Si besoin futur de likes utilisateur : creer une table `photo_likes` (photo_id, user_id/session_id, created_at)

---

## Phases restantes a implementer

### Phase E — Cookies & consentement

#### E1. Consentement YouTube

- [ ] Bloquer les iframes YouTube tant que le consentement marketing n'est pas donne
- [ ] Afficher un placeholder avec bouton "Accepter pour voir la video"

#### E2. Revocation du consentement

- [ ] Verifier le flow : accepter → naviguer → refuser → verifier arret tracking
- [ ] Supprimer cookies GA4, desactiver tracking, supprimer cookies tiers

---

### Phase F — Admin & fonctionnalites

#### F1. Publier/depublier les evenements

- [ ] Champ `is_published` (boolean, default true) sur Gallery (type event)
- [ ] Migration + modifier `EventGalleryController::index()` (public) pour filtrer
- [ ] Toggle dans l'admin EventGalleries

#### F2. Galerie parent : mode creation simplifie

- [ ] Option "Galerie parent" (checkbox) dans le formulaire de creation
- [ ] Rendre optionnels les champs non necessaires (photos, product types)
- [ ] Backend : validation conditionnelle selon le flag `is_parent`

---

### Phase G — SEO & prerendering

#### G1. Audit du prerendering actuel

- [ ] Verifier pages prerendues, balises meta, sitemap.xml
- [ ] Tester avec Google Search Console

#### G2. Amelioration SEO technique

- [ ] Donnees structurees JSON-LD (schema.org/Service, schema.org/Event)
- [ ] Core Web Vitals (lie a Phase A)

#### G3. Strategie de prerendering *(depend de H3 — Nuxt ou non)*

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

- [ ] Evaluer benefice vs cout de migration
- [ ] Si Nuxt adopte → G3 devient obsolete

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
| Validation                 | 0 FormRequest, 43+ inline                             | 19 FormRequests, ~27 inline mineures             |
| Autorisation               | 0 Policy                                              | 1 Policy (OrderPolicy)                           |
| Separation responsabilites | Emails inline, pricing dans modele                    | Events/Listeners, PricingService                 |
| Performance N+1            | 2 critiques                                           | Corriges (batch, withCount)                      |
| Securite                   | SQL injection, pas de headers, tokens sans expiration | Corrige (bindings, middleware, 30j)              |
| RGPD                       | Droit oubli partiel, pas de retention                 | Etendu (commandes/contacts), retention hebdo     |
| Compte client              | Pas de commandes, rattachement partiel                | Dashboard complet, rattachement auto             |
| Commandes                  | 1 champ nom, pas de retry admin                       | Nom+prenom, copier lien, retry, infos SumUp      |

**Score estime : 5.5/10 → 8/10**

---

*Rapport mis a jour le 1er avril 2026.*
