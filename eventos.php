<?php
require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/astronomy-events.php';
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
require_once __DIR__ . '/includes/date-format.php';
require_once __DIR__ . '/includes/event-date-header.php';
require_once __DIR__ . '/includes/explore-sky.php';
require_once __DIR__ . '/includes/calendar-event.php';
require_once __DIR__ . '/includes/eclipse-detail-component.php';
require_once __DIR__ . '/includes/photography-links.php';
require_once __DIR__ . '/includes/event-infographic.php';
require_once __DIR__ . '/includes/store-admin-auth.php';
sendDynamicNoCacheHeaders();

$showEventInfographicLinks = storeAdminHasValidSessionCookie();

$availableTypes = astronomyEventPublicTypesForSurface(ASTRONOMY_EVENT_SURFACE_EVENTS);
$availableTypeLabels = array_intersect_key(astronomyEventPublicTypeLabels(), array_flip($availableTypes));

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
    return astronomyNearbyEventDate($date);
}

$location = astronomyLocationContext();
$latitude = $location['latitude'];
$longitude = $location['longitude'];
$timezoneName = $location['timezone'];
$locationLabel = $location['name'];
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
        $eventsData = astronomyEvents([
            'start_date' => $startDate,
            'days' => $days,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezoneName,
            'types' => implode(',', $selectedTypes),
            'max_difference_minutes' => (int) astronomyEditorialNumber('event.full_moon.max_difference_minutes'),
        ], 'events', 35);
        $items = array_values(array_filter(
            $eventsData['items'],
            static fn($event): bool => is_array($event) && astronomyEarthshineEventIsDisplayable($event)
        ));
        $items = astronomyFilterEventsForSurface($items, ASTRONOMY_EVENT_SURFACE_EVENTS);
        if ($items === []) {
            $emptyMessage = 'No se encontraron eventos lunares para este período y estos filtros.';
        }
    } catch (RuntimeException $exception) {
        $apiErrorMessage = 'No se pudieron cargar los eventos lunares en este momento.';
        error_log('Aquellas Lunas events source error: ' . $exception->getMessage());
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
$pageSeo = aquellasLunasSeoPage('Eventos lunares | Aquellas Lunas', 'Eventos lunares por fecha, ubicación y tipo.', '/eventos.php', 'webpage');
if (canUseSiteDebugTools() && (string) ($_REQUEST['location_debug'] ?? '') === '1') {
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
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/home-v2.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/event-infographics.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/events.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyEditorialFrontendConfiguration(); ?>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/cloud-cover.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/calendar-scheduler.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/eclipses.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
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
                        <?php foreach ($availableTypeLabels as $type => $label): ?>
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
                        <?php renderAstronomyEventDateHeader($groupDate, $today, eventsGroupDate($groupDate), ['class' => 'event-day__date', 'date_tag' => 'h2', 'id' => 'event-day-' . $key]); ?>
                        <div class="event-list">
                            <?php foreach ($events as $eventIndex => $event): ?>
                                <?php
                                $eventType = is_string($event['type'] ?? null) ? $event['type'] : 'unknown';
                                $iconKey = astronomyIconKey($event);
                                $accessibleType = astronomyIconLabel($iconKey);
                                $presentation = astronomyEventPresentation($event, $timezoneName);
                                $observation = is_array($presentation['observation'] ?? null) ? $presentation['observation'] : null;
                                $publicDetails = is_array($presentation['public_details'] ?? null) ? $presentation['public_details'] : [];
                                $contactPoints = is_array($presentation['contact_points'] ?? null) ? $presentation['contact_points'] : [];
                                $eventAlert = is_string($presentation['alert'] ?? null) ? trim((string) $presentation['alert']) : '';
                                $eventDateTime = astronomyEventDateTime($event['datetime'] ?? null, $timezoneName);
                                $observationMoment = astronomyFullMoonObservationMoment($event, $timezoneName);
                                $cloudDateTime = $observation['cloud_time'] ?? $observationMoment['date'] ?? $eventDateTime;
                                $popoverId = 'event-technical-' . substr(md5($key . '-' . $eventIndex . '-' . ($event['datetime'] ?? '')), 0, 12);
                                $calendarEvent = astronomyCalendarEventData($event, $presentation, $timezoneName, $locationLabel, astronomyCalendarPageUrl('eventos.php'));
                                $photographyUrl = photographyEventUrl($event, $location, $observation['start'] ?? $observationMoment['date'] ?? $eventDateTime);
                                $infographicUrl = astronomyEventInfographicIsEligible($event) ? astronomyEventInfographicUrl($event, $timezoneName) : null;
                                ?>
                                <article class="event-card event-card--<?= htmlspecialchars((string) ($event['type'] ?? 'unknown')) ?>"<?= $cloudDateTime !== null ? ' data-cloud-cover-event="' . htmlspecialchars($cloudDateTime->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                    <?php renderAstronomyIcon($event, $latitude, 'event-symbol'); ?>
                                    <div class="event-content">
                                        <div class="event-heading"><h3><?= htmlspecialchars($presentation['title']) ?></h3><?php if ($presentation['show_time']): ?><time><?= htmlspecialchars($presentation['time_label']) ?></time><?php endif; ?></div>
                                        <?php if ($presentation['summary'] !== ''): ?><p><?= htmlspecialchars($presentation['summary']) ?></p><?php endif; ?>
                                        <?php if ($observation !== null): ?><p class="event-observation-moment"><?= htmlspecialchars($observation['first_label']) ?> · <time datetime="<?= htmlspecialchars($observation['first_time']->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($observation['first_time']->format('H:i')) ?></time> · <?= htmlspecialchars($observation['second_label']) ?> · <time datetime="<?= htmlspecialchars($observation['second_time']->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($observation['second_time']->format('H:i')) ?></time></p><?php endif; ?>
                                        <?php if ($observationMoment !== null): ?><p class="event-observation-moment"><?= htmlspecialchars($observationMoment['label']) ?> · <time datetime="<?= htmlspecialchars($observationMoment['date']->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($observationMoment['date']->format('H:i')) ?></time></p><?php endif; ?>
                                        <?php $cloudPopoverId = 'event-cloud-cover-' . substr(md5($key . '-' . $eventIndex . '-' . ($event['datetime'] ?? '')), 0, 12); ?>
                                        <?php if ($cloudDateTime !== null): ?><p class="event-cloud-cover" data-cloud-cover-value hidden><?php if ($observationMoment !== null || $observation !== null): ?><span class="weather-cloud-icon weather-cloud-icon--compact" data-cloud-cover-inline-icon hidden></span><?php endif; ?><span data-cloud-cover-text></span> <button type="button" class="cloud-cover-info" data-cloud-cover-info popovertarget="<?= $cloudPopoverId ?>" aria-label="Ver detalle de altura de las nubes" hidden><span class="cloud-cover-info__icon" aria-hidden="true"></span><span>Altura de nubes</span></button><span id="<?= $cloudPopoverId ?>" class="cloud-cover-popover" data-cloud-cover-popover popover role="dialog" aria-labelledby="<?= $cloudPopoverId ?>-title"><strong id="<?= $cloudPopoverId ?>-title">Distribución de las nubes</strong><span data-cloud-cover-layers></span><span>Las bajas suelen tapar más el cielo. Las altas pueden ser finas y dejar ver la Luna, aunque con menos contraste. Los porcentajes de las capas no se suman entre sí.</span></span></p><?php endif; ?>
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
                                        <?php if ($presentation['technical_details'] !== [] && $eventType !== 'eclipse'): ?>
                                            <button type="button" class="event-technical-trigger" popovertarget="<?= htmlspecialchars($popoverId) ?>" aria-label="Ver datos técnicos de <?= htmlspecialchars($presentation['title'], ENT_QUOTES, 'UTF-8') ?>">Datos técnicos</button>
                                            <div id="<?= htmlspecialchars($popoverId) ?>" class="event-technical-popover" popover role="dialog" aria-labelledby="<?= htmlspecialchars($popoverId) ?>-title">
                                                <div class="event-technical-heading"><h4 id="<?= htmlspecialchars($popoverId) ?>-title">Datos técnicos</h4><button type="button" popovertarget="<?= htmlspecialchars($popoverId) ?>" popovertargetaction="hide" aria-label="Cerrar datos técnicos">Cerrar</button></div>
                                                <dl><?php foreach ($presentation['technical_details'] as $label => $value): ?><div><dt><?= htmlspecialchars((string) $label) ?></dt><dd><?= htmlspecialchars((string) $value) ?></dd></div><?php endforeach; ?></dl>
                                            </div>
                                        <?php endif; ?>
                                        <div class="event-actions"><?php renderAstronomyCalendarLink($calendarEvent); ?><?php renderPhotographyEventLink($photographyUrl); ?><?php if ($showEventInfographicLinks && $infographicUrl !== null): ?><a class="event-infographic-link" href="<?= htmlspecialchars($infographicUrl, ENT_QUOTES, 'UTF-8') ?>">Crear infografía</a><?php endif; ?></div>
                                        <?php if ($eventType === 'eclipse'): ?><?php renderAstronomyEclipseDetailTrigger($event, 'Datos técnicos'); ?><?php renderAstronomyEclipseDetailTemplate($event, $timezoneName, $locationLabel, astronomyCalendarPageUrl('eclipses.php'), null, $location); ?><?php endif; ?>
                                    </div>
                                    <span class="visually-hidden">Tipo: <?= htmlspecialchars($accessibleType) ?></span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </section>
            <?php renderAstronomyExploreSky('events'); ?>
            <?php renderAstronomyTimings(); ?>
        </div>
    </main>
    <?php renderAstronomyEclipseModal(); ?>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
