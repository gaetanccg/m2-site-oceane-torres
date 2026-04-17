# Feature : Photos Scolaires (Bulk Gallery Creation)

**Date :** 16 avril 2026
**Branche :** `feature/school-photos`
**Statut :** Etude de faisabilite

---

## 1. Besoin

Oceane realise des shootings scolaires avec 200+ enfants. Chaque enfant a 4-5 photos portraits.
Aujourd'hui, creer 200 galeries manuellement une par une via l'admin est impraticable.

**Objectif :** Permettre a la photographe d'uploader un dossier structure par enfant et de generer automatiquement toutes les galeries en une seule operation.

**Input attendu :**
```
shooting-ecole-dupont/
  ├── 6eA/                          <-- groupement par classe
  │   ├── _classe/                  <-- photo(s) de groupe, partagee(s) entre les ~30 enfants
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
- 1-2 photos de classe **partagees** entre toutes les galeries du shooting
- Total par galerie : 4-5 photos (mix portraits + classe)

**Output attendu :**
- 1 galerie parente "Shooting Ecole Dupont" (type event, sert de conteneur)
- 200+ galeries enfants, chacune nommee d'apres le sous-dossier (ex: "Martin Lucas")
- 4-5 photos par galerie (portraits uniques + photos de classe partagees), traitees avec watermark + thumbnails
- Chaque galerie avec son access_token unique et son share_code

---

## 2. Faisabilite

### Verdict : FAISABLE — l'architecture existante supporte deja 90% du besoin

| Brique necessaire | Existe deja ? | Detail |
|---|---|---|
| Galeries hierarchiques (parent/enfants) | OUI | `parent_id` sur galleries, utilise pour les events |
| Upload async avec queue | OUI | `ProcessPhotoJob` + `PhotoUpload` avec batch_id |
| Traitement images (watermark, preview, thumbnail) | OUI | `ImageProcessingService` — 3 versions par photo |
| Stockage MinIO S3 | OUI | Structure `galleries/{uuid}/original\|preview\|thumbnail/` |
| Suivi de progression | OUI | Endpoint `GET /upload-status?batch_id=` |
| Product types configurables par galerie | OUI | `GalleryProductType` avec prix et tiers |
| Bulk operations (toggle downloadable, sort order) | OUI | Endpoints admin existants |
| **Upload d'un dossier structure en une operation** | **NON** | A creer |
| **Creation de galeries en batch** | **NON** | A creer |
| **Interface admin dediee** | **NON** | A creer |

---

## 3. Architecture proposee

### Vue d'ensemble

```
[Admin Frontend]                    [Backend API]                      [Queue Worker]
     |                                   |                                  |
     |  1. Upload dossier ZIP            |                                  |
     |  (ou drag & drop structure)       |                                  |
     |---------------------------------->|                                  |
     |                                   |  2. Extraire ZIP                 |
     |                                   |  3. Lire l'arborescence          |
     |                                   |  4. Creer galerie parente        |
     |                                   |  5. Creer galeries enfants       |
     |                                   |     (1 par sous-dossier)         |
     |                                   |  6. Dispatch ProcessPhotoJob     |
     |                                   |     pour chaque photo            |
     |   <-- batch_id + resume -------   |--------------------------------->|
     |                                   |                                  |  7. Process watermark
     |  8. Poll progression              |                                  |     + thumbnails
     |  (batch_id existant)              |                                  |     + upload MinIO
     |---------------------------------->|                                  |
     |   <-- status (120/800) ------     |                                  |
```

### Choix technique : NAS direct vs ZIP vs Drag & Drop

| Approche | Avantages | Inconvenients |
|---|---|---|
| **Dossier sur le NAS** | Pas de limite taille, pas d'upload HTTP, rapide | Necessite de copier les fichiers sur le NAS avant (USB/SMB) |
| **Upload ZIP** | Tout via le navigateur | ZIP de 2-4 GB, chunked upload necessaire, complexe |
| **Drag & Drop dossier** | UX intuitive | API `webkitdirectory` limitee, pas fiable pour 800+ fichiers |

**Recommandation : Dossier sur le NAS.** La photographe copie le dossier structure sur le NAS (via USB, SMB ou l'UI UGREEN), puis lance la creation depuis l'admin. Pas de limite de taille, pas de complexite d'upload HTTP. L'endpoint ZIP peut etre ajoute plus tard si necessaire.

### Gestion des photos de classe partagees

Les photos de classe ne sont pas globales au shooting — chaque classe a sa propre photo de groupe, partagee entre les ~30 enfants de cette classe.

**Structure du dossier attendue :**
```
shooting-ecole-dupont/
  ├── 6eA/                            <-- dossier par classe (groupement)
  │   ├── _classe/                    <-- prefixe _ = photos partagees pour ce groupe
  │   │   └── photo-classe-6eA.jpg
  │   ├── Martin Lucas/               <-- 1 dossier = 1 galerie
  │   │   ├── portrait1.jpg
  │   │   └── portrait2.jpg
  │   └── Dubois Emma/
  │       └── portrait1.jpg
  ├── 6eB/
  │   ├── _classe/
  │   │   └── photo-classe-6eB.jpg
  │   ├── Petit Leo/
  │   │   └── portrait1.jpg
  │   └── ...
  └── ...
```

**Hierarchie resultante (a plat, 2 niveaux) :**
- 1 galerie parente "Shooting Ecole Dupont" (conteneur)
- 200+ galeries enfants a plat (Martin Lucas, Dubois Emma, Petit Leo, ...)
- Les dossiers de classe (6eA, 6eB) ne creent PAS de galeries — ils servent uniquement a determiner quelle photo de groupe va dans quelles galeries

**Traitement des photos de classe :**
1. Pour chaque classe, les photos du dossier `_classe/` sont traitees UNE SEULE FOIS (watermark, preview, thumbnail)
2. Pour chaque enfant de la classe, on cree des enregistrements `Photo` qui pointent vers les MEMES fichiers MinIO
3. Resultat : les ~30 galeries d'une classe referencent la meme photo de groupe, mais le fichier n'existe qu'une fois dans MinIO

Cela evite de traiter la meme image 30 fois par classe et economise ~95% du stockage pour les photos de groupe.

---

## 4. Ce qu'il faut creer

### Backend (API Laravel)

#### 4.1 — Nouvel endpoint : `POST /admin/school-sessions`

Recoit le ZIP et orchestre toute la creation.

**Input :**
- `file` : fichier ZIP (multipart)
- `title` : nom du shooting (ex: "Ecole Dupont - Juin 2026")
- `event_category_id` : categorie d'evenement (optionnel)
- `event_date` : date du shooting (optionnel)
- `product_types` : configuration des prix (appliquee a toutes les galeries enfants)

**Process :**
1. Valider et extraire le ZIP dans un dossier temp
2. Lire les sous-dossiers (chacun = 1 enfant)
3. Creer la galerie parente (type `event`, is_published=false par defaut)
4. Pour chaque sous-dossier :
   a. Creer une galerie enfant (titre = nom du dossier, type `event`, parent_id = galerie parente)
   b. Copier le product_types de la config globale
   c. Pour chaque image du sous-dossier : creer un `PhotoUpload` + dispatch `ProcessPhotoJob`
5. Retourner : batch_id, nombre de galeries, nombre de photos, resume

**Response :**
```json
{
  "parent_gallery_id": "uuid",
  "batch_id": "uuid",
  "galleries_created": 215,
  "photos_queued": 847,
  "galleries": [
    { "id": "uuid", "title": "Martin Lucas", "photos_count": 4 },
    { "id": "uuid", "title": "Dubois Emma", "photos_count": 3 }
  ]
}
```

#### 4.2 — Nouveau Job : `ProcessSchoolSessionJob` (optionnel)

Si le ZIP est gros (>500 photos), l'extraction + creation des galeries pourrait etre long.
On peut dispatcher un job pour toute l'operation et retourner un ID de suivi immediatement.

Mais pour un MVP, un traitement synchrone de l'extraction + creation galeries (rapide : ~1-2s pour 200 INSERT) puis dispatch async des photos est suffisant.

#### 4.3 — Endpoint de suivi (existant)

`GET /upload-status?batch_id={id}` — deja fonctionnel, reutilisable tel quel.

#### 4.4 — Endpoint listing : `GET /admin/school-sessions`

Liste les shootings scolaires (= galeries parentes avec metadata "school_session").
Optionnel pour le MVP — on peut simplement filtrer les galeries events.

### Frontend (Vue 3 Admin)

#### 4.5 — Nouvelle vue : `SchoolSessionCreate.vue`

Page accessible depuis le menu admin : **"Shooting scolaire"**

**Elements UI :**
1. Champ titre du shooting
2. Selecteur categorie + date
3. Configuration product_types (reutiliser le composant existant des galeries)
4. Zone d'upload ZIP (drag & drop ou file picker)
5. Bouton "Creer les galeries"
6. Barre de progression apres lancement :
   - Phase 1 : "Extraction en cours..." (court)
   - Phase 2 : "Traitement des photos : 120/847 (14%)" (long, utilise le polling batch_id)
7. Resume final : nombre de galeries creees, lien vers la galerie parente

#### 4.6 — Adaptation de la vue galeries existante

Les galeries scolaires apparaitront dans la liste des galeries events.
Pas besoin de vue specifique pour les gerer apres creation — les vues existantes suffisent
(modifier titre, supprimer, envoyer les liens d'acces, etc.)

---

## 5. Reutilisation du code existant

| Composant existant | Reutilisation |
|---|---|
| `ProcessPhotoJob` | 100% — dispatch identique |
| `ImageProcessingService` | 100% — meme pipeline watermark/thumbnail |
| `MinioStorageService` | 100% — meme stockage |
| `PhotoUpload` model + batch tracking | 100% — meme pattern de suivi |
| `GET /upload-status` endpoint | 100% — meme polling |
| `SyncsProductTypes` trait | 100% — copier product_types sur chaque galerie |
| `Gallery::generateAccessToken()` | 100% — auto-genere a la creation |
| `EventGalleryController::store()` | ~80% — logique de creation event gallery reutilisable |
| Upload chunk frontend (`chunkUploader.ts`) | ~50% — adaptable pour le ZIP chunke |
| `ProductTypeConfig` composant Vue | 100% — reutilisable dans le formulaire |

---

## 6. Contraintes et risques

### 6.1 — Taille du ZIP

| Scenario | Photos | Poids estime (JPEG haute qualite) | ZIP compresse |
|---|---|---|---|
| Petit shooting | 200 enfants x 4 photos = 800 | ~4 GB (5 MB/photo) | ~3.5 GB |
| Gros shooting | 300 enfants x 5 photos = 1500 | ~7.5 GB | ~6.5 GB |

**Probleme :** la config actuelle limite les uploads a 100MB (nginx + PHP).

**Solutions :**
- **Chunked upload du ZIP** (recommande) : decoupage en morceaux de 50MB cote frontend, reassemblage cote backend
- **Augmenter les limites** : viable pour des ZIP plus petits mais pas scalable
- **Upload direct depuis le NAS** : si les photos sont deja sur le NAS (via SMB/USB), le backend lit directement le dossier sans upload HTTP. Plus simple mais moins generique.

### 6.2 — Temps de traitement

Le traitement watermark/thumbnail prend ~30-50s par photo (selon la resolution et le hardware du NAS).

| Scenario | Photos | Temps estime (1 worker) | Temps (2 workers) |
|---|---|---|---|
| 800 photos | 800 | ~7-11 heures | ~3.5-5.5 heures |
| 1500 photos | 1500 | ~12-20 heures | ~6-10 heures |

**C'est long mais acceptable** — le traitement se fait en arriere-plan. La photographe lance le soir, c'est pret le matin.

**Optimisations possibles :**
- Augmenter le nombre de queue workers (actuellement 1)
- Reduire la taille des previews (2560px → 1920px)
- Skipper le watermark pour les thumbnails (600px, peu lisible de toute facon)

### 6.3 — Espace disque temporaire

L'extraction du ZIP necessite de l'espace temp sur le container :
- ZIP de 3.5 GB + fichiers extraits = ~7 GB d'espace temp necessaire
- Le dossier `storage/app/temp/` est sur le volume monte (NAS), donc pas de souci d'espace container
- Nettoyage automatique apres traitement

### 6.4 — Nommage des dossiers

Le nom du dossier = titre de la galerie. Il faut gerer :
- Accents et caracteres speciaux (UTF-8 dans le ZIP)
- Dossiers vides (ignorer)
- Fichiers non-image (ignorer `.DS_Store`, `Thumbs.db`, etc.)
- Sous-sous-dossiers (ignorer ou erreur)

### 6.5 — Queue driver

La config actuelle est `QUEUE_CONNECTION=sync` sur le NAS.

**Prerequis : passer a `QUEUE_CONNECTION=database`** dans `deploy/.env.prod`.

Avec `sync`, les jobs s'executent dans la requete HTTP (bloquant). Avec `database`, les jobs sont enregistres dans la table `jobs` et traites en arriere-plan par le container `api-queue` (deja en place).

Ce changement est safe — le container queue worker et les tables `jobs`/`failed_jobs` existent deja. Les uploads async existants fonctionneront mieux (reponse HTTP immediate au lieu de bloquer).

---

## 7. Product type scolaire

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

**Impact technique : aucun.** On reutilise le systeme `GalleryProductType` + `PackTier` existant. Le product_type `print_scolaire` sera ajoute dans les types autorises.

---

## 8. Envoi automatique des liens d'acces

Apres creation des galeries, envoi automatique (email ou SMS) du lien d'acces a chaque parent.

**Probleme :** le mapping enfant → email parent n'existe pas dans le dossier de photos.

**Solutions possibles :**

1. **CSV d'accompagnement** dans le dossier racine :
```csv
nom_enfant,email_parent,telephone_parent
Martin Lucas,parents.martin@email.com,0612345678
Dubois Emma,parents.dubois@email.com,0698765432
```
Le nom_enfant correspond au nom du sous-dossier. Apres creation, le systeme matche et envoie.

2. **Envoi post-creation via l'admin** : apres creation, l'admin voit la liste des galeries et peut saisir/importer les contacts puis lancer l'envoi en batch.

**Recommandation :** Option 2 pour le MVP (plus flexible, pas de format CSV a imposer a la photographe), option 1 en amelioration.

---

## 9. Plan d'implementation recommande

### Prerequis (~15min)

- Passer `QUEUE_CONNECTION=database` dans `.env.prod` sur le NAS
- Redemarrer les containers `api-php` et `api-queue`

### Phase 1 — Service backend + commande artisan (~4-6h)

1. `SchoolSessionService` contenant toute la logique metier :
   - Lire l'arborescence d'un dossier local
   - Detecter le dossier `_photos-de-classe/` (prefixe `_`)
   - Traiter les photos de classe UNE FOIS, stocker les paths MinIO
   - Creer la galerie parente (type `event`)
   - Creer les galeries enfants (1 par sous-dossier)
   - Pour chaque galerie : dispatcher `ProcessPhotoJob` pour les portraits + creer les `Photo` records partages pour les photos de classe
   - Appliquer le product_type scolaire a chaque galerie
2. Commande artisan `school:create-from-folder` qui appelle le service
3. Tester avec un petit jeu (5 dossiers x 3 photos + 1 photo de classe)

### Phase 2 — Endpoint API (~2-3h)

1. Endpoint `POST /admin/school-sessions` (recoit path du dossier sur le NAS)
2. Endpoint `GET /admin/school-sessions/{id}/status` (progression batch)
3. FormRequest de validation

### Phase 3 — Frontend admin (~4-5h)

1. Vue `SchoolSessionCreate.vue` :
   - Formulaire (titre, date, categorie, product_types, chemin du dossier sur le NAS)
   - Apercu des sous-dossiers detectes (endpoint de scan)
   - Barre de progression (polling batch_id)
   - Resume final avec lien vers la galerie parente
2. Route admin + lien dans le menu

### Phase 4 — Envoi liens en batch (~3-4h)

1. Endpoint `POST /admin/school-sessions/{id}/send-access` (envoie email/SMS)
2. Import CSV ou saisie manuelle des contacts dans l'admin
3. Template email specifique scolaire

**Effort total estime : 13-18h de dev**

---

## 10. Decisions prises

| Question | Reponse |
|---|---|
| Source des photos | Dossier copie directement sur le NAS (USB/SMB/UI) |
| Envoi auto des liens | Oui, apres creation — saisie contacts dans l'admin (MVP) |
| Product type | 1 seul format impression, prix degressif a la quantite (a definir) |
| Template de prix | Oui, configurable par shooting, tarif pas encore decide |
| QUEUE_CONNECTION | A passer de `sync` a `database` (prerequis) |
| Photos de classe | Par classe (~30 enfants), traitees 1 fois, referencees dans les galeries de la classe (pas de duplication fichier) |
