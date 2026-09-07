import * as THREE from './vendor/three.module.min.js';

const axisX = new THREE.Vector3(1, 0, 0);
const axisY = new THREE.Vector3(0, 1, 0);
const axisZ = new THREE.Vector3(0, 0, 1);
const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));
const interpolate = (first, second, fraction) => first + (second - first) * fraction;

function interpolateAngle(first, second, fraction) {
  let difference = (second - first) % 360;
  if (difference > 180) difference -= 360;
  if (difference < -180) difference += 360;
  return first + difference * fraction;
}

function interpolateBody(first, second, fraction) {
  return {
    longitude: interpolateAngle(Number(first.longitude), Number(second.longitude), fraction),
    latitude: interpolate(Number(first.latitude), Number(second.latitude), fraction),
    diskAngle: interpolateAngle(Number(first.disk_angle), Number(second.disk_angle), fraction),
    illumination: interpolate(Number(first.illumination), Number(second.illumination), fraction),
    cycleAngle: interpolateAngle(Number(first.cycle_angle), Number(second.cycle_angle), fraction),
  };
}

function sampleAt(samples, progress) {
  const scaled = clamp(progress, 0, 1) * (samples.length - 1);
  const index = Math.min(samples.length - 2, Math.floor(scaled));
  const fraction = scaled - index;
  const first = samples[index];
  const second = samples[index + 1];
  return {
    timestamp: interpolate(Date.parse(first.datetime), Date.parse(second.datetime), fraction),
    moon: {
      ...interpolateBody(first.moon, second.moon, fraction),
      sun: new THREE.Vector3(
        interpolate(Number(first.moon.sun.x), Number(second.moon.sun.x), fraction),
        interpolate(Number(first.moon.sun.y), Number(second.moon.sun.y), fraction),
        interpolate(Number(first.moon.sun.z), Number(second.moon.sun.z), fraction),
      ).normalize(),
    },
    earth: interpolateBody(first.earth, second.earth, fraction),
  };
}

function configureTexture(texture, renderer, colorSpace) {
  texture.colorSpace = colorSpace;
  texture.wrapS = THREE.RepeatWrapping;
  texture.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
}

function addMoonAppearance(material, appearance) {
  material.onBeforeCompile = (shader) => {
    Object.assign(shader.uniforms, {
      textureContrast: { value: Number(appearance.texture_contrast) },
      textureBrightness: { value: Number(appearance.texture_brightness) },
      textureGamma: { value: Number(appearance.texture_gamma) },
      textureSaturation: { value: Number(appearance.texture_saturation) },
    });
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
  material.customProgramCacheKey = () => 'aquellas-lunas-earth-moon-v1';
}

function createBodyRenderer(host) {
  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.domElement.setAttribute('aria-hidden', 'true');
  host.append(renderer.domElement);
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
  camera.position.z = 4.15;
  return { host, renderer, scene, camera };
}

async function mountEarthMoonWidget(container) {
  const data = container.querySelector('[data-earth-moon-payload]');
  if (!data) return;
  let payload;
  try { payload = JSON.parse(data.textContent || ''); } catch (error) { return; }
  const samples = Array.isArray(payload.samples) ? payload.samples : [];
  const moonHost = container.querySelector('[data-earth-moon-canvas="moon"]');
  const earthHost = container.querySelector('[data-earth-moon-canvas="earth"]');
  if (!moonHost || !earthHost || samples.length < 2) return;

  let moonView;
  let earthView;
  try {
    moonView = createBodyRenderer(moonHost);
    earthView = createBodyRenderer(earthHost);
  } catch (error) {
    container.dataset.earthMoonState = 'fallback';
    return;
  }
  moonView.renderer.toneMappingExposure = Number(payload.comparison.moon_exposure);
  earthView.renderer.toneMappingExposure = Number(payload.comparison.earth_exposure);

  try {
    const loader = new THREE.TextureLoader();
    const requests = [loader.loadAsync(payload.textures.albedo), loader.loadAsync(payload.textures.earth_albedo)];
    if (payload.textures.relief) requests.push(loader.loadAsync(payload.textures.relief));
    const [moonAlbedo, earthAlbedo, relief = null] = await Promise.all(requests);
    configureTexture(moonAlbedo, moonView.renderer, THREE.SRGBColorSpace);
    configureTexture(earthAlbedo, earthView.renderer, THREE.SRGBColorSpace);
    if (relief) configureTexture(relief, moonView.renderer, THREE.NoColorSpace);

    const moonOptions = { map: moonAlbedo, roughness: Number(payload.comparison.moon_roughness), metalness: 0 };
    if (payload.appearance.relief_mode === 'normal') moonOptions.normalMap = relief;
    if (payload.appearance.relief_mode === 'bump') moonOptions.bumpMap = relief;
    const moonMaterial = new THREE.MeshStandardMaterial(moonOptions);
    moonMaterial.bumpScale = Number(payload.appearance.bump_scale);
    moonMaterial.normalScale.set(Number(payload.comparison.moon_normal_x), Number(payload.comparison.moon_normal_y));
    addMoonAppearance(moonMaterial, payload.appearance);
    const earthMaterial = new THREE.MeshStandardMaterial({ map: earthAlbedo, roughness: Number(payload.comparison.earth_roughness), metalness: 0 });

    const createGroups = (material, segments) => {
      const mesh = new THREE.Mesh(new THREE.SphereGeometry(1, segments, segments / 2), material);
      mesh.rotation.y = -Math.PI / 2;
      const longitude = new THREE.Group();
      const latitude = new THREE.Group();
      const disk = new THREE.Group();
      longitude.add(mesh); latitude.add(longitude); disk.add(latitude);
      return { mesh, longitude, latitude, disk };
    };
    const moon = createGroups(moonMaterial, 192);
    const earth = createGroups(earthMaterial, 128);
    moonView.scene.add(moon.disk);
    earthView.scene.add(earth.disk);
    const moonLight = new THREE.DirectionalLight(0xfff7e8, Number(payload.comparison.moon_sun));
    const earthLight = new THREE.DirectionalLight(0xfff7e8, Number(payload.comparison.earth_sun));
    moonView.scene.add(moonLight, moonLight.target, new THREE.AmbientLight(0x73809b, Number(payload.comparison.moon_ambient)));
    earthView.scene.add(earthLight, earthLight.target, new THREE.AmbientLight(0x52617d, Number(payload.comparison.earth_ambient)));

    const toggle = container.querySelector('[data-earth-moon-toggle]');
    const reset = container.querySelector('[data-earth-moon-reset]');
    const progressControl = container.querySelector('[data-earth-moon-progress]');
    const dateOutput = container.querySelector('[data-earth-moon-date]');
    const moonPhaseOutput = container.querySelector('[data-earth-moon-moon-phase]');
    const earthPhaseOutput = container.querySelector('[data-earth-moon-earth-phase]');
    const initialProgress = clamp(Number(payload.comparison.initial_progress), 0, 1);
    let progress = initialProgress;
    let playing = payload.comparison.autoplay === true;
    let previousTimestamp = performance.now();
    const cycleSeconds = Math.max(4, Number(payload.comparison.cycle_seconds) / Number(payload.comparison.speed));

    const resizeView = (view) => {
      const bounds = view.host.getBoundingClientRect();
      view.renderer.setSize(Math.max(1, Math.round(bounds.width)), Math.max(1, Math.round(bounds.height)), false);
      view.camera.aspect = bounds.width / Math.max(1, bounds.height);
      view.camera.updateProjectionMatrix();
    };
    const resize = () => { resizeView(moonView); resizeView(earthView); };
    const render = () => {
      const sample = sampleAt(samples, progress);
      const moonLongitude = -THREE.MathUtils.degToRad(sample.moon.longitude);
      const moonLatitude = THREE.MathUtils.degToRad(sample.moon.latitude);
      const moonDisk = -THREE.MathUtils.degToRad(sample.moon.diskAngle);
      moon.longitude.rotation.y = moonLongitude;
      moon.latitude.rotation.x = moonLatitude;
      moon.disk.rotation.z = moonDisk;
      const moonSunScreen = sample.moon.sun.clone()
        .applyAxisAngle(axisY, moonLongitude)
        .applyAxisAngle(axisX, moonLatitude)
        .applyAxisAngle(axisZ, moonDisk)
        .normalize();
      moonLight.position.copy(moonSunScreen).multiplyScalar(5);

      earth.longitude.rotation.y = -THREE.MathUtils.degToRad(sample.earth.longitude);
      earth.latitude.rotation.x = THREE.MathUtils.degToRad(sample.earth.latitude);
      earth.disk.rotation.z = -THREE.MathUtils.degToRad(sample.earth.diskAngle);
      earthLight.position.copy(moonSunScreen).multiplyScalar(-5);
      moonView.renderer.render(moonView.scene, moonView.camera);
      earthView.renderer.render(earthView.scene, earthView.camera);

      if (progressControl && document.activeElement !== progressControl) progressControl.value = String(Math.round(progress * 1000));
      if (dateOutput) dateOutput.textContent = new Intl.DateTimeFormat('es-AR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(sample.timestamp));
      if (moonPhaseOutput) moonPhaseOutput.textContent = `${(sample.moon.illumination * 100).toFixed(1)}% iluminada`;
      if (earthPhaseOutput) earthPhaseOutput.textContent = `${(sample.earth.illumination * 100).toFixed(1)}% iluminada`;
    };
    const animate = (timestamp) => {
      const elapsed = Math.min(100, timestamp - previousTimestamp);
      previousTimestamp = timestamp;
      if (playing) progress = (progress + elapsed / (cycleSeconds * 1000)) % 1;
      render();
      requestAnimationFrame(animate);
    };
    toggle?.addEventListener('click', () => { playing = !playing; toggle.textContent = playing ? 'Pausar' : 'Reproducir'; });
    reset?.addEventListener('click', () => { playing = false; progress = initialProgress; if (toggle) toggle.textContent = 'Reproducir'; });
    progressControl?.addEventListener('input', () => { playing = false; progress = Number(progressControl.value) / 1000; if (toggle) toggle.textContent = 'Reproducir'; });
    const observer = new ResizeObserver(resize);
    observer.observe(container);
    resize();
    container.classList.add('is-ready');
    container.dataset.earthMoonState = 'ready';
    requestAnimationFrame(animate);
  } catch (error) {
    console.error('Widget Tierra–Luna: no se pudieron cargar los recursos.', error);
    container.dataset.earthMoonState = 'fallback';
  }
}

document.querySelectorAll('[data-earth-moon-widget]').forEach(mountEarthMoonWidget);
