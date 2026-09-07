import * as THREE from './vendor/three.module.min.js';

const frame = document.querySelector('[data-photography-frame]');
const payload = window.photographyScene?.simulation_moon;
if (frame && payload?.geometry?.surface_geometry?.subobserver && payload?.geometry?.surface_geometry?.subsolar) {
  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
  renderer.setClearColor(0x000000, 0);
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.NoToneMapping;
  renderer.domElement.className = 'photography-moon-three';
  renderer.domElement.hidden = true;
  renderer.domElement.setAttribute('aria-hidden', 'true');

  const scene = new THREE.Scene();
  const camera = new THREE.OrthographicCamera(-1.65, 1.65, 1.65, -1.65, 0.1, 10);
  camera.position.set(0, 0, 4);
  const loader = new THREE.TextureLoader();

  try {
    const [albedo, normalMap] = await Promise.all([
      loader.loadAsync(payload.textures.albedo),
      loader.loadAsync(payload.textures.normal),
    ]);
    albedo.colorSpace = THREE.SRGBColorSpace;
    albedo.wrapS = THREE.RepeatWrapping;
    albedo.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
    normalMap.colorSpace = THREE.NoColorSpace;
    normalMap.wrapS = THREE.RepeatWrapping;
    normalMap.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());

    const moonViewport = new THREE.Vector2(1, 1);
    const moonHorizonClip = new THREE.Vector4(0, 0, 0, 1);
    const material = new THREE.ShaderMaterial({
      uniforms: {
        moonMap: { value: albedo },
        moonNormalMap: { value: normalMap },
        moonSunDirection: { value: new THREE.Vector3(0, 0, 1) },
        moonLitColor: { value: new THREE.Color(payload.appearance.lit_color) },
        moonLitBrightness: { value: Number(payload.appearance.lit_brightness) },
        moonTextureContrast: { value: Number(payload.appearance.contrast) || 1 },
        moonTextureVisibility: { value: Number(payload.appearance.texture_visibility) || 0 },
        moonLitSkyMix: { value: Number(payload.appearance.lit_sky_mix) || 0 },
        moonTerminatorDetail: { value: Number(payload.appearance.terminator_detail) || 0 },
        moonLimbSoftness: { value: Number(payload.appearance.limb_softness) || 0 },
        moonShadowSkyColor: { value: new THREE.Color('#030711') },
        moonShadowIntensity: { value: 1 },
        moonViewport: { value: moonViewport },
        moonHorizonClip: { value: moonHorizonClip },
        moonHorizonClipEnabled: { value: 0 },
      },
      vertexShader: `
varying vec2 moonUv;
varying vec3 moonWorldNormal;
varying vec3 moonWorldPosition;
void main() {
  moonUv = uv;
  moonWorldNormal = normalize(mat3(modelMatrix) * normal);
  vec4 worldPosition = modelMatrix * vec4(position, 1.0);
  moonWorldPosition = worldPosition.xyz;
  gl_Position = projectionMatrix * viewMatrix * worldPosition;
}`,
      fragmentShader: `
uniform sampler2D moonMap;
uniform sampler2D moonNormalMap;
uniform vec3 moonSunDirection;
uniform vec3 moonLitColor;
uniform float moonLitBrightness;
uniform float moonTextureContrast;
uniform float moonTextureVisibility;
uniform float moonLitSkyMix;
uniform float moonTerminatorDetail;
uniform float moonLimbSoftness;
uniform vec3 moonShadowSkyColor;
uniform float moonShadowIntensity;
uniform vec2 moonViewport;
uniform vec4 moonHorizonClip;
uniform float moonHorizonClipEnabled;
varying vec2 moonUv;
varying vec3 moonWorldNormal;
varying vec3 moonWorldPosition;
mat3 moonCotangentFrame(vec3 normalDirection, vec3 position, vec2 uv) {
  vec3 positionX = dFdx(position);
  vec3 positionY = dFdy(position);
  vec2 uvX = dFdx(uv);
  vec2 uvY = dFdy(uv);
  vec3 positionYPerpendicular = cross(positionY, normalDirection);
  vec3 positionXPerpendicular = cross(normalDirection, positionX);
  vec3 tangent = positionYPerpendicular * uvX.x + positionXPerpendicular * uvY.x;
  vec3 bitangent = positionYPerpendicular * uvX.y + positionXPerpendicular * uvY.y;
  float inverseScale = inversesqrt(max(dot(tangent, tangent), dot(bitangent, bitangent)));
  return mat3(tangent * inverseScale, bitangent * inverseScale, normalDirection);
}
void main() {
  if (moonHorizonClipEnabled > 0.5) {
    vec2 screenPosition = gl_FragCoord.xy / moonViewport;
    float horizonSide = (moonHorizonClip.x * screenPosition.x + moonHorizonClip.y * screenPosition.y + moonHorizonClip.z) * moonHorizonClip.w;
    if (horizonSide < 0.0) discard;
  }
  vec3 textureColor = texture2D(moonMap, moonUv).rgb;
  textureColor = clamp((textureColor - 0.5) * moonTextureContrast + 0.5, 0.0, 1.0);
  vec3 geometricNormal = normalize(moonWorldNormal);
  vec3 mappedNormal = texture2D(moonNormalMap, moonUv).xyz * 2.0 - 1.0;
  mappedNormal.xy *= 0.24;
  mappedNormal = normalize(mappedNormal);
  vec3 surfaceNormal = normalize(moonCotangentFrame(geometricNormal, moonWorldPosition, moonUv) * mappedNormal);
  float geometricIncidence = dot(geometricNormal, normalize(moonSunDirection));
  float reliefIncidence = dot(surfaceNormal, normalize(moonSunDirection));
  float reliefWeight = 1.0 - smoothstep(0.02, 0.45, abs(geometricIncidence));
  float solarIncidence = mix(geometricIncidence, reliefIncidence, reliefWeight);
  float positiveIncidence = max(solarIncidence, 0.0);
  float directLight = smoothstep(0.0, 0.012, positiveIncidence) * pow(positiveIncidence, 0.45);
  float terminatorProximity = 1.0 - smoothstep(0.04, 0.5, abs(solarIncidence));
  float textureVisibility = clamp(moonTextureVisibility + terminatorProximity * moonTerminatorDetail, 0.0, 1.5);
  textureColor = mix(vec3(0.62), textureColor, textureVisibility);
  vec3 illuminated = textureColor * moonLitColor * (0.10 + moonLitBrightness * 0.15);
  illuminated = mix(illuminated, moonShadowSkyColor, clamp(moonLitSkyMix, 0.0, 0.8));
  vec3 shadow = moonShadowSkyColor * moonShadowIntensity;
  float limbAlpha = smoothstep(0.0, max(0.0001, moonLimbSoftness), abs(normalize(moonWorldNormal).z));
  gl_FragColor = vec4(mix(shadow, illuminated, directLight), limbAlpha);
  #include <colorspace_fragment>
}`,
      transparent: true,
    });

    const haloMaterial = new THREE.ShaderMaterial({
      uniforms: {
        moonSunDirection: { value: new THREE.Vector3(0, 0, 1) },
        moonHaloColor: { value: new THREE.Color(payload.appearance.lit_color) },
        moonHaloIntensity: { value: 0 },
        moonHaloRadius: { value: 1.65 },
        moonIlluminationFraction: { value: Number(payload.geometry.illumination_fraction) || 0 },
        moonViewport: { value: moonViewport },
        moonHorizonClip: { value: moonHorizonClip },
        moonHorizonClipEnabled: { value: 0 },
      },
      vertexShader: `
varying vec2 moonHaloUv;
void main() {
  moonHaloUv = uv;
  gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
}`,
      fragmentShader: `
uniform vec3 moonSunDirection;
uniform vec3 moonHaloColor;
uniform float moonHaloIntensity;
uniform float moonHaloRadius;
uniform float moonIlluminationFraction;
uniform vec2 moonViewport;
uniform vec4 moonHorizonClip;
uniform float moonHorizonClipEnabled;
varying vec2 moonHaloUv;
void main() {
  if (moonHorizonClipEnabled > 0.5) {
    vec2 screenPosition = gl_FragCoord.xy / moonViewport;
    float horizonSide = (moonHorizonClip.x * screenPosition.x + moonHorizonClip.y * screenPosition.y + moonHorizonClip.z) * moonHorizonClip.w;
    if (horizonSide < 0.0) discard;
  }
  vec2 haloPosition = (moonHaloUv - 0.5) * 2.0 * moonHaloRadius;
  float radius = length(haloPosition);
  if (radius < 1.0 || radius >= moonHaloRadius || moonHaloIntensity <= 0.0) discard;
  float radialPosition = clamp((radius - 1.0) / max(0.001, moonHaloRadius - 1.0), 0.0, 1.0);
  float radialFalloff = exp(-2.8 * radialPosition) * (1.0 - smoothstep(0.58, 1.0, radialPosition));
  vec2 limbDirection = normalize(haloPosition);
  vec3 haloLimbNormal = normalize(vec3(limbDirection * 0.94, 0.34));
  float haloSolarIncidence = dot(haloLimbNormal, normalize(moonSunDirection));
  float illuminatedProximity = smoothstep(-0.28, 0.22, haloSolarIncidence);
  float nearFullWrap = smoothstep(0.90, 1.0, moonIlluminationFraction);
  float illuminationDistribution = mix(0.03 + illuminatedProximity * 0.97, 1.0, nearFullWrap * 0.45);
  float luminance = dot(moonHaloColor, vec3(0.2126, 0.7152, 0.0722));
  vec3 softColor = mix(moonHaloColor, vec3(luminance), 0.32);
  float alpha = radialFalloff * illuminationDistribution * moonHaloIntensity;
  gl_FragColor = vec4(softColor, alpha);
  #include <colorspace_fragment>
}`,
      transparent: true,
      depthTest: false,
      depthWrite: false,
    });

    const moon = new THREE.Mesh(new THREE.SphereGeometry(1, 192, 96), material);
    moon.rotation.y = -Math.PI / 2;
    moon.renderOrder = 1;
    const halo = new THREE.Mesh(new THREE.PlaneGeometry(2, 2), haloMaterial);
    halo.position.z = -1.1;
    halo.renderOrder = 0;
    const longitudeGroup = new THREE.Group(); const latitudeGroup = new THREE.Group(); const diskGroup = new THREE.Group();
    longitudeGroup.add(moon); latitudeGroup.add(longitudeGroup); diskGroup.add(latitudeGroup); scene.add(halo, diskGroup);

    const axisX = new THREE.Vector3(1, 0, 0); const axisY = new THREE.Vector3(0, 1, 0); const axisZ = new THREE.Vector3(0, 0, 1);
    const subobserver = payload.geometry.surface_geometry.subobserver;
    const subsolar = payload.geometry.surface_geometry.subsolar;
    const longitudeRotation = -THREE.MathUtils.degToRad(Number(subobserver.longitude_degrees));
    const latitudeRotation = THREE.MathUtils.degToRad(Number(subobserver.latitude_degrees));

    const renderLayout = (event) => {
      const detail = event.detail || {};
      const layout = detail.layout;
      if (!detail.visible || !layout) {
        renderer.domElement.hidden = true;
        return;
      }
      const frameRect = frame.getBoundingClientRect();
      const localSkyColor = new THREE.Color(/^#[0-9a-f]{6}$/i.test(String(detail.skyColor)) ? detail.skyColor : '#030711');
      const integration = Math.max(0, Math.min(1, Number(payload.appearance.dark_sky_mix)));
      material.uniforms.moonLitColor.value.set(payload.appearance.lit_color);
      material.uniforms.moonLitBrightness.value = Number(payload.appearance.lit_brightness);
      material.uniforms.moonTextureContrast.value = Number(payload.appearance.contrast) || 1;
      material.uniforms.moonTextureVisibility.value = Number(payload.appearance.texture_visibility) || 0;
      material.uniforms.moonLitSkyMix.value = Number(payload.appearance.lit_sky_mix) || 0;
      material.uniforms.moonTerminatorDetail.value = Number(payload.appearance.terminator_detail) || 0;
      material.uniforms.moonLimbSoftness.value = Number(payload.appearance.limb_softness) || 0;
      material.uniforms.moonShadowSkyColor.value.copy(localSkyColor);
      material.uniforms.moonShadowIntensity.value = Number(payload.appearance.dark_brightness) * (0.25 + integration * 0.75);
      const nightAltitude = Number(window.photographyScene?.simulation?.night_transition_altitude);
      const dayAltitude = Number(window.photographyScene?.simulation?.day_transition_altitude);
      const twilightCenter = Math.max(nightAltitude + 0.001, Math.min(dayAltitude - 0.001, Number(window.photographyScene?.simulation?.twilight_center_altitude)));
      const solarAltitude = Number(window.photographyScene?.astronomy?.sun?.altitude_degrees);
      const smoothstep = (edge0, edge1, value) => {
        const normalized = Math.max(0, Math.min(1, (value - edge0) / Math.max(0.001, edge1 - edge0)));
        return normalized * normalized * (3 - 2 * normalized);
      };
      const skyHaloWeight = solarAltitude <= twilightCenter
        ? 1 + (0.32 - 1) * smoothstep(nightAltitude, twilightCenter, solarAltitude)
        : 0.32 * (1 - smoothstep(twilightCenter, dayAltitude, solarAltitude));
      const illuminationFraction = Math.max(0, Math.min(1, Number(payload.geometry.illumination_fraction) || 0));
      const phaseStart = Math.max(0, Math.min(0.99, Number(window.photographyScene?.simulation?.moon_halo_phase_start_percent) / 100));
      const phaseHaloWeight = smoothstep(phaseStart, 1, illuminationFraction);
      const haloRadius = Math.max(1.05, Math.min(3, Number(window.photographyScene?.simulation?.moon_halo_radius) || 1.65));
      const haloSetting = window.photographyScene?.simulation?.moon_halo_enabled;
      const haloEnabled = haloSetting === true || haloSetting === 1 || haloSetting === '1';
      const haloIntensity = haloEnabled ? Math.max(0, Number(window.photographyScene?.simulation?.moon_halo_max_intensity) || 0) * phaseHaloWeight * skyHaloWeight : 0;
      halo.visible = haloIntensity > 0;
      const haloColor = new THREE.Color(payload.appearance.lit_color);
      haloMaterial.uniforms.moonHaloColor.value.copy(haloColor);
      haloMaterial.uniforms.moonHaloIntensity.value = haloIntensity;
      haloMaterial.uniforms.moonHaloRadius.value = haloRadius;
      haloMaterial.uniforms.moonIlluminationFraction.value = illuminationFraction;
      halo.scale.setScalar(haloRadius);
      const renderRadius = halo.visible ? haloRadius : 1.08;
      camera.left = -renderRadius; camera.right = renderRadius; camera.top = renderRadius; camera.bottom = -renderRadius; camera.updateProjectionMatrix();
      const diameterX = frameRect.width * Number(layout.diameterWidthPercent) / 100;
      const diameterY = frameRect.height * Number(layout.diameterHeightPercent) / 100;
      const diameter = Math.max(1, (diameterX + diameterY) / 2);
      const extent = diameter * renderRadius;
      const centerX = frameRect.width * Number(layout.xPercent) / 100;
      const centerY = frameRect.height * Number(layout.yPercent) / 100;
      renderer.domElement.style.width = `${extent}px`; renderer.domElement.style.height = `${extent}px`;
      renderer.domElement.style.left = `${centerX - extent / 2}px`; renderer.domElement.style.top = `${centerY - extent / 2}px`;
      if (detail.visibilityState === 'partially_visible' && Array.isArray(detail.visibleSkyPolygon)) {
        const canvasLeft = centerX - extent / 2;
        const canvasTop = centerY - extent / 2;
        const localPoints = detail.visibleSkyPolygon.map((point) => {
          const x = (frameRect.width * Number(point.x) / 100 - canvasLeft) / extent * 100;
          const y = (frameRect.height * Number(point.y) / 100 - canvasTop) / extent * 100;
          return { x: x / 100, y: 1 - y / 100 };
        });
        const first = localPoints[0]; const second = localPoints[1]; const skyPoint = localPoints[2];
        const a = first.y - second.y; const b = second.x - first.x; const c = first.x * second.y - second.x * first.y;
        const length = Math.max(1e-9, Math.hypot(a, b));
        const normalizedA = a / length; const normalizedB = b / length; const normalizedC = c / length;
        const skySign = normalizedA * skyPoint.x + normalizedB * skyPoint.y + normalizedC >= 0 ? 1 : -1;
        material.uniforms.moonHorizonClip.value.set(normalizedA, normalizedB, normalizedC, skySign);
        material.uniforms.moonHorizonClipEnabled.value = 1;
        haloMaterial.uniforms.moonHorizonClipEnabled.value = 1;
      } else {
        material.uniforms.moonHorizonClipEnabled.value = 0;
        haloMaterial.uniforms.moonHorizonClipEnabled.value = 0;
      }
      const renderPixels = Math.max(64, Math.min(1024, Math.round(extent * Math.min(window.devicePixelRatio || 1, 2))));
      renderer.setSize(renderPixels, renderPixels, false);
      material.uniforms.moonViewport.value.set(renderPixels, renderPixels);

      const diskRotation = -THREE.MathUtils.degToRad(Number(payload.geometry.orientation.lunar_north_screen_angle_degrees) + Number(detail.roll || 0));
      longitudeGroup.rotation.y = longitudeRotation; latitudeGroup.rotation.x = latitudeRotation; diskGroup.rotation.z = diskRotation;
      material.uniforms.moonSunDirection.value.set(Number(subsolar.body_fixed_unit_vector.x), Number(subsolar.body_fixed_unit_vector.y), Number(subsolar.body_fixed_unit_vector.z))
        .applyAxisAngle(axisY, longitudeRotation).applyAxisAngle(axisX, latitudeRotation).applyAxisAngle(axisZ, diskRotation).normalize();
      haloMaterial.uniforms.moonSunDirection.value.copy(material.uniforms.moonSunDirection.value);
      renderer.render(scene, camera);
      if (!renderer.domElement.isConnected) frame.append(renderer.domElement);
      renderer.domElement.hidden = false;
      frame.classList.add('has-simulated-moon-3d');
    };
    frame.addEventListener('photography-moon-layout', renderLayout);
    frame.dispatchEvent(new CustomEvent('photography-moon-three-ready'));
  } catch (error) {
    renderer.dispose();
    console.error('Fotografía: no se pudo iniciar la Luna 3D; se conserva el fallback SVG.', error);
  }
}
