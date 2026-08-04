#!/bin/sh
set -eu

base_url="${1:-http://localhost/explorador}"
location='lat=-34.6037&lon=-58.3816&timezone=America%2FArgentina%2FBuenos_Aires'

daily="$(curl -fsS "$base_url/api/series.php?fecha_desde=2020-01-01&fecha_hasta=2020-01-03&$location&campos=moon_illumination")"
docker exec web-astro php -r '$d=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR); if(count($d["rows"])!==3||$d["date_selection"]["mode"]!=="daily")exit(1);' "$daily"

full="$(curl -fsS "$base_url/api/series.php?fecha_desde=2020-01-01&fecha_hasta=2020-12-31&$location&campos=moon_illumination&fases=full_moon")"
docker exec web-astro php -r '$d=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR); if(count($d["rows"])!==13||$d["date_selection"]["selected_dates"]!==13)exit(1);' "$full"

four="$(curl -fsS "$base_url/api/series.php?fecha_desde=2011-01-01&fecha_hasta=2020-12-31&$location&campos=moon_illumination&fases=new_moon,first_quarter,full_moon,last_quarter")"
docker exec web-astro php -r '$d=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR); if(count($d["rows"])!==495||$d["date_selection"]["calendar_days"]!==3653)exit(1);' "$four"

two="$(curl -fsS "$base_url/api/series.php?fecha_desde=2020-01-01&fecha_hasta=2020-12-31&$location&campos=moon_illumination&fases=new_moon,full_moon")"
docker exec web-astro php -r '$d=json_decode($argv[1],true,512,JSON_THROW_ON_ERROR); if(count($d["rows"])!==25)exit(1);' "$two"

status="$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/api/series.php?fecha_desde=2020-01-01&fecha_hasta=2020-12-31&$location&campos=moon_illumination&fases=invalid")"
[ "$status" = "400" ]

curl -N -fsS "$base_url/api/series-stream.php?fecha_desde=2020-01-01&fecha_hasta=2020-12-31&$location&campos=moon_illumination,sun_altitude&fases=new_moon,full_moon" \
    | docker exec -i web-astro php -r '$start=null;$complete=null;$progress=false;$half=false;$last=0.0;while(($l=fgets(STDIN))!==false){$m=json_decode($l,true,512,JSON_THROW_ON_ERROR);if($m["type"]==="start")$start=$m;if($m["type"]==="progress"){$progress=true;if($m["total_days"]!==25||$m["overall_percent"]<$last)exit(1);$last=$m["overall_percent"];$half=$half||($last>=40&&$last<=60);}if($m["type"]==="complete")$complete=$m;}if(!$progress||!$half||count($start["providers"])!==2||$start["calendar_days"]!==366||$start["selected_dates"]!==25||count($complete["result"]["rows"])!==25)exit(1);'

echo "Phase HTTP smoke tests: OK"
