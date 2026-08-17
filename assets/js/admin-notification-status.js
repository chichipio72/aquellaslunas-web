(() => {
  'use strict';

  const escapeText = (value) => value == null || value === '' ? '—' : String(value);
  const localTime = () => new Intl.DateTimeFormat('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date());
  const dateTime = (value, timeZone, withSeconds = false) => {
    if (!value) return '—';
    const parsed = new Date(String(value).replace(' ', 'T') + (String(value).includes('T') ? '' : 'Z'));
    if (Number.isNaN(parsed.getTime())) return String(value);
    try {
      return new Intl.DateTimeFormat('es-AR', {
        year: withSeconds ? 'numeric' : undefined, day: '2-digit', month: '2-digit',
        hour: '2-digit', minute: '2-digit', second: withSeconds ? '2-digit' : undefined,
        hour12: false, timeZone: timeZone || 'UTC'
      }).format(parsed);
    } catch { return String(value); }
  };
  const createCell = (row, value, className = '', label = '') => {
    const cell = row.insertCell();
    if (className) cell.className = className;
    if (label) cell.dataset.label = label;
    cell.textContent = escapeText(value);
    return cell;
  };
  const setFeedback = (root, message, error = false) => {
    const feedback = root.querySelector('[data-refresh-feedback]');
    if (!feedback) return;
    feedback.textContent = message;
    feedback.classList.toggle('is-error', error);
  };
  const request = async (url, options) => {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, ...options });
    let payload;
    try { payload = await response.json(); } catch { payload = {}; }
    if (!response.ok) throw new Error(payload.error || 'No se pudo actualizar.');
    return payload;
  };

  const subscriptionHealthClass = { healthy: 'is-active', inactive: 'is-inactive', error: 'is-error', idle: 'is-pending' };
  const renderSubscriptions = (root, payload) => {
    const body = root.querySelector('[data-subscriptions-body]');
    if (!body) throw new Error('No se encontró la tabla administrativa de suscripciones.');
    if (!payload || !Array.isArray(payload.subscriptions)) {
      throw new Error('La respuesta administrativa no contiene una lista de suscripciones válida.');
    }
    const fragment = document.createDocumentFragment();
    payload.subscriptions.forEach((item) => {
      const row = document.createElement('tr');
      createCell(row, item.subscription_id);
      createCell(row, item.support_id);
      const stateCell = row.insertCell();
      const state = document.createElement('span');
      state.className = `push-admin__state ${subscriptionHealthClass[item.health?.code] || 'is-pending'}`;
      state.textContent = item.health?.label || 'Sin datos';
      stateCell.append(state);
      createCell(row, item.device_name || 'Sin nombre');
      createCell(row, item.created_at, 'push-test-admin__date');
      createCell(row, item.last_activity_at, 'push-test-admin__date');
      createCell(row, Number(item.active) === 1 ? 'Sí' : 'No');
      createCell(row, item.last_success_at, 'push-test-admin__date');
      const error = item.last_error_at ? `${item.last_error_at}${item.last_error_message ? ` · ${item.last_error_message}` : ''}` : '—';
      createCell(row, error, 'push-test-admin__date');
      createCell(row, item.user_agent || 'No informado');
      fragment.append(row);
    });
    body.replaceChildren(fragment);
  };

  const testLabels = { pending: 'Programada', processing: 'Procesando', sent: 'Enviada', failed: 'Fallida', skipped_quiet_hours: 'Omitida por No molestar', skipped_unavailable: 'Tipo no disponible', expired: 'Vencida', cancelled: 'Cancelada', success: 'Correcto', running: 'En ejecución' };
  const renderTests = (root, payload) => {
    const body = root.querySelector('[data-tests-body]');
    if (!body) return;
    const csrf = root.dataset.csrfToken || '';
    const subscriptionId = root.dataset.subscriptionId || '';
    const fragment = document.createDocumentFragment();
    payload.tests.forEach((item) => {
      const row = document.createElement('tr');
      createCell(row, dateTime(item.scheduled_at_utc, root.dataset.deviceTimezone, true), '', 'Programada');
      createCell(row, Number(item.respect_quiet_hours) === 1 ? 'Sí' : 'No', '', 'No molestar');
      createCell(row, testLabels[item.status] || item.status, '', 'Estado');
      createCell(row, dateTime(item.processed_at, root.dataset.deviceTimezone, true), '', 'Procesada');
      createCell(row, item.error_message || (item.notification_log_id ? `Log #${item.notification_log_id}` : '—'), '', 'Resultado');
      const action = row.insertCell();
      action.dataset.label = 'Acción';
      if (item.status === 'pending') {
        const form = document.createElement('form');
        form.dataset.notificationAction = '';
        [['csrf_token', csrf], ['subscription_id', subscriptionId], ['action', 'cancel_test'], ['test_id', item.id]].forEach(([name, value]) => {
          const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input);
        });
        const button = document.createElement('button'); button.type = 'submit'; button.className = 'button compact-secondary-button'; button.textContent = 'Cancelar'; form.append(button); action.append(form);
      } else action.textContent = '—';
      fragment.append(row);
    });
    body.replaceChildren(fragment);
  };
  const renderLogs = (root, payload) => {
    const body = root.querySelector('[data-logs-body]');
    if (!body) return;
    const fragment = document.createDocumentFragment();
    payload.logs.forEach((item) => {
      const row = document.createElement('tr');
      createCell(row, item.display_name || item.notification_type, '', 'Tipo');
      createCell(row, dateTime(item.attempted_at, root.dataset.deviceTimezone), '', 'Momento');
      createCell(row, testLabels[item.status] || item.status, '', 'Estado');
      createCell(row, item.decision_reason || '—', '', 'Decisión');
      const resultCell = createCell(row, item.sent_at ? `Entregada · ${dateTime(item.sent_at, root.dataset.deviceTimezone)}` : (item.error_message || '—'), '', 'Resultado');
      const details = document.createElement('details');
      details.className = 'astronomy-notifications-admin__row-details';
      const summary = document.createElement('summary');
      summary.textContent = 'Detalles';
      const list = document.createElement('dl');
      [['Evento', item.event_key], ['Evento UTC', item.event_time_utc], ['Aviso UTC', item.notification_time_utc], ['Título', item.title_sent], ['Mensaje', item.body_sent], ['URL', item.target_url_sent]].forEach(([label, value]) => {
        const wrapper = document.createElement('div');
        const term = document.createElement('dt'); term.textContent = label;
        const description = document.createElement('dd'); description.textContent = escapeText(value);
        wrapper.append(term, description); list.append(wrapper);
      });
      details.append(summary, list); resultCell.append(details);
      fragment.append(row);
    });
    body.replaceChildren(fragment);
  };
  const renderScheduler = (root, payload) => {
    Object.entries(payload.scheduler || {}).forEach(([key, value]) => {
      const target = root.querySelector(`[data-scheduler-${key.replaceAll('_', '-')}]`);
      if (target) target.textContent = key === 'status' ? (value === 'failed' ? 'Error' : (testLabels[value] || escapeText(value)))
        : (key.startsWith('last_') && key.endsWith('_at') ? dateTime(value, root.dataset.deviceTimezone) : escapeText(value));
    });
    root.querySelector('[data-scheduler-block]')?.classList.toggle('has-error', payload.scheduler?.status === 'failed');
  };

  document.querySelectorAll('[data-live-admin-status]').forEach((root) => {
    const button = root.querySelector('[data-refresh-button]');
    let busy = false;
    const refresh = async () => {
      if (busy) return;
      busy = true;
      if (button) button.disabled = true;
      setFeedback(root, 'Actualizando…');
      try {
        const payload = await request(root.dataset.statusUrl);
        if (root.dataset.statusKind === 'subscriptions') renderSubscriptions(root, payload);
        else { renderScheduler(root, payload); renderTests(root, payload); renderLogs(root, payload); }
        setFeedback(root, `Actualizado: ${localTime()}`);
      } catch (error) {
        console.error('Admin status refresh failed:', error);
        setFeedback(root, error instanceof Error ? error.message : 'No se pudo actualizar.', true);
      } finally { busy = false; if (button) button.disabled = false; }
    };
    button?.addEventListener('click', refresh);
    const quietToggle = root.querySelector('[data-quiet-toggle]');
    quietToggle?.addEventListener('change', () => {
      const times = root.querySelector('[data-quiet-times]');
      const summary = root.querySelector('[data-quiet-summary]');
      if (times) times.hidden = !quietToggle.checked;
      if (summary) summary.textContent = quietToggle.checked ? (summary.dataset.activeSummary || 'Activo') : 'Desactivado';
    });
    root.addEventListener('submit', async (event) => {
      const form = event.target.closest('[data-notification-action]');
      if (!form || busy) return;
      event.preventDefault();
      busy = true;
      const submit = form.querySelector('[type="submit"]');
      if (submit) submit.disabled = true;
      setFeedback(root, 'Guardando…');
      try {
        const result = await request(root.dataset.statusUrl, { method: 'POST', body: new FormData(form) });
        busy = false;
        await refresh();
        if (result.action_result?.message) setFeedback(root, `${result.action_result.message} Actualizado: ${localTime()}`);
      } catch (error) {
        console.error('Admin notification action failed:', error);
        setFeedback(root, error instanceof Error ? error.message : 'No se pudo completar la acción.', true);
      } finally { busy = false; if (submit) submit.disabled = false; }
    });
  });
})();
