# Feature : Photos Scolaires (Bulk Gallery Creation)

**Date :** 16 avril 2026
**Derniere mise a jour :** 24 avril 2026
**Branche :** `feature/school-photos`
**Statut :** Specification validee

---

## 1. Besoin

Oceane realise des shootings scolaires avec 200+ enfants. Chaque enfant a 4-5 photos portraits.
Aujourd'hui, creer 200 galeries manuellement une par une via l'admin est impraticable.

**Objectif :** Permettre a la photographe d'uploader un ZIP structure par classe/enfant et de generer automatiquement toutes les galeries en une seule operation, avec un suivi fiable de bout en bout.

**Contrainte legale :** Droit a l'image des mineurs — chaque galerie d'enfant doit etre accessible uniquement via son propre lien/code. Aucune galerie ne doit etre visible par un autre parent.

**Input attendu (ZIP) :**
```
shooting-ecole-dupont/
  ├── 6eA/                          <-- groupement par classe
  │   ├── _classe/                  <-- photo(s) de groupe, partagee(s) entre les ~30 enfants de 6eA
  │   │   └── photo-classe-6eA.jpg
  │   ├── Martin Lucas/             <-- 2-3 portraits uniques
  │   │   ├── portrait1.jpg
  │   │   └── portrait2.jpg
  │   ├── Dubois Emma/
  │   │   └── portrait1.jpg
  │   └── ... (~30 enfants)
  ├── 6eB/
  │   ├── _classe/
  │   │   └── photo-classe-6eB.jpg
  │   ├── Petit Leo/
  │   └── ... (~30 enfants)
  └── ... (autant de classes que necessaire)
```

**Precision sur les photos :**
- 2-3 photos portraits **uniques** par enfant
- 1-2 photos de classe **partagees** entre les ~30 enfants de la meme classe (pas entre toutes les classes)
- Total par galerie : 4-5 photos (portraits + photo de groupe de sa classe)

**Output attendu :**
- 1 `SchoolSession` "Shooting Ecole Dupont" (regroupement admin uniquement)
- 200+ galeries **privees independantes**, chacune nommee d'apres le sous-dossier (ex: "Martin Lucas")
- 4-5 photos par galerie (portraits uniques + photo de classe partagee), traitees avec watermark + thumbnails
- Chaque galerie avec son `access_token` unique et son `share_code`
- Acces uniquement via lien/code — pas de lien avec les comptes utilisateurs

---

## 2. Architecture

### Principe fondamental

Les galeries scolaires ne sont **PAS** des galeries event. Ce sont des galeries **privees classiques** (`type='private'`), chacune protegee par son propre code d'acces. Le regroupement est purement administratif via un nouveau modele `SchoolSession`.

### Nouveau modele : SchoolSession

```
school_sessions
├── id (UUID)
├── title (string)                  -- "Ecole Dupont - Juin 2026"
├── event_date (date, nullable)     -- date du shooting
├── status (enum)                   -- uploading, extracting, creating_galleries,
│                                      processing_photos, completed, failed
├── total_galleries (int, default 0)
├── total_photos (int, default 0)
├── processed_photos (int, default 0)
├── batch_id (string, nullable)     -- lie aux PhotoUpload pour tracking photos
├── zip_path (string, nullable)     -- chemin temp du ZIP sur le serveur
├── error_message (text, nullable)  -- details en cas de failure
├── created_at
└── updated_at
```

### Modification du modele Gallery

```
galleries
├── ...champs existants...
└── school_session_id (UUID, nullable, FK→school_sessions, cascade on delete)
```

- `school_session_id` sert UNIQUEMENT au regroupement admin
- Les galeries restent `type='private'` avec `access_token` + `share_code`
- Pas de `parent_id`, pas de hierarchie — les galeries sont independantes
- Supprimer une SchoolSession supprime toutes ses galeries + fichiers MinIO (cascade)

### Stockage MinIO

```
galleries/
  ├── {gallery_uuid_1}/             <-- portraits de Martin Lucas
  │   ├── original/
  │   ├── preview/
  │   └── thumbnail/
  ├── {gallery_uuid_2}/             <-- portraits de Dubois Emma
  │   └── ...
school-sessions/
  └── {session_uuid}/
      └── shared/                   <-- photos de classe (stockees 1 seule fois)
          ├── original/
          ├── preview/
          └── thumbnail/
```

Les photos de classe sont dans `school-sessions/{uuid}/shared/`. Chaque galerie enfant a des enregistrements `Photo` qui pointent vers ces fichiers partages. Si on supprime une galerie individuelle, les fichiers partages ne sont pas touches. Si on supprime la session entiere, tout est nettoye.

### Gestion des photos de classe

Les dossiers prefixes `_` (ex: `_classe/`) contiennent les photos de groupe.

**Traitement :**
1. Pour chaque classe (6eA, 6eB...), les photos du dossier `_classe/` sont traitees UNE SEULE FOIS (watermark, preview, thumbnail) et stockees dans `school-sessions/{session_uuid}/shared/`
2. Pour chaque enfant de la classe, on cree des enregistrements `Photo` qui pointent vers les MEMES fichiers MinIO
3. Les ~30 galeries d'une classe referencent la meme photo de groupe, mais le fichier n'existe qu'une fois

---

## 3. Flow complet

### Upload ZIP + creation

```
Admin Frontend                     API Backend                          Queue Worker
     |                                  |                                    |
     | 1. POST /school-sessions         |                                    |
     |    { title, event_date,          |                                    |
     |      product_types }             |                                    |
     |--------------------------------->| Cree SchoolSession                 |
     |<-- { session_id } --------------|   status: 'uploading'              |
     |                                  |                                    |
     | 2. Chunked upload du ZIP         |                                    |
     |    PUT /school-sessions/{id}/    |                                    |
     |        upload                    |                                    |
     |    (chunks de 50MB)              |                                    |
     |--------------------------------->| Recoit + reassemble               |
     |--------------------------------->|   (upload progress trackable)     |
     |--------------------------------->|                                    |
     |<-- upload complete --------------|                                    |
     |                                  |                                    |
     | 3. POST /school-sessions/{id}/   |                                    |
     |        process                   |                                    |
     |--------------------------------->| Dispatch ProcessSchoolSessionJob ->|
     |<-- { status: extracting } ------|                                    |
     |                                  |                   Extrait le ZIP   |
     |                                  |                   Lit arborescence |
     |                                  |                   Cree galeries    |
     |                                  |                   Traite photos    |
     |                                  |                   de classe (1x)   |
     |                                  |                   Dispatch         |
     | 4. Polling                       |                   ProcessPhotoJob  |
     |    GET /school-sessions/{id}     |                   pour portraits   |
     |    (toutes les 5s)               |                                    |
     |--------------------------------->|<-- MAJ processed_photos -----------|
     |<-- { status: processing,  ------|                                    |
     |      processed: 340/847,         |                                    |
     |      galleries: 215 }            |                                    |
     |         ...                      |                                    |
     |<-- { status: completed } --------|                                    |
```

### Interface admin "Photos scolaires"

**Page liste : `/admin/school-sessions`**
- Liste des sessions avec : titre, date du shooting, date de creation, nombre de galeries, statut
- Bouton "Nouveau shooting scolaire"
- Actions : voir le detail, supprimer (supprime tout)

**Page creation : `/admin/school-sessions/create`**
- Formulaire : titre, date du shooting
- Configuration product_types (prix impression scolaire + paliers degressifs)
- Zone upload ZIP (drag & drop ou file picker, avec progression d'upload)
- Bouton "Creer les galeries"
- Apres lancement : barre de progression en temps reel
  - Phase 1 : "Extraction du ZIP..." (court)
  - Phase 2 : "Creation des galeries... 215 galeries creees"
  - Phase 3 : "Traitement des photos : 340/847 (40%)" (long)
- Resume final : nombre de galeries, lien vers le detail

**Page detail : `/admin/school-sessions/{id}`**
- Infos session : titre, date shooting, date creation, statut
- Liste des galeries avec : nom enfant, nombre de photos, share_code, lien d'acces
- Recherche/filtre par nom
- Actions par galerie : copier le lien, voir la galerie
- Actions globales : exporter les liens (CSV), envoyer les liens en batch, supprimer la session

---

## 4. Faisabilite

### Verdict : FAISABLE

| Brique necessaire | Existe deja ? | Detail |
|---|---|---|
| Galeries privees avec access_token | OUI | Systeme de galeries existant |
| Upload async avec queue | OUI | `ProcessPhotoJob` + `PhotoUpload` avec batch_id |
| Traitement images (watermark, preview, thumbnail) | OUI | `ImageProcessingService` — 3 versions par photo |
| Stockage MinIO S3 | OUI | Structure `galleries/{uuid}/original\|preview\|thumbnail/` |
| Product types configurables par galerie | OUI | `GalleryProductType` avec prix et tiers |
| `SyncsProductTypes` trait | OUI | Applique les product_types a une galerie |
| `Gallery::generateAccessToken()` | OUI | Auto-genere access_token + share_code |
| **Modele SchoolSession** | **NON** | Nouveau modele + migration |
| **Upload ZIP chunke** | **NON** | Endpoint chunk + reassemblage |
| **Extraction ZIP + creation batch galeries** | **NON** | Service + Job |
| **Progression temps reel des sessions** | **NON** | Polling endpoint sur SchoolSession |
| **Interface admin dediee** | **NON** | 3 nouvelles vues Vue |

---

## 5. Contraintes et risques

### 5.1 — Taille du ZIP

| Scenario | Photos | Poids estime (JPEG haute qualite) | ZIP compresse |
|---|---|---|---|
| Petit shooting | 200 enfants x 4 photos = 800 | ~4 GB (5 MB/photo) | ~3.5 GB |
| Gros shooting | 300 enfants x 5 photos = 1500 | ~7.5 GB | ~6.5 GB |

**Solution : chunked upload.** Le frontend decoupe le ZIP en morceaux de 50MB et les envoie sequentiellement. Le backend reassemble. Pas de limite de taille nginx/PHP a contourner.

Config NAS necessaire :
- `upload_max_filesize = 60M` (par chunk, pas par ZIP)
- `post_max_size = 60M`
- `client_max_body_size = 60M` (nginx)
- Espace temp suffisant pour le ZIP + extraction (~7 GB pour un gros shooting)

### 5.2 — Temps de traitement

Le traitement watermark/thumbnail prend ~30-50s par photo (selon la resolution et le hardware du NAS).

| Scenario | Photos | Temps estime (1 worker) | Temps (2 workers) |
|---|---|---|---|
| 800 photos | 800 | ~7-11 heures | ~3.5-5.5 heures |
| 1500 photos | 1500 | ~12-20 heures | ~6-10 heures |

C'est long mais acceptable — le traitement se fait en arriere-plan. La photographe lance le soir, c'est pret le matin.

**Optimisations possibles :**
- Augmenter le nombre de queue workers (actuellement 1)
- Reduire la taille des previews (2560px -> 1920px)
- Skipper le watermark pour les thumbnails (600px, peu lisible de toute facon)

### 5.3 — Nommage des dossiers

Le nom du dossier = titre de la galerie. Il faut gerer :
- Accents et caracteres speciaux (UTF-8 dans le ZIP)
- Dossiers vides (ignorer)
- Fichiers non-image (ignorer `.DS_Store`, `Thumbs.db`, etc.)
- Prefixe `_` = dossier special (photos de classe), pas une galerie

### 5.4 — Queue driver

**Prerequis : passer `QUEUE_CONNECTION=database`** dans `deploy/.env.prod`.

Avec `sync` (actuel), les jobs s'executent dans la requete HTTP — impossible pour 800+ photos.
Avec `database`, les jobs sont stockes dans la table `jobs` et traites en arriere-plan par le container `api-queue` (deja en place).

Ce changement est safe. Le container queue worker et les tables `jobs`/`failed_jobs` existent deja.

### 5.5 — Suppression en cascade

Supprimer une SchoolSession doit :
1. Supprimer toutes les galeries liees (cascade FK)
2. Pour chaque galerie : supprimer les fichiers MinIO dans `galleries/{uuid}/`
3. Supprimer les fichiers partages dans `school-sessions/{session_uuid}/shared/`

Cela necessite un event `deleting` sur SchoolSession ou un Job de nettoyage dedie pour eviter un timeout sur la suppression de 200+ galeries + fichiers MinIO.

---

## 6. Product type scolaire

Un seul format d'impression disponible pour les scolaires. Les parents choisissent la quantite avec prix degressif.

**Structure :**
```
product_type: "print_scolaire"
is_enabled: true
price: (a definir)
packTiers:
  - { min_quantity: X, unit_price: Y }
  - ...
```

Le prix n'est pas encore decide. L'implementation permet de configurer le tarif au moment de la creation du shooting et de le modifier ensuite.

**Impact technique : aucun.** On reutilise le systeme `GalleryProductType` + `PackTier` existant.

---

## 7. Envoi des liens d'acces

Apres creation des galeries, envoi des liens d'acces aux parents.

**MVP : envoi post-creation via l'admin**
- Page detail de la session : liste des galeries avec nom enfant + lien
- Export CSV des liens (nom, share_code, URL complete)
- Saisie/import des contacts parents dans l'admin
- Envoi en batch (email via Brevo)

**Amelioration future : CSV d'accompagnement dans le ZIP**
```csv
nom_enfant,email_parent,telephone_parent
Martin Lucas,parents.martin@email.com,0612345678
```

---

## 8. Plan d'implementation

### Prerequis (~15min)

- Passer `QUEUE_CONNECTION=database` dans `.env.prod` sur le NAS
- Redemarrer les containers `api-php` et `api-queue`

### Phase 1 — Backend : modele + migration + service (~5-7h)

1. Migration : table `school_sessions` + ajout `school_session_id` sur `galleries`
2. Modele `SchoolSession` avec relations et statuts
3. `SchoolSessionService` :
   - Extraction ZIP + lecture arborescence
   - Detection des dossiers `_classe/` (prefixe `_`)
   - Traitement photos de classe 1 seule fois, stockage dans `school-sessions/{uuid}/shared/`
   - Creation des galeries privees (type `private`, access_token, share_code)
   - Creation des records `Photo` partages pour les photos de classe
   - Dispatch `ProcessPhotoJob` pour chaque portrait
   - Sync product_types sur chaque galerie
   - Mise a jour du statut de la session a chaque etape
4. `ProcessSchoolSessionJob` : job qui orchestre toute l'operation en arriere-plan
5. Endpoints API :
   - `POST /admin/school-sessions` — creer une session
   - `PUT /admin/school-sessions/{id}/upload` — upload chunk du ZIP
   - `POST /admin/school-sessions/{id}/process` — lancer le traitement
   - `GET /admin/school-sessions` — lister les sessions
   - `GET /admin/school-sessions/{id}` — detail + progression
   - `DELETE /admin/school-sessions/{id}` — suppression cascade
6. FormRequests de validation

### Phase 2 — Frontend admin (~5-6h)

1. Service API `schoolSessionApi.ts`
2. Vue liste `SchoolSessions.vue` :
   - Liste des sessions (titre, date, nb galeries, statut, date creation)
   - Actions : voir, supprimer
3. Vue creation `SchoolSessionCreate.vue` :
   - Formulaire (titre, date, product_types)
   - Upload ZIP avec progression (chunk upload)
   - Lancement du traitement
   - Barre de progression temps reel (polling)
4. Vue detail `SchoolSessionDetail.vue` :
   - Infos session + progression si en cours
   - Liste des galeries (nom, share_code, lien)
   - Recherche/filtre par nom
   - Export CSV des liens
   - Actions : copier lien, supprimer session
5. Routes admin + navigation (onglet "Photos scolaires")

### Phase 3 — Envoi liens en batch (~3-4h)

1. Import contacts (CSV ou saisie manuelle)
2. Matching nom enfant -> email parent
3. Template email scolaire
4. Endpoint + Job d'envoi batch

**Effort total estime : 13-17h de dev**

---

## 9. Decisions prises

| Question | Reponse |
|---|---|
| Type de galeries | Galeries **privees** avec access_token/share_code, PAS des galeries event |
| Hierarchie | Aucune — galeries independantes, regroupees par SchoolSession (admin only) |
| Lien comptes utilisateurs | Aucun — acces uniquement via lien/code |
| Source des photos | Upload ZIP depuis l'admin (chunked) |
| Photos de classe | Par classe (~30 enfants), traitees 1 fois, referencees dans les galeries de la classe |
| Suppression | Supprimer une session = supprimer toutes les galeries + fichiers MinIO |
| Duree de vie | Pas d'expiration auto, l'admin gere manuellement. Date de creation affichee |
| Envoi auto des liens | Oui, apres creation — saisie contacts dans l'admin (MVP) |
| Product type | 1 seul format impression, prix degressif a la quantite (a definir) |
| QUEUE_CONNECTION | A passer de `sync` a `database` (prerequis) |
| Admin UI | Onglet dedie "Photos scolaires" dans l'admin |
