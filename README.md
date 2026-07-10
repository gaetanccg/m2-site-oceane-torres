# Océane Torres Photographie

**Plateforme Web pour photographe indépendante**
Projet de fin d'année – Master 2 Expert en Développement Web

**Étudiant : Gaëtan Chollet - 2025**

---

## Présentation

Le projet vise à créer une **plateforme web complète** pour Océane Torres, photographe professionnelle indépendante.

Objectifs principaux :

* Site vitrine moderne et responsive
* Gestion des **réservations et paiements en ligne**
* Galerie privée sécurisée pour les clients
* Tableau de bord administrateur pour la photographe
* Gestion automatisée et centralisée de l'activité

---

## Stack Technique

| Côté            | Technologie                      | Rôle                                          |
|-----------------|----------------------------------|-----------------------------------------------|
| Frontend        | Vue.js 3 + Vite                  | SPA responsive et dynamique                   |
| UI              | TailwindCSS + shadcn-vue         | Composants UI réutilisables                   |
| State           | Pinia                            | Gestion de l'état global                      |
| Routing         | Vue Router                       | Navigation entre pages                        |
| Backend         | Laravel 12 + PHP 8.3             | API REST, logique métier, dashboard admin     |
| Auth            | Laravel Sanctum                  | Auth API sécurisée                            |
| Base de données | Supabase (PostgreSQL)            | Stockage utilisateurs, réservations, galeries |
| Stockage        | Supabase Storage                 | Photos et vidéos publiques et privées         |
| Paiements       | Stripe & PayPal                  | Paiement en ligne sécurisé                    |
| Images          | Intervention Image               | Watermark, optimisation, redimensionnement    |
| Emails          | Mailgun / MailHog (dev)          | Notifications et confirmations                |
| Docker          | Docker & Docker Compose          | Conteneurs backend, Nginx, MailHog            |
| Tests           | PHPUnit (Laravel) + Vitest (Vue) | Tests unitaires et d'intégration              |
| CI/CD           | GitHub Actions                   | Build, tests et déploiement automatique       |

---

## Structure du projet

```
/m2-site-oceane-torres/
├── api/                    # Backend Laravel
│   ├── app/
│   │   ├── Http/Controllers/Api/    # Controllers API
│   │   └── Models/                   # Modèles Eloquent
│   ├── database/migrations/          # Migrations BDD
│   ├── routes/api.php                # Routes API
│   └── Dockerfile
├── web/                    # Frontend Vue (à importer)
├── docker/                 # Configuration Docker
│   ├── nginx.conf
│   └── php.ini
├── supabase/              # Documentation Supabase
├── .github/workflows/     # CI/CD GitHub Actions
├── docker-compose.yml
├── Makefile               # Commandes utilitaires
└── README.md
```

---

## Installation rapide

### Prérequis

- PHP 8.3+
- Composer
- Docker & Docker Compose
- Node.js 20+ (pour le frontend)

### Setup

```bash
# 1. Cloner le projet
git clone git@github.com:gaetanccg/m2-site-oceane-torres.git
cd m2-site-oceane-torres

# 2. Initialiser le projet
make init

# 3. Configurer les variables d'environnement
# Éditer api/.env avec vos credentials Supabase, Stripe, PayPal

# 4. Démarrer les conteneurs Docker
make up

# 5. Exécuter les migrations
make migrate
```

---

## Commandes Makefile

```bash
make help          # Voir toutes les commandes disponibles

# Docker
make up            # Démarrer les conteneurs
make down          # Arrêter les conteneurs
make restart       # Redémarrer les conteneurs
make logs          # Voir les logs de tous les services
make logs-api      # Voir les logs Laravel uniquement

# Laravel
make shell         # Accéder au shell du container Laravel
make migrate       # Exécuter les migrations
make seed          # Exécuter les seeders
make fresh         # Reset la base de données (migrate:fresh --seed)

# Tests & Qualité
make test          # Lancer les tests PHPUnit (démarre la base de test au besoin)
make test-db-up    # Démarrer le Postgres de test (conteneur dédié, port 55432)
make test-db-down  # Arrêter le Postgres de test
make test-coverage # Tests avec couverture de code
make lint          # Formater le code avec Pint
make lint-check    # Vérifier le style sans corriger

# Développement sans Docker
make serve         # Démarrer le serveur Laravel local
make queue         # Démarrer le worker de queue

# Nettoyage
make clean         # Vider les caches Laravel
make prune         # Supprimer toutes les données Docker
```

---

## URLs en développement

| Service          | URL                   |
|------------------|-----------------------|
| API Laravel      | http://localhost:8000 |
| Frontend Vue     | http://localhost:5173 |
| MailHog (emails) | http://localhost:8025 |

---

## API Endpoints

### Authentification

| Méthode | Endpoint             | Description          |
|---------|----------------------|----------------------|
| POST    | `/api/auth/register` | Inscription          |
| POST    | `/api/auth/login`    | Connexion            |
| POST    | `/api/auth/logout`   | Déconnexion          |
| GET     | `/api/auth/user`     | Utilisateur connecté |
| PUT     | `/api/auth/profile`  | Modifier profil      |

### Public

| Méthode | Endpoint                       | Description           |
|---------|--------------------------------|-----------------------|
| GET     | `/api/prestations`             | Liste des prestations |
| GET     | `/api/galleries`               | Galeries publiques    |
| GET     | `/api/galleries/token/{token}` | Galerie par token     |
| POST    | `/api/contact`                 | Formulaire de contact |

### Client (authentifié)

| Méthode | Endpoint                             | Description           |
|---------|--------------------------------------|-----------------------|
| GET     | `/api/reservations`                  | Mes réservations      |
| POST    | `/api/reservations`                  | Nouvelle réservation  |
| GET     | `/api/my-galleries`                  | Mes galeries privées  |
| POST    | `/api/payments/stripe/create-intent` | Créer paiement Stripe |

### Admin

| Méthode | Endpoint               | Description          |
|---------|------------------------|----------------------|
| GET     | `/api/admin/dashboard` | Tableau de bord      |
| CRUD    | `/api/admin/users`     | Gestion utilisateurs |
| CRUD    | `/api/admin/galleries` | Gestion galeries     |
| CRUD    | `/api/admin/factures`  | Gestion factures     |

---

## Configuration Supabase

Voir [supabase/README.md](supabase/README.md) pour :

- Création du projet Supabase
- Configuration des buckets Storage
- Policies RLS (Row Level Security)

---

## Modèle de données

### Tables principales

- **users** - Utilisateurs (admin/client)
- **prestations** - Services proposés
- **reservations** - Réservations clients
- **client_forms** - Formulaires de réservation
- **galleries** - Galeries photo (public/privé)
- **photos** - Médias (images/vidéos)
- **payments** - Paiements (Stripe/PayPal)
- **factures** - Factures générées
- **gift_cards** - Bons cadeaux
- **notifications** - Notifications utilisateurs

---

## Tests

La suite de tests backend (PHPUnit) couvre les **parties critiques** : paiement (checkout,
webhook SumUp, vérification, annulation), téléchargement (ZIP galerie, photo, HD verrouillé
par achat) et upload (traitement photo, validation, jobs).

### Prérequis : base de données de test dédiée (Postgres)

Le code utilise du SQL spécifique à PostgreSQL (`pg_advisory_xact_lock`, `FILTER (WHERE)`,
casts booléens) **incompatible avec SQLite**. Les tests tournent donc sur une base Postgres
dédiée, lancée dans un conteneur Docker isolé (profil `test`, données en RAM via `tmpfs`,
port `55432`). Elle est **séparée** de la base applicative (Supabase) et détruite à l'arrêt.

Aucune configuration manuelle n'est requise : la connexion de test est définie dans
`api/phpunit.xml` (services externes neutralisés, SumUp en mode **sandbox**).

### Lancer les tests

```bash
# Tout-en-un : démarre la base de test puis exécute PHPUnit
make test

# Base de test seule (à garder démarrée pendant le développement des tests)
make test-db-up      # démarre le Postgres de test (127.0.0.1:55432)
make test-db-down    # arrête et supprime le conteneur de test

# Une fois la base démarrée, on peut lancer PHPUnit directement :
cd api && php artisan test
cd api && php artisan test --filter=CheckoutTest   # un test précis

# Couverture de code
make test-coverage

# Style de code (Laravel Pint)
make lint-check      # vérifie sans corriger
make lint            # corrige
```

> **Prérequis** : Docker doit être démarré (la base de test tourne dans un conteneur).
> Le port `55432` doit être libre.

---

## CI/CD

Le pipeline GitHub Actions (`.github/workflows/ci.yml`) :

1. **Backend Tests** - PHPUnit sur Laravel
2. **Code Quality** - Laravel Pint (style)
3. **Build** - Construction de l'image Docker

---

## Licence

Projet académique - Tous droits réservés.
