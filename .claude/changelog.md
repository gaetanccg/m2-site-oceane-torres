# Changelog

---

## 19/12/2025 — 09:00–10:00

#### J'ai implémenté toutes les fonctionnalités d'administration pour ton projet. Voici un résumé de ce qui a été créé :

### Structure des fichiers créés

#### Stores (Pinia)

- `src/stores/auth.ts` - Gestion de l'authentification admin

#### Types

- `src/types/admin.ts` - Tous les types TypeScript pour l'admin (User, Reservation, Client, Prestation, Gallery, Facture, etc.)

#### Services API

- `src/services/adminApi.ts` - Service complet pour toutes les requêtes API admin

#### Composants UI réutilisables (`src/components/admin/`)

- `AdminLayout.vue` - Layout principal avec sidebar
- `AdminSidebar.vue` - Navigation latérale avec liens et déconnexion
- `AdminHeader.vue` - En-tête avec notifications
- `ui/Button.vue` - Bouton stylisé
- `ui/DataTable.vue` - Tableau de données avec pagination, recherche et tri
- `ui/Calendar.vue` - Calendrier pour les réservations
- `ui/FormField.vue` - Champs de formulaire (input, select, textarea, checkbox)
- `ui/Modal.vue` - Modal réutilisable
- `ui/StatCard.vue` - Carte de statistiques
- `ui/StatusBadge.vue` - Badge de statut coloré

#### Pages Admin (`src/views/admin/`)

- `Login.vue` - Page de connexion
- `Dashboard.vue` - Tableau de bord avec statistiques et activité récente
- `Reservations.vue` - Gestion des réservations + vue calendrier
- `Clients.vue` - Gestion des clients (CRUD)
- `Prestations.vue` - Gestion des prestations (CRUD)
- `Galleries.vue` - Gestion des galeries publiques/privées avec upload photos
- `Factures.vue` - Gestion des factures avec génération PDF
- `GiftCards.vue` - Gestion des bons cadeaux

#### Mises à jour

- `router/index.ts` - Routes admin avec guard d'authentification
- `main.ts` - Intégration de Pinia
- `App.vue` - Support des layouts (public/admin)
- `package.json` - Ajout de Pinia et VueUse

#### Accès

- URL Admin : `/admin/login`
- Dashboard : `/admin`

---

## 20/12/2025 — 14:00–15:00

### Le système de galeries dual-access est maintenant implémenté. Voici un résumé de ce qui a été fait :

## Backend (Laravel)

#### Migration BDD (`api/database/migrations/2024_12_20_000001_add_gallery_share_features.php`)

- `share_code` (6 caractères) sur `galleries`
- `is_downloadable` sur `photos`

#### Modèles mis à jour

- `Gallery.php` : génération automatique du `share_code`, scopes `byShareCode()`, `byAccessToken()`, attributs `total_likes`, `downloadable_count`
- `Photo.php` : scope `downloadable()`, `liked()`, méthodes `toggleDownloadable()`, `incrementLikes()`

#### Service (`api/app/Services/SupabaseStorageService.php`)

- Upload/download de photos
- Suppression de galeries
- URLs signées pour téléchargement

#### Routes API publiques

- `GET /galleries/code/{code}` - Accès galerie protégée
- `GET /galleries/download/{token}` - Accès galerie téléchargeable
- `POST /photos/{photo}/like` - Liker une photo
- `GET /photos/{photo}/download` - Télécharger une photo
- `GET /galleries/{id}/download-zip` - Télécharger ZIP

#### Routes API admin

- `POST /galleries/{id}/regenerate-code` - Régénérer le code
- `PUT /photos/{id}/toggle-downloadable` - Basculer téléchargeable

## Frontend (Vue.js)

#### Composants

- `WatermarkOverlay.vue` - Overlay CSS avec "@ oceane torres"
- `ShareCodeInput.vue` - 6 inputs pour saisie du code
- `LikeButton.vue` - Bouton coeur animé
- `Lightbox.vue` - Ajoute swipe mobile

#### Pages publiques

- `/gallery` - `GalleryAccess.vue` - Saisie du code
- `/gallery/code/:code` - `ProtectedGallery.vue` - Photos watermarkées + likes
- `/gallery/download/:token` - `DownloadGallery.vue` - Photos HD téléchargeables

#### Admin

- `Galleries.vue` - Affiche `share_code`, `total likes`, `downloadable count`, toggle sur photos

#### Navbar

- Lien "Galerie Privée" redirige maintenant vers `/gallery` (plus de lien externe)

#### Supabase

- Edge Function `cleanup-gallery-files` pour nettoyer les fichiers

### Pour finaliser :

1. Exécuter la migration : `php artisan migrate`
2. Créer le bucket `galleries` sur Supabase Storage
3. Déployer l'Edge Function : `supabase functions deploy cleanup-gallery-files`

---

## 29/12/2025 — 14:00–16:00

### Résumé des modifications

1. Miniature de galerie

- La première photo de la galerie est automatiquement utilisée comme miniature si aucune `cover_image` n'est définie
- Modifié dans `Gallery.php` avec un accessor `getCoverImageAttribute`

2. Tracking des visites

- Nouvelle migration ajoutant `last_viewed_at` et `views_count` à la table `galleries`
- Les visites sont enregistrées quand un client consulte la galerie (via code de partage ou lien de téléchargement)
- Affichage dans l'admin : nombre de vues en haut à droite de la miniature + date relative de dernière visite

3. Photos likées par galerie

- Nouveau compteur `liked_photos_count` affiché dans les stats
- Onglet "Likées" dans le modal des photos pour filtrer
- Bordure rouge sur les photos likées non téléchargeables

4. Sélection des photos téléchargeables

- Interface améliorée avec 3 onglets : Toutes / Likées / Téléchargeables
- Bouton rapide pour rendre toutes les photos likées téléchargeables
- Bouton pour retirer toutes les photos du téléchargement
- Bordure verte sur les photos téléchargeables

5. Interface admin améliorée

- Stats en grille : Photos / Likées / Téléchargeables
- Badge Active/Inactive visible
- Compteur de vues sur la miniature
- Dernière visite avec date relative ("il y a 2h", "il y a 3 jours", etc.)

---

---

## 29/12/2025 — 16:00–17:00

### Résumé des modifications

L'interface de sélection multiple est maintenant complète. Voici ce qui a été ajouté :

Nouvelles fonctionnalités

Mode sélection

- Bouton "Sélectionner" pour activer le mode sélection
- Checkboxes sur chaque photo quand le mode est actif
- Clic sur une photo pour la sélectionner/désélectionner
- Bordure bleue sur les photos sélectionnées

Barre d'actions (quand des photos sont sélectionnées)

- Compteur de photos sélectionnées
- "Tout sélectionner" - sélectionne toutes les photos du filtre actif
- "Désélectionner" - vide la sélection
- "Rendre téléchargeables" (vert) - ajoute les photos sélectionnées à la galerie de téléchargement
- "Retirer du DL" (gris) - retire les photos sélectionnées de la galerie de téléchargement

Workflow typique

1. Ouvrir une galerie
2. Filtrer par "Likées" pour voir les photos favorites du client
3. Cliquer sur "Sélectionner"
4. Cocher les photos à inclure dans le téléchargement
5. Cliquer sur "Rendre téléchargeables"

Les photos marquées comme téléchargeables seront disponibles via le lien de téléchargement de la galerie.

---

## 29/12/2025 — 17:00–17:30

### Lightbox admin pour visualiser les photos en grand

Ajout d'une lightbox dans la gestion des galeries admin permettant de visualiser les photos en plein écran.

#### Fonctionnalités

- **Clic sur une photo** (hors mode sélection) l'affiche en grand sur fond noir
- **Navigation** avec les flèches gauche/droite pour parcourir les photos
- **Fermer** en cliquant sur le X ou sur le fond noir
- **Barre d'info** en bas affichant :
    - Position actuelle (ex: "3 / 15")
    - Nombre de likes si la photo est likée
    - Badge "DL" si la photo est téléchargeable
- **Respect du filtre actif** : la navigation se fait uniquement entre les photos du filtre sélectionné (Toutes / Likées / Téléchargeables)

#### Fichiers modifiés

- `web/src/views/admin/Galleries.vue` : ajout du composant lightbox avec Teleport, variables d'état et fonctions de navigation

---

### Résumé de l'implémentation 02/01/2026

#### Backend (Laravel)

**Nouveaux fichiers créés :**

1. `api/app/Services/ImageProcessingService.php`
    - Génère 3 versions : HD, preview (1200px), thumbnail (400px)
    - Applique un watermark diagonal \@ Oceane Torres intégré dans l'image
    - Supporte le traitement à la volée pour les photos existantes

2. `api/app/Http/Controllers/Api/ImageProxyController.php`
    - `GET /api/images/preview/{photo}` — stream de la version preview
    - `GET /api/images/thumbnail/{photo}` — stream de la version thumbnail
    - `GET /api/images/download/{photo}?token=&order=` — HD après validation d'achat
    - Fallback automatique : génération à la volée si la photo n'est pas traitée

3. `api/database/migrations/2026_02_02_000001_add_image_versions_to_photos.php`
    - `file_path_preview` (string, nullable)
    - `file_path_thumbnail` (string, nullable)
    - `is_processed` (boolean, default false)

4. `api/app/Console/Commands/ProcessExistingPhotos.php`
    - Commande : `php artisan photos:process-existing --batch=25`
    - Traite les photos existantes par batch

**Fichiers modifiés :**

- `api/app/Models/Photo.php` — nouveaux attributs : `preview_url`, `thumbnail_url`, `display_url` (rétrocompatible)
- `api/app/Http/Controllers/PhotoController.php` — utilise `ImageProcessingService` à l'upload
- `api/app/Http/Controllers/OrderController.php` — ajoute `preview_url` et `thumbnail_url` dans `formatOrder`
- `api/routes/api.php` — nouvelles routes avec rate limiting
- `api/app/Providers/AppServiceProvider.php` — configuration rate limiting (120/min images, 30/min downloads)

#### Frontend (Vue)

**Fichiers modifiés (11 composants Vue) :**

- `web/src/views/ProtectedGallery.vue` — utilise `preview_url`, suppression de l'import `WatermarkOverlay`
- `web/src/components/EventGallery.vue` — utilise `preview_url`
- `web/src/views/DownloadGallery.vue` — utilise `preview_url`
- `web/src/views/Events.vue` — utilise `thumbnail_url`
- `web/src/components/Cart.vue` — utilise `thumbnail_url`
- `web/src/views/Checkout.vue` — utilise `thumbnail_url`
- `web/src/views/OrderConfirmation.vue` — utilise `thumbnail_url`
- `web/src/components/CartItem.vue` — utilise `thumbnail_url`
- `web/src/views/admin/Galleries.vue` — utilise `preview_url`
- `web/src/views/admin/EventGalleries.vue` — utilise `thumbnail_url` et `preview_url`
- `web/src/views/admin/Orders.vue` — utilise `thumbnail_url`
- `web/src/components/account/GalleryCard.vue` — utilise `thumbnail_url`

**Types TypeScript mis à jour :**

- `src/types/admin.ts` — ajout : `preview_url`, `thumbnail_url`, `is_processed`
- `src/types/account.ts` — ajout : `preview_url`, `thumbnail_url`
- `src/services/cartApi.ts` — ajout : `preview_url`, `thumbnail_url`

**Fichier supprimé :**

- `web/src/components/WatermarkOverlay.vue` — watermark CSS devenu inutile

#### Ordre de déploiement recommandé

1. Déployer le backend avec la nouvelle migration
2. Exécuter `php artisan migrate`
3. Les galeries existantes continueront de fonctionner (fallback à la volée)
4. Lancer en arrière-plan : `php artisan photos:process-existing --batch=25`
5. Déployer le frontend
