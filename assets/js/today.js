document.addEventListener('DOMContentLoaded', () => {
  const dateToggle = document.querySelector('.today-date-toggle');
  const dateDialog = document.querySelector('[data-today-date-dialog]');
  dateToggle?.addEventListener('click', () => {
    if (!dateDialog?.showModal) return;
    dateDialog.showModal();
    dateDialog.querySelector('input')?.focus();
  });
  dateDialog?.querySelector('[data-today-date-close]')?.addEventListener('click', () => dateDialog.close());
  dateDialog?.addEventListener('click', (event) => {
    if (event.target === dateDialog) dateDialog.close();
  });

  const activateTab = (tab) => {
    const list = tab.closest('[role="tablist"]');
    if (!list) return;
    list.querySelectorAll('[role="tab"]').forEach((item) => {
      const selected = item === tab;
      item.setAttribute('aria-selected', String(selected));
      const panel = document.getElementById(item.getAttribute('aria-controls'));
      if (panel) panel.hidden = !selected;
    });
  };
  document.querySelectorAll('[role="tablist"] [role="tab"]').forEach((tab) => {
    tab.addEventListener('click', () => activateTab(tab));
    tab.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
      const tabs = [...tab.closest('[role="tablist"]').querySelectorAll('[role="tab"]')];
      const next = tabs[(tabs.indexOf(tab) + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
      activateTab(next);
      next.focus();
    });
  });

  const dialog = document.querySelector('[data-moon-detail-dialog]');
  document.querySelector('[data-moon-detail-open]')?.addEventListener('click', () => dialog?.showModal());
  dialog?.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });

  const loadConditions = async () => {
    const section = document.querySelector('[data-today-conditions]');
    const cloud = globalThis.AstronomyCloudCover;
    if (!section || !cloud) return;
    const latitude = Number(document.body.dataset.cloudCoverLatitude);
    const longitude = Number(document.body.dataset.cloudCoverLongitude);
    const timezone = document.body.dataset.cloudCoverTimezone;
    const date = document.body.dataset.todayDate;
    const summary = section.querySelector('[data-today-cloud-summary]');
    try {
      const forecast = await cloud.requestForecast({ latitude, longitude, timezone, cacheScope: date });
      const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', hourCycle: 'h23',
      });
      const points = forecast.times.map((time, index) => {
        const parts = Object.fromEntries(formatter.formatToParts(new Date(time)).filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));
        return { time, date: `${parts.year}-${parts.month}-${parts.day}`, hour: parts.hour, cloud: forecast.values[index] };
      }).filter((point) => point.date === date && Number.isFinite(point.cloud));
      if (points.length === 0) throw new Error('date unavailable');
      const average = Math.round(points.reduce((total, point) => total + point.cloud, 0) / points.length);
      summary.textContent = `Nubosidad media prevista: ${average} %.`;
      const summaryCloud = document.querySelector('[data-today-summary-cloud]');
      if (summaryCloud) {
        summaryCloud.textContent = `Nubosidad general prevista: ${average} %.`;
        summaryCloud.hidden = false;
      }
      const hours = section.querySelector('[data-today-cloud-hours]');
      hours.replaceChildren(...points.filter((_, index) => index % 3 === 0).map((point) => {
        const item = document.createElement('span');
        item.innerHTML = `<strong>${point.hour}:00</strong><small>${Math.round(point.cloud)} %</small>`;
        return item;
      }));
      hours.hidden = false;
      let intervals = [];
      try { intervals = JSON.parse(document.body.dataset.moonIntervals || '[]'); } catch (_) {}
      const visible = points.filter((point) => intervals.some((interval) => {
        const start = Date.parse(interval.start || '');
        const end = Date.parse(interval.end || '');
        return point.time >= start && point.time <= end;
      }));
      const best = (visible.length ? visible : points).reduce((current, point) => point.cloud < current.cloud ? point : current);
      const recommendation = section.querySelector('[data-today-cloud-recommendation]');
      recommendation.textContent = visible.length
        ? `La menor nubosidad mientras la Luna esté sobre el horizonte se prevé cerca de las ${best.hour}:00 (${Math.round(best.cloud)} %).`
        : `La menor nubosidad del día se prevé cerca de las ${best.hour}:00 (${Math.round(best.cloud)} %).`;
      recommendation.hidden = false;
    } catch (_) {
      summary.textContent = 'El pronóstico de nubosidad no está disponible para esta fecha.';
      document.querySelector('[data-today-summary-cloud]')?.remove();
    }
  };
  loadConditions();
});
