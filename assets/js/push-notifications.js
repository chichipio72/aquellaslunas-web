(() => {
  'use strict';

  const root = document.querySelector('[data-push-notifications]');
  if (!root) return;

  const action = root.querySelector('[data-push-action]');
  const deactivateAction = root.querySelector('[data-push-deactivate]');
  const status = root.querySelector('[data-push-status]');
  const workerSupport = root.querySelector('[data-push-support-worker]');
  const apiSupport = root.querySelector('[data-push-support-api]');
  const permissionState = root.querySelector('[data-push-permission]');
  const subscriptionState = root.querySelector('[data-push-subscription]');
  const publicKey = root.dataset.pushPublicKey || '';
  const workerUrl = root.dataset.pushWorkerUrl || '';
  const workerScope = root.dataset.pushWorkerScope || './';
  const subscribeUrl = root.dataset.pushSubscribeUrl || '';
  const unsubscribeUrl = root.dataset.pushUnsubscribeUrl || '';
  const serviceWorkerSupported = 'serviceWorker' in navigator;
  const pushSupported = serviceWorkerSupported && 'PushManager' in window && 'Notification' in window;

  const permissionLabel = () => ({default: 'Pendiente', granted: 'Concedido', denied: 'Denegado'}[Notification.permission] || 'Desconocido');

  const setStatus = (message) => {
    if (status) status.textContent = message;
  };

  const setSubscriptionState = (active) => {
    if (subscriptionState) subscriptionState.textContent = active ? 'Activa' : 'Inactiva';
    root.classList.toggle('is-active', active);
    if (action) action.textContent = active ? 'Notificaciones activadas' : 'Activar notificaciones en este dispositivo';
    if (deactivateAction) deactivateAction.disabled = !active;
  };

  const updateCapabilityState = () => {
    if (workerSupport) workerSupport.textContent = serviceWorkerSupported ? 'Compatible' : 'No compatible';
    if (apiSupport) apiSupport.textContent = pushSupported ? 'Compatible' : 'No compatible';
    if (permissionState) permissionState.textContent = pushSupported ? permissionLabel() : 'No disponible';
  };

  const urlBase64ToUint8Array = (value) => {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return Uint8Array.from(raw, (character) => character.charCodeAt(0));
  };

  const jsonRequest = async (url, body) => {
    const response = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok !== true) {
      throw new Error(typeof result.message === 'string' ? result.message : 'No pudimos actualizar la suscripción.');
    }
  };

  const registration = () => navigator.serviceWorker.register(workerUrl, {scope: workerScope});
  const reloadPanel = () => window.setTimeout(() => window.location.reload(), 650);

  const currentSubscription = async () => {
    if (!pushSupported || Notification.permission !== 'granted') return null;
    const worker = await registration();
    return worker.pushManager.getSubscription();
  };

  const activate = async () => {
    if (!pushSupported) {
      setStatus('Este navegador no admite notificaciones Web Push.');
      return;
    }
    if (Notification.permission === 'denied') {
      setStatus('Las notificaciones están bloqueadas. Podés habilitarlas desde la configuración del navegador.');
      return;
    }

    action.disabled = true;
    setStatus('Preparando las notificaciones…');
    try {
      const permission = Notification.permission === 'granted' ? 'granted' : await Notification.requestPermission();
      updateCapabilityState();
      if (permission !== 'granted') {
        setSubscriptionState(false);
        setStatus(permission === 'denied' ? 'Las notificaciones quedaron bloqueadas en el navegador.' : 'No se activaron las notificaciones.');
        return;
      }
      const worker = await registration();
      let subscription = await worker.pushManager.getSubscription();
      if (!subscription) {
        subscription = await worker.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(publicKey),
        });
      }
      await jsonRequest(subscribeUrl, subscription.toJSON());
      setSubscriptionState(true);
      setStatus('Notificaciones activadas en este dispositivo.');
      reloadPanel();
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'No pudimos activar las notificaciones.');
    } finally {
      action.disabled = false;
    }
  };

  const deactivate = async () => {
    deactivateAction.disabled = true;
    setStatus('Desactivando las notificaciones…');
    try {
      const subscription = await currentSubscription();
      if (subscription) {
        await jsonRequest(unsubscribeUrl, {endpoint: subscription.endpoint});
        await subscription.unsubscribe();
      }
      setSubscriptionState(false);
      setStatus('Notificaciones desactivadas en este dispositivo.');
      reloadPanel();
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'No pudimos desactivar las notificaciones.');
      deactivateAction.disabled = false;
    }
  };

  action?.addEventListener('click', activate);
  deactivateAction?.addEventListener('click', deactivate);
  updateCapabilityState();

  if (!pushSupported) {
    if (action) action.disabled = true;
    setSubscriptionState(false);
    setStatus('Este navegador no admite notificaciones Web Push.');
  } else if (Notification.permission === 'denied') {
    setSubscriptionState(false);
    setStatus('Las notificaciones están bloqueadas. Podés habilitarlas desde la configuración del navegador.');
  } else if (Notification.permission === 'granted') {
    currentSubscription()
      .then((subscription) => setSubscriptionState(subscription !== null))
      .catch(() => {
        setSubscriptionState(false);
        setStatus('No pudimos comprobar la suscripción de este dispositivo.');
      });
  } else {
    setSubscriptionState(false);
  }
})();
