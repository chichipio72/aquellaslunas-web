const AstronomyMap = (() => {
  const placementBindings = new WeakMap();
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
    const beganOnInteractiveElement = (event) => event?.originalEvent?.target
      ?.closest?.('.leaflet-control, .leaflet-marker-icon, .leaflet-marker-shadow');
    const confirm = (event) => {
      // Leaflet marca así el contextmenu que genera su TapHold tras validar
      // un solo dedo durante 600 ms, tolerancia, touchend/touchcancel y movimiento.
      if (event?.originalEvent?._simulated !== true || !event.latlng || beganOnInteractiveElement(event)) return;
      onConfirm(event.latlng, 'longpress');
      vibrate();
    };
    map.on('contextmenu', confirm);

    return {
      destroy() {
        map.off('contextmenu', confirm);
      },
    };
  };

  const bindPlacementInteractions = (map, marker, onConfirm) => {
    if (!map || typeof map.on !== 'function' || !marker || typeof onConfirm !== 'function') return null;
    placementBindings.get(map)?.destroy();
    let lastTouchAt = 0;
    const rememberTouch = () => { lastTouchAt = Date.now(); };
    const isTouchGenerated = (event) => event?.originalEvent?.sourceCapabilities?.firesTouchEvents === true
      || event?.originalEvent?.pointerType === 'touch'
      || Date.now() - lastTouchAt < 1200;
    const beganOnInteractiveElement = (event) => event?.originalEvent?.target
      ?.closest?.('.leaflet-control, .leaflet-marker-icon, .leaflet-marker-shadow');
    const placeOnDoubleClick = (event) => {
      if (!event?.latlng || isTouchGenerated(event) || beganOnInteractiveElement(event)) return;
      onConfirm(event.latlng, 'doubleclick');
    };

    map.doubleClickZoom?.disable();
    map.on('touchstart', rememberTouch);
    map.on('dblclick', placeOnDoubleClick);
    const longPress = bindLongPress(map, onConfirm);

    const binding = {
      destroy() {
        map.off('touchstart', rememberTouch);
        map.off('dblclick', placeOnDoubleClick);
        longPress?.destroy();
        if (placementBindings.get(map) === binding) placementBindings.delete(map);
      },
    };
    placementBindings.set(map, binding);
    return binding;
  };

  return { bindLongPress, bindPlacementInteractions, createObserverMap, destinationPoint };
})();

document.addEventListener('DOMContentLoaded', () => {
  const mapElement = document.querySelector('[data-observer-map]');
  const form = document.getElementById('global-location-form');
  if (!mapElement || !form || typeof L === 'undefined') return;

  const fields = Object.fromEntries(['location_name', 'latitude', 'longitude', 'elevation_meters', 'resolved_timezone', 'location_mode']
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
  let statusTimer = null;

  const message = (text, error = false, temporarySelection = false) => {
    window.clearTimeout(statusTimer);
    status.textContent = text;
    status.classList.toggle('is-error', error);
    status.classList.toggle('is-selection', temporarySelection);
    if (temporarySelection) {
      statusTimer = window.setTimeout(() => {
        status.textContent = '';
        status.classList.remove('is-selection');
      }, 4000);
    }
  };
  const highlightMarker = () => {
    const element = observerMarker.getElement?.();
    if (!element) return;
    element.classList.remove('location-marker--selected');
    void element.offsetWidth;
    element.classList.add('location-marker--selected');
    window.setTimeout(() => element.classList.remove('location-marker--selected'), 900);
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
  const setObserver = async ({ latitude, longitude, elevation = 0, name = null, mode = 'manual', timezone = null }) => {
    const lat = Number(latitude);
    const lon = Number(longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
    observerMarker.setLatLng([lat, lon]);
    map.setView([lat, lon], Math.max(map.getZoom(), 11));
    fields.latitude.value = lat.toFixed(6);
    fields.longitude.value = lon.toFixed(6);
    fields.elevation_meters.value = Number.isFinite(Number(elevation)) ? Number(elevation).toFixed(1) : '0.0';
    fields.location_mode.value = mode;
    fields.resolved_timezone.value = timezone || 'Automática al guardar';
    fields.location_name.value = name || await reverse(lat, lon);
    message('Posición actualizada. Guardá para aplicarla a todo el sitio.');
  };
  ['latitude', 'longitude'].forEach((name) => fields[name]?.addEventListener('input', () => {
    fields.resolved_timezone.value = 'Automática al guardar';
  }));

  const applyMarkerPosition = async (point, interaction = 'drag') => {
    message('Resolviendo localidad…');
    if (interaction === 'doubleclick' || interaction === 'longpress') highlightMarker();
    try {
      await setObserver({ latitude: point.lat, longitude: point.lng, mode: 'map' });
      if (interaction === 'doubleclick' || interaction === 'longpress') {
        message('Ubicación seleccionada. Confirmala para guardarla.', false, true);
      }
    } catch (error) {
      fields.latitude.value = point.lat.toFixed(6);
      fields.longitude.value = point.lng.toFixed(6);
      fields.elevation_meters.value = '0.0';
      fields.resolved_timezone.value = 'Automática al guardar';
      fields.location_name.value = 'Ubicación seleccionada';
      fields.location_mode.value = 'map';
      message(error.message, true);
    }
  };

  observerMarker.on('dragend', () => {
    applyMarkerPosition(observerMarker.getLatLng());
  });
  AstronomyMap.bindPlacementInteractions(map, observerMarker, applyMarkerPosition);

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
      });
    } catch (error) {
      message(error.message, true);
    }
  });
  document.getElementById('use-default-location')?.addEventListener('click', () => {
    setObserver(AstronomyLocation.DEFAULT_LOCATION).catch((error) => message(error.message, true));
  });
});
