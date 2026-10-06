#!/usr/bin/env bash
# List @upstream-shim tags in source and ids registered in docs/upstream-shims.md
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "=== @upstream-shim in source ==="
if rg -n '@upstream-shim' source/ --glob '*.php' --glob '*.scss' 2>/dev/null; then
  :
else
  echo "(none)"
fi

echo ""
echo "=== Registry ids (docs/upstream-shims.md) ==="
REG_IDS=$(rg '^\| `[a-z][a-z0-9-]*` \|' docs/upstream-shims.md 2>/dev/null | sed -n 's/^| `\([^`]*\)`.*/\1/p' | sort -u || true)
echo "$REG_IDS"

echo ""
echo "=== In code but not in registry table (manual check) ==="
CODE_IDS=$(rg -o '@upstream-shim id=[a-z0-9-]+' source/ --glob '*.php' 2>/dev/null | sed 's/.*id=//' | sort -u || true)
comm -23 <(echo "$CODE_IDS" | grep -v '^$' || true) <(echo "$REG_IDS" | grep -v '^$' || true) || true

echo ""
echo "=== In registry but not in code (stale row?) ==="
comm -13 <(echo "$CODE_IDS" | grep -v '^$' || true) <(echo "$REG_IDS" | grep -v '^$' || true) || true
