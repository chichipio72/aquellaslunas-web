<?php

function capabilitiesAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/que-podes-hacer.php';
ob_start();
require __DIR__ . '/../que-podes-hacer.php';
$html = ob_get_clean();
$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$text = preg_replace('/\s+/u', ' ', $text);
capabilitiesAssert(is_string($text), 'No se pudo obtener el texto visible.');

$source = file(__DIR__ . '/../docs/docs/contenido-que-podes-hacer.txt', FILE_IGNORE_NEW_LINES);
capabilitiesAssert(is_array($source), 'No se pudo leer el contenido fuente.');
foreach ($source as $line) {
    $line = trim($line);
    if ($line === '' || preg_match('/^[A-ZÁÉÍÓÚÑ ]+$/u', $line) === 1) {
        continue;
    }
    capabilitiesAssert(str_contains($text, $line), 'Falta el texto fuente: ' . $line);
}

foreach ([
    'Qué podés hacer en Aquellas Lunas',
    'El cielo hoy',
    'El cielo esta noche',
    'Calendario solar y lunar',
    'Eventos lunares',
    'Eclipses',
    'Planificador',
    'Tu ubicación',
    'Cálculos astronómicos propios',
    'Previsión del cielo',
    'Información simple y detalle técnico',
    'Agendar eventos',
    'Instalar Aquellas Lunas',
    'Una web para entender y disfrutar el cielo',
] as $heading) {
    capabilitiesAssert(str_contains($text, $heading), 'Falta el encabezado: ' . $heading);
}

$menuPosition = strpos($html, '>Qué ofrece Aquellas Lunas</a>');
$aboutPosition = strpos($html, '>Acerca del sitio</a>');
capabilitiesAssert($menuPosition !== false && $aboutPosition !== false && $menuPosition < $aboutPosition, 'La nueva entrada no está antes de Acerca del sitio.');
capabilitiesAssert(substr_count($html, 'class="card capability-card"') === 6, 'Cantidad incorrecta de tarjetas principales.');
capabilitiesAssert(substr_count($html, 'class="card capability-feature') === 6, 'Cantidad incorrecta de bloques especiales.');
capabilitiesAssert(str_contains($html, 'aria-current="page">Qué ofrece Aquellas Lunas</a>'), 'El menú no marca la página activa.');
capabilitiesAssert(str_contains($html, 'https://aquellaslunas.com.ar/astro/que-podes-hacer.php'), 'Falta canonical.');

echo "Página Qué podés hacer: OK\n";
