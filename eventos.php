<?php
require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/presentation.php';
require_once __DIR__ . '/includes/event-presentation.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/site-sections.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/astronomy-icon.php';
sendDynamicNoCacheHeaders();

$availableTypes = ['moon_phase', 'apsis', 'conjunction', 'earthshine', 'libration', 'eclipse'];

function eventsNormalizeCoordinate($value, float $min, float $max): ?float
{
    return astronomyLocationCoordinate($value, $min, $max);
}

function eventsNormalizeTimezone($value): ?string
{
    return astronomyLocationTimezone($value);
}

function eventsGroupDate(?DateTimeImmutable $date): string
{
    if ($date === null) {
        return 'Fecha no disponible';
    }
    return $date->format('d/m/Y');
}

$location = astronomyLocationContext();
$latitude = $location['latitude'];
$longitude = $location['longitude'];
$timezoneName = $location['timezone'];
$locationLabel = $location['name'];
$locationMode = $location['mode'];
$locationMessage = astronomyLocationStatusMessage((string) ($_GET['location_status'] ?? ''));

$today = get_current_datetime($timezoneName);
$startDate = isset($_GET['start_date']) ? trim((string) $_GET['start_date']) : $today->format('Y-m-d');
$validStartDate = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate, new DateTimeZone($timezoneName));
if ($validStartDate === false || $validStartDate->format('Y-m-d') !== $startDate) {
    $startDate = $today->format('Y-m-d');
}
$days = filter_var($_GET['days'] ?? 30, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 366]]);
$days = $days === false ? 30 : $days;
$filtersWereSubmitted = isset($_GET['filters_submitted']);
$requestedTypes = $_GET['types'] ?? ($filtersWereSubmitted ? [] : $availableTypes);
$requestedTypes = is_array($requestedTypes) ? $requestedTypes : explode(',', (string) $requestedTypes);
$selectedTypes = array_values(array_intersect($availableTypes, array_map('strval', $requestedTypes)));

$apiErrorMessage = null;
$emptyMessage = null;
$items = [];
if ($filtersWereSubmitted && $selectedTypes === []) {
    $apiErrorMessage = 'Seleccioná al menos un tipo de evento.';
} else {
    try {
        $apiConfig = loadAstronomyApiConfig();
        error_log('Aquellas Lunas API configuration source: ' . $apiConfig['source']);
        $query = http_build_query([
            'start_date' => $startDate,
            'days' => $days,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezoneName,
            'types' => implode(',', $selectedTypes),
        ]);
        $requestResult = astronomyApiRequest($apiConfig['base_url'] . '/v1/astronomy/events?' . $query, 'events', 35);
        if ($locationMode !== 'default' && astronomyApiRejectedLocationParameters($requestResult)) {
            astronomyRecoverDefaultLocationFromApi($requestResult);
        }
        $response = $requestResult['body'];
        $httpCode = $requestResult['http_code'];
        if ($response === false || $httpCode !== 200) {
            $apiErrorMessage = 'No se pudieron cargar los eventos lunares en este momento.';
            error_log('Aquellas Lunas API events request failed with HTTP status ' . $httpCode . '.');
            astronomyApiRecordValidation('events', null, false);
        } else {
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || !is_array($decoded['items'] ?? null)) {
                $apiErrorMessage = 'No se pudieron cargar los eventos lunares en este momento.';
                error_log('Aquellas Lunas API events invalid response: ' . json_last_error_msg());
                astronomyApiRecordValidation('events', false, false);
            } else {
                $items = $decoded['items'];
                usort($items, static function ($first, $second) use ($timezoneName): int {
                    $firstDate = is_array($first) ? astronomyEventDateTime($first['datetime'] ?? null, $timezoneName) : null;
                    $secondDate = is_array($second) ? astronomyEventDateTime($second['datetime'] ?? null, $timezoneName) : null;
                    return ($firstDate?->getTimestamp() ?? PHP_INT_MAX) <=> ($secondDate?->getTimestamp() ?? PHP_INT_MAX);
                });
                astronomyApiRecordValidation('events', true, true);
                if ($items === []) {
                    $emptyMessage = 'No se encontraron eventos lunares para este período y estos filtros.';
                }
            }
        }
    } catch (RuntimeException $exception) {
        $apiErrorMessage = 'No se pudieron cargar los eventos lunares en este momento.';
        error_log('Aquellas Lunas API configuration error: ' . $exception->getMessage());
    }
}

$groups = [];
foreach ($items as $event) {
    if (!is_array($event)) {
        error_log('Aquellas Lunas API events invalid item: expected object.');
        continue;
    }
    $date = astronomyEventDateTime($event['datetime'] ?? null, $timezoneName);
    $key = $date?->format('Y-m-d') ?? 'unknown';
    $groups[$key][] = $event;
}
$pageSeo = aquellasLunasSeoPage('Eventos lunares | Aquellas Lunas', 'Eventos lunares por fecha, ubicación y tipo.', '/eventos.php', 'article');
if ((string) ($_REQUEST['location_debug'] ?? '') === '1') {
    header('X-Astronomy-Location-Name: ' . rawurlencode($locationLabel));
    header('X-Astronomy-Geocoder-Status: ' . (string) ($GLOBALS['astronomy_location_geocoder_status'] ?? 'not_requested'));
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<?php renderSeoHead($pageSeo); ?>
<?php renderAnalyticsTracking(); ?>
<?php renderFaviconLinks(); ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/events.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/cloud-cover.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/page-recovery.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyMobileSwipeNavigationScript('events'); ?>
</head>
<body data-api-state="<?= $apiErrorMessage !== null ? 'error' : 'ok' ?>" data-cloud-cover-latitude="<?= htmlspecialchars((string) $latitude, ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-longitude="<?= htmlspecialchars((string) $longitude, ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-timezone="<?= htmlspecialchars($timezoneName, ENT_QUOTES, 'UTF-8') ?>"<?= astronomyMobileSwipeNavigationAttributes('events') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($today); ?>
    <?php renderAstronomySiteHeader('events', $location); ?>

    <main class="page events-page">
        <div class="container public-page-container events-container">
            <section class="hero events-hero atmosphere-card--night" aria-labelledby="events-title">
                <p class="eyebrow">LA LUNA COMO PROTAGONISTA</p>
                <h1 id="events-title"><?= htmlspecialchars(astronomySiteSectionLabel('events')) ?></h1>
                <p class="hero-subtitle">Fases, conjunciones, libraciones y otros momentos destacados.</p>

                <form id="events-query-form" class="events-controls" method="get">
                    <?php renderAstronomyDebugClockInput(); ?>
                    <input type="hidden" name="filters_submitted" value="1">
                    <label class="control-field events-date"><span>Desde</span><input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required></label>
                    <label class="control-field events-days"><span>Días</span><input type="number" name="days" min="1" max="366" value="<?= htmlspecialchars((string) $days) ?>" required></label>
                    <fieldset class="events-filters">
                        <legend>Tipos</legend>
                        <?php foreach (['moon_phase' => 'Fases', 'apsis' => 'Perigeo y apogeo', 'conjunction' => 'Conjunciones', 'earthshine' => 'Luz cenicienta', 'libration' => 'Libraciones', 'eclipse' => 'Eclipses'] as $type => $label): ?>
                            <label class="event-filter"><input type="checkbox" name="types[]" value="<?= $type ?>" <?= in_array($type, $selectedTypes, true) ? 'checked' : '' ?>><span><?= $label ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <button class="button button-primary events-submit" type="submit">Consultar</button>
                </form>

                <p id="events-loading" class="events-status" role="status" aria-live="polite" hidden>Cargando eventos lunares…</p>
                <?php if ($locationMessage !== ''): ?><p class="card-note status-info" role="status"><?= htmlspecialchars($locationMessage) ?></p><?php endif; ?>
                <?php if ($apiErrorMessage !== null): ?><div class="api-error-notice" data-api-error role="alert"><p><?= htmlspecialchars($apiErrorMessage) ?> Reintentá en unos segundos.</p><button type="button" class="button compact-secondary-button" data-api-retry>Reintentar</button></div><?php endif; ?>
            </section>

            <section class="events-results" aria-labelledby="results-title">
                <h2 id="results-title" class="visually-hidden">Resultados de efemérides</h2>
                <?php if ($emptyMessage !== null): ?><p class="card events-empty" role="status"><?= htmlspecialchars($emptyMessage) ?></p><?php endif; ?>
                <?php foreach ($groups as $key => $events): ?>
                    <?php $groupDate = astronomyEventDateTime($events[0]['datetime'] ?? null, $timezoneName); ?>
                    <section class="event-day" aria-labelledby="event-day-<?= htmlspecialchars($key) ?>">
                        <h2 id="event-day-<?= htmlspecialchars($key) ?>"><?= htmlspecialchars(eventsGroupDate($groupDate)) ?></h2>
                        <div class="event-list">
                            <?php foreach ($events as $eventIndex => $event): ?>
                                <?php
                                $eventType = is_string($event['type'] ?? null) ? $event['type'] : 'unknown';
                                $iconKey = astronomyIconKey($event);
                                $accessibleType = astronomyIconLabel($iconKey);
                                $presentation = astronomyEventPresentation($event, $timezoneName);
                                $publicDetails = is_array($presentation['public_details'] ?? null) ? $presentation['public_details'] : [];
                                $contactPoints = is_array($presentation['contact_points'] ?? null) ? $presentation['contact_points'] : [];
                                $eventAlert = is_string($presentation['alert'] ?? null) ? trim((string) $presentation['alert']) : '';
                                $eventDateTime = astronomyEventDateTime($event['datetime'] ?? null, $timezoneName);
                                $popoverId = 'event-technical-' . substr(md5($key . '-' . $eventIndex . '-' . ($event['datetime'] ?? '')), 0, 12);
                                ?>
                                <article class="event-card event-card--<?= htmlspecialchars((string) ($event['type'] ?? 'unknown')) ?>"<?= $eventDateTime !== null ? ' data-cloud-cover-event="' . htmlspecialchars($eventDateTime->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                    <?php renderAstronomyIcon($event, $latitude, 'event-symbol'); ?>
                                    <div class="event-content">
                                        <div class="event-heading"><h3><?= htmlspecialchars($presentation['title']) ?></h3><?php if ($presentation['show_time']): ?><time><?= htmlspecialchars($presentation['time_label']) ?></time><?php endif; ?></div>
                                        <?php if ($presentation['summary'] !== ''): ?><p><?= htmlspecialchars($presentation['summary']) ?></p><?php endif; ?>
                                        <?php $cloudPopoverId = 'event-cloud-cover-' . substr(md5($key . '-' . $eventIndex . '-' . ($event['datetime'] ?? '')), 0, 12); ?>
                                        <?php if ($eventDateTime !== null): ?><p class="event-cloud-cover" data-cloud-cover-value hidden><span data-cloud-cover-text></span> <button type="button" class="cloud-cover-info" data-cloud-cover-info popovertarget="<?= $cloudPopoverId ?>" aria-label="Ver distribución de las nubes" hidden>Nubes</button><span id="<?= $cloudPopoverId ?>" class="cloud-cover-popover" data-cloud-cover-popover popover role="dialog" aria-labelledby="<?= $cloudPopoverId ?>-title"><strong id="<?= $cloudPopoverId ?>-title">Distribución de las nubes</strong><span data-cloud-cover-layers></span><span>Las bajas suelen tapar más el cielo. Las altas pueden ser finas y dejar ver la Luna, aunque con menos contraste. Los porcentajes de las capas no se suman entre sí.</span></span></p><?php endif; ?>
                                        <?php if ($publicDetails !== []): ?>
                                            <dl class="event-facts">
                                                <?php foreach ($publicDetails as $entry): ?>
                                                    <?php
                                                    $factLabel = is_array($entry) && is_string($entry['label'] ?? null) ? trim((string) $entry['label']) : '';
                                                    $factValue = is_array($entry) && is_string($entry['value'] ?? null) ? trim((string) $entry['value']) : '';
                                                    if ($factLabel === '' || $factValue === '') {
                                                        continue;
                                                    }
                                                    ?>
                                                    <div><dt><?= htmlspecialchars($factLabel) ?></dt><dd><?= htmlspecialchars($factValue) ?></dd></div>
                                                <?php endforeach; ?>
                                            </dl>
                                        <?php endif; ?>
                                        <?php if ($contactPoints !== []): ?>
                                            <ul class="event-contacts" aria-label="Contactos del eclipse">
                                                <?php foreach ($contactPoints as $line): ?>
                                                    <?php if (is_string($line) && trim($line) !== ''): ?><li><?= htmlspecialchars(trim($line)) ?></li><?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <?php if ($eventAlert !== ''): ?><p class="event-alert" role="status"><?= htmlspecialchars($eventAlert) ?></p><?php endif; ?>
                                        <?php if ($presentation['explanation'] !== ''): ?><p class="event-note"><?= htmlspecialchars($presentation['explanation']) ?></p><?php endif; ?>
                                        <?php if ($presentation['technical_details'] !== []): ?>
                                            <button type="button" class="event-technical-trigger" popovertarget="<?= htmlspecialchars($popoverId) ?>" aria-label="Ver datos técnicos de <?= htmlspecialchars($presentation['title'], ENT_QUOTES, 'UTF-8') ?>">Datos técnicos</button>
                                            <div id="<?= htmlspecialchars($popoverId) ?>" class="event-technical-popover" popover role="dialog" aria-labelledby="<?= htmlspecialchars($popoverId) ?>-title">
                                                <div class="event-technical-heading"><h4 id="<?= htmlspecialchars($popoverId) ?>-title">Datos técnicos</h4><button type="button" popovertarget="<?= htmlspecialchars($popoverId) ?>" popovertargetaction="hide" aria-label="Cerrar datos técnicos">Cerrar</button></div>
                                                <dl><?php foreach ($presentation['technical_details'] as $label => $value): ?><div><dt><?= htmlspecialchars((string) $label) ?></dt><dd><?= htmlspecialchars((string) $value) ?></dd></div><?php endforeach; ?></dl>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="visually-hidden">Tipo: <?= htmlspecialchars($accessibleType) ?></span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </section>
            <?php renderAstronomyTimings(); ?>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
