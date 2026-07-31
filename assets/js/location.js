const AstronomyLocation = (() => {
  const DEFAULT_LOCATION = {
    name: 'Buenos Aires',
    latitude: -34.53,
    longitude: -58.48,
    timezone: 'America/Argentina/Buenos_Aires',
    mode: 'default',
  };

  const analyzePosition = (position) => {
    const rawLatitude = position?.coords?.latitude;
    const rawLongitude = position?.coords?.longitude;
    const latitude = rawLatitude === null || rawLatitude === '' ? Number.NaN : Number(rawLatitude);
    const longitude = rawLongitude === null || rawLongitude === '' ? Number.NaN : Number(rawLongitude);
    return Number.isFinite(latitude) && latitude >= -90 && latitude <= 90
      && Number.isFinite(longitude) && longitude >= -180 && longitude <= 180
      ? { latitude, longitude }
      : null;
  };

  const geolocate = () => new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('La geolocalización no está disponible en este navegador.'));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const coordinates = analyzePosition(position);
        coordinates ? resolve(coordinates) : reject(new Error('El navegador devolvió coordenadas inválidas.'));
      },
      (error) => {
        const messages = {
          1: 'El navegador rechazó el permiso de ubicación.',
          2: 'El navegador no pudo determinar tu ubicación.',
          3: 'La ubicación demoró demasiado en responder.',
        };
        reject(new Error(messages[error.code] || 'No se pudo obtener tu ubicación.'));
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
    );
  });

  return { DEFAULT_LOCATION, analyzePosition, geolocate };
})();

// Compatibilidad con las pruebas y consumidores anteriores del helper puro.
const normalizeBrowserLocation = (position, detectedTimezone = '') => {
  const coordinates = AstronomyLocation.analyzePosition(position);
  return coordinates === null ? null : {
    ...coordinates,
    timezone: detectedTimezone?.trim() || 'America/Argentina/Buenos_Aires',
  };
};

document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.menu-toggle');
  const panel = document.getElementById('site-menu-panel');
  const backdrop = document.querySelector('.menu-backdrop');
  if (!toggle || !panel || !backdrop) return;

  const close = () => {
    toggle.setAttribute('aria-expanded', 'false');
    panel.setAttribute('aria-hidden', 'true');
    panel.classList.remove('is-open');
    backdrop.hidden = true;
    document.body.classList.remove('site-menu-open');
  };
  const open = () => {
    toggle.setAttribute('aria-expanded', 'true');
    panel.setAttribute('aria-hidden', 'false');
    panel.classList.add('is-open');
    backdrop.hidden = false;
    document.body.classList.add('site-menu-open');
    panel.querySelector('a, button')?.focus();
  };
  toggle.addEventListener('click', () => toggle.getAttribute('aria-expanded') === 'true' ? close() : open());
  document.querySelectorAll('[data-menu-close]').forEach((item) => item.addEventListener('click', close));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && panel.classList.contains('is-open')) {
      close();
      toggle.focus();
    }
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const panel = document.querySelector('[data-location-intro]');
  const trigger = document.querySelector('[data-location-intro-trigger]');
  if (!panel || !trigger) return;

  const status = panel.querySelector('[data-location-intro-status]');
  const primaryAction = panel.querySelector('[data-location-intro-geolocate]');
  const seenCookie = 'astro_location_intro_seen=1; Max-Age=34560000; Path=/; SameSite=Lax';
  const markSeen = () => {
    document.cookie = `${seenCookie}${window.location.protocol === 'https:' ? '; Secure' : ''}`;
  };
  const open = ({ focus = true } = {}) => {
    panel.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    if (focus) primaryAction?.focus();
  };
  const close = ({ remember = true, restoreFocus = true } = {}) => {
    if (remember) markSeen();
    panel.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    if (restoreFocus) trigger.focus();
  };

  trigger.addEventListener('click', () => {
    if (panel.hidden) open();
    else close();
  });
  panel.querySelector('[data-location-intro-close]')?.addEventListener('click', () => close());
  panel.querySelector('[data-location-intro-manual]')?.addEventListener('click', markSeen);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) {
      event.preventDefault();
      close();
    }
  });

  primaryAction?.addEventListener('click', async () => {
    primaryAction.disabled = true;
    status.textContent = 'Solicitando ubicación al navegador…';
    try {
      const coordinates = await AstronomyLocation.geolocate();
      const timezone = typeof Intl !== 'undefined' && typeof Intl.DateTimeFormat === 'function'
        ? Intl.DateTimeFormat().resolvedOptions().timeZone
        : '';
      const form = document.createElement('form');
      form.method = 'post';
      form.action = panel.querySelector('form')?.action || 'ubicacion.php';
      const values = {
        location_name: `${coordinates.latitude.toFixed(4)}, ${coordinates.longitude.toFixed(4)}`,
        latitude: coordinates.latitude,
        longitude: coordinates.longitude,
        timezone: timezone || window.siteTimeContext?.timezone || AstronomyLocation.DEFAULT_LOCATION.timezone,
        location_mode: 'geolocation',
        return_to: window.location.pathname,
      };
      Object.entries(values).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value);
        form.append(input);
      });
      document.body.append(form);
      form.submit();
    } catch (error) {
      status.textContent = error.message;
      primaryAction.disabled = false;
      primaryAction.focus();
    }
  });

  if (!panel.hidden) open({ focus: true });
});
