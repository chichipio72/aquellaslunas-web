<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/api-config.php';
require_once __DIR__ . '/../includes/site-sections.php';
require_once __DIR__ . '/../includes/photography-links.php';
function photographyLinkTest(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$location = ['latitude' => -34.6037, 'longitude' => -58.3816, 'elevation_meters' => 0.0, 'timezone' => 'America/Argentina/Buenos_Aires'];
$conjunction = photographyEventUrl(['type' => 'conjunction', 'subtype' => 'mars', 'datetime' => '2026-09-06T19:28:08+00:00'], $location);
photographyLinkTest(is_string($conjunction) && str_contains($conjunction, 'objects=mars') && str_contains($conjunction, 'aim=automatic'), 'La conjunción no prepara Fotografía.');
parse_str((string) parse_url((string) $conjunction, PHP_URL_QUERY), $conjunctionQuery);
photographyLinkTest(($conjunctionQuery['event_time'] ?? '') === '2026-09-06T16:28:08-03:00' && ($conjunctionQuery['time'] ?? '') === '13:15', 'La conjunción Luna–Marte no separa máximo canónico y momento observable.');
photographyLinkTest(abs((float) ($conjunctionQuery['observation_moon_altitude'] ?? 0) - 3.0) < 0.01 && (float) ($conjunctionQuery['observation_object_altitude'] ?? 0) > 7.0, 'El momento observable no conserva margen sobre el horizonte.');
$horizon = photographyEventUrl(['type' => 'full_moon_observation', 'datetime' => '2026-08-29T07:52:34-03:00', 'details' => ['moon_event' => 'moonset', 'moon_event_time' => '2026-08-29T07:52:34-03:00']], $location, new DateTimeImmutable('2026-08-29T07:40:00-03:00'));
photographyLinkTest(is_string($horizon) && str_contains($horizon, 'horizon=1') && str_contains($horizon, 'event_type=moonset') && str_contains($horizon, 'observation_time='), 'El evento de horizonte no conserva su instante y convención.');
parse_str((string) parse_url((string) $horizon, PHP_URL_QUERY), $moonsetQuery);
photographyLinkTest(($moonsetQuery['event_time'] ?? '') === '2026-08-29T07:52:34-03:00', 'La puesta perdió su instante astronómico canónico.');
photographyLinkTest(($moonsetQuery['observation_time'] ?? '') === '2026-08-29T07:51:34-03:00' && ($moonsetQuery['time'] ?? '') === '07:51', 'La vista de la puesta no se desplazó exactamente un minuto hacia mayor visibilidad.');
$riseLink = photographyEventUrl(['type' => 'full_moon_observation', 'datetime' => '2026-08-28T19:03:12-03:00', 'details' => ['moon_event' => 'moonrise', 'moon_event_time' => '2026-08-28T19:03:12-03:00']], $location);
parse_str((string) parse_url((string) $riseLink, PHP_URL_QUERY), $moonriseQuery);
photographyLinkTest(($moonriseQuery['event_time'] ?? '') === '2026-08-28T19:03:12-03:00' && ($moonriseQuery['observation_time'] ?? '') === '2026-08-28T19:04:12-03:00' && ($moonriseQuery['time'] ?? '') === '19:04', 'La vista de la salida no se desplazó exactamente un minuto hacia mayor visibilidad.');
$lunarDayCalculator = new \AstronomyEngine\LunarDayCalculator(new \AstronomyEngine\MeeusLunarCalculator());
$lunarDay = $lunarDayCalculator->calculate(new DateTimeImmutable('2026-08-29 00:00:00', new DateTimeZone($location['timezone'])), $location['latitude'], $location['longitude'], $location['elevation_meters']);
photographyLinkTest($lunarDay->moonset instanceof DateTimeImmutable, 'No se obtuvo la puesta lunar del caso contractual.');
$moonsetScene = photographyAstronomicalScene($lunarDay->moonset, $location, [], null, ['type' => 'moonset']);
photographyLinkTest($moonsetScene['moon']['above_horizon'] === true, 'La puesta canónica volvió a quedar no visible.');
photographyLinkTest(abs((float) $moonsetScene['horizon']['relative_y_degrees'] + (float) $moonsetScene['moon']['topocentric_semidiameter_degrees']) < 0.001, 'El limbo superior no toca el horizonte efectivo en la puesta.');
$lunarRiseDay = $lunarDayCalculator->calculate(new DateTimeImmutable('2026-08-28 00:00:00', new DateTimeZone($location['timezone'])), $location['latitude'], $location['longitude'], $location['elevation_meters']);
photographyLinkTest($lunarRiseDay->moonrise instanceof DateTimeImmutable, 'No se obtuvo una salida lunar para verificar la geometría inversa.');
$moonriseScene = photographyAstronomicalScene($lunarRiseDay->moonrise, $location, [], null, ['type' => 'moonrise']);
photographyLinkTest(abs((float) $moonriseScene['horizon']['relative_y_degrees'] + (float) $moonriseScene['moon']['topocentric_semidiameter_degrees']) < 0.001, 'El limbo superior no toca el horizonte efectivo en la salida.');
$eclipse = photographyEventUrl(['type' => 'eclipse', 'subtype' => 'lunar_eclipse', 'datetime' => '2026-09-03T01:20:00-03:00', 'details' => ['eclipse_local' => ['visibility_classification' => 'visible_total']]], $location);
photographyLinkTest(is_string($eclipse) && str_contains($eclipse, 'scene=eclipse') && !str_contains($eclipse, 'focal='), 'El eclipse impone focal o pierde su escena.');
photographyLinkTest(str_contains((string) $eclipse, 'event_type=eclipse') && str_contains((string) $eclipse, 'event_time='), 'El eclipse no transmite su instante canónico.');
parse_str((string) parse_url((string) $eclipse, PHP_URL_QUERY), $eclipseQuery);
photographyLinkTest(($eclipseQuery['event_time'] ?? '') === ($eclipseQuery['observation_time'] ?? ''), 'El eclipse recibió indebidamente el desplazamiento fotográfico de horizonte.');
photographyLinkTest(photographyEventUrl(['type' => 'eclipse', 'subtype' => 'solar_eclipse', 'datetime' => '2026-09-03T01:20:00-03:00', 'details' => ['solar_eclipse_local' => ['visibility_classification' => 'not_visible']]], $location) === null, 'Se enlazó un eclipse no visible localmente.');
photographyLinkTest(photographyEventUrl(['type' => 'apsis', 'datetime' => '2026-09-04T01:00:00-03:00'], $location) === null, 'Se enlazó un evento sin preparación fotográfica definida.');
$tonightSceneLink = photographyTonightSceneUrl([
    'datetime' => new DateTimeImmutable('2026-08-16T19:25:00-03:00'), 'anchor_id' => 'venus', 'horizon_visible' => false,
    'objects' => [['id' => 'venus'], ['id' => 'saturn']],
], $location);
parse_str((string) parse_url((string) $tonightSceneLink, PHP_URL_QUERY), $tonightSceneQuery);
photographyLinkTest(($tonightSceneQuery['time'] ?? '') === '19:25' && ($tonightSceneQuery['observation_time'] ?? '') === '2026-08-16T19:25:00-03:00', 'La escena de esta noche no conserva exactamente la hora del esquema.');
photographyLinkTest(($tonightSceneQuery['objects'] ?? '') === 'venus,saturn' && ($tonightSceneQuery['event_object'] ?? '') === 'venus', 'La escena de esta noche no conserva sus astros y ancla.');
echo "Photography event links passed.\n";
