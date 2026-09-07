<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/event-infographic.php';

function eventInfographicAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$location = [
    'name' => 'Buenos Aires',
    'latitude' => -34.53,
    'longitude' => -58.48,
    'timezone' => 'America/Argentina/Buenos_Aires',
];
$visible = [
    'type' => 'conjunction',
    'subtype' => 'venus',
    'datetime' => '2026-09-15T00:10:00+00:00',
    'title' => 'Conjunción Luna–Venus',
    'details' => [
        'planet' => 'venus',
        'object_kind' => 'planet',
        'visibility_classification' => 'visible_nearby',
        'best_visible_time' => '2026-09-14T20:40:00-03:00',
        'visual_moon_altitude_degrees' => 22.0,
        'visual_moon_azimuth_degrees' => 40.5,
        'visual_relative_x_degrees' => -1.14,
        'visual_relative_y_degrees' => -2.37,
        'illumination_percent' => 24.2,
        'separation_degrees' => 1.2,
        'moon_altitude_degrees' => 18.0,
        'target_altitude_degrees' => 17.5,
        'solar_elongation_degrees' => 30.0,
    ],
];

eventInfographicAssert(astronomyEventInfographicIsEligible($visible), 'Una conjunción planetaria visible no fue elegible.');
$model = astronomyConjunctionInfographicModel($visible, $location);
eventInfographicAssert(is_array($model), 'No se construyó el modelo de infografía.');
eventInfographicAssert($model['recommended_time'] === 'Mirá alrededor de las 20:40', 'No se priorizó best_visible_time.');
eventInfographicAssert($model['visual']['direction_x'] < 0 && $model['visual']['direction_y'] < 0, 'El modelo perdió la dirección real del planeta respecto de la Luna.');
eventInfographicAssert(str_contains($model['observation_guide'], 'baja') && str_contains($model['observation_guide'], 'noreste'), 'No se aplicó la guía editorial de altura y punto cardinal.');
eventInfographicAssert(astronomyEventInfographicHasUsableLocation($location), 'Una ubicación válida no fue reconocida como utilizable.');
$unconfirmedLocation = $location;
$unconfirmedLocation['confirmed'] = false;
eventInfographicAssert(astronomyConjunctionInfographicModel($visible, $unconfirmedLocation) !== null, 'El modelo bloqueó una ubicación utilizable no confirmada.');
$incompleteLocation = $location;
unset($incompleteLocation['timezone']);
eventInfographicAssert(!astronomyEventInfographicHasUsableLocation($incompleteLocation), 'Una ubicación incompleta fue aceptada como utilizable.');
eventInfographicAssert($model['time_context'] === 'Después del atardecer', 'El momento cotidiano no corresponde.');
eventInfographicAssert($model['date'] === '14 de septiembre de 2026', 'La fecha local no se presentó correctamente.');
eventInfographicAssert($model['date_iso'] === '2026-09-14', 'El modelo no expone una fecha canónica para nombrar el PNG.');
eventInfographicAssert($model['city'] === 'Buenos Aires', 'Falta la ciudad activa.');
$serialized = json_encode($model, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
foreach (['azimut', 'altura', 'grados', 'separation_degrees', 'coordinates', 'timezone', 'magnitud'] as $forbidden) {
    eventInfographicAssert(stripos($serialized, $forbidden) === false, 'El modelo público expuso un dato técnico: ' . $forbidden);
}

$notObservable = $visible;
$notObservable['details']['visibility_classification'] = 'not_observable';
eventInfographicAssert(!astronomyEventInfographicIsEligible($notObservable), 'Se habilitó una conjunción no observable.');
eventInfographicAssert(astronomyConjunctionInfographicModel($notObservable, $location) === null, 'Se generó un modelo ambiguo no observable.');

$star = $visible;
$star['subtype'] = 'spica';
$star['details']['planet'] = 'spica';
$star['details']['object_kind'] = 'star';
eventInfographicAssert(!astronomyEventInfographicIsEligible($star), 'Se habilitó una conjunción con una estrella.');

$nearbyWithoutMoment = $visible;
unset($nearbyWithoutMoment['details']['best_visible_time']);
eventInfographicAssert(!astronomyEventInfographicIsEligible($nearbyWithoutMoment), 'Se habilitó visible_nearby sin un momento observable útil.');
eventInfographicAssert(astronomyEventInfographicRecommendedMoment($nearbyWithoutMoment, $location['timezone']) === null, 'visible_nearby cayó incorrectamente al mínimo invisible.');

$visibleAtMinimum = $visible;
$visibleAtMinimum['details']['visibility_classification'] = 'visible_at_closest_approach';
$visibleAtMinimum['details']['best_visible_time'] = '2026-09-14T19:10:00-03:00';
eventInfographicAssert(astronomyEventInfographicIsEligible($visibleAtMinimum), 'Se bloqueó un mínimo visible.');
eventInfographicAssert(astronomyEventInfographicRecommendedMoment($visibleAtMinimum, $location['timezone'])?->format(DateTimeInterface::ATOM) === '2026-09-14T21:10:00-03:00', 'El mínimo visible no conservó el datetime canónico del evento.');

$found = astronomyEventInfographicFind([$visible], '2026-09-14', 'venus', $location['timezone']);
eventInfographicAssert($found === $visible, 'La resolución por fecha local y planeta falló.');
$url = astronomyEventInfographicUrl($visible, $location['timezone']);
eventInfographicAssert($url === 'infografia-evento.php?type=lunar_conjunction&date=2026-09-14&target=venus', 'La URL no conserva la fecha local y el target canónico.');
$query = [];
parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
$request = astronomyEventInfographicRequest($query, $location['timezone']);
eventInfographicAssert(is_array($request), 'La página receptora rechazó la consulta creada por el enlace.');
eventInfographicAssert($request['type'] === 'lunar_conjunction' && $request['date'] === '2026-09-14' && $request['target'] === 'venus', 'El ida y vuelta del enlace alteró sus identificadores.');
eventInfographicAssert(astronomyEventInfographicFind([$visible], $request['date'], $request['target'], $location['timezone']) === $visible, 'La consulta generada no volvió a resolver el evento.');

$legacyRequest = astronomyEventInfographicRequest([
    'type' => 'lunar_conjunction', 'date' => '2026-09-14', 'planet' => 'venus',
], $location['timezone']);
eventInfographicAssert(is_array($legacyRequest) && $legacyRequest['target'] === 'venus', 'Se rompió un enlace anterior que usa planet.');
eventInfographicAssert(astronomyEventInfographicRequest(['type' => 'conjunction', 'date' => '2026-09-14', 'target' => 'venus'], $location['timezone']) === null, 'Se aceptó un type no público del generador.');

$legacyApi = $visible;
unset($legacyApi['details']['object_kind']);
eventInfographicAssert(astronomyEventInfographicIsEligible($legacyApi), 'El contrato API anterior no reconoció un ID planetario permitido.');

// Caso real devuelto por la API para Buenos Aires y visible cerca del máximo.
$realMars = [
    'datetime' => '2026-10-05T03:11:35-03:00',
    'type' => 'conjunction',
    'subtype' => 'mars',
    'title' => 'Conjunción Luna–Marte',
    'details' => [
        'illumination_percent' => 31.2,
        'planet' => 'mars',
        'visibility_classification' => 'visible_nearby',
        'best_visible_time' => '2026-10-05T05:51:35-03:00',
    ],
];
eventInfographicAssert(astronomyEventInfographicIsEligible($realMars), 'La conjunción real Luna–Marte no habilitó el botón.');
$realUrl = astronomyEventInfographicUrl($realMars, $location['timezone']);
parse_str((string) parse_url((string) $realUrl, PHP_URL_QUERY), $realQuery);
$realRequest = astronomyEventInfographicRequest($realQuery, $location['timezone']);
eventInfographicAssert(is_array($realRequest) && astronomyEventInfographicFind([$realMars], $realRequest['date'], $realRequest['target'], $location['timezone']) === $realMars, 'El enlace de la conjunción real Luna–Marte no llegó al mismo evento.');
$realModel = astronomyConjunctionInfographicModel($realMars, $location);
eventInfographicAssert(is_array($realModel) && $realModel['recommended_time'] === 'Mirá alrededor de las 05:51', 'La conjunción real no llegó al modelo con su momento visible.');
eventInfographicAssert($realModel['visual']['moment'] === '2026-10-05T05:51:35-03:00', 'El visual no quedó anclado a best_visible_time.');

$pageSource = file_get_contents(__DIR__ . '/../infografia-evento.php');
$rendererSource = file_get_contents(__DIR__ . '/../assets/js/event-infographic-renderer.js');
eventInfographicAssert(is_string($pageSource) && str_contains($pageSource, 'moonThreeRenderPayload(') && str_contains($pageSource, "model['visual']['moment']"), 'La página no genera la Luna Three.js en el instante visual efectivo.');
eventInfographicAssert(is_string($rendererSource) && str_contains($rendererSource, 'drawImage(moonCanvas') && !str_contains($rendererSource, 'function drawMoon('), 'La infografía no compone exclusivamente el canvas lunar realista.');
eventInfographicAssert(str_contains((string) $rendererSource, 'drawConjunctionStoryRealistic') && str_contains((string) $rendererSource, "model.variant === 'c'"), 'No están disponibles las variantes realistas de Historia para conjunciones.');
eventInfographicAssert(str_contains((string) $pageSource, 'data-infographic-share') && str_contains((string) $rendererSource, 'navigator.canShare') && str_contains((string) $rendererSource, 'navigator.share({ files: [file]'), 'No se integró compartir archivos mediante Web Share API.');
eventInfographicAssert(str_contains((string) $rendererSource, 'const pngCache = new Map()'), 'Descarga y compartir no reutilizan el PNG generado.');

$lunarEclipse = [
    'type' => 'eclipse', 'subtype' => 'lunar_eclipse',
    'datetime' => '2026-03-03T11:35:41+00:00',
    'details' => [
        'eclipse_global' => ['global_type' => 'total'],
        'eclipse_local' => [
            'visibility_classification' => 'visible_partial',
            'first_visible_instant' => '2026-03-03T05:46:25-04:00',
            'last_visible_instant' => '2026-03-03T06:08:03-04:00',
        ],
    ],
];
$eclipseLocation = ['name' => 'Georgetown', 'latitude' => 6.8013, 'longitude' => -58.1551, 'timezone' => 'America/Guyana'];
eventInfographicAssert(astronomyEventInfographicIsEligible($lunarEclipse), 'El eclipse lunar parcialmente visible no fue elegible.');
$eclipseModel = astronomyEclipseInfographicModel($lunarEclipse, $eclipseLocation);
eventInfographicAssert(is_array($eclipseModel), 'No se construyó el modelo del eclipse lunar.');
eventInfographicAssert($eclipseModel['visibility'] === 'Visible parcialmente', 'La visibilidad lunar no usa lenguaje editorial simple.');
eventInfographicAssert($eclipseModel['visual']['moment'] === '2026-03-03T06:08:03-04:00', 'El visual no se acotó al último instante visible cuando el máximo era invisible.');
eventInfographicAssert($eclipseModel['moments'][0]['label'] === 'Inicio' && $eclipseModel['moments'][2]['label'] === 'Fin', 'Los horarios públicos exponen contactos técnicos.');
$eclipseUrl = astronomyEventInfographicUrl($lunarEclipse, $eclipseLocation['timezone']);
eventInfographicAssert($eclipseUrl === 'infografia-evento.php?type=lunar_eclipse&date=2026-03-03', 'La URL lunar no es canónica.');
$eclipseQuery = [];
parse_str((string) parse_url($eclipseUrl, PHP_URL_QUERY), $eclipseQuery);
eventInfographicAssert(astronomyEventInfographicRequest($eclipseQuery, $eclipseLocation['timezone'])['type'] === 'lunar_eclipse', 'El receptor rechazó el eclipse lunar.');
eventInfographicAssert(astronomyEclipseInfographicFind([$lunarEclipse], '2026-03-03', 'lunar_eclipse', $eclipseLocation['timezone']) === $lunarEclipse, 'No se volvió a resolver el eclipse lunar.');

$solarEclipse = $lunarEclipse;
$solarEclipse['subtype'] = 'solar_eclipse';
$solarEclipse['datetime'] = '2027-02-06T15:59:55+00:00';
$solarEclipse['details'] = [
    'solar_eclipse_global' => ['global_type' => 'annular'],
    'solar_eclipse_local' => [
        'visibility_classification' => 'partial',
        'first_visible_instant' => '2027-02-06T10:48:35-03:00',
        'last_visible_instant' => '2027-02-06T14:17:53-03:00',
    ],
];
$solarModel = astronomyEclipseInfographicModel($solarEclipse, $location);
eventInfographicAssert(is_array($solarModel) && $solarModel['visual']['kind'] === 'solar', 'No se construyó el modelo del eclipse solar visible.');
eventInfographicAssert($solarModel['moments'][1]['time'] === '12:59', 'El máximo solar editorial no coincide con el instante visual.');

$notVisibleEclipse = $solarEclipse;
$notVisibleEclipse['details']['solar_eclipse_local'] = ['visibility_classification' => 'not_visible'];
eventInfographicAssert(!astronomyEventInfographicIsEligible($notVisibleEclipse), 'Se habilitó un eclipse no visible.');
eventInfographicAssert(astronomyEventInfographicUrl($notVisibleEclipse, $location['timezone']) === null, 'El eclipse no visible recibió enlace de infografía.');
eventInfographicAssert(astronomyEclipseInfographicModel($notVisibleEclipse, $location) === null, 'Se generó una pieza para un eclipse no visible.');

foreach (['azimut', 'altura', 'magnitud', 'P1', 'U1', 'C1', 'coordinates', 'timezone'] as $forbidden) {
    eventInfographicAssert(stripos(json_encode($eclipseModel, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $forbidden) === false, 'El modelo de eclipse expuso un dato técnico: ' . $forbidden);
}
eventInfographicAssert(str_contains((string) $pageSource, 'data-infographic-eclipse-source'), 'La página no monta el simulador de Eclipses como fuente visual.');
eventInfographicAssert(str_contains((string) $rendererSource, 'drawEclipseImage(context, eclipseImages.get(role)'), 'Canvas 2D no compone las capturas del simulador existente.');
eventInfographicAssert(str_contains((string) $rendererSource, "model.variant === 'a'") && str_contains((string) $rendererSource, "model.variant === 'b'") && str_contains((string) $rendererSource, "model.variant === 'c'"), 'No están disponibles las tres variantes de Historia.');

echo "OK event infographic\n";
