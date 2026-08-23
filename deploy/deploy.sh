#!/bin/bash
# ===========================================
# DEPLOIEMENT PRODUCTION - NAS UGREEN
# ===========================================
# Usage: ./deploy/deploy.sh [--no-pull] [--no-build] [--fresh] [--yes]
#        ./deploy/deploy.sh --rollback <tag>   |   --list
#
# L'image est l'artefact : le code y est copie, pas monte. Un retour arriere est donc un
# changement de tag. Seul ./api/storage reste monte depuis l'hote.
#
# Prerequis: le repo est clone sur le NAS, deploy/.env.prod existe.
# ===========================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$REPO_ROOT"

COMPOSE_FILE="deploy/docker-compose.prod.yml"
IMAGE_NAME="oceane-api"

# --project-directory a la RACINE : compose resout ./api et ./deploy/ par rapport au
# dossier du fichier compose, pas du projet.
DC=(docker compose --project-directory "$REPO_ROOT" -f "$COMPOSE_FILE")

red() { printf '\033[31m%s\033[0m\n' "$*"; }
grn() { printf '\033[32m%s\033[0m\n' "$*"; }
ylw() { printf '\033[33m%s\033[0m\n' "$*"; }
die() { red "ERREUR: $*" >&2; exit 1; }

if [[ "${1:-}" == "--list" ]]; then
    echo "Images disponibles :"
    docker images "${IMAGE_NAME}" --format '  {{.Tag}}\t{{.CreatedSince}}\t{{.Size}}'
    echo
    echo "Actuellement deployee :"
    docker inspect api-php --format '  {{.Config.Image}}' 2>/dev/null || echo "  (aucun conteneur api-php)"
    exit 0
fi

if [[ "${1:-}" == "--rollback" ]]; then
    TAG="${2:-}"
    [[ -n "$TAG" ]] || die "Usage: $0 --rollback <tag>   (voir $0 --list)"
    docker image inspect "${IMAGE_NAME}:${TAG}" >/dev/null 2>&1 \
        || die "L'image ${IMAGE_NAME}:${TAG} n'existe pas localement. Voir: $0 --list"

    ylw "Retour arriere vers ${IMAGE_NAME}:${TAG}"
    ylw "La base de donnees n'est PAS touchee. Si le tag precede une migration"
    ylw "deja appliquee, le code deploye devra la supporter."
    API_IMAGE_TAG="$TAG" "${DC[@]}" up -d --no-build
    grn "Retour arriere effectue."
    "${DC[@]}" ps
    exit 0
fi

[[ -f "deploy/.env.prod" ]] || die "deploy/.env.prod introuvable.

    Ce script se lance depuis le NAS, ou ce fichier existe.

    Sur un poste de developpement il est ABSENT volontairement : les identifiants de
    production ont ete sortis de l'arborescence du projet, pour qu'aucune commande lancee
    ici ne puisse atteindre les donnees reelles (cf. docs/isolation-production.md).
    Deployer depuis un poste de dev n'est pas prevu.

    Sur le NAS, si le fichier manque vraiment :
      cp deploy/.env.prod.example deploy/.env.prod  puis remplir les secrets."

COMMIT="$(git rev-parse --short HEAD 2>/dev/null || echo 'nogit')"
export API_IMAGE_TAG="$COMMIT"

echo "=========================================="
echo "  DEPLOIEMENT OCEANE API"
echo "  Repo : $REPO_ROOT"
echo "  Tag  : ${IMAGE_NAME}:${API_IMAGE_TAG}"
echo "=========================================="

# ── 1. Code
if [[ "$*" != *"--no-pull"* ]]; then
    echo; echo "1/6 - Recuperation du code..."
    git pull origin main
    COMMIT="$(git rev-parse --short HEAD)"
    export API_IMAGE_TAG="$COMMIT"
    ylw "  tag apres pull : ${IMAGE_NAME}:${API_IMAGE_TAG}"
else
    echo; echo "1/6 - Recuperation du code ignoree (--no-pull)"
fi

# ── 2. Construction de l'image
if [[ "$*" != *"--no-build"* ]]; then
    echo; echo "2/6 - Construction de l'image..."
    if [[ "$*" == *"--fresh"* ]]; then
        "${DC[@]}" build --no-cache
    else
        "${DC[@]}" build
    fi
    docker tag "${IMAGE_NAME}:${API_IMAGE_TAG}" "${IMAGE_NAME}:latest"
else
    echo; echo "2/6 - Construction ignoree (--no-build)"
    docker image inspect "${IMAGE_NAME}:${API_IMAGE_TAG}" >/dev/null 2>&1 \
        || die "Aucune image ${IMAGE_NAME}:${API_IMAGE_TAG}. Relancer sans --no-build."
fi

# ── 3. Demarrage
echo; echo "3/6 - Demarrage des conteneurs..."
"${DC[@]}" up -d --remove-orphans

# ── 4. Attente de disponibilite
echo; echo "4/6 - Attente de php-fpm..."
for _ in $(seq 1 30); do
    if docker exec api-php php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);' 2>/dev/null; then
        break
    fi
    printf '.'; sleep 2
done
echo
docker exec api-php php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);' 2>/dev/null \
    || die "php-fpm n'a pas demarre. Voir: ${DC[*]} logs laravel"

# ── 5. Migrations
echo; echo "5/6 - Migrations"
PENDING="$(docker exec api-php php artisan migrate:status 2>/dev/null | grep -c 'Pending' || true)"

if [[ "${PENDING:-0}" -eq 0 ]]; then
    grn "  Aucune migration en attente."
else
    ylw "  ${PENDING} migration(s) en attente :"
    docker exec api-php php artisan migrate:status 2>/dev/null | grep 'Pending' | sed 's/^/    /'
    echo
    if [[ "$*" == *"--yes"* ]]; then
        ylw "  --yes : application sans confirmation."
    else
        red  "  Une migration est irreversible en pratique. Une sauvegarde recente est-elle disponible ?"
        ylw  "  (./deploy/backup.sh db)"
        read -r -p "  Appliquer ces migrations ? [oui/NON] " reponse
        [[ "$reponse" == "oui" ]] || die "Migrations refusees. Les conteneurs tournent sur l'ancien schema."
    fi
    docker exec api-php php artisan migrate --force
fi

# ── 6. Caches applicatifs
echo; echo "6/6 - Caches applicatifs..."
docker exec api-php php artisan storage:link 2>/dev/null || true
docker exec api-php php artisan config:cache
docker exec api-php php artisan route:cache
docker exec api-php php artisan view:cache

echo
grn "=========================================="
grn "  DEPLOIEMENT TERMINE"
grn "  Image : ${IMAGE_NAME}:${API_IMAGE_TAG}"
grn "  Retour arriere : $0 --rollback <tag>   ($0 --list)"
grn "=========================================="
echo
"${DC[@]}" ps
echo
echo "Test: curl http://localhost:8080/api/prestations"
