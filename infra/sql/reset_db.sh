#!/usr/bin/env bash
# Recree une base vide, la monte a jour (migrate.sh : schema/ + migrations/) puis joue seed.sql.
#   ./infra/sql/reset_db.sh [--yes] [--no-seed] [base]   (defaut : DB_NAME de back/.env)
# Connexion : voir lib.sh.
set -euo pipefail
cd "$(dirname "$0")"
source ./lib.sh

YES=0
SEED=1
DB_ARG=""
for arg in "$@"; do
    case "$arg" in
        --yes|-y)  YES=1 ;;
        --no-seed) SEED=0 ;;
        -*)        echo "Option inconnue : $arg" >&2; exit 2 ;;
        *)         DB_ARG="$arg" ;;
    esac
done
load_db_config "$DB_ARG"

if [ "$YES" -eq 0 ]; then
    read -r -p "La base '$DB_NAME' sur $DB_HOST:$DB_PORT va etre supprimee puis recreee. Continuer ? [y/N] " answer
    [[ "$answer" =~ ^[yYoO]$ ]] || { echo "Annule."; exit 1; }
fi

run_mysql -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
./migrate.sh "$DB_NAME"

if [ "$SEED" -eq 1 ]; then
    run_mysql "$DB_NAME" < seed.sql
    run_mysql "$DB_NAME" --table -e "
        SELECT 'users' AS t, COUNT(*) AS c FROM users
        UNION ALL SELECT 'events',        COUNT(*) FROM events
        UNION ALL SELECT 'schools',       COUNT(*) FROM schools
        UNION ALL SELECT 'registrations', COUNT(*) FROM registrations;"
fi
