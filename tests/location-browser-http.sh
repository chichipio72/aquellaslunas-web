#!/bin/sh
set -eu

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
trap 'rm -r "$work_dir"' EXIT

# Primera visita: ubicación inicial y ayuda visibles.
curl -sS -D "$work_dir/first-headers" -o "$work_dir/first-body" "$base_url/index.php"
grep -Fq 'data-location-intro' "$work_dir/first-body"
grep -Fq 'Elegí tu ubicación' "$work_dir/first-body"
grep -Fq 'Buenos Aires <span aria-hidden="true">·</span> ubicación inicial' "$work_dir/first-body"

# Cerrar sólo controla la ayuda: Buenos Aires sigue marcado como ubicación inicial.
curl -sS \
    -H 'Cookie: astro_location_intro_seen=1' \
    -o "$work_dir/dismissed-body" \
    "$base_url/index.php"
grep -Fq 'data-location-intro hidden' "$work_dir/dismissed-body"
grep -Fq 'ubicación inicial' "$work_dir/dismissed-body"

# Una selección manual completa también queda confirmada entre sesiones.
curl -sS \
    -D "$work_dir/manual-headers" \
    -o "$work_dir/manual-body" \
    -c "$work_dir/manual-cookies" \
    -X POST \
    --data-urlencode 'location_mode=manual' \
    --data-urlencode 'location_name=Vicente López' \
    --data-urlencode 'latitude=-34.525' \
    --data-urlencode 'longitude=-58.473' \
    --data-urlencode 'timezone=America/Argentina/Buenos_Aires' \
    "$base_url/ubicacion.php"
grep -Eqi '^Set-Cookie: astro_location_confirmed=1;.*Max-Age=34560000' "$work_dir/manual-headers"
curl -sS -b "$work_dir/manual-cookies" -o "$work_dir/manual-home" "$base_url/index.php"
grep -Fq 'Vicente López' "$work_dir/manual-home"
! grep -Fq 'data-location-intro' "$work_dir/manual-home"

# Selección equivalente a geolocalización: cookies persistentes y ayuda ausente al volver.
curl -sS \
    -D "$work_dir/headers" \
    -o "$work_dir/body" \
    -c "$work_dir/geolocation-cookies" \
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
grep -Eqi '^Set-Cookie: astro_location_confirmed=1;.*Max-Age=34560000' "$work_dir/headers"
grep -Eqi '^Set-Cookie: astro_location_intro_seen=1;.*Max-Age=34560000' "$work_dir/headers"
grep -Eqi '^Location: .*ubicacion\.php\?saved=1' "$work_dir/headers"
curl -sS -b "$work_dir/geolocation-cookies" -o "$work_dir/geolocation-home" "$base_url/index.php"
! grep -Fq 'data-location-intro' "$work_dir/geolocation-home"
grep -Fq 'Boulogne Sur Mer' "$work_dir/geolocation-home"

# Buenos Aires sólo deja de ser inicial cuando se confirma explícitamente.
curl -sS \
    -D "$work_dir/default-headers" \
    -o "$work_dir/default-body" \
    -c "$work_dir/default-cookies" \
    -X POST \
    --data-urlencode 'location_mode=default' \
    --data-urlencode 'location_name=Buenos Aires' \
    --data-urlencode 'latitude=-34.53' \
    --data-urlencode 'longitude=-58.48' \
    --data-urlencode 'timezone=America/Argentina/Buenos_Aires' \
    --data-urlencode 'return_to=/index.php' \
    "$base_url/ubicacion.php"
grep -Eqi '^Location: /index\.php' "$work_dir/default-headers"
grep -Eqi '^Set-Cookie: astro_location_confirmed=1;.*Max-Age=34560000' "$work_dir/default-headers"
curl -sS -b "$work_dir/default-cookies" -o "$work_dir/default-home" "$base_url/index.php"
! grep -Fq 'data-location-intro' "$work_dir/default-home"
! grep -Fq 'ubicación inicial' "$work_dir/default-home"

# Cookies inválidas vuelven al estado inicial, se limpian y no generan warnings.
curl -sS \
    -D "$work_dir/invalid-headers" \
    -o "$work_dir/invalid-body" \
    -H 'Cookie: astro_latitude=999; astro_longitude=-58.48; astro_timezone=Invalid%2FZone; astro_location_mode=broken; astro_location_name=Anterior; astro_location_intro_seen=1' \
    "$base_url/index.php"
grep -Fq 'data-location-intro' "$work_dir/invalid-body"
grep -Eqi '^Set-Cookie: astro_latitude=deleted;.*Max-Age=0' "$work_dir/invalid-headers"
grep -Eqi '^Set-Cookie: astro_location_intro_seen=deleted;.*Max-Age=0' "$work_dir/invalid-headers"

printf 'Primera visita, cierre, selección manual, geolocalización, Buenos Aires confirmado y cookies inválidas: OK\n'
