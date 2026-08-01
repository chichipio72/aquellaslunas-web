document.addEventListener('DOMContentLoaded', () => {
  if (!document.body.classList.contains('today-visual-experiment')) return;

  const SVG_NS = 'http://www.w3.org/2000/svg';
  const editorial = globalThis.AstronomyEditorialConfiguration?.today || {};
  let lightPeriods = {};
  try {
    lightPeriods = JSON.parse(document.body.dataset.todayLightPeriods || '{}');
  } catch (_) {}

  const minute = (value) => {
    const match = String(value || '').match(/T(\d{2}):(\d{2})/);
    return match ? Number(match[1]) * 60 + Number(match[2]) : null;
  };

  const validPeriod = (period) => {
    const start = minute(period?.start);
    const end = minute(period?.end);
    if (!Number.isFinite(start) || !Number.isFinite(end)) return null;
    return { start, end: end === 0 && start > 0 ? 1440 : end };
  };

  const bands = () => {
    const twilight = lightPeriods?.twilight || {};
    const photo = lightPeriods?.photographic || {};
    const sun = lightPeriods?._sun || {};
    const result = [];
    const fullDay = Number(sun.day_length_seconds) >= 86340;
    if (fullDay) {
      result.push({ type: 'day', start: 0, end: 1440 });
    }
    [
      ['astronomical', twilight?.morning?.astronomical],
      ['nautical', twilight?.morning?.nautical],
      ['civil', twilight?.morning?.civil],
      ['day', fullDay ? null : {
        start: sun.rise || twilight?.morning?.civil?.end,
        end: sun.set || twilight?.evening?.civil?.start,
      }],
      ['civil', twilight?.evening?.civil],
      ['nautical', twilight?.evening?.nautical],
      ['astronomical', twilight?.evening?.astronomical],
      ['blue', photo?.morning?.blue_hour],
      ['golden', photo?.morning?.golden_hour],
      ['golden', photo?.evening?.golden_hour],
      ['blue', photo?.evening?.blue_hour],
    ].forEach(([type, period]) => {
      const normalized = validPeriod(period);
      if (normalized && normalized.end > normalized.start) result.push({ type, ...normalized });
    });
    return result;
  };

  const addLegend = (panel) => {
    if (panel.querySelector('.today-light-legend')) return;
    const legend = document.createElement('div');
    legend.className = 'today-light-legend';
    legend.setAttribute('aria-label', 'Referencias del fondo del gráfico');
    [
      ['day', 'Día'], ['twilight', 'Crepúsculo'], ['night', 'Noche'],
      ['blue', 'Hora azul'], ['golden', 'Hora dorada'],
    ].forEach(([type, label]) => {
      const item = document.createElement('span');
      item.className = `today-light-legend__${type}`;
      item.innerHTML = `<i aria-hidden="true"></i>${label}`;
      legend.append(item);
    });
    panel.append(legend);
  };

  const decorate = (figure) => {
    const svg = figure.querySelector('.altitude-profile-svg');
    if (!svg || svg.querySelector('.today-light-bands')) return false;
    const viewBox = svg.viewBox.baseVal;
    const left = 8;
    const right = viewBox.width - 8;
    const top = 8;
    const bottom = 116;
    const x = (value) => left + (value / 1440) * (right - left);
    const group = document.createElementNS(SVG_NS, 'g');
    group.setAttribute('class', 'today-light-bands');
    group.setAttribute('aria-hidden', 'true');

    const night = document.createElementNS(SVG_NS, 'rect');
    night.setAttribute('class', 'today-light-band--night');
    night.setAttribute('x', String(left));
    night.setAttribute('y', String(top));
    night.setAttribute('width', String(right - left));
    night.setAttribute('height', String(bottom - top));
    group.append(night);

    bands().forEach(({ type, start, end }) => {
      const rect = document.createElementNS(SVG_NS, 'rect');
      rect.setAttribute('class', `today-light-band--${type}`);
      rect.setAttribute('x', String(x(start)));
      rect.setAttribute('y', String(top));
      rect.setAttribute('width', String(Math.max(0, x(end) - x(start))));
      rect.setAttribute('height', String(bottom - top));
      group.append(rect);
    });
    const firstGraphic = [...svg.children].find((child) => !['title', 'desc'].includes(child.tagName.toLowerCase()));
    svg.insertBefore(group, firstGraphic || null);
    addLegend(figure.closest('[role="tabpanel"]'));
    return true;
  };

  document.querySelectorAll('[data-altitude-profile]').forEach((figure) => {
    if (decorate(figure)) return;
    const observer = new MutationObserver(() => {
      if (decorate(figure)) observer.disconnect();
    });
    observer.observe(figure, { childList: true, subtree: true });
  });

  document.querySelectorAll('#today-photo-panel .today-time-rows > div, #today-twilight-panel .today-time-rows > div').forEach((row) => {
    const label = row.querySelector('strong')?.textContent?.toLowerCase() || '';
    const type = label.includes('hora azul')
      ? 'blue'
      : (label.includes('hora dorada')
        ? 'golden'
        : (label.includes('astronómico')
          ? 'astronomical'
          : (label.includes('náutico') ? 'nautical' : (label.includes('civil') ? 'civil' : null))));
    if (type) row.classList.add('today-period-row', `today-period-row--${type}`);

    if (label.includes('crepúsculo civil vespertino')) {
      const help = document.createElement('small');
      help.className = 'today-venus-help';
      help.textContent = editorial.venusBeltHelp || '';
      row.append(help);
    }
  });

  const venusOpportunity = document.querySelector('[data-venus-belt-opportunity]');
  const summaryCloud = document.querySelector('[data-today-summary-cloud]');
  const updateVenusOpportunity = () => {
    if (!venusOpportunity || !summaryCloud) return false;
    const match = summaryCloud.textContent.match(/(\d{1,3})\s*%/);
    if (!match) return false;
    venusOpportunity.hidden = Number(match[1]) > Number(editorial.venusBeltMaxCloudPercent);
    return true;
  };
  if (venusOpportunity && summaryCloud && !updateVenusOpportunity()) {
    const observer = new MutationObserver(() => {
      if (updateVenusOpportunity()) observer.disconnect();
    });
    observer.observe(summaryCloud, { childList: true, characterData: true, subtree: true });
  }

  const cloudHours = document.querySelector('[data-today-cloud-hours]');
  const renderCloudHours = () => {
    if (!cloudHours) return false;
    let decorated = 0;
    cloudHours.querySelectorAll(':scope > span').forEach((block) => {
      if (block.querySelector('img')) {
        decorated += 1;
        return;
      }
      const match = block.textContent.match(/(\d{1,3})\s*%/);
      if (!match) return;
      const value = Math.max(0, Math.min(100, Number(match[1])));
      const category = globalThis.AstronomyCloudCover?.cloudCategory(value);
      if (!category) return;
      const hour = block.querySelector('strong')?.textContent?.trim() || 'Horario';
      const percentage = block.querySelector('small');
      if (!percentage) return;
      const image = document.createElement('img');
      image.src = `assets/images/weather/cloud-${category.key}.svg`;
      image.alt = '';
      image.width = 64;
      image.height = 48;
      block.insertBefore(image, percentage);
      block.title = `${category.label}: ${value} % de cobertura`;
      block.setAttribute('aria-label', `${hour}. ${category.label}. ${value} % de cobertura.`);
      decorated += 1;
    });
    return decorated > 0;
  };
  if (cloudHours && !renderCloudHours()) {
    const observer = new MutationObserver(() => {
      if (renderCloudHours()) observer.disconnect();
    });
    observer.observe(cloudHours, { childList: true, subtree: true });
  }
});
