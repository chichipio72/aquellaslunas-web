<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/photography-scene.php';

use AstronomyEngine\LunarDayCalculator;
use AstronomyEngine\MeeusLunarCalculator;

function photographyHorizonAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$timezone = new DateTimeZone('America/Argentina/Buenos_Aires');
$location = [
    'latitude' => -34.533,
    'longitude' => -58.5381,
    'elevation_meters' => 0.0,
    'timezone' => $timezone->getName(),
];
$calculator = new LunarDayCalculator(new MeeusLunarCalculator());
$day = $calculator->calculate(new DateTimeImmutable('2026-08-29 00:00:00', $timezone), $location['latitude'], $location['longitude'], 0.0);
photographyHorizonAssert($day->moonset instanceof DateTimeImmutable, 'El caso debe tener puesta lunar.');
$canonicalScene = photographyAstronomicalScene($day->moonset, $location, [], null, ['type' => 'moonset']);
photographyHorizonAssert(abs((float) $canonicalScene['moon']['upper_limb_altitude_degrees']) < 0.00003, 'La puesta canónica debe coincidir con el cruce general del limbo superior.');

$referenceDistance = 384400.0;
$referenceSemidiameter = LunarDayCalculator::apparentSemidiameterDegrees($referenceDistance);
$referenceRefraction = LunarDayCalculator::STANDARD_REFRACTION_DEGREES;
$syntheticFull = photographyMoonHorizonVisibility(-$referenceRefraction + $referenceSemidiameter, $referenceDistance);
$syntheticHalf = photographyMoonHorizonVisibility(-$referenceRefraction, $referenceDistance);
$syntheticBelow = photographyMoonHorizonVisibility(-$referenceRefraction - $referenceSemidiameter - 0.001, $referenceDistance);
photographyHorizonAssert($syntheticFull['state'] === 'fully_visible', 'El limbo inferior sobre el horizonte debe producir disco completo.');
photographyHorizonAssert($syntheticHalf['state'] === 'partially_visible' && abs($syntheticHalf['fraction_above_horizon'] - 0.5) < 1e-12, 'El centro aparente sobre el horizonte debe dejar medio disco visible.');
photographyHorizonAssert($syntheticBelow['state'] === 'below_horizon', 'El limbo superior bajo el horizonte debe ocultar el disco.');

$rows = [];
$previousFraction = 1.0;
foreach (range(45, 54) as $minute) {
    $instant = new DateTimeImmutable(sprintf('2026-08-29 07:%02d:00', $minute), $timezone);
    $scene = photographyAstronomicalScene($instant, $location);
    $moon = $scene['moon'];
    photographyHorizonAssert($moon['visibility_reference'] === 'apparent_limb_with_standard_refraction', 'La referencia no debe depender del contexto de evento.');
    photographyHorizonAssert(abs((float) $scene['horizon']['standard_refraction_degrees'] - LunarDayCalculator::STANDARD_REFRACTION_DEGREES) < 1e-12, 'Debe usarse la refracción estándar del motor.');
    photographyHorizonAssert((float) $moon['fraction_above_horizon'] <= $previousFraction + 1e-9, 'La fracción visible debe descender continuamente durante la puesta.');
    $previousFraction = (float) $moon['fraction_above_horizon'];
    $rows[] = [
        'time' => $instant->format('H:i'),
        'center' => (float) $moon['altitude_degrees'],
        'semidiameter' => (float) $moon['topocentric_semidiameter_degrees'],
        'refraction' => (float) $scene['horizon']['standard_refraction_degrees'],
        'state' => (string) $moon['visibility_state'],
        'fraction' => (float) $moon['fraction_above_horizon'],
    ];
}

photographyHorizonAssert($rows[0]['state'] === 'fully_visible', 'A las 07:45 la Luna debe estar completamente visible.');
photographyHorizonAssert(in_array('partially_visible', array_column($rows, 'state'), true), 'La serie debe contener una etapa parcialmente visible.');
photographyHorizonAssert($rows[array_key_last($rows)]['state'] === 'below_horizon', 'A las 07:54 la Luna debe estar bajo el horizonte.');

$renderer = file_get_contents(__DIR__ . '/../assets/js/photography-planner.js');
$simulatedRenderer = file_get_contents(__DIR__ . '/../assets/js/photography-moon-three.js');
photographyHorizonAssert(is_string($renderer) && str_contains($renderer, "visibilityState === 'partially_visible'") && str_contains($renderer, 'photography-visible-sky'), 'El modo Esquema debe recortar el disco parcial contra el horizonte.');
photographyHorizonAssert(str_contains((string) $renderer, "const moonVisibleInCurrentMode = visibilityState !== 'below_horizon'") && !str_contains((string) $renderer, "Number(payload.astronomy.moon.altitude_degrees) > 0"), 'Los renderers no comparten exclusivamente el estado físico de visibilidad lunar.');
photographyHorizonAssert(is_string($simulatedRenderer) && str_contains($simulatedRenderer, "detail.visibilityState === 'partially_visible'") && str_contains($simulatedRenderer, 'detail.visibleSkyPolygon'), 'El modo Simulado no recorta la Luna 3D parcial contra el mismo horizonte.');
photographyHorizonAssert(str_contains((string) $simulatedRenderer, 'moonHorizonClipEnabled') && str_contains((string) $simulatedRenderer, 'if (horizonSide < 0.0) discard'), 'El recorte del horizonte no se aplica dentro del shader lunar 3D.');
photographyHorizonAssert(str_contains((string) $renderer, 'photography-simulated-foreground') && str_contains((string) $renderer, "state.displayMode === 'simulated' && payload.include_horizon"), 'El terreno simulado no posee una capa de primer plano sobre la Luna 3D.');

echo 'moonset=' . $day->moonset->format(DateTimeInterface::ATOM) . "\n";
foreach ($rows as $row) {
    printf("%s center=%+.6f semidiameter=%.6f refraction=%.6f state=%s fraction=%.6f\n", $row['time'], $row['center'], $row['semidiameter'], $row['refraction'], $row['state'], $row['fraction']);
}
echo "photography-horizon-visibility: ok\n";
