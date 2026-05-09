# Photos Scolaires

Branche : `feature/school-photos`

Upload d'un ZIP structuré → N galeries privées indépendantes, une par enfant.

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

`Gallery.school_session_id` (nullable, FK cascade). Les galeries restent `type='private'` avec leur `access_token` + `share_code` — accès via `/gallery/{share_code}` uniquement.

Statuts : `uploading → extracting → creating_galleries → processing_photos → completed` (ou `failed`).

### Stockage MinIO

```
galleries/{gallery_uuid}/{original,preview,thumbnail}/   <- portraits
school-sessions/{session_uuid}/shared/{...}/             <- photos classe (1 seule fois)
```

Les galeries d'une classe partagent les mêmes chemins MinIO pour la photo de groupe — les records `Photo` pointent vers les mêmes fichiers, zéro duplication.

### Flow

1. `POST /admin/school-sessions` → crée la session (status `uploading`)
2. `PUT /upload` chunks 50MB → assemblage par `FILE_APPEND`
3. `POST /process` → dispatch `ProcessSchoolSessionJob` (tries=1, timeout=3600)
4. Job : extract ZIP → process shared photos (1×/classe) → crée galeries + Photos partagées → dispatch `ProcessPhotoJob` pour chaque portrait avec un `batch_id` commun → cleanup ZIP/temp
5. Front polle `GET /{id}` toutes les 5s. `show()` auto-transitionne `processing_photos → completed` quand le batch est complet.
6. `POST /send-emails` → `Mail::queue(GalleryAccessMail)` pour chaque contact

Progression live : `PhotoUpload::getBatchStatus($batch_id)` (completed / failed / total / progress).

### Product type

`print_scolaire` : 6/15/22 € par défaut (1/3/5 photos), configurable à la création via `product_types_config` JSON. Tarifs dégressifs implémentés via `PackTier` (min_quantity 3 et 5).

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
| Création session | Status `uploading`, modal détail s'ouvre |
| Upload ZIP | Barre + ETA, bascule à 100% |
| Traitement | Status passe par toutes les phases, ETA s'affiche après ~3s |
| Galeries créées | N galeries avec `share_code`, `access_token` uniques |
| Photos partagées | Chaque galerie d'une classe a la photo `_classe/`, mais MinIO ne contient qu'une seule copie dans `school-sessions/{id}/shared/` |
| Encodage | Nom avec accents (Léa, François) lisible côté admin |
| Wrapper folder | ZIP avec dossier racine unique fonctionne |
| Échec ZIP corrompu | Status `failed` + `error_message` affiché, fichiers temp nettoyés |
| Accès galerie | `/gallery/{share_code}` ouvre la galerie, ne demande pas de compte |
| Exclusion vue admin | "Galeries Clients" ne liste PAS les galeries scolaires |
| Envoi emails | Import CSV `nom;email` avec BOM, matching case-insensitive, mail reçu avec lien |
| Suppression | Cascade DB + MinIO (galeries + shared) |
| `print_scolaire` au panier | 1=6€, 3=15€, 5=22€ (avec config par défaut) |

### Endpoints à toucher

| Route | Action |
|---|---|
| `GET    /admin/school-sessions` | liste paginée |
| `POST   /admin/school-sessions` | créer |
| `GET    /admin/school-sessions/{id}` | détail + progress live |
| `GET    /admin/school-sessions/{id}/galleries` | galeries avec share_code |
| `PUT    /admin/school-sessions/{id}/upload` | chunk ZIP |
| `POST   /admin/school-sessions/{id}/process` | dispatch job |
| `POST   /admin/school-sessions/{id}/send-emails` | envoi batch |
| `DELETE /admin/school-sessions/{id}` | cascade |

---

## Fichiers concernés

### Nouveaux

**Backend**
- `database/migrations/2026_04_26_000001_create_school_sessions_table.php`
- `database/migrations/2026_04_26_000002_add_school_session_id_to_galleries_table.php`
- `app/Models/SchoolSession.php`
- `app/Services/SchoolSessionService.php` *(cœur de la feature : ZIP, photos partagées, galeries, cleanup)*
- `app/Jobs/ProcessSchoolSessionJob.php`
- `app/Http/Controllers/Api/Admin/SchoolSessionController.php`
- `app/Http/Requests/Admin/StoreSchoolSessionRequest.php`
- `app/Http/Requests/Admin/SendSchoolSessionEmailsRequest.php`

**Frontend**
- `web/src/views/admin/SchoolSessions.vue`
- `web/src/services/admin/schoolSessionApi.ts`
- `web/src/composables/useEta.ts`

### Modifiés

**Backend**
- `app/Models/Gallery.php` — `school_session_id` fillable + relation
- `app/Models/CartItem.php` — `print_scolaire` dans `PRODUCT_TYPES`
- `app/Traits/SyncsProductTypes.php` — règle validation
- `app/Http/Controllers/Api/GalleryController.php` — `whereNull('school_session_id')` dans `adminIndex`
- `routes/api.php` — 8 routes
- 6 FormRequests existants — `print_scolaire` dans `in:`

**Frontend**
- `web/src/types/admin.ts` — types SchoolSession, BatchProgress, etc.
- `web/src/services/adminApi.ts` — façade
- `web/src/router/index.ts` — route `/admin/school-sessions`
- `web/src/components/admin/AdminSidebar.vue` — menu "Photos Scolaires"
- `web/src/components/admin/ui/StatusBadge.vue` — 4 statuts
- `web/src/components/admin/ui/UploadProgress.vue` — ETA via `useEta`

---

## Étapes de dev / déploiement

1. Vérifier `QUEUE_CONNECTION=database` dans `deploy/.env.prod`
2. `docker exec api-php php artisan migrate --force`
3. `docker restart api-php api-queue`
4. Test rapide : créer une session avec un petit ZIP (2 classes, 3 enfants), vérifier status `completed` et qu'une galerie est accessible via son `share_code`
5. Vérifier les logs queue : `docker logs -f api-queue`

Migrations safe (nouvelle table + colonne nullable) — aucun impact sur l'existant.
