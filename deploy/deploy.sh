#!/bin/bash
# ===========================================
# SCRIPT DE DEPLOIEMENT - NAS UGREEN
# ===========================================
# Usage: ./deploy/deploy.sh [--no-pull] [--no-build] [--fresh]
#
# --fresh: rebuild sans cache Docker. Inutile au quotidien: le compose monte
#   ./api sur /var/www, donc les couches applicatives de l'image sont de toute
#   facon masquees au runtime, et un changement de Dockerfile invalide deja le
#   cache tout seul. A reserver aux cas ou une couche systeme doit repartir de
#   zero (pecl install imagick n'est pas epingle) ou a un cache corrompu.
#   Pour recuperer aussi une base php:8.4-fpm-alpine plus recente, il faut un
#   docker compose ... build --pull, que --fresh ne fait pas.
#
# Prerequis:
#   1. Le repo est clone sur le NAS
#   2. deploy/.env.prod existe (copie depuis .env.prod.example)
#
# Le compose est lance depuis la RACINE du repo.
# Il reference ./api, ./deploy/docker, ./deploy/.env.prod
# ===========================================

set -e

# Se placer a la racine du repo (parent de deploy/)
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$REPO_ROOT"

COMPOSE_FILE="deploy/docker-compose.prod.yml"

# Compose V2 resout les chemins relatifs (context: ./api, env_file: ./deploy/...)
# par rapport au dossier du fichier compose. On force --project-directory a la
# RACINE du repo pour que ./api et ./deploy/... pointent au bon endroit.
DC=(docker compose --project-directory "$REPO_ROOT" -f "$COMPOSE_FILE")

echo "=========================================="
echo "  DEPLOIEMENT OCEANE API"
echo "  Repo: $REPO_ROOT"
echo "=========================================="

# Verifier que .env.prod existe
if [ ! -f "deploy/.env.prod" ]; then
    echo "ERREUR: deploy/.env.prod introuvable."
    echo "Copier le template: cp deploy/.env.prod.example deploy/.env.prod"
    echo "Puis remplir les secrets."
    exit 1
fi

# Etape 1: Git pull (sauf si --no-pull)
if [[ "$*" != *"--no-pull"* ]]; then
    echo ""
    echo "1/6 - Pulling latest code..."
    git pull origin main
else
    echo ""
    echo "1/6 - Skipping git pull (--no-pull)"
fi

# Etape 2: Build
if [[ "$*" != *"--no-build"* ]]; then
    echo ""
    if [[ "$*" == *"--fresh"* ]]; then
        echo "2/6 - Building containers (--fresh: sans cache Docker)..."
        "${DC[@]}" build --no-cache
    else
        echo "2/6 - Building containers..."
        "${DC[@]}" build
    fi
else
    echo ""
    echo "2/6 - Skipping build (--no-build)"
fi

# Etape 3: Stop
echo ""
echo "3/6 - Stopping old containers..."
"${DC[@]}" down

# Etape 4: Start
echo ""
echo "4/6 - Starting new containers..."
"${DC[@]}" up -d

# Etape 5: Wait + Laravel setup
echo ""
echo "5/6 - Waiting for containers + Laravel setup..."
sleep 15
docker exec api-php composer install --no-dev --optimize-autoloader --no-interaction
docker exec api-php php artisan migrate --force
docker exec api-php php artisan storage:link || true
docker exec api-php php artisan config:cache
docker exec api-php php artisan route:cache
docker exec api-php php artisan view:cache

# Etape 6: Permissions
echo ""
echo "6/6 - Setting permissions..."
docker exec api-php chmod -R 775 storage bootstrap/cache
docker exec api-php chown -R www-data:www-data storage bootstrap/cache

echo ""
echo "=========================================="
echo "  DEPLOIEMENT TERMINE !"
echo "=========================================="
echo ""
"${DC[@]}" ps
echo ""
echo "Test: curl http://localhost:8080/api/prestations"
