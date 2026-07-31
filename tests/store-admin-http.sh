#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

status="$(curl -sS -o "$work_dir/admin-index" -D "$work_dir/admin-index-headers" -w '%{http_code}' "$base_url/admin/")"
test "$status" = "303"
grep -Eqi '^Location: login\.php' "$work_dir/admin-index-headers"
grep -Eqi '^Cache-Control: no-store' "$work_dir/admin-index-headers"
grep -Eqi '^X-Robots-Tag: noindex' "$work_dir/admin-index-headers"
! grep -qi 'Index of /admin' "$work_dir/admin-index"

status="$(curl -sS -o "$work_dir/admin-index-explicit" -D "$work_dir/admin-index-explicit-headers" -w '%{http_code}' "$base_url/admin/index.php")"
test "$status" = "303"
grep -Eqi '^Location: login\.php' "$work_dir/admin-index-explicit-headers"

session_id="$(runuser -u www-data -- php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("admin-http-" . bin2hex(random_bytes(12))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/admin-authenticated" -D "$work_dir/admin-authenticated-headers" -w '%{http_code}' "$base_url/admin/")"
test "$status" = "200"
grep -Eqi '^Cache-Control: no-store' "$work_dir/admin-authenticated-headers"
grep -Eqi '^X-Robots-Tag: noindex' "$work_dir/admin-authenticated-headers"
grep -q '<h1>Administración</h1>' "$work_dir/admin-authenticated"
grep -q 'Galería y tienda' "$work_dir/admin-authenticated"
grep -q 'Laboratorio astronómico' "$work_dir/admin-authenticated"
grep -q 'href="fotos.php"' "$work_dir/admin-authenticated"
grep -q 'href="laboratorio-astronomico.php"' "$work_dir/admin-authenticated"
grep -q 'href="index.php" aria-current="page"' "$work_dir/admin-authenticated"
grep -q 'class="store-admin-navigation"' "$work_dir/admin-authenticated"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/login-authenticated" -D "$work_dir/login-authenticated-headers" -w '%{http_code}' "$base_url/admin/login.php")"
test "$status" = "303"
grep -Eqi '^Location: index\.php' "$work_dir/login-authenticated-headers"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/admin-photos-authenticated" -w '%{http_code}' "$base_url/admin/fotos.php")"
test "$status" = "200"
grep -q 'href="fotos.php" aria-current="page"' "$work_dir/admin-photos-authenticated"
grep -q 'href="index.php"' "$work_dir/admin-photos-authenticated"
grep -q 'href="laboratorio-astronomico.php"' "$work_dir/admin-photos-authenticated"
grep -q 'class="store-admin-navigation"' "$work_dir/admin-photos-authenticated"
grep -q 'name="action" value="batch_price"' "$work_dir/admin-photos-authenticated"
grep -q 'name="action" value="individual_price"' "$work_dir/admin-photos-authenticated"
grep -q 'name="action" value="editorial_metadata"' "$work_dir/admin-photos-authenticated"
grep -q 'name="description"' "$work_dir/admin-photos-authenticated"
grep -q 'name="keywords"' "$work_dir/admin-photos-authenticated"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/admin-laboratory-authenticated" -D "$work_dir/admin-laboratory-authenticated-headers" -w '%{http_code}' "$base_url/admin/laboratorio-astronomico.php")"
test "$status" = "200"
grep -q '<h1>Laboratorio astronómico</h1>' "$work_dir/admin-laboratory-authenticated"
grep -q 'href="laboratorio-astronomico.php" aria-current="page"' "$work_dir/admin-laboratory-authenticated"
grep -q 'href="index.php"' "$work_dir/admin-laboratory-authenticated"
grep -q 'href="fotos.php"' "$work_dir/admin-laboratory-authenticated"
grep -q 'class="store-admin-navigation"' "$work_dir/admin-laboratory-authenticated"
grep -q 'name="fecha_desde"' "$work_dir/admin-laboratory-authenticated"
grep -q 'name="fecha_hasta"' "$work_dir/admin-laboratory-authenticated"
grep -q 'value="distancia_luna_km" checked' "$work_dir/admin-laboratory-authenticated"
grep -q 'value="iluminacion_porc" checked' "$work_dir/admin-laboratory-authenticated"
grep -q 'value="hora_salida_luna"' "$work_dir/admin-laboratory-authenticated"
grep -q 'value="hora_puesta_luna"' "$work_dir/admin-laboratory-authenticated"
grep -q 'value="hora_salida_sol"' "$work_dir/admin-laboratory-authenticated"
grep -q 'value="hora_puesta_sol"' "$work_dir/admin-laboratory-authenticated"
! grep -q 'value="hora_fase_lunar"' "$work_dir/admin-laboratory-authenticated"
grep -q 'class="astronomy-laboratory__body astronomy-laboratory__body--moon"' "$work_dir/admin-laboratory-authenticated"
grep -q 'class="astronomy-laboratory__body astronomy-laboratory__body--sun"' "$work_dir/admin-laboratory-authenticated"
grep -q 'data-year-select="fecha_desde"' "$work_dir/admin-laboratory-authenticated"
grep -q 'data-year-select="fecha_hasta"' "$work_dir/admin-laboratory-authenticated"
grep -q '<option value="1900"' "$work_dir/admin-laboratory-authenticated"
grep -q '<option value="2100"' "$work_dir/admin-laboratory-authenticated"
grep -q 'data-range-years="1"' "$work_dir/admin-laboratory-authenticated"
grep -q 'data-range-years="5"' "$work_dir/admin-laboratory-authenticated"
grep -q 'data-range-years="10"' "$work_dir/admin-laboratory-authenticated"
php -r '$html = file_get_contents($argv[1]); if (!preg_match("/data-daily-range-shortcuts.*?data-range-years=\"20\"/s", $html)) exit(1);' "$work_dir/admin-laboratory-authenticated"
php -r '$html = file_get_contents($argv[1]); if (!preg_match("/data-daily-range-shortcuts.*?data-future-range-years=\"1\".*?data-future-range-years=\"5\".*?data-future-range-years=\"10\".*?data-future-range-years=\"20\"/s", $html)) exit(1);' "$work_dir/admin-laboratory-authenticated"
grep -q 'value="duracion_dia"' "$work_dir/admin-laboratory-authenticated"
grep -q 'value="tiempo_luna_sobre_horizonte"' "$work_dir/admin-laboratory-authenticated"
grep -q 'value="superluna_llena"' "$work_dir/admin-laboratory-authenticated"
grep -q 'class="astronomy-laboratory__field-pair"' "$work_dir/admin-laboratory-authenticated"
php -r '$html = file_get_contents($argv[1]); if (!preg_match("/astronomy-laboratory__field-pair.*?value=\"hora_salida_luna\".*?value=\"hora_puesta_luna\"/s", $html)) exit(1);' "$work_dir/admin-laboratory-authenticated"
grep -q '<legend>Fases lunares</legend>' "$work_dir/admin-laboratory-authenticated"
grep -q 'name="fases\[\]" value="Cuarto menguante"' "$work_dir/admin-laboratory-authenticated"
! grep -q 'name="fases\[\]" value="Otros"' "$work_dir/admin-laboratory-authenticated"
grep -q 'data-astronomy-laboratory-chart' "$work_dir/admin-laboratory-authenticated"
grep -q 'echarts@6.1.0' "$work_dir/admin-laboratory-authenticated"
grep -q 'name="modo" value="diario" checked' "$work_dir/admin-laboratory-authenticated"
grep -q 'name="modo" value="extremos"' "$work_dir/admin-laboratory-authenticated"
grep -q 'name="tipo_extremo" value="ambos" checked' "$work_dir/admin-laboratory-authenticated"
grep -q 'data-extrema-range-shortcuts' "$work_dir/admin-laboratory-authenticated"
grep -q 'data-range-years="20"' "$work_dir/admin-laboratory-authenticated"
php -r '$html = file_get_contents($argv[1]); if (!preg_match("/data-extrema-range-shortcuts.*?data-future-range-years=\"5\".*?data-future-range-years=\"10\".*?data-future-range-years=\"20\"/s", $html)) exit(1);' "$work_dir/admin-laboratory-authenticated"
grep -q 'data-range-all' "$work_dir/admin-laboratory-authenticated"
php -r '$html = file_get_contents($argv[1]); if (!preg_match("/<select name=\"variable_extremos\">(.*?)<\\/select>/s", $html, $matches)) exit(1); foreach (["distancia_luna_km", "amplitud_salida_luna", "amplitud_puesta_luna", "tiempo_luna_sobre_horizonte", "diferencia_salida_luna_min", "duracion_dia", "amplitud_salida_sol", "amplitud_puesta_sol"] as $field) { if (!str_contains($matches[1], "value=\"$field\"")) exit(1); } foreach (["hora_salida_luna", "iluminacion_porc", "fase_lunar", "angulo_nodo_sol"] as $field) { if (str_contains($matches[1], "value=\"$field\"")) exit(1); }' "$work_dir/admin-laboratory-authenticated"
grep -Eqi '<meta name="robots" content="noindex,nofollow,noarchive">' "$work_dir/admin-laboratory-authenticated"
grep -Eqi '^Cache-Control: no-store' "$work_dir/admin-laboratory-authenticated-headers"
grep -Eqi '^X-Robots-Tag: noindex, nofollow, noarchive' "$work_dir/admin-laboratory-authenticated-headers"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-valid" -D "$work_dir/api-valid-headers" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2026-01-01' --data-urlencode 'fecha_hasta=2026-01-03' --data-urlencode 'campos=distancia_luna_km,iluminacion_porc' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
grep -Eqi '^Content-Type: application/json' "$work_dir/api-valid-headers"
grep -Eqi '^Cache-Control: no-store' "$work_dir/api-valid-headers"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); if ($data["modo"] !== "diario" || $data["columns"] !== ["fecha", "distancia_luna_km", "iluminacion_porc"] || $data["field_types"] !== ["fecha" => "date", "distancia_luna_km" => "number", "iluminacion_porc" => "number"] || !is_array($data["rows"])) { exit(1); } $dates = array_column($data["rows"], "fecha"); $sorted = $dates; sort($sorted); if ($dates !== $sorted) { exit(1); }' "$work_dir/api-valid"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-extrema-both" -w '%{http_code}' --get --data-urlencode 'modo=extremos' --data-urlencode 'fecha_desde=2020-01-01' --data-urlencode 'fecha_hasta=2025-12-31' --data-urlencode 'variable=distancia_luna_km' --data-urlencode 'tipo_extremo=ambos' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); if ($data["modo"] !== "extremos" || $data["variable"] !== "distancia_luna_km" || $data["series_labels"] !== ["maximos" => "Apogeos", "minimos" => "Perigeos"] || $data["conteos"]["maximos"] !== count($data["series"]["maximos"]) || $data["conteos"]["minimos"] !== count($data["series"]["minimos"]) || $data["conteos"]["maximos"] < 1 || $data["conteos"]["minimos"] < 1) exit(1); foreach (array_merge($data["series"]["maximos"], $data["series"]["minimos"]) as $point) { if ($point["valor"] === null) exit(1); }' "$work_dir/api-extrema-both"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-extrema-maximums" -w '%{http_code}' --get --data-urlencode 'modo=extremos' --data-urlencode 'fecha_desde=2020-01-01' --data-urlencode 'fecha_hasta=2025-12-31' --data-urlencode 'variable=distancia_luna_km' --data-urlencode 'tipo_extremo=maximo' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); if ($data["conteos"]["maximos"] < 1 || $data["conteos"]["minimos"] !== 0 || $data["series"]["minimos"] !== []) exit(1);' "$work_dir/api-extrema-maximums"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-extrema-minimums" -w '%{http_code}' --get --data-urlencode 'modo=extremos' --data-urlencode 'fecha_desde=2020-01-01' --data-urlencode 'fecha_hasta=2025-12-31' --data-urlencode 'variable=distancia_luna_km' --data-urlencode 'tipo_extremo=minimo' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); if ($data["conteos"]["minimos"] < 1 || $data["conteos"]["maximos"] !== 0 || $data["series"]["maximos"] !== []) exit(1);' "$work_dir/api-extrema-minimums"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-extrema-short-moon" -w '%{http_code}' --get --data-urlencode 'modo=extremos' --data-urlencode 'fecha_desde=2025-01-01' --data-urlencode 'fecha_hasta=2026-12-31' --data-urlencode 'variable=distancia_luna_km' --data-urlencode 'tipo_extremo=ambos' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "400"
grep -q 'al menos 2 años' "$work_dir/api-extrema-short-moon"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-extrema-warning" -w '%{http_code}' --get --data-urlencode 'modo=extremos' --data-urlencode 'fecha_desde=2022-01-01' --data-urlencode 'fecha_hasta=2025-12-31' --data-urlencode 'variable=distancia_luna_km' --data-urlencode 'tipo_extremo=ambos' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
grep -q 'se recomienda un período de al menos 5 años' "$work_dir/api-extrema-warning"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-extrema-short-sun" -w '%{http_code}' --get --data-urlencode 'modo=extremos' --data-urlencode 'fecha_desde=2021-01-01' --data-urlencode 'fecha_hasta=2025-12-31' --data-urlencode 'variable=duracion_dia' --data-urlencode 'tipo_extremo=ambos' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "400"
grep -q 'al menos 5 años' "$work_dir/api-extrema-short-sun"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-extrema-invalid-variable" -w '%{http_code}' --get --data-urlencode 'modo=extremos' --data-urlencode 'fecha_desde=2020-01-01' --data-urlencode 'fecha_hasta=2025-12-31' --data-urlencode 'variable=iluminacion_porc' --data-urlencode 'tipo_extremo=ambos' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "400"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-extrema-invalid-type" -w '%{http_code}' --get --data-urlencode 'modo=extremos' --data-urlencode 'fecha_desde=2020-01-01' --data-urlencode 'fecha_hasta=2025-12-31' --data-urlencode 'variable=distancia_luna_km' --data-urlencode 'tipo_extremo=todos' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "400"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-invalid-mode" -w '%{http_code}' --get --data-urlencode 'modo=mezclado' --data-urlencode 'fecha_desde=2020-01-01' --data-urlencode 'fecha_hasta=2025-12-31' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "400"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-time-phase" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2100-12-27' --data-urlencode 'fecha_hasta=2100-12-31' --data-urlencode 'campos=hora_salida_luna,hora_puesta_luna' --data-urlencode 'fases=Luna nueva,Cuarto creciente' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); if ($data["field_types"]["hora_salida_luna"] !== "time_fraction" || $data["field_types"]["hora_puesta_luna"] !== "time_fraction" || $data["field_scales"]["hora_salida_luna"] !== "time_fraction" || $data["field_scales"]["hora_puesta_luna"] !== "time_fraction" || count($data["rows"]) !== 1 || $data["rows"][0]["fecha"] !== "2100-12-30") { exit(1); } $expected = ((20 * 3600) + (16 * 60)) / 86400; if (abs($data["rows"][0]["hora_puesta_luna"] - $expected) > 0.000000001) { exit(1); }' "$work_dir/api-time-phase"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-shared-distance-axis" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2026-01-01' --data-urlencode 'fecha_hasta=2026-01-02' --data-urlencode 'campos=distancia_luna_km,distancia_sol_km' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); if (array_values(array_unique($data["field_scales"])) !== ["distance_km"] || $data["scale_groups"]["distance_km"]["label"] !== "Distancia (km)") exit(1);' "$work_dir/api-shared-distance-axis"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-derived" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2026-01-01' --data-urlencode 'fecha_hasta=2026-01-01' --data-urlencode 'campos=duracion_dia,duracion_noche,tiempo_luna_sobre_horizonte,amplitud_salida_luna,amplitud_puesta_luna,amplitud_salida_sol,amplitud_puesta_sol' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); $types = $data["field_types"]; foreach (["duracion_dia", "duracion_noche", "tiempo_luna_sobre_horizonte"] as $field) { if ($types[$field] !== "time_duration") exit(1); } $row = $data["rows"][0]; $expected = ["duracion_dia" => 865 / 1440, "duracion_noche" => 575 / 1440, "tiempo_luna_sobre_horizonte" => 567 / 1440, "amplitud_salida_luna" => 54.75515463754561 - 90, "amplitud_puesta_luna" => 303.909475477407 - 270, "amplitud_salida_sol" => 118.98066241646298 - 90, "amplitud_puesta_sol" => 241.08522346324435 - 270]; foreach ($expected as $field => $value) { if (abs($row[$field] - $value) > 0.000000001) exit(1); } if ($data["field_units"]["amplitud_salida_luna"] !== "°") exit(1);' "$work_dir/api-derived"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-moon-no-cross" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2100-12-30' --data-urlencode 'fecha_hasta=2100-12-30' --data-urlencode 'campos=tiempo_luna_sobre_horizonte' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); if (abs($data["rows"][0]["tiempo_luna_sobre_horizonte"] - (924 / 1440)) > 0.000000001) exit(1);' "$work_dir/api-moon-no-cross"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-derived-null" -w '%{http_code}' --get --data-urlencode 'fecha_desde=1900-01-25' --data-urlencode 'fecha_hasta=1900-01-25' --data-urlencode 'campos=tiempo_luna_sobre_horizonte' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); if ($data["rows"][0]["tiempo_luna_sobre_horizonte"] !== null) exit(1);' "$work_dir/api-derived-null"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-supermoon" -w '%{http_code}' --get --data-urlencode 'fecha_desde=1933-06-07' --data-urlencode 'fecha_hasta=1933-06-09' --data-urlencode 'campos=superluna_llena' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); if ($data["field_types"]["superluna_llena"] !== "event_marker" || $data["event_thresholds"]["population"] !== 2486 || $data["event_thresholds"]["from_year"] !== 1900 || $data["event_thresholds"]["to_year"] !== 2100) exit(1); $markers = array_values(array_filter($data["rows"], fn($row) => $row["superluna_llena"] !== null)); if (count($markers) !== 1 || $markers[0]["fecha"] !== "1933-06-08" || $markers[0]["superluna_llena"] !== 1.0 || $markers[0]["_event_marker_details"]["superluna_llena"]["distancia_luna_km"] !== 350583.0) exit(1);' "$work_dir/api-supermoon"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-minimoon" -w '%{http_code}' --get --data-urlencode 'fecha_desde=1915-12-20' --data-urlencode 'fecha_hasta=1915-12-22' --data-urlencode 'campos=miniluna_llena' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); $markers = array_values(array_filter($data["rows"], fn($row) => $row["miniluna_llena"] !== null)); if (count($markers) !== 1 || $markers[0]["fecha"] !== "1915-12-21" || $markers[0]["miniluna_llena"] !== 1.0 || $markers[0]["_event_marker_details"]["miniluna_llena"]["distancia_luna_km"] !== 403510.0) exit(1);' "$work_dir/api-minimoon"

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-invalid-date" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2026-02-30' --data-urlencode 'fecha_hasta=2026-03-01' --data-urlencode 'campos=distancia_luna_km' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "400"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-invalid-field" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2026-01-01' --data-urlencode 'fecha_hasta=2026-01-02' --data-urlencode 'campos=distancia_luna_km,password' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "400"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-invalid-derived" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2026-01-01' --data-urlencode 'fecha_hasta=2026-01-02' --data-urlencode 'campos=duracion_inventada' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "400"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-removed-time" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2026-01-01' --data-urlencode 'fecha_hasta=2026-01-02' --data-urlencode 'campos=hora_fase_lunar' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "400"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-too-many-scales" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2026-01-01' --data-urlencode 'fecha_hasta=2026-01-02' --data-urlencode 'campos=distancia_luna_km,iluminacion_porc,azimut_salida_luna,diferencia_salida_luna_min,dia_ciclo_lunar' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "400"
grep -q 'más de cuatro grupos de escala' "$work_dir/api-too-many-scales"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-invalid-phase" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2026-01-01' --data-urlencode 'fecha_hasta=2026-01-02' --data-urlencode 'campos=distancia_luna_km' --data-urlencode 'fases=Luna azul' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "400"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/api-long-range" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2016-01-01' --data-urlencode 'fecha_hasta=2026-01-02' --data-urlencode 'campos=distancia_luna_km' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "200"
php -r '$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); if (count($data["rows"]) <= 3650) exit(1);' "$work_dir/api-long-range"

csrf_token="$(php -r '$html = file_get_contents($argv[1]); if (!preg_match("/name=\"csrf_token\" value=\"([a-f0-9]{64})\"/", $html, $matches)) exit(1); echo $matches[1];' "$work_dir/admin-authenticated")"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/logout-valid" -D "$work_dir/logout-valid-headers" -w '%{http_code}' -X POST --data-urlencode "csrf_token=$csrf_token" "$base_url/admin/logout.php")"
test "$status" = "303"
grep -Eqi '^Location: login\.php' "$work_dir/logout-valid-headers"
status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/admin-after-logout" -D "$work_dir/admin-after-logout-headers" -w '%{http_code}' "$base_url/admin/")"
test "$status" = "303"
grep -Eqi '^Location: login\.php' "$work_dir/admin-after-logout-headers"

status="$(curl -sS -c "$work_dir/cookies" -o "$work_dir/login" -D "$work_dir/login-headers" -w '%{http_code}' "$base_url/admin/login.php")"
test "$status" = "200"
grep -Eqi '^Cache-Control: no-store' "$work_dir/login-headers"
grep -Eqi '^X-Robots-Tag: noindex' "$work_dir/login-headers"
grep -Eqi '^Set-Cookie: .*HttpOnly.*SameSite=Lax' "$work_dir/login-headers"

status="$(curl -sS -b "$work_dir/cookies" -o "$work_dir/invalid-csrf" -w '%{http_code}' -X POST --data 'csrf_token=invalid&user=x&password=x' "$base_url/admin/login.php")"
test "$status" = "400"

status="$(curl -sS -o "$work_dir/fotos" -D "$work_dir/headers" -w '%{http_code}' "$base_url/admin/fotos.php")"
test "$status" = "303"
grep -Eqi '^Location: login\.php' "$work_dir/headers"
grep -Eqi '^Cache-Control: no-store' "$work_dir/headers"
grep -Eqi '^X-Robots-Tag: noindex' "$work_dir/headers"

status="$(curl -sS -o "$work_dir/laboratory" -D "$work_dir/laboratory-headers" -w '%{http_code}' "$base_url/admin/laboratorio-astronomico.php")"
test "$status" = "303"
grep -Eqi '^Location: login\.php' "$work_dir/laboratory-headers"
grep -Eqi '^Cache-Control: no-store' "$work_dir/laboratory-headers"
grep -Eqi '^X-Robots-Tag: noindex, nofollow, noarchive' "$work_dir/laboratory-headers"

status="$(curl -sS -o "$work_dir/api-unauthorized" -D "$work_dir/api-unauthorized-headers" -w '%{http_code}' --get --data-urlencode 'fecha_desde=2026-01-01' --data-urlencode 'fecha_hasta=2026-01-02' --data-urlencode 'campos=distancia_luna_km' "$base_url/admin/api/datos-astronomicos.php")"
test "$status" = "401"
grep -Eqi '^Content-Type: application/json' "$work_dir/api-unauthorized-headers"
grep -q '"error":"Autenticación requerida."' "$work_dir/api-unauthorized"

status="$(curl -sS -o "$work_dir/logout" -D "$work_dir/logout-headers" -w '%{http_code}' "$base_url/admin/logout.php")"
test "$status" = "405"
grep -Eqi '^Allow: POST' "$work_dir/logout-headers"

status="$(curl -sS -o "$work_dir/script" -w '%{http_code}' "$base_url/scripts/check-store-database.php")"
test "$status" = "403"

status="$(curl -sS -o "$work_dir/include" -w '%{http_code}' "$base_url/includes/store-admin-auth.php")"
test "$status" = "403"

status="$(curl -sS -o "$work_dir/test" -w '%{http_code}' "$base_url/tests/store-admin.php")"
test "$status" = "403"

grep -Fq "type: isEvent ? 'scatter' : 'line'" assets/js/astronomy-laboratory.js
grep -Fq 'smooth: false' assets/js/astronomy-laboratory.js
grep -Fq '.astronomy-laboratory__bodies {' assets/css/styles.css
grep -Fq '.astronomy-laboratory__field-pair {' assets/css/styles.css
grep -Fq 'grid-template-columns: repeat(2, minmax(0, 1fr));' assets/css/styles.css
grep -Fq 'width: calc(100% - 2rem);' assets/css/styles.css
grep -Fq '@media (max-width: 64rem)' assets/css/styles.css
grep -Fq '@media (max-width: 44rem)' assets/css/styles.css
grep -Fq '@media (min-width: 110rem)' assets/css/styles.css
grep -Fq '.astronomy-laboratory__segmented-control {' assets/css/styles.css
grep -Fq '.astronomy-laboratory__extrema-fields {' assets/css/styles.css

printf 'store admin HTTP tests: ok\n'
