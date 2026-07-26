<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/event-presentation.php';

$tests = [];

$tests[] = function (): void {
    $event = [
        'datetime' => '2026-08-28T01:12:53-03:00',
        'type' => 'eclipse',
        'subtype' => 'lunar_eclipse',
        'title' => 'Eclipse lunar parcial',
        'details' => [
            'eclipse_global' => [
                'global_type' => 'partial',
                'contacts' => [
                    'P1' => '2026-08-28T01:23:59Z',
                    'U1' => '2026-08-28T02:33:55Z',
                    'MAX' => '2026-08-28T04:12:53Z',
                    'U4' => '2026-08-28T05:51:59Z',
                    'P4' => '2026-08-28T07:01:47Z',
                ],
                'magnitudes' => [
                    'umbral' => 0.928643457,
                ],
            ],
            'eclipse_local' => [
                'visibility_classification' => 'visible_partial',
                'contacts' => [
                    ['code' => 'P1', 'datetime' => '2026-08-27T22:23:59-03:00'],
                    ['code' => 'U1', 'datetime' => '2026-08-27T23:33:55-03:00'],
                    ['code' => 'MAX', 'datetime' => '2026-08-28T01:12:53-03:00'],
                    ['code' => 'U4', 'datetime' => '2026-08-28T02:51:59-03:00'],
                    ['code' => 'P4', 'datetime' => '2026-08-28T04:01:47-03:00'],
                ],
            ],
        ],
    ];

    $presentation = astronomyEventPresentation($event, 'America/Argentina/Buenos_Aires');

    if (($presentation['title'] ?? '') !== 'Eclipse lunar parcial') {
        throw new RuntimeException('El título del eclipse lunar parcial no es correcto.');
    }

    if (strpos((string) ($presentation['summary'] ?? ''), 'parcialmente') === false) {
        throw new RuntimeException('El resumen del eclipse lunar parcial no describe visibilidad parcial.');
    }

    $publicDetails = is_array($presentation['public_details'] ?? null) ? $presentation['public_details'] : [];
    $hasUmbral = false;
    foreach ($publicDetails as $entry) {
        if (($entry['label'] ?? '') === 'Magnitud umbral') {
            $hasUmbral = true;
            break;
        }
    }
    if (!$hasUmbral) {
        throw new RuntimeException('Falta la magnitud umbral en detalles públicos de eclipse lunar.');
    }

    $contacts = is_array($presentation['contact_points'] ?? null) ? $presentation['contact_points'] : [];
    if (!in_array('Máximo — 01:12', $contacts, true)) {
        throw new RuntimeException('No se encontró el contacto de máximo en hora local para eclipse lunar.');
    }
};

$tests[] = function (): void {
    $event = [
        'datetime' => '2026-03-03T08:33:41-03:00',
        'type' => 'eclipse',
        'subtype' => 'lunar_eclipse',
        'title' => 'Eclipse lunar total',
        'details' => [
            'eclipse_global' => [
                'global_type' => 'total',
                'magnitudes' => [
                    'umbral' => 1.1494,
                ],
            ],
            'eclipse_local' => [
                'visibility_classification' => 'not_visible',
            ],
        ],
    ];

    $presentation = astronomyEventPresentation($event, 'America/Argentina/Buenos_Aires');

    if (($presentation['summary'] ?? '') !== 'No será visible desde tu ubicación.') {
        throw new RuntimeException('El mensaje no visible para eclipse lunar no coincide.');
    }

    $publicDetails = is_array($presentation['public_details'] ?? null) ? $presentation['public_details'] : [];
    $visibility = null;
    foreach ($publicDetails as $entry) {
        if (($entry['label'] ?? '') === 'Visibilidad local') {
            $visibility = $entry['value'] ?? null;
            break;
        }
    }
    if ($visibility !== 'No visible') {
        throw new RuntimeException('La visibilidad local no se expone correctamente para eclipse lunar no visible.');
    }
};

$tests[] = function (): void {
    $event = [
        'datetime' => '2026-08-12T14:45:50-03:00',
        'type' => 'eclipse',
        'subtype' => 'solar_eclipse',
        'title' => 'Eclipse solar total',
        'details' => [
            'solar_eclipse_global' => [
                'global_type' => 'total',
                'central_duration_seconds' => 138,
            ],
            'solar_eclipse_local' => [
                'visibility_classification' => 'visible_total',
                'near_central_path_boundary' => true,
                'max_magnitude' => 0.8734,
                'max_obscuration' => 0.725,
                'contacts' => [
                    [
                        'code' => 'MAX',
                        'datetime' => '2026-08-12T14:45:50-03:00',
                        'sun' => [
                            'altitude_degrees' => 34.5118,
                            'azimuth_degrees' => 328.1537,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $presentation = astronomyEventPresentation($event, 'America/Argentina/Buenos_Aires');

    if (($presentation['title'] ?? '') !== 'Eclipse solar total') {
        throw new RuntimeException('El título del eclipse solar no es correcto.');
    }

    if (strpos((string) ($presentation['summary'] ?? ''), 'fase total') === false) {
        throw new RuntimeException('El resumen del eclipse solar no describe fase total local.');
    }

    $publicDetails = is_array($presentation['public_details'] ?? null) ? $presentation['public_details'] : [];
    $foundObscuration = false;
    $foundAltitude = false;
    $foundDirection = false;
    $foundCentralDuration = false;
    foreach ($publicDetails as $entry) {
        if (($entry['label'] ?? '') === 'Oscurecimiento' && ($entry['value'] ?? '') === '72,5%') {
            $foundObscuration = true;
        }
        if (($entry['label'] ?? '') === 'Altura del Sol en el máximo') {
            $foundAltitude = true;
        }
        if (($entry['label'] ?? '') === 'Dirección del Sol en el máximo' && ($entry['value'] ?? '') === 'Noroeste') {
            $foundDirection = true;
        }
        if (($entry['label'] ?? '') === 'Duración de la fase central') {
            $foundCentralDuration = true;
        }
    }

    if (!$foundObscuration || !$foundAltitude || !$foundDirection || !$foundCentralDuration) {
        throw new RuntimeException('Faltan detalles públicos esperados para eclipse solar visible.');
    }

    if (strpos((string) ($presentation['alert'] ?? ''), 'límite calculado') === false) {
        throw new RuntimeException('No se mostró la alerta por cercanía al límite de franja central.');
    }
};

$tests[] = function (): void {
    $event = [
        'datetime' => '2026-02-17T09:11:50-03:00',
        'type' => 'eclipse',
        'subtype' => 'solar_eclipse',
        'title' => 'Eclipse solar anular',
        'details' => [
            'solar_eclipse_global' => [
                'global_type' => 'annular',
                'contacts' => [
                    'MAX' => '2026-02-17T12:11:50Z',
                ],
            ],
            'solar_eclipse_local' => [
                'visibility_classification' => 'not_visible',
                'contacts' => [
                    [
                        'code' => 'MAX',
                        'datetime' => '2026-02-17T09:11:50-03:00',
                        'sun' => [
                            'altitude_degrees' => 32.1245,
                            'azimuth_degrees' => 82.124,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $presentation = astronomyEventPresentation($event, 'America/Argentina/Buenos_Aires');

    if (($presentation['summary'] ?? '') !== 'El eclipse ocurrirá, pero no será visible desde tu ubicación.') {
        throw new RuntimeException('El mensaje no visible para eclipse solar no coincide.');
    }

    $publicDetails = is_array($presentation['public_details'] ?? null) ? $presentation['public_details'] : [];
    foreach ($publicDetails as $entry) {
        $label = $entry['label'] ?? '';
        if ($label === 'Altura del Sol en el máximo' || $label === 'Dirección del Sol en el máximo') {
            throw new RuntimeException('No deberían mostrarse datos geométricos solares cuando el eclipse no es visible.');
        }
    }

    if (($presentation['alert'] ?? '') !== '') {
        throw new RuntimeException('No debería mostrarse alerta cuando near_central_path_boundary no aplica.');
    }
};

$tests[] = function (): void {
    $event = [
        'datetime' => '2026-12-01T10:00:00-03:00',
        'type' => 'eclipse',
        'subtype' => 'solar_eclipse',
        'title' => 'Eclipse solar',
        'details' => [
            'solar_eclipse_global' => [
                'global_type' => 'partial',
            ],
            'solar_eclipse_local' => [],
        ],
    ];

    $presentation = astronomyEventPresentation($event, 'America/Argentina/Buenos_Aires');

    if (($presentation['summary'] ?? '') !== 'No se pudo determinar la visibilidad local desde tu ubicación.') {
        throw new RuntimeException('El fallback de visibilidad indeterminada no coincide.');
    }

    if (($presentation['explanation'] ?? '') !== 'No se pudo determinar la visibilidad local.') {
        throw new RuntimeException('El fallback de explicación indeterminada no coincide.');
    }
};

$passed = 0;
foreach ($tests as $index => $test) {
    $test();
    $passed++;
}

echo "OK ({$passed} casos)\n";
