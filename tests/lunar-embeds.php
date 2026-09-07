<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/lunar-embeds.php';
require_once __DIR__ . '/../includes/full-moon-size-embed.php';

function lunarEmbedsAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

lunarEmbedsAssert(lunarLibrationEmbedOptions([]) === ['illumination' => 'realistic', 'autoplay' => true, 'speed' => 1.0, 'zoom' => 1.0, 'controls' => true], 'Cambió el contrato inicial de libración.');
lunarEmbedsAssert(lunarLibrationEmbedOptions(['mode' => 'full', 'autoplay' => '0', 'speed' => '20', 'zoom' => '2', 'controls' => '0']) === ['illumination' => 'full', 'autoplay' => false, 'speed' => 8.0, 'zoom' => 1.35, 'controls' => false], 'No se validan las opciones de libración.');
$interactive = lunarInteractiveEmbedOptions(['rotation' => 'locked', 'controls' => '0', 'yaw' => '200', 'pitch' => '-80', 'zoom' => '1.4']);
lunarEmbedsAssert($interactive['rotation'] === 'locked' && $interactive['controls'] === false, 'No se validan bloqueo y controles.');
lunarEmbedsAssert($interactive['yaw'] === 180.0 && $interactive['pitch'] === -75.0 && $interactive['zoom'] === 1.4, 'No se limitan orientación y zoom.');
lunarEmbedsAssert($interactive['detail'] === 'auto' && lunarInteractiveEmbedOptions(['detail' => 'main'])['detail'] === 'main' && lunarInteractiveEmbedOptions(['detail' => 'more'])['detail'] === 'more', 'El embed no conserva los parámetros históricos de etiquetas o el nuevo default automático.');
$earthMoonDefaults = earthMoonEmbedOptions([]);
lunarEmbedsAssert($earthMoonDefaults['autoplay'] === false && $earthMoonDefaults['speed'] === 1.0 && $earthMoonDefaults['controls'] === true, 'Cambió el contrato inicial de fases Tierra–Luna.');
$earthMoonCustom = earthMoonEmbedOptions(['autoplay' => '1', 'speed' => '20', 'controls' => '0', 'moon_sun' => '4.1', 'moon_normal_y' => '-2', 'earth_ambient' => '0.2', 'earth_roughness' => '0.75']);
lunarEmbedsAssert($earthMoonCustom['autoplay'] === true && $earthMoonCustom['speed'] === 8.0 && $earthMoonCustom['controls'] === false, 'No se validan las opciones generales de fases Tierra–Luna.');
lunarEmbedsAssert($earthMoonCustom['moon_sun'] === 4.1 && $earthMoonCustom['moon_normal_y'] === -2.0 && $earthMoonCustom['earth_ambient'] === 0.2 && $earthMoonCustom['earth_roughness'] === 0.75, 'No se separan los parámetros Three.js de ambos cuerpos.');
$fullMoonOptions = fullMoonSizeEmbedOptions(['date' => '2026-09-06', 'controls' => '0'], new DateTimeZone('UTC'), new DateTimeImmutable('2026-01-01T12:00:00Z'));
lunarEmbedsAssert($fullMoonOptions['reference']->format('Y-m-d') === '2026-09-06' && $fullMoonOptions['controls'] === false, 'No se validan la fecha reproducible y los controles de tamaños lunares.');
lunarEmbedsAssert(abs(AstronomyEngine\MoonApparentSize::percentOfMean(AstronomyEngine\MoonApparentSize::MEAN_DISTANCE_KILOMETERS) - 100.0) < 1e-12, 'La referencia de diámetro lunar medio no equivale a 100 %.');
$eclipseDefaults = lunarEclipseEmbedOptions([]);
lunarEmbedsAssert($eclipseDefaults === ['autoplay' => false, 'speed' => 1.0, 'controls' => true, 'geometry' => true, 'sun_intensity' => 3.2, 'ambient_intensity' => 0.01, 'exposure' => 1.15, 'penumbra_darkness' => 0.18, 'umbra_darkness' => 0.955, 'copper_intensity' => 0.26, 'copper_color' => '#c95f32'], 'Cambió el contrato inicial del eclipse lunar.');
$eclipseCustom = lunarEclipseEmbedOptions(['autoplay' => '1', 'speed' => '20', 'controls' => '0', 'geometry' => '0', 'sun_intensity' => '20', 'ambient_intensity' => '-1', 'exposure' => '1.4', 'penumbra_darkness' => '0.3', 'umbra_darkness' => '0.9', 'copper_intensity' => '0.4', 'copper_color' => '#A04F31']);
lunarEmbedsAssert($eclipseCustom === ['autoplay' => true, 'speed' => 8.0, 'controls' => false, 'geometry' => false, 'sun_intensity' => 8.0, 'ambient_intensity' => 0.0, 'exposure' => 1.4, 'penumbra_darkness' => 0.3, 'umbra_darkness' => 0.9, 'copper_intensity' => 0.4, 'copper_color' => '#a04f31'], 'No se validan las opciones visuales del eclipse lunar.');

$location = ['latitude' => -34.53, 'longitude' => -58.48, 'timezone' => 'America/Argentina/Buenos_Aires', 'elevation_meters' => 0.0];
$testInstant = new DateTimeImmutable('2026-08-17T12:00:00-03:00');
$observer = lunarEmbedObserver($location);
$fullMoons = nextFullMoonSizes(new DateTimeImmutable('2026-09-06T00:00:00-03:00'), $location);
lunarEmbedsAssert(count($fullMoons) === 12, 'El comparador no devuelve exactamente doce lunas llenas.');
$previousFullMoonTimestamp = null;
foreach ($fullMoons as $fullMoon) {
    $timestamp = (new DateTimeImmutable($fullMoon['datetime']))->getTimestamp();
    lunarEmbedsAssert($previousFullMoonTimestamp === null || $timestamp > $previousFullMoonTimestamp, 'Las lunas llenas no están en orden cronológico estricto.');
    lunarEmbedsAssert(abs($fullMoon['size_percent'] - AstronomyEngine\MoonApparentSize::percentOfMean($fullMoon['distance_km'])) < 1e-9, 'El porcentaje no corresponde al diámetro aparente calculado.');
    lunarEmbedsAssert(abs($fullMoon['diameter_arcminutes'] - AstronomyEngine\MoonApparentSize::angularDiameterArcminutes($fullMoon['distance_km'])) < 1e-9, 'El diámetro angular no corresponde a la distancia del evento.');
    $previousFullMoonTimestamp = $timestamp;
}
$eclipsePayload = lunarEclipseEmbedPayload($observer, $eclipseDefaults);
lunarEmbedsAssert(($eclipsePayload['eclipse']['classification'] ?? null) === 'total', 'El caso de referencia no es un eclipse lunar total calculado.');
lunarEmbedsAssert(($eclipsePayload['eclipse']['maximum'] ?? null) === '2026-03-03T11:35:41+00:00', 'Cambió inesperadamente el máximo calculado del eclipse de referencia.');
lunarEmbedsAssert(($eclipsePayload['eclipse']['contacts']['P1'] ?? null) === '2026-03-03T08:46:25+00:00' && ($eclipsePayload['eclipse']['contacts']['P4'] ?? null) === '2026-03-03T14:25:02+00:00', 'El recorrido no conserva los contactos penumbrales calculados.');
lunarEmbedsAssert(isset($eclipsePayload['eclipse']['contacts']['U2'], $eclipsePayload['eclipse']['contacts']['U3']), 'Faltan los contactos de totalidad.');
lunarEmbedsAssert((float) $eclipsePayload['eclipse']['magnitudes']['umbral'] > 1.0, 'La magnitud umbral no permite totalidad.');
lunarEmbedsAssert((float) $eclipsePayload['eclipse']['shadow']['penumbra_radius_moon_radii'] > (float) $eclipsePayload['eclipse']['shadow']['umbra_radius_moon_radii'], 'Los radios de sombra no conservan su relación física.');
$cycle = lunarLibrationCycleBounds($testInstant);
$favoriteConfiguration = moonThreeRenderCatalogDefaults(favoriteMoonThreeRenderConfigurationCatalog());
$favoriteConfiguration['favorite.moon_three.sun_intensity'] = 4.6;
$favoriteConfiguration['favorite.moon_three.ambient_intensity'] = 0.17;
$favoriteConfiguration['favorite.moon_three.texture_contrast'] = 1.55;
$favoriteConfiguration['favorite.moon_three.background_color'] = '#17233a';
$favoriteConfiguration['favorite.moon_three.background_brightness'] = 0.12;
$favoriteConfiguration['favorite.moon_three.background_gradient'] = 0.2;
$payload = lunarLibrationEmbedPayload($testInstant, $observer, lunarLibrationEmbedOptions([]), $favoriteConfiguration);
$expectedGeometry = (new AstronomyEngine\MoonDiskAppearanceCalculator())->calculate($cycle['start'], $observer);
lunarEmbedsAssert(count($payload['samples'] ?? []) === 121, 'La animación no tiene la cantidad acordada de muestras.');
lunarEmbedsAssert(isset($payload['textures']['albedo'], $payload['appearance']['relief_mode']), 'La animación no reutiliza mapas y apariencia lunar.');
lunarEmbedsAssert(($payload['appearance']['sun_intensity'] ?? null) === 4.6 && ($payload['appearance']['ambient_intensity'] ?? null) === 0.17 && ($payload['appearance']['texture_contrast'] ?? null) === 1.55, 'La libración no consume la apariencia de La Luna de tu fecha favorita.');
lunarEmbedsAssert(($payload['wallpaper']['background_color'] ?? null) === '#17233a' && ($payload['wallpaper']['background_brightness'] ?? null) === 0.12 && ($payload['wallpaper']['background_gradient'] ?? null) === 0.2, 'La libración no conserva el fondo configurado de La Luna de tu fecha favorita.');
lunarEmbedsAssert((float) $payload['samples'][0]['disk_angle'] >= -180.0 && (float) $payload['samples'][0]['disk_angle'] <= 180.0, 'La orientación local quedó fuera del convenio angular.');
lunarEmbedsAssert(abs((float) $payload['samples'][0]['disk_angle'] - (float) $expectedGeometry['orientation']['lunar_north_screen_angle_degrees']) < 1e-9, 'La animación no usa la orientación topocéntrica local del motor.');
$maximumDiskStep = 0.0;
for ($index = 1; $index < count($payload['samples']); $index++) {
    $difference = fmod((float) $payload['samples'][$index]['disk_angle'] - (float) $payload['samples'][$index - 1]['disk_angle'] + 540.0, 360.0) - 180.0;
    $maximumDiskStep = max($maximumDiskStep, abs($difference));
}
lunarEmbedsAssert($maximumDiskStep < 8.0, 'La animación volvió a incorporar la rotación diaria rápida del campo local.');
lunarEmbedsAssert(($payload['animation']['loop_blend_fraction'] ?? null) === 0.02, 'El cierre no conserva el blend visual corto acordado.');
lunarEmbedsAssert(($payload['animation']['period'] ?? null) === 'luna nueva a luna nueva', 'El modo realista no recorre una lunación delimitada por fases calculadas.');
lunarEmbedsAssert($payload['samples'][0]['datetime'] === $payload['animation']['cycle_start'] && $payload['samples'][array_key_last($payload['samples'])]['datetime'] === $payload['animation']['cycle_end'], 'Las muestras no coinciden con las lunas nuevas limítrofes.');
lunarEmbedsAssert((float) $payload['samples'][0]['illumination'] < 0.002 && (float) $payload['samples'][array_key_last($payload['samples'])]['illumination'] < 0.002, 'El ciclo no comienza y termina cerca de Luna nueva.');

$fullPayload = lunarLibrationEmbedPayload($testInstant, $observer, lunarLibrationEmbedOptions(['mode' => 'full']), $favoriteConfiguration);
lunarEmbedsAssert(($fullPayload['animation']['period'] ?? null) === 'luna nueva a luna nueva', 'El modo iluminado no usa el mismo recorrido temporal realista.');
lunarEmbedsAssert(($fullPayload['animation']['loop_blend_fraction'] ?? null) === 0.02, 'El modo iluminado no usa el blend reducido.');
lunarEmbedsAssert($fullPayload['animation']['cycle_start'] === $payload['animation']['cycle_start'] && $fullPayload['animation']['cycle_end'] === $payload['animation']['cycle_end'], 'Los dos modos no comparten exactamente el mismo intervalo temporal.');

$earthMoonPayload = earthMoonEmbedPayload($testInstant, $observer, earthMoonEmbedOptions([]));
lunarEmbedsAssert(count($earthMoonPayload['samples'] ?? []) === 121, 'La comparación Tierra–Luna no cubre una lunación muestreada.');
lunarEmbedsAssert(($earthMoonPayload['comparison']['selected_datetime'] ?? '') === $testInstant->format(DateTimeInterface::ATOM), 'Fecha y hora elegidas no llegan al payload.');
lunarEmbedsAssert(abs((float) $earthMoonPayload['comparison']['earth_moon_apparent_diameter_ratio'] - 3.67) < 1e-9, 'No se conserva la escala angular Tierra–Luna.');
lunarEmbedsAssert(isset($earthMoonPayload['textures']['earth_albedo']), 'Falta la textura terrestre local.');
$principalSamples = [];
$maximumEarthDiskVariation = 0.0;
$maximumMoonDiskStep = 0.0;
foreach ($earthMoonPayload['samples'] as $sample) {
    $moonIllumination = (float) $sample['moon']['illumination'];
    $earthIllumination = (float) $sample['earth']['illumination'];
    lunarEmbedsAssert(abs($moonIllumination + $earthIllumination - 1.0) < 1e-9, 'Las fases observadas no son complementarias.');
    $cycleSeparation = abs(fmod((float) $sample['earth']['cycle_angle'] - (float) $sample['moon']['cycle_angle'] + 540.0, 360.0) - 180.0);
    lunarEmbedsAssert(abs($cycleSeparation - 180.0) < 1e-9, 'La Tierra no conserva una fase de ciclo opuesta a la Luna.');
    $maximumEarthDiskVariation = max($maximumEarthDiskVariation, abs((float) $sample['earth']['disk_angle'] - (float) $earthMoonPayload['samples'][0]['earth']['disk_angle']));
    foreach ([0.0, 90.0, 180.0, 270.0] as $angle) {
        $difference = abs(fmod((float) $sample['moon']['cycle_angle'] - $angle + 540.0, 360.0) - 180.0);
        if (!isset($principalSamples[(string) $angle]) || $difference < $principalSamples[(string) $angle]['difference']) {
            $principalSamples[(string) $angle] = ['difference' => $difference, 'sample' => $sample];
        }
    }
}
for ($index = 1; $index < count($earthMoonPayload['samples']); $index++) {
    $difference = fmod((float) $earthMoonPayload['samples'][$index]['moon']['disk_angle'] - (float) $earthMoonPayload['samples'][$index - 1]['moon']['disk_angle'] + 540.0, 360.0) - 180.0;
    $maximumMoonDiskStep = max($maximumMoonDiskStep, abs($difference));
}
lunarEmbedsAssert($maximumEarthDiskVariation < 1e-9, 'El eje terrestre vuelve a rotar durante el ciclo.');
lunarEmbedsAssert($maximumMoonDiskStep < 8.0, 'La Luna volvió a incorporar la rotación diaria rápida del campo local.');
lunarEmbedsAssert((float) $principalSamples['0']['sample']['moon']['illumination'] < 0.01 && (float) $principalSamples['0']['sample']['earth']['illumination'] > 0.99, 'Luna nueva no corresponde a Tierra llena.');
lunarEmbedsAssert((float) $principalSamples['180']['sample']['moon']['illumination'] > 0.99 && (float) $principalSamples['180']['sample']['earth']['illumination'] < 0.01, 'Luna llena no corresponde a Tierra nueva.');
foreach (['90', '270'] as $quarter) {
    lunarEmbedsAssert(abs((float) $principalSamples[$quarter]['sample']['moon']['illumination'] - 0.5) < 0.04, 'El cuarto lunar no queda cerca de 50%.');
    lunarEmbedsAssert(abs((float) $principalSamples[$quarter]['sample']['earth']['illumination'] - 0.5) < 0.04, 'El cuarto terrestre no queda cerca de 50%.');
}

$publicInteractive = file_get_contents(__DIR__ . '/../luna-interactiva.php');
$interactiveModule = file_get_contents(__DIR__ . '/../assets/js/interactive-moon.js');
$interactiveEmbed = file_get_contents(__DIR__ . '/../embeds/luna-interactiva.php');
$librationEmbed = file_get_contents(__DIR__ . '/../embeds/libracion-lunar.php');
$librationModule = file_get_contents(__DIR__ . '/../assets/js/lunar-libration-widget.js');
$earthMoonEmbed = file_get_contents(__DIR__ . '/../embeds/fases-tierra-luna.php');
$fullMoonSizeEmbed = file_get_contents(__DIR__ . '/../embeds/lunas-llenas-tamano.php');
$lunarSceneEmbed = file_get_contents(__DIR__ . '/../embeds/escena-lunar.php');
$earthMoonModule = file_get_contents(__DIR__ . '/../assets/js/earth-moon-phases-widget.js');
$moonThreeModule = file_get_contents(__DIR__ . '/../assets/js/moon-three-render.js');
$eclipseEmbed = file_get_contents(__DIR__ . '/../embeds/eclipse-lunar.php');
$eclipseModule = file_get_contents(__DIR__ . '/../assets/js/lunar-eclipse-widget.js');
$admin = file_get_contents(__DIR__ . '/../admin/widgets-lunares/index.php');
$widgetStyles = file_get_contents(__DIR__ . '/../assets/css/lunar-widgets.css');
lunarEmbedsAssert(is_string($publicInteractive) && str_contains($publicInteractive, '<h1>Luna interactiva</h1>'), 'La sección pública de Luna interactiva fue reemplazada.');
lunarEmbedsAssert(is_string($interactiveEmbed) && str_contains($interactiveEmbed, "assets/js/interactive-moon.js") && str_contains($interactiveEmbed, 'is-rotation-locked'), 'El embed interactivo no reutiliza el motor o no admite bloqueo.');
lunarEmbedsAssert(str_contains($interactiveEmbed, "'gazetteer'") && str_contains($interactiveEmbed, 'value="auto"'), 'El embed no comparte el catálogo progresivo o el selector automático.');
lunarEmbedsAssert(is_string($librationEmbed) && str_contains($librationEmbed, 'data-libration-widget'), 'No existe la superficie embebible de libración.');
lunarEmbedsAssert(is_string($librationModule) && str_contains($librationModule, 'requestAnimationFrame') && str_contains($librationModule, 'sampleAt(samples'), 'La libración no se interpola y renderiza en cliente.');
lunarEmbedsAssert(str_contains($librationModule, "payload.animation.illumination === 'full'") && str_contains($librationModule, 'loopSampleAt'), 'Faltan iluminación completa o cierre continuo.');
lunarEmbedsAssert(str_contains($librationModule,
    'progress = (Number(container.dataset.animationPhase || progress) + phaseIncrement) % 1;'),
    'El tiempo de la animación no avanza siempre hacia adelante.');
lunarEmbedsAssert(str_contains($librationModule, 'captureStream(30)') && str_contains($librationModule, 'new MediaRecorder'), 'La exportación no captura el canvas como WebM en frontend.');
lunarEmbedsAssert(str_contains($librationModule, 'baseCameraDistance / Math.min(1, camera.aspect) / cameraZoom / configuredSize'), 'El marco vertical no compensa su campo horizontal ni combina zoom con el tamaño configurado.');
lunarEmbedsAssert(str_contains($librationModule, 'paintWallpaperBackground(context, canvas.width, canvas.height, payload.wallpaper)') && str_contains($librationModule, 'paintLibrationGlow('), 'La previsualización y el video no componen el fondo configurado de la Luna favorita.');
lunarEmbedsAssert(!str_contains($librationModule, "background.addColorStop(0, '#0b1325')"), 'La exportación conserva un fondo hardcodeado.');
lunarEmbedsAssert(str_contains($admin, 'data-export-video="16:9"') && str_contains($admin, 'data-export-video="9:16"'), 'Administración no ofrece ambos formatos de video.');
lunarEmbedsAssert(str_contains($admin, 'La Luna de tu fecha favorita'), 'Administración no informa el origen de la apariencia de libración.');
lunarEmbedsAssert(is_string($admin) && str_contains($admin, 'data-widget-preview') && str_contains($admin, 'data-widget-iframe'), 'Administración no ofrece preview e iframe.');
lunarEmbedsAssert(is_string($widgetStyles) && str_contains($widgetStyles, 'fieldset[hidden] { display: none; }'), 'CSS vuelve visibles las opciones del widget no seleccionado.');
lunarEmbedsAssert(is_string($interactiveModule) && str_contains($interactiveModule, 'data-moon-reset-view'), 'El embed dejó de ser compatible con el motor interactivo.');
lunarEmbedsAssert(is_string($earthMoonEmbed) && str_contains($earthMoonEmbed, 'noindex,nofollow,noarchive') && !str_contains($earthMoonEmbed, 'renderStoreNavigation'), 'El widget Tierra–Luna no está aislado o indexable.');
lunarEmbedsAssert(is_string($earthMoonModule) && str_contains($earthMoonModule, 'earthLight.position.copy(moonSunScreen).multiplyScalar(-5)'), 'La iluminación terrestre no es opuesta a la lunar.');
lunarEmbedsAssert(str_contains($earthMoonModule, 'payload.comparison.moon_exposure') && str_contains($earthMoonModule, 'payload.comparison.earth_exposure') && str_contains($earthMoonModule, 'payload.comparison.moon_normal_x'), 'El render no consume la configuración Three.js independiente.');
lunarEmbedsAssert(str_contains($widgetStyles, 'calc(var(--moon-diameter) * 3.67)') && str_contains($widgetStyles, '@media (max-width: 600px)'), 'No se conserva la escala aparente o el diseño responsive.');
lunarEmbedsAssert(str_contains($admin, 'data-widget-options="earth-moon"') && str_contains($admin, 'Fases Tierra–Luna'), 'Administración no permite configurar el nuevo widget.');
lunarEmbedsAssert(is_string($fullMoonSizeEmbed) && str_contains($fullMoonSizeEmbed, 'data-full-moon-sizes') && str_contains($fullMoonSizeEmbed, 'noindex,nofollow,noarchive'), 'No existe el widget aislado y no indexable de tamaños lunares.');
lunarEmbedsAssert(str_contains($admin, 'data-widget-options="full-moon-sizes"') && str_contains($admin, 'Tamaño de próximas lunas llenas'), 'Administración no integra el comparador de tamaños lunares.');
lunarEmbedsAssert(str_contains($widgetStyles, 'repeat(6, minmax(0, 1fr))') && str_contains($widgetStyles, 'repeat(3, minmax(0, 1fr))'), 'El comparador no conserva 2 × 6 en escritorio y adaptación móvil.');
lunarEmbedsAssert(str_contains($fullMoonSizeEmbed, '--size-scale:') && str_contains($widgetStyles, 'scale(var(--size-scale))'), 'La escala visual no usa directamente la proporción del diámetro calculado.');
lunarEmbedsAssert(str_contains($widgetStyles, '.full-moon-sizes__reference::before') && str_contains($widgetStyles, '.full-moon-sizes__reference::after'), 'Faltan los límites visuales fijos del diámetro de referencia.');
lunarEmbedsAssert(str_contains($widgetStyles, 'top: -14%; bottom: -14%') && str_contains($widgetStyles, 'overflow: visible'), 'Las referencias del 100 % pueden volver a quedar completamente ocultas o recortadas por el disco.');
lunarEmbedsAssert(is_string($lunarSceneEmbed) && str_contains($lunarSceneEmbed, 'data-lunar-scene') && str_contains($lunarSceneEmbed, 'noindex,nofollow,noarchive'), 'No existe la escena lunar interna y no indexable.');
lunarEmbedsAssert(str_contains($admin, 'data-widget-options="lunar-scene"') && str_contains($admin, 'Escena lunar configurable'), 'Administración no integra la escena lunar configurable.');
lunarEmbedsAssert(is_string($moonThreeModule) && str_contains($moonThreeModule, 'earthshineIntensity') && str_contains($moonThreeModule, 'shadowSkyMix') && str_contains($moonThreeModule, 'darkSideOpacity') && str_contains($moonThreeModule, 'darkLimbSoftness') && str_contains($moonThreeModule, 'edgeSoftness') && str_contains($moonThreeModule, 'terminatorSoftness') && str_contains($moonThreeModule, 'litTint'), 'El renderer compartido no admite la integración atmosférica opcional.');
lunarEmbedsAssert(str_contains($admin, 'data-lunar-scene-presets') && str_contains($admin, 'data-preset-action="duplicate"') && str_contains($admin, 'data-preset-action="delete"'), 'La administración no ofrece el ciclo completo de presets de escena lunar.');
lunarEmbedsAssert(str_contains($admin, 'Luna · Three.js') && str_contains($admin, 'Tierra · Three.js') && str_contains($admin, 'data-option="earth_roughness"'), 'Administración no separa los controles visuales de Luna y Tierra.');
lunarEmbedsAssert(is_string($eclipseEmbed) && str_contains($eclipseEmbed, 'data-lunar-eclipse-widget') && str_contains($eclipseEmbed, 'noindex,nofollow,noarchive'), 'No existe la superficie embebible y no indexable del eclipse lunar.');
lunarEmbedsAssert(is_string($eclipseModule) && str_contains($eclipseModule, 'eclipsePenumbraRadius') && str_contains($eclipseModule, 'eclipseUmbraRadius') && str_contains($eclipseModule, 'vec3 copper'), 'El render no distingue penumbra, umbra y color cobrizo.');
lunarEmbedsAssert(str_contains($eclipseModule, 'circleOverlapFraction') && str_contains($eclipseModule, 'eclipseUmbraCoverage') && str_contains($eclipseModule, 'smoothstep(0.84, 1.0, eclipseUmbraCoverage)'), 'El cobre no depende de la fracción del disco cubierta por la umbra.');
lunarEmbedsAssert(str_contains($eclipseModule, 'umbraDepth') && str_contains($eclipseModule, 'atmosphericDepth') && str_contains($eclipseModule, 'darkUmbra'), 'El shader no separa profundidad, oscuridad umbral y transmisión atmosférica.');
lunarEmbedsAssert(str_contains($eclipseModule, "geometryMoon.setAttribute('cy'") && str_contains($eclipseEmbed, 'M455 116 L485 4'), 'El esquema no muestra el cruce transversal de la sombra.');
lunarEmbedsAssert(str_contains($admin, 'data-widget-options="eclipse"') && str_contains($admin, 'Eclipse lunar'), 'Administración no permite probar el eclipse lunar.');
lunarEmbedsAssert(str_contains($admin, 'data-option="penumbra_darkness"') && str_contains($admin, 'data-option="umbra_darkness"') && str_contains($admin, 'data-option="copper_color"'), 'Administración no expone los parámetros visuales del eclipse.');
$contentEditor = file_get_contents(__DIR__ . '/../admin/contenidos/index.php');
lunarEmbedsAssert(is_string($contentEditor) && str_contains($contentEditor, "'label' => 'Insertar embed'") && str_contains($contentEditor, "'cursor_offset' => -3"), 'El editor no muestra el rótulo nuevo o cambió la inserción controlada.');

echo "lunar-embeds: OK\n";
