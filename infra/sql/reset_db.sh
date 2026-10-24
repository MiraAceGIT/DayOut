#!/usr/bin/env bash
# Recree une base vide, joue schema/*.sql puis seed.sql.
#   ./infra/sql/reset_db.sh               -> base DB_NAME de back/.env (demande confirmation)
#   ./infra/sql/reset_db.sh dayout_test   -> autre base (ex. tests)
#   options : --yes (pas de confirmation), --no-seed (schema seul)
# Connexion : DB_HOST / DB_PORT / DB_USER / DB_PASSWORD de back/.env, surchargeables par l'environnement.
#   ENV_FILE=/chemin/.env : lit un autre fichier que back/.env (chemin absolu, ou relatif a infra/sql/).
#   Fichier ou cle absents : valeurs par defaut (127.0.0.1, 3306, root sans mot de passe, dayout).
# Client : $MYSQL, sinon `mysql` du PATH, sinon celui de XAMPP.
set -euo pipefail
cd "$(dirname "$0")"

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

# Lit KEY dans $ENV_FILE (defaut back/.env) sans l'executer (meme format que config/config.php).
# Fichier absent ou cle absente -> chaine vide (la valeur par defaut s'applique ensuite) :
# grep sort en 1 sans correspondance, `|| true` evite l'arret silencieux sous set -e + pipefail.
ENV_FILE="${ENV_FILE:-../../back/.env}"
env_value() {
    [ -f "$ENV_FILE" ] || return 0
    { grep -E "^[[:space:]]*$1[[:space:]]*=" "$ENV_FILE" || true; } | tail -n 1 | cut -d= -f2- \
        | tr -d '\r' | sed -E "s/^[[:space:]\"']+//; s/[[:space:]\"']+\$//"
}

DB_HOST="${DB_HOST:-$(env_value DB_HOST)}"
DB_PORT="${DB_PORT:-$(env_value DB_PORT)}"
DB_USER="${DB_USER:-$(env_value DB_USER)}"
DB_PASSWORD="${DB_PASSWORD:-$(env_value DB_PASSWORD)}"
DB_NAME="${DB_ARG:-${DB_NAME:-$(env_value DB_NAME)}}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_NAME="${DB_NAME:-dayout}"

if ! [[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "Nom de base invalide : $DB_NAME" >&2
    exit 2
fi

if [ -n "${MYSQL:-}" ]; then
    :
elif command -v mysql >/dev/null 2>&1; then
    MYSQL=mysql
elif [ -x /c/xampp/mysql/bin/mysql.exe ]; then
    MYSQL=/c/xampp/mysql/bin/mysql.exe
else
    echo "Client mysql introuvable : l'ajouter au PATH ou definir MYSQL=/chemin/vers/mysql" >&2
    exit 1
fi

if [ "$YES" -eq 0 ]; then
    read -r -p "La base '$DB_NAME' sur $DB_HOST:$DB_PORT va etre supprimee puis recreee. Continuer ? [y/N] " answer
    [[ "$answer" =~ ^[yYoO]$ ]] || { echo "Annule."; exit 1; }
fi

# Mot de passe par variable d'environnement : invisible dans la liste des processus.
export MYSQL_PWD="$DB_PASSWORD"
run_mysql() {
    "$MYSQL" --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --default-character-set=utf8mb4 "$@"
}

run_mysql -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cat schema/*.sql | run_mysql "$DB_NAME"
echo "Schema importe dans '$DB_NAME' ($(ls schema/*.sql | wc -l) fichiers)"

if [ "$SEED" -eq 1 ]; then
    run_mysql "$DB_NAME" < seed.sql
    run_mysql "$DB_NAME" --table -e "
        SELECT 'users' AS t, COUNT(*) AS c FROM users
        UNION ALL SELECT 'events',        COUNT(*) FROM events
        UNION ALL SELECT 'schools',       COUNT(*) FROM schools
        UNION ALL SELECT 'registrations', COUNT(*) FROM registrations;"
fi
