#!/bin/bash
#
# Build the distributable plugin package.
#
# Copies every file not listed in .distignore into build/blankless/
# and zips it as build/blankless.zip (the file to upload to
# WordPress.org). Fails if the plugin header Version and the readme.txt
# Stable tag disagree.
#
# Usage:
#   ./build.sh                 Build the package.
#   ./build.sh --check 1.2.0   Only check that both versions equal 1.2.0.
#

set -euo pipefail

PLUGIN_SLUG="blankless"
PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
BUILD_DIR="$PLUGIN_DIR/build"

HEADER_VERSION=$(grep -m1 "^ \* Version:" "$PLUGIN_DIR/$PLUGIN_SLUG.php" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
STABLE_TAG=$(grep -m1 "^Stable tag:" "$PLUGIN_DIR/readme.txt" | sed 's/.*Stable tag:[[:space:]]*//' | tr -d '[:space:]')

if [ -z "$HEADER_VERSION" ] || [ "$HEADER_VERSION" != "$STABLE_TAG" ]; then
    echo "Error: plugin header Version ($HEADER_VERSION) does not match readme.txt Stable tag ($STABLE_TAG)"
    exit 1
fi

if [ "${1:-}" = "--check" ]; then
    EXPECTED="${2:?Usage: $0 --check <version>}"
    if [ "$HEADER_VERSION" != "$EXPECTED" ]; then
        echo "Error: plugin version $HEADER_VERSION does not match expected version $EXPECTED"
        exit 1
    fi
    echo "Version $HEADER_VERSION matches."
    exit 0
fi

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"

rsync -a --exclude-from="$PLUGIN_DIR/.distignore" --exclude="/build" "$PLUGIN_DIR/" "$BUILD_DIR/$PLUGIN_SLUG/"

(cd "$BUILD_DIR" && zip -qrX "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG")

echo "Built $PLUGIN_SLUG v$HEADER_VERSION"
echo "  Package: $BUILD_DIR/$PLUGIN_SLUG/"
echo "  Zip:     $BUILD_DIR/$PLUGIN_SLUG.zip"
