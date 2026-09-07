<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/home-solar-events.php';

function homeSolarAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$days = [
    ['rise' => '2026-09-06T07:11:00-03:00', 'set' => '2026-09-06T18:42:00-03:00'],
    ['rise' => '2026-09-07T07:10:00-03:00', 'set' => '2026-09-07T18:43:00-03:00'],
];

$beforeRise = homeNextSolarEvents($days, new DateTimeImmutable('2026-09-06T05:30:00-03:00'), 'America/Argentina/Buenos_Aires');
homeSolarAssert(array_column($beforeRise, 'kind') === ['rise', 'set'], 'Antes de la salida no se conservaron los dos hitos futuros en orden.');
homeSolarAssert(array_column($beforeRise, 'label') === ['Salida del Sol', 'Puesta del Sol'], 'Los hitos del día actual recibieron etiquetas ambiguas.');

$afternoon = homeNextSolarEvents($days, new DateTimeImmutable('2026-09-06T15:00:00-03:00'), 'America/Argentina/Buenos_Aires');
homeSolarAssert(array_column($afternoon, 'kind') === ['set', 'rise'], 'Durante la tarde se impuso un orden fijo en vez del cronológico.');
homeSolarAssert(array_column($afternoon, 'label') === ['Puesta del Sol', 'Salida mañana'], 'La salida del día siguiente no se distinguió como mañana.');

$night = homeNextSolarEvents($days, new DateTimeImmutable('2026-09-06T22:30:00-03:00'), 'America/Argentina/Buenos_Aires');
homeSolarAssert(array_column($night, 'kind') === ['rise', 'set'], 'Durante la noche no se ordenaron salida y puesta del día siguiente.');
homeSolarAssert(array_column($night, 'label') === ['Salida mañana', 'Puesta mañana'], 'La noche no marcó ambos hitos del día siguiente.');

$afterMidnight = homeNextSolarEvents($days, new DateTimeImmutable('2026-09-07T01:00:00-03:00'), 'America/Argentina/Buenos_Aires');
homeSolarAssert(array_column($afterMidnight, 'label') === ['Salida del Sol', 'Puesta del Sol'], 'El cambio de medianoche alteró las etiquetas de la fecha civil vigente.');

$exactRise = homeNextSolarEvents($days, new DateTimeImmutable('2026-09-06T07:11:00-03:00'), 'America/Argentina/Buenos_Aires');
homeSolarAssert(array_column($exactRise, 'kind') === ['set', 'rise'], 'Un evento ya alcanzado siguió figurando como próximo.');

$utcNow = new DateTimeImmutable('2026-09-06T18:00:00Z');
$localized = homeNextSolarEvents($days, $utcNow, 'America/Argentina/Buenos_Aires');
homeSolarAssert(($localized[0]['time'] ?? null) === '18:42', 'No se respetó la zona horaria activa al comparar o presentar eventos.');

$simulatedNow = new DateTimeImmutable('2026-09-06T22:30:00-03:00');
$simulated = homeNextSolarEvents($days, $simulatedNow, 'America/Argentina/Buenos_Aires');
homeSolarAssert(array_column($simulated, 'kind') === ['rise', 'set'], 'El modelo no usa el instante simulado que recibe la portada.');

$indexSource = file_get_contents(__DIR__ . '/../index.php');
homeSolarAssert(is_string($indexSource) && str_contains($indexSource, 'homeNextSolarEvents(') && str_contains($indexSource, '$now,'), 'La portada no conecta los hitos solares con su reloj efectivo real o simulado.');

echo "Próximos hitos solares de portada: OK\n";
