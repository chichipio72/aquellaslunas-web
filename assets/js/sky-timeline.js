document.addEventListener('DOMContentLoaded', () => {
  const rows = document.querySelectorAll('.timeline-row');

  const parseTimestamp = (value) => {
    if (typeof value !== 'string' || value === '') {
      return null;
    }

    const match = value.match(/^(?:(\d{4})-(\d{2})-(\d{2})T)?(\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:\d{2})?$/);
    if (!match) {
      return null;
    }

    const [, year, month, day, hours, minutes] = match;
    return {
      date: year ? Date.UTC(Number(year), Number(month) - 1, Number(day)) : null,
      minutes: Number(hours) * 60 + Number(minutes),
    };
  };

  const parseIntervals = (value, rowId, attributeName) => {
    if (!value) {
      console.error(`Cielo del día: falta ${attributeName} en ${rowId}.`);
      return [];
    }

    try {
      const parsed = JSON.parse(value);
      if (!Array.isArray(parsed)) {
        console.error(`Cielo del día: ${attributeName} no contiene una lista en ${rowId}.`);
        return [];
      }
      return parsed;
    } catch (error) {
      console.error(`Cielo del día: JSON inválido en ${attributeName} de ${rowId}.`, error);
      return [];
    }
  };

  const renderTimeline = (container, sunIntervals, moonIntervals) => {
    const legend = document.createElement('div');
    legend.setAttribute('class', 'timeline-legend');
    legend.setAttribute('aria-hidden', 'true');
    [['☀', 'sun'], ['🌙', 'moon']].forEach(([symbol, type]) => {
      const item = document.createElement('span');
      const symbolElement = document.createElement('span');
      symbolElement.className = `astro-symbol astro-symbol--${type}`;
      symbolElement.setAttribute('aria-hidden', 'true');
      symbolElement.textContent = symbol;
      item.appendChild(symbolElement);
      legend.appendChild(item);
    });

    const chart = document.createElement('div');
    chart.setAttribute('class', 'timeline-chart');
    chart.setAttribute('role', 'button');
    chart.setAttribute('tabindex', '0');
    chart.setAttribute('aria-label', 'Ver horarios de visibilidad del Sol y la Luna');
    chart.setAttribute('aria-haspopup', 'dialog');
    chart.setAttribute('aria-expanded', 'false');
    chart.setAttribute('aria-controls', 'sky-popover');

    const scale = document.createElement('div');
    scale.setAttribute('class', 'timeline-scale');
    ['00', '06', '12', '18', '24'].forEach((label, index) => {
      const tick = document.createElement('span');
      tick.textContent = label;
      scale.appendChild(tick);
    });
    chart.appendChild(scale);

    const sunTrack = document.createElement('div');
    sunTrack.setAttribute('class', 'timeline-track');
    const moonTrack = document.createElement('div');
    moonTrack.setAttribute('class', 'timeline-track');
    chart.appendChild(sunTrack);
    chart.appendChild(moonTrack);

    const addBar = (interval, track, bandClass) => {
      const startTimestamp = parseTimestamp(interval.start);
      const endTimestamp = parseTimestamp(interval.end);
      if (startTimestamp === null || endTimestamp === null) {
        console.error('Cielo del día: intervalo inválido.', interval);
        return;
      }

      const dayOffset = startTimestamp.date !== null && endTimestamp.date !== null
        ? Math.round((endTimestamp.date - startTimestamp.date) / 86400000) * 24 * 60
        : 0;
      const start = startTimestamp.minutes;
      const end = endTimestamp.minutes + dayOffset;
      if (start === null || end === null) {
        return;
      }

      const normalizedStart = Math.max(0, Math.min(24 * 60, start));
      const normalizedEnd = Math.max(0, Math.min(24 * 60, end));
      const left = (normalizedStart / (24 * 60)) * 100;
      const barWidth = ((normalizedEnd - normalizedStart) / (24 * 60)) * 100;
      if (barWidth <= 0) {
        return;
      }

      const segment = document.createElement('span');
      segment.setAttribute('class', `timeline-segment ${bandClass}`);
      segment.animate(
        [{ left: `${left}%`, width: `${barWidth}%` }],
        { duration: 0, fill: 'forwards' },
      );
      track.appendChild(segment);
    };

    sunIntervals.forEach((interval) => addBar(interval, sunTrack, 'timeline-segment-sun'));
    moonIntervals.forEach((interval) => addBar(interval, moonTrack, 'timeline-segment-moon'));

    container.innerHTML = '';
    container.appendChild(legend);
    container.appendChild(chart);
  };

  rows.forEach((row) => {
    const container = row.querySelector('.timeline-cell');
    if (!container) {
      return;
    }

    const rowId = row.getAttribute('data-row-id') || 'fila sin identificador';
    const sunIntervals = parseIntervals(row.getAttribute('data-sun'), rowId, 'data-sun');
    const moonIntervals = parseIntervals(row.getAttribute('data-moon'), rowId, 'data-moon');

    renderTimeline(container, sunIntervals, moonIntervals);
  });
});
