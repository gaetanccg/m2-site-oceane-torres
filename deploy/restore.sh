#!/usr/bin/env bash
set -euo pipefail

# Restauration d'un dump produit par backup.sh vers une base cible.
#
# Operation destructive : le schema `public` de la cible est supprime avant d'etre
# recharge. Elle exige donc CONFIRM=<ref-projet> pour que l'intention soit explicite,
# et refuse par defaut tout ce qui n'est pas une reprise (voir --allow-nonempty).

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

ENV_FILE="${RESTORE_ENV_FILE:-${SCRIPT_DIR}/.env.prod}"
BACKUP_DIR="${BACKUP_DIR:-${REPO_ROOT}/backups}"
POSTGRES_IMAGE="${POSTGRES_IMAGE:-postgres:17-alpine}"

# pg_restore exige une session stable : le pooler transaction (6543) ne convient pas.
PGPORT_RESTORE="${PGPORT_RESTORE:-5432}"
RESTORE_SCHEMA="${RESTORE_SCHEMA:-public}"

WITNESS_TABLES=(users clients galleries photos orders order_items invoices payments privacy_audit_logs)

red()  { printf '\033[31m%b\033[0m\n' "$*"; }
grn()  { printf '\033[32m%b\033[0m\n' "$*"; }
ylw()  { printf '\033[33m%b\033[0m\n' "$*"; }
die()  { red "✖ $*" >&2; exit 1; }
step() { printf '\n\033[1m── %s\033[0m\n' "$*"; }

usage() {
    cat <<'EOF'
Usage : ./deploy/restore.sh --dump <fichier> [options]

  --dump <fichier>     dump a restaurer (obligatoire)
  --allow-nonempty     autorise la restauration sur une cible qui contient des donnees
  --dry-run            restaure dans un conteneur jetable, ne touche pas la cible

Variables :
  CONFIRM=<ref>        obligatoire hors --dry-run : doit egaler la ref du projet cible
  RESTORE_ENV_FILE     fichier d'environnement de la cible (defaut deploy/.env.prod)
  PGPORT_RESTORE       port de la cible (defaut 5432 — pas le pooler 6543)
EOF
}

DUMP=""
ALLOW_NONEMPTY=0
DRY_RUN=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dump)           DUMP="${2:-}"; shift 2 ;;
        --allow-nonempty) ALLOW_NONEMPTY=1; shift ;;
        --dry-run)        DRY_RUN=1; shift ;;
        -h|--help)        usage; exit 0 ;;
        *)                usage; die "Option inconnue : $1" ;;
    esac
done

[[ -n "${DUMP}" ]] || { usage; die "--dump est obligatoire."; }
[[ -f "${DUMP}" ]] || die "Dump introuvable : ${DUMP}"

COUNTS_FILE="${DUMP%.dump}.counts"

# ─────────────────────────────────────────────────────────────────────────────
# Repetition a blanc : meme sequence, sur un Postgres jetable et sans reseau.
# ─────────────────────────────────────────────────────────────────────────────
if [[ "${DRY_RUN}" -eq 1 ]]; then
    step "Répétition à blanc"
    ylw "  dump : $(basename "${DUMP}")"

    recorded="$(grep -m1 '^# server_major=' "${COUNTS_FILE}" 2>/dev/null | cut -d= -f2 || true)"
    if [[ -n "${recorded}" ]]; then
        POSTGRES_IMAGE="postgres:${recorded}-alpine"
        ylw "  image : ${POSTGRES_IMAGE} (version du dump)"
    fi

    container="ot-restore-rehearsal-$$"
    docker run -d --rm --name "${container}" \
        --network none \
        --tmpfs /var/lib/postgresql/data \
        -e POSTGRES_PASSWORD=postgres \
        -e POSTGRES_DB=rehearsal \
        "${POSTGRES_IMAGE}" >/dev/null
    trap "docker rm -f '${container}' >/dev/null 2>&1 || true" EXIT

    printf '  démarrage'
    for _ in $(seq 1 40); do
        if docker exec "${container}" pg_isready -U postgres -d rehearsal >/dev/null 2>&1; then
            break
        fi
        printf '.'; sleep 1
    done
    echo
    docker exec "${container}" pg_isready -U postgres -d rehearsal >/dev/null 2>&1 \
        || die "Le Postgres de répétition n'a pas démarré."

    # On reproduit l'etat reel de la cible : un schema `public` existant mais vide,
    # tel que le laisse un migrate:fresh. C'est ce que la sequence doit savoir ecraser.
    docker exec -i "${container}" psql -U postgres -d rehearsal -q \
        -c 'CREATE TABLE public.vestige_migrate_fresh (id int);' >/dev/null

    docker exec -i "${container}" psql -U postgres -d rehearsal -q \
        -c "DROP SCHEMA IF EXISTS ${RESTORE_SCHEMA} CASCADE; CREATE SCHEMA ${RESTORE_SCHEMA};" >/dev/null

    docker exec -i "${container}" pg_restore \
        -U postgres -d rehearsal --schema="${RESTORE_SCHEMA}" --no-owner --no-privileges \
        < "${DUMP}" >/dev/null 2>&1 || true

    step "Comptages après répétition"
    rc=0
    for t in "${WITNESS_TABLES[@]}"; do
        got="$(docker exec "${container}" psql -U postgres -d rehearsal -At \
            -c "SELECT count(*) FROM ${RESTORE_SCHEMA}.${t};" 2>/dev/null || echo '-')"
        want="$(grep -m1 "^${t}=" "${COUNTS_FILE}" 2>/dev/null | cut -d= -f2 || echo '?')"
        if [[ "${got}" == "${want}" ]]; then
            printf '  \033[32m✓\033[0m %-20s %s\n' "${t}" "${got}"
        else
            printf '  \033[31m✗\033[0m %-20s attendu=%s obtenu=%s\n' "${t}" "${want}" "${got}"
            rc=1
        fi
    done

    left="$(docker exec "${container}" psql -U postgres -d rehearsal -At \
        -c "SELECT count(*) FROM information_schema.tables WHERE table_schema='${RESTORE_SCHEMA}' AND table_name='vestige_migrate_fresh';" 2>/dev/null || echo '?')"
    if [[ "${left}" == "0" ]]; then
        printf '  \033[32m✓\033[0m %-20s %s\n' "table vestige" "supprimée"
    else
        printf '  \033[31m✗\033[0m %-20s %s\n' "table vestige" "toujours présente"
        rc=1
    fi

    echo
    [[ "${rc}" -eq 0 ]] && grn "✅ Répétition concluante — la séquence est prête." \
                        || die "Répétition en échec : ne pas lancer sur la cible."
    exit 0
fi

# ─────────────────────────────────────────────────────────────────────────────
# Restauration reelle
# ─────────────────────────────────────────────────────────────────────────────
[[ -f "${ENV_FILE}" ]] || die "Fichier d'environnement introuvable : ${ENV_FILE}"
set -a; . "${ENV_FILE}"; set +a

: "${DB_HOST:?DB_HOST manquant}"
: "${DB_DATABASE:?DB_DATABASE manquant}"
: "${DB_USERNAME:?DB_USERNAME manquant}"
: "${DB_PASSWORD:?DB_PASSWORD manquant}"

# La ref du projet Supabase est le suffixe du nom d'utilisateur : postgres.<ref>
PROJECT_REF="${DB_USERNAME##*.}"

step "Cible"
ylw "  hôte     : ${DB_HOST}:${PGPORT_RESTORE}"
ylw "  base     : ${DB_DATABASE}"
ylw "  projet   : ${PROJECT_REF}"
ylw "  dump     : $(basename "${DUMP}")"

if [[ "${CONFIRM:-}" != "${PROJECT_REF}" ]]; then
    die "Confirmation requise. Relancer avec :\n    CONFIRM=${PROJECT_REF} $0 --dump ${DUMP}"
fi

psql_target() {
    docker run --rm -i -e PGPASSWORD="${DB_PASSWORD}" "${POSTGRES_IMAGE}" \
        psql -h "${DB_HOST}" -p "${PGPORT_RESTORE}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" "$@"
}

step "État actuel de la cible"
existing=0
for t in "${WITNESS_TABLES[@]}"; do
    n="$(psql_target -At -c "SELECT count(*) FROM ${RESTORE_SCHEMA}.${t};" 2>/dev/null || echo 0)"
    [[ "${n}" =~ ^[0-9]+$ ]] || n=0
    printf '  %-20s %s\n' "${t}" "${n}"
    existing=$(( existing + n ))
done

if [[ "${existing}" -gt 0 && "${ALLOW_NONEMPTY}" -eq 0 ]]; then
    die "La cible contient ${existing} ligne(s) sur les tables témoins.\n   Restaurer l'écraserait. Relancer avec --allow-nonempty si c'est voulu."
fi

step "Restauration"
ylw "  suppression puis recréation du schéma ${RESTORE_SCHEMA}"
psql_target -q -c "DROP SCHEMA IF EXISTS ${RESTORE_SCHEMA} CASCADE; CREATE SCHEMA ${RESTORE_SCHEMA};"

ylw "  chargement du dump"
docker run --rm -i -e PGPASSWORD="${DB_PASSWORD}" "${POSTGRES_IMAGE}" \
    pg_restore -h "${DB_HOST}" -p "${PGPORT_RESTORE}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" \
    --schema="${RESTORE_SCHEMA}" --no-owner --no-privileges \
    < "${DUMP}" || ylw "  pg_restore a signalé des avertissements (vérification ci-dessous)"

step "Vérification"
rc=0
for t in "${WITNESS_TABLES[@]}"; do
    got="$(psql_target -At -c "SELECT count(*) FROM ${RESTORE_SCHEMA}.${t};" 2>/dev/null || echo '-')"
    want="$(grep -m1 "^${t}=" "${COUNTS_FILE}" 2>/dev/null | cut -d= -f2 || echo '?')"
    if [[ "${got}" == "${want}" ]]; then
        printf '  \033[32m✓\033[0m %-20s %s\n' "${t}" "${got}"
    else
        printf '  \033[31m✗\033[0m %-20s attendu=%s obtenu=%s\n' "${t}" "${want}" "${got}"
        rc=1
    fi
done

echo
[[ "${rc}" -eq 0 ]] && grn "✅ Restauration vérifiée sur ${#WITNESS_TABLES[@]} tables témoins." \
                    || die "Des comptages divergent — ne pas remettre le service en ligne."
