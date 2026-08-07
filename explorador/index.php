<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/interfaz-base.php';
$html = ob_get_clean();
if (!is_string($html)) {
    throw new RuntimeException('No se pudo preparar la interfaz del Explorador.');
}

$html = str_replace(
    '<link rel="stylesheet" href="assets/explorador.css?v=20260804-1">',
    '<link rel="stylesheet" href="assets/explorador.css?v=20260804-1">' . "\n"
        . '    <link rel="stylesheet" href="assets/pruebas-interfaz.css?v=20260805-3&amp;layout=12">',
    $html
);
$html = str_replace('<body class="explorer-page"', '<body class="explorer-page interface-test-page"', $html);
$html = preg_replace_callback(
    '#<div class="date-grid">\s*'
        . '<label>Fecha desde(?<from_date><input name="fecha_desde"[^>]*>)</label>\s*'
        . '<label>Año desde\s*(?<from_help>.*?)'
        . '(?<from_year><input name="anio_desde"[^>]*>)</label>\s*'
        . '<label>Fecha hasta(?<to_date><input name="fecha_hasta"[^>]*>)</label>\s*'
        . '<label>Año hasta\s*(?<to_help>.*?)'
        . '(?<to_year><input name="anio_hasta"[^>]*>)</label>\s*'
        . '</div>#s',
    static function (array $match): string {
        $input = static function (string $inputHtml, string $name): string {
            return str_replace('name="' . $name . '"', 'id="interface-' . $name . '" name="' . $name . '"', $inputHtml);
        };
        return '<div class="date-grid interface-period-grid">'
            . '<label class="interface-period-label" for="interface-fecha_desde">Fecha desde</label>'
            . '<div class="interface-period-field">' . $input($match['from_date'], 'fecha_desde') . '</div>'
            . '<label class="interface-period-label" for="interface-anio_desde"><span>Año desde</span>' . trim($match['from_help']) . '</label>'
            . '<div class="interface-period-field">' . $input($match['from_year'], 'anio_desde') . '</div>'
            . '<label class="interface-period-label" for="interface-fecha_hasta">Fecha hasta</label>'
            . '<div class="interface-period-field">' . $input($match['to_date'], 'fecha_hasta') . '</div>'
            . '<label class="interface-period-label" for="interface-anio_hasta"><span>Año hasta</span>' . trim($match['to_help']) . '</label>'
            . '<div class="interface-period-field">' . $input($match['to_year'], 'anio_hasta') . '</div>'
            . '</div>';
    },
    $html,
    1
) ?? $html;
$html = preg_replace(
    '#(<script src="assets/explorador\.js\?v=[^"]+"></script>)#',
    '<script src="assets/pruebas-interfaz.js?v=20260805-3&amp;layout=12"></script>' . "\n" . '$1',
    $html,
    1
) ?? $html;

echo $html;
