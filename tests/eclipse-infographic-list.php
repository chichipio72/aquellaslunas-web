<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/astronomy-events.php';
require_once __DIR__ . '/../includes/store-admin-auth.php';

startStoreAdminSession();
$_SESSION[STORE_ADMIN_SESSION_KEY] = true;
$case = $argv[1] ?? '';
$cases = [
    'lunar' => ['start' => '2026-03-01', 'end' => '2026-03-05', 'type' => 'lunar', 'lat' => '35.6762', 'lon' => '139.6503', 'timezone' => 'Asia/Tokyo', 'name' => 'Tokio', 'url' => 'type=lunar_eclipse&amp;date=2026-03-03', 'button' => true],
    'solar' => ['start' => '2027-02-04', 'end' => '2027-02-08', 'type' => 'solar', 'lat' => '-34.6037', 'lon' => '-58.3816', 'timezone' => 'America/Argentina/Buenos_Aires', 'name' => 'Buenos Aires', 'url' => 'type=solar_eclipse&amp;date=2027-02-06', 'button' => true],
    'hidden' => ['start' => '2027-02-04', 'end' => '2027-02-08', 'type' => 'solar', 'lat' => '35.6762', 'lon' => '139.6503', 'timezone' => 'Asia/Tokyo', 'name' => 'Tokio', 'url' => 'type=solar_eclipse', 'button' => false],
];
if (!isset($cases[$case])) throw new RuntimeException('Caso de listado inválido.');
$fixture = $cases[$case];
$cache = &astronomyEventSourceRequestCache();
$cache['eclipse'] = 'php';
$_COOKIE = [
    'astro_latitude' => $fixture['lat'], 'astro_longitude' => $fixture['lon'], 'astro_elevation' => '0',
    'astro_timezone' => $fixture['timezone'], 'astro_location_mode' => 'manual', 'astro_location_name' => $fixture['name'],
    'astro_location_confirmed' => '1', 'astro_location_intro_seen' => '1', 'astro_location_version' => '2',
];
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/astro/eclipses.php';
$_GET = ['filters_submitted' => '1', 'start_date' => $fixture['start'], 'end_date' => $fixture['end'], 'type_filter' => $fixture['type'], 'visibility_filter' => 'all'];
ob_start();
require __DIR__ . '/../eclipses.php';
$html = (string) ob_get_clean();
$hasCanonicalLink = str_contains($html, 'Crear infografía') && str_contains($html, $fixture['url']);
if ($hasCanonicalLink !== $fixture['button']) throw new RuntimeException('La elegibilidad del enlace en Eclipses no corresponde al caso ' . $case . '.');
echo 'OK eclipse infographic list ' . $case . PHP_EOL;
