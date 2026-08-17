(() => {
  'use strict';

  const links = Array.from(document.querySelectorAll('[data-home-notification-link]'));
  if (links.length === 0) return;

  const updateLink = (link, active) => {
    const category = link.dataset.notificationLabel || 'esta categoría';
    const state = active ? 'active' : 'inactive';
    const text = active
      ? `Configurar avisos de ${category} · actualmente activados`
      : `Activar o configurar avisos de ${category} · actualmente desactivados`;
    link.dataset.notificationState = state;
    link.setAttribute('aria-label', text);
    link.title = text;
  };

  const loadState = async () => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)
      || Notification.permission !== 'granted') return;
    const configUrl = document.body.dataset.notificationConfigUrl || '';
    const csrfToken = document.body.dataset.notificationCsrf || '';
    if (!configUrl || !csrfToken) return;

    try {
      const registration = await navigator.serviceWorker.getRegistration('./');
      const subscription = await registration?.pushManager.getSubscription();
      if (!subscription) return;
      const response = await fetch(configUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({action: 'read', csrf_token: csrfToken, subscription: subscription.toJSON(), config: null}),
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || result.ok !== true || !result.state) return;
      const states = new Map((result.state.notification_types || []).map((type) => [type.notification_type, type.enabled === true]));
      links.forEach((link) => {
        if (states.has(link.dataset.notificationType)) updateLink(link, states.get(link.dataset.notificationType));
      });
    } catch (error) {
      console.debug('Notification category state unavailable.', {code: 'notification_state_unavailable'});
    }
  };

  loadState();
})();
