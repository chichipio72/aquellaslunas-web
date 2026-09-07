export const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

const angularFields = new Set([
  'azimuth',
  'celestial_north_screen_angle',
  'celestial_north_screen_angle_degrees',
  'disk_angle',
  'longitude',
]);

export function interpolateAngleDegrees(first, second, fraction) {
  const delta = ((Number(second) - Number(first) + 540) % 360) - 180;
  return Number(first) + delta * fraction;
}

export function unwrapAngularSamples(samples) {
  const previousByPath = new Map();
  const cloneValue = (value, path = '') => {
    if (!value || typeof value !== 'object') return value;
    if (Array.isArray(value)) return value.map((item, index) => cloneValue(item, `${path}[${index}]`));
    const clone = {};
    for (const [key, item] of Object.entries(value)) {
      const itemPath = path ? `${path}.${key}` : key;
      if (typeof item === 'number' && angularFields.has(key)) {
        const previous = previousByPath.get(itemPath);
        const unwrapped = previous === undefined ? item : interpolateAngleDegrees(previous, item, 1);
        previousByPath.set(itemPath, unwrapped);
        clone[key] = unwrapped;
      } else {
        clone[key] = cloneValue(item, itemPath);
      }
    }
    return clone;
  };
  return samples.map((sample) => cloneValue(sample));
}

export function interpolate(samples, progress) {
  const position = clamp(progress, 0, 1) * (samples.length - 1);
  const index = Math.min(samples.length - 2, Math.floor(position));
  const fraction = position - index; const first = samples[index]; const second = samples[index + 1];
  const mix = (a, b) => Number(a) + (Number(b) - Number(a)) * fraction;
  const interpolateValue = (a, b, key = '') => {
    if (typeof a === 'number' && typeof b === 'number') {
      return angularFields.has(key) ? interpolateAngleDegrees(a, b, fraction) : mix(a, b);
    }
    if (a && b && typeof a === 'object' && typeof b === 'object' && !Array.isArray(a) && !Array.isArray(b)) {
      const nested = {}; for (const nestedKey of Object.keys(a)) nested[nestedKey] = interpolateValue(a[nestedKey], b[nestedKey], nestedKey); return nested;
    }
    return a;
  };
  const result = {};
  for (const key of Object.keys(first)) result[key] = interpolateValue(first[key], second[key], key);
  return result;
}

export function contactState(time, contacts, kind, classification) {
  const at = (code) => contacts[code] ? Date.parse(contacts[code]) : null;
  if (kind === 'solar') {
    if (at('C1') === null || time < at('C1') || time > at('C4')) return 'Fuera del eclipse';
    if (at('C2') !== null && time >= at('C2') && time <= at('C3')) return classification === 'annular' ? 'Anularidad' : 'Totalidad';
    return 'Fase parcial';
  }
  if (time < at('P1') || time > at('P4')) return 'Fuera de la sombra';
  if (at('U1') === null || time < at('U1') || time > at('U4')) return 'Fase penumbral';
  if (at('U2') !== null && time >= at('U2') && time <= at('U3')) return 'Totalidad';
  return 'Fase umbral';
}

export function setupTimeline(root, payload, render) {
  const eclipse = payload.eclipse; const start = Date.parse(eclipse.timeline_start); const end = Date.parse(eclipse.timeline_end); const maximum = Date.parse(eclipse.maximum);
  const query = new URLSearchParams(location.search);
  const previewValue = query.get('_preview_progress');
  const previewProgress = previewValue === null ? null : Number(previewValue);
  const previewInstantValue = query.get('_preview_instant');
  const previewInstant = previewInstantValue === null ? NaN : Date.parse(previewInstantValue);
  let progress = Number.isFinite(previewInstant)
    ? clamp((previewInstant - start) / (end - start), 0, 1)
    : (previewProgress !== null && Number.isFinite(previewProgress) ? clamp(previewProgress, 0, 1) : clamp((maximum - start) / (end - start), 0, 1));
  let playing = eclipse.animation.autoplay === true; let speed = Number(eclipse.animation.speed || 1); let previous = performance.now();
  const slider = root.querySelector('[data-eclipse-progress]'); const toggle = root.querySelector('[data-eclipse-toggle]'); const speedControl = root.querySelector('[data-eclipse-speed]');
  const time = root.querySelector('[data-eclipse-time]'); const state = root.querySelector('[data-eclipse-state]'); const visibility = root.querySelector('[data-eclipse-visibility]');
  if (speedControl) speedControl.value = String(speed);
  const paint = () => {
    const instant = start + (end - start) * progress; root.dataset.eclipseProgress = String(progress); render(progress, instant);
    if (slider && document.activeElement !== slider) slider.value = String(Math.round(progress * 1000));
    if (time) time.textContent = new Intl.DateTimeFormat('es-AR', { timeZone: eclipse.timezone, dateStyle: 'medium', timeStyle: 'medium', hour12: false }).format(new Date(instant));
    if (state) state.textContent = contactState(instant, eclipse.contacts, eclipse.kind, eclipse.local_classification || eclipse.classification);
    if (visibility) visibility.textContent = eclipse.visibility_label || (eclipse.visible ? 'Observable desde la ubicación indicada' : 'Por debajo del horizonte o no visible desde esta ubicación');
  };
  slider?.addEventListener('input', () => { playing = false; progress = Number(slider.value) / 1000; if (toggle) toggle.textContent = 'Reproducir'; paint(); });
  toggle?.addEventListener('click', () => { playing = !playing; toggle.textContent = playing ? 'Pausar' : 'Reproducir'; });
  speedControl?.addEventListener('change', () => { speed = Number(speedControl.value) || 1; });
  root.querySelector('[data-eclipse-maximum]')?.addEventListener('click', () => { playing = false; progress = clamp((maximum - start) / (end - start), 0, 1); if (toggle) toggle.textContent = 'Reproducir'; paint(); });
  const marks = root.querySelector('[data-eclipse-contact-marks]');
  if (marks) Object.entries(eclipse.contacts).forEach(([code, value]) => {
    if (!value) return; const mark = document.createElement('span'); mark.textContent = code;
    mark.style.left = `${clamp((Date.parse(value) - start) / (end - start), 0, 1) * 100}%`;
    mark.title = new Intl.DateTimeFormat('es-AR', { timeZone: eclipse.timezone, timeStyle: 'medium', hour12: false }).format(new Date(value)); marks.appendChild(mark);
  });
  const frame = (now) => { const elapsed = Math.min(100, now - previous); previous = now; if (playing) { progress += elapsed / ((Number(eclipse.animation.duration_seconds) || 45) * 1000) * speed; if (progress > 1) progress = 0; } paint(); requestAnimationFrame(frame); };
  paint();
  const captureInstants = eclipse.capture_instants && typeof eclipse.capture_instants === 'object' ? Object.entries(eclipse.capture_instants) : [];
  if (captureInstants.length > 0) {
    requestAnimationFrame(() => {
      captureInstants.forEach(([role, value]) => {
        const instant = Date.parse(value);
        progress = Number.isFinite(instant) ? clamp((instant - start) / (end - start), 0, 1) : progress;
        paint();
        root.dispatchEvent(new CustomEvent('eclipse-capture-frame', { detail: { role } }));
      });
    });
  } else if (root.closest('[data-eclipse-capture]') === null && new URLSearchParams(location.search).get('capture') !== '1') requestAnimationFrame(frame);
}

export function publishEclipseCapture(canvas, kind, force = false, hasSequence = false) {
  if (!force && new URLSearchParams(location.search).get('capture') !== '1') return;
  const publish = (role = kind) => {
    try {
      const imageData = canvas.toDataURL('image/png');
      const captureHost = canvas.closest('[data-eclipse-capture]');
      let captureImage = captureHost?.querySelector(`[data-eclipse-capture-image="${role}"]`) || null;
      if (!(captureImage instanceof HTMLImageElement)) {
        captureImage = document.createElement('img');
        captureImage.hidden = true;
        captureImage.dataset.eclipseCaptureImage = role;
        (captureHost || document.body).append(captureImage);
      }
      captureImage.src = imageData;
      if (window.parent !== window) window.parent.postMessage({ type: 'aquellas-lunas-eclipse-capture', kind, image: imageData }, location.origin);
    } catch (error) {
      if (window.parent !== window) window.parent.postMessage({ type: 'aquellas-lunas-eclipse-capture-error', kind }, location.origin);
    }
  };
  if (!hasSequence) requestAnimationFrame(() => requestAnimationFrame(() => publish()));
  canvas.closest('[data-real-lunar-eclipse], [data-real-solar-eclipse]')?.addEventListener('eclipse-capture-frame', (event) => publish(String(event.detail?.role || kind)));
  if (window.parent !== window) window.addEventListener('message', (event) => {
    if (event.origin === location.origin && event.source === window.parent && event.data?.type === 'aquellas-lunas-eclipse-capture-request') publish();
  });
}
