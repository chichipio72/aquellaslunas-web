<?php

require_once __DIR__ . '/../includes/event-presentation.php';

function eventPresentationLibrationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$timezone = 'America/Argentina/Buenos_Aires';
$subtypeCases = [
    'libration_east' => ['este', 'Libración favorable hacia el este'],
    'libration_west' => ['oeste', 'Libración favorable hacia el oeste'],
    'libration_north' => ['norte', 'Libración favorable hacia el norte'],
    'libration_south' => ['sur', 'Libración favorable hacia el sur'],
];

foreach ($subtypeCases as $subtype => [$direction, $expectedTitle]) {
    $event = [
        'datetime' => '2026-01-07T11:15:45+00:00',
        'type' => 'libration',
        'subtype' => $subtype,
        'details' => [
            'absolute_value_degrees' => 6.999196,
            'moon_phase' => [
                'name' => 'waning_gibbous',
                'illumination_percent' => 83.0,
                'waxing' => false,
            ],
        ],
    ];
    $presentation = astronomyEventPresentation($event, $timezone);

    eventPresentationLibrationAssert($presentation['title'] === $expectedTitle, 'Título incorrecto para ' . $subtype . '.');
    eventPresentationLibrationAssert($presentation['show_time'] === true, 'show_time debe permanecer activo en libraciones.');
    eventPresentationLibrationAssert($presentation['time_label'] === '08:15', 'Hora local incorrecta para ' . $subtype . '.');
    eventPresentationLibrationAssert(str_contains($presentation['summary'], 'borde ' . $direction), 'No se informó el borde favorecido para ' . $subtype . '.');
    eventPresentationLibrationAssert(str_contains($presentation['summary'], 'Amplitud aproximada: 7,0°.'), 'No se redondeó amplitud a un decimal con coma para ' . $subtype . '.');
    eventPresentationLibrationAssert(str_contains($presentation['explanation'], 'Luna gibosa menguante, 83% iluminada.'), 'No se mostró fase/iluminación amigable para ' . $subtype . '.');
    eventPresentationLibrationAssert($presentation['technical_details'] === [], 'No deben exponerse detalles técnicos en libraciones públicas.');
}

$highAmplitude = astronomyEventPresentation([
    'datetime' => '2026-01-07T11:15:45+00:00',
    'type' => 'libration',
    'subtype' => 'libration_west',
    'details' => [
        'absolute_value_degrees' => 7.4,
    ],
], $timezone);
eventPresentationLibrationAssert(
    str_contains($highAmplitude['explanation'], 'Con telescopio o una fotografía detallada puede notarse mejor cerca del borde favorecido.'),
    'Con amplitud alta faltó la recomendación sobria de observación detallada.'
);

$fallbackEvent = astronomyEventPresentation([
    'datetime' => '2026-01-08T00:05:00-03:00',
    'type' => 'libration',
    'subtype' => 'libration_east',
    'details' => [],
], $timezone);

eventPresentationLibrationAssert($fallbackEvent['title'] === 'Libración favorable hacia el este', 'El título por subtipo no debe depender de detalles opcionales.');
eventPresentationLibrationAssert(!str_contains(strtolower($fallbackEvent['summary']), 'null'), 'No debe imprimirse null en el resumen.');
eventPresentationLibrationAssert(!str_contains(strtolower($fallbackEvent['explanation']), 'nan'), 'No debe imprimirse NaN en la explicación.');

fwrite(STDOUT, "event presentation libration tests: ok\n");
