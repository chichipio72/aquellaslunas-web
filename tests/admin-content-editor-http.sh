#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

status="$(curl -sS -o "$work_dir/unauth" -D "$work_dir/unauth-headers" -w '%{http_code}' "$base_url/admin/contenidos/")"
test "$status" = "303"
grep -Eqi '^Location: \../login\.php' "$work_dir/unauth-headers"
grep -Eqi '^Cache-Control: no-store' "$work_dir/unauth-headers"
grep -Eqi '^X-Robots-Tag: noindex, nofollow, noarchive' "$work_dir/unauth-headers"

if runuser -u www-data -- true >/dev/null 2>&1; then
  session_id="$(runuser -u www-data -- php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("admin-content-http-" . bin2hex(random_bytes(12))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
else
  session_id="$(docker exec -i --user www-data web-astro php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("admin-content-http-" . bin2hex(random_bytes(12))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
fi

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/auth" -D "$work_dir/auth-headers" -w '%{http_code}' "$base_url/admin/contenidos/")"
test "$status" = "200"
grep -Eqi '^Cache-Control: no-store' "$work_dir/auth-headers"
grep -q '<h1>Contenidos</h1>' "$work_dir/auth"
grep -q 'Nuevo artículo' "$work_dir/auth"
grep -q 'href="../contenidos/" aria-current="page"' "$work_dir/auth"
grep -q 'href="../configuracion-sitio/"' "$work_dir/auth"
grep -q 'href="../fotos.php"' "$work_dir/auth"
grep -q 'href="../laboratorio-astronomico.php"' "$work_dir/auth"
! grep -q 'contenidos/contenidos/' "$work_dir/auth"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/csrf-invalid" -w '%{http_code}' -X POST \
  --data-urlencode 'action=save' \
  --data-urlencode 'mode=new' \
  --data-urlencode 'csrf_token=invalid' \
  --data-urlencode 'slug=csrf-test-invalido' \
  --data-urlencode 'version=1' \
  --data-urlencode 'titulo=Título inválido' \
  --data-urlencode 'resumen=Resumen inválido' \
  --data-urlencode 'articulo=# Título' \
  --data-urlencode 'content_editor_form_complete=1' \
  "$base_url/admin/contenidos/index.php")"
test "$status" = "200"
grep -q 'token CSRF no es válido' "$work_dir/csrf-invalid"

echo 'admin content editor HTTP tests: ok'
