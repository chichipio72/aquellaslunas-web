globalThis.AstronomyEditorialConfiguration = globalThis.AstronomyEditorialConfiguration || {
  clouds: {
    clearMaxPercent: 20, someMaxPercent: 50, mostlyMaxPercent: 80,
    eventToleranceMinutes: 30,
    labels: { clear: 'Despejado', some: 'Algunas nubes', mostly: 'Mayormente nublado', overcast: 'Cubierto' },
  },
};

const cloudCoverTest = async () => {
  const storageValues = new Map();
  const storage = {
    getItem: (key) => storageValues.get(key) || null,
    setItem: (key, value) => storageValues.set(key, value),
  };
  const nowMs = 2_000_000_000_000;
  let successRequests = 0;
  let requestedUrl = '';
  const successConfig = {
    latitude: -34.53,
    longitude: -58.48,
    timezone: 'America/Argentina/Buenos_Aires',
  };
  const successFetch = async (url) => {
    successRequests += 1;
    requestedUrl = String(url);
    return {
      ok: true,
      json: async () => ({
        current: { cloud_cover: 42 },
        hourly: {
          time: [2_000_000_000, 2_000_003_600],
          cloud_cover: [35, 60],
          cloud_cover_low: [2, 20],
          cloud_cover_mid: [0, 30],
          cloud_cover_high: [3, 40],
        },
      }),
    };
  };

  const forecast = await AstronomyCloudCover.requestForecast(successConfig, {
    fetchImpl: successFetch,
    storage,
    nowMs,
  });
  const cached = await AstronomyCloudCover.requestForecast(successConfig, {
    fetchImpl: successFetch,
    storage,
    nowMs,
  });
  if (successRequests !== 1 || cached !== forecast) {
    throw new Error('La respuesta horaria no se reutilizó.');
  }
  const scopedConfig = { ...successConfig, cacheScope: '2026-07-25' };
  await AstronomyCloudCover.requestForecast(scopedConfig, {
    fetchImpl: successFetch,
    storage,
    nowMs,
  });
  await AstronomyCloudCover.requestForecast(scopedConfig, {
    fetchImpl: successFetch,
    storage,
    nowMs,
  });
  if (successRequests !== 2) {
    throw new Error('La caché nocturna no quedó aislada y reutilizada por fecha.');
  }
  if (
    !requestedUrl.includes('current=cloud_cover')
    || !requestedUrl.includes('hourly=cloud_cover')
    || !requestedUrl.includes('cloud_cover_low')
    || !requestedUrl.includes('cloud_cover_mid')
    || !requestedUrl.includes('cloud_cover_high')
    || !requestedUrl.includes('forecast_days=7')
    || requestedUrl.includes('probability')
  ) {
    throw new Error('La consulta de Open-Meteo contiene parámetros incorrectos.');
  }
  if (AstronomyCloudCover.nearestCloudCover(forecast, nowMs + 1000) !== 35) {
    throw new Error('No se eligió la hora de pronóstico más cercana.');
  }
  const expectedCategories = [
    [0, 'clear'], [20, 'clear'], [21, 'some'], [50, 'some'],
    [51, 'mostly'], [80, 'mostly'], [81, 'overcast'], [100, 'overcast'],
  ];
  expectedCategories.forEach(([value, expected]) => {
    if (AstronomyCloudCover.cloudCategory(value)?.key !== expected) {
      throw new Error(`Categoría de nubosidad incorrecta para ${value} %.`);
    }
  });
  const layers = AstronomyCloudCover.nearestCloudLayers(forecast, nowMs + 1000);
  if (!layers || layers.low !== 2 || layers.mid !== 0 || layers.high !== 3) {
    throw new Error('No se conservaron las cuatro variables de nubosidad.');
  }
  const incompleteLayers = AstronomyCloudCover.parseForecast({
    current: { cloud_cover: 42 },
    hourly: {
      time: [2_000_000_000],
      cloud_cover: [35],
      cloud_cover_low: [2],
      cloud_cover_high: [3],
    },
  });
  if (
    AstronomyCloudCover.nearestCloudCover(incompleteLayers, nowMs + 1000) !== 35
    || AstronomyCloudCover.nearestCloudLayers(incompleteLayers, nowMs + 1000) !== null
  ) {
    throw new Error('Una capa ausente ocultó el total o dejó un detalle incompleto.');
  }
  const nightTimes = [
    Date.parse('2026-07-26T00:00:00-03:00'),
    Date.parse('2026-07-26T01:00:00-03:00'),
    Date.parse('2026-07-26T02:00:00-03:00'),
  ];
  const nightForecast = AstronomyCloudCover.parseForecast({
    hourly: {
      time: nightTimes.map((time) => time / 1000),
      cloud_cover: [0, 50, 100],
      cloud_cover_low: [0, 45, 100],
      cloud_cover_mid: [null, 20, 80],
      cloud_cover_high: [3, 35, 70],
    },
  });
  const nightPoints = AstronomyCloudCover.nightForecastPoints(
    nightForecast,
    '2026-07-25T23:35:00-03:00',
    '2026-07-26T02:25:00-03:00',
  );
  if (
    nightPoints.length !== 3
    || nightPoints[0].total !== 0
    || nightPoints[1].total !== 50
    || nightPoints[2].total !== 100
    || nightPoints[0].mid !== null
    || nightPoints[1].mid !== 20
  ) {
    throw new Error('La serie nocturna o una capa ausente se procesaron incorrectamente.');
  }

  const fakeRoot = (latitude, withCurrent = false) => {
    const textOutput = { textContent: '' };
    const trigger = {
      hidden: true,
      removed: false,
      remove() { this.removed = true; },
    };
    const layerOutput = { textContent: '' };
    const popover = {
      removed: false,
      remove() { this.removed = true; },
    };
    const output = {
      hidden: true,
      removed: false,
      textContent: '',
      remove() { this.removed = true; },
      querySelector(selector) {
        return {
          '[data-cloud-cover-text]': textOutput,
          '[data-cloud-cover-info]': trigger,
          '[data-cloud-cover-layers]': layerOutput,
          '[data-cloud-cover-popover]': popover,
        }[selector] || null;
      },
    };
    const event = {
      dataset: { cloudCoverEvent: new Date(nowMs + 1000).toISOString() },
      querySelector: () => output,
    };
    const currentOutput = {
      hidden: true,
      removed: false,
      textContent: '',
      remove() { this.removed = true; },
    };
    return {
      body: {
        dataset: {
          cloudCoverLatitude: String(latitude),
          cloudCoverLongitude: '-58.48',
          cloudCoverTimezone: 'America/Argentina/Buenos_Aires',
        },
      },
      querySelectorAll: (selector) => (
        selector === '[data-cloud-cover-event]' ? [event] : [output]
      ),
      querySelector: (selector) => (
        selector === '[data-current-cloud-cover]' && withCurrent ? currentOutput : null
      ),
      output,
      textOutput,
      trigger,
      layerOutput,
      popover,
      currentOutput,
    };
  };
  const successRoot = fakeRoot(-34.53, true);
  await AstronomyCloudCover.loadCloudCover({
    root: successRoot,
    fetchImpl: successFetch,
    storage,
    nowMs,
  });
  if (
    successRoot.output.hidden
    || successRoot.textOutput.textContent !== 'Cobertura nubosa prevista: 35 %'
    || successRoot.trigger.hidden
    || successRoot.layerOutput.textContent !== 'Bajas: 2 % · Medias: 0 % · Altas: 3 %'
  ) {
    throw new Error('No se mostró el texto esperado.');
  }
  if (
    successRoot.currentOutput.hidden
    || successRoot.currentOutput.textContent !== 'Cobertura nubosa actual: 42 %'
  ) {
    throw new Error('No se mostró la cobertura nubosa actual.');
  }
  const currentOnlyOutput = {
    hidden: true,
    textContent: '',
    remove() { throw new Error('Se eliminó una cobertura actual válida.'); },
  };
  await AstronomyCloudCover.loadCloudCover({
    root: {
      body: successRoot.body,
      querySelectorAll: () => [],
      querySelector: (selector) => (
        selector === '[data-current-cloud-cover]' ? currentOnlyOutput : null
      ),
    },
    fetchImpl: successFetch,
    storage,
    nowMs,
  });
  if (
    currentOnlyOutput.hidden
    || currentOnlyOutput.textContent !== 'Cobertura nubosa actual: 42 %'
  ) {
    throw new Error('La nubosidad actual dependió de que hubiera eventos próximos.');
  }
  const incompleteRoot = fakeRoot(-30.1);
  await AstronomyCloudCover.loadCloudCover({
    root: incompleteRoot,
    fetchImpl: async () => ({
      ok: true,
      json: async () => ({
        hourly: {
          time: [2_000_000_000],
          cloud_cover: [35],
          cloud_cover_low: [2],
          cloud_cover_high: [3],
        },
      }),
    }),
    storage,
    nowMs,
  });
  if (
    incompleteRoot.output.hidden
    || incompleteRoot.textOutput.textContent !== 'Cobertura nubosa prevista: 35 %'
    || !incompleteRoot.trigger.removed
    || !incompleteRoot.popover.removed
  ) {
    throw new Error('No se omitió únicamente el detalle de capas incompleto.');
  }

  let failureHidden = false;
  try {
    await AstronomyCloudCover.requestForecast(
      { ...successConfig, latitude: -33.1 },
      {
        fetchImpl: async () => ({ ok: false }),
        storage,
        nowMs,
      },
    );
  } catch (_) {
    failureHidden = true;
  }
  if (!failureHidden) throw new Error('Un fallo HTTP fue aceptado.');
  const failureRoot = fakeRoot(-31.1);
  await AstronomyCloudCover.loadCloudCover({
    root: failureRoot,
    fetchImpl: async () => ({ ok: false }),
    storage,
    nowMs,
  });
  if (!failureRoot.output.removed) {
    throw new Error('El fallo HTTP dejó visible el destino.');
  }
  const failedNightSection = {
    removed: false,
    remove() { this.removed = true; },
  };
  await AstronomyCloudCover.loadCloudCover({
    root: {
      body: {
        dataset: {
          cloudCoverLatitude: '-29.1',
          cloudCoverLongitude: '-58.48',
          cloudCoverTimezone: 'America/Argentina/Buenos_Aires',
          cloudCoverCacheScope: '2026-07-25',
        },
      },
      querySelectorAll: () => [],
      querySelector: (selector) => (
        selector === '[data-cloud-cover-night]' ? failedNightSection : null
      ),
    },
    fetchImpl: async () => ({ ok: false }),
    storage,
    nowMs,
  });
  if (!failedNightSection.removed) {
    throw new Error('El fallo dejó visible la sección nocturna.');
  }

  let timeoutHidden = false;
  const timeoutPromise = AstronomyCloudCover.requestForecast(
    { ...successConfig, latitude: -32.1 },
    {
      fetchImpl: (_url, { signal }) => new Promise((_resolve, reject) => {
        signal.onabort = () => reject(new Error('aborted'));
      }),
      storage,
      timeoutMs: 1,
      nowMs,
    },
  );
  fireCloudCoverTimers();
  try {
    await timeoutPromise;
  } catch (_) {
    timeoutHidden = true;
  }
  if (!timeoutHidden) throw new Error('El timeout no abortó la consulta.');

  let invalidRejected = false;
  try {
    AstronomyCloudCover.parseForecast({
      hourly: { time: [2_000_000_000], cloud_cover: [101] },
    });
  } catch (_) {
    invalidRejected = true;
  }
  if (!invalidRejected) throw new Error('Se aceptó cobertura fuera de 0–100.');

  testLog('Cobertura nubosa: éxito, caché, fallo y timeout OK');
};

cloudCoverTest().catch((error) => {
  testLog(`ERROR: ${error.message}`);
  throw error;
});
