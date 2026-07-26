const north = AstronomyMap.destinationPoint(-34.53, -58.48, 0, 10000);
const east = AstronomyMap.destinationPoint(-34.53, -58.48, 90, 10000);
if (!(north[0] > -34.53) || Math.abs(north[1] + 58.48) > 0.001) {
  throw new Error('El destino geodésico hacia el norte es incorrecto.');
}
if (!(east[1] > -58.48) || Math.abs(east[0] + 34.53) > 0.001) {
  throw new Error('El destino geodésico hacia el este es incorrecto.');
}

let receivedMapOptions = null;
let receivedMarkerOptions = null;
const fakeMap = {
  setView() { return this; },
};
globalThis.L = {
  map: (_element, options) => {
    receivedMapOptions = options;
    return fakeMap;
  },
  tileLayer: () => ({ addTo: () => {} }),
  marker: (_position, options) => {
    receivedMarkerOptions = options;
    return { addTo() { return this; } };
  },
};
AstronomyMap.createObserverMap({}, -34.53, -58.48, {
  zoom: 10,
  draggable: true,
  longPress: true,
  longPressTolerance: 10,
});
if (
  receivedMapOptions?.tapHold !== true
  || receivedMapOptions?.tapTolerance !== 10
  || receivedMarkerOptions?.draggable !== true
) {
  throw new Error('El selector no conserva TapHold de Leaflet y el marcador arrastrable.');
}

const mapListeners = {};
let confirmed = null;
let vibrations = 0;
const map = {
  on: (type, callback) => { mapListeners[type] = callback; },
  off: (type, callback) => {
    if (mapListeners[type] === callback) delete mapListeners[type];
  },
};
const binding = AstronomyMap.bindLongPress(map, (point) => { confirmed = point; }, {
  vibrate: () => { vibrations += 1; },
});
mapListeners.contextmenu({
  originalEvent: { _simulated: false },
  latlng: { lat: -34, lng: -58 },
});
if (confirmed !== null) throw new Error('Un contextmenu normal cambió la ubicación.');
mapListeners.contextmenu({
  originalEvent: { _simulated: true },
  latlng: { lat: -31.42, lng: -64.19 },
});
if (!confirmed || confirmed.lat !== -31.42 || confirmed.lng !== -64.19 || vibrations !== 1) {
  throw new Error('La pulsación prolongada válida no fijó la ubicación.');
}
binding.destroy();
if (mapListeners.contextmenu) throw new Error('No se liberó el listener de pulsación prolongada.');
console.log('Mapa: destino geodésico y pulsación prolongada OK');
