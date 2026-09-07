<?php

declare(strict_types=1);

define('STORE_ADMIN_LOGIN_PATH', '../login.php');
require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/store-admin-navigation.php';
require_once __DIR__ . '/../../includes/asset-url.php';
require_once __DIR__ . '/../../includes/favicon-links.php';
require_once __DIR__ . '/../../includes/seo.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();
$publicBase = rtrim(aquellasLunasPublicBaseUrl(), '/');
$html = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Widgets lunares · Administración</title>
    <?php renderFaviconLinks('../../'); ?>
    <link rel="stylesheet" href="<?= $html('../../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <link rel="stylesheet" href="<?= $html('../../' . versionedAssetUrl('assets/css/lunar-widgets.css')) ?>">
</head>
<body class="store-admin">
    <?php renderStoreAdminNavigation('lunar_widgets', 'Widgets lunares'); ?>
    <main class="store-admin-main lunar-widget-admin" data-lunar-widget-builder data-public-base="<?= $html($publicBase) ?>" data-csrf-token="<?= $html(storeAdminCsrfToken()) ?>">
        <section class="card lunar-widget-admin__settings">
            <h2>Generador de embeds</h2>
            <p>Configurá una variante, comprobala en la vista previa y copiá la URL o el iframe. Estos widgets no aparecen en el sitio público.</p>
            <div class="lunar-widget-admin__grid">
                <label><span>Widget</span><select data-widget-kind><option value="libration">Libración lunar</option><option value="lunar-scene">Escena lunar configurable</option><option value="interactive">Luna interactiva</option><option value="earth-moon">Fases Tierra–Luna</option><option value="full-moon-sizes">Tamaño de próximas lunas llenas</option><option value="eclipse">Eclipse lunar</option><option value="real-lunar-eclipse">Eclipse lunar real</option><option value="real-solar-eclipse">Eclipse solar real</option><option value="solar-space-eclipse">Eclipse solar desde el espacio</option></select></label>

                <fieldset data-widget-options="libration">
                    <legend>Libración lunar</legend>
                    <p class="lunar-widget-admin__appearance-note">La apariencia usa la configuración guardada en <a href="../luna-portada/">La Luna de tu fecha favorita</a>.</p>
                    <label><span>Iluminación</span><select data-option="mode"><option value="realistic">Realista</option><option value="full">Totalmente iluminada</option></select></label>
                    <label><span>Reproducción automática</span><select data-option="autoplay"><option value="1">Activada</option><option value="0">Desactivada</option></select></label>
                    <label><span>Velocidad</span><input type="number" min="0.25" max="8" step="0.25" value="1" data-option="speed"></label>
                    <label class="lunar-widget-admin__range"><span>Zoom</span><input type="range" min="0.65" max="1.35" step="0.05" value="1" data-option="zoom" data-libration-zoom><output data-libration-zoom-value>100 %</output></label>
                    <label><span>Controles</span><select data-option="controls"><option value="1">Visibles</option><option value="0">Ocultos</option></select></label>
                </fieldset>

                <fieldset data-widget-options="lunar-scene" hidden>
                    <legend>Escena lunar configurable</legend>
                    <p class="lunar-widget-admin__appearance-note">Usa la ubicación activa y recrea la fase y orientación observacional del instante elegido.</p>
                    <label><span>Fecha</span><input type="date" min="1900-01-01" max="2050-12-31" value="<?= $html(date('Y-m-d')) ?>" data-option="fecha"></label>
                    <label><span>Hora</span><input type="time" value="<?= $html(date('H:i')) ?>" data-option="hora"></label>
                    <label><span>Ubicación</span><select data-option="location_mode"><option value="active">Ubicación activa configurada</option></select></label>
                    <label><span>Brillo de zona iluminada</span><input type="number" min="0" max="8" step="0.1" value="3.2" data-option="lit_brightness"></label>
                    <label><span>Tinte de zona iluminada</span><input type="color" value="#fff7e8" data-option="lit_tint"></label>
                    <label><span>Brillo de zona oscura</span><input type="number" min="0" max="1" step="0.01" value="0.04" data-option="dark_brightness"></label>
                    <label><span>Saturación de textura</span><input type="number" min="0" max="2" step="0.05" value="0.85" data-option="texture_saturation"></label>
                    <label><span>Contraste de textura</span><input type="number" min="0.5" max="2" step="0.05" value="1.15" data-option="texture_contrast"></label>
                    <label><span>Resolución de textura/relieve</span><select data-option="texture_resolution"><option value="high">Alta</option><option value="low">Baja</option></select></label>
                    <label><span>Luz cenicienta</span><input type="number" min="0" max="1" step="0.01" value="0.12" data-option="earthshine_intensity"></label>
                    <label><span>Fusión de sombra con el cielo</span><input type="number" min="0" max="1" step="0.01" value="0.12" data-option="shadow_sky_mix"></label>
                    <label><span>Opacidad del lado oscuro</span><input type="number" min="0" max="1" step="0.01" value="0.82" data-option="dark_side_opacity"></label>
                    <label><span>Suavidad del limbo oscuro</span><input type="number" min="0" max="0.3" step="0.01" value="0.08" data-option="dark_limb_softness"></label>
                    <label><span>Halo atmosférico</span><input type="number" min="0" max="1" step="0.01" value="0.18" data-option="halo_intensity"></label>
                    <label><span>Suavidad del borde</span><input type="number" min="0" max="0.15" step="0.005" value="0.02" data-option="edge_softness"></label>
                    <label><span>Suavidad del terminador</span><input type="number" min="0.01" max="0.35" step="0.01" value="0.13" data-option="terminator_softness"></label>
                    <label><span>Comportamiento del cielo</span><select data-option="sky_mode"><option value="natural">Según la hora</option><option value="custom">Color manual</option></select></label>
                    <label><span>Color del centro</span><input type="color" value="#789fc4" data-option="sky_center_color"></label>
                    <label><span>Brillo del centro</span><input type="number" min="0" max="2" step="0.05" value="1" data-option="sky_center_brightness"></label>
                    <label><span>Color del borde</span><input type="color" value="#263f62" data-option="sky_edge_color"></label>
                    <label><span>Brillo del borde</span><input type="number" min="0" max="2" step="0.05" value="1" data-option="sky_edge_brightness"></label>
                    <label><span>Extensión del gradiente (%)</span><input type="number" min="25" max="100" step="1" value="64" data-option="sky_gradient_radius"></label>
                </fieldset>

                <fieldset data-widget-options="full-moon-sizes" hidden>
                    <legend>Tamaño de próximas lunas llenas</legend>
                    <p class="lunar-widget-admin__appearance-note">Compara las próximas 12 lunas llenas desde la fecha elegida. La URL conserva esa referencia.</p>
                    <label><span>Fecha de referencia</span><input type="date" min="1900-01-01" max="2049-12-31" value="<?= $html(date('Y-m-d')) ?>" data-option="date"></label>
                    <label><span>Control de fecha en el embed</span><select data-option="controls"><option value="1">Visible</option><option value="0">Oculto</option></select></label>
                </fieldset>

                <fieldset data-widget-options="interactive" hidden>
                    <legend>Luna interactiva</legend>
                    <label><span>Orientación horizontal</span><input type="number" min="-180" max="180" step="1" value="0" data-option="yaw"></label>
                    <label><span>Orientación vertical</span><input type="number" min="-75" max="75" step="1" value="0" data-option="pitch"></label>
                    <label><span>Zoom</span><input type="number" min="0.8" max="1.7" step="0.05" value="1" data-option="zoom"></label>
                    <label><span>Rotación</span><select data-option="rotation"><option value="free">Libre</option><option value="locked">Bloqueada</option></select></label>
                    <label><span>Cráteres</span><select data-option="craters"><option value="1">Activados</option><option value="0">Desactivados</option></select></label>
                    <label><span>Mares</span><select data-option="maria"><option value="1">Activados</option><option value="0">Desactivados</option></select></label>
                    <label><span>Otros accidentes</span><select data-option="other"><option value="0">Desactivados</option><option value="1">Activados</option></select></label>
                    <label><span>Alunizajes</span><select data-option="landings"><option value="1">Activados</option><option value="0">Desactivados</option></select></label>
                    <label><span>Etiquetas</span><select data-option="detail"><option value="auto">Automáticas</option><option value="main">Principales</option><option value="more">Máximo detalle</option></select></label>
                    <label><span>Iluminación</span><select data-option="illumination"><option value="realistic">Realista</option><option value="full">Todo iluminado</option></select></label>
                    <label><span>Controles</span><select data-option="controls"><option value="1">Visibles</option><option value="0">Ocultos</option></select></label>
                </fieldset>

                <fieldset data-widget-options="earth-moon" hidden>
                    <legend>Fases Tierra–Luna</legend>
                    <label><span>Fecha</span><input type="date" min="1900-01-01" max="2050-12-31" value="<?= $html(date('Y-m-d')) ?>" data-option="fecha"></label>
                    <label><span>Hora</span><input type="time" value="<?= $html(date('H:i')) ?>" data-option="hora"></label>
                    <label><span>Reproducción automática</span><select data-option="autoplay"><option value="0">Desactivada</option><option value="1">Activada</option></select></label>
                    <label><span>Velocidad</span><input type="number" min="0.25" max="8" step="0.25" value="1" data-option="speed"></label>
                    <label><span>Controles</span><select data-option="controls"><option value="1">Visibles</option><option value="0">Ocultos</option></select></label>
                    <div class="lunar-widget-admin__body-settings">
                        <h3>Luna · Three.js</h3>
                        <label><span>Luz principal</span><input type="number" min="0" max="8" step="0.1" value="3.2" data-option="moon_sun"></label>
                        <label><span>Luz ambiente</span><input type="number" min="0" max="1" step="0.01" value="0.01" data-option="moon_ambient"></label>
                        <label><span>Exposición</span><input type="number" min="0.5" max="2" step="0.05" value="1.15" data-option="moon_exposure"></label>
                        <label><span>Rugosidad</span><input type="number" min="0" max="1" step="0.05" value="1" data-option="moon_roughness"></label>
                        <label><span>Normal scale X</span><input type="number" min="-4" max="4" step="0.1" value="1" data-option="moon_normal_x"></label>
                        <label><span>Normal scale Y</span><input type="number" min="-4" max="4" step="0.1" value="1" data-option="moon_normal_y"></label>
                    </div>
                    <div class="lunar-widget-admin__body-settings">
                        <h3>Tierra · Three.js</h3>
                        <label><span>Luz principal</span><input type="number" min="0" max="8" step="0.1" value="2.7" data-option="earth_sun"></label>
                        <label><span>Luz ambiente</span><input type="number" min="0" max="1" step="0.01" value="0.035" data-option="earth_ambient"></label>
                        <label><span>Exposición</span><input type="number" min="0.5" max="2" step="0.05" value="1.05" data-option="earth_exposure"></label>
                        <label><span>Rugosidad</span><input type="number" min="0" max="1" step="0.05" value="1" data-option="earth_roughness"></label>
                    </div>
                </fieldset>

                <fieldset data-widget-options="eclipse" hidden>
                    <legend>Eclipse lunar</legend>
                    <label><span>Reproducción automática</span><select data-option="autoplay"><option value="0">Desactivada</option><option value="1">Activada</option></select></label>
                    <label><span>Velocidad</span><input type="number" min="0.25" max="8" step="0.25" value="1" data-option="speed"></label>
                    <label><span>Controles</span><select data-option="controls"><option value="1">Visibles</option><option value="0">Ocultos</option></select></label>
                    <label><span>Esquema geométrico</span><select data-option="geometry"><option value="1">Visible</option><option value="0">Oculto</option></select></label>
                    <label><span>Luz principal</span><input type="number" min="0" max="8" step="0.1" value="3.2" data-option="sun_intensity"></label>
                    <label><span>Luz ambiente</span><input type="number" min="0" max="1" step="0.01" value="0.01" data-option="ambient_intensity"></label>
                    <label><span>Exposición</span><input type="number" min="0.5" max="2" step="0.05" value="1.15" data-option="exposure"></label>
                    <label><span>Oscuridad de penumbra</span><input type="number" min="0" max="1" step="0.01" value="0.18" data-option="penumbra_darkness"></label>
                    <label><span>Oscuridad de umbra</span><input type="number" min="0" max="1" step="0.005" value="0.955" data-option="umbra_darkness"></label>
                    <label><span>Intensidad cobriza</span><input type="number" min="0" max="1" step="0.01" value="0.26" data-option="copper_intensity"></label>
                    <label><span>Color durante totalidad</span><input type="color" value="#c95f32" data-option="copper_color"></label>
                </fieldset>

                <fieldset data-widget-options="real-lunar-eclipse" hidden>
                    <legend>Eclipse lunar real</legend>
                    <p class="lunar-widget-admin__appearance-note">La fecha identifica el evento; las coordenadas determinan orientación, hora local y ventana observable.</p>
                    <label><span>Fecha del eclipse</span><input type="date" value="2026-03-03" data-option="date"></label>
                    <label><span>Latitud de prueba</span><input type="number" min="-90" max="90" step="0.0001" value="-34.6037" data-option="lat"></label>
                    <label><span>Longitud de prueba</span><input type="number" min="-180" max="180" step="0.0001" value="-58.3816" data-option="lon"></label>
                    <label><span>Elevación (m)</span><input type="number" min="-500" max="10000" step="1" value="0" data-option="elevation"></label>
                    <label><span>Reproducción automática</span><select data-option="autoplay"><option value="0">Desactivada</option><option value="1">Activada</option></select></label>
                    <label><span>Velocidad inicial</span><input type="number" min="0.25" max="8" step="0.25" value="1" data-option="speed"></label>
                    <label><span>Duración del recorrido (s)</span><input type="number" min="10" max="180" step="5" value="40" data-option="duration"></label>
                    <label><span>Controles</span><select data-option="controls"><option value="1">Visibles</option><option value="0">Ocultos</option></select></label>
                    <label><span>Luz principal</span><input type="number" min="0" max="8" step="0.1" value="3.2" data-option="sun_intensity"></label>
                    <label><span>Luz ambiente</span><input type="number" min="0" max="1" step="0.01" value="0.01" data-option="ambient_intensity"></label>
                    <label><span>Exposición</span><input type="number" min="0.5" max="2" step="0.05" value="1.15" data-option="exposure"></label>
                    <label><span>Luminosidad del cielo</span><input type="number" min="0" max="2" step="0.05" value="0" data-option="sky_brightness"></label>
                    <label><span>Color del cielo</span><input type="color" value="#02040a" data-option="sky_color"></label>
                    <label><span>Oscuridad de penumbra</span><input type="number" min="0" max="1" step="0.01" value="0.18" data-option="penumbra_darkness"></label>
                    <label><span>Oscuridad de umbra</span><input type="number" min="0" max="1" step="0.005" value="0.955" data-option="umbra_darkness"></label>
                    <label><span>Intensidad cobriza</span><input type="number" min="0" max="1" step="0.01" value="0.26" data-option="copper_intensity"></label>
                    <label><span>Color de totalidad</span><input type="color" value="#c95f32" data-option="copper_color"></label>
                    <label><span>Brillo base durante totalidad</span><input type="number" min="0" max="2" step="0.05" value="0.55" data-option="totality_brightness"></label>
                    <label><span>Intensidad cobriza durante totalidad</span><input type="number" min="0" max="2" step="0.05" value="0.7" data-option="totality_copper_intensity"></label>
                    <label><span>Oscuridad máxima de la umbra</span><input type="number" min="0" max="1" step="0.01" value="0.88" data-option="totality_max_darkness"></label>
                    <label><span>Contraste del gradiente umbral</span><input type="number" min="0.25" max="3" step="0.05" value="1.25" data-option="totality_gradient_contrast"></label>
                    <label><span>Tono del borde menos profundo</span><input type="color" value="#d98b45" data-option="totality_edge_color"></label>
                    <label><span>Tono de la zona profunda</span><input type="color" value="#5a120e" data-option="totality_deep_color"></label>
                    <label><span>Saturación durante totalidad</span><input type="number" min="0" max="2" step="0.05" value="1.1" data-option="totality_saturation"></label>
                    <label><span>Contraste de textura durante totalidad</span><input type="number" min="0" max="1.5" step="0.05" value="0.58" data-option="totality_texture_contrast"></label>
                    <label><span>Suavidad del gradiente</span><input type="number" min="0.02" max="0.5" step="0.01" value="0.16" data-option="totality_gradient_softness"></label>
                    <label><span>Irregularidad atmosférica</span><input type="number" min="0" max="0.5" step="0.01" value="0.1" data-option="totality_atmospheric_irregularity"></label>
                </fieldset>

                <fieldset data-widget-options="real-solar-eclipse" hidden>
                    <legend>Eclipse solar real</legend>
                    <p class="lunar-widget-admin__appearance-note">La geometría topocéntrica es calculada por el motor. Corona y fenómenos del borde son representaciones visuales.</p>
                    <label><span>Fecha del eclipse</span><input type="date" value="2026-08-12" data-option="date"></label>
                    <label><span>Latitud de prueba</span><input type="number" min="-90" max="90" step="0.0001" value="40.4168" data-option="lat"></label>
                    <label><span>Longitud de prueba</span><input type="number" min="-180" max="180" step="0.0001" value="-3.7038" data-option="lon"></label>
                    <label><span>Elevación (m)</span><input type="number" min="-500" max="10000" step="1" value="0" data-option="elevation"></label>
                    <label><span>Reproducción automática</span><select data-option="autoplay"><option value="0">Desactivada</option><option value="1">Activada</option></select></label>
                    <label><span>Velocidad inicial</span><input type="number" min="0.25" max="8" step="0.25" value="1" data-option="speed"></label>
                    <label><span>Duración del recorrido (s)</span><input type="number" min="10" max="180" step="5" value="45" data-option="duration"></label>
                    <label><span>Controles</span><select data-option="controls"><option value="1">Visibles</option><option value="0">Ocultos</option></select></label>
                    <label><span>Corona · intensidad y complejidad (0–10)</span><input type="number" min="0" max="10" step="1" value="7" data-option="corona_level"></label>
                    <label><span>Prominencias · presencia visual (0–10)</span><input type="number" min="0" max="10" step="1" value="4" data-option="prominence_level"></label>
                    <label><span>Perlas y diamante · presencia visual (0–10)</span><input type="number" min="0" max="10" step="1" value="6" data-option="baily_level"></label>
                    <label><span>Efectos antes de totalidad (s)</span><input type="number" min="0" max="120" step="1" value="5" data-option="totality_effects_before_seconds"></label>
                    <label><span>Efectos después de totalidad (s)</span><input type="number" min="0" max="120" step="1" value="5" data-option="totality_effects_after_seconds"></label>
                    <label><span>Exposición general</span><input type="number" min="0.5" max="2" step="0.05" value="1" data-option="exposure"></label>
                    <label><span>Intensidad del Sol</span><input type="number" min="0" max="2" step="0.05" value="1" data-option="sun_intensity"></label>
                    <label><span>Color del Sol</span><input type="color" value="#fff7df" data-option="sun_color"></label>
                    <label><span>Oscurecimiento del limbo</span><input type="number" min="0" max="0.3" step="0.005" value="0.075" data-option="limb_darkening"></label>
                    <label><span>Color de la Luna</span><input type="color" value="#010104" data-option="moon_color"></label>
                    <label><span>Oscurecimiento del cielo</span><input type="number" min="0" max="1" step="0.01" value="0.94" data-option="sky_darkening"></label>
                    <label><span>Brillo base del cielo</span><input type="number" min="0" max="2" step="0.05" value="1" data-option="sky_brightness"></label>
                    <label><span>Color del cielo</span><input type="color" value="#122343" data-option="sky_color"></label>
                    <label><span>Brillo de corona</span><input type="number" min="0" max="2" step="0.05" value="1" data-option="corona_brightness"></label>
                    <label><span>Color de corona</span><input type="color" value="#dcecff" data-option="corona_color"></label>
                    <label><span>Intensidad de prominencias</span><input type="number" min="0" max="2" step="0.05" value="1" data-option="prominence_intensity"></label>
                    <label><span>Color de prominencias</span><input type="color" value="#ff5839" data-option="prominence_color"></label>
                    <label><span>Intensidad de perlas/diamante</span><input type="number" min="0" max="2" step="0.05" value="1" data-option="baily_intensity"></label>
                    <label><span>Color de perlas/diamante</span><input type="color" value="#fffdf1" data-option="baily_color"></label>
                </fieldset>

                <fieldset data-widget-options="solar-space-eclipse" hidden>
                    <legend>Eclipse solar desde el espacio</legend>
                    <p class="lunar-widget-admin__appearance-note">La sombra y el terminador usan geometría geocéntrica real. Sólo la distancia visual Tierra–Luna está comprimida.</p>
                    <label><span>Fecha del eclipse</span><input type="date" value="2026-08-12" data-option="date"></label>
                    <label><span>Reproducción automática</span><select data-option="autoplay"><option value="0">Desactivada</option><option value="1">Activada</option></select></label>
                    <label><span>Velocidad inicial</span><input type="number" min="0.25" max="8" step="0.25" value="1" data-option="speed"></label>
                    <label><span>Duración del recorrido (s)</span><input type="number" min="10" max="180" step="5" value="50" data-option="duration"></label>
                    <label><span>Controles</span><select data-option="controls"><option value="1">Visibles</option><option value="0">Ocultos</option></select></label>
                    <label><span>Mostrar Luna</span><select data-option="show_moon"><option value="1">Visible</option><option value="0">Oculta</option></select></label>
                    <label><span>Mostrar penumbra</span><select data-option="show_penumbra"><option value="1">Visible</option><option value="0">Oculta</option></select></label>
                    <label><span>Opacidad de penumbra</span><input type="number" min="0" max="1" step="0.01" value="0.22" data-option="penumbra_opacity"></label>
                    <label><span>Mostrar umbra/antumbra</span><select data-option="show_core"><option value="1">Visible</option><option value="0">Oculta</option></select></label>
                    <label><span>Opacidad de umbra/antumbra</span><input type="number" min="0" max="1" step="0.01" value="0.62" data-option="core_opacity"></label>
                    <label><span>Mostrar terminador</span><select data-option="show_terminator"><option value="1">Visible</option><option value="0">Oculto</option></select></label>
                    <label><span>Brillo ambiental</span><input type="number" min="0" max="1.5" step="0.01" value="0.08" data-option="ambient_intensity"></label>
                    <label><span>Exposición</span><input type="number" min="0.5" max="2" step="0.05" value="1.05" data-option="exposure"></label>
                    <label><span>Distancia visual Luna–Tierra</span><input type="number" min="2.2" max="7" step="0.1" value="3.4" data-option="distance_scale"></label>
                    <label><span>Rotación de cámara</span><select data-option="rotation"><option value="free">Libre</option><option value="locked">Bloqueada</option></select></label>
                    <label><span>Zoom inicial</span><input type="number" min="0.65" max="1.8" step="0.05" value="1" data-option="zoom"></label>
                    <label><span>Seguimiento de sombra</span><select data-option="follow_shadow"><option value="0">Desactivado</option><option value="1">Activado</option></select></label>
                </fieldset>
            </div>
        </section>

        <section class="card lunar-widget-admin__presets" data-lunar-scene-presets hidden>
            <h2>Presets de escena lunar</h2>
            <p>Guardá y recuperá configuraciones completas de fecha, ubicación activa, Luna y cielo.</p>
            <label><span>Preset guardado</span><select data-preset-select><option value="">Seleccionar preset…</option></select></label>
            <label><span>Nombre</span><input type="text" maxlength="100" data-preset-name></label>
            <label><span>Descripción breve (opcional)</span><input type="text" maxlength="300" data-preset-description></label>
            <div class="lunar-widget-admin__preset-actions">
                <button type="button" class="button button-primary" data-preset-action="create">Guardar nuevo</button>
                <button type="button" class="button compact-secondary-button" data-preset-action="update" disabled>Actualizar</button>
                <button type="button" class="button compact-secondary-button" data-preset-action="duplicate" disabled>Duplicar</button>
                <button type="button" class="button compact-secondary-button" data-preset-action="delete" disabled>Eliminar</button>
            </div>
            <p data-preset-status role="status" aria-live="polite"></p>
        </section>

        <section class="card lunar-widget-admin__preview">
            <div><h2>Vista previa</h2><p data-widget-preview-status role="status">Actualización automática.</p></div>
            <div class="lunar-widget-admin__video-actions" data-libration-video-actions>
                <button type="button" class="button compact-secondary-button" data-export-video="16:9">Exportar video 16:9</button>
                <button type="button" class="button compact-secondary-button" data-export-video="9:16">Exportar video 9:16</button>
                <span data-video-export-status role="status" aria-live="polite"></span>
            </div>
            <div class="lunar-widget-admin__preview-frame" data-widget-preview-frame data-aspect="16:9">
                <iframe title="Vista previa del widget lunar" data-widget-preview loading="eager"></iframe>
            </div>
        </section>

        <section class="card lunar-widget-admin__output">
            <label><span>URL del embed</span><input type="url" inputmode="url" autocomplete="off" spellcheck="false" data-widget-url aria-describedby="widget-url-help"></label>
            <button type="button" class="button compact-secondary-button" data-copy-target="url">Copiar URL</button>
            <p id="widget-url-help" class="lunar-widget-admin__url-help">También podés pegar una URL de cualquiera de los widgets para cargar sus parámetros en el generador.</p>
            <label><span>Código iframe</span><textarea rows="4" readonly data-widget-iframe></textarea></label>
            <button type="button" class="button button-primary" data-copy-target="iframe">Copiar iframe</button>
            <p data-widget-copy-status role="status" aria-live="polite"></p>
        </section>
    </main>
    <script src="<?= $html('../../' . versionedAssetUrl('assets/js/lunar-widget-admin.js')) ?>" defer></script>
</body>
</html>
