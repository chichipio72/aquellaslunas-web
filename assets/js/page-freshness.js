document.addEventListener('DOMContentLoaded', () => {
  const notice = document.querySelector('[data-page-freshness]');
  if (!(notice instanceof HTMLElement)) return;

  const thresholdSeconds = Number(notice.dataset.pageFreshnessThresholdSeconds);
  if (!Number.isFinite(thresholdSeconds) || thresholdSeconds <= 0) return;

  const effectiveClock = () => {
    const context = globalThis.siteTimeContext;
    if (context?.simulated) {
      const simulated = Date.parse(context.now || '');
      return Number.isFinite(simulated) ? { now: simulated, simulated: true } : null;
    }
    return { now: Date.now(), simulated: false };
  };

  const initialClock = effectiveClock();
  if (initialClock === null) return;
  notice.dataset.pageLoadedAt = String(initialClock.now);
  notice.dataset.pageLoadedWithSimulatedClock = initialClock.simulated ? 'true' : 'false';

  const checkFreshness = () => {
    const currentClock = effectiveClock();
    const loadedAt = Number(notice.dataset.pageLoadedAt);
    const loadedWithSimulatedClock = notice.dataset.pageLoadedWithSimulatedClock === 'true';
    if (currentClock === null || !Number.isFinite(loadedAt)) return;
    if (currentClock.simulated !== loadedWithSimulatedClock) {
      notice.dataset.pageLoadedAt = String(currentClock.now);
      notice.dataset.pageLoadedWithSimulatedClock = currentClock.simulated ? 'true' : 'false';
      notice.hidden = true;
      return;
    }
    if (currentClock.now - loadedAt > thresholdSeconds * 1000) notice.hidden = false;
  };

  checkFreshness();
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') checkFreshness();
  });
  window.addEventListener('pageshow', checkFreshness);
  notice.querySelector('[data-page-freshness-reload]')?.addEventListener('click', () => window.location.reload());
});
