document.addEventListener('DOMContentLoaded', () => {
  const hasApiError = () => document.body.dataset.apiState === 'error'
    || document.querySelector('[data-api-error]') !== null;
  let hiddenAt = null;
  let lastReloadAt = 0;

  const retry = () => {
    const now = Date.now();
    if (now - lastReloadAt < 10000) return;
    lastReloadAt = now;

    const recoveryUrl = new URL(window.location.href);
    recoveryUrl.searchParams.delete('_astro_recovery');
    recoveryUrl.searchParams.set('_astro_recovery', String(now));
    window.location.replace(recoveryUrl.href);
  };

  document.addEventListener('click', (event) => {
    if (event.target.closest?.('[data-api-retry]')) retry();
  });

  window.addEventListener('pageshow', (event) => {
    document.dispatchEvent(new CustomEvent('astronomy:page-visible'));
    if (event.persisted && hasApiError()) retry();
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      hiddenAt = Date.now();
      return;
    }
    document.dispatchEvent(new CustomEvent('astronomy:page-visible'));
    if (hasApiError() && hiddenAt !== null && Date.now() - hiddenAt >= 5000) retry();
    hiddenAt = null;
  });
});
