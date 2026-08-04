#!/bin/sh
set -eu

base_url="${1:-http://localhost/explorador}"
location='lat=-34.6037&lon=-58.3816&timezone=America%2FArgentina%2FBuenos_Aires'
query="modo=extremos&variable=moon_distance_geocentric&tipo_extremo=ambos&fecha_desde=2020-01-01&fecha_hasta=2020-12-31&$location"

payload="$(curl -fsS "$base_url/api/series.php?$query")"
docker exec web-astro php -r '$d=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR);if($d["modo"]!=="extremos"||$d["counts"]["maximos"]<1||$d["counts"]["minimos"]<1||isset($d["rows"]))exit(1);' "$payload"

status="$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/api/series.php?$query&fases=full_moon")"
[ "$status" = "400" ]
status="$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/api/series.php?modo=extremos&variable=moon_azimuth&tipo_extremo=ambos&fecha_desde=2020-01-01&fecha_hasta=2020-12-31&$location")"
[ "$status" = "400" ]

curl -N -fsS "$base_url/api/series-stream.php?$query" \
    | docker exec -i web-astro php -r '$start=false;$detect=false;$complete=false;while(($l=fgets(STDIN))!==false){$m=json_decode($l,true,512,JSON_THROW_ON_ERROR);$start=$start||($m["type"]==="start"&&$m["mode"]==="extremos"&&$m["selected_dates"]===368);$detect=$detect||($m["type"]==="stage"&&$m["stage"]==="detecting_extrema");$complete=$complete||($m["type"]==="complete"&&$m["result"]["modo"]==="extremos");}if(!$start||!$detect||!$complete)exit(1);'

echo "Local extrema HTTP smoke tests: OK"
