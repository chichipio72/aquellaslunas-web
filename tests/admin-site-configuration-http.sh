#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

status="$(curl -sS -o "$work_dir/unauth" -D "$work_dir/unauth-headers" -w '%{http_code}' "$base_url/admin/configuracion-sitio/")"
test "$status" = "303"
grep -Eqi '^Location: \../login\.php' "$work_dir/unauth-headers"
grep -Eqi '^Cache-Control: no-store' "$work_dir/unauth-headers"

if runuser -u www-data -- true >/dev/null 2>&1; then
  session_id="$(runuser -u www-data -- php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("admin-site-config-http-" . bin2hex(random_bytes(12))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
else
  session_id="$(docker exec -i --user www-data web-astro php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("admin-site-config-http-" . bin2hex(random_bytes(12))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
fi

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/auth" -D "$work_dir/auth-headers" -w '%{http_code}' "$base_url/admin/configuracion-sitio/")"
test "$status" = "200"
grep -Eqi '^Cache-Control: no-store' "$work_dir/auth-headers"
grep -q '<h1>Menú y secciones</h1>' "$work_dir/auth"
grep -q 'href="../configuracion-sitio/" aria-current="page"' "$work_dir/auth"
grep -q '<strong>Menú y secciones</strong>' "$work_dir/auth"
grep -q 'href="../contenidos/"' "$work_dir/auth"
grep -q 'href="../fotos.php"' "$work_dir/auth"
grep -q 'href="../laboratorio-astronomico.php"' "$work_dir/auth"
grep -q 'name="settings\[content.enabled\]"' "$work_dir/auth"
grep -q 'name="sections\[today\]\[group_id\]"' "$work_dir/auth"
grep -q 'name="sections\[today\]\[public_visible\]"' "$work_dir/auth"
grep -q 'name="sections\[today\]\[admin_visible\]"' "$work_dir/auth"
grep -q 'name="groups\[events\]\[label\]"' "$work_dir/auth"
grep -q 'name="settings\[home.today.enabled\]"' "$work_dir/auth"
grep -q 'name="settings\[home.satellite_transits.enabled\]"' "$work_dir/auth"
grep -q 'name="eclipse_settings\[enabled\]"' "$work_dir/auth"
grep -q 'name="eclipse_settings\[days\]"' "$work_dir/auth"
grep -q 'name="eclipse_settings\[solar_url\]"' "$work_dir/auth"
grep -q 'name="eclipse_settings\[lunar_url\]"' "$work_dir/auth"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/csrf-invalid" -w '%{http_code}' -X POST \
  --data-urlencode 'csrf_token=token-invalido' \
  --data-urlencode 'settings[content.enabled]=1' \
  "$base_url/admin/configuracion-sitio/index.php")"
test "$status" = "200"
grep -q 'token CSRF no es válido' "$work_dir/csrf-invalid"

csrf_token="$(grep -o 'name="csrf_token" value="[a-f0-9]\{64\}"' "$work_dir/auth" | head -n 1 | sed -E 's/.*value="([a-f0-9]{64})"/\1/')"
test "${#csrf_token}" = "64"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/save" -D "$work_dir/save-headers" -w '%{http_code}' -X POST \
  --data-urlencode "csrf_token=$csrf_token" \
  --data-urlencode 'mode=save_flags' \
  --data-urlencode 'settings[content.enabled]=1' \
  --data-urlencode 'settings[home.today.enabled]=1' \
  --data-urlencode 'settings[home.tonight.enabled]=1' \
  --data-urlencode 'settings[home.phases.enabled]=1' \
  --data-urlencode 'settings[home.upcoming.enabled]=1' \
  --data-urlencode 'settings[home.satellite_transits.enabled]=1' \
  --data-urlencode 'settings[home.explore_sky.enabled]=1' \
  --data-urlencode 'settings[home.trivia.enabled]=1' \
  --data-urlencode 'settings[home.sabias_que.enabled]=1' \
  --data-urlencode 'settings[home.install.enabled]=1' \
  "$base_url/admin/configuracion-sitio/index.php")"
test "$status" = "303"
grep -Eqi '^Location: index\.php\?saved=1' "$work_dir/save-headers"

echo 'admin site configuration HTTP tests: ok'
