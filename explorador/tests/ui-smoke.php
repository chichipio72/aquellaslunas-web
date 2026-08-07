<?php

declare(strict_types=1);

use Explorador\VariableCatalog;

require_once dirname(__DIR__) . '/includes/VariableCatalog.php';
require_once dirname(__DIR__, 2) . '/includes/store-admin-auth.php';

$adminMode = in_array('--admin', $argv ?? [], true);
unset($_COOKIE['aquellas_lunas_admin']);
if ($adminMode) {
    startStoreAdminSession();
    $_SESSION[STORE_ADMIN_SESSION_KEY] = true;
    $adminSessionId = session_id();
    session_write_close();
    $_COOKIE['aquellas_lunas_admin'] = $adminSessionId;
}

ob_start();
require dirname(__DIR__) . '/index.php';
$html = ob_get_clean();
if (!is_string($html)) throw new RuntimeException('No se pudo renderizar la interfaz.');
foreach (['prototipo local', 'Esta interfaz es técnica', 'AstronomyEngine PHP'] as $privateText) {
    if (stripos($html, $privateText) !== false) throw new RuntimeException('Texto técnico visible: ' . $privateText);
}
foreach (['Explorador astronómico | Aquellas Lunas',
    'Compará ciclos, posiciones y eventos del Sol y la Luna', 'class="site-header"', 'class="site-footer"'] as $publicText) {
    if (!str_contains($html, $publicText)) throw new RuntimeException('Integración pública ausente: ' . $publicText);
}

foreach (['name="fecha_desde"', 'name="fecha_hasta"', 'name="anio_desde"', 'name="anio_hasta"'] as $control) {
    if (substr_count($html, $control) !== 1) throw new RuntimeException('Control temporal ausente o duplicado: ' . $control);
}
if (substr_count($html, 'data-range-direction="backward"') !== 4
    || substr_count($html, 'data-range-direction="forward"') !== 4) {
    throw new RuntimeException('Cantidad incorrecta de rangos rápidos.');
}
$catalog = new VariableCatalog();
foreach (array_keys($catalog->all()) as $key) {
    if (preg_match('/<input[^>]+name="campos\[\]"[^>]+value="' . preg_quote($key, '/') . '"/', $html) !== 1) {
        throw new RuntimeException('Variable ausente o duplicada: ' . $key);
    }
}
if (substr_count($html, 'name="campos[]"') !== count($catalog->all())) {
    throw new RuntimeException('La cantidad de variables renderizadas no coincide con el catálogo.');
}
if (substr_count($html, 'id="help-variable-') !== count($catalog->all())
    || substr_count($html, 'class="context-help"') < count($catalog->all()) + 10) {
    throw new RuntimeException('Las ayudas contextuales no cubren variables y controles principales.');
}
preg_match_all('/<div class="variable-pair(?: variable-pair--single)?">/', $html, $pairMatches);
if (count($pairMatches[0]) !== 20) {
    throw new RuntimeException('La cantidad de parejas semánticas no coincide con el diseño esperado.');
}
foreach (['Ecuación del tiempo', 'Distancia angular Sol–Luna', 'Diámetro aparente de la Luna'] as $newVariableText) {
    if (!str_contains($html, $newVariableText)) {
        throw new RuntimeException('Falta la nueva variable instantánea: ' . $newVariableText);
    }
}
foreach (['Distancia Tierra–Sol', 'Distancia entre el centro de la Tierra y el Sol en la fecha indicada.'] as $solarDistanceText) {
    if (!str_contains($html, $solarDistanceText)) {
        throw new RuntimeException('Falta la presentación de distancia solar: ' . $solarDistanceText);
    }
}
foreach (['process-panel', 'process-track', 'process-stage', 'process-days', 'process-percent',
    'process-elapsed', 'cancel-calculation', 'all-days', 'relation-help', 'relation-selectors',
    'relation-variable-a', 'relation-variable-b', 'relation-phase-note', 'extrema-panel',
    'extrema-range-guidance', 'mode-notice', 'observation-location-title', 'change-location',
    'calculation-location-value', 'shared-location-source'] as $id) {
    if (substr_count($html, 'id="' . $id . '"') !== 1) {
        throw new RuntimeException('Control de progreso ausente o duplicado: ' . $id);
    }
}
foreach (['share-configuration', 'shared-configuration-status', 'share-dialog', 'share-url',
    'share-dialog-copy', 'share-dialog-close', 'share-dialog-status',
    'shared-configuration-notice', 'local-time-chart-help'] as $id) {
    if (substr_count($html, 'id="' . $id . '"') !== 1) {
        throw new RuntimeException('Control de URL compartible ausente o duplicado: ' . $id);
    }
}
foreach (['metrics-title', 'metrics', 'technical-metrics'] as $adminMetricId) {
    $count = substr_count($html, 'id="' . $adminMetricId . '"');
    if ($count !== ($adminMode ? 1 : 0)) {
        throw new RuntimeException('Visibilidad administrativa incorrecta para: ' . $adminMetricId);
    }
}
$formStart = strpos($html, '<form id="explorer-form"');
$formEnd = $formStart === false ? false : strpos($html, '</form>', $formStart);
$explorerForm = $formStart !== false && $formEnd !== false ? substr($html, $formStart, $formEnd - $formStart) : '';
if (!str_contains($html, 'data-official-location=') || $explorerForm === ''
    || str_contains($explorerForm, 'name="lat"') || str_contains($explorerForm, 'name="lon"')
    || str_contains($explorerForm, 'name="timezone"')) {
    throw new RuntimeException('La ubicación oficial no quedó integrada como contexto de sólo lectura.');
}
if (substr_count($html, 'name="modo"') !== 2 || substr_count($html, 'name="tipo_extremo"') !== 3
    || substr_count($html, 'data-minimum-years=') !== 13) {
    throw new RuntimeException('Los controles de Extremos locales no reflejan el catálogo.');
}
$css = file_get_contents(dirname(__DIR__) . '/assets/explorador.css');
$javascript = file_get_contents(dirname(__DIR__) . '/assets/explorador.js');
$dateTools = file_get_contents(dirname(__DIR__) . '/assets/date-controls.js');
if (substr_count($html, 'assets/time-series-segments.js?v=1') !== 1
    || !str_contains((string) $javascript, "metadata.scaleGroup === 'local_time'")
    || !str_contains((string) $javascript, 'timeSeriesSegments.splitAtMidnight(values)')
    || !str_contains((string) $javascript, "value === null || value === undefined ? 'Sin dato'")) {
    throw new RuntimeException('La segmentación visual de horas locales no está integrada.');
}
if (!is_string($css) || !str_contains($css, '.relation-panel[hidden] { display: none; }')) {
    throw new RuntimeException('El Análisis de relación no se oculta completamente en Extremos.');
}
if (!str_contains($css, '.relation-selectors[hidden] { display: none; }')) {
    throw new RuntimeException('Los selectores A/B no respetan el atributo hidden.');
}
foreach (['container-type: inline-size', '@container (max-width: 390px)',
    '@media (min-width: 1001px) and (max-width: 1599px)', '@media (max-width: 760px)',
    'overflow-wrap: anywhere'] as $responsiveRule) {
    if (!str_contains((string) $css, $responsiveRule)) {
        throw new RuntimeException('Falta una regla responsive de variables: ' . $responsiveRule);
    }
}
if (str_contains((string) $css, '.context-help-wrap:focus-within .context-help__content')
    || str_contains((string) $css, '.context-help-wrap:hover .context-help__content')
    || !str_contains((string) $css, '.context-help[aria-expanded="false"] + .context-help__content { display: none !important; }')
    || !str_contains((string) $css, '.explorer-shell .range-button')) {
    throw new RuntimeException('Las ayudas persistentes o los botones rápidos discretos no quedaron corregidos.');
}
if (!str_contains((string) $css, '.mode-options label > span')
    || str_contains((string) $css, '.mode-options span, .extrema-type-picker span')) {
    throw new RuntimeException('Los modos no preservan título y ayuda en una misma línea.');
}
if (!is_string($javascript) || !str_contains($javascript, 'modeDateState.switchTo(')
    || !is_string($dateTools) || !str_contains($dateTools, 'backwardRangeStart(currentTo, 2)')) {
    throw new RuntimeException('No se encontró el estado de fechas independiente por modo.');
}
$scaleGroups = require dirname(__DIR__) . '/catalog/scale-groups.php';
$catalogGroups = array_unique(array_column($catalog->all(), 'scaleGroup'));
foreach ($catalogGroups as $scaleGroup) {
    if (!isset($scaleGroups[$scaleGroup]['label'])) {
        throw new RuntimeException('Grupo de escala sin título público: ' . $scaleGroup);
    }
}
foreach (['Hora local', 'Duración', 'Azimut', 'Ángulo', 'Ángulo con signo', 'Altura',
    'Distancia lunar', 'Porcentaje', 'Diferencia diaria', 'Ascensión recta'] as $axisTitle) {
    if (!str_contains($html, $axisTitle)) {
        throw new RuntimeException('Título español de eje ausente: ' . $axisTitle);
    }
}
if (!str_contains((string) $javascript, 'name: scaleTitle(group)')
    || !str_contains((string) $javascript, 'name: scaleTitle(data.scale_group)')
    || !str_contains((string) $javascript, "nameRotate: index % 2 ? -90 : 90")
    || !str_contains((string) $javascript, 'offset: Math.floor(index / 2) * 54')
    || str_contains((string) $javascript, "group.replaceAll('_', ' ')")) {
    throw new RuntimeException('Los títulos de ejes no usan el catálogo explícito.');
}
if (substr_count($html, 'data-relation-method') !== 2
    || substr_count($html, 'assets/relation-analysis.js') !== 1) {
    throw new RuntimeException('Los métodos o el módulo del análisis de relación no se renderizaron correctamente.');
}
if (substr_count($html, 'name="fases[]"') !== 4) {
    throw new RuntimeException('La interfaz no contiene las cuatro fases principales.');
}
if (substr_count($html, 'assets/vertical-axis-control.js') !== 1
    || substr_count($html, 'Shift + rueda: zoom vertical') !== 1
    || !str_contains((string) $javascript, 'verticalAxisController?.update(')) {
    throw new RuntimeException('El control de zoom vertical no está integrado correctamente.');
}
if (substr_count($html, '../vendor/frontend/echarts/5.6.0/echarts.min.js') !== 1
    || str_contains($html, 'cdn.jsdelivr.net/npm/echarts')) {
    throw new RuntimeException('ECharts no se carga exclusivamente desde la copia local fijada.');
}
if (!str_contains((string) $javascript, 'copia local del sitio')) {
    throw new RuntimeException('Falta el aviso para una falla de la copia local de ECharts.');
}
$verticalControl = file_get_contents(dirname(__DIR__) . '/assets/vertical-axis-control.js');
if (!is_string($verticalControl) || !str_contains($verticalControl, "if (!event.shiftKey) return;")) {
    throw new RuntimeException('El control vertical no reserva las interacciones exclusivamente para Shift.');
}
if (!str_contains($verticalControl, "window.addEventListener('pointermove', onPointerMove, true)")
    || str_contains($verticalControl, "element.addEventListener('pointerleave', finishDrag")) {
    throw new RuntimeException('El arrastre vertical no mantiene la captura hasta soltar el puntero.');
}
foreach (['updateLocationDisplay()', "params.set('lat'", "params.set('lon'", "params.set('timezone'", 'cancelActiveRequest('] as $locationBehavior) {
    if (!str_contains((string) $javascript, $locationBehavior)) {
        throw new RuntimeException('Falta el comportamiento de ubicación de cálculo: ' . $locationBehavior);
    }
}
foreach (['latitudeInput', 'longitudeInput', 'timezoneInput', 'restoreOfficialLocation'] as $removedLocationControl) {
    if (str_contains((string) $javascript, $removedLocationControl)) {
        throw new RuntimeException('Persiste un control editable de ubicación: ' . $removedLocationControl);
    }
}
if (substr_count($html, 'assets/share-config.js?v=1') !== 1
    || !str_contains((string) $javascript, 'navigator.clipboard?.writeText')
    || !str_contains((string) $javascript, 'window.history.replaceState')
    || !str_contains((string) $javascript, 'form.requestSubmit()')) {
    throw new RuntimeException('La restauración o copia de URLs compartibles no está integrada.');
}
if (str_contains($html, 'class="share-panel"') || str_contains($html, 'id="share-manual-fallback"')
    || !str_contains($html, 'class="action-bar"')
    || !str_contains($html, '<dialog id="share-dialog"')
    || !str_contains((string) $javascript, 'shareDialog.showModal()')
    || !str_contains((string) $javascript, "shareDialog.addEventListener('cancel'")
    || !str_contains((string) $javascript, "shareDialog.addEventListener('close', () => shareButton.focus())")
    || !str_contains((string) $javascript, 'if (!controlState.showSelectors)')
    || !str_contains((string) $javascript, 'cancelButton.hidden = false')
    || !str_contains((string) $css, 'grid-template-columns: auto minmax(260px, 1fr) auto')) {
    throw new RuntimeException('La barra compacta, la modal o las reglas A/B no quedaron integradas.');
}
$actionStart = strpos($html, '<div class="action-bar">');
$calculatePosition = strpos($html, 'class="calculate-button"', $actionStart ?: 0);
$processPosition = strpos($html, 'id="process-panel"', $actionStart ?: 0);
$sharePosition = strpos($html, 'id="share-configuration"', $actionStart ?: 0);
if ($actionStart === false || $calculatePosition === false || $processPosition === false || $sharePosition === false
    || !($calculatePosition < $processPosition && $processPosition < $sharePosition)
    || !str_contains((string) $css, '.process-panel { grid-column: 1 / -1; grid-row: 2;')) {
    throw new RuntimeException('El orden o la adaptación responsive de la barra de acciones es incorrecto.');
}

if ($adminMode) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    session_name('aquellas_lunas_admin');
    session_id($adminSessionId);
    startStoreAdminSession();
    destroyStoreAdminSession();
}
echo 'UI smoke tests (' . ($adminMode ? 'admin' : 'public') . "): OK\n";
