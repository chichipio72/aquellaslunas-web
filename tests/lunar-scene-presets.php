<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/lunar-embeds.php';
require_once __DIR__ . '/../includes/lunar-scene-presets.php';

function lunarPresetAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$connection = new PDO('sqlite::memory:');
$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$connection->exec('CREATE TABLE lunar_scene_presets (id INTEGER PRIMARY KEY AUTOINCREMENT,name VARCHAR(100) NOT NULL,description VARCHAR(300) NOT NULL DEFAULT \'\',configuration_json TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$configuration = [
    'fecha' => '2026-09-12', 'hora' => '06:25', 'location_mode' => 'active',
    'lit_brightness' => '4.1', 'lit_tint' => '#ffd4a3', 'dark_brightness' => '0.08',
    'texture_saturation' => '1.1', 'texture_contrast' => '1.25', 'texture_resolution' => 'high',
    'earthshine_intensity' => '0.32', 'shadow_sky_mix' => '0.48', 'dark_side_opacity' => '0.66', 'dark_limb_softness' => '0.12', 'halo_intensity' => '0.22',
    'edge_softness' => '0.03', 'terminator_softness' => '0.2', 'sky_mode' => 'custom',
    'sky_center_color' => '#91b7d4', 'sky_center_brightness' => '1.1',
    'sky_edge_color' => '#496a8a', 'sky_edge_brightness' => '0.9', 'sky_gradient_radius' => '74',
];
$created = lunarScenePresetCreate($connection, 'Amanecer fino', 'Prueba de integración atmosférica.', $configuration);
lunarPresetAssert($created['id'] === 1 && $created['configuration']['lit_tint'] === '#ffd4a3', 'No se creó el preset completo.');
lunarPresetAssert(array_keys($created['configuration']) === LUNAR_SCENE_PRESET_CONFIGURATION_KEYS, 'El preset no conserva el contrato exacto de configuración.');
$updated = lunarScenePresetUpdate($connection, 1, 'Amanecer cálido', '', array_replace($configuration, ['earthshine_intensity' => '0.4']));
lunarPresetAssert($updated['name'] === 'Amanecer cálido' && $updated['configuration']['earthshine_intensity'] === '0.4', 'No se actualizó el preset.');
$duplicate = lunarScenePresetCreate($connection, $updated['name'] . ' (copia)', $updated['description'], $updated['configuration']);
lunarPresetAssert($duplicate['id'] === 2 && count(lunarScenePresets($connection)) === 2, 'No se pudo duplicar el preset.');
lunarScenePresetDelete($connection, 1);
lunarPresetAssert(lunarScenePreset($connection, 1) === null && count(lunarScenePresets($connection)) === 1, 'No se eliminó únicamente el preset elegido.');
$invalidLocationRejected = false;
try { lunarScenePresetNormalizeConfiguration(array_replace($configuration, ['location_mode' => 'coordinates'])); } catch (InvalidArgumentException) { $invalidLocationRejected = true; }
lunarPresetAssert($invalidLocationRejected, 'El preset aceptó un modo de ubicación no soportado.');
$migratedConfiguration = lunarScenePresetNormalizeConfiguration(array_diff_key($configuration, ['dark_side_opacity' => true, 'dark_limb_softness' => true]));
lunarPresetAssert($migratedConfiguration['dark_side_opacity'] === '0.82' && $migratedConfiguration['dark_limb_softness'] === '0.08', 'Un preset anterior no completa los nuevos parámetros con defaults seguros.');
$unknownRejected = false;
try { lunarScenePresetNormalizeConfiguration($configuration + ['unknown' => '1']); } catch (InvalidArgumentException) { $unknownRejected = true; }
lunarPresetAssert($unknownRejected, 'El preset aceptó un parámetro desconocido.');

echo "lunar-scene-presets: OK\n";
