const validMobilePosition = {
  coords: {
    latitude: -34.6037,
    longitude: -58.3816,
    accuracy: 24,
    altitude: null,
    altitudeAccuracy: null,
    heading: null,
    speed: null,
  },
};

const diagnostics = {
  latitude: [validMobilePosition.coords.latitude, typeof validMobilePosition.coords.latitude],
  longitude: [validMobilePosition.coords.longitude, typeof validMobilePosition.coords.longitude],
  accuracy: [validMobilePosition.coords.accuracy, typeof validMobilePosition.coords.accuracy],
  timezone: ['', typeof ''],
};
console.log(JSON.stringify(diagnostics));

const normalized = normalizeBrowserLocation(validMobilePosition, '');
if (normalized === null
  || normalized.latitude !== -34.6037
  || normalized.longitude !== -58.3816
  || normalized.elevation !== 0
  || normalized.timezone !== 'America/Argentina/Buenos_Aires') {
  throw new Error('Una posición móvil válida con campos opcionales nulos fue rechazada.');
}

const numericStrings = normalizeBrowserLocation({
  coords: { latitude: '-31.4201', longitude: '-64.1888', accuracy: null },
}, 'America/Argentina/Cordoba');
if (numericStrings === null || numericStrings.latitude !== -31.4201 || numericStrings.longitude !== -64.1888) {
  throw new Error('Las coordenadas numéricas representadas como string fueron rechazadas.');
}

for (const coords of [
  { latitude: null, longitude: -58 },
  { latitude: NaN, longitude: -58 },
  { latitude: 91, longitude: -58 },
  { latitude: -34, longitude: 181 },
]) {
  if (normalizeBrowserLocation({ coords }, 'UTC') !== null) {
    throw new Error(`Se aceptaron coordenadas inválidas: ${JSON.stringify(coords)}`);
  }
}

console.log('Simulación de geolocalización Android: OK');
