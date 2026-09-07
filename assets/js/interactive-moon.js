import * as THREE from './vendor/three.module.min.js';

const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));
const axisX = new THREE.Vector3(1, 0, 0);
const axisY = new THREE.Vector3(0, 1, 0);
const axisZ = new THREE.Vector3(0, 0, 1);
const publicNumber = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 2 });
const publicDate = new Intl.DateTimeFormat('es-AR', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
const cardinalCoordinate = (value, positive, negative) => `${publicNumber.format(Math.abs(Number(value)))}° ${Number(value) >= 0 ? positive : negative}`;
let scientificPresentation = { values: {}, sources: {}, units: {} };

const normalizeSearchText = (value) => String(value || '')
  .normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
})[character]);
const readableKey = (key) => ({
  formation_process: 'Formación', classification: 'Clasificación', geologic_system: 'Período geológico',
  absolute_age: 'Edad absoluta', materials: 'Materiales', context: 'Contexto', relation_to_major_event: 'Relación con evento mayor',
  center_elevation_m: 'Elevación central', depth_m: 'Profundidad', rim_height_m: 'Altura del borde', relief_m: 'Relieve',
  floor_to_rim_m: 'Piso a borde', central_peak_above_floor_m: 'Pico sobre el piso', length_km: 'Longitud',
  maximum_width_km: 'Ancho máximo', local_width_km: 'Ancho local', local_depth_m: 'Profundidad local',
  features: 'Rasgos', mission_findings: 'Muestras y hallazgos', limitations: 'Limitaciones', value: 'Valor',
  value_ma: 'Valor', value_ga: 'Valor', value_min_ga: 'Mínimo', value_max_ga: 'Máximo', range_ga: 'Rango',
  applies_to: 'Alcance', method: 'Método', resolution_km: 'Resolución', precision: 'Precisión', note: 'Nota',
  recommended_derivation: 'Cálculo futuro recomendado', source_product: 'Producto', location: 'Ubicación de la medición',
  transient_crater_diameter_km: 'Cavidad transitoria', excavated_volume_km3: 'Volumen excavado',
  mare_emplacement_age_ga: 'Edad de basaltos regionales', site_units: 'Unidades del sitio', usgs_map_unit: 'Unidad USGS',
  unit_symbol: 'Símbolo', reason: 'Estado', summit_elevation_m: 'Elevación de cumbre', height_above_local_mare_m: 'Altura sobre el mare',
  derived: 'derivado', measured: 'medido', modeled: 'modelado', published_estimate: 'estimación publicada', interpretation: 'interpretación',
  relation: 'Relación espacial', map_scale: 'Escala del mapa', unit: 'Unidad', symbol: 'Código', age: 'Edad de la unidad',
  period: 'Período', name: 'Nombre', description: 'Descripción', interpretation: 'Interpretación', limitation: 'Limitación',
  relative_age: 'Edad relativa', age_class: 'Clase de edad', source_detail: 'Referencia de edad', catalog_diameter_km: 'Diámetro en el catálogo',
  coordinate_separation_degrees: 'Separación de coordenadas', diameter_difference_percent: 'Diferencia de diámetro',
  measured_rim_to_floor_depth_km: 'Profundidad medida piso-borde', modeled_rim_to_floor_depth_km: 'Profundidad modelada piso-borde',
  modeled_rim_height_km: 'Altura modelada del borde', measured_central_peak_height_km: 'Altura medida del pico central',
  peak_degradation: 'Degradación del pico', rays: 'Rayos', catalog_match: 'Match de catálogo', crater_catalog: 'Control del match',
  map_unit_name: 'Nombre cartográfico', mission: 'Misión', landing_date: 'Fecha de alunizaje', site_name: 'Lugar', findings: 'Hallazgos',
}[key] || key.replaceAll('_', ' '));

const localizedText = (value) => scientificPresentation.values?.[String(value)] || String(value);

function localizedScientific(value, parentKey = '') {
  if (Array.isArray(value)) return value.map((item) => localizedScientific(item, parentKey));
  if (!value || typeof value !== 'object') return typeof value === 'string' ? localizedText(value) : value;
  const localized = {};
  Object.entries(value).forEach(([key, child]) => { localized[key] = localizedScientific(child, key); });
  if (parentKey === 'unit' && value.symbol && scientificPresentation.units?.[value.symbol]) {
    const translation = scientificPresentation.units[value.symbol];
    if (translation.name) localized.name = translation.name;
    if (translation.description) localized.description = translation.description;
    if (translation.interpretation) localized.interpretation = translation.interpretation;
    delete localized.map_unit_name;
  }
  return localized;
}

function valueText(key, value) {
  if (value === null || value === undefined || value === '') return '';
  if (Array.isArray(value) && value.every((item) => typeof item !== 'object')) return value.map((item) => valueText(key, item)).join(' – ');
  if (typeof value === 'boolean') return value ? 'Sí' : 'No';
  const units = key.endsWith('_km3') ? ' km³' : key.endsWith('_km') ? ' km' : key.endsWith('_m') ? ' m'
    : key.endsWith('_ma') ? ' Ma' : key.endsWith('_ga') ? ' Ga' : key.endsWith('_deg') ? '°' : '';
  return `${typeof value === 'number' ? publicNumber.format(value) : localizedText(value)}${units}`;
}

function appendScientificValue(root, key, value) {
  if (['source', 'sources', 'label', 'evidence', 'map_unit_name'].includes(key)) return;
  if (value === null || value === undefined || value === '') return;
  const item = document.createElement('li');
  if (typeof value !== 'object') {
    item.textContent = `${readableKey(key)}: ${valueText(key, value)}`;
    root.append(item);
    return;
  }
  if (Array.isArray(value)) {
    const title = document.createElement('strong');
    title.textContent = `${readableKey(key)}:`;
    item.append(title);
    const nested = document.createElement('ul');
    value.forEach((entry) => appendScientificValue(nested, 'value', entry));
    if (nested.children.length) item.append(nested);
    root.append(item);
    return;
  }
  const scalar = valueText(key, value.value ?? value.value_ma ?? value.value_ga);
  const title = document.createElement('strong');
  title.textContent = `${readableKey(key)}${scalar ? `: ${scalar}` : ''}`;
  item.append(title);
  if (value.evidence) {
    const badge = document.createElement('span');
    badge.className = 'interactive-moon-evidence';
    badge.textContent = localizedText(value.evidence);
    item.append(badge);
  }
  const nested = document.createElement('ul');
  Object.entries(value).forEach(([childKey, childValue]) => {
    if (['value', 'value_ma', 'value_ga', 'evidence', 'source', 'sources', 'label'].includes(childKey)) return;
    appendScientificValue(nested, childKey, childValue);
  });
  if (nested.children.length) item.append(nested);
  root.append(item);
}

function collectSourceIds(value, target = new Set()) {
  if (Array.isArray(value)) value.forEach((item) => collectSourceIds(item, target));
  else if (value && typeof value === 'object') Object.entries(value).forEach(([key, child]) => {
    if (key === 'sources' && Array.isArray(child)) child.forEach((id) => target.add(id));
    else if (key === 'source' && typeof child === 'string') target.add(child);
    else collectSourceIds(child, target);
  });
  return target;
}

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
diffuseColor.rgb = pow(max(diffuseColor.rgb, vec3(0.0)), vec3(1.0 / textureGamma));`);
  };
  material.customProgramCacheKey = () => 'aquellas-lunas-interactive-appearance-v2';
}

function bodyVector(longitudeDegrees, latitudeDegrees, radius = 1.012) {
  const longitude = THREE.MathUtils.degToRad(longitudeDegrees);
  const latitude = THREE.MathUtils.degToRad(latitudeDegrees);
  return new THREE.Vector3(
    Math.cos(latitude) * Math.sin(longitude),
    Math.sin(latitude),
    Math.cos(latitude) * Math.cos(longitude),
  ).multiplyScalar(radius);
}

function overlaps(first, second, padding = 4) {
  return first.left < second.right + padding && first.right > second.left - padding
    && first.top < second.bottom + padding && first.bottom > second.top - padding;
}

const MAXIMUM_LABEL_DETAIL_ZOOM = 1.62;

function automaticLabelBand(zoom) {
  if (zoom < 1.12) return { id: 'low', denseMinimumDiameter: Infinity, budgetArea: 45000, minimum: 8, maximum: 18 };
  if (zoom < 1.42) return { id: 'medium', denseMinimumDiameter: Infinity, budgetArea: 30000, minimum: 10, maximum: 28 };
  if (zoom < 1.56) return { id: 'high-large', denseMinimumDiameter: 100, budgetArea: 24000, minimum: 12, maximum: 36 };
  if (zoom < 1.66) return { id: 'high', denseMinimumDiameter: 50, budgetArea: 20000, minimum: 14, maximum: 46 };
  return { id: 'maximum', denseMinimumDiameter: 20, budgetArea: 16000, minimum: 18, maximum: 64 };
}

function gazetteerLayer(object) {
  const type = String(object.type || '');
  if (type.startsWith('Crater') || type === 'Satellite Feature') return 'craters';
  if (/^(Mare|Oceanus|Lacus|Palus|Sinus),?/.test(type)) return 'maria';
  return 'other';
}

async function mountInteractiveMoon(container) {
  const data = container.querySelector('[data-interactive-moon-payload]');
  const labelsRoot = container.querySelector('[data-moon-labels]');
  if (!data || !labelsRoot) return;
  let payload;
  try {
    payload = JSON.parse(data.textContent || '');
  } catch (error) {
    console.error('Luna interactiva: payload inválido.', error);
    container.dataset.moonState = 'fallback';
    return;
  }

  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
  } catch (error) {
    console.error('Luna interactiva: WebGL no disponible.', error);
    container.dataset.moonState = 'fallback';
    return;
  }

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);
  const standalone = payload.interactive?.standalone === true;
  const controlsRoot = standalone ? (container.closest('[data-home-moon-explorer-dialog]') || container.parentElement) : document;
  const query = standalone ? new URLSearchParams() : new URLSearchParams(window.location.search);
  const view = {
    yaw: clamp(Number(query.get('yaw')) || 0, -180, 180),
    pitch: clamp(Number(query.get('pitch')) || 0, -75, 75),
    zoom: clamp(Number(query.get('zoom')) || 1, 0.8, 1.7),
  };
  const configuredSize = clamp(Number(payload.appearance?.size_percent) || 100, 85, 120) / 100;
  const focusQuaternion = new THREE.Quaternion();
  let selectedObject = null;
  let selectedFeature = null;
  let previousView = null;
  const layers = {
    craters: payload.interactive?.craters !== false,
    maria: payload.interactive?.maria !== false,
    other: payload.interactive?.other === true,
    landings: payload.interactive?.landings !== false,
  };
  let detail = ['main', 'more'].includes(payload.interactive?.detail) ? payload.interactive.detail : 'auto';
  let illumination = payload.interactive?.illumination === 'full' ? 'full' : 'realistic';
  camera.position.set(0, 0, 4.15 / (view.zoom * configuredSize));
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = Number(payload.appearance.exposure);
  renderer.domElement.className = 'interactive-moon-canvas';
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
    addAppearanceShader(material, standalone
      ? { ...payload.appearance, terminator_attenuation_enabled: 'off' }
      : payload.appearance);
    const highResolution = payload.appearance.high_resolution || {};
    const highResolutionAllowed = highResolution.enabled === 'on'
      && payload.appearance.relief_mode === 'normal'
      && highResolution.mode !== 'standard'
      && typeof payload.textures.relief_high === 'string';
    const highResolutionSupported = renderer.capabilities.maxTextureSize >= 8192;
    let highResolutionActive = false;
    let highResolutionLoading = null;
    let expandedHighResolution = standalone && controlsRoot instanceof HTMLDialogElement && controlsRoot.open;

    const moon = new THREE.Mesh(new THREE.SphereGeometry(1, 256, 128), material);
    moon.rotation.y = -Math.PI / 2;
    const longitudeGroup = new THREE.Group();
    const latitudeGroup = new THREE.Group();
    const diskGroup = new THREE.Group();
    const inspectionGroup = new THREE.Group();
    longitudeGroup.add(moon);
    latitudeGroup.add(longitudeGroup);
    diskGroup.add(latitudeGroup);
    inspectionGroup.add(diskGroup);
    scene.add(inspectionGroup);

    const geometry = payload.geometry;
    const subobserver = geometry.surface_geometry.subobserver;
    const subsolar = geometry.surface_geometry.subsolar;
    const longitudeRotation = -THREE.MathUtils.degToRad(subobserver.longitude_degrees);
    const latitudeRotation = THREE.MathUtils.degToRad(subobserver.latitude_degrees);
    const diskRotation = -THREE.MathUtils.degToRad(geometry.orientation.lunar_north_screen_angle_degrees);
    longitudeGroup.rotation.y = longitudeRotation;
    latitudeGroup.rotation.x = latitudeRotation;
    diskGroup.rotation.z = diskRotation;

    const baseSunPosition = new THREE.Vector3(
      subsolar.body_fixed_unit_vector.x,
      subsolar.body_fixed_unit_vector.y,
      subsolar.body_fixed_unit_vector.z,
    ).applyAxisAngle(axisY, longitudeRotation)
      .applyAxisAngle(axisX, latitudeRotation)
      .applyAxisAngle(axisZ, diskRotation)
      .multiplyScalar(5);
    const sunlight = new THREE.DirectionalLight(0xfff7e8, Number(payload.appearance.sun_intensity));
    sunlight.target.position.set(0, 0, 0);
    scene.add(sunlight, sunlight.target, new THREE.AmbientLight(0x73809b, Number(payload.appearance.ambient_intensity)));

    const labelsInteractive = Boolean(payload.catalogs?.gazetteer);
    const createFeatureModel = (feature, append = true) => {
      const model = { ...feature, position: bodyVector(Number(feature.lon), Number(feature.lat)), element: null };
      if (!append) return model;
      ensureFeatureElement(model, true);
      return model;
    };
    function ensureFeatureElement(model, append = false) {
      if (model.element) {
        if (append && !model.element.isConnected) labelsRoot.append(model.element);
        return model.element;
      }
      const element = document.createElement(labelsInteractive ? 'button' : 'span');
      if (labelsInteractive) element.type = 'button';
      element.className = `interactive-moon-label interactive-moon-label--${model.layer}`;
      element.innerHTML = `<i aria-hidden="true"></i><b>${String(model.name).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character])}</b>`;
      model.element = element;
      if (labelsInteractive) {
        element.classList.add('interactive-moon-label--clickable');
        element.setAttribute('aria-label', `Ver información de ${model.name}`);
        element.addEventListener('pointerdown', (event) => event.stopPropagation());
        element.addEventListener('click', (event) => {
          event.stopPropagation();
          if (model.choose) model.choose();
          else {
            model.revealed = !model.revealed;
            element.classList.toggle('is-revealed', model.revealed);
          }
        });
      }
      if (append) labelsRoot.append(element);
      return element;
    }
    const features = (payload.features?.features || []).map((feature) => createFeatureModel(feature));
    let maximumDetailFeatures = [];
    const installGazetteerFeatures = (gazetteer) => {
      if (maximumDetailFeatures.length || !Array.isArray(gazetteer?.features)) return;
      const curatedSourceIds = new Set(features.map((feature) => Number(feature.source_id)).filter(Number.isFinite));
      maximumDetailFeatures = gazetteer.features
        .filter((object) => !curatedSourceIds.has(Number(object.iau_feature_id)))
        .map((object) => createFeatureModel({
          name: object.name,
          layer: gazetteerLayer(object),
          type: object.type,
          lat: Number(object.latitude),
          lon: Number(object.longitude),
          diameter_km: Number(object.diameter_km) || 0,
          importance: 3,
          source_id: object.iau_feature_id,
          gazetteer_object: object,
        }, false));
    };
    const gazetteerPromise = payload.catalogs?.gazetteer
      ? fetch(payload.catalogs.gazetteer).then((response) => {
        if (!response.ok) throw new Error('Gazetteer no disponible');
        return response.json();
      })
      : Promise.resolve(null);
    const selectedElement = document.createElement('span');
    selectedElement.className = 'interactive-moon-label interactive-moon-label--selected';
    selectedElement.innerHTML = '<i aria-hidden="true"></i><b></b>';
    selectedElement.hidden = true;
    labelsRoot.append(selectedElement);

    const updateInspection = () => {
      const manualRotation = new THREE.Quaternion().setFromEuler(new THREE.Euler(
        THREE.MathUtils.degToRad(view.pitch), THREE.MathUtils.degToRad(view.yaw), 0, 'YXZ',
      ));
      inspectionGroup.quaternion.copy(manualRotation).multiply(focusQuaternion);
      inspectionGroup.updateMatrixWorld(true);
      if (illumination === 'full') sunlight.position.set(0, 0, 5);
      else sunlight.position.copy(baseSunPosition).applyQuaternion(inspectionGroup.quaternion);
      const portraitFit = Math.max(1, 1 / Math.max(camera.aspect, 0.01));
      camera.position.z = 4.15 * portraitFit / (view.zoom * configuredSize);
    };

    const placeLabels = (width, height) => {
      const occupied = [];
      const candidates = [];
      let collisionRejects = 0;
      const maximumDetailActive = detail === 'more' && view.zoom >= MAXIMUM_LABEL_DETAIL_ZOOM;
      const automaticBand = automaticLabelBand(view.zoom);
      const automaticDenseActive = detail === 'auto' && Number.isFinite(automaticBand.denseMinimumDiameter);
      const fixedLabelBudget = detail === 'auto'
        ? clamp(Math.round((width * height) / automaticBand.budgetArea), automaticBand.minimum, automaticBand.maximum)
        : Infinity;
      const markerBudget = detail === 'auto' ? fixedLabelBudget * 4 : Infinity;
      const markerCells = new Set();
      let densityRejects = 0;
      longitudeGroup.updateMatrixWorld(true);
      maximumDetailFeatures.forEach((feature) => {
        feature.visibleThisRender = false;
        if (feature.element) {
          feature.element.hidden = true;
        }
      });
      const labelFeatures = maximumDetailActive || automaticDenseActive ? [...features, ...maximumDetailFeatures] : features;
      labelFeatures.forEach((feature) => {
        const isSelected = feature === selectedFeature;
        if (feature.element) feature.element.classList.toggle('interactive-moon-label--active', isSelected);
        const enabled = layers[feature.layer] === true;
        const importance = Number(feature.importance);
        const detailedEnough = importance === 1
          || (detail === 'more' && view.zoom >= 1.08)
          || (detail === 'auto' && importance === 2 && view.zoom >= 1.12)
          || (detail === 'auto' && importance > 2 && Number(feature.diameter_km || 0) >= automaticBand.denseMinimumDiameter);
        if (!isSelected && (!enabled || !detailedEnough)) {
          if (feature.element) feature.element.hidden = true;
          return;
        }
        const world = longitudeGroup.localToWorld(feature.position.clone());
        const normal = feature.position.clone().normalize().transformDirection(longitudeGroup.matrixWorld);
        const toCamera = camera.position.clone().sub(world).normalize();
        if (normal.dot(toCamera) < 0.08) {
          if (feature.element) feature.element.hidden = true;
          return;
        }
        const projected = world.clone().project(camera);
        const x = (projected.x * 0.5 + 0.5) * width;
        const y = (-projected.y * 0.5 + 0.5) * height;
        if (!isSelected && (x < -8 || x > width + 8 || y < -8 || y > height + 8)) return;
        candidates.push({ feature, x, y, depth: projected.z, selected: isSelected });
      });
      candidates.sort((first, second) => Number(second.selected) - Number(first.selected)
        || Number(first.feature.importance) - Number(second.feature.importance)
        || (maximumDetailActive || detail === 'auto'
          ? Number(second.feature.diameter_km || 0) - Number(first.feature.diameter_km || 0)
          : 0)
        || first.depth - second.depth);
      let placedMarkers = 0;
      candidates.forEach(({ feature, x, y }) => {
        const markerCell = `${Math.round(x / 14)}:${Math.round(y / 14)}`;
        const secondaryMarker = detail === 'auto' && Number(feature.importance) > 2;
        if (secondaryMarker && (placedMarkers >= markerBudget || markerCells.has(markerCell))) {
          if (feature.element) {
            feature.element.hidden = true;
            feature.element.remove();
          }
          densityRejects += 1;
          return;
        }
        markerCells.add(markerCell);
        placedMarkers += 1;
        const widthEstimate = Math.max(48, String(feature.name).length * 7 + 20);
        const heightEstimate = 24;
        const offsets = feature.layer === 'maria' ? [0, -18, 18, -36, 36] : [-14, 8, -30, 26, -48, 44];
        let placement = null;
        const canShowFixedLabel = feature === selectedFeature || occupied.length < fixedLabelBudget;
        for (const offsetY of canShowFixedLabel ? offsets : []) {
          const left = clamp(x - widthEstimate / 2, 4, width - widthEstimate - 4);
          const top = clamp(y + offsetY - heightEstimate / 2, 4, height - heightEstimate - 4);
          const box = { left, top, right: left + widthEstimate, bottom: top + heightEstimate };
          const padding = (maximumDetailActive || detail === 'auto') && Number(feature.importance) > 2 ? 2 : 4;
          if (!occupied.some((other) => overlaps(box, other, padding))) {
            placement = box;
            break;
          }
        }
        const element = ensureFeatureElement(feature, true);
        feature.visibleThisRender = true;
        element.hidden = false;
        element.style.transform = `translate3d(${x}px, ${y}px, 0)`;
        element.classList.toggle('is-centered', feature.layer === 'maria');
        element.classList.toggle('is-marker-only', !placement);
        if (!placement) {
          collisionRejects += 1;
          const text = element.querySelector('b');
          if (text) text.style.transform = 'translate3d(8px, -30px, 0)';
          return;
        }
        occupied.push(placement);
        const text = element.querySelector('b');
        if (text) text.style.transform = `translate3d(${placement.left - x}px, ${placement.top - y}px, 0)`;
      });
      maximumDetailFeatures.forEach((feature) => {
        if (!feature.visibleThisRender && feature.element?.isConnected) feature.element.remove();
      });
      labelsRoot.dataset.labelCandidates = String(candidates.length);
      labelsRoot.dataset.labelPlaced = String(occupied.length);
      labelsRoot.dataset.labelCollisionRejects = String(collisionRejects);
      labelsRoot.dataset.labelDensityRejects = String(densityRejects);
      labelsRoot.dataset.labelMarkers = String(placedMarkers);
      labelsRoot.dataset.labelMode = detail;
      labelsRoot.dataset.labelBand = detail === 'auto' ? automaticBand.id : detail;
      labelsRoot.dataset.labelBudget = Number.isFinite(fixedLabelBudget) ? String(fixedLabelBudget) : 'unlimited';
      if (!selectedObject || selectedFeature) {
        selectedElement.hidden = true;
        return;
      }
      const selectedPosition = bodyVector(Number(selectedObject.longitude), Number(selectedObject.latitude));
      const selectedWorld = longitudeGroup.localToWorld(selectedPosition.clone());
      const selectedNormal = selectedPosition.clone().normalize().transformDirection(longitudeGroup.matrixWorld);
      if (selectedNormal.dot(camera.position.clone().sub(selectedWorld).normalize()) < 0.02) {
        selectedElement.hidden = true;
        return;
      }
      const projected = selectedWorld.project(camera);
      const x = (projected.x * .5 + .5) * width;
      const y = (-projected.y * .5 + .5) * height;
      selectedElement.hidden = false;
      selectedElement.style.transform = `translate3d(${x}px, ${y}px, 0)`;
      const selectedText = selectedElement.querySelector('b');
      if (selectedText) {
        selectedText.textContent = selectedObject.name;
        selectedText.style.transform = 'translate3d(10px, -30px, 0)';
      }
    };

    const render = () => {
      const bounds = container.getBoundingClientRect();
      const width = Math.max(1, Math.round(bounds.width));
      const height = Math.max(1, Math.round(bounds.height));
      renderer.setSize(width, height, false);
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
      updateInspection();
      renderer.render(scene, camera);
      placeLabels(width, height);
      const threshold = clamp(Number(highResolution.zoom_threshold) || 1.3, 1, 1.7);
      const shouldLoadHighResolution = highResolutionAllowed
        && highResolutionSupported
        && (highResolution.mode === 'high' || (highResolution.mode === 'auto' && (expandedHighResolution || view.zoom >= threshold)));
      if (shouldLoadHighResolution && !highResolutionActive && !highResolutionLoading) {
        container.dataset.moonHighResolution = 'loading';
        highResolutionLoading = loader.loadAsync(payload.textures.relief_high).then((texture) => {
          configureTexture(texture, renderer, THREE.NoColorSpace);
          texture.generateMipmaps = false;
          texture.minFilter = THREE.LinearFilter;
          texture.needsUpdate = true;
          material.normalMap = texture;
          material.bumpMap = null;
          material.normalScale.set(
            Number(highResolution.normal_scale_x) || 0,
            Number(highResolution.normal_scale_y) || 0,
          );
          material.needsUpdate = true;
          highResolutionActive = true;
          container.dataset.moonHighResolution = 'ready';
          render();
        }).catch((error) => {
          container.dataset.moonHighResolution = 'fallback';
          console.error('Luna interactiva: no se pudo cargar el normal map 8K.', error);
        });
      } else if (highResolutionAllowed && !highResolutionSupported) {
        container.dataset.moonHighResolution = 'unsupported';
      } else if (!highResolutionActive && !highResolutionLoading) {
        container.dataset.moonHighResolution = 'standard';
      }
    };
    container.prepend(renderer.domElement);
    render();
    if (standalone) container.addEventListener('moonexploreropen', () => {
      expandedHighResolution = true;
      render();
    });
    container.dataset.moonState = 'ready';
    container.classList.add('is-ready');
    gazetteerPromise.then((gazetteer) => {
      installGazetteerFeatures(gazetteer);
      render();
    }).catch((error) => console.error('Luna interactiva: catálogo de etiquetas no disponible.', error));

    let urlTimer = null;
    const updateUrl = () => {
      if (standalone) return;
      window.clearTimeout(urlTimer);
      urlTimer = window.setTimeout(() => {
        const url = new URL(window.location.href);
        Object.entries(layers).forEach(([layer, enabled]) => url.searchParams.set(layer, enabled ? '1' : '0'));
        url.searchParams.set('detail', detail);
        url.searchParams.set('illumination', illumination);
        if (Math.abs(view.yaw) > 0.05) url.searchParams.set('yaw', view.yaw.toFixed(1)); else url.searchParams.delete('yaw');
        if (Math.abs(view.pitch) > 0.05) url.searchParams.set('pitch', view.pitch.toFixed(1)); else url.searchParams.delete('pitch');
        if (Math.abs(view.zoom - 1) > 0.005) url.searchParams.set('zoom', view.zoom.toFixed(2)); else url.searchParams.delete('zoom');
        if (selectedObject?.id) url.searchParams.set('feature', selectedObject.id); else url.searchParams.delete('feature');
        history.replaceState(null, '', url);
      }, 120);
    };

    controlsRoot.querySelectorAll('[data-moon-layer]').forEach((control) => {
      control.addEventListener('change', () => {
        const layer = control.dataset.moonLayer;
        if (!(layer in layers)) return;
        layers[layer] = control.checked;
        const stateInput = document.querySelector(`[data-layer-state-input="${layer}"]`);
        if (stateInput) stateInput.value = control.checked ? '1' : '0';
        render();
        updateUrl();
      });
    });
    const detailControl = controlsRoot.querySelector('[data-moon-detail]');
    detailControl?.addEventListener('change', () => {
      detail = ['main', 'more'].includes(detailControl.value) ? detailControl.value : 'auto';
      const stateInput = document.querySelector('[data-detail-state-input]');
      if (stateInput) stateInput.value = detail;
      render();
      updateUrl();
    });
    const illuminationControl = controlsRoot.querySelector('[data-moon-illumination]');
    illuminationControl?.addEventListener('change', () => {
      illumination = illuminationControl.value === 'full' ? 'full' : 'realistic';
      const stateInput = document.querySelector('[data-illumination-state-input]');
      if (stateInput) stateInput.value = illumination;
      const lightLabel = document.querySelector('[data-moon-light-label]');
      if (lightLabel) lightLabel.textContent = illumination === 'full' ? 'Todo iluminado' : 'Realista';
      render();
      updateUrl();
    });
    controlsRoot.querySelector('[data-moon-reset-view]')?.addEventListener('click', () => {
      view.yaw = 0;
      view.pitch = 0;
      view.zoom = 1;
      focusQuaternion.identity();
      selectedObject = null;
      selectedFeature = null;
      previousView = null;
      document.querySelector('[data-moon-object-detail]')?.setAttribute('hidden', '');
      document.querySelector('[data-moon-default-summary]')?.removeAttribute('hidden');
      render();
      updateUrl();
    });
    controlsRoot.querySelector('[data-moon-zoom-out]')?.addEventListener('click', () => {
      view.zoom = clamp(view.zoom / 1.16, 0.8, 1.7);
      render();
      updateUrl();
    });
    controlsRoot.querySelector('[data-moon-zoom-in]')?.addEventListener('click', () => {
      view.zoom = clamp(view.zoom * 1.16, 0.8, 1.7);
      render();
      updateUrl();
    });

    const pointers = new Map();
    let pinch = null;
    const pointerDistance = () => {
      const active = [...pointers.values()];
      return active.length < 2 ? 0 : Math.hypot(active[0].x - active[1].x, active[0].y - active[1].y);
    };
    container.addEventListener('pointerdown', (event) => {
      if (event.pointerType === 'mouse' && event.button !== 0) return;
      pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
      container.setPointerCapture(event.pointerId);
      container.classList.add('is-dragging');
      if (pointers.size >= 2) pinch = { distance: pointerDistance(), zoom: view.zoom };
    });
    container.addEventListener('pointermove', (event) => {
      const pointer = pointers.get(event.pointerId);
      if (!pointer) return;
      const deltaX = event.clientX - pointer.x;
      const deltaY = event.clientY - pointer.y;
      pointer.x = event.clientX;
      pointer.y = event.clientY;
      if (pointers.size >= 2 && pinch?.distance > 0) {
        view.zoom = clamp(pinch.zoom * pointerDistance() / pinch.distance, 0.8, 1.7);
        render();
        updateUrl();
        return;
      }
      view.yaw = clamp(view.yaw + deltaX * 0.28, -180, 180);
      view.pitch = clamp(view.pitch + deltaY * 0.28, -75, 75);
      render();
      updateUrl();
    });
    const releasePointer = (event) => {
      pointers.delete(event.pointerId);
      if (pointers.size < 2) pinch = null;
      if (pointers.size === 0) container.classList.remove('is-dragging');
    };
    container.addEventListener('pointerup', releasePointer);
    container.addEventListener('pointercancel', releasePointer);
    container.addEventListener('wheel', (event) => {
      event.preventDefault();
      view.zoom = clamp(view.zoom * Math.exp(-event.deltaY * 0.001), 0.8, 1.7);
      render();
      updateUrl();
    }, { passive: false });

    const searchRoot = controlsRoot.querySelector('[data-moon-search]');
    const searchInput = searchRoot?.querySelector('[data-moon-search-input]');
    const searchResults = searchRoot?.querySelector('[data-moon-search-results]');
    const searchStatus = searchRoot?.querySelector('[data-moon-search-status]');
    const detailRoot = document.querySelector('[data-moon-object-detail]');
    const defaultSummary = document.querySelector('[data-moon-default-summary]');

    const renderObjectDetail = (object, enrichment, automatic, sourceCatalogs) => {
      if (!detailRoot) return;
      detailRoot.replaceChildren();
      const heading = document.createElement('h2');
      heading.textContent = object.name;
      const type = document.createElement('p');
      type.className = 'interactive-moon-object-type';
      type.textContent = localizedText(object.type);
      const actions = document.createElement('p');
      actions.className = 'interactive-moon-object-actions';
      const back = document.createElement('button');
      back.type = 'button';
      back.textContent = '← Volver a la vista anterior';
      actions.append(back);
      detailRoot.append(heading, type, actions);

      const finishDetail = () => {
        defaultSummary?.setAttribute('hidden', '');
        detailRoot.removeAttribute('hidden');
        back.addEventListener('click', () => {
          if (previousView) {
            view.yaw = previousView.yaw;
            view.pitch = previousView.pitch;
            view.zoom = previousView.zoom;
            focusQuaternion.copy(previousView.focusQuaternion);
          } else focusQuaternion.identity();
          selectedObject = null;
          selectedFeature = null;
          previousView = null;
          detailRoot.setAttribute('hidden', '');
          defaultSummary?.removeAttribute('hidden');
          render();
          updateUrl();
        });
      };

      if (object.landing) {
        const landing = object.landing;
        type.textContent = `${landing.agency} · ${landing.country}`;
        const landingSection = document.createElement('section');
        landingSection.className = 'interactive-moon-object-section interactive-moon-landing-detail';
        const landingDate = new Date(`${landing.landing_date}T00:00:00Z`);
        landingSection.innerHTML = `<h3>Alunizaje</h3><dl>
          <div><dt>Fecha</dt><dd>${escapeHtml(publicDate.format(landingDate))}</dd></div>
          <div><dt>Región</dt><dd>${escapeHtml(landing.region)}</dd></div>
          <div><dt>Coordenadas</dt><dd>${cardinalCoordinate(landing.latitude, 'N', 'S')} · ${cardinalCoordinate(landing.longitude, 'E', 'O')}</dd></div>
          <div><dt>Tipo de misión</dt><dd>${escapeHtml((landing.mission_types || []).join(' · '))}</dd></div>
        </dl><p class="interactive-moon-landing-description">${escapeHtml(landing.description)}</p>`;
        detailRoot.append(landingSection);
        const landingSources = document.createElement('section');
        landingSources.className = 'interactive-moon-object-section';
        const sourceTitle = document.createElement('h3');
        sourceTitle.textContent = 'Fuentes';
        const sourceList = document.createElement('ul');
        sourceList.className = 'interactive-moon-sources';
        (landing.sources || []).forEach((sourceId) => {
          const source = payload.landings?.sources?.[sourceId];
          if (!source?.url || !source?.title) return;
          const item = document.createElement('li');
          const link = document.createElement('a');
          link.href = source.url;
          link.target = '_blank';
          link.rel = 'external noopener';
          link.textContent = source.title;
          item.append(link);
          sourceList.append(item);
        });
        if (sourceList.children.length) {
          landingSources.append(sourceTitle, sourceList);
          detailRoot.append(landingSources);
        }
        finishDetail();
        return;
      }

      const basic = document.createElement('section');
      basic.className = 'interactive-moon-object-section';
      basic.innerHTML = `<h3>Datos básicos</h3><dl>
        <div><dt>Coordenadas</dt><dd>${publicNumber.format(Number(object.latitude))}° lat · ${publicNumber.format(Number(object.longitude))}° lon E</dd></div>
        ${object.diameter_km !== null && object.diameter_km !== undefined ? `<div><dt>Diámetro / dimensión</dt><dd>${publicNumber.format(Number(object.diameter_km))} km</dd></div>` : ''}
        ${object.approval_date ? `<div><dt>Aprobación</dt><dd>${escapeHtml(localizedText(object.approval_status || ''))} · ${escapeHtml(object.approval_date)}</dd></div>` : ''}
        ${object.reference ? `<div><dt>Referencia IAU</dt><dd>${escapeHtml(object.reference)}</dd></div>` : ''}
      </dl>`;
      if (object.official_url) {
        const source = document.createElement('p');
        const link = document.createElement('a');
        link.href = object.official_url;
        link.target = '_blank';
        link.rel = 'external noopener';
        link.textContent = 'Ficha oficial IAU/USGS';
        source.append(link);
        basic.append(source);
      }
      detailRoot.append(basic);

      if (automatic) {
        const automaticBlocks = [
          ['Contexto geológico regional', automatic.regional_geology],
          ['Cronología de catálogo', automatic.chronology],
          ['Topografía de catálogo', automatic.topography],
          ['Morfología de catálogo', automatic.morphology],
        ];
        automaticBlocks.forEach(([titleText, block]) => {
          if (!block) return;
          const section = document.createElement('section');
          section.className = 'interactive-moon-object-section';
          const title = document.createElement('h3');
          title.textContent = titleText;
          if (block.evidence) {
            const badge = document.createElement('span');
            badge.className = 'interactive-moon-evidence';
            badge.textContent = localizedText(block.evidence);
            title.append(badge);
          }
          const list = document.createElement('ul');
          Object.entries(localizedScientific(block)).forEach(([key, value]) => appendScientificValue(list, key, value));
          section.append(title, list);
          detailRoot.append(section);
        });
      }

      if (enrichment) {
        const mission = enrichment.identity?.mission ? {
          mission: enrichment.identity.mission,
          landing_date: enrichment.identity.landing_date,
          site_name: enrichment.identity.site_name,
          findings: enrichment.mission_findings,
        } : enrichment.mission_findings;
        const blocks = [
          ['Geología', enrichment.geology], ['Topografía', enrichment.topography],
          ['Morfología y rasgos', enrichment.morphology], ['Muestras y misión', mission],
          ['Limitaciones', enrichment.limitations],
        ];
        blocks.forEach(([titleText, block]) => {
          if (block === null || block === undefined || (Array.isArray(block) && !block.length)) return;
          const section = document.createElement('section');
          section.className = 'interactive-moon-object-section';
          const title = document.createElement('h3');
          title.textContent = titleText;
          const list = document.createElement('ul');
          const localizedBlock = localizedScientific(block);
          if (Array.isArray(localizedBlock)) localizedBlock.forEach((entry) => appendScientificValue(list, 'value', entry));
          else Object.entries(localizedBlock).forEach(([key, value]) => appendScientificValue(list, key, value));
          section.append(title, list);
          detailRoot.append(section);
        });
      }
      const sourceIds = collectSourceIds(enrichment);
      collectSourceIds(automatic, sourceIds);
      if (sourceIds.size) {
          const section = document.createElement('section');
          section.className = 'interactive-moon-object-section';
          const title = document.createElement('h3');
          title.textContent = 'Fuentes científicas';
          const list = document.createElement('ul');
          list.className = 'interactive-moon-sources';
          sourceIds.forEach((sourceId) => {
            const source = sourceCatalogs.find((catalog) => catalog?.sources?.[sourceId])?.sources?.[sourceId];
            if (!source) return;
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = source.url;
            link.target = '_blank';
            link.rel = 'external noopener';
            link.textContent = scientificPresentation.sources?.[sourceId] || source.title;
            item.append(link);
            list.append(item);
          });
          section.append(title, list);
          detailRoot.append(section);
      }
      finishDetail();
    };

    const selectObject = (object, enrichment, automatic, sourceCatalogs, matchingFeature = null) => {
      if (!previousView) previousView = {
        yaw: view.yaw, pitch: view.pitch, zoom: view.zoom, focusQuaternion: focusQuaternion.clone(),
      };
      selectedObject = object;
      selectedFeature = matchingFeature;
      const target = bodyVector(Number(object.longitude), Number(object.latitude), 1)
        .applyAxisAngle(axisY, longitudeRotation)
        .applyAxisAngle(axisX, latitudeRotation)
        .applyAxisAngle(axisZ, diskRotation)
        .normalize();
      focusQuaternion.setFromUnitVectors(target, axisZ);
      view.yaw = 0;
      view.pitch = 0;
      const size = Number(object.diameter_km);
      view.zoom = !Number.isFinite(size) ? 1.45 : size < 25 ? 1.7 : size < 120 ? 1.55 : size < 500 ? 1.4 : 1.25;
      renderObjectDetail(object, enrichment, automatic, sourceCatalogs);
      render();
      updateUrl();
      detailRoot?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    if (searchInput && searchResults && payload.catalogs?.gazetteer && payload.catalogs?.geology && payload.catalogs?.geology_auto && payload.catalogs?.geology_presentation) {
      Promise.all([
        gazetteerPromise,
        fetch(payload.catalogs.geology).then((response) => { if (!response.ok) throw new Error('Geología no disponible'); return response.json(); }),
        fetch(payload.catalogs.geology_auto).then((response) => { if (!response.ok) throw new Error('Geología automática no disponible'); return response.json(); }),
        fetch(payload.catalogs.geology_presentation).then((response) => { if (!response.ok) throw new Error('Presentación científica no disponible'); return response.json(); }),
      ]).then(([gazetteer, geology, geologyAuto, presentation]) => {
        scientificPresentation = presentation;
        const landings = (payload.landings?.landings || []).map((landing) => ({
          id: landing.id,
          name: landing.name,
          normalized_name: normalizeSearchText(landing.name),
          search_text: normalizeSearchText([landing.name, ...(landing.aliases || [])].join(' ')),
          type: 'Alunizaje', latitude: Number(landing.latitude), longitude: Number(landing.longitude), diameter_km: null,
          approval_status: null, approval_date: null, origin: null, official_url: null, landing,
        }));
        const objects = [...(gazetteer.features || []), ...landings];
        const objectsByIauId = new Map((gazetteer.features || []).map((object) => [Number(object.iau_feature_id), object]));
        const objectsById = new Map(objects.map((object) => [object.id, object]));
        installGazetteerFeatures(gazetteer);
        const objectForFeature = (feature) => {
          if (feature.landing_id) {
            const landing = objectsById.get(feature.landing_id);
            if (landing) return landing;
          }
          if (feature.gazetteer_object) return feature.gazetteer_object;
          const sourceId = Number(feature.source_id);
          if (Number.isFinite(sourceId)) {
            const official = objectsByIauId.get(sourceId);
            if (official) return official;
          }
          const featureName = normalizeSearchText(feature.name).replace(/^apolo /, 'apollo ');
          return objects.find((object) => normalizeSearchText(object.name).replace(/^apolo /, 'apollo ') === featureName
            && Math.abs(Number(object.latitude) - Number(feature.lat)) < 0.1
            && Math.abs(Number(object.longitude) - Number(feature.lon)) < 0.1) || null;
        };
        const featureBySourceId = new Map([...features, ...maximumDetailFeatures]
          .map((feature) => [Number(feature.source_id), feature]).filter(([sourceId]) => Number.isFinite(sourceId)));
        const featureForObject = (object) => featureBySourceId.get(Number(object.iau_feature_id))
          || features.find((feature) => objectForFeature(feature)?.id === object.id) || null;
        const enrichedByIau = new Map();
        const enrichedByName = new Map();
        (geology.objects || []).forEach((entry) => {
          const iauId = entry.identity?.iau_feature_id ?? entry.identity?.associated_iau_feature?.id;
          if (iauId) enrichedByIau.set(Number(iauId), entry);
          enrichedByName.set(normalizeSearchText(entry.name), entry);
        });
        const enrichmentFor = (object) => enrichedByIau.get(Number(object.iau_feature_id))
          || enrichedByName.get(normalizeSearchText(object.name))
          || enrichedByName.get(normalizeSearchText(object.name).replace(/^apolo /, 'apollo '));
        if (searchStatus) searchStatus.textContent = `${objects.length.toLocaleString('es-AR')} objetos buscables`;

        const choose = (object) => {
          searchInput.value = object.name;
          searchResults.hidden = true;
          searchInput.setAttribute('aria-expanded', 'false');
          selectObject(object, enrichmentFor(object), geologyAuto.objects?.[object.id], [geology, geologyAuto], featureForObject(object));
        };
        features.forEach((feature) => {
          const object = objectForFeature(feature);
          if (object) feature.choose = () => choose(object);
        });
        maximumDetailFeatures.forEach((feature) => {
          const object = objectForFeature(feature);
          if (object) feature.choose = () => choose(object);
        });
        render();
        const showResults = () => {
          const term = normalizeSearchText(searchInput.value);
          searchResults.replaceChildren();
          if (term.length < 2) {
            searchResults.hidden = true;
            searchInput.setAttribute('aria-expanded', 'false');
            return;
          }
          const matches = objects.filter((object) => (object.search_text || object.normalized_name).includes(term))
            .sort((a, b) => Number(!a.normalized_name.startsWith(term)) - Number(!b.normalized_name.startsWith(term))
              || a.name.localeCompare(b.name, 'es')).slice(0, 12);
          matches.forEach((object) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'interactive-moon-search-result';
            button.setAttribute('role', 'option');
            const name = document.createElement('b');
            name.textContent = object.name;
            const type = document.createElement('span');
            type.textContent = localizedText(object.type);
            button.append(name, type);
            button.addEventListener('click', () => choose(object));
            searchResults.append(button);
          });
          if (!matches.length) {
            const empty = document.createElement('p');
            empty.className = 'interactive-moon-search-empty';
            empty.textContent = 'No se encontraron coincidencias.';
            searchResults.append(empty);
          }
          searchResults.hidden = false;
          searchInput.setAttribute('aria-expanded', 'true');
        };
        searchInput.addEventListener('input', showResults);
        searchInput.addEventListener('keydown', (event) => {
          if (event.key === 'Enter') {
            const first = searchResults.querySelector('button');
            if (first) { event.preventDefault(); first.click(); }
          } else if (event.key === 'Escape') {
            searchResults.hidden = true;
            searchInput.setAttribute('aria-expanded', 'false');
          }
        });
        document.addEventListener('pointerdown', (event) => {
          if (!searchRoot?.contains(event.target)) searchResults.hidden = true;
        });
        const requestedId = query.get('feature');
        const requested = requestedId ? objects.find((object) => object.id === requestedId) : null;
        if (requested) choose(requested);
      }).catch((error) => {
        console.error('Luna interactiva: no se pudo cargar el catálogo de búsqueda.', error);
        if (searchStatus) searchStatus.textContent = 'El catálogo de búsqueda no está disponible.';
        searchInput.disabled = true;
      });
    }

    const resizeObserver = new ResizeObserver(render);
    resizeObserver.observe(container);
    renderer.domElement.addEventListener('webglcontextlost', () => {
      resizeObserver.disconnect();
      container.dataset.moonState = 'fallback';
      container.classList.remove('is-ready');
    }, { once: true });
  } catch (error) {
    renderer.dispose();
    container.dataset.moonState = 'fallback';
    console.error('Luna interactiva: no se pudo completar el render.', error);
  }
}

document.querySelectorAll('[data-interactive-moon]').forEach((container) => mountInteractiveMoon(container));
