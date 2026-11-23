#!/usr/bin/env bash
# Hook PostToolUse : apres chaque Edit/Write, verifications legeres sur LE fichier modifie.
# Verifications lourdes (PHPStan, test suites, typecheck global) : voir quality-gate.sh.
# Exit 2 = l'erreur est renvoyee a Claude, qui doit corriger.
set -uo pipefail

INPUT=$(cat)
if command -v jq >/dev/null 2>&1; then
  FILE=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')
else
  # Pas de jq -> on prend le premier chemin "file_path" du JSON brut, en ignorant les echappements.
  FILE=$(echo "$INPUT" | sed -n 's/.*"file_path"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n1)
fi
[[ -z "$FILE" || ! -f "$FILE" ]] && exit 0

# On ignore les dossiers generes / externes.
[[ "$FILE" == *vendor/* ]] && exit 0
[[ "$FILE" == *node_modules/* ]] && exit 0
[[ "$FILE" == *storage/logs/* ]] && exit 0

cd "$CLAUDE_PROJECT_DIR" || exit 0

case "$FILE" in
  *.php)
    if ! OUT=$(php -l "$FILE" 2>&1); then
      echo "Erreur de syntaxe PHP dans $FILE :" >&2
      echo "$OUT" >&2
      exit 2
    fi
    ;;
  *.json)
    # back/.env.example, package.json, OpenAPI JSON : un JSON casse arrete tout le chargement.
    if ! OUT=$(jq empty "$FILE" 2>&1); then
      echo "JSON invalide dans $FILE :" >&2
      echo "$OUT" >&2
      exit 2
    fi
    ;;
  *.yaml|*.yml)
    # Spec OpenAPI : un YAML casse fait planter DocsController + OpenApiContractTest.
    if command -v yq >/dev/null 2>&1; then
      if ! OUT=$(yq eval 'true' "$FILE" >/dev/null 2>&1); then
        echo "YAML invalide dans $FILE :" >&2
        echo "$OUT" >&2
        exit 2
      fi
    fi
    ;;
esac
exit 0
