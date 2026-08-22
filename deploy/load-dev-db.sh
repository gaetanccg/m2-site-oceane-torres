#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

BACKUP_DIR="${BACKUP_DIR:-${REPO_ROOT}/backups}"
POSTGRES_IMAGE="${POSTGRES_IMAGE:-postgres:17-alpine}"
DEV_CONTAINER="${DEV_CONTAINER:-ot-postgres-dev}"
DEV_DB="${DEV_DB:-oceane_dev}"
DEV_USER="${DEV_USER:-postgres}"
WORK="ot-anon-work-$$"

red() { printf '\033[31m%b\033[0m\n' "$*"; }
grn() { printf '\033[32m%b\033[0m\n' "$*"; }
ylw() { printf '\033[33m%b\033[0m\n' "$*"; }
die() { red "✖ $*" >&2; exit 1; }
step() { printf '\n\033[1m── %s\033[0m\n' "$*"; }

usage() {
    cat <<'EOF'
Charge un dump de production ANONYMISÉ dans la base de développement locale.

  ./deploy/load-dev-db.sh [chemin/vers/dump]

Sans argument, prend le dump le plus récent de ./backups (hors dumps déjà anonymisés).

Chaîne : restauration dans un conteneur jetable → anonymisation → nouveau dump
anonymisé → chargement dans postgres-dev. La base de développement ne voit donc
jamais les données réelles, et le dump anonymisé reste réutilisable.

⚠️ Écrase intégralement la base de développement.
EOF
}

case "${1:-}" in -h|--help|help) usage; exit 0 ;; esac

command -v docker >/dev/null 2>&1 || die "docker est requis."
docker info >/dev/null 2>&1 || die "Le démon Docker ne répond pas."
[[ -f "${SCRIPT_DIR}/anonymize.sql" ]] || die "deploy/anonymize.sql introuvable."

SOURCE_DUMP="${1:-}"
if [[ -z "${SOURCE_DUMP}" ]]; then
    SOURCE_DUMP="$(ls -t "${BACKUP_DIR}"/oceane-*.dump 2>/dev/null | grep -v -- '-anon-' | head -1 || true)"
fi
[[ -n "${SOURCE_DUMP}" && -f "${SOURCE_DUMP}" ]] \
    || die "Aucun dump source. Lancer d'abord : ./deploy/backup.sh db"

docker inspect "${DEV_CONTAINER}" >/dev/null 2>&1 \
    || die "Le conteneur ${DEV_CONTAINER} n'existe pas. Lancer : docker compose up -d postgres-dev"
[[ "$(docker inspect -f '{{.State.Running}}' "${DEV_CONTAINER}")" == "true" ]] \
    || die "Le conteneur ${DEV_CONTAINER} est arrêté."

ylw "Source : $(basename "${SOURCE_DUMP}")"
ylw "Cible  : ${DEV_CONTAINER}/${DEV_DB}  (⚠️ sera écrasée)"

cleanup() { docker rm -f "${WORK}" >/dev/null 2>&1 || true; }
trap cleanup EXIT

# ── 1. Copie de travail jetable, sans réseau ────────────────────────────────
step "Restauration dans une copie de travail"
docker run -d --rm --name "${WORK}" --network none \
    --tmpfs /var/lib/postgresql/data \
    -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=work \
    "${POSTGRES_IMAGE}" >/dev/null

printf '  démarrage'
for _ in $(seq 1 40); do
    docker exec "${WORK}" pg_isready -U postgres -d work >/dev/null 2>&1 && break
    printf '.'; sleep 1
done
echo
docker exec "${WORK}" pg_isready -U postgres -d work >/dev/null 2>&1 \
    || die "La copie de travail n'a pas démarré."

docker cp "${SOURCE_DUMP}" "${WORK}:/tmp/src.dump" >/dev/null
docker exec -e PGPASSWORD=postgres "${WORK}" \
    pg_restore --dbname=work --no-owner --no-privileges --username=postgres \
               --schema=public --exit-on-error /tmp/src.dump \
    || die "La restauration de la copie de travail a échoué."
grn "  restaurée"

# ── 2. Anonymisation ───────────────────────────────────────────────────────
step "Anonymisation"
docker cp "${SCRIPT_DIR}/anonymize.sql" "${WORK}:/tmp/anon.sql" >/dev/null
docker exec -e PGPASSWORD=postgres "${WORK}" \
    psql -U postgres -d work -X -q -v ON_ERROR_STOP=1 -f /tmp/anon.sql \
    || die "L'anonymisation a échoué — la base de développement n'a pas été touchée."

leaks="$(docker exec "${WORK}" psql -U postgres -d work -XAt -c "
    SELECT count(*) FROM (
      SELECT email v FROM users UNION ALL SELECT email FROM clients
      UNION ALL SELECT guest_email FROM orders UNION ALL SELECT guest_email FROM carts
      UNION ALL SELECT assigned_email FROM galleries UNION ALL SELECT email FROM contact_messages
    ) x WHERE v IS NOT NULL AND btrim(v) <> '' AND v NOT LIKE '%@example.test';" | tr -d '\r')"

[[ "${leaks}" == "0" ]] || die "${leaks} adresse(s) réelle(s) subsistent — chargement annulé."
grn "  anonymisée · 0 adresse réelle restante"

# ── 3. Dump anonymisé, réutilisable ────────────────────────────────────────
step "Dump anonymisé"
ANON_DUMP="${BACKUP_DIR}/oceane-anon-$(date +%F).dump"
docker exec -e PGPASSWORD=postgres "${WORK}" \
    pg_dump -U postgres -d work --format=custom --no-owner --no-privileges \
            --schema=public --file=/tmp/anon.dump \
    || die "Le dump anonymisé a échoué."
docker cp "${WORK}:/tmp/anon.dump" "${ANON_DUMP}" >/dev/null
grn "  ${ANON_DUMP} ($(du -h "${ANON_DUMP}" | cut -f1))"

# ── 4. Chargement dans la base de développement ────────────────────────────
step "Chargement dans ${DEV_DB}"
docker exec -e PGPASSWORD=postgres "${DEV_CONTAINER}" \
    psql -U "${DEV_USER}" -d postgres -X -q \
    -c "DROP DATABASE IF EXISTS ${DEV_DB} WITH (FORCE);" \
    -c "CREATE DATABASE ${DEV_DB};" \
    || die "Impossible de recréer ${DEV_DB}."

# Le dump porte un CREATE SCHEMA public, qu'une base neuve possede deja.
docker exec -e PGPASSWORD=postgres "${DEV_CONTAINER}" \
    psql -U "${DEV_USER}" -d "${DEV_DB}" -X -q -c "DROP SCHEMA IF EXISTS public CASCADE;" \
    || die "Impossible de préparer le schéma de ${DEV_DB}."

docker cp "${ANON_DUMP}" "${DEV_CONTAINER}:/tmp/load.dump" >/dev/null
docker exec -e PGPASSWORD=postgres "${DEV_CONTAINER}" \
    pg_restore --dbname="${DEV_DB}" --no-owner --no-privileges \
               --username="${DEV_USER}" --exit-on-error /tmp/load.dump \
    || die "Le chargement a échoué."

step "Contrôle"
docker exec "${DEV_CONTAINER}" psql -U "${DEV_USER}" -d "${DEV_DB}" -X -c "
SELECT
  (SELECT count(*) FROM information_schema.tables WHERE table_schema='public') AS tables,
  (SELECT count(*) FROM users)     AS users,
  (SELECT count(*) FROM galleries) AS galeries,
  (SELECT count(*) FROM photos)    AS photos,
  (SELECT count(*) FROM orders)    AS commandes,
  (SELECT count(*) FROM invoices)  AS factures;"

echo
grn "✅ ${DEV_DB} chargée avec des données anonymisées."
ylw "   Connexion applicative : 127.0.0.1:55433/${DEV_DB}"
ylw "   Tous les comptes ont le mot de passe : MotDePasseDev2026!"
