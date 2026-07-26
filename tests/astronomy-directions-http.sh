#!/bin/sh
set -eu

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
trap 'rm -r "$work_dir"' EXIT

curl -sS -D "$work_dir/post-headers" -o "$work_dir/post-body" -X POST "$base_url/astronomy-directions.php"
grep -Eqi '^HTTP/[0-9.]+ 405' "$work_dir/post-headers"
grep -Eqi '^Allow: GET' "$work_dir/post-headers"
grep -q 'Método no permitido' "$work_dir/post-body"

curl -sS -D "$work_dir/invalid-headers" -o "$work_dir/invalid-body" \
  "$base_url/astronomy-directions.php?date=2051-01-01&time=99:00&latitude=91&longitude=181&timezone=UTCx"
grep -Eqi '^HTTP/[0-9.]+ 400' "$work_dir/invalid-headers"
grep -q 'Fecha u hora inválida' "$work_dir/invalid-body"
! grep -Eqi 'fastapi|traceback|host\.docker|18000' "$work_dir/invalid-body"

printf 'Proxy de direcciones: métodos, validación y errores públicos OK\n'

curl -sS -D "$work_dir/featured-post-headers" -o "$work_dir/featured-post-body" \
  -X POST "$base_url/astronomy-featured-dates.php"
grep -Eqi '^HTTP/[0-9.]+ 405' "$work_dir/featured-post-headers"
grep -Eqi '^Allow: GET' "$work_dir/featured-post-headers"

curl -sS -D "$work_dir/featured-invalid-headers" -o "$work_dir/featured-invalid-body" \
  "$base_url/astronomy-featured-dates.php?date=invalid&latitude=91&longitude=181&timezone=UTCx"
grep -Eqi '^HTTP/[0-9.]+ 400' "$work_dir/featured-invalid-headers"
! grep -Eqi 'fastapi|traceback|host\.docker|18000' "$work_dir/featured-invalid-body"

printf 'Proxy de fechas destacadas: métodos, validación y errores públicos OK\n'
