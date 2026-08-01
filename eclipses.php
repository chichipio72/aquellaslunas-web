<?php
require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
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
require_once __DIR__ . '/includes/eclipse-query-policy.php';
require_once __DIR__ . '/includes/calendar-event.php';
require_once __DIR__ . '/includes/eclipse-detail-component.php';
require_once __DIR__ . '/includes/event-type-configuration.php';

sendDynamicNoCacheHeaders();

const ECLIPSES_IMAGE_SOL_PARTIAL_PATH = 'assets/images/contenido/SolParcial.jpg';
const ECLIPSES_IMAGE_SOL_TOTAL_PATH = 'assets/images/contenido/SolTotal.jpg';
const ECLIPSES_IMAGE_LUNA_PARTIAL_PATH = 'assets/images/contenido/LunaParcial.jpg';
const ECLIPSES_IMAGE_LUNA_TOTAL_PATH = 'assets/images/contenido/LunaTotal.jpg';

function eclipsesParseDate(string $value, DateTimeZone $timezone): ?DateTimeImmutable
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
        return null;
    }
    return $parsed;
}

function eclipsesEventDateTime($value, string $timezoneName): ?DateTimeImmutable
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezoneName));
    } catch (Exception $exception) {
        return null;
    }
}

function eclipsesVisibilityClassification(array $event): ?string
{
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $subtype = is_string($event['subtype'] ?? null) ? $event['subtype'] : '';
    $local = [];
    if ($subtype === 'solar_eclipse') {
        $local = is_array($details['solar_eclipse_local'] ?? null) ? $details['solar_eclipse_local'] : [];
    } elseif ($subtype === 'lunar_eclipse') {
        $local = is_array($details['eclipse_local'] ?? null) ? $details['eclipse_local'] : [];
    }
    $value = is_string($local['visibility_classification'] ?? null)
        ? strtolower(trim((string) $local['visibility_classification']))
        : '';
    return $value !== '' ? $value : null;
}

function eclipsesIsVisible(?string $classification): bool
{
    if ($classification === null) {
        return false;
    }
    return $classification !== 'not_visible';
}

function eclipsesVisibilityLabel(?string $classification): string
{
    if ($classification === null) {
        return 'Visibilidad sin determinar';
    }
    return match ($classification) {
        'not_visible' => 'No visible desde tu ubicación',
        'visible_penumbral_only' => 'Visible: sólo fase penumbral',
        'visible_partial', 'partial' => 'Visible: fase parcial',
        'visible_total', 'total' => 'Visible: fase total',
        'visible_annular', 'annular' => 'Visible: fase anular',
        'visible_hybrid', 'hybrid' => 'Visible: fase híbrida',
        default => 'Visibilidad sin determinar',
    };
}

function eclipsesTypeLabel(array $event): string
{
    $subtype = is_string($event['subtype'] ?? null) ? $event['subtype'] : '';
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];

    if ($subtype === 'lunar_eclipse') {
        $globalType = is_string($details['eclipse_global']['global_type'] ?? null)
            ? strtolower(trim((string) $details['eclipse_global']['global_type']))
            : '';
        return match ($globalType) {
            'penumbral' => 'Eclipse lunar penumbral',
            'partial' => 'Eclipse lunar parcial',
            'total' => 'Eclipse lunar total',
            default => 'Eclipse lunar',
        };
    }

    if ($subtype === 'solar_eclipse') {
        $classification = eclipsesVisibilityClassification($event);
        $localType = match ($classification) {
            'visible_partial', 'partial' => 'parcial',
            'visible_annular', 'annular' => 'anular',
            'visible_total', 'total' => 'total',
            'visible_hybrid', 'hybrid' => 'híbrido',
            default => '',
        };
        if ($localType !== '') {
            return 'Eclipse solar ' . $localType . ' (local)';
        }

        $globalType = is_string($details['solar_eclipse_global']['global_type'] ?? null)
            ? strtolower(trim((string) $details['solar_eclipse_global']['global_type']))
            : '';
        return match ($globalType) {
            'partial' => 'Eclipse solar parcial',
            'annular' => 'Eclipse solar anular',
            'total' => 'Eclipse solar total',
            'hybrid' => 'Eclipse solar híbrido',
            default => 'Eclipse solar',
        };
    }

    return 'Eclipse';
}

function eclipsesTypeGroup(array $event): string
{
    return match ((string) ($event['subtype'] ?? '')) {
        'lunar_eclipse' => 'lunar',
        'solar_eclipse' => 'solar',
        default => 'other',
    };
}

function eclipsesContactCodeLabel(string $code): string
{
    return match (strtoupper($code)) {
        'MAX' => 'Máximo',
        default => strtoupper($code),
    };
}

function eclipsesPrimaryBodyLabel(array $event): string
{
    return eclipsesTypeGroup($event) === 'solar' ? 'Sol' : 'Luna';
}

function eclipsesPickImage(array $event): ?array
{
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $candidates = [
        $details['image_preview_url'] ?? null,
        $details['photo_preview_url'] ?? null,
        $details['image']['preview_url'] ?? null,
        $details['photo']['preview_url'] ?? null,
    ];
    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || trim($candidate) === '') {
            continue;
        }
        $url = trim($candidate);
        if (preg_match('#^https?://#i', $url) !== 1 && !str_starts_with($url, '/')) {
            continue;
        }
        return [
            'url' => $url,
            'label' => 'Foto específica',
        ];
    }

    $representativePath = ECLIPSES_IMAGE_LUNA_PARTIAL_PATH;
    $group = eclipsesTypeGroup($event);

    if ($group === 'solar') {
        $details = is_array($event['details'] ?? null) ? $event['details'] : [];
        $classification = eclipsesVisibilityClassification($event);
        $solarGlobalType = is_string($details['solar_eclipse_global']['global_type'] ?? null)
            ? strtolower(trim((string) $details['solar_eclipse_global']['global_type']))
            : '';

        $representativePath = match (true) {
            $classification === 'visible_partial',
            $classification === 'partial',
            $solarGlobalType === 'partial' => ECLIPSES_IMAGE_SOL_PARTIAL_PATH,
            // Para anular, mientras no haya foto específica, usamos SolTotal.
            $classification === 'visible_annular',
            $classification === 'annular',
            $solarGlobalType === 'annular',
            $classification === 'visible_total',
            $classification === 'total',
            $solarGlobalType === 'total',
            $classification === 'visible_hybrid',
            $classification === 'hybrid',
            $solarGlobalType === 'hybrid' => ECLIPSES_IMAGE_SOL_TOTAL_PATH,
            default => ECLIPSES_IMAGE_SOL_TOTAL_PATH,
        };
    } elseif ($group === 'lunar') {
        $lunarGlobalType = is_string($details['eclipse_global']['global_type'] ?? null)
            ? strtolower(trim((string) $details['eclipse_global']['global_type']))
            : '';
        $representativePath = match ($lunarGlobalType) {
            'total' => ECLIPSES_IMAGE_LUNA_TOTAL_PATH,
            'partial', 'penumbral' => ECLIPSES_IMAGE_LUNA_PARTIAL_PATH,
            default => ECLIPSES_IMAGE_LUNA_PARTIAL_PATH,
        };
    }

    if (is_file(__DIR__ . '/' . $representativePath)) {
        return [
            'url' => versionedAssetUrl($representativePath),
            'label' => 'Imagen representativa',
        ];
    }

    return null;
}

function eclipsesNumber($value, int $decimals = 1): ?string
{
    if (!is_numeric($value) || !is_finite((float) $value)) {
        return null;
    }
    return number_format((float) $value, $decimals, ',', '.');
}

function eclipsesPercent($value): ?string
{
    if (!is_numeric($value) || !is_finite((float) $value)) {
        return null;
    }
    $number = (float) $value;
    if ($number <= 1.0) {
        $number *= 100.0;
    }
    if ($number < 0) {
        return null;
    }
    return number_format($number, 1, ',', '.') . '%';
}

function eclipsesDuration($value): ?string
{
    if (!is_numeric($value) || !is_finite((float) $value)) {
        return null;
    }
    $seconds = (int) round((float) $value);
    if ($seconds <= 0) {
        return null;
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remaining = $seconds % 60;
    if ($hours > 0) {
        return $hours . ' h ' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . ' min';
    }
    if ($minutes > 0) {
        return $minutes . ' min ' . $remaining . ' s';
    }
    return $remaining . ' s';
}

function eclipsesVisibilityMap(array $global, array $event, ?DateTimeImmutable $eventDate): ?array
{
    $map = is_array($global['visibility_map'] ?? null) ? $global['visibility_map'] : [];
    $status = is_string($map['status'] ?? null) ? strtolower(trim((string) $map['status'])) : '';
    $filename = is_string($map['local_filename'] ?? null) ? trim((string) $map['local_filename']) : '';

    if (($map['available'] ?? null) !== true || $status !== 'available' || $filename === '') {
        return null;
    }
    if (
        basename($filename) !== $filename
        || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $filename) !== 1
        || str_contains($filename, '..')
    ) {
        return null;
    }

    $sourceLink = null;
    foreach (['catalog_url', 'source_url'] as $key) {
        $candidate = is_string($map[$key] ?? null) ? trim((string) $map[$key]) : '';
        $scheme = strtolower((string) parse_url($candidate, PHP_URL_SCHEME));
        if ($candidate !== '' && in_array($scheme, ['http', 'https'], true) && filter_var($candidate, FILTER_VALIDATE_URL) !== false) {
            $sourceLink = $candidate;
            break;
        }
    }

    $mapKind = is_string($map['map_kind'] ?? null) ? trim((string) $map['map_kind']) : '';
    $typeLabel = eclipsesTypeLabel($event);
    $dateLabel = $eventDate !== null ? astronomyEclipseDate($eventDate) : 'fecha no disponible';
    $alt = 'Mapa de visibilidad mundial del ' . strtolower($typeLabel) . ' del ' . $dateLabel;
    if ($mapKind !== '') {
        $alt .= ' (' . $mapKind . ')';
    }

    return [
        'url' => versionedAssetUrl('assets/images/eclipses/' . rawurlencode($filename)),
        'alt' => $alt,
        'attribution' => is_string($map['attribution'] ?? null) ? trim((string) $map['attribution']) : '',
        'source_link' => $sourceLink,
    ];
}

function eclipsesModalData(array $event, string $timezoneName): array
{
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $group = eclipsesTypeGroup($event);
    $global = [];
    $local = [];
    if ($group === 'solar') {
        $global = is_array($details['solar_eclipse_global'] ?? null) ? $details['solar_eclipse_global'] : [];
        $local = is_array($details['solar_eclipse_local'] ?? null) ? $details['solar_eclipse_local'] : [];
    } elseif ($group === 'lunar') {
        $global = is_array($details['eclipse_global'] ?? null) ? $details['eclipse_global'] : [];
        $local = is_array($details['eclipse_local'] ?? null) ? $details['eclipse_local'] : [];
    }

    $generalRows = [];
    $localRows = [];
    $globalType = is_string($global['global_type'] ?? null) ? trim((string) $global['global_type']) : '';
    if ($globalType !== '') {
        $generalRows[] = ['label' => 'Tipo global', 'value' => ucfirst($globalType)];
    }

    $classification = eclipsesVisibilityClassification($event);
    if ($classification !== null) {
        $localRows[] = ['label' => 'Tipo local', 'value' => eclipsesVisibilityLabel($classification)];
    }

    $maximum = eclipsesEventDateTime($event['datetime'] ?? null, $timezoneName);
    if ($maximum !== null) {
        $generalRows[] = ['label' => 'Máximo (hora local)', 'value' => astronomyEclipseDateTime($maximum)];
    }

    $firstVisible = eclipsesEventDateTime($local['first_visible_instant'] ?? null, $timezoneName);
    if ($firstVisible !== null) {
        $localRows[] = ['label' => 'Inicio visible', 'value' => astronomyEclipseDateTime($firstVisible)];
    }

    $lastVisible = eclipsesEventDateTime($local['last_visible_instant'] ?? null, $timezoneName);
    if ($lastVisible !== null) {
        $localRows[] = ['label' => 'Final visible', 'value' => astronomyEclipseDateTime($lastVisible)];
    }

    foreach (['sunrise_during_eclipse' => 'Amanecer durante eclipse', 'sunset_during_eclipse' => 'Atardecer durante eclipse', 'moonrise_during_eclipse' => 'Salida de la Luna durante eclipse', 'moonset_during_eclipse' => 'Puesta de la Luna durante eclipse'] as $key => $label) {
        $value = eclipsesEventDateTime($local[$key] ?? null, $timezoneName);
        if ($value !== null) {
            $localRows[] = ['label' => $label, 'value' => astronomyEclipseDateTime($value)];
        }
    }

    if ($group === 'solar') {
        $magnitude = eclipsesNumber($local['max_magnitude'] ?? null, 3);
        if ($magnitude !== null) {
            $localRows[] = ['label' => 'Magnitud local', 'value' => $magnitude];
        }
        $obscuration = eclipsesPercent($local['max_obscuration'] ?? null);
        if ($obscuration !== null) {
            $localRows[] = ['label' => 'Oscurecimiento', 'value' => $obscuration];
        }
        $duration = eclipsesDuration($global['central_duration_seconds'] ?? null);
        if ($duration !== null) {
            $generalRows[] = ['label' => 'Duración de fase central', 'value' => $duration];
        }
    } else {
        $umbralMagnitude = eclipsesNumber($global['magnitudes']['umbral'] ?? null, 3);
        if ($umbralMagnitude !== null) {
            $generalRows[] = ['label' => 'Magnitud umbral', 'value' => $umbralMagnitude];
        }
        $penumbralMagnitude = eclipsesNumber($global['magnitudes']['penumbral'] ?? null, 3);
        if ($penumbralMagnitude !== null) {
            $generalRows[] = ['label' => 'Magnitud penumbral', 'value' => $penumbralMagnitude];
        }
        foreach (['penumbral' => 'Duración penumbral', 'umbral' => 'Duración umbral', 'total' => 'Duración total'] as $key => $label) {
            $duration = eclipsesDuration($global['durations_seconds'][$key] ?? null);
            if ($duration !== null) {
                $generalRows[] = ['label' => $label, 'value' => $duration];
            }
        }
    }

    $contacts = [];
    foreach ((is_array($local['contacts'] ?? null) ? $local['contacts'] : []) as $contact) {
        if (!is_array($contact)) {
            continue;
        }
        $code = is_string($contact['code'] ?? null) ? strtoupper(trim((string) $contact['code'])) : '';
        if ($code === '') {
            continue;
        }
        $date = eclipsesEventDateTime($contact['datetime'] ?? null, $timezoneName);
        if ($date === null) {
            continue;
        }

        $bodyKey = $group === 'solar' ? 'sun' : 'moon';
        $bodyData = is_array($contact[$bodyKey] ?? null) ? $contact[$bodyKey] : [];
        $altitude = eclipsesNumber($bodyData['altitude_degrees'] ?? null, 1);
        $azimuth = eclipsesNumber($bodyData['azimuth_degrees'] ?? null, 1);
        $extra = [];
        if ($altitude !== null) {
            $extra[] = 'Alt. ' . $altitude . '°';
        }
        if ($azimuth !== null) {
            $extra[] = 'Az. ' . $azimuth . '°';
        }

        $contacts[] = [
            'label' => eclipsesContactCodeLabel($code),
            'time' => astronomyEclipseDateTime($date),
            'body' => eclipsesPrimaryBodyLabel($event),
            'extra' => $extra,
        ];
    }

    return [
        'general_rows' => $generalRows,
        'local_rows' => $localRows,
        'contacts' => $contacts,
        'visibility_map' => eclipsesVisibilityMap($global, $event, $maximum),
    ];
}

$location = astronomyLocationContext();
$timezoneName = $location['timezone'];
$latitude = $location['latitude'];
$longitude = $location['longitude'];
$locationLabel = $location['name'];
$locationMode = $location['mode'];
$locationMessage = astronomyLocationStatusMessage((string) ($_GET['location_status'] ?? ''));

$today = get_current_datetime($timezoneName);
$timezone = new DateTimeZone($timezoneName);
$defaultStart = $today->format('Y-m-d');
$defaultEnd = $today->modify('+5 years')->format('Y-m-d');

$startDateInput = isset($_GET['start_date']) ? trim((string) $_GET['start_date']) : $defaultStart;
$endDateInput = isset($_GET['end_date']) ? trim((string) $_GET['end_date']) : $defaultEnd;
$startDate = eclipsesParseDate($startDateInput, $timezone);
$endDate = eclipsesParseDate($endDateInput, $timezone);
if ($startDate === null) {
    $startDateInput = $defaultStart;
    $startDate = eclipsesParseDate($startDateInput, $timezone);
}
if ($endDate === null) {
    $endDateInput = $defaultEnd;
    $endDate = eclipsesParseDate($endDateInput, $timezone);
}

$typeFilter = isset($_GET['type_filter']) ? trim((string) $_GET['type_filter']) : 'all';
if (!in_array($typeFilter, ['all', 'lunar', 'solar'], true)) {
    $typeFilter = 'all';
}
$visibilityFilter = isset($_GET['visibility_filter']) ? trim((string) $_GET['visibility_filter']) : 'all';
if (!in_array($visibilityFilter, ['all', 'visible', 'not_visible'], true)) {
    $visibilityFilter = 'all';
}

$filtersWereSubmitted = isset($_GET['filters_submitted']);
$apiErrorMessage = null;
$emptyMessage = null;
$rangeNotice = null;
$events = [];

if ($filtersWereSubmitted) {
    if ($startDate === null || $endDate === null) {
        $apiErrorMessage = 'Revisá las fechas ingresadas.';
    } elseif ($endDate < $startDate) {
        $apiErrorMessage = 'La fecha hasta debe ser igual o posterior a la fecha desde.';
    } else {
        if (!eclipsesRangeIsAllowed($startDate, $endDate)) {
            $rangeNotice = 'El intervalo máximo de consulta es de 5 años.';
        } else {
            $days = (int) $startDate->diff($endDate)->days + 1;
            try {
                $apiConfig = loadAstronomyApiConfig();
                $query = http_build_query([
                    'start_date' => $startDate->format('Y-m-d'),
                    'days' => $days,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timezone' => $timezoneName,
                    'types' => 'eclipse',
                ]);
                $requestResult = astronomyApiRequest($apiConfig['base_url'] . '/v1/astronomy/events?' . $query, 'eclipses', 35);
                if ($locationMode !== 'default' && astronomyApiRejectedLocationParameters($requestResult)) {
                    astronomyRecoverDefaultLocationFromApi($requestResult);
                }
                if ($requestResult['body'] === false || (int) $requestResult['http_code'] !== 200) {
                    $apiErrorMessage = 'No se pudieron cargar los eclipses en este momento.';
                    astronomyApiRecordValidation('eclipses', null, false);
                } else {
                    $decoded = json_decode((string) $requestResult['body'], true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || !is_array($decoded['items'] ?? null)) {
                        $apiErrorMessage = 'No se pudieron cargar los eclipses en este momento.';
                        astronomyApiRecordValidation('eclipses', false, false);
                    } else {
                        astronomyApiRecordValidation('eclipses', true, true);
                        foreach (astronomyFilterEventsForSurface($decoded['items'], ASTRONOMY_EVENT_SURFACE_ECLIPSES) as $item) {
                            if (!is_array($item) || ($item['type'] ?? null) !== 'eclipse') {
                                continue;
                            }
                            $group = eclipsesTypeGroup($item);
                            if ($typeFilter === 'lunar' && $group !== 'lunar') {
                                continue;
                            }
                            if ($typeFilter === 'solar' && $group !== 'solar') {
                                continue;
                            }

                            $classification = eclipsesVisibilityClassification($item);
                            $visible = eclipsesIsVisible($classification);
                            if ($visibilityFilter === 'visible' && !$visible) {
                                continue;
                            }
                            if ($visibilityFilter === 'not_visible' && $visible) {
                                continue;
                            }
                            $events[] = $item;
                        }

                        usort($events, static function ($first, $second) use ($timezoneName): int {
                            $firstDate = eclipsesEventDateTime($first['datetime'] ?? null, $timezoneName);
                            $secondDate = eclipsesEventDateTime($second['datetime'] ?? null, $timezoneName);
                            return ($firstDate?->getTimestamp() ?? PHP_INT_MAX) <=> ($secondDate?->getTimestamp() ?? PHP_INT_MAX);
                        });

                        if ($events === []) {
                            if ($visibilityFilter === 'visible') {
                                $emptyMessage = 'No hay eclipses visibles desde tu ubicación para el rango y tipo seleccionados.';
                            } else {
                                $emptyMessage = 'No se encontraron eclipses para el rango y filtros seleccionados.';
                            }
                        }
                    }
                }
            } catch (RuntimeException $exception) {
                $apiErrorMessage = 'No se pudieron cargar los eclipses en este momento.';
            }
        }
    }
}

$pageSeo = aquellasLunasSeoPage(
    'Eclipses | Aquellas Lunas',
    'Listado de eclipses solares y lunares para tu ubicación, con filtros por tipo y visibilidad.',
    '/eclipses.php',
    'article'
);
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
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/eclipses.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/calendar-scheduler.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/page-recovery.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyMobileSwipeNavigationScript('eclipses'); ?>
</head>
<body data-api-state="<?= $apiErrorMessage !== null ? 'error' : 'ok' ?>"<?= astronomyMobileSwipeNavigationAttributes('eclipses') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($today); ?>
    <?php renderAstronomySiteHeader('eclipses', $location); ?>

    <main class="page eclipses-page">
        <div class="container public-page-container eclipses-container">
            <section class="hero eclipses-hero atmosphere-card--night" aria-labelledby="eclipses-title">
                <p class="eyebrow">SOL Y LUNA JUNTOS</p>
                <h1 id="eclipses-title"><?= htmlspecialchars(astronomySiteSectionLabel('eclipses')) ?></h1>
                <p class="hero-subtitle">Consultá fechas, tipos y visibilidad desde tu ubicación.</p>

                <form id="eclipses-query-form" class="eclipses-controls" method="get" data-max-range-years="<?= ECLIPSES_MAX_YEARS ?>">
                    <?php renderAstronomyDebugClockInput(); ?>
                    <input type="hidden" name="filters_submitted" value="1">
                    <label class="control-field eclipses-date"><span>Desde</span><input type="date" name="start_date" value="<?= htmlspecialchars($startDateInput) ?>" required></label>
                    <label class="control-field eclipses-date"><span>Hasta</span><input type="date" name="end_date" value="<?= htmlspecialchars($endDateInput) ?>" min="<?= htmlspecialchars($startDateInput) ?>" max="<?= htmlspecialchars($startDate->modify('+' . ECLIPSES_MAX_YEARS . ' years')->format('Y-m-d')) ?>" required></label>

                    <label class="control-field eclipses-select"><span>Tipo</span>
                        <select name="type_filter">
                            <option value="all"<?= $typeFilter === 'all' ? ' selected' : '' ?>>Todos</option>
                            <option value="lunar"<?= $typeFilter === 'lunar' ? ' selected' : '' ?>>Lunares</option>
                            <option value="solar"<?= $typeFilter === 'solar' ? ' selected' : '' ?>>Solares</option>
                        </select>
                    </label>

                    <label class="control-field eclipses-select"><span>Visibilidad</span>
                        <select name="visibility_filter">
                            <option value="all"<?= $visibilityFilter === 'all' ? ' selected' : '' ?>>Todos</option>
                            <option value="visible"<?= $visibilityFilter === 'visible' ? ' selected' : '' ?>>Visibles desde mi ubicación</option>
                            <option value="not_visible"<?= $visibilityFilter === 'not_visible' ? ' selected' : '' ?>>No visibles desde mi ubicación</option>
                        </select>
                    </label>

                    <button class="button button-primary eclipses-submit" type="submit">Buscar eclipses</button>
                </form>

                <p id="eclipses-loading" class="events-status visually-hidden" role="status" aria-live="polite" hidden>Buscando eclipses… La consulta puede tardar unos segundos.</p>
                <?php if ($locationMessage !== ''): ?><p class="card-note status-info" role="status"><?= htmlspecialchars($locationMessage) ?></p><?php endif; ?>
                <?php if ($rangeNotice !== null): ?><p class="card-note status-info" role="alert"><?= htmlspecialchars($rangeNotice) ?></p><?php endif; ?>
                <?php if ($apiErrorMessage !== null): ?><div class="api-error-notice" data-api-error role="alert"><p><?= htmlspecialchars($apiErrorMessage) ?> Reintentá en unos segundos.</p><button type="button" class="button compact-secondary-button" data-api-retry>Reintentar</button></div><?php endif; ?>
            </section>

            <section id="eclipses-results" class="eclipses-results" aria-labelledby="eclipses-results-title" aria-busy="false">
                <h2 id="eclipses-results-title" class="visually-hidden">Resultados de eclipses</h2>
                <div id="eclipses-results-overlay" class="eclipses-results-overlay" role="status" aria-live="polite" aria-atomic="true" hidden>
                    <div class="eclipses-results-overlay__content">
                        <span class="eclipses-results-spinner" aria-hidden="true"></span>
                        <p class="eclipses-results-overlay__title">Buscando eclipses…</p>
                        <p class="eclipses-results-overlay__detail">La consulta puede tardar unos segundos.</p>
                    </div>
                </div>
                <div id="eclipses-results-content" class="eclipses-results-content">

                <?php if (!$filtersWereSubmitted): ?>
                    <p class="card events-empty" role="status">Elegí un rango, configurá filtros y presioná Buscar eclipses.</p>
                <?php elseif ($emptyMessage !== null): ?>
                    <p class="card events-empty" role="status"><?= htmlspecialchars($emptyMessage) ?></p>
                <?php elseif ($apiErrorMessage === null && $rangeNotice === null): ?>
                    <ol class="eclipses-list" aria-label="Listado cronológico de eclipses">
                        <?php foreach ($events as $index => $event): ?>
                            <?php
                            $eventDate = eclipsesEventDateTime($event['datetime'] ?? null, $timezoneName);
                            $dateLabel = $eventDate !== null ? astronomyEclipseDate($eventDate) : 'Fecha no disponible';
                            $timeLabel = $eventDate?->format('H:i') ?? 'Hora no disponible';
                            $typeLabel = eclipsesTypeLabel($event);
                            $classification = eclipsesVisibilityClassification($event);
                            $visibilityLabel = eclipsesVisibilityLabel($classification);
                            $isVisible = eclipsesIsVisible($classification);
                            $templateId = 'eclipse-detail-' . astronomyEclipseDetailId($event);
                            $calendarPresentation = [
                                'title' => $typeLabel,
                                'summary' => $visibilityLabel,
                                'explanation' => '',
                            ];
                            $calendarEvent = astronomyCalendarEventData($event, $calendarPresentation, $timezoneName, $locationLabel, astronomyCalendarPageUrl('eclipses.php'));
                            ?>
                            <li class="eclipse-item<?= $isVisible ? '' : ' eclipse-item--not-visible' ?>">
                                <button type="button" class="eclipse-item-trigger" data-eclipse-modal-open data-eclipse-id="<?= htmlspecialchars(astronomyEclipseDetailId($event)) ?>" data-template-id="<?= htmlspecialchars($templateId) ?>" aria-label="Abrir detalle de <?= htmlspecialchars($typeLabel) ?> del <?= htmlspecialchars($dateLabel) ?>">
                                    <div class="eclipse-item-head">
                                        <?php renderAstronomyIcon(['type' => 'eclipse', 'subtype' => (string) ($event['subtype'] ?? '')], $latitude, 'eclipse-item-icon'); ?>
                                        <h3><?= htmlspecialchars($typeLabel) ?></h3>
                                        <span class="eclipse-time" aria-label="Hora local del máximo"><?= htmlspecialchars($timeLabel) ?></span>
                                    </div>
                                    <?php renderAstronomyEventDateHeader($eventDate, $today, $dateLabel, ['class' => 'eclipse-date']); ?>
                                    <p class="eclipse-visibility"><?= htmlspecialchars($visibilityLabel) ?></p>
                                    <?php if (!$isVisible): ?><p class="eclipse-badge" aria-label="Evento no visible desde la ubicación seleccionada">No visible</p><?php endif; ?>
                                </button>
                                <?php renderAstronomyCalendarLink($calendarEvent, 'calendar-action--eclipse'); ?>
                                <?php renderAstronomyEclipseDetailTemplate($event, $timezoneName, $locationLabel, astronomyCalendarPageUrl('eclipses.php')); ?>

                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
                </div>
            </section>
            <?php renderAstronomyExploreSky('eclipses'); ?>
            <?php renderAstronomyTimings(); ?>
        </div>
    </main>

    <?php renderAstronomyEclipseModal(); ?>

    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
