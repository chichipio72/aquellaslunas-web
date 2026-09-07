<?php

require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/astronomy-events.php';
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/site-sections.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/tonight.php';
require_once __DIR__ . '/includes/home-tonight-scene.php';
require_once __DIR__ . '/includes/moon-crater-recommendations.php';
require_once __DIR__ . '/includes/explore-sky.php';
require_once __DIR__ . '/includes/event-type-configuration.php';
require_once __DIR__ . '/includes/editorial-configuration.php';
require_once __DIR__ . '/includes/eclipse-widget-embed.php';

sendDynamicNoCacheHeaders();

$location = astronomyLocationContext();
$timezoneName = $location['timezone'];
$now = get_current_datetime($timezoneName);
$date = ((int) $now->format('H') < 12 ? $now->modify('-1 day') : $now)->format('Y-m-d');
$tonightData = null;
$tonightEvents = [];
$apiErrorMessage = null;

try {
    $tonightData = astronomyTonightRequest(
        null,
        $location,
        $date,
        'full',
        'tonight full',
        12,
        $now
    );
    if ($tonightData === null) {
        $apiErrorMessage = 'No pudimos cargar el cielo de esta noche.';
    } else {
        $nightEnd = astronomyTonightDateTime($tonightData['night']['end'] ?? null, $timezoneName);
        $nightStart = astronomyTonightDateTime($tonightData['night']['start'] ?? null, $timezoneName);
        if (($nightEnd !== null && $nightEnd < $now) || ($nightStart !== null && $date < $now->format('Y-m-d') && $nightStart > $now)) {
            $date = $now->format('Y-m-d');
            $tonightData = astronomyTonightRequest(
                null,
                $location,
                $date,
                'full',
                'tonight full next',
                12,
                $now
            );
        }
    }
    if ($tonightData !== null) {
        $eventsData = astronomyEvents([
            'start_date' => $date,
            'days' => 2,
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'timezone' => $timezoneName,
            'types' => implode(',', astronomyEventPublicTypesForSurface(ASTRONOMY_EVENT_SURFACE_TONIGHT)),
            'max_difference_minutes' => (int) astronomyEditorialNumber('event.full_moon.max_difference_minutes'),
        ],
            'tonight lunar events',
            20
        );
        $tonightEvents = astronomyFilterEventsForSurface($eventsData['items'], ASTRONOMY_EVENT_SURFACE_TONIGHT);
    }
} catch (RuntimeException $exception) {
    $apiErrorMessage = 'No pudimos cargar el cielo de esta noche.';
    error_log('Aquellas Lunas tonight astronomy error: ' . $exception->getMessage());
}

$sections = $tonightData !== null ? astronomyTonightPreparedSections($tonightData, $now, $timezoneName) : [];
$moonEncounters = $tonightData !== null ? astronomyTonightMoonEncounters($tonightData, $tonightEvents, $now, $timezoneName) : [];
$tonightHighlight = homeTonightHighlightModel($tonightData, $tonightEvents, $tonightEvents, $now, $timezoneName, (float) $location['latitude'], (float) $location['longitude'], $location);
$tonightMoonScene = $tonightHighlight['scene'];
$tonightLunarEclipse = $tonightHighlight['eclipse'];
$sections = astronomyTonightApplyMoonEditorialPriority(
    $sections,
    $tonightData !== null && astronomyTonightHasRelevantMoonEvent($tonightData, $tonightEvents, $timezoneName)
);
$highlights = astronomyTonightHighlights($sections);
$featuredStars = $sections['stars'] ?? [];
usort($featuredStars, static fn(array $a, array $b): int => ((float) ($a['magnitude'] ?? 99)) <=> ((float) ($b['magnitude'] ?? 99)));
$featuredStars = array_slice($featuredStars, 0, (int) astronomyEditorialNumber('tonight.stars.display_max'));
$featuredStarIds = array_column($featuredStars, 'id');
$remainingStars = array_values(array_filter($sections['stars'] ?? [], static fn(array $star): bool => !in_array($star['id'] ?? null, $featuredStarIds, true)));
$tonightCraterRecommendations = [];
try {
    $tonightCraterRecommendations = moonCraterRecommendations($tonightData, $location, $now, 5);
} catch (Throwable $exception) {
    error_log('Aquellas Lunas crater recommendations error: ' . $exception->getMessage());
}
$nightStartLabel = $tonightData !== null ? astronomyTonightTime($tonightData['night']['start'] ?? null, $timezoneName) : null;
$nightEndLabel = $tonightData !== null ? astronomyTonightTime($tonightData['night']['end'] ?? null, $timezoneName) : null;
$pageSeo = aquellasLunasSeoPage(
    'El cielo esta noche | Aquellas Lunas',
    'Planetas, Luna, estrellas y otros objetos visibles esta noche desde tu ubicación.',
    '/cielo-de-esta-noche.php',
    'webpage'
);
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
    <?php renderAstronomyEditorialFrontendConfiguration(); ?>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/cloud-cover.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/tonight-moon-scene.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/page-recovery.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyMobileSwipeNavigationScript('tonight'); ?>
</head>
<body data-api-state="<?= $apiErrorMessage !== null ? 'error' : 'ok' ?>" data-cloud-cover-latitude="<?= htmlspecialchars((string) $location['latitude'], ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-longitude="<?= htmlspecialchars((string) $location['longitude'], ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-timezone="<?= htmlspecialchars($timezoneName, ENT_QUOTES, 'UTF-8') ?>" data-cloud-cover-cache-scope="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>"<?= astronomyMobileSwipeNavigationAttributes('tonight') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($now); ?>
    <?php renderAstronomySiteHeader('tonight', $location); ?>

    <main class="page tonight-page">
        <div class="container public-page-container tonight-container">
            <header class="hero tonight-hero atmosphere-card--night">
                <p class="eyebrow">OBSERVACIÓN NOCTURNA</p>
                <h1><?= htmlspecialchars(astronomySiteSectionLabel('tonight')) ?></h1>
                <?php if ($nightStartLabel !== null && $nightEndLabel !== null): ?>
                    <p class="hero-subtitle">Planetas y estrellas que podrás ver desde tu ubicación de <?= htmlspecialchars($nightStartLabel) ?> a <?= htmlspecialchars($nightEndLabel) ?>.</p>
                <?php else: ?>
                    <p class="hero-subtitle">Planetas y estrellas que podrás ver desde tu ubicación durante la noche seleccionada.</p>
                <?php endif; ?>
            </header>

            <?php if ($tonightData !== null): ?>
                <section
                    class="tonight-section tonight-cloud-section"
                    data-cloud-cover-night
                    data-night-start="<?= htmlspecialchars((string) ($tonightData['night']['start'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-night-end="<?= htmlspecialchars((string) ($tonightData['night']['end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    aria-labelledby="tonight-cloud-title"
                    hidden
                >
                    <h2 id="tonight-cloud-title">Nubosidad durante la noche</h2>
                    <figure class="night-cloud-chart" aria-labelledby="tonight-cloud-title">
                        <div data-night-cloud-chart></div>
                    </figure>
                </section>
            <?php endif; ?>

            <?php if ($apiErrorMessage !== null): ?>
                <div class="api-error-notice" data-api-error role="alert">
                    <p><?= htmlspecialchars($apiErrorMessage) ?> Reintentá en unos segundos.</p>
                    <button type="button" class="button compact-secondary-button" data-api-retry>Reintentar</button>
                </div>
            <?php else: ?>
                <?php if ($sections === [] && $moonEncounters === []): ?>
                    <p class="tonight-empty" role="status">No hay objetos observables para mostrar durante esta noche.</p>
                <?php else: ?>
                    <div class="tonight-sections">
                        <?php if ($moonEncounters !== [] || $tonightLunarEclipse !== null): ?><section class="tonight-section tonight-moon-encounters<?= $tonightLunarEclipse !== null ? ' tonight-moon-encounters--eclipse' : '' ?>" aria-labelledby="tonight-moon-encounters-title">
                            <h2 id="tonight-moon-encounters-title"><?= htmlspecialchars($tonightLunarEclipse !== null ? astronomyEditorialText('tonight.lunar_eclipse.section_title') : astronomyEditorialText('tonight.moon_encounter.section_title')) ?></h2>
                            <div class="tonight-moon-encounters__layout">
                                <div class="tonight-object-list tonight-object-list--moon-encounters"><?php if ($tonightLunarEclipse !== null): ?><article class="tonight-object tonight-object--highlight"><h3><?= htmlspecialchars(astronomyEditorialText('tonight.lunar_eclipse.card_title'), ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars((string) $tonightHighlight['eclipse_text'], ENT_QUOTES, 'UTF-8') ?></p></article><?php endif; ?><?php foreach ($moonEncounters as $encounter): ?>
                                    <article class="tonight-object">
                                        <h3><?= htmlspecialchars(astronomyTonightMoonEncounterTitle($encounter)) ?></h3>
                                        <p><?= htmlspecialchars(astronomyTonightMoonEncounterText($encounter)) ?></p>
                                    </article>
                                <?php endforeach; ?></div>
                                <?php if ($tonightLunarEclipse !== null): ?><div><?php renderAstronomyEclipseWidget($tonightLunarEclipse, $timezoneName, $location, astronomyEditorialText('tonight.lunar_eclipse.card_title'), ['autoplay' => true, 'controls' => false]); ?></div><?php elseif ($tonightMoonScene !== null): ?><div><div class="tonight-moon-scene-trigger" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="tonight-moon-scene-dialog" aria-label="Ampliar esquema de la Luna y los astros cercanos" data-tonight-moon-scene-open><?php renderHomeTonightMoonScene($tonightMoonScene, false); ?><span class="tonight-moon-scene-trigger__hint">Tocá para ampliar</span></div><?php renderPhotographyEventLink(is_string($tonightMoonScene['photography_url'] ?? null) ? $tonightMoonScene['photography_url'] : null, 'home-tonight-scene__photography'); ?></div><?php endif; ?>
                            </div>
                        </section><?php endif; ?>
                        <?php if ($highlights !== []): ?><section class="tonight-section tonight-highlights" aria-labelledby="tonight-highlights-title">
                            <h2 id="tonight-highlights-title">Lo mejor para mirar esta noche</h2>
                            <div class="tonight-object-list tonight-object-list--highlights"><?php foreach ($highlights as $object): $constellationName = astronomyTonightConstellationName($object); ?>
                                <article class="tonight-object tonight-object--highlight">
                                    <span class="tonight-moment tonight-moment--<?= htmlspecialchars($object['_moment']) ?>"><?= htmlspecialchars(astronomyTonightMomentLabel($object)) ?></span>
                                    <h3><span><?= htmlspecialchars((string) $object['name']) ?></span><?php if ($constellationName !== null): ?><span class="tonight-object__constellation">(<?= htmlspecialchars($constellationName) ?>)</span><?php endif; ?></h3>
                                    <p><?= htmlspecialchars(astronomyTonightNaturalSentence($object, $tonightData, $now, $timezoneName)) ?></p>
                                </article>
                            <?php endforeach; ?></div>
                        </section><?php endif; ?>

                        <?php if (($sections['planets'] ?? []) !== []): ?><section class="tonight-section tonight-planets" aria-labelledby="tonight-planets-title">
                            <h2 id="tonight-planets-title">Planetas</h2>
                            <div class="tonight-object-list"><?php foreach ($sections['planets'] as $object): $constellationName = astronomyTonightConstellationName($object); ?>
                                <article class="tonight-object tonight-object--planet">
                                    <span class="tonight-moment tonight-moment--<?= htmlspecialchars($object['_moment']) ?>"><?= htmlspecialchars(astronomyTonightMomentLabel($object)) ?></span>
                                    <h3><span><?= htmlspecialchars((string) $object['name']) ?></span><?php if ($constellationName !== null): ?><span class="tonight-object__constellation">(<?= htmlspecialchars($constellationName) ?>)</span><?php endif; ?></h3>
                                    <p><?= htmlspecialchars(astronomyTonightNaturalSentence($object, $tonightData, $now, $timezoneName)) ?></p>
                                </article>
                            <?php endforeach; ?></div>
                        </section><?php endif; ?>

                        <?php if ($featuredStars !== []): ?><section class="tonight-section" aria-labelledby="tonight-stars-featured-title">
                            <h2 id="tonight-stars-featured-title">Estrellas destacadas</h2>
                            <div class="tonight-object-list tonight-object-list--stars"><?php foreach ($featuredStars as $object): $constellationName = astronomyTonightConstellationName($object); ?>
                                <article class="tonight-object">
                                    <span class="tonight-moment tonight-moment--<?= htmlspecialchars($object['_moment']) ?>"><?= htmlspecialchars(astronomyTonightMomentLabel($object)) ?></span>
                                    <h3><span><?= htmlspecialchars((string) $object['name']) ?></span><?php if ($constellationName !== null): ?><span class="tonight-object__constellation">(<?= htmlspecialchars($constellationName) ?>)</span><?php endif; ?></h3>
                                    <p><?= htmlspecialchars(astronomyTonightNaturalSentence($object, $tonightData, $now, $timezoneName)) ?></p>
                                </article>
                            <?php endforeach; ?></div>
                        </section><?php endif; ?>

                        <?php if ($remainingStars !== []): ?><details class="tonight-more-stars">
                            <summary>Resto de las estrellas visibles <span><?= count($remainingStars) ?></span></summary>
                            <div class="tonight-compact-list"><?php foreach ($remainingStars as $object): $constellationName = astronomyTonightConstellationName($object); ?>
                                <article><h3><span><?= htmlspecialchars((string) $object['name']) ?></span><?php if ($constellationName !== null): ?><span class="tonight-object__constellation">(<?= htmlspecialchars($constellationName) ?>)</span><?php endif; ?></h3><p><?= htmlspecialchars(astronomyTonightNaturalSentence($object, $tonightData, $now, $timezoneName)) ?></p></article>
                            <?php endforeach; ?></div>
                        </details><?php endif; ?>

                        <?php $otherObjects = array_merge($sections['moon'] ?? [], $sections['deep_sky'] ?? []); if ($otherObjects !== []): ?><section class="tonight-section" aria-labelledby="tonight-other-title">
                            <h2 id="tonight-other-title">Otros objetos</h2>
                            <div class="tonight-compact-list"><?php foreach ($otherObjects as $object): $constellationName = astronomyTonightConstellationName($object); ?>
                                <article><h3><span><?= htmlspecialchars((string) $object['name']) ?></span><?php if ($constellationName !== null): ?><span class="tonight-object__constellation">(<?= htmlspecialchars($constellationName) ?>)</span><?php endif; ?></h3><p><?= htmlspecialchars(astronomyTonightNaturalSentence($object, $tonightData, $now, $timezoneName)) ?></p></article>
                            <?php endforeach; ?></div>
                        </section><?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php renderMoonCraterRecommendations($tonightCraterRecommendations); ?>
            <?php renderAstronomyExploreSky('tonight'); ?>
            <?php renderAstronomyTimings(); ?>
        </div>
    </main>
    <?php if ($tonightMoonScene !== null && $moonEncounters !== []): ?><dialog id="tonight-moon-scene-dialog" class="tonight-moon-scene-dialog" aria-label="Esquema ampliado de la Luna y los astros cercanos" data-tonight-moon-scene-dialog><div class="tonight-moon-scene-dialog__surface"><button type="button" class="tonight-moon-scene-dialog__close" aria-label="Cerrar esquema ampliado" data-tonight-moon-scene-close>×</button><div data-tonight-moon-scene-content></div></div></dialog><?php endif; ?>
    <?php renderAstronomySiteFooter(); ?>
</body>
</html>
