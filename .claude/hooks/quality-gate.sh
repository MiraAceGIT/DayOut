#!/usr/bin/env bash
# Hook Stop : avant que Claude rende la main, verifier ce qui a change sur HEAD.
# - back/ touche : tests unitaires PHP (pas d'integration ici, elle exige MariaDB).
# - front/ touche : tests Node + typecheck JSDoc.
# Si ca casse, exit 2 -> Claude reprend la main pour corriger.
# Garde-fou : un seul retour force par reponse (stop_hook_active) pour eviter une boucle infinie.
set -uo pipefail

INPUT=$(cat)
if command -v jq >/dev/null 2>&1; then
  [[ "$(echo "$INPUT" | jq -r '.stop_hook_active // false')" == "true" ]] && exit 0
else
  # Pas de jq -> grep tolerant ; si le flag est posee, Claude Code l'ecrira "stop_hook_active": true.
  echo "$INPUT" | grep -q '"stop_hook_active"[[:space:]]*:[[:space:]]*true' && exit 0
fi

cd "$CLAUDE_PROJECT_DIR" || exit 0

# Fichiers modifies (staged + unstaged) par rapport a HEAD.
CHANGED=$(git diff --name-only HEAD 2>/dev/null; git diff --name-only --cached 2>/dev/null)
CHANGED=$(printf '%s\n' "$CHANGED" | sort -u)

# Rien touche -> rien a verifier (ex. edit d'un .md en dehors de back/front).
[[ -z "$CHANGED" ]] && exit 0

BACK_CHANGED=false
FRONT_CHANGED=false
while IFS= read -r file; do
  [[ -z "$file" ]] && continue
  case "$file" in
    back/*.php|back/docs/openapi/*.yaml|back/routes/*.php) BACK_CHANGED=true ;;
    front/*.js|front/*.html|front/*.css) FRONT_CHANGED=true ;;
  esac
done <<< "$CHANGED"

if $BACK_CHANGED; then
  if ! OUT=$(./back/tests/run.sh --unit 2>&1); then
    echo "Tests back KO, corrige avant de terminer :" >&2
    echo "$OUT" | tail -60 >&2
    exit 2
  fi
fi

if $FRONT_CHANGED; then
  if ! OUT=$(cd front && node --test "tests/**/*.test.js" 2>&1); then
    echo "Tests front KO, corrige avant de terminer :" >&2
    echo "$OUT" | tail -60 >&2
    exit 2
  fi
  if ! OUT=$(bash front/tools/typecheck.sh 2>&1); then
    echo "Typecheck front KO, corrige avant de terminer :" >&2
    echo "$OUT" | tail -60 >&2
    exit 2
  fi
fi

exit 0
