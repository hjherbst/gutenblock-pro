#!/usr/bin/env bash
# Downloads the variable / static TTF files for the 10 Google Fonts that
# the SaaS customizer exposes but are not yet bundled with GutenTheme.
# Files are pulled from the official `google/fonts` repository on
# GitHub and dropped into `themes/gutentheme/assets/fonts/{slug}/`.
#
# Re-running the script is safe: existing files are overwritten so a
# fresh release always ships the latest upstream snapshot.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="${ROOT}/themes/gutentheme/assets/fonts"
RAW="https://github.com/google/fonts/raw/main/ofl"

mkdir -p "${DEST}"

# Mapping: theme slug | upstream font dir | filename(s) under that dir.
# For variable fonts a single TTF carries every weight; for static
# fonts (Poppins, Cormorant Garamond) we ship Regular + Bold.
read -r -d '' FONTS <<'EOF' || true
inter|inter|Inter[opsz,wght].ttf
urbanist|urbanist|Urbanist[wght].ttf
open-sans|opensans|OpenSans[wdth,wght].ttf
bricolage-grotesque|bricolagegrotesque|BricolageGrotesque[opsz,wdth,wght].ttf
mulish|mulish|Mulish[wght].ttf
poppins|poppins|Poppins-Regular.ttf;Poppins-Bold.ttf
bodoni-moda|bodonimoda|BodoniModa[opsz,wght].ttf
eb-garamond|ebgaramond|EBGaramond[wght].ttf
fraunces|fraunces|Fraunces[SOFT,WONK,opsz,wght].ttf
cormorant-garamond|cormorantgaramond|CormorantGaramond[wght].ttf
EOF

while IFS='|' read -r slug repo files; do
  [ -z "${slug:-}" ] && continue
  target_dir="${DEST}/${slug}"
  mkdir -p "${target_dir}"
  IFS=';' read -r -a file_list <<< "${files}"
  for file in "${file_list[@]}"; do
    encoded="$(python3 -c "import urllib.parse,sys; print(urllib.parse.quote(sys.argv[1]))" "${file}")"
    url="${RAW}/${repo}/${encoded}"
    out="${target_dir}/${file}"
    echo "→ ${slug}/${file}"
    curl -fsSL --retry 3 --retry-delay 1 -o "${out}" "${url}"
  done
done <<< "${FONTS}"

echo
echo "Done. New size of assets/fonts:"
du -sh "${DEST}"
