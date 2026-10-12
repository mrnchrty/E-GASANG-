#!/usr/bin/env bash
# E-GASANG — build a flat, drag-and-drop-ready bundle for Vercel.
# Produces:  egasang-vercel-ready/  and  egasang-vercel-ready.zip
# Usage:     ./deploy/make-bundle.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STAGE="$ROOT/egasang-vercel-ready"
ZIP="$ROOT/egasang-vercel-ready.zip"

cd "$ROOT"

[ -f index.html ] || { echo "✗ index.html not found in $ROOT"; exit 1; }
[ -f vercel.json ] || { echo "✗ vercel.json not found in $ROOT"; exit 1; }

rm -rf "$STAGE" "$ZIP"
mkdir -p "$STAGE"

# Only what a static deploy needs — index.html is fully self-contained.
cp index.html "$STAGE/"
cp vercel.json "$STAGE/"

# Sanity check: the bundle must not contain any PHP/SQL source.
if find "$STAGE" -type f \( -name '*.php' -o -name '*.sql' -o -name '*.zip' \) | grep -q .; then
  echo "✗ aborted: backend or archive files leaked into the bundle"; exit 1
fi

( cd "$STAGE" && zip -q -r "$ZIP" . -x '.*' )

echo "✓ Bundle ready"
echo "   folder: $STAGE"
echo "   zip:    $ZIP ($(du -h "$ZIP" | cut -f1))"
echo "   files:  $(unzip -l "$ZIP" | awk 'NR>3 {print $4}' | grep -c . ) (index.html + vercel.json)"
echo
echo "Next: drag $ZIP onto https://vercel.com/drop"
