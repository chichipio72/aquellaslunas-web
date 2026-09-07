<?php

declare(strict_types=1);

function renderExplorerInterface(string $file, string $scriptName): string
{
    $_SERVER['SCRIPT_NAME'] = $scriptName;
    ob_start();
    require dirname(__DIR__) . '/' . $file;
    $html = ob_get_clean();
    return is_string($html) ? $html : '';
}

$mainHtml = renderExplorerInterface('index.php', '/astro/explorador/index.php');
if ($mainHtml === '') throw new RuntimeException('La interfaz principal no se renderizó.');
foreach (['interface-test-page', 'assets/pruebas-interfaz.css?v=20260902-1&amp;layout=13',
    'assets/pruebas-interfaz.js?v=20260902-1&amp;layout=13'] as $marker) {
    if (!str_contains($mainHtml, $marker)) throw new RuntimeException('Falta la interfaz compacta principal: ' . $marker);
}
if (stripos($mainHtml, 'prueba de interfaz') !== false) {
    throw new RuntimeException('La interfaz principal conserva texto de prueba.');
}
if (!str_contains($mainHtml, '/astro/ubicacion.php?return=%2Fastro%2Fexplorador%2F')) {
    throw new RuntimeException('El retorno de ubicación principal es incorrecto.');
}

$classicHtml = renderExplorerInterface('clasico.php', '/astro/explorador/clasico.php');
if ($classicHtml === '') throw new RuntimeException('La interfaz clásica no se renderizó.');
if (str_contains($classicHtml, 'interface-test-page') || str_contains($classicHtml, 'pruebas-interfaz.css')
    || str_contains($classicHtml, 'pruebas-interfaz.js')) {
    throw new RuntimeException('Los assets compactos alcanzaron la interfaz clásica.');
}
if (!str_contains($classicHtml, '/astro/ubicacion.php?return=%2Fastro%2Fexplorador%2Fclasico.php')) {
    throw new RuntimeException('El retorno de ubicación clásico es incorrecto.');
}
if (!str_contains($classicHtml, 'data-explorer-share-path="/astro/explorador/"')) {
    throw new RuntimeException('La interfaz clásica no comparte hacia la URL principal.');
}

$applicationJavascript = file_get_contents(dirname(__DIR__) . '/assets/explorador.js');
$compactJavascript = file_get_contents(dirname(__DIR__) . '/assets/pruebas-interfaz.js');
$compactCss = file_get_contents(dirname(__DIR__) . '/assets/pruebas-interfaz.css');
foreach (['api/series.php?', 'api/series-stream.php?'] as $endpoint) {
    if (!is_string($applicationJavascript) || !str_contains($applicationJavascript, $endpoint)) {
        throw new RuntimeException('No se reutiliza el endpoint existente: ' . $endpoint);
    }
}
foreach (['IntersectionObserver', 'window.echarts.init', 'grid.left = 38', 'grid.right = 24',
    'interface-clear-selection', 'interface-config-resize-handle', 'astronomyExplorerPanelWidth',
    'maximumPanelViewportRatio = 0.82', 'sharedConfigurationWillAutoExecute',
    'selectDataAttentionTimer', '3000', "form?.querySelector('#generate-embed-code')",
    'interface-embed-code-button', 'originalEmbedCode.click()'] as $behavior) {
    if (!is_string($compactJavascript) || !str_contains($compactJavascript, $behavior)) {
        throw new RuntimeException('Falta comportamiento compacto: ' . $behavior);
    }
}
foreach (['width: 100vw', '@media (max-width: 620px)',
    'grid-template-columns: repeat(2, minmax(0, 1fr))', 'container: interface-config / inline-size',
    '@container interface-config (min-width: 900px)', 'prefers-reduced-motion: reduce',
    'interface-select-data-attention 3s', 'interface-select-data-halo 1s ease-out 3',
    'border-color: #8bd8ff', ':has(.interface-embed-code-button)',
    'grid-template-columns: minmax(0, 1fr) repeat(4, auto)',
    'grid-template-columns: repeat(2, minmax(0, 1fr))'] as $rule) {
    if (!is_string($compactCss) || !str_contains($compactCss, $rule)) {
        throw new RuntimeException('Falta adaptación móvil compacta: ' . $rule);
    }
}
foreach ([$mainHtml, $classicHtml] as $html) {
    if (substr_count($html, 'assets/explorador.js?v=') !== 1
        || substr_count($html, '../vendor/frontend/echarts/5.6.0/echarts.min.js') !== 1) {
        throw new RuntimeException('Una interfaz duplicó la aplicación o ECharts.');
    }
    if (str_contains($html, 'name="lat"') || str_contains($html, 'name="lon"')) {
        throw new RuntimeException('Una interfaz reintrodujo campos editables de ubicación.');
    }
}

echo "Explorer interface swap: OK\n";
