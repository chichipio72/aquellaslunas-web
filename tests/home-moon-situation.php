<?php

require_once __DIR__ . '/../includes/home-sky.php';

function homeMoonSituationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function homeMoonSituationInstant(float $altitude, float $azimuth = 0.0, bool $aboveHorizon = true): array
{
    return [
        'instant' => new DateTimeImmutable('2026-07-22T20:00:00-03:00'),
        'altitude_degrees' => $altitude,
        'azimuth_degrees' => $azimuth,
        'above_horizon' => $aboveHorizon,
        'age_days' => 10.0,
    ];
}

$boundaryCases = [
    [14.9, 'Está visible, muy baja hacia norte.'],
    [15.0, 'Está visible, baja hacia norte.'],
    [34.9, 'Está visible, baja hacia norte.'],
    [35.0, 'Está visible, a media altura hacia norte.'],
    [59.9, 'Está visible, a media altura hacia norte.'],
    [60.0, 'Está visible, muy alta. Mirá casi hacia arriba.'],
    [79.9, 'Está visible, muy alta. Mirá casi hacia arriba.'],
    [80.0, 'Está visible, prácticamente sobre tu cabeza.'],
];
foreach ($boundaryCases as [$altitude, $expected]) {
    homeMoonSituationAssert(
        homeMoonSituation(homeMoonSituationInstant($altitude), 10.0) === $expected,
        'Mensaje incorrecto para altura ' . $altitude . '°.'
    );
}

$directionCases = [
    [0.0, 'norte'], [45.0, 'noreste'], [90.0, 'este'], [135.0, 'sudeste'],
    [180.0, 'sur'], [225.0, 'sudoeste'], [270.0, 'oeste'], [315.0, 'noroeste'],
];
foreach ($directionCases as [$azimuth, $direction]) {
    $text = homeMoonSituation(homeMoonSituationInstant(40.0, $azimuth), 10.0);
    homeMoonSituationAssert($text === 'Está visible, a media altura hacia ' . $direction . '.', 'Dirección incorrecta para azimut ' . $azimuth . '°.');
}

foreach ([60.0, 70.0, 79.9, 80.0, 89.9] as $altitude) {
    foreach (array_column($directionCases, 0) as $azimuth) {
        $text = homeMoonSituation(homeMoonSituationInstant($altitude, (float) $azimuth), 10.0);
        homeMoonSituationAssert(
            preg_match('/\b(?:norte|sur|este|oeste|noreste|noroeste|sudeste|sudoeste)\b/u', $text) !== 1,
            'Una Luna cenital incluyó un punto cardinal.'
        );
    }
}

$below = homeMoonSituation(homeMoonSituationInstant(-5.0, 180.0, false), 0.1);
homeMoonSituationAssert($below === 'No está sobre el horizonte.', 'El mensaje bajo el horizonte perdió prioridad.');
$newMoon = homeMoonSituation(homeMoonSituationInstant(40.0, 90.0), 1.0);
homeMoonSituationAssert($newMoon === 'Está sobre el horizonte, pero es prácticamente imposible verla.', 'El mensaje de Luna nueva perdió prioridad.');
$thinMoon = homeMoonSituation(homeMoonSituationInstant(40.0, 90.0), 3.0);
homeMoonSituationAssert($thinMoon === 'Está muy finita y cuesta encontrarla a simple vista.', 'El mensaje de Luna fina perdió prioridad.');

foreach ($boundaryCases as [$altitude]) {
    $text = homeMoonSituation(homeMoonSituationInstant((float) $altitude, 90.0), 10.0);
    homeMoonSituationAssert(substr_count($text, 'Está visible') === 1, 'Se duplicó “Está visible”.');
    homeMoonSituationAssert(preg_match('/(?:,,|\.\.|,\s*\.|hacia\s*[.,]|\s{2,})/u', $text) !== 1, 'Se generó puntuación o un conector incorrecto.');
}

fwrite(STDOUT, "home moon situation tests: ok\n");
