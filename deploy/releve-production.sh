#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

POSTGRES_IMAGE="${POSTGRES_IMAGE:-postgres:16-alpine}"
OUT_DIR="${OUT_DIR:-${REPO_ROOT}/backups}"

PGHOST="${PGHOST:-127.0.0.1}"
PGPORT="${PGPORT:-55433}"
PGDATABASE="${PGDATABASE:-oceane_dev}"
PGUSER="${PGUSER:-postgres}"
PGPASSWORD="${PGPASSWORD:-postgres}"

# Cibles reconnues comme des copies locales. Allowlist, jamais blocklist.
LOCAL_HOSTS=(127.0.0.1 localhost ::1 host.docker.internal postgres postgres-dev ot-postgres-dev)

red() { printf '\033[31m%s\033[0m\n' "$*"; }
grn() { printf '\033[32m%s\033[0m\n' "$*"; }
ylw() { printf '\033[33m%s\033[0m\n' "$*"; }
die() { red "✖ $*" >&2; exit 1; }

usage() {
    cat <<'EOF'
Relevé de production — phase 1 du plan de refonte.

  ./deploy/releve-production.sh

Joue les 14 blocs de requêtes de l'audit et écrit un document Markdown daté.
À exécuter sur une COPIE RESTAURÉE, jamais sur la production.

Cible par défaut : la base locale 127.0.0.1:55433/oceane_dev.
Surcharges : PGHOST PGPORT PGDATABASE PGUSER PGPASSWORD OUT_DIR POSTGRES_IMAGE

Un hôte non local est refusé, sauf ALLOW_REMOTE=1 — ce garde-fou existe parce que
l'intégralité de l'audit a été faite sans exécuter une requête, faute de copie.
EOF
}

case "${1:-}" in
    -h|--help|help) usage; exit 0 ;;
esac

command -v docker >/dev/null 2>&1 || die "docker est requis."
docker info >/dev/null 2>&1 || die "Le démon Docker ne répond pas."

is_local=0
for h in "${LOCAL_HOSTS[@]}"; do
    [[ "${PGHOST}" == "${h}" ]] && is_local=1
done

if [[ "${is_local}" -eq 0 && "${ALLOW_REMOTE:-0}" != "1" ]]; then
    die "PGHOST « ${PGHOST} » n'est pas une cible locale.
   Le relevé doit tourner sur la copie restaurée par ./deploy/backup.sh, pas sur la
   production. Pour forcer malgré tout : ALLOW_REMOTE=1"
fi

[[ -f "${SCRIPT_DIR}/releve-production.sql" ]] || die "releve-production.sql introuvable."

mkdir -p "${OUT_DIR}"
STAMP="$(date +%F-%H%M)"
OUT="${OUT_DIR}/releve-production-${STAMP}.md"

ylw "Cible : ${PGUSER}@${PGHOST}:${PGPORT}/${PGDATABASE}"
[[ "${is_local}" -eq 0 ]] && red "  ⚠️  cible NON locale, forcée par ALLOW_REMOTE=1"

# En-tête du document
{
    echo "# Relevé de production"
    echo
    echo "> Livrable de la phase 1 du plan de refonte. Généré le $(date '+%Y-%m-%d à %H:%M')."
    echo "> Cible : \`${PGHOST}:${PGPORT}/${PGDATABASE}\`."
    echo ">"
    echo "> Ce document remplace l'audit comme référence : l'audit reconstruisait le schéma"
    echo "> depuis les migrations, ce relevé lit la base. Les deux divergent (audit §3bis.14)."
    echo
    echo '---'
    echo
} > "${OUT}"

# psql tourne dans un conteneur : 127.0.0.1 y designe sa propre boucle locale.
PSQL_HOST="${PGHOST}"
case "${PGHOST}" in
    127.0.0.1|localhost|::1) PSQL_HOST="host.docker.internal" ;;
esac

ylw "Exécution des 14 blocs…"
if docker run --rm -i \
    --add-host "host.docker.internal:host-gateway" \
    -e "PGPASSWORD=${PGPASSWORD}" \
    -v "${SCRIPT_DIR}/releve-production.sql:/releve.sql:ro" \
    "${POSTGRES_IMAGE}" \
    psql -h "${PSQL_HOST}" -p "${PGPORT}" -U "${PGUSER}" -d "${PGDATABASE}" \
         -X -q -f /releve.sql >> "${OUT}" 2>&1
then
    :
else
    red "  psql a renvoyé une erreur — le document contient le détail."
fi

errors="$(grep -ciE '^(ERROR|psql:.*(ERROR|FATAL))' "${OUT}" || true)"
sections="$(grep -c '^## ' "${OUT}" || true)"

echo
grn "✅ Relevé écrit : ${OUT}"
printf '   %s sections · %s erreur(s) SQL\n' "${sections}" "${errors}"

if [[ "${errors}" -gt 0 ]]; then
    ylw "   Les erreurs sont attendues là où le schéma réel diffère des migrations."
    ylw "   Chacune est un écart à documenter, pas un bug du script :"
    grep -iE '^(ERROR|psql:.*(ERROR|FATAL))' "${OUT}" | sed 's/^/     /' | head -20
fi

echo
ylw "Trois résultats conditionnent la suite du plan :"
echo "   · bloc C  — chemins des photos : dimensionne la phase 4 (peut la doubler)"
echo "   · bloc F2 — factures sans PDF : portée du constat #69"
echo "   · bloc G  — compte admin du seeder : action de sécurité immédiate"
