<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/astronomy-events.php';
require_once __DIR__ . '/../includes/store-admin-auth.php';

startStoreAdminSession();
$_SESSION[STORE_ADMIN_SESSION_KEY] = true;

$case = $argv[1] ?? '';
$cases = [
    'lunar-visible' => ['date' => '2026-03-03', 'type' => 'lunar_eclipse', 'lat' => '35.6762', 'lon' => '139.6503', 'timezone' => 'Asia/Tokyo', 'name' => 'Tokio', 'visible' => true],
    'lunar-partial' => ['date' => '2026-03-03', 'type' => 'lunar_eclipse', 'lat' => '0', 'lon' => '-60', 'timezone' => 'America/Guyana', 'name' => 'Georgetown', 'visible' => true],
    'solar-visible' => ['date' => '2027-02-06', 'type' => 'solar_eclipse', 'lat' => '-34.6037', 'lon' => '-58.3816', 'timezone' => 'America/Argentina/Buenos_Aires', 'name' => 'Buenos Aires', 'visible' => true],
    'solar-hidden' => ['date' => '2027-02-07', 'type' => 'solar_eclipse', 'lat' => '35.6762', 'lon' => '139.6503', 'timezone' => 'Asia/Tokyo', 'name' => 'Tokio', 'visible' => false],
];
if (!isset($cases[$case])) throw new RuntimeException('Caso de eclipse inválido.');
$fixture = $cases[$case];

$sourceCache = &astronomyEventSourceRequestCache();
$sourceCache['eclipse'] = 'php';
$_COOKIE = [
    'astro_latitude' => $fixture['lat'], 'astro_longitude' => $fixture['lon'], 'astro_elevation' => '0',
    'astro_timezone' => $fixture['timezone'], 'astro_location_mode' => 'manual',
    'astro_location_name' => $fixture['name'], 'astro_location_confirmed' => '1',
    'astro_location_intro_seen' => '1', 'astro_location_version' => '2',
];
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/astro/infografia-evento.php';
$_GET = ['type' => $fixture['type'], 'date' => $fixture['date']];

ob_start();
require __DIR__ . '/../infografia-evento.php';
$html = (string) ob_get_clean();

if ($fixture['visible']) {
    foreach (['data-event-infographic', 'data-infographic-eclipse-source', 'data-eclipse-capture', 'data-real-eclipse-payload', 'data-infographic-download'] as $expected) {
        if (!str_contains($html, $expected)) throw new RuntimeException($case . ' no contiene: ' . $expected);
    }
    if (str_contains($html, 'No podemos generar esta pieza')) throw new RuntimeException($case . ' fue bloqueado incorrectamente.');
    if ($case === 'lunar-visible' && !str_contains($html, 'Visible completo')) throw new RuntimeException('El eclipse lunar visible no informa visibilidad completa.');
    if ($case === 'lunar-partial' && !str_contains($html, 'Visible parcialmente')) throw new RuntimeException('El eclipse lunar parcial no informa su visibilidad.');
    if ($case === 'solar-visible' && !str_contains($html, 'Visible parcialmente')) throw new RuntimeException('El eclipse solar visible no informa su visibilidad local.');
} else {
    if (!str_contains($html, 'Este eclipse no es visible desde la ubicación actual.')) throw new RuntimeException('El caso no visible no explica el bloqueo.');
    if (str_contains($html, 'data-infographic-eclipse-source')) throw new RuntimeException('El caso no visible montó el simulador descargable.');
}

echo 'OK eclipse infographic page ' . $case . PHP_EOL;
