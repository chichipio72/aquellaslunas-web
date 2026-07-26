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
  };
  const open = () => {
    toggle.setAttribute('aria-expanded', 'true');
    panel.setAttribute('aria-hidden', 'false');
    panel.classList.add('is-open');
    backdrop.hidden = false;
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
