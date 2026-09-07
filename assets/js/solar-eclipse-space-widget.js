import * as THREE from './vendor/three.module.min.js';
import { interpolateAngleDegrees, setupTimeline } from './real-eclipse-controls.js?v=20260819-angular-series';

const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));
const vector = (value, divisor = 1) => new THREE.Vector3(Number(value.x) / divisor, Number(value.z) / divisor, -Number(value.y) / divisor);

function interpolateAtTime(samples, timestamp) {
  const times = samples.map((sample) => Date.parse(sample.datetime));
  if (timestamp <= times[0]) return samples[0];
  if (timestamp >= times[times.length - 1]) return samples[samples.length - 1];
  let low = 0; let high = times.length - 1;
  while (high - low > 1) { const middle = Math.floor((low + high) / 2); if (times[middle] <= timestamp) low = middle; else high = middle; }
  const fraction = (timestamp - times[low]) / Math.max(1, times[high] - times[low]);
  const mixValue = (first, second, key = '') => {
    if (typeof first === 'number' && typeof second === 'number') {
      if (key === 'greenwich_sidereal_angle_degrees') return interpolateAngleDegrees(first, second, fraction);
      return first + (second - first) * fraction;
    }
    if (first && second && typeof first === 'object' && typeof second === 'object' && !Array.isArray(first) && !Array.isArray(second)) {
      return Object.fromEntries(Object.keys(first).map((nestedKey) => [nestedKey, mixValue(first[nestedKey], second[nestedKey], nestedKey)]));
    }
    return first;
  };
  return Object.fromEntries(Object.keys(samples[low]).map((key) => [key, mixValue(samples[low][key], samples[high][key], key)]));
}

function configureTexture(texture, renderer) {
  texture.colorSpace = THREE.SRGBColorSpace;
  texture.wrapS = THREE.RepeatWrapping;
  texture.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
}

function createFrustum(material, segments = 64) {
  const positions = new Float32Array((segments + 1) * 2 * 3);
  const indices = [];
  for (let index = 0; index < segments; index += 1) {
    const next = index + 1;
    indices.push(index, next, segments + 1 + index, next, segments + 1 + next, segments + 1 + index);
  }
  const geometry = new THREE.BufferGeometry();
  geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  geometry.setIndex(indices);
  const mesh = new THREE.Mesh(geometry, material);
  mesh.frustumCulled = false;
  return mesh;
}

function updateFrustum(mesh, start, end, startRadius, endRadius) {
  const direction = end.clone().sub(start); const length = direction.length();
  if (length < 1e-6) { mesh.visible = false; return; }
  direction.divideScalar(length);
  const reference = Math.abs(direction.y) < 0.9 ? new THREE.Vector3(0, 1, 0) : new THREE.Vector3(1, 0, 0);
  const firstAxis = new THREE.Vector3().crossVectors(direction, reference).normalize();
  const secondAxis = new THREE.Vector3().crossVectors(direction, firstAxis).normalize();
  const attribute = mesh.geometry.getAttribute('position'); const segments = attribute.count / 2 - 1;
  for (let ring = 0; ring < 2; ring += 1) {
    const center = ring === 0 ? start : end; const radius = ring === 0 ? startRadius : endRadius;
    for (let index = 0; index <= segments; index += 1) {
      const angle = index / segments * Math.PI * 2;
      const point = center.clone().addScaledVector(firstAxis, Math.cos(angle) * radius).addScaledVector(secondAxis, Math.sin(angle) * radius);
      attribute.setXYZ(ring * (segments + 1) + index, point.x, point.y, point.z);
    }
  }
  attribute.needsUpdate = true; mesh.geometry.computeBoundingSphere(); mesh.visible = true;
}

function addEclipseShadow(material, uniforms) {
  material.onBeforeCompile = (shader) => {
    Object.assign(shader.uniforms, uniforms);
    shader.vertexShader = shader.vertexShader
      .replace('void main() {', 'varying vec3 vEarthPoint;\nvoid main() {')
      .replace('#include <begin_vertex>', '#include <begin_vertex>\nvEarthPoint = (modelMatrix * vec4(position, 1.0)).xyz;');
    shader.fragmentShader = shader.fragmentShader
      .replace('void main() {', `varying vec3 vEarthPoint;
uniform vec3 eclipseSunPosition;
uniform vec3 eclipseMoonPosition;
uniform float eclipseSunRadius;
uniform float eclipseMoonRadius;
uniform float eclipsePenumbraOpacity;
uniform float eclipseCoreOpacity;
float diskOverlap(float sunRadius, float moonRadius, float separation) {
  if (separation >= sunRadius + moonRadius) return 0.0;
  if (separation <= abs(sunRadius - moonRadius)) return moonRadius >= sunRadius ? 1.0 : (moonRadius * moonRadius) / (sunRadius * sunRadius);
  float sunAngle = acos(clamp((separation * separation + sunRadius * sunRadius - moonRadius * moonRadius) / (2.0 * separation * sunRadius), -1.0, 1.0));
  float moonAngle = acos(clamp((separation * separation + moonRadius * moonRadius - sunRadius * sunRadius) / (2.0 * separation * moonRadius), -1.0, 1.0));
  float triangle = 0.5 * sqrt(max(0.0, (-separation + sunRadius + moonRadius) * (separation + sunRadius - moonRadius) * (separation - sunRadius + moonRadius) * (separation + sunRadius + moonRadius)));
  return (sunRadius * sunRadius * sunAngle + moonRadius * moonRadius * moonAngle - triangle) / (3.14159265 * sunRadius * sunRadius);
}
void main() {`)
      .replace('#include <opaque_fragment>', `
vec3 toSun = eclipseSunPosition - vEarthPoint;
vec3 toMoon = eclipseMoonPosition - vEarthPoint;
float sunAngularRadius = asin(clamp(eclipseSunRadius / length(toSun), 0.0, 1.0));
float moonAngularRadius = asin(clamp(eclipseMoonRadius / length(toMoon), 0.0, 1.0));
float separation = acos(clamp(dot(normalize(toSun), normalize(toMoon)), -1.0, 1.0));
float coverage = diskOverlap(sunAngularRadius, moonAngularRadius, separation);
float coreBoundary = abs(moonAngularRadius - sunAngularRadius);
float core = 1.0 - smoothstep(coreBoundary - 0.000015, coreBoundary + 0.000015, separation);
float daylight = smoothstep(-0.015, 0.025, dot(normalize(vEarthPoint), normalize(toSun)));
float eclipseDarkness = clamp(coverage * eclipsePenumbraOpacity + core * eclipseCoreOpacity, 0.0, 0.985) * daylight;
outgoingLight *= 1.0 - eclipseDarkness;
gl_FragColor = vec4(outgoingLight, diffuseColor.a);`);
  };
  material.customProgramCacheKey = () => 'aquellas-lunas-solar-space-shadow-v2-inertial';
}

document.querySelectorAll('[data-solar-space-eclipse]').forEach(async (root) => {
  const payload = JSON.parse(root.querySelector('[data-real-eclipse-payload]').textContent || '{}');
  const eclipse = payload.eclipse; const options = eclipse.animation; const host = root.querySelector('[data-eclipse-stage]');
  let renderer;
  try { renderer = new THREE.WebGLRenderer({antialias: true, alpha: false, powerPreference: 'high-performance'}); } catch (error) { root.classList.add('has-error'); return; }
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2)); renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping; renderer.toneMappingExposure = Number(options.exposure); renderer.setClearColor(0x01040c, 1);
  renderer.domElement.className = 'solar-space-eclipse__canvas'; host.append(renderer.domElement);
  const scene = new THREE.Scene(); const camera = new THREE.PerspectiveCamera(38, 1, 0.05, 100);
  const earthRadius = Number(eclipse.samples[0].earth_radius_km); const moonRadius = Number(eclipse.samples[0].moon_radius_km) / earthRadius;
  const uniforms = {
    eclipseSunPosition: {value: new THREE.Vector3()}, eclipseMoonPosition: {value: new THREE.Vector3()},
    eclipseSunRadius: {value: Number(eclipse.samples[0].sun_radius_km) / earthRadius}, eclipseMoonRadius: {value: moonRadius},
    eclipsePenumbraOpacity: {value: options.show_penumbra ? Number(options.penumbra_opacity) : 0},
    eclipseCoreOpacity: {value: options.show_core ? Number(options.core_opacity) : 0},
  };
  try {
    const loader = new THREE.TextureLoader(); const [earthTexture, moonTexture] = await Promise.all([loader.loadAsync(payload.textures.earth), loader.loadAsync(payload.textures.moon)]);
    configureTexture(earthTexture, renderer); configureTexture(moonTexture, renderer);
    const earthMaterial = new THREE.MeshStandardMaterial({map: earthTexture, roughness: 0.92, metalness: 0}); addEclipseShadow(earthMaterial, uniforms);
    const earth = new THREE.Mesh(new THREE.SphereGeometry(1, 192, 96), earthMaterial); scene.add(earth);
    const moonMaterial = new THREE.MeshStandardMaterial({map: moonTexture, roughness: 1, metalness: 0});
    const moon = new THREE.Mesh(new THREE.SphereGeometry(moonRadius, 96, 48), moonMaterial); scene.add(moon);
    const transparentMaterial = (color, opacity) => new THREE.MeshBasicMaterial({color, transparent: true, opacity, depthWrite: false, side: THREE.DoubleSide, blending: THREE.AdditiveBlending});
    const penumbra = createFrustum(transparentMaterial(0x7189b8, Number(options.penumbra_opacity) * 0.36));
    const umbra = createFrustum(transparentMaterial(0x26324d, Number(options.core_opacity) * 0.5));
    const antumbra = createFrustum(transparentMaterial(0x8b6652, Number(options.core_opacity) * 0.42));
    scene.add(penumbra, umbra, antumbra);
    const sunlight = new THREE.DirectionalLight(0xfff5df, options.show_terminator ? 2.5 : 0);
    const ambient = new THREE.AmbientLight(0x8ca0c4, options.show_terminator ? Number(options.ambient_intensity) : Math.max(1.15, Number(options.ambient_intensity)));
    scene.add(sunlight, sunlight.target, ambient);

    let yaw = 0.72; let pitch = 0.34; let cameraDistance = 8.4 / Number(options.zoom); let dragging = false; let lastX = 0; let lastY = 0;
    let followShadow = options.follow_shadow === true; const followButton = root.querySelector('[data-space-follow]');
    const cameraTarget = new THREE.Vector3();
    const updateCamera = () => {
      const effectiveDistance = cameraDistance / Math.min(1, camera.aspect);
      const cosine = Math.cos(pitch); camera.position.set(cameraTarget.x + Math.sin(yaw) * cosine * effectiveDistance, cameraTarget.y + Math.sin(pitch) * effectiveDistance, cameraTarget.z + Math.cos(yaw) * cosine * effectiveDistance); camera.lookAt(cameraTarget);
    };
    if (options.rotation === 'free') {
      renderer.domElement.addEventListener('pointerdown', (event) => { dragging = true; lastX = event.clientX; lastY = event.clientY; renderer.domElement.setPointerCapture(event.pointerId); });
      renderer.domElement.addEventListener('pointermove', (event) => { if (!dragging) return; yaw -= (event.clientX - lastX) * 0.006; pitch = clamp(pitch + (event.clientY - lastY) * 0.006, -1.25, 1.25); lastX = event.clientX; lastY = event.clientY; });
      renderer.domElement.addEventListener('pointerup', () => { dragging = false; });
      renderer.domElement.addEventListener('wheel', (event) => { event.preventDefault(); cameraDistance = clamp(cameraDistance * Math.exp(event.deltaY * 0.001), 4.4, 15); }, {passive: false});
    }
    followButton?.addEventListener('click', () => { followShadow = !followShadow; followButton.setAttribute('aria-pressed', String(followShadow)); });
    const resize = () => { const bounds = host.getBoundingClientRect(); renderer.setSize(Math.max(1, bounds.width), Math.max(1, bounds.height), false); camera.aspect = bounds.width / Math.max(1, bounds.height); camera.updateProjectionMatrix(); };
    new ResizeObserver(resize).observe(host); resize();

    setupTimeline(root, payload, (progress, timestamp) => {
      const sample = interpolateAtTime(eclipse.samples, timestamp);
      const sunPosition = vector(sample.sun_position_inertial_km, earthRadius); const actualMoonPosition = vector(sample.moon_position_inertial_km, earthRadius);
      const shadowPoint = vector(sample.nearest_shadow_surface_point_inertial_km, earthRadius);
      const moonPosition = actualMoonPosition.clone().normalize().multiplyScalar(Number(options.distance_scale));
      earth.rotation.y = THREE.MathUtils.degToRad(Number(sample.greenwich_sidereal_angle_degrees));
      uniforms.eclipseSunPosition.value.copy(sunPosition); uniforms.eclipseMoonPosition.value.copy(actualMoonPosition);
      sunlight.position.copy(sunPosition).normalize().multiplyScalar(8); moon.position.copy(moonPosition); moon.visible = options.show_moon === true;
      root.dataset.moonRelativePosition = JSON.stringify({timestamp, x: moonPosition.x, y: moonPosition.y, z: moonPosition.z, distance: moonPosition.length()});
      const penumbraRadius = Number(sample.penumbra_radius_at_earth_plane_km) / earthRadius; const coreRadius = Number(sample.core_radius_at_earth_plane_km) / earthRadius;
      penumbra.visible = options.show_penumbra === true; if (penumbra.visible) updateFrustum(penumbra, moonPosition, shadowPoint, moonRadius, penumbraRadius);
      const coreKind = sample.core_kind_at_earth_plane; umbra.visible = options.show_core === true && coreKind === 'umbra'; antumbra.visible = options.show_core === true && coreKind === 'antumbra';
      if (umbra.visible) updateFrustum(umbra, moonPosition, shadowPoint, moonRadius, coreRadius);
      if (antumbra.visible) {
        const apexFraction = moonRadius / Math.max(1e-6, moonRadius + coreRadius); const apex = moonPosition.clone().lerp(shadowPoint, apexFraction);
        updateFrustum(umbra, moonPosition, apex, moonRadius, 0); umbra.visible = true; updateFrustum(antumbra, apex, shadowPoint, 0, coreRadius);
      }
      if (followShadow && !dragging) cameraTarget.lerp(shadowPoint.clone().multiplyScalar(0.28), 0.12); else cameraTarget.lerp(new THREE.Vector3(), 0.08);
      updateCamera(); renderer.render(scene, camera);
    });
    root.classList.add('is-ready'); root.dataset.spaceEclipseState = 'ready';
  } catch (error) {
    console.error('Widget de eclipse solar desde el espacio:', error); root.classList.add('has-error');
    const loading = root.querySelector('.lunar-widget__loading'); if (loading) loading.textContent = 'No se pudo cargar la visualización.';
  }
});
