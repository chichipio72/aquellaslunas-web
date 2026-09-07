import * as THREE from './vendor/three.module.min.js';

const axisX = new THREE.Vector3(1, 0, 0);
const axisY = new THREE.Vector3(0, 1, 0);
const axisZ = new THREE.Vector3(0, 0, 1);
const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

function wallpaperColor(hex, brightness, gradientOffset = 0) {
  const normalized = /^#[0-9a-f]{6}$/i.test(hex) ? hex.slice(1) : '000000';
  const adjustment = Math.round((brightness + gradientOffset) * 255);
  const channels = [0, 2, 4].map((offset) => clamp(parseInt(normalized.slice(offset, offset + 2), 16) + adjustment, 0, 255));
  return `rgb(${channels.join(', ')})`;
}

function paintWallpaperBackground(context, width, height, wallpaper = {}) {
  const color = String(wallpaper.background_color || '#000000');
  const brightness = clamp(Number(wallpaper.background_brightness) || 0, 0, 0.3);
  const gradientStrength = clamp(Number(wallpaper.background_gradient) || 0, 0, 0.3);
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
  return `rgba(${channels.join(', ')}, ${clamp(alpha, 0, 1).toFixed(4)})`;
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

function paintLibrationGlow(context, width, height, moonRadius, wallpaper, illumination, brightLimbRadians) {
  const mode = String(wallpaper.glow_mode || 'off');
  if (mode === 'off') return;
  const intensity = clamp(Number(wallpaper.glow_intensity) || 0, 0, 1);
  const radiusFactor = clamp(Number(wallpaper.glow_radius) || 1.65, 1.05, 3);
  const softness = clamp(Number(wallpaper.glow_softness) || 0.75, 0.1, 1);
  const color = String(wallpaper.glow_color || '#dbe5ff');
  const directionalWeight = clamp(Number(wallpaper.glow_directional_weight) || 0, 0, 2);
  const symmetricWeight = clamp(Number(wallpaper.glow_symmetric_weight) || 0, 0, 2);
  const fullMoonBoost = clamp(Number(wallpaper.glow_full_moon_boost) || 0, 0, 2);
  const centerX = width / 2;
  const centerY = height / 2;
  const directionX = Math.sin(brightLimbRadians);
  const directionY = -Math.cos(brightLimbRadians);
  const fullOpening = smoothstep(clamp((illumination - 0.78) / 0.22, 0, 1));
  context.save();
  context.globalCompositeOperation = 'screen';
  if (mode === 'sky_diffusion') {
    const phaseOpening = smoothstep(clamp((illumination - 0.08) / 0.8, 0, 1));
    const extent = moonRadius * radiusFactor * (1.12 + phaseOpening * 0.38);
    const directionalAlpha = intensity * 0.48 * directionalWeight * Math.sqrt(illumination) * Math.pow(1 - illumination, 0.42);
    const symmetricAlpha = intensity * 0.32 * (symmetricWeight * Math.pow(illumination, 1.15) + fullMoonBoost * fullOpening);
    const offset = moonRadius * (0.72 - phaseOpening * 0.28);
    const angle = Math.atan2(directionY, directionX);
    paintDiffuseGradient(context, centerX, centerY, extent, color, symmetricAlpha, softness, 0, 1.16, 0.92 + fullOpening * 0.16);
    paintDiffuseGradient(context, centerX + directionX * offset, centerY + directionY * offset, extent * 0.86, color, directionalAlpha, softness, angle, 1.34 - phaseOpening * 0.14, 0.62 + phaseOpening * 0.28);
    paintDiffuseGradient(context, centerX + directionX * offset * 0.45, centerY + directionY * offset * 0.45, extent * 1.08, color, directionalAlpha * 0.34, Math.min(1, softness + 0.15), angle, 1.08, 0.88);
  } else {
    const directionalAlpha = intensity * directionalWeight * Math.sqrt(illumination) * Math.pow(1 - illumination, 0.55);
    const symmetricAlpha = intensity * (symmetricWeight * Math.pow(illumination, 1.2) + fullMoonBoost * fullOpening);
    const outerRadius = moonRadius * radiusFactor * (0.82 + illumination * 0.22);
    const offset = moonRadius * (0.56 - illumination * 0.3);
    paintGlowGradient(context, centerX, centerY, moonRadius, outerRadius, color, symmetricAlpha, softness);
    paintGlowGradient(context, centerX + directionX * offset, centerY + directionY * offset, moonRadius * 0.72, outerRadius, color, directionalAlpha, softness);
  }
  context.restore();
}

function configureTexture(texture, renderer, colorSpace) {
  texture.colorSpace = colorSpace;
  texture.wrapS = THREE.RepeatWrapping;
  texture.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
}

function addAppearanceShader(material, appearance) {
  const uniforms = {
    textureContrast: { value: Number(appearance.texture_contrast) },
    textureBrightness: { value: Number(appearance.texture_brightness) },
    textureGamma: { value: Number(appearance.texture_gamma) },
    textureSaturation: { value: Number(appearance.texture_saturation) },
  };
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
  material.customProgramCacheKey = () => 'aquellas-lunas-libration-widget-v1';
}

function interpolate(first, second, fraction) {
  return first + (second - first) * fraction;
}

function interpolateAngle(first, second, fraction) {
  let difference = (second - first) % 360;
  if (difference > 180) difference -= 360;
  if (difference < -180) difference += 360;
  return first + difference * fraction;
}

function sampleAt(samples, progress) {
  const scaled = clamp(progress, 0, 1) * (samples.length - 1);
  const index = Math.min(samples.length - 2, Math.floor(scaled));
  const fraction = scaled - index;
  const first = samples[index];
  const second = samples[index + 1];
  const sun = new THREE.Vector3(
    interpolate(Number(first.sun.x), Number(second.sun.x), fraction),
    interpolate(Number(first.sun.y), Number(second.sun.y), fraction),
    interpolate(Number(first.sun.z), Number(second.sun.z), fraction),
  ).normalize();
  return {
    longitude: interpolateAngle(Number(first.longitude), Number(second.longitude), fraction),
    latitude: interpolate(Number(first.latitude), Number(second.latitude), fraction),
    diskAngle: interpolateAngle(Number(first.disk_angle), Number(second.disk_angle), fraction),
    illumination: interpolate(Number(first.illumination), Number(second.illumination), fraction),
    timestamp: interpolate(Date.parse(first.datetime), Date.parse(second.datetime), fraction),
    sun,
  };
}

function smoothstep(value) {
  const bounded = clamp(value, 0, 1);
  return bounded * bounded * (3 - 2 * bounded);
}

function loopSampleAt(samples, progress, blendFraction) {
  const forwardSample = sampleAt(samples, progress);
  const blendStart = 1 - blendFraction;
  if (progress <= blendStart) return forwardSample;

  const firstSample = sampleAt(samples, 0);
  const fraction = smoothstep((progress - blendStart) / blendFraction);
  return {
    longitude: interpolateAngle(forwardSample.longitude, firstSample.longitude, fraction),
    latitude: interpolate(forwardSample.latitude, firstSample.latitude, fraction),
    diskAngle: interpolateAngle(forwardSample.diskAngle, firstSample.diskAngle, fraction),
    illumination: interpolate(forwardSample.illumination, firstSample.illumination, fraction),
    // El reloj del ciclo sigue avanzando: el blend afecta sólo al estado visual.
    timestamp: forwardSample.timestamp,
    sun: forwardSample.sun.clone().lerp(firstSample.sun, fraction).normalize(),
  };
}

async function mountLibrationWidget(container) {
  const data = container.querySelector('[data-libration-payload]');
  if (!data) return;
  let payload;
  try {
    payload = JSON.parse(data.textContent || '');
  } catch (error) {
    console.error('Widget de libración: payload inválido.', error);
    container.dataset.librationState = 'fallback';
    return;
  }
  const samples = Array.isArray(payload.samples) ? payload.samples : [];
  if (samples.length < 2) return;

  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
  } catch (error) {
    container.dataset.librationState = 'fallback';
    return;
  }
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
  const baseCameraDistance = 4.15;
  const cameraZoom = clamp(Number(payload.animation.zoom || 1), 0.65, 1.35);
  const configuredSize = clamp(Number(payload.appearance.size_percent || 100) / 100, 0.85, 1.2);
  camera.position.set(0, 0, baseCameraDistance);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = Number(payload.appearance.exposure);
  renderer.domElement.className = 'libration-widget__canvas';
  renderer.domElement.setAttribute('aria-hidden', 'true');

  try {
    const loader = new THREE.TextureLoader();
    const requests = [loader.loadAsync(payload.textures.albedo)];
    if (payload.textures.relief) requests.push(loader.loadAsync(payload.textures.relief));
    const [albedo, relief = null] = await Promise.all(requests);
    configureTexture(albedo, renderer, THREE.SRGBColorSpace);
    if (relief) configureTexture(relief, renderer, THREE.NoColorSpace);
    const materialOptions = { map: albedo, roughness: 1, metalness: 0 };
    if (payload.appearance.relief_mode === 'normal') materialOptions.normalMap = relief;
    if (payload.appearance.relief_mode === 'bump') materialOptions.bumpMap = relief;
    const material = new THREE.MeshStandardMaterial(materialOptions);
    material.bumpScale = Number(payload.appearance.bump_scale);
    material.normalScale.set(Number(payload.appearance.normal_scale_x), Number(payload.appearance.normal_scale_y));
    addAppearanceShader(material, payload.appearance);

    const moon = new THREE.Mesh(new THREE.SphereGeometry(1, 192, 96), material);
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
    scene.add(sunlight, sunlight.target, new THREE.AmbientLight(0x73809b, Number(payload.appearance.ambient_intensity)));
    const backgroundCanvas = document.createElement('canvas');
    backgroundCanvas.className = 'libration-widget__background';
    backgroundCanvas.setAttribute('aria-hidden', 'true');
    container.prepend(backgroundCanvas, renderer.domElement);

    const toggle = container.querySelector('[data-libration-toggle]');
    const progressControl = container.querySelector('[data-libration-progress]');
    const dateOutput = container.querySelector('[data-libration-date]');
    const valuesOutput = container.querySelector('[data-libration-values]');
    let playing = payload.animation.autoplay === true;
    let progress = 0;
    let previousTimestamp = performance.now();
    let exportSession = null;
    let currentBackdrop = { illumination: 0, brightLimbRadians: 0 };
    const cycleSeconds = Math.max(4, Number(payload.animation.cycle_seconds) / Number(payload.animation.speed));
    const blendFraction = clamp(Number(payload.animation.loop_blend_fraction || 0), 0, 0.1);

    const exportVideo = (aspect) => {
      if (exportSession || typeof MediaRecorder === 'undefined' || typeof HTMLCanvasElement.prototype.captureStream !== 'function') {
        window.parent.postMessage({ type: 'lunar-libration-export-error', message: 'Este navegador no permite exportar video WebM.' }, window.location.origin);
        return;
      }
      const vertical = aspect === '9:16';
      const width = vertical ? 720 : 1280;
      const height = vertical ? 1280 : 720;
      const recordingCanvas = document.createElement('canvas');
      recordingCanvas.width = width;
      recordingCanvas.height = height;
      const context = recordingCanvas.getContext('2d', { alpha: false });
      if (!context) return;
      const stream = recordingCanvas.captureStream(30);
      const mimeTypes = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'];
      const mimeType = mimeTypes.find((type) => MediaRecorder.isTypeSupported(type)) || '';
      const recorder = new MediaRecorder(stream, mimeType ? { mimeType, videoBitsPerSecond: 6000000 } : { videoBitsPerSecond: 6000000 });
      const chunks = [];
      const previousState = { playing, progress };
      recorder.addEventListener('dataavailable', (event) => { if (event.data.size > 0) chunks.push(event.data); });
      recorder.addEventListener('stop', () => {
        stream.getTracks().forEach((track) => track.stop());
        const blob = new Blob(chunks, { type: recorder.mimeType || 'video/webm' });
        exportSession = null;
        container.classList.remove('is-exporting');
        playing = previousState.playing;
        progress = previousState.progress;
        container.dataset.animationPhase = String(progress);
        window.parent.postMessage({
          type: 'lunar-libration-export-complete',
          aspect: vertical ? '9:16' : '16:9',
          blob,
        }, window.location.origin);
      });
      progress = 0;
      container.dataset.animationPhase = '0';
      playing = true;
      previousTimestamp = performance.now();
      exportSession = { recorder, context, canvas: recordingCanvas, startedAt: previousTimestamp, durationMilliseconds: cycleSeconds * 1000 };
      container.classList.add('is-exporting');
      recorder.start(1000);
      window.parent.postMessage({ type: 'lunar-libration-export-started', aspect: vertical ? '9:16' : '16:9' }, window.location.origin);
    };

    const captureExportFrame = (timestamp) => {
      if (!exportSession) return;
      const { context, canvas, startedAt, durationMilliseconds, recorder } = exportSession;
      paintWallpaperBackground(context, canvas.width, canvas.height, payload.wallpaper);
      paintLibrationGlow(
        context,
        canvas.width,
        canvas.height,
        Math.min(canvas.width, canvas.height) * 0.42 * configuredSize * cameraZoom,
        payload.wallpaper,
        currentBackdrop.illumination,
        currentBackdrop.brightLimbRadians,
      );
      context.drawImage(renderer.domElement, 0, 0, canvas.width, canvas.height);
      if (timestamp - startedAt >= durationMilliseconds && recorder.state === 'recording') recorder.stop();
    };

    const resize = () => {
      const bounds = container.getBoundingClientRect();
      const width = Math.max(1, Math.round(bounds.width));
      const height = Math.max(1, Math.round(bounds.height));
      renderer.setSize(width, height, false);
      backgroundCanvas.width = width;
      backgroundCanvas.height = height;
      const backgroundContext = backgroundCanvas.getContext('2d');
      if (backgroundContext) paintWallpaperBackground(backgroundContext, width, height, payload.wallpaper);
      camera.aspect = bounds.width / Math.max(1, bounds.height);
      // En marcos verticales compensa el campo horizontal más estrecho para que
      // el disco completo entre; zoom se aplica después sobre ese encuadre base.
      camera.position.z = baseCameraDistance / Math.min(1, camera.aspect) / cameraZoom / configuredSize;
      camera.updateProjectionMatrix();
    };
    const renderSample = () => {
      const sample = loopSampleAt(samples, progress, blendFraction);
      const longitudeRotation = -THREE.MathUtils.degToRad(sample.longitude);
      const latitudeRotation = THREE.MathUtils.degToRad(sample.latitude);
      const diskRotation = -THREE.MathUtils.degToRad(sample.diskAngle);
      longitudeGroup.rotation.y = longitudeRotation;
      latitudeGroup.rotation.x = latitudeRotation;
      diskGroup.rotation.z = diskRotation;
      if (payload.animation.illumination === 'full') {
        sunlight.position.set(0, 0, 5);
        currentBackdrop = { illumination: 1, brightLimbRadians: 0 };
      } else {
        sunlight.position.copy(sample.sun)
          .applyAxisAngle(axisY, longitudeRotation)
          .applyAxisAngle(axisX, latitudeRotation)
          .applyAxisAngle(axisZ, diskRotation)
          .multiplyScalar(5);
        currentBackdrop = {
          illumination: clamp(sample.illumination, 0, 1),
          brightLimbRadians: Math.atan2(sunlight.position.x, -sunlight.position.y),
        };
      }
      const backgroundContext = backgroundCanvas.getContext('2d');
      if (backgroundContext) {
        paintWallpaperBackground(backgroundContext, backgroundCanvas.width, backgroundCanvas.height, payload.wallpaper);
        paintLibrationGlow(
          backgroundContext,
          backgroundCanvas.width,
          backgroundCanvas.height,
          Math.min(backgroundCanvas.width, backgroundCanvas.height) * 0.42 * configuredSize * cameraZoom,
          payload.wallpaper,
          currentBackdrop.illumination,
          currentBackdrop.brightLimbRadians,
        );
      }
      renderer.render(scene, camera);
      if (progressControl && document.activeElement !== progressControl) progressControl.value = String(Math.round(progress * 1000));
      if (dateOutput) dateOutput.textContent = new Intl.DateTimeFormat('es-AR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(sample.timestamp));
      if (valuesOutput) valuesOutput.textContent = `Libración ${sample.longitude >= 0 ? '+' : ''}${sample.longitude.toFixed(1)}° lon · ${sample.latitude >= 0 ? '+' : ''}${sample.latitude.toFixed(1)}° lat · ${(sample.illumination * 100).toFixed(0)}% iluminada`;
    };
    const animate = (timestamp) => {
      const elapsed = Math.min(100, timestamp - previousTimestamp);
      previousTimestamp = timestamp;
      if (playing) {
        const phaseIncrement = elapsed / (cycleSeconds * 1000);
        progress = (Number(container.dataset.animationPhase || progress) + phaseIncrement) % 1;
        container.dataset.animationPhase = String(progress);
      }
      renderSample();
      captureExportFrame(timestamp);
      requestAnimationFrame(animate);
    };
    toggle?.addEventListener('click', () => {
      playing = !playing;
      toggle.textContent = playing ? 'Pausar' : 'Reproducir';
    });
    progressControl?.addEventListener('input', () => {
      playing = false;
      if (toggle) toggle.textContent = 'Reproducir';
      progress = Number(progressControl.value) / 1000;
      container.dataset.animationPhase = String(progress);
      renderSample();
    });
    window.addEventListener('message', (event) => {
      if (event.origin !== window.location.origin || event.source !== window.parent) return;
      if (event.data?.type === 'lunar-libration-export') exportVideo(event.data.aspect === '9:16' ? '9:16' : '16:9');
    });
    resize();
    new ResizeObserver(resize).observe(container);
    container.dataset.librationState = 'ready';
    container.classList.add('is-ready');
    window.parent.postMessage({ type: 'lunar-libration-ready' }, window.location.origin);
    requestAnimationFrame(animate);
  } catch (error) {
    renderer.dispose();
    container.dataset.librationState = 'fallback';
    console.error('Widget de libración: no se pudo completar el render.', error);
  }
}

document.querySelectorAll('[data-libration-widget]').forEach((container) => mountLibrationWidget(container));
