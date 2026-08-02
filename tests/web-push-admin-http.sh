#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

status="$(curl -sS -o "$work_dir/unauthenticated" -D "$work_dir/unauthenticated-headers" -w '%{http_code}' "$base_url/admin/notificaciones-prueba.php")"
test "$status" = '303'
grep -Eqi '^Location: login\.php' "$work_dir/unauthenticated-headers"

session_id="$(runuser -u www-data -- php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("push-admin-http-" . bin2hex(random_bytes(12))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/authenticated" -D "$work_dir/authenticated-headers" -w '%{http_code}' "$base_url/admin/notificaciones-prueba.php")"
test "$status" = '200'
grep -Eqi '^Cache-Control: no-store' "$work_dir/authenticated-headers"
grep -Eqi '^X-Robots-Tag: noindex' "$work_dir/authenticated-headers"
grep -q '<h1>Notificaciones de prueba</h1>' "$work_dir/authenticated"
grep -q '>Estado de Web Push</h2>' "$work_dir/authenticated"
grep -q '>Activar notificaciones en este dispositivo</button>' "$work_dir/authenticated"
grep -q '>Desactivar notificaciones</button>' "$work_dir/authenticated"
grep -q '>Enviar notificación de prueba</h2>' "$work_dir/authenticated"
grep -q 'value="Aquellas Lunas"' "$work_dir/authenticated"
grep -q '>Esta es una notificación de prueba.</textarea>' "$work_dir/authenticated"
grep -q 'name="target_url"' "$work_dir/authenticated"
grep -q '>Suscripciones guardadas</h2>' "$work_dir/authenticated"
grep -q 'href="notificaciones-prueba.php" aria-current="page"' "$work_dir/authenticated"
! grep -q 'p256dh' "$work_dir/authenticated"
! grep -q 'endpoint_hash' "$work_dir/authenticated"
! grep -q 'WEB_PUSH_VAPID_PRIVATE_KEY' "$work_dir/authenticated"

status="$(curl -sS -o "$work_dir/home" -w '%{http_code}' "$base_url/")"
test "$status" = '200'
grep -q '>Administración</a>' "$work_dir/home"
! grep -q 'data-push-notifications' "$work_dir/home"
! grep -q 'Notificarme cuando esté por salir la Luna' "$work_dir/home"

printf 'OK web push admin HTTP\n'
