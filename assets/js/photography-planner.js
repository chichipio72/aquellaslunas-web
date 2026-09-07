(() => {
  document.querySelectorAll('[data-field-help]').forEach((help) => {
    const trigger = help.querySelector('[data-field-help-trigger]');
    const tooltip = help.querySelector('[data-field-help-tooltip]');
    if (!(trigger instanceof HTMLButtonElement) || !(tooltip instanceof HTMLElement)) return;
    let pinned = false;
    const show = () => { tooltip.hidden = false; trigger.setAttribute('aria-expanded', 'true'); };
    const hide = () => { tooltip.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
    help.addEventListener('mouseenter', show);
    help.addEventListener('mouseleave', () => { if (!pinned && document.activeElement !== trigger) hide(); });
    trigger.addEventListener('focus', show);
    trigger.addEventListener('blur', () => { if (!pinned) hide(); });
    trigger.addEventListener('click', () => { pinned = !pinned; pinned ? show() : hide(); });
    trigger.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      pinned = false;
      hide();
      event.stopPropagation();
    });
    document.addEventListener('pointerdown', (event) => {
      if (!pinned || help.contains(event.target)) return;
      pinned = false;
      hide();
    });
  });

  const initialUrl = new URL(window.location.href);
  try {
    const saved = JSON.parse(localStorage.getItem('photography.equipment') || 'null');
    const sceneEntry = !initialUrl.searchParams.has('orientation') && (initialUrl.searchParams.has('scene') || initialUrl.searchParams.has('event_type'));
    let restoreUrl = false;
    if (sceneEntry && !initialUrl.searchParams.has('preferred_orientation') && ['horizontal', 'vertical'].includes(saved?.orientation)) {
      initialUrl.searchParams.set('preferred_orientation', saved.orientation);
      restoreUrl = true;
    }
    if (!initialUrl.searchParams.has('sensor') && saved?.sensor && saved?.focal && saved?.orientation) {
      Object.entries(saved).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '' || (sceneEntry && key === 'orientation')) return;
        initialUrl.searchParams.set(key, value);
      });
      restoreUrl = true;
    }
    if (restoreUrl) { window.location.replace(initialUrl.toString()); return; }
  } catch (_) {}

  const payload = window.photographyScene;
  const frame = document.querySelector('[data-photography-frame]');
  const form = document.querySelector('[data-photography-form]');
  if (!payload || !frame || !form) return;

  document.documentElement.classList.add('photography-auto-update');
  const siteHeader = document.querySelector('.site-header');
  const updateStickyHeaderOffset = () => {
    const height = siteHeader?.getBoundingClientRect().height || 0;
    document.documentElement.style.setProperty('--photography-sticky-top', `${Math.ceil(height + 6)}px`);
  };
  updateStickyHeaderOffset();
  if (siteHeader && 'ResizeObserver' in window) new ResizeObserver(updateStickyHeaderOffset).observe(siteHeader);
  window.addEventListener('resize', updateStickyHeaderOffset, { passive: true });
  try {
    const restore = JSON.parse(sessionStorage.getItem('photography.restoreScroll') || 'null');
    sessionStorage.removeItem('photography.restoreScroll');
    if (restore?.path === window.location.pathname && Date.now() - Number(restore.savedAt) < 10000) {
      window.requestAnimationFrame(() => window.requestAnimationFrame(() => window.scrollTo({ top: Number(restore.y) || 0, behavior: 'auto' })));
    }
  } catch (_) {}

  const focalInput = form.querySelector('[data-focal]');
  const focalSlider = form.querySelector('[data-focal-slider]');
  const timeOffsetInput = form.querySelector('[data-time-offset]');
  const timeOffsetOutput = form.querySelector('[data-time-offset-value]');
  const effectiveTimeOutput = form.querySelector('[data-effective-time]');
  const aimInput = form.querySelector('[data-framing-aim]');
  const offsetXInput = form.querySelector('[data-offset-x]');
  const offsetYInput = form.querySelector('[data-offset-y]');
  const rollInput = form.querySelector('[data-camera-roll]');
  const rollOutput = form.querySelector('[data-camera-roll-value]');
  const rollNotice = form.querySelector('[data-camera-roll-notice]');
  const resetFrameButton = form.querySelector('[data-reset-frame]');
  const orientationAutoInput = form.querySelector('[data-orientation-auto]');
  const centeringToggle = document.querySelector('[data-centering-toggle]');
  const centeringStatus = document.querySelector('[data-centering-status]');
  const displayModeInput = form.querySelector('[data-display-mode-value]');
  const displayModeButtons = [...document.querySelectorAll('[data-display-mode]')];
  const horizonInput = form.querySelector('[data-horizon]');
  const fovOutput = document.querySelector('[data-field-of-view]');
  const names = new Map(payload.astronomy.objects.map((object) => [object.id, object.name]));
  names.set('moon', 'Luna'); names.set('horizon', 'Horizonte');

  const initialFocal = Number(focalInput.value);
  const initialFov = payload.framing.field_of_view;
  const sensorWidth = 2 * initialFocal * Math.tan(initialFov.horizontal_degrees * Math.PI / 360);
  const sensorHeight = 2 * initialFocal * Math.tan(initialFov.vertical_degrees * Math.PI / 360);
  const state = {
    focal: initialFocal,
    aim: payload.framing.aim,
    automaticCenter: { x: payload.framing.automatic_center.x_degrees, y: payload.framing.automatic_center.y_degrees },
    center: { x: payload.framing.center.x_degrees, y: payload.framing.center.y_degrees },
    offset: { x: payload.framing.manual_offset.x_degrees, y: payload.framing.manual_offset.y_degrees },
    roll: Number(payload.framing.roll_degrees) || 0,
    displayMode: payload.display_mode === 'simulated' ? 'simulated' : 'scheme',
    centering: centeringToggle?.checked !== false,
    fov: { horizontal: initialFov.horizontal_degrees, vertical: initialFov.vertical_degrees },
  };
  let moonThreeRequested = false;
  const ensureMoonThree = () => {
    if (state.displayMode !== 'simulated' || moonThreeRequested) return;
    moonThreeRequested = true;
    import(payload.simulation_moon?.renderer_url || './photography-moon-three.js').catch((error) => {
      moonThreeRequested = false;
      console.error('Fotografía: no se pudo cargar el renderer lunar 3D.', error);
    });
  };
  const initialCamera = {
    focal: initialFocal,
    aim: state.aim === 'manual'
      ? (payload.include_horizon || payload.astronomy.objects.some((object) => object.selected) ? 'automatic' : 'moon')
      : state.aim,
  };
  const sceneAutomaticOrientation = payload.orientation_source === 'scene_auto';
  let orientationManuallyChanged = false;
  const focalToSlider = (focal) => Math.log(Math.max(1, Math.min(3000, focal))) / Math.log(3000) * 1000;
  const sliderToFocal = (position) => Math.exp(Math.max(0, Math.min(1000, position)) / 1000 * Math.log(3000));
  const fieldOfView = (focal) => ({
    horizontal: Math.atan(sensorWidth / (2 * focal)) * 360 / Math.PI,
    vertical: Math.atan(sensorHeight / (2 * focal)) * 360 / Math.PI,
  });
  const clamp = (value, minimum = 0, maximum = 1) => Math.max(minimum, Math.min(maximum, value));
  const smoothstep = (edge0, edge1, value) => {
    const normalized = clamp((value - edge0) / Math.max(1e-9, edge1 - edge0));
    return normalized * normalized * (3 - 2 * normalized);
  };
  const simulatedStarSolarVisibility = (solarAltitude) => {
    if (solarAltitude >= -3) return 0;
    if (solarAltitude >= -6) return .08 * (1 - smoothstep(-6, -3, solarAltitude));
    if (solarAltitude >= -12) return .08 + .92 * (1 - smoothstep(-12, -6, solarAltitude));
    return 1;
  };
  const simulatedPlanetSolarVisibility = (solarAltitude) => {
    if (solarAltitude >= 0) return 0;
    if (solarAltitude >= -3) return .18 * (1 - smoothstep(-3, 0, solarAltitude));
    if (solarAltitude >= -8) return .18 + .82 * (1 - smoothstep(-8, -3, solarAltitude));
    return 1;
  };
  const simulatedObjectVisibility = (object) => {
    const solarAltitude = Number(payload.astronomy.sun.altitude_degrees);
    const solarWeight = object.kind === 'planet'
      ? simulatedPlanetSolarVisibility(solarAltitude)
      : simulatedStarSolarVisibility(solarAltitude);
    const nocturnalGain = Math.max(0, Number(payload.simulation.star_visibility) || 0);
    // Punto único para incorporar magnitud aparente sin cambiar las curvas solares globales.
    const apparentMagnitudeGain = 1;
    return clamp(solarWeight * nocturnalGain * apparentMagnitudeGain);
  };
  const smootherstep = (edge0, edge1, value) => {
    const normalized = clamp((value - edge0) / Math.max(1e-9, edge1 - edge0));
    return normalized ** 3 * (normalized * (normalized * 6 - 15) + 10);
  };
  const relativeDirectionUnit = (x, y) => {
    const radius = Math.hypot(x, y); const radians = radius * Math.PI / 180;
    if (radius < 1e-9) return [0, 0, 1];
    const tangentScale = Math.sin(radians) / radius;
    return [x * tangentScale, -y * tangentScale, Math.cos(radians)];
  };
  const relativeAngularSeparation = (firstX, firstY, secondX, secondY) => {
    const first = relativeDirectionUnit(firstX, firstY); const second = relativeDirectionUnit(secondX, secondY);
    const cosine = clamp(first[0] * second[0] + first[1] * second[1] + first[2] * second[2], -1, 1);
    return Math.acos(cosine) * 180 / Math.PI;
  };
  const colorChannels = (color) => [1, 3, 5].map((index) => Number.parseInt(String(color).slice(index, index + 2), 16));
  const mixColor = (first, second, amount) => {
    const a = colorChannels(first); const b = colorChannels(second); const weight = clamp(amount);
    return `#${a.map((channel, index) => Math.round(channel + (b[index] - channel) * weight).toString(16).padStart(2, '0')).join('')}`;
  };
  const simulatedSky = () => {
    const config = payload.simulation;
    const solarAltitude = Number(payload.astronomy.sun.altitude_degrees);
    const nightAltitude = Number(config.night_transition_altitude);
    const dayAltitude = Number(config.day_transition_altitude);
    const twilightCenter = Math.max(nightAltitude + .001, Math.min(dayAltitude - .001, Number(config.twilight_center_altitude)));
    const center = centerForAim();
    const centerSolarSeparation = relativeAngularSeparation(
      center.x, center.y, Number(payload.astronomy.sun.relative_x_degrees), Number(payload.astronomy.sun.relative_y_degrees),
    );
    const directionWeight = smootherstep(0, 180, centerSolarSeparation);
    const twilightHorizon = mixColor(config.twilight_horizon, config.twilight_horizon_antisolar, directionWeight);
    const mixBand = (night, twilight, day) => solarAltitude <= twilightCenter
      ? mixColor(night, twilight, smoothstep(nightAltitude, twilightCenter, solarAltitude))
      : mixColor(twilight, day, smoothstep(twilightCenter, dayAltitude, solarAltitude));
    return {
      zenith: mixBand(config.night_zenith, config.twilight_zenith, config.day_zenith),
      horizon: mixBand(config.night_horizon, twilightHorizon, config.day_horizon),
      brightness: smoothstep(nightAltitude, dayAltitude, solarAltitude),
      directionStrength: Number(config.sun_direction_influence) * smoothstep(nightAltitude, dayAltitude, solarAltitude),
      horizonIntensity: Number(config.horizon_intensity),
    };
  };
  const moonProfile = (sky, height) => {
    const prefix = `moon_${sky}_${height}_`;
    return {
      lit_color: String(payload.simulation[`${prefix}lit_color`] || '#eaf3ff'),
      lit_brightness: Number(payload.simulation[`${prefix}lit_brightness`]),
      dark_brightness: Number(payload.simulation[`${prefix}dark_brightness`]),
      dark_sky_mix: Number(payload.simulation[`${prefix}dark_sky_mix`]),
      contrast: Number(payload.simulation[`${prefix}contrast`]),
      texture_visibility: Number(payload.simulation[`${prefix}texture_visibility`]),
      lit_sky_mix: Number(payload.simulation[`${prefix}lit_sky_mix`]),
      terminator_detail: Number(payload.simulation[`${prefix}terminator_detail`]),
      limb_softness: Number(payload.simulation[`${prefix}limb_softness`]),
    };
  };
  const mixMoonAppearance = (first, second, weight) => ({
    lit_color: mixColor(first.lit_color, second.lit_color, weight),
    lit_brightness: first.lit_brightness + (second.lit_brightness - first.lit_brightness) * weight,
    dark_brightness: first.dark_brightness + (second.dark_brightness - first.dark_brightness) * weight,
    dark_sky_mix: first.dark_sky_mix + (second.dark_sky_mix - first.dark_sky_mix) * weight,
    contrast: first.contrast + (second.contrast - first.contrast) * weight,
    texture_visibility: first.texture_visibility + (second.texture_visibility - first.texture_visibility) * weight,
    lit_sky_mix: first.lit_sky_mix + (second.lit_sky_mix - first.lit_sky_mix) * weight,
    terminator_detail: first.terminator_detail + (second.terminator_detail - first.terminator_detail) * weight,
    limb_softness: first.limb_softness + (second.limb_softness - first.limb_softness) * weight,
  });
  const simulatedMoonAppearance = () => {
    const solarAltitude = Number(payload.astronomy.sun.altitude_degrees);
    const lunarAltitude = Number(payload.astronomy.moon.altitude_degrees);
    const nightAltitude = Number(payload.simulation.night_transition_altitude);
    const dayAltitude = Number(payload.simulation.day_transition_altitude);
    const twilightCenter = Math.max(nightAltitude + .001, Math.min(dayAltitude - .001, Number(payload.simulation.twilight_center_altitude)));
    const heightStart = Number(payload.simulation.moon_low_to_high_start_altitude);
    const heightEnd = Math.max(heightStart + .001, Number(payload.simulation.moon_low_to_high_end_altitude));
    const heightWeight = smoothstep(heightStart, heightEnd, lunarAltitude);
    const directionWeight = smootherstep(0, 180, Number(payload.astronomy.sun.separation_from_moon_degrees));
    const profiles = {};
    ['night', 'day'].forEach((sky) => {
      profiles[sky] = mixMoonAppearance(moonProfile(sky, 'low'), moonProfile(sky, 'high'), heightWeight);
    });
    const twilightLow = mixMoonAppearance(moonProfile('twilight', 'low'), moonProfile('twilight', 'low_antisolar'), directionWeight);
    profiles.twilight = mixMoonAppearance(twilightLow, moonProfile('twilight', 'high'), heightWeight);
    const appearance = solarAltitude <= twilightCenter
      ? mixMoonAppearance(profiles.night, profiles.twilight, smoothstep(nightAltitude, twilightCenter, solarAltitude))
      : mixMoonAppearance(profiles.twilight, profiles.day, smoothstep(twilightCenter, dayAltitude, solarAltitude));
    const horizonAltitude = Number(payload.simulation.atmosphere_horizon_altitude);
    const clearAltitude = Math.max(horizonAltitude + 0.5, Number(payload.simulation.atmosphere_clear_altitude));
    const atmosphereStrength = Math.pow(1 - smoothstep(horizonAltitude, clearAltitude, lunarAltitude), 1.35);
    const contextWarmth = solarAltitude <= twilightCenter
      ? Number(payload.simulation.atmosphere_warmth_night) + (Number(payload.simulation.atmosphere_warmth_twilight) - Number(payload.simulation.atmosphere_warmth_night)) * smoothstep(nightAltitude, twilightCenter, solarAltitude)
      : Number(payload.simulation.atmosphere_warmth_twilight) + (Number(payload.simulation.atmosphere_warmth_day) - Number(payload.simulation.atmosphere_warmth_twilight)) * smoothstep(twilightCenter, dayAltitude, solarAltitude);
    const extinction = atmosphereStrength * Number(payload.simulation.atmosphere_extinction_strength);
    const contrastLoss = atmosphereStrength * Number(payload.simulation.atmosphere_contrast_loss);
    const detailLoss = atmosphereStrength * Number(payload.simulation.atmosphere_detail_loss);
    const haze = atmosphereStrength * Number(payload.simulation.atmosphere_haze_integration);
    appearance.lit_brightness *= 1 - extinction;
    appearance.contrast *= 1 - contrastLoss;
    appearance.texture_visibility *= 1 - detailLoss;
    appearance.lit_sky_mix += (0.8 - appearance.lit_sky_mix) * haze;
    appearance.dark_sky_mix += (1 - appearance.dark_sky_mix) * haze;
    appearance.limb_softness = Math.min(0.15, appearance.limb_softness + haze * 0.04);
    appearance.lit_color = mixColor(appearance.lit_color, payload.simulation.atmosphere_warm_color, atmosphereStrength * contextWarmth);
    return appearance;
  };
  const centerForAim = () => {
    if (state.aim === 'moon') return { x: 0, y: 0 };
    if (state.aim === 'manual') return { x: state.automaticCenter.x + state.offset.x, y: state.automaticCenter.y + state.offset.y };
    return { ...state.automaticCenter };
  };
  const cameraOffset = (x, y) => {
    const angle = state.roll * Math.PI / 180; const cosine = Math.cos(angle); const sine = Math.sin(angle);
    return { x: cosine * x + sine * y, y: -sine * x + cosine * y };
  };
  const skyOffset = (x, y) => {
    const angle = state.roll * Math.PI / 180; const cosine = Math.cos(angle); const sine = Math.sin(angle);
    return { x: cosine * x - sine * y, y: sine * x + cosine * y };
  };
  const horizonIntersectsFrame = (horizonY) => {
    const halfWidth = state.fov.horizontal / 2; const halfHeight = state.fov.vertical / 2;
    const angle = state.roll * Math.PI / 180; const sine = Math.sin(angle); const cosine = Math.cos(angle);
    const relativeY = horizonY - state.center.y;
    if (Math.abs(cosine) < 1e-9) return Math.abs(sine) > 1e-9 && Math.abs(relativeY / sine) <= halfWidth;
    const firstY = (relativeY - sine * -halfWidth) / cosine; const secondY = (relativeY - sine * halfWidth) / cosine;
    return Math.min(firstY, secondY) <= halfHeight && Math.max(firstY, secondY) >= -halfHeight;
  };
  const currentPoints = () => payload.framing.points.map((point) => {
    const offset = cameraOffset(point.x - state.center.x, point.y - state.center.y); const dx = offset.x; const dy = offset.y;
    const halfWidth = state.fov.horizontal / 2; const halfHeight = state.fov.vertical / 2;
    if (point.id === 'horizon') {
      const visible = horizonIntersectsFrame(point.y);
      const outsideX = Math.max(0, Math.abs(dx) - halfWidth); const outsideY = Math.max(0, Math.abs(dy) - halfHeight);
      return { ...point, camera_x: dx, camera_y: dy, frame_visibility: visible ? 'partial' : 'outside', in_frame: visible, outside_by_degrees: visible ? 0 : Math.hypot(outsideX, outsideY) };
    }
    const radius = point.id === 'moon' ? Math.max(0, Number(point.diameter) || 0) / 2 : 0;
    const fullyInside = Math.abs(dx) + radius <= halfWidth && Math.abs(dy) + radius <= halfHeight;
    const visible = Math.abs(dx) - radius <= halfWidth && Math.abs(dy) - radius <= halfHeight;
    const outsideX = Math.max(0, Math.abs(dx) - radius - halfWidth); const outsideY = Math.max(0, Math.abs(dy) - radius - halfHeight);
    return { ...point, camera_x: dx, camera_y: dy, frame_visibility: fullyInside ? 'inside' : (visible ? 'partial' : 'outside'), in_frame: visible, outside_by_degrees: Math.hypot(outsideX, outsideY) };
  });
  const project = (x, y) => { const offset = cameraOffset(x - state.center.x, y - state.center.y); return { x: 50 + offset.x / state.fov.horizontal * 100, y: 50 + offset.y / state.fov.vertical * 100 }; };
  const arrowFor = (x, y) => {
    if (Math.abs(x) > Math.abs(y) * 1.5) return x < 0 ? '←' : '→';
    if (Math.abs(y) > Math.abs(x) * 1.5) return y < 0 ? '↑' : '↓';
    return y < 0 ? (x < 0 ? '↖' : '↗') : (x < 0 ? '↙' : '↘');
  };
  const syncState = (replaceUrl = true) => {
    focalInput.value = state.focal.toFixed(1).replace(/\.0$/, '');
    if (focalSlider) {
      focalSlider.value = String(Math.round(focalToSlider(state.focal)));
      focalSlider.setAttribute('aria-valuetext', `${focalInput.value} mm`);
    }
    aimInput.value = state.aim;
    offsetXInput.value = state.offset.x.toFixed(6).replace(/\.?0+$/, '');
    offsetYInput.value = state.offset.y.toFixed(6).replace(/\.?0+$/, '');
    rollInput.value = String(state.roll);
    rollOutput.textContent = `${state.roll > 0 ? '+' : ''}${state.roll.toFixed(0)}°`;
    rollNotice.hidden = !horizonInput?.checked || Math.abs(state.roll) < .001;
    displayModeInput.value = state.displayMode;
    frame.dataset.displayMode = state.displayMode;
    frame.setAttribute('aria-label', state.displayMode === 'simulated' ? 'Simulación inicial del encuadre fotográfico' : 'Esquema del encuadre fotográfico');
    displayModeButtons.forEach((button) => {
      const active = button.dataset.displayMode === state.displayMode;
      button.classList.toggle('photography-mode__active', active); button.setAttribute('aria-pressed', String(active));
    });
    fovOutput.textContent = `${state.fov.horizontal.toFixed(2).replace('.', ',')}° × ${state.fov.vertical.toFixed(2).replace('.', ',')}°`;
    if (!replaceUrl) return;
    const url = new URL(window.location.href);
    url.searchParams.set('focal', focalInput.value); url.searchParams.set('aim', state.aim); url.searchParams.set('centering', state.centering ? '1' : '0'); url.searchParams.set('roll', String(state.roll)); url.searchParams.set('mode', state.displayMode);
    if (state.aim === 'manual') {
      url.searchParams.set('offset_x', offsetXInput.value); url.searchParams.set('offset_y', offsetYInput.value);
    } else {
      url.searchParams.delete('offset_x'); url.searchParams.delete('offset_y');
    }
    window.history.replaceState(null, '', url);
  };

  const render = () => {
    frame.replaceChildren();
    const points = currentPoints(); const pointMap = new Map(points.map((point) => [point.id, point]));
    let moonLayout = null;
    let moonSkyColor = '#030711';
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 1000 1000'); svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('preserveAspectRatio', 'none');
    if (state.displayMode === 'simulated') {
      const sky = simulatedSky(); const sun = project(payload.astronomy.sun.relative_x_degrees, payload.astronomy.sun.relative_y_degrees);
      const topSkyOffset = skyOffset(0, -state.fov.vertical / 2); const bottomSkyOffset = skyOffset(0, state.fov.vertical / 2);
      const moonAltitude = Number(payload.astronomy.moon.altitude_degrees);
      const colorAtAltitude = (altitude) => mixColor(sky.zenith, sky.horizon, clamp(Math.exp(-Math.max(0, altitude) / 18) * sky.horizonIntensity));
      const topColor = colorAtAltitude(moonAltitude - (state.center.y + topSkyOffset.y));
      const bottomColor = colorAtAltitude(moonAltitude - (state.center.y + bottomSkyOffset.y));
      const moonBaseColor = colorAtAltitude(moonAltitude);
      const moonPoint = project(0, 0);
      const solarDistance = Math.hypot(moonPoint.x - sun.x, moonPoint.y - sun.y);
      const solarInfluence = clamp(sky.directionStrength * (1 - solarDistance / 105));
      moonSkyColor = mixColor(moonBaseColor, sky.horizon, solarInfluence);
      const definitions = document.createElementNS(svg.namespaceURI, 'defs');
      const vertical = document.createElementNS(svg.namespaceURI, 'linearGradient'); vertical.id = 'photography-sky-vertical'; vertical.setAttribute('x1', '0%'); vertical.setAttribute('y1', '0%'); vertical.setAttribute('x2', '0%'); vertical.setAttribute('y2', '100%');
      const high = document.createElementNS(svg.namespaceURI, 'stop'); high.setAttribute('offset', '0%'); high.setAttribute('stop-color', topColor);
      const low = document.createElementNS(svg.namespaceURI, 'stop'); low.setAttribute('offset', '100%'); low.setAttribute('stop-color', bottomColor);
      vertical.append(high, low);
      const solar = document.createElementNS(svg.namespaceURI, 'radialGradient'); solar.id = 'photography-sky-solar'; solar.setAttribute('cx', `${clamp(sun.x, -80, 180)}%`); solar.setAttribute('cy', `${clamp(sun.y, -80, 180)}%`); solar.setAttribute('r', '105%');
      const glow = document.createElementNS(svg.namespaceURI, 'stop'); glow.setAttribute('offset', '0'); glow.setAttribute('stop-color', sky.horizon); glow.setAttribute('stop-opacity', String(clamp(sky.directionStrength, 0, 1)));
      const clear = document.createElementNS(svg.namespaceURI, 'stop'); clear.setAttribute('offset', '1'); clear.setAttribute('stop-color', sky.horizon); clear.setAttribute('stop-opacity', '0'); solar.append(glow, clear); definitions.append(vertical, solar); svg.append(definitions);
      const skyLayer = document.createElementNS(svg.namespaceURI, 'g'); skyLayer.setAttribute('class', 'photography-simulated-sky');
      const base = document.createElementNS(svg.namespaceURI, 'rect'); base.setAttribute('width', '1000'); base.setAttribute('height', '1000'); base.setAttribute('fill', 'url(#photography-sky-vertical)');
      const direction = document.createElementNS(svg.namespaceURI, 'rect'); direction.setAttribute('width', '1000'); direction.setAttribute('height', '1000'); direction.setAttribute('fill', 'url(#photography-sky-solar)'); skyLayer.append(base, direction); svg.append(skyLayer);
    }
    const sceneLayer = document.createElementNS(svg.namespaceURI, 'g');
    sceneLayer.setAttribute('class', 'photography-scene-layer');
    sceneLayer.setAttribute('data-camera-roll', String(state.roll));
    svg.append(sceneLayer);
    const add = (name, attributes, text) => { const node = document.createElementNS(svg.namespaceURI, name); Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value)); if (text) node.textContent = text; sceneLayer.append(node); return node; };
    const horizonY = Number(payload.astronomy.horizon.relative_y_degrees);
    const horizonExtent = Math.max(state.fov.horizontal, state.fov.vertical) * 4;
    const horizonLeft = project(state.center.x - horizonExtent, horizonY);
    const horizonRight = project(state.center.x + horizonExtent, horizonY);
    const skyRight = project(state.center.x + horizonExtent, horizonY - horizonExtent);
    const skyLeft = project(state.center.x - horizonExtent, horizonY - horizonExtent);
    const visibilityState = String(payload.astronomy.moon.visibility_state || 'below_horizon');
    const visibleSkyPolygon = [horizonLeft, horizonRight, skyRight, skyLeft];
    if (visibilityState === 'partially_visible') {
      const definitions = document.createElementNS(svg.namespaceURI, 'defs');
      const clip = document.createElementNS(svg.namespaceURI, 'clipPath'); clip.id = 'photography-visible-sky'; clip.setAttribute('clipPathUnits', 'userSpaceOnUse');
      const visibleSky = document.createElementNS(svg.namespaceURI, 'polygon');
      visibleSky.setAttribute('points', `${horizonLeft.x * 10},${horizonLeft.y * 10} ${horizonRight.x * 10},${horizonRight.y * 10} ${skyRight.x * 10},${skyRight.y * 10} ${skyLeft.x * 10},${skyLeft.y * 10}`);
      clip.append(visibleSky); definitions.append(clip); svg.insertBefore(definitions, sceneLayer);
    }
    if (payload.include_horizon) {
      const lowerRight = project(state.center.x + horizonExtent, horizonY + horizonExtent); const lowerLeft = project(state.center.x - horizonExtent, horizonY + horizonExtent);
      add('polygon', { points: `${horizonLeft.x * 10},${horizonLeft.y * 10} ${horizonRight.x * 10},${horizonRight.y * 10} ${lowerRight.x * 10},${lowerRight.y * 10} ${lowerLeft.x * 10},${lowerLeft.y * 10}`, class: 'photography-skyline' });
      add('line', { x1: horizonLeft.x * 10, x2: horizonRight.x * 10, y1: horizonLeft.y * 10, y2: horizonRight.y * 10, class: 'photography-horizon' });
    }
    const moonVisibleInCurrentMode = visibilityState !== 'below_horizon';
    if (moonVisibleInCurrentMode) {
      const moon = project(0, 0); const radius = payload.astronomy.moon.angular_diameter_degrees / state.fov.horizontal * 500;
      const radiusY = payload.astronomy.moon.angular_diameter_degrees / state.fov.vertical * 500;
      moonLayout = { xPercent: moon.x, yPercent: moon.y, diameterWidthPercent: radius / 5, diameterHeightPercent: radiusY / 5 };
      let moonParent = sceneLayer;
      if (visibilityState === 'partially_visible') {
        moonParent = document.createElementNS(svg.namespaceURI, 'g'); moonParent.setAttribute('clip-path', 'url(#photography-visible-sky)'); sceneLayer.append(moonParent);
      }
      const moonGroup = document.createElementNS(svg.namespaceURI, 'g');
      moonGroup.setAttribute('transform', `translate(${moon.x * 10} ${moon.y * 10}) scale(1 ${radiusY / Math.max(radius, 1e-9)}) rotate(${-state.roll})`);
      moonGroup.setAttribute('class', `photography-moon${pointMap.get('moon')?.in_frame ? '' : ' is-outside'}`); moonParent.append(moonGroup);
      const outline = document.createElementNS(svg.namespaceURI, 'circle'); outline.setAttribute('r', radius); outline.setAttribute('class', 'photography-moon-outline'); moonGroup.append(outline);
      const fraction = Math.max(0, Math.min(1, payload.astronomy.moon.illumination_fraction));
      const terminatorRadius = Math.abs(1 - 2 * fraction) * radius;
      const illuminated = document.createElementNS(svg.namespaceURI, 'path');
      illuminated.setAttribute('d', `M 0 ${-radius} A ${radius} ${radius} 0 0 1 0 ${radius} A ${terminatorRadius} ${radius} 0 0 ${fraction > 0.5 ? 1 : 0} 0 ${-radius} Z`);
      illuminated.setAttribute('transform', `rotate(${payload.astronomy.moon.bright_limb_angle_degrees - 90})`);
      illuminated.setAttribute('class', 'photography-moon-illuminated'); moonGroup.append(illuminated);
    }
    payload.astronomy.objects.filter((object) => object.selected).forEach((object) => {
      const point = project(object.relative_x_degrees, object.relative_y_degrees);
      const simulated = state.displayMode === 'simulated';
      const magnitude = Number.isFinite(Number(object.apparent_magnitude)) ? Number(object.apparent_magnitude) : 1;
      const visibility = simulated ? simulatedObjectVisibility(object) : 1;
      const radius = simulated ? (object.kind === 'planet' ? 5.5 : clamp(5.5 - magnitude * .55, 2.2, 5)) : 5;
      if (!simulated || visibility >= .01) {
        add('circle', { cx: point.x * 10, cy: point.y * 10, r: radius, opacity: visibility, 'data-simulated-visibility': simulated ? visibility.toFixed(4) : '1', class: `photography-object${simulated ? ` photography-object--${object.kind === 'planet' ? 'planet' : 'star'}` : ''}${pointMap.get(object.id)?.in_frame ? '' : ' is-outside'}` });
        add('text', { x: point.x * 10 + 12, y: point.y * 10 - 10, opacity: visibility, class: 'photography-label' }, object.name);
      }
    });
    frame.append(svg);
    if (state.displayMode === 'simulated' && payload.include_horizon) {
      const foreground = document.createElementNS(svg.namespaceURI, 'svg');
      foreground.setAttribute('viewBox', '0 0 1000 1000'); foreground.setAttribute('preserveAspectRatio', 'none');
      foreground.setAttribute('aria-hidden', 'true'); foreground.setAttribute('class', 'photography-simulated-foreground');
      const lowerRight = project(state.center.x + horizonExtent, horizonY + horizonExtent);
      const lowerLeft = project(state.center.x - horizonExtent, horizonY + horizonExtent);
      const ground = document.createElementNS(svg.namespaceURI, 'polygon');
      ground.setAttribute('points', `${horizonLeft.x * 10},${horizonLeft.y * 10} ${horizonRight.x * 10},${horizonRight.y * 10} ${lowerRight.x * 10},${lowerRight.y * 10} ${lowerLeft.x * 10},${lowerLeft.y * 10}`);
      ground.setAttribute('class', 'photography-skyline'); foreground.append(ground);
      const horizon = document.createElementNS(svg.namespaceURI, 'line');
      horizon.setAttribute('x1', horizonLeft.x * 10); horizon.setAttribute('x2', horizonRight.x * 10);
      horizon.setAttribute('y1', horizonLeft.y * 10); horizon.setAttribute('y2', horizonRight.y * 10);
      horizon.setAttribute('class', 'photography-horizon'); foreground.append(horizon);
      frame.append(foreground);
    }

    const halfWidth = state.fov.horizontal / 2; const halfHeight = state.fov.vertical / 2;
    points.filter((point) => !point.in_frame).forEach((point) => {
      const dx = point.camera_x; const dy = point.camera_y;
      const normalizedX = dx / halfWidth; const normalizedY = dy / halfHeight;
      const edgeScale = Math.max(Math.abs(normalizedX), Math.abs(normalizedY), 1);
      const edgeX = 50 + normalizedX / edgeScale * 50; const edgeY = 50 + normalizedY / edgeScale * 50;
      const indicator = document.createElement('div'); indicator.className = 'photography-offscreen-indicator';
      if (Math.abs(normalizedX) >= Math.abs(normalizedY)) {
        indicator.style.left = normalizedX < 0 ? '1%' : '99%'; indicator.style.top = `${Math.max(18, Math.min(82, edgeY))}%`;
        indicator.style.transform = normalizedX < 0 ? 'translate(0,-50%)' : 'translate(-100%,-50%)';
      } else {
        indicator.style.left = `${Math.max(18, Math.min(82, edgeX))}%`; indicator.style.top = normalizedY < 0 ? '1%' : '99%';
        indicator.style.transform = normalizedY < 0 ? 'translate(-50%,0)' : 'translate(-50%,-100%)';
      }
      const distance = point.outside_by_degrees >= .05 ? ` · ${point.outside_by_degrees.toFixed(1).replace('.', ',')}° más allá` : '';
      indicator.textContent = `${arrowFor(dx, dy)} ${names.get(point.id) || point.id} · fuera del encuadre${distance}`;
      frame.append(indicator);
    });

    const visibleCount = points.filter((point) => point.in_frame).length;
    if (!moonVisibleInCurrentMode) {
      const unavailable = document.createElement('div'); unavailable.className = 'photography-empty-frame';
      const title = document.createElement('strong'); title.textContent = 'La Luna está bajo el horizonte.';
      const detail = document.createElement('span'); detail.textContent = 'No es visible desde esta ubicación en la fecha y hora elegidas.';
      unavailable.append(title, detail); frame.append(unavailable);
    } else if (visibleCount === 0) {
      const empty = document.createElement('div'); empty.className = 'photography-empty-frame';
      const title = document.createElement('strong'); title.textContent = 'La escena seleccionada no entra con esta focal.';
      const detail = document.createElement('span'); detail.textContent = `Para incluirla necesitás aproximadamente ${Math.round(payload.framing.maximum_focal_mm)} mm o menos.`;
      const actions = document.createElement('span'); actions.className = 'photography-empty-frame__actions';
      const adjust = document.createElement('button'); adjust.type = 'button'; adjust.textContent = 'Ajustar focal para incluir la escena';
      adjust.addEventListener('click', () => { state.focal = Math.max(1, payload.framing.suggested_focal_mm); state.fov = fieldOfView(state.focal); syncState(); render(); });
      actions.append(adjust); empty.append(title, detail, actions); frame.append(empty);
    }
    frame.dispatchEvent(new CustomEvent('photography-moon-layout', { detail: {
      mode: state.displayMode,
      visible: state.displayMode === 'simulated' && moonVisibleInCurrentMode && moonLayout !== null,
      visibilityState,
      visibleSkyPolygon: visibilityState === 'partially_visible' ? visibleSkyPolygon : null,
      roll: state.roll,
      layout: moonLayout,
      skyColor: moonSkyColor,
    } }));
  };

  frame.addEventListener('photography-moon-three-ready', render);
  window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin || event.source !== window.parent) return;
    if (event.data?.type === 'photography-simulation-preview') {
      if (!event.data.simulation || typeof event.data.simulation !== 'object') return;
      Object.assign(payload.simulation, event.data.simulation);
      Object.assign(payload.simulation_moon.appearance, simulatedMoonAppearance());
      render();
    } else if (event.data?.type === 'photography-preview-camera') {
      const focal = Number(event.data.focal);
      if (Number.isFinite(focal)) setFocal(focal);
    }
  });

  const setFocal = (value) => {
    state.focal = Math.max(1, Math.min(3000, Math.round(value * 10) / 10));
    state.fov = fieldOfView(state.focal);
    state.center = centerForAim();
    syncState(); render();
  };
  frame.addEventListener('wheel', (event) => {
    event.preventDefault();
    const delta = event.deltaY * (event.deltaMode === 1 ? 16 : (event.deltaMode === 2 ? frame.clientHeight : 1));
    setFocal(state.focal * Math.exp(-delta * .001));
  }, { passive: false });
  focalInput.addEventListener('input', () => { const focal = Number(focalInput.value); if (Number.isFinite(focal) && focal >= 1 && focal <= 3000) setFocal(focal); });
  focalSlider?.addEventListener('input', () => setFocal(sliderToFocal(Number(focalSlider.value))));
  rollInput.addEventListener('input', () => { const roll = Number(rollInput.value); if (!Number.isFinite(roll)) return; state.roll = Math.max(-45, Math.min(45, Math.round(roll))); syncState(); render(); });
  displayModeButtons.forEach((button) => button.addEventListener('click', () => {
    state.displayMode = button.dataset.displayMode === 'simulated' ? 'simulated' : 'scheme';
    syncState(); render(); ensureMoonThree();
  }));
  resetFrameButton?.addEventListener('click', () => {
    const retainedCenter = { ...state.center };
    state.focal = initialCamera.focal;
    state.fov = fieldOfView(state.focal);
    state.roll = 0;
    if (state.centering) {
      state.aim = horizonInput?.checked || form.querySelectorAll('[data-object]:checked').length > 0 ? 'automatic' : 'moon';
      state.offset = { x: 0, y: 0 };
      state.center = centerForAim();
    } else {
      state.aim = 'manual';
      state.center = retainedCenter;
      state.offset = { x: state.center.x - state.automaticCenter.x, y: state.center.y - state.automaticCenter.y };
    }
    syncState(); render();
  });

  const activePointers = new Map();
  let gesture = null;
  const pointerDistance = () => {
    const pointers = [...activePointers.values()];
    return pointers.length < 2 ? 0 : Math.hypot(pointers[0].x - pointers[1].x, pointers[0].y - pointers[1].y);
  };
  frame.addEventListener('pointerdown', (event) => {
    if ((event.pointerType === 'mouse' && event.button !== 0) || event.target.closest?.('button')) return;
    activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    frame.setPointerCapture(event.pointerId);
    if (activePointers.size === 1) {
      gesture = { type: 'drag', pointerId: event.pointerId, x: event.clientX, y: event.clientY, centerX: state.center.x, centerY: state.center.y };
    } else if (activePointers.size === 2) {
      gesture = { type: 'pinch', distance: pointerDistance(), focal: state.focal };
    }
    frame.classList.add('is-dragging'); event.preventDefault();
  });
  frame.addEventListener('pointermove', (event) => {
    if (!activePointers.has(event.pointerId) || !frame.hasPointerCapture(event.pointerId)) return;
    activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    if (gesture?.type === 'pinch') {
      const distance = pointerDistance();
      if (gesture.distance > 0 && distance > 0) setFocal(gesture.focal * distance / gesture.distance);
      event.preventDefault();
      return;
    }
    if (gesture?.type !== 'drag' || gesture.pointerId !== event.pointerId || activePointers.size !== 1) return;
    const rect = frame.getBoundingClientRect();
    const cameraDrag = { x: (event.clientX - gesture.x) / rect.width * state.fov.horizontal, y: (event.clientY - gesture.y) / rect.height * state.fov.vertical };
    const skyDrag = skyOffset(cameraDrag.x, cameraDrag.y);
    state.center.x = gesture.centerX - skyDrag.x;
    state.center.y = gesture.centerY - skyDrag.y;
    state.aim = 'manual'; state.offset.x = state.center.x - state.automaticCenter.x; state.offset.y = state.center.y - state.automaticCenter.y;
    syncState(); render(); event.preventDefault();
  });
  const endGesturePointer = (event) => {
    if (!activePointers.has(event.pointerId)) return;
    activePointers.delete(event.pointerId);
    if (frame.hasPointerCapture(event.pointerId)) frame.releasePointerCapture(event.pointerId);
    // Después de un pinch se espera a levantar ambos dedos para evitar convertirlo en drag con un salto.
    if (activePointers.size === 0) {
      gesture = null;
      frame.classList.remove('is-dragging');
    } else if (gesture?.type === 'drag') {
      gesture = null;
    }
  };
  frame.addEventListener('pointerup', endGesturePointer); frame.addEventListener('pointercancel', endGesturePointer);

  const thirds = document.querySelector('[data-thirds-toggle]');
  const savedGuides = localStorage.getItem('photography.thirds'); thirds.checked = savedGuides !== '0'; frame.dataset.thirds = thirds.checked ? '1' : '0';
  thirds.addEventListener('change', () => { frame.dataset.thirds = thirds.checked ? '1' : '0'; localStorage.setItem('photography.thirds', thirds.checked ? '1' : '0'); });
  centeringToggle?.addEventListener('change', () => {
    state.centering = centeringToggle.checked;
    if (centeringStatus) centeringStatus.textContent = state.centering ? 'Activado' : 'Desactivado';
    if (!state.centering) {
      state.aim = 'manual';
      state.offset = { x: state.center.x - state.automaticCenter.x, y: state.center.y - state.automaticCenter.y };
    }
    syncState();
  });
  const sensor = form.querySelector('[data-sensor]');
  let automaticSubmitTimer = 0;
  let submitting = false;
  const requestAutomaticSubmit = (delay = 0) => {
    window.clearTimeout(automaticSubmitTimer);
    automaticSubmitTimer = window.setTimeout(() => {
      if (submitting || !form.checkValidity()) return;
      form.requestSubmit();
    }, delay);
  };
  const syncCustomSensorFields = () => {
    const custom = sensor?.value === 'custom';
    form.querySelectorAll('[data-custom-sensor-field]').forEach((field) => { field.hidden = !custom; });
    form.querySelectorAll('[data-custom-sensor]').forEach((input) => { input.disabled = !custom; });
  };
  syncCustomSensorFields();
  sensor?.addEventListener('change', () => {
    syncCustomSensorFields();
    requestAutomaticSubmit();
  });
  form.querySelector('[data-variant]')?.addEventListener('change', () => requestAutomaticSubmit());
  const updateEffectiveTime = () => {
    if (!timeOffsetInput || !timeOffsetOutput || !effectiveTimeOutput) return;
    const offset = Number(timeOffsetInput.value) || 0;
    timeOffsetOutput.textContent = `${offset > 0 ? '+' : ''}${offset} min`;
    const dateValue = form.elements.date?.value || '';
    const timeValue = form.elements.time?.value || '';
    const dateMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateValue);
    const timeMatch = /^(\d{2}):(\d{2})$/.exec(timeValue);
    if (dateMatch && timeMatch) {
      const effective = new Date(Date.UTC(Number(dateMatch[1]), Number(dateMatch[2]) - 1, Number(dateMatch[3]), Number(timeMatch[1]), Number(timeMatch[2]) + offset));
      const two = (value) => String(value).padStart(2, '0');
      const effectiveDate = `${effective.getUTCFullYear()}-${two(effective.getUTCMonth() + 1)}-${two(effective.getUTCDate())}`;
      const effectiveClock = `${two(effective.getUTCHours())}:${two(effective.getUTCMinutes())}`;
      effectiveTimeOutput.textContent = effectiveDate === dateValue ? effectiveClock : `${two(effective.getUTCDate())}/${two(effective.getUTCMonth() + 1)} ${effectiveClock}`;
    }
    const url = new URL(window.location.href);
    if (offset === 0) url.searchParams.delete('time_offset'); else url.searchParams.set('time_offset', String(offset));
    window.history.replaceState(null, '', url);
  };
  const recenterForTimedChange = () => {
    if (!state.centering) return;
    state.aim = horizonInput?.checked || form.querySelectorAll('[data-object]:checked').length > 0 ? 'automatic' : 'moon';
    state.offset = { x: 0, y: 0 };
    state.center = centerForAim();
    syncState(); render();
  };
  form.querySelectorAll('input[name="date"],input[name="time"]').forEach((input) => {
    input.addEventListener('input', () => { updateEffectiveTime(); recenterForTimedChange(); requestAutomaticSubmit(500); });
    input.addEventListener('change', () => { updateEffectiveTime(); recenterForTimedChange(); requestAutomaticSubmit(); });
  });
  timeOffsetInput?.addEventListener('input', () => { updateEffectiveTime(); recenterForTimedChange(); requestAutomaticSubmit(180); });
  timeOffsetInput?.addEventListener('change', () => { updateEffectiveTime(); recenterForTimedChange(); requestAutomaticSubmit(); });
  updateEffectiveTime();
  form.querySelectorAll('[data-custom-sensor]').forEach((input) => {
    input.addEventListener('input', () => { if (!input.disabled) requestAutomaticSubmit(500); });
    input.addEventListener('change', () => { if (!input.disabled) requestAutomaticSubmit(); });
  });
  form.querySelectorAll('input[name="orientation"]').forEach((input) => input.addEventListener('change', () => {
    orientationManuallyChanged = true;
    if (orientationAutoInput) orientationAutoInput.value = '0';
    requestAutomaticSubmit();
  }));
  const preserveCenterForSelection = () => {
    state.aim = 'manual';
    state.offset = { x: state.center.x - state.automaticCenter.x, y: state.center.y - state.automaticCenter.y };
    syncState(); render();
  };
  horizonInput?.addEventListener('change', () => { preserveCenterForSelection(); requestAutomaticSubmit(); });
  form.querySelectorAll('[data-object]').forEach((input) => input.addEventListener('change', () => { preserveCenterForSelection(); requestAutomaticSubmit(); }));
  form.addEventListener('submit', () => {
    submitting = true;
    window.clearTimeout(automaticSubmitTimer);
    try {
      sessionStorage.setItem('photography.restoreScroll', JSON.stringify({ path: window.location.pathname, y: window.scrollY, savedAt: Date.now() }));
    } catch (_) {}
    const selectedIds = [...form.querySelectorAll('[data-object]:checked')].map((input) => input.value);
    form.querySelector('[data-objects-value]').value = selectedIds.join(',');
    if (state.aim === 'manual') {
      const selectedPoints = [{ x: 0, y: 0 }];
      payload.astronomy.objects.forEach((object) => { if (selectedIds.includes(object.id)) selectedPoints.push({ x: object.relative_x_degrees, y: object.relative_y_degrees }); });
      if (horizonInput?.checked) selectedPoints.push({ x: 0, y: payload.astronomy.horizon.relative_y_degrees });
      const xs = selectedPoints.map((point) => point.x); const ys = selectedPoints.map((point) => point.y);
      const nextAutomatic = { x: (Math.min(...xs) + Math.max(...xs)) / 2, y: (Math.min(...ys) + Math.max(...ys)) / 2 };
      state.offset = { x: state.center.x - nextAutomatic.x, y: state.center.y - nextAutomatic.y };
      offsetXInput.value = state.offset.x.toFixed(6).replace(/\.?0+$/, ''); offsetYInput.value = state.offset.y.toFixed(6).replace(/\.?0+$/, '');
    }
    const data = Object.fromEntries(new FormData(form));
    let savedEquipment = {};
    try { savedEquipment = JSON.parse(localStorage.getItem('photography.equipment') || '{}') || {}; } catch (_) {}
    const equipment = { ...savedEquipment, sensor: data.sensor, focal: data.focal, sensor_width: data.sensor_width, sensor_height: data.sensor_height };
    if (!sceneAutomaticOrientation || orientationManuallyChanged) equipment.orientation = data.orientation;
    localStorage.setItem('photography.equipment', JSON.stringify(equipment));
  });

  syncState(false); render(); ensureMoonThree();
})();
