'use strict';

self.addEventListener('push', (event) => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (error) {
    payload = {};
  }

  event.waitUntil(self.registration.showNotification(payload.title || 'Aquellas Lunas', {
    body: payload.body || 'Tenés una nueva notificación.',
    icon: 'assets/images/favicon/icon-192.png',
    badge: 'assets/images/favicon/favicon-32x32.png',
    data: {url: payload.url || './'},
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = new URL(event.notification.data?.url || './', self.registration.scope).href;
  event.waitUntil((async () => {
    const windows = await clients.matchAll({type: 'window', includeUncontrolled: true});
    for (const client of windows) {
      if (new URL(client.url).origin === new URL(targetUrl).origin) {
        await client.navigate(targetUrl);
        return client.focus();
      }
    }
    return clients.openWindow(targetUrl);
  })());
});
