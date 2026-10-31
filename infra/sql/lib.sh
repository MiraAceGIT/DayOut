# Partage par reset_db.sh, migrate.sh et build_schema.sh (source apres un cd dans infra/sql/).
# DB_* de $ENV_FILE (defaut back/.env), surchargeables par l'environnement ; $1 = nom de base force.

# Lit KEY sans sourcer le fichier. `|| true` : grep sort en 1 sans correspondance (set -e + pipefail).
ENV_FILE="${ENV_FILE:-../../back/.env}"
env_value() {
    [ -f "$ENV_FILE" ] || return 0
    { grep -E "^[[:space:]]*$1[[:space:]]*=" "$ENV_FILE" || true; } | tail -n 1 | cut -d= -f2- \
        | tr -d '\r' | sed -E "s/^[[:space:]\"']+//; s/[[:space:]\"']+\$//"
}

load_db_config() {
    DB_HOST="${DB_HOST:-$(env_value DB_HOST)}"
    DB_PORT="${DB_PORT:-$(env_value DB_PORT)}"
    DB_USER="${DB_USER:-$(env_value DB_USER)}"
    DB_PASSWORD="${DB_PASSWORD:-$(env_value DB_PASSWORD)}"
    DB_NAME="${1:-${DB_NAME:-$(env_value DB_NAME)}}"
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

    # Exportees : migrate.sh, appele par reset_db.sh, se connecte au meme serveur.
    export DB_HOST DB_PORT DB_USER DB_PASSWORD MYSQL
    # Mot de passe par variable d'environnement : invisible dans la liste des processus.
    export MYSQL_PWD="$DB_PASSWORD"
}

run_mysql() {
    "$MYSQL" --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --default-character-set=utf8mb4 "$@"
}

# Journal des migrations ; schema/ y figure sous la version BASELINE.
BASELINE=00000000000000_baseline
JOURNAL="CREATE TABLE IF NOT EXISTS schema_migrations (
  version    VARCHAR(190) NOT NULL,
  checksum   CHAR(64)     NOT NULL,
  applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

journal_insert() {
    echo "INSERT INTO schema_migrations (version, checksum) VALUES ('$1', '$2');"
}

# CRLF retires : une meme migration a le meme checksum sous Windows et sous Linux.
checksum() { cat "$@" | tr -d '\r' | sha256sum | cut -c1-64; }
