<?php

declare(strict_types=1);

// Render funcional compartido por las interfaces principal y clásica.
$directScript = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
if ($directScript !== false && $directScript === __FILE__) {
    http_response_code(404);
    exit;
}

use Explorador\VariableCatalog;

require_once __DIR__ . '/includes/VariableCatalog.php';
require_once __DIR__ . '/includes/PhaseEventDateProvider.php';
require_once dirname(__DIR__) . '/includes/location-context.php';
require_once dirname(__DIR__) . '/includes/site-header.php';
require_once dirname(__DIR__) . '/includes/site-footer.php';
require_once dirname(__DIR__) . '/includes/asset-url.php';
require_once dirname(__DIR__) . '/includes/favicon-links.php';
require_once dirname(__DIR__) . '/includes/analytics.php';
require_once dirname(__DIR__) . '/includes/seo.php';
require_once dirname(__DIR__) . '/includes/store-admin-auth.php';

sendDynamicNoCacheHeaders();

if (!function_exists('explorerContextHelp')) {
    function explorerContextHelp(string $id, string $text): void
    {
        ?><span class="context-help-wrap"><button class="context-help" type="button" aria-label="Más información" aria-describedby="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false">?</button><span id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" class="context-help__content" role="tooltip"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></span></span><?php
    }
}

$location = astronomyLocationContext();
$catalog = new VariableCatalog();
$variables = $catalog->all();
$scaleGroups = require __DIR__ . '/catalog/scale-groups.php';
$showTechnicalMetrics = storeAdminHasValidSessionCookie();
$extremaVariables = array_filter($variables, static fn(array $definition): bool => ($definition['extrema_supported'] ?? false) === true);
$variableSections = [
    ['title' => 'Luna', 'groups' => [
        ['title' => 'Horarios: salidas y puestas', 'pairs' => [
            ['moonrise_time', 'moonset_time'],
        ]],
        ['title' => 'Diferencias diarias de salidas y puestas', 'pairs' => [
            ['moonrise_daily_difference', 'moonset_daily_difference'],
        ]],
        ['title' => 'Amplitudes de salidas y puestas', 'pairs' => [
            ['moonrise_amplitude', 'moonset_amplitude'],
        ]],
        ['title' => 'Azimutes de salidas y puestas', 'pairs' => [
            ['moonrise_azimuth', 'moonset_azimuth'],
        ]],
        ['title' => 'Distancias y otras variables', 'wide' => true, 'pairs' => [
            ['moon_illumination', 'moon_phase_angle'],
            ['sun_moon_angular_distance'],
            ['moon_distance_geocentric', 'moon_distance_topocentric'],
            ['moon_apparent_diameter_arcmin'],
            ['moon_right_ascension', 'moon_declination'],
            ['moon_altitude', 'moon_azimuth'],
            ['moon_ecliptic_latitude'],
            ['moon_above_horizon_hours'],
        ]],
    ]],
    ['title' => 'Sol', 'groups' => [
        ['title' => 'Horarios: salidas y puestas', 'pairs' => [
            ['sunrise_time', 'sunset_time'],
        ]],
        ['title' => 'Diferencias diarias de salidas y puestas', 'pairs' => [
            ['sunrise_daily_difference', 'sunset_daily_difference'],
        ]],
        ['title' => 'Amplitudes de salidas y puestas', 'pairs' => [
            ['sunrise_amplitude', 'sunset_amplitude'],
        ]],
        ['title' => 'Azimutes de salidas y puestas', 'pairs' => [
            ['sunrise_azimuth', 'sunset_azimuth'],
        ]],
        ['title' => 'Distancias y otras variables', 'wide' => true, 'pairs' => [
            ['sun_altitude', 'sun_azimuth'],
            ['sun_distance_km'],
            ['sun_equation_of_time_minutes'],
            ['daylight_hours', 'night_hours'],
        ]],
    ]],
];
$arrangedVariables = [];
foreach ($variableSections as $section) {
    foreach ($section['groups'] as $group) {
        foreach ($group['pairs'] as $pair) {
            foreach ($pair as $key) $arrangedVariables[$key] = true;
        }
    }
}
$unarrangedVariables = array_diff_key($variables, $arrangedVariables);
$today = new DateTimeImmutable('today', new DateTimeZone($location['timezone']));
$defaultFrom = $today->modify('-29 days')->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/explorador/index.php');
$explorerPath = rtrim(dirname($scriptName), '/\\') . '/';
$locationReturnPath = isset($explorerLocationReturnPathOverride)
    ? (string) $explorerLocationReturnPathOverride
    : $explorerPath;
$applicationPath = rtrim(dirname(dirname($scriptName)), '/\\');
$locationPage = ($applicationPath === '' || $applicationPath === '.') ? '/ubicacion.php' : $applicationPath . '/ubicacion.php';
$changeLocationUrl = $locationPage . '?return=' . rawurlencode($locationReturnPath);
$locationHasName = $location['name'] !== astronomyLocationCoordinateLabel($location['latitude'], $location['longitude']);
$pageSeo = aquellasLunasSeoPage(
    'Explorador astronómico | Aquellas Lunas',
    'Compará series y ciclos astronómicos del Sol y la Luna según ubicación y rango temporal.',
    '/explorador/',
    'website'
);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<?php renderSeoHead($pageSeo); ?>
<?php renderAnalyticsTracking(); ?>
<?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= htmlspecialchars('../' . versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="assets/explorador.css?v=20260804-1">
    <script src="<?= htmlspecialchars('../' . versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body class="explorer-page" data-explorer-share-path="<?= htmlspecialchars($explorerPath, ENT_QUOTES, 'UTF-8') ?>">
<?php renderAstronomySiteHeader('explorer', $location, '../'); ?>
<main class="explorer-shell">
    <header class="explorer-heading">
        <p class="eyebrow">Explorá el cielo a lo largo del tiempo</p>
        <h1>Explorador astronómico</h1>
        <p>Compará ciclos, posiciones y eventos del Sol y la Luna a lo largo del tiempo para cualquier ubicación.</p>
    </header>

    <section class="panel" aria-labelledby="parameters-title">
        <h2 id="parameters-title">Parámetros</h2>
        <form id="explorer-form" novalidate>
            <section class="observation-location" aria-labelledby="observation-location-title"
                data-official-location="<?= htmlspecialchars(json_encode([
                    'latitude' => $location['latitude'], 'longitude' => $location['longitude'],
                    'timezone' => $location['timezone'], 'name' => $locationHasName ? $location['name'] : '',
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>">
                <h3 id="observation-location-title">Ubicación de cálculo:</h3>
                <span id="calculation-location-value"><?php if ($locationHasName): ?><?= htmlspecialchars($location['name'], ENT_QUOTES, 'UTF-8') ?> · <?php endif; ?><?= htmlspecialchars(str_replace('-', '−', number_format($location['latitude'], 4, ',', ''))) ?>°, <?= htmlspecialchars(str_replace('-', '−', number_format($location['longitude'], 4, ',', ''))) ?>° · <?= htmlspecialchars($location['timezone'], ENT_QUOTES, 'UTF-8') ?></span>
                <small id="shared-location-source" role="status" aria-live="polite" hidden>Ubicación incluida en el enlace compartido.</small>
                <a id="change-location" class="location-action" href="<?= htmlspecialchars($changeLocationUrl, ENT_QUOTES, 'UTF-8') ?>">Cambiar</a>
            </section>

            <fieldset class="mode-picker">
                <legend>Modo de análisis</legend>
                <div class="mode-options">
                    <label><input type="radio" name="modo" value="diario" checked><span>Serie diaria <?php explorerContextHelp('help-daily-mode', 'Muestra una observación por cada fecha del intervalo.'); ?></span></label>
                    <label><input type="radio" name="modo" value="extremos"><span>Extremos locales <?php explorerContextHelp('help-extrema-mode', 'Detecta valores mayores o menores que los días inmediatamente anterior y posterior.'); ?></span></label>
                </div>
                <p id="mode-notice" class="mode-notice" role="status" aria-live="polite"></p>
            </fieldset>

            <section class="time-controls" aria-labelledby="time-controls-title">
                <h3 id="time-controls-title">Período</h3>
                <div class="date-grid">
                    <label>Fecha desde<input name="fecha_desde" type="date" value="<?= htmlspecialchars($defaultFrom) ?>" required></label>
                    <label>Año desde <?php explorerContextHelp('help-from-year', 'Completa rápidamente el inicio del período con el 1 de enero del año indicado.'); ?><input name="anio_desde" type="number" min="1000" max="9999" step="1" inputmode="numeric" value="<?= htmlspecialchars(substr($defaultFrom, 0, 4)) ?>"></label>
                    <label>Fecha hasta<input name="fecha_hasta" type="date" value="<?= htmlspecialchars($defaultTo) ?>" required></label>
                    <label>Año hasta <?php explorerContextHelp('help-to-year', 'Completa rápidamente el final del período con el 31 de diciembre del año indicado.'); ?><input name="anio_hasta" type="number" min="1000" max="9999" step="1" inputmode="numeric" value="<?= htmlspecialchars(substr($defaultTo, 0, 4)) ?>"></label>
                </div>
                <p id="date-control-error" class="inline-error" role="alert" hidden></p>
                <div class="quick-ranges">
                    <div class="quick-range-group quick-range-group--backward">
                        <div><strong>← Hacia atrás</strong><span>Conserva la fecha final</span></div>
                        <div class="quick-range-buttons">
                            <?php foreach ([1, 5, 10, 20] as $years): ?>
                                <button type="button" class="range-button" data-range-direction="backward" data-range-years="<?= $years ?>"><?= $years ?> año<?= $years === 1 ? '' : 's' ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="quick-range-group quick-range-group--forward">
                        <div><strong>Hacia adelante →</strong><span>Conserva la fecha inicial</span></div>
                        <div class="quick-range-buttons">
                            <?php foreach ([1, 5, 10, 20] as $years): ?>
                                <button type="button" class="range-button" data-range-direction="forward" data-range-years="<?= $years ?>"><?= $years ?> año<?= $years === 1 ? '' : 's' ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <fieldset class="phase-picker" data-daily-controls>
                <legend>Fases lunares <?php explorerContextHelp('help-phases', 'Calcula sólo las fechas locales en las que ocurre alguna de las fases seleccionadas.'); ?></legend>
                <p>Cuando se seleccionan fases, sólo se calculan las fechas locales en las que ocurre alguno de esos eventos.</p>
                <div class="phase-options">
                    <label class="phase-option"><input id="all-days" type="checkbox" checked><span class="checkbox-visual" aria-hidden="true"></span><strong>Todos los días</strong></label>
                    <?php foreach (\Explorador\PhaseEventDateProvider::PHASES as $key => $label): ?>
                        <label class="phase-option"><input type="checkbox" name="fases[]" value="<?= htmlspecialchars($key) ?>"><span class="checkbox-visual" aria-hidden="true"></span><strong><?= htmlspecialchars($label) ?></strong></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="variable-picker" data-daily-controls>
                <legend>Variables</legend>
                <div class="variable-domains">
                <?php foreach ($variableSections as $section): ?>
                    <section class="variable-domain">
                        <h2><?= htmlspecialchars($section['title']) ?></h2>
                        <?php foreach ($section['groups'] as $group): ?>
                            <section class="variable-group<?= !empty($group['wide']) ? ' variable-group--wide' : '' ?>">
                                <h3><?= htmlspecialchars($group['title']) ?></h3>
                                <div class="variable-pairs">
                                    <?php foreach ($group['pairs'] as $pair): ?>
                                        <div class="variable-pair<?= count($pair) === 1 ? ' variable-pair--single' : '' ?>">
                                            <?php foreach ($pair as $key): ?>
                                                <?php $definition = $variables[$key]; ?>
                                                <label class="variable-option">
                                                    <input type="checkbox" name="campos[]" value="<?= htmlspecialchars($key) ?>" data-value-type="<?= htmlspecialchars($definition['type']) ?>"
                                                        <?= in_array($key, ['moon_illumination', 'moon_distance_geocentric'], true) ? 'checked' : '' ?>>
                                                    <span class="checkbox-visual" aria-hidden="true"></span>
                                                    <span class="variable-copy">
                                                        <span class="variable-title"><strong><?= htmlspecialchars($definition['shortName']) ?></strong><small><?= htmlspecialchars($definition['unit']) ?></small></span>
                                                        <span class="variable-description"><?= htmlspecialchars($definition['description']) ?></span>
                                                        <?php $variableHelp = $definition['description'] . ' Unidad: ' . $definition['unit'] . '. ' . ($definition['scope'] === 'local' ? 'Depende de la ubicación de observación.' : 'No depende de la ubicación del observador.'); ?>
                                                        <?php explorerContextHelp('help-variable-' . $key, $variableHelp); ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
                <?php if ($unarrangedVariables !== []): ?>
                    <section class="variable-domain">
                        <h2>Otras variables</h2>
                        <section class="variable-group">
                            <h3>Sin pareja asignada</h3>
                            <div class="variable-pairs">
                                <?php foreach ($unarrangedVariables as $key => $definition): ?>
                                    <div class="variable-pair variable-pair--single">
                                        <label class="variable-option">
                                            <input type="checkbox" name="campos[]" value="<?= htmlspecialchars($key) ?>" data-value-type="<?= htmlspecialchars($definition['type']) ?>">
                                            <span class="checkbox-visual" aria-hidden="true"></span>
                                            <span class="variable-copy"><span class="variable-title"><strong><?= htmlspecialchars($definition['shortName']) ?></strong><small><?= htmlspecialchars($definition['unit']) ?></small></span><span class="variable-description"><?= htmlspecialchars($definition['description']) ?></span><?php $variableHelp = $definition['description'] . ' Unidad: ' . $definition['unit'] . '. ' . ($definition['scope'] === 'local' ? 'Depende de la ubicación de observación.' : 'No depende de la ubicación del observador.'); explorerContextHelp('help-variable-' . $key, $variableHelp); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </section>
                <?php endif; ?>
                </div>
            </fieldset>
            <fieldset class="relation-panel" data-daily-controls aria-describedby="relation-concept relation-circular-note">
                <legend>Análisis de relación <?php explorerContextHelp('help-relation', 'Compara dos variables dentro de sus rangos observados. Depende del período y los filtros cargados y no mide correlación estadística.'); ?></legend>
                <p id="relation-concept">Compara la posición relativa de dos variables dentro de sus rangos observados. El centro es (mínimo observado + máximo observado) / 2. No mide correlación estadística ni evolución entre días.</p>
                <div class="relation-methods">
                    <label class="relation-option"><input type="checkbox" value="product" data-relation-method><span class="checkbox-visual" aria-hidden="true"></span><span><strong>Coincidencia / oposición <?php explorerContextHelp('help-coincidence', 'Indica si dos variables están del mismo lado o en lados opuestos del punto medio de sus rangos observados.'); ?></strong><small>Mismo lado o lados opuestos del punto medio.</small></span></label>
                    <label class="relation-option"><input type="checkbox" value="average" data-relation-method><span class="checkbox-visual" aria-hidden="true"></span><span><strong>Refuerzo positivo / negativo <?php explorerContextHelp('help-reinforcement', 'Indica si la posición conjunta se acerca más a los máximos o a los mínimos observados.'); ?></strong><small>Posición conjunta hacia máximos o mínimos.</small></span></label>
                </div>
                <p id="relation-help" class="relation-help" aria-live="polite">Seleccioná al menos dos variables.</p>
                <div id="relation-selectors" class="relation-selectors" hidden>
                    <label>Variable A <?php explorerContextHelp('help-relation-variable-a', 'Primera variable numérica de la comparación.'); ?><select id="relation-variable-a"></select></label>
                    <label>Variable B <?php explorerContextHelp('help-relation-variable-b', 'Segunda variable numérica; debe ser distinta de A.'); ?><select id="relation-variable-b"></select></label>
                </div>
                <p id="relation-phase-note" class="relation-phase-note" hidden>La normalización se calcula sólo sobre las fechas de fases seleccionadas.</p>
                <p id="relation-circular-note" class="relation-circular-note">El análisis usa normalización lineal. En variables circulares, como azimutes, la interpretación puede requerir cautela.</p>
            </fieldset>
            <fieldset id="extrema-panel" class="extrema-panel" hidden>
                <legend>Extremos locales</legend>
                <p>Los extremos locales se detectan comparando cada valor diario con los días inmediatamente anterior y posterior.</p>
                <div class="extrema-controls">
                    <label>Variable
                        <select name="variable">
                            <?php foreach ($extremaVariables as $key => $definition): ?>
                                <option value="<?= htmlspecialchars($key) ?>"
                                    data-minimum-years="<?= (int) $definition['extrema_minimum_years'] ?>"
                                    data-recommended-years="<?= (int) $definition['extrema_recommended_years'] ?>">
                                    <?= htmlspecialchars($definition['extrema_label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <fieldset class="extrema-type-picker">
                        <legend>Mostrar</legend>
                        <label><input type="radio" name="tipo_extremo" value="maximo"><span>Máximos</span></label>
                        <label><input type="radio" name="tipo_extremo" value="minimo"><span>Mínimos</span></label>
                        <label><input type="radio" name="tipo_extremo" value="ambos" checked><span>Ambos</span></label>
                    </fieldset>
                </div>
                <p id="extrema-range-guidance" class="extrema-range-guidance" aria-live="polite"></p>
                <p class="extrema-resolution-note">Son extremos de la muestra diaria a las 00:00 locales o del valor diario calculado, no instantes físicos interpolados.</p>
            </fieldset>
            <div class="action-bar">
                <button class="calculate-button" type="submit">Calcular</button>
                <section id="process-panel" class="process-panel" data-state="idle" aria-labelledby="process-stage">
                    <div class="process-summary">
                        <strong id="process-stage">Inactivo</strong>
                        <span id="process-days">Sin cálculo en curso</span>
                        <span id="process-percent">0 %</span>
                        <span class="process-elapsed">Tiempo: <time id="process-elapsed">00:00,0</time></span>
                        <button id="cancel-calculation" class="cancel-button" type="button" disabled hidden>Cancelar</button>
                    </div>
                    <div id="process-track" class="process-track" role="progressbar" aria-label="Progreso del cálculo" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="process-fill"></span></div>
                    <span id="request-status" role="status" aria-live="polite"></span>
                </section>
                <button id="share-configuration" class="share-button" type="button">Compartir configuración</button>
            </div>
            <p id="shared-configuration-status" class="shared-configuration-status" role="status" aria-live="polite"></p>
            <p id="shared-configuration-notice" class="shared-configuration-notice" role="status" hidden></p>
        </form>
    </section>

    <dialog id="share-dialog" class="share-dialog" aria-labelledby="share-dialog-title" aria-describedby="share-dialog-note">
        <div class="share-dialog__surface">
            <h2 id="share-dialog-title">Compartir configuración</h2>
            <label for="share-url">Enlace completo</label>
            <input id="share-url" type="text" readonly>
            <p id="share-dialog-note">El enlace incluye la ubicación utilizada para el cálculo.</p>
            <p id="share-dialog-status" class="share-dialog__status" role="status" aria-live="polite"></p>
            <div class="share-dialog__actions">
                <button id="share-dialog-copy" type="button">Copiar</button>
                <button id="share-dialog-close" class="share-dialog__close" type="button">Cerrar</button>
            </div>
        </div>
    </dialog>

<?php if ($showTechnicalMetrics): ?>
    <section class="panel metrics-panel" aria-labelledby="metrics-title">
        <h2 id="metrics-title">Resumen del cálculo</h2>
        <dl id="metrics"><div><dt>Estado</dt><dd>Sin calcular</dd></div></dl>
        <details class="technical-metrics"><summary>Ver detalles técnicos</summary><dl id="technical-metrics"></dl></details>
    </section>
<?php endif; ?>

    <section class="panel chart-panel" aria-labelledby="chart-title">
        <div class="chart-heading"><h2 id="chart-title">Serie</h2><p class="chart-vertical-help">Shift + rueda: zoom vertical · Shift + arrastre: desplazar eje</p></div>
        <p id="local-time-chart-help" class="local-time-chart-help" hidden>Las líneas horarias se interrumpen al cruzar la medianoche para evitar saltos visuales artificiales.</p>
        <div id="chart-message" class="chart-message">Elegí variables y calculá una serie.</div>
        <div id="chart" role="img" aria-label="Gráfico de series astronómicas"></div>
    </section>
</main>
<?php renderAstronomySiteFooter(); ?>
<script src="../vendor/frontend/echarts/5.6.0/echarts.min.js"></script>
<script src="assets/date-controls.js"></script>
<script src="assets/relation-analysis.js"></script>
<script src="assets/share-config.js?v=1"></script>
<script src="assets/time-series-segments.js?v=1"></script>
<script src="assets/vertical-axis-control.js?v=20260803-2"></script>
<script>window.ExplorerScaleGroups = <?= json_encode($scaleGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="assets/explorador.js?v=20260804-3"></script>
</body>
</html>
