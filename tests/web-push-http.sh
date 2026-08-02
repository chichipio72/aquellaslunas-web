#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
endpoint='https://push.example.invalid/aquellas-lunas-http-test'

cleanup() {
    php -r 'require "/var/www/html/includes/web-database.php"; $statement=getWebDatabaseConnection()->prepare("DELETE FROM web_push_subscriptions WHERE endpoint = ?"); $statement->execute([$argv[1]]);' "$endpoint" >/dev/null 2>&1 || true
    rm -rf "$work_dir"
}
trap cleanup EXIT
php -r 'require "/var/www/html/includes/web-database.php"; $statement=getWebDatabaseConnection()->prepare("DELETE FROM web_push_subscriptions WHERE endpoint = ?"); $statement->execute([$argv[1]]);' "$endpoint"

status="$(curl -sS -o "$work_dir/get" -D "$work_dir/get-headers" -w '%{http_code}' "$base_url/web-push/subscribe.php")"
test "$status" = '405'
grep -Eqi '^Content-Type: application/json' "$work_dir/get-headers"
grep -Eqi '^Cache-Control: no-store' "$work_dir/get-headers"
grep -q '"ok":false' "$work_dir/get"

status="$(curl -sS -o "$work_dir/invalid" -w '%{http_code}' -H 'Content-Type: application/json' --data '{' "$base_url/web-push/subscribe.php")"
test "$status" = '400'

p256dh="B$(printf 'A%.0s' {1..86})"
auth="$(printf 'b%.0s' {1..22})"
payload="{\"endpoint\":\"$endpoint\",\"keys\":{\"p256dh\":\"$p256dh\",\"auth\":\"$auth\"}}"
for attempt in 1 2; do
    status="$(curl -sS -o "$work_dir/valid-$attempt" -w '%{http_code}' -H 'Content-Type: application/json' -H 'Origin: http://localhost' --data "$payload" "$base_url/web-push/subscribe.php")"
    test "$status" = '200'
    grep -q '"ok":true' "$work_dir/valid-$attempt"
done

count="$(php -r 'require "/var/www/html/includes/web-database.php"; $statement=getWebDatabaseConnection()->prepare("SELECT COUNT(*) FROM web_push_subscriptions WHERE endpoint = ? AND active = 1"); $statement->execute([$argv[1]]); echo $statement->fetchColumn();' "$endpoint")"
test "$count" = '1'

status="$(curl -sS -o "$work_dir/unsubscribe" -w '%{http_code}' -H 'Content-Type: application/json' -H 'Origin: http://localhost' --data "{\"endpoint\":\"$endpoint\"}" "$base_url/web-push/unsubscribe.php")"
test "$status" = '200'
grep -q '"ok":true' "$work_dir/unsubscribe"
active="$(php -r 'require "/var/www/html/includes/web-database.php"; $statement=getWebDatabaseConnection()->prepare("SELECT active FROM web_push_subscriptions WHERE endpoint = ?"); $statement->execute([$argv[1]]); echo $statement->fetchColumn();' "$endpoint")"
test "$active" = '0'

printf 'OK web push HTTP\n'
