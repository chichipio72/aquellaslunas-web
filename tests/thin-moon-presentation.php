<?php

require_once __DIR__ . '/../includes/presentation.php';
require_once __DIR__ . '/../includes/event-presentation.php';

function thinMoonAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function thinMoonEvent(string $subtype, float $illumination, bool $earthshine, int $offsetDays): array
{
    $morning = $subtype === 'morning';
    return [
        'type' => 'earthshine',
        'subtype' => $subtype,
        'title' => $earthshine ? 'Ventana de luz cenicienta' : 'Luna fina',
        'datetime' => $morning ? '2026-08-11T06:55:00-03:00' : '2026-08-13T18:22:00-03:00',
        'end_datetime' => $morning ? '2026-08-11T07:38:00-03:00' : '2026-08-13T19:35:00-03:00',
        'details' => [
            'illumination_percent' => $illumination,
            'separation_degrees' => $morning ? 18.252 : 15.876,
            'earthshine_visible' => $earthshine,
            'offset_days' => $offsetDays,
            'start_time' => $morning ? '2026-08-11T06:55:00-03:00' : '2026-08-13T18:22:00-03:00',
            'end_time' => $morning ? '2026-08-11T07:38:00-03:00' : '2026-08-13T19:35:00-03:00',
            'best_visible_time' => $morning ? '2026-08-11T07:16:00-03:00' : '2026-08-13T18:58:00-03:00',
            'moon_event_time' => $morning ? '2026-08-11T06:55:00-03:00' : '2026-08-13T19:35:00-03:00',
            'solar_event_time' => $morning ? '2026-08-11T07:38:00-03:00' : '2026-08-13T18:22:00-03:00',
            'difference_minutes' => $morning ? -43 : 73,
        ],
    ];
}

$morning = thinMoonEvent('morning', 2.529, false, -1);
$presentation = astronomyEventPresentation($morning, 'America/Argentina/Buenos_Aires');
thinMoonAssert($presentation['title'] === 'Luna fina antes del amanecer', 'Título matutino incorrecto.');
thinMoonAssert($presentation['observation']['period_label'] === 'Antes del amanecer', 'Período matutino ausente.');
thinMoonAssert(
    $presentation['summary'] === 'Iluminación 2,5 % · Separación del Sol 18,3° · La Luna saldrá 43 min antes',
    'Resumen matutino incorrecto.'
);
thinMoonAssert($presentation['explanation'] === '', 'Se afirmó luz cenicienta sin cumplir su regla.');

$evening = thinMoonEvent('evening', 1.915, true, 1);
$presentation = astronomyEventPresentation($evening, 'America/Argentina/Buenos_Aires');
thinMoonAssert($presentation['title'] === 'La parte oscura de la Luna también será visible', 'No se preservó el título de luz cenicienta.');
thinMoonAssert($presentation['observation']['period_label'] === 'Después del atardecer', 'Período vespertino ausente.');
thinMoonAssert(str_contains($presentation['summary'], 'La Luna se pondrá 73 min después'), 'Relación vespertina incorrecta.');
thinMoonAssert(
    $presentation['explanation'] === 'También puede verse la parte oscura del disco lunar.',
    'No se integró la frase de luz cenicienta.'
);

$newMoonHidden = thinMoonEvent('evening', 0.3999, true, 0);
thinMoonAssert(!astronomyEarthshineEventIsDisplayable($newMoonHidden), 'Se mostró el día de Luna nueva por debajo de 0,4 %.');
$newMoonBoundary = thinMoonEvent('evening', 0.4, true, 0);
thinMoonAssert(astronomyEarthshineEventIsDisplayable($newMoonBoundary), 'Se rechazó el límite exacto de 0,4 %.');
$newMoonHidden['details']['illumination_percent'] = 0.49;
$newMoonHidden['details']['display_percent'] = 0;
thinMoonAssert(
    astronomyEarthshineEventIsDisplayable($newMoonHidden),
    'El filtro usó un porcentaje redondeado en vez del valor real.'
);

echo "Presentación integrada de Luna fina y luz cenicienta: OK\n";
