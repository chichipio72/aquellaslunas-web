document.addEventListener('DOMContentLoaded', () => {
  const SVG_NS = 'http://www.w3.org/2000/svg';
  const HEIGHT = 168;
  const ROLE_LABELS = {
    sun: {
      winter_solstice: 'Invierno',
      requested_date: 'Hoy',
      summer_solstice: 'Verano',
    },
    moon: {
      previous_date: 'Ayer',
      requested_date: 'Hoy',
      next_date: 'Mañana',
    },
  };

  const svgElement = (name, attributes = {}) => {
    const element = document.createElementNS(SVG_NS, name);
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value));
    return element;
  };

  const localMinutes = (point, localDate) => {
    const match = String(point.local_time || '').match(/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/);
    if (!match) return null;
    const minutes = Number(match[2]) * 60 + Number(match[3]);
    return match[1] !== localDate && minutes === 0 ? 1440 : minutes;
  };

  const normalizeSeries = (series) => {
    if (!series || !Array.isArray(series.points)) return null;
    const points = series.points.map((point) => ({
      minute: localMinutes(point, series.local_date),
      altitude: Number(point.altitude_degrees),
    })).filter((point) => Number.isFinite(point.minute) && Number.isFinite(point.altitude));
    return points.length >= 2 ? { ...series, points } : null;
  };

  const horizonCrossing = (first, second) => {
    const fraction = -first.altitude / (second.altitude - first.altitude);
    return {
      minute: first.minute + (second.minute - first.minute) * fraction,
      altitude: 0,
    };
  };

  const positiveRegions = (points) => {
    const regions = [];
    let current = null;
    for (let index = 0; index < points.length - 1; index += 1) {
      const first = points[index];
      const second = points[index + 1];
      if (first.altitude > 0 && current === null) current = [first];
      if (first.altitude === 0 && second.altitude > 0 && current === null) current = [first];

      if (first.altitude <= 0 && second.altitude > 0) {
        current = [first.altitude === 0 ? first : horizonCrossing(first, second), second];
      } else if (first.altitude > 0 && second.altitude > 0) {
        current.push(second);
      } else if (first.altitude > 0 && second.altitude <= 0) {
        current.push(second.altitude === 0 ? second : horizonCrossing(first, second));
        regions.push(current);
        current = null;
      }
    }
    if (current && current.length > 1) regions.push(current);
    return regions;
  };

  const clockParts = (instant, timezone) => Object.fromEntries(
    new Intl.DateTimeFormat('en-CA', {
      timeZone: timezone,
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(instant).filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]),
  );

  class AltitudeProfileChart {
    constructor(container) {
      this.container = container;
      this.canvas = container.querySelector('.home-altitude-profile__canvas');
      this.status = container.querySelector('[data-profile-status]');
      this.target = container.dataset.target;
      this.marker = null;
      this.todayPoints = null;
      this.scales = null;
      this.timer = null;
    }

    async load() {
      try {
        const response = await fetch(this.container.dataset.endpoint, {
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const payload = await response.json();
        const series = Array.isArray(payload.series) ? payload.series.map(normalizeSeries).filter(Boolean) : [];
        if (payload.target !== this.target || series.length !== 3 || !Number.isFinite(Number(payload.interval_minutes))) {
          throw new Error('Contrato de perfil inválido');
        }
        this.render(series);
      } catch (error) {
        this.showError(error);
      }
    }

    render(series) {
      const width = this.target === 'moon' ? 520 : 320;
      const plot = { left: 8, right: width - 8, top: 8, bottom: 116 };
      const altitudes = series.flatMap((item) => item.points.map((point) => point.altitude));
      const minimum = Math.max(-90, Math.min(-10, Math.floor(Math.min(...altitudes) / 10) * 10 - 5));
      const maximum = Math.min(90, Math.max(10, Math.ceil(Math.max(...altitudes) / 10) * 10 + 5));
      const x = (minute) => plot.left + (Math.max(0, Math.min(1440, minute)) / 1440) * (plot.right - plot.left);
      const y = (altitude) => plot.bottom - ((altitude - minimum) / (maximum - minimum)) * (plot.bottom - plot.top);
      this.scales = { x, y };

      const svg = svgElement('svg', {
        class: `altitude-profile-svg altitude-profile-svg--${this.target}`,
        viewBox: `0 0 ${width} ${HEIGHT}`,
        role: 'img',
        'aria-labelledby': `${this.target}-profile-title ${this.target}-profile-description`,
      });
      const title = svgElement('title', { id: `${this.target}-profile-title` });
      title.textContent = this.target === 'sun' ? 'Altura del Sol sobre el horizonte' : 'Altura de la Luna sobre el horizonte';
      const description = svgElement('desc', { id: `${this.target}-profile-description` });
      description.textContent = `Tres recorridos diarios. El relleno indica cuándo ${this.target === 'sun' ? 'el Sol' : 'la Luna'} está sobre el horizonte.`;
      svg.append(title, description);

      const horizonY = y(0);
      const horizon = svgElement('line', {
        class: 'altitude-profile-horizon', x1: plot.left, x2: plot.right, y1: horizonY, y2: horizonY,
      });
      svg.append(horizon);

      [0, 360, 720, 1080, 1440].forEach((minute, index) => {
        const label = svgElement('text', {
          class: 'altitude-profile-hour', x: x(minute), y: 130,
          'text-anchor': index === 0 ? 'start' : (index === 4 ? 'end' : 'middle'),
        });
        label.textContent = String(index * 6).padStart(2, '0');
        svg.append(label);
      });

      const today = series.find((item) => item.role === 'requested_date');
      positiveRegions(today.points).forEach((region) => {
        const polygonPoints = [
          `${x(region[0].minute)},${horizonY}`,
          ...region.map((point) => `${x(point.minute)},${y(point.altitude)}`),
          `${x(region[region.length - 1].minute)},${horizonY}`,
        ];
        svg.append(svgElement('polygon', {
          class: 'altitude-profile-fill', points: polygonPoints.join(' '),
        }));
      });

      series.forEach((item) => {
        svg.append(svgElement('polyline', {
          class: `altitude-profile-line altitude-profile-line--${item.role}`,
          points: item.points.map((point) => `${x(point.minute)},${y(point.altitude)}`).join(' '),
        }));
      });

      this.marker = svgElement('circle', { class: 'altitude-profile-marker', r: 3.2, hidden: '' });
      svg.append(this.marker);
      this.todayPoints = today.points;
      this.renderLegend(svg, series, width);
      this.canvas.replaceChildren(svg);
      this.canvas.removeAttribute('aria-hidden');
      this.container.setAttribute('aria-busy', 'false');
      this.status.textContent = '';
      this.updateMarker();
      if (!this.container.dataset.debugNow) {
        this.timer = window.setInterval(() => this.updateMarker(), 60000);
      }
    }

    renderLegend(svg, series, width) {
      const labels = ROLE_LABELS[this.target];
      const slotWidth = width / series.length;
      series.forEach((item, index) => {
        const start = this.target === 'moon' ? 18 + index * slotWidth : 18 + index * 100;
        svg.append(svgElement('line', {
          class: `altitude-profile-legend-line altitude-profile-line--${item.role}`,
          x1: start, x2: start + 18, y1: 151, y2: 151,
        }));
        const text = svgElement('text', { class: 'altitude-profile-legend-text', x: start + 23, y: 154 });
        text.textContent = labels[item.role] || item.role;
        svg.append(text);
      });
    }

    updateMarker() {
      if (!this.marker || !this.todayPoints || !this.scales) return;
      try {
        const simulated = this.container.dataset.debugNow;
        const instant = simulated ? new Date(simulated) : new Date();
        const parts = clockParts(instant, this.container.dataset.timezone);
        const localDate = `${parts.year}-${parts.month}-${parts.day}`;
        if (localDate !== this.container.dataset.date) {
          this.marker.setAttribute('hidden', '');
          return;
        }
        const minute = Number(parts.hour) * 60 + Number(parts.minute);
        let first = this.todayPoints[0];
        let second = this.todayPoints[this.todayPoints.length - 1];
        for (let index = 0; index < this.todayPoints.length - 1; index += 1) {
          if (minute >= this.todayPoints[index].minute && minute <= this.todayPoints[index + 1].minute) {
            first = this.todayPoints[index];
            second = this.todayPoints[index + 1];
            break;
          }
        }
        const span = second.minute - first.minute;
        const ratio = span > 0 ? (minute - first.minute) / span : 0;
        const altitude = first.altitude + (second.altitude - first.altitude) * Math.max(0, Math.min(1, ratio));
        this.marker.setAttribute('cx', this.scales.x(minute));
        this.marker.setAttribute('cy', this.scales.y(altitude));
        this.marker.removeAttribute('hidden');
        const label = `${simulated ? 'Hora simulada' : 'Hora actual'}: ${parts.hour}:${parts.minute}; altura ${altitude.toFixed(1)} grados.`;
        this.marker.setAttribute('aria-label', label);
      } catch (error) {
        this.marker.setAttribute('hidden', '');
      }
    }

    showError(error) {
      this.container.setAttribute('aria-busy', 'false');
      this.container.classList.add('home-altitude-profile--error');
      this.status.textContent = 'No se pudo cargar el recorrido';
      console.error(`Perfil de altura de ${this.target}:`, error);
    }
  }

  document.querySelectorAll('[data-altitude-profile]').forEach((container) => {
    const chart = new AltitudeProfileChart(container);
    chart.load();
    document.addEventListener('astronomy:page-visible', () => {
      if (!container.dataset.debugNow) chart.updateMarker();
    });
  });
});
