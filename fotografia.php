<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/photography-scene.php';
require_once __DIR__ . '/includes/astronomy-events.php';
require_once __DIR__ . '/includes/photography-geometry.php';
require_once __DIR__ . '/includes/photography-editorial.php';
require_once __DIR__ . '/includes/web-database.php';
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/timezone-resolver.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/site-sections.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/photography-simulation.php';
require_once __DIR__ . '/includes/astronomy-data.php';

sendDynamicNoCacheHeaders();

function photographyNumber(mixed $value, float $minimum, float $maximum, float $fallback): float
{
    return is_numeric($value) && is_finite((float) $value) && (float) $value >= $minimum && (float) $value <= $maximum ? (float) $value : $fallback;
}

function photographyDailyEventClock(mixed $value, DateTimeZone $timezone): ?string
{
    if (!is_string($value) || trim($value) === '') return null;
    try {
        return (new DateTimeImmutable($value))->setTimezone($timezone)->format('H:i');
    } catch (Throwable) {
        return null;
    }
}

$location = astronomyLocationContext();
if (isset($_GET['lat'], $_GET['lon'])) {
    $latitude = photographyNumber($_GET['lat'], -90, 90, (float) $location['latitude']);
    $longitude = photographyNumber($_GET['lon'], -180, 180, (float) $location['longitude']);
    $elevation = photographyNumber($_GET['elevation'] ?? null, -500, 10000, 0.0);
    try {
        $location = ['name' => astronomyLocationCoordinateLabel($latitude, $longitude), 'latitude' => $latitude, 'longitude' => $longitude, 'elevation_meters' => $elevation, 'timezone' => astronomyResolveTimezone($latitude, $longitude), 'mode' => 'url', 'confirmed' => true];
    } catch (Throwable $exception) {
        // Una ubicación URL incompleta nunca reemplaza el contexto central válido.
    }
}
$now = get_current_datetime((string) $location['timezone']);
$date = is_string($_GET['date'] ?? null) ? $_GET['date'] : $now->format('Y-m-d');
$time = is_string($_GET['time'] ?? null) ? $_GET['time'] : $now->format('H:i');
$wallTime = astronomyValidLocalWallTime($date, $time);
if ($wallTime === null || $date < '1900-01-01' || $date > '2050-12-31') {
    $date = $now->format('Y-m-d'); $time = $now->format('H:i'); $wallTime = $date . ' ' . $time;
}
$timeOffset = (int) round(photographyNumber($_GET['time_offset'] ?? null, -120, 120, 0.0));
$eventType = is_string($_GET['event_type'] ?? null) && in_array($_GET['event_type'], ['moonrise', 'moonset', 'conjunction', 'eclipse'], true) ? $_GET['event_type'] : null;
$eventTime = null; $observationTime = null;
try {
    if ($eventType !== null && is_string($_GET['event_time'] ?? null)) $eventTime = (new DateTimeImmutable($_GET['event_time']))->setTimezone(new DateTimeZone((string) $location['timezone']));
    if ($eventType !== null && is_string($_GET['observation_time'] ?? null)) $observationTime = (new DateTimeImmutable($_GET['observation_time']))->setTimezone(new DateTimeZone((string) $location['timezone']));
} catch (Throwable) {
    $eventTime = null; $observationTime = null; $eventType = null;
}
$observationMatchesForm = $observationTime instanceof DateTimeImmutable && $observationTime->format('Y-m-d H:i') === $date . ' ' . $time;
$baseInstant = $observationMatchesForm ? $observationTime : new DateTimeImmutable($wallTime, new DateTimeZone((string) $location['timezone']));
$instant = $timeOffset === 0 ? $baseInstant : $baseInstant->modify(($timeOffset > 0 ? '+' : '') . $timeOffset . ' minutes');
$eventContext = $timeOffset === 0 && $observationMatchesForm && $eventType !== null ? ['type' => $eventType, 'canonical_time' => $eventTime?->format(DateTimeInterface::ATOM), 'observation_time' => $observationTime->format(DateTimeInterface::ATOM)] : null;
$effectiveTimeLabel = $instant->format('Y-m-d') === $date ? $instant->format('H:i') : $instant->format('d/m H:i');
$sensors = photographySensorPresets();
$sensorId = is_string($_GET['sensor'] ?? null) && isset($sensors[$_GET['sensor']]) ? $_GET['sensor'] : 'full_frame';
$sensorWidth = $sensorId === 'custom' ? photographyNumber($_GET['sensor_width'] ?? null, 1, 100, 36) : (float) $sensors[$sensorId]['width_mm'];
$sensorHeight = $sensorId === 'custom' ? photographyNumber($_GET['sensor_height'] ?? null, 1, 100, 24) : (float) $sensors[$sensorId]['height_mm'];
$focal = photographyNumber($_GET['focal'] ?? null, 1, 3000, 200);
$hasExplicitOrientation = isset($_GET['orientation']) && in_array($_GET['orientation'], ['horizontal', 'vertical'], true);
$preferredOrientation = ($_GET['preferred_orientation'] ?? '') === 'vertical' ? 'vertical' : 'horizontal';
$orientation = $hasExplicitOrientation ? (string) $_GET['orientation'] : $preferredOrientation;
$hasExplicitSceneEntry = !$hasExplicitOrientation && (isset($_GET['scene']) || isset($_GET['event_type']));
$roll = photographyNumber($_GET['roll'] ?? null, -45, 45, 0.0);
$displayMode = ($_GET['mode'] ?? '') === 'simulated' ? 'simulated' : 'scheme';
$hasExplicitAim = isset($_GET['aim']) && in_array($_GET['aim'], ['automatic', 'moon', 'manual'], true);
$centeringEnabled = !array_key_exists('centering', $_GET) || $_GET['centering'] !== '0';
$includeHorizon = array_key_exists('horizon', $_GET) ? ($_GET['horizon'] !== '0') : false;
$objectIds = isset($_GET['objects']) && is_string($_GET['objects']) ? array_filter(explode(',', $_GET['objects'])) : [];
$aim = $hasExplicitAim ? (string) $_GET['aim'] : (($includeHorizon || $objectIds !== []) ? 'automatic' : 'moon');
$manualOffsetX = $aim === 'manual' ? photographyNumber($_GET['offset_x'] ?? null, -180, 180, 0.0) : 0.0;
$manualOffsetY = $aim === 'manual' ? photographyNumber($_GET['offset_y'] ?? null, -180, 180, 0.0) : 0.0;
$eclipseState = ['active' => false, 'type' => null, 'stage' => null, 'source' => 'astronomy_events'];
try {
    $eclipseResponse = astronomyEvents([
        'start_date' => $instant->modify('-1 day')->format('Y-m-d'), 'days' => 3, 'types' => 'eclipse',
        'latitude' => (float) $location['latitude'], 'longitude' => (float) $location['longitude'], 'timezone' => (string) $location['timezone'],
    ], 'photography eclipse state', 25);
    $eclipseState = photographyEclipseStateFromEvents(is_array($eclipseResponse['items'] ?? null) ? $eclipseResponse['items'] : [], $instant);
} catch (Throwable $exception) {
    error_log('Aquellas Lunas photography eclipse state unavailable: ' . $exception->getMessage());
}
$state = photographyAstronomicalScene($instant, $location, $objectIds, $eclipseState, $eventContext);
if ($hasExplicitSceneEntry) {
    $orientation = photographyCompositionOrientation($state, $objectIds, $includeHorizon, $preferredOrientation);
}
$orientationSource = ($_GET['orientation_auto'] ?? '') === '1'
    ? 'scene_auto'
    : ($hasExplicitOrientation ? 'explicit' : ($hasExplicitSceneEntry ? 'scene_auto' : 'preference'));
$dailyEventTimes = ['sunrise' => null, 'sunset' => null, 'moonrise' => null, 'moonset' => null];
try {
    $dailyAstronomy = astronomyDataDaily($location, $date, false, 'photography daily events', 15);
    $eventTimezone = new DateTimeZone((string) $location['timezone']);
    $dailyEventTimes = [
        'sunrise' => photographyDailyEventClock($dailyAstronomy['sun']['rise'] ?? null, $eventTimezone),
        'sunset' => photographyDailyEventClock($dailyAstronomy['sun']['set'] ?? null, $eventTimezone),
        'moonrise' => photographyDailyEventClock($dailyAstronomy['moon']['rise'] ?? null, $eventTimezone),
        'moonset' => photographyDailyEventClock($dailyAstronomy['moon']['set'] ?? null, $eventTimezone),
    ];
} catch (Throwable $exception) {
    error_log('Aquellas Lunas photography daily events unavailable: ' . $exception->getMessage());
}
$classification = photographySceneClassification($state, $includeHorizon);
$elementCandidates = photographyElementCandidates($state, $includeHorizon);
$selectedEditorialScene = null;
try {
    $editorialCatalog = photographyEditorialCatalog(getWebDatabaseConnection());
    foreach ($editorialCatalog as $editorialScene) {
        if ($editorialScene['scene_key'] === $classification['primary_scene'] && (bool) $editorialScene['active']) {
            $selectedEditorialScene = $editorialScene;
            $classification['available_variants'] = array_values(array_filter($editorialScene['variants'], static fn(array $variant): bool => (bool) $variant['active']));
            break;
        }
    }
} catch (Throwable $exception) {
    $editorialCatalog = [];
}
$variantIds = array_map(static fn(array $variant): string => (string) $variant['variant_key'], $classification['available_variants']);
$selectedVariant = is_string($_GET['variant'] ?? null) && in_array($_GET['variant'], $variantIds, true) ? $_GET['variant'] : ($variantIds[0] ?? '');
$selectedEditorialVariant = null;
foreach ($classification['available_variants'] as $variant) if ((string) $variant['variant_key'] === $selectedVariant) { $selectedEditorialVariant = $variant; break; }
$editorialReferenceImage = null;
$editorialReferenceImageLabel = null;
$editorialReferenceImageWidth = null;
$editorialReferenceImageHeight = null;
$editorialReferenceImageOrientation = null;
if (is_array($selectedEditorialVariant) && is_string($selectedEditorialVariant['example_image_path'] ?? null) && trim($selectedEditorialVariant['example_image_path']) !== '' && is_file(__DIR__ . '/' . $selectedEditorialVariant['example_image_path'])) {
    $editorialReferenceImage = $selectedEditorialVariant['example_image_path'];
    $editorialReferenceImageLabel = $selectedEditorialVariant['title'];
} elseif (is_array($selectedEditorialScene) && is_string($selectedEditorialScene['example_image_path'] ?? null) && trim($selectedEditorialScene['example_image_path']) !== '' && is_file(__DIR__ . '/' . $selectedEditorialScene['example_image_path'])) {
    $editorialReferenceImage = $selectedEditorialScene['example_image_path'];
    $editorialReferenceImageLabel = $selectedEditorialScene['title'];
}
if ($editorialReferenceImage !== null) {
    $editorialReferenceImageSize = @getimagesize(__DIR__ . '/' . $editorialReferenceImage);
    if (is_array($editorialReferenceImageSize) && (int) $editorialReferenceImageSize[0] > 0 && (int) $editorialReferenceImageSize[1] > 0) {
        $editorialReferenceImageWidth = (int) $editorialReferenceImageSize[0];
        $editorialReferenceImageHeight = (int) $editorialReferenceImageSize[1];
        $editorialReferenceImageOrientation = $editorialReferenceImageHeight > $editorialReferenceImageWidth ? 'portrait' : 'landscape';
    }
}
$framing = photographyFraming($state, $objectIds, $includeHorizon, $sensorWidth, $sensorHeight, $focal, $orientation, $aim, $manualOffsetX, $manualOffsetY, $roll);
$outsideFrame = array_values(array_filter($framing['points'], static fn(array $point): bool => !$point['in_frame']));
$simulationConfig = photographySimulationConfig();
$payload = ['astronomy' => $state, 'classification' => $classification, 'framing' => $framing, 'orientation' => $orientation, 'orientation_source' => $orientationSource, 'include_horizon' => $includeHorizon, 'display_mode' => $displayMode, 'event_context' => $eventContext, 'simulation' => $simulationConfig, 'simulation_moon' => photographySimulationMoonPayload($state, $simulationConfig)];
$pageTitle = 'Fotografía lunar';
$pageDescription = 'Planificá un encuadre lunar con geometría astronómica y campo visual reales.';
$pageSeo = aquellasLunasSeoPage($pageTitle, $pageDescription, '/fotografia.php');
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php renderSeoHead($pageSeo); renderFaviconLinks(); renderAnalyticsTracking(); ?>
<link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/photography.css'), ENT_QUOTES, 'UTF-8') ?>">
</head><body>
<?php renderAstronomySiteHeader('photography', $location); ?>
<main class="container photography-page">
  <header class="page-heading"><p class="eyebrow">Fotografía</p><h1>Planificador fotográfico lunar</h1><p>Nosotros ponemos la astronomía, la geometría y algunas referencias. La fotografía la hacés vos.</p></header>
  <section class="card photography-mode" aria-label="Modo de representación y horarios del día">
    <div class="photography-mode__buttons" data-photography-mode-selector><button type="button" data-display-mode="scheme" aria-pressed="<?= $displayMode === 'scheme' ? 'true' : 'false' ?>"<?= $displayMode === 'scheme' ? ' class="photography-mode__active"' : '' ?>>Esquema</button><button type="button" data-display-mode="simulated" aria-pressed="<?= $displayMode === 'simulated' ? 'true' : 'false' ?>"<?= $displayMode === 'simulated' ? ' class="photography-mode__active"' : '' ?>>Simulado <small>Cielo inicial</small></button></div>
    <dl class="photography-day-events" aria-label="Salidas y puestas para el día seleccionado"><div><dt>Sol</dt><dd><span>Salida <time><?= htmlspecialchars($dailyEventTimes['sunrise'] ?? '—', ENT_QUOTES, 'UTF-8') ?></time></span><span>Puesta <time><?= htmlspecialchars($dailyEventTimes['sunset'] ?? '—', ENT_QUOTES, 'UTF-8') ?></time></span></dd></div><div><dt>Luna</dt><dd><span>Salida <time><?= htmlspecialchars($dailyEventTimes['moonrise'] ?? '—', ENT_QUOTES, 'UTF-8') ?></time></span><span>Puesta <time><?= htmlspecialchars($dailyEventTimes['moonset'] ?? '—', ENT_QUOTES, 'UTF-8') ?></time></span></dd></div></dl>
  </section>
  <section class="card photography-workspace">
    <div class="photography-frame-wrap">
      <div class="photography-frame photography-frame--<?= $orientation ?>" data-photography-frame data-thirds="1" role="img" aria-label="Esquema del encuadre fotográfico"></div>
      <div class="photography-viewer-controls"><label class="photography-check"><input type="checkbox" data-thirds-toggle checked> Guías de tercios</label><input type="hidden" name="centering" value="0" form="photography-controls-form"><label class="photography-check"><input type="checkbox" name="centering" value="1" form="photography-controls-form" data-centering-toggle<?= $centeringEnabled ? ' checked' : '' ?>> Centrado <span class="photography-check__state" data-centering-status><?= $centeringEnabled ? 'Activado' : 'Desactivado' ?></span></label></div>
    </div>
    <form id="photography-controls-form" class="photography-controls" method="get" data-photography-form>
      <input type="hidden" name="mode" value="<?= htmlspecialchars($displayMode, ENT_QUOTES, 'UTF-8') ?>" data-display-mode-value>
      <input type="hidden" name="orientation_auto" value="<?= $orientationSource === 'scene_auto' ? '1' : '0' ?>" data-orientation-auto>
      <?php if ($observationMatchesForm && $eventType !== null): ?><input type="hidden" name="event_type" value="<?= htmlspecialchars((string) $eventType, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="event_time" value="<?= htmlspecialchars((string) ($_GET['event_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="observation_time" value="<?= htmlspecialchars((string) ($_GET['observation_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?php if (is_string($_GET['event_object'] ?? null)): ?><input type="hidden" name="event_object" value="<?= htmlspecialchars($_GET['event_object'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?><?php endif; ?>
      <div class="photography-control-grid">
        <label>Fecha<input type="date" name="date" min="1900-01-01" max="2050-12-31" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>" required></label>
        <label>Hora base<input type="time" name="time" value="<?= htmlspecialchars($time, ENT_QUOTES, 'UTF-8') ?>" required></label>
        <div class="photography-field photography-time-offset"><span class="photography-control-value"><label for="photography-time-offset">Desplazamiento temporal</label><output for="photography-time-offset" data-time-offset-value><?= $timeOffset > 0 ? '+' : '' ?><?= $timeOffset ?> min</output></span><input id="photography-time-offset" type="range" name="time_offset" min="-120" max="120" step="1" value="<?= $timeOffset ?>" data-time-offset><small>Hora mostrada: <time data-effective-time><?= htmlspecialchars($effectiveTimeLabel, ENT_QUOTES, 'UTF-8') ?></time></small></div>
        <label>Sensor<select name="sensor" data-sensor><?php foreach ($sensors as $id => $sensor): ?><option value="<?= $id ?>"<?= $id === $sensorId ? ' selected' : '' ?>><?= htmlspecialchars($sensor['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
        <div class="photography-field photography-focal-field">
          <span class="photography-field__label"><label for="photography-focal">Focal real (mm)</label><span class="photography-field-help" data-field-help><button type="button" class="photography-field-help__trigger" aria-label="Ayuda sobre focal real" aria-describedby="photography-focal-help" aria-expanded="false" data-field-help-trigger>i</button><span id="photography-focal-help" class="photography-field-help__tooltip" role="tooltip" data-field-help-tooltip hidden>Ingresá la distancia focal real indicada en el objetivo. El tamaño del sensor ya se tiene en cuenta.</span></span></span>
          <input type="range" min="0" max="1000" step="1" value="<?= (int) round(log($focal) / log(3000.0) * 1000.0) ?>" aria-label="Zoom de distancia focal" data-focal-slider>
          <span class="photography-focal-number"><input id="photography-focal" type="number" name="focal" min="1" max="3000" step="0.1" value="<?= htmlspecialchars((string) $focal, ENT_QUOTES, 'UTF-8') ?>" data-focal required><span>mm</span></span>
        </div>
        <label data-custom-sensor-field<?= $sensorId !== 'custom' ? ' hidden' : '' ?>>Ancho sensor (mm)<input type="number" name="sensor_width" min="1" max="100" step="0.1" value="<?= htmlspecialchars((string) $sensorWidth, ENT_QUOTES, 'UTF-8') ?>" data-custom-sensor<?= $sensorId !== 'custom' ? ' disabled' : '' ?>></label>
        <label data-custom-sensor-field<?= $sensorId !== 'custom' ? ' hidden' : '' ?>>Alto sensor (mm)<input type="number" name="sensor_height" min="1" max="100" step="0.1" value="<?= htmlspecialchars((string) $sensorHeight, ENT_QUOTES, 'UTF-8') ?>" data-custom-sensor<?= $sensorId !== 'custom' ? ' disabled' : '' ?>></label>
      </div>
      <fieldset class="photography-camera-controls"><legend>Orientación</legend><label><input type="radio" name="orientation" value="horizontal"<?= $orientation === 'horizontal' ? ' checked' : '' ?>> Horizontal</label><label><input type="radio" name="orientation" value="vertical"<?= $orientation === 'vertical' ? ' checked' : '' ?>> Vertical</label><label class="photography-roll-control"><span>Giro del encuadre</span><span><input type="range" name="roll" min="-45" max="45" step="1" value="<?= htmlspecialchars((string) $roll, ENT_QUOTES, 'UTF-8') ?>" data-camera-roll><output data-camera-roll-value><?= htmlspecialchars(number_format($roll, 0, ',', '.'), ENT_QUOTES, 'UTF-8') ?>°</output></span></label><button class="photography-reset-frame" type="button" data-reset-frame>Restablecer encuadre</button><small class="photography-roll-notice" data-camera-roll-notice<?= !$includeHorizon || abs($roll) < .001 ? ' hidden' : '' ?>>El horizonte quedará inclinado por el giro del encuadre.</small></fieldset>
      <fieldset class="photography-elements"><legend>Elementos</legend><?php if ($elementCandidates['show_horizon']): ?><label><input type="hidden" name="horizon" value="0"><input type="checkbox" name="horizon" value="1" data-horizon<?= $includeHorizon ? ' checked' : '' ?>> <span>Horizonte</span></label><?php endif; ?><?php foreach ($elementCandidates['objects'] as $object): ?><label><input type="checkbox" data-object value="<?= htmlspecialchars((string) $object['id'], ENT_QUOTES, 'UTF-8') ?>"<?= $object['selected'] ? ' checked' : '' ?>> <span><?= htmlspecialchars((string) $object['name'], ENT_QUOTES, 'UTF-8') ?> <small>· <?= htmlspecialchars(number_format((float) $object['separation_from_moon_degrees'], 1, ',', '.'), ENT_QUOTES, 'UTF-8') ?>°</small></span></label><?php endforeach; ?><?php if (!$elementCandidates['show_horizon'] && $elementCandidates['objects'] === []): ?><p class="photography-elements__empty">No hay otros elementos cercanos a la Luna en este momento.</p><?php endif; ?><input type="hidden" name="objects" value="<?= htmlspecialchars(implode(',', $objectIds), ENT_QUOTES, 'UTF-8') ?>" data-objects-value></fieldset>
      <input type="hidden" name="scene" value="<?= htmlspecialchars($classification['primary_scene'], ENT_QUOTES, 'UTF-8') ?>"><?php if ($classification['available_variants'] !== []): ?><label>Variante editorial<select name="variant" data-variant><?php foreach ($classification['available_variants'] as $variant): ?><option value="<?= htmlspecialchars($variant['variant_key'], ENT_QUOTES, 'UTF-8') ?>"<?= $selectedVariant === $variant['variant_key'] ? ' selected' : '' ?>><?= htmlspecialchars($variant['title'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><?php endif; ?>
      <input type="hidden" name="aim" value="<?= $aim ?>" data-framing-aim>
      <input type="hidden" name="offset_x" value="<?= htmlspecialchars((string) $manualOffsetX, ENT_QUOTES, 'UTF-8') ?>" data-offset-x><input type="hidden" name="offset_y" value="<?= htmlspecialchars((string) $manualOffsetY, ENT_QUOTES, 'UTF-8') ?>" data-offset-y>
      <input type="hidden" name="lat" value="<?= htmlspecialchars((string) $location['latitude'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="lon" value="<?= htmlspecialchars((string) $location['longitude'], ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="elevation" value="<?= htmlspecialchars((string) ($location['elevation_meters'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
      <button class="button photography-update-fallback" type="submit" data-update-fallback>Aplicar cambios</button>
    </form>
  </section>
  <section class="photography-results">
    <article class="card"><h2>Campo visual y encuadre</h2><dl class="photography-data"><div><dt>Campo visual</dt><dd data-field-of-view><?= number_format($framing['field_of_view']['horizontal_degrees'], 2, ',', '.') ?>° × <?= number_format($framing['field_of_view']['vertical_degrees'], 2, ',', '.') ?>°</dd></div><div><dt>Focal máxima geométrica</dt><dd><?= number_format($framing['maximum_focal_mm'], 0, ',', '.') ?> mm</dd></div><div><dt>Focal con margen de composición</dt><dd><?= number_format($framing['suggested_focal_mm'], 0, ',', '.') ?> mm</dd></div></dl><?php if ($outsideFrame !== []): ?><p class="photography-warning">Quedan fuera del encuadre: <?= htmlspecialchars(implode(', ', array_map(static fn(array $point): string => $point['id'] === 'moon' ? 'Luna' : ($point['id'] === 'horizon' ? 'horizonte' : ucfirst($point['id'])), $outsideFrame)), ENT_QUOTES, 'UTF-8') ?>.</p><?php endif; ?><p class="photography-note">Es una referencia geométrica, no una receta fotográfica.</p></article>
    <article class="card"><h2>Estado de la escena</h2><h3><?= htmlspecialchars($classification['primary_scene_label'], ENT_QUOTES, 'UTF-8') ?></h3><div class="photography-tags"><?php foreach ($classification['labels'] as $label): ?><span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></div><dl class="photography-data"><div><dt>Luna</dt><dd><?= number_format($state['moon']['altitude_degrees'], 1, ',', '.') ?>° de altura · azimut <?= number_format($state['moon']['azimuth_degrees'], 1, ',', '.') ?>°</dd></div><div><dt>Fase</dt><dd><?= htmlspecialchars($state['moon']['phase_display_name'], ENT_QUOTES, 'UTF-8') ?> · <?= number_format($state['moon']['illumination_fraction'] * 100, 1, ',', '.') ?>%</dd></div><div><dt>Sol</dt><dd><?= number_format($state['sun']['altitude_degrees'], 1, ',', '.') ?>° de altura · separación <?= number_format($state['sun']['separation_from_moon_degrees'], 1, ',', '.') ?>°</dd></div><?php foreach ($state['objects'] as $object): ?><?php if ($object['selected']): ?><div><dt><?= htmlspecialchars($object['name'], ENT_QUOTES, 'UTF-8') ?></dt><dd><?= number_format($object['altitude_degrees'], 1, ',', '.') ?>° de altura · separación <?= number_format($object['separation_from_moon_degrees'], 1, ',', '.') ?>°</dd></div><?php endif; ?><?php endforeach; ?></dl><?php foreach ($classification['conditions'] as $condition): ?><p class="photography-warning"><?= htmlspecialchars($condition, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?></article>
  </section>
  <?php if (is_array($selectedEditorialScene) && is_array($selectedEditorialVariant)): ?>
  <section class="card photography-editorial-public<?= $editorialReferenceImage === null ? ' photography-editorial-public--without-image' : '' ?>" aria-labelledby="photography-reference-title">
    <?php if ($editorialReferenceImage !== null): ?><figure class="photography-editorial-public__image<?= $editorialReferenceImageOrientation !== null ? ' photography-editorial-public__image--' . $editorialReferenceImageOrientation : '' ?>"><img class="protected-photo js-protected-photo" src="<?= htmlspecialchars(versionedAssetUrl($editorialReferenceImage), ENT_QUOTES, 'UTF-8') ?>" alt="Referencia fotográfica real de <?= htmlspecialchars((string) $editorialReferenceImageLabel, ENT_QUOTES, 'UTF-8') ?>" draggable="false" loading="lazy"<?= $editorialReferenceImageWidth !== null && $editorialReferenceImageHeight !== null ? ' width="' . $editorialReferenceImageWidth . '" height="' . $editorialReferenceImageHeight . '"' : '' ?>></figure><?php endif; ?>
    <div><p class="eyebrow">Referencia editorial</p><h2 id="photography-reference-title"><?= htmlspecialchars($selectedEditorialScene['title'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($selectedEditorialVariant['title'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($selectedEditorialScene['description'], ENT_QUOTES, 'UTF-8') ?></p><p><?= htmlspecialchars($selectedEditorialVariant['description'], ENT_QUOTES, 'UTF-8') ?></p><h3>Una posible configuración</h3><dl class="photography-data"><?php foreach ([['Focal usada en esa foto', $selectedEditorialVariant['reference_focal_mm'] !== null ? number_format((float) $selectedEditorialVariant['reference_focal_mm'], 0, ',', '.') . ' mm' : null], ['Apertura', $selectedEditorialVariant['aperture']], ['Velocidad', $selectedEditorialVariant['shutter_speed']], ['ISO', $selectedEditorialVariant['iso_value']]] as [$label, $value]): ?><?php if (is_string($value) && trim($value) !== ''): ?><div><dt><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?><?php endforeach; ?></dl><?php foreach ([$selectedEditorialScene['editorial_notes'] ?? null, $selectedEditorialVariant['editorial_notes'] ?? null] as $notes): ?><?php if (is_string($notes) && trim($notes) !== ''): ?><p><?= nl2br(htmlspecialchars($notes, ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?><?php endforeach; ?><p><strong>Con celular:</strong> <?= htmlspecialchars(['no' => 'No recomendada.', 'limited' => 'Viabilidad limitada.', 'good' => 'Buena oportunidad.'][$selectedEditorialVariant['phone_viability']] ?? 'Viabilidad limitada.', ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($selectedEditorialVariant['phone_note'] ?: '', ENT_QUOTES, 'UTF-8') ?></p><p class="photography-note">Es una referencia de una fotografía real y una posible elección artística, no una receta.</p></div>
  </section>
  <?php endif; ?>
</main>
<script>window.photographyScene=<?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/photography-planner.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/protected-photos.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php renderAstronomySiteFooter(); ?>
</body></html>
