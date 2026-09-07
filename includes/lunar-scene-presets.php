<?php

declare(strict_types=1);

require_once __DIR__ . '/lunar-scene-embed.php';

const LUNAR_SCENE_PRESET_CONFIGURATION_KEYS = [
    'fecha', 'hora', 'location_mode', 'lit_brightness', 'lit_tint', 'dark_brightness',
    'texture_saturation', 'texture_contrast', 'texture_resolution', 'earthshine_intensity',
    'shadow_sky_mix', 'dark_side_opacity', 'dark_limb_softness', 'halo_intensity', 'edge_softness', 'terminator_softness', 'sky_mode',
    'sky_center_color', 'sky_center_brightness', 'sky_edge_color', 'sky_edge_brightness',
    'sky_gradient_radius',
];

function lunarScenePresetText(mixed $value, int $maximum, string $label, bool $required): string
{
    $text = trim((string) $value);
    if ($required && $text === '') throw new InvalidArgumentException($label . ' es obligatorio.');
    if (strlen($text) > $maximum) throw new InvalidArgumentException($label . ' es demasiado largo.');
    return $text;
}

/** @return array<string,string> */
function lunarScenePresetNormalizeConfiguration(array $configuration): array
{
    $keys = array_keys($configuration);
    sort($keys);
    $expectedKeys = LUNAR_SCENE_PRESET_CONFIGURATION_KEYS;
    sort($expectedKeys);
    if (array_diff($keys, $expectedKeys) !== []) throw new InvalidArgumentException('La configuración del preset contiene parámetros desconocidos.');
    $timezone = new DateTimeZone('America/Argentina/Buenos_Aires');
    $fallback = new DateTimeImmutable('now', $timezone);
    $options = lunarSceneEmbedOptions($configuration, $fallback);
    if ($options['corrected']) throw new InvalidArgumentException('La fecha o la hora del preset no son válidas.');
    if (($configuration['location_mode'] ?? 'active') !== 'active') {
        throw new InvalidArgumentException('El modo de ubicación no es válido.');
    }
    $normalized = [];
    foreach (LUNAR_SCENE_PRESET_CONFIGURATION_KEYS as $key) {
        $value = match ($key) {
            'fecha' => $options['date'],
            'hora' => $options['time'],
            'location_mode' => 'active',
            default => $options[$key],
        };
        $normalized[$key] = is_float($value) ? rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.') : (string) $value;
    }
    return $normalized;
}

/** @return list<array<string,mixed>> */
function lunarScenePresets(PDO $connection): array
{
    $rows = $connection->query('SELECT id,name,description,configuration_json,created_at,updated_at FROM lunar_scene_presets ORDER BY name,id')->fetchAll(PDO::FETCH_ASSOC);
    return array_map('lunarScenePresetPublicRow', $rows);
}

function lunarScenePreset(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT id,name,description,configuration_json,created_at,updated_at FROM lunar_scene_presets WHERE id=:id');
    $statement->execute([':id' => $id]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? lunarScenePresetPublicRow($row) : null;
}

function lunarScenePresetPublicRow(array $row): array
{
    $configuration = json_decode((string) ($row['configuration_json'] ?? ''), true);
    if (!is_array($configuration)) $configuration = [];
    return [
        'id' => (int) $row['id'], 'name' => (string) $row['name'], 'description' => (string) ($row['description'] ?? ''),
        'configuration' => $configuration, 'created_at' => (string) $row['created_at'], 'updated_at' => (string) $row['updated_at'],
    ];
}

function lunarScenePresetCreate(PDO $connection, mixed $name, mixed $description, array $configuration): array
{
    $statement = $connection->prepare('INSERT INTO lunar_scene_presets (name,description,configuration_json) VALUES (:name,:description,:configuration)');
    $statement->execute([
        ':name' => lunarScenePresetText($name, 100, 'El nombre', true),
        ':description' => lunarScenePresetText($description, 300, 'La descripción', false),
        ':configuration' => json_encode(lunarScenePresetNormalizeConfiguration($configuration), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    return lunarScenePreset($connection, (int) $connection->lastInsertId()) ?? throw new RuntimeException('No se pudo recuperar el preset creado.');
}

function lunarScenePresetUpdate(PDO $connection, int $id, mixed $name, mixed $description, array $configuration): array
{
    $statement = $connection->prepare('UPDATE lunar_scene_presets SET name=:name,description=:description,configuration_json=:configuration,updated_at=CURRENT_TIMESTAMP WHERE id=:id');
    $statement->execute([
        ':id' => $id, ':name' => lunarScenePresetText($name, 100, 'El nombre', true),
        ':description' => lunarScenePresetText($description, 300, 'La descripción', false),
        ':configuration' => json_encode(lunarScenePresetNormalizeConfiguration($configuration), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    if ($statement->rowCount() === 0 && lunarScenePreset($connection, $id) === null) throw new InvalidArgumentException('El preset elegido no existe.');
    return lunarScenePreset($connection, $id) ?? throw new RuntimeException('No se pudo recuperar el preset actualizado.');
}

function lunarScenePresetDelete(PDO $connection, int $id): void
{
    $statement = $connection->prepare('DELETE FROM lunar_scene_presets WHERE id=:id');
    $statement->execute([':id' => $id]);
    if ($statement->rowCount() !== 1) throw new InvalidArgumentException('El preset elegido no existe.');
}
