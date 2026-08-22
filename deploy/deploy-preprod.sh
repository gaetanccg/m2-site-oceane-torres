#!/bin/bash
# ===========================================
# SCRIPT DE DEPLOIEMENT PREPROD - NAS UGREEN
# ===========================================
# Usage: ./deploy/deploy-preprod.sh [--no-pull] [--no-build] [--fresh]
#        ./deploy/deploy-preprod.sh --rollback <tag>
#        ./deploy/deploy-preprod.sh --list
#
# L'IMAGE EST L'ARTEFACT. Le code n'est plus monte depuis l'hote : il est copie dans
# l'image a la construction, avec ses dependances. Deux consequences :
#   - ce qui tourne est exactement ce qui a ete construit, pas l'etat du repo ;
#   - un retour arriere est un changement de tag, pas un git checkout.
#
# Seul ./api/storage reste monte depuis l'hote : il porte les exports generes et les
# journaux, qui doivent survivre a un redeploiement.
#
# Les migrations ne tournent plus au demarrage des conteneurs. Elles sont une etape
# explicite de ce script, apres confirmation : un `migrate --force` dans le `command:`
# s'executait sans surveillance a chaque redemarrage, et les trois services partageant
# l'image le lançaient en parallele.
#
# --fresh: rebuild sans cache Docker. A reserver aux cas ou une couche systeme doit
#   repartir de zero, ou a un cache corrompu. Pour recuperer aussi une base
#   php:8.4-fpm-alpine plus recente, il faut un `docker compose ... build --pull`.
#
# Prerequis:
#   1. Le repo est clone sur le NAS
#   2. deploy/.env.preprod existe (copie depuis .env.preprod.example)
# ===========================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$REPO_ROOT"

COMPOSE_FILE="deploy/docker-compose.preprod.yml"
IMAGE_NAME="oceane-api-preprod"

# Compose V2 resout les chemins relatifs par rapport au dossier du fichier compose.
# On force --project-directory a la RACINE pour que ./api et ./deploy/... pointent juste.
DC=(docker compose --project-directory "$REPO_ROOT" -f "$COMPOSE_FILE")

red() { printf '\033[31m%s\033[0m\n' "$*"; }
grn() { printf '\033[32m%s\033[0m\n' "$*"; }
ylw() { printf '\033[33m%s\033[0m\n' "$*"; }
die() { red "ERREUR: $*" >&2; exit 1; }

# ─────────────────────────────────────────────────────────────────────────────
# --list : quels tags sont disponibles pour un retour arriere
# ─────────────────────────────────────────────────────────────────────────────
if [[ "${1:-}" == "--list" ]]; then
    echo "Images disponibles :"
    docker images "${IMAGE_NAME}" --format '  {{.Tag}}\t{{.CreatedSince}}\t{{.Size}}'
    echo
    echo "Actuellement deployee :"
    docker inspect api-php-preprod --format '  {{.Config.Image}}' 2>/dev/null || echo "  (aucun conteneur api-php-preprod)"
    exit 0
fi

# ─────────────────────────────────────────────────────────────────────────────
# --rollback <tag> : redemarre sur une image deja construite. Ne touche PAS la base :
# annuler une migration n'est pas un changement de tag, c'est une decision a part.
# ─────────────────────────────────────────────────────────────────────────────
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

[[ -f "deploy/.env.preprod" ]] || die "deploy/.env.preprod introuvable.
    Copier le template: cp deploy/.env.preprod.example deploy/.env.preprod, puis remplir les secrets."

# Le tag identifie ce qui tourne. Le SHA court du commit est reproductible et permet de
# remonter au code exact ; `latest` suit toujours le dernier deploiement.
COMMIT="$(git rev-parse --short HEAD 2>/dev/null || echo 'nogit')"
export API_IMAGE_TAG="$COMMIT"

echo "=========================================="
echo "  DEPLOIEMENT OCEANE API - PREPROD"
echo "  Repo : $REPO_ROOT"
echo "  Tag  : ${IMAGE_NAME}:${API_IMAGE_TAG}"
echo "=========================================="

# ── 1. Code
if [[ "$*" != *"--no-pull"* ]]; then
    echo; echo "1/6 - Recuperation du code..."
    git pull
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
    # `latest` pointe sur ce qui vient d'etre construit, pour que le compose sans tag
    # explicite demarre la meme chose.
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
    if docker exec api-php-preprod php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);' 2>/dev/null; then
        break
    fi
    printf '.'; sleep 2
done
echo
docker exec api-php-preprod php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);' 2>/dev/null \
    || die "php-fpm n'a pas demarre. Voir: ${DC[*]} logs laravel"

# ── 5. Migrations : etape explicite, jamais au demarrage d'un conteneur
echo; echo "5/6 - Migrations"
PENDING="$(docker exec api-php-preprod php artisan migrate:status 2>/dev/null | grep -c 'Pending' || true)"

if [[ "${PENDING:-0}" -eq 0 ]]; then
    grn "  Aucune migration en attente."
else
    ylw "  ${PENDING} migration(s) en attente :"
    docker exec api-php-preprod php artisan migrate:status 2>/dev/null | grep 'Pending' | sed 's/^/    /'
    echo
    # Pas de confirmation en preprod : c'est l'environnement fait pour casser, et c'est
    # ici qu'une migration doit etre eprouvee avant d'atteindre la production.
    ylw "  Application (preprod, sans confirmation)."
    docker exec api-php-preprod php artisan migrate --force
fi

# ── 6. Caches applicatifs, une fois les variables reelles presentes
echo; echo "6/6 - Caches applicatifs..."
docker exec api-php-preprod php artisan storage:link 2>/dev/null || true
docker exec api-php-preprod php artisan config:cache
docker exec api-php-preprod php artisan route:cache
docker exec api-php-preprod php artisan view:cache

echo
grn "=========================================="
grn "  DEPLOIEMENT TERMINE"
grn "  Image : ${IMAGE_NAME}:${API_IMAGE_TAG}"
grn "  Retour arriere : $0 --rollback <tag>   ($0 --list)"
grn "=========================================="
echo
"${DC[@]}" ps
echo
echo "Test: curl http://localhost:8081/api/prestations"
