<?php
require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/presentation.php';
require_once __DIR__ . '/includes/home-sky.php';
require_once __DIR__ . '/includes/event-presentation.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/site-sections.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/tonight.php';
sendDynamicNoCacheHeaders();

function normalizeLocationValue($value, float $min, float $max)
{
    return astronomyLocationCoordinate($value, $min, $max);
}

function sanitizeTimezone($value): ?string
{
    return astronomyLocationTimezone($value);
}

function homeApiRequest(string $url, string $context, int $timeout = 12, bool $customLocation = false): ?array
{
    $result = astronomyApiRequest($url, $context, $timeout);
    if ($customLocation && astronomyApiRejectedLocationParameters($result)) {
        astronomyRecoverDefaultLocationFromApi($result);
    }
    if ($result['body'] === false || $result['http_code'] !== 200) {
        astronomyApiRecordValidation($context, null, false);
        return null;
    }
    $decoded = json_decode($result['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        error_log('Aquellas Lunas API ' . $context . ' invalid response: ' . json_last_error_msg());
        astronomyApiRecordValidation($context, false, false);
        return null;
    }
    astronomyApiRecordValidation($context, true, true);
    return $decoded;
}

function formatHour(?string $value, string $timezoneName): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezoneName))->format('H:i');
    } catch (Exception $exception) {
        return '—';
    }
}

function formatDayLength(int $seconds): string
{
    return intdiv($seconds, 3600) . 'h ' . intdiv($seconds % 3600, 60) . 'm';
}

function homeEventDate($value, string $timezoneName): ?DateTimeImmutable
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezoneName));
    } catch (Exception $exception) {
        return null;
    }
}

function homeShortDate(DateTimeImmutable $date): string
{
    $months = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
    return (int) $date->format('j') . ' ' . $months[(int) $date->format('n')];
}

function homePhaseDate(DateTimeImmutable $date): string
{
    $months = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
    return (int) $date->format('j') . ' de ' . $months[(int) $date->format('n')] . ' · ' . $date->format('H:i');
}

function homeLongDate(DateTimeImmutable $date): string
{
    $months = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
    return (int) $date->format('j') . ' de ' . $months[(int) $date->format('n')];
}

function homeDecimal($value, int $decimals = 1): string
{
    return is_numeric($value) ? number_format((float) $value, $decimals, ',', '.') : '—';
}

$location = astronomyLocationContext();
$timezoneName = $location['timezone'];
$latitude = $location['latitude'];
$longitude = $location['longitude'];
$locationLabel = $location['name'];
$locationMode = $location['mode'];
$locationMessage = astronomyLocationStatusMessage((string) ($_GET['location_status'] ?? ''));

$now = get_current_datetime($timezoneName);
$dateParam = $now->format('Y-m-d');
$apiData = null;
$phasesData = null;
$upcomingData = null;
$tonightData = null;
$moonInstantRaw = null;
$apiErrorMessage = null;
$phasesErrorMessage = null;
$upcomingErrorMessage = null;
$tonightErrorMessage = null;
$commonParameters = ['latitude' => $latitude, 'longitude' => $longitude, 'timezone' => $timezoneName];

try {
    $apiConfig = loadAstronomyApiConfig();
    $apiBaseUrl = $apiConfig['base_url'];
    error_log('Aquellas Lunas API configuration source: ' . $apiConfig['source']);
    $moonInstantParameters = ['datetime' => $now->format(DateTimeInterface::ATOM)] + $commonParameters;
    $usingCustomLocation = $locationMode !== 'default';
    $moonInstantRaw = homeApiRequest($apiBaseUrl . '/v1/moon/instant?' . http_build_query($moonInstantParameters), 'moon instant', 12, $usingCustomLocation);
    $apiData = homeApiRequest($apiBaseUrl . '/v1/astronomy/daily?' . http_build_query(['date' => $dateParam] + $commonParameters), 'daily', 12, $usingCustomLocation);
    if ($apiData === null || !is_array($apiData['sun'] ?? null) || !is_array($apiData['moon'] ?? null)) {
        if ($apiData !== null) {
            astronomyApiRecordValidation('daily', true, false);
            error_log('Aquellas Lunas API daily invalid response: missing sun or moon data.');
            $apiData = null;
        }
        $apiErrorMessage = 'No se pudieron cargar los datos del cielo en este momento.';
    }
    $phasesStartDate = $now->modify('-35 days')->format('Y-m-d');
    $phasesParameters = ['start_date' => $phasesStartDate, 'days' => 80, 'types' => 'moon_phase'] + $commonParameters;
    $phasesData = homeApiRequest($apiBaseUrl . '/v1/astronomy/events?' . http_build_query($phasesParameters), 'home phases', 35, $usingCustomLocation);
    if ($phasesData === null || !is_array($phasesData['items'] ?? null)) {
        if ($phasesData !== null) {
            astronomyApiRecordValidation('home phases', true, false);
        }
        $phasesData = null;
        $phasesErrorMessage = 'Las próximas fases no están disponibles por el momento.';
    }
    $upcomingParameters = ['start_date' => $dateParam, 'days' => 30, 'types' => 'moon_phase,apsis,conjunction,earthshine,full_moon_observation', 'max_difference_minutes' => 70] + $commonParameters;
    $upcomingData = homeApiRequest($apiBaseUrl . '/v1/astronomy/events?' . http_build_query($upcomingParameters), 'home upcoming', 20, $usingCustomLocation);
    if ($upcomingData === null || !is_array($upcomingData['items'] ?? null)) {
        if ($upcomingData !== null) {
            astronomyApiRecordValidation('home upcoming', true, false);
        }
        $upcomingData = null;
        $upcomingErrorMessage = 'Los próximos eventos no están disponibles por el momento.';
    }
    $tonightData = astronomyTonightRequest(
        $apiBaseUrl,
        $location,
        $dateParam,
        'summary',
        'home tonight',
        8
    );
    if ($tonightData === null) {
        $tonightErrorMessage = 'La visibilidad de esta noche no está disponible por el momento.';
    }
} catch (RuntimeException $exception) {
    $apiErrorMessage = 'No se pudieron cargar los datos del cielo en este momento.';
    $phasesErrorMessage = 'Las próximas fases no están disponibles por el momento.';
    $upcomingErrorMessage = 'Los próximos eventos no están disponibles por el momento.';
    $tonightErrorMessage = 'La visibilidad de esta noche no está disponible por el momento.';
    error_log('Aquellas Lunas API configuration error: ' . $exception->getMessage());
}

$phaseOrder = ['new_moon' => 'Luna nueva', 'first_quarter' => 'Cuarto creciente', 'full_moon' => 'Luna llena', 'last_quarter' => 'Cuarto menguante'];
$nextPhases = [];
$upcomingEvents = [];
$phaseItems = $phasesData['items'] ?? [];
foreach ($phaseItems as $event) {
    if (!is_array($event)) {
        continue;
    }
    $subtype = $event['subtype'] ?? '';
    $eventDate = homeEventDate($event['datetime'] ?? null, $timezoneName);
    if (($event['type'] ?? '') === 'moon_phase' && isset($phaseOrder[$subtype]) && $eventDate !== null && $eventDate >= $now && !isset($nextPhases[$subtype])) {
        $nextPhases[$subtype] = $event;
    }
}
foreach (($upcomingData['items'] ?? []) as $event) {
    $eventDate = is_array($event) ? homeEventDate($event['datetime'] ?? null, $timezoneName) : null;
    if (is_array($event) && $eventDate !== null && $eventDate >= $now) {
        $upcomingEvents[] = $event;
    }
}
usort($upcomingEvents, static function (array $first, array $second) use ($timezoneName): int {
    $firstDate = homeEventDate($first['datetime'] ?? null, $timezoneName);
    $secondDate = homeEventDate($second['datetime'] ?? null, $timezoneName);
    return ($firstDate?->getTimestamp() ?? PHP_INT_MAX) <=> ($secondDate?->getTimestamp() ?? PHP_INT_MAX);
});
$upcomingEvents = array_slice($upcomingEvents, 0, 5);
$tonightPresentation = $tonightData !== null
    ? astronomyTonightSummaryPresentation($tonightData, $now, $timezoneName)
    : null;

if ($phasesData !== null && count($nextPhases) < 4) {
    error_log('Aquellas Lunas API home phases: incomplete future phase set.');
}
uasort($nextPhases, static function (array $first, array $second) use ($timezoneName): int {
    $firstDate = homeEventDate($first['datetime'] ?? null, $timezoneName);
    $secondDate = homeEventDate($second['datetime'] ?? null, $timezoneName);
    return ($firstDate?->getTimestamp() ?? PHP_INT_MAX) <=> ($secondDate?->getTimestamp() ?? PHP_INT_MAX);
});

$moonInstant = homeValidateMoonInstant($moonInstantRaw);
$moonSituation = null;
$nextMoonRise = null;
if ($moonInstantRaw !== null && $moonInstant === null) {
    astronomyApiRecordValidation('moon instant', true, false);
    error_log('Aquellas Lunas API moon instant invalid response: missing observer or datetime data.');
}
if ($moonInstant !== null) {
    if ($moonInstant['above_horizon'] === false && $apiData !== null) {
        $nextMoonRise = homeFutureMoonrise($apiData['moon']['rise'] ?? null, $moonInstant['instant'], $timezoneName);
        if ($nextMoonRise === null && isset($apiBaseUrl)) {
            $nextDayData = homeApiRequest(
                $apiBaseUrl . '/v1/astronomy/daily?' . http_build_query(['date' => $now->modify('+1 day')->format('Y-m-d')] + $commonParameters),
                'next moonrise',
                12
            );
            if (is_array($nextDayData['moon'] ?? null)) {
                $nextMoonRise = homeFutureMoonrise($nextDayData['moon']['rise'] ?? null, $moonInstant['instant'], $timezoneName);
            }
        }
    }
    $newMoonDifferenceDays = homeNearestNewMoonDifferenceDays($phaseItems, $moonInstant['instant'], $timezoneName);
    if ($newMoonDifferenceDays === null) {
        $newMoonDifferenceDays = homeNewMoonDifferenceFromAge($moonInstant['age_days']);
    }
    try {
        $moonriseNoticeMaxMinutes = loadMoonriseNoticeMaxMinutes();
    } catch (RuntimeException $exception) {
        error_log('Aquellas Lunas moonrise notice configuration error: ' . $exception->getMessage());
        $moonriseNoticeMaxMinutes = DEFAULT_MOONRISE_NOTICE_MAX_MINUTES;
    }
    $moonSituationPresentation = homeMoonSituationPresentation($moonInstant, $newMoonDifferenceDays, $nextMoonRise, $moonriseNoticeMaxMinutes);
    $moonSituation = $moonSituationPresentation['text'];
    $moonriseNoticeLevel = $moonSituationPresentation['level'];
}

$moonPhase = capitalizeVisibleText($apiData['moon']['phase']['name'] ?? 'Sin datos');
$illumination = isset($apiData['moon']['illumination_percent']) ? round((float) $apiData['moon']['illumination_percent']) . '% iluminada' : 'Iluminación no disponible';
$moonRise = formatHour($apiData['moon']['rise'] ?? null, $timezoneName);
$moonSet = formatHour($apiData['moon']['set'] ?? null, $timezoneName);
$apparentSize = isset($apiData['moon']['apparent_size_percent']) ? homeDecimal($apiData['moon']['apparent_size_percent']) . '% del promedio' : 'No disponible';
$sunRise = formatHour($apiData['sun']['rise'] ?? null, $timezoneName);
$sunSet = formatHour($apiData['sun']['set'] ?? null, $timezoneName);
$dayLength = isset($apiData['sun']['day_length_seconds']) ? formatDayLength((int) $apiData['sun']['day_length_seconds']) : '—';
$sunIntervals = homeNormalizeDailyIntervals($apiData['sun']['visibility_intervals'] ?? null, $dateParam, $timezoneName);
$moonIntervals = homeNormalizeDailyIntervals($apiData['moon']['visibility_intervals'] ?? null, $dateParam, $timezoneName);
$sunVisibilityLabel = $sunIntervals !== null ? homeVisibilityLabel('El Sol', $sunIntervals) : null;
$moonVisibilityLabel = $moonIntervals !== null ? homeVisibilityLabel('La Luna', $moonIntervals) : null;
$nowMinutes = ((int) $now->format('G') * 60) + (int) $now->format('i');
$nowPosition = max(0.0, min(100.0, $nowMinutes / 14.4));
$nowLabel = (astronomyCurrentDateTimeIsSimulated() ? 'Hora simulada: ' : 'Hora actual: ') . $now->format('H:i') . '.';
$debugNowValue = astronomyCurrentDateTimeIsSimulated() ? $now->format(DateTimeInterface::ATOM) : '';
$altitudeProfileParameters = [
    'date' => $dateParam,
    'latitude' => $latitude,
    'longitude' => $longitude,
    'timezone' => $timezoneName,
];
$moonAltitudeProfileUrl = 'altitude-profile.php?' . http_build_query(['target' => 'moon'] + $altitudeProfileParameters);
$sunAltitudeProfileUrl = 'altitude-profile.php?' . http_build_query(['target' => 'sun'] + $altitudeProfileParameters);
$moonImageUrl = 'moon-image.php?' . http_build_query(['latitude' => $latitude, 'longitude' => $longitude, 'timezone' => $timezoneName, 'datetime' => $now->format(DateTimeInterface::ATOM)]);
$monthNames = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
$displayDate = (int) $now->format('j') . ' de ' . $monthNames[(int) $now->format('n')] . ' de ' . $now->format('Y');
$nearestEvent = $upcomingEvents[0] ?? null;
$nearestEventDate = is_array($nearestEvent) ? homeEventDate($nearestEvent['datetime'] ?? null, $timezoneName) : null;
$nearestEventPresentation = is_array($nearestEvent) ? astronomyEventPresentation($nearestEvent, $timezoneName) : null;
$nearestEventTitle = $nearestEventPresentation !== null ? capitalizeVisibleText($nearestEventPresentation['title']) : null;
$nearestEventDetail = $nearestEventPresentation !== null ? capitalizeVisibleText($nearestEventPresentation['summary']) : '';
$nearestEventShowsTime = ($nearestEventPresentation['show_time'] ?? false) === true;
$hasApiError = $apiErrorMessage !== null || $phasesErrorMessage !== null || $upcomingErrorMessage !== null;
$pageSeo = aquellasLunasSeoPage('Aquellas Lunas | El cielo de hoy', 'El cielo de hoy, la Luna aparente y las próximas efemérides para tu ubicación.', '/');
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
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/home-sky.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/cloud-cover.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/page-recovery.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyMobileSwipeNavigationScript('home'); ?>
</head>
<body data-api-state="<?= $hasApiError ? 'error' : 'ok' ?>" data-cloud-cover-latitude="<?= htmlspecialchars((string) $latitude, ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-longitude="<?= htmlspecialchars((string) $longitude, ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-timezone="<?= htmlspecialchars($timezoneName, ENT_QUOTES, 'UTF-8') ?>"<?= astronomyMobileSwipeNavigationAttributes('home') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($now); ?>
    <?php renderAstronomySiteHeader('home', $location); ?>

    <main class="page home-page"><div class="container">
        <section class="home-heading" aria-labelledby="hero-title">
            <div><h1 id="hero-title">El cielo de hoy</h1><p><?= htmlspecialchars($displayDate) ?></p></div>
            <?php if ($locationMessage !== ''): ?><p class="home-message status-info" role="status"><?= htmlspecialchars($locationMessage) ?></p><?php endif; ?>
            <?php if ($hasApiError): ?><div class="api-error-notice" data-api-error role="alert"><p>No pudimos actualizar todos los datos astronómicos. Reintentá en unos segundos.</p><button type="button" class="button compact-secondary-button" data-api-retry>Reintentar</button></div><?php endif; ?>
        </section>

        <?php if ($apiData !== null): ?>
        <section class="home-sky" aria-label="Luna y Sol de hoy">
            <article class="home-moon">
                <div class="home-moon-image"><img src="<?= htmlspecialchars($moonImageUrl, ENT_QUOTES, 'UTF-8') ?>" width="320" height="320" alt="Apariencia actual de la Luna desde <?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="home-moon-copy"><p class="home-kicker">La Luna ahora</p><h2><?= htmlspecialchars($moonPhase) ?></h2><p class="home-moon-light"><?= htmlspecialchars($illumination) ?></p><p class="event-cloud-cover" data-current-cloud-cover hidden></p><?php if ($moonSituation !== null): ?><p class="home-moon-situation<?= $moonriseNoticeLevel !== null ? ' moonrise-notice moonrise-notice--' . htmlspecialchars($moonriseNoticeLevel, ENT_QUOTES, 'UTF-8') : '' ?>"><?= htmlspecialchars($moonSituation) ?></p><?php endif; ?><p>Sale <?= htmlspecialchars($moonRise) ?> · <?= $moonSet === '—' ? 'No se pone hoy' : 'Se pone ' . htmlspecialchars($moonSet) ?></p><p class="home-detail">Tamaño aparente: <?= htmlspecialchars($apparentSize) ?></p></div>
                <figure class="home-altitude-profile home-moon-chart" data-altitude-profile data-target="moon" data-endpoint="<?= htmlspecialchars($moonAltitudeProfileUrl, ENT_QUOTES, 'UTF-8') ?>" data-date="<?= htmlspecialchars($dateParam, ENT_QUOTES, 'UTF-8') ?>" data-timezone="<?= htmlspecialchars($timezoneName, ENT_QUOTES, 'UTF-8') ?>" data-debug-now="<?= htmlspecialchars($debugNowValue, ENT_QUOTES, 'UTF-8') ?>" aria-busy="true"><div class="home-altitude-profile__canvas" aria-hidden="true"><span class="home-altitude-profile__loading-horizon"></span></div><figcaption class="home-altitude-profile__status" data-profile-status aria-live="polite">Cargando recorrido…</figcaption></figure>
            </article>
            <div class="home-sky-secondary">
                <article class="home-sun">
                    <p class="home-kicker">Sol hoy</p><h2><span><?= htmlspecialchars($sunRise) ?></span><span aria-hidden="true">→</span><span><?= htmlspecialchars($sunSet) ?></span></h2><p><?= htmlspecialchars($dayLength) ?> de luz</p>
                    <figure class="home-altitude-profile home-sun-chart" data-altitude-profile data-target="sun" data-endpoint="<?= htmlspecialchars($sunAltitudeProfileUrl, ENT_QUOTES, 'UTF-8') ?>" data-date="<?= htmlspecialchars($dateParam, ENT_QUOTES, 'UTF-8') ?>" data-timezone="<?= htmlspecialchars($timezoneName, ENT_QUOTES, 'UTF-8') ?>" data-debug-now="<?= htmlspecialchars($debugNowValue, ENT_QUOTES, 'UTF-8') ?>" aria-busy="true"><div class="home-altitude-profile__canvas" aria-hidden="true"><span class="home-altitude-profile__loading-horizon"></span></div><figcaption class="home-altitude-profile__status" data-profile-status aria-live="polite">Cargando recorrido…</figcaption></figure>
                </article>
                <aside class="home-next-highlight" aria-labelledby="home-next-highlight-title">
                    <p class="home-kicker">Próximo en el cielo</p>
                    <?php if ($nearestEventTitle !== null): ?>
                        <h2 id="home-next-highlight-title"><?= htmlspecialchars($nearestEventTitle) ?></h2>
                        <?php if ($nearestEventDate !== null): ?><p class="home-next-time"><?= htmlspecialchars($nearestEventShowsTime ? homePhaseDate($nearestEventDate) : homeLongDate($nearestEventDate)) ?></p><?php endif; ?>
                        <?php if ($nearestEventDetail !== ''): ?><p><?= htmlspecialchars($nearestEventDetail) ?></p><?php endif; ?>
                        <a href="<?= htmlspecialchars(astronomyInternalUrl('eventos.php'), ENT_QUOTES, 'UTF-8') ?>">Ver detalle del evento</a>
                    <?php else: ?>
                        <h2 id="home-next-highlight-title">Explorá los próximos días</h2>
                        <p>Consultá horarios y visibilidad para tu ubicación.</p>
                        <a href="<?= htmlspecialchars(astronomyInternalUrl('sol-y-luna.php'), ENT_QUOTES, 'UTF-8') ?>">Ver próximos 30 días</a>
                    <?php endif; ?>
                </aside>
            </div>
        </section>
        <?php endif; ?>

        <section class="home-section home-tonight-section" aria-labelledby="home-tonight-title">
            <a class="home-tonight-card" href="<?= htmlspecialchars(astronomyInternalUrl('cielo-de-esta-noche.php'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="home-tonight-card__icon" aria-hidden="true">✦</span>
                <span class="home-tonight-card__content">
                    <span class="home-kicker">¿Qué planetas se ven esta noche?</span>
                    <?php if ($tonightPresentation !== null): ?>
                        <strong id="home-tonight-title"><?= htmlspecialchars($tonightPresentation['title']) ?></strong>
                        <span><?= htmlspecialchars($tonightPresentation['text']) ?></span>
                    <?php else: ?>
                        <strong id="home-tonight-title">El cielo de esta noche</strong>
                        <span><?= htmlspecialchars($tonightErrorMessage ?? 'Consultá qué podrá verse desde tu ubicación.') ?></span>
                    <?php endif; ?>
                </span>
                <span class="home-tonight-card__arrow" aria-hidden="true">→</span>
            </a>
        </section>

        <section class="home-section" aria-labelledby="phases-title">
            <div class="home-section-heading"><h2 id="phases-title">Próximas fases</h2></div>
            <?php if ($nextPhases !== []): ?><div class="home-phases">
                <?php foreach ($nextPhases as $subtype => $phase): ?>
                    <?php $phasePresentation = astronomyEventPresentation($phase, $timezoneName); $label = $phasePresentation['title']; $phaseDate = homeEventDate($phase['datetime'] ?? null, $timezoneName); ?>
                    <article class="home-phase"><span aria-hidden="true">☾</span><div><h3><?= htmlspecialchars($label) ?></h3><p><?= $phaseDate !== null ? htmlspecialchars(homePhaseDate($phaseDate)) : 'Fecha no disponible' ?></p></div></article>
                <?php endforeach; ?>
            </div><?php elseif ($phasesErrorMessage !== null): ?><p class="home-unavailable"><?= htmlspecialchars($phasesErrorMessage) ?></p><?php endif; ?>
        </section>

        <section class="home-section" aria-labelledby="upcoming-title">
            <div class="home-section-heading"><h2 id="upcoming-title">Lo próximo</h2><a href="<?= htmlspecialchars(astronomyInternalUrl('eventos.php'), ENT_QUOTES, 'UTF-8') ?>">Ver todos los eventos</a></div>
            <?php if ($upcomingEvents !== []): ?><div class="home-upcoming">
                <?php foreach ($upcomingEvents as $eventIndex => $event): ?>
                    <?php $eventDate = homeEventDate($event['datetime'] ?? null, $timezoneName); $eventPresentation = astronomyEventPresentation($event, $timezoneName); $eventTitle = $eventPresentation['title']; $eventDetail = $eventPresentation['summary']; $cloudPopoverId = 'home-cloud-cover-' . $eventIndex; ?>
                    <article<?= $eventDate !== null ? ' data-cloud-cover-event="' . htmlspecialchars($eventDate->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') . '"' : '' ?>><time datetime="<?= $eventDate?->format(DateTimeInterface::ATOM) ?? '' ?>"><?= $eventDate !== null ? htmlspecialchars(homeShortDate($eventDate)) : 'Sin fecha' ?></time><div><h3><?= htmlspecialchars($eventTitle) ?></h3><?php if ($eventDetail !== ''): ?><p><?= htmlspecialchars($eventDetail) ?></p><?php endif; ?><?php if ($eventDate !== null): ?><p class="event-cloud-cover" data-cloud-cover-value hidden><span data-cloud-cover-text></span> <button type="button" class="cloud-cover-info" data-cloud-cover-info popovertarget="<?= $cloudPopoverId ?>" aria-label="Ver distribución de las nubes" hidden>☁️</button><span id="<?= $cloudPopoverId ?>" class="cloud-cover-popover" data-cloud-cover-popover popover role="dialog" aria-labelledby="<?= $cloudPopoverId ?>-title"><strong id="<?= $cloudPopoverId ?>-title">Distribución de las nubes</strong><span data-cloud-cover-layers></span><span>Las bajas suelen tapar más el cielo. Las altas pueden ser finas y dejar ver la Luna, aunque con menos contraste. Los porcentajes de las capas no se suman entre sí.</span></span></p><?php endif; ?></div></article>
                <?php endforeach; ?>
            </div><?php elseif ($upcomingErrorMessage !== null): ?><p class="home-unavailable"><?= htmlspecialchars($upcomingErrorMessage) ?></p><?php else: ?><p class="home-unavailable">No hay otros eventos cercanos para mostrar.</p><?php endif; ?>
        </section>

        <nav class="home-actions" aria-label="Explorar el cielo"><a class="button button-primary" href="<?= htmlspecialchars(astronomyInternalUrl('sol-y-luna.php'), ENT_QUOTES, 'UTF-8') ?>">Ver próximos 30 días</a><a class="button" href="<?= htmlspecialchars(astronomyInternalUrl('eventos.php'), ENT_QUOTES, 'UTF-8') ?>">Ver todos los eventos</a></nav>
        <?php renderAstronomyTimings(); ?>
    </div></main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
