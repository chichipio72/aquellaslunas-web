<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/astronomy-events.php';
require_once __DIR__ . '/../includes/store-admin-auth.php';

startStoreAdminSession();
$_SESSION[STORE_ADMIN_SESSION_KEY] = true;

$sourceCache = &astronomyEventSourceRequestCache();
$sourceCache['lunar_conjunction'] = 'api';

$_COOKIE = [
    'astro_latitude' => '-34.6037', 'astro_longitude' => '-58.3816', 'astro_elevation' => '0',
    'astro_timezone' => 'America/Argentina/Buenos_Aires', 'astro_location_mode' => 'manual',
    'astro_location_name' => 'Buenos Aires', 'astro_location_confirmed' => '0',
    'astro_location_intro_seen' => '1', 'astro_location_version' => '2',
];
$_SERVER['HTTP_HOST'] = 'localhost';
$surface = $argv[1] ?? '';

ob_start();
if ($surface === 'events') {
    $_SERVER['SCRIPT_NAME'] = '/astro/eventos.php';
    $_GET = [
        'start_date' => '2026-10-04', 'days' => '3',
        'types' => ['conjunction'], 'filters_submitted' => '1',
    ];
    require __DIR__ . '/../eventos.php';
} elseif ($surface === 'generator') {
    $_SERVER['SCRIPT_NAME'] = '/astro/infografia-evento.php';
    $_GET = ['type' => 'lunar_conjunction', 'date' => '2026-10-05', 'target' => 'mars'];
    require __DIR__ . '/../infografia-evento.php';
} else {
    throw new RuntimeException('Superficie de prueba inválida.');
}
$html = (string) ob_get_clean();

if ($surface === 'events') {
    if (!str_contains($html, 'La Luna cerca de Marte') && !str_contains($html, 'Marte y la Luna')) {
        throw new RuntimeException('La conjunción real Luna–Marte no apareció en Eventos.');
    }
    if (!str_contains($html, 'infografia-evento.php?type=lunar_conjunction&amp;date=2026-10-05&amp;target=mars')) {
        throw new RuntimeException('La conjunción real visible_nearby no mostró Crear infografía con su enlace canónico.');
    }
} else {
    foreach (['data-event-infographic', 'Mirá alrededor de las 05:51', 'Está visible, baja hacia el noreste.', 'data-infographic-download', 'event-infographic-renderer.js', 'data-infographic-moon-source', 'data-moon-three-payload'] as $expected) {
        if (!str_contains($html, $expected)) throw new RuntimeException('El generador real no contiene: ' . $expected);
    }
    if (str_contains($html, 'El enlace de esta infografía no es válido.')) {
        throw new RuntimeException('El generador rechazó el enlace real visible_nearby.');
    }
    if (str_contains($html, 'Confirmá tu ubicación')) {
        throw new RuntimeException('El generador exigió confirmación para un contexto de ubicación utilizable.');
    }
}

echo 'OK event infographic API ' . $surface . PHP_EOL;
