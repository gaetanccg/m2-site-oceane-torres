# Photos Scolaires

Branche : `feature/school-photos`

Upload d'un ZIP structuré → N galeries privées indépendantes, une par enfant.
Workflow complet : import → envoi liens → ventes → clôture → export ZIP des commandes.

---

## Workflow complet

1. **Import ZIP** : drag & drop dans l'admin → création automatique des galeries (watermark + thumbnails)
2. **Envoi des liens** : import CSV `nom;email` → matching auto → mail avec lien direct (1 clic)
3. **Consultation / paiement** : parents voient le message scolaire personnalisé, achètent par packs (1/3/5 photos), paient via SumUp
4. **Confirmation parent** : `OrderConfirmationMail` automatique avec facture (existant)
5. **Clôture** : bouton "Clôturer" → cart guard côté API + bandeau côté parents + bouton "Ajouter au panier" masqué (réversible)
6. **Export ZIP** : ZIP organisé `Classe/Eleve/photo.jpg` (5 copies si 5 commandés) + `_index.csv` récapitulatif
7. La photographe imprime / livre comme elle veut

---

## Comment ça fonctionne

### Input ZIP attendu

```
shooting/
  6eA/
    _classe/           <- photos de groupe (partagées dans la classe)
      photo-classe.jpg
    Martin Lucas/      <- 1 dossier = 1 galerie
      portrait1.jpg
      portrait2.jpg
    Dubois Emma/
      ...
  6eB/
    _classe/
    ...
```

- Dossier préfixé `_` = photos partagées de la classe
- Tout autre dossier = 1 galerie enfant
- Wrapper folder unique au root détecté et "unwrappé"
- Encodage CP437 (ZIP Windows) transcodé en UTF-8

### Modèle de données

`SchoolSession` (UUID) regroupe les galeries — c'est purement admin, aucun lien avec des comptes utilisateurs.
- `gallery_message` (text) : message personnalisé affiché aux parents (remplace le bloc 3 étapes)
- `closed_at` (timestamp) : si non null, les achats sont bloqués
- `class_name` (string) sur `Gallery` : nom de la classe, utilisé pour le groupement admin

`Gallery.school_session_id` (nullable, FK cascade). Les galeries restent `type='private'` avec leur `access_token` + `share_code` — accès via `/gallery/{share_code}` uniquement.

`SchoolSessionExport` (UUID) : suivi des exports ZIP des commandes (status, include_digital, file_path, file_size_bytes, processed/total_items).

Statuts session : `uploading → extracting → creating_galleries → processing_photos → completed` (ou `failed`).

### Stockage MinIO

```
galleries/{gallery_uuid}/{original,preview,thumbnail}/   <- portraits
school-sessions/{session_uuid}/shared/{...}/             <- photos classe (1 seule fois)
```

Les galeries d'une classe partagent les mêmes chemins MinIO pour la photo de groupe — les records `Photo` pointent vers les mêmes fichiers, zéro duplication.

### Flow technique

1. `POST /admin/school-sessions` → crée la session (status `uploading`)
2. `PUT /upload` chunks 50MB → assemblage par `FILE_APPEND`
3. `POST /process` → dispatch `ProcessSchoolSessionJob` (tries=1, timeout=3600)
4. Job : extract ZIP → process shared photos (1×/classe) → crée galeries + Photos partagées (avec `class_name`) → dispatch `ProcessPhotoJob` pour chaque portrait avec un `batch_id` commun → cleanup ZIP/temp
5. Front polle `GET /{id}` toutes les 5s. `show()` auto-transitionne `processing_photos → completed` quand le batch est complet.
6. `POST /send-emails` → `Mail::queue(GalleryAccessMail)` avec lien direct `/gallery/{share_code}` + `isDirectLink: true`

Progression live : `PhotoUpload::getBatchStatus($batch_id, includeUploads: false)` renvoie uniquement les counts agrégés via `COUNT(*) FILTER (WHERE...)` (optimisé pour 1000+ photos).

### Clôture des sessions

- `POST /admin/school-sessions/{id}/close` → set `closed_at = now()`
- `POST /admin/school-sessions/{id}/reopen` → set `closed_at = null`
- **Cart guard** : `CartService::addItem` vérifie `$gallery->schoolSession?->isClosed()` et lève une exception
- API publique `showByShareCode` retourne `school_closed: bool`
- `ProtectedGallery.vue` : si fermé, bandeau orange + `AddToCartButton` masqué

### Export ZIP des commandes

- `POST /admin/school-sessions/{id}/exports` (body: `include_digital: bool`)
- Crée un `SchoolSessionExport` + dispatch `GenerateSchoolSessionExportJob`
- Service `SchoolSessionExportService::build()` :
  - Query `OrderItem` joints à `Photo` (galleries de la session) avec `Order.status = 'paid'`
  - Filtre `product_type != 'digital'` sauf si toggle activé
  - Télécharge chaque photo unique 1× depuis MinIO vers temp
  - ZipArchive → `Classe/Eleve/photo.jpg` avec suffixe ` (2)`, ` (3)` pour collisions (5 copies = 5 fichiers)
  - Génère `_index.csv` (BOM UTF-8 Excel-compatible) avec colonnes `Classe;Eleve;Photo;Type;Quantite;Prix unitaire;Total ligne` + ligne TOTAL
  - Stocke ZIP dans `storage/app/private/exports/school-sessions/{session_id}/`
- Endpoints : `GET /{id}/exports/latest` (status + progression), `GET /school-session-exports/{id}/download` (BinaryFileResponse)
- Frontend : modal avec toggle digital + barre de progression (polling 3s) + bouton télécharger / régénérer
- Cleanup automatique des fichiers ZIP à la suppression de la session

### Product type

`print_scolaire` : 6/15/22 € par défaut (1/3/5 photos), configurable à la création via `product_types_config` JSON. Tarifs dégressifs implémentés via `PackTier` (min_quantity 3 et 5).

### Galeries scolaires côté client

- Route `/gallery/{share_code}` (existante)
- Si la session a un `gallery_message`, il remplace le bloc "Comment fonctionne votre galerie ?" (3 étapes par défaut) par un cadre simple avec le texte personnalisé
- Si `closed_at` non null, bandeau orange "Galerie fermée aux commandes" + boutons panier masqués

### Email d'accès parents

Réutilise `GalleryAccessMail` (existant) avec un nouveau flag `isDirectLink` :
- **Sessions scolaires** : URL = `/gallery/{share_code}` (lien direct, 1 clic), instructions simplifiées ("Cliquez simplement sur le bouton ci-dessus"), code montré en backup
- **Galeries classiques** : URL = `/gallery`, instructions en 3 étapes (saisie code), inchangé

---

## Comment tester

### Prérequis local

```bash
# .env: QUEUE_CONNECTION=database
docker exec api-php php artisan migrate
docker compose restart api-queue
```

### ZIP de test minimal

```
test/
  classe-A/
    _classe/photo-groupe.jpg
    Enfant Un/p1.jpg p2.jpg
    Enfant Deux/p1.jpg
  classe-B/
    Enfant Trois/p1.jpg
```

### Cas à vérifier

| Cas | Vérification |
|---|---|
| Création session | Modal s'ouvre, status `uploading` après création |
| Upload ZIP | Barre + ETA, bascule à 100% puis redirection vers la page détail |
| Traitement | Status passe par toutes les phases, ETA s'affiche après ~3s |
| Galeries créées | N galeries avec `share_code`, `access_token`, `class_name` uniques |
| Photos partagées | Chaque galerie d'une classe a la photo `_classe/`, mais MinIO ne contient qu'une seule copie dans `school-sessions/{id}/shared/` |
| Encodage | Nom avec accents (Léa, François) lisible côté admin |
| Wrapper folder | ZIP avec dossier racine unique fonctionne |
| Échec ZIP corrompu | Status `failed` + `error_message` affiché, fichiers temp nettoyés |
| Page détail | Pleine largeur, galeries groupées par classe avec accordéons |
| Recherche détail | Filtre par nom enfant OU nom de classe |
| Message scolaire | `/gallery/{share_code}` affiche le texte custom au lieu du bloc 3 étapes |
| Accès galerie | `/gallery/{share_code}` ouvre directement (avec ou sans code, ne demande pas de compte) |
| Exclusion vue admin | "Galeries Clients" ne liste PAS les galeries scolaires |
| Envoi emails | Import CSV `nom;email` avec BOM, matching case-insensitive, mail reçu avec lien DIRECT vers `/gallery/{share_code}` |
| Clôture | Bouton clôturer → bandeau orange parents + cart bloqué côté API + AddToCartButton masqué |
| Réouverture | Bouton rouvrir → cart à nouveau actif |
| Export ZIP commandes | Modal avec toggle digital, progression live, bouton download → ZIP avec arborescence + `_index.csv` |
| Export multiple unités | 5 prints du même photo = 5 fichiers `photo.jpg`, `photo (2).jpg`, ... |
| Suppression | Cascade DB + MinIO (galeries + shared + ZIP exports) |
| `print_scolaire` au panier | 1=6€, 3=15€, 5=22€ (avec config par défaut) |

### Endpoints

| Route | Action |
|---|---|
| `GET    /admin/school-sessions` | liste paginée |
| `POST   /admin/school-sessions` | créer |
| `GET    /admin/school-sessions/{id}` | détail + progress live (counts only) |
| `GET    /admin/school-sessions/{id}/galleries` | galeries avec `class_name` + `share_code` |
| `PUT    /admin/school-sessions/{id}/upload` | chunk ZIP |
| `POST   /admin/school-sessions/{id}/process` | dispatch job |
| `POST   /admin/school-sessions/{id}/send-emails` | envoi batch (lien direct) |
| `POST   /admin/school-sessions/{id}/close` | clôturer |
| `POST   /admin/school-sessions/{id}/reopen` | rouvrir |
| `POST   /admin/school-sessions/{id}/exports` | dispatch export ZIP |
| `GET    /admin/school-sessions/{id}/exports/latest` | status export |
| `GET    /admin/school-session-exports/{id}/download` | télécharger ZIP |
| `DELETE /admin/school-sessions/{id}` | cascade |

---

## Fichiers concernés

### Nouveaux

**Backend**
- `database/migrations/` :
  - `2026_04_26_000001_create_school_sessions_table.php`
  - `2026_04_26_000002_add_school_session_id_to_galleries_table.php`
  - `2026_05_01_000001_add_class_name_to_galleries_table.php`
  - `2026_05_01_000002_add_gallery_message_to_school_sessions_table.php`
  - `2026_05_01_000003_add_closed_at_to_school_sessions_table.php`
  - `2026_05_01_000004_create_school_session_exports_table.php`
- `app/Models/SchoolSession.php`
- `app/Models/SchoolSessionExport.php`
- `app/Services/SchoolSessionService.php` *(cœur de la feature : ZIP, photos partagées, galeries, cleanup)*
- `app/Services/SchoolSessionExportService.php` *(génération ZIP commandes + CSV index)*
- `app/Jobs/ProcessSchoolSessionJob.php`
- `app/Jobs/GenerateSchoolSessionExportJob.php`
- `app/Http/Controllers/Api/Admin/SchoolSessionController.php` *(13 endpoints)*
- `app/Http/Requests/Admin/StoreSchoolSessionRequest.php`
- `app/Http/Requests/Admin/SendSchoolSessionEmailsRequest.php`

**Frontend**
- `web/src/views/admin/SchoolSessions.vue` *(liste + modal création + upload)*
- `web/src/views/admin/SchoolSessionDetail.vue` *(page dédiée détail + galeries groupées par classe + envoi mails + export ZIP + clôture)*
- `web/src/services/admin/schoolSessionApi.ts`
- `web/src/composables/useEta.ts` *(temps restant sliding window 30s)*

### Modifiés

**Backend**
- `app/Models/Gallery.php` — `school_session_id` + `class_name` fillable + relation `schoolSession()`
- `app/Models/CartItem.php` — `print_scolaire` dans `PRODUCT_TYPES`
- `app/Models/PhotoUpload.php` — `getBatchStatus($id, $includeUploads = true)` optimisé COUNT
- `app/Mail/GalleryAccessMail.php` — flag `isDirectLink` (default false)
- `app/Services/CartService.php` — cart guard pour sessions clôturées
- `app/Traits/SyncsProductTypes.php` — règle validation `print_scolaire`
- `app/Http/Controllers/Api/GalleryController.php` — `whereNull('school_session_id')` dans `adminIndex` + expose `school_message` + `school_closed` dans `showByShareCode`
- `routes/api.php` — 13 routes
- 6 FormRequests existants — `print_scolaire` dans `in:`
- `resources/views/emails/gallery-access.blade.php` — instructions conditionnelles selon `isDirectLink`

**Frontend**
- `web/src/types/admin.ts` — types SchoolSession, BatchProgress, SchoolSessionExport, etc.
- `web/src/services/adminApi.ts` — façade
- `web/src/router/index.ts` — routes `/admin/school-sessions` + `/admin/school-sessions/:id`
- `web/src/components/admin/AdminSidebar.vue` — menu "Photos Scolaires"
- `web/src/components/admin/ui/StatusBadge.vue` — 4 statuts scolaires
- `web/src/components/admin/ui/UploadProgress.vue` — ETA via `useEta`
- `web/src/views/ProtectedGallery.vue` — affichage message scolaire personnalisé + bandeau "fermée"

---

## Étapes de dev / déploiement

1. Vérifier `QUEUE_CONNECTION=database` dans `deploy/.env.prod`
2. `docker exec api-php php artisan migrate --force` (joue les 6 migrations dans l'ordre)
3. `docker restart api-php api-queue`
4. Test rapide : créer une session avec un petit ZIP (2 classes, 3 enfants), vérifier status `completed`
5. Tester le flow complet : envoi mails → achat test → clôture → export ZIP
6. Vérifier les logs queue : `docker logs -f api-queue`

Migrations safe (nouvelles tables + colonnes nullables) — aucun impact sur l'existant.

---

## Optimisations scaling (1000+ photos)

- **`PhotoUpload::getBatchStatus`** : paramètre `$includeUploads` (false par défaut côté school session show). Utilise `COUNT(*) FILTER (WHERE...)` au lieu de hydrater toutes les rows. Polling : ~150 KB → ~200 octets toutes les 5s.
- **`SchoolSessionService::createChildGallery`** : `copy()` au lieu de `file_get_contents + Storage::put` pour copier les portraits en temp. RAM allouée minimale même pour 1000 photos.
- **Export ZIP** : chaque photo unique téléchargée 1 seule fois depuis MinIO, puis référencée multiple fois dans le ZIP via `addFile($tempPath, $zipPath)` (lecture lazy au close).
