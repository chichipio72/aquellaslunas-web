<?php
require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/presentation.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/site-sections.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/explore-sky.php';
sendDynamicNoCacheHeaders();

$defaultDays = 30;

$locationMessage = astronomyLocationStatusMessage((string) ($_GET['location_status'] ?? ''));
$location = astronomyLocationContext();
$timezoneName = $location['timezone'];
$latitude = $location['latitude'];
$longitude = $location['longitude'];
$locationLabel = $location['name'];
$locationMode = $location['mode'];

function normalizeLocationValue($value, float $min, float $max)
{
    return astronomyLocationCoordinate($value, $min, $max);
}

function sanitizeTimezone($value): ?string
{
    return astronomyLocationTimezone($value);
}

function formatDateValue(string $value): string
{
    try {
        $date = new DateTimeImmutable($value);
        return $date->format('d/m/y');
    } catch (Exception $exception) {
        return '—';
    }
}

function formatLongDateValue(string $value): string
{
    $monthNames = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    try {
        $date = new DateTimeImmutable($value);
        return (int) $date->format('d') . ' de ' . $monthNames[(int) $date->format('n')] . ' de ' . $date->format('Y');
    } catch (Exception $exception) {
        return 'Fecha no disponible';
    }
}

function formatVisibilitySummary(array $intervals): string
{
    if ($intervals === []) {
        return 'No visible durante el día';
    }

    if (count($intervals) === 1) {
        $start = $intervals[0]['start'] ?? '';
        $end = $intervals[0]['end'] ?? '';
        if (is_string($start) && is_string($end) && str_contains($start, 'T00:00:00') && str_contains($end, 'T00:00:00')) {
            return 'Visible todo el día';
        }

        return '1 intervalo de visibilidad';
    }

    return count($intervals) . ' intervalos de visibilidad';
}

function formatHourValue(?string $value, string $timezoneName): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone($timezoneName))->format('H:i');
    } catch (Exception $exception) {
        return '—';
    }
}

function formatDayLength(int $seconds): string
{
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return $hours . 'h ' . $minutes . 'm';
}

function formatPercent($value): string
{
    if ($value === null) {
        return '—';
    }

    return (string) (int) round((float) $value) . '%';
}

function formatAge($value): string
{
    if ($value === null) {
        return '—';
    }

    return number_format((float) $value, 1, '.', '');
}

$currentDateTime = get_current_datetime($timezoneName);
$startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? (string) $_GET['start_date'] : $currentDateTime->format('Y-m-d');
$days = isset($_GET['days']) ? (int) $_GET['days'] : $defaultDays;
if ($days < 1) {
    $days = $defaultDays;
}
if ($days > 90) {
    $days = 90;
}

require_once __DIR__ . '/includes/moon-images.php';

$apiBaseUrl = null;
$apiData = null;
$apiErrorMessage = null;

try {
    $apiConfig = loadAstronomyApiConfig();
    $apiBaseUrl = $apiConfig['base_url'];
    error_log('Aquellas Lunas API configuration source: ' . $apiConfig['source']);
} catch (RuntimeException $exception) {
    $apiErrorMessage = 'No se pudieron cargar los datos en este momento.';
    error_log('Aquellas Lunas API configuration error: ' . $exception->getMessage());
}

if ($apiBaseUrl !== null) {
    $apiUrl = $apiBaseUrl . '/v1/astronomy/range';
    $query = http_build_query([
        'start_date' => $startDate,
        'days' => $days,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'timezone' => $timezoneName,
    ]);

    $requestResult = astronomyApiRequest($apiUrl . '?' . $query, 'range', 12);
    if ($locationMode !== 'default' && astronomyApiRejectedLocationParameters($requestResult)) {
        astronomyRecoverDefaultLocationFromApi($requestResult);
    }
    $response = $requestResult['body'];
    $httpCode = $requestResult['http_code'];

    if ($response !== false && $httpCode === 200) {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $apiData = $decoded;
            astronomyApiRecordValidation('range', true, true);
        } else {
            $apiErrorMessage = 'No se pudieron cargar los datos en este momento.';
            error_log('Aquellas Lunas API invalid response: ' . json_last_error_msg());
            astronomyApiRecordValidation('range', false, false);
        }
    } else {
        $apiErrorMessage = 'No se pudieron cargar los datos en este momento.';
        error_log('Aquellas Lunas API request failed with HTTP status ' . $httpCode . '.');
        astronomyApiRecordValidation('range', null, false);
    }
}

$rows = [];
if (is_array($apiData)) {
    $rows = $apiData['items'] ?? [];
}

if ($apiErrorMessage === null && empty($rows)) {
    $apiErrorMessage = 'No hay datos astronómicos disponibles para esa fecha y ubicación.';
    astronomyApiRecordValidation('range', true, false);
    error_log('Aquellas Lunas API invalid response: range contains no items.');
}
$pageSeo = aquellasLunasSeoPage('Calendario solar y lunar | Aquellas Lunas', 'Calendario solar y lunar con horarios, fases e intervalos de visibilidad para varios días.', '/sol-y-luna.php', 'article');
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
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/home-v2.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/sky-timeline.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/sky-popover.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/page-recovery.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyMobileSwipeNavigationScript('sun_moon'); ?>
</head>
<body data-api-state="<?= $apiErrorMessage !== null ? 'error' : 'ok' ?>"<?= astronomyMobileSwipeNavigationAttributes('sun_moon') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($currentDateTime); ?>
    <?php renderAstronomySiteHeader('sun_moon', $location); ?>

    <main class="page">
        <div class="container public-page-container">
            <section class="hero atmosphere-card--solar" aria-labelledby="hero-title">
                <p class="eyebrow">HORARIOS Y FASES</p>
                <h1 id="hero-title"><?= htmlspecialchars(astronomySiteSectionLabel('sun_moon')) ?></h1>
                <p class="hero-subtitle">Consultá salidas, puestas, fases y visibilidad día por día.</p>

                <form id="range-form" method="get"></form>

                <div class="compact-controls">
                    <div class="compact-primary-controls">
                        <label class="control-field compact-date">
                            <span>Fecha inicial</span>
                            <input form="range-form" type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                        </label>

                        <label class="control-field compact-days">
                            <span>Días</span>
                            <input form="range-form" type="number" name="days" min="1" max="90" value="<?= htmlspecialchars((string) $days) ?>">
                        </label>

                        <button form="range-form" type="submit" class="button button-primary compact-submit">Calcular</button>
                    </div>

                </div>

                <?php if ($locationMessage !== ''): ?>
                    <p class="card-note status-info"><?= htmlspecialchars($locationMessage) ?></p>
                <?php endif; ?>
                <?php if ($apiErrorMessage !== null): ?>
                    <div class="api-error-notice" data-api-error role="alert"><p>No pudimos actualizar los datos astronómicos. Reintentá en unos segundos.</p><button type="button" class="button compact-secondary-button" data-api-retry>Reintentar</button></div>
                <?php endif; ?>
            </section>

            <?php if ($apiErrorMessage === null): ?>
            <section class="card table-card" aria-labelledby="table-title">
                <div class="table-header">
                    <h2 id="table-title">Resumen diario</h2>
                    <p class="table-caption">Cada fila corresponde al día civil local. La Luna puede ponerse antes de salir nuevamente dentro del mismo día.</p>
                </div>
                <div class="table-wrapper">
                    <table class="astro-table">
                        <thead>
                            <tr>
                                <th scope="col">Fecha</th>
                                <th scope="col" title="Salida del Sol" aria-label="Salida del Sol"><span class="astro-symbol astro-symbol--sun" aria-hidden="true">☀</span> <span class="astro-arrow astro-arrow--rise" aria-hidden="true">↑</span></th>
                                <th scope="col" title="Puesta del Sol" aria-label="Puesta del Sol"><span class="astro-symbol astro-symbol--sun" aria-hidden="true">☀</span> <span class="astro-arrow astro-arrow--set" aria-hidden="true">↓</span></th>
                                <th scope="col" title="Duración del día" aria-label="Duración del día">Día</th>
                                <th scope="col" title="Salida de la Luna" aria-label="Salida de la Luna"><span class="astro-symbol astro-symbol--moon" aria-hidden="true">🌙</span> <span class="astro-arrow astro-arrow--rise" aria-hidden="true">↑</span></th>
                                <th scope="col" title="Puesta de la Luna" aria-label="Puesta de la Luna"><span class="astro-symbol astro-symbol--moon" aria-hidden="true">🌙</span> <span class="astro-arrow astro-arrow--set" aria-hidden="true">↓</span></th>
                                <th scope="col" title="Iluminación lunar" aria-label="Iluminación lunar">Luz</th>
                                <th scope="col" title="Edad lunar en días" aria-label="Edad lunar en días">Edad</th>
                                <th class="visibility-column-heading" scope="col" title="Horarios de visibilidad" aria-label="Horarios de visibilidad"><span>Horarios de visibilidad</span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $rowDate = $row['date'] ?? '';
                            $rowSun = $row['sun'] ?? [];
                            $rowMoon = $row['moon'] ?? [];
                            $sunIntervals = $rowSun['visibility_intervals'] ?? [];
                            $moonIntervals = $rowMoon['visibility_intervals'] ?? [];
                            $rowId = 'row-' . md5($rowDate . '-' . $latitude . '-' . $longitude);
                            $sunRise = formatHourValue($rowSun['rise'] ?? null, $timezoneName);
                            $sunSet = formatHourValue($rowSun['set'] ?? null, $timezoneName);
                            $dayLengthSeconds = isset($rowSun['day_length_seconds']) ? (int) $rowSun['day_length_seconds'] : null;
                            $dayLength = $dayLengthSeconds !== null ? formatDayLength($dayLengthSeconds) : '—';
                            $moonRise = formatHourValue($rowMoon['rise'] ?? null, $timezoneName);
                            $moonSet = formatHourValue($rowMoon['set'] ?? null, $timezoneName);
                            $illumination = formatPercent($rowMoon['illumination_percent'] ?? null);
                            $moonAge = formatAge($rowMoon['age_days'] ?? null);
                            $moonThumbnail = moonPhaseThumbnail(
                                $rowMoon['illumination_percent'] ?? null,
                                $rowMoon['age_days'] ?? null,
                                $latitude
                            );
                            $popoverDate = formatLongDateValue($rowDate);
                            $popoverSunRise = ($rowSun['rise'] ?? null) !== null ? $sunRise : 'Sin salida este día';
                            $popoverSunSet = ($rowSun['set'] ?? null) !== null ? $sunSet : 'Sin puesta este día';
                            $popoverMoonRise = ($rowMoon['rise'] ?? null) !== null ? $moonRise : 'Sin salida este día';
                            $popoverMoonSet = ($rowMoon['set'] ?? null) !== null ? $moonSet : 'Sin puesta este día';
                            $popoverSunVisibility = formatVisibilitySummary($sunIntervals);
                            $popoverMoonVisibility = formatVisibilitySummary($moonIntervals);
                            ?>
                            <tr class="timeline-row" data-row-id="<?= htmlspecialchars($rowId) ?>" data-sun='<?= htmlspecialchars(json_encode($sunIntervals), ENT_QUOTES, 'UTF-8') ?>' data-moon='<?= htmlspecialchars(json_encode($moonIntervals), ENT_QUOTES, 'UTF-8') ?>'>
                                <td data-label="Fecha"><?= htmlspecialchars(formatDateValue($rowDate)) ?></td>
                                <td data-label="Salida del Sol"><?= htmlspecialchars($sunRise) ?></td>
                                <td data-label="Puesta del Sol"><?= htmlspecialchars($sunSet) ?></td>
                                <td data-label="Duración del día"><?= htmlspecialchars($dayLength) ?></td>
                                <td data-label="Salida de la Luna"><?= htmlspecialchars($moonRise) ?></td>
                                <td data-label="Puesta de la Luna"><?= htmlspecialchars($moonSet) ?></td>
                                <td data-label="Iluminación lunar"><?= htmlspecialchars($illumination) ?></td>
                                <td data-label="Edad lunar"><?= htmlspecialchars($moonAge) ?></td>
                                <td data-label="Cielo">
                                    <div class="sky-cell">
                                        <?php if ($moonThumbnail !== null): ?>
                                            <img
                                                class="moon-phase-thumbnail"
                                                src="<?= htmlspecialchars($moonThumbnail['url'], ENT_QUOTES, 'UTF-8') ?>"
                                                width="40"
                                                height="40"
                                                alt="Luna <?= $moonThumbnail['direction'] === 'waxing' ? 'creciente' : 'menguante' ?>, <?= $moonThumbnail['percent'] ?> % iluminada"
                                                loading="lazy"
                                            >
                                        <?php else: ?>
                                            <span class="moon-phase-thumbnail-placeholder" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div
                                            class="timeline-cell"
                                            id="<?= htmlspecialchars($rowId) ?>"
                                            data-popover-date="<?= htmlspecialchars($popoverDate, ENT_QUOTES, 'UTF-8') ?>"
                                            data-popover-sun-rise="<?= htmlspecialchars($popoverSunRise, ENT_QUOTES, 'UTF-8') ?>"
                                            data-popover-sun-set="<?= htmlspecialchars($popoverSunSet, ENT_QUOTES, 'UTF-8') ?>"
                                            data-popover-sun-visibility="<?= htmlspecialchars($popoverSunVisibility, ENT_QUOTES, 'UTF-8') ?>"
                                            data-popover-moon-rise="<?= htmlspecialchars($popoverMoonRise, ENT_QUOTES, 'UTF-8') ?>"
                                            data-popover-moon-set="<?= htmlspecialchars($popoverMoonSet, ENT_QUOTES, 'UTF-8') ?>"
                                            data-popover-moon-visibility="<?= htmlspecialchars($popoverMoonVisibility, ENT_QUOTES, 'UTF-8') ?>"
                                        ></div>
                                    </div>
                                </td>
                                <td class="mobile-daily-cell">
                                    <div class="mobile-daily-summary">
                                        <time class="mobile-summary-date" datetime="<?= htmlspecialchars($rowDate, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(formatDateValue($rowDate)) ?></time>
                                        <span><?= htmlspecialchars($illumination) ?></span>
                                        <span><?= htmlspecialchars($moonAge) ?>d</span>
                                        <?php if ($moonThumbnail !== null): ?>
                                            <img
                                                class="mobile-moon-thumbnail"
                                                src="<?= htmlspecialchars($moonThumbnail['url'], ENT_QUOTES, 'UTF-8') ?>"
                                                width="30"
                                                height="30"
                                                alt="Luna <?= $moonThumbnail['direction'] === 'waxing' ? 'creciente' : 'menguante' ?>, <?= $moonThumbnail['percent'] ?> % iluminada"
                                                loading="lazy"
                                            >
                                        <?php else: ?>
                                            <span class="mobile-moon-thumbnail" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <span class="mobile-summary-range" aria-label="Sol: salida <?= htmlspecialchars($popoverSunRise, ENT_QUOTES, 'UTF-8') ?>, puesta <?= htmlspecialchars($popoverSunSet, ENT_QUOTES, 'UTF-8') ?>">
                                            <span aria-hidden="true"><span class="astro-symbol astro-symbol--sun">☀</span><span class="astro-arrow astro-arrow--rise">↑</span><?= htmlspecialchars($sunRise) ?><span class="astro-arrow astro-arrow--set">↓</span><?= htmlspecialchars($sunSet) ?></span>
                                        </span>
                                        <span class="mobile-summary-range" aria-label="Luna: salida <?= htmlspecialchars($popoverMoonRise, ENT_QUOTES, 'UTF-8') ?>, puesta <?= htmlspecialchars($popoverMoonSet, ENT_QUOTES, 'UTF-8') ?>">
                                            <span aria-hidden="true"><span class="astro-symbol astro-symbol--moon">🌙</span><span class="astro-arrow astro-arrow--rise">↑</span><?= htmlspecialchars($moonRise) ?><span class="astro-arrow astro-arrow--set">↓</span><?= htmlspecialchars($moonSet) ?></span>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>
            <?php renderAstronomyExploreSky('calendar'); ?>
            <?php renderAstronomyTimings(); ?>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
