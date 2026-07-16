#!/usr/bin/env bash
#
# Construit le ZIP installable du plugin, prêt à être attaché à une release.
#
# Le dossier À L'INTÉRIEUR du zip doit impérativement s'appeler
# "younessWeb-manager" : c'est ce nom que WordPress utilise pour retrouver
# le plugin. S'il change, la mise à jour désactive le plugin.
#
# Usage :  ./build.sh
# Sortie :  younessWeb-manager.zip

set -euo pipefail

SLUG="younessWeb-manager"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUT="$ROOT/$SLUG.zip"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# La version affichée doit correspondre à l'en-tête du plugin.
VERSION=$(grep -m1 "^ \* Version:" "$ROOT/vendbase-manager.php" | awk '{print $3}')
CONST=$(grep -m1 "define( 'VB_VERSION'" "$ROOT/vendbase-manager.php" | sed -E "s/.*'([0-9.]+)'.*/\1/")

if [ "$VERSION" != "$CONST" ]; then
  echo "❌ Incohérence de version : en-tête = $VERSION, VB_VERSION = $CONST"
  echo "   Les deux doivent être identiques, sinon WordPress boucle sur la mise à jour."
  exit 1
fi

echo "→ Construction de $SLUG v$VERSION"

mkdir -p "$STAGE/$SLUG"
rsync -a \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude '.agentsroom/' \
  --exclude '.claude/' \
  --exclude '.mcp.json' \
  --exclude '.gitignore' \
  --exclude '.DS_Store' \
  --exclude 'build.sh' \
  --exclude '*.zip' \
  --exclude 'backups/' \
  "$ROOT/" "$STAGE/$SLUG/"

rm -f "$OUT"
( cd "$STAGE" && zip -rq "$OUT" "$SLUG" -x '*.DS_Store' )

echo "✅ $OUT"
echo
echo "Contenu (racine du zip) :"
unzip -l "$OUT" | awk 'NR>3 && $4 ~ /\// {split($4,a,"/"); print a[1]}' | sort -u | sed 's/^/   /'
echo
echo "Publier :  gh release create v$VERSION $SLUG.zip --title \"v$VERSION\""
