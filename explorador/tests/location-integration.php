<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/location-context.php';

function locationCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function renderExplorerWithCookies(array $cookies, string $scriptName): string
{
    $_COOKIE = $cookies;
    $_SERVER['SCRIPT_NAME'] = $scriptName;
    ob_start();
    require dirname(__DIR__) . '/index.php';
    $html = ob_get_clean();
    return is_string($html) ? $html : '';
}

$originalCookies = $_COOKIE;
$originalScriptName = $_SERVER['SCRIPT_NAME'] ?? null;
$confirmedCookies = [
    'astro_latitude' => '-34.52',
    'astro_longitude' => '-58.48',
    'astro_timezone' => 'America/Argentina/Buenos_Aires',
    'astro_location_mode' => 'manual',
    'astro_location_name' => 'Vicente López',
    'astro_location_confirmed' => '1',
];
$confirmedHtml = renderExplorerWithCookies($confirmedCookies, '/explorador/index.php');
$formStart = strpos($confirmedHtml, '<form id="explorer-form"');
$formEnd = $formStart === false ? false : strpos($confirmedHtml, '</form>', $formStart);
$confirmedForm = $formStart !== false && $formEnd !== false ? substr($confirmedHtml, $formStart, $formEnd - $formStart) : '';
locationCheck(str_contains($confirmedHtml, 'Vicente López'), 'No se mostró la etiqueta oficial.');
locationCheck(str_contains($confirmedHtml, '−34,5200°') && str_contains($confirmedHtml, '−58,4800°'),
    'No se mostraron las coordenadas oficiales.');
locationCheck($confirmedForm !== '' && !str_contains($confirmedForm, 'name="lat"') && !str_contains($confirmedForm, 'name="lon"')
    && !str_contains($confirmedForm, 'name="timezone"'), 'Persisten inputs propios de ubicación.');
locationCheck(str_contains($confirmedHtml, 'id="shared-location-source"'), 'Falta el estado de ubicación compartida.');
locationCheck(str_contains($confirmedHtml, '/ubicacion.php?return=%2Fexplorador%2F'), 'Retorno local incorrecto.');
locationCheck($_COOKIE === $confirmedCookies, 'Renderizar el Explorador modificó las cookies generales.');

$fallbackHtml = renderExplorerWithCookies([], '/astro/explorador/index.php');
locationCheck(str_contains($fallbackHtml, '−34,5300°') && str_contains($fallbackHtml, 'Buenos Aires'),
    'No se utilizó el fallback oficial.');
locationCheck(str_contains($fallbackHtml, '/astro/ubicacion.php?return=%2Fastro%2Fexplorador%2F'), 'Prefijo productivo incorrecto.');

locationCheck(astronomyLocationReturnPath('/explorador/', '/ubicacion.php') === '/explorador/', 'Se rechazó un retorno local válido.');
locationCheck(astronomyLocationReturnPath('/astro/explorador/', '/astro/ubicacion.php') === '/astro/explorador/',
    'Se rechazó un retorno productivo válido.');
foreach (['https://example.com/', '//example.com/', '/astro/../admin/', '/explorador/?token=x', "/explorador/\rX: y", '/other/'] as $unsafe) {
    locationCheck(astronomyLocationReturnPath($unsafe, '/astro/ubicacion.php') === null, 'Se aceptó un retorno inseguro: ' . $unsafe);
}
locationCheck(astronomyLocationReturnPath('https://example.com/', '/ubicacion.php') === null,
    'Se aceptó un retorno externo en una instalación raíz.');

$_COOKIE = $originalCookies;
if ($originalScriptName === null) unset($_SERVER['SCRIPT_NAME']); else $_SERVER['SCRIPT_NAME'] = $originalScriptName;
echo "Location integration tests: OK\n";
