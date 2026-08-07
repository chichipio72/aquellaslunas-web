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
  const locationRequired = root.dataset.requiresLocation === 'true';
  const locationReady = !locationRequired || root.dataset.locationReady === 'true';
  const serviceWorkerSupported = 'serviceWorker' in navigator;
  const pushSupported = serviceWorkerSupported && 'PushManager' in window && 'Notification' in window;
  const workerActivationTimeoutMs = 15000;
  const controllerTimeoutMs = 4000;
  const reloadMarker = 'aquellas_lunas_push_worker_reload';
  let activationInProgress = false;
  let activeWorkerPromise = null;

  const permissionLabel = () => ({default: 'Pendiente', granted: 'Concedido', denied: 'Denegado'}[Notification.permission] || 'Desconocido');

  const setStatus = (message) => {
    if (status) status.textContent = message;
  };

  const setSubscriptionState = (active) => {
    if (subscriptionState) subscriptionState.textContent = active ? 'Activa' : 'Inactiva';
    root.classList.toggle('is-active', active);
    if (action) action.textContent = active ? 'Notificaciones activadas' : 'Activar notificaciones en este dispositivo';
    if (deactivateAction) deactivateAction.disabled = !active;
    if (action && !active) {
      action.disabled = !locationReady;
      action.setAttribute('aria-disabled', locationReady ? 'false' : 'true');
    }
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

  const timeoutError = () => new Error('El Service Worker no se activó dentro del tiempo esperado.');

  const waitForActiveWorker = (registration, timeoutMs = workerActivationTimeoutMs) => new Promise((resolve, reject) => {
    if (registration.active?.state === 'activated') {
      resolve(registration);
      return;
    }
    let worker = registration.installing || registration.waiting || registration.active;
    let finished = false;
    let timer = null;
    const finish = (callback, value) => {
      if (finished) return;
      finished = true;
      if (timer !== null) window.clearTimeout(timer);
      worker?.removeEventListener('statechange', checkState);
      registration.removeEventListener('updatefound', handleUpdateFound);
      callback(value);
    };
    const checkState = () => {
      if (registration.active?.state === 'activated' || worker?.state === 'activated') {
        finish(resolve, registration);
      } else if (worker?.state === 'redundant') {
        finish(reject, new Error('El Service Worker quedó inactivo durante la instalación.'));
      }
    };
    const watchWorker = (candidate) => {
      worker?.removeEventListener('statechange', checkState);
      worker = candidate;
      worker?.addEventListener('statechange', checkState);
      checkState();
    };
    const handleUpdateFound = () => watchWorker(registration.installing || registration.waiting || registration.active);
    timer = window.setTimeout(() => finish(reject, timeoutError()), timeoutMs);
    registration.addEventListener('updatefound', handleUpdateFound);
    watchWorker(worker);
  });

  const waitForController = (timeoutMs = controllerTimeoutMs) => new Promise((resolve) => {
    if (navigator.serviceWorker.controller) {
      resolve(true);
      return;
    }
    let finished = false;
    const finish = (controlled) => {
      if (finished) return;
      finished = true;
      window.clearTimeout(timer);
      navigator.serviceWorker.removeEventListener('controllerchange', handleControllerChange);
      resolve(controlled);
    };
    const handleControllerChange = () => finish(navigator.serviceWorker.controller !== null);
    const timer = window.setTimeout(() => finish(false), timeoutMs);
    navigator.serviceWorker.addEventListener('controllerchange', handleControllerChange);
  });

  const ensureActiveServiceWorker = async () => {
    if (!serviceWorkerSupported) throw new Error('Este navegador no admite Service Worker.');
    if (!activeWorkerPromise) {
      activeWorkerPromise = (async () => {
        const registration = await navigator.serviceWorker.register(workerUrl, {scope: workerScope});
        await waitForActiveWorker(registration);
        if (!registration.active || registration.active.state !== 'activated') throw timeoutError();
        const controlled = navigator.serviceWorker.controller !== null || await waitForController();
        return {registration, controlled};
      })().catch((error) => {
        activeWorkerPromise = null;
        throw error;
      });
    }
    return activeWorkerPromise;
  };

  window.AstronomyPushServiceWorker = Object.freeze({ensureActiveServiceWorker});
  const reloadPanel = () => window.setTimeout(() => window.location.reload(), 650);

  const currentSubscription = async () => {
    if (!pushSupported || Notification.permission !== 'granted') return null;
    const {registration} = await ensureActiveServiceWorker();
    return registration.pushManager.getSubscription();
  };

  const activate = async () => {
    if (!locationReady) {
      setStatus('Elegí una ubicación para continuar.');
      return;
    }
    if (!pushSupported) {
      setStatus('Este navegador no admite notificaciones Web Push.');
      return;
    }
    if (Notification.permission === 'denied') {
      setStatus('Las notificaciones están bloqueadas. Podés habilitarlas desde la configuración del navegador.');
      return;
    }

    if (activationInProgress) return;
    activationInProgress = true;
    let reloadPending = false;
    action.disabled = true;
    try {
      setStatus('Activando el servicio de notificaciones…');
      const {registration, controlled} = await ensureActiveServiceWorker();
      if (!registration.active || registration.active.state !== 'activated') throw timeoutError();
      if (!controlled) {
        if (sessionStorage.getItem(reloadMarker) !== '1') {
          sessionStorage.setItem(reloadMarker, '1');
          setStatus('El servicio quedó activo. Recargando una vez para terminar la preparación…');
          reloadPending = true;
          reloadPanel();
          return;
        }
        throw new Error('El Service Worker está activo, pero todavía no controla esta página.');
      }
      sessionStorage.removeItem(reloadMarker);
      setStatus(Notification.permission === 'granted'
        ? 'Permiso concedido. Creando la suscripción…'
        : 'Solicitando permiso para mostrar notificaciones…');
      const permission = Notification.permission === 'granted' ? 'granted' : await Notification.requestPermission();
      updateCapabilityState();
      if (permission !== 'granted') {
        setSubscriptionState(false);
        setStatus(permission === 'denied' ? 'Las notificaciones quedaron bloqueadas en el navegador.' : 'No se activaron las notificaciones.');
        return;
      }
      setStatus('Creando la suscripción…');
      let subscription = await registration.pushManager.getSubscription();
      if (!subscription) {
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(publicKey),
        });
      }
      await jsonRequest(subscribeUrl, subscription.toJSON());
      setSubscriptionState(true);
      setStatus('Notificaciones activadas en este dispositivo.');
      reloadPending = true;
      reloadPanel();
    } catch (error) {
      console.error('Web Push activation failed:', error);
      const technicalMessage = error instanceof Error ? error.message : '';
      const workerFailure = /active Service Worker|Service Worker|activ/i.test(technicalMessage);
      setStatus(workerFailure
        ? 'No se pudo activar el servicio de notificaciones. Recargá la página e intentá nuevamente.'
        : 'No pudimos activar las notificaciones. Intentá nuevamente.');
    } finally {
      if (!reloadPending) {
        activationInProgress = false;
        action.disabled = !locationReady;
      }
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

  if (!locationReady) {
    action.disabled = true;
    action.setAttribute('aria-disabled', 'true');
    setStatus('Elegí una ubicación para continuar.');
  }

  if (sessionStorage.getItem(reloadMarker) === '1' && navigator.serviceWorker.controller) {
    sessionStorage.removeItem(reloadMarker);
    setStatus('El servicio de notificaciones está listo. Podés continuar con la activación.');
  }

  if (!pushSupported) {
    if (action) action.disabled = true;
    setSubscriptionState(false);
    setStatus('Este navegador no admite notificaciones Web Push.');
  } else if (Notification.permission === 'denied') {
    setSubscriptionState(false);
    setStatus('Las notificaciones están bloqueadas. Podés habilitarlas desde la configuración del navegador.');
  } else if (Notification.permission === 'granted') {
    currentSubscription()
      .then((subscription) => {
        setSubscriptionState(subscription !== null);
        if (subscription === null) {
          setStatus(locationReady
            ? 'Permiso concedido. Falta activar el servicio de notificaciones.'
            : 'Elegí una ubicación para continuar.');
        }
      })
      .catch(() => {
        setSubscriptionState(false);
        setStatus('No pudimos comprobar la suscripción de este dispositivo.');
      });
  } else {
    setSubscriptionState(false);
  }
})();
