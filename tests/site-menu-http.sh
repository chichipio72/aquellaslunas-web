#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://127.0.0.1:18080}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

curl -fsS "$base_url/index.php" -o "$work_dir/public"
grep -q 'class="site-nav__group-title"' "$work_dir/public"
grep -q '>Inicio</a>' "$work_dir/public"
grep -q '>Eventos</span>' "$work_dir/public"
grep -q 'data-install-trigger data-install-source="menu"' "$work_dir/public"
! grep -q '>Galería</a>' "$work_dir/public"
! grep -q '>Pruebas visuales</a>' "$work_dir/public"

session_id="$(docker exec -i --user www-data web-astro php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("site-menu-http-" . bin2hex(random_bytes(12))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
curl -fsS -H "Cookie: aquellas_lunas_admin=$session_id" "$base_url/index.php" -o "$work_dir/admin"
grep -q '>Galería</a>' "$work_dir/admin"
grep -q '>Pruebas visuales</a>' "$work_dir/admin"

grep -Fq '.site-menu-panel .site-nav__group-title' assets/css/styles.css
grep -Fq 'overflow-y: auto' assets/css/styles.css

echo 'site menu HTTP: ok'
