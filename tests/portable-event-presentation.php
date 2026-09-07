<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/event-presentation.php';
require_once __DIR__ . '/../includes/astronomy-events.php';

use AstronomyEngine\Facade\AstronomyEventsFacade;
use AstronomyEngine\Facade\AstronomyObserver;

function portableEventAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$timezone = 'America/Argentina/Buenos_Aires';
$latitude = -34.6037;
$longitude = -58.3816;
$start = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
$end = new DateTimeImmutable('2027-01-01T00:00:00+00:00');

$phases = astronomyEventsFromPhp('moon_phase', $start, $end, $latitude, $longitude, $timezone);
$fullMoon = current(array_filter($phases, static fn(array $event): bool => ($event['subtype'] ?? null) === 'full_moon'));
portableEventAssert(is_array($fullMoon), 'El motor PHP no devolvió una Luna llena real.');
portableEventAssert(is_numeric($fullMoon['details']['apparent_size_percent'] ?? null), 'La fase pública perdió el tamaño aparente.');
$fullMoonPresentation = astronomyEventPresentation($fullMoon, $timezone);
portableEventAssert($fullMoonPresentation['title'] === 'Superluna', 'La Luna llena real cercana no recuperó la clasificación de Superluna.');
portableEventAssert(isset($fullMoonPresentation['technical_details']['Tamaño relativo']), 'La fase real no mostró Tamaño relativo.');

$apsides = astronomyEventsFromPhp('lunar_apsis', $start, $end, $latitude, $longitude, $timezone);
$perigee = current(array_filter($apsides, static fn(array $event): bool => ($event['subtype'] ?? null) === 'perigee'));
portableEventAssert(is_array($perigee), 'El motor PHP no devolvió un perigeo real.');
$perigeePresentation = astronomyEventPresentation($perigee, $timezone);
portableEventAssert(isset($perigeePresentation['technical_details']['Tamaño relativo']), 'El perigeo real no mostró Tamaño relativo.');

$librations = astronomyEventsFromPhp('lunar_libration', $start, $end, $latitude, $longitude, $timezone);
$libration = $librations[0] ?? null;
portableEventAssert(is_array($libration), 'El motor PHP no devolvió una libración real.');
$moonPhase = is_array($libration['details']['moon_phase'] ?? null) ? $libration['details']['moon_phase'] : [];
portableEventAssert(is_string($moonPhase['name'] ?? null), 'La libración real perdió el nombre de fase.');
portableEventAssert(is_numeric($moonPhase['illumination_percent'] ?? null), 'La libración real perdió la iluminación.');
portableEventAssert(is_bool($moonPhase['waxing'] ?? null), 'La libración real perdió la dirección creciente/menguante.');
$librationPresentation = astronomyEventPresentation($libration, $timezone);
portableEventAssert(str_contains($librationPresentation['explanation'], 'iluminada'), 'La libración real no presentó fase e iluminación.');

$conjunctions = astronomyEventsFromPhp('lunar_conjunction', $start, $end, $latitude, $longitude, $timezone);
foreach (['mars' => 'Marte', 'pleiades' => 'Pléyades'] as $subtype => $publicName) {
    $conjunction = current(array_filter($conjunctions, static fn(array $event): bool => ($event['subtype'] ?? null) === $subtype));
    portableEventAssert(is_array($conjunction), "El motor PHP no devolvió la conjunción real con {$publicName}.");
    portableEventAssert(($conjunction['details']['object_id'] ?? null) === $subtype, "La conjunción con {$publicName} perdió su ID público.");
    portableEventAssert(($conjunction['details']['object_name'] ?? null) === $publicName, "La conjunción con {$publicName} perdió su nombre público.");
    $conjunctionPresentation = astronomyEventPresentation($conjunction, $timezone);
    portableEventAssert(str_contains($conjunctionPresentation['title'], $publicName), "La presentación real no nombró a {$publicName}.");
    portableEventAssert(!str_contains($conjunctionPresentation['title'], 'Otro astro'), "La presentación real usó el fallback para {$publicName}.");
}

$observer = new AstronomyObserver($latitude, $longitude, $timezone);
$nodes = (new AstronomyEventsFacade())->between(
    new DateTimeImmutable('2026-01-01', new DateTimeZone($timezone)),
    $observer,
    60,
    ['lunar_nodes']
);
portableEventAssert($nodes['items'] !== [], 'La fachada no devolvió nodos para verificar su contrato.');
foreach ($nodes['items'] as $node) {
    $expectedTitle = ($node['subtype'] ?? null) === 'ascending_node' ? 'Nodo lunar ascendente' : 'Nodo lunar descendente';
    portableEventAssert(
        in_array($node['subtype'] ?? null, ['ascending_node', 'descending_node'], true),
        'La fachada publicó un subtipo corto de nodo.'
    );
    portableEventAssert(($node['title'] ?? null) === $expectedTitle, 'La fachada desalineó el título y el subtipo del nodo.');
    $nodePresentation = astronomyEventPresentation($node, $timezone);
    $ascending = ($node['subtype'] ?? null) === 'ascending_node';
    portableEventAssert(
        $nodePresentation['title'] === ($ascending ? 'La Luna cruza hacia el norte' : 'La Luna cruza hacia el sur'),
        'El nodo real no recibió un título público comprensible.'
    );
    portableEventAssert(
        ($nodePresentation['technical_details']['Tipo de nodo'] ?? null) === ($ascending ? 'Nodo ascendente' : 'Nodo descendente'),
        'El nodo real no conservó su subtipo como dato secundario.'
    );
    portableEventAssert(isset($nodePresentation['technical_details']['Distancia lunar']), 'El nodo real perdió la distancia calculada.');
    portableEventAssert(str_contains($nodePresentation['explanation'], 'eclipses'), 'El nodo real no presenta su relación educativa con eclipses.');
}

echo "Contratos públicos de eventos PHP portables: OK\n";
