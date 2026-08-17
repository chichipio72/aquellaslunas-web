(() => {
  'use strict';

  const root = document.querySelector('.notification-settings[data-device-config-url]');
  if (!root) return;

  const formCard = root.querySelector('[data-device-form-card]');
  const form = root.querySelector('[data-device-form]');
  const configState = root.querySelector('[data-device-config-state]');
  const summaryState = root.querySelector('[data-push-summary-state]');
  const saveStatus = root.querySelector('[data-device-save-status]');
  const lastSaved = root.querySelector('[data-device-last-saved]');
  const typesContainer = root.querySelector('[data-notification-types]');
  const context = root.querySelector('[data-notification-context]');
  const contextType = context?.dataset.notificationType || '';
  const pushHeading = root.querySelector('[data-push-heading]');
  const pushStepLabel = root.querySelector('[data-push-step-label]');
  const pushReadyCopy = root.querySelector('[data-push-ready-copy]');
  const quietHoursInput = form?.elements.quiet_hours_enabled;
  const quietTimes = root.querySelector('[data-quiet-times]');
  const quietState = root.querySelector('[data-quiet-state]');
  const selectedLocation = root.querySelector('[data-selected-location]');
  const iosHelp = root.querySelector('[data-ios-pwa-help]');
  const supportBlock = root.querySelector('[data-device-support]');
  const supportId = root.querySelector('[data-device-support-id]');
  const copySupportId = root.querySelector('[data-copy-support-id]');
  const configUrl = root.dataset.deviceConfigUrl || '';
  const csrfToken = root.dataset.deviceCsrf || '';
  const locationReady = root.dataset.locationReady === 'true';
  let useGeneralLocation = root.dataset.locationChangeRequested === 'true';
  const serviceWorkerTools = window.AstronomyPushServiceWorker;
  let currentSubscription = null;

  const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;
  if (iosHelp && isIos && !isStandalone) iosHelp.hidden = false;

  const setText = (element, text) => {
    if (element) element.textContent = text;
  };

  const request = async (action, subscription, config = null) => {
    const response = await fetch(configUrl, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({action, csrf_token: csrfToken, subscription: subscription.toJSON(), config}),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok !== true || !result.state) {
      const error = new Error(typeof result.message === 'string' ? result.message : 'No pudimos cargar la configuración.');
      error.httpStatus = response.status;
      error.logicalCode = typeof result.code === 'string' ? result.code : 'invalid_response';
      throw error;
    }
    return result.state;
  };

  const scheduleText = (type) => {
    if (type.schedule_mode === 'before_event' && Number.isInteger(type.lead_minutes)) {
      return `Se enviará ${type.lead_minutes} minutos antes del evento.`;
    }
    if (type.schedule_mode === 'fixed_time' && type.delivery_time) {
      return `Horario predeterminado: ${type.delivery_time}.`;
    }
    return 'La programación se define según el evento.';
  };

  const typeDescription = (type) => {
    const description = String(type.description || '').trim();
    const schedule = scheduleText(type);
    if (!description) return schedule;
    if ((Number.isInteger(type.lead_minutes) && description.includes(String(type.lead_minutes)))
      || (type.delivery_time && description.includes(String(type.delivery_time).slice(0, 5)))) {
      return description;
    }
    return `${description} ${schedule}`;
  };

  const renderTypes = (types) => {
    typesContainer.replaceChildren();
    types.forEach((type) => {
      const label = document.createElement('label');
      label.className = 'notification-settings__type';
      const copy = document.createElement('span');
      copy.className = 'notification-settings__type-copy';
      const name = document.createElement('strong');
      name.textContent = type.display_name;
      const description = document.createElement('small');
      description.textContent = typeDescription(type);
      copy.append(name, description);
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.name = `notification_preferences[${type.notification_type}]`;
      input.dataset.notificationType = type.notification_type;
      input.checked = type.enabled === true;
      const control = document.createElement('span');
      control.className = 'notification-settings__switch-control';
      control.setAttribute('aria-hidden', 'true');
      label.append(copy, input, control);
      if (type.notification_type === contextType) {
        label.classList.add('is-context-target');
        label.dataset.notificationContextTarget = '';
      }
      typesContainer.append(label);
    });
    if (types.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'notification-settings__message';
      empty.textContent = 'No hay tipos de aviso disponibles actualmente.';
      typesContainer.append(empty);
    }
    const target = typesContainer.querySelector('[data-notification-context-target]');
    if (target) {
      window.requestAnimationFrame(() => {
        target.scrollIntoView({behavior: 'smooth', block: 'center'});
        target.querySelector('input')?.focus({preventScroll: true});
      });
    }
  };

  const updateQuietHours = () => {
    const enabled = quietHoursInput?.checked === true;
    if (quietTimes) quietTimes.hidden = !enabled;
    setText(quietState, enabled ? 'Activado' : 'Desactivado');
  };

  const fillForm = (state) => {
    const device = state.device;
    if (!device && !locationReady) {
      setText(configState, 'Falta ubicación');
      setText(summaryState, 'Falta completar la ubicación');
      setText(saveStatus, 'Elegí una ubicación general para terminar la configuración de este dispositivo.');
      formCard.hidden = true;
      return;
    }
    form.elements.device_name.value = device?.device_name || state.suggested_device_name || 'Mi dispositivo';
    const currentSupportId = typeof device?.support_id === 'string' ? device.support_id : '';
    setText(supportId, currentSupportId);
    if (supportBlock) supportBlock.hidden = currentSupportId === '';
    if (copySupportId) copySupportId.dataset.supportId = currentSupportId;
    form.elements.notifications_enabled.checked = device ? device.notifications_enabled === true : true;
    form.elements.location_name.value = (!useGeneralLocation && device?.location_name) || root.dataset.defaultLocationName || '';
    form.elements.latitude.value = (!useGeneralLocation && device?.latitude != null) ? device.latitude : (root.dataset.defaultLatitude ?? '');
    form.elements.longitude.value = (!useGeneralLocation && device?.longitude != null) ? device.longitude : (root.dataset.defaultLongitude ?? '');
    form.elements.timezone.value = (!useGeneralLocation && device?.timezone)
      ? device.timezone
      : (root.dataset.defaultTimezone || '');
    setText(selectedLocation, form.elements.location_name.value);
    form.elements.quiet_hours_enabled.checked = device?.quiet_hours_enabled === true;
    form.elements.quiet_start_local.value = device?.quiet_start_local || '23:00';
    form.elements.quiet_end_local.value = device?.quiet_end_local || '08:00';
    updateQuietHours();
    renderTypes(Array.isArray(state.notification_types) ? state.notification_types : []);
    setText(configState, state.configured ? 'Configurada' : 'Falta completar');
    setText(summaryState, state.configured ? 'Activas' : 'Suscripción activa');
    root.classList.toggle('is-ready', state.configured === true);
    if (state.configured) {
      setText(pushHeading, 'Notificaciones activadas');
      if (pushStepLabel) pushStepLabel.hidden = true;
      if (pushReadyCopy) pushReadyCopy.hidden = false;
    }
    setText(lastSaved, device?.updated_at ? `Último guardado: ${device.updated_at}` : 'Todavía sin guardar');
    if (!state.configured) {
      setText(saveStatus, 'Este dispositivo todavía no tiene configuración. Completá las preferencias para guardarla.');
    }
    formCard.hidden = false;
  };

  const load = async () => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      setText(configState, 'No compatible');
      setText(summaryState, 'Web Push no compatible');
      return;
    }
    if (Notification.permission !== 'granted') {
      setText(configState, Notification.permission === 'denied' ? 'Permiso denegado' : 'Activá las notificaciones');
      setText(summaryState, Notification.permission === 'denied' ? 'Permiso bloqueado' : 'Pendiente de activación');
      return;
    }
    try {
      if (!serviceWorkerTools?.ensureActiveServiceWorker) {
        throw new Error('No se pudo preparar el controlador de notificaciones.');
      }
      setText(configState, 'Activando servicio…');
      const {registration} = await serviceWorkerTools.ensureActiveServiceWorker();
      currentSubscription = await registration.pushManager.getSubscription();
      if (!currentSubscription) {
        setText(configState, 'Sin suscripción');
        setText(summaryState, 'Permiso concedido');
        setText(saveStatus, 'Permiso concedido. Falta activar el servicio de notificaciones.');
        return;
      }
      fillForm(await request('read', currentSubscription));
    } catch (error) {
      const diagnostic = {
        httpStatus: Number.isInteger(error?.httpStatus) ? error.httpStatus : 0,
        code: typeof error?.logicalCode === 'string' ? error.logicalCode : 'client_error',
      };
      console.error('Web Push settings load failed:', diagnostic);
      if (diagnostic.code === 'subscription_not_active') {
        setText(configState, 'Reactivación necesaria');
        setText(summaryState, 'Suscripción desactualizada');
        setText(saveStatus, 'La suscripción guardada por este navegador ya no está activa. Volvé a activar las notificaciones en este dispositivo.');
      } else {
        setText(configState, 'No disponible');
        setText(summaryState, 'No pudimos cargarla');
        setText(saveStatus, 'No se pudo cargar la configuración. Recargá la página e intentá nuevamente.');
      }
    }
  };

  quietHoursInput?.addEventListener('change', updateQuietHours);

  copySupportId?.addEventListener('click', async () => {
    const value = copySupportId.dataset.supportId || '';
    if (!value) return;
    try {
      await navigator.clipboard.writeText(value);
      setText(copySupportId, 'Copiado');
      window.setTimeout(() => setText(copySupportId, 'Copiar'), 1600);
    } catch (error) {
      console.error('Notification support ID copy failed:', {code: 'clipboard_unavailable'});
      setText(saveStatus, `No se pudo copiar automáticamente. ID: ${value}`);
    }
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!currentSubscription || !form.reportValidity()) return;
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    setText(saveStatus, 'Guardando…');
    const preferences = {};
    form.querySelectorAll('[data-notification-type]').forEach((input) => {
      if (input.checked) preferences[input.dataset.notificationType] = true;
    });
    const config = {
      device_name: form.elements.device_name.value,
      notifications_enabled: form.elements.notifications_enabled.checked,
      location_name: form.elements.location_name.value,
      latitude: form.elements.latitude.value,
      longitude: form.elements.longitude.value,
      timezone: form.elements.timezone.value,
      quiet_hours_enabled: form.elements.quiet_hours_enabled.checked,
      quiet_start_local: form.elements.quiet_start_local.value,
      quiet_end_local: form.elements.quiet_end_local.value,
      notification_preferences: preferences,
    };
    try {
      const state = await request('save', currentSubscription, config);
      useGeneralLocation = false;
      if (root.dataset.locationChangeRequested === 'true') {
        root.dataset.locationChangeRequested = 'false';
        window.history.replaceState(null, '', window.location.pathname);
      }
      fillForm(state);
      setText(saveStatus, 'Preferencias guardadas. Los próximos avisos serán procesados por el sistema.');
    } catch (error) {
      console.error('Web Push settings save failed:', {
        httpStatus: Number.isInteger(error?.httpStatus) ? error.httpStatus : 0,
        code: typeof error?.logicalCode === 'string' ? error.logicalCode : 'client_error',
      });
      setText(saveStatus, error instanceof Error ? error.message : 'No pudimos guardar las preferencias.');
    } finally {
      submit.disabled = false;
    }
  });

  load();
})();
