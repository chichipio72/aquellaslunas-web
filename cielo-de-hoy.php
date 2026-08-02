<?php
require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/astronomy-events.php';
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
require_once __DIR__ . '/includes/astronomy-icon.php';
require_once __DIR__ . '/includes/explore-sky.php';
require_once __DIR__ . '/includes/moon-phase-presentation.php';
sendDynamicNoCacheHeaders();

function todayApiRequest(string $url, string $context, int $timeout, bool $customLocation): ?array
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
        astronomyApiRecordValidation($context, false, false);
        return null;
    }
    astronomyApiRecordValidation($context, true, true);
    return $decoded;
}

function todayDateTime($value, string $timezone): ?DateTimeImmutable
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezone));
    } catch (Throwable $exception) {
        return null;
    }
}

function todayHour($value, string $timezone): ?string
{
    return todayDateTime($value, $timezone)?->format('H:i');
}

function todayLongDate(DateTimeImmutable $date): string
{
    $months = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
    return (int) $date->format('j') . ' de ' . $months[(int) $date->format('n')] . ' de ' . $date->format('Y');
}

function todayDuration($seconds): ?string
{
    if (!is_numeric($seconds) || (int) $seconds < 0) {
        return null;
    }
    $seconds = (int) $seconds;
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return ($hours > 0 ? $hours . ' h ' : '') . $minutes . ' min';
}

function todayPeriod(array $period, string $timezone): ?array
{
    $start = todayHour($period['start'] ?? null, $timezone);
    $end = todayHour($period['end'] ?? null, $timezone);
    if ($start === null || $end === null) {
        return null;
    }
    return ['start' => $start, 'end' => $end, 'duration' => todayDuration($period['duration_seconds'] ?? null)];
}

function todayDayPart(DateTimeImmutable $date): string
{
    $hour = (int) $date->format('G');
    return $hour < astronomyEditorialNumber('today.daypart.morning_hour') ? 'la madrugada' : ($hour < astronomyEditorialNumber('today.daypart.afternoon_hour') ? 'la mañana' : ($hour < astronomyEditorialNumber('today.daypart.night_hour') ? 'la tarde' : 'la noche'));
}

function todayMoonSummary(array $moon, string $timezone, DateTimeImmutable $reference, bool $isToday): string
{
    $intervals = is_array($moon['visibility_intervals'] ?? null) ? $moon['visibility_intervals'] : [];
    if ($intervals === []) {
        return astronomyEditorialText('today.moon.no_intervals');
    }
    $normalized = [];
    foreach ($intervals as $interval) {
        $start = is_array($interval) ? todayDateTime($interval['start'] ?? null, $timezone) : null;
        $end = is_array($interval) ? todayDateTime($interval['end'] ?? null, $timezone) : null;
        if ($start !== null && $end !== null) {
            $normalized[] = ['start' => $start, 'end' => $end];
        }
    }
    if ($normalized === []) {
        return astronomyEditorialText('today.moon.unavailable');
    }
    if ($isToday) {
        foreach ($normalized as $interval) {
            if ($reference >= $interval['start'] && $reference < $interval['end']) {
                $minutes = (int) floor(($interval['end']->getTimestamp() - $reference->getTimestamp()) / 60);
                return $minutes > 0 && $minutes <= astronomyEditorialNumber('today.visibility.soon_minutes')
                    ? astronomyEditorialText('today.moon.sets_soon', ['minutos' => $minutes . ' minutos'])
                    : astronomyEditorialText('today.moon.visible_part', ['parte_dia' => todayDayPart($interval['end'])]);
            }
        }
        foreach ($normalized as $interval) {
            if ($interval['start'] > $reference) {
                $minutes = (int) floor(($interval['start']->getTimestamp() - $reference->getTimestamp()) / 60);
                return $minutes > 0 && $minutes <= astronomyEditorialNumber('today.visibility.soon_minutes')
                    ? astronomyEditorialText('today.moon.rises_soon', ['minutos' => $minutes . ' minutos'])
                    : astronomyEditorialText('today.moon.returns_part', ['parte_dia' => todayDayPart($interval['start'])]);
            }
        }
        return astronomyEditorialText('today.moon.finished');
    }
    $first = $normalized[0];
    $last = $normalized[count($normalized) - 1];
    if (count($normalized) > 1) {
        return astronomyEditorialText('today.moon.multiple_intervals', ['primera_parte' => todayDayPart($first['start']), 'ultima_parte' => todayDayPart($last['start'])]);
    }
    return astronomyEditorialText('today.moon.single_interval', ['parte_dia' => todayDayPart($first['start'])]);
}

function todayNextHorizonEvent(array $moonDays, string $key, DateTimeImmutable $reference, string $timezone): ?DateTimeImmutable
{
    $dates = [];
    foreach ($moonDays as $moonDay) {
        $date = is_array($moonDay) ? todayDateTime($moonDay[$key] ?? null, $timezone) : null;
        if ($date !== null && $date >= $reference) {
            $dates[] = $date;
        }
    }
    usort($dates, static fn(DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);
    return $dates[0] ?? null;
}

function todayDirectionLabel($degrees): ?string
{
    if (!is_numeric($degrees) || !is_finite((float) $degrees)) {
        return null;
    }
    return number_format((float) $degrees, 1, ',', '.') . '° (' . homeMoonDirection((float) $degrees) . ')';
}

$location = astronomyLocationContext();
$timezoneName = $location['timezone'];
$latitude = $location['latitude'];
$longitude = $location['longitude'];
$locationLabel = $location['name'];
$now = get_current_datetime($timezoneName);
$requestedDate = isset($_GET['date']) ? trim((string) $_GET['date']) : $now->format('Y-m-d');
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedDate, new DateTimeZone($timezoneName));
if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $requestedDate || $requestedDate < '1900-01-01' || $requestedDate > '2050-12-31') {
    $requestedDate = $now->format('Y-m-d');
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedDate, new DateTimeZone($timezoneName));
}
$isToday = $requestedDate === $now->format('Y-m-d');
$visiblePageTitle = $isToday
    ? astronomySiteSectionLabel('today')
    : 'El cielo el ' . todayLongDate($parsedDate);
$common = ['latitude' => $latitude, 'longitude' => $longitude, 'timezone' => $timezoneName];
$usingCustomLocation = ($location['mode'] ?? 'default') !== 'default';
$daily = $nextDaily = $directions = $eventsData = $phasesData = null;
$apiError = false;
$eventTypes = implode(',', astronomyEventPublicTypesForSurface(ASTRONOMY_EVENT_SURFACE_TODAY));

try {
    $apiBaseUrl = loadAstronomyApiConfig()['base_url'];
    $daily = todayApiRequest($apiBaseUrl . '/v1/astronomy/daily?' . http_build_query(['date' => $requestedDate, 'include_light_periods' => 'true'] + $common), 'today daily', 18, $usingCustomLocation);
    $nextDaily = todayApiRequest($apiBaseUrl . '/v1/astronomy/daily?' . http_build_query(['date' => $parsedDate->modify('+1 day')->format('Y-m-d')] + $common), 'today next daily', 15, $usingCustomLocation);
    $directions = todayApiRequest($apiBaseUrl . '/v1/astronomy/directions?' . http_build_query(['date' => $requestedDate, 'time' => '12:00:00'] + $common), 'today directions', 15, $usingCustomLocation);
    $eventsData = astronomyEvents(['start_date' => $requestedDate, 'days' => 1, 'types' => $eventTypes] + $common, 'today events', 35);
    $phasesData = astronomyEvents(['start_date' => $parsedDate->modify('-35 days')->format('Y-m-d'), 'days' => 80, 'types' => 'moon_phase'] + $common, 'today phases', 35);
} catch (RuntimeException $exception) {
    error_log('Aquellas Lunas today configuration error: ' . $exception->getMessage());
}
if (!is_array($daily['moon'] ?? null) || !is_array($daily['sun'] ?? null)) {
    $daily = null;
    $apiError = true;
}

$moon = $daily['moon'] ?? [];
$sun = $daily['sun'] ?? [];
$light = is_array($sun['light_periods'] ?? null) ? $sun['light_periods'] : [];
$visualLightData = $light;
$visualLightData['_sun'] = [
    'rise' => $sun['rise'] ?? null,
    'set' => $sun['set'] ?? null,
    'day_length_seconds' => $sun['day_length_seconds'] ?? null,
];
$phase = astronomyMoonPhaseLabelForLocalDate($requestedDate, $timezoneName, $phasesData['items'] ?? []) ?? 'Fase no disponible';
$illumination = is_numeric($moon['illumination_percent'] ?? null) ? (int) round((float) $moon['illumination_percent']) : null;
$isSupermoon = is_numeric($moon['apparent_size_percent'] ?? null) && (float) $moon['apparent_size_percent'] >= astronomySupermoonMinApparentSizePercent();
$moonImageInstant = $parsedDate->setTime(12, 0);
$moonImageUrl = 'moon-image.php?' . http_build_query($common + ['datetime' => $moonImageInstant->format(DateTimeInterface::ATOM)]);
$moonProfileUrl = 'altitude-profile.php?' . http_build_query(['target' => 'moon', 'date' => $requestedDate] + $common);
$sunProfileUrl = 'altitude-profile.php?' . http_build_query(['target' => 'sun', 'date' => $requestedDate] + $common);
$moonSummary = $daily !== null ? todayMoonSummary($moon, $timezoneName, $isToday ? $now : $parsedDate, $isToday) : '';
$horizonReference = $isToday ? $now : $parsedDate;
$moonDays = array_values(array_filter([$moon, $nextDaily['moon'] ?? null], 'is_array'));
$nextRise = todayNextHorizonEvent($moonDays, 'rise', $horizonReference, $timezoneName);
$nextSet = todayNextHorizonEvent($moonDays, 'set', $horizonReference, $timezoneName);
$horizonEvents = array_values(array_filter([
    $nextRise !== null ? ['label' => 'Sale', 'date' => $nextRise] : null,
    $nextSet !== null ? ['label' => 'Se pone', 'date' => $nextSet] : null,
]));
usort($horizonEvents, static fn(array $a, array $b): int => $a['date'] <=> $b['date']);
$moonTrend = is_numeric($moon['age_days'] ?? null) ? ((float) $moon['age_days'] < 14.765 ? 'Creciente' : 'Menguante') : null;
$moonDirections = is_array($directions['moon'] ?? null) ? $directions['moon'] : [];
$moonRise = todayDateTime($moon['rise'] ?? null, $timezoneName);
$sunset = todayDateTime($sun['set'] ?? null, $timezoneName);
$eveningCivil = todayPeriod(is_array($light['twilight']['evening']['civil'] ?? null) ? $light['twilight']['evening']['civil'] : [], $timezoneName);
$moonRiseAzimuth = $moonDirections['rise']['azimuth_degrees'] ?? null;
$moonRiseDirection = is_numeric($moonRiseAzimuth) ? homeMoonDirection((float) $moonRiseAzimuth) : null;
$venusBeltOpportunity = $illumination !== null
    && $illumination >= astronomyEditorialNumber('today.venus_belt.min_illumination_percent')
    && $moonRise !== null
    && $sunset !== null
    && $eveningCivil !== null
    && in_array($moonRiseDirection, ['noreste', 'este', 'sudeste'], true)
    && abs($moonRise->getTimestamp() - $sunset->getTimestamp()) <= astronomyEditorialNumber('today.venus_belt.max_difference_minutes') * 60;

$photoRows = [
    'Hora azul matutina' => todayPeriod(is_array($light['photographic']['morning']['blue_hour'] ?? null) ? $light['photographic']['morning']['blue_hour'] : [], $timezoneName),
    'Hora dorada matutina' => todayPeriod(is_array($light['photographic']['morning']['golden_hour'] ?? null) ? $light['photographic']['morning']['golden_hour'] : [], $timezoneName),
    'Hora dorada vespertina' => todayPeriod(is_array($light['photographic']['evening']['golden_hour'] ?? null) ? $light['photographic']['evening']['golden_hour'] : [], $timezoneName),
    'Hora azul vespertina' => todayPeriod(is_array($light['photographic']['evening']['blue_hour'] ?? null) ? $light['photographic']['evening']['blue_hour'] : [], $timezoneName),
];
$twilightRows = [
    'Crepúsculo astronómico matutino' => todayPeriod(is_array($light['twilight']['morning']['astronomical'] ?? null) ? $light['twilight']['morning']['astronomical'] : [], $timezoneName),
    'Crepúsculo náutico matutino' => todayPeriod(is_array($light['twilight']['morning']['nautical'] ?? null) ? $light['twilight']['morning']['nautical'] : [], $timezoneName),
    'Crepúsculo civil matutino' => todayPeriod(is_array($light['twilight']['morning']['civil'] ?? null) ? $light['twilight']['morning']['civil'] : [], $timezoneName),
    'Crepúsculo civil vespertino' => todayPeriod(is_array($light['twilight']['evening']['civil'] ?? null) ? $light['twilight']['evening']['civil'] : [], $timezoneName),
    'Crepúsculo náutico vespertino' => todayPeriod(is_array($light['twilight']['evening']['nautical'] ?? null) ? $light['twilight']['evening']['nautical'] : [], $timezoneName),
    'Crepúsculo astronómico vespertino' => todayPeriod(is_array($light['twilight']['evening']['astronomical'] ?? null) ? $light['twilight']['evening']['astronomical'] : [], $timezoneName),
];
$events = array_values(array_filter($eventsData['items'] ?? [], static function ($event) use ($requestedDate, $timezoneName): bool {
    return is_array($event) && astronomyEventDateTime($event['datetime'] ?? null, $timezoneName)?->format('Y-m-d') === $requestedDate;
}));
$events = astronomyFilterEventsForSurface($events, ASTRONOMY_EVENT_SURFACE_TODAY);
$locationMessage = astronomyLocationStatusMessage((string) ($_GET['location_status'] ?? ''));
$pageSeo = aquellasLunasSeoPage('El cielo hoy | Aquellas Lunas', 'Resumen de la Luna, el Sol, la luz y las condiciones del cielo para una fecha y ubicación.', '/cielo-de-hoy.php', 'article');
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
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/today.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/today-visual-experiment.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/home-sky.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyEditorialFrontendConfiguration(); ?>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/cloud-cover.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/today.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/today-visual-experiment.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/page-recovery.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyMobileSwipeNavigationScript('today'); ?>
</head>
<body class="today-visual-experiment" data-api-state="<?= $apiError ? 'error' : 'ok' ?>" data-cloud-cover-latitude="<?= htmlspecialchars((string) $latitude) ?>" data-cloud-cover-longitude="<?= htmlspecialchars((string) $longitude) ?>" data-cloud-cover-timezone="<?= htmlspecialchars($timezoneName) ?>" data-cloud-cover-cache-scope="<?= htmlspecialchars($requestedDate) ?>" data-today-date="<?= htmlspecialchars($requestedDate) ?>" data-moon-intervals="<?= htmlspecialchars(json_encode($moon['visibility_intervals'] ?? []), ENT_QUOTES, 'UTF-8') ?>" data-today-light-periods="<?= htmlspecialchars(json_encode($visualLightData, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"<?= astronomyMobileSwipeNavigationAttributes('today') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($now); ?>
    <?php renderAstronomySiteHeader('today', $location); ?>
    <main class="today-page"><div class="container public-page-container today-container">
        <div class="today-date-control">
            <button class="today-date-toggle" type="button" aria-haspopup="dialog" aria-controls="today-date-dialog">Ver otra fecha</button>
        </div>

        <article class="today-card today-summary atmosphere-card--mixed" aria-labelledby="today-summary-title">
            <h1 id="today-summary-title"><?= htmlspecialchars($visiblePageTitle) ?></h1>
            <p class="today-page-intro">Luna, Sol, luz y condiciones del cielo para la fecha seleccionada.</p>
            <?php if ($locationMessage !== ''): ?><p class="status-info"><?= htmlspecialchars($locationMessage) ?></p><?php endif; ?>
            <?php if ($apiError): ?><div class="api-error-notice" data-api-error role="alert"><p>No pudimos actualizar los datos astronómicos.</p><button type="button" class="button compact-secondary-button" data-api-retry>Reintentar</button></div><?php else: ?>
            <div class="today-summary__body">
                <img class="today-moon-image" src="<?= htmlspecialchars($moonImageUrl, ENT_QUOTES, 'UTF-8') ?>" width="320" height="320" alt="Apariencia de la Luna el <?= htmlspecialchars(todayLongDate($parsedDate)) ?>">
                <div class="today-summary__copy">
                    <h2><?= htmlspecialchars($phase) ?></h2>
                    <p class="today-lead"><?= htmlspecialchars($moonSummary) ?></p>
                    <div class="today-highlights">
                        <p><strong><?= $illumination !== null ? htmlspecialchars((string) $illumination) . ' %' : '—' ?></strong><span>iluminada</span></p>
                        <?php foreach ($horizonEvents as $event): $eventDay = $event['date']->format('Y-m-d') === $requestedDate ? 'hoy' : 'mañana'; ?>
                        <p><strong><?= htmlspecialchars($event['date']->format('H:i')) ?></strong><span><?= htmlspecialchars($event['label'] . ' ' . $eventDay) ?></span></p>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($isSupermoon): ?><p class="today-supermoon">Superluna: su tamaño aparente será <?= htmlspecialchars(number_format((float) $moon['apparent_size_percent'], 1, ',', '.')) ?> % del promedio.</p><?php endif; ?>
                    <?php if ($venusBeltOpportunity): ?><p class="today-venus-opportunity" data-venus-belt-opportunity hidden><?= htmlspecialchars(astronomyEditorialText('today.venus_belt.message')) ?></p><?php endif; ?>
                    <p class="today-current-cloud" data-today-summary-cloud hidden></p>
                    <button class="today-detail-trigger" type="button" data-moon-detail-open>Ver datos de la Luna</button>
                </div>
            </div>
            <?php endif; ?>
        </article>

        <article class="today-card atmosphere-card--mixed" aria-labelledby="today-path-title">
            <h2 id="today-path-title">Recorrido del Sol y la Luna</h2>
            <div class="today-tabs" role="tablist" aria-label="Elegir astro"><button type="button" role="tab" aria-selected="true" aria-controls="today-moon-panel" id="today-moon-tab">Luna</button><button type="button" role="tab" aria-selected="false" aria-controls="today-sun-panel" id="today-sun-tab">Sol</button></div>
            <section id="today-moon-panel" role="tabpanel" aria-labelledby="today-moon-tab">
                <figure class="home-altitude-profile home-moon-chart" data-altitude-profile data-target="moon" data-endpoint="<?= htmlspecialchars($moonProfileUrl, ENT_QUOTES, 'UTF-8') ?>" data-date="<?= htmlspecialchars($requestedDate) ?>" data-timezone="<?= htmlspecialchars($timezoneName) ?>" data-debug-now="<?= $isToday && astronomyCurrentDateTimeIsSimulated() ? htmlspecialchars($now->format(DateTimeInterface::ATOM)) : '' ?>" aria-busy="true"><div class="home-altitude-profile__canvas" aria-hidden="true"><span class="home-altitude-profile__loading-horizon"></span></div><figcaption class="home-altitude-profile__status" data-profile-status aria-live="polite">Cargando recorrido…</figcaption></figure>
            </section>
            <section id="today-sun-panel" role="tabpanel" aria-labelledby="today-sun-tab" hidden>
                <figure class="home-altitude-profile home-moon-chart today-sun-profile" data-altitude-profile data-target="sun" data-profile-width="520" data-endpoint="<?= htmlspecialchars($sunProfileUrl, ENT_QUOTES, 'UTF-8') ?>" data-date="<?= htmlspecialchars($requestedDate) ?>" data-timezone="<?= htmlspecialchars($timezoneName) ?>" data-debug-now="<?= $isToday && astronomyCurrentDateTimeIsSimulated() ? htmlspecialchars($now->format(DateTimeInterface::ATOM)) : '' ?>" aria-busy="true"><div class="home-altitude-profile__canvas" aria-hidden="true"><span class="home-altitude-profile__loading-horizon"></span></div><figcaption class="home-altitude-profile__status" data-profile-status aria-live="polite">Cargando recorrido…</figcaption></figure>
            </section>
        </article>

        <article class="today-card atmosphere-card--solar" aria-labelledby="today-light-title">
            <h2 id="today-light-title">Luz del día</h2>
            <div class="today-sun-summary">
                <p><span>Salida del Sol</span><strong><?= htmlspecialchars(todayHour($sun['rise'] ?? null, $timezoneName) ?? 'No ocurre') ?></strong></p>
                <p><span>Puesta del Sol</span><strong><?= htmlspecialchars(todayHour($sun['set'] ?? null, $timezoneName) ?? 'No ocurre') ?></strong></p>
                <p><span>Duración del día</span><strong><?= htmlspecialchars(todayDuration($sun['day_length_seconds'] ?? null) ?? 'No disponible') ?></strong></p>
                <p><span>Mediodía solar</span><strong><?= htmlspecialchars(todayHour($light['solar_noon'] ?? null, $timezoneName) ?? 'No ocurre') ?></strong></p>
            </div>
            <div class="today-tabs" role="tablist" aria-label="Elegir información de luz"><button type="button" role="tab" aria-selected="true" aria-controls="today-photo-panel" id="today-photo-tab">Para fotografía</button><button type="button" role="tab" aria-selected="false" aria-controls="today-twilight-panel" id="today-twilight-tab">Crepúsculos</button></div>
            <section id="today-photo-panel" role="tabpanel" aria-labelledby="today-photo-tab">
                <div class="today-time-rows"><?php foreach ($photoRows as $label => $period): ?><div><?php if ($period !== null): ?><strong><?= htmlspecialchars($label) ?></strong><span><?= htmlspecialchars($period['start']) ?>–<?= htmlspecialchars($period['end']) ?><?= $period['duration'] !== null ? ' · ' . htmlspecialchars($period['duration']) : '' ?></span><?php else: ?><strong><?= htmlspecialchars($label) ?></strong><span>No se produce en esta fecha.</span><?php endif; ?></div><?php endforeach; ?></div>
                <p class="today-help">La hora azul y la hora dorada son períodos especialmente útiles para fotografía.</p>
            </section>
            <section id="today-twilight-panel" role="tabpanel" aria-labelledby="today-twilight-tab" hidden>
                <div class="today-time-rows"><?php foreach ($twilightRows as $label => $period): ?><div><?php if ($period !== null): ?><strong><?= htmlspecialchars($label) ?></strong><span><?= htmlspecialchars($period['start']) ?>–<?= htmlspecialchars($period['end']) ?><?= $period['duration'] !== null ? ' · ' . htmlspecialchars($period['duration']) : '' ?></span><?php else: ?><strong><?= htmlspecialchars($label) ?></strong><span>No se produce en esta fecha.</span><?php endif; ?></div><?php endforeach; ?></div>
                <p class="today-help">Civil: todavía hay bastante claridad. Náutico: el horizonte comienza a perderse. Astronómico: al terminar, el cielo puede considerarse completamente oscuro.</p>
            </section>
        </article>

        <?php if ($events !== []): ?><article class="today-card atmosphere-card--night" aria-labelledby="today-events-title">
            <h2 id="today-events-title">Qué sucede hoy</h2>
            <div class="today-events"><?php foreach ($events as $event): $eventDate = astronomyEventDateTime($event['datetime'] ?? null, $timezoneName); $presentation = astronomyEventPresentation($event, $timezoneName); $isEveningFullMoonObservation = ($event['type'] ?? '') === 'full_moon_observation' && ($event['subtype'] ?? '') === 'evening'; ?><section><?php renderAstronomyIcon($event, $latitude, 'today-event-icon'); ?><time datetime="<?= htmlspecialchars($eventDate?->format(DateTimeInterface::ATOM) ?? '') ?>"><?= htmlspecialchars($eventDate?->format('H:i') ?? '—') ?></time><div><h3><?= htmlspecialchars($presentation['title']) ?></h3><?php if ($presentation['summary'] !== ''): ?><p><?= htmlspecialchars($presentation['summary']) ?></p><?php endif; ?><?php if (($presentation['visibility'] ?? '') !== ''): ?><p><?= htmlspecialchars($presentation['visibility']) ?></p><?php endif; ?><?php if ($isEveningFullMoonObservation): ?><p class="today-event-editorial"><?= htmlspecialchars(astronomyEditorialText('today.venus_belt.full_moon')) ?></p><?php endif; ?></div></section><?php endforeach; ?></div>
        </article><?php endif; ?>

        <?php if ($isToday): ?><article class="today-card today-conditions atmosphere-card--night" aria-labelledby="today-conditions-title" data-today-conditions>
            <h2 id="today-conditions-title">Condiciones para observar</h2>
            <p class="today-conditions__lead" data-today-cloud-summary>Cargando el pronóstico de nubosidad…</p>
            <div class="today-cloud-hours" data-today-cloud-hours hidden></div>
            <p class="today-help" data-today-cloud-recommendation hidden></p>
        </article><?php endif; ?>
        <?php renderAstronomyExploreSky('today'); ?>
        <?php renderAstronomyTimings(); ?>
    </div></main>

    <dialog id="today-date-dialog" class="today-date-dialog" aria-labelledby="today-date-dialog-title" data-today-date-dialog>
        <form method="dialog" class="today-dialog-close-form"><button type="submit" aria-label="Cerrar selector de fecha">×</button></form>
        <h2 id="today-date-dialog-title">Ver otra fecha</h2>
        <form method="get" class="today-date-form">
            <label><span>Fecha a consultar</span><input type="date" name="date" min="1900-01-01" max="2050-12-31" value="<?= htmlspecialchars($requestedDate) ?>" required></label>
            <div class="today-date-actions">
                <button class="button" type="submit">Aplicar</button>
                <button class="today-date-cancel" type="button" data-today-date-close>Cancelar</button>
            </div>
        </form>
        <p class="today-date-forecast-note">El pronóstico de nubosidad solo se muestra para hoy.</p>
    </dialog>

    <dialog class="today-moon-dialog" data-moon-detail-dialog aria-labelledby="today-moon-detail-title">
        <form method="dialog"><button type="submit" aria-label="Cerrar">×</button></form>
        <h2 id="today-moon-detail-title">Datos de la Luna</h2>
        <dl>
            <div><dt>Edad lunar</dt><dd><?= is_numeric($moon['age_days'] ?? null) ? htmlspecialchars(number_format((float) $moon['age_days'], 1, ',', '.')) . ' días' : 'No disponible' ?></dd></div>
            <div><dt>Ciclo</dt><dd><?= htmlspecialchars($moonTrend ?? 'No disponible') ?></dd></div>
            <div><dt>Distancia</dt><dd><?= is_numeric($moon['distance_km'] ?? null) ? htmlspecialchars(number_format((float) $moon['distance_km'], 0, ',', '.')) . ' km' : 'No disponible' ?></dd></div>
            <div><dt>Tamaño aparente</dt><dd><?= is_numeric($moon['apparent_size_percent'] ?? null) ? htmlspecialchars(number_format((float) $moon['apparent_size_percent'], 1, ',', '.')) . ' % del promedio' : 'No disponible' ?></dd></div>
            <div><dt>Azimut de salida</dt><dd><?= htmlspecialchars(todayDirectionLabel($moonDirections['rise']['azimuth_degrees'] ?? null) ?? 'No disponible') ?></dd></div>
            <div><dt>Azimut de puesta</dt><dd><?= htmlspecialchars(todayDirectionLabel($moonDirections['set']['azimuth_degrees'] ?? null) ?? 'No disponible') ?></dd></div>
        </dl>
    </dialog>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
