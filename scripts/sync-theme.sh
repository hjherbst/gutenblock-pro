#!/usr/bin/env bash
# Sync the local GutenTheme installation into the plugin's bundled
# themes/gutentheme/ directory so the next plugin release ships with the
# current theme files. Run from anywhere; paths are absolute.

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
PLUGIN_DIR="$(cd -- "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)"
TARGET_DIR="$PLUGIN_DIR/themes/gutentheme"

# The canonical theme source lives in the LocalWP "gutentheme" site.
SOURCE_DIR="${GUTENTHEME_SOURCE:-/Users/hjherbst/Local Sites/gutentheme/app/public/wp-content/themes/gutentheme}"

if [ ! -d "$SOURCE_DIR" ]; then
  echo "Theme source not found: $SOURCE_DIR" >&2
  echo "Set GUTENTHEME_SOURCE to point at a valid gutentheme directory." >&2
  exit 1
fi

mkdir -p "$TARGET_DIR"

rsync -a --delete \
  --exclude='node_modules' \
  --exclude='.git' \
  --exclude='.DS_Store' \
  --exclude='package-lock.json' \
  "$SOURCE_DIR/" \
  "$TARGET_DIR/"

echo "Synced GutenTheme from $SOURCE_DIR to $TARGET_DIR"
