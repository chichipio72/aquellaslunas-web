<?php

require_once __DIR__ . '/../includes/tonight.php';

function tonightAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$timezone = 'America/Argentina/Buenos_Aires';
$base = [
    'night' => [
        'start' => '2026-07-25T18:35:00-03:00',
        'end' => '2026-07-26T07:25:00-03:00',
        'polar_state' => 'normal',
    ],
    'planets' => [
        [
            'name' => 'Venus',
            'visibility_status' => 'visible_now',
            'visibility_end' => '2026-07-25T20:50:00-03:00',
            'direction' => 'oeste',
            'observation_aid' => 'naked_eye',
        ],
        [
            'name' => 'Saturno',
            'visibility_status' => 'visible_later',
            'visibility_start' => '2026-07-26T00:35:00-03:00',
            'observation_aid' => 'naked_eye',
        ],
        [
            'name' => 'Mercurio',
            'visibility_status' => 'visible_earlier',
            'visibility_end' => '2026-07-25T19:10:00-03:00',
            'observation_aid' => 'naked_eye',
        ],
        ['name' => 'Marte', 'visibility_status' => 'not_visible_tonight'],
    ],
];

$duringNight = new DateTimeImmutable('2026-07-25T19:30:00-03:00');
$summary = astronomyTonightSummaryPresentation($base, $duringNight, $timezone);
tonightAssert($summary['title'] === 'Esta noche se verán 2 planetas', 'Falló el plural con varios planetas.');
tonightAssert(str_contains($summary['text'], 'Venus está visible ahora hacia el oeste, hasta las 20:50.'), 'Falló visible_now.');
tonightAssert(str_contains($summary['text'], 'Saturno aparecerá desde las 00:35.'), 'Falló visible_later después de medianoche.');
tonightAssert(!str_contains($summary['text'], 'Mercurio'), 'Se mostró un planeta cuya ventana ya terminó.');
tonightAssert(!str_contains($summary['text'], 'fue visible'), 'Se generó una frase histórica.');
tonightAssert(!str_contains($summary['text'], 'Marte'), 'Se mostró not_visible_tonight.');

$beforeNight = astronomyTonightSummaryPresentation(
    $base,
    new DateTimeImmutable('2026-07-25T12:00:00-03:00'),
    $timezone
);
tonightAssert(count($beforeNight['planets']) === 2, 'visible_earlier se mostró antes de comenzar la noche.');

$onePlanet = $base;
$onePlanet['planets'] = [$base['planets'][1]];
$oneSummary = astronomyTonightSummaryPresentation($onePlanet, $duringNight, $timezone);
tonightAssert($oneSummary['title'] === 'Esta noche se verá 1 planeta', 'Falló el singular.');

$none = $base;
$none['planets'] = [$base['planets'][3]];
$noneSummary = astronomyTonightSummaryPresentation($none, $duringNight, $timezone);
tonightAssert(
    $noneSummary['text'] === 'Esta noche no habrá planetas visibles a simple vista desde tu ubicación.',
    'Falló el estado sin planetas.'
);

$above = [
    'id' => 'moon',
    'object_kind' => 'moon',
    'name' => 'Luna',
    'visibility_status' => 'visible_now',
    'visibility_end' => '2026-07-25T23:40:00-03:00',
    'direction' => 'arriba',
    'near_moon' => true,
    'moon_proximity' => 'very_close',
    'observation_aid' => 'binoculars',
];
tonightAssert(
    astronomyTonightObjectSentence($above, $timezone)
        === 'Visible ahora, preferentemente con binoculares, arriba, hasta las 23:40.',
    'Falló la dirección arriba.'
);
tonightAssert(astronomyTonightMoonProximity($above) === null, 'La Luna mostró cercanía consigo misma.');
tonightAssert(astronomyTonightObservationAid('binoculars') === 'Mejor con binoculares', 'Falló ayuda de observación.');

$nearMoonStar = [
    'id' => 'sirius',
    'object_kind' => 'star',
    'near_moon' => true,
    'moon_proximity' => 'very_close',
];
tonightAssert(
    astronomyTonightMoonProximity($nearMoonStar) === 'Se verá muy cerca de la Luna.',
    'Falló very_close.'
);
$nearMoonStar['moon_proximity'] = 'close';
tonightAssert(
    astronomyTonightMoonProximity($nearMoonStar) === 'Se verá cerca de la Luna.',
    'Falló close.'
);
$nearMoonStar['moon_proximity'] = null;
tonightAssert(
    astronomyTonightMoonProximity($nearMoonStar) === null,
    'Una proximidad incompleta produjo texto inventado.'
);

foreach ([
    ['name' => 'Sirio', 'constellation' => ['id' => 'can_mayor', 'name' => 'Can Mayor', 'iau_abbreviation' => 'CMa']],
    ['name' => 'Marte', 'constellation' => ['id' => 'tauro', 'name' => 'Tauro', 'iau_abbreviation' => 'Tau']],
    ['name' => 'Luna', 'constellation' => ['id' => 'geminis', 'name' => 'Géminis', 'iau_abbreviation' => 'Gem']],
] as $objectWithConstellation) {
    tonightAssert(
        astronomyTonightConstellationName($objectWithConstellation)
            === $objectWithConstellation['constellation']['name'],
        'No se presentó la constelación de ' . $objectWithConstellation['name'] . '.'
    );
}
tonightAssert(
    astronomyTonightConstellationName(['name' => 'Objeto anterior']) === null,
    'Un objeto sin constelación recibió texto de reemplazo.'
);

$laterWithoutEnd = [
    'visibility_status' => 'visible_later',
    'visibility_start' => '2026-07-26T05:56:00-03:00',
];
tonightAssert(
    astronomyTonightObjectSentence($laterWithoutEnd, $timezone)
        === 'Será visible desde las 05:56 hasta el amanecer.',
    'Falló la ventana que atraviesa medianoche.'
);

$laterWithoutEnd['observation_aid'] = 'naked_eye';
tonightAssert(
    astronomyTonightObjectSentence($laterWithoutEnd, $timezone)
        === 'Será visible a simple vista desde las 05:56 hasta el amanecer.',
    'No se integró la observación a simple vista en la visibilidad.'
);
$laterWithoutEnd['observation_aid'] = 'binoculars';
tonightAssert(
    astronomyTonightObjectSentence($laterWithoutEnd, $timezone)
        === 'Será visible, preferentemente con binoculares, desde las 05:56 hasta el amanecer.',
    'No se integró la recomendación de binoculares en la visibilidad.'
);

$incomplete = ['night' => [], 'planets' => [['name' => 'Venus']]];
tonightAssert(
    astronomyTonightSummaryPresentation($incomplete, $duringNight, $timezone)['planets'] === [],
    'Una respuesta incompleta produjo un planeta visible.'
);
tonightAssert(
    astronomyTonightSections($incomplete) === [],
    'Una respuesta antigua dejó secciones vacías.'
);

$orderedStars = [
    ['name' => 'Sirio', 'visibility_status' => 'visible_now'],
    ['name' => 'Vega', 'visibility_status' => 'visible_later'],
    ['name' => 'Rigel', 'visibility_status' => 'not_visible_tonight'],
];
$full = [
    'planets' => [],
    'moon' => null,
    'stars' => $orderedStars,
    'deep_sky_objects' => [],
];
$fullSections = astronomyTonightSections($full);
tonightAssert(array_keys($fullSections) === ['Estrellas'], 'No se ocultaron las secciones vacías.');
tonightAssert(
    array_column($fullSections['Estrellas'], 'name') === ['Sirio', 'Vega'],
    'Se alteró el orden recibido o se mostró una estrella no observable.'
);

$temporalData = [
    'night' => [
        'start' => '2026-07-25T18:35:00-03:00',
        'end' => '2026-07-26T07:25:00-03:00',
    ],
    'planets' => [
        ['id' => 'ended', 'name' => 'Terminó', 'visibility_start' => '2026-07-25T18:40:00-03:00', 'visibility_end' => '2026-07-25T19:00:00-03:00'],
        ['id' => 'current', 'name' => 'Actual', 'visibility_start' => '2026-07-25T19:00:00-03:00', 'visibility_end' => '2026-07-26T02:00:00-03:00'],
        ['id' => 'later', 'name' => 'Posterior', 'visibility_start' => '2026-07-26T03:00:00-03:00', 'visibility_end' => '2026-07-26T07:25:00-03:00'],
    ],
];
$prepared = astronomyTonightPreparedSections(
    $temporalData,
    new DateTimeImmutable('2026-07-25T23:00:00-03:00'),
    $timezone
);
tonightAssert(
    array_column($prepared['planets'], 'id') === ['current', 'later'],
    'No se filtró una ventana terminada o se alteró el próximo orden útil.'
);
tonightAssert($prepared['planets'][0]['_effective_status'] === 'visible_now', 'No se recalculó visible_now.');
tonightAssert($prepared['planets'][1]['_effective_status'] === 'visible_later', 'No se recalculó visible_later.');
tonightAssert(
    !str_contains(astronomyTonightNaturalSentence($prepared['planets'][0], $temporalData, new DateTimeImmutable('2026-07-25T23:00:00-03:00'), $timezone), 'Fue'),
    'La redacción local volvió a producir pasado.'
);

echo "Presentación de esta noche: escenarios completos e incompletos OK\n";
