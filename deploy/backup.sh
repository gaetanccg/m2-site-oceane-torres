#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Cible par DEFAUT : la preproduction. Viser la production exige de la nommer.
# Une faute de frappe ou un oubli d'argument ne doit jamais atteindre les donnees reelles.
ENV_FILE="${BACKUP_ENV_FILE:-${SCRIPT_DIR}/.env.preprod}"
BACKUP_DIR="${BACKUP_DIR:-${REPO_ROOT}/backups}"
POSTGRES_IMAGE_PINNED="${POSTGRES_IMAGE:-}"
POSTGRES_IMAGE="${POSTGRES_IMAGE:-postgres:17-alpine}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

# pg_dump exige une session stable : le pooler transaction (6543) ne convient pas.
PGDUMP_PORT="${PGDUMP_PORT:-5432}"

# Seul `public` porte les donnees de l'application. Sans ce filtre, le dump embarque
# les schemas internes de Supabase et leurs extensions (supabase_vault, pg_graphql…),
# qui rendent la restauration impossible ailleurs que sur Supabase.
DUMP_SCHEMA="${DUMP_SCHEMA:-public}"

WITNESS_TABLES=(users clients galleries photos orders order_items invoices payments privacy_audit_logs)

red()  { printf '\033[31m%b\033[0m\n' "$*"; }
grn()  { printf '\033[32m%b\033[0m\n' "$*"; }
ylw()  { printf '\033[33m%b\033[0m\n' "$*"; }
die()  { red "✖ $*" >&2; exit 1; }
step() { printf '\n\033[1m── %s\033[0m\n' "$*"; }

usage() {
    cat <<'EOF'
Sauvegarde base PostgreSQL + stockage objet MinIO.

  ./deploy/backup.sh db        dump + comptages de référence
  ./deploy/backup.sh verify    restaure le dernier dump et compare les comptages
  ./deploy/backup.sh storage   miroir du bucket MinIO
  ./deploy/backup.sh all       les trois, dans l'ordre

Cible : deploy/.env.preprod par défaut. Pour la PRODUCTION, la nommer :
  BACKUP_ENV_FILE=deploy/.env.prod ./deploy/backup.sh all

Sur le poste de développement, deploy/.env.prod n'existe pas : les identifiants de
production sont hors de l'arborescence du projet (voir docs/isolation-production.md).

Surcharges : BACKUP_DIR, PGDUMP_PORT, RETENTION_DAYS, DUMP_SCHEMA
POSTGRES_IMAGE s'aligne seul sur la version du serveur. Le définir l'épingle et
désactive cet alignement.
EOF
}

load_env() {
    if [[ -f "${ENV_FILE}" ]]; then
        while IFS='=' read -r key value; do
            [[ "${key}" =~ ^[A-Z_][A-Z0-9_]*$ ]] || continue
            if [[ -n "${!key:-}" ]]; then continue; fi
            value="${value%\"}"; value="${value#\"}"
            export "${key}=${value}"
        done < <(grep -E '^[A-Z_]+=' "${ENV_FILE}" || true)
    fi

    for var in DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
        [[ -n "${!var:-}" ]] || die "${var} manquant (ni environnement, ni ${ENV_FILE})"
        if [[ "${!var}" == *"<SECRET"* ]]; then
            die "${var} contient encore un placeholder de template."
        fi
    done
}

pg_in_docker() {
    local bin="$1"; shift
    docker run --rm -i \
        -e "PGPASSWORD=${DB_PASSWORD}" \
        -v "${BACKUP_DIR}:/backup" \
        "${POSTGRES_IMAGE}" \
        "${bin}" "$@"
}

target_label() {
    printf '%s' "${DB_USERNAME##*.}"
}

cmd_db() {
    step "Sauvegarde de la base"
    mkdir -p "${BACKUP_DIR}"
    ylw "  cible : $(target_label) @ ${DB_HOST}:${PGDUMP_PORT} · schéma ${DUMP_SCHEMA}  (source : $(basename "${ENV_FILE}"))"

    local server_version
    server_version="$(pg_in_docker psql \
        -h "${DB_HOST}" -p "${PGDUMP_PORT}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" \
        -XAt -c 'SHOW server_version;' 2>/dev/null | tr -d '\r')" \
        || die "Connexion impossible sur ${DB_HOST}:${PGDUMP_PORT}.
   Le port 6543 (pooler transaction) ne convient pas à pg_dump — utiliser 5432.
   Vérifier aussi que l'IP du poste est autorisée côté Supabase."

    grn "  serveur PostgreSQL : ${server_version}"
    local server_major="${server_version%%.*}"
    local client_major="${POSTGRES_IMAGE##*:}"; client_major="${client_major%%-*}"

    # pg_dump refuse de tourner contre un serveur plus recent que lui.
    if (( server_major > client_major )); then
        if [[ -n "${POSTGRES_IMAGE_PINNED}" ]]; then
            die "pg_dump ${client_major} est antérieur au serveur ${server_major} : il refusera de tourner.
   POSTGRES_IMAGE est épinglé à « ${POSTGRES_IMAGE_PINNED} » — relancer avec postgres:${server_major}-alpine."
        fi
        POSTGRES_IMAGE="postgres:${server_major}-alpine"
        ylw "  client aligné sur le serveur : ${POSTGRES_IMAGE}"
    fi

    local stamp outfile
    stamp="$(date +%F)"
    outfile="oceane-$(target_label)-${stamp}.dump"

    ylw "  dump → ${BACKUP_DIR}/${outfile}"
    pg_in_docker pg_dump \
        -h "${DB_HOST}" -p "${PGDUMP_PORT}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" \
        --format=custom --no-owner --no-privileges \
        --schema="${DUMP_SCHEMA}" \
        --file="/backup/${outfile}" \
        || die "pg_dump a échoué — supprimer le fichier partiel, ce n'est pas une sauvegarde."

    grn "  dump écrit ($(du -h "${BACKUP_DIR}/${outfile}" | cut -f1))"

    local ntables
    ntables="$(pg_in_docker pg_restore --list "/backup/${outfile}" | grep -c 'TABLE DATA' || true)"
    (( ntables > 0 )) || die "Le dump ne contient aucune donnée de table."
    grn "  table des matières lisible — ${ntables} tables avec données"

    step "Comptages de référence"
    local counts_file="${BACKUP_DIR}/oceane-$(target_label)-${stamp}.counts"
    printf '# server_major=%s\n' "${server_major}" > "${counts_file}"
    for t in "${WITNESS_TABLES[@]}"; do
        local n
        n="$(pg_in_docker psql -h "${DB_HOST}" -p "${PGDUMP_PORT}" \
              -U "${DB_USERNAME}" -d "${DB_DATABASE}" \
              -XAt -c "SELECT count(*) FROM ${t};" 2>/dev/null | tr -d '\r' || echo '?')"
        printf '%s=%s\n' "${t}" "${n}" >> "${counts_file}"
        printf '  %-20s %s\n' "${t}" "${n}"
    done

    ylw "\n  Rétention : dumps de plus de ${RETENTION_DAYS} jours"
    find "${BACKUP_DIR}" -name 'oceane-*.dump' -type f -mtime "+${RETENTION_DAYS}" -print -delete || true
}

cmd_verify() {
    step "Restauration de contrôle"

    # Un argument explicite prime : `ls -t` prend le plus recent, qui peut etre une
    # copie anonymisee et non le dump de reference.
    local dump="${VERIFY_DUMP:-}"
    if [[ -z "${dump}" ]]; then
        dump="$(ls -t "${BACKUP_DIR}"/oceane-*.dump 2>/dev/null | grep -v -- '-anon-' | head -1)" || true
    fi
    [[ -n "${dump}" ]] || die "Aucun dump dans ${BACKUP_DIR}. Lancer d'abord : $0 db"
    [[ -f "${dump}" ]] || die "Dump introuvable : ${dump}"
    ylw "  dump : $(basename "${dump}")"

    # Un dump produit par pg_dump N ne se restaure pas dans un serveur anterieur.
    local recorded
    recorded="$(grep -m1 '^# server_major=' "${dump%.dump}.counts" 2>/dev/null | cut -d= -f2 || true)"
    if [[ -n "${recorded}" && -z "${POSTGRES_IMAGE_PINNED}" ]]; then
        POSTGRES_IMAGE="postgres:${recorded}-alpine"
        ylw "  base de contrôle : ${POSTGRES_IMAGE} (version du dump)"
    fi

    local container="ot-restore-check-$$"
    docker run -d --rm --name "${container}" \
        --network none \
        --tmpfs /var/lib/postgresql/data \
        -e POSTGRES_PASSWORD=postgres \
        -e POSTGRES_DB=oceane_restore_check \
        "${POSTGRES_IMAGE}" >/dev/null
    # shellcheck disable=SC2064
    trap "docker rm -f '${container}' >/dev/null 2>&1 || true" EXIT

    printf '  démarrage de la base de contrôle'
    for _ in $(seq 1 40); do
        if docker exec "${container}" pg_isready -U postgres -d oceane_restore_check >/dev/null 2>&1; then
            break
        fi
        printf '.'; sleep 1
    done
    echo
    docker exec "${container}" pg_isready -U postgres -d oceane_restore_check >/dev/null 2>&1 \
        || die "La base de contrôle n'a pas démarré."

    # Filtre aussi a la restauration : un dump anterieur au filtre cote pg_dump
    # embarque les schemas Supabase, dont des extensions absentes d'un PostgreSQL nu.
    docker cp "${dump}" "${container}:/tmp/restore.dump"
    docker exec -e PGPASSWORD=postgres "${container}" \
        pg_restore --dbname=oceane_restore_check --no-owner --no-privileges \
                   --username=postgres --schema="${DUMP_SCHEMA}" \
                   --exit-on-error /tmp/restore.dump \
        || die "pg_restore a échoué : ce dump n'est pas restaurable."
    grn "  restauration réussie"

    step "Comparaison source ↔ copie restaurée"
    local counts_file="${dump%.dump}.counts"
    local failures=0
    for t in "${WITNESS_TABLES[@]}"; do
        local restored expected
        restored="$(docker exec -e PGPASSWORD=postgres "${container}" \
            psql -U postgres -d oceane_restore_check -XAt -c "SELECT count(*) FROM ${t};" \
            2>/dev/null | tr -d '\r' || echo '?')"
        expected='?'
        if [[ -f "${counts_file}" ]]; then
            expected="$(grep "^${t}=" "${counts_file}" | cut -d= -f2 || echo '?')"
            [[ -n "${expected}" ]] || expected='?'
        fi

        if [[ "${expected}" == "${restored}" && "${restored}" != '?' ]]; then
            printf '  \033[32m✓\033[0m %-20s %s\n' "${t}" "${restored}"
        else
            printf '  \033[31m✗\033[0m %-20s source=%s  restauré=%s\n' "${t}" "${expected}" "${restored}"
            failures=$((failures + 1))
        fi
    done

    (( failures == 0 )) || die "${failures} table(s) divergent — sauvegarde non fiable."
    grn "\n✅ Restauration vérifiée sur ${#WITNESS_TABLES[@]} tables témoins."
}

cmd_storage() {
    step "Miroir du stockage objet"
    command -v mc >/dev/null 2>&1 || die "mc absent : brew install minio/stable/mc"

    for var in MINIO_PUBLIC_URL MINIO_ACCESS_KEY MINIO_SECRET_KEY; do
        [[ -n "${!var:-}" ]] || die "${var} manquant"
        if [[ "${!var}" == *"<SECRET"* ]]; then
            die "${var} contient encore un placeholder."
        fi
    done

    local bucket="${MINIO_BUCKET:-galleries}"
    local dest="${BACKUP_DIR}/minio/${bucket}"
    mkdir -p "${dest}"

    local alias="ot-backup-$$"
    mc alias set "${alias}" "${MINIO_PUBLIC_URL}" "${MINIO_ACCESS_KEY}" "${MINIO_SECRET_KEY}" >/dev/null \
        || die "MinIO injoignable sur ${MINIO_PUBLIC_URL}"
    # shellcheck disable=SC2064
    trap "mc alias remove '${alias}' >/dev/null 2>&1 || true" EXIT

    ylw "  ${bucket} → ${dest}"
    mc mirror --preserve --overwrite "${alias}/${bucket}" "${dest}"

    local src_count dst_count
    src_count="$(mc ls --recursive "${alias}/${bucket}" | wc -l | tr -d ' ')"
    dst_count="$(find "${dest}" -type f ! -name '.DS_Store' | wc -l | tr -d ' ')"
    printf '  objets source : %s\n  objets copiés : %s\n' "${src_count}" "${dst_count}"

    [[ "${src_count}" == "${dst_count}" ]] || die "Miroir incomplet (${src_count} vs ${dst_count})."
    grn "✅ Miroir complet : ${dst_count} objets."
}

main() {
    case "${1:-}" in
        db|verify|storage|all) ;;
        *) usage; exit 1 ;;
    esac

    command -v docker >/dev/null 2>&1 || die "docker est requis."
    docker info >/dev/null 2>&1 || die "Le démon Docker ne répond pas."
    load_env

    case "$1" in
        db)      cmd_db ;;
        verify)  cmd_verify ;;
        storage) cmd_storage ;;
        all)     cmd_db; cmd_verify; cmd_storage ;;
    esac
}

main "$@"
