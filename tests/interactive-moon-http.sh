#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://127.0.0.1:18080}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

curl -fsS "$base_url/luna-interactiva.php?fecha=2026-08-17&hora=19%3A00&craters=1&maria=0&other=1&landings=1&detail=more" -o "$work_dir/page"
grep -q '<h1>Luna interactiva</h1>' "$work_dir/page"
grep -q 'data-interactive-moon-payload' "$work_dir/page"
grep -q '"illumination_fraction"' "$work_dir/page"
grep -q '"lunar_north_screen_angle_degrees"' "$work_dir/page"
grep -q '"name":"Tycho"' "$work_dir/page"
grep -q '"name":"Apolo 11"' "$work_dir/page"
grep -q '"name":"Chang’e 4"' "$work_dir/page"
grep -q 'data-moon-layer="maria"' "$work_dir/page"
grep -q 'data-moon-layer="other" checked' "$work_dir/page"
grep -q '<option value="more" selected>' "$work_dir/page"

curl -fsS "$base_url/luna-interactiva.php?illumination=full" -o "$work_dir/full"
grep -q '<option value="full" selected>Todo iluminado</option>' "$work_dir/full"
grep -q '"illumination":"full"' "$work_dir/full"

curl -fsS "$base_url/luna-interactiva.php?embed=1&fecha=2026-08-17&hora=19%3A00" -o "$work_dir/embed"
grep -q 'interactive-moon-page--embed' "$work_dir/embed"
grep -q 'name="embed" value="1"' "$work_dir/embed"
! grep -q '<header class="site-header">' "$work_dir/embed"
! grep -q '<footer class="site-footer">' "$work_dir/embed"

echo 'interactive moon HTTP: ok'
