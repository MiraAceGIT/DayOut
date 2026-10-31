#!/usr/bin/env bash
# Concatene schema/*.sql puis migrations/*.sql en dist/schema.sql, importable en une fois dans phpMyAdmin
# sur une base vide. Le journal schema_migrations est rempli : migrate.sh prend ensuite le relais.
set -euo pipefail
cd "$(dirname "$0")"
source ./lib.sh

migrations=(migrations/*.sql)
[ -e "${migrations[0]}" ] || migrations=()

mkdir -p dist
{
    cat schema/*.sql
    for file in "${migrations[@]}"; do
        printf '\n-- %s\n' "$file"
        cat "$file"
        printf '\nDELIMITER ;\n'
    done
    printf '\n%s\n' "$JOURNAL"
    journal_insert "$BASELINE" "$(checksum schema/*.sql)"
    for file in "${migrations[@]}"; do
        journal_insert "$(basename "$file" .sql)" "$(checksum "$file")"
    done
} > dist/schema.sql
echo "dist/schema.sql genere ($(ls schema/*.sql | wc -l) fichiers de schema, ${#migrations[@]} migration(s))"
