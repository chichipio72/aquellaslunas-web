const plannerHasPendingChanges = (appliedKey, currentKey) => (
  appliedKey !== null && appliedKey !== currentKey
);

const plannerAppliedMomentSummary = (dateText, time, label, locationName) => (
  label
    ? `${dateText}, ${time} · ${label} · ${locationName}`
    : `Mostrando el cielo del ${dateText} a las ${time} desde ${locationName}.`
);

const plannerApplyCompleteMoment = (
  { date, time, label },
  { isBusy, setValues, markPending, apply },
  trigger = null,
) => {
  if (isBusy()) return false;
  setValues(date, time);
  markPending();
  apply(trigger, label);
  return true;
};

const plannerQuickMoments = (data) => ([
  ['sun', 'rise', '☀', 'Salida del Sol'],
  ['sun', 'set', '☀', 'Puesta del Sol'],
  ['moon', 'rise', '☾', 'Salida de la Luna'],
  ['moon', 'set', '☾', 'Puesta de la Luna'],
]).flatMap(([body, eventName, icon, label]) => {
  const event = data?.[body]?.[eventName];
  return event ? [{ body, icon, label, date: data.date, time: event.time.slice(0, 5) }] : [];
});

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('planner-form');
  const mapElement = document.querySelector('[data-directions-map]');
  const submitButton = document.getElementById('planner-submit');
  const useNowButton = document.getElementById('planner-use-now');
  const pending = document.getElementById('planner-pending');
  const loading = document.getElementById('planner-loading');
  const errorBox = document.getElementById('planner-error');
  const summary = document.getElementById('planner-summary');
  const featuredDate = document.getElementById('planner-featured-date');
  const featuredEnhancement = document.getElementById('planner-featured-enhancement');
  const featuredTrigger = document.getElementById('planner-featured-trigger');
  const featuredPanel = document.getElementById('planner-featured-panel');
  const featuredBackdrop = document.getElementById('planner-featured-backdrop');
  const featuredOptions = featuredPanel?.querySelector('.planner-featured-options');
  const quickTimes = document.getElementById('planner-quick-times');
  const quickTimeButtons = quickTimes?.querySelector('[data-quick-time-buttons]');
  const mapShell = document.getElementById('planner-map-shell');
  const mapOverlay = document.getElementById('planner-map-overlay');
  const mapOverlayTitle = document.getElementById('planner-map-overlay-title');
  const mapOverlayDetail = document.getElementById('planner-map-overlay-detail');
  const mapUpdateButton = document.getElementById('planner-map-update');
  const mapSpinner = mapOverlay?.querySelector('.planner-map-spinner');
  if (!form || !mapElement || !submitButton || typeof L === 'undefined' || typeof AstronomyMap === 'undefined') {
    if (errorBox) {
      errorBox.textContent = 'No se pudo cargar el mapa.';
      errorBox.hidden = false;
    }
    return;
  }

  const latitude = Number(form.dataset.latitude);
  const longitude = Number(form.dataset.longitude);
  const timezone = form.dataset.timezone;
  const locationName = form.dataset.locationName;
  const observerMap = AstronomyMap.createObserverMap(mapElement, latitude, longitude, { zoom: 12 });
  if (!observerMap) return;
  const { map } = observerMap;
  const layers = new Map();
  let appliedKey = null;
  let appliedData = null;
  let activeController = null;
  let requestInProgress = false;
  let appliedMomentLabel = null;
  const visualDistanceMetres = 12000;

  const styles = {
    'sun-rise': { color: '#ffd36a', weight: 4, dashArray: '2 7', label: '☀ Salida' },
    'sun-instant': { color: '#ff9f43', weight: 5, label: '☀ Posición' },
    'sun-set': { color: '#e8793e', weight: 4, dashArray: '10 7', label: '☀ Puesta' },
    'moon-rise': { color: '#b9c8ff', weight: 4, dashArray: '2 7', label: '☾ Salida' },
    'moon-instant': { color: '#8ea8ff', weight: 5, label: '☾ Posición' },
    'moon-set': { color: '#9d83d7', weight: 4, dashArray: '10 7', label: '☾ Puesta' },
  };
  const formatNumber = (value) => Number(value).toLocaleString('es-AR', {
    minimumFractionDigits: 1, maximumFractionDigits: 1,
  });
  const formatClock = (value) => value ? value.slice(0, 5) : '—';
  const dateLabel = (value) => {
    const [year, month, day] = value.split('-').map(Number);
    return new Intl.DateTimeFormat('es-AR', { dateStyle: 'long', timeZone: 'UTC' })
      .format(new Date(Date.UTC(year, month - 1, day)));
  };
  const compactDate = (value) => value.split('-').reverse().join('/');
  const mapInteractionHandlers = [
    'dragging', 'touchZoom', 'doubleClickZoom', 'scrollWheelZoom', 'boxZoom', 'keyboard',
  ];
  const setMapInteractions = (enabled) => {
    mapInteractionHandlers.forEach((name) => {
      const handler = map[name];
      if (handler && typeof handler[enabled ? 'enable' : 'disable'] === 'function') {
        handler[enabled ? 'enable' : 'disable']();
      }
    });
    mapElement.querySelectorAll('.leaflet-control a, .leaflet-control button').forEach((control) => {
      if (!enabled) {
        if (!control.hasAttribute('data-planner-tabindex')) {
          control.dataset.plannerTabindex = control.getAttribute('tabindex') ?? '';
        }
        control.setAttribute('tabindex', '-1');
        control.setAttribute('aria-disabled', 'true');
      } else {
        const previous = control.dataset.plannerTabindex;
        if (previous === undefined || previous === '') control.removeAttribute('tabindex');
        else control.setAttribute('tabindex', previous);
        control.removeAttribute('aria-disabled');
        delete control.dataset.plannerTabindex;
      }
    });
  };
  const setMapState = (state) => {
    if (!mapShell || !mapOverlay || !mapOverlayTitle || !mapOverlayDetail || !mapUpdateButton || !mapSpinner) return;
    mapShell.dataset.mapState = state;
    const updated = state === 'updated';
    mapOverlay.hidden = updated;
    setMapInteractions(updated);
    mapSpinner.hidden = state !== 'loading';
    mapUpdateButton.hidden = state === 'loading';
    mapUpdateButton.disabled = state === 'loading';
    if (state === 'pending') {
      mapOverlayTitle.textContent = 'Hay cambios sin aplicar';
      mapOverlayDetail.textContent = appliedData
        ? `El mapa todavía muestra el ${compactDate(appliedData.date)} a las ${appliedData.time.slice(0, 5)}.`
        : 'Confirmá la fecha y la hora para actualizar el mapa.';
      mapUpdateButton.textContent = 'Actualizar mapa';
    } else if (state === 'loading') {
      mapOverlayTitle.textContent = 'Actualizando mapa…';
      mapOverlayDetail.textContent = appliedData
        ? 'Mientras tanto se conservan los datos anteriores.'
        : 'Estamos cargando las direcciones.';
    } else if (state === 'error') {
      mapOverlayTitle.textContent = 'No se pudo actualizar';
      mapOverlayDetail.textContent = appliedData
        ? 'Se siguen mostrando los datos anteriores.'
        : 'No fue posible cargar las direcciones.';
      mapUpdateButton.textContent = 'Reintentar';
      mapUpdateButton.hidden = false;
      mapUpdateButton.disabled = false;
    }
  };
  const queryState = () => ({
    date: form.elements.date.value,
    time: form.elements.time.value,
    latitude,
    longitude,
    timezone,
  });
  const stateKey = (state) => JSON.stringify(state);
  const updateDirty = () => {
    const isPending = plannerHasPendingChanges(appliedKey, stateKey(queryState()));
    pending.hidden = !isPending;
    if (!requestInProgress) {
      setMapState(isPending ? 'pending' : 'updated');
      if (!isPending && appliedKey !== null) errorBox.hidden = true;
    }
    if (quickTimes) {
      quickTimes.hidden = !appliedData || form.elements.date.value !== appliedData.date;
    }
  };
  form.elements.date.addEventListener('input', updateDirty);
  form.elements.time.addEventListener('input', updateDirty);

  const zonedNow = () => {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
      timeZone: timezone,
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date()).filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));
    return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}` };
  };
  featuredDate?.addEventListener('change', () => {
    if (!featuredDate.value) return;
    form.elements.date.value = featuredDate.value;
    updateDirty();
  });

  const setupFeaturedPicker = () => {
    if (!featuredDate || featuredDate.disabled || featuredDate.options.length <= 1
      || !featuredEnhancement || !featuredTrigger || !featuredPanel || !featuredOptions || !featuredBackdrop) return;
    const closeButtons = featuredPanel.querySelectorAll('[data-featured-close]');
    const close = (restoreFocus = true) => {
      featuredPanel.hidden = true;
      featuredBackdrop.hidden = true;
      featuredTrigger.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('featured-picker-open');
      if (restoreFocus) featuredTrigger.focus();
    };
    const open = () => {
      featuredPanel.hidden = false;
      featuredBackdrop.hidden = false;
      featuredTrigger.setAttribute('aria-expanded', 'true');
      document.body.classList.add('featured-picker-open');
      const selected = featuredOptions.querySelector(`[data-featured-date="${featuredDate.value}"]`);
      (selected || featuredOptions.querySelector('[data-featured-date]'))?.focus();
    };
    Array.from(featuredDate.children).forEach((group) => {
      if (!(group instanceof HTMLOptGroupElement)) return;
      const section = document.createElement('section');
      section.className = 'planner-featured-group';
      const heading = document.createElement('h4');
      heading.textContent = group.label;
      section.append(heading);
      const list = document.createElement('div');
      list.className = 'planner-featured-group-list';
      Array.from(group.children).forEach((option, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.featuredDate = option.value;
        button.textContent = option.textContent;
        if (index >= 8) {
          button.hidden = true;
          button.dataset.featuredExtra = 'true';
        }
        button.addEventListener('click', () => {
          featuredDate.value = option.value;
          featuredDate.dispatchEvent(new Event('change', { bubbles: true }));
          featuredTrigger.textContent = option.textContent;
          close();
        });
        list.append(button);
      });
      section.append(list);
      if (group.children.length > 8) {
        const more = document.createElement('button');
        more.type = 'button';
        more.className = 'planner-featured-more';
        more.textContent = `Ver más (${group.children.length - 8})`;
        more.addEventListener('click', () => {
          list.querySelectorAll('[data-featured-extra]').forEach((item) => { item.hidden = false; });
          more.remove();
        });
        section.append(more);
      }
      featuredOptions.append(section);
    });
    featuredDate.classList.add('is-enhanced');
    featuredEnhancement.hidden = false;
    featuredTrigger.addEventListener('click', () => (
      featuredTrigger.getAttribute('aria-expanded') === 'true' ? close() : open()
    ));
    featuredBackdrop.addEventListener('click', () => close());
    closeButtons.forEach((button) => button.addEventListener('click', () => close()));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !featuredPanel.hidden) {
        event.preventDefault();
        close();
      }
    });
  };
  setupFeaturedPicker();

  const clearLayers = () => {
    layers.forEach((layer) => map.removeLayer(layer));
    layers.clear();
  };
  const addDirection = (key, azimuth) => {
    const endpoint = AstronomyMap.destinationPoint(latitude, longitude, azimuth, visualDistanceMetres);
    const style = styles[key];
    const line = L.polyline([[latitude, longitude], endpoint], {
      color: style.color, weight: style.weight, dashArray: style.dashArray || null, opacity: .95,
    }).bindTooltip(`${style.label} · ${formatNumber(azimuth)}°`, { permanent: true, direction: 'center' });
    layers.set(key, line);
    const toggle = document.querySelector(`[data-layer-toggle="${key}"]`);
    if (!toggle || toggle.checked) line.addTo(map);
  };
  const eventMarkup = (bodyLabel, eventLabel, value, eventName) => value
    ? `<div><dt>${eventLabel}</dt><dd>${formatClock(value.time)} · Azimut ${formatNumber(value.azimuth_degrees)}°</dd></div>`
    : `<div><dt>${eventLabel}</dt><dd>${bodyLabel} no ${eventName} durante este día civil.</dd></div>`;
  const renderBody = (bodyKey, bodyLabel, eventBodyLabel, body, selectedTime) => {
    const target = document.querySelector(`[data-body-results="${bodyKey}"]`);
    const instant = body.instant;
    target.innerHTML = `<dl>
      ${eventMarkup(bodyLabel, `Salida ${eventBodyLabel}`, body.rise, 'sale')}
      <div><dt>${bodyLabel} a las ${selectedTime}</dt><dd>Azimut ${formatNumber(instant.azimuth_degrees)}° · Altura ${formatNumber(instant.altitude_degrees)}°<br>${instant.above_horizon ? 'Visible sobre el horizonte' : `${bodyLabel} está debajo del horizonte a esta hora.`}</dd></div>
      ${eventMarkup(bodyLabel, `Puesta ${eventBodyLabel}`, body.set, 'se pone')}
    </dl>`;
  };
  const renderQuickTimes = (data) => {
    if (!quickTimes || !quickTimeButtons) return;
    quickTimeButtons.replaceChildren();
    plannerQuickMoments(data).forEach(({ body, icon, label, date, time }) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `planner-quick-time planner-quick-time--${body}`;
      button.innerHTML = `<span aria-hidden="true">${icon}</span><span>${label}</span><strong>${time}</strong>`;
      const isApplied = appliedMomentLabel === label;
      button.classList.toggle('is-applied', isApplied);
      button.setAttribute('aria-pressed', isApplied ? 'true' : 'false');
      button.addEventListener('click', () => {
        applyCompleteMoment(date, time, label, button);
      });
      quickTimeButtons.append(button);
    });
    quickTimes.hidden = quickTimeButtons.childElementCount === 0;
  };
  const render = (data) => {
    clearLayers();
    for (const bodyKey of ['sun', 'moon']) {
      const body = data[bodyKey];
      if (body.rise) addDirection(`${bodyKey}-rise`, body.rise.azimuth_degrees);
      if (body.instant.above_horizon) addDirection(`${bodyKey}-instant`, body.instant.azimuth_degrees);
      if (body.set) addDirection(`${bodyKey}-set`, body.set.azimuth_degrees);
      renderBody(
        bodyKey,
        bodyKey === 'sun' ? 'El Sol' : 'La Luna',
        bodyKey === 'sun' ? 'del Sol' : 'de la Luna',
        body,
        data.time.slice(0, 5),
      );
    }
    summary.textContent = plannerAppliedMomentSummary(
      dateLabel(data.date),
      data.time.slice(0, 5),
      appliedMomentLabel,
      locationName,
    );
    renderQuickTimes(data);
    useNowButton?.classList.toggle('is-applied', appliedMomentLabel === 'Ahora');
    useNowButton?.setAttribute('aria-pressed', appliedMomentLabel === 'Ahora' ? 'true' : 'false');
    if (featuredDate) featuredDate.selectedIndex = 0;
    if (featuredTrigger) featuredTrigger.textContent = 'Elegir fecha destacada…';
    map.setView([latitude, longitude], 12);
  };

  document.querySelectorAll('[data-layer-toggle]').forEach((toggle) => {
    toggle.addEventListener('change', () => {
      const layer = layers.get(toggle.dataset.layerToggle);
      if (!layer) return;
      toggle.checked ? layer.addTo(map) : map.removeLayer(layer);
    });
  });

  const requestDirections = async (trigger = submitButton, momentLabel = null) => {
    if (requestInProgress || !form.reportValidity()) return;
    const state = queryState();
    const key = stateKey(state);
    if (key === appliedKey && appliedData) {
      appliedMomentLabel = momentLabel;
      render(appliedData);
      pending.hidden = true;
      setMapState('updated');
      return;
    }
    activeController?.abort();
    activeController = new AbortController();
    requestInProgress = true;
    submitButton.disabled = true;
    useNowButton.disabled = true;
    quickTimeButtons?.querySelectorAll('button').forEach((button) => { button.disabled = true; });
    trigger?.classList.add('is-applying');
    mapUpdateButton.disabled = true;
    loading.hidden = false;
    errorBox.hidden = true;
    setMapState('loading');
    try {
      const url = new URL('astronomy-directions.php', window.location.href);
      url.search = new URLSearchParams(state);
      const response = await fetch(url, { signal: activeController.signal, cache: 'no-store' });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'No se pudieron cargar las direcciones.');
      appliedData = data;
      appliedKey = key;
      appliedMomentLabel = momentLabel;
      render(data);
      const stillMatches = stateKey(queryState()) === appliedKey;
      pending.hidden = stillMatches;
      setMapState(stillMatches ? 'updated' : 'pending');
      if (trigger === mapUpdateButton && stillMatches) submitButton.focus();
    } catch (error) {
      if (error.name !== 'AbortError') {
        const remainsPending = plannerHasPendingChanges(appliedKey, stateKey(queryState()));
        if (appliedKey !== null && !remainsPending) {
          errorBox.hidden = true;
          pending.hidden = true;
          setMapState('updated');
        } else {
          errorBox.textContent = error.message || 'No se pudieron cargar las direcciones.';
          errorBox.hidden = false;
          pending.hidden = appliedKey === null;
          setMapState('error');
          if (trigger === mapUpdateButton) mapUpdateButton.focus();
        }
      }
    } finally {
      requestInProgress = false;
      submitButton.disabled = false;
      useNowButton.disabled = false;
      quickTimeButtons?.querySelectorAll('button').forEach((button) => { button.disabled = false; });
      trigger?.classList.remove('is-applying');
      mapUpdateButton.disabled = false;
      loading.hidden = true;
    }
  };
  const applyCompleteMoment = (date, time, label, trigger) => {
    plannerApplyCompleteMoment(
      { date, time, label },
      {
        isBusy: () => requestInProgress,
        setValues: (selectedDate, selectedTime) => {
          form.elements.date.value = selectedDate;
          form.elements.time.value = selectedTime;
        },
        markPending: updateDirty,
        apply: requestDirections,
      },
      trigger,
    );
  };
  useNowButton?.setAttribute('aria-pressed', 'false');
  useNowButton?.addEventListener('click', () => {
    const now = zonedNow();
    applyCompleteMoment(now.date, now.time, 'Ahora', useNowButton);
  });
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    requestDirections(submitButton);
  });
  mapUpdateButton?.addEventListener('click', () => requestDirections(mapUpdateButton));
  requestDirections();
});
