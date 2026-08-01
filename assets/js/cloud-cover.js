(() => {
  'use strict';

  const ENDPOINT = 'https://api.open-meteo.com/v1/forecast';
  const RANGE_MS = 7 * 24 * 60 * 60 * 1000;
  const CACHE_TTL_MS = 30 * 60 * 1000;
  const HOUR_MS = 60 * 60 * 1000;
  const CACHE_PREFIX = 'aquellas-lunas-cloud-cover-v4:';
  const memoryCache = new Map();
  const pendingRequests = new Map();
  const siteNowMs = () => {
    const configured = globalThis.siteTimeContext?.simulated
      ? Date.parse(globalThis.siteTimeContext.now || '')
      : NaN;
    return Number.isFinite(configured) ? configured : Date.now();
  };

  const locationConfig = (body) => {
    const latitude = Number(body?.dataset?.cloudCoverLatitude);
    const longitude = Number(body?.dataset?.cloudCoverLongitude);
    const timezone = body?.dataset?.cloudCoverTimezone?.trim() || '';
    const cacheScope = body?.dataset?.cloudCoverCacheScope?.trim() || 'rolling';
    if (
      !Number.isFinite(latitude) || latitude < -90 || latitude > 90
      || !Number.isFinite(longitude) || longitude < -180 || longitude > 180
      || timezone === ''
    ) return null;
    return { latitude, longitude, timezone, cacheScope };
  };

  const cacheKey = ({ latitude, longitude, timezone, cacheScope = 'rolling' }) => (
    `${CACHE_PREFIX}${latitude.toFixed(3)},${longitude.toFixed(3)},${timezone},${cacheScope}`
  );

  const validPercentage = (value) => (
    Number.isFinite(value) && value >= 0 && value <= 100
  );

  const editorialClouds = () => globalThis.AstronomyEditorialConfiguration?.clouds || {};

  const cloudCategory = (value) => {
    if (!validPercentage(value)) return null;
    const config = editorialClouds();
    if (value <= Number(config.clearMaxPercent)) return { key: 'clear', label: config.labels?.clear || '' };
    if (value <= Number(config.someMaxPercent)) return { key: 'some', label: config.labels?.some || '' };
    if (value <= Number(config.mostlyMaxPercent)) return { key: 'mostly', label: config.labels?.mostly || '' };
    return { key: 'overcast', label: config.labels?.overcast || '' };
  };

  const renderCloudIcon = (output, value, prefix = '') => {
    const category = cloudCategory(value);
    if (!output || !category) return false;
    const image = output.ownerDocument.createElement('img');
    image.src = `assets/images/weather/cloud-${category.key}.svg`;
    image.alt = '';
    image.width = 64;
    image.height = 48;
    output.replaceChildren(image);
    output.title = `${category.label}: ${Math.round(value)} % de cobertura`;
    output.setAttribute('aria-label', `${prefix ? `${prefix}. ` : ''}${category.label}. ${Math.round(value)} % de cobertura nubosa.`);
    output.hidden = false;
    return true;
  };

  const parsePercentage = (value) => {
    if (value === null || value === undefined || value === '') return null;
    const numericValue = Number(value);
    return validPercentage(numericValue) ? numericValue : null;
  };

  const validForecast = (value) => (
    value
    && Array.isArray(value.times)
    && Array.isArray(value.values)
    && value.times.length > 0
    && value.times.length === value.values.length
    && value.times.every((time, index) => (
      Number.isFinite(time)
      && validPercentage(value.values[index])
    ))
  );

  const parseForecast = (payload) => {
    const rawTimes = payload?.hourly?.time;
    const rawValues = payload?.hourly?.cloud_cover;
    if (!Array.isArray(rawTimes) || !Array.isArray(rawValues) || rawTimes.length !== rawValues.length) {
      throw new Error('invalid forecast');
    }
    const forecast = {
      times: rawTimes.map((value) => Number(value) * 1000),
      values: rawValues.map(parsePercentage),
      low: parseLayer(payload?.hourly?.cloud_cover_low, rawTimes.length),
      mid: parseLayer(payload?.hourly?.cloud_cover_mid, rawTimes.length),
      high: parseLayer(payload?.hourly?.cloud_cover_high, rawTimes.length),
      current: parsePercentage(payload?.current?.cloud_cover),
    };
    if (!validForecast(forecast)) throw new Error('invalid forecast');
    if (!validPercentage(forecast.current)) forecast.current = null;
    return forecast;
  };

  const parseLayer = (values, expectedLength) => {
    if (!Array.isArray(values) || values.length !== expectedLength) return null;
    return values.map((value) => {
      return parsePercentage(value);
    });
  };

  const readCache = (key, storage, nowMs) => {
    if (memoryCache.has(key)) return memoryCache.get(key);
    try {
      const parsed = JSON.parse(storage?.getItem(key) || 'null');
      if (
        parsed && Number.isFinite(parsed.savedAt)
        && nowMs - parsed.savedAt < CACHE_TTL_MS
        && validForecast(parsed.forecast)
      ) {
        memoryCache.set(key, parsed.forecast);
        return parsed.forecast;
      }
    } catch (_) {}
    return null;
  };

  const writeCache = (key, forecast, storage, nowMs) => {
    memoryCache.set(key, forecast);
    try {
      storage?.setItem(key, JSON.stringify({ savedAt: nowMs, forecast }));
    } catch (_) {}
  };

  const requestForecast = async (
    config,
    {
      fetchImpl = globalThis.fetch,
      storage = globalThis.sessionStorage,
      timeoutMs = 5000,
      nowMs = siteNowMs(),
    } = {},
  ) => {
    const key = cacheKey(config);
    const cached = readCache(key, storage, nowMs);
    if (cached) return cached;
    if (pendingRequests.has(key)) return pendingRequests.get(key);

    const request = (async () => {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), timeoutMs);
      try {
        const url = new URL(ENDPOINT);
        url.search = new URLSearchParams({
          latitude: String(config.latitude),
          longitude: String(config.longitude),
          timezone: config.timezone,
          current: 'cloud_cover',
          hourly: 'cloud_cover,cloud_cover_low,cloud_cover_mid,cloud_cover_high',
          forecast_days: '7',
          timeformat: 'unixtime',
        }).toString();
        const response = await fetchImpl(url, { signal: controller.signal });
        if (!response.ok) throw new Error('forecast unavailable');
        const forecast = parseForecast(await response.json());
        writeCache(key, forecast, storage, nowMs);
        return forecast;
      } finally {
        clearTimeout(timeout);
        pendingRequests.delete(key);
      }
    })();
    pendingRequests.set(key, request);
    return request;
  };

  const nearestCloudCover = (forecast, eventTimeMs) => {
    let nearestIndex = -1;
    let nearestDistance = Infinity;
    forecast.times.forEach((time, index) => {
      const distance = Math.abs(time - eventTimeMs);
      if (distance < nearestDistance) {
        nearestDistance = distance;
        nearestIndex = index;
      }
    });
    return nearestIndex < 0 || nearestDistance > Number(editorialClouds().eventToleranceMinutes) * 60 * 1000
      ? null
      : Math.round(forecast.values[nearestIndex]);
  };

  const nearestCloudLayers = (forecast, eventTimeMs) => {
    let nearestIndex = -1;
    let nearestDistance = Infinity;
    forecast.times.forEach((time, index) => {
      const distance = Math.abs(time - eventTimeMs);
      if (distance < nearestDistance) {
        nearestDistance = distance;
        nearestIndex = index;
      }
    });
    if (
      nearestIndex < 0
      || nearestDistance > Number(editorialClouds().eventToleranceMinutes) * 60 * 1000
      || !validPercentage(forecast.low?.[nearestIndex])
      || !validPercentage(forecast.mid?.[nearestIndex])
      || !validPercentage(forecast.high?.[nearestIndex])
    ) return null;
    return {
      low: Math.round(forecast.low[nearestIndex]),
      mid: Math.round(forecast.mid[nearestIndex]),
      high: Math.round(forecast.high[nearestIndex]),
    };
  };

  const nightForecastPoints = (forecast, startValue, endValue) => {
    const startMs = Date.parse(startValue || '');
    const endMs = Date.parse(endValue || '');
    if (!validForecast(forecast) || !Number.isFinite(startMs) || !Number.isFinite(endMs) || endMs <= startMs) {
      return [];
    }
    const firstHour = Math.ceil(startMs / HOUR_MS) * HOUR_MS;
    const lastHour = Math.floor(endMs / HOUR_MS) * HOUR_MS;
    return forecast.times.flatMap((time, index) => {
      if (time < firstHour || time > lastHour) return [];
      return [{
        time,
        total: Math.round(forecast.values[index]),
        low: validPercentage(forecast.low?.[index]) ? Math.round(forecast.low[index]) : null,
        mid: validPercentage(forecast.mid?.[index]) ? Math.round(forecast.mid[index]) : null,
        high: validPercentage(forecast.high?.[index]) ? Math.round(forecast.high[index]) : null,
      }];
    });
  };

  const localHour = (time, timezone) => new Intl.DateTimeFormat('es-AR', {
    timeZone: timezone,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date(time));

  const renderNightChart = (section, forecast, timezone) => {
    const points = nightForecastPoints(
      forecast,
      section.dataset.nightStart,
      section.dataset.nightEnd,
    );
    const chart = section.querySelector('[data-night-cloud-chart]');
    const documentRef = section.ownerDocument;
    if (points.length === 0 || !chart || !documentRef) return false;

    const plot = documentRef.createElement('div');
    plot.className = 'night-cloud-chart__plot';
    plot.style.setProperty('--night-cloud-points', String(points.length));
    const labelStep = points.length <= 6 ? 1 : (points.length <= 12 ? 2 : 3);

    points.forEach((point, index) => {
      const hour = localHour(point.time, timezone);
      const column = documentRef.createElement('div');
      column.className = 'night-cloud-chart__column';

      const trigger = documentRef.createElement('button');
      trigger.type = 'button';
      trigger.className = 'night-cloud-chart__point';
      const popoverId = `night-cloud-detail-${index}`;
      trigger.setAttribute('popovertarget', popoverId);
      renderCloudIcon(trigger, point.total, hour);
      const percentage = documentRef.createElement('span');
      percentage.className = 'night-cloud-chart__percentage';
      percentage.textContent = `${point.total} %`;
      percentage.setAttribute('aria-hidden', 'true');
      trigger.append(percentage);

      const hourLabel = documentRef.createElement('span');
      hourLabel.className = 'night-cloud-chart__hour';
      hourLabel.textContent = hour;
      if (index % labelStep !== 0 && index !== points.length - 1) {
        hourLabel.classList.add('is-visually-hidden');
      }
      column.append(trigger, hourLabel);

      const popover = documentRef.createElement('div');
      popover.id = popoverId;
      popover.className = 'cloud-cover-popover night-cloud-popover';
      popover.setAttribute('popover', '');
      popover.setAttribute('role', 'dialog');
      popover.setAttribute('aria-label', `Nubosidad a las ${hour}`);
      const heading = documentRef.createElement('strong');
      heading.textContent = hour;
      popover.append(heading);
      [
        ['Total', point.total],
        ['Bajas', point.low],
        ['Medias', point.mid],
        ['Altas', point.high],
      ].forEach(([label, value]) => {
        if (!validPercentage(value)) return;
        const detail = documentRef.createElement('span');
        detail.textContent = `${label}: ${value} %`;
        popover.append(detail);
      });
      const note = documentRef.createElement('small');
      note.textContent = 'Los porcentajes por altura no se suman entre sí.';
      popover.append(note);
      column.append(popover);

      trigger.addEventListener('mouseenter', () => {
        if (typeof popover.showPopover === 'function' && !popover.matches(':popover-open')) {
          popover.showPopover();
        }
      });
      trigger.addEventListener('mouseleave', () => {
        if (typeof popover.hidePopover === 'function' && popover.matches(':popover-open')) {
          popover.hidePopover();
        }
      });
      plot.append(column);
    });

    chart.replaceChildren(plot);
    section.hidden = false;
    return true;
  };

  const renderEventOutput = (output, total, layers) => {
    const text = `Cobertura nubosa prevista: ${total} %`;
    const inlineIcon = output.querySelector?.('[data-cloud-cover-inline-icon]');
    if (inlineIcon) renderCloudIcon(inlineIcon, total);
    const textOutput = output.querySelector?.('[data-cloud-cover-text]');
    if (textOutput) textOutput.textContent = text;
    else output.textContent = text;

    const trigger = output.querySelector?.('[data-cloud-cover-info]');
    const layerOutput = output.querySelector?.('[data-cloud-cover-layers]');
    if (layers && trigger && layerOutput) {
      layerOutput.textContent = `Bajas: ${layers.low} % · Medias: ${layers.mid} % · Altas: ${layers.high} %`;
      trigger.hidden = false;
    } else {
      trigger?.remove();
      output.querySelector?.('[data-cloud-cover-popover]')?.remove();
    }
    output.hidden = false;
  };

  const removeTargets = (targets) => targets.forEach(({ output }) => output.remove());

  const loadCloudCover = async ({
    root = document,
    nowMs = siteNowMs(),
    fetchImpl = globalThis.fetch,
    storage = globalThis.sessionStorage,
    timeoutMs = 5000,
  } = {}) => {
    const config = locationConfig(root.body);
    const currentOutput = root.querySelector('[data-current-cloud-cover]');
    const nightSection = root.querySelector('[data-cloud-cover-night]');
    const allOutputs = [...root.querySelectorAll('[data-cloud-cover-value]')];
    const targets = [...root.querySelectorAll('[data-cloud-cover-event]')]
      .map((element) => {
        const output = element.querySelector('[data-cloud-cover-value], [data-cloud-cover-icon]');
        return {
          eventTimeMs: Date.parse(element.dataset.cloudCoverEvent || ''),
          output,
          iconOnly: output?.hasAttribute?.('data-cloud-cover-icon') === true,
        };
      })
      .filter(({ eventTimeMs, output }) => (
        output
        && Number.isFinite(eventTimeMs)
        && eventTimeMs > nowMs
        && eventTimeMs <= nowMs + RANGE_MS
      ));
    allOutputs.forEach((output) => {
      if (!targets.some((target) => target.output === output)) output.remove();
    });
    if (!config || (targets.length === 0 && !currentOutput && !nightSection) || typeof fetchImpl !== 'function') {
      removeTargets(targets);
      currentOutput?.remove();
      nightSection?.remove();
      return;
    }
    try {
      const forecast = await requestForecast(
        config,
        { fetchImpl, storage, timeoutMs, nowMs },
      );
      if (currentOutput && validPercentage(forecast.current)) {
        currentOutput.textContent = `Cobertura nubosa actual: ${Math.round(forecast.current)} %`;
        currentOutput.hidden = false;
      } else {
        currentOutput?.remove();
      }
      if (nightSection && !renderNightChart(nightSection, forecast, config.timezone)) {
        nightSection.remove();
      }
      targets.forEach(({ eventTimeMs, output, iconOnly }) => {
        const value = nearestCloudCover(forecast, eventTimeMs);
        if (value === null || value < 0 || value > 100) {
          output.remove();
          return;
        }
        if (iconOnly) renderCloudIcon(output, value);
        else renderEventOutput(output, value, nearestCloudLayers(forecast, eventTimeMs));
      });
    } catch (_) {
      removeTargets(targets);
      currentOutput?.remove();
      nightSection?.remove();
    }
  };

  globalThis.AstronomyCloudCover = {
    loadCloudCover,
    nearestCloudCover,
    nearestCloudLayers,
    nightForecastPoints,
    cloudCategory,
    renderCloudIcon,
    parseForecast,
    requestForecast,
  };

  if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
      loadCloudCover();
    }, { once: true });
  }
})();
