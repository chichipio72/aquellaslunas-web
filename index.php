<?php
$homePageProfileRequestStarted = hrtime(true);
$homePageProfileBootstrapStarted = $homePageProfileRequestStarted;
require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/astronomy-data.php';
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
require_once __DIR__ . '/includes/tonight.php';
require_once __DIR__ . '/includes/astronomy-icon.php';
require_once __DIR__ . '/includes/date-format.php';
require_once __DIR__ . '/includes/event-date-header.php';
require_once __DIR__ . '/includes/explore-sky.php';
require_once __DIR__ . '/includes/moon-phase-presentation.php';
require_once __DIR__ . '/includes/moon-images.php';
require_once __DIR__ . '/includes/moon-three-render.php';
require_once __DIR__ . '/includes/calendar-event.php';
require_once __DIR__ . '/includes/home-upcoming-events.php';
require_once __DIR__ . '/includes/photography-links.php';
require_once __DIR__ . '/includes/home-satellite-context.php';
require_once __DIR__ . '/includes/home-tonight-scene.php';
require_once __DIR__ . '/includes/home-solar-events.php';
require_once __DIR__ . '/includes/moon-crater-recommendations.php';
require_once __DIR__ . '/includes/page-freshness.php';
require_once __DIR__ . '/includes/web-push-device-config.php';
require_once __DIR__ . '/includes/home-satellite-local-test.php';
require_once __DIR__ . '/includes/content-system.php';
require_once __DIR__ . '/includes/eclipse-detail-component.php';
require_once __DIR__ . '/includes/home-eclipse-notice.php';
sendDynamicNoCacheHeaders();
astronomyPushDeviceStartSession();
$homeNotificationCsrfToken = astronomyPushDeviceCsrfToken();

/** @param array<string,float> $details */
function homePageProfileRecord(string $label, int $startedAt, array $details = []): void
{
    if (!astronomyTimingsEnabled()) {
        return;
    }
    $GLOBALS['home_page_profile']['blocks'][$label] = (hrtime(true) - $startedAt) / 1_000_000;
    if ($details !== []) {
        $GLOBALS['home_page_profile']['details'][$label] = $details;
    }
}

function homePageProfileSet(string $label, float $milliseconds): void
{
    if (astronomyTimingsEnabled()) {
        $GLOBALS['home_page_profile']['blocks'][$label] = max(0.0, $milliseconds);
    }
}

homePageProfileRecord('Bootstrap/includes', $homePageProfileBootstrapStarted);

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
    return astronomyTonightCardText($data, $events, $now, $timezone);
}

$homePageProfileLocationStarted = hrtime(true);
$location = astronomyLocationContext();
$homeSatelliteLocalMode = homeSatelliteLocalTestMode($_GET['satellite_test'] ?? null);
$initialTimezoneName = (string) $location['timezone'];
$homeSatelliteLocalSetup = homeSatelliteLocalTestSetup(
    $homeSatelliteLocalMode,
    $location,
    get_current_datetime($initialTimezoneName)
);
$location = $homeSatelliteLocalSetup['location'];
$timezoneName = $location['timezone'];
$latitude = $location['latitude'];
$longitude = $location['longitude'];
$locationLabel = $location['name'];
$now = $homeSatelliteLocalSetup['now'];
$dateParam = $now->format('Y-m-d');
$common = ['latitude' => $latitude, 'longitude' => $longitude, 'timezone' => $timezoneName];
$usingCustomLocation = ($location['mode'] ?? 'default') !== 'default';
homePageProfileRecord('Ubicación/contexto', $homePageProfileLocationStarted);
$daily = $nextDaily = $phasesData = $tonightData = $moonInstantRaw = null;
$upcomingEvents = [];
$hasApiError = false;

$homePageProfileAstronomyStarted = hrtime(true);
$homePageProfileAstronomyDetails = [];
try {
    $homePageProfileOperationStarted = hrtime(true);
    $daily = astronomyDataDaily($common, $dateParam, false, 'home v2 daily', 12);
    $homePageProfileAstronomyDetails['daily'] = (hrtime(true) - $homePageProfileOperationStarted) / 1_000_000;
    $homePageProfileOperationStarted = hrtime(true);
    $nextDaily = astronomyDataDaily($common, $now->modify('+1 day')->format('Y-m-d'), false, 'home v2 next daily', 12);
    $homePageProfileAstronomyDetails['next daily'] = (hrtime(true) - $homePageProfileOperationStarted) / 1_000_000;
    $homePageProfileOperationStarted = hrtime(true);
    $moonInstantRaw = astronomyDataMoonInstant($common, $now, 'home v2 moon instant', 12);
    $homePageProfileAstronomyDetails['moon/instant'] = (hrtime(true) - $homePageProfileOperationStarted) / 1_000_000;
    $homePageProfileOperationStarted = hrtime(true);
    $phasesData = astronomyEvents([
        'start_date' => $now->modify('-35 days')->format('Y-m-d'),
        'days' => 80,
        'types' => 'moon_phase',
    ] + $common, 'home v2 phases', 35);
    $homePageProfileAstronomyDetails['phases'] = (hrtime(true) - $homePageProfileOperationStarted) / 1_000_000;
    $homePageProfileOperationStarted = hrtime(true);
    $GLOBALS['home_upcoming_profile_enabled'] = astronomyTimingsEnabled();
    homeUpcomingProfileReset();
    $upcomingSearch = homeUpcomingProgressiveSearch(
        $now,
        $timezoneName,
        static function (string $startDate, int $days, string $segmentLabel) use ($common): ?array {
            $typesStarted = hrtime(true);
            $publicTypes = astronomyEventPublicTypesForSurface(ASTRONOMY_EVENT_SURFACE_HOME_UPCOMING);
            homeUpcomingProfileAdd('preparación de tipos solicitados · ' . $segmentLabel, (hrtime(true) - $typesStarted) / 1_000_000);
            homeUpcomingProfileCount('lecturas de tipos para superficie');
            $editorialStarted = hrtime(true);
            $maxDifferenceMinutes = (int) astronomyEditorialNumber('event.full_moon.max_difference_minutes');
            homeUpcomingProfileAdd('preparación editorial de solicitud · ' . $segmentLabel, (hrtime(true) - $editorialStarted) / 1_000_000);
            homeUpcomingProfileCount('lecturas de parámetro editorial por segmento');
            return astronomyEvents(
                [
                    'start_date' => $startDate,
                    'days' => $days,
                    'types' => implode(',', $publicTypes),
                    'max_difference_minutes' => $maxDifferenceMinutes,
                ] + $common,
                'home v2 upcoming ' . $segmentLabel,
                20
            );
        }
    );
    $homePageProfileAstronomyDetails['upcoming'] = (hrtime(true) - $homePageProfileOperationStarted) / 1_000_000;
    $upcomingVisibilityStarted = hrtime(true);
    $upcomingEvents = astronomyFilterEventsForSurface($upcomingSearch['events'], ASTRONOMY_EVENT_SURFACE_HOME_UPCOMING);
    homeUpcomingProfileAdd('enriquecimiento local/visibilidad', (hrtime(true) - $upcomingVisibilityStarted) / 1_000_000);
    homeUpcomingProfileCount('eventos descartados por superficie', count($upcomingSearch['events']) - count($upcomingEvents));
    $homePageProfileOperationStarted = hrtime(true);
    $tonightData = astronomyTonightRequest(null, $location, $dateParam, 'summary', 'home v2 tonight', 8, $now);
    $homePageProfileAstronomyDetails['tonight'] = (hrtime(true) - $homePageProfileOperationStarted) / 1_000_000;
} catch (RuntimeException $exception) {
    error_log('Aquellas Lunas home v2 astronomy error: ' . $exception->getMessage());
}
$homeEclipseNotice = null;
$homeEclipseEvents = [];
$eclipseNoticeEnabled = astronomySiteConfigBool('eclipse.upcoming_notice.enabled', true);
$eclipseNoticeDays = (int) astronomySiteConfigValue('eclipse.upcoming_notice.days', 10);
try {
        $eclipseNoticeResponse = astronomyEvents([
            'start_date' => $now->format('Y-m-d'),
            'days' => $eclipseNoticeEnabled ? $eclipseNoticeDays + 1 : 2,
            'types' => 'eclipse',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'elevation_meters' => (float) ($location['elevation_meters'] ?? 0),
            'timezone' => $timezoneName,
        ], 'home visible eclipse notice', 35);
        $homeEclipseEvents = is_array($eclipseNoticeResponse['items'] ?? null) ? $eclipseNoticeResponse['items'] : [];
        if ($eclipseNoticeEnabled) {
        $homeEclipseNotice = homeUpcomingVisibleEclipse(
            $homeEclipseEvents,
            $now,
            $timezoneName,
            $eclipseNoticeDays
        );
        }
    } catch (RuntimeException $exception) {
        error_log('Aquellas Lunas home eclipse notice error: ' . $exception->getMessage());
    }
$homePageProfileOperationStarted = hrtime(true);
$satelliteTransitsEnabled = astronomySiteConfigBool('home.satellite_transits.enabled', true);
$homeSatelliteContext = homeSatelliteContext(
    $location,
    $now,
    $tonightData,
    $homeSatelliteLocalSetup['runner'],
    $homeSatelliteLocalMode !== null ? true : $satelliteTransitsEnabled
);
$homeSatelliteTotalMilliseconds = (hrtime(true) - $homePageProfileOperationStarted) / 1_000_000;
$homePageProfileAstronomyDetails['satellite transits'] = $homeSatelliteTotalMilliseconds;
$GLOBALS['home_satellite_diagnostic'] = homeSatelliteDiagnostic($homeSatelliteContext, $homeSatelliteTotalMilliseconds, $location);
$GLOBALS['home_satellite_local_test_reference'] = homeSatelliteLocalTestReference();
$homePageContext = [
    'location' => $location,
    'now' => $now,
    'satellite' => $homeSatelliteContext,
    'sections' => [
        'tonight' => ['satellite_events' => $homeSatelliteContext['tonight_events']],
        'upcoming' => ['satellite_events' => $homeSatelliteContext['upcoming_events']],
    ],
];
$tonightSatelliteEvents = $homePageContext['sections']['tonight']['satellite_events'];
$upcomingSatelliteEvents = $homePageContext['sections']['upcoming']['satellite_events'];
$visibleTonightSatelliteEvents = homeSatelliteDisplayEvents($tonightSatelliteEvents);
$visibleUpcomingSatelliteEvents = homeSatelliteDisplayEvents($upcomingSatelliteEvents);
homePageProfileRecord('Astronomía', $homePageProfileAstronomyStarted, $homePageProfileAstronomyDetails);

$homePageProfileCardsStarted = hrtime(true);
if (!is_array($daily['moon'] ?? null)) {
    $daily = null;
    $hasApiError = true;
}
$phaseLabels = astronomyMajorMoonPhaseLabels();
$nextPhases = [];
foreach (($phasesData['items'] ?? []) as $event) {
    $subtype = is_array($event) ? ($event['subtype'] ?? '') : '';
    $date = is_array($event) ? homeV2Date($event['datetime'] ?? null, $timezoneName) : null;
    if (($event['type'] ?? '') === 'moon_phase' && astronomyEventVisibleOnSurface($event, ASTRONOMY_EVENT_SURFACE_HOME_PHASES) && isset($phaseLabels[$subtype]) && astronomyEventIsFuture($date, $now) && !isset($nextPhases[$subtype])) {
        $nextPhases[$subtype] = $event;
    }
}
uasort($nextPhases, static fn(array $a, array $b): int => (homeV2Date($a['datetime'] ?? null, $timezoneName)?->getTimestamp() ?? PHP_INT_MAX) <=> (homeV2Date($b['datetime'] ?? null, $timezoneName)?->getTimestamp() ?? PHP_INT_MAX));
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
        $moonriseNoticeMaxMinutes = (int) astronomyEditorialNumber('home.moonrise.max_minutes');
    } catch (RuntimeException $exception) {
        error_log('Aquellas Lunas home v2 moonrise configuration error: ' . $exception->getMessage());
        $moonriseNoticeMaxMinutes = DEFAULT_MOONRISE_NOTICE_MAX_MINUTES;
    }
    $moonSituationPresentation = homeMoonSituationPresentation($moonInstant, $difference, $nextMoonRise, $moonriseNoticeMaxMinutes);
    $moonSituation = $moonSituationPresentation['text'];
    $moonriseNoticeLevel = $moonSituationPresentation['level'];
}
$moon = $daily['moon'] ?? [];
$moonPhase = astronomyMoonPhaseLabelForLocalDate($dateParam, $timezoneName, $phasesData['items'] ?? []) ?? 'Fase no disponible';
$illumination = isset($moon['illumination_percent']) ? round((float) $moon['illumination_percent']) . '%' : null;
$apparentSizeNumber = is_numeric($moon['apparent_size_percent'] ?? null) ? (float) $moon['apparent_size_percent'] : null;
$isSupermoon = $apparentSizeNumber !== null && $apparentSizeNumber >= astronomySupermoonMinApparentSizePercent();
$moonImageUrl = 'moon-image.php?' . http_build_query($common + ['datetime' => $now->format(DateTimeInterface::ATOM)]);
recordMoonImageDiagnostic('home v2 moon image', $moon['illumination_percent'] ?? null, $moon['age_days'] ?? null, (float) $latitude);
$moonOrientation = moonApparentRotation($now, (float) $latitude, (float) $longitude, $timezoneName);
$moonCssRotation = $moonOrientation['css_degrees'] ?? 0.0;
$homeMoonThreePayload = null;
$homeMoonExplorerPayload = null;
$homeMoonInteractiveUrl = astronomyInternalUrl('luna-interactiva.php?' . http_build_query([
    'fecha' => $now->format('Y-m-d'),
    'hora' => $now->format('H:i'),
]));
$homeMoonThreePreparationStarted = hrtime(true);
try {
    $homeMoonObserver = new AstronomyEngine\Facade\AstronomyObserver(
        (float) $latitude,
        (float) $longitude,
        $timezoneName,
        (float) ($location['elevation_meters'] ?? 0.0),
    );
    $homeMoonThreePayload = moonThreeRenderPayload(
        $now,
        $homeMoonObserver,
    );
    $homeMoonExplorerPayload = moonThreeRenderPayload(
        $now,
        $homeMoonObserver,
        interactiveMoonThreeRenderConfigurationLoad(),
        'interactive.moon_three.',
    );
    // El visor comparte exactamente el instante/geometría de la tarjeta, aunque su
    // apariencia y relieve procedan de la configuración de Luna interactiva.
    $homeMoonExplorerPayload['geometry'] = $homeMoonThreePayload['geometry'];
    $homeMoonExplorerPayload['features'] = [];
    $homeMoonExplorerPayload['interactive'] = [
        'standalone' => true,
        'craters' => false,
        'maria' => false,
        'other' => false,
        'landings' => false,
        'detail' => 'main',
        'illumination' => 'realistic',
    ];
} catch (Throwable $exception) {
    error_log('Aquellas Lunas home Three.js Moon error: ' . $exception->getMessage());
}
if (astronomyTimingsEnabled()) {
    $GLOBALS['home_moon_three_diagnostic'] = [
        'server_ms' => (hrtime(true) - $homeMoonThreePreparationStarted) / 1_000_000,
        'status' => $homeMoonThreePayload !== null ? 'preparada' : 'fallback',
    ];
}
$displayDate = homeV2LongDate($now, true);
$tonightHighlight = homeTonightHighlightModel($tonightData, $upcomingEvents, $homeEclipseEvents, $now, $timezoneName, (float) $latitude, (float) $longitude, $location);
$tonightText = $tonightHighlight['text'];
$tonightMoonScene = $tonightHighlight['scene'];
$tonightLunarEclipse = $tonightHighlight['eclipse'];
$homeNextSolarEvents = homeNextSolarEvents(
    array_values(array_filter([$daily['sun'] ?? null, $nextDaily['sun'] ?? null], 'is_array')),
    $now,
    $timezoneName
);
$tonightCraterRecommendations = [];
try {
    $tonightCraterRecommendations = moonCraterRecommendations($tonightData, $location, $now, 3);
} catch (Throwable $exception) {
    error_log('Aquellas Lunas crater recommendations error: ' . $exception->getMessage());
}
$locationMessage = astronomyLocationStatusMessage((string) ($_GET['location_status'] ?? ''));
$pageSeo = aquellasLunasSeoPage('Aquellas Lunas | El cielo de hoy', 'La Luna, el cielo de esta noche y los próximos eventos para tu ubicación.', '/');
homePageProfileRecord('Armado de datos/tarjetas', $homePageProfileCardsStarted);

$homePageProfileMysqlStarted = hrtime(true);
$showHomeTodayCard = astronomySiteHomeBlockEnabled('today');
$showHomeTonightCard = astronomySiteHomeBlockEnabled('tonight');
$showHomePhasesCard = astronomySiteHomeBlockEnabled('phases');
$showHomeUpcomingCard = astronomySiteHomeBlockEnabled('upcoming');
$showHomeExploreCard = astronomySiteHomeBlockEnabled('explore_sky');
$showHomeInstallCard = astronomySiteHomeBlockEnabled('install');
$showHomeTriviaCard = astronomySiteHomeBlockEnabled('trivia');
$showHomeFactCard = astronomySiteHomeBlockEnabled('sabias_que');
$contentEnabled = isContentEnabled();
homePageProfileRecord('MySQL/configuración', $homePageProfileMysqlStarted);
$homePageProfileRenderStarted = hrtime(true);
$homePageProfileContentMilliseconds = 0.0;
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
    <?php if ($showHomeTodayCard && $homeMoonExplorerPayload !== null): ?><link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/interactive-moon.css'), ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyEditorialFrontendConfiguration(); ?>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/cloud-cover.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/calendar-scheduler.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/content-trivia.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/protected-photos.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/page-recovery.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/page-freshness.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/home-notification-links.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php if ($homeEclipseNotice !== null): ?><script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/eclipses.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script><?php endif; ?>
    <?php if ($showHomeTodayCard && $homeMoonThreePayload !== null): ?><script type="module" src="<?= htmlspecialchars(versionedAssetUrl('assets/js/moon-three-render.js'), ENT_QUOTES, 'UTF-8') ?>"></script><?php endif; ?>
    <?php if ($showHomeTodayCard && $homeMoonExplorerPayload !== null): ?>
    <script type="module" src="<?= htmlspecialchars(versionedAssetUrl('assets/js/interactive-moon.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/home-moon-explorer.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php endif; ?>
    <?php renderAstronomyMobileSwipeNavigationScript('home'); ?>
</head>
<body data-api-state="<?= $hasApiError ? 'error' : 'ok' ?>" data-cloud-cover-latitude="<?= htmlspecialchars((string) $latitude, ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-longitude="<?= htmlspecialchars((string) $longitude, ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-timezone="<?= htmlspecialchars($timezoneName, ENT_QUOTES, 'UTF-8') ?>" data-notification-config-url="<?= htmlspecialchars(astronomyInternalUrl('web-push/device-config.php'), ENT_QUOTES, 'UTF-8') ?>" data-notification-csrf="<?= htmlspecialchars($homeNotificationCsrfToken, ENT_QUOTES, 'UTF-8') ?>"<?= astronomyMobileSwipeNavigationAttributes('home') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($now); ?>
    <?php renderAstronomySiteHeader('home', $location); ?>
    <main class="home-v2"><div class="container public-page-container home-v2__container">
        <?php renderAstronomyPageFreshnessNotice(); ?>
        <?php if ($homeEclipseNotice !== null): ?>
        <?php $noticeEvent = $homeEclipseNotice['event']; $noticeDate = $homeEclipseNotice['date']; ?>
        <aside class="home-eclipse-notice atmosphere-card--night" aria-label="Próximo eclipse visible">
            <div><p class="eyebrow">ECLIPSE VISIBLE DESDE TU UBICACIÓN</p><p><?= htmlspecialchars(homeEclipseNoticeText($noticeEvent, $noticeDate, $now)) ?></p></div>
            <?php renderAstronomyEclipseDetailTrigger($noticeEvent, 'Ver eclipse'); ?>
            <?php renderAstronomyEclipseDetailTemplate($noticeEvent, $timezoneName, $locationLabel, astronomyCalendarPageUrl('eclipses.php'), null, $location); ?>
        </aside>
        <?php endif; ?>
        <?php if ($showHomeTodayCard): ?>
        <article class="home-v2-card home-v2-moon atmosphere-card--mixed" aria-labelledby="v2-today-title">
            <div class="home-v2-card__heading"><h1 id="v2-today-title">El cielo hoy</h1></div>
            <?php if ($locationMessage !== ''): ?><p class="status-info" role="status"><?= htmlspecialchars($locationMessage) ?></p><?php endif; ?>
            <?php if ($hasApiError): ?><div class="api-error-notice" data-api-error role="alert"><p>No pudimos actualizar todos los datos astronómicos.</p><button type="button" class="button compact-secondary-button" data-api-retry>Reintentar</button></div><?php endif; ?>
            <?php if ($daily !== null): ?>
            <div class="home-v2-moon__body">
                <div class="home-v2-moon__image moon-apparent-orientation<?= $homeMoonThreePayload !== null ? ' moon-three-render home-v2-moon__image--explorable' : '' ?>" data-moon-rotation-degrees="<?= htmlspecialchars((string) $moonCssRotation) ?>"<?= $homeMoonThreePayload !== null ? ' data-moon-three data-moon-three-state="loading" data-home-moon-explorer-open role="button" tabindex="0" aria-haspopup="dialog" aria-controls="home-moon-explorer-dialog" aria-label="Ampliar y explorar la Luna actual desde ' . htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                    <img class="moon-three-render__fallback" <?= $homeMoonThreePayload !== null ? 'data-fallback-src="' . htmlspecialchars($moonImageUrl, ENT_QUOTES, 'UTF-8') . '" hidden' : 'src="' . htmlspecialchars($moonImageUrl, ENT_QUOTES, 'UTF-8') . '"' ?> width="360" height="360" alt="<?= $homeMoonThreePayload !== null ? '' : 'Apariencia actual de la Luna desde ' . htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?>" style="--moon-apparent-rotation: <?= htmlspecialchars((string) $moonCssRotation) ?>deg">
                    <?php if ($homeMoonThreePayload !== null): ?><script type="application/json" data-moon-three-payload><?= json_encode($homeMoonThreePayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script><?php endif; ?>
                </div>
                <div class="home-v2-moon__copy">
                    <h2><?= htmlspecialchars($moonPhase) ?></h2>
                    <?php if ($moonHorizonEvents !== []): ?><div class="home-v2-horizon<?= count($moonHorizonEvents) >= 2 ? ' home-v2-horizon--two-events' : '' ?>" aria-label="Próximos horarios de la Luna">
                        <?php foreach ($moonHorizonEvents as $horizonEvent): ?><p><time datetime="<?= htmlspecialchars($horizonEvent['date']->format(DateTimeInterface::ATOM)) ?>"><?= htmlspecialchars(homeV2HorizonLabel($horizonEvent['kind'], $horizonEvent['date'], $now)) ?></time></p><?php endforeach; ?>
                        <?php if ($nextMoonRise !== null): ?><?php renderHomeNotificationLink('moonrise'); ?><?php endif; ?>
                    </div><?php endif; ?>
                    <?php if ($moonSituation !== null): ?><p class="home-v2-situation<?= $moonriseNoticeLevel !== null ? ' moonrise-notice moonrise-notice--' . htmlspecialchars($moonriseNoticeLevel) : '' ?>"><?= htmlspecialchars($moonSituation) ?></p><?php endif; ?>
                    <?php if ($illumination !== null): ?><p class="home-v2-illumination"><strong><?= htmlspecialchars($illumination) ?></strong> iluminada</p><?php endif; ?>
                    <?php if ($isSupermoon): ?><p class="home-v2-supermoon"><?= htmlspecialchars(astronomyEditorialText('event.supermoon.size', ['porcentaje' => number_format($apparentSizeNumber, 1, ',', '.')])) ?></p><?php endif; ?>
                    <p class="event-cloud-cover home-v2-clouds" data-current-cloud-cover hidden></p>
                </div>
            </div>
            <?php else: ?><p class="home-v2-unavailable">Los datos de la Luna no están disponibles por el momento.</p><?php endif; ?>
            <a class="home-v2-card__link" href="<?= htmlspecialchars(astronomyInternalUrl('cielo-de-hoy.php'), ENT_QUOTES, 'UTF-8') ?>">Ver el cielo de hoy en detalle <span aria-hidden="true">→</span></a>
        </article>
        <?php endif; ?>

        <?php if ($showHomeTonightCard): ?>
        <article class="home-v2-card home-v2-tonight<?= $tonightLunarEclipse !== null ? ' home-v2-tonight--eclipse' : '' ?> atmosphere-card--night" aria-labelledby="v2-tonight-title">
            <div class="home-v2-card__heading"><h2 id="v2-tonight-title">El cielo esta noche</h2></div>
            <div class="home-v2-tonight__body<?= $tonightMoonScene !== null || $tonightLunarEclipse !== null ? ' home-v2-tonight__body--with-scene' : '' ?>"><div class="home-v2-tonight__copy"><p class="home-v2-tonight__summary"><?= htmlspecialchars($tonightText ?? 'La información de esta noche no está disponible por el momento.') ?></p><?php if (count($homeNextSolarEvents) === 2): ?><p class="home-v2-tonight__solar"><?php foreach ($homeNextSolarEvents as $solarIndex => $solarEvent): ?><?= $solarIndex > 0 ? ' <span aria-hidden="true">·</span> ' : '' ?><span><?= htmlspecialchars($solarEvent['label'], ENT_QUOTES, 'UTF-8') ?> <time datetime="<?= htmlspecialchars($solarEvent['date']->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($solarEvent['time'], ENT_QUOTES, 'UTF-8') ?></time></span><?php endforeach; ?></p><?php endif; ?></div><?php if ($tonightLunarEclipse !== null): ?><?php renderAstronomyEclipseWidget($tonightLunarEclipse, $timezoneName, $location, astronomyEditorialText('tonight.lunar_eclipse.card_title'), ['autoplay' => true, 'controls' => false]); ?><?php elseif ($tonightMoonScene !== null): ?><?php renderHomeTonightMoonScene($tonightMoonScene); ?><?php endif; ?></div>
            <?php renderMoonCraterRecommendations($tonightCraterRecommendations, true); ?>
            <?php if ($visibleTonightSatelliteEvents !== []): ?><div class="home-v2-upcoming home-v2-satellite-events home-v2-satellite-events--tonight">
                <?php renderHomeSatelliteEventItems($visibleTonightSatelliteEvents, $timezoneName, $now, true); ?>
            </div><?php endif; ?>
            <a class="home-v2-card__link" href="<?= htmlspecialchars(astronomyInternalUrl('cielo-de-esta-noche.php'), ENT_QUOTES, 'UTF-8') ?>">Explorar esta noche <span aria-hidden="true">→</span></a>
        </article>
        <?php endif; ?>

        <?php if ($showHomePhasesCard): ?>
        <article class="home-v2-card home-v2-phases-card atmosphere-card--mixed" aria-labelledby="v2-phases-title">
            <div class="home-v2-card__heading"><h2 id="v2-phases-title">Próximas fases</h2></div>
            <?php if ($nextPhases !== []): ?>
            <div class="home-v2-phases">
                <?php foreach ($nextPhases as $index => $phase): $date = homeV2Date($phase['datetime'] ?? null, $timezoneName); $presentation = astronomyEventPresentation($phase, $timezoneName); ?>
                <div class="home-v2-phase<?= $index === array_key_first($nextPhases) ? ' home-v2-phase--featured' : '' ?>">
                    <?php renderAstronomyIcon($phase, $latitude, 'home-v2-phase__icon'); ?>
                    <div><h3><?= htmlspecialchars($presentation['title']) ?></h3><?php renderAstronomyEventDateHeader($date, $now, $date !== null ? ($index === array_key_first($nextPhases) ? astronomyNearbyEventDate($date) . ' · ' . $date->format('H:i') : astronomyNearbyEventDate($date)) : 'Fecha no disponible', ['class' => 'home-v2-phase__date', 'observational_night' => true]); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?><p class="home-v2-unavailable">Las próximas fases no están disponibles por el momento.</p><?php endif; ?>
            <a class="home-v2-card__link" href="<?= htmlspecialchars(astronomyInternalUrl('sol-y-luna.php'), ENT_QUOTES, 'UTF-8') ?>">Calendario solar y lunar <span aria-hidden="true">→</span></a>
        </article>
        <?php endif; ?>

        <?php if ($showHomeUpcomingCard): ?>
        <article class="home-v2-card home-v2-upcoming-card atmosphere-card--night" aria-labelledby="v2-upcoming-title">
            <div class="home-v2-card__heading"><h2 id="v2-upcoming-title">Lo próximo</h2></div>
            <?php if ($upcomingEvents !== [] || $visibleUpcomingSatelliteEvents !== []): ?><div class="home-v2-upcoming">
                <?php foreach ($upcomingEvents as $index => $event): ?><?php
                    $cardPreparationStarted = hrtime(true);
                    $date = homeV2Date($event['datetime'] ?? null, $timezoneName);
                    $presentation = astronomyEventPresentation($event, $timezoneName);
                    $horizonDetail = astronomyFullMoonObservationMoment($event, $timezoneName);
                    $observation = is_array($presentation['observation'] ?? null) ? $presentation['observation'] : null;
                    homeUpcomingProfileAdd('presentación y enriquecimiento de tarjetas', (hrtime(true) - $cardPreparationStarted) / 1_000_000);
                    $cloudPreparationStarted = hrtime(true);
                    $cloudDate = $observation['cloud_time'] ?? $horizonDetail['date'] ?? $date;
                    homeUpcomingProfileAdd('preparación de nubosidad', (hrtime(true) - $cloudPreparationStarted) / 1_000_000);
                    $calendarPreparationStarted = hrtime(true);
                    $calendarEvent = astronomyCalendarEventData($event, $presentation, $timezoneName, $locationLabel, astronomyCalendarPageUrl('eventos.php'));
                    $notificationType = homeNotificationTypeForEvent($event);
                    $photographyUrl = photographyEventUrl($event, $location, $observation['start'] ?? $horizonDetail['date'] ?? $date);
                    homeUpcomingProfileAdd('preparación de acciones de tarjetas', (hrtime(true) - $calendarPreparationStarted) / 1_000_000);
                    $cardRenderStarted = hrtime(true);
                ?>
                <section class="<?= $index === 0 ? 'home-v2-event home-v2-event--featured' : 'home-v2-event' ?><?= $notificationType !== null ? ' home-v2-event--notifiable' : '' ?>">
                    <?php if ($notificationType !== null) renderHomeNotificationLink($notificationType); ?>
                    <?php renderAstronomyIcon($event, $latitude, 'home-v2-event__icon'); ?>
                    <?php if ($date !== null): ?><?php renderAstronomyEventDateHeader($date, $now, astronomyNearbyEventDate($date), ['class' => 'home-v2-event__date', 'observational_night' => true]); ?><?php endif; ?>
                    <div><h3><?= htmlspecialchars($presentation['title']) ?></h3><?php if ($presentation['summary'] !== ''): ?><p><?= htmlspecialchars($presentation['summary']) ?></p><?php endif; ?><?php if ($observation !== null): ?><p class="home-v2-event__times"><?= htmlspecialchars($observation['first_label']) ?> <?= htmlspecialchars($observation['first_time']->format('H:i')) ?> · <?= htmlspecialchars($observation['second_label']) ?> <?= htmlspecialchars($observation['second_time']->format('H:i')) ?></p><?php elseif ($horizonDetail !== null): ?><p class="home-v2-event__horizon"><span><?= htmlspecialchars($horizonDetail['label']) ?> · <time datetime="<?= htmlspecialchars($horizonDetail['date']->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($horizonDetail['date']->format('H:i')) ?></time></span></p><?php endif; ?><?php if ($cloudDate !== null): ?><p class="home-v2-event__horizon" data-cloud-cover-event="<?= htmlspecialchars($cloudDate->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') ?>"><span>Nubosidad prevista</span><span class="weather-cloud-icon weather-cloud-icon--compact" data-cloud-cover-icon hidden></span></p><?php endif; ?><?php if ($presentation['explanation'] !== ''): ?><p><?= htmlspecialchars($presentation['explanation']) ?></p><?php endif; ?><div class="event-actions"><?php renderAstronomyCalendarLink($calendarEvent, 'calendar-action--home'); ?><?php renderPhotographyEventLink($photographyUrl, 'photography-event-link--home'); ?></div></div>
                </section>
                <?php homeUpcomingProfileAdd('armado final de tarjetas', (hrtime(true) - $cardRenderStarted) / 1_000_000); ?>
                <?php endforeach; ?>
                <?php renderHomeSatelliteEventItems($visibleUpcomingSatelliteEvents, $timezoneName, $now, $upcomingEvents === []); ?>
            </div><?php else: ?><p class="home-v2-unavailable">No hay eventos cercanos para mostrar.</p><?php endif; ?>
            <a class="home-v2-card__link" href="<?= htmlspecialchars(astronomyInternalUrl('eventos.php'), ENT_QUOTES, 'UTF-8') ?>">Ver todos los eventos <span aria-hidden="true">→</span></a>
        </article>
        <?php if (astronomyTimingsEnabled()): ?><?php
            $homeUpcomingProfile = is_array($GLOBALS['home_upcoming_profile'] ?? null) ? $GLOBALS['home_upcoming_profile'] : [];
            foreach (($homeUpcomingProfile['timings'] ?? []) as $profileLabel => $profileMilliseconds) {
                $GLOBALS['home_page_profile']['details']['Astronomía']['upcoming · ' . $profileLabel] = (float) $profileMilliseconds;
            }
            $GLOBALS['home_page_profile']['upcoming'] = $homeUpcomingProfile;
        ?><?php endif; ?>
        <?php endif; ?>

        <?php if ($contentEnabled && ($showHomeTriviaCard || $showHomeFactCard)): ?><?php
            $homePageProfileContentStarted = hrtime(true);
            $homePageProfileContentLoadStarted = hrtime(true);
            $GLOBALS['home_page_profile_content_detail_enabled'] = astronomyTimingsEnabled();
            $homeContentCatalog = astronomyLoadHomeContentCatalog($showHomeTriviaCard, $showHomeFactCard);
            unset($GLOBALS['home_page_profile_content_detail_enabled']);
            $homePageProfileContentLoadMilliseconds = (hrtime(true) - $homePageProfileContentLoadStarted) / 1_000_000;
            $homePageProfileContentRenderStarted = hrtime(true);
            renderAstronomyHomeContentCards($homeContentCatalog, $showHomeTriviaCard, $showHomeFactCard);
            $homePageProfileContentRenderMilliseconds = (hrtime(true) - $homePageProfileContentRenderStarted) / 1_000_000;
            $homePageProfileContentMilliseconds = (hrtime(true) - $homePageProfileContentStarted) / 1_000_000;
            homePageProfileSet('Contenido/trivias', $homePageProfileContentMilliseconds);
            if (astronomyTimingsEnabled()) {
                $GLOBALS['home_page_profile']['details']['Contenido/trivias'] = ['carga focalizada' => $homePageProfileContentLoadMilliseconds]
                    + (is_array($GLOBALS['home_page_profile_content_detail'] ?? null) ? $GLOBALS['home_page_profile_content_detail'] : [])
                    + ['render de tarjetas' => $homePageProfileContentRenderMilliseconds];
                $GLOBALS['home_page_profile']['counts']['Contenido/trivias queries'] = (int) ($GLOBALS['home_content_query_count'] ?? 0);
            }
        ?><?php else: ?><?php homePageProfileSet('Contenido/trivias', 0.0); ?><?php endif; ?>
        <?php if ($showHomeExploreCard): ?><?php renderAstronomyExploreSky(); ?><?php endif; ?>
        <?php if ($showHomeInstallCard): ?>
        <article class="home-v2-card home-v2-install-card" data-install-card hidden aria-labelledby="v2-install-title">
            <div class="home-v2-card__heading home-v2-install-card__heading">
                <h2 id="v2-install-title" data-install-title>Tené Aquellas Lunas a mano</h2>
                <button class="home-v2-install-card__dismiss" type="button" data-install-dismiss data-install-source="home_card" aria-label="Cerrar sugerencia de instalación">×</button>
            </div>
            <p class="home-v2-install-card__copy" data-install-copy>Guardá Aquellas Lunas en tu dispositivo para volver a abrirla rápido desde la pantalla de inicio o desde favoritos.</p>
            <div class="home-v2-install-card__actions">
                <button type="button" class="button button-primary" data-install-action data-install-trigger data-install-source="home_card" hidden>Instalar aplicación</button>
                <button type="button" class="button compact-secondary-button" data-install-secondary data-install-source="home_card" hidden>Guardar en favoritos</button>
                <button type="button" class="home-v2-install-card__help-toggle" data-install-help-toggle data-install-source="home_card" hidden>Ver pasos</button>
            </div>
            <p class="home-v2-install-card__status" data-install-status aria-live="polite"></p>
            <div class="home-v2-install-card__help" data-install-help hidden>
                <ol>
                    <li>Tocar Compartir.</li>
                    <li>Elegir Agregar a pantalla de inicio.</li>
                    <li>Confirmar con Agregar.</li>
                </ol>
            </div>
        </article>
        <?php endif; ?>
        <?php
        $homePageProfileRenderedMilliseconds = (hrtime(true) - $homePageProfileRenderStarted) / 1_000_000;
        homePageProfileSet('Clima/APIs externas server-side', 0.0);
        homePageProfileSet('Render restante', $homePageProfileRenderedMilliseconds - $homePageProfileContentMilliseconds);
        if (astronomyTimingsEnabled()) {
            $GLOBALS['home_page_profile']['total_ms'] = array_sum($GLOBALS['home_page_profile']['blocks'] ?? []);
            $GLOBALS['home_page_profile']['measured_until_ms'] = (hrtime(true) - $homePageProfileRequestStarted) / 1_000_000;
        }
        renderAstronomyTimings();
        ?>
    </div></main>
    <?php if ($showHomeTodayCard && $homeMoonExplorerPayload !== null): ?>
    <dialog class="home-moon-explorer" id="home-moon-explorer-dialog" data-home-moon-explorer-dialog aria-labelledby="home-moon-explorer-title">
        <div class="home-moon-explorer__surface" data-home-moon-explorer-surface>
            <header class="home-moon-explorer__header">
                <div>
                    <h2 id="home-moon-explorer-title">La Luna de ahora</h2>
                    <p><?= htmlspecialchars($displayDate . ' · ' . $now->format('H:i') . ' · ' . $locationLabel) ?></p>
                </div>
                <button type="button" class="home-moon-explorer__icon-button" data-home-moon-explorer-close aria-label="Cerrar visor">×</button>
            </header>
            <div class="interactive-moon-render home-moon-explorer__render" data-interactive-moon data-moon-state="loading">
                <script type="application/json" data-interactive-moon-payload><?= json_encode($homeMoonExplorerPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                <div class="interactive-moon-labels" data-moon-labels aria-hidden="true"></div>
                <p class="interactive-moon-loading">Preparando la Luna…</p>
                <p class="interactive-moon-instructions">Arrastrá para girar · rueda o pellizco para acercar</p>
            </div>
            <footer class="home-moon-explorer__controls">
                <div class="interactive-moon-zoom" aria-label="Zoom">
                    <button type="button" data-moon-zoom-out aria-label="Alejar">−</button>
                    <button type="button" data-moon-zoom-in aria-label="Acercar">+</button>
                </div>
                <button type="button" class="home-moon-explorer__button" data-moon-reset-view>Volver a orientación real</button>
                <button type="button" class="home-moon-explorer__button" data-home-moon-explorer-fullscreen>Pantalla completa</button>
                <a class="home-moon-explorer__link" href="<?= htmlspecialchars($homeMoonInteractiveUrl, ENT_QUOTES, 'UTF-8') ?>">Abrir en Luna interactiva <span aria-hidden="true">→</span></a>
            </footer>
        </div>
    </dialog>
    <?php endif; ?>
    <?php if ($homeEclipseNotice !== null) renderAstronomyEclipseModal(); ?>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
