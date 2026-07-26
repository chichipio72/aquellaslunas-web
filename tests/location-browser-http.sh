#!/bin/sh
set -eu

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
trap 'rm -r "$work_dir"' EXIT

curl -sS \
    -D "$work_dir/headers" \
    -o "$work_dir/body" \
    -X POST \
    --data-urlencode 'location_mode=geolocation' \
    --data-urlencode 'location_name=Boulogne Sur Mer' \
    --data-urlencode 'latitude=-34.4990' \
    --data-urlencode 'longitude=-58.5751' \
    --data-urlencode 'timezone=America/Buenos_Aires' \
    "$base_url/ubicacion.php"

grep -Eqi '^HTTP/[0-9.]+ 303' "$work_dir/headers"
grep -Eqi '^Set-Cookie: astro_location_mode=geolocation;' "$work_dir/headers"
grep -Eqi '^Set-Cookie: astro_latitude=-34\.499;' "$work_dir/headers"
grep -Eqi '^Set-Cookie: astro_longitude=-58\.5751;' "$work_dir/headers"
grep -Eqi '^Set-Cookie: astro_timezone=America%2FArgentina%2FBuenos_Aires;' "$work_dir/headers"
grep -Eqi '^Set-Cookie: astro_location_name=[^;]+;' "$work_dir/headers"
grep -Eqi '^Location: .*ubicacion\.php\?saved=1' "$work_dir/headers"

printf 'POST global con alias IANA: ubicación y nombre conservados\n'
