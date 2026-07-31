<?php

require_once __DIR__ . '/../includes/presentation.php';
require_once __DIR__ . '/../includes/event-presentation.php';

function fullMoonObservationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$event = [
    'type' => 'full_moon_observation',
    'subtype' => 'morning',
    'datetime' => '2026-07-29T06:10:00-03:00',
    'details' => [
        'difference_minutes' => 18,
        'temporal_classification' => 'before_sunrise',
        'moon_event' => 'moonset',
        'moon_event_time' => '2026-07-29T06:04:00-03:00',
        'illumination_percent' => 99.8,
    ],
];
$presentation = astronomyEventPresentation($event, 'America/Argentina/Buenos_Aires');
$moment = astronomyFullMoonObservationMoment($event, 'America/Argentina/Buenos_Aires');

fullMoonObservationAssert($presentation['title'] === 'Luna llena cerca de la salida del Sol', 'Título derivado incorrecto.');
fullMoonObservationAssert(str_contains($presentation['summary'], '18 minutos'), 'No se mostró la diferencia en minutos.');
fullMoonObservationAssert($presentation['explanation'] !== '', 'Falta la explicación breve.');
fullMoonObservationAssert($moment !== null && $moment['label'] === 'Puesta de la Luna', 'Momento lunar incorrecto.');
fullMoonObservationAssert($moment['date']->format('H:i') === '06:04', 'Hora lunar incorrecta.');

$event['subtype'] = 'evening';
$event['details']['moon_event'] = 'moonrise';
$event['details']['moon_event_time'] = '2026-07-29T18:02:00-03:00';
$event['details']['temporal_classification'] = 'after_sunset';
$presentation = astronomyEventPresentation($event, 'America/Argentina/Buenos_Aires');
$moment = astronomyFullMoonObservationMoment($event, 'America/Argentina/Buenos_Aires');
fullMoonObservationAssert($presentation['title'] === 'Luna llena cerca de la puesta del Sol', 'Título vespertino incorrecto.');
fullMoonObservationAssert($moment !== null && $moment['label'] === 'Salida de la Luna', 'Momento vespertino incorrecto.');

echo "Presentación compartida de Luna llena y horizonte: OK\n";
