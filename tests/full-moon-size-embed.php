<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/lunar-embeds.php';
require_once __DIR__ . '/../includes/full-moon-size-embed.php';

function fullMoonSizeAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$location = [
    'latitude' => -34.53,
    'longitude' => -58.48,
    'timezone' => 'America/Argentina/Buenos_Aires',
    'elevation_meters' => 0.0,
];
$timezone = new DateTimeZone($location['timezone']);
$effectiveNow = new DateTimeImmutable('2026-09-06T18:42:31-03:00');
$defaultOptions = fullMoonSizeEmbedOptions([], $timezone, $effectiveNow);
fullMoonSizeAssert($defaultOptions['reference']->format(DateTimeInterface::ATOM) === $effectiveNow->format(DateTimeInterface::ATOM), 'Sin date se perdió la hora del ahora efectivo.');
$forcedOptions = fullMoonSizeEmbedOptions(['date' => '2026-09-06'], $timezone, $effectiveNow);
fullMoonSizeAssert($forcedOptions['reference']->format('Y-m-d H:i:s') === '2026-09-06 00:00:00', 'La fecha forzada no conserva su comportamiento de inicio del día local.');

$moons = nextFullMoonSizes($defaultOptions['reference'], $location);
fullMoonSizeAssert(count($moons) === 12, 'El comparador no devuelve exactamente doce lunas llenas.');
$previousTimestamp = null;
foreach ($moons as $moon) {
    $timestamp = (new DateTimeImmutable($moon['datetime']))->getTimestamp();
    fullMoonSizeAssert($previousTimestamp === null || $timestamp > $previousTimestamp, 'Las lunas llenas no están en orden cronológico estricto.');
    fullMoonSizeAssert(abs($moon['size_percent'] - AstronomyEngine\MoonApparentSize::percentOfMean($moon['distance_km'])) < 1e-9, 'El porcentaje no corresponde al diámetro aparente calculado.');
    fullMoonSizeAssert(abs($moon['diameter_arcminutes'] - AstronomyEngine\MoonApparentSize::angularDiameterArcminutes($moon['distance_km'])) < 1e-9, 'El diámetro angular no corresponde a la distancia del evento.');
    $previousTimestamp = $timestamp;
}
fullMoonSizeAssert(abs(AstronomyEngine\MoonApparentSize::percentOfMean(AstronomyEngine\MoonApparentSize::MEAN_DISTANCE_KILOMETERS) - 100.0) < 1e-12, 'La distancia media no corresponde exactamente al 100 %.');

$firstInstant = new DateTimeImmutable($moons[0]['datetime']);
$atEvent = nextFullMoonSizes($firstInstant, $location);
fullMoonSizeAssert($atEvent[0]['datetime'] === $moons[0]['datetime'], 'La coincidencia exacta con una Luna llena no la incluye.');
$afterEvent = nextFullMoonSizes($firstInstant->modify('+1 microsecond'), $location);
fullMoonSizeAssert($afterEvent[0]['datetime'] === $moons[1]['datetime'], 'Después de una Luna llena no se salta al evento siguiente.');
$beforeEvent = nextFullMoonSizes($firstInstant->modify('-1 microsecond'), $location);
fullMoonSizeAssert($beforeEvent[0]['datetime'] === $moons[0]['datetime'], 'Un evento futuro inmediato no se incluye.');

$previousDebugNow = $_GET['debug_now'] ?? null;
putenv('LOCAL_TIME_SIMULATION_ENABLED=1');
$_GET['debug_now'] = '2026-09-06T21:42:31Z';
$simulatedNow = get_current_datetime($timezone->getName());
fullMoonSizeAssert($simulatedNow->format(DateTimeInterface::ATOM) === '2026-09-06T18:42:31-03:00', 'El widget no recibe la hora simulada del reloj central.');
$simulatedOptions = fullMoonSizeEmbedOptions([], $timezone, $simulatedNow);
fullMoonSizeAssert($simulatedOptions['reference']->format(DateTimeInterface::ATOM) === $simulatedNow->format(DateTimeInterface::ATOM), 'Sin date no se conserva el ahora simulado completo.');
if ($previousDebugNow === null) unset($_GET['debug_now']); else $_GET['debug_now'] = $previousDebugNow;
putenv('LOCAL_TIME_SIMULATION_ENABLED');

echo "full-moon-size-embed: OK\n";
