const AstronomyMap = (() => {
  const createObserverMap = (element, latitude, longitude, options = {}) => {
    if (!element || typeof L === 'undefined') return null;
    const initial = [Number(latitude), Number(longitude)];
    const mapOptions = options.longPress === true
      ? { tapHold: true, tapTolerance: options.longPressTolerance || 10 }
      : {};
    const map = L.map(element, mapOptions).setView(initial, options.zoom || 10);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
    }).addTo(map);
    const marker = L.marker(initial, {
      draggable: options.draggable === true,
      title: 'Ubicación del observador',
    }).addTo(map);
    return { map, marker };
  };

  // Destino geodésico sobre una esfera: azimut desde el norte en sentido horario.
  const destinationPoint = (latitude, longitude, azimuthDegrees, distanceMetres) => {
    const radius = 6371008.8;
    const toRadians = (degrees) => degrees * Math.PI / 180;
    const toDegrees = (radians) => radians * 180 / Math.PI;
    const angularDistance = distanceMetres / radius;
    const bearing = toRadians(azimuthDegrees);
    const startLatitude = toRadians(latitude);
    const startLongitude = toRadians(longitude);
    const endLatitude = Math.asin(
      Math.sin(startLatitude) * Math.cos(angularDistance)
      + Math.cos(startLatitude) * Math.sin(angularDistance) * Math.cos(bearing),
    );
    const endLongitude = startLongitude + Math.atan2(
      Math.sin(bearing) * Math.sin(angularDistance) * Math.cos(startLatitude),
      Math.cos(angularDistance) - Math.sin(startLatitude) * Math.sin(endLatitude),
    );
    return [toDegrees(endLatitude), ((toDegrees(endLongitude) + 540) % 360) - 180];
  };

  const bindLongPress = (
    map,
    onConfirm,
    {
      vibrate = () => {
        if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
          navigator.vibrate(20);
        }
      },
    } = {},
  ) => {
    if (!map || typeof map.on !== 'function' || typeof onConfirm !== 'function') return null;
    const confirm = (event) => {
      // Leaflet marca así el contextmenu que genera su TapHold tras validar
      // un solo dedo, tolerancia, touchend/touchcancel y movimiento.
      if (event?.originalEvent?._simulated !== true || !event.latlng) return;
      onConfirm(event.latlng);
      vibrate();
    };
    map.on('contextmenu', confirm);

    return {
      destroy() {
        map.off('contextmenu', confirm);
      },
    };
  };

  return { bindLongPress, createObserverMap, destinationPoint };
})();

document.addEventListener('DOMContentLoaded', () => {
  const mapElement = document.querySelector('[data-observer-map]');
  const form = document.getElementById('global-location-form');
  if (!mapElement || !form || typeof L === 'undefined') return;

  const fields = Object.fromEntries(['location_name', 'latitude', 'longitude', 'timezone', 'location_mode']
    .map((name) => [name, form.elements.namedItem(name)]));
  const status = document.getElementById('location-map-status');
  const observerMap = AstronomyMap.createObserverMap(
    mapElement,
    mapElement.dataset.latitude,
    mapElement.dataset.longitude,
    { zoom: 10, draggable: true, longPress: true, longPressTolerance: 10 },
  );
  if (!observerMap) return;
  const { map, marker: observerMarker } = observerMap;

  const message = (text, error = false) => {
    status.textContent = text;
    status.classList.toggle('is-error', error);
  };
  const reverse = async (latitude, longitude) => {
    const url = new URL('https://nominatim.openstreetmap.org/reverse');
    url.search = new URLSearchParams({
      format: 'jsonv2', lat: latitude, lon: longitude, zoom: 12, addressdetails: 1, 'accept-language': 'es',
    });
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error('No se pudo resolver la localidad.');
    const data = await response.json();
    const address = data.address || {};
    return address.city || address.municipality || address.locality || address.town || address.village
      || address.county || data.display_name?.split(',')[0] || 'Ubicación seleccionada';
  };
  const setObserver = async ({ latitude, longitude, name = null, mode = 'manual', timezone = null }) => {
    const lat = Number(latitude);
    const lon = Number(longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
    observerMarker.setLatLng([lat, lon]);
    map.setView([lat, lon], Math.max(map.getZoom(), 11));
    fields.latitude.value = lat.toFixed(6);
    fields.longitude.value = lon.toFixed(6);
    fields.location_mode.value = mode;
    if (timezone) fields.timezone.value = timezone;
    fields.location_name.value = name || await reverse(lat, lon);
    message('Posición actualizada. Guardá para aplicarla a todo el sitio.');
  };

  const applyMarkerPosition = async (point) => {
    message('Resolviendo localidad…');
    try {
      await setObserver({ latitude: point.lat, longitude: point.lng, mode: 'map' });
    } catch (error) {
      fields.latitude.value = point.lat.toFixed(6);
      fields.longitude.value = point.lng.toFixed(6);
      fields.location_name.value = 'Ubicación seleccionada';
      fields.location_mode.value = 'map';
      message(error.message, true);
    }
  };

  observerMarker.on('dragend', () => {
    applyMarkerPosition(observerMarker.getLatLng());
  });
  AstronomyMap.bindLongPress(map, applyMarkerPosition);

  document.getElementById('location-search-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const query = new FormData(event.currentTarget).get('place')?.trim();
    if (!query) return;
    message('Buscando localidad…');
    try {
      const url = new URL('https://nominatim.openstreetmap.org/search');
      url.search = new URLSearchParams({ format: 'jsonv2', q: query, limit: 1, addressdetails: 1, 'accept-language': 'es' });
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      const results = response.ok ? await response.json() : [];
      if (!results.length) throw new Error('No encontramos esa localidad.');
      const result = results[0];
      await setObserver({
        latitude: result.lat,
        longitude: result.lon,
        name: result.name || result.display_name.split(',')[0],
        mode: 'manual',
      });
    } catch (error) {
      message(error.message, true);
    }
  });

  document.getElementById('use-browser-location')?.addEventListener('click', async () => {
    message('Solicitando ubicación al navegador…');
    try {
      const coordinates = await AstronomyLocation.geolocate();
      await setObserver({
        ...coordinates,
        mode: 'geolocation',
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || fields.timezone.value,
      });
    } catch (error) {
      message(error.message, true);
    }
  });
  document.getElementById('use-default-location')?.addEventListener('click', () => {
    setObserver(AstronomyLocation.DEFAULT_LOCATION).catch((error) => message(error.message, true));
  });
});
