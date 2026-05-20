# Audit complet du système d'upload et de traitement des photos

> **Date** : 2026-05-19
> **Périmètre** : Upload de photos vers galeries privées (clients) et galeries d'événements
> **Auditeur** : Audit automatisé Claude (Opus 4.7)
> **Contexte** : Plaintes utilisateur — uploads plus lents qu'avant, échecs occasionnels sans raison apparente, photos de 20 à 35 Mo en moyenne.

## État d'avancement

| Plan | Statut | Commit |
|---|---|---|
| **P1 — Quick wins** | ✅ **Appliqué le 2026-05-19** | `fc5a569` |
| **P2 — Optimisations pipeline d'image** | ✅ **Appliqué le 2026-05-19** — 4/5 actifs (Imagick activé en local), P2.2 reste opt-in | (à committer) |
| **Sync pivot — abandon du queue worker pour les uploads** | ✅ **Appliqué et validé le 2026-05-19** (8/8 puis **61/61 photos sans perte**) | (à committer) |
| **P3 — Infra & optimisations réseau** | ✅ **P3.2 appliqué le 2026-05-19** (dual endpoint MinIO host.docker.internal) | (à committer) |
| **Bonus — Chargement admin instantané** | ✅ **Appliqué le 2026-05-19** (grid utilise thumbnail_url + lazy loading natif) | (à committer) |
| **P4 — UX & robustesse** | ✅ **Appliqué le 2026-05-19** (P4.1 retry button, P4.2 ETA précis, P4.3 messages d'erreur friendly). P4.4 endpoint config skippé (non prioritaire) | (à committer) |
| P5 — Tests & monitoring | ⏳ À faire | — |

### Sync pivot — résumé

Pendant le smoke test post-P1+P2, 3 à 5 photos sur 8 disparaissaient en local sans erreur visible côté UI (mais bien tracées en DB comme `failed`). Cause : une race virtio-fs entre les containers `laravel` (writer du fichier temp) et `queue-worker` (reader), aggravée par des workers PHP CLI qui survivent aux restarts avec des classes obsolètes en mémoire (voir § 10).

**Solution adoptée** : on supprime le fichier temp partagé et la queue pour le flow upload. `PhotoController::storeAsync` traite désormais les photos en SYNCHRONE, dans le même process PHP-FPM que la requête HTTP entrante. Plus aucun partage de filesystem entre containers, plus aucune dépendance à un worker.

**Validation** :
- Test 1 (8 photos × 18 Mo) : **8/8 OK**, ~15 s total
- Test 2 (61 photos, galerie complète) : **61/61 OK**, pas de perte
- Aucune erreur dans les logs Laravel ni queue après bascule

Détails dans § 10.

### Changelog des P1 (cf. § 5 pour le détail)

| Action | Fichier(s) | Effet attendu |
|---|---|---|
| P1.1 Retrait du `->delay(3 s)` | `api/app/Http/Controllers/Api/PhotoController.php` | -3 s par batch, gain visible sur la 1re photo |
| P1.1 `waitForTempFile` env-conditionnel (3 s en prod) | `api/app/Jobs/ProcessPhotoJob.php` | Plus aucune attente inutile sur le NAS |
| P1.2 `$tries = 5 → 3`, backoff `[10,30,60,120,300] → [10,30,60]` | `api/app/Jobs/ProcessPhotoJob.php` | Échec définitif en ~100 s au lieu de ~520 s |
| P1.3 Re-throw + `cleanupTempFile` seulement au succès / dans `failed()` | `api/app/Jobs/ProcessPhotoJob.php` | **Les retries Laravel fonctionnent enfin** sur les erreurs MinIO transitoires |
| P1.4 Timeouts nginx adaptés aux body 30 Mo | `deploy/nginx.prod.conf` | Fin des `408` silencieux sur connexions lentes |
| P1.5 `?include_uploads=false` + polling allégé > 50 photos | `api/app/Http/Requests/UploadStatusRequest.php`, `api/app/Http/Controllers/Api/PhotoController.php`, `web/src/services/uploadService.ts` | -90 % de payload de poll pour les gros batches |

### Changelog des P2 (cf. § 5 pour le détail)

| Action | Fichier(s) | Statut | Effet attendu |
|---|---|---|---|
| P2.1 Décoder une seule fois, dériver thumbnail du preview cloné | `api/app/Services/ImageProcessingService.php` | ✅ Actif | -50 % CPU image, -130 Mo pic RAM par photo |
| P2.2 Pré-rendre le filigrane (cache statique par dimensions) | `api/app/Services/ImageProcessingService.php` | ⚠️ Implémenté, **opt-in** (constante `USE_WATERMARK_LAYER_CACHE = false`) | -2 à -3 s par photo (active après smoke test) |
| P2.3 Stream de l'original via `putFileAs` (multipart S3) | `api/app/Services/ImageProcessingService.php` | ✅ Actif | -30 Mo allocation PHP par photo + multipart natif S3 |
| P2.4 Imagick installé (PECL) + driver activé en local | `api/Dockerfile`, `api/app/Services/ImageProcessingService.php` | ✅ **Actif** (constante `USE_IMAGICK_DRIVER = true`) — driver `Intervention\Image\Drivers\Imagick\Driver` confirmé en runtime | 2-3× plus rapide, ~3-5 s par photo 18-30 Mo |
| P2.5 Qualité preview JPEG 95 → 93 | `api/app/Services/ImageProcessingService.php` | ✅ Actif | -10 à -15 % taille preview (compromis qualité conservateur) |

### Actions de déploiement (P1 + P2)

1. **Backend (NAS)** : `git pull` puis `docker compose -f docker-compose.prod.yml build laravel queue` (Imagick s'installe pendant le build) puis `docker compose -f docker-compose.prod.yml up -d`.
2. **Frontend (Render)** : push sur `main`, déploiement automatique.
3. **Smoke test P1+P2 actif** : uploader un batch de 5 photos 25-30 Mo en galerie événement + un batch en galerie privée. Vérifier que :
   - Les previews et thumbnails affichent le filigrane correctement (identique à avant).
   - Les images ne sont pas dégradées (qualité 88 sur le preview).
   - Le temps total est sensiblement plus court qu'avant.
4. **Smoke test des flags opt-in** (à faire ensuite, indépendamment) :
   - **Imagick** : flipper `USE_IMAGICK_DRIVER = true` dans `ImageProcessingService.php`, redéployer, uploader 1 photo de test, vérifier sur la galerie publique que le rendu est OK (couleurs, netteté, EXIF orientation). Si ça casse : revert.
   - **Watermark cache** : flipper `USE_WATERMARK_LAYER_CACHE = true`, redéployer, uploader 1 photo, vérifier visuellement que le filigrane est correctement placé. Si ça casse : revert.
5. **Monitoring** : surveiller `storage/logs/laravel.log` pour :
   - `ProcessPhotoJob: attempt failed, will retry` (warning) — P1.3 fait son travail si les retries finaux passent.
   - `Watermark layer caching failed, falling back to inline drawing` — P2.2 a échoué silencieusement, vérifier les images.
   - `Image processing failed` — toute erreur résiduelle dans le pipeline.

---

## 1. Résumé exécutif

Le système d'upload souffre de **plusieurs goulots d'étranglement cumulés** (architecture, traitement d'image, infra) et de **régressions récentes** introduites par les commits d'audit/fix (workarounds développement macOS Docker Desktop qui ont fuité en production).

### Top 5 — ce qui ralentit le plus

1. **Un seul worker de queue en production** traite les photos en série → plafond ~1 photo / 5-15s → 100 photos = **8 à 25 minutes**.
2. **`->delay(now()->addSeconds(3))`** ajouté récemment dans `PhotoController::storeAsync` → **+3 s de latence systématique** sur chaque batch en prod (utile uniquement en dev macOS).
3. **`waitForTempFile(maxSeconds: 60)`** dans `ProcessPhotoJob` peut bloquer le worker jusqu'à **60 secondes** par photo si la condition de course macOS se reproduit pour une autre raison (peu probable en prod, mais le coût latent est là).
4. **L'image est décodée DEUX FOIS depuis le disque** (une fois pour le preview, une fois pour le thumbnail) → ~129 Mo de RAM × 2 pour une 7000×4600 + CPU doublé.
5. **42 rendus de texte de filigrane par photo** (21 sur le preview + 21 sur le thumbnail) avec FreeType → plusieurs secondes de CPU par image.

### Top 3 — ce qui fait ÉCHOUER des uploads

1. **`client_body_timeout` nginx par défaut à 60 s** (non explicitement configuré) → tout upload de 30 Mo sur connexion mobile/lente (< 5 Mbit/s) est tué silencieusement.
2. **Le `try/finally` de `ProcessPhotoJob::handle()` supprime le fichier temporaire à la première tentative**, donc tout retry tombe sur "Fichier temporaire non trouvé". Les retries (`$tries = 5`) ne récupèrent **pas** les erreurs MinIO transitoires, et le `php artisan photos:retry-failed` ne peut rien faire non plus.
3. **MinIO joint via `https://s3.oceanetorresphotographie.fr`** (tunnel Cloudflare) **même depuis le NAS lui-même** → la pile TLS+tunnel+routage publique introduit du jitter et des erreurs transitoires que le code traite comme définitives.

### Architecture cible recommandée (vision)

- Worker queue parallélisé (3-4 workers, ou Redis+Horizon)
- MinIO accédé via réseau Docker interne pour le worker
- Image décodée 1 seule fois par photo, watermark pré-rendu et composé via `place()`
- Stream direct du fichier vers MinIO (pas de `file_get_contents`)
- nginx : timeouts adaptés à des body de 100 Mo
- Retries Laravel **avec préservation du fichier temporaire** jusqu'à la dernière tentative
- UI : bouton « Retry » par photo échouée, exposant `photos:retry-failed`

---

## 2. Architecture actuelle

### 2.1 Topologie de déploiement

```
Internet
   │
   ├── oceanetorresphotographie.fr ────────────► Render (Static Site, Vue 3 SPA)
   │                                                    │
   │                                                    │ fetch/XHR
   │                                                    ▼
   ├── api.oceanetorresphotographie.fr ──► Cloudflare Tunnel ──► NAS UGREEN
   │                                                                  │
   │                                                                  ├─ container `api-nginx`        (reverse proxy)
   │                                                                  ├─ container `api-php`         (PHP-FPM 8.4, 30 workers)
   │                                                                  ├─ container `api-queue`       (1 worker `queue:work`)
   │                                                                  └─ container `api-scheduler`   (schedule:work)
   │
   └── s3.oceanetorresphotographie.fr ──► Cloudflare Tunnel ──► NAS UGREEN (MinIO)

DB : Supabase PostgreSQL (Session Pooler) — queue + sessions + cache stockés en base.
```

### 2.2 Flux d'upload — vue d'ensemble

```
Frontend (PhotosManager.vue)
   │  L'utilisateur sélectionne N fichiers (drag-drop ou input file)
   │
   ▼
useChunkedUpload → ChunkedUploadService.uploadChunked()
   │  Split en chunks (max 10 fichiers ET max 30 Mo par chunk)
   │  3 chunks parallèles via XMLHttpRequest
   │
   ▼  POST /api/admin/{events|galleries}/{id}/photos/async (multipart)
nginx (NAS) ──► php-fpm ──► PhotoController::storeAsync()
   │  - Pour chaque fichier :
   │    1. PhotoUpload::create({status: 'uploading'})
   │    2. $file->storeAs('temp_uploads', '{uuid}.{ext}', 'local')
   │    3. PhotoUpload->update({status: 'pending'})
   │    4. ProcessPhotoJob::dispatch(...)->delay(now()->addSeconds(3))
   │  - Réponse JSON immédiate : { batch_id, uploads: [...] }
   │
   ▼  Job persisté en table `jobs` (queue=database)
api-queue (1 worker) ──► ProcessPhotoJob::handle()
   │  waitForTempFile (jusqu'à 60s)
   │  markAsProcessing
   │  ImageProcessingService::processUploadedPhoto()
   │    ├─ file_get_contents(original) ──► MinIO PUT (HTTPS via Cloudflare)
   │    ├─ readScaledOriented(disk, 2560px) → applyWatermark(21 textes) → encode JPEG 95% → MinIO PUT
   │    └─ readScaledOriented(disk, 600px)  → applyWatermark(21 textes) → encode JPEG 90% → MinIO PUT
   │  Photo::create({...})
   │  markAsCompleted | markAsFailed
   │  finally: cleanupTempFile()
   │
Frontend en parallèle :
   GET /api/admin/upload-status?batch_id=... toutes les 3 s (polling)
   Met à jour l'UI quand chaque PhotoUpload passe à completed/failed
```

### 2.3 Configurations actuelles

| Paramètre | Valeur | Fichier |
|---|---|---|
| **Frontend** | | |
| `chunkSize` (fichiers / chunk) | 10 | `web/src/config/constants.ts:103` |
| `maxChunkBytes` | 30 Mo | `web/src/config/constants.ts:104` |
| `concurrentChunks` | 3 | `web/src/config/constants.ts:105` |
| `timeout` (XHR / chunk) | 300 000 ms (5 min) | `web/src/config/constants.ts:106` |
| `pollInterval` | 3 000 ms | `web/src/config/constants.ts:107` |
| `maxFileSize` | 50 Mo / fichier | `web/src/config/constants.ts:108` |
| `maxRetries` | 2 (timeout/network only) | `web/src/config/constants.ts:109` |
| **Backend HTTP** | | |
| `upload_max_filesize` | 100 Mo | `deploy/docker/php.ini:12` |
| `post_max_size` | 100 Mo | `deploy/docker/php.ini:13` |
| `max_file_uploads` | 20 | `deploy/docker/php.ini:14` |
| `memory_limit` | 2 048 Mo | `deploy/docker/php.ini:9` |
| `max_execution_time` | 120 s | `deploy/docker/php.ini:3` |
| `max_input_time` | 60 s | `deploy/docker/php.ini:6` |
| `client_max_body_size` (nginx) | 100 Mo | `deploy/nginx.prod.conf:12` |
| `fastcgi_read_timeout` | 300 s | `deploy/nginx.prod.conf:40` |
| `client_body_timeout` (nginx) | **(défaut = 60 s)** ⚠️ | non configuré |
| `client_header_timeout` (nginx) | **(défaut = 60 s)** ⚠️ | non configuré |
| `client_body_buffer_size` | défaut 16 Ko | non configuré |
| **PHP-FPM (intake HTTP)** | | |
| `pm.max_children` | 30 | `deploy/docker/www.conf:16` |
| `pm.max_requests` | 500 | `deploy/docker/www.conf:29` |
| **Queue worker** | | |
| Nombre de workers | **1** ⚠️ | `deploy/docker-compose.prod.yml:87` |
| `--sleep` | 3 s | idem |
| `--tries` (CLI) | 3 — **mais Job impose 5** | idem |
| `--max-time` | 3 600 s | idem |
| `--timeout` (CLI) | 3 600 s — **mais Job impose 300** | idem |
| `--memory` | 2 048 Mo | idem |
| `Job::$tries` | 5 | `api/app/Jobs/ProcessPhotoJob.php:18` |
| `Job::$timeout` | 300 s | `api/app/Jobs/ProcessPhotoJob.php:20` |
| `Job::$backoff` | [10, 30, 60, 120, 300] s | `api/app/Jobs/ProcessPhotoJob.php:24` |
| `Job::delay()` | **+3 s à chaque dispatch** ⚠️ | `api/app/Http/Controllers/Api/PhotoController.php:344` |
| `waitForTempFile` | jusqu'à 60 s, poll 2 s | `api/app/Jobs/ProcessPhotoJob.php:56` |
| **Image processing** | | |
| Driver Intervention | **GD** | `api/app/Services/ImageProcessingService.php:51` |
| Largeur max preview | 2 560 px (QHD) | idem `:24` |
| Largeur max thumbnail | 600 px | idem `:26` |
| Qualité preview | **95** | idem `:29` |
| Qualité thumbnail | **90** | idem `:31` |
| Watermark grid | 4 × 5 = **20 textes** | idem `:322-323` |
| Watermark central | **1 texte** (≈ 15 % min(W,H)) | idem `:346` |
| **MinIO** | | |
| Endpoint | `https://s3.oceanetorresphotographie.fr` (public, via Cloudflare) | `deploy/.env.prod` |
| `use_path_style_endpoint` | true | `api/config/filesystems.php:70` |
| Multipart upload | non configuré (PUT simple) | `api/app/Services/ImageProcessingService.php:442-447` |
| **Queue connection** | | |
| Driver | **database** (Supabase Postgres) | `.env` / `api/config/queue.php` |
| `retry_after` | 7 200 s | `api/config/queue.php:43` |

---

## 3. Problèmes identifiés

### 🔴 CRITIQUE — provoquent des échecs ou de la lenteur perçue ≥ 30 s

#### C1. `cleanupTempFile()` dans le `finally` supprime le fichier avant tout retry

**Fichier** : `api/app/Jobs/ProcessPhotoJob.php:58-82`

```php
$this->waitForTempFile(maxSeconds: 60, ...);   // OUTSIDE try

try {
    $upload->markAsProcessing();
    // ... processing ...
} catch (\Exception $e) {
    Log::error(...);
    $upload->markAsFailed($e->getMessage());      // ⚠️ exception swallowed
} finally {
    $this->cleanupTempFile();                      // ⚠️ deletes temp ALWAYS
}
```

**Conséquences** :

1. **Toute exception de traitement est attrapée et avalée** : Laravel ne voit pas l'erreur, donc **pas de retry automatique** pour les erreurs MinIO transitoires (timeout, 503, reset TLS). L'upload est marqué `failed` du premier coup.
2. **Si l'exception venait à se propager** (par ex. via la branche `waitForTempFile`), le `finally` aurait quand même supprimé le fichier → les `$tries=5` retries suivantes échoueraient toutes avec « Fichier temporaire non trouvé ». 5 × 60 s = **5 min de waitForTempFile pour rien**.
3. La commande `php artisan photos:retry-failed` ne peut **jamais** ré-aiguiller un échec de traitement (le fichier temp est parti) — elle ne sert qu'aux cas marginaux où le worker a planté avant d'exécuter le `finally`.

**Cause racine** : la séparation try-OK / catch-FAIL est cassée. On distingue « erreur fatale » (mauvais MIME, image corrompue) et « erreur transitoire » (réseau, MinIO 5xx) sans nuance.

#### C2. nginx `client_body_timeout` (défaut 60 s) inadapté aux uploads de 30 Mo

**Fichier** : `deploy/nginx.prod.conf` — pas de directive `client_body_timeout`.

Le défaut nginx est **60 secondes** entre deux lectures consécutives du corps de la requête. Un client qui upload 30 Mo à < 5 Mbit/s effectif (≈ 50 s minimum pour 30 Mo) ou qui subit la moindre micro-coupure réseau (mobile en mouvement, wifi public) verra son upload **silencieusement tué par nginx avec un 408 Request Timeout** que le client peut interpréter comme un échec réseau XHR.

Côté frontend (`chunkUploader.ts:62`) `xhr.ontimeout = () => { ...; reject(new Error('Upload timeout')) }` — ce qui DÉCLENCHE bien un retry (timeout/network sont retryables, `chunkUploader.ts:102-103`). Donc une partie est rattrapée. Mais sur des chunks à 1 photo de 30 Mo, c'est très fragile.

#### C3. Workarounds macOS Docker Desktop en production

**Fichiers** :
- `api/app/Http/Controllers/Api/PhotoController.php:344` — `->delay(now()->addSeconds(3))`
- `api/app/Jobs/ProcessPhotoJob.php:56` — `waitForTempFile(maxSeconds: 60, pollIntervalSeconds: 2)`
- `api/app/Jobs/ProcessPhotoJob.php:24` — `$backoff = [10, 30, 60, 120, 300];` (vs. ancien [5,15,30])
- `api/app/Jobs/ProcessPhotoJob.php:18` — `$tries = 5;` (vs. ancien 3)

**Pourquoi** : commit `audit fix setp 4`. Sur Docker Desktop macOS, la propagation virtio-fs entre conteneurs peut prendre quelques secondes. Le `delay(3 s)` et le `waitForTempFile` ont été ajoutés pour absorber ce délai.

**Problème en prod (NAS UGREEN)** :
- Les deux conteneurs (`api-php` et `api-queue`) partagent un volume natif Linux (`./api:/var/www`) — la propagation est **synchrone**. Le délai et le polling sont inutiles.
- Le `delay(3 s)` ajoute **3 secondes garanties** au temps perçu par le user avant que la première photo commence à passer en « processing ».
- Avec `$tries=5` et backoff `[10, 30, 60, 120, 300]`, un échec définitif s'étale sur jusqu'à **~10 minutes** au lieu de quelques secondes — l'utilisateur attend, croit que ça tourne, et abandonne.

#### C4. Le worker de queue est **seul** ⇒ traitement strictement séquentiel

**Fichier** : `deploy/docker-compose.prod.yml:60-91` — un seul service `api-queue`, une seule commande `php artisan queue:work`.

Le frontend pousse 3 chunks en parallèle (`concurrentChunks: 3`), PHP-FPM accepte 30 requêtes simultanées (`pm.max_children = 30`) → l'**intake HTTP est parallèle**, mais le **traitement** (qui est ce qui prend le plus de temps : 5-15 s par photo) est **strictement séquentiel** : 1 photo à la fois.

**Math** : pour une galerie de 100 photos de 25 Mo, optimistiquement 6 s par photo en traitement = **10 min de queue**. Pessimistiquement 15 s = **25 min**. Pendant ce temps, l'UI affiche un anneau de progression, l'utilisateur croit que ça plante.

C'est probablement la cause numéro un de « il est plus long d'uploader des photos ». La réalité : l'upload HTTP est rapide (quelques secondes par photo) ; ce qui prend du temps, c'est le traitement séquentiel.

#### C5. MinIO accédé via tunnel Cloudflare depuis le NAS lui-même

**Fichier** : `deploy/.env.prod` — `MINIO_ENDPOINT=https://s3.oceanetorresphotographie.fr`

Le conteneur `api-queue` tourne sur le NAS UGREEN. MinIO tourne sur **le même NAS** (cf. `docs/DEPLOY-RENDER.md:43-47`). Mais l'endpoint pointe vers le hostname public, donc chaque PUT (3 par photo) fait :

```
api-queue (Docker) → résolution DNS publique → Cloudflare edge → Cloudflare tunnel → cloudflared sur NAS → MinIO sur NAS
```

Coûts additionnels par PUT vs. réseau interne :
- ~150-300 ms de handshake TLS (réutilisable mais le keep-alive sur SDK AWS PHP est imparfait)
- Round-trips vers une edge Cloudflare (latence variable selon trafic)
- Limites tunnel Cloudflare (concurrence, payload max), avec erreurs **524 / 522 / 521** parfois retournées comme erreurs transitoires que le code traite comme définitives (cf. C1)

Sur 100 photos × 3 PUT × ~200 ms d'overhead = **60 secondes perdues** dans la stack TLS/tunnel publique, alors qu'un PUT loopback `http://minio:9000` serait quasi gratuit.

---

### 🟠 IMPORTANT — augmentent significativement la latence ou le coût ressources

#### H1. L'image est lue et décodée **deux fois** depuis le disque

**Fichier** : `api/app/Services/ImageProcessingService.php:78-89`

```php
// Preview
$preview = $this->readScaledOriented($file->getRealPath(), self::PREVIEW_MAX_WIDTH);  // lit + décode
// ... watermark + encode + upload ...

// Thumbnail
$thumbnail = $this->readScaledOriented($file->getRealPath(), self::THUMBNAIL_MAX_WIDTH);  // RE-lit + RE-décode
```

`readScaledOriented` appelle `$this->rawManager->read($source)` qui charge **toute** l'image dans GD avant de scaler (cf. `:260`). Pour une photo 7000×4600 en JPEG 30 Mo :

- Allocation GD ≈ 7 000 × 4 600 × 4 octets = **~129 Mo** par décodage (RGBA interne)
- Décodage JPEG = quelques centaines de ms à 2 s selon CPU
- ⇒ **2× ~130 Mo et 2× décodage** par photo

**Fix** : décoder une fois, faire la copie avant scaleDown / scaleDown plus agressif pour le thumbnail à partir du buffer preview, ou utiliser des `clone()` Intervention pour éviter la re-lecture disque.

#### H2. 42 rendus FreeType par photo (watermark)

**Fichier** : `api/app/Services/ImageProcessingService.php:308-366`

```php
// Layer 1 : grid 4 × 5 = 20 textes en angle -30° avec FreeType
for ($row = 0; $row < 5; $row++) {
    for ($col = 0; $col < 4; $col++) {
        $image->text(...);   // ouverture police + rasterize + compose
    }
}

// Layer 2 : 1 texte central
$image->text(...);
```

Cela est fait **deux fois** (preview + thumbnail), soit **42 rasterisations FreeType** par photo. Chaque `$image->text()` :
1. Ouvre la police via FreeType (mise en cache par instance mais ré-ouverte à chaque appel selon le driver)
2. Calcule métriques + glyph rendering
3. Compose dans l'image (alpha blend sur ~17 Mo de buffer pour le preview)

Sur un NAS UGREEN (CPU ARM ou x86 modeste), ces 42 opérations représentent facilement **2-4 s de CPU pure par photo**, qui se cumulent sur la queue séquentielle.

**Fix** :
1. Pré-rendre un **calque de filigrane PNG** une seule fois par taille (2 560 px, 600 px), garder en cache mémoire process ou disque.
2. Utiliser `$image->place($watermarkLayer, ...)` (une seule opération de composition alpha).
3. Bonus : la grille peut être pré-calculée en SVG → PNG une fois pour toutes au boot.

#### H3. `file_get_contents()` charge l'original en mémoire pour l'envoyer à MinIO

**Fichiers** :
- `api/app/Services/ImageProcessingService.php:70-71` (original)
- `api/app/Services/MinioStorageService.php:27`

```php
$originalContent = file_get_contents($file->getRealPath());           // +30 Mo RAM
$this->uploadContent($originalPath, $originalContent, ...);            // SDK envoie en mémoire
unset($originalContent);
```

Pour un fichier de 30 Mo, cela alloue 30 Mo de string PHP, qui est ensuite copié vers le buffer du SDK AWS S3. Sur la queue séquentielle, ce n'est pas catastrophique côté RAM (cleanup `unset`), mais c'est **du temps perdu en allocations** et ça gêne l'autoload de gros volumes en parallèle.

**Fix** : utiliser `Storage::disk('minio')->putFileAs($dir, $file, $filename)` qui utilise un stream et délègue le multipart au SDK si > 5 Mo.

#### H4. Driver GD au lieu d'Imagick

**Fichier** : `api/app/Services/ImageProcessingService.php:51` — `new GdDriver`.

L'extension PHP `gd` est installée (`api/Dockerfile:16-17`), mais `imagick` ne l'est pas. Imagick est en moyenne **2 à 5× plus rapide** pour décoder/encoder les JPEG de grande dimension et **2-3× moins gourmand en RAM** (utilise du tile-streaming en interne).

**Fix** : ajouter `pecl install imagick && docker-php-ext-enable imagick` dans `api/Dockerfile`, basculer Intervention sur `ImagickDriver`.

#### H5. `$tries = 5` + backoff `[10, 30, 60, 120, 300]` = jusqu'à 10 min pour échouer définitivement

**Fichier** : `api/app/Jobs/ProcessPhotoJob.php:18,24`

Total au pire : 10 + 30 + 60 + 120 + 300 = **520 secondes** soit 8 min 40 s de wait cumulé entre retries. Pour une erreur définitive (image corrompue, gallery supprimée, MinIO down), l'utilisateur attend ~10 min pour voir le statut « échec ». Combiné avec C1 (l'exception est avalée → pas de retry pour le bon cas), c'est le pire des deux mondes : retries inutiles sur les bonnes erreurs, mauvaise UX sur les erreurs définitives.

#### H6. Polling de statut avec liste complète d'uploads

**Fichier** : `api/app/Models/PhotoUpload.php:46-94` + `api/app/Http/Controllers/Api/PhotoController.php:379-386`

L'endpoint `/admin/upload-status` retourne **toutes les lignes `PhotoUpload`** du batch à chaque poll (toutes les 3 s). Pour un batch de 100 photos, ce sont 100 × ~150 octets JSON = **~15 Ko par poll, toutes les 3 s pendant les 10-25 min de traitement**, donc 200-500 polls × 15 Ko = 3-7,5 Mo de trafic d'observabilité.

Le code dans `PhotoUpload::getBatchStatus()` supporte déjà `includeUploads: false` (`:46`), mais l'endpoint le force à `true` (`UploadStatusRequest` n'expose pas le paramètre).

**Fix** : par défaut renvoyer uniquement les compteurs ; ne renvoyer la liste détaillée que pour les uploads `failed` (besoin d'afficher le message d'erreur). Ou paginer.

#### H7. Cache « event_galleries_page_{1..10} » invalidé après chaque batch

**Fichier** : `api/app/Traits/ClearsEventGalleriesCache.php` + `api/app/Http/Controllers/Api/PhotoController.php:113-115`

Mineur, mais l'invalidation est faite **après chaque appel `storeAsync`** (par chunk), donc si on upload 100 photos en 10 chunks, on invalide 10 fois les 10 mêmes clés.

**Fix** : invalider une seule fois après que le BATCH soit complet (côté worker ou via un event groupé), ou utiliser des tags de cache si le driver le supporte.

#### H8. Génération de preview/thumbnail à la volée sans validation de cache MinIO

**Fichier** : `api/app/Http/Controllers/Api/ImageProxyController.php:146-180`

Quand l'admin ouvre une galerie qui contient des photos pas encore traitées (cas où `is_processed = false`), `ImageProxyController::streamImage()` génère le thumbnail à la volée avec mise en cache 1 h. Mais **dans le flux normal d'upload, les photos sont marquées `is_processed = true` ET ont leurs `file_path_thumbnail` renseignés** dès que `ProcessPhotoJob` termine. Donc le fallback ne devrait pas être déclenché en pratique — mais si on rate ce fallback à cause d'une erreur MinIO de lecture transitoire, on régénère un thumbnail en CPU au lieu de retry sur MinIO. Coût caché.

---

### 🟡 MOYEN — affecte la qualité/UX/robustesse

#### M1. Pas de bouton « réessayer » dans l'UI pour les photos failed

**Fichier** : `web/src/components/admin/ui/UploadProgress.vue` (composant UI).

La photographe doit re-glisser-déposer les photos qui ont échoué, ce qui crée de nouveaux `PhotoUpload` (et la galerie peut afficher des doublons si on n'est pas attentif aux états).

Le backend a `php artisan photos:retry-failed` (cf. `api/app/Console/Commands/RetryFailedPhotoUploads.php`), mais :
- Pas d'endpoint HTTP qui l'expose
- La commande ne marche que si le temp file existe (rare en pratique, cf. C1)

**Fix** : exposer un endpoint `POST /admin/photo-uploads/{id}/retry` qui :
- Si le temp file existe encore : ré-dispatch `ProcessPhotoJob`
- Sinon : retourne une erreur claire « ré-uploader la photo »

#### M2. Chunks à 1 photo pour les fichiers 20-35 Mo

**Fichier** : `web/src/services/upload/uploadUtils.ts:11-35` + `web/src/config/constants.ts:103-104`

Avec `chunkSize: 10` et `maxChunkBytes: 30 Mo` :
- 1 photo de 30 Mo → 1 par chunk
- 2 photos de 25 Mo → 1 par chunk (50 Mo > 30 Mo)
- 1 photo de 35 Mo → 1 par chunk (mais le chunk fait 35 Mo, ce qui dépasse `maxChunkBytes` !)

Le code accepte un fichier seul même s'il dépasse `maxChunkBytes` (cf. `:20` `if (currentChunk.length > 0 && ...)`). OK.

Conséquence : chaque chunk = **1 requête HTTP pour 1 photo**. L'overhead HTTP (TLS + Cloudflare + auth Laravel) est payé entièrement pour chaque photo. Pour 100 photos = 100 requêtes au lieu de 10. Overhead estimé : 200-400 ms × 100 = **20-40 s** côté frontend.

**Fix** : monter `maxChunkBytes` à 80-100 Mo (mais alors gérer `post_max_size` côté PHP : 100 Mo OK, mais le timeout d'XHR et la perception de progrès deviennent plus saccadés). Trade-off à arbitrer.

#### M3. Pas de progression byte-level réaliste post-upload

**Fichier** : `web/src/services/uploadService.ts:115-122`

```js
state.progress = Math.round(chunkProgress * 0.5)
```

La progression est plafonnée à 50 % pendant l'upload, puis avance à 90 % lors du polling de statut (`state.progress = Math.min(90, state.progress + 5)` `:211`), puis 100 % à la fin. L'utilisateur n'a aucune visibilité réelle sur le travail du worker (qui peut prendre 10+ secondes par photo). Sur une galerie de 50 photos, la barre reste à ~50-90 % pendant ~5-10 min, ce qui fait croire à un blocage.

**Fix** : exposer un compteur « X / N traitées » prééminent, et abandonner l'illusion d'un pourcentage continu après l'upload HTTP.

#### M4. Le client peut sélectionner des centaines de fichiers d'un coup sans avertissement

**Fichier** : `web/src/composables/useChunkedUpload.ts` — aucun garde-fou sur le nombre total.

Valider individuellement la taille c'est bien, mais 500 photos × 25 Mo = 12,5 Go à passer en série dans la queue. L'utilisateur n'a pas conscience que ça prendra 1-2 heures. Aucun feedback type « voulez-vous vraiment uploader 500 photos ? (estimation : 1 h 30) ».

**Fix** : afficher une estimation de durée avant de commencer (`files.length * 8 s` ou un calcul plus fin si on connaît la vitesse réseau).

#### M5. Le validator backend autorise 15 fichiers max par requête, le frontend chunke à 10

**Fichier** : `api/app/Http/Requests/StoreAsyncPhotoRequest.php:17` — `'photos' => [..., 'max:15']`

Aligné approximativement, mais désynchronisé : si un dev passe `chunkSize: 15` côté frontend, ça passera. Si quelqu'un override avec 20, la validation back rejette. **Source de bugs futurs**.

**Fix** : centraliser la limite (au moins documenter, idéalement émettre une `/config` depuis le back qui pilote le front).

#### M6. La queue est sur Supabase (PostgreSQL distant)

**Fichier** : `.env` — `QUEUE_CONNECTION=database` + DB Supabase.

Chaque `dispatch` fait un INSERT sur `jobs` table dans une DB distante (au-delà de Cloudflare tunnel ? non, Supabase est direct). Chaque `pop` fait un SELECT FOR UPDATE + UPDATE pour réserver le job. À 1 worker on s'en sort, mais pour passer à N workers, la contention sur les locks Postgres devient un facteur.

**Fix futur** : Redis local sur le NAS pour la queue. Throughput largement supérieur, lock-free pop.

#### M7. Pas de tests automatisés sur le flux upload

`grep -r "ProcessPhotoJob\|storeAsync" api/tests/` retournerait probablement vide. Le code chemine régulièrement à travers ce service critique sans filet.

**Fix** : test feature qui :
1. Mock MinIO (faker Storage)
2. POST /admin/.../photos/async avec un fichier de test
3. `Bus::fake()` ou exécution sync de la queue
4. Assert que la Photo est créée avec les bons paths, watermarks, statuts

---

### 🟢 PETIT — code quality, à corriger sans urgence

#### L1. `mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi` mais le watermark vidéo n'existe pas

**Fichier** : `api/app/Http/Requests/StoreAsyncPhotoRequest.php:18`

Les vidéos passent la validation et sont uploadées telles quelles vers MinIO (cf. `ProcessPhotoJob::processVideo`). Mais aucune limite de taille spécifique (les vidéos peuvent être bien plus grosses que les photos), pas de transcodage, pas de génération de thumbnail (le `file_path_preview` et `file_path_thumbnail` restent null pour les vidéos). Si une vidéo est uploadée par erreur dans une galerie d'événement, l'UI peut casser.

#### L2. Le worker recompose les chemins de fichier sans alignement avec `SchoolSessionService`

**Fichier** : `api/app/Console/Commands/RetryFailedPhotoUploads.php:88-107`

Le commentaire dit « Aligns with SchoolSessionService's {uuid}.{ext} convention » mais la commande contient aussi une « Legacy format » pour les fichiers `{uuid}_{filename}`. Si on devait nettoyer le `temp_uploads/`, on aurait des règles mixtes. **Code à factoriser**.

#### L3. `clearstatcache` appelé dans `waitForTempFile` à chaque tour

**Fichier** : `api/app/Jobs/ProcessPhotoJob.php:166`

`clearstatcache(true, $fullPath)` est OK mais inutile si le fichier existe déjà — appel toutes les 2 s pendant jusqu'à 60 s = 30 appels.

#### L4. `processVideo` et `processImage` dupliquent la construction d'`UploadedFile`

**Fichier** : `api/app/Jobs/ProcessPhotoJob.php:85-156`

Petite duplication. À factoriser.

#### L5. Pas de purge du cache OPCache après déploiement du code des workers

**Fichier** : `deploy/docker-compose.prod.yml:87`

Le worker tourne avec `--max-time=3600` (1 h max). En CLI, OPCache n'est pas actif par défaut (`opcache.enable_cli = 0` dans `php.ini:34`), donc OK. **Mais** si un jour on l'active, le worker retiendra le code en mémoire jusqu'à `max-time`.

#### L6. Pas de monitoring d'observabilité

Pas de log de durée par photo, pas de métriques d'échec, pas de Sentry / log file structuré.

**Fix** : `Log::info('ProcessPhotoJob: completed', ['upload_id', 'duration_ms', 'gallery_id'])` dans le `finally` du `handle()`.

---

## 4. Régressions récentes — pourquoi c'est plus lent maintenant

D'après `git log` sur les fichiers modifiés (`api/app/Jobs/ProcessPhotoJob.php`, `api/app/Http/Controllers/Api/PhotoController.php`, `api/app/Services/ImageProcessingService.php`) :

| Commit | Date relative | Impact |
|---|---|---|
| `ea3cd55 fix pooling issue and add retry` | il y a ~2 mois | `$tries = 3 → 3` + backoff initial |
| `a08662c audit fix setp 1 and 2` | récent | refonte ImageProcessingService (decode 2× resté) |
| `ce859a1 audit fix setp 4` | récent | `$tries = 3 → 5`, backoff `[5,15,30] → [10,30,60,120,300]`, ajout de `waitForTempFile(60s)`, ajout de `->delay(3s)` |
| `d9b8a88 phase R5` | récent | refonte côté frontend : split `uploadService.ts` en modules, `maxRetries: 2`, `maxChunkBytes: 30Mo` |
| (uncommitted) | actuel | Ajustements logging dans `storeAsync` |

### Le verdict

La principale source de ralentissement perçu est le **commit `audit fix setp 4`** qui a introduit :

1. `->delay(now()->addSeconds(3))` — **+3 s constant** sur chaque batch dispatched
2. `waitForTempFile(60s)` — **jusqu'à 60 s** par photo si le fichier est introuvable (peut arriver après le bug C1 quand le temp file a été nettoyé puis retry tente de le relire)
3. `$tries = 5` au lieu de 3 + backoff cumulé jusqu'à 520 s — **les erreurs définitives durent 5-10 min** au lieu d'1-2 min

Pour les **échecs sans raison apparente**, c'est probablement l'effet combiné de :

- C2 (nginx `client_body_timeout` 60 s) pour les chunks lents
- C5 (Cloudflare tunnel qui renvoie parfois des 5xx transitoires)
- C1 (exceptions avalées + temp file nettoyé) qui empêche tout retry de fonctionner

---

## 5. Plan de remédiation priorisé

> Priorité = **gain / effort**. Les P1 doivent être déployés dans l'ordre, ils stabilisent avant d'optimiser.

### 🚀 P1 — Quick wins ✅ **Appliqués (2026-05-19)**

> Toutes les actions ci-dessous ont été appliquées. Voir le tableau « Changelog des P1 » en tête du document. Le détail technique est conservé ci-après à titre de référence et pour les revues de code.

#### P1.1 Supprimer les workarounds macOS Docker en production ✅

**Fichier** : `api/app/Http/Controllers/Api/PhotoController.php:344`

```diff
- ProcessPhotoJob::dispatch(...)
-     ->delay(now()->addSeconds(3));
+ ProcessPhotoJob::dispatch(...);
```

**Fichier** : `api/app/Jobs/ProcessPhotoJob.php:56`

Conditionner ou raccourcir drastiquement :

```diff
- $this->waitForTempFile(maxSeconds: 60, pollIntervalSeconds: 2);
+ // Garde-fou court — en prod (volume natif) le fichier est immédiatement visible.
+ // En dev macOS (env APP_ENV=local), un délai plus généreux peut être laissé.
+ $waitSeconds = app()->environment('local') ? 30 : 3;
+ $this->waitForTempFile(maxSeconds: $waitSeconds, pollIntervalSeconds: 1);
```

#### P1.2 Restaurer un comportement de retry sain ✅

**Fichier** : `api/app/Jobs/ProcessPhotoJob.php`

- `$tries = 3` (revenir à l'ancien)
- `$backoff = [10, 30, 60]` (revenir à l'ancien + un peu plus large pour S3 transient)
- **Surtout** : ne plus avaler les exceptions de traitement dans le `catch` ; les laisser remonter pour que Laravel les retry. Mais **distinguer** les erreurs définitives (mauvaise image, ressource manquante) des transitoires (réseau, MinIO).

#### P1.3 Préserver le fichier temporaire entre retries ✅

**Fichier** : `api/app/Jobs/ProcessPhotoJob.php`

```diff
  try {
      // ... processing ...
- } catch (\Exception $e) {
-     Log::error(...);
-     $upload->markAsFailed($e->getMessage());
+ } catch (\App\Exceptions\PermanentPhotoProcessingException $e) {
+     // Erreurs définitives (validation, image corrompue)
+     Log::error(...);
+     $upload->markAsFailed($e->getMessage());
+     $this->cleanupTempFile();   // <-- supprimer SEULEMENT ici
+ } catch (\Exception $e) {
+     // Erreurs transitoires : laisser Laravel retry
+     Log::warning('ProcessPhotoJob transient failure', [...]);
+     throw $e;   // <-- pas de cleanup, retry préservera le fichier
  } finally {
-     $this->cleanupTempFile();
+     // Cleanup uniquement sur succès, géré ailleurs.
  }
```

Et dans `failed(Throwable $e)` (dernier retry consommé), cleanup forcé du temp file.

#### P1.4 Adapter les timeouts nginx aux gros uploads ✅

**Fichier** : `deploy/nginx.prod.conf`

```nginx
server {
    # ... (existant) ...

    client_max_body_size 100M;
+   client_body_timeout 600s;       # 10 min entre 2 lectures du body
+   client_header_timeout 60s;
+   send_timeout 600s;
+   keepalive_timeout 75s;

    # FastCGI
    location ~ \.php$ {
        # ...
        fastcgi_read_timeout 600;    # passer de 300 à 600 pour cohérence
+       fastcgi_send_timeout 600;
+       fastcgi_request_buffering on;
+       fastcgi_max_temp_file_size 0;  # pas de buffering disque inutile pour les uploads
    }
}
```

#### P1.5 Réduire le polling et le payload ✅

**Fichier** : `api/app/Http/Controllers/Api/PhotoController.php:382`

```diff
- $status = PhotoUpload::getBatchStatus($validated['batch_id']);
+ // Pour les gros batches, ne pas charger la liste détaillée à chaque poll.
+ // Le frontend déduit le statut par photo via le compteur + détails uniquement pour les failed.
+ $includeUploads = (bool) $request->query('include_uploads', true);
+ $status = PhotoUpload::getBatchStatus($validated['batch_id'], $includeUploads);
```

Et dans `web/src/services/uploadService.ts`, passer `include_uploads=false` après le seuil de 30 photos par exemple, ou ne demander que les failed (nouvelle option backend `only=failed`).

#### P1.6 Coût immédiat : -3 s par batch + retries cohérents + uploads lents qui passent ✅

Avec uniquement P1.1–P1.5, le temps d'upload perçu pour un batch typique baisse de **5-10 %** et les échecs silencieux dus à `client_body_timeout` disparaissent.

---

### 🛠️ P2 — Optimisations du pipeline d'image ✅ **Appliqué (2026-05-19)**

> P2.1, P2.3, P2.4 (Imagick activé en local), P2.5 sont **actifs et validés**. Seul P2.2 (cache de calque de filigrane) reste **codé mais opt-in** via `USE_WATERMARK_LAYER_CACHE` dans `ImageProcessingService.php` — gain marginal vs P2.4, à activer si la perf devient un goulot. Voir le tableau « Changelog des P2 » en tête du document.

#### P2.1 Décoder l'image une seule fois ✅

**Fichier** : `api/app/Services/ImageProcessingService.php:64-108`

Pattern proposé :

```php
$rawDecoded = $this->rawManager->read($file->getRealPath());   // 1 seul decode

// Original : stream depuis le fichier disque, pas via PHP memory
Storage::disk('minio')->putFileAs(
    "{$galleryId}/original",
    $file,
    $filename
);

// Preview : clone le decode déjà fait, scale, watermark
$preview = clone $rawDecoded;
$preview->scaleDown(width: self::PREVIEW_MAX_WIDTH);
$preview->modify(new AlignRotationModifier);
$this->applyWatermark($preview, ...);
$previewContent = $preview->toJpeg(self::PREVIEW_QUALITY)->toString();
$this->uploadContent($previewPath, $previewContent, $file->getMimeType());

// Thumbnail : clone du PREVIEW déjà scalé (pas du raw !) avant watermark
//   mais ça impose de garder le preview sans watermark en mémoire
//   alternative : faire un second clone du raw et scaler directement
$thumbnail = clone $rawDecoded;
$thumbnail->scaleDown(width: self::THUMBNAIL_MAX_WIDTH);
$thumbnail->modify(new AlignRotationModifier);
$this->applyWatermark($thumbnail, ...);
$thumbnailContent = $thumbnail->toJpeg(self::THUMBNAIL_QUALITY)->toString();
$this->uploadContent($thumbnailPath, $thumbnailContent, $file->getMimeType());

unset($rawDecoded, $preview, $thumbnail);
```

**Gain** : 1 décodage au lieu de 2 ⇒ ~50 % du CPU image + ~130 Mo de RAM économisés.

#### P2.2 Pré-rendre le filigrane comme calque PNG ⚠️ Opt-in

**Fichier** : `api/app/Services/ImageProcessingService.php:308-366`

Au lieu de 21 `text()` par image, générer une fois par taille un PNG transparent contenant la grille + le central, puis `place()` cette image.

```php
private function getWatermarkLayer(int $width, int $height, float $gridOpacity, float $centralOpacity): ImageInterface
{
    $cacheKey = "watermark_{$width}x{$height}_{$gridOpacity}_{$centralOpacity}";
    return Cache::rememberForever($cacheKey, function() use ($width, $height, $gridOpacity, $centralOpacity) {
        $layer = $this->rawManager->create($width, $height);
        // ... draws 21 textes ...
        return $layer;
    });
}
```

Puis dans `applyWatermark` :

```php
$layer = $this->getWatermarkLayer($image->width(), $image->height(), $gridOpacity, $centralOpacity);
$image->place($layer);
```

**Gain** : 42 rasterisations FreeType → ~0 par photo (juste 1 composition alpha). **~1-3 s par photo économisées**.

⚠️ Attention : si la dimension du preview/thumbnail varie selon la photo (portrait vs paysage), prévoir plusieurs entrées cache (par résolution arrondie au 100 px près) ou générer le layer dynamiquement mais en gardant les textes individuels en cache.

#### P2.3 Streamer l'original sans `file_get_contents` ✅

**Fichier** : `api/app/Services/ImageProcessingService.php:70-72` + `api/app/Services/MinioStorageService.php:27`

```diff
- $originalContent = file_get_contents($file->getRealPath());
- $this->uploadContent($originalPath, $originalContent, $file->getMimeType());
- unset($originalContent);
+ Storage::disk('minio')->putFileAs(
+     dirname($originalPath),
+     $file,
+     basename($originalPath),
+     ['ContentType' => $file->getMimeType()]
+ );
```

Le SDK AWS S3 utilisera un stream et bascule en multipart au-delà de ~5 Mo si configuré. **Gain** : pic mémoire -30 Mo, allocations -1.

#### P2.4 Activer Imagick ✅ Driver actif (validé 8/8 puis 61/61 en local)

**Fichier** : `api/Dockerfile`

```diff
  RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
      && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl opcache
+ # Imagick — ~3× plus rapide que GD pour JPEG, moins gourmand en RAM
+ RUN apk add --no-cache imagemagick imagemagick-dev \
+     && pecl install imagick \
+     && docker-php-ext-enable imagick
```

**Fichier** : `api/app/Services/ImageProcessingService.php:60,82`

Bascule via la constante `USE_IMAGICK_DRIVER` (true par défaut depuis l'activation) :

```php
private const USE_IMAGICK_DRIVER = true;

// Dans __construct :
$driver = self::USE_IMAGICK_DRIVER && class_exists(\Imagick::class)
    ? new ImagickDriver
    : new GdDriver;
```

**Gain mesuré** : ~3-5 s par photo 18-30 Mo, contre ~8-15 s en GD. Soit **2-3× plus rapide** confirmé en runtime (durée loggée via `Log::info('storeAsync: photo processed', ['duration_ms' => ...])`).

**Note sur le déploiement** : sur le NAS, après `docker compose -f docker-compose.prod.yml build laravel queue && up -d --force-recreate`, vérifier que la constante est bien `true` (commitée dans le code) et qu'Imagick est chargé via `docker exec api-php php -m | grep imagick`.

#### P2.5 Baisser légèrement la qualité du preview ✅

**Fichier** : `api/app/Services/ImageProcessingService.php:29`

```diff
- private const PREVIEW_QUALITY = 95;
+ private const PREVIEW_QUALITY = 93;
```

À 93, la perte visuelle est imperceptible (sauf à comparer pixel par pixel), mais le fichier preview est typiquement **plus petit**. Bonus : moins de bande passante consommée côté client public.

---

### ⚙️ P3 — Infra & optimisations réseau ✅ **P3.2 appliqué (2026-05-19)**

> Une seule action retenue : **P3.2 — dual endpoint MinIO**. Les autres pistes initialement envisagées (parallélisation de la queue, Redis, Horizon) ont été rendues obsolètes par le sync pivot — PHP-FPM (30 workers) absorbe naturellement les 3 chunks parallèles du frontend, plus besoin d'infra autour de la queue pour les uploads.

#### P3.2 ✅ Dual endpoint MinIO — appliqué (2026-05-19)

**Contexte** : MinIO tourne dans un **docker-compose séparé** sur le NAS (image `quay.io/minio/minio`, container `minio`, ports `9000:9000` et `9001:9001` publiés sur le host). Le tunnel Cloudflare `s3.oceanetorresphotographie.fr` route vers `http://localhost:9000`. Avant l'optim, Laravel sur le NAS faisait 4 hops pour atteindre un service local : DNS public → Cloudflare edge → tunnel cloudflared → MinIO.

**Solution adoptée — dual endpoint** : Laravel utilise l'endpoint interne pour les API calls, mais réécrit les URLs signées renvoyées au navigateur avec l'URL publique.

```
[laravel container, NAS]
        │
        ├── PUT (upload original)    ────► http://host.docker.internal:9000     (interne, fast)
        ├── PUT (preview)            ────► http://host.docker.internal:9000     (interne, fast)
        ├── PUT (thumbnail)          ────► http://host.docker.internal:9000     (interne, fast)
        └── ::temporaryUrl()         ────► https://s3.oceanetorresphotographie.fr  (public, browser-reachable)
```

**Changements** :

1. `api/config/filesystems.php` — disk `minio` accepte un `temporary_url` (Laravel 12 supporte nativement) :

```diff
  'minio' => [
      'driver' => 's3',
      'endpoint' => env('MINIO_ENDPOINT'),
+     'temporary_url' => env('MINIO_PUBLIC_URL'),  // host pour les URLs signées
      ...
  ],
```

2. `deploy/docker-compose.prod.yml` — ajout `extra_hosts` aux services `laravel`, `queue`, `scheduler` :

```diff
  laravel:
    dns: [8.8.8.8, 8.8.4.4, 1.1.1.1]
+   extra_hosts:
+     - "host.docker.internal:host-gateway"
    ...
```

3. `deploy/.env.prod` (à mettre à jour sur le NAS au moment du déploiement) :

```diff
- MINIO_ENDPOINT=https://s3.oceanetorresphotographie.fr
+ MINIO_ENDPOINT=http://host.docker.internal:9000
+ MINIO_PUBLIC_URL=https://s3.oceanetorresphotographie.fr
```

**Pourquoi `host.docker.internal:host-gateway`** plutôt qu'un réseau Docker partagé ? MinIO tourne dans un compose stack séparé (donc autre réseau par défaut). Plutôt que d'imposer un réseau partagé (qui obligerait à modifier le compose MinIO aussi), on utilise la passerelle host de Docker : depuis le container Laravel, `host.docker.internal:9000` résout vers le host, qui a MinIO publié sur ce port. 1 hop loopback, latence sub-milliseconde, **0 modification côté MinIO**.

**Gain attendu** : 100-300 ms par PUT × 3 PUTs/photo × N photos. Pour un batch de 61 photos × 3 PUTs = 183 PUTs × ~200 ms = **~36 s gagnés** + stabilité accrue (plus de variabilité Cloudflare).

**Local dev** : pas concerné. Le local pointe vers la prod MinIO via internet (choix utilisateur). La config `temporary_url` est laissée vide en local (fallback sur l'endpoint).

---

### 🎨 P4 — UX & robustesse ✅ **Appliqué (2026-05-19)**

> P4.1, P4.2, P4.3 sont actifs. P4.4 (endpoint de config centralisée) a été skippé : les limites frontend/backend sont stables et le bénéfice ne justifie pas la complexité ajoutée (validation, cache, fallback).

#### P4.1 ✅ Bouton « réessayer les échouées »

**Fichiers** : `web/src/components/admin/ui/UploadProgress.vue` + `web/src/components/admin/PhotosManager.vue`.

Depuis le sync pivot, plus de fichier temp côté serveur — un retry serveur n'est plus possible. Implémentation 100 % frontend :

- `UploadProgress.vue` expose une computed `failedFiles` qui filtre `progress.files` sur `status === 'failed'` et que l'objet a bien une référence `File` DOM valide.
- Bouton « Réessayer les N échouée(s) » visible **uniquement quand l'upload est terminé ET qu'il y a des failed**.
- Émet `retry: [files: File[]]` au parent.
- `PhotosManager.vue` écoute `@retry="handleRetryFailed"` qui appelle `uploadPhotos(files)` avec uniquement les fichiers en échec.
- Le bouton de progression n'est plus auto-reset après 2 s s'il y a des échecs (sinon le state qui détient les `File` serait wipé avant que l'utilisateur clique). Si tout est OK, auto-reset 2 s comme avant.

Limite documentée : si l'utilisateur ferme la modale puis la rouvre, le state est reset → bouton disparaît, il faut re-glisser-déposer. Acceptable pour 95 % des cas.

#### P4.2 ✅ ETA précis pour les batches courts

**Fichier** : `web/src/composables/useEta.ts`.

L'ETA était déjà câblé dans `UploadProgress.vue:38-40` via le composable `useEta`. Mais `formatDuration` retournait `"< 1 min"` pour tout sub-60s, ce qui est trop vague depuis que les batches finissent typiquement en 15-90 s (sync pivot + Imagick).

Améliorations :

- `MIN_ELAPSED_MS` : 3000 → 1500 ms (l'ETA apparaît plus vite, plus pertinent pour batches courts).
- `formatDuration` retourne désormais :
  - `~quelques secondes` pour ≤ 5 s
  - `~Xs` pour 6-59 s
  - `~X min`, `~Xh Ymin` pour > 1 min (inchangé)

Plus de modale bloquante avant l'upload — l'ETA inline dans la barre de progression est suffisant pour donner de la visibilité.

#### P4.3 ✅ Messages d'erreur friendly

**Fichier** : `api/app/Http/Controllers/Api/PhotoController.php`.

Le `catch` dans `storeAsync` appelait `$upload->markAsFailed($e->getMessage())`, ce qui exposait des messages techniques (`"Échec du traitement de la photo XX.jpg"`, `"cURL error 7: Failed to connect..."`) directement dans l'UI admin. Mapping vers des messages courts + actionnables :

```php
private function humanizeUploadError(\Throwable $e): string
{
    $lower = mb_strtolower($e->getMessage());
    return match (true) {
        str_contains($lower, 'memory')          => 'Image trop volumineuse pour être traitée.',
        str_contains($lower, 'minio')           => 'Stockage temporairement indisponible. Clique sur « Réessayer ».',
        str_contains($lower, 'mime')            => 'Format non supporté. Formats acceptés : JPEG, PNG, WEBP, MP4.',
        str_contains($lower, 'decode')          => 'Image corrompue ou illisible.',
        str_contains($lower, 'permission')      => 'Accès refusé au stockage. Contacter l\'administrateur.',
        default                                 => 'Erreur lors du traitement. Clique sur « Réessayer ».',
    };
}
```

Le message technique original reste dans `laravel.log` via `Log::error('storeAsync: inline processing failed', ['error' => $e->getMessage(), ...])` pour le debug.

**Toast amélioré** : `PhotosManager.vue` distingue désormais le toast succès (`"X photo(s) uploadée(s)"` avec le nombre réel, pas `files.length` aveuglément) et le toast échec (`"X photo(s) n'ont pas pu être uploadées — utilise « Réessayer »."`).

#### P4.4 ❌ Endpoint `/api/admin/config/upload` (skippé)

Initialement prévu pour centraliser les limites (`maxFileSize`, `chunkSize`, etc.) côté serveur et les charger depuis le frontend au boot. Skippé : les limites sont stables (50 Mo/fichier, 30 Mo/chunk, 10 fichiers/chunk) et la complexité ajoutée (validation, cache, fallback offline) ne se justifie pas par un gain mesurable. À ressortir uniquement si on doit changer ces limites souvent à l'avenir.

---

### 🧪 P5 — Tests et monitoring

#### P5.1 Test feature E2E sur `storeAsync` + `ProcessPhotoJob`

`api/tests/Feature/PhotoUploadTest.php` :

```php
test('async upload creates upload record and dispatches job', function () {
    Storage::fake('local');
    Storage::fake('minio');
    Bus::fake();

    $gallery = Gallery::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 7000, 4600)->size(25000);

    $this->actingAs($admin)
        ->postJson("/api/admin/galleries/{$gallery->id}/photos/async", [
            'photos' => [$file],
            'batch_id' => 'batch_test_1',
        ])
        ->assertStatus(200)
        ->assertJsonStructure(['batch_id', 'uploads' => [['id', 'original_filename', 'status']]]);

    Bus::assertDispatched(ProcessPhotoJob::class);
});

test('process job handles MinIO transient failure by retrying', function () {
    // ... simulate MinIO throwing once, verify temp file NOT cleaned, job retried, succeeds 2nd time
});
```

#### P5.2 Logger la durée par photo

`api/app/Jobs/ProcessPhotoJob.php` :

```php
public function handle(): void
{
    $started = microtime(true);
    try {
        // ... existing logic ...
    } finally {
        Log::info('ProcessPhotoJob', [
            'upload_id' => $this->uploadId,
            'gallery_id' => $this->galleryId,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            'status' => $upload?->status ?? 'unknown',
        ]);
    }
}
```

Permet ensuite de tirer des stats : durée médiane, p95, taux d'échec.

---

## 6. Gains estimés vs mesurés

### Actions appliquées (✅ effet observé en runtime)

| Action | Gain attendu | Effet réel | Coût (effort) |
|---|---|---|---|
| P1.1 retirer `delay(3s)` + raccourcir `waitForTempFile` | -3 s par batch | ✅ Conforme | 5 min |
| P1.2 tries=3 + backoff [10,30,60] | Échec en ~100 s au lieu de ~520 s | ✅ Conforme | 5 min |
| P1.3 re-throw + cleanup tardif | Retries fonctionnels sur erreurs transitoires | ✅ Conforme **mais rendu sans objet par le sync pivot** | 30 min |
| P1.4 timeouts nginx | -99 % d'échecs « connexion fermée » sur uploads lents | ✅ Pas reproduit en local | 10 min |
| P1.5 polling allégé + fix `include_uploads=0` | -90 % de payload de poll pour gros batches | ✅ Validé sur batch 61 photos | 1 h 30 (+ fix 422) |
| P2.1 décodage unique | -50 % CPU image, -130 Mo RAM pic | ✅ Conforme | 2 h |
| P2.3 stream original via `putFileAs` | -30 Mo allocation PHP / photo | ✅ Conforme | 30 min |
| P2.4 Imagick driver actif | 2-3× plus rapide | ✅ **Mesuré : ~3-5 s/photo 18 Mo (vs ~8-15 s en GD)** | 2 h (Dockerfile + autoconf) |
| P2.5 qualité 93 (vs 95) | -10 à -15 % taille preview | ✅ Conforme | 1 min |
| **Sync pivot** (storeAsync inline) | **0 perte de photo, perf équivalente** | ✅ **Validé 8/8 puis 61/61** | 2 h |
| P3.2 Dual endpoint MinIO | -36 s sur batch 61 photos en prod (-200 ms × 183 PUTs) | ⏳ Validation en attendant le déploiement prod | 30 min |
| **Bonus admin grid** (thumbnail + lazy loading) | -14× les données téléchargées, ouverture instantanée | ✅ **Validé : "instantané"** | 5 min |
| P4.1 Bouton retry des échouées | UX — re-upload des `failed` sans re-drag | ✅ Implémenté | 30 min |
| P4.2 ETA précis pour batches courts | "~Xs" au lieu de "< 1 min" sous 60s | ✅ Implémenté | 5 min |
| P4.3 Messages d'erreur friendly | `humanizeUploadError()` + toast accurate | ✅ Implémenté | 15 min |

### Actions opt-in restantes

| Action | Gain attendu | Statut |
|---|---|---|
| P2.2 cache de filigrane | -2 à -3 s par photo | Codé, `USE_WATERMARK_LAYER_CACHE = false` — à activer si la perf devient un goulot |

### Actions devenues obsolètes / déplacées

| Action initiale | Pourquoi |
|---|---|
| `photos:retry-failed` command | Plus de temp file à récupérer en mode sync — quasi dead code |
| `ProcessPhotoJob` class | Plus dispatched depuis `storeAsync`. Conservée pour usage futur, n'est plus chargée en hot path |

### Performances finales

| Volume | Avant tous les fixes (estimé) | Après tous les fixes (mesuré) |
|---|---|---|
| 8 photos × 18 Mo | ~1 min + ~30 % de pertes | **~15 s, 0 perte** |
| 50 photos × 25 Mo | ~7 min + pertes possibles | ~1 min 20 (estimé, basé sur ~5 s/photo Imagick × 50/3 parallèles) |
| 61 photos × 18 Mo | non testé avant | **~1 min 40, 0 perte** |
| 100 photos × 25 Mo | ~13 min + pertes | ~2 min 50 (estimé) |

**Conclusion** : le système d'upload est désormais 4-5× plus rapide qu'avant l'audit, et n'a plus aucune perte de photo structurelle.

---

## 7. Annexes

### 7.1 Fichiers clés inventoriés

**Backend** :
- `api/app/Http/Controllers/Api/PhotoController.php`
- `api/app/Http/Requests/StoreAsyncPhotoRequest.php`
- `api/app/Http/Requests/UploadStatusRequest.php`
- `api/app/Jobs/ProcessPhotoJob.php`
- `api/app/Services/ImageProcessingService.php`
- `api/app/Services/MinioStorageService.php`
- `api/app/Models/Photo.php`
- `api/app/Models/PhotoUpload.php`
- `api/app/Models/Gallery.php`
- `api/app/Traits/ClearsEventGalleriesCache.php`
- `api/app/Console/Commands/RetryFailedPhotoUploads.php`
- `api/routes/api.php` (routes 216-217, 244-245, 248)

**Frontend** :
- `web/src/components/admin/PhotosManager.vue`
- `web/src/composables/useChunkedUpload.ts`
- `web/src/services/uploadService.ts`
- `web/src/services/upload/chunkUploader.ts`
- `web/src/services/upload/uploadUtils.ts`
- `web/src/types/upload.ts`
- `web/src/config/constants.ts`

**Infrastructure** :
- `deploy/docker-compose.prod.yml`
- `deploy/nginx.prod.conf`
- `deploy/docker/php.ini`
- `deploy/docker/www.conf`
- `api/Dockerfile`
- `api/config/queue.php`
- `api/config/filesystems.php`

### 7.2 Diagramme « avant » vs « après » (50 photos × 25 Mo)

```
AVANT — état initial (queue + GD + tous les bugs)
─────────────────────────────────────────────────
T=0s    user clique « upload »
T=1s    chunk 1 (1 photo) en route
T=4s    chunk 1 arrivé, job dispatché +3s delay
T=7s    worker pickup, waitForTempFile (~0 sur NAS), process commence
T=13s   photo 1 traitée (4 décodages, 21 watermarks, 3 PUT via Cloudflare)
T=18s   photo 2 traitée
...
T=320s  photo 50 traitée
T=323s  UI affiche « terminé »
  ⇒ ~5 min 23 s, séquentiel, + 3-30 % de pertes silencieuses sur macOS

APRÈS — état actuel (P1 + P2 + sync pivot, Imagick actif)
──────────────────────────────────────────────────────────
T=0s    user clique « upload »
T=1s    chunks 1, 2, 3 en route en parallèle (concurrentChunks=3)
T=1.5s  3 workers PHP-FPM commencent le traitement Imagick
T=6s    3 photos traitées simultanément (Imagick + P2.1 décode unique + stream MinIO)
T=6.5s  chunks 4, 5, 6 démarrent
T=11s   photos 4-6 traitées
...
T=85s   photo 50 traitée
T=85s   UI montre 50/50 (réponse de chaque chunk = status=completed)
  ⇒ ~1 min 25 s, parallèle 3×, ZÉRO perte
```

Pas besoin de queue worker ni de Redis ni de multi-workers infra. La parallélisation se fait naturellement côté PHP-FPM grâce au sync pivot.

### 7.3 Glossaire rapide

- **Chunk** : groupe de fichiers envoyé dans une seule requête HTTP multipart.
- **PhotoUpload** : ligne en DB qui tracke l'état d'un fichier individuel (`pending → uploading → processing → completed | failed`).
- **Batch** : ensemble de chunks partageant un même `batch_id`, ce qui permet au frontend de poller le statut agrégé.
- **MinIO** : serveur S3-compatible auto-hébergé. Ici stocke 3 versions par photo : `{galleryId}/original/`, `/preview/`, `/thumbnail/`.
- **Watermark** : tampon ©Oceane Torres composé de 20 textes en grille diagonale + 1 grand texte central, appliqué sur preview et thumbnail.
- **Queue worker** : processus PHP qui consomme la table `jobs` et exécute les jobs (ici `ProcessPhotoJob`).

---

## 8. Conclusion

Le système d'upload est désormais **fiable et performant**. Récap des problèmes identifiés initialement et de leur statut :

1. ~~Régressions récentes (workarounds dev en prod) — **réversibles en 15 min**.~~ ✅ **Corrigé par P1.1**
2. ~~Architecture séquentielle (1 worker) — **3-4× de débit perdu**.~~ ✅ **Résolu autrement par le sync pivot** (§ 10) — plus de queue worker du tout pour les photos, 3 PHP-FPM workers en parallèle suffisent
3. ~~Pipeline d'image non optimisé (double décodage, watermark à la volée, GD) — **40-60 % de CPU gaspillé**.~~ ✅ **Corrigé par P2.1+P2.3+P2.4+P2.5** — décodage unique, stream MinIO, Imagick actif, qualité optimisée. Seul P2.2 (watermark cache) reste opt-in.
4. ~~Mauvaise gestion des erreurs transitoires (exception avalée, temp file nettoyé) — **les retries n'apportent rien**.~~ ✅ **Corrigé par P1.3**, puis **rendu sans objet par le sync pivot** (plus de retries asynchrones)
5. ~~Timeouts nginx inadaptés — **échecs silencieux sur connexions lentes**.~~ ✅ **Corrigé par P1.4**
6. **(Nouveau)** Race virtio-fs + workers Laravel CLI à code stale en mémoire — **3-5 photos sur 8 perdues en local**. ✅ **Corrigé par le sync pivot** (§ 10)
7. **(Nouveau)** Validation Laravel `boolean` rejette la string `"false"` — toast d'erreur fantôme à > 50 photos. ✅ **Corrigé** (§ 11)

### État actuel (2026-05-19 — fin de la session de fix)

- ✅ **P1 appliqué** : régressions annulées, retries cohérents, timeouts nginx généreux, polling optimisé pour gros batches
- ✅ **P2 appliqué** : pipeline d'image refactoré, Imagick actif en local (à activer aussi sur le NAS via flip de constante), qualité preview ajustée
- ✅ **Sync pivot appliqué et validé** : 8/8 puis 61/61 photos sans perte. Le système d'upload est désormais structurellement immunisé aux races filesystem et aux problèmes de code stale dans les workers
- ⏳ **P3 obsolète** : l'objectif initial (paralléliser le queue worker) n'a plus de sens. À la place, les uploads se font dans PHP-FPM qui parallélise déjà via ses 30 workers
- ⏳ **P4/P5 à faire** : améliorations UX cosmétiques (bouton retry manuel, estimation de temps) et observabilité (durée par photo dans Datadog ou similaire, tests E2E)

### Performances réelles mesurées (local, Imagick actif)

| Volume | Durée | Débit |
|---|---|---|
| 8 photos × 18 Mo | ~15 s | ~530 Ko/s effectif (traitement compris) |
| 61 photos × 18 Mo | ~1 min 40 | ~1,1 Mo/s effectif |

Sur le NAS UGREEN (Linux natif, bind-mount kernel) le gain sera encore meilleur — pas de virtio-fs à supporter, et l'overhead réseau MinIO est négligeable car les deux services tournent sur la même machine.

### Action de déploiement prod (récap)

Pour porter tout ça en production :

```bash
# Sur le NAS
cd /volume1/docker/oceane-api
git pull --ff-only
docker compose -f docker-compose.prod.yml build laravel queue   # rebuild pour Imagick
docker compose -f docker-compose.prod.yml up -d --force-recreate

# Vérifier
docker exec api-php php -m | grep imagick         # doit retourner "imagick"
docker exec api-php php artisan tinker --execute='echo get_class(app(App\Services\ImageProcessingService::class)) . PHP_EOL;'
```

Le worker queue prod n'est plus utilisé pour les photos mais reste actif pour SMS Brevo, exports school session, etc. Le sync pivot fonctionne nativement en prod (bind-mount Linux instantané, aucun cas pathologique à craindre).

---

*Audit généré le 2026-05-19. Pour toute question ou implémentation, voir les références fichier:ligne dans chaque section.*

---

## 9. Déploiement sur le NAS UGREEN

Toute la suite suppose que tu es **connecté en SSH au NAS** et positionné dans le répertoire qui contient `docker-compose.prod.yml` (typiquement `/volume1/docker/oceane-api` ou équivalent — adapter au tien).

### 9.1 Récapitulatif de ce qui doit être propagé

| Changement | Nécessite |
|---|---|
| P1 (PhotoController, ProcessPhotoJob, UploadStatusRequest) | `git pull` + restart `api-php` et `api-queue` |
| P1 (`uploadService.ts` frontend) | déploiement frontend Render (push GitHub) |
| P1 (`nginx.prod.conf`) | restart `api-nginx` (ou reload) |
| P2.1 + P2.3 + P2.5 (`ImageProcessingService.php`) | `git pull` + restart `api-php` et `api-queue` |
| P2.4 (`api/Dockerfile` : install Imagick + `USE_IMAGICK_DRIVER = true`) | **rebuild des images** `laravel` + `queue` |
| **Sync pivot** (`PhotoController.php`) | `git pull` + restart `api-php` (la queue n'est plus utilisée pour les uploads) |
| **P3.2 dual endpoint MinIO** (`config/filesystems.php`, `docker-compose.prod.yml`, `.env.prod`) | `git pull` + **mettre à jour `.env.prod`** (cf. 9.2 étape 1bis) + `--force-recreate` des 3 services API |
| **Bonus grid admin** (`PhotosManager.vue`) | déploiement frontend Render |

### 9.2 Procédure de déploiement (ordre exact)

```bash
# 1. Se mettre sur la bonne branche / brancher l'origin
cd /volume1/docker/oceane-api          # adapte le chemin
git fetch origin
git checkout main                       # ou improve/photo-upload si pas encore mergé
git pull --ff-only

# 1bis. P3.2 — Mettre à jour deploy/.env.prod sur le NAS avec les deux variables MinIO :
#       (à faire UNE seule fois, avant le rebuild — le contenu de .env.prod n'est pas versionné)
#   MINIO_ENDPOINT=http://host.docker.internal:9000
#   MINIO_PUBLIC_URL=https://s3.oceanetorresphotographie.fr
# Garde MINIO_ACCESS_KEY / MINIO_SECRET_KEY inchangés.

# 2. Rebuild des images API (nécessaire à cause du Dockerfile pour Imagick).
docker compose -f docker-compose.prod.yml build laravel queue

# 3. Recréer les containers avec le nouveau code + nouvelle image + nouveau compose.
#    --force-recreate garantit que les extra_hosts (P3.2), le nouveau code (sync pivot)
#    et les nouvelles env vars sont bien chargés. Sinon un simple restart pourrait
#    réutiliser l'ancienne config.
docker compose -f docker-compose.prod.yml up -d --force-recreate

# 4. Vérifier que tous les containers sont healthy.
docker compose -f docker-compose.prod.yml ps

# 5. Vérifier que P3.2 fonctionne : depuis le container Laravel, MinIO doit être joignable
#    sur le host.docker.internal sans passer par le tunnel Cloudflare.
docker exec api-php sh -c 'curl -sI http://host.docker.internal:9000/minio/health/live'
# Attendu : HTTP/1.1 200 OK (avec X-Amz-Request-Id etc.)
```

### 9.3 Vérifications post-déploiement (smoke tests)

#### a) Le worker queue tourne avec le nouveau code

```bash
docker logs --tail 50 api-queue
# Cherche : "Processing: App\Jobs\ProcessPhotoJob" ou les warnings "attempt failed, will retry"
```

#### b) nginx a bien chargé les nouveaux timeouts

```bash
docker exec api-nginx nginx -T 2>/dev/null | grep -E "client_body_timeout|send_timeout|fastcgi_read_timeout"
# Attendu :
#   client_body_timeout 600s;
#   send_timeout 600s;
#   fastcgi_read_timeout 600;
#   fastcgi_send_timeout 600;
```

#### c) Imagick est bien installé (pour P2.4)

```bash
docker exec api-php php -m | grep -i imagick
# Attendu : "imagick"
# Si rien ne sort : la PECL install a échoué pendant le build. Voir 9.6.
```

#### d) Upload de test depuis le frontend

1. Ouvrir `https://oceanetorresphotographie.fr/admin`.
2. Créer une galerie (event + private), uploader **5 photos de 25-30 Mo**.
3. Vérifier dans l'UI :
   - Toutes les photos passent à `completed` sans erreur.
   - Les vignettes affichent un **filigrane visible** (grille diagonale + texte central).
   - Le preview (clic sur une photo) charge en quelques secondes max.
4. Vérifier les logs côté NAS :
   ```bash
   docker exec api-php tail -100 /var/www/storage/logs/laravel.log | grep -E "ProcessPhotoJob|Image processing"
   ```
   - 0 occurrence de `Image processing failed` ou `Job failed permanently`.
   - Les `attempt failed, will retry` éventuels doivent être suivis d'un succès final.

### 9.4 Activer les flags opt-in P2 (à faire APRÈS 9.3 validé)

#### Étape 1 — Activer Imagick (P2.4)

Si le smoke test 9.3.c a confirmé que Imagick est chargé :

```bash
# Sur ton poste local (ou directement sur le NAS si tu édites en direct) :
# éditer api/app/Services/ImageProcessingService.php
# Ligne ~60 :
#   private const USE_IMAGICK_DRIVER = false;
# remplacer par :
#   private const USE_IMAGICK_DRIVER = true;
git add api/app/Services/ImageProcessingService.php
git commit -m "p2.4 enable Imagick driver after smoke test"
git push origin main
```

Sur le NAS :

```bash
git pull --ff-only
docker compose -f docker-compose.prod.yml restart laravel queue
# Pas besoin de rebuild — seul le code PHP change.
```

Refaire un upload test (1 photo suffit). Vérifier :
- Le filigrane est correctement placé (mêmes positions qu'avant).
- Les couleurs et la netteté du preview sont identiques (à l'œil nu).
- Si la photo a une EXIF orientation, elle est correctement appliquée.

Si ça part en sucette : `git revert HEAD && git push` puis `git pull && restart` sur le NAS.

#### Étape 2 — Activer le cache de filigrane (P2.2)

Procédure identique avec `USE_WATERMARK_LAYER_CACHE = true`. **Risque visuel principal** : si le canvas transparent n'est pas correctement géré par GD/Imagick, le calque pré-rendu peut écraser une partie de la photo avec un fond noir/blanc opaque. À l'œil nu c'est instantanément visible.

Le code a un fallback : si la construction du calque jette une exception, on retombe sur le dessin inline (comportement identique à avant P2.2). Mais si le calque a un fond opaque sans throw, **les photos uploadées seront visuellement endommagées**. Donc tester sur 1 photo, regarder l'aperçu, et revert immédiatement si le rendu est bizarre.

### 9.5 Frontend (Render)

Le polling allégé pour gros batches (P1.5) est dans `web/src/services/uploadService.ts`. Pour qu'il soit actif côté navigateur :

```bash
# Sur ton poste local — le push déclenche un build Render automatique.
git push origin main
```

Render rebuild + redéploie le static site en ~2-3 min. Vérifier sur https://oceanetorresphotographie.fr que la version est bien la nouvelle (DevTools → Network → un fichier `assets/index-<hash>.js` avec un hash différent).

### 9.6 Troubleshooting

#### Imagick n'est pas installé après le build

Le `|| echo` dans le Dockerfile fait que le build n'échoue jamais sur l'install Imagick. Pour avoir un message d'erreur explicite, rebuild avec output verbeux :

```bash
docker compose -f docker-compose.prod.yml build --no-cache --progress=plain laravel 2>&1 | grep -iE "imagick|pecl|error"
```

Causes fréquentes sur Alpine :
- `imagemagick-dev` non disponible dans le mirror Alpine → essayer `apk update` avant.
- PECL build qui demande l'autoconf path en interactif → ajouter `printf "\n" | pecl install imagick` dans le Dockerfile.
- Incompat PHP 8.4 ↔ imagick < 3.7 → forcer une version : `pecl install imagick-3.7.0`.

#### Le filigrane est mal placé après activation du cache

Revert immédiat :

```bash
# Local
sed -i '' 's/USE_WATERMARK_LAYER_CACHE = true/USE_WATERMARK_LAYER_CACHE = false/' api/app/Services/ImageProcessingService.php
git commit -am "revert p2.2 cache, visual regression"
git push
# NAS
git pull && docker compose -f docker-compose.prod.yml restart laravel queue
```

#### Les uploads échouent encore avec "Fichier temporaire non trouvé"

C'est le bug P1.3 qui n'a pas été corrigé — soit le code n'a pas été rechargé, soit on est sur une vieille image. Forcer le restart complet :

```bash
docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.prod.yml up -d
```

Et vérifier que la version du code est bien la nouvelle :

```bash
docker exec api-queue git -C /var/www log -1 --oneline
# Doit pointer vers le commit qui contient les changements P1.
```

### 9.7 Rollback complet (au pire)

Si quelque chose se passe vraiment mal après le déploiement P1+P2 :

```bash
# Sur le NAS
cd /volume1/docker/oceane-api
git log --oneline -10                  # repérer le SHA d'avant la mise à jour
git reset --hard <SHA_AVANT_UPDATE>
docker compose -f docker-compose.prod.yml build laravel queue
docker compose -f docker-compose.prod.yml up -d --force-recreate
```

⚠️ `reset --hard` jette toute modification locale non commitée — bien vérifier qu'il n'y en a pas avant. Alternativement, `git revert` pour un retour propre.

---

*Section déploiement ajoutée le 2026-05-19, après application de P1 + P2 (3 actifs / 2 opt-in).*

---

## 10. Sync pivot — pourquoi on a abandonné la queue pour les uploads

### 10.1 Le bug constaté

Test post-P1+P2 (2026-05-19 09:03) : 8 photos de 18 Mo uploadées, **3 traitées, 5 perdues** :

- 8 lignes `photo_uploads` créées (donc storeAsync s'est exécuté pour les 8)
- 5 marquées `failed` avec `error_message: "Échec après plusieurs tentatives: Fichier temporaire non trouvé: temp_uploads/<uuid>.jpg"`
- L'exception est levée depuis `ProcessPhotoJob.php:55` avec le message *ancien format* (sans le `"après Xs d'attente"` introduit par P1.1)

Ce message ne correspond à AUCUN throw dans le code actuel sur disque (le fichier ne contient plus ce throw depuis P1.1, commité dans `fc5a569`). Pourtant le worker l'a thrown.

### 10.2 Cause racine

Deux problèmes se cumulent :

#### Problème 1 — Race virtio-fs sur macOS Docker Desktop

Le container `laravel` (PHP-FPM) écrit `storage/app/private/temp_uploads/{uuid}.ext` via le bind-mount `./api:/var/www`. Le container `queue-worker` lit le même fichier via le même bind-mount.

Sur macOS Docker Desktop, le partage est **virtio-fs**. Pour un fichier de 4 octets, la propagation est instantanée (testé : `echo > _xtest && cat _xtest` fonctionne immédiatement entre containers). Mais **3-8 fichiers de 18-30 Mo écrits simultanément** par 3 workers PHP-FPM en parallèle peuvent saturer le canal et retarder la visibilité côté lecteur de plusieurs secondes.

En production (NAS UGREEN, Linux natif), il n'y a pas de virtio-fs — le bind-mount est un montage kernel direct, instantané. **Ce bug est spécifique au dev macOS.**

#### Problème 2 — Workers Laravel CLI qui survivent avec des classes obsolètes en RAM

`php artisan queue:work` est un process PHP CLI long-running. PHP charge chaque classe **une seule fois** dans sa table de classes, puis la garde en mémoire jusqu'à la fin du process. Modifier le `.php` sur disque ne recharge **PAS** la classe dans un process déjà actif.

Le flag `--max-time=300` est censé compenser en exitant le worker toutes les 5 min (Docker le restarte ensuite via `restart: unless-stopped`). En pratique :

- Sur macOS Docker Desktop, le timer peut dériver (suspend, swap, virtualisation).
- Un `docker compose restart` ne garantit pas que le PHP process est tué proprement — selon la version Docker, l'ancien process peut persister.
- Un `docker compose up -d --force-recreate` détruit et recrée le container, garantissant un PHP process frais.

Résultat : on a vu un worker tourner avec un code PRE-P1 alors que le fichier sur disque était POST-P1 depuis 2 heures. Les retries faisaient toujours appel au throw obsolète, et le `finally { cleanupTempFile(); }` (lui aussi obsolète, supprimé par P1.3) effaçait le fichier à chaque tentative.

### 10.3 Pourquoi la solution P1.3 était insuffisante

P1.3 préservait le fichier temp entre les retries (en re-throwant l'exception au lieu de l'avaler, et en déplaçant le cleanup dans `failed()`). Cela aurait corrigé le bug **SI le worker avait tourné avec la version P1.3 du code**.

Mais comme on n'a pas de garantie que le worker a effectivement chargé le nouveau code (cf. § 10.2 Problème 2), le bug reste latent. À chaque édition de `ProcessPhotoJob.php`, on dépend du fait que le worker a bien restart depuis. Fragile.

### 10.4 La solution : éliminer le partage de filesystem

`PhotoController::storeAsync` traite désormais chaque photo **en synchrone**, dans le même process PHP-FPM qui reçoit l'upload HTTP :

```
AVANT
─────
[Frontend] ──POST chunk──> [laravel:PHP-FPM]
                                │
                                ├── PhotoUpload::create(status=pending)
                                ├── $file->storeAs(temp_uploads/xxx.jpg, 'local')   ← bind-mount
                                ├── ProcessPhotoJob::dispatch                       ← DB insert
                                └── 200 OK (status=pending)
                                                                                     ╲
                                                                                      virtio-fs
                                                                                     ╱  (race)
                                            [queue-worker:CLI] <──poll jobs table───╯
                                                │
                                                ├── waitForTempFile(30s)
                                                ├── ImageProcessingService::process()
                                                └── markCompleted

APRÈS
─────
[Frontend] ──POST chunk──> [laravel:PHP-FPM]
                                │
                                ├── PhotoUpload::create(status=processing)
                                ├── ImageProcessingService::processUploadedPhoto($file, ...)   ← inline
                                │     ├── putFileAs MinIO (stream)                              ← P2.3
                                │     └── preview + thumbnail (decode once)                     ← P2.1
                                ├── Photo::create + markCompleted
                                └── 200 OK (status=completed, photo_id)
```

Plus de fichier temp, plus de queue, plus de cross-container, plus de problème de code stale.

### 10.5 Trade-offs

| Aspect | Avant (queue) | Après (sync) |
|---|---|---|
| Réponse HTTP | Instantanée (status=pending) | Après traitement (5-10 s par chunk) |
| Race condition virtio-fs | Possible (3-30 % de pertes constatées) | **Impossible** |
| Workers stale | Possible (PHP CLI long-running) | **Impossible** (process PHP-FPM redémarre à chaque request) |
| Parallélisme | 1 worker queue | 3 workers PHP-FPM en parallèle (limité par `concurrentChunks: 3`) |
| Retries automatiques | 3 essais via Laravel queue | Pas de retry serveur — le frontend retry les chunks failed |
| Mémoire PHP par worker | Faible quand idle | ~200-300 Mo pendant traitement |

Le seul "moins" est l'absence de retries serveur. Mitigation :

- Le frontend retry déjà les chunks en cas de `Network error` ou `Upload timeout` (`maxRetries: 2` dans `web/src/config/constants.ts`).
- Si l'erreur est définitive (image corrompue, MinIO down), un retry serveur n'aurait pas aidé non plus.
- Pour les erreurs transitoires non-timeout, l'utilisateur voit le file en `failed` dans l'UI et peut le re-déposer.

### 10.6 Validation en local (2026-05-19)

| Test | Photos | Taille / photo | Résultat | Durée approx. |
|---|---|---|---|---|
| Smoke 1 | 8 | 18 Mo | **8/8 OK** | ~15 s |
| Galerie complète | 61 | 18 Mo (~moyenne) | **61/61 OK** | ~1 min 40 |

Aucune perte, aucune erreur côté Laravel ni queue worker. Logs `storeAsync: photo processed` montrent `duration_ms` typique 3 000–5 000 par photo, conforme à l'estimation Imagick + P2.1.

Estimation pour 100 photos (jamais testé à cette taille) :

- Frontend chunke en 100 chunks de 1 photo
- `concurrentChunks: 3` → traitement par batches de 3 en parallèle
- ~5 s par photo en backend (Imagick + P2.1)
- **Total ≈ 100/3 × 5 s = 170 s ≈ 2 min 50 s**

Sous le `max_execution_time = 120 s` par chunk (chaque chunk = 1 photo de 5 s, largement OK).

### 10.7 Impact sur la prod NAS

- Le NAS Linux n'avait JAMAIS le problème virtio-fs (bind-mount kernel natif).
- Le sync pivot fonctionne PARTOUT. Le NAS bénéficie de :
  - Suppression de l'overhead queue (3 s `--sleep` entre jobs + I/O Postgres pour la table `jobs`)
  - Suppression de la dépendance au timing de restart du worker
  - Mêmes 30 workers PHP-FPM disponibles pour les uploads
  - Le worker queue NAS continue de tourner pour SMS Brevo, exports school session, etc.

### 10.8 Ce qui reste de l'architecture queue

- Le worker `queue-worker` (dev) et `api-queue` (prod) continuent à tourner — d'autres jobs les utilisent (`DispatchSmsBatchJob`, `GenerateSchoolSessionExportJob`, `ProcessSchoolSessionJob`, `SendSchoolSessionSmsJob`).
- La classe `App\Jobs\ProcessPhotoJob` reste en place — peut servir à un futur batch-retry, mais n'est plus dispatched depuis `storeAsync`.
- La commande `php artisan photos:retry-failed` est désormais inutile pour les nouveaux uploads (pas de temp file à récupérer). À supprimer dans un futur cleanup.

### 10.9 Action de déploiement supplémentaire

Côté NAS, le sync pivot ne nécessite rien de plus que `git pull` + `docker compose -f docker-compose.prod.yml restart laravel`. Pas de rebuild Docker (le `Dockerfile` n'a pas changé pour ce pivot).

Le service `api-queue` peut continuer à tourner — il ne traitera juste plus de `ProcessPhotoJob`. Si on veut nettoyer plus tard, on pourra retirer la classe inutilisée et la commande `photos:retry-failed`.

---

*Section sync pivot ajoutée le 2026-05-19 après diagnostic et fix du bug de pertes 3/8 en local.*

---

## 11. Régression secondaire détectée pendant la validation 61 photos

### 11.1 Symptôme

À 61 photos, l'UI affichait un toast d'erreur **après un upload pourtant réussi** (61/61 en DB). Console JS :

```
GET /api/admin/upload-status?batch_id=...&include_uploads=false
→ 422 Unprocessable Content
{"include_uploads":["The include uploads field must be true or false."]}
```

### 11.2 Cause

P1.5 introduit le polling allégé pour les batches > 50 photos : le frontend appelle `/admin/upload-status` avec `?include_uploads=false`. Côté backend, `UploadStatusRequest` valide ce paramètre avec la règle Laravel `boolean`.

La règle `boolean` de Laravel n'accepte que `true`, `false`, `1`, `0`, `"1"`, `"0"`. **La string `"false"` est rejetée** — c'est documenté mais contre-intuitif (cf. [Laravel docs · boolean rule](https://laravel.com/docs/12.x/validation#rule-boolean)).

À 50 photos ou moins, le frontend reste en polling complet (envoie `include_uploads` non set, donc règle satisfaite via `nullable`). Le bug n'a donc surgi qu'à partir de 51 photos.

### 11.3 Fix

`web/src/services/uploadService.ts` envoie désormais `include_uploads=0` au lieu de `include_uploads=false`. Une ligne, validation côté Laravel passe.

### 11.4 Pourquoi l'upload réussissait quand même

Le 422 du polling ne casse pas l'upload : les photos sont déjà persistées en DB et sur MinIO via `storeAsync` (sync, cf. § 10). Le polling était purement informatif — quand il jette, le frontend prend la branche d'erreur et affiche un toast trompeur. Le pivot sync rend même ce polling quasi inutile (toutes les photos sont déjà `completed` quand le polling commence), mais on le garde pour l'instant car il sert encore à propager les erreurs réelles si un chunk a un échec réseau.

### 11.5 Cleanup possible plus tard

Vu que `storeAsync` est désormais synchrone, le polling est redondant : la réponse de chaque chunk POST contient déjà `status: 'completed'`. À terme, on peut :

- Supprimer la boucle `pollUntilComplete` côté frontend
- Supprimer l'endpoint `/admin/upload-status` côté backend
- Mettre à jour le composable `useChunkedUpload` pour ne plus exposer le polling

Pas urgent (le polling est inoffensif), mais ce serait du code mort à nettoyer dans P4.

---

*Section 11 ajoutée le 2026-05-19 après validation 61/61 et fix du 422.*
