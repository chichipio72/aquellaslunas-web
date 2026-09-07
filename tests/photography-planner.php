<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/photography-geometry.php';
require_once __DIR__ . '/../includes/photography-scene.php';
require_once __DIR__ . '/../includes/photography-simulation.php';
function photographyTest(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$phaseCases = [
    [0.01, true, 'Creciente muy fina'], [0.10, true, 'Creciente'], [0.25, true, 'Creciente'],
    [0.50, true, 'Cuarto creciente'], [0.75, true, 'Gibosa creciente'], [1.0, true, 'Luna llena'],
    [0.50, false, 'Cuarto menguante'], [0.10, false, 'Menguante'],
];
foreach ($phaseCases as [$fraction, $waxing, $expected]) photographyTest(photographyMoonPhaseLabel($fraction, $waxing) === $expected, 'Clasificación de fase incorrecta para ' . $fraction . '.');
photographyTest(photographyMoonPhaseLabel(0.012, true) !== 'Luna nueva', 'Una hoz de 1,2 % no debe clasificarse como Luna nueva.');
$fov = photographyFieldOfView(36.0, 24.0, 50.0, 'horizontal');
photographyTest(abs($fov['horizontal_degrees'] - 39.5978) < 0.001, 'Campo horizontal Full Frame incorrecto.');
photographyTest(abs($fov['vertical_degrees'] - 26.9915) < 0.001, 'Campo vertical Full Frame incorrecto.');
$vertical = photographyFieldOfView(36.0, 24.0, 50.0, 'vertical');
photographyTest(abs($vertical['horizontal_degrees'] - $fov['vertical_degrees']) < 1e-9, 'La orientación no intercambia el sensor.');
$orientationState = ['moon' => ['angular_diameter_degrees' => 0.5], 'horizon' => ['relative_y_degrees' => 0.0], 'objects' => [['id' => 'antares', 'relative_x_degrees' => 8.8, 'relative_y_degrees' => 1.1]]];
photographyTest(photographyCompositionOrientation($orientationState, ['antares'], false, 'vertical') === 'horizontal', 'Una composición Luna + Antares predominantemente horizontal no abre apaisada.');
$orientationState['objects'][0]['relative_x_degrees'] = 1.0; $orientationState['objects'][0]['relative_y_degrees'] = 8.8;
photographyTest(photographyCompositionOrientation($orientationState, ['antares'], false, 'horizontal') === 'vertical', 'Una composición predominantemente vertical no abre en vertical.');
$orientationState['objects'][0]['relative_x_degrees'] = 5.0; $orientationState['objects'][0]['relative_y_degrees'] = 5.0;
photographyTest(photographyCompositionOrientation($orientationState, ['antares'], false, 'vertical') === 'vertical', 'Una composición equilibrada no respeta la preferencia como desempate.');
$location = ['latitude' => -34.6037, 'longitude' => -58.3816, 'timezone' => 'America/Argentina/Buenos_Aires', 'elevation_meters' => 25.0];
$instant = new DateTimeImmutable('2026-08-20 20:00:00', new DateTimeZone($location['timezone']));
$state = photographyAstronomicalScene($instant, $location, ['venus']);
photographyTest(isset($state['moon']['bright_limb_angle_degrees'], $state['sun']['separation_from_moon_degrees']), 'Falta geometría continua de la escena.');
$northernState = photographyAstronomicalScene($instant, ['latitude' => 34.6037, 'longitude' => -58.3816, 'timezone' => 'America/Argentina/Buenos_Aires', 'elevation_meters' => 25.0], []);
photographyTest(abs($state['moon']['bright_limb_angle_degrees'] - $northernState['moon']['bright_limb_angle_degrees']) > 1.0, 'La orientación aparente no debe quedar hardcodeada por fase o hemisferio.');
photographyTest(count(array_filter($state['objects'], static fn(array $object): bool => $object['selected'])) === 1, 'La selección de astros no se conserva.');
$starState = photographyAstronomicalScene($instant, $location, ['spica']);
$spica = array_values(array_filter($starState['objects'], static fn(array $object): bool => $object['id'] === 'spica'))[0] ?? null;
photographyTest(is_array($spica) && $spica['kind'] === 'star' && $spica['selected'] === true, 'Spica no reutiliza el catálogo estelar existente.');
photographyTest(isset($spica['altitude_degrees'], $spica['azimuth_degrees'], $spica['separation_from_moon_degrees'], $spica['relative_x_degrees']), 'La estrella no conserva el estado fotográfico continuo.');
$candidateState = $state;
$candidateState['moon']['altitude_degrees'] = 35.0;
foreach ($candidateState['objects'] as &$candidateObject) {
    $candidateObject['selected'] = false;
    $candidateObject['separation_from_moon_degrees'] = $candidateObject['id'] === 'venus' ? 2.4 : 18.0;
}
unset($candidateObject);
$candidates = photographyElementCandidates($candidateState, false);
photographyTest($candidates['show_horizon'] === false && array_column($candidates['objects'], 'id') === ['venus'], 'El selector no limita horizonte y astros por cercanía lunar.');
$candidateState['objects'][array_key_first(array_filter($candidateState['objects'], static fn(array $object): bool => $object['id'] === 'spica'))]['selected'] = true;
$explicitCandidates = photographyElementCandidates($candidateState, true);
photographyTest($explicitCandidates['show_horizon'] === true && in_array('spica', array_column($explicitCandidates['objects'], 'id'), true), 'El selector descartó una selección explícita fuera del umbral.');
$classification = photographySceneClassification($state, true);
photographyTest(isset($classification['primary_scene_label']) && in_array('Con horizonte', $classification['labels'], true), 'El clasificador extendido perdió escena o etiquetas independientes.');
$eclipseInstant = new DateTimeImmutable('2026-08-12T17:37:00-03:00');
$eclipse = photographyEclipseStateFromEvents([[
    'type' => 'eclipse', 'subtype' => 'solar_eclipse',
    'details' => ['solar_eclipse_global' => ['global_type' => 'total'], 'solar_eclipse_local' => [
        'visibility_classification' => 'visible_partial', 'max_magnitude' => 0.8,
        'contacts' => [['code' => 'C1', 'datetime' => '2026-08-12T17:00:00-03:00'], ['code' => 'MAX', 'datetime' => '2026-08-12T17:36:00-03:00'], ['code' => 'C4', 'datetime' => '2026-08-12T18:00:00-03:00']],
    ]],
]], $eclipseInstant);
photographyTest($eclipse['active'] === true && $eclipse['type'] === 'solar' && $eclipse['stage'] === 'máximo' && $eclipse['source'] === 'astronomy_events', 'Fotografía no consume correctamente el contrato validado de eclipses.');
$framing = photographyFraming($state, ['venus'], true, 36.0, 24.0, 200.0, 'horizontal');
photographyTest($framing['maximum_focal_mm'] > 0 && $framing['suggested_focal_mm'] < $framing['maximum_focal_mm'], 'Las focales geométricas no son coherentes.');
photographyTest(count(array_filter($framing['points'], static fn(array $point): bool => !$point['in_frame'] && $point['outside_by_degrees'] > 0)) > 0, 'Falta la distancia angular de los elementos fuera del cuadro.');
$moonAim = photographyFraming($state, ['venus'], true, 36.0, 24.0, 200.0, 'horizontal', 'moon');
photographyTest($moonAim['center']['x_degrees'] === 0.0 && $moonAim['center']['y_degrees'] === 0.0, 'Centrar en la Luna no cambia solamente el apuntado.');
photographyTest($moonAim['points'][0]['in_frame'] === true && $moonAim['maximum_focal_mm'] === $framing['maximum_focal_mm'], 'Centrar en la Luna alteró la geometría de la escena.');
$manualAim = photographyFraming($state, ['venus'], true, 36.0, 24.0, 200.0, 'horizontal', 'manual', 2.5, -1.25);
photographyTest(abs($manualAim['center']['x_degrees'] - ($manualAim['automatic_center']['x_degrees'] + 2.5)) < 1e-9, 'El offset horizontal manual no es angular.');
photographyTest(abs($manualAim['center']['y_degrees'] - ($manualAim['automatic_center']['y_degrees'] - 1.25)) < 1e-9, 'El offset vertical manual no es angular.');
$rolled = photographyFraming($state, ['venus'], true, 36.0, 24.0, 200.0, 'horizontal', 'automatic', 0.0, 0.0, 30.0);
photographyTest(abs($rolled['roll_degrees'] - 30.0) < 1e-9, 'El giro no quedó en el contrato reproducible del encuadre.');
photographyTest(photographyCameraRollOffset(1.0, 0.0, 90.0)['y'] < -0.999, 'La transformación del giro no rota los ejes de cámara esperados.');
$partialAngle = deg2rad(30.0);
$partialMoon = photographyFrameElementVisibility(['id' => 'moon', 'x' => cos($partialAngle) * 5.1, 'y' => sin($partialAngle) * 5.1, 'diameter' => 0.5], ['x_degrees' => 0.0, 'y_degrees' => 0.0], ['horizontal_degrees' => 10.0, 'vertical_degrees' => 6.0], 30.0);
photographyTest($partialMoon['visibility'] === 'partial' && $partialMoon['in_frame'] === true, 'Una Luna parcialmente visible con roll fue marcada fuera del encuadre.');
$rolledPoint = photographyFrameElementVisibility(['id' => 'antares', 'x' => cos($partialAngle) * 4.5, 'y' => sin($partialAngle) * 4.5, 'diameter' => 0.08], ['x_degrees' => 0.0, 'y_degrees' => 0.0], ['horizontal_degrees' => 10.0, 'vertical_degrees' => 6.0], 30.0);
photographyTest($rolledPoint['visibility'] === 'inside', 'Un astro proyectado dentro con roll fue marcado fuera del encuadre.');
photographyTest(photographyHorizonIntersectsFrame(0.0, ['x_degrees' => 0.0, 'y_degrees' => 0.0], ['horizontal_degrees' => 10.0, 'vertical_degrees' => 6.0], 30.0), 'El horizonte inclinado no intersecta el sensor según su geometría final.');
$plannerSource = file_get_contents(__DIR__ . '/../assets/js/photography-planner.js');
$photographyStyles = file_get_contents(__DIR__ . '/../assets/css/photography.css');
$photographyPageSource = file_get_contents(__DIR__ . '/../fotografia.php');
photographyTest(is_string($plannerSource) && str_contains($plannerSource, 'photography-scene-layer'), 'El renderer no separa la escena rotada de los overlays.');
photographyTest(is_string($photographyStyles) && !str_contains($photographyStyles, 'rotate(var(--photography-roll'), 'Las guías de tercios todavía reciben el giro de cámara.');
photographyTest(!preg_match('/photography-moon-illuminated[^}]*drop-shadow/', (string) $photographyStyles), 'El resplandor CSS lunar anterior sigue activo junto al halo opcional de Three.js.');
photographyTest(str_contains((string) $plannerSource, "gesture = { type: 'pinch'") && str_contains((string) $plannerSource, 'activePointers.size === 1'), 'El visor no conserva drag táctil de un dedo y pinch de dos dedos.');
photographyTest(str_contains((string) $plannerSource, 'state.focal = initialCamera.focal') && str_contains((string) $plannerSource, 'state.roll = 0') && str_contains((string) $plannerSource, 'if (state.centering)'), 'Restablecer encuadre no recupera la cámara técnica o no respeta el estado de centrado.');
photographyTest(str_contains((string) $photographyStyles, 'touch-action: none'), 'El visor no aísla sus gestos táctiles del scroll de la página.');
photographyTest(str_contains((string) $photographyStyles, 'top: var(--photography-sticky-top') && str_contains((string) $photographyStyles, 'max-height: 40vh'), 'El visor móvil no conserva un único encuadre sticky limitado por la altura visible.');
photographyTest(str_contains((string) $plannerSource, "document.querySelector('.site-header')") && str_contains((string) $plannerSource, 'new ResizeObserver(updateStickyHeaderOffset)'), 'El sticky móvil no mide el header real del sitio.');
photographyTest(str_contains((string) $plannerSource, 'requestAutomaticSubmit') && str_contains((string) $plannerSource, "input[name=\"date\"],input[name=\"time\"]") && str_contains((string) $plannerSource, "input[name=\"orientation\"]"), 'Los controles del planner no activan la actualización automática diferenciada.');
photographyTest(str_contains((string) $plannerSource, "payload.orientation_source === 'scene_auto'") && str_contains((string) $plannerSource, "key === 'orientation'") && str_contains((string) $plannerSource, 'orientationManuallyChanged') && str_contains((string) $photographyPageSource, 'name="orientation_auto"'), 'La orientación automática de escena sobrescribe la preferencia general o se vuelve a imponer después de cargar.');
photographyTest(str_contains((string) $plannerSource, "sessionStorage.setItem('photography.restoreScroll'") && str_contains((string) $photographyStyles, '.photography-auto-update .photography-update-fallback'), 'La actualización automática no conserva la posición de trabajo o no deja un fallback progresivo.');
photographyTest(str_contains((string) $plannerSource, 'sliderToFocal') && str_contains((string) $plannerSource, 'Math.log(3000)') && str_contains((string) $plannerSource, "focalSlider?.addEventListener('input'"), 'La focal no conserva un slider logarítmico sincronizado con el valor real.');
photographyTest(str_contains((string) $plannerSource, 'syncCustomSensorFields') && str_contains((string) $photographyStyles, '[data-custom-sensor-field][hidden]'), 'Los presets de sensor no ocultan correctamente las dimensiones reservadas para Personalizado.');
photographyTest(str_contains((string) $photographyPageSource, 'name="time_offset"'), 'Fotografía no conserva el offset temporal como estado reproducible separado.');
photographyTest(str_contains((string) $plannerSource, 'data-time-offset') && str_contains((string) $plannerSource, "url.searchParams.set('time_offset'") && str_contains((string) $plannerSource, 'updateEffectiveTime'), 'El offset temporal no actualiza su hora efectiva o la URL antes del recálculo.');
photographyTest(str_contains((string) $photographyPageSource, 'data-centering-toggle') && str_contains((string) $photographyPageSource, 'photography-viewer-controls'), 'El control binario de centrado no quedó junto a las guías del visor.');
photographyTest(str_contains((string) $plannerSource, 'recenterForTimedChange') && str_contains((string) $plannerSource, 'if (!state.centering) return') && str_contains((string) $plannerSource, 'preserveCenterForSelection'), 'El centrado no es puntual o una selección todavía recentra inesperadamente.');
photographyTest(!str_contains((string) $plannerSource, "moon.textContent = 'Centrar en la Luna'"), 'El visor todavía expone una acción de centrado paralela al control binario.');
photographyTest(count(photographySimulationDefinitions()) === 92, 'La calibración del modo Simulado no conserva el cielo, los umbrales, el halo opcional, la atmósfera y las anclas direccionales lunares completas.');
$simulationAdminStep = 0.005;
foreach (photographySimulationDefinitions() as $key => $definition) {
    if (($definition['type'] ?? '') !== 'decimal') continue;
    $stepsFromMinimum = ((float) $definition['default'] - (float) $definition['min']) / $simulationAdminStep;
    photographyTest(abs($stepsFromMinimum - round($stepsFromMinimum)) < 1e-7, 'El valor predeterminado no coincide con el paso nativo del admin: ' . $key);
}
photographyTest(astronomySiteConfigNormalizeValue('photography.simulated.day_zenith', '#ABCDEF') === '#abcdef', 'La calibración no valida colores CSS controlados.');
photographyTest(abs((float) astronomySiteConfigNormalizeValue('photography.simulated.sun_direction_influence', 9) - 1.5) < 1e-9, 'La calibración no limita intensidades visuales.');
$brightnessDefinition = photographySimulationDefinitions()['photography.simulated.moon_night_low_lit_brightness'] ?? [];
photographyTest((float) ($brightnessDefinition['default'] ?? 0.0) === 2.8 && (float) ($brightnessDefinition['max'] ?? 0.0) === 20.0, 'Brillo iluminado no conserva su valor inicial o el nuevo margen de calibración.');
photographyTest(str_contains((string) $plannerSource, "state.displayMode = button.dataset.displayMode") && str_contains((string) $plannerSource, "url.searchParams.set('mode', state.displayMode)"), 'Esquema y Simulado no comparten estado reproducible en URL.');
$moonThreeSource = file_get_contents(__DIR__ . '/../assets/js/photography-moon-three.js');
$photographyAdminSource = file_get_contents(__DIR__ . '/../admin/fotografia/index.php');
photographyTest(is_string($moonThreeSource) && str_contains($moonThreeSource, "import * as THREE") && str_contains($moonThreeSource, 'surface_geometry.subsolar'), 'La Luna simulada no reutiliza Three.js y la geometría física compartida.');
photographyTest(str_contains((string) $plannerSource, "import(payload.simulation_moon?.renderer_url") && str_contains((string) $photographyPageSource, 'photographySimulationMoonPayload'), 'El módulo lunar dinámico no usa una URL versionada reproducible.');
$simulationMoonPayload = photographySimulationMoonPayload($state, []);
photographyTest(preg_match('#^\./photography-moon-three\.js\?v=\d+$#', (string) ($simulationMoonPayload['renderer_url'] ?? '')) === 1, 'La URL versionada del módulo lunar no se resuelve relativa al planificador que la importa.');
photographyTest(abs((float) ($simulationMoonPayload['geometry']['illumination_fraction'] ?? -1) - (float) $state['moon']['illumination_fraction']) < 1e-9, 'El renderer 3D no recibe la fracción iluminada astronómica para modular el halo.');
photographyTest(str_contains((string) $moonThreeSource, 'moonNormalMap') && str_contains((string) $moonThreeSource, 'reliefIncidence'), 'El terminador 3D no conserva el relieve del normal map.');
photographyTest(str_contains((string) $moonThreeSource, '.applyAxisAngle(axisY, longitudeRotation).applyAxisAngle(axisX, latitudeRotation).applyAxisAngle(axisZ, diskRotation)'), 'La dirección solar no recibe la libración y el roll completos del encuadre.');
photographyTest(is_string($photographyAdminSource) && str_contains($photographyAdminSource, 'name="simulation_profile"') && !str_contains($photographyAdminSource, 'photography-admin-preview-cases'), 'La vista previa administrativa conserva controles paralelos al desplegable de perfiles.');
photographyTest(str_contains((string) $photographyAdminSource, 'data-preview-focal') && str_contains((string) $photographyAdminSource, 'focal=1200&aim=moon'), 'La vista previa administrativa no parte ampliada ni expone su focal independiente.');
photographyTest(str_contains((string) $photographyAdminSource, "'twilight_low_antisolar' => '../../fotografia.php") && str_contains((string) $photographyAdminSource, 'date=2026-08-13&time=18:45'), 'La administración no conserva las previews solar y antisolar del crepúsculo bajo.');
photographyTest(str_contains((string) $moonThreeSource, 'pow(positiveIncidence, 0.45)'), 'La hoz fina no conserva una respuesta fotométrica legible sin alterar el terminador.');
photographyTest(str_contains((string) $moonThreeSource, 'illuminationDistribution') && str_contains((string) $moonThreeSource, 'haloSolarIncidence = dot(haloLimbNormal, normalize(moonSunDirection))') && str_contains((string) $moonThreeSource, 'nearFullWrap'), 'El halo no deriva su distribución espacial de la incidencia solar sobre una normal cercana al limbo y la fase real.');
photographyTest(str_contains((string) $moonThreeSource, 'phaseHaloWeight') && str_contains((string) $moonThreeSource, 'skyHaloWeight') && str_contains((string) $moonThreeSource, 'moon_halo_phase_start_percent'), 'El halo no interpola continuamente por fracción iluminada y altura solar.');
photographyTest(str_contains((string) $moonThreeSource, 'haloMaterial.uniforms.moonHorizonClipEnabled') && str_contains((string) $moonThreeSource, 'const extent = diameter * renderRadius'), 'El halo no comparte el recorte de horizonte o la escala angular de la Luna.');
photographyTest(str_contains((string) $moonThreeSource, "haloSetting === true || haloSetting === 1 || haloSetting === '1'") && str_contains((string) $moonThreeSource, 'const renderRadius = halo.visible ? haloRadius : 1.08'), 'Desactivar el halo no elimina su capa o no restaura el encuadre interno anterior.');
photographyTest(str_contains((string) $moonThreeSource, 'radius < 1.0') && str_contains((string) $moonThreeSource, 'halo.position.z = -1.1'), 'El halo no nace exactamente fuera del disco o dejó de renderizarse detrás de la esfera opaca.');
photographyTest(str_contains((string) $plannerSource, "event.data?.type === 'photography-preview-camera'") && str_contains((string) $plannerSource, 'setFocal(focal)'), 'El planificador no recibe el zoom aislado de la vista previa administrativa.');
photographyTest(is_string($photographyPageSource) && !str_contains($photographyPageSource, 'storeAdminHasValidSessionCookie()') && str_contains($photographyPageSource, "\$displayMode = (\$_GET['mode'] ?? '') === 'simulated'") && str_contains($photographyPageSource, 'data-display-mode="simulated"'), 'El modo Simulado todavía está restringido para el público.');
photographyTest(str_contains((string) $photographyPageSource, 'astronomyDataDaily($location, $date') && str_contains((string) $photographyPageSource, 'class="photography-day-events"'), 'Fotografía no muestra las salidas y puestas del día seleccionado mediante la fuente astronómica común.');
photographyTest(str_contains((string) $moonThreeSource, 'dark_sky_mix') && str_contains((string) $plannerSource, 'skyColor: moonSkyColor'), 'La sombra lunar no recibe el color local del cielo con mezcla administrable.');
photographyTest(str_contains((string) $plannerSource, 'photography-simulation-preview') && str_contains((string) $moonThreeSource, 'moonLitBrightness.value = Number(payload.appearance.lit_brightness)'), 'La vista previa no aplica en vivo la calibración al renderer lunar.');
photographyTest(!array_key_exists('photography.simulated.moon_dark_color', photographySimulationDefinitions()), 'La sombra lunar volvió a depender de un tono oscuro absoluto.');
$simulationDefaults = [];
foreach (photographySimulationDefinitions() as $key => $definition) $simulationDefaults[substr($key, strlen('photography.simulated.'))] = $definition['default'];
$simulationDefaults['moon_night_low_lit_brightness'] = 1.0;
$simulationDefaults['moon_night_high_lit_brightness'] = 3.0;
$simulationDefaults['moon_twilight_low_lit_brightness'] = 5.0;
$simulationDefaults['moon_twilight_high_lit_brightness'] = 7.0;
$profileState = ['sun' => ['altitude_degrees' => -10.5], 'moon' => ['altitude_degrees' => 30.0]];
$interpolatedMoon = photographySimulationMoonAppearance($profileState, $simulationDefaults);
photographyTest($interpolatedMoon['lit_brightness'] > 1.0 && $interpolatedMoon['lit_brightness'] < 7.0, 'La apariencia lunar no interpola simultáneamente cielo y altura.');
photographyTest(abs($interpolatedMoon['interpolation']['height_weight'] - 0.5) < 1e-9, 'La transición horizonte/cenit no es continua en su punto medio.');
$customHeightConfig = $simulationDefaults;
$customHeightConfig['moon_low_to_high_start_altitude'] = 5.0;
$customHeightConfig['moon_low_to_high_end_altitude'] = 15.0;
$heightLow = photographySimulationMoonAppearance(['sun' => ['altitude_degrees' => -10.5], 'moon' => ['altitude_degrees' => 5.0]], $customHeightConfig);
$heightMiddle = photographySimulationMoonAppearance(['sun' => ['altitude_degrees' => -10.5], 'moon' => ['altitude_degrees' => 10.0]], $customHeightConfig);
$heightHigh = photographySimulationMoonAppearance(['sun' => ['altitude_degrees' => -10.5], 'moon' => ['altitude_degrees' => 15.0]], $customHeightConfig);
photographyTest(abs((float) $heightLow['interpolation']['height_weight']) < 1e-9 && abs((float) $heightMiddle['interpolation']['height_weight'] - 0.5) < 1e-9 && abs((float) $heightHigh['interpolation']['height_weight'] - 1.0) < 1e-9, 'Los umbrales lunares configurables no delimitan una transición smoothstep continua.');
$customTwilightConfig = $simulationDefaults;
$customTwilightConfig['twilight_center_altitude'] = -5.0;
$twilightCenterAppearance = photographySimulationMoonAppearance(['sun' => ['altitude_degrees' => -5.0], 'moon' => ['altitude_degrees' => 30.0]], $customTwilightConfig);
photographyTest(abs((float) $twilightCenterAppearance['interpolation']['sky_weight'] - 1.0) < 1e-9 && abs((float) $twilightCenterAppearance['interpolation']['twilight_center_altitude_degrees'] + 5.0) < 1e-9, 'El máximo configurable de crepúsculo no alcanza el ancla crepuscular.');
photographyTest($simulationDefaults['moon_day_high_texture_visibility'] < $simulationDefaults['moon_night_high_texture_visibility'] && $simulationDefaults['moon_day_high_lit_sky_mix'] > $simulationDefaults['moon_night_high_lit_sky_mix'], 'El perfil diurno no parte de una textura más lavada e integrada que el nocturno.');
$atmosphereHeights = [60.0, 20.0, 10.0, 5.0, 2.0, 0.5];
foreach ([20.0, -8.0, -25.0] as $solarAltitude) {
    $previousStrength = -1.0;
    foreach ($atmosphereHeights as $lunarAltitude) {
        $atmosphereAppearance = photographySimulationMoonAppearance(['sun' => ['altitude_degrees' => $solarAltitude], 'moon' => ['altitude_degrees' => $lunarAltitude]], $simulationDefaults);
        $strength = (float) $atmosphereAppearance['interpolation']['atmosphere_strength'];
        photographyTest($strength + 1e-9 >= $previousStrength, 'La atmósfera no crece continuamente al bajar la Luna.');
        $previousStrength = $strength;
    }
}
$dayLowAtmosphere = photographySimulationMoonAppearance(['sun' => ['altitude_degrees' => 20.0], 'moon' => ['altitude_degrees' => 0.5]], $simulationDefaults);
$nightLowAtmosphere = photographySimulationMoonAppearance(['sun' => ['altitude_degrees' => -25.0], 'moon' => ['altitude_degrees' => 0.5]], $simulationDefaults);
photographyTest($dayLowAtmosphere['interpolation']['atmosphere_context_warmth'] < $nightLowAtmosphere['interpolation']['atmosphere_context_warmth'], 'La Luna baja diurna no limita la calidez frente a la nocturna.');
photographyTest(abs((float) $nightLowAtmosphere['interpolation']['atmosphere_brightness_multiplier'] - 0.62) < 1e-9 && abs((float) $nightLowAtmosphere['interpolation']['brightness_after_extinction'] - (float) $nightLowAtmosphere['lit_brightness']) < 1e-9, 'El estado no separa de forma trazable el brillo base y la extinción atmosférica.');
photographyTest($dayLowAtmosphere['contrast'] < photographySimulationMoonAppearance(['sun' => ['altitude_degrees' => 20.0], 'moon' => ['altitude_degrees' => 60.0]], $simulationDefaults)['contrast'], 'La capa atmosférica no reduce el contraste cerca del horizonte.');
photographyTest(!array_key_exists('photography.simulated.moon_lit_brightness', photographySimulationDefinitions()), 'La calibración lunar global anterior sigue expuesta junto a los perfiles.');
photographyTest(isset($state['moon']['surface_geometry']['subobserver'], $state['moon']['surface_geometry']['subsolar']), 'El estado fotográfico no publica la orientación 3D lunar existente.');
$directionalDefinitions = photographySimulationDefinitions();
photographyTest(isset($directionalDefinitions['photography.simulated.twilight_horizon_antisolar'], $directionalDefinitions['photography.simulated.moon_twilight_low_antisolar_lit_brightness']), 'Faltan anclas administrables de cielo o Luna antisolar.');
$directionalConfig = $simulationDefaults;
$directionalConfig['moon_twilight_low_lit_brightness'] = 2.0;
$directionalConfig['moon_twilight_low_antisolar_lit_brightness'] = 6.0;
$directionalConfig['atmosphere_extinction_strength'] = 0.0;
$solarDirectional = photographySimulationMoonAppearance(['sun' => ['altitude_degrees' => -3.0, 'separation_from_moon_degrees' => 15.0], 'moon' => ['altitude_degrees' => 0.0]], $directionalConfig);
$antisolarDirectional = photographySimulationMoonAppearance(['sun' => ['altitude_degrees' => -3.0, 'separation_from_moon_degrees' => 177.0], 'moon' => ['altitude_degrees' => 0.0]], $directionalConfig);
photographyTest((float) $solarDirectional['interpolation']['solar_direction_weight'] < 0.01 && (float) $antisolarDirectional['interpolation']['solar_direction_weight'] > 0.99, 'El eje direccional no mantiene independientes los casos de 15° y 177°.');
photographyTest((float) $solarDirectional['lit_brightness'] < 2.05 && (float) $antisolarDirectional['lit_brightness'] > 5.95, 'La apariencia lunar baja no interpola las anclas solar y antisolar esperadas.');
photographyTest(str_contains((string) $plannerSource, 'relativeAngularSeparation') && str_contains((string) $plannerSource, 'twilight_horizon_antisolar') && str_contains((string) $plannerSource, "moonProfile('twilight', 'low_antisolar')"), 'El cliente no aplica el eje direccional al centro del cielo y a la Luna 3D.');
photographyTest(str_contains((string) $plannerSource, 'simulatedStarSolarVisibility') && str_contains((string) $plannerSource, 'smoothstep(-12, -6, solarAltitude)') && str_contains((string) $plannerSource, 'simulatedPlanetSolarVisibility'), 'El modo Simulado no separa las curvas solares continuas de estrellas y planetas.');
photographyTest(str_contains((string) $plannerSource, "opacity: visibility, class: 'photography-label'") && str_contains((string) $plannerSource, 'visibility >= .01'), 'Las etiquetas simuladas no se atenúan y desaparecen junto con el astro.');
photographyTest(!str_contains((string) $plannerSource, "clamp((1 - sky.brightness * .94)"), 'El renderer conserva la visibilidad mínima artificial que hacía aparecer estrellas sobre cielo claro.');
photographyTest(str_contains((string) $plannerSource, 'moon_low_to_high_start_altitude') && str_contains((string) $plannerSource, 'twilight_center_altitude'), 'El cliente no consume los nuevos umbrales configurables.');
photographyTest(str_contains((string) $photographyAdminSource, 'A. Umbrales del cielo') && str_contains((string) $photographyAdminSource, 'B. Umbrales lunares') && str_contains((string) $photographyAdminSource, 'C. Umbrales de atmósfera lunar'), 'El admin no separa claramente los tres grupos de umbrales.');
photographyTest(str_contains((string) $photographyAdminSource, '<legend>Resplandor lunar</legend>') && str_contains((string) $photographyAdminSource, 'name="simulation[moon_halo_enabled]"') && count(array_filter(array_keys(photographySimulationDefinitions()), static fn(string $key): bool => str_contains($key, '.moon_halo_'))) === 4, 'El admin no limita el resplandor lunar al interruptor y sus tres controles globales.');
$haloEnabledDefinition = photographySimulationDefinitions()['photography.simulated.moon_halo_enabled'] ?? [];
photographyTest(($haloEnabledDefinition['type'] ?? '') === 'boolean' && ($haloEnabledDefinition['default'] ?? false) === true, 'El resplandor lunar no posee un interruptor booleano con el valor recomendado.');
echo "Photography planner tests passed.\n";
