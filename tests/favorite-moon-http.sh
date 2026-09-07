#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

url="$base_url/luna-fecha-favorita.php?fecha=2024-04-08&hora=15%3A17"
status="$(curl -sS -o "$work_dir/page" -D "$work_dir/headers" -w '%{http_code}' "$url")"
test "$status" = "200"
grep -Eqi '^Cache-Control: .*no-store' "$work_dir/headers"
grep -q '<h1>La Luna de tu fecha favorita</h1>' "$work_dir/page"
grep -q 'name="fecha" value="2024-04-08"' "$work_dir/page"
grep -q 'name="hora" value="15:17"' "$work_dir/page"
grep -q 'data-moon-three' "$work_dir/page"
grep -q 'data-moon-wallpaper-download' "$work_dir/page"
grep -q 'data-moon-wallpaper-share' "$work_dir/page"
grep -q '1440 × 2560' "$work_dir/page"
grep -q '2560 × 1440' "$work_dir/page"
grep -q '>Descargar para celular<' "$work_dir/page"
grep -q '>Compartir para celular<' "$work_dir/page"
grep -q '>Descargar para PC<' "$work_dir/page"
grep -q 'return=%2Fluna-fecha-favorita.php%3Ffecha%3D2024-04-08%26hora%3D15%253A17' "$work_dir/page"

return_path='%2Fluna-fecha-favorita.php%3Ffecha%3D2024-04-08%26hora%3D15%253A17'
status="$(curl -sS -o "$work_dir/location" -w '%{http_code}' "$base_url/ubicacion.php?return=$return_path")"
test "$status" = "200"
grep -q 'name="return_to" value="/luna-fecha-favorita.php?fecha=2024-04-08&amp;hora=15%3A17"' "$work_dir/location"
grep -q 'Volver a La Luna de tu fecha favorita' "$work_dir/location"

echo 'favorite moon HTTP: ok'
