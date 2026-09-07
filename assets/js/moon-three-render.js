import * as THREE from './vendor/three.module.min.js';

const axisX = new THREE.Vector3(1, 0, 0);
const axisY = new THREE.Vector3(0, 1, 0);
const axisZ = new THREE.Vector3(0, 0, 1);

function configureTexture(texture, renderer, colorSpace) {
  texture.colorSpace = colorSpace;
  texture.wrapS = THREE.RepeatWrapping;
  texture.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
  return texture;
}

function addAppearanceShader(material, appearance) {
  const uniforms = {
    textureContrast: { value: Number(appearance.texture_contrast) },
    textureBrightness: { value: Number(appearance.texture_brightness) },
    textureGamma: { value: Number(appearance.texture_gamma) },
    textureSaturation: { value: Number(appearance.texture_saturation) },
    earthshineIntensity: { value: Math.max(0, Number(appearance.earthshine_intensity) || 0) },
    shadowSkyMix: { value: Math.max(0, Math.min(1, Number(appearance.shadow_sky_mix) || 0)) },
    darkSideOpacity: { value: Math.max(0, Math.min(1, appearance.dark_side_opacity === undefined ? 1 : Number(appearance.dark_side_opacity))) },
    darkLimbSoftness: { value: Math.max(0, Math.min(0.3, Number(appearance.dark_limb_softness) || 0)) },
    sceneSkyColor: { value: new THREE.Color(/^#[0-9a-f]{6}$/i.test(String(appearance.scene_sky_color)) ? appearance.scene_sky_color : '#000000') },
    edgeSoftness: { value: Math.max(0, Math.min(0.15, Number(appearance.edge_softness) || 0)) },
    terminatorSoftness: { value: Math.max(0.01, Math.min(0.35, Number(appearance.terminator_softness) || 0.13)) },
    litTint: { value: new THREE.Color(/^#[0-9a-f]{6}$/i.test(String(appearance.lit_tint)) ? appearance.lit_tint : '#ffffff') },
    terminatorAttenuationEnabled: { value: appearance.relief_mode === 'normal' && appearance.terminator_attenuation_enabled === 'on' ? 1 : 0 },
    terminatorAttenuationWidth: { value: Math.sin(THREE.MathUtils.degToRad(Number(appearance.terminator_attenuation_width_degrees) || 8)) },
    terminatorAttenuationMinimum: { value: Math.max(0, Math.min(1, (Number(appearance.terminator_attenuation_minimum_percent) || 0) / 100)) },
    terminatorAttenuationCurve: { value: Math.max(0.25, Number(appearance.terminator_attenuation_curve) || 1.5) },
  };
  material.onBeforeCompile = (shader) => {
    Object.assign(shader.uniforms, uniforms);
    shader.fragmentShader = shader.fragmentShader
      .replace('#include <map_pars_fragment>', `#include <map_pars_fragment>
uniform float textureContrast;
uniform float textureBrightness;
uniform float textureGamma;
uniform float textureSaturation;
uniform float earthshineIntensity;
uniform float shadowSkyMix;
uniform float darkSideOpacity;
uniform float darkLimbSoftness;
uniform vec3 sceneSkyColor;
uniform float edgeSoftness;
uniform float terminatorSoftness;
uniform vec3 litTint;
uniform float terminatorAttenuationEnabled;
uniform float terminatorAttenuationWidth;
uniform float terminatorAttenuationMinimum;
uniform float terminatorAttenuationCurve;`)
      .replace('#include <normal_fragment_maps>', `vec3 moonGeometricNormal = normal;
#include <normal_fragment_maps>
if (terminatorAttenuationEnabled > 0.5) {
  float moonTerminatorProximity = abs(dot(normalize(moonGeometricNormal), normalize(directionalLights[0].direction)));
  float moonNormalWeight = smoothstep(0.0, max(terminatorAttenuationWidth, 0.0001), moonTerminatorProximity);
  moonNormalWeight = pow(moonNormalWeight, terminatorAttenuationCurve);
  moonNormalWeight = mix(terminatorAttenuationMinimum, 1.0, moonNormalWeight);
  normal = normalize(mix(moonGeometricNormal, normal, moonNormalWeight));
}`)
      .replace('#include <map_fragment>', `#include <map_fragment>
diffuseColor.rgb = (diffuseColor.rgb - 0.5) * textureContrast + 0.5;
diffuseColor.rgb *= textureBrightness;
float textureLuma = dot(diffuseColor.rgb, vec3(0.2126, 0.7152, 0.0722));
diffuseColor.rgb = mix(vec3(textureLuma), diffuseColor.rgb, textureSaturation);
diffuseColor.rgb = pow(max(diffuseColor.rgb, vec3(0.0)), vec3(1.0 / textureGamma));`)
      .replace('#include <opaque_fragment>', `float moonSolarIncidence = dot(normalize(moonGeometricNormal), normalize(directionalLights[0].direction));
float moonShadowWeight = 1.0 - smoothstep(-terminatorSoftness, terminatorSoftness, moonSolarIncidence);
float moonLitWeight = 1.0 - moonShadowWeight;
outgoingLight += diffuseColor.rgb * earthshineIntensity * moonShadowWeight;
outgoingLight = mix(outgoingLight, sceneSkyColor, shadowSkyMix * moonShadowWeight);
vec3 moonNormalizedTint = litTint / max(max(litTint.r, litTint.g), max(litTint.b, 0.001));
outgoingLight *= mix(vec3(1.0), moonNormalizedTint, moonLitWeight);
float moonGeometricLimbFacing = abs(dot(normalize(moonGeometricNormal), vec3(0.0, 0.0, 1.0)));
float moonDarkLimbAlpha = darkLimbSoftness > 0.0 ? smoothstep(0.0, darkLimbSoftness, moonGeometricLimbFacing) : 1.0;
diffuseColor.a *= mix(1.0, darkSideOpacity * moonDarkLimbAlpha, moonShadowWeight);
if (edgeSoftness > 0.0) {
  float moonLimbFacing = abs(dot(normalize(normal), vec3(0.0, 0.0, 1.0)));
  diffuseColor.a *= smoothstep(0.0, edgeSoftness, moonLimbFacing);
}
#include <opaque_fragment>`);
  };
  material.transparent = Number(appearance.edge_softness) > 0 || Number(appearance.dark_side_opacity) < 1 || Number(appearance.dark_limb_softness) > 0;
  material.customProgramCacheKey = () => 'aquellas-lunas-moon-appearance-v3';
  return uniforms;
}

function wallpaperColor(hex, brightness, gradientOffset = 0) {
  const normalized = /^#[0-9a-f]{6}$/i.test(hex) ? hex.slice(1) : '000000';
  const adjustment = Math.round((brightness + gradientOffset) * 255);
  const channels = [0, 2, 4].map((offset) => Math.max(0, Math.min(255, parseInt(normalized.slice(offset, offset + 2), 16) + adjustment)));
  return `rgb(${channels.join(', ')})`;
}

function paintWallpaperBackground(context, width, height, wallpaper = {}) {
  const color = String(wallpaper.background_color || '#000000');
  const brightness = Math.min(0.3, Math.max(0, Number(wallpaper.background_brightness) || 0));
  const gradientStrength = Math.min(0.3, Math.max(0, Number(wallpaper.background_gradient) || 0));
  const gradient = context.createLinearGradient(0, 0, width, 0);
  gradient.addColorStop(0, wallpaperColor(color, brightness, -gradientStrength * 0.35));
  gradient.addColorStop(0.5, wallpaperColor(color, brightness, gradientStrength));
  gradient.addColorStop(1, wallpaperColor(color, brightness, -gradientStrength * 0.35));
  context.fillStyle = gradient;
  context.fillRect(0, 0, width, height);
}

function glowRgba(hex, alpha) {
  const normalized = /^#[0-9a-f]{6}$/i.test(hex) ? hex.slice(1) : 'dbe5ff';
  const channels = [0, 2, 4].map((offset) => parseInt(normalized.slice(offset, offset + 2), 16));
  return `rgba(${channels.join(', ')}, ${Math.max(0, Math.min(1, alpha)).toFixed(4)})`;
}

function smoothstep(minimum, maximum, value) {
  const normalized = Math.max(0, Math.min(1, (value - minimum) / (maximum - minimum)));
  return normalized * normalized * (3 - 2 * normalized);
}

function paintGlowGradient(context, centerX, centerY, moonRadius, outerRadius, color, alpha, softness) {
  if (alpha <= 0 || outerRadius <= moonRadius) return;
  const gradient = context.createRadialGradient(centerX, centerY, moonRadius * 0.55, centerX, centerY, outerRadius);
  const limbStop = Math.min(0.82, moonRadius / outerRadius);
  const fadeStop = limbStop + (1 - limbStop) * (1 - softness * 0.72);
  gradient.addColorStop(0, glowRgba(color, alpha * 0.12));
  gradient.addColorStop(limbStop, glowRgba(color, alpha));
  gradient.addColorStop(Math.min(0.98, fadeStop), glowRgba(color, alpha * 0.2));
  gradient.addColorStop(1, glowRgba(color, 0));
  context.fillStyle = gradient;
  context.fillRect(centerX - outerRadius, centerY - outerRadius, outerRadius * 2, outerRadius * 2);
}

function paintDiffuseGradient(context, centerX, centerY, radius, color, alpha, softness, rotation = 0, stretchX = 1, stretchY = 1) {
  if (alpha <= 0 || radius <= 0) return;
  const fadeStart = 0.12 + (1 - softness) * 0.18;
  const gradient = context.createRadialGradient(0, 0, 0, 0, 0, radius);
  gradient.addColorStop(0, glowRgba(color, alpha * 0.55));
  gradient.addColorStop(fadeStart, glowRgba(color, alpha * 0.42));
  gradient.addColorStop(0.55, glowRgba(color, alpha * 0.14));
  gradient.addColorStop(0.82, glowRgba(color, alpha * 0.035));
  gradient.addColorStop(1, glowRgba(color, 0));
  context.save();
  context.translate(centerX, centerY);
  context.rotate(rotation);
  context.scale(stretchX, stretchY);
  context.fillStyle = gradient;
  context.fillRect(-radius, -radius, radius * 2, radius * 2);
  context.restore();
}

function paintSkyDiffusion(context, centerX, centerY, moonRadius, payload, settings) {
  const { illumination, intensity, radiusFactor, softness, color, directionalWeight, symmetricWeight, fullMoonBoost, brightLimbRadians } = settings;
  const directionX = Math.sin(brightLimbRadians);
  const directionY = -Math.cos(brightLimbRadians);
  const directionAngle = Math.atan2(directionY, directionX);
  const phaseOpening = smoothstep(0.08, 0.88, illumination);
  const fullOpening = smoothstep(0.78, 1, illumination);
  const extent = moonRadius * radiusFactor * (1.12 + phaseOpening * 0.38);
  const directionalAlpha = intensity * 0.48 * directionalWeight * Math.sqrt(illumination) * Math.pow(1 - illumination, 0.42);
  const symmetricAlpha = intensity * 0.32 * (symmetricWeight * Math.pow(illumination, 1.15) + fullMoonBoost * fullOpening);
  const directionalOffset = moonRadius * (0.72 - phaseOpening * 0.28);

  // Varias capas elípticas de baja opacidad evitan un borde o radio reconocible.
  paintDiffuseGradient(context, centerX, centerY, extent, color, symmetricAlpha, softness, 0, 1.16, 0.92 + fullOpening * 0.16);
  paintDiffuseGradient(
    context,
    centerX + directionX * directionalOffset,
    centerY + directionY * directionalOffset,
    extent * 0.86,
    color,
    directionalAlpha,
    softness,
    directionAngle,
    1.34 - phaseOpening * 0.14,
    0.62 + phaseOpening * 0.28,
  );
  paintDiffuseGradient(
    context,
    centerX + directionX * directionalOffset * 0.45,
    centerY + directionY * directionalOffset * 0.45,
    extent * 1.08,
    color,
    directionalAlpha * 0.34,
    Math.min(1, softness + 0.15),
    directionAngle,
    1.08,
    0.88,
  );
}

function paintMoonGlow(context, centerX, centerY, moonRadius, payload) {
  const wallpaper = payload.wallpaper || {};
  const mode = String(wallpaper.glow_mode || (wallpaper.glow_enabled === 'on' ? 'halo' : 'off'));
  if (mode === 'off') return;
  const illumination = Math.max(0, Math.min(1, Number(payload.geometry?.phase?.illumination_fraction) || 0));
  const intensity = Math.max(0, Math.min(1, Number(wallpaper.glow_intensity) || 0));
  const radiusFactor = Math.max(1.05, Math.min(3, Number(wallpaper.glow_radius) || 1.65));
  const softness = Math.max(0.1, Math.min(1, Number(wallpaper.glow_softness) || 0.75));
  const color = String(wallpaper.glow_color || '#dbe5ff');
  const directionalWeight = Math.max(0, Math.min(2, Number(wallpaper.glow_directional_weight) || 0));
  const symmetricWeight = Math.max(0, Math.min(2, Number(wallpaper.glow_symmetric_weight) || 0));
  const fullMoonBoost = Math.max(0, Math.min(2, Number(wallpaper.glow_full_moon_boost) || 0));
  const directionalAlpha = intensity * directionalWeight * Math.sqrt(illumination) * Math.pow(1 - illumination, 0.55);
  const symmetricAlpha = intensity * (symmetricWeight * Math.pow(illumination, 1.2) + fullMoonBoost * smoothstep(0.82, 1, illumination));
  const outerRadius = moonRadius * radiusFactor * (0.82 + illumination * 0.22);
  const brightLimbRadians = THREE.MathUtils.degToRad(Number(payload.geometry?.orientation?.bright_limb_angle_degrees) || 0);
  const offset = moonRadius * (0.56 - illumination * 0.3);
  context.save();
  context.globalCompositeOperation = 'screen';
  if (mode === 'sky_diffusion') {
    paintSkyDiffusion(context, centerX, centerY, moonRadius, payload, {
      illumination, intensity, radiusFactor, softness, color, directionalWeight, symmetricWeight, fullMoonBoost, brightLimbRadians,
    });
  } else {
    paintGlowGradient(context, centerX, centerY, moonRadius, outerRadius, color, symmetricAlpha, softness);
    paintGlowGradient(
      context,
      centerX + Math.sin(brightLimbRadians) * offset,
      centerY - Math.cos(brightLimbRadians) * offset,
      moonRadius * 0.72,
      outerRadius,
      color,
      directionalAlpha,
      softness,
    );
  }
  context.restore();
}

function canvasBlob(canvas) {
  return new Promise((resolve, reject) => canvas.toBlob(
    (blob) => blob ? resolve(blob) : reject(new Error('El navegador no pudo crear el PNG.')),
    'image/png',
  ));
}

function paintWallpaperText(context, width, height, copy) {
  if (!copy || typeof copy !== 'object') return;
  const portrait = height > width;
  const unit = Math.min(width, height);
  const left = portrait ? width * 0.1 : width * 0.64;
  const top = portrait ? height * 0.74 : height * 0.58;
  const align = portrait ? 'left' : 'left';
  context.save();
  context.textAlign = align;
  context.textBaseline = 'top';
  context.shadowColor = 'rgba(0, 0, 0, .72)';
  context.shadowBlur = unit * 0.018;
  context.fillStyle = 'rgba(218, 229, 248, .74)';
  context.font = `600 ${Math.round(unit * 0.026)}px system-ui, sans-serif`;
  context.letterSpacing = `${Math.round(unit * 0.004)}px`;
  context.fillText(String(copy.eyebrow || '').toUpperCase(), left, top);
  context.letterSpacing = '0px';
  context.fillStyle = '#f1d995';
  context.font = `500 ${Math.round(unit * (portrait ? 0.065 : 0.052))}px Georgia, serif`;
  context.fillText(String(copy.phase || ''), left, top + unit * 0.055);
  context.fillStyle = 'rgba(240, 244, 252, .9)';
  context.font = `600 ${Math.round(unit * 0.026)}px system-ui, sans-serif`;
  context.fillText(String(copy.illumination || ''), left, top + unit * 0.14);
  context.fillStyle = 'rgba(200, 211, 232, .82)';
  context.font = `400 ${Math.round(unit * 0.024)}px system-ui, sans-serif`;
  context.fillText(`${String(copy.date || '')} · ${String(copy.time || '')}`, left, top + unit * 0.185);
  context.restore();
}

async function mountMoon(container) {
  const componentStartedAt = performance.now();
  const diagnosticStatus = document.querySelector('[data-moon-three-diagnostic-status]');
  const dataElement = container.querySelector('[data-moon-three-payload]');
  const fallbackImage = container.querySelector('.moon-three-render__fallback');
  const showFallback = () => {
    if (fallbackImage instanceof HTMLImageElement) {
      if (!fallbackImage.getAttribute('src') && fallbackImage.dataset.fallbackSrc) fallbackImage.src = fallbackImage.dataset.fallbackSrc;
      fallbackImage.hidden = false;
    }
    container.classList.remove('is-rendered');
    container.dataset.moonThreeState = 'fallback';
  };
  if (!dataElement) return;

  let payload;
  let previewCanvas = null;
  let renderScale = 1;
  let previewScale = 1;
  try {
    payload = JSON.parse(dataElement.textContent || '');
    const sizePercent = Math.min(120, Math.max(85, Number(payload.appearance.size_percent) || 100));
    previewScale = container.hasAttribute('data-moon-admin-preview') || container.hasAttribute('data-moon-wallpaper-preview') ? 0.76 : 1;
    renderScale = (sizePercent / 100) * previewScale;
    container.style.setProperty('--moon-render-scale', String(renderScale));
    if (container.hasAttribute('data-moon-wallpaper-preview')) {
      previewCanvas = document.createElement('canvas');
      previewCanvas.className = 'moon-wallpaper-preview__background';
      previewCanvas.setAttribute('aria-hidden', 'true');
      container.prepend(previewCanvas);
    }
  } catch (error) {
    if (diagnosticStatus) diagnosticStatus.textContent = `configuración inválida tras ${(performance.now() - componentStartedAt).toFixed(1)} ms`;
    console.error('Luna Three.js: configuración inválida.', error);
    showFallback();
    return;
  }

  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({
      antialias: true,
      alpha: true,
      powerPreference: 'high-performance',
      preserveDrawingBuffer: container.hasAttribute('data-moon-capture'),
    });
  } catch (error) {
    if (diagnosticStatus) diagnosticStatus.textContent = `WebGL no disponible tras ${(performance.now() - componentStartedAt).toFixed(1)} ms`;
    console.error('Luna Three.js: WebGL no disponible.', error);
    showFallback();
    return;
  }

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
  camera.position.set(0, 0, 4.15);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = Number(payload.appearance.exposure);
  renderer.domElement.className = 'moon-three-render__canvas';
  renderer.domElement.setAttribute('aria-hidden', 'true');

  const loader = new THREE.TextureLoader();
  try {
    const resourcesStartedAt = performance.now();
    const requests = [loader.loadAsync(payload.textures.albedo)];
    if (payload.textures.relief) requests.push(loader.loadAsync(payload.textures.relief));
    const [albedo, initialReliefMap = null] = await Promise.all(requests);
    let reliefMap = initialReliefMap;
    const resourcesMilliseconds = performance.now() - resourcesStartedAt;
    const preparationStartedAt = performance.now();
    configureTexture(albedo, renderer, THREE.SRGBColorSpace);
    if (reliefMap) configureTexture(reliefMap, renderer, THREE.NoColorSpace);

    const materialOptions = { map: albedo, roughness: 1, metalness: 0 };
    if (payload.appearance.relief_mode === 'normal') materialOptions.normalMap = reliefMap;
    if (payload.appearance.relief_mode === 'bump') materialOptions.bumpMap = reliefMap;
    const material = new THREE.MeshStandardMaterial(materialOptions);
    material.bumpScale = Number(payload.appearance.bump_scale);
    material.normalScale.set(Number(payload.appearance.normal_scale_x), Number(payload.appearance.normal_scale_y));
    const appearanceUniforms = addAppearanceShader(material, payload.appearance);
    const highResolution = payload.appearance.high_resolution || {};
    const highResolutionAllowed = highResolution.enabled === 'on'
      && payload.appearance.relief_mode === 'normal'
      && typeof payload.textures.relief_high === 'string';
    let highResolutionMap = null;
    let highResolutionLoading = null;
    const activateHighResolution = async (capabilityRenderer) => {
      if (!highResolutionAllowed) return false;
      if (capabilityRenderer.capabilities.maxTextureSize < 8192) {
        container.dataset.moonHighResolution = 'unsupported';
        return false;
      }
      if (!highResolutionMap && !highResolutionLoading) {
        container.dataset.moonHighResolution = 'loading';
        highResolutionLoading = loader.loadAsync(payload.textures.relief_high).then((texture) => {
          configureTexture(texture, capabilityRenderer, THREE.NoColorSpace);
          texture.generateMipmaps = false;
          texture.minFilter = THREE.LinearFilter;
          texture.needsUpdate = true;
          highResolutionMap = texture;
          return texture;
        }).catch((error) => {
          container.dataset.moonHighResolution = 'fallback';
          console.error('Luna Three.js: no se pudo cargar el normal map 8K para exportar.', error);
          return null;
        });
      }
      const texture = highResolutionMap || await highResolutionLoading;
      if (!texture) return false;
      material.normalMap = texture;
      material.bumpMap = null;
      material.normalScale.set(Number(highResolution.normal_scale_x) || 0, Number(highResolution.normal_scale_y) || 0);
      material.needsUpdate = true;
      container.dataset.moonHighResolution = 'ready';
      return true;
    };

    const moon = new THREE.Mesh(new THREE.SphereGeometry(1, 256, 128), material);
    moon.rotation.y = -Math.PI / 2;
    const longitudeGroup = new THREE.Group();
    const latitudeGroup = new THREE.Group();
    const diskGroup = new THREE.Group();
    longitudeGroup.add(moon);
    latitudeGroup.add(longitudeGroup);
    diskGroup.add(latitudeGroup);
    scene.add(diskGroup);

    const sunlight = new THREE.DirectionalLight(0xfff7e8, Number(payload.appearance.sun_intensity));
    sunlight.target.position.set(0, 0, 0);
    const ambientLight = new THREE.AmbientLight(0x73809b, Number(payload.appearance.ambient_intensity));
    scene.add(sunlight, sunlight.target, ambientLight);

    const applyGeometry = (nextPayload) => {
      payload = nextPayload;
      const geometry = payload.geometry;
      const subobserver = geometry.surface_geometry.subobserver;
      const subsolar = geometry.surface_geometry.subsolar;
      const longitudeRotation = -THREE.MathUtils.degToRad(subobserver.longitude_degrees);
      const latitudeRotation = THREE.MathUtils.degToRad(subobserver.latitude_degrees);
      const diskRotation = -THREE.MathUtils.degToRad(geometry.orientation.lunar_north_screen_angle_degrees);
      longitudeGroup.rotation.y = longitudeRotation;
      latitudeGroup.rotation.x = latitudeRotation;
      diskGroup.rotation.z = diskRotation;
      sunlight.position.set(
        subsolar.body_fixed_unit_vector.x,
        subsolar.body_fixed_unit_vector.y,
        subsolar.body_fixed_unit_vector.z,
      ).applyAxisAngle(axisY, longitudeRotation)
        .applyAxisAngle(axisX, latitudeRotation)
        .applyAxisAngle(axisZ, diskRotation)
        .multiplyScalar(5);
    };
    applyGeometry(payload);

    const applyAppearance = async (nextPayload) => {
      const previousReliefUrl = payload.textures?.relief || null;
      const previousReliefMode = payload.appearance?.relief_mode || 'none';
      payload = nextPayload;
      const nextReliefUrl = payload.textures?.relief || null;
      if (nextReliefUrl !== previousReliefUrl) {
        reliefMap = nextReliefUrl ? configureTexture(await loader.loadAsync(nextReliefUrl), renderer, THREE.NoColorSpace) : null;
      }
      material.bumpMap = payload.appearance.relief_mode === 'bump' ? reliefMap : null;
      material.normalMap = payload.appearance.relief_mode === 'normal' ? reliefMap : null;
      material.bumpScale = Number(payload.appearance.bump_scale);
      material.normalScale.set(Number(payload.appearance.normal_scale_x), Number(payload.appearance.normal_scale_y));
      if (nextReliefUrl !== previousReliefUrl || payload.appearance.relief_mode !== previousReliefMode) material.needsUpdate = true;
      appearanceUniforms.textureContrast.value = Number(payload.appearance.texture_contrast);
      appearanceUniforms.textureBrightness.value = Number(payload.appearance.texture_brightness);
      appearanceUniforms.textureGamma.value = Number(payload.appearance.texture_gamma);
      appearanceUniforms.textureSaturation.value = Number(payload.appearance.texture_saturation);
      appearanceUniforms.earthshineIntensity.value = Math.max(0, Number(payload.appearance.earthshine_intensity) || 0);
      appearanceUniforms.shadowSkyMix.value = Math.max(0, Math.min(1, Number(payload.appearance.shadow_sky_mix) || 0));
      appearanceUniforms.darkSideOpacity.value = Math.max(0, Math.min(1, payload.appearance.dark_side_opacity === undefined ? 1 : Number(payload.appearance.dark_side_opacity)));
      appearanceUniforms.darkLimbSoftness.value = Math.max(0, Math.min(0.3, Number(payload.appearance.dark_limb_softness) || 0));
      appearanceUniforms.sceneSkyColor.value.set(/^#[0-9a-f]{6}$/i.test(String(payload.appearance.scene_sky_color)) ? payload.appearance.scene_sky_color : '#000000');
      appearanceUniforms.edgeSoftness.value = Math.max(0, Math.min(0.15, Number(payload.appearance.edge_softness) || 0));
      appearanceUniforms.terminatorSoftness.value = Math.max(0.01, Math.min(0.35, Number(payload.appearance.terminator_softness) || 0.13));
      appearanceUniforms.litTint.value.set(/^#[0-9a-f]{6}$/i.test(String(payload.appearance.lit_tint)) ? payload.appearance.lit_tint : '#ffffff');
      appearanceUniforms.terminatorAttenuationEnabled.value = payload.appearance.relief_mode === 'normal' && payload.appearance.terminator_attenuation_enabled === 'on' ? 1 : 0;
      appearanceUniforms.terminatorAttenuationWidth.value = Math.sin(THREE.MathUtils.degToRad(Number(payload.appearance.terminator_attenuation_width_degrees) || 8));
      appearanceUniforms.terminatorAttenuationMinimum.value = Math.max(0, Math.min(1, (Number(payload.appearance.terminator_attenuation_minimum_percent) || 0) / 100));
      appearanceUniforms.terminatorAttenuationCurve.value = Math.max(0.25, Number(payload.appearance.terminator_attenuation_curve) || 1.5);
      sunlight.intensity = Number(payload.appearance.sun_intensity);
      ambientLight.intensity = Number(payload.appearance.ambient_intensity);
      renderer.toneMappingExposure = Number(payload.appearance.exposure);
      const sizePercent = Math.min(120, Math.max(85, Number(payload.appearance.size_percent) || 100));
      renderScale = (sizePercent / 100) * previewScale;
      container.style.setProperty('--moon-render-scale', String(renderScale));
      applyGeometry(payload);
    };

    let firstRenderMilliseconds = null;
    const render = () => {
      const size = Math.max(1, Math.round(container.getBoundingClientRect().width));
      if (previewCanvas) {
        previewCanvas.width = size;
        previewCanvas.height = size;
        const previewContext = previewCanvas.getContext('2d');
        if (previewContext) {
          paintWallpaperBackground(previewContext, size, size, payload.wallpaper);
          paintMoonGlow(previewContext, size / 2, size / 2, size * 0.42 * renderScale, payload);
        }
      }
      renderer.setSize(size, size, false);
      camera.aspect = 1;
      camera.updateProjectionMatrix();
      const renderStartedAt = performance.now();
      renderer.render(scene, camera);
      if (firstRenderMilliseconds === null) firstRenderMilliseconds = performance.now() - renderStartedAt;
    };
    container.append(renderer.domElement);
    render();
    const wallpaperBlobCache = new Map();
    container.addEventListener('moon-three-update', async (event) => {
      if (!event.detail?.geometry) return;
      try {
        await applyAppearance(event.detail);
        wallpaperBlobCache.clear();
        render();
      } catch (error) {
        console.error('Luna Three.js: no se pudo actualizar la vista previa.', error);
      }
    });
    container.addEventListener('moon-wallpaper-update', (event) => {
      if (!event.detail || typeof event.detail !== 'object') return;
      payload.wallpaper = { ...(payload.wallpaper || {}), ...event.detail };
      wallpaperBlobCache.clear();
      render();
    });
    container.classList.add('is-rendered');
    container.dataset.moonThreeState = 'ready';
    const preparationMilliseconds = performance.now() - preparationStartedAt - (firstRenderMilliseconds || 0);
    if (diagnosticStatus) {
      const totalMilliseconds = performance.now() - componentStartedAt;
      diagnosticStatus.textContent = `total ${totalMilliseconds.toFixed(1)} ms · recursos ${resourcesMilliseconds.toFixed(1)} ms · preparación ${Math.max(0, preparationMilliseconds).toFixed(1)} ms · primer render ${Number(firstRenderMilliseconds || 0).toFixed(1)} ms`;
    }

    const wallpaperActions = [...document.querySelectorAll(`[data-moon-target="${container.id}"][data-moon-wallpaper-download], [data-moon-target="${container.id}"][data-moon-wallpaper-share]`)];
    const wallpaperOptions = (action) => {
      const copyRoot = action.closest('.favorite-moon-copy');
      const textToggle = copyRoot?.querySelector('[data-moon-wallpaper-text-toggle]');
      return {
        width: Number(action.dataset.wallpaperWidth) || 1080,
        height: Number(action.dataset.wallpaperHeight) || 1920,
        filename: action.dataset.filename || 'luna-1080x1920.png',
        includeText: !(textToggle instanceof HTMLInputElement) || textToggle.checked,
        copyElement: copyRoot?.querySelector('[data-moon-wallpaper-copy]'),
        status: copyRoot?.querySelector('[data-moon-wallpaper-status]'),
      };
    };
    const prepareWallpaperBlob = (action) => {
      const options = wallpaperOptions(action);
      const cacheKey = `${options.width}x${options.height}:${options.includeText ? 'text' : 'plain'}`;
      if (wallpaperBlobCache.has(cacheKey)) return wallpaperBlobCache.get(cacheKey);
      const pending = (async () => {
      let exportRenderer = null;
      try {
        const { width, height } = options;
        const renderSize = Math.min(width, height);
        exportRenderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
        exportRenderer.setPixelRatio(1);
        exportRenderer.setSize(renderSize, renderSize, false);
        exportRenderer.outputColorSpace = THREE.SRGBColorSpace;
        exportRenderer.toneMapping = THREE.ACESFilmicToneMapping;
        exportRenderer.toneMappingExposure = Number(payload.appearance.exposure);
        await activateHighResolution(exportRenderer);
        camera.aspect = 1;
        camera.updateProjectionMatrix();
        exportRenderer.render(scene, camera);

        const wallpaper = document.createElement('canvas');
        wallpaper.width = width;
        wallpaper.height = height;
        const context = wallpaper.getContext('2d');
        if (!context) throw new Error('Canvas 2D no disponible.');
        paintWallpaperBackground(context, width, height, payload.wallpaper);
        const scale = Math.min(1.2, Math.max(0.85, Number(payload.appearance.size_percent) / 100 || 1));
        const drawSize = renderSize * 0.84 * scale;
        const { copyElement, includeText } = options;
        const moonCenterX = includeText && width > height ? width * 0.34 : width / 2;
        const moonCenterY = includeText && height > width ? height * 0.39 : height / 2;
        paintMoonGlow(context, moonCenterX, moonCenterY, drawSize * 0.42, payload);
        context.drawImage(exportRenderer.domElement, moonCenterX - drawSize / 2, moonCenterY - drawSize / 2, drawSize, drawSize);
        if (includeText && copyElement) paintWallpaperText(context, width, height, JSON.parse(copyElement.textContent || '{}'));
        return await canvasBlob(wallpaper);
      } finally {
        exportRenderer?.dispose();
      }
      })();
      wallpaperBlobCache.set(cacheKey, pending);
      pending.catch(() => wallpaperBlobCache.delete(cacheKey));
      return pending;
    };

    const downloadButtons = wallpaperActions.filter((action) => action.hasAttribute('data-moon-wallpaper-download'));
    downloadButtons.forEach((downloadButton) => downloadButton.addEventListener('click', async () => {
      const options = wallpaperOptions(downloadButton);
      downloadButton.disabled = true;
      if (options.status) options.status.textContent = 'Preparando el fondo…';
      try {
        const blob = await prepareWallpaperBlob(downloadButton);
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = options.filename;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        if (options.status) options.status.textContent = `Fondo PNG generado en ${options.width} × ${options.height}.`;
      } catch (error) {
        console.error('Luna Three.js: no se pudo exportar el fondo.', error);
        if (options.status) options.status.textContent = 'No se pudo generar el fondo en este navegador.';
      } finally {
        downloadButton.disabled = false;
      }
    }));

    const shareButtons = wallpaperActions.filter((action) => action.hasAttribute('data-moon-wallpaper-share'));
    const shareFilesSupported = (() => {
      if (typeof navigator.share !== 'function' || typeof File !== 'function') return false;
      if (typeof navigator.canShare !== 'function') return true;
      try { return navigator.canShare({ files: [new File([''], 'prueba.png', { type: 'image/png' })] }); } catch (error) { return false; }
    })();
    shareButtons.forEach((shareButton) => {
      if (!shareFilesSupported) return;
      shareButton.hidden = false;
      shareButton.addEventListener('click', async () => {
        const options = wallpaperOptions(shareButton);
        shareButton.disabled = true;
        if (options.status) options.status.textContent = 'Preparando para compartir…';
        try {
          const blob = await prepareWallpaperBlob(shareButton);
          const file = new File([blob], options.filename, { type: 'image/png' });
          if (typeof navigator.canShare === 'function' && !navigator.canShare({ files: [file] })) {
            shareButton.hidden = true; if (options.status) options.status.textContent = ''; return;
          }
          await navigator.share({ files: [file], title: 'La Luna de tu fecha favorita' });
          if (options.status) options.status.textContent = 'Imagen compartida.';
        } catch (error) {
          if (error?.name === 'AbortError') { if (options.status) options.status.textContent = ''; }
          else if (options.status) options.status.textContent = 'No se pudo compartir. Podés descargar el PNG.';
        } finally { if (!shareButton.hidden) shareButton.disabled = false; }
      });
    });

    const resizeObserver = typeof ResizeObserver === 'function' ? new ResizeObserver(render) : null;
    if (resizeObserver) resizeObserver.observe(container);
    else window.addEventListener('resize', render, { passive: true });
    renderer.domElement.addEventListener('webglcontextlost', () => {
      showFallback();
      resizeObserver?.disconnect();
    }, { once: true });
  } catch (error) {
    renderer.dispose();
    showFallback();
    if (diagnosticStatus) diagnosticStatus.textContent = `fallback tras ${(performance.now() - componentStartedAt).toFixed(1)} ms`;
    console.error('Luna Three.js: no se pudo completar el render.', error);
  }
}

document.querySelectorAll('[data-moon-three]').forEach((container) => mountMoon(container));
