<?php

declare(strict_types=1);

require_once __DIR__ . '/favorite-moon.php';

/** @return array{craters:bool,maria:bool,other:bool,landings:bool,detail:string,illumination:string,embed:bool} */
function interactiveMoonOptions(array $query): array
{
    $boolean = static fn(string $key, bool $default): bool => array_key_exists($key, $query)
        ? in_array((string) $query[$key], ['1', 'true', 'on'], true)
        : $default;
    return [
        'craters' => $boolean('craters', true),
        'maria' => $boolean('maria', true),
        'other' => $boolean('other', false),
        'landings' => $boolean('landings', true),
        'detail' => in_array(($query['detail'] ?? ''), ['main', 'more'], true) ? (string) $query['detail'] : 'auto',
        'illumination' => ($query['illumination'] ?? '') === 'full' ? 'full' : 'realistic',
        'embed' => ($query['embed'] ?? '') === '1',
    ];
}

/** @return array<string,mixed> */
function interactiveMoonFeatureCatalog(): array
{
    $path = __DIR__ . '/../assets/data/moon-features.json';
    $contents = file_get_contents($path);
    if (!is_string($contents)) throw new RuntimeException('No se pudo leer el catálogo lunar.');
    $catalog = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($catalog) || !is_array($catalog['features'] ?? null)) {
        throw new RuntimeException('El catálogo lunar no es válido.');
    }
    $catalog['features'] = array_values(array_filter(
        $catalog['features'],
        static fn(mixed $feature): bool => is_array($feature) && ($feature['layer'] ?? '') !== 'landings',
    ));
    foreach (interactiveMoonLandingCatalog()['landings'] as $landing) {
        $catalog['features'][] = [
            'name' => $landing['name'],
            'layer' => 'landings',
            'type' => 'Alunizaje',
            'lat' => $landing['latitude'],
            'lon' => $landing['longitude'],
            'importance' => $landing['importance'],
            'mission_year' => (int) substr((string) $landing['landing_date'], 0, 4),
            'landing_id' => $landing['id'],
        ];
    }
    return $catalog;
}

/** @return array<string,mixed> */
function interactiveMoonLandingCatalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) return $catalog;
    $path = __DIR__ . '/../assets/data/moon-landings.json';
    $contents = file_get_contents($path);
    if (!is_string($contents)) throw new RuntimeException('No se pudo leer el catálogo de alunizajes.');
    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || !is_array($decoded['landings'] ?? null) || !is_array($decoded['sources'] ?? null)) {
        throw new RuntimeException('El catálogo de alunizajes no es válido.');
    }
    $catalog = $decoded;
    return $catalog;
}
