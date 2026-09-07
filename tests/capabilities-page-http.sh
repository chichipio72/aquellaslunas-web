#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://127.0.0.1:18080}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

status="$(curl -sS -o "$work_dir/page" -w '%{http_code}' "$base_url/que-podes-hacer.php")"
test "$status" = "200"
grep -q 'Qué podés hacer en Aquellas Lunas' "$work_dir/page"
test "$(grep -o 'class="card capability-card"' "$work_dir/page" | wc -l)" -gt 0
test "$(grep -o 'class="card capability-feature' "$work_dir/page" | wc -l)" -gt 0
grep -q 'data-install-trigger data-install-source="capabilities"' "$work_dir/page"

status="$(curl -sS -o "$work_dir/json" -w '%{http_code}' "$base_url/content-data/que-podes-hacer.json")"
test "$status" = "403"
! grep -q 'Qué podés hacer' "$work_dir/json"

echo 'capabilities page HTTP: ok'
