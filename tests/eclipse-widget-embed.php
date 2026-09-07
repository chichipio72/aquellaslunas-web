<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/eclipse-widget-embed.php';
require_once __DIR__ . '/../includes/home-eclipse-notice.php';

function eclipseWidgetAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$location = ['latitude' => -34.6037, 'longitude' => -58.3816, 'elevation_meters' => 25.5];
$url = astronomyEclipseWidgetContextualizeUrl(
    'https://aquellaslunas.com.ar/astro/embeds/eclipse-solar-real.php?date=2000-01-01&lat=1&lon=2&elevation=3&controls=1&sun_color=%23ffeecc&future_option=kept',
    'solar',
    '2027-02-06',
    $location
);
eclipseWidgetAssert($url !== null, 'No se construyó la URL contextual válida.');
parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);
eclipseWidgetAssert(($query['date'] ?? '') === '2027-02-06', 'No se reemplazó la fecha.');
eclipseWidgetAssert((float) ($query['lat'] ?? 0) === -34.6037 && (float) ($query['lon'] ?? 0) === -58.3816, 'No se reemplazaron las coordenadas.');
eclipseWidgetAssert((float) ($query['elevation'] ?? 0) === 25.5, 'No se reemplazó la elevación.');
eclipseWidgetAssert(($query['controls'] ?? '') === '1' && ($query['sun_color'] ?? '') === '#ffeecc' && ($query['future_option'] ?? '') === 'kept', 'Se alteraron parámetros no contextuales.');
eclipseWidgetAssert(astronomyEclipseWidgetContextualizeUrl(str_replace('solar', 'lunar', (string) $url), 'solar', '2027-02-06', $location) === null, 'Se aceptó el widget incorrecto.');
eclipseWidgetAssert(astronomyEclipseWidgetContextualizeUrl('https://example.com/astro/embeds/eclipse-solar-real.php', 'solar', '2027-02-06', $location) === null, 'Se aceptó un host externo.');

function renderedEclipseIframeSrc(array $event, array $observer): string
{
    ob_start();
    $rendered = renderAstronomyEclipseWidget($event, 'UTC', $observer, 'Eclipse de prueba');
    $html = (string) ob_get_clean();
    eclipseWidgetAssert($rendered, 'No se pudo renderizar el iframe final.');
    eclipseWidgetAssert(preg_match('/<iframe\s+src="([^"]+)"/', $html, $match) === 1, 'No se encontró el src final del iframe.');
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$differentEclipse = [
    'type' => 'eclipse',
    'subtype' => 'solar_eclipse',
    'datetime' => '2027-02-06T15:00:00Z',
];
$observerA = ['latitude' => -34.6037, 'longitude' => -58.3816, 'elevation_meters' => 25.5];
$observerB = ['latitude' => 35.6762, 'longitude' => 139.6503, 'elevation_meters' => 44.2];
$srcA = renderedEclipseIframeSrc($differentEclipse, $observerA);
$srcB = renderedEclipseIframeSrc($differentEclipse, $observerB);
eclipseWidgetAssert(str_starts_with($srcA, '/embeds/eclipse-solar-real.php?') && str_starts_with($srcB, '/embeds/eclipse-solar-real.php?'), 'El detalle público no usa el widget del mismo entorno.');
parse_str((string) parse_url($srcA, PHP_URL_QUERY), $srcQueryA);
parse_str((string) parse_url($srcB, PHP_URL_QUERY), $srcQueryB);
foreach ([[$srcQueryA, $observerA], [$srcQueryB, $observerB]] as [$srcQuery, $observer]) {
    eclipseWidgetAssert(($srcQuery['date'] ?? '') === '2027-02-06', 'Sobrevivió al iframe la fecha de ejemplo de la plantilla.');
    eclipseWidgetAssert((float) ($srcQuery['lat'] ?? 0) === $observer['latitude'], 'Sobrevivió al iframe la latitud de ejemplo de la plantilla.');
    eclipseWidgetAssert((float) ($srcQuery['lon'] ?? 0) === $observer['longitude'], 'Sobrevivió al iframe la longitud de ejemplo de la plantilla.');
    eclipseWidgetAssert((float) ($srcQuery['elevation'] ?? 0) === $observer['elevation_meters'], 'Sobrevivió al iframe la elevación de ejemplo de la plantilla.');
}
$visualQueryA = $srcQueryA;
$visualQueryB = $srcQueryB;
foreach (['date', 'lat', 'lon', 'elevation'] as $contextKey) {
    unset($visualQueryA[$contextKey], $visualQueryB[$contextKey]);
}
eclipseWidgetAssert($visualQueryA !== [], 'El iframe perdió todos los parámetros visuales y de comportamiento.');
eclipseWidgetAssert($visualQueryA === $visualQueryB, 'Los parámetros no contextuales cambiaron entre observadores.');
eclipseWidgetAssert($srcA !== $srcB, 'Dos ubicaciones diferentes produjeron el mismo src final.');

$differentLunarEclipse = [
    'type' => 'eclipse',
    'subtype' => 'lunar_eclipse',
    'datetime' => '2026-08-28T04:14:11Z',
];
$lunarSrcA = renderedEclipseIframeSrc($differentLunarEclipse, $observerA);
$lunarSrcB = renderedEclipseIframeSrc($differentLunarEclipse, $observerB);
eclipseWidgetAssert(str_starts_with($lunarSrcA, '/embeds/eclipse-lunar-real.php?') && str_starts_with($lunarSrcB, '/embeds/eclipse-lunar-real.php?'), 'El detalle lunar no usa el widget del mismo entorno.');
parse_str((string) parse_url($lunarSrcA, PHP_URL_QUERY), $lunarQueryA);
parse_str((string) parse_url($lunarSrcB, PHP_URL_QUERY), $lunarQueryB);
foreach ([[$lunarQueryA, $observerA], [$lunarQueryB, $observerB]] as [$srcQuery, $observer]) {
    eclipseWidgetAssert(($srcQuery['date'] ?? '') === '2026-08-28', 'Sobrevivió al iframe lunar la fecha de ejemplo de la plantilla.');
    eclipseWidgetAssert((float) ($srcQuery['lat'] ?? 0) === $observer['latitude'], 'Sobrevivió al iframe lunar la latitud de ejemplo.');
    eclipseWidgetAssert((float) ($srcQuery['lon'] ?? 0) === $observer['longitude'], 'Sobrevivió al iframe lunar la longitud de ejemplo.');
    eclipseWidgetAssert((float) ($srcQuery['elevation'] ?? 0) === $observer['elevation_meters'], 'Sobrevivió al iframe lunar la elevación de ejemplo.');
}
foreach (['date', 'lat', 'lon', 'elevation'] as $contextKey) {
    unset($lunarQueryA[$contextKey], $lunarQueryB[$contextKey]);
}
eclipseWidgetAssert($lunarQueryA !== [] && $lunarQueryA === $lunarQueryB, 'El preset visual lunar no se conservó idéntico entre ubicaciones.');
eclipseWidgetAssert($lunarSrcA !== $lunarSrcB, 'El iframe lunar no cambió entre dos ubicaciones diferentes.');

$visible = ['type' => 'eclipse', 'subtype' => 'solar_eclipse', 'datetime' => '2027-02-06T15:00:00Z', 'details' => ['solar_eclipse_global' => ['global_type' => 'annular'], 'solar_eclipse_local' => ['visibility_classification' => 'visible_partial']]];
$hidden = $visible;
$hidden['datetime'] = '2027-02-05T15:00:00Z';
$hidden['details']['solar_eclipse_local']['visibility_classification'] = 'not_visible';
$now = new DateTimeImmutable('2027-02-01T12:00:00Z');
$selected = homeUpcomingVisibleEclipse([$hidden, $visible], $now, 'UTC', 10);
eclipseWidgetAssert(($selected['event']['datetime'] ?? '') === $visible['datetime'], 'El aviso eligió un eclipse no visible.');
eclipseWidgetAssert(homeEclipseNoticeText($visible, $selected['date'], $now) === 'En 5 días vamos a poder ver un eclipse parcial de Sol.', 'El texto del aviso no refleja plazo y tipo local.');
eclipseWidgetAssert(isset(astronomyEditorialDefinitions()['texts']['home.eclipse.notice.future']), 'El texto futuro del aviso no está disponible en reglas y mensajes.');

$noticeTimezone = new DateTimeZone('America/Argentina/Buenos_Aires');
$noticeNow = new DateTimeImmutable('2026-08-27 12:00:00', $noticeTimezone);
$lunarNotice = ['type' => 'eclipse', 'subtype' => 'lunar_eclipse', 'details' => ['eclipse_global' => ['global_type' => 'total']]];
eclipseWidgetAssert(
    str_starts_with(homeEclipseNoticeText($lunarNotice, new DateTimeImmutable('2026-08-28 01:00:00', $noticeTimezone), $noticeNow), 'Esta noche'),
    'La madrugada inmediata siguió anunciándose como mañana.'
);
eclipseWidgetAssert(
    str_starts_with(homeEclipseNoticeText($lunarNotice, new DateTimeImmutable('2026-08-28 09:00:00', $noticeTimezone), $noticeNow), 'Mañana'),
    'Un evento posterior al corte dejó de anunciarse como mañana.'
);

echo "eclipse-widget-embed: ok\n";
