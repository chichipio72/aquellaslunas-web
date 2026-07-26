#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

status="$(curl -sS -c "$work_dir/cookies" -o "$work_dir/gallery" -w '%{http_code}' "$base_url/galeria.php")"
test "$status" = "200"
grep -q 'action="tienda/iniciar-compra.php"' /var/www/html/galeria.php
grep -q 'name="csrf_token"' /var/www/html/galeria.php
grep -q 'data-gallery-selection-count' /var/www/html/galeria.php

status="$(curl -sS -o "$work_dir/checkout-get" -D "$work_dir/checkout-headers" -w '%{http_code}' "$base_url/tienda/iniciar-compra.php")"
test "$status" = "405"
grep -Eqi '^Allow: POST' "$work_dir/checkout-headers"

status="$(curl -sS -b "$work_dir/cookies" -o "$work_dir/checkout-csrf" -w '%{http_code}' -X POST --data 'csrf_token=invalid' "$base_url/tienda/iniciar-compra.php")"
test "$status" = "400"

for page in pago-exitoso.php pago-pendiente.php pago-fallido.php; do
    status="$(curl -sS -o "$work_dir/$page" -w '%{http_code}' "$base_url/tienda/$page")"
    test "$status" = "200"
    grep -q 'no hay descargas habilitadas' "$work_dir/$page"
done

printf 'store checkout HTTP tests: ok\n'
