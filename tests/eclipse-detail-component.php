<?php

require_once __DIR__ . '/../includes/presentation.php';
require_once __DIR__ . '/../includes/eclipse-detail-component.php';

function eclipseDetailAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$event = [
    'id' => 'eclipse-test-1',
    'type' => 'eclipse',
    'subtype' => 'solar_eclipse',
    'datetime' => '2027-02-06T15:00:00Z',
    'details' => [
        'solar_eclipse_global' => [
            'global_type' => 'annular',
            'contacts' => ['C1' => '2027-02-06T13:40:00Z', 'MAX' => '2027-02-06T15:00:00Z', 'C4' => '2027-02-06T16:20:00Z'],
            'visibility_map' => ['available' => false, 'status' => 'not_found'],
        ],
        'solar_eclipse_local' => [
            'visibility_classification' => 'not_visible',
            'contacts' => [[
                'code' => 'MAX',
                'datetime' => '2027-02-06T15:00:00Z',
                'sun' => ['altitude_degrees' => -22.4, 'azimuth_degrees' => 248.1],
            ]],
            'max_magnitude' => 0,
        ],
    ],
];

$model = astronomyEclipseDetailModel($event, 'America/Argentina/Buenos_Aires');
eclipseDetailAssert($model['not_visible'], 'No se destacó la falta de visibilidad local.');
eclipseDetailAssert($model['visibility_map'] === null, 'Se creó un bloque para un mapa no disponible.');
eclipseDetailAssert($model['contacts'] !== [] && in_array('Alt. -22,4°', $model['contacts'][0]['extra'], true), 'No se conservaron contactos y alturas.');
eclipseDetailAssert(astronomyEclipseDetailId($event) === astronomyEclipseDetailId($event), 'El identificador no es estable.');

ob_start();
renderAstronomyEclipseDetailTrigger($event, 'Datos técnicos');
renderAstronomyEclipseDetailTemplate($event, 'America/Argentina/Buenos_Aires', 'Buenos Aires', 'https://example.test/eclipses.php', null, [
    'latitude' => -34.6037,
    'longitude' => -58.3816,
    'elevation_meters' => 0,
]);
renderAstronomyEclipseModal();
$html = ob_get_clean();
eclipseDetailAssert(substr_count($html, 'eclipse-detail-' . astronomyEclipseDetailId($event)) >= 2, 'Acción y plantilla no comparten identificador.');
eclipseDetailAssert(str_contains($html, 'Datos técnicos'), 'Falta la acción unificada Datos técnicos.');
eclipseDetailAssert(str_contains($html, 'No visible desde tu ubicación'), 'Falta el aviso explícito de no visibilidad.');
eclipseDetailAssert(!str_contains($html, 'eclipse-detail-world-map'), 'El mapa ausente dejó un bloque vacío.');
eclipseDetailAssert(str_contains($html, 'Agendar evento'), 'El modal no incluye la opción de agenda.');
eclipseDetailAssert(str_contains($html, '<iframe') && str_contains($html, 'date=2027-02-06'), 'El detalle no reemplazó la fotografía por el widget contextual.');
eclipseDetailAssert(substr_count($html, '<dialog id="eclipses-modal"') === 1, 'El modal compartido se duplicó.');

echo "Detalle compartido de eclipses: OK\n";
