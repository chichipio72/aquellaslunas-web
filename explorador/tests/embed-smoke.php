<?php

declare(strict_types=1);

function embedCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$_GET = [
    'v' => '1', 'modo' => 'diario', 'desde' => '2026-01-01', 'hasta' => '2026-12-31',
    'lat' => '-34.6037', 'lon' => '-58.3816', 'tz' => 'America/Argentina/Buenos_Aires',
    'ubicacion' => 'Buenos Aires', 'campos' => 'moon_ecliptic_longitude,moon_node_angle',
    'dias' => 'todos', 'alto' => '640', 'titulo' => 'Ciclos lunares',
];
$_SERVER['SCRIPT_NAME'] = '/astro/explorador/embed.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/embed.php';

ob_start();
require dirname(__DIR__) . '/embed.php';
$html = ob_get_clean();
embedCheck(is_string($html) && $html !== '', 'El embed no se renderizó.');

foreach (['explorer-embed-page', 'data-explorer-embed="true"', 'data-explorer-full-path="/astro/explorador/"',
    '--embed-chart-height:640px', '>Ciclos lunares<', 'id="embed-open-explorer"',
    'id="process-panel"', 'id="cancel-calculation"', 'id="chart"', 'assets/embed.css?v=1',
    '<meta name="robots" content="noindex, follow">'] as $fragment) {
    embedCheck(str_contains($html, $fragment), 'Falta el contrato embed: ' . $fragment);
}
foreach (['class="site-header"', 'class="site-footer"', 'id="metrics"', 'id="technical-metrics"',
    'googletagmanager.com', 'assets/js/location.js'] as $fragment) {
    embedCheck(!str_contains($html, $fragment), 'El embed muestra una superficie excluida: ' . $fragment);
}

$css = file_get_contents(dirname(__DIR__) . '/assets/embed.css');
$javascript = file_get_contents(dirname(__DIR__) . '/assets/explorador.js');
embedCheck(is_string($css) && str_contains($css, 'overflow-x: hidden')
    && str_contains($css, '.explorer-embed-page .variable-picker')
    && str_contains($css, '.explorer-embed-page #change-location'),
    'Los controles estructurales o el overflow no están cubiertos por el CSS compacto.');
embedCheck(is_string($javascript)
    && str_contains($javascript, 'restoreLockedEmbedConfiguration()')
    && str_contains($javascript, 'updateEmbedExplorerLink()')
    && str_contains($javascript, '!parsed.present || !parsed.valid'),
    'El controlador no fija configuración, actualiza el enlace o rechaza URLs inválidas.');
embedCheck(!str_contains($javascript, 'embed.php?'), 'El embed contiene un backend alternativo.');

echo "Embed smoke tests: OK\n";
