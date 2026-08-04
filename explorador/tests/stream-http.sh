#!/bin/sh
set -eu

base_url="${1:-http://localhost/explorador}"
container_base_url="${2:-http://localhost/explorador}"
location='lat=-34.6037&lon=-58.3816&timezone=America%2FArgentina%2FBuenos_Aires'
short_query="fecha_desde=2020-01-01&fecha_hasta=2020-01-03&$location&campos=moon_illumination,moon_distance_geocentric"
year_query="fecha_desde=2020-01-01&fecha_hasta=2020-12-31&$location&campos=moonrise_time"
combined_query="fecha_desde=2020-01-01&fecha_hasta=2020-01-30&$location&campos=moon_illumination,sun_altitude"

curl -N -fsS "$base_url/api/series-stream.php?$short_query" \
    | docker exec -i web-astro php /var/www/html/explorador/tests/stream-consumer.php contract "$container_base_url/api/series.php?$short_query"

curl -N -fsS "$base_url/api/series-stream.php?$year_query" \
    | docker exec -i web-astro php /var/www/html/explorador/tests/stream-consumer.php timing

curl -N -fsS "$base_url/api/series-stream.php?$combined_query" \
    | docker exec -i web-astro php /var/www/html/explorador/tests/stream-consumer.php combined

echo "Endpoint streaming HTTP tests: OK"
