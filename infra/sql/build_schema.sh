#!/usr/bin/env bash
# Concatene schema/*.sql (dans l'ordre) en dist/schema.sql, importable en une fois dans phpMyAdmin.
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
cat schema/*.sql > dist/schema.sql
echo "dist/schema.sql genere ($(ls schema/*.sql | wc -l) fichiers)"
