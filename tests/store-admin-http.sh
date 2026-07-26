#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

status="$(curl -sS -o "$work_dir/admin-index" -D "$work_dir/admin-index-headers" -w '%{http_code}' "$base_url/admin/")"
test "$status" = "303"
grep -Eqi '^Location: login\.php' "$work_dir/admin-index-headers"
grep -Eqi '^Cache-Control: no-store' "$work_dir/admin-index-headers"
grep -Eqi '^X-Robots-Tag: noindex' "$work_dir/admin-index-headers"
! grep -qi 'Index of /admin' "$work_dir/admin-index"

session_id="$(runuser -u www-data -- php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("admin-http-" . bin2hex(random_bytes(12))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/admin-authenticated" -D "$work_dir/admin-authenticated-headers" -w '%{http_code}' "$base_url/admin/")"
test "$status" = "303"
grep -Eqi '^Location: fotos\.php' "$work_dir/admin-authenticated-headers"
grep -Eqi '^Cache-Control: no-store' "$work_dir/admin-authenticated-headers"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/admin-photos-authenticated" -w '%{http_code}' "$base_url/admin/fotos.php")"
test "$status" = "200"
grep -q 'name="action" value="batch_price"' "$work_dir/admin-photos-authenticated"
grep -q 'name="action" value="individual_price"' "$work_dir/admin-photos-authenticated"
grep -q 'name="action" value="editorial_metadata"' "$work_dir/admin-photos-authenticated"
grep -q 'name="description"' "$work_dir/admin-photos-authenticated"
grep -q 'name="keywords"' "$work_dir/admin-photos-authenticated"
runuser -u www-data -- php -r 'session_name("aquellas_lunas_admin"); session_id($argv[1]); session_start(); $_SESSION = []; session_destroy();' "$session_id"

status="$(curl -sS -c "$work_dir/cookies" -o "$work_dir/login" -D "$work_dir/login-headers" -w '%{http_code}' "$base_url/admin/login.php")"
test "$status" = "200"
grep -Eqi '^Cache-Control: no-store' "$work_dir/login-headers"
grep -Eqi '^X-Robots-Tag: noindex' "$work_dir/login-headers"
grep -Eqi '^Set-Cookie: .*HttpOnly.*SameSite=Lax' "$work_dir/login-headers"

status="$(curl -sS -b "$work_dir/cookies" -o "$work_dir/invalid-csrf" -w '%{http_code}' -X POST --data 'csrf_token=invalid&user=x&password=x' "$base_url/admin/login.php")"
test "$status" = "400"

status="$(curl -sS -o "$work_dir/fotos" -D "$work_dir/headers" -w '%{http_code}' "$base_url/admin/fotos.php")"
test "$status" = "303"
grep -Eqi '^Location: login\.php' "$work_dir/headers"
grep -Eqi '^Cache-Control: no-store' "$work_dir/headers"
grep -Eqi '^X-Robots-Tag: noindex' "$work_dir/headers"

status="$(curl -sS -o "$work_dir/logout" -D "$work_dir/logout-headers" -w '%{http_code}' "$base_url/admin/logout.php")"
test "$status" = "405"
grep -Eqi '^Allow: POST' "$work_dir/logout-headers"

status="$(curl -sS -o "$work_dir/script" -w '%{http_code}' "$base_url/scripts/check-store-database.php")"
test "$status" = "403"

status="$(curl -sS -o "$work_dir/include" -w '%{http_code}' "$base_url/includes/store-admin-auth.php")"
test "$status" = "403"

status="$(curl -sS -o "$work_dir/test" -w '%{http_code}' "$base_url/tests/store-admin.php")"
test "$status" = "403"

printf 'store admin HTTP tests: ok\n'
