#!/usr/bin/env bash
# Analyse statique du back (config : back/phpstan.neon). Sans Composer : le phar est telecharge une fois
# dans tools/.cache, a la version epinglee, et son empreinte SHA-256 est verifiee avant chaque lancement.
#   back/tools/phpstan.sh [options de `phpstan analyse`]
# Monter de version : changer VERSION et SHA256 (sha256sum du nouveau phpstan.phar).
set -euo pipefail
cd "$(dirname "$0")/.."

VERSION=2.1.17
SHA256=767edc1d6a596308c9bdd1fa7e42bf27c88ce3772b8e527cba87dc1c19bd1c3b
PHAR="tools/.cache/phpstan-$VERSION.phar"

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

if [ ! -f "$PHAR" ]; then
    mkdir -p tools/.cache
    curl -sSfL -o "$PHAR.tmp" "https://github.com/phpstan/phpstan/releases/download/$VERSION/phpstan.phar"
    mv "$PHAR.tmp" "$PHAR"
fi

if [ "$(sha256sum "$PHAR" | cut -d' ' -f1)" != "$SHA256" ]; then
    echo "Empreinte de $PHAR inattendue : fichier supprime, relancer pour le retelecharger." >&2
    rm -f "$PHAR"
    exit 1
fi

exec "$PHP" "$PHAR" analyse --no-progress --memory-limit=1G "$@"
