<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/astronomy-events.php';
require_once __DIR__ . '/../includes/home-tonight-scene.php';
require_once __DIR__ . '/../includes/eclipse-widget-embed.php';

function homeTonightEclipseAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$timezone = 'America/Argentina/Buenos_Aires';
$location = [
    'latitude' => -34.6037,
    'longitude' => -58.3816,
    'elevation_meters' => 0.0,
    'timezone' => $timezone,
];
$now = new DateTimeImmutable('2026-08-27 12:00:00', new DateTimeZone($timezone));
$sourceCache = &astronomyEventSourceRequestCache();
$sourceCache['eclipse'] = 'php';
$response = astronomyEvents([
    'start_date' => '2026-08-27',
    'days' => 2,
    'types' => 'eclipse',
    'latitude' => $location['latitude'],
    'longitude' => $location['longitude'],
    'elevation_meters' => $location['elevation_meters'],
    'timezone' => $timezone,
], 'test home tonight eclipse highlight', 35);
$events = is_array($response['items'] ?? null) ? $response['items'] : [];
$highlight = homeTonightHighlightModel(null, [], $events, $now, $timezone, $location['latitude'], $location['longitude'], $location);

homeTonightEclipseAssert($highlight['kind'] === 'lunar_eclipse' && is_array($highlight['eclipse']), 'El eclipse lunar real no fue elegido como protagonista.');
homeTonightEclipseAssert(str_starts_with((string) $highlight['text'], 'Esta noche'), 'El protagonista no usa la noche observacional.');
$regressionEvent = $highlight['eclipse'];
$regressionEvent['datetime'] = '2026-08-28T01:00:00-03:00';
$regressionEvents = [$regressionEvent];
$eclipseInstant = new DateTimeImmutable($regressionEvent['datetime']);
foreach (['2026-08-27 18:00:00', '2026-08-27 23:30:00', '2026-08-28 00:30:00'] as $beforeInstant) {
    $before = homeTonightHighlightModel(null, [], $regressionEvents, new DateTimeImmutable($beforeInstant, new DateTimeZone($timezone)), $timezone, $location['latitude'], $location['longitude'], $location);
    homeTonightEclipseAssert($before['kind'] === 'lunar_eclipse', 'El eclipse futuro dejó de ser protagonista antes de su instante: ' . $beforeInstant);
}
foreach (['2026-08-28 01:00:00', '2026-08-28 01:30:00', '2026-08-28 09:00:00', '2026-08-28 18:00:00'] as $expiredReference) {
    $expiredNow = new DateTimeImmutable($expiredReference, new DateTimeZone($timezone));
    $after = homeTonightHighlightModel(null, [], $regressionEvents, $expiredNow, $timezone, $location['latitude'], $location['longitude'], $location);
    homeTonightEclipseAssert($after['kind'] === 'usual' && $after['eclipse'] === null, 'El eclipse siguió siendo candidato desde ' . $expiredReference . '.');
    homeTonightEclipseAssert(homeUpcomingVisibleEclipse($regressionEvents, $expiredNow, $timezone, 10) === null, 'El aviso de próximo eclipse conservó un evento vencido desde ' . $expiredReference . '.');
}
$noticeBefore = homeUpcomingVisibleEclipse($regressionEvents, new DateTimeImmutable('2026-08-28 00:30:00', new DateTimeZone($timezone)), $timezone, 10);
homeTonightEclipseAssert(is_array($noticeBefore) && ($noticeBefore['event']['datetime'] ?? '') === $regressionEvent['datetime'], 'El aviso descartó el eclipse todavía futuro de la madrugada inmediata.');
homeTonightEclipseAssert(
    homeTonightMergeEclipseText('Esta noche habrá un eclipse lunar parcial.', 'Deneb Algedi y la Luna podrán verse juntos. También estarán visibles Venus y Saturno.')
        === 'Esta noche habrá un eclipse lunar parcial. También Deneb Algedi y la Luna podrán verse juntos y estarán visibles Venus y Saturno.',
    'El eclipse eliminó o alteró el resumen habitual con encuentro y planetas.'
);
homeTonightEclipseAssert(
    homeTonightMergeEclipseText('Esta noche habrá un eclipse lunar parcial.', 'Esta noche estarán visibles Venus y Saturno.')
        === 'Esta noche habrá un eclipse lunar parcial. También estarán visibles Venus y Saturno.',
    'La combinación duplicó el prefijo Esta noche.'
);
ob_start();
$rendered = renderAstronomyEclipseWidget($highlight['eclipse'], $timezone, $location, 'Eclipse lunar visible esta noche', ['autoplay' => true, 'controls' => false]);
$widget = (string) ob_get_clean();
homeTonightEclipseAssert($rendered && str_contains($widget, 'eclipse-lunar-real.php'), 'No se montó el simulador lunar real.');
homeTonightEclipseAssert(str_contains($widget, 'autoplay=1') && str_contains($widget, 'controls=0'), 'El simulador protagonista no quedó animado y sin controles.');

$usual = homeTonightHighlightModel(null, [], [], $now, $timezone, $location['latitude'], $location['longitude'], $location);
homeTonightEclipseAssert($usual['kind'] === 'usual' && $usual['eclipse'] === null, 'Sin eclipse no se restauró la selección habitual.');

echo "Protagonista eclipse lunar de esta noche: OK\n";
