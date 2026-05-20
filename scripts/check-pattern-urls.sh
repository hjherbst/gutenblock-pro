#!/usr/bin/env bash
# Fail if any pattern content.html contains instance-specific plugin-asset URLs.
#
# Plugin pattern markup must use the `__PLUGIN_URL__` placeholder so the asset
# resolves to the local plugin URL on any WordPress installation. This guard
# blocks regressions where the in-editor image picker writes absolute URLs
# (e.g. `http://localhost:10038/...`) and they accidentally get committed.
#
# Run locally:
#   scripts/check-pattern-urls.sh
#
# Exit codes: 0 = clean, 1 = forbidden URLs found.

set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." &>/dev/null && pwd)"
cd "$ROOT_DIR"

PATTERNS=("https?://[^[:space:]\"'<>]+/wp-content/plugins/gutenblock-pro/assets/images" \
          "\"/wp-content/plugins/gutenblock-pro/assets/images" \
          "src=\"/wp-content/plugins/gutenblock-pro/assets/images")

found=0
for pat in "${PATTERNS[@]}"; do
  if matches="$(grep -rEn --include='content*.html' "$pat" patterns/ 2>/dev/null || true)"; then
    if [[ -n "$matches" ]]; then
      printf '\n[check-pattern-urls] FORBIDDEN URL pattern: %s\n%s\n' "$pat" "$matches"
      found=1
    fi
  fi
done

if [[ "$found" -eq 1 ]]; then
  cat <<'MSG'

ERROR: pattern content.html files contain instance-specific plugin-asset URLs.

All plugin-managed images in patterns MUST use the host-agnostic placeholder
`__PLUGIN_URL__/assets/images/<file>` so the plugin works on any installation.

Fix: re-save the pattern via the Pattern Creator (the save handler rewrites
URLs automatically) or run scripts/normalize-pattern-urls.php once.
MSG
  exit 1
fi

echo "[check-pattern-urls] OK — all pattern URLs use __PLUGIN_URL__ placeholder."
