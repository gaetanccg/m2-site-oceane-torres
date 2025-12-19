# Administration - Océane Torres Photographie

## Accès au Dashboard Admin

### URL
```
http://localhost:5173/admin/login
```

### Routes disponibles

| Route | Description |
|-------|-------------|
| `/admin/login` | Page de connexion |
| `/admin` | Tableau de bord |
| `/admin/reservations` | Gestion des réservations + calendrier |
| `/admin/clients` | Gestion des clients |
| `/admin/prestations` | Gestion des prestations |
| `/admin/galleries` | Gestion des galeries (publiques/privées) |
| `/admin/factures` | Gestion des factures |
| `/admin/gift-cards` | Gestion des bons cadeaux |

## Authentification

L'accès admin nécessite une authentification via l'API Laravel.

### Créer un utilisateur admin (Backend)

Dans le backend Laravel, utilise Tinker pour créer un admin :

```bash
cd ../api
php artisan tinker
```

```php
$user = new App\Models\User();
$user->name = 'Admin';
$user->email = 'admin@oceanetorres.fr';
$user->password = bcrypt('motdepasse123');
$user->role = 'admin';
$user->save();
```

### Identifiants de test

Une fois l'utilisateur créé :

- **Email** : `admin@oceanetorres.fr`
- **Mot de passe** : `motdepasse123`

## Fonctionnalités

### Dashboard
- Statistiques (réservations, revenus, clients)
- Réservations récentes
- Activité récente
- Actions rapides

### Réservations
- Liste avec filtres par statut
- Vue calendrier
- Changement de statut (en attente, confirmé, annulé, terminé)

### Clients
- Liste avec recherche
- Détails client (statistiques, réservations, galeries)
- Modification/Suppression

### Prestations
- CRUD complet
- Activation/Désactivation
- Catégories (portrait, sport, animalier, automobile, entreprise, vidéo)

### Galeries
- Galeries publiques et privées
- Upload de photos
- Génération de token d'accès pour galeries privées
- Date d'expiration configurable

### Factures
- Création avec calcul TVA automatique
- Téléchargement PDF
- Envoi par email
- Statuts (brouillon, envoyée, payée, annulée)

### Bons Cadeaux
- Suivi des bons vendus
- Statuts (actif, utilisé, expiré)
- Modification date d'expiration

## Notes techniques

- **State management** : Pinia
- **Authentification** : Token JWT via Laravel Sanctum
- **Stockage token** : localStorage (`auth_token`)
