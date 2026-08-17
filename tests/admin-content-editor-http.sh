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
grep -q 'Importar contenido completo' "$work_dir/auth"
grep -q 'href="../contenidos/" aria-current="page"' "$work_dir/auth"
grep -q '<strong>Contenidos</strong>' "$work_dir/auth"
grep -q 'href="../configuracion-sitio/"' "$work_dir/auth"
grep -q 'href="../fotos.php"' "$work_dir/auth"
grep -q 'href="../laboratorio-astronomico.php"' "$work_dir/auth"
! grep -q 'contenidos/contenidos/' "$work_dir/auth"
grep -q 'class="editor-title-link" href="../../contenido.php?slug=' "$work_dir/auth"
grep -q 'name="mode" value="visibility_toggle"' "$work_dir/auth"
grep -q 'class="editor-visibility-toggle' "$work_dir/auth"
grep -q '<th>Imagen principal</th>' "$work_dir/auth"
grep -q 'class="editor-image-status' "$work_dir/auth"
grep -q '>Editar</a>' "$work_dir/auth"
! grep -q '>Ver</a>' "$work_dir/auth"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/edit-images" -w '%{http_code}' "$base_url/admin/contenidos/index.php?action=edit&slug=pascua")"
test "$status" = "200"
grep -q 'data-image-selector data-image-filter="hero"' "$work_dir/edit-images"
grep -q 'data-image-selector data-image-filter="vertical"' "$work_dir/edit-images"
grep -q 'data-image-hero="\(true\|false\)"' "$work_dir/edit-images"
grep -q 'data-image-vertical="\(true\|false\)"' "$work_dir/edit-images"
grep -q 'data-image-filter-empty hidden' "$work_dir/edit-images"
grep -Fq 'data-insert-snippet="[[embed url=&quot;&quot;]]" data-insert-cursor-offset="-3"' "$work_dir/edit-images"
grep -q 'class="editor-slug-warning"' "$work_dir/edit-images"
grep -q 'cambiar el slug modifica su URL pública' "$work_dir/edit-images"
grep -q "option.dataset.imageHero === 'true'" /var/www/html/admin/contenidos/editor.js
grep -q "option.dataset.imageVertical === 'true'" /var/www/html/admin/contenidos/editor.js
grep -Fq '.editor-image-option[hidden] { display: none; }' /var/www/html/admin/contenidos/editor.css

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/visibility-csrf-invalid" -w '%{http_code}' -X POST \
  --data-urlencode 'mode=visibility_toggle' \
  --data-urlencode 'csrf_token=invalid' \
  --data-urlencode 'slug=pascua' \
  --data-urlencode 'visible=0' \
  "$base_url/admin/contenidos/index.php")"
test "$status" = "200"
grep -q 'token CSRF no es válido' "$work_dir/visibility-csrf-invalid"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/import" -D "$work_dir/import-headers" -w '%{http_code}' "$base_url/admin/contenidos/index.php?action=import")"
test "$status" = "200"
grep -q 'data-package-import' "$work_dir/import"
grep -q 'name="package_json"' "$work_dir/import"
grep -q 'data-copy-package-example' "$work_dir/import"
grep -q '>Copiar ejemplo<' "$work_dir/import"
grep -q 'name="package_action" value="validate"' "$work_dir/import"
grep -q 'name="package_action" value="import" disabled' "$work_dir/import"
grep -q 'data-package-example' "$work_dir/import"
grep -q '"ejemplo-trivia-2"' "$work_dir/import"
grep -q '"ejemplo-sabias-2"' "$work_dir/import"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/import-csrf-invalid" -w '%{http_code}' -X POST \
  --data-urlencode 'mode=package_import' \
  --data-urlencode 'package_action=import' \
  --data-urlencode 'csrf_token=invalid' \
  --data-urlencode 'package_json={"version":1}' \
  "$base_url/admin/contenidos/index.php")"
test "$status" = "200"
grep -q 'token CSRF no es válido' "$work_dir/import-csrf-invalid"

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
