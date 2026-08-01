const astronomyLaboratoryFormatDayFraction = (value) => {
  if (value === null || value === undefined || !Number.isFinite(Number(value))) return 'Sin dato';
  const totalMinutes = Math.round(Math.min(1, Math.max(0, Number(value))) * 1440);
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
};

const astronomyLaboratoryFormatNumber = (value) => {
  const numericValue = Number(value);
  if (!Number.isFinite(numericValue)) return 'Sin dato';
  return numericValue.toLocaleString('es-AR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const astronomyLaboratoryFormatSeriesValue = (value, type, unit = '') => {
  if (value === null || value === undefined) return 'Sin dato';
  return type === 'time_fraction' || type === 'time_duration'
    ? astronomyLaboratoryFormatDayFraction(value)
    : `${astronomyLaboratoryFormatNumber(value)}${unit}`;
};

const astronomyLaboratorySubtractYears = (dateValue, years, minimumYear) => {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateValue);
  if (!match) return '';
  const targetYear = Math.max(minimumYear, Number(match[1]) - years);
  const month = Number(match[2]);
  const maximumDay = new Date(Date.UTC(targetYear, month, 0)).getUTCDate();
  const day = Math.min(Number(match[3]), maximumDay);
  return `${targetYear}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};

const astronomyLaboratoryAddYears = (dateValue, years, maximumYear) => {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateValue);
  if (!match) return '';
  const targetYear = Math.min(maximumYear, Number(match[1]) + years);
  const month = Number(match[2]);
  const maximumDay = new Date(Date.UTC(targetYear, month, 0)).getUTCDate();
  const day = Math.min(Number(match[3]), maximumDay);
  return `${targetYear}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
};

const astronomyLaboratoryDateForYear = (year, boundary) => (
  boundary === 'start' ? `${year}-01-01` : `${year}-12-31`
);

const astronomyLaboratoryYearFromDate = (dateValue) => (
  /^\d{4}-\d{2}-\d{2}$/.test(dateValue) ? dateValue.slice(0, 4) : ''
);

const astronomyLaboratorySelectedScaleGroups = (fields, fieldScales) => (
  [...new Set(fields.map((field) => fieldScales[field]).filter(Boolean))]
);

const astronomyLaboratoryExpandedSteps = (steps, required) => {
  const result = [...steps];
  while (result[result.length - 1] < required) result.push(result[result.length - 1] * 10);
  return result;
};

const astronomyLaboratoryNiceScale = (values, definition, requestedIntervals = definition.intervals || 8) => {
  const finite = values
    .filter((value) => value !== null && value !== undefined && value !== '')
    .map(Number)
    .filter(Number.isFinite);
  const dataMin = finite.length ? Math.min(...finite) : (definition.natural_min ?? 0);
  const dataMax = finite.length ? Math.max(...finite) : (definition.natural_max ?? 1);
  const spread = Math.max(0, dataMax - dataMin);
  const padding = spread > 0 ? spread * 0.05 : Math.max(Math.abs(dataMin) * 0.05, definition.steps[0]);
  const required = definition.center_zero
    ? Math.max(Math.abs(dataMin), Math.abs(dataMax)) / (requestedIntervals / 2)
    : (spread + (2 * padding)) / requestedIntervals;
  let interval = astronomyLaboratoryExpandedSteps(definition.steps, required)
    .find((step) => step + Number.EPSILON >= required) || definition.steps[definition.steps.length - 1];
  if (
    definition.natural_min !== undefined && definition.natural_max !== undefined
    && dataMin <= definition.natural_min && dataMax >= definition.natural_max
  ) {
    interval = (definition.natural_max - definition.natural_min) / requestedIntervals;
    return { min: definition.natural_min, max: definition.natural_max, interval };
  }
  if (definition.center_zero) {
    return { min: -(requestedIntervals / 2) * interval, max: (requestedIntervals / 2) * interval, interval };
  }
  let min = Math.floor((dataMin - padding) / interval) * interval;
  let max = min + requestedIntervals * interval;
  if (max < dataMax + padding) {
    max = Math.ceil((dataMax + padding) / interval) * interval;
    min = max - requestedIntervals * interval;
  }
  if (definition.natural_min !== undefined && min < definition.natural_min) {
    min = definition.natural_min;
    max = min + requestedIntervals * interval;
  }
  if (definition.natural_max !== undefined && max > definition.natural_max && dataMin >= definition.natural_min) {
    max = definition.natural_max;
    min = max - requestedIntervals * interval;
  }
  return { min, max, interval };
};

const astronomyLaboratoryFormatAxisLabel = (value, group, definition) => {
  const epsilon = 0.000001;
  if (
    (definition.natural_min !== undefined && value < definition.natural_min - epsilon)
    || (definition.natural_max !== undefined && value > definition.natural_max + epsilon)
  ) {
    return '';
  }
  if (definition.format === 'HH:MM') return astronomyLaboratoryFormatDayFraction(value);
  const interval = Math.abs(Number(definition.current_interval ?? definition.interval ?? 1));
  const digits = interval >= 1 || Math.abs(value) >= 100
    ? 0
    : Math.min(2, Math.max(0, Math.ceil(-Math.log10(interval))));
  return Number(value).toLocaleString('es-AR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: definition.format === 'integer' ? 0 : digits,
  });
};

const astronomyLaboratoryBuildAxisLayout = (groups, definitions, valuesByGroup = {}) => {
  const axes = groups.map((group, index) => {
    const definition = definitions[group] || {};
    const scale = astronomyLaboratoryNiceScale(valuesByGroup[group] || [], definition);
    return {
      group,
      name: definition.label || group,
      type: 'value',
      position: index % 2 === 0 ? 'left' : 'right',
      offset: Math.floor(index / 2) * 76,
      scale: true,
      min: scale.min,
      max: scale.max,
      interval: scale.interval,
      initialScale: scale,
    };
  });
  const leftAxes = axes.filter((axis) => axis.position === 'left').length;
  const rightAxes = axes.filter((axis) => axis.position === 'right').length;
  return {
    axes,
    indexes: Object.fromEntries(groups.map((group, index) => [group, index])),
    grid: {
      left: 70 + Math.max(0, leftAxes - 1) * 82,
      right: 58 + Math.max(0, rightAxes - 1) * 82,
      top: rightAxes > 0 ? 124 : 100,
      bottom: 92,
      containLabel: true,
    },
  };
};

const astronomyLaboratoryExtremaRangeAssessment = (from, to, variable, minimumYear = 1) => {
  const minimumFrom = astronomyLaboratorySubtractYears(to, variable.minimumYears, minimumYear);
  const recommendedFrom = astronomyLaboratorySubtractYears(to, variable.recommendedYears, minimumYear);
  if (minimumFrom === '' || recommendedFrom === '' || !/^\d{4}-\d{2}-\d{2}$/.test(from)) {
    return { status: 'invalid' };
  }
  if (from > minimumFrom) {
    return {
      status: 'blocked',
      message: `Para detectar suficientes extremos ${variable.body === 'moon' ? 'lunares' : 'solares'}, seleccioná un período de al menos ${variable.minimumYears} años.`,
    };
  }
  if (from > recommendedFrom) {
    return {
      status: 'warning',
      message: `Para observar la modulación se recomienda un período de al menos ${variable.recommendedYears} años.`,
    };
  }
  return {
    status: 'ok',
    message: `El período cumple la recomendación de ${variable.recommendedYears} años o más.`,
  };
};

const astronomyLaboratoryBuildExtremaSeries = (payload) => {
  const definitions = [
    ['maximos', '#f2d486'],
    ['minimos', '#8eb4ff'],
  ];
  return definitions
    .filter(([key]) => Array.isArray(payload.series?.[key]) && payload.series[key].length > 0)
    .map(([key, color]) => ({
      name: payload.series_labels[key],
      type: 'line',
      data: payload.series[key].map((point) => [point.fecha, point.valor]),
      showSymbol: true,
      symbol: 'circle',
      symbolSize: 8,
      smooth: false,
      connectNulls: false,
      lineStyle: { width: 1.8, color },
      itemStyle: { color },
      emphasis: { focus: 'series' },
    }));
};

const astronomyLaboratoryBuildExtremaAxis = (payload, scaleDefinitions) => {
  const definition = scaleDefinitions[payload.scale_group] || {};
  const values = Object.values(payload.series || {}).flat().map((point) => point.valor);
  const scale = astronomyLaboratoryNiceScale(values, definition);
  return {
    group: payload.scale_group,
    type: 'value',
    name: definition.label || payload.variable_label,
    scale: true,
    ...scale,
    initialScale: scale,
    axisLabel: {
      color: '#aebbd8',
      formatter: (value) => astronomyLaboratoryFormatAxisLabel(
        value,
        payload.scale_group,
        { ...definition, current_interval: scale.interval }
      ),
    },
    axisLine: { show: true, lineStyle: { color: '#f2d486' } },
    splitLine: { lineStyle: { color: 'rgba(145, 166, 210, .12)' } },
  };
};

const astronomyLaboratoryZoomScale = (scale, anchor, wheelDelta, definition) => {
  const factor = wheelDelta < 0 ? 0.8 : 1.25;
  const ratio = Math.min(1, Math.max(0, (anchor - scale.min) / (scale.max - scale.min)));
  let min = anchor - ((anchor - scale.min) * factor);
  let max = anchor + ((scale.max - anchor) * factor);
  if (definition.natural_min !== undefined && min < definition.natural_min) {
    max += definition.natural_min - min;
    min = definition.natural_min;
  }
  if (definition.natural_max !== undefined && max > definition.natural_max) {
    max = definition.natural_max;
  }
  const required = (max - min) / Math.max(4, definition.intervals || 8);
  const interval = astronomyLaboratoryExpandedSteps(definition.steps, required)
    .find((step) => step >= required) || definition.steps[definition.steps.length - 1];
  return { min, max, interval, anchorRatio: ratio };
};

const astronomyLaboratoryPanScale = (scale, delta, definition) => {
  const span = scale.max - scale.min;
  let min = scale.min + delta;
  let max = scale.max + delta;
  if (definition.natural_min !== undefined && min < definition.natural_min) {
    min = definition.natural_min;
    max = min + span;
  }
  if (definition.natural_max !== undefined && max > definition.natural_max) {
    max = definition.natural_max;
    min = max - span;
  }
  return { ...scale, min, max };
};

const astronomyLaboratoryNearestAxisIndex = (pointerX, axisPixels, tolerance = 70) => {
  const nearest = axisPixels.map((pixel, index) => ({ index, distance: Math.abs(pointerX - pixel) }))
    .sort((a, b) => a.distance - b.distance)[0];
  return nearest && nearest.distance <= tolerance ? nearest.index : null;
};

const astronomyLaboratorySeriesAxisIndex = (option, seriesIndex) => (
  Number(option.series?.[seriesIndex]?.yAxisIndex || 0)
);

const astronomyLaboratoryNormalizeValues = (values) => {
  const finite = values
    .filter((value) => value !== null && value !== undefined && value !== '')
    .map(Number)
    .filter(Number.isFinite);
  const min = finite.length ? Math.min(...finite) : null;
  const max = finite.length ? Math.max(...finite) : null;
  return {
    min,
    max,
    values: values.map((value) => {
      if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) return null;
      if (min === max) return 0;
      return ((Number(value) - min) / (max - min) * 2) - 1;
    }),
  };
};

const astronomyLaboratoryRelationMethods = {
  product: (a, b) => a * b,
  average: (a, b) => (a + b) / 2,
};

const astronomyLaboratoryInterpretRelation = (value, method = 'product') => {
  if (value === null || !Number.isFinite(Number(value))) return 'Sin dato';
  if (method === 'average') {
    if (value >= .6) return 'Refuerzo positivo fuerte';
    if (value >= .15) return 'Refuerzo positivo moderado';
    if (value > -.15) return 'Equilibrio';
    if (value > -.6) return 'Refuerzo negativo moderado';
    return 'Refuerzo negativo fuerte';
  }
  if (value >= .6) return 'Coincidencia fuerte';
  if (value >= .15) return 'Coincidencia moderada';
  if (value > -.15) return 'Neutro';
  if (value > -.6) return 'Oposición moderada';
  return 'Oposición fuerte';
};

const astronomyLaboratoryBuildRelation = (rows, fieldA, fieldB, method = 'product') => {
  const normalizedA = astronomyLaboratoryNormalizeValues(rows.map((row) => row[fieldA]));
  const normalizedB = astronomyLaboratoryNormalizeValues(rows.map((row) => row[fieldB]));
  const combine = astronomyLaboratoryRelationMethods[method];
  return rows.map((row, index) => {
    const a = normalizedA.values[index];
    const b = normalizedB.values[index];
    const relationIndex = a === null || b === null ? null : combine(a, b);
    return {
      value: [row.fecha, method, relationIndex],
      relation: {
        date: row.fecha,
        fieldA,
        fieldB,
        rawA: row[fieldA],
        rawB: row[fieldB],
        normalizedA: a,
        normalizedB: b,
        index: relationIndex,
        method,
        interpretation: astronomyLaboratoryInterpretRelation(relationIndex, method),
      },
    };
  });
};

const astronomyLaboratoryRelationControlState = (fields, requestedMethods = []) => ({
  enabled: fields.length >= 2,
  automatic: fields.length === 2,
  showSelectors: fields.length > 2,
  fields: fields.length === 2 ? fields : null,
  requestedMethods: [...requestedMethods],
  renderedMethods: fields.length >= 2 ? [...requestedMethods] : [],
});

const astronomyLaboratoryCreateDebouncedRequest = (callback, delay = 250, timers = window) => {
  let timer = null;
  return {
    schedule() {
      if (timer !== null) timers.clearTimeout(timer);
      timer = timers.setTimeout(() => {
        timer = null;
        callback();
      }, delay);
    },
    flush() {
      if (timer !== null) timers.clearTimeout(timer);
      timer = null;
      callback();
    },
    pending: () => timer !== null,
  };
};

const astronomyLaboratoryRelationGridEdges = (chartWidth, mainRect) => ({
  left: mainRect.x,
  right: Math.max(0, chartWidth - mainRect.x - mainRect.width),
});

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('[data-astronomy-laboratory-form]');
  const chartElement = document.querySelector('[data-astronomy-laboratory-chart]');
  const loading = document.querySelector('[data-astronomy-laboratory-loading]');
  const error = document.querySelector('[data-astronomy-laboratory-error]');
  const count = document.querySelector('[data-astronomy-laboratory-count]');
  const scaleGroupHelp = document.querySelector('[data-scale-group-help]');
  const chartTitle = document.querySelector('#astronomy-laboratory-chart-title');
  const extremaSummary = document.querySelector('[data-extrema-summary]');
  const extremaRangeGuidance = document.querySelector('[data-extrema-range-guidance]');
  const dailyControls = [...document.querySelectorAll('[data-analysis-daily]')];
  const extremaControls = document.querySelector('[data-analysis-extrema]');
  const dailyRangeShortcuts = document.querySelector('[data-daily-range-shortcuts]');
  const extremaRangeShortcuts = document.querySelector('[data-extrema-range-shortcuts]');
  const relationToggles = [...document.querySelectorAll('[data-relation-toggle]')];
  const relationHelp = document.querySelector('[data-relation-help]');
  const relationSelectors = document.querySelector('[data-relation-selectors]');
  const relationSelectA = document.querySelector('[data-relation-variable="a"]');
  const relationSelectB = document.querySelector('[data-relation-variable="b"]');
  const config = window.astronomyLaboratoryConfig;
  if (!form || !chartElement || !config) return;

  let chart = null;
  let lastDailyPayload = null;
  let lastDailyFields = [];
  let activeRequest = null;
  let lastRequestKey = '';
  let scheduleBackendUpdate = () => {};
  const preferredRelationFields = { a: '', b: '' };
  let axisInteractionCleanup = () => {};
  let alignRelationBands = () => {};
  const colors = ['#8eb4ff', '#f2d486', '#89d6c6', '#d9a5ff', '#ff9f9f', '#9ccf75', '#f7b267'];
  const setLoading = (active) => {
    loading.hidden = !active;
  };
  const showError = (message = '') => {
    error.textContent = message;
    error.hidden = message === '';
  };
  const selectedFields = () => [...form.querySelectorAll('input[name="campos[]"]:checked')]
    .map((input) => input.value);
  const selectedPhases = () => [...form.querySelectorAll('input[name="fases[]"]:checked')]
    .map((input) => input.value);
  const analysisMode = () => form.elements.modo.value;
  const extremaVariable = () => config.extremaVariables[form.elements.variable_extremos.value];
  const selectedScaleGroups = (fields) => astronomyLaboratorySelectedScaleGroups(
    fields,
    config.fieldScales || {}
  );
  const updateScaleGroupHelp = () => {
    const groupCount = selectedScaleGroups(selectedFields()).length;
    scaleGroupHelp.textContent = groupCount > 4
      ? `${groupCount} grupos seleccionados. El máximo es cuatro; desmarcá variables de al menos un grupo.`
      : `${groupCount} de 4 grupos de escala seleccionados. Varias variables del mismo grupo comparten eje.`;
    scaleGroupHelp.classList.toggle('is-warning', groupCount > 4);
  };
  const fillRelationSelect = (select, fields, preferred) => {
    select.replaceChildren(...fields.map((field) => {
      const option = document.createElement('option');
      option.value = field;
      option.textContent = config.labels[field] || field;
      return option;
    }));
    if (fields.includes(preferred)) select.value = preferred;
  };
  const preventDuplicateRelationVariables = (changedSelect = null) => {
    if (relationSelectA.value === relationSelectB.value) {
      const target = changedSelect === relationSelectB ? relationSelectA : relationSelectB;
      const forbidden = target === relationSelectA ? relationSelectB.value : relationSelectA.value;
      target.value = [...target.options].find((option) => option.value !== forbidden)?.value || '';
    }
    [...relationSelectA.options].forEach((option) => { option.disabled = option.value === relationSelectB.value; });
    [...relationSelectB.options].forEach((option) => { option.disabled = option.value === relationSelectA.value; });
  };
  const updateRelationControls = () => {
    const fields = selectedFields();
    const state = astronomyLaboratoryRelationControlState(fields);
    relationToggles.forEach((toggle) => { toggle.disabled = !state.enabled; });
    if (!state.enabled) {
      relationSelectors.hidden = true;
      relationHelp.textContent = 'Seleccioná al menos dos variables para habilitar el análisis.';
      return;
    }
    if (state.automatic) {
      relationSelectors.hidden = true;
      relationHelp.textContent = `Se analizarán ${config.labels[fields[0]]} y ${config.labels[fields[1]]}.`;
      return;
    }
    fillRelationSelect(relationSelectA, fields, preferredRelationFields.a || fields[0]);
    fillRelationSelect(relationSelectB, fields, preferredRelationFields.b || fields[1]);
    preventDuplicateRelationVariables();
    preferredRelationFields.a = relationSelectA.value;
    preferredRelationFields.b = relationSelectB.value;
    relationSelectors.hidden = false;
    relationHelp.textContent = 'Elegí dos de las variables seleccionadas para construir la banda.';
  };
  const selectedRelationFields = (fields) => fields.length === 2
    ? fields
    : [relationSelectA.value, relationSelectB.value];
  const selectedRelationMethods = () => relationToggles
    .filter((toggle) => toggle.checked)
    .map((toggle) => toggle.value);
  const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
  const clearResult = () => {
    axisInteractionCleanup();
    axisInteractionCleanup = () => {};
    alignRelationBands = () => {};
    chart?.clear();
    count.textContent = 'Sin datos consultados.';
    extremaSummary.hidden = true;
    extremaSummary.replaceChildren();
    showError();
  };

  const rerenderDailyAnalysis = () => {
    const fields = selectedFields();
    if (
      analysisMode() === 'diario'
      && lastDailyPayload
      && fields.join(',') === lastDailyFields.join(',')
    ) {
      renderChart(lastDailyPayload, lastDailyFields);
    }
  };

  const installAxisInteractions = (axes, definitions) => {
    axisInteractionCleanup();
    if (!axes.length) return;
    let activeAxis = 0;
    const currentScales = axes.map((axis) => ({ ...axis.initialScale }));
    const initialScales = axes.map((axis) => ({ ...axis.initialScale }));
    const axisPixel = (index) => {
      const model = chart.getModel().getComponent('yAxis', index);
      const rect = model.axis.grid.getRect();
      return model.get('position') === 'left'
        ? rect.x - Number(model.get('offset') || 0)
        : rect.x + rect.width + Number(model.get('offset') || 0);
    };
    const paintActiveAxis = () => {
      chart.setOption({
        yAxis: axes.map((axis, index) => ({
          axisLine: { show: true, lineStyle: { color: index === activeAxis ? '#ffffff' : axis.axisLine.lineStyle.color, width: index === activeAxis ? 2 : 1 } },
          axisLabel: { color: index === activeAxis ? '#f2d486' : '#aebbd8' },
          nameTextStyle: { color: index === activeAxis ? '#f2d486' : '#dce5fa', fontWeight: index === activeAxis ? 'bold' : 'normal' },
        })),
      });
    };
    const activate = (index) => {
      if (!Number.isInteger(index) || index < 0 || index >= axes.length) return;
      activeAxis = index;
      paintActiveAxis();
    };
    const selectedAxisAt = (x) => astronomyLaboratoryNearestAxisIndex(
      x, axes.map((_, index) => axisPixel(index))
    );
    const applyScale = (index, scale) => {
      currentScales[index] = { ...scale };
      const definition = definitions[axes[index].group] || {};
      chart.setOption({ yAxis: axes.map((_, axisIndex) => (axisIndex === index ? {
        min: scale.min,
        max: scale.max,
        interval: scale.interval,
        axisLabel: {
          color: axisIndex === activeAxis ? '#f2d486' : '#aebbd8',
          formatter: (value) => astronomyLaboratoryFormatAxisLabel(value, axes[index].group, { ...definition, current_interval: scale.interval }),
        },
      } : {})) });
    };
    const onChartClick = (parameter) => {
      if (parameter.componentType !== 'series') return;
      activate(astronomyLaboratorySeriesAxisIndex(chart.getOption(), parameter.seriesIndex));
    };
    const onCanvasClick = (event) => {
      const index = selectedAxisAt(event.offsetX);
      if (index !== null) activate(index);
    };
    const onDoubleClick = (event) => {
      const index = selectedAxisAt(event.offsetX);
      if (index !== null) {
        activate(index);
        applyScale(index, initialScales[index]);
      }
    };
    const onWheel = (event) => {
      if (!event.shiftKey) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      const chartBounds = chartElement.getBoundingClientRect();
      const pixelY = event.clientY - chartBounds.top;
      const anchor = Number(chart.convertFromPixel({ yAxisIndex: activeAxis }, pixelY));
      if (!Number.isFinite(anchor)) return;
      applyScale(activeAxis, astronomyLaboratoryZoomScale(
        currentScales[activeAxis], anchor, event.deltaY, definitions[axes[activeAxis].group] || {}
      ));
    };
    let verticalDrag = null;
    const valueAtPointer = (event) => {
      const chartBounds = chartElement.getBoundingClientRect();
      return Number(chart.convertFromPixel(
        { yAxisIndex: activeAxis },
        event.clientY - chartBounds.top
      ));
    };
    const onPointerDown = (event) => {
      if (!event.shiftKey || event.button !== 0) return;
      const value = valueAtPointer(event);
      if (!Number.isFinite(value)) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      verticalDrag = {
        pointerId: event.pointerId,
        axisIndex: activeAxis,
        startClientY: event.clientY,
        pixelHeight: chart.getModel().getComponent('yAxis', activeAxis).axis.grid.getRect().height,
        startScale: { ...currentScales[activeAxis] },
      };
      wheelTarget.setPointerCapture?.(event.pointerId);
      chartElement.classList.add('is-panning-y-axis');
    };
    const onPointerMove = (event) => {
      if (!verticalDrag || event.pointerId !== verticalDrag.pointerId) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      const definition = definitions[axes[verticalDrag.axisIndex].group] || {};
      const valueDelta = (event.clientY - verticalDrag.startClientY)
        * (verticalDrag.startScale.max - verticalDrag.startScale.min)
        / verticalDrag.pixelHeight;
      applyScale(verticalDrag.axisIndex, astronomyLaboratoryPanScale(
        verticalDrag.startScale,
        valueDelta,
        definition
      ));
    };
    const finishVerticalDrag = (event) => {
      if (!verticalDrag || event.pointerId !== verticalDrag.pointerId) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      wheelTarget.releasePointerCapture?.(event.pointerId);
      verticalDrag = null;
      chartElement.classList.remove('is-panning-y-axis');
    };
    chart.on('click', onChartClick);
    chart.getZr().on('click', onCanvasClick);
    chart.getZr().on('dblclick', onDoubleClick);
    const wheelTarget = chart.getZr().painter.getViewportRoot();
    wheelTarget.addEventListener('wheel', onWheel, { passive: false, capture: true });
    wheelTarget.addEventListener('pointerdown', onPointerDown, { capture: true });
    wheelTarget.addEventListener('pointermove', onPointerMove, { capture: true });
    wheelTarget.addEventListener('pointerup', finishVerticalDrag, { capture: true });
    wheelTarget.addEventListener('pointercancel', finishVerticalDrag, { capture: true });
    const onRestore = () => {
      currentScales.splice(0, currentScales.length, ...initialScales.map((scale) => ({ ...scale })));
      activeAxis = 0;
      paintActiveAxis();
    };
    chart.on('restore', onRestore);
    paintActiveAxis();
    axisInteractionCleanup = () => {
      chart.off('click', onChartClick);
      chart.getZr().off('click', onCanvasClick);
      chart.getZr().off('dblclick', onDoubleClick);
      chart.off('restore', onRestore);
      wheelTarget.removeEventListener('wheel', onWheel, true);
      wheelTarget.removeEventListener('pointerdown', onPointerDown, true);
      wheelTarget.removeEventListener('pointermove', onPointerMove, true);
      wheelTarget.removeEventListener('pointerup', finishVerticalDrag, true);
      wheelTarget.removeEventListener('pointercancel', finishVerticalDrag, true);
      chartElement.classList.remove('is-panning-y-axis');
    };
  };
  const updateExtremaRangeGuidance = () => {
    const variable = extremaVariable();
    const assessment = astronomyLaboratoryExtremaRangeAssessment(
      form.elements.fecha_desde.value,
      form.elements.fecha_hasta.value,
      variable
    );
    extremaRangeGuidance.textContent = assessment.message || '';
    extremaRangeGuidance.classList.toggle('is-warning', assessment.status === 'warning');
    extremaRangeGuidance.classList.toggle('is-error', assessment.status === 'blocked');
    return assessment;
  };
  const updateAnalysisMode = ({ initializeRange = false } = {}) => {
    const extremaMode = analysisMode() === 'extremos';
    dailyControls.forEach((control) => { control.hidden = extremaMode; });
    extremaControls.hidden = !extremaMode;
    dailyRangeShortcuts.hidden = extremaMode;
    extremaRangeShortcuts.hidden = !extremaMode;
    chartTitle.textContent = extremaMode ? 'Extremos locales' : 'Serie temporal';
    if (extremaMode && initializeRange) {
      form.querySelectorAll('input[name="campos[]"]:checked').forEach((input) => {
        input.checked = false;
      });
      updateScaleGroupHelp();
      updateRelationControls();
      const fromValue = astronomyLaboratorySubtractYears(
        form.elements.fecha_hasta.value,
        5,
        config.availableYears.min
      );
      if (fromValue !== '') {
        form.elements.fecha_desde.value = fromValue;
        syncYearSelect(form.elements.fecha_desde);
      }
    }
    updateExtremaRangeGuidance();
    clearResult();
  };

  const syncYearSelect = (dateInput) => {
    const yearSelect = form.querySelector(`[data-year-select="${dateInput.name}"]`);
    const year = astronomyLaboratoryYearFromDate(dateInput.value);
    if (yearSelect && year !== '') {
      yearSelect.value = year;
    }
  };
  form.querySelectorAll('[data-year-select]').forEach((select) => {
    select.addEventListener('change', () => {
      const dateInput = form.elements[select.dataset.yearSelect];
      dateInput.value = astronomyLaboratoryDateForYear(
        select.value,
        select.dataset.yearSelect === 'fecha_desde' ? 'start' : 'end'
      );
      updateExtremaRangeGuidance();
      scheduleBackendUpdate();
    });
  });
  [form.elements.fecha_desde, form.elements.fecha_hasta].forEach((input) => {
    input.addEventListener('change', () => {
      syncYearSelect(input);
      updateExtremaRangeGuidance();
      scheduleBackendUpdate();
    });
    input.addEventListener('input', () => {
      syncYearSelect(input);
      updateExtremaRangeGuidance();
      scheduleBackendUpdate();
    });
  });
  form.querySelectorAll('[data-range-years]').forEach((button) => {
    button.addEventListener('click', () => {
      const years = Number(button.dataset.rangeYears);
      const fromValue = astronomyLaboratorySubtractYears(
        form.elements.fecha_hasta.value,
        years,
        config.availableYears.min
      );
      if (fromValue !== '') {
        form.elements.fecha_desde.value = fromValue;
        syncYearSelect(form.elements.fecha_desde);
        updateExtremaRangeGuidance();
        scheduleBackendUpdate();
      }
    });
  });
  form.querySelectorAll('[data-future-range-years]').forEach((button) => {
    button.addEventListener('click', () => {
      const years = Number(button.dataset.futureRangeYears);
      const toValue = astronomyLaboratoryAddYears(
        form.elements.fecha_desde.value,
        years,
        config.availableYears.max
      );
      if (toValue !== '') {
        form.elements.fecha_hasta.value = toValue;
        syncYearSelect(form.elements.fecha_hasta);
        updateExtremaRangeGuidance();
        scheduleBackendUpdate();
      }
    });
  });
  form.querySelector('[data-range-all]').addEventListener('click', () => {
    form.elements.fecha_desde.value = `${config.availableYears.min}-01-01`;
    form.elements.fecha_hasta.value = `${config.availableYears.max}-12-31`;
    syncYearSelect(form.elements.fecha_desde);
    syncYearSelect(form.elements.fecha_hasta);
    updateExtremaRangeGuidance();
    scheduleBackendUpdate();
  });
  form.querySelectorAll('input[name="modo"]').forEach((input) => {
    input.addEventListener('change', () => {
      updateAnalysisMode({ initializeRange: input.value === 'extremos' });
      scheduleBackendUpdate();
    });
  });
  form.elements.variable_extremos.addEventListener('change', () => {
    updateExtremaRangeGuidance();
    clearResult();
    scheduleBackendUpdate();
  });
  form.querySelectorAll('input[name="tipo_extremo"]').forEach((input) => {
    input.addEventListener('change', () => {
      clearResult();
      scheduleBackendUpdate();
    });
  });
  form.querySelectorAll('input[name="campos[]"]').forEach((input) => {
    input.addEventListener('change', () => {
      updateScaleGroupHelp();
      updateRelationControls();
      scheduleBackendUpdate();
    });
  });
  form.querySelectorAll('input[name="fases[]"]').forEach((input) => {
    input.addEventListener('change', () => scheduleBackendUpdate());
  });
  [relationSelectA, relationSelectB].forEach((select) => {
    select.addEventListener('change', () => {
      preventDuplicateRelationVariables(select);
      preferredRelationFields.a = relationSelectA.value;
      preferredRelationFields.b = relationSelectB.value;
      rerenderDailyAnalysis();
    });
  });
  relationToggles.forEach((toggle) => {
    toggle.addEventListener('change', rerenderDailyAnalysis);
  });
  form.querySelectorAll('[data-clear-selection]').forEach((button) => {
    button.addEventListener('click', () => {
      form.querySelectorAll(`input[name="${button.dataset.clearSelection}"]:checked`).forEach((input) => {
        input.checked = false;
      });
      if (button.dataset.clearSelection === 'campos[]') {
        updateScaleGroupHelp();
        updateRelationControls();
      }
      scheduleBackendUpdate();
    });
  });
  updateScaleGroupHelp();
  updateRelationControls();

  const renderChart = (payload, fields) => {
    if (typeof echarts === 'undefined') {
      throw new Error('No se pudo cargar la biblioteca del gráfico.');
    }
    chart ||= echarts.init(chartElement, 'dark', { renderer: 'canvas' });
    const dates = payload.rows.map((row) => row.fecha);
    const fieldTypes = payload.field_types || config.fieldTypes || {};
    const fieldUnits = payload.field_units || config.fieldUnits || {};
    const fieldScales = payload.field_scales || config.fieldScales || {};
    const scaleDefinitions = payload.scale_groups || config.scaleGroups || {};
    const fieldsByLabel = Object.fromEntries(fields.map((field) => [config.labels[field], field]));
    const hasEventFields = fields.some((field) => fieldTypes[field] === 'event_marker');
    const selectedGroups = astronomyLaboratorySelectedScaleGroups(fields, fieldScales);
    const relationFields = selectedRelationFields(fields);
    const relationMethods = selectedRelationMethods();
    const relationEnabled = relationMethods.length > 0
      && relationFields.length === 2
      && relationFields[0] !== relationFields[1];
    const relationBands = relationEnabled
      ? relationMethods.map((method) => ({
        method,
        label: method === 'product' ? 'Coincidencia / oposición' : 'Refuerzo positivo / negativo',
        data: astronomyLaboratoryBuildRelation(payload.rows, relationFields[0], relationFields[1], method),
      }))
      : [];
    const valuesByGroup = Object.fromEntries(selectedGroups.map((group) => [group, payload.rows.flatMap(
      (row) => fields.filter((field) => fieldScales[field] === group).map((field) => row[field])
    )]));
    const axisLayout = astronomyLaboratoryBuildAxisLayout(selectedGroups, scaleDefinitions, valuesByGroup);
    const axes = axisLayout.axes.map((axis, index) => ({
      ...axis,
      axisLine: { show: true, lineStyle: { color: colors[index % colors.length] } },
      axisLabel: {
        color: '#aebbd8',
        formatter: (value) => astronomyLaboratoryFormatAxisLabel(
          value,
          axis.group,
          { ...(scaleDefinitions[axis.group] || {}), current_interval: axis.interval }
        ),
      },
      splitLine: { show: index === 0, lineStyle: { color: 'rgba(145, 166, 210, .12)' } },
    }));
    let eventOnlyAxisIndex = null;
    if (hasEventFields && selectedGroups.length === 0) {
      eventOnlyAxisIndex = axes.length;
      axes.push({ type: 'value', min: 0, max: 1, show: false });
    }
    const markerAnchorFields = selectedGroups.length === 0
      ? []
      : fields.filter((field) => fieldScales[field] === selectedGroups[0]);
    const markerAnchorFallback = markerAnchorFields.length === 0
      ? 1
      : payload.rows
        .flatMap((row) => markerAnchorFields.map((field) => row[field]))
        .find((value) => value !== null && value !== undefined) ?? 1;

    const formatTooltip = (parameters) => {
      if (!Array.isArray(parameters) || parameters.length === 0) return '';
      const relationParameter = parameters.find((parameter) => parameter.data?.relation);
      if (relationParameter?.data?.relation) {
        const relation = relationParameter.data.relation;
        const formatRaw = (field, value) => astronomyLaboratoryFormatSeriesValue(
          value,
          fieldTypes[field],
          fieldUnits[field] || ''
        );
        const formatNormalized = (value) => value === null
          ? 'Sin dato'
          : Number(value).toLocaleString('es-AR', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
        return [
          `<strong>${escapeHtml(relation.date)}</strong>`,
          `<strong>${escapeHtml(relation.method === 'product' ? 'Coincidencia / oposición' : 'Refuerzo positivo / negativo')}</strong>`,
          `${escapeHtml(config.labels[relation.fieldA])}: ${escapeHtml(formatRaw(relation.fieldA, relation.rawA))} · normalizado ${escapeHtml(formatNormalized(relation.normalizedA))}`,
          `${escapeHtml(config.labels[relation.fieldB])}: ${escapeHtml(formatRaw(relation.fieldB, relation.rawB))} · normalizado ${escapeHtml(formatNormalized(relation.normalizedB))}`,
          `Índice: ${escapeHtml(formatNormalized(relation.index))}`,
          `<strong>${escapeHtml(relation.interpretation)}</strong>`,
        ].join('<br>');
      }
      const lines = [`<strong>${escapeHtml(parameters[0].axisValueLabel || parameters[0].name)}</strong>`];
      parameters.forEach((parameter) => {
        const field = fieldsByLabel[parameter.seriesName];
        const type = fieldTypes[field];
        const rawValue = Array.isArray(parameter.value) ? parameter.value[parameter.value.length - 1] : parameter.value;
        if (rawValue === null || rawValue === undefined || rawValue === '-') return;
        if (type === 'event_marker') {
          const distance = parameter.data?.eventDistance;
          const distanceText = Number.isFinite(Number(distance))
            ? ` · ${astronomyLaboratoryFormatNumber(distance)} km`
            : '';
          lines.push(`${parameter.marker}${escapeHtml(parameter.seriesName)}${distanceText}`);
          return;
        }
        const displayedValue = astronomyLaboratoryFormatSeriesValue(
          rawValue,
          type,
          fieldUnits[field] || ''
        );
        lines.push(`${parameter.marker}${escapeHtml(parameter.seriesName)}: ${escapeHtml(displayedValue)}`);
      });
      return lines.join('<br>');
    };

    const series = fields.map((field) => {
      const type = fieldTypes[field];
      const isEvent = type === 'event_marker';
      const axisIndex = isEvent
        ? (selectedGroups.length === 0 ? eventOnlyAxisIndex : axisLayout.indexes[selectedGroups[0]])
        : axisLayout.indexes[fieldScales[field]];
      return {
        name: config.labels[field],
        type: isEvent ? 'scatter' : 'line',
        yAxisIndex: axisIndex,
        data: payload.rows.map((row) => {
          if (!isEvent || row[field] === null) return row[field];
          const anchorValue = markerAnchorFields
            .map((anchorField) => row[anchorField])
            .find((value) => value !== null && value !== undefined);
          return {
            value: anchorValue ?? markerAnchorFallback,
            eventDistance: row._event_marker_details?.[field]?.distancia_luna_km ?? null,
          };
        }),
        showSymbol: isEvent || payload.rows.length < 100,
        symbol: isEvent ? 'circle' : 'emptyCircle',
        symbolSize: isEvent ? 13 : 5,
        smooth: false,
        connectNulls: false,
        sampling: isEvent ? undefined : 'lttb',
        lineStyle: isEvent ? undefined : { width: 1.8 },
        emphasis: { focus: 'series' },
      };
    });
    relationBands.forEach((band, index) => {
      series.push({
        name: band.label,
        type: 'heatmap',
        xAxisIndex: index + 1,
        yAxisIndex: axes.length + index,
        data: band.data.filter((point) => point.relation.index !== null),
        progressive: 1000,
        emphasis: { itemStyle: { borderColor: '#f2f5ff', borderWidth: 1 } },
      });
    });

    const chartGrids = relationEnabled
      ? [
        { ...axisLayout.grid, bottom: 118 + (relationBands.length * 60) },
        ...relationBands.map((_, index) => ({
          left: axisLayout.grid.left,
          right: axisLayout.grid.right,
          bottom: 76 + ((relationBands.length - index - 1) * 60),
          height: 26,
          containLabel: false,
        })),
      ]
      : axisLayout.grid;
    const chartXAxes = relationEnabled
      ? [
        {
          type: 'category', boundaryGap: false, data: dates,
          axisLabel: { color: '#aebbd8', hideOverlap: true },
          axisLine: { lineStyle: { color: '#52617e' } },
        },
        ...relationBands.map((_, index) => ({
          type: 'category', gridIndex: index + 1, boundaryGap: false, data: dates,
          axisLabel: { show: false }, axisTick: { show: false }, axisLine: { show: false },
        })),
      ]
      : {
        type: 'category', boundaryGap: false, data: dates,
        axisLabel: { color: '#aebbd8', hideOverlap: true },
        axisLine: { lineStyle: { color: '#52617e' } },
      };
    const chartYAxes = relationEnabled
      ? [...axes, ...relationBands.map((band, index) => ({
        type: 'category', gridIndex: index + 1, data: [band.method],
        axisLabel: { show: false },
        axisTick: { show: false }, axisLine: { show: false }, splitLine: { show: false },
      }))]
      : axes;
    const synchronizedXAxisIndexes = relationEnabled
      ? Array.from({ length: relationBands.length + 1 }, (_, index) => index)
      : 0;

    chart.setOption({
      backgroundColor: 'transparent',
      color: colors,
      animation: payload.rows.length < 1500,
      textStyle: { color: '#dce5fa' },
      legend: {
        type: 'scroll',
        top: 4,
        textStyle: { color: '#dce5fa' },
        data: fields.map((field) => config.labels[field]),
      },
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'cross' },
        formatter: formatTooltip,
      },
      toolbox: {
        right: 8,
        top: 34,
        feature: {
          dataZoom: { yAxisIndex: 'none', title: { zoom: 'Seleccionar zoom', back: 'Restablecer zoom' } },
          restore: { title: 'Restablecer' },
        },
        iconStyle: { borderColor: '#b9c9ed' },
      },
      grid: chartGrids,
      xAxis: chartXAxes,
      yAxis: chartYAxes,
      dataZoom: [
        { type: 'inside', xAxisIndex: synchronizedXAxisIndexes, zoomOnMouseWheel: true, moveOnMouseWheel: true, moveOnMouseMove: true },
        { type: 'slider', xAxisIndex: synchronizedXAxisIndexes, bottom: 22, height: 28, borderColor: '#405079', textStyle: { color: '#b9c9ed' } },
      ],
      ...(relationEnabled ? {
        visualMap: relationBands.map((band, index) => ({
          show: false,
          type: 'continuous', min: -1, max: 1, dimension: 2,
          seriesIndex: series.length - relationBands.length + index,
          inRange: { color: band.method === 'product'
            ? ['#a95668', '#343b52', '#4f8a76']
            : ['#557cac', '#343b52', '#b58b4a'] },
        })),
        axisPointer: { link: [{ xAxisIndex: synchronizedXAxisIndexes }] },
      } : {}),
      series,
    }, true);
    if (relationEnabled) {
      alignRelationBands = () => {
        const mainRect = chart.getModel().getComponent('grid', 0)?.coordinateSystem?.getRect();
        if (!mainRect) return;
        const edges = astronomyLaboratoryRelationGridEdges(chart.getWidth(), mainRect);
        const wrapTitles = mainRect.width < 520;
        const titleText = (band) => {
          if (!wrapTitles) return band.label;
          return band.method === 'product'
            ? 'Coincidencia /\noposición'
            : 'Refuerzo positivo /\nnegativo';
        };
        chart.setOption({
          grid: [
            {},
            ...relationBands.map(() => ({ ...edges, containLabel: false })),
          ],
          graphic: relationBands.map((band, index) => {
            const bandRect = chart.getModel().getComponent('grid', index + 1)?.coordinateSystem?.getRect();
            return {
              id: `relation-title-${band.method}`,
              type: 'text',
              silent: true,
              left: edges.left,
              top: Math.max(0, (bandRect?.y || 0) - (wrapTitles ? 31 : 19)),
              style: {
                text: titleText(band),
                fill: '#9eaccb',
                font: '11px sans-serif',
                lineHeight: 12,
                width: mainRect.width,
                overflow: 'break',
              },
            };
          }),
        });
      };
      alignRelationBands();
    } else {
      alignRelationBands = () => {};
    }
    installAxisInteractions(axes.filter((axis) => axis.group), scaleDefinitions);
  };

  const renderExtremaChart = (payload) => {
    if (typeof echarts === 'undefined') {
      throw new Error('No se pudo cargar la biblioteca del gráfico.');
    }
    chart ||= echarts.init(chartElement, 'dark', { renderer: 'canvas' });
    alignRelationBands = () => {};
    const variable = config.extremaVariables[payload.variable];
    const series = astronomyLaboratoryBuildExtremaSeries(payload);
    const unit = payload.unidad ? ` ${payload.unidad}` : '';
    const formatValue = (value) => astronomyLaboratoryFormatSeriesValue(
      value,
      payload.field_type,
      unit
    );
    const extremaAxis = astronomyLaboratoryBuildExtremaAxis(payload, config.scaleGroups);
    chart.setOption({
      backgroundColor: 'transparent',
      color: ['#f2d486', '#8eb4ff'],
      animation: true,
      textStyle: { color: '#dce5fa' },
      legend: {
        type: 'scroll',
        top: 4,
        textStyle: { color: '#dce5fa' },
        data: series.map((item) => item.name),
      },
      tooltip: {
        trigger: 'item',
        formatter: (parameter) => {
          const value = Array.isArray(parameter.value) ? parameter.value[1] : parameter.value;
          const date = Array.isArray(parameter.value) ? parameter.value[0] : parameter.name;
          return `<strong>${escapeHtml(date)}</strong><br>${parameter.marker}${escapeHtml(parameter.seriesName)}: ${escapeHtml(formatValue(value))}`;
        },
      },
      toolbox: {
        right: 8,
        top: 34,
        feature: {
          dataZoom: { yAxisIndex: 'none', title: { zoom: 'Seleccionar zoom', back: 'Restablecer zoom' } },
          restore: { title: 'Restablecer' },
        },
        iconStyle: { borderColor: '#b9c9ed' },
      },
      grid: { top: 100, right: 58, bottom: 92, left: 82, containLabel: true },
      xAxis: {
        type: 'time',
        axisLabel: { color: '#aebbd8', hideOverlap: true },
        axisLine: { lineStyle: { color: '#52617e' } },
      },
      yAxis: extremaAxis,
      dataZoom: [
        { type: 'inside', xAxisIndex: 0, zoomOnMouseWheel: true, moveOnMouseWheel: true, moveOnMouseMove: true },
        { type: 'slider', xAxisIndex: 0, bottom: 22, height: 28, borderColor: '#405079', textStyle: { color: '#b9c9ed' } },
      ],
      series,
    }, true);
    installAxisInteractions([extremaAxis], config.scaleGroups);

    const maximumLabel = payload.series_labels.maximos;
    const minimumLabel = payload.series_labels.minimos;
    extremaSummary.innerHTML = [
      `<div><dt>Período:</dt><dd>${escapeHtml(payload.desde.slice(0, 4))}–${escapeHtml(payload.hasta.slice(0, 4))}</dd></div>`,
      `<div><dt>Variable:</dt><dd>${escapeHtml(variable.label)}</dd></div>`,
      `<div><dt>${escapeHtml(maximumLabel)} detectados:</dt><dd>${payload.conteos.maximos.toLocaleString('es-AR')}</dd></div>`,
      `<div><dt>${escapeHtml(minimumLabel)} detectados:</dt><dd>${payload.conteos.minimos.toLocaleString('es-AR')}</dd></div>`,
    ].join('');
    extremaSummary.hidden = false;
    if (payload.advertencia) {
      extremaRangeGuidance.textContent = payload.advertencia;
      extremaRangeGuidance.classList.add('is-warning');
    }
  };

  const requestGraph = async () => {
    showError();
    extremaSummary.hidden = true;
    const mode = analysisMode();
    const fields = mode === 'diario' ? selectedFields() : [];
    if (mode === 'diario') {
      if (fields.length === 0) {
        showError('Seleccioná al menos una variable.');
        clearResult();
        lastRequestKey = '';
        return;
      }
      const scaleGroups = selectedScaleGroups(fields);
      if (scaleGroups.length > 4) {
        showError('Seleccionaste más de cuatro grupos de escala. Podés elegir varias variables dentro de un mismo grupo sin agregar otro eje.');
        return;
      }
    } else {
      const assessment = updateExtremaRangeGuidance();
      if (assessment.status === 'blocked' || assessment.status === 'invalid') {
        showError(assessment.message || 'El rango de fechas no es válido para calcular extremos.');
        return;
      }
    }
    const parameters = new URLSearchParams({
      modo: mode,
      fecha_desde: form.elements.fecha_desde.value,
      fecha_hasta: form.elements.fecha_hasta.value,
    });
    if (mode === 'diario') {
      parameters.set('campos', fields.join(','));
      const phases = selectedPhases();
      if (phases.length > 0) parameters.set('fases', phases.join(','));
    } else {
      parameters.set('variable', form.elements.variable_extremos.value);
      parameters.set('tipo_extremo', form.elements.tipo_extremo.value);
    }
    const requestKey = parameters.toString();
    if (requestKey === lastRequestKey) return;
    lastRequestKey = requestKey;
    activeRequest?.abort();
    const request = new AbortController();
    activeRequest = request;
    setLoading(true);
    try {
      const response = await fetch(`${config.endpoint}?${parameters}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: request.signal,
      });
      const payload = await response.json().catch(() => null);
      const validPayload = mode === 'diario'
        ? Array.isArray(payload?.rows)
        : payload?.modo === 'extremos' && payload?.series && payload?.conteos;
      if (!response.ok || !validPayload) {
        throw new Error(payload?.error || 'No se pudieron obtener los datos.');
      }
      if (mode === 'diario') {
        lastDailyPayload = payload;
        lastDailyFields = [...fields];
        count.textContent = `${payload.rows.length.toLocaleString('es-AR')} registros obtenidos.`;
        renderChart(payload, fields);
      } else {
        const total = payload.conteos.maximos + payload.conteos.minimos;
        count.textContent = `${total.toLocaleString('es-AR')} extremos detectados.`;
        renderExtremaChart(payload);
      }
    } catch (exception) {
      if (exception.name === 'AbortError') return;
      lastRequestKey = '';
      count.textContent = 'No se obtuvieron registros.';
      showError(exception.message || 'No se pudo actualizar el gráfico.');
    } finally {
      if (activeRequest === request) {
        activeRequest = null;
        setLoading(false);
      }
    }
  };
  const debouncedRequest = astronomyLaboratoryCreateDebouncedRequest(requestGraph);
  scheduleBackendUpdate = () => {
    activeRequest?.abort();
    lastRequestKey = '';
    debouncedRequest.schedule();
  };
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    debouncedRequest.flush();
  });

  window.addEventListener('resize', () => {
    chart?.resize();
    alignRelationBands();
  });
  updateAnalysisMode();
  debouncedRequest.flush();
});
