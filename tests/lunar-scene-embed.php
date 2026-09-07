<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/lunar-embeds.php';
require_once __DIR__ . '/../includes/lunar-scene-embed.php';

function lunarSceneAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$location = ['name' => 'Buenos Aires', 'latitude' => -34.6037, 'longitude' => -58.3816, 'timezone' => 'America/Argentina/Buenos_Aires', 'elevation_meters' => 25.0];
$current = new DateTimeImmutable('2026-09-06T12:34:00-03:00');
$options = lunarSceneEmbedOptions([
    'fecha' => '2026-09-20', 'hora' => '18:45', 'lit_brightness' => '4.2', 'dark_brightness' => '0.12',
    'texture_saturation' => '1.3', 'texture_contrast' => '1.4', 'texture_resolution' => 'low',
    'earthshine_intensity' => '0.28', 'shadow_sky_mix' => '0.35', 'dark_side_opacity' => '0.62', 'dark_limb_softness' => '0.14',
    'halo_intensity' => '0.42', 'edge_softness' => '0.035',
    'terminator_softness' => '0.21', 'lit_tint' => '#ffd4a3',
    'sky_mode' => 'custom', 'sky_center_color' => '#315b88', 'sky_center_brightness' => '1.2',
    'sky_edge_color' => '#182840', 'sky_edge_brightness' => '0.5', 'sky_gradient_radius' => '72',
], $current);
lunarSceneAssert($options['instant']->format('Y-m-d H:i') === '2026-09-20 18:45', 'La escena no conserva fecha y hora en la URL.');
lunarSceneAssert($options['lit_brightness'] === 4.2 && $options['dark_brightness'] === 0.12, 'No se validan por separado las zonas iluminada y oscura.');
lunarSceneAssert($options['texture_resolution'] === 'low' && $options['sky_mode'] === 'custom', 'No se validan resolución o modo de cielo.');
$payload = lunarSceneEmbedPayload($options['instant'], $location, $options);
lunarSceneAssert(($payload['moon']['appearance']['sun_intensity'] ?? null) === 4.2 && ($payload['moon']['appearance']['ambient_intensity'] ?? null) === 0.12, 'La apariencia lunar no consume sus controles.');
lunarSceneAssert(str_contains((string) ($payload['moon']['textures']['relief'] ?? ''), 'ldem_3_normal.png'), 'La resolución baja no selecciona el mapa existente correspondiente.');
$highOptions = lunarSceneEmbedOptions(['fecha' => '2026-09-20', 'hora' => '18:45', 'texture_resolution' => 'high'], $current);
$highPayload = lunarSceneEmbedPayload($highOptions['instant'], $location, $highOptions);
lunarSceneAssert(str_contains((string) ($highPayload['moon']['textures']['relief'] ?? ''), 'ldem_8_normal.png'), 'La resolución alta no selecciona el mapa existente correspondiente.');
$observer = lunarEmbedObserver($location);
$expected = (new AstronomyEngine\MoonDiskAppearanceCalculator())->calculate($options['instant'], $observer);
lunarSceneAssert(abs((float) $payload['moon']['geometry']['orientation']['lunar_north_screen_angle_degrees'] - (float) $expected['orientation']['lunar_north_screen_angle_degrees']) < 1e-9, 'La escena no conserva la orientación observacional del motor.');
lunarSceneAssert($payload['scene']['sky_center_color'] === '#3b6da3', 'El color y brillo manuales del centro no son reproducibles.');
lunarSceneAssert($payload['scene']['sky_edge_color'] === '#0c1420', 'El color y brillo manuales del borde no son reproducibles.');
lunarSceneAssert($payload['scene']['sky_gradient_radius_percent'] === 72.0, 'La extensión del gradiente no es reproducible.');
lunarSceneAssert($payload['moon']['appearance']['earthshine_intensity'] === 0.28, 'La luz cenicienta no llega al render lunar.');
lunarSceneAssert($payload['moon']['appearance']['shadow_sky_mix'] === 0.35 && $payload['moon']['appearance']['scene_sky_color'] === '#3b6da3', 'La cara nocturna no recibe la fusión con el cielo central.');
lunarSceneAssert($payload['moon']['appearance']['dark_side_opacity'] === 0.62 && $payload['moon']['appearance']['dark_limb_softness'] === 0.14, 'La composición alfa controlada de la cara nocturna no llega al render.');
lunarSceneAssert($payload['scene']['halo_intensity'] === 0.42 && $payload['moon']['appearance']['edge_softness'] === 0.035, 'Halo o suavidad de borde no son reproducibles.');
lunarSceneAssert($payload['moon']['appearance']['terminator_softness'] === 0.21 && $payload['moon']['appearance']['lit_tint'] === '#ffd4a3', 'Suavidad del terminador o tinte iluminado no son reproducibles.');
$night = lunarSceneNaturalSkyColor(-25.0);
$twilight = lunarSceneNaturalSkyColor(-6.0);
$day = lunarSceneNaturalSkyColor(25.0);
lunarSceneAssert($night !== $twilight && $twilight !== $day && $night !== $day, 'El cielo automático no distingue noche, crepúsculo y día.');

echo "lunar-scene-embed: OK\n";
