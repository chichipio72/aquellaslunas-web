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
require_once __DIR__ . '/includes/astronomy-icon.php';
sendDynamicNoCacheHeaders();

function homeV2ApiRequest(string $url, string $context, int $timeout, bool $customLocation): ?array
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

function homeV2Date($value, string $timezone): ?DateTimeImmutable
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

function homeV2Hour($value, string $timezone): string
{
    return homeV2Date($value, $timezone)?->format('H:i') ?? '—';
}

function homeV2ShortDate(DateTimeImmutable $date): string
{
    $months = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
    return (int) $date->format('j') . ' ' . $months[(int) $date->format('n')];
}

function homeV2LongDate(DateTimeImmutable $date, bool $withYear = false): string
{
    $months = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
    return (int) $date->format('j') . ' de ' . $months[(int) $date->format('n')] . ($withYear ? ' de ' . $date->format('Y') : '');
}

function homeV2NextHorizonEvent(array $dailyMoonData, string $key, string $timezone, DateTimeImmutable $now): ?DateTimeImmutable
{
    $candidates = [];
    foreach ($dailyMoonData as $moonData) {
        $date = is_array($moonData) ? homeV2Date($moonData[$key] ?? null, $timezone) : null;
        if ($date !== null && $date >= $now) {
            $candidates[] = $date;
        }
    }
    usort($candidates, static fn(DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);
    return $candidates[0] ?? null;
}

function homeV2HorizonLabel(string $kind, DateTimeImmutable $date, DateTimeImmutable $now): string
{
    $day = $date->format('Y-m-d') === $now->format('Y-m-d') ? '' : ' mañana';
    return ($kind === 'rise' ? 'Sale' : 'Se pone') . $day . ' ' . $date->format('H:i');
}

function homeV2TonightText(?array $data, array $events, DateTimeImmutable $now, string $timezone): ?string
{
    if ($data === null) {
        return null;
    }
    $nightStart = astronomyTonightDateTime($data['night']['start'] ?? null, $timezone);
    $nightEnd = astronomyTonightDateTime($data['night']['end'] ?? null, $timezone);
    $planets = astronomyTonightVisibleObjects(
        is_array($data['planets'] ?? null) ? $data['planets'] : [],
        astronomyTonightNightIsCurrent($data, $now, $timezone)
    );
    foreach ($events as $event) {
        $date = homeV2Date($event['datetime'] ?? null, $timezone);
        if (($event['type'] ?? '') !== 'conjunction' || $date === null || $nightStart === null || $nightEnd === null || $date < $nightStart || $date > $nightEnd) {
            continue;
        }
        $presentation = astronomyEventPresentation($event, $timezone);
        $planetNames = array_slice(array_column($planets, 'name'), 0, 2);
        return rtrim($presentation['summary'] !== '' ? $presentation['summary'] : $presentation['title'], '.')
            . ($planetNames !== [] ? '; también podrán verse ' . implode(' y ', $planetNames) : '') . '.';
    }
    if ($planets !== []) {
        $nightStart = astronomyTonightDateTime($data['night']['start'] ?? null, $timezone);
        $sentences = array_filter(array_map(static function (array $planet) use ($timezone, $nightStart): string {
            $name = trim((string) ($planet['name'] ?? ''));
            $start = astronomyTonightDateTime($planet['visibility_start'] ?? null, $timezone);
            if ($name === '') {
                return '';
            }
            if (($planet['visibility_status'] ?? null) === 'visible_now') {
                return $name . ' está visible ahora.';
            }
            if ($start === null) {
                return '';
            }
            if ($nightStart !== null && abs($start->getTimestamp() - $nightStart->getTimestamp()) <= 20 * 60) {
                return 'Al anochecer, ' . $name . ' ya estará visible.';
            }
            return $name . ' saldrá por el este a las ' . $start->format('H:i') . '.';
        }, array_slice($planets, 0, 2)));
        return implode(' ', $sentences);
    }
    $stars = astronomyTonightVisibleObjects(is_array($data['stars'] ?? null) ? $data['stars'] : [], astronomyTonightNightIsCurrent($data, $now, $timezone));
    if ($stars !== []) {
        $names = array_slice(array_column($stars, 'name'), 0, 2);
        return implode(' y ', $names) . (count($names) === 1 ? ' será una estrella notable para buscar esta noche.' : ' serán dos estrellas notables para buscar esta noche.');
    }
    return 'Esta noche no habrá planetas visibles a simple vista desde tu ubicación.';
}

$location = astronomyLocationContext();
$timezoneName = $location['timezone'];
$latitude = $location['latitude'];
$longitude = $location['longitude'];
$locationLabel = $location['name'];
$now = get_current_datetime($timezoneName);
$dateParam = $now->format('Y-m-d');
$common = ['latitude' => $latitude, 'longitude' => $longitude, 'timezone' => $timezoneName];
$usingCustomLocation = ($location['mode'] ?? 'default') !== 'default';
$daily = $nextDaily = $phasesData = $eventsData = $tonightData = $moonInstantRaw = null;
$hasApiError = false;

try {
    $apiBaseUrl = loadAstronomyApiConfig()['base_url'];
    $daily = homeV2ApiRequest($apiBaseUrl . '/v1/astronomy/daily?' . http_build_query(['date' => $dateParam] + $common), 'home v2 daily', 12, $usingCustomLocation);
    $nextDaily = homeV2ApiRequest($apiBaseUrl . '/v1/astronomy/daily?' . http_build_query(['date' => $now->modify('+1 day')->format('Y-m-d')] + $common), 'home v2 next daily', 12, $usingCustomLocation);
    $moonInstantRaw = homeV2ApiRequest($apiBaseUrl . '/v1/moon/instant?' . http_build_query(['datetime' => $now->format(DateTimeInterface::ATOM)] + $common), 'home v2 moon instant', 12, $usingCustomLocation);
    $phasesData = homeV2ApiRequest($apiBaseUrl . '/v1/astronomy/events?' . http_build_query(['start_date' => $now->modify('-35 days')->format('Y-m-d'), 'days' => 80, 'types' => 'moon_phase'] + $common), 'home v2 phases', 35, $usingCustomLocation);
    $eventsData = homeV2ApiRequest($apiBaseUrl . '/v1/astronomy/events?' . http_build_query(['start_date' => $dateParam, 'days' => 30, 'types' => 'moon_phase,apsis,conjunction,earthshine,full_moon_observation', 'max_difference_minutes' => 70] + $common), 'home v2 upcoming', 20, $usingCustomLocation);
    $tonightData = astronomyTonightRequest($apiBaseUrl, $location, $dateParam, 'summary', 'home v2 tonight', 8);
} catch (RuntimeException $exception) {
    error_log('Aquellas Lunas home v2 API configuration error: ' . $exception->getMessage());
}

if (!is_array($daily['moon'] ?? null)) {
    $daily = null;
    $hasApiError = true;
}
$phaseLabels = ['new_moon' => 'Luna nueva', 'first_quarter' => 'Cuarto creciente', 'full_moon' => 'Luna llena', 'last_quarter' => 'Cuarto menguante'];
$nextPhases = [];
foreach (($phasesData['items'] ?? []) as $event) {
    $subtype = is_array($event) ? ($event['subtype'] ?? '') : '';
    $date = is_array($event) ? homeV2Date($event['datetime'] ?? null, $timezoneName) : null;
    if (($event['type'] ?? '') === 'moon_phase' && isset($phaseLabels[$subtype]) && $date !== null && $date >= $now && !isset($nextPhases[$subtype])) {
        $nextPhases[$subtype] = $event;
    }
}
uasort($nextPhases, static fn(array $a, array $b): int => (homeV2Date($a['datetime'] ?? null, $timezoneName)?->getTimestamp() ?? PHP_INT_MAX) <=> (homeV2Date($b['datetime'] ?? null, $timezoneName)?->getTimestamp() ?? PHP_INT_MAX));
$upcomingEvents = array_values(array_filter($eventsData['items'] ?? [], static function ($event) use ($now, $timezoneName): bool {
    $date = is_array($event) ? homeV2Date($event['datetime'] ?? null, $timezoneName) : null;
    return $date !== null && $date >= $now;
}));
usort($upcomingEvents, static fn(array $a, array $b): int => (homeV2Date($a['datetime'] ?? null, $timezoneName)?->getTimestamp() ?? PHP_INT_MAX) <=> (homeV2Date($b['datetime'] ?? null, $timezoneName)?->getTimestamp() ?? PHP_INT_MAX));
$upcomingEvents = array_slice($upcomingEvents, 0, 4);

$moonInstant = homeValidateMoonInstant($moonInstantRaw);
$moonSituation = null;
$moonriseNoticeLevel = null;
$moonDailyCandidates = array_values(array_filter([$daily['moon'] ?? null, $nextDaily['moon'] ?? null], 'is_array'));
$nextMoonRise = homeV2NextHorizonEvent($moonDailyCandidates, 'rise', $timezoneName, $now);
$nextMoonSet = homeV2NextHorizonEvent($moonDailyCandidates, 'set', $timezoneName, $now);
$moonHorizonEvents = array_values(array_filter([
    $nextMoonRise !== null ? ['kind' => 'rise', 'date' => $nextMoonRise] : null,
    $nextMoonSet !== null ? ['kind' => 'set', 'date' => $nextMoonSet] : null,
]));
usort($moonHorizonEvents, static fn(array $a, array $b): int => $a['date'] <=> $b['date']);
if ($moonInstant !== null) {
    $difference = homeNearestNewMoonDifferenceDays($phasesData['items'] ?? [], $moonInstant['instant'], $timezoneName) ?? homeNewMoonDifferenceFromAge($moonInstant['age_days']);
    try {
        $moonriseNoticeMaxMinutes = loadMoonriseNoticeMaxMinutes();
    } catch (RuntimeException $exception) {
        error_log('Aquellas Lunas home v2 moonrise configuration error: ' . $exception->getMessage());
        $moonriseNoticeMaxMinutes = DEFAULT_MOONRISE_NOTICE_MAX_MINUTES;
    }
    $moonSituationPresentation = homeMoonSituationPresentation($moonInstant, $difference, $nextMoonRise, $moonriseNoticeMaxMinutes);
    $moonSituation = $moonSituationPresentation['text'];
    $moonriseNoticeLevel = $moonSituationPresentation['level'];
}
$moon = $daily['moon'] ?? [];
$moonPhase = capitalizeVisibleText($moon['phase']['name'] ?? 'Sin datos');
$illumination = isset($moon['illumination_percent']) ? round((float) $moon['illumination_percent']) . '%' : null;
$apparentSizeNumber = is_numeric($moon['apparent_size_percent'] ?? null) ? (float) $moon['apparent_size_percent'] : null;
$isSupermoon = $apparentSizeNumber !== null && $apparentSizeNumber >= astronomySupermoonMinApparentSizePercent();
$moonImageUrl = 'moon-image.php?' . http_build_query($common + ['datetime' => $now->format(DateTimeInterface::ATOM)]);
$displayDate = homeV2LongDate($now, true);
$tonightText = homeV2TonightText($tonightData, $upcomingEvents, $now, $timezoneName);
$locationMessage = astronomyLocationStatusMessage((string) ($_GET['location_status'] ?? ''));
$pageSeo = aquellasLunasSeoPage('Aquellas Lunas | El cielo de hoy', 'La Luna, el cielo de esta noche y los próximos eventos para tu ubicación.', '/');
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
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/cloud-cover.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/page-recovery.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyMobileSwipeNavigationScript('home'); ?>
</head>
<body data-api-state="<?= $hasApiError ? 'error' : 'ok' ?>" data-cloud-cover-latitude="<?= htmlspecialchars((string) $latitude, ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-longitude="<?= htmlspecialchars((string) $longitude, ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-timezone="<?= htmlspecialchars($timezoneName, ENT_QUOTES, 'UTF-8') ?>"<?= astronomyMobileSwipeNavigationAttributes('home') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($now); ?>
    <?php renderAstronomySiteHeader('home', $location); ?>
    <main class="home-v2"><div class="container public-page-container home-v2__container">
        <article class="home-v2-card home-v2-moon atmosphere-card--mixed" aria-labelledby="v2-today-title">
            <div class="home-v2-card__heading"><h1 id="v2-today-title">El cielo hoy</h1></div>
            <?php if ($locationMessage !== ''): ?><p class="status-info" role="status"><?= htmlspecialchars($locationMessage) ?></p><?php endif; ?>
            <?php if ($hasApiError): ?><div class="api-error-notice" data-api-error role="alert"><p>No pudimos actualizar todos los datos astronómicos.</p><button type="button" class="button compact-secondary-button" data-api-retry>Reintentar</button></div><?php endif; ?>
            <?php if ($daily !== null): ?>
            <div class="home-v2-moon__body">
                <div class="home-v2-moon__image"><img src="<?= htmlspecialchars($moonImageUrl, ENT_QUOTES, 'UTF-8') ?>" width="360" height="360" alt="Apariencia actual de la Luna desde <?= htmlspecialchars($locationLabel) ?>"></div>
                <div class="home-v2-moon__copy">
                    <h3><?= htmlspecialchars($moonPhase) ?></h3>
                    <?php if ($moonHorizonEvents !== []): ?><div class="home-v2-horizon" aria-label="Próximos horarios de la Luna">
                        <?php foreach ($moonHorizonEvents as $horizonEvent): ?><p><time datetime="<?= htmlspecialchars($horizonEvent['date']->format(DateTimeInterface::ATOM)) ?>"><?= htmlspecialchars(homeV2HorizonLabel($horizonEvent['kind'], $horizonEvent['date'], $now)) ?></time></p><?php endforeach; ?>
                    </div><?php endif; ?>
                    <?php if ($moonSituation !== null): ?><p class="home-v2-situation<?= $moonriseNoticeLevel !== null ? ' moonrise-notice moonrise-notice--' . htmlspecialchars($moonriseNoticeLevel) : '' ?>"><?= htmlspecialchars($moonSituation) ?></p><?php endif; ?>
                    <?php if ($illumination !== null): ?><p class="home-v2-illumination"><strong><?= htmlspecialchars($illumination) ?></strong> iluminada</p><?php endif; ?>
                    <?php if ($isSupermoon): ?><p class="home-v2-supermoon">Tamaño aparente: <?= htmlspecialchars(number_format($apparentSizeNumber, 1, ',', '.')) ?>% del promedio</p><?php endif; ?>
                    <p class="event-cloud-cover home-v2-clouds" data-current-cloud-cover hidden></p>
                </div>
            </div>
            <?php else: ?><p class="home-v2-unavailable">Los datos de la Luna no están disponibles por el momento.</p><?php endif; ?>
            <a class="home-v2-card__link" href="<?= htmlspecialchars(astronomyInternalUrl('cielo-de-hoy.php'), ENT_QUOTES, 'UTF-8') ?>">Ver el cielo de hoy en detalle <span aria-hidden="true">→</span></a>
        </article>

        <article class="home-v2-card home-v2-tonight atmosphere-card--night" aria-labelledby="v2-tonight-title">
            <div class="home-v2-card__heading"><h2 id="v2-tonight-title">El cielo esta noche</h2></div>
            <div class="home-v2-tonight__layout">
                <p class="home-v2-tonight__summary"><?= htmlspecialchars($tonightText ?? 'La información de esta noche no está disponible por el momento.') ?></p>
                <div class="home-v2-night-scene" aria-label="Esquema orientativo del cielo, no representa posiciones precisas">
                    <span class="home-v2-night-scene__star home-v2-night-scene__star--one"></span>
                    <span class="home-v2-night-scene__star home-v2-night-scene__star--two"></span>
                    <span class="home-v2-night-scene__object home-v2-night-scene__object--one"></span>
                    <span class="home-v2-night-scene__object home-v2-night-scene__object--two"></span>
                    <span class="home-v2-night-scene__horizon"></span>
                    <span class="home-v2-night-scene__east">E</span><span class="home-v2-night-scene__south">S</span><span class="home-v2-night-scene__west">O</span>
                </div>
            </div>
            <a class="home-v2-card__link" href="<?= htmlspecialchars(astronomyInternalUrl('cielo-de-esta-noche.php'), ENT_QUOTES, 'UTF-8') ?>">Explorar esta noche <span aria-hidden="true">→</span></a>
        </article>

        <article class="home-v2-card home-v2-phases-card atmosphere-card--mixed" aria-labelledby="v2-phases-title">
            <div class="home-v2-card__heading"><h2 id="v2-phases-title">Próximas fases</h2></div>
            <?php if ($nextPhases !== []): ?>
            <div class="home-v2-phases">
                <?php foreach ($nextPhases as $index => $phase): $date = homeV2Date($phase['datetime'] ?? null, $timezoneName); $presentation = astronomyEventPresentation($phase, $timezoneName); ?>
                <div class="home-v2-phase<?= $index === array_key_first($nextPhases) ? ' home-v2-phase--featured' : '' ?>">
                    <?php renderAstronomyIcon($phase, $latitude, 'home-v2-phase__icon'); ?>
                    <div><h3><?= htmlspecialchars($presentation['title']) ?></h3><p><?= $date !== null ? htmlspecialchars($index === array_key_first($nextPhases) ? homeV2ShortDate($date) . ' · ' . $date->format('H:i') : homeV2ShortDate($date)) : 'Fecha no disponible' ?></p></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?><p class="home-v2-unavailable">Las próximas fases no están disponibles por el momento.</p><?php endif; ?>
            <a class="home-v2-card__link" href="<?= htmlspecialchars(astronomyInternalUrl('sol-y-luna.php'), ENT_QUOTES, 'UTF-8') ?>">Calendario solar y lunar <span aria-hidden="true">→</span></a>
        </article>

        <article class="home-v2-card home-v2-upcoming-card atmosphere-card--night" aria-labelledby="v2-upcoming-title">
            <div class="home-v2-card__heading"><h2 id="v2-upcoming-title">Lo próximo</h2></div>
            <?php if ($upcomingEvents !== []): ?><div class="home-v2-upcoming">
                <?php foreach ($upcomingEvents as $index => $event): $date = homeV2Date($event['datetime'] ?? null, $timezoneName); $presentation = astronomyEventPresentation($event, $timezoneName); ?>
                <section class="<?= $index === 0 ? 'home-v2-event home-v2-event--featured' : 'home-v2-event' ?>">
                    <?php renderAstronomyIcon($event, $latitude, 'home-v2-event__icon'); ?>
                    <?php if ($date !== null): ?><time datetime="<?= htmlspecialchars($date->format(DateTimeInterface::ATOM)) ?>"><?= htmlspecialchars($index === 0 ? homeV2LongDate($date) : homeV2ShortDate($date)) ?></time><?php endif; ?>
                    <div><h3><?= htmlspecialchars($presentation['title']) ?></h3><?php if ($presentation['summary'] !== ''): ?><p><?= htmlspecialchars($presentation['summary']) ?></p><?php endif; ?></div>
                </section>
                <?php endforeach; ?>
            </div><?php else: ?><p class="home-v2-unavailable">No hay eventos cercanos para mostrar.</p><?php endif; ?>
            <a class="home-v2-card__link" href="<?= htmlspecialchars(astronomyInternalUrl('eventos.php'), ENT_QUOTES, 'UTF-8') ?>">Ver todos los eventos <span aria-hidden="true">→</span></a>
        </article>

        <section class="home-v2-card home-v2-explore" aria-labelledby="home-explore-title">
            <div class="home-v2-card__heading"><h2 id="home-explore-title">Explorá el cielo</h2></div>
            <p class="home-v2-explore__intro">Todo lo que necesitás para disfrutar, entender y fotografiar el cielo.</p>
            <div class="home-v2-explore__grid">
                <?php foreach ([
                    ['today', 'El cielo hoy', 'Luna, Sol y luz del día.', 'cielo-de-hoy.php'],
                    ['tonight', 'El cielo esta noche', 'Planetas y estrellas visibles.', 'cielo-de-esta-noche.php'],
                    ['calendar', 'Calendario solar y lunar', 'Horarios y próximas fases.', 'sol-y-luna.php'],
                    ['events', 'Eventos lunares', 'Conjunciones y momentos destacados.', 'eventos.php'],
                    ['eclipses', 'Eclipses', 'Cuándo ocurren y cómo se verán.', 'eclipses.php'],
                    ['planner', 'Planificador', 'Direcciones para planificar tus fotos.', 'planificador.php'],
                ] as [$theme, $title, $description, $url]): ?>
                <a class="home-v2-explore__card home-v2-explore__card--<?= $theme ?>" href="<?= htmlspecialchars(astronomyInternalUrl($url), ENT_QUOTES, 'UTF-8') ?>"><span class="home-v2-explore__visual" aria-hidden="true"><span></span><i></i></span><span><strong><?= htmlspecialchars($title) ?></strong><small><?= htmlspecialchars($description) ?></small></span><span class="home-v2-explore__arrow" aria-hidden="true">→</span></a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php renderAstronomyTimings(); ?>
    </div></main>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
