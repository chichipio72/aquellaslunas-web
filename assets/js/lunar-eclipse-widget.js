import * as THREE from './vendor/three.module.min.js';

const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

function configureTexture(texture, renderer, colorSpace) {
  texture.colorSpace = colorSpace;
  texture.wrapS = THREE.RepeatWrapping;
  texture.wrapT = THREE.ClampToEdgeWrapping;
  texture.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
}

function eclipseState(timestamp, contacts) {
  const time = (code) => contacts[code] ? Date.parse(contacts[code]) : null;
  if (timestamp < time('P1') || timestamp > time('P4')) return 'Fuera de la sombra';
  if (timestamp < time('U1') || timestamp > time('U4')) return 'En la penumbra';
  if (time('U2') !== null && timestamp >= time('U2') && timestamp <= time('U3')) return 'Totalidad';
  return 'En la umbra';
}

function circleOverlapFraction(radius, separation) {
  if (separation >= radius + 1) return 0;
  if (separation <= Math.abs(radius - 1)) return radius >= 1 ? 1 : radius * radius;
  const first = Math.acos(clamp((separation * separation + 1 - radius * radius) / (2 * separation), -1, 1));
  const second = radius * radius * Math.acos(clamp((separation * separation + radius * radius - 1) / (2 * separation * radius), -1, 1));
  const triangle = 0.5 * Math.sqrt(Math.max(0, (-separation + 1 + radius) * (separation + 1 - radius) * (separation - 1 + radius) * (separation + 1 + radius)));
  return clamp((first + second - triangle) / Math.PI, 0, 1);
}

function mountEclipseWidget(container) {
  const payloadElement = container.querySelector('[data-eclipse-payload]');
  const host = container.querySelector('[data-eclipse-moon]');
  if (!payloadElement || !host) return;
  let payload;
  try { payload = JSON.parse(payloadElement.textContent || ''); } catch (error) { container.dataset.eclipseState = 'fallback'; return; }

  let renderer;
  try { renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' }); } catch (error) { container.dataset.eclipseState = 'fallback'; return; }
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = Number(payload.eclipse.animation.exposure || payload.appearance.exposure || 1.1);
  renderer.domElement.className = 'lunar-eclipse-widget__canvas';
  renderer.domElement.setAttribute('aria-hidden', 'true');
  host.appendChild(renderer.domElement);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
  camera.position.set(0, 0, 4.1);
  const shadowUniforms = {
    center: { value: new THREE.Vector2(-8, 0) },
    umbraRadius: { value: Number(payload.eclipse.shadow.umbra_radius_moon_radii) },
    penumbraRadius: { value: Number(payload.eclipse.shadow.penumbra_radius_moon_radii) },
    umbraCoverage: { value: 0 },
    penumbraDarkness: { value: Number(payload.eclipse.animation.penumbra_darkness) },
    umbraDarkness: { value: Number(payload.eclipse.animation.umbra_darkness) },
    copperIntensity: { value: Number(payload.eclipse.animation.copper_intensity) },
    copperColor: { value: new THREE.Color(String(payload.eclipse.animation.copper_color || '#c95f32')) },
  };

  const loader = new THREE.TextureLoader();
  const requests = [loader.loadAsync(payload.textures.albedo)];
  if (payload.textures.relief) requests.push(loader.loadAsync(payload.textures.relief));
  Promise.all(requests).then(([albedo, relief = null]) => {
    configureTexture(albedo, renderer, THREE.SRGBColorSpace);
    if (relief) configureTexture(relief, renderer, THREE.NoColorSpace);
    const options = { map: albedo, roughness: 1, metalness: 0 };
    if (payload.appearance.relief_mode === 'normal' && relief) options.normalMap = relief;
    if (payload.appearance.relief_mode === 'bump' && relief) options.bumpMap = relief;
    const material = new THREE.MeshStandardMaterial(options);
    material.bumpScale = Number(payload.appearance.bump_scale || 0.04);
    material.normalScale.set(Number(payload.appearance.normal_scale_x || 1), Number(payload.appearance.normal_scale_y || 1));
    material.onBeforeCompile = (shader) => {
      shader.uniforms.eclipseCenter = shadowUniforms.center;
      shader.uniforms.eclipseUmbraRadius = shadowUniforms.umbraRadius;
      shader.uniforms.eclipsePenumbraRadius = shadowUniforms.penumbraRadius;
      shader.uniforms.eclipseUmbraCoverage = shadowUniforms.umbraCoverage;
      shader.uniforms.eclipsePenumbraDarkness = shadowUniforms.penumbraDarkness;
      shader.uniforms.eclipseUmbraDarkness = shadowUniforms.umbraDarkness;
      shader.uniforms.eclipseCopperIntensity = shadowUniforms.copperIntensity;
      shader.uniforms.eclipseCopperColor = shadowUniforms.copperColor;
      shader.vertexShader = shader.vertexShader
        .replace('void main() {', 'varying vec2 vEclipseDisk;\nvoid main() {')
        .replace('#include <defaultnormal_vertex>', '#include <defaultnormal_vertex>\n vEclipseDisk = normalize(transformedNormal).xy;');
      shader.fragmentShader = shader.fragmentShader
        .replace('void main() {', 'varying vec2 vEclipseDisk;\nuniform vec2 eclipseCenter;\nuniform float eclipseUmbraRadius;\nuniform float eclipsePenumbraRadius;\nuniform float eclipseUmbraCoverage;\nuniform float eclipsePenumbraDarkness;\nuniform float eclipseUmbraDarkness;\nuniform float eclipseCopperIntensity;\nuniform vec3 eclipseCopperColor;\nvoid main() {')
        .replace('#include <opaque_fragment>', `
          float eclipseDistance = distance(vEclipseDisk, eclipseCenter);
          float penumbraAmount = 1.0 - smoothstep(eclipsePenumbraRadius - 0.24, eclipsePenumbraRadius + 0.24, eclipseDistance);
          float umbraAmount = 1.0 - smoothstep(eclipseUmbraRadius - 0.10, eclipseUmbraRadius + 0.10, eclipseDistance);
          vec3 uneclipsedLight = outgoingLight;
          outgoingLight *= mix(1.0, 1.0 - eclipsePenumbraDarkness, penumbraAmount);
          vec3 darkUmbra = uneclipsedLight * (1.0 - eclipseUmbraDarkness);
          outgoingLight = mix(outgoingLight, darkUmbra, umbraAmount * 0.985);
          float umbraDepth = clamp((eclipseUmbraRadius - eclipseDistance) / eclipseUmbraRadius, 0.0, 1.0);
          float nearTotality = smoothstep(0.84, 1.0, eclipseUmbraCoverage);
          float atmosphericDepth = smoothstep(0.08, 0.72, umbraDepth);
          float copperVisibility = umbraAmount * nearTotality * atmosphericDepth;
          float surfaceLuma = dot(uneclipsedLight, vec3(0.299, 0.587, 0.114));
          vec3 copper = uneclipsedLight * eclipseCopperColor * eclipseCopperIntensity
            + eclipseCopperColor * eclipseCopperIntensity * 0.04 * (0.35 + surfaceLuma);
          outgoingLight = mix(outgoingLight, copper, copperVisibility);
          #include <opaque_fragment>
        `);
    };
    material.customProgramCacheKey = () => 'aquellas-lunas-lunar-eclipse-v1';

    const moon = new THREE.Mesh(new THREE.SphereGeometry(1, 192, 96), material);
    moon.rotation.y = -Math.PI / 2;
    const longitudeGroup = new THREE.Group();
    const latitudeGroup = new THREE.Group();
    const diskGroup = new THREE.Group();
    longitudeGroup.add(moon); latitudeGroup.add(longitudeGroup); diskGroup.add(latitudeGroup); scene.add(diskGroup);
    const geometry = payload.geometry;
    longitudeGroup.rotation.y = -THREE.MathUtils.degToRad(Number(geometry.surface_geometry.subobserver.longitude_degrees));
    latitudeGroup.rotation.x = THREE.MathUtils.degToRad(Number(geometry.surface_geometry.subobserver.latitude_degrees));
    diskGroup.rotation.z = -THREE.MathUtils.degToRad(Number(geometry.orientation.lunar_north_screen_angle_degrees));
    const sunlight = new THREE.DirectionalLight(0xfff7e8, Number(payload.eclipse.animation.sun_intensity));
    sunlight.position.set(0, 0, 5); sunlight.target.position.set(0, 0, 0);
    scene.add(sunlight, sunlight.target, new THREE.AmbientLight(0x73809b, Number(payload.eclipse.animation.ambient_intensity)));

    const start = Date.parse(payload.eclipse.timeline_start);
    const end = Date.parse(payload.eclipse.timeline_end);
    const maximum = Date.parse(payload.eclipse.maximum);
    const p1 = Date.parse(payload.eclipse.contacts.P1);
    const p4 = Date.parse(payload.eclipse.contacts.P4);
    const penumbraRadius = Number(payload.eclipse.shadow.penumbra_radius_moon_radii);
    const impact = Number(payload.eclipse.shadow.closest_approach_moon_radii);
    const contactX = Math.sqrt(Math.max(0, (penumbraRadius + 1) ** 2 - impact ** 2));
    const beforeSpeed = contactX / Math.max(1, maximum - p1);
    const afterSpeed = contactX / Math.max(1, p4 - maximum);
    const progressControl = container.querySelector('[data-eclipse-progress]');
    const toggle = container.querySelector('[data-eclipse-toggle]');
    const stateOutput = container.querySelector('[data-eclipse-state-label]');
    const timeOutput = container.querySelector('[data-eclipse-time]');
    const geometryMoon = container.querySelector('[data-eclipse-geometry-moon]');
    let progress = 0.5;
    let playing = payload.eclipse.animation.autoplay === true;
    let previous = performance.now();
    const cycleSeconds = Number(payload.eclipse.animation.duration_seconds || 32) / Number(payload.eclipse.animation.speed || 1);

    const resize = () => {
      const bounds = host.getBoundingClientRect();
      renderer.setSize(Math.max(1, Math.round(bounds.width)), Math.max(1, Math.round(bounds.height)), false);
      camera.aspect = bounds.width / Math.max(1, bounds.height); camera.updateProjectionMatrix();
    };
    const render = () => {
      const timestamp = start + (end - start) * progress;
      const x = timestamp <= maximum ? -(maximum - timestamp) * beforeSpeed : (timestamp - maximum) * afterSpeed;
      shadowUniforms.center.value.set(x, impact);
      shadowUniforms.umbraCoverage.value = circleOverlapFraction(Number(payload.eclipse.shadow.umbra_radius_moon_radii), Math.hypot(x, impact));
      renderer.render(scene, camera);
      if (progressControl && document.activeElement !== progressControl) progressControl.value = String(Math.round(progress * 1000));
      if (stateOutput) stateOutput.textContent = eclipseState(timestamp, payload.eclipse.contacts);
      if (timeOutput) timeOutput.textContent = new Intl.DateTimeFormat('es-AR', { timeZone: 'UTC', dateStyle: 'medium', timeStyle: 'short', hour12: false }).format(new Date(timestamp)) + ' UTC';
      if (geometryMoon) {
        const maximumProgress = (maximum - start) / (end - start);
        const impactRatio = impact / Number(payload.eclipse.shadow.umbra_radius_moon_radii);
        const maximumY = 60 - clamp(impactRatio, -1, 1) * 18;
        const y = progress <= maximumProgress
          ? 116 + (maximumY - 116) * (progress / maximumProgress)
          : maximumY + (4 - maximumY) * ((progress - maximumProgress) / (1 - maximumProgress));
        geometryMoon.setAttribute('cx', String(455 + progress * 30));
        geometryMoon.setAttribute('cy', String(y));
      }
    };
    const animate = (timestamp) => {
      const elapsed = Math.min(100, timestamp - previous); previous = timestamp;
      if (playing) progress = (progress + elapsed / (cycleSeconds * 1000)) % 1;
      render(); requestAnimationFrame(animate);
    };
    progressControl?.addEventListener('input', () => { playing = false; progress = Number(progressControl.value) / 1000; if (toggle) toggle.textContent = 'Reproducir'; render(); });
    toggle?.addEventListener('click', () => { playing = !playing; toggle.textContent = playing ? 'Pausar' : 'Reproducir'; });
    new ResizeObserver(resize).observe(host); resize();
    container.classList.add('is-ready'); container.dataset.eclipseState = 'ready';
    requestAnimationFrame(animate);
  }).catch((error) => { console.error('Widget de eclipse lunar:', error); container.dataset.eclipseState = 'fallback'; });
}

document.querySelectorAll('[data-lunar-eclipse-widget]').forEach(mountEclipseWidget);
