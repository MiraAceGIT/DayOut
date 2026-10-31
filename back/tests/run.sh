#!/usr/bin/env bash
# Remet la base de test a zero (schema + seed) puis lance tests/run.php.
#   back/tests/run.sh [EventsTest]   filtre sur "Classe::methode"
#   --unit : unitaires seuls, sans BDD ; --integration : integration seule
# Base : TEST_DB_NAME (defaut dayout_test), jamais celle de dev.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -n "${PHP:-}" ]; then
    :
elif command -v php >/dev/null 2>&1; then
    PHP=php
elif [ -x /c/xampp/php/php.exe ]; then
    PHP=/c/xampp/php/php.exe
else
    echo "PHP introuvable : l'ajouter au PATH ou definir PHP=/chemin/vers/php" >&2
    exit 1
fi

for arg in "$@"; do
    if [ "$arg" = "--unit" ]; then
        exec "$PHP" tests/run.php "$@"
    fi
done

export TEST_DB_NAME="${TEST_DB_NAME:-dayout_test}"
# || true : .env absent ou sans DB_NAME ne doit pas arreter le script (set -e + pipefail).
DEV_DB="$({ grep -E '^[[:space:]]*DB_NAME[[:space:]]*=' .env 2>/dev/null || true; } | tail -n 1 | cut -d= -f2- | tr -d "\r\"' ")"
if [ "$TEST_DB_NAME" = "${DEV_DB:-dayout}" ]; then
    echo "TEST_DB_NAME ($TEST_DB_NAME) est la base de dev : refuse, elle serait effacee." >&2
    exit 2
fi

echo "Remise a zero de la base '$TEST_DB_NAME'..."
../infra/sql/reset_db.sh --yes "$TEST_DB_NAME" > /dev/null
exec "$PHP" tests/run.php "$@"
