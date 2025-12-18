# Commandes du projet Océane Torres Photographie

## Démarrage Local (sans Docker)

```bash
# Terminal 1 - Backend Laravel
cd api && php artisan serve

# Terminal 2 - Frontend Vue.js
cd web && npm run dev
```

**URLs :**

- Frontend : http://localhost:5173
- API : http://localhost:8000/api

---

## Docker

### Démarrage

```bash
# Démarrer tous les services
docker compose up -d

# Démarrer avec rebuild des images
docker compose up -d --build

# Voir les logs en temps réel
docker compose logs -f

# Logs d'un service spécifique
docker compose logs -f laravel
docker compose logs -f frontend
```

### Arrêt

```bash
# Arrêter les conteneurs
docker compose stop

# Arrêter et supprimer les conteneurs
docker compose down

# Arrêter et supprimer avec les volumes (reset complet)
docker compose down -v
```

### Status

```bash
# Voir l'état des conteneurs
docker compose ps

# Accéder à un conteneur
docker compose exec laravel sh
docker compose exec frontend sh
```

**URLs Docker :**

- Frontend : http://localhost:5173
- API : http://localhost:8000/api
- Mailpit (emails) : http://localhost:8025

---

## Backend Laravel (api/)

### Migrations

```bash
cd api

# Lancer les migrations
php artisan migrate

# Rollback dernière migration
php artisan migrate:rollback

# Reset complet + re-migrate
php artisan migrate:fresh

# Avec seeders
php artisan migrate:fresh --seed

# Status des migrations
php artisan migrate:status
```

### Création de fichiers

```bash
cd api

# Controller
php artisan make:controller NomController
php artisan make:controller Api/NomController --api    # Controller API (sans create/edit)
php artisan make:controller NomController --resource   # Controller CRUD complet

# Model
php artisan make:model Nom
php artisan make:model Nom -m                          # Avec migration
php artisan make:model Nom -mcr                        # Avec migration, controller, resource

# Migration
php artisan make:migration create_noms_table
php artisan make:migration add_column_to_noms_table

# Seeder
php artisan make:seeder NomSeeder
php artisan db:seed                                    # Lancer tous les seeders
php artisan db:seed --class=NomSeeder                  # Seeder spécifique

# Request (validation)
php artisan make:request NomRequest

# Resource (API response)
php artisan make:resource NomResource
php artisan make:resource NomCollection --collection

# Middleware
php artisan make:middleware NomMiddleware

# Policy
php artisan make:policy NomPolicy --model=Nom

# Event & Listener
php artisan make:event NomEvent
php artisan make:listener NomListener --event=NomEvent

# Job (queue)
php artisan make:job NomJob

# Mail
php artisan make:mail NomMail

# Notification
php artisan make:notification NomNotification
```

### Commandes utiles

```bash
cd api

# Cache
php artisan config:cache        # Cache la config
php artisan config:clear        # Vide le cache config
php artisan cache:clear         # Vide le cache application
php artisan view:clear          # Vide le cache des vues
php artisan route:clear         # Vide le cache des routes
php artisan optimize:clear      # Vide tous les caches

# Routes
php artisan route:list          # Liste toutes les routes
php artisan route:list --path=api  # Filtrer par path

# Tinker (REPL)
php artisan tinker

# Queue
php artisan queue:work          # Lancer le worker
php artisan queue:listen        # Worker avec hot reload

# Storage
php artisan storage:link        # Créer le lien symbolique public/storage

# Tests
php artisan test                # Lancer les tests
php artisan test --filter=NomTest  # Test spécifique
```

---

## Frontend Vue.js (web/)

```bash
cd web

# Développement
npm run dev

# Build production
npm run build

# Preview build
npm run preview

# Linting
npm run lint
npm run lint:fix

# Type check
npm run type-check
```

---

## Base de données

### Accès Supabase

- Dashboard : https://supabase.com/dashboard/project/ekhlybdoblhjigpnpbff
- API URL : https://ekhlybdoblhjigpnpbff.supabase.co

### Commandes utiles

```bash
cd api

# Voir la structure d'une table
php artisan db:table users

# Lancer un SQL brut
php artisan tinker
>>> DB::select('SELECT * FROM users');
```

---

## Git

```bash
# Status
git status

# Commit
git add .
git commit -m "message"

# Push
git push origin develop

# Pull
git pull origin develop

# Changer de branche
git checkout main
git checkout develop
```
