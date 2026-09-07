const AstronomyLocation = (() => {
  const DEFAULT_LOCATION = {
    name: 'Buenos Aires',
    latitude: -34.53,
    longitude: -58.48,
    elevation: 0,
    timezone: 'America/Argentina/Buenos_Aires',
    mode: 'default',
  };

  const analyzePosition = (position) => {
    const rawLatitude = position?.coords?.latitude;
    const rawLongitude = position?.coords?.longitude;
    const latitude = rawLatitude === null || rawLatitude === '' ? Number.NaN : Number(rawLatitude);
    const longitude = rawLongitude === null || rawLongitude === '' ? Number.NaN : Number(rawLongitude);
    const rawElevation = position?.coords?.altitude;
    const elevation = rawElevation === null || rawElevation === '' || !Number.isFinite(Number(rawElevation))
      ? 0 : Math.max(-500, Math.min(10000, Number(rawElevation)));
    return Number.isFinite(latitude) && latitude >= -90 && latitude <= 90
      && Number.isFinite(longitude) && longitude >= -180 && longitude <= 180
      ? { latitude, longitude, elevation }
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

document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('global-location-form');
  const syncPanel = form?.querySelector('[data-location-notification-sync]');
  const syncInput = form?.elements.sync_notification_location;
  const currentLabel = form?.querySelector('[data-notification-location-current]');
  if (!form || !syncPanel || !syncInput || !('serviceWorker' in navigator)) return;

  const configUrl = form.dataset.deviceConfigUrl || '';
  const csrfToken = form.dataset.deviceCsrf || '';
  let subscription = null;
  const pushRequest = async (action, extra = {}) => {
    const response = await fetch(configUrl, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({action, csrf_token: csrfToken, subscription: subscription.toJSON(), ...extra}),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok !== true) throw new Error('No se pudo sincronizar la ubicación de las notificaciones.');
    return result.state;
  };

  try {
    const registration = await navigator.serviceWorker.getRegistration('./');
    subscription = await registration?.pushManager?.getSubscription();
    if (!subscription) return;
    const state = await pushRequest('read');
    if (state?.configured !== true || !state.device) return;
    currentLabel.textContent = `Actualmente: ${state.device.location_name}`;
    syncPanel.hidden = false;
  } catch (_) {
    return;
  }

  form.addEventListener('submit', async (event) => {
    if (!syncInput.checked || !subscription) return;
    event.preventDefault();
    const submitter = event.submitter;
    if (submitter) submitter.disabled = true;
    try {
      const response = await fetch(form.action || window.location.href, {
        method: 'POST',
        headers: {'Accept': 'application/json'},
        credentials: 'same-origin',
        body: new FormData(form),
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || result.ok !== true || !result.location || !result.redirect) {
        throw new Error(typeof result.message === 'string' ? result.message : 'No se pudo guardar la ubicación.');
      }
      try {
        await pushRequest('update_location', {location: {
          location_name: result.location.name,
          latitude: result.location.latitude,
          longitude: result.location.longitude,
          timezone: result.location.timezone,
        }});
      } catch (_) {
        const status = document.getElementById('location-map-status');
        if (status) {
          status.textContent = 'La ubicación general se guardó, pero no se pudo actualizar la ubicación de las notificaciones.';
          status.classList.add('is-error');
        }
        if (submitter) submitter.disabled = false;
        syncInput.checked = false;
        return;
      }
      const target = new URL(result.redirect, window.location.href);
      if (target.pathname === window.location.pathname && target.searchParams.get('saved') === '1') {
        target.searchParams.set('notification_sync', 'ok');
      }
      window.location.assign(target.href);
    } catch (error) {
      const status = document.getElementById('location-map-status');
      if (status) {
        status.textContent = error.message;
        status.classList.add('is-error');
      }
      if (submitter) submitter.disabled = false;
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
      const form = document.createElement('form');
      form.method = 'post';
      form.action = panel.querySelector('form')?.action || 'ubicacion.php';
      const values = {
        location_name: `${coordinates.latitude.toFixed(4)}, ${coordinates.longitude.toFixed(4)}`,
        latitude: coordinates.latitude,
        longitude: coordinates.longitude,
        elevation_meters: coordinates.elevation,
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
