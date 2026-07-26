#!/usr/bin/env bash

set -Eeuo pipefail

readonly BASE_URL="${1:-http://localhost/altitude-profile.php}"
readonly VALID_QUERY='target=sun&date=2026-07-21&latitude=-34.53&longitude=-58.48&timezone=America%2FArgentina%2FBuenos_Aires'
test_tmp_dir="$(mktemp -d)"
trap 'rm -rf -- "$test_tmp_dir"' EXIT

request() {
    local method="$1"
    local headers_file="$test_tmp_dir/${method}.headers"
    local body_file="$test_tmp_dir/${method}.json"
    local request_url="$BASE_URL"
    local status
    if [[ "$method" == 'GET' ]]; then
        request_url="${BASE_URL}?${VALID_QUERY}"
    fi
    status="$(curl -sS -X "$method" -D "$headers_file" -o "$body_file" -w '%{http_code}' "$request_url")"

    if [[ "$method" == 'GET' ]]; then
        [[ "$status" == '200' ]] || { printf 'GET: se esperaba 200, se recibió %s\n' "$status" >&2; return 1; }
        grep -Eqi '^Content-Type: application/json; charset=utf-8[[:space:]]*$' "$headers_file"
        grep -Eqi '^Cache-Control:.*(^|[ ,])no-store([,[:space:]]|$)' "$headers_file"
        php -r '$payload = json_decode(file_get_contents($argv[1]), true); exit(is_array($payload) && ($payload["target"] ?? null) === "sun" && count($payload["series"] ?? []) === 3 ? 0 : 1);' "$body_file"
        printf 'GET: 200, JSON válido, 3 series, no-store\n'
        return
    fi

    [[ "$status" == '405' ]] || { printf '%s: se esperaba 405, se recibió %s\n' "$method" "$status" >&2; return 1; }
    grep -Eqi '^Allow: GET[[:space:]]*$' "$headers_file"
    grep -Eqi '^Content-Type: application/json; charset=utf-8[[:space:]]*$' "$headers_file"
    grep -Eqi '^Cache-Control:.*(^|[ ,])no-store([,[:space:]]|$)' "$headers_file"
    php -r '$payload = json_decode(file_get_contents($argv[1]), true); exit($payload === ["error" => "Método no permitido."] ? 0 : 1);' "$body_file"
    printf '%s: 405, Allow GET, JSON de error, no-store\n' "$method"
}

request GET
request POST
request PUT
request DELETE
