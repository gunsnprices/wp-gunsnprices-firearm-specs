#!/bin/sh
# Builds dist/gunsnprices-firearm-specs.zip: the plugin folder without development files (see .distignore).
# Usage: sh tools/build-zip.sh
set -eu

SLUG=gunsnprices-firearm-specs
ROOT=$(cd "$(dirname "$0")/.." && pwd)
BUILD="$ROOT/build"
DIST="$ROOT/dist"

rm -rf "$BUILD" "$DIST/$SLUG.zip"
mkdir -p "$BUILD/$SLUG" "$DIST"

EXCLUDES=$(mktemp)
grep -v '^#' "$ROOT/.distignore" | grep -v '^$' | sed 's#^#/#' > "$EXCLUDES"
echo '.DS_Store' >> "$EXCLUDES"
rsync -a --exclude-from="$EXCLUDES" "$ROOT/" "$BUILD/$SLUG/"
rm -f "$EXCLUDES"

# Every PHP file must parse.
find "$BUILD/$SLUG" -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null

# Version numbers must agree.
V_MAIN=$(sed -n 's/^ \* Version: *//p' "$BUILD/$SLUG/$SLUG.php")
V_CONST=$(sed -n "s/^define( 'GNPFS_VERSION', '\(.*\)' );/\1/p" "$BUILD/$SLUG/$SLUG.php")
V_README=$(sed -n 's/^Stable tag: *//p' "$BUILD/$SLUG/readme.txt")
V_BLOCK=$(sed -n 's/^.*"version": "\(.*\)",$/\1/p' "$BUILD/$SLUG/blocks/cost-per-round/block.json")
if [ "$V_MAIN" != "$V_CONST" ] || [ "$V_MAIN" != "$V_README" ] || [ "$V_MAIN" != "$V_BLOCK" ]; then
	echo "Version mismatch: header $V_MAIN, constant $V_CONST, readme $V_README, block.json $V_BLOCK" >&2
	exit 1
fi

(cd "$BUILD" && find "$SLUG" -type f | sort | zip -q -X "$DIST/$SLUG.zip" -@)
echo "Built $DIST/$SLUG.zip (version $V_MAIN, $(unzip -l "$DIST/$SLUG.zip" | tail -1 | awk '{print $2}') files, $(du -k "$DIST/$SLUG.zip" | cut -f1) KB)"
