import * as THREE from './vendor/three.module.min.js';
import { interpolate, publishEclipseCapture, setupTimeline, unwrapAngularSamples } from './real-eclipse-controls.js?v=20260827-infographic-capture';

const finite = (value, fallback = 0) => Number.isFinite(Number(value)) ? Number(value) : fallback;
const smoothstep = (start, end, value) => { const progress = Math.max(0, Math.min(1, (value - start) / Math.max(1, end - start))); return progress * progress * (3 - 2 * progress); };

function configureTexture(texture, renderer, colorSpace) {
  texture.colorSpace = colorSpace;
  texture.wrapS = THREE.RepeatWrapping;
  texture.wrapT = THREE.ClampToEdgeWrapping;
  texture.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
}

document.querySelectorAll('[data-real-lunar-eclipse]').forEach((root) => {
  const payload = JSON.parse(root.querySelector('[data-real-eclipse-payload]').textContent);
  const host = root.querySelector('[data-eclipse-stage]');
  const eclipse = payload.eclipse;
  const samples = unwrapAngularSamples(eclipse.samples);
  const capture = new URLSearchParams(location.search).get('capture') === '1' || root.closest('[data-eclipse-capture]') !== null;
  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: capture });
  renderer.setPixelRatio(Math.min(devicePixelRatio || 1, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = Number(eclipse.animation.exposure || 1.15);
  renderer.domElement.className = 'real-eclipse-widget__canvas';
  host.appendChild(renderer.domElement);
  const scene = new THREE.Scene();
  const skyColor = new THREE.Color(eclipse.animation.sky_color || '#02040a');
  const skyBrightness = Math.max(0, Number(eclipse.animation.sky_brightness) || 0);
  skyColor.multiplyScalar(Math.max(1, skyBrightness));
  renderer.setClearColor(skyColor, Math.min(1, skyBrightness));
  const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
  const cameraDistance = 5.2;
  camera.position.z = cameraDistance;
  const uniforms = {
    center: { value: new THREE.Vector2(8, 0) }, umbra: { value: 2.5 }, penumbra: { value: 4 },
    penumbraDark: { value: Number(eclipse.animation.penumbra_darkness) }, umbraDark: { value: Number(eclipse.animation.umbra_darkness) },
    copper: { value: new THREE.Color(eclipse.animation.copper_color) }, copperPower: { value: Number(eclipse.animation.copper_intensity) },
    totalityFactor: { value: 0 }, totalityBrightness: { value: Number(eclipse.animation.totality_brightness) },
    totalityCopper: { value: Number(eclipse.animation.totality_copper_intensity) },
    totalityMaxDarkness: { value: Number(eclipse.animation.totality_max_darkness) },
    totalityGradientContrast: { value: Number(eclipse.animation.totality_gradient_contrast) },
    totalityEdgeColor: { value: new THREE.Color(eclipse.animation.totality_edge_color) },
    totalityDeepColor: { value: new THREE.Color(eclipse.animation.totality_deep_color) },
    totalitySaturation: { value: Number(eclipse.animation.totality_saturation) },
    totalityTextureContrast: { value: Number(eclipse.animation.totality_texture_contrast) },
    totalityGradientSoftness: { value: Number(eclipse.animation.totality_gradient_softness) },
    totalityIrregularity: { value: Number(eclipse.animation.totality_atmospheric_irregularity) },
  };
  const loader = new THREE.TextureLoader();
  Promise.all([loader.loadAsync(payload.textures.albedo), payload.textures.relief ? loader.loadAsync(payload.textures.relief) : null]).then(([map, normal]) => {
    configureTexture(map, renderer, THREE.SRGBColorSpace);
    if (normal) configureTexture(normal, renderer, THREE.NoColorSpace);
    const materialOptions = { map, roughness: 1, metalness: 0 };
    if (payload.appearance.relief_mode === 'normal' && normal) materialOptions.normalMap = normal;
    if (payload.appearance.relief_mode === 'bump' && normal) materialOptions.bumpMap = normal;
    const material = new THREE.MeshStandardMaterial(materialOptions);
    material.bumpScale = finite(payload.appearance.bump_scale, 0.04);
    material.normalScale.set(finite(payload.appearance.normal_scale_x, 1), finite(payload.appearance.normal_scale_y, 1));
    material.onBeforeCompile = (shader) => {
      Object.assign(shader.uniforms, {
        shadowCenter: uniforms.center, umbraRadius: uniforms.umbra, penumbraRadius: uniforms.penumbra,
        penumbraDark: uniforms.penumbraDark, umbraDark: uniforms.umbraDark, copper: uniforms.copper,
        copperPower: uniforms.copperPower, totalityFactor: uniforms.totalityFactor,
        totalityBrightness: uniforms.totalityBrightness, totalityCopper: uniforms.totalityCopper,
        totalityMaxDarkness: uniforms.totalityMaxDarkness, totalityGradientContrast: uniforms.totalityGradientContrast,
        totalityEdgeColor: uniforms.totalityEdgeColor, totalityDeepColor: uniforms.totalityDeepColor,
        totalitySaturation: uniforms.totalitySaturation, totalityTextureContrast: uniforms.totalityTextureContrast,
        totalityGradientSoftness: uniforms.totalityGradientSoftness, totalityIrregularity: uniforms.totalityIrregularity,
      });
      shader.vertexShader = shader.vertexShader.replace('void main() {', 'varying vec2 vDisk;\nvoid main() {').replace('#include <defaultnormal_vertex>', '#include <defaultnormal_vertex>\nvDisk=normalize(transformedNormal).xy;');
      const shadowCode = `
        outgoingLight *= 1.0 - p * penumbraDark;
        vec3 partialUmbra = lit * (1.0 - umbraDark) + copper * copperPower * .11;
        float atmosphere = (sin(vDisk.x * 17.3 + vDisk.y * 4.7) * sin(vDisk.y * 13.1 - vDisk.x * 3.9)
          + .45 * sin(vDisk.x * 29.7 - vDisk.y * 21.3)) / 1.45;
        float perturbedDistance = d + atmosphere * totalityIrregularity * umbraRadius * .12;
        float rawDepth = clamp((umbraRadius - perturbedDistance) / max(umbraRadius, .001), 0.0, 1.0);
        float softenedDepth = smoothstep(0.0, max(.02, 1.0 - totalityGradientSoftness * .7), rawDepth);
        float umbralDepth = pow(softenedDepth, totalityGradientContrast);
        vec3 umbralTone = mix(totalityEdgeColor, totalityDeepColor, umbralDepth);
        float toneLuma = dot(umbralTone, vec3(.299, .587, .114));
        umbralTone = mix(vec3(toneLuma), umbralTone, totalitySaturation);
        vec3 subduedTexture = clamp(vec3(.5) + (lit - vec3(.5)) * totalityTextureContrast, 0.0, 2.0);
        float copperMix = clamp(totalityCopper, 0.0, 1.0);
        vec3 coloredTexture = mix(subduedTexture, subduedTexture * umbralTone * 2.0, copperMix);
        coloredTexture *= 1.0 + max(0.0, totalityCopper - 1.0) * umbralTone;
        float localBrightness = totalityBrightness * mix(1.0, 1.0 - totalityMaxDarkness, umbralDepth);
        vec3 adaptedUmbra = coloredTexture * localBrightness;
        outgoingLight = mix(outgoingLight, mix(partialUmbra, adaptedUmbra, totalityFactor), u);
      `;
      shader.fragmentShader = shader.fragmentShader
        .replace('void main() {', 'varying vec2 vDisk; uniform vec2 shadowCenter; uniform float umbraRadius; uniform float penumbraRadius; uniform float penumbraDark; uniform float umbraDark; uniform vec3 copper; uniform float copperPower; uniform float totalityFactor; uniform float totalityBrightness; uniform float totalityCopper; uniform float totalityMaxDarkness; uniform float totalityGradientContrast; uniform vec3 totalityEdgeColor; uniform vec3 totalityDeepColor; uniform float totalitySaturation; uniform float totalityTextureContrast; uniform float totalityGradientSoftness; uniform float totalityIrregularity;\nvoid main() {')
        .replace('#include <opaque_fragment>', `float d=distance(vDisk,shadowCenter);float p=1.0-smoothstep(penumbraRadius-.2,penumbraRadius+.2,d);float u=1.0-smoothstep(umbraRadius-.08,umbraRadius+.08,d);vec3 lit=outgoingLight;${shadowCode}gl_FragColor=vec4(outgoingLight,diffuseColor.a);`);
    };
    const moon = new THREE.Mesh(new THREE.SphereGeometry(1, 160, 80), material);
    const longitude = new THREE.Group(); const latitude = new THREE.Group(); const disk = new THREE.Group();
    moon.rotation.y = -Math.PI / 2; longitude.add(moon); latitude.add(longitude); disk.add(latitude); scene.add(disk);
    scene.add(new THREE.AmbientLight(0x8290aa, Number(eclipse.animation.ambient_intensity)));
    const light = new THREE.DirectionalLight(0xfff5df, Number(eclipse.animation.sun_intensity)); light.position.z = 5; scene.add(light);
    const resize = () => { const bounds = host.getBoundingClientRect(); renderer.setSize(bounds.width, bounds.height, false); camera.aspect = bounds.width / Math.max(1, bounds.height); camera.position.z = cameraDistance / Math.min(1, camera.aspect); camera.updateProjectionMatrix(); };
    new ResizeObserver(resize).observe(host); resize();
    const u2 = eclipse.contacts.U2 ? Date.parse(eclipse.contacts.U2) : null;
    const maximum = Date.parse(eclipse.maximum);
    const u3 = eclipse.contacts.U3 ? Date.parse(eclipse.contacts.U3) : null;
    setupTimeline(root, payload, (progress, timestamp) => {
      const sample = interpolate(samples, progress);
      const moonGeometry = sample.moon || {};
      const angle = THREE.MathUtils.degToRad(finite(moonGeometry.celestial_north_screen_angle));
      const shadowEast = finite(sample.shadow_east_moon_radii, 8); const shadowNorth = finite(sample.shadow_north_moon_radii);
      const x = shadowEast * Math.cos(angle) + shadowNorth * Math.sin(angle);
      const y = -shadowEast * Math.sin(angle) + shadowNorth * Math.cos(angle);
      uniforms.center.value.set(x, y); uniforms.umbra.value = finite(sample.umbra_radius_moon_radii, 2.5); uniforms.penumbra.value = finite(sample.penumbra_radius_moon_radii, 4);
      uniforms.totalityFactor.value = u2 !== null && u3 !== null && timestamp >= u2 && timestamp <= u3
        ? (timestamp <= maximum ? smoothstep(u2, maximum, timestamp) : 1 - smoothstep(maximum, u3, timestamp)) : 0;
      longitude.rotation.y = -THREE.MathUtils.degToRad(finite(moonGeometry.longitude));
      latitude.rotation.x = THREE.MathUtils.degToRad(finite(moonGeometry.latitude));
      disk.rotation.z = -THREE.MathUtils.degToRad(finite(moonGeometry.disk_angle));
      renderer.render(scene, camera);
    });
    root.classList.add('is-ready');
    publishEclipseCapture(renderer.domElement, 'lunar', capture, eclipse.capture_instants && Object.keys(eclipse.capture_instants).length > 0);
  }).catch((error) => {
    console.error('Widget de eclipse lunar real:', error);
    const loading = root.querySelector('.lunar-widget__loading');
    if (loading) loading.textContent = 'No se pudo cargar la simulación.';
    root.classList.add('has-error');
  });
});
