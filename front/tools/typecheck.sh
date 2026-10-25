#!/usr/bin/env bash
# Verification des types JSDoc du front (config : front/jsconfig.json), comme en CI.
# Le front reste sans package.json : TypeScript et @types/node (versions epinglees) sont installes
# une fois dans tools/.cache (ignore par git).
#   front/tools/typecheck.sh
set -euo pipefail
cd "$(dirname "$0")/.."

TYPESCRIPT=5.6.3
TYPES_NODE=22.10.2
CACHE="tools/.cache/ts-$TYPESCRIPT-node-$TYPES_NODE"

if [ ! -x "$CACHE/node_modules/.bin/tsc" ]; then
    npm install --no-save --no-audit --no-fund --silent --prefix "$CACHE" \
        "typescript@$TYPESCRIPT" "@types/node@$TYPES_NODE"
fi

exec "$CACHE/node_modules/.bin/tsc" -p jsconfig.json --typeRoots "$CACHE/node_modules/@types"
