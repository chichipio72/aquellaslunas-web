#!/bin/sh
set -eu

base_url="${1:-http://localhost/explorador}"
query='fecha_desde=2020-01-01&fecha_hasta=2020-01-03&lat=-34.6037&lon=-58.3816&timezone=America%2FArgentina%2FBuenos_Aires&campos=moon_illumination'

valid="$(curl -fsS "$base_url/api/series.php?$query")"
docker exec web-astro php -r '$data=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR); foreach(["columns","field_metadata","request","metrics","rows"] as $key)if(!array_key_exists($key,$data))exit(1); if(count($data["rows"])!==3)exit(1);' "$valid"

status="$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/api/series.php?${query%moon_illumination}unknown_field")"
[ "$status" = "400" ]

status="$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/api/series.php?fecha_desde=2020-01-01&fecha_hasta=2020-01-03&lat=91&lon=0&timezone=UTC&campos=moon_illumination")"
[ "$status" = "400" ]

status="$(curl -sS -X POST -o /dev/null -w '%{http_code}' "$base_url/api/series.php")"
[ "$status" = "405" ]

echo "Endpoint HTTP smoke tests: OK"
