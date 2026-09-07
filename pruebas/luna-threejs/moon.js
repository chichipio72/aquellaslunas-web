import * as THREE from './vendor/three.module.min.js';

const byId = (id) => document.querySelector(`#${id}`);
const stage = byId('moon-stage');
const loadingMessage = byId('loading-message');
const phaseOutput = byId('phase-output');
const reliefDescription = byId('relief-description');
const demStatus = byId('dem-status');
const astronomySummary = byId('astronomy-summary');
const customObserver = byId('custom-observer');
const customDatetime = byId('custom-datetime');
const customLatitude = byId('custom-latitude');
const customLongitude = byId('custom-longitude');
const loadCustomButton = byId('load-custom');
const loadCarapachayButton = byId('load-carapachay');
const featureAudit = byId('feature-audit');
const featureAuditList = byId('feature-audit-list');
const debugJson = byId('debug-json');
const debugClient = byId('debug-client');
const debugFields = {
    clock: byId('debug-clock'), cycle: byId('debug-cycle'),
    illumination: byId('debug-illumination'), phase: byId('debug-phase'),
};
const astronomyFields = {
    datetime: byId('astro-datetime'), location: byId('astro-location'),
    phase: byId('astro-phase'), illumination: byId('astro-illumination'),
    libration: byId('astro-libration'), orientation: byId('astro-orientation'),
    subobserver: byId('astro-subobserver'), subsolar: byId('astro-subsolar'),
    brightLimb: byId('astro-bright-limb'), horizontal: byId('astro-horizontal'),
};

const controls = {
    renderMode: byId('render-mode'),
    dayOffset: byId('astronomy-day-offset'),
    angle: byId('sun-angle'), elevation: byId('sun-elevation'),
    sunIntensity: byId('sun-intensity'), fillIntensity: byId('fill-intensity'),
    bumpScale: byId('bump-scale'), normalScaleX: byId('normal-scale-x'),
    normalScaleY: byId('normal-scale-y'), reliefMode: byId('relief-mode'),
    dem: byId('dem-resolution'), contrast: byId('texture-contrast'),
    brightness: byId('texture-brightness'), gamma: byId('texture-gamma'),
    saturation: byId('texture-saturation'), exposure: byId('render-exposure'),
};

const outputs = {
    dayOffset: byId('astronomy-day-output'),
    angle: byId('sun-angle-output'), elevation: byId('sun-elevation-output'),
    sunIntensity: byId('sun-intensity-output'), fillIntensity: byId('fill-intensity-output'),
    bumpScale: byId('bump-scale-output'), normalScaleX: byId('normal-scale-x-output'),
    normalScaleY: byId('normal-scale-y-output'), contrast: byId('texture-contrast-output'),
    brightness: byId('texture-brightness-output'), gamma: byId('texture-gamma-output'),
    saturation: byId('texture-saturation-output'), exposure: byId('render-exposure-output'),
};

const initialSettings = {
    angle: 70, elevation: 12, sunIntensity: 3.2, fillIntensity: 0.01,
    bumpScale: 0.035, normalScaleX: 1, normalScaleY: 1,
    reliefMode: 'normal', dem: 'medium', contrast: 1.15, brightness: 0.95,
    gamma: 0.95, saturation: 0.8, exposure: 1.15,
};

const presets = {
    reset: initialSettings,
    full: { ...initialSettings, angle: 0, elevation: 8, sunIntensity: 2.7, fillIntensity: 0.025 },
    terminator: {
        ...initialSettings, angle: 78, elevation: 5, sunIntensity: 3.2,
        fillIntensity: 0.005, contrast: 1.1, gamma: 1,
    },
    realistic: {
        ...initialSettings, angle: 48, elevation: 7, sunIntensity: 2.9,
        fillIntensity: 0.012, dem: 'high', contrast: 1.25, brightness: 0.9,
        gamma: 0.9, saturation: 0.65, exposure: 1.05,
    },
};

const demSources = {
    low: { label: 'LOLA 1K · 1024 × 512', height: './textures/ldem_3_8bit.jpg', normal: './textures/ldem_3_normal.png' },
    medium: { label: 'LOLA 4 ppd · 1440 × 720', height: './textures/ldem_4_height.png', normal: './textures/ldem_4_normal.png' },
    high: { label: 'LOLA alta · 2880 × 1440', height: './textures/ldem_8_height.png', normal: './textures/ldem_8_normal.png' },
};

const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
camera.position.set(0, 0, 4.15);

let renderer;
try {
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    stage.prepend(renderer.domElement);
} catch (error) {
    showError('Este navegador no pudo iniciar WebGL. Probá con una versión reciente de Firefox, Chrome o Safari.');
    throw error;
}

const sunlight = new THREE.DirectionalLight(0xfff7e8, 3.2);
sunlight.target.position.set(0, 0, 0);
const fillLight = new THREE.AmbientLight(0x73809b, 0.01);
scene.add(sunlight, sunlight.target, fillLight);

const textureLoader = new THREE.TextureLoader();
const demCache = new Map();
let moonMaterial = null;
let activeDem = null;
let demRequest = 0;
let diskOrientationGroup = null;
let librationLatitudeGroup = null;
let librationLongitudeGroup = null;
let astronomyData = null;
let astronomyRawJson = '';
let astronomyResponseMeta = null;
let astronomyRequestId = 0;
let astronomyReloadTimer = null;

function configureTexture(texture, colorSpace) {
    texture.colorSpace = colorSpace;
    texture.wrapS = THREE.RepeatWrapping;
    texture.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
    return texture;
}

function loadDem(key) {
    if (!demCache.has(key)) {
        const source = demSources[key];
        const startedAt = performance.now();
        demCache.set(key, Promise.all([
            textureLoader.loadAsync(source.height), textureLoader.loadAsync(source.normal),
        ]).then(([heightMap, normalMap]) => ({
            heightMap: configureTexture(heightMap, THREE.NoColorSpace),
            normalMap: configureTexture(normalMap, THREE.NoColorSpace),
            loadTime: performance.now() - startedAt,
        })));
    }
    return demCache.get(key);
}

Promise.all([textureLoader.loadAsync('./textures/lroc_color_2k.jpg'), loadDem(initialSettings.dem)])
    .then(([albedo, dem]) => {
        configureTexture(albedo, THREE.SRGBColorSpace);
        activeDem = initialSettings.dem;
        moonMaterial = new THREE.MeshStandardMaterial({
            map: albedo, normalMap: dem.normalMap, roughness: 1, metalness: 0,
        });
        addAppearanceControls(moonMaterial);

        const moon = new THREE.Mesh(new THREE.SphereGeometry(1, 256, 128), moonMaterial);
        // SphereGeometry mira U=0,25 hacia la cámara; −90° coloca U=0,5
        // (longitud lunar 0°) al frente. +90° mostraba la cara lejana.
        moon.rotation.y = -Math.PI / 2;
        librationLongitudeGroup = new THREE.Group();
        librationLatitudeGroup = new THREE.Group();
        diskOrientationGroup = new THREE.Group();
        librationLongitudeGroup.add(moon);
        librationLatitudeGroup.add(librationLongitudeGroup);
        diskOrientationGroup.add(librationLatitudeGroup);
        scene.add(diskOrientationGroup);

        loadingMessage.remove();
        applySettings(initialSettings);
        demStatus.textContent = `${demSources[activeDem].label} cargado en ${Math.round(dem.loadTime)} ms.`;
        resizeRenderer();
        renderer.setAnimationLoop(() => renderer.render(scene, camera));
        loadAstronomy();
    }).catch((error) => {
        console.error(error);
        showError('No se pudieron cargar las texturas. Abrí esta carpeta mediante el servidor local, no con una URL file://.');
    });

function addAppearanceControls(material) {
    const uniforms = {
        textureContrast: { value: 1 }, textureBrightness: { value: 1 },
        textureGamma: { value: 1 }, textureSaturation: { value: 1 },
    };
    material.userData.appearanceUniforms = uniforms;
    material.onBeforeCompile = (shader) => {
        Object.assign(shader.uniforms, uniforms);
        shader.fragmentShader = shader.fragmentShader
            .replace('#include <map_pars_fragment>', `#include <map_pars_fragment>
uniform float textureContrast;
uniform float textureBrightness;
uniform float textureGamma;
uniform float textureSaturation;`)
            .replace('#include <map_fragment>', `#include <map_fragment>
diffuseColor.rgb = (diffuseColor.rgb - 0.5) * textureContrast + 0.5;
diffuseColor.rgb *= textureBrightness;
float textureLuma = dot(diffuseColor.rgb, vec3(0.2126, 0.7152, 0.0722));
diffuseColor.rgb = mix(vec3(textureLuma), diffuseColor.rgb, textureSaturation);
diffuseColor.rgb = pow(max(diffuseColor.rgb, vec3(0.0)), vec3(1.0 / textureGamma));`);
    };
    material.customProgramCacheKey = () => 'moon-appearance-v1';
}

async function updateScene() {
    const astronomicalMode = controls.renderMode.value !== 'manual';
    let angle = THREE.MathUtils.degToRad(Number(controls.angle.value));
    let elevation = THREE.MathUtils.degToRad(Number(controls.elevation.value));
    const distance = 5;
    if (astronomicalMode && astronomyData) {
        const incidence = THREE.MathUtils.degToRad(astronomyData.phase.incidence_angle_degrees);
        const brightLimb = THREE.MathUtils.degToRad(astronomyData.orientation.bright_limb_angle_degrees);
        const subobserver = astronomyData.surface_geometry.subobserver;
        const subsolar = astronomyData.surface_geometry.subsolar;
        const longitudeRotation = -THREE.MathUtils.degToRad(subobserver.longitude_degrees);
        const latitudeRotation = THREE.MathUtils.degToRad(subobserver.latitude_degrees);
        const diskRotation = -THREE.MathUtils.degToRad(astronomyData.orientation.lunar_north_screen_angle_degrees);
        librationLongitudeGroup.rotation.y = longitudeRotation;
        librationLatitudeGroup.rotation.x = latitudeRotation;
        diskOrientationGroup.rotation.z = -THREE.MathUtils.degToRad(astronomyData.orientation.lunar_north_screen_angle_degrees);
        sunlight.position.set(
            subsolar.body_fixed_unit_vector.x,
            subsolar.body_fixed_unit_vector.y,
            subsolar.body_fixed_unit_vector.z,
        ).applyAxisAngle(new THREE.Vector3(0, 1, 0), longitudeRotation)
            .applyAxisAngle(new THREE.Vector3(1, 0, 0), latitudeRotation)
            .applyAxisAngle(new THREE.Vector3(0, 0, 1), diskRotation)
            .multiplyScalar(distance);
        renderClientTrace({ incidence, brightLimb, distance });
    } else {
        sunlight.position.set(
            Math.sin(angle) * Math.cos(elevation) * distance,
            Math.sin(elevation) * distance,
            Math.cos(angle) * Math.cos(elevation) * distance,
        );
        if (diskOrientationGroup) {
            librationLongitudeGroup.rotation.y = 0;
            librationLatitudeGroup.rotation.x = 0;
            diskOrientationGroup.rotation.z = 0;
        }
    }
    sunlight.intensity = Number(controls.sunIntensity.value);
    fillLight.intensity = Number(controls.fillIntensity.value);
    renderer.toneMappingExposure = Number(controls.exposure.value);

    if (moonMaterial) {
        if (activeDem !== controls.dem.value) {
            const requestedDem = controls.dem.value;
            const requestId = ++demRequest;
            demStatus.textContent = `Cargando ${demSources[requestedDem].label}…`;
            try {
                const loadedDem = await loadDem(requestedDem);
                if (requestId !== demRequest || controls.dem.value !== requestedDem) return;
                activeDem = requestedDem;
                demStatus.textContent = `${demSources[activeDem].label} cargado en ${Math.round(loadedDem.loadTime)} ms.`;
            } catch (error) {
                console.error(error);
                demStatus.textContent = 'No se pudo cargar ese DEM; se conserva el anterior.';
                controls.dem.value = activeDem;
            }
        }

        const dem = await loadDem(activeDem);
        const reliefMode = controls.reliefMode.value;
        const nextBumpMap = reliefMode === 'bump' ? dem.heightMap : null;
        const nextNormalMap = reliefMode === 'normal' ? dem.normalMap : null;
        const mapsChanged = moonMaterial.bumpMap !== nextBumpMap || moonMaterial.normalMap !== nextNormalMap;
        moonMaterial.bumpMap = nextBumpMap;
        moonMaterial.normalMap = nextNormalMap;
        moonMaterial.bumpScale = Number(controls.bumpScale.value);
        moonMaterial.normalScale.set(Number(controls.normalScaleX.value), Number(controls.normalScaleY.value));
        moonMaterial.needsUpdate = mapsChanged;

        const uniforms = moonMaterial.userData.appearanceUniforms;
        uniforms.textureContrast.value = Number(controls.contrast.value);
        uniforms.textureBrightness.value = Number(controls.brightness.value);
        uniforms.textureGamma.value = Number(controls.gamma.value);
        uniforms.textureSaturation.value = Number(controls.saturation.value);

        controls.bumpScale.disabled = reliefMode !== 'bump';
        controls.normalScaleX.disabled = reliefMode !== 'normal';
        controls.normalScaleY.disabled = reliefMode !== 'normal';
        reliefDescription.textContent = {
            bump: 'Bump map activo: el DEM se interpreta en tiempo real como altura aparente.',
            normal: 'Normal map activo: pendientes OpenGL derivadas del DEM LOLA seleccionado.',
            none: 'Sin relieve: sólo albedo sobre una esfera lisa, como referencia.',
        }[reliefMode];
    }
    controls.angle.disabled = astronomicalMode;
    controls.elevation.disabled = astronomicalMode;
    controls.dayOffset.disabled = controls.renderMode.value !== 'astronomical';
    customObserver.disabled = controls.renderMode.value !== 'custom';
    astronomySummary.hidden = !astronomicalMode;
    featureAudit.hidden = !astronomicalMode || !astronomyData;
    updateOutputs(angle, elevation, astronomicalMode && astronomyData ? astronomyData.phase.illumination_percent : null);
}

function updateOutputs(angle, elevation, astronomicalIllumination = null) {
    const illuminatedFraction = astronomicalIllumination ?? ((1 + Math.cos(angle) * Math.cos(elevation)) / 2) * 100;
    outputs.angle.value = `${controls.angle.value}°`;
    outputs.elevation.value = `${controls.elevation.value}°`;
    outputs.sunIntensity.value = Number(controls.sunIntensity.value).toFixed(1);
    outputs.fillIntensity.value = Number(controls.fillIntensity.value).toFixed(3);
    outputs.bumpScale.value = Number(controls.bumpScale.value).toFixed(3);
    outputs.normalScaleX.value = Number(controls.normalScaleX.value).toFixed(2);
    outputs.normalScaleY.value = Number(controls.normalScaleY.value).toFixed(2);
    outputs.contrast.value = Number(controls.contrast.value).toFixed(2);
    outputs.brightness.value = Number(controls.brightness.value).toFixed(2);
    outputs.gamma.value = Number(controls.gamma.value).toFixed(2);
    outputs.saturation.value = Number(controls.saturation.value).toFixed(2);
    outputs.exposure.value = Number(controls.exposure.value).toFixed(2);
    phaseOutput.value = `Iluminación: ${illuminatedFraction.toFixed(1)} %`;
}

async function loadAstronomy() {
    const requestId = ++astronomyRequestId;
    const dayOffset = Number(controls.dayOffset.value);
    astronomyFields.datetime.textContent = 'Calculando…';
    try {
        const parameters = new URLSearchParams({ clock: 'system', day_offset: String(dayOffset) });
        if (controls.renderMode.value === 'custom') {
            parameters.set('local_datetime', customDatetime.value);
            parameters.set('latitude', customLatitude.value);
            parameters.set('longitude', customLongitude.value);
            const isCarapachay = customDatetime.value === '2026-08-16T19:01'
                && customLatitude.value === '-34.532989356165835'
                && customLongitude.value === '-58.537805002828605';
            parameters.set('location_name', isCarapachay ? 'Carapachay, Provincia de Buenos Aires' : 'Ubicación personalizada');
        }
        const response = await fetch(`./astronomy.php?${parameters}`, { cache: 'no-store' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const rawJson = await response.text();
        if (requestId !== astronomyRequestId) return;
        astronomyRawJson = rawJson;
        astronomyData = JSON.parse(rawJson);
        astronomyResponseMeta = {
            url: response.url,
            cacheControl: response.headers.get('cache-control'),
            age: response.headers.get('age'),
        };
        renderAstronomySummary();
        updateScene();
    } catch (error) {
        console.error(error);
        astronomyFields.datetime.textContent = 'No se pudieron cargar los parámetros astronómicos.';
    }
}

function renderAstronomySummary() {
    const local = new Date(astronomyData.datetime.local);
    const dateLabel = new Intl.DateTimeFormat('es-AR', {
        dateStyle: 'medium', timeStyle: 'medium', timeZone: astronomyData.datetime.timezone,
    }).format(local);
    const signed = (value) => `${value >= 0 ? '+' : ''}${Number(value).toFixed(2)}°`;
    astronomyFields.datetime.textContent = `${dateLabel}${astronomyData.datetime.simulated ? ' · simulada' : ''}`;
    const offset = Number(astronomyData.debug.day_offset);
    const datePrefix = astronomyData.datetime.source === 'custom_local_datetime'
        ? 'Personalizada'
        : (offset === 0 ? 'Hoy' : `${offset > 0 ? '+' : ''}${offset} días`);
    outputs.dayOffset.value = `${datePrefix} · ${new Intl.DateTimeFormat('es-AR', {
        day: '2-digit', month: '2-digit', year: 'numeric', timeZone: astronomyData.datetime.timezone,
    }).format(local)}`;
    astronomyFields.location.textContent = `${astronomyData.location.name} · ${astronomyData.location.latitude.toFixed(2)}°, ${astronomyData.location.longitude.toFixed(2)}°`;
    astronomyFields.phase.textContent = astronomyData.phase.name;
    astronomyFields.illumination.textContent = `${astronomyData.phase.illumination_percent.toFixed(2)} %`;
    astronomyFields.libration.textContent = `lon ${signed(astronomyData.libration.longitude_degrees)} · lat ${signed(astronomyData.libration.latitude_degrees)}`;
    astronomyFields.subobserver.textContent = `lon ${signed(astronomyData.surface_geometry.subobserver.longitude_degrees)} · lat ${signed(astronomyData.surface_geometry.subobserver.latitude_degrees)}`;
    astronomyFields.subsolar.textContent = `lon ${signed(astronomyData.surface_geometry.subsolar.longitude_degrees)} · lat ${signed(astronomyData.surface_geometry.subsolar.latitude_degrees)}`;
    astronomyFields.orientation.textContent = `${signed(astronomyData.orientation.lunar_north_screen_angle_degrees)} desde el cenit`;
    astronomyFields.brightLimb.textContent = `${signed(astronomyData.orientation.bright_limb_angle_degrees)} desde el cenit`;
    astronomyFields.horizontal.textContent = `${astronomyData.observer.moon_altitude_degrees.toFixed(2)}° / ${astronomyData.observer.moon_azimuth_degrees.toFixed(2)}°${astronomyData.observer.above_horizon ? '' : ' · bajo el horizonte'}`;
    debugFields.clock.textContent = `${astronomyData.datetime.local} · ${astronomyData.datetime.timezone} · ${astronomyData.debug.clock_source}`;
    debugFields.cycle.textContent = String(astronomyData.debug.cycle_fraction_raw);
    debugFields.illumination.textContent = String(astronomyData.debug.illuminated_fraction_raw);
    debugFields.phase.textContent = `${astronomyData.debug.phase_name_calculated} · waxing=${astronomyData.debug.waxing_calculated}`;
    debugJson.textContent = astronomyRawJson;
    renderFeatureAudit();
}

function renderFeatureAudit() {
    featureAuditList.replaceChildren(...astronomyData.feature_audit.map((feature) => {
        const row = document.createElement('div');
        row.className = 'feature-audit__row';
        const distance = Number(feature.signed_distance_from_terminator_degrees);
        row.innerHTML = `<strong>${feature.name}</strong><span>${feature.visible ? 'visible' : 'cara oculta'}</span><span>${distance >= 0 ? '+' : ''}${distance.toFixed(2)}° del terminador</span><span>incidencia ${Number(feature.solar_incidence_angle_degrees).toFixed(2)}°</span>`;
        return row;
    }));
}

function renderClientTrace({ incidence, brightLimb, distance }) {
    const received = {
        datetime: astronomyData.datetime,
        phase: astronomyData.phase,
        surface_geometry: astronomyData.surface_geometry,
        feature_audit: astronomyData.feature_audit,
        debug: astronomyData.debug,
    };
    const usedForRender = {
        mode: controls.renderMode.value,
        incidence_degrees: astronomyData.phase.incidence_angle_degrees,
        incidence_radians: incidence,
        bright_limb_degrees: astronomyData.orientation.bright_limb_angle_degrees,
        bright_limb_radians: brightLimb,
        light_position: sunlight.position.toArray(),
        light_direction_normalized: sunlight.position.clone().normalize().toArray(),
        illumination_from_light_vector: (1 + sunlight.position.z / distance) / 2,
        libration_longitude_rotation_degrees: THREE.MathUtils.radToDeg(librationLongitudeGroup.rotation.y),
        libration_latitude_rotation_degrees: THREE.MathUtils.radToDeg(librationLatitudeGroup.rotation.x),
        disk_rotation_degrees: THREE.MathUtils.radToDeg(diskOrientationGroup.rotation.z),
    };
    debugClient.textContent = JSON.stringify({ response: astronomyResponseMeta, received, usedForRender }, null, 2);
}

function applySettings(settings) {
    Object.entries(settings).forEach(([key, value]) => {
        if (controls[key]) controls[key].value = value;
    });
    updateScene();
}

function resizeRenderer() {
    const width = stage.clientWidth;
    const height = stage.clientHeight;
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
}

function showError(message) {
    loadingMessage.textContent = message;
    loadingMessage.classList.add('status--error');
}

Object.values(controls).forEach((control) => {
    control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', updateScene);
});
controls.dayOffset.addEventListener('input', () => {
    clearTimeout(astronomyReloadTimer);
    astronomyReloadTimer = setTimeout(loadAstronomy, 120);
});
controls.renderMode.addEventListener('change', () => {
    updateScene();
    if (controls.renderMode.value !== 'manual') loadAstronomy();
});
loadCustomButton.addEventListener('click', loadAstronomy);
loadCarapachayButton.addEventListener('click', () => {
    customDatetime.value = '2026-08-16T19:01';
    customLatitude.value = '-34.532989356165835';
    customLongitude.value = '-58.537805002828605';
    controls.renderMode.value = 'custom';
    updateScene();
    loadAstronomy();
});
document.querySelectorAll('[data-preset]').forEach((button) => {
    button.addEventListener('click', () => {
        controls.renderMode.value = 'manual';
        applySettings(presets[button.dataset.preset]);
    });
});
window.addEventListener('resize', resizeRenderer);
