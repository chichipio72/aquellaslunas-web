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
  const geolocationButton = root.querySelector('[data-use-current-location]');
  const geolocationStatus = root.querySelector('[data-geolocation-status]');
  const iosHelp = root.querySelector('[data-ios-pwa-help]');
  const configUrl = root.dataset.deviceConfigUrl || '';
  const csrfToken = root.dataset.deviceCsrf || '';
  const locationReady = root.dataset.locationReady === 'true';
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
      throw new Error(typeof result.message === 'string' ? result.message : 'No pudimos cargar la configuración.');
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
      description.textContent = `${type.description || ''} ${scheduleText(type)}`.trim();
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
      typesContainer.append(label);
    });
    if (types.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'notification-settings__message';
      empty.textContent = 'No hay tipos de aviso disponibles actualmente.';
      typesContainer.append(empty);
    }
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
    form.elements.notifications_enabled.checked = device ? device.notifications_enabled === true : true;
    form.elements.location_name.value = device?.location_name || root.dataset.defaultLocationName || '';
    form.elements.latitude.value = device?.latitude ?? root.dataset.defaultLatitude ?? '';
    form.elements.longitude.value = device?.longitude ?? root.dataset.defaultLongitude ?? '';
    form.elements.timezone.value = device?.timezone
      || root.dataset.defaultTimezone
      || Intl.DateTimeFormat().resolvedOptions().timeZone
      || '';
    form.elements.quiet_hours_enabled.checked = device?.quiet_hours_enabled === true;
    form.elements.quiet_start_local.value = device?.quiet_start_local || '23:00';
    form.elements.quiet_end_local.value = device?.quiet_end_local || '08:00';
    renderTypes(Array.isArray(state.notification_types) ? state.notification_types : []);
    setText(configState, state.configured ? 'Configurada' : 'Falta completar');
    setText(summaryState, state.configured ? 'Lista para recibir avisos' : 'Suscripción activa');
    setText(lastSaved, device?.updated_at ? `Último guardado: ${device.updated_at}` : 'Todavía sin guardar');
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
      console.error('Web Push settings load failed:', error);
      setText(configState, 'No disponible');
      setText(summaryState, 'No pudimos cargarla');
      setText(saveStatus, 'No se pudo activar el servicio de notificaciones. Recargá la página e intentá nuevamente.');
    }
  };

  geolocationButton?.addEventListener('click', () => {
    if (!navigator.geolocation) {
      setText(geolocationStatus, 'Este navegador no permite obtener la ubicación. Podés ingresar las coordenadas manualmente.');
      return;
    }
    geolocationButton.disabled = true;
    setText(geolocationStatus, 'Obteniendo tu ubicación…');
    navigator.geolocation.getCurrentPosition((position) => {
      form.elements.latitude.value = position.coords.latitude.toFixed(6);
      form.elements.longitude.value = position.coords.longitude.toFixed(6);
      if (!form.elements.timezone.value) {
        form.elements.timezone.value = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
      }
      setText(geolocationStatus, `Coordenadas obtenidas: ${form.elements.latitude.value}, ${form.elements.longitude.value}. Escribí el nombre del lugar si hace falta.`);
      geolocationButton.disabled = false;
    }, (error) => {
      const denied = error.code === error.PERMISSION_DENIED;
      setText(geolocationStatus, denied
        ? 'No se concedió acceso a la ubicación. Podés completar los datos manualmente.'
        : 'No pudimos obtener la ubicación. Revisá los datos manualmente.');
      geolocationButton.disabled = false;
    }, {enableHighAccuracy: false, timeout: 12000, maximumAge: 300000});
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
      fillForm(state);
      setText(saveStatus, 'Preferencias guardadas. Los próximos avisos serán procesados por el sistema.');
    } catch (error) {
      setText(saveStatus, error instanceof Error ? error.message : 'No pudimos guardar las preferencias.');
    } finally {
      submit.disabled = false;
    }
  });

  load();
})();
