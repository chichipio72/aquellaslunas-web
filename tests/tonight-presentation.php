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
$defaultHighlights = astronomyTonightHighlights([
    'planets' => [['id' => 'venus'], ['id' => 'mars'], ['id' => 'saturn']],
    'stars' => [['id' => 'dim', 'magnitude' => 2], ['id' => 'bright', 'magnitude' => -1]],
    'moon' => [['id' => 'moon']],
]);
tonightAssert(array_column($defaultHighlights, 'id') === ['venus', 'mars', 'bright'], 'Los cupos y el orden predeterminados alteraron los destacados históricos.');
$sparseHighlights = astronomyTonightHighlights(['planets' => [['id' => 'venus']], 'moon' => [['id' => 'moon']]]);
tonightAssert(array_column($sparseHighlights, 'id') === ['venus', 'moon'], 'La Luna dejó de completar una selección nocturna escasa.');

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

$moonEditorialSections = [
    'planets' => [['id' => 'venus']],
    'moon' => [['id' => 'moon']],
];
$nightWithoutMoonEvent = astronomyTonightApplyMoonEditorialPriority($moonEditorialSections, false);
tonightAssert(!isset($nightWithoutMoonEvent['moon']), 'Una noche sin evento lunar conservó la Luna.');
tonightAssert(isset($nightWithoutMoonEvent['planets']), 'El filtro lunar eliminó los planetas principales.');

$conjunction = [[
    'type' => 'conjunction',
    'subtype' => 'jupiter',
    'datetime' => '2026-07-26T00:30:00-03:00',
]];
tonightAssert(
    astronomyTonightHasRelevantMoonEvent($temporalData, $conjunction, $timezone),
    'Una conjunción dentro de la noche no volvió protagonista a la Luna.'
);
$nightWithConjunction = astronomyTonightApplyMoonEditorialPriority($moonEditorialSections, true);
tonightAssert(isset($nightWithConjunction['moon']), 'La conjunción no conservó la Luna visible.');

$ordinaryPhase = [[
    'type' => 'moon_phase',
    'subtype' => 'first_quarter',
    'datetime' => '2026-07-25T22:00:00-03:00',
]];
tonightAssert(
    !astronomyTonightHasRelevantMoonEvent($temporalData, $ordinaryPhase, $timezone),
    'Un cuarto ordinario volvió protagonista a la Luna.'
);

$fullMoon = [[
    'type' => 'moon_phase',
    'subtype' => 'full_moon',
    'datetime' => '2026-07-25T12:00:00-03:00',
]];
tonightAssert(
    astronomyTonightHasRelevantMoonEvent($temporalData, $fullMoon, $timezone),
    'La Luna llena del día civil no fue considerada protagonista.'
);

$cardData = [
    'night' => [
        'start' => '2026-07-30T18:38:11-03:00',
        'end' => '2026-07-31T07:21:21-03:00',
    ],
    'planets' => [
        ['name' => 'Venus', 'visibility_status' => 'visible_later', 'visibility_start' => '2026-07-30T18:40:00-03:00', 'visibility_end' => '2026-07-30T21:00:00-03:00'],
        ['name' => 'Marte', 'visibility_status' => 'visible_later', 'visibility_start' => '2026-07-30T20:00:00-03:00', 'visibility_end' => '2026-07-31T02:00:00-03:00'],
    ],
];
$cardEvent = [[
    'type' => 'conjunction',
    'title' => 'Conjunción Luna–Júpiter',
    'datetime' => '2026-07-30T21:30:00-03:00',
    'details' => ['both_above_horizon' => true],
]];
tonightAssert(
    astronomyTonightCardText($cardData, $cardEvent, $duringNight, $timezone)
        === 'La Luna y Júpiter podrán verse juntos alrededor de las 21:30. También estarán visibles Venus y Marte.',
    'La tarjeta no compuso un encuentro autónomo y separó los otros planetas visibles.'
);

$incompleteCardEvent = $cardEvent;
unset($incompleteCardEvent[0]['title']);
tonightAssert(
    astronomyTonightCardText($cardData, $incompleteCardEvent, $duringNight, $timezone)
        === 'Esta noche estarán visibles Venus y Marte.',
    'Una conjunción incompleta produjo referencias sin objetos u hora.'
);

$venusEncounter = $cardEvent;
$venusEncounter[0]['title'] = 'Conjunción Luna–Venus';
tonightAssert(
    astronomyTonightCardText($cardData, $venusEncounter, $duringNight, $timezone)
        === 'La Luna y Venus podrán verse juntos alrededor de las 21:30. También estará visible Marte.',
    'El objeto de la conjunción se repitió entre los planetas meramente visibles.'
);
tonightAssert(
    astronomyTonightComparisonKey('Júpiter', false) === astronomyTonightComparisonKey('júpiter', false),
    'El fallback sin mbstring no conservó la comparación de nombres en español.'
);

$simulatedClockRegression = [
    'night' => [
        'start' => '2026-07-25T18:35:00-03:00',
        'end' => '2026-07-26T07:25:00-03:00',
    ],
    'planets' => [
        ['name' => 'Venus', 'visibility_status' => 'visible_earlier', 'visibility_start' => '2026-07-25T18:35:00-03:00', 'visibility_end' => '2026-07-25T20:50:00-03:00'],
        ['name' => 'Saturno', 'visibility_status' => 'visible_earlier', 'visibility_start' => '2026-07-26T00:35:00-03:00', 'visibility_end' => '2026-07-26T07:25:00-03:00'],
        ['name' => 'Marte', 'visibility_status' => 'visible_earlier', 'visibility_start' => '2026-07-26T04:00:00-03:00', 'visibility_end' => '2026-07-26T07:25:00-03:00'],
    ],
];
$simulatedNow = new DateTimeImmutable('2026-07-25T18:00:00-03:00');
$regressionPrepared = astronomyTonightPreparedSections($simulatedClockRegression, $simulatedNow, $timezone);
$regressionCard = astronomyTonightCardText($simulatedClockRegression, [], $simulatedNow, $timezone);
tonightAssert(array_column($regressionPrepared['planets'], 'name') === ['Venus', 'Saturno', 'Marte'],
    'La preparación detallada no conservó los planetas de la noche simulada.');
tonightAssert(is_string($regressionCard) && str_contains($regressionCard, 'Venus')
    && !str_contains($regressionCard, 'no habrá planetas'),
    'La tarjeta volvió a interpretar con el reloj real estados generados para una noche simulada.');

$encounterData = [
    'night' => $base['night'],
    'moon_encounters' => [
        ['id' => 'venus', 'name' => 'Venus', 'object_kind' => 'planet', 'minimum_separation_degrees' => 3.24, 'visibility_end' => '2026-07-25T22:00:00-03:00'],
        ['id' => 'mars', 'name' => 'Marte', 'object_kind' => 'planet', 'minimum_separation_degrees' => 4.8, 'visibility_end' => '2026-07-26T03:00:00-03:00'],
    ],
];
$formalVenus = [[
    'type' => 'conjunction', 'subtype' => 'venus',
    'datetime' => '2026-07-25T20:00:00-03:00',
    'details' => ['both_above_horizon' => true],
]];
$withoutDuplicate = astronomyTonightMoonEncounters($encounterData, $formalVenus, $duringNight, $timezone);
tonightAssert(array_column($withoutDuplicate, 'id') === ['mars'], 'Una conjunción formal se duplicó como objeto cerca de la Luna.');
tonightAssert(
    astronomyTonightMoonEncounterText($encounterData['moon_encounters'][0]) === 'Esta noche Venus y la Luna se verán separados por unos 3,2°.',
    'La separación observacional no se presentó con una cifra decimal.'
);
tonightAssert(
    astronomyTonightMoonEncounterTitle($encounterData['moon_encounters'][0]) === 'Venus cerca de la Luna',
    'El título del encuentro lunar no usó la configuración editorial.'
);
$pastConjunction = $formalVenus;
$pastConjunction[0]['datetime'] = '2026-07-25T15:00:00-03:00';
tonightAssert(count(astronomyTonightMoonEncounters($encounterData, $pastConjunction, $duringNight, $timezone)) === 2,
    'Una conjunción formal anterior a la noche ocultó el encuentro observacional.');
$futureConjunction = $formalVenus;
$futureConjunction[0]['datetime'] = '2026-07-26T10:00:00-03:00';
tonightAssert(count(astronomyTonightMoonEncounters($encounterData, $futureConjunction, $duringNight, $timezone)) === 2,
    'Una conjunción formal posterior a la noche ocultó el encuentro observacional.');
$notObservableFormal = $formalVenus;
$notObservableFormal[0]['details']['both_above_horizon'] = false;
tonightAssert(count(astronomyTonightMoonEncounters($encounterData, $notObservableFormal, $duringNight, $timezone)) === 2,
    'Una conjunción formal no observable ocultó el encuentro observacional.');
$endedEncounter = $encounterData;
$endedEncounter['moon_encounters'][0]['visibility_end'] = '2026-07-25T19:00:00-03:00';
tonightAssert(array_column(astronomyTonightMoonEncounters($endedEncounter, [], $duringNight, $timezone), 'id') === ['mars'],
    'Se mostró un encuentro que ya no era observable durante la noche en curso.');

$homeEncounterData = $cardData;
$homeEncounterData['moon_encounters'] = [[
    'id' => 'venus', 'name' => 'Venus', 'object_kind' => 'planet', 'minimum_separation_degrees' => 3.24,
    'visibility_end' => '2026-07-30T22:00:00-03:00',
]];
$homeEncounterText = astronomyTonightCardText(
    $homeEncounterData,
    [],
    new DateTimeImmutable('2026-07-30T18:00:00-03:00'),
    $timezone
);
tonightAssert(
    $homeEncounterText === 'Esta noche Venus y la Luna se verán separados por unos 3,2°. También estará visible Marte.',
    'La tarjeta de portada no mostró el encuentro observacional o repitió el planeta.'
);

$priorityData = $homeEncounterData;
$priorityData['moon_encounters'] = [
    ['id' => 'spica', 'name' => 'Spica', 'object_kind' => 'star', 'minimum_separation_degrees' => 1.2, 'visibility_end' => '2026-07-30T23:00:00-03:00'],
    ['id' => 'mars', 'name' => 'Marte', 'object_kind' => 'planet', 'minimum_separation_degrees' => 6.4, 'visibility_end' => '2026-07-31T03:00:00-03:00'],
    ['id' => 'venus', 'name' => 'Venus', 'object_kind' => 'planet', 'minimum_separation_degrees' => 3.2, 'visibility_end' => '2026-07-30T22:00:00-03:00'],
];
$prioritized = astronomyTonightPrioritizedMoonEncounters($priorityData, [], new DateTimeImmutable('2026-07-30T18:00:00-03:00'), $timezone);
tonightAssert(array_column($prioritized, 'name') === ['Venus', 'Marte'], 'Los planetas no tuvieron prioridad sobre una estrella más cercana.');
tonightAssert(
    astronomyTonightMoonEncountersText($prioritized) === 'Esta noche la Luna estará cerca de Venus (3,2°) y de Marte (6,4°).',
    'Dos planetas cercanos no usaron el texto editorial especial.'
);
$starsOnly = $priorityData;
$starsOnly['moon_encounters'] = [
    ['id' => 'regulus', 'name' => 'Régulo', 'object_kind' => 'star', 'minimum_separation_degrees' => 7.0, 'visibility_end' => '2026-07-31T03:00:00-03:00'],
    ['id' => 'spica', 'name' => 'Spica', 'object_kind' => 'star', 'minimum_separation_degrees' => 2.0, 'visibility_end' => '2026-07-31T03:00:00-03:00'],
];
tonightAssert(
    array_column(astronomyTonightPrioritizedMoonEncounters($starsOnly, [], new DateTimeImmutable('2026-07-30T18:00:00-03:00'), $timezone), 'name') === ['Spica'],
    'Sin planetas no se eligió la estrella más cercana.'
);
$homeFormalText = astronomyTonightCardText(
    $homeEncounterData,
    [[
        'type' => 'conjunction', 'subtype' => 'venus', 'title' => 'Conjunción Luna–Venus',
        'datetime' => '2026-07-30T21:30:00-03:00', 'details' => ['both_above_horizon' => true],
    ]],
    new DateTimeImmutable('2026-07-30T18:00:00-03:00'),
    $timezone
);
tonightAssert(
    $homeFormalText === 'La Luna y Venus podrán verse juntos alrededor de las 21:30. También estará visible Marte.',
    'La tarjeta de portada no dio prioridad a la conjunción formal.'
);

echo "Presentación de esta noche: escenarios completos e incompletos OK\n";
