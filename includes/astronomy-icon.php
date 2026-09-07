<?php

function astronomyIconKey(array $event): string
{
    $type = is_string($event['type'] ?? null) ? $event['type'] : 'unknown';
    $subtype = is_string($event['subtype'] ?? null) ? $event['subtype'] : '';
    if ($type === 'moon_phase') {
        return match ($subtype) {
            'new_moon' => 'moon-new',
            'first_quarter' => 'moon-first-quarter',
            'full_moon' => 'moon-full',
            'last_quarter' => 'moon-last-quarter',
            default => 'moon-generic',
        };
    }
    if ($type === 'conjunction') {
        $objects = $event['details']['objects'] ?? $event['objects'] ?? [];
        $target = strtolower(trim(implode(' ', array_filter([
            $subtype,
            is_string($event['details']['planet'] ?? null) ? $event['details']['planet'] : '',
            is_string($event['title'] ?? null) ? $event['title'] : '',
        ]))));
        $knownCluster = preg_match('/pleiades|pleyades|pléyades|hyades|híades|beehive|m44/', $target) === 1;
        return (is_array($objects) && count($objects) > 2) || $knownCluster
            ? 'conjunction-cluster'
            : 'conjunction';
    }
    if ($type === 'libration') {
        return match ($subtype) {
            'libration_east' => 'libration-east',
            'libration_west' => 'libration-west',
            'libration_north' => 'libration-north',
            'libration_south' => 'libration-south',
            default => 'libration',
        };
    }
    if ($type === 'apsis') {
        return in_array($subtype, ['perigee', 'apogee'], true) ? $subtype : 'distance';
    }
    if ($type === 'lunar_nodes') {
        return match ($subtype) {
            'ascending_node' => 'node-ascending',
            'descending_node' => 'node-descending',
            default => 'generic',
        };
    }
    return match ($type) {
        'earthshine' => 'moon-earthshine',
        'eclipse' => 'eclipse',
        'full_moon_observation' => 'moon-full',
        default => 'generic',
    };
}

function astronomyIconLabel(string $key): string
{
    return match ($key) {
        'moon-new' => 'Luna nueva',
        'moon-first-quarter' => 'Cuarto creciente',
        'moon-full' => 'Luna llena',
        'moon-last-quarter' => 'Cuarto menguante',
        'moon-earthshine' => 'Luz cenicienta',
        'conjunction', 'conjunction-cluster' => 'Conjunción',
        'libration-east' => 'Libración hacia el este',
        'libration-west' => 'Libración hacia el oeste',
        'libration-north' => 'Libración hacia el norte',
        'libration-south' => 'Libración hacia el sur',
        'libration' => 'Libración lunar',
        'perigee' => 'Perigeo',
        'apogee' => 'Apogeo',
        'distance' => 'Distancia lunar',
        'node-ascending' => 'Nodo ascendente',
        'node-descending' => 'Nodo descendente',
        'eclipse' => 'Eclipse',
        default => 'Evento astronómico',
    };
}

function renderAstronomyIcon(array $event, float $latitude, string $extraClass = ''): void
{
    $key = astronomyIconKey($event);
    $hemisphere = $latitude < 0 ? 'south' : 'north';
    $classes = trim('astro-icon astro-icon--' . $key . ' astro-icon--' . $hemisphere . ' ' . $extraClass);
    ?><span class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"><i></i></span><?php
}
