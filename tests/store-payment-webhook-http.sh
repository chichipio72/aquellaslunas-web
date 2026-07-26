#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://127.0.0.1:18081}"
work_dir="$(mktemp -d)"
server_pid=""
cleanup() {
    if [[ -n "$server_pid" ]]; then
        kill "$server_pid" 2>/dev/null || true
        wait "$server_pid" 2>/dev/null || true
    fi
    rm -rf "$work_dir"
}
trap cleanup EXIT

if [[ "$#" -eq 0 ]]; then
    export MERCADO_PAGO_MODE=test
    export MERCADO_PAGO_ACCESS_TOKEN=mock-http-token
    export MERCADO_PAGO_PUBLIC_KEY=mock-http-public-key
    export MERCADO_PAGO_WEBHOOK_SECRET=mock-http-webhook-secret
    export MERCADO_PAGO_SUCCESS_URL=https://aquellaslunas.com.ar/astro/tienda/pago-exitoso.php
    export MERCADO_PAGO_PENDING_URL=https://aquellaslunas.com.ar/astro/tienda/pago-pendiente.php
    export MERCADO_PAGO_FAILURE_URL=https://aquellaslunas.com.ar/astro/tienda/pago-fallido.php
    export MERCADO_PAGO_NOTIFICATION_URL=https://aquellaslunas.com.ar/astro/webhooks/mercado-pago.php
    export STORE_DOWNLOAD_EXPIRY_HOURS=0
    php -S 127.0.0.1:18081 -t /var/www/html >"$work_dir/server.log" 2>&1 &
    server_pid="$!"
    for _ in {1..20}; do
        curl -sS -o /dev/null "$base_url/webhooks/mercado-pago.php" 2>/dev/null && break || sleep 0.1
    done
fi

status="$(curl -sS -o "$work_dir/get-body" -D "$work_dir/get-headers" -w '%{http_code}' "$base_url/webhooks/mercado-pago.php")"
test "$status" = "405"
grep -Eqi '^Allow: POST' "$work_dir/get-headers"
grep -q 'Método no permitido' "$work_dir/get-body"

status="$(curl -sS -o "$work_dir/malformed" -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data '{' "$base_url/webhooks/mercado-pago.php")"
test "$status" = "200"
grep -q '"status":"ignored"' "$work_dir/malformed"

status="$(curl -sS -o "$work_dir/empty" -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data '{}' "$base_url/webhooks/mercado-pago.php")"
test "$status" = "200"
grep -q '"status":"ignored"' "$work_dir/empty"

status="$(curl -sS -o "$work_dir/auxiliary" -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data '{}' "$base_url/webhooks/mercado-pago.php?data.id=ABC123&type=merchant_order")"
test "$status" = "200"
grep -q '"status":"ignored"' "$work_dir/auxiliary"

status="$(curl -sS -o "$work_dir/unauthorized" -w '%{http_code}' -X POST \
    -H 'Content-Type: application/json' \
    -H 'X-Request-Id: request-http-test' \
    -H 'X-Signature: ts=1753200000,v1=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' \
    --data '{"type":"payment","data":{"id":"123456789"}}' \
    "$base_url/webhooks/mercado-pago.php?data.id=123456789&type=payment")"
test "$status" = "401"
grep -q 'Notificación no autorizada' "$work_dir/unauthorized"

timestamp=1753200000
request_id=request-http-valid-signature
data_id=123456789
manifest="id:${data_id};request-id:${request_id};ts:${timestamp};"
signature_hash="$(php -r 'echo hash_hmac("sha256", $argv[1], "mock-http-webhook-secret");' "$manifest")"
status="$(curl -sS -o "$work_dir/unavailable" -w '%{http_code}' -X POST \
    -H 'Content-Type: application/json' \
    -H "X-Request-Id: $request_id" \
    -H "X-Signature: ts=$timestamp,v1=$signature_hash" \
    --data '{"action":"payment.updated"}' \
    "$base_url/webhooks/mercado-pago.php?data.id=$data_id&type=payment")"
test "$status" = "503"
grep -q 'Servicio temporalmente no disponible' "$work_dir/unavailable"
trace_id="$(sed -n 's/.*"trace_id":"\([a-f0-9]\{32\}\)".*/\1/p' "$work_dir/unavailable")"
test -n "$trace_id"
grep -q "\"request_trace_id\":\"$trace_id\"" "$work_dir/server.log"
grep -q '"stage":"config_loaded"' "$work_dir/server.log"
grep -q '"stage":"secret_diagnostic"' "$work_dir/server.log"
grep -q '"notification_source":"unknown"' "$work_dir/server.log"
grep -q '"secret_length":24' "$work_dir/server.log"
grep -q '"secret_trimmed_length":24' "$work_dir/server.log"
grep -q '"secret_source":"environment"' "$work_dir/server.log"
grep -Eq '"secret_sha256_prefix":"[a-f0-9]{12}"' "$work_dir/server.log"
grep -q '"stage":"signature_comparison"' "$work_dir/server.log"
grep -q '"custom_valid":true' "$work_dir/server.log"
grep -q '"official_sdk_valid":true' "$work_dir/server.log"
grep -q '"stage":"signature_valid"' "$work_dir/server.log"
grep -q '"stage":"signature_diagnostic"' "$work_dir/server.log"
grep -q '"signature_component_count":2' "$work_dir/server.log"
grep -q '"signature_v1_length":64' "$work_dir/server.log"
grep -q '"stage":"exception_type"' "$work_dir/server.log"
grep -q '"exception_message":"La configuración de descargas de la tienda no está disponible."' "$work_dir/server.log"
grep -q '"exception_file":"api-config.php"' "$work_dir/server.log"
grep -Eq '"exception_line":[0-9]+' "$work_dir/server.log"
grep -q '"stage":"finished"' "$work_dir/server.log"

if grep -Eqi 'mock-http-token|mock-http-webhook-secret|Authorization|Bearer' "$work_dir/get-body" "$work_dir/malformed" "$work_dir/auxiliary" "$work_dir/unauthorized" "$work_dir/unavailable" "$work_dir/server.log"; then
    echo 'El endpoint expuso credenciales.' >&2
    exit 1
fi

printf 'store payment webhook HTTP tests: ok\n'
