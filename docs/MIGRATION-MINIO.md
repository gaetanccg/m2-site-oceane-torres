# Migration Supabase Storage vers MinIO

## Statut : EFFECTUEE

La migration du stockage Supabase vers MinIO a été effectuée.

---

## Configuration appliquée

| Variable         | Valeur      |
|------------------|-------------|
| MINIO_ENDPOINT   | ``          |
| MINIO_ACCESS_KEY | ``          |
| MINIO_SECRET_KEY | ``          |
| MINIO_BUCKET     | `galleries` |
| MINIO_REGION     | `us-east-1` |

---

## Fichiers modifies

| Fichier                                              | Modification                          |
|------------------------------------------------------|---------------------------------------|
| `api/composer.json`                                  | Ajout de `league/flysystem-aws-s3-v3` |
| `api/config/filesystems.php`                         | Ajout du disk `minio`                 |
| `api/.env`                                           | Variables MinIO ajoutees              |
| `api/.env.example`                                   | Template mis a jour                   |
| `api/app/Services/MinioStorageService.php`           | Nouveau service (remplace Supabase)   |
| `api/app/Http/Controllers/Api/PhotoController.php`   | Utilise MinioStorageService           |
| `api/app/Http/Controllers/Api/GalleryController.php` | Utilise MinioStorageService           |

---

## A faire sur MinIO

### 1. Creer le bucket `galleries`

Via la console MinIO (port 9001) :

1. Aller dans "Buckets"
2. Cliquer "Create Bucket"
3. Nom : `galleries`
4. Laisser les options par defaut (bucket prive)

### 2. Verifier la policy

Ta policy `site_access` doit etre attachee a ton utilisateur/access key.

---

## Tester la migration

```bash
# 1. Vider le cache Laravel
cd api && php artisan config:clear

# 2. Tester la connexion MinIO
php artisan tinker
>>> Storage::disk('minio')->put('test.txt', 'Hello MinIO');
>>> Storage::disk('minio')->get('test.txt');
>>> Storage::disk('minio')->delete('test.txt');

# 3. Lancer le serveur
php artisan serve
```

Ensuite dans l'admin :

1. Creer une galerie
2. Uploader une photo
3. Verifier qu'elle apparait dans MinIO (console port 9001)
4. Tester le telechargement

---

## Rollback (si besoin)

Pour revenir a Supabase, modifier les imports dans les controleurs :

```php
// Remplacer
use App\Services\MinioStorageService;
// Par
use App\Services\SupabaseStorageService;
```

Et utiliser `SupabaseStorageService` au lieu de `MinioStorageService`.

---

## Notes importantes

- **Bucket prive** : Les photos ne sont accessibles que via signed URLs (expire apres 1h par defaut, 5min pour les telechargements)
- **Edge Function supprimee** : Le nettoyage des fichiers est maintenant gere directement par Laravel
- **Compatibilite** : Le code gere les anciennes photos (metadata `supabase_path`) et les nouvelles (`storage_path`)
