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

const astronomyLaboratoryAutomaticAxisMargin = ({ min, max }) => {
  const range = max - min;
  const margin = range > 0
    ? range * 0.05
    : Math.max(Math.abs(min) * 0.05, 1);
  return { min: min - margin, max: max + margin };
};

const astronomyLaboratoryFormatAxisLabel = (value, group, definition) => {
  const epsilon = 0.000001;
  if (
    (definition.data_min !== undefined && value < definition.data_min - epsilon)
    || (definition.data_max !== undefined && value > definition.data_max + epsilon)
  ) {
    return '';
  }
  return group === 'time_fraction' || group === 'time_duration'
    ? astronomyLaboratoryFormatDayFraction(value)
    : astronomyLaboratoryFormatNumber(value);
};

const astronomyLaboratoryBuildAxisLayout = (groups, definitions) => {
  const axes = groups.map((group, index) => {
    const definition = definitions[group] || {};
    const automaticMinimum = (extent) => astronomyLaboratoryAutomaticAxisMargin(extent).min;
    const automaticMaximum = (extent) => astronomyLaboratoryAutomaticAxisMargin(extent).max;
    return {
      group,
      name: definition.label || group,
      type: 'value',
      position: index % 2 === 0 ? 'left' : 'right',
      offset: Math.floor(index / 2) * 76,
      scale: definition.min === undefined && definition.max === undefined,
      min: definition.min === undefined ? automaticMinimum : definition.min,
      max: definition.max === undefined ? automaticMaximum : definition.max,
      ...(definition.interval === undefined ? {} : { interval: definition.interval }),
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
      top: 90,
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

const astronomyLaboratoryBuildExtremaAxis = (payload, scaleDefinitions) => ({
  type: 'value',
  name: scaleDefinitions[payload.scale_group]?.label || payload.variable_label,
  scale: true,
  min: (extent) => astronomyLaboratoryAutomaticAxisMargin(extent).min,
  max: (extent) => astronomyLaboratoryAutomaticAxisMargin(extent).max,
  axisLabel: {
    color: '#aebbd8',
    formatter: payload.field_type === 'time_duration'
      ? (value) => astronomyLaboratoryFormatAxisLabel(
        value,
        'time_duration',
        scaleDefinitions.time_duration || {}
      )
      : (value) => astronomyLaboratoryFormatNumber(value),
  },
  axisLine: { show: true, lineStyle: { color: '#f2d486' } },
  splitLine: { lineStyle: { color: 'rgba(145, 166, 210, .12)' } },
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
  const config = window.astronomyLaboratoryConfig;
  if (!form || !chartElement || !config) return;

  let chart = null;
  const colors = ['#8eb4ff', '#f2d486', '#89d6c6', '#d9a5ff', '#ff9f9f', '#9ccf75', '#f7b267'];
  const setLoading = (active) => {
    loading.hidden = !active;
    form.querySelector('button[type="submit"]').disabled = active;
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
  const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
  const clearResult = () => {
    chart?.clear();
    count.textContent = 'Sin datos consultados.';
    extremaSummary.hidden = true;
    extremaSummary.replaceChildren();
    showError();
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
    });
  });
  [form.elements.fecha_desde, form.elements.fecha_hasta].forEach((input) => {
    input.addEventListener('change', () => {
      syncYearSelect(input);
      updateExtremaRangeGuidance();
    });
    input.addEventListener('input', () => {
      syncYearSelect(input);
      updateExtremaRangeGuidance();
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
      }
    });
  });
  form.querySelector('[data-range-all]').addEventListener('click', () => {
    form.elements.fecha_desde.value = `${config.availableYears.min}-01-01`;
    form.elements.fecha_hasta.value = `${config.availableYears.max}-12-31`;
    syncYearSelect(form.elements.fecha_desde);
    syncYearSelect(form.elements.fecha_hasta);
    updateExtremaRangeGuidance();
  });
  form.querySelectorAll('input[name="modo"]').forEach((input) => {
    input.addEventListener('change', () => updateAnalysisMode({ initializeRange: input.value === 'extremos' }));
  });
  form.elements.variable_extremos.addEventListener('change', () => {
    updateExtremaRangeGuidance();
    clearResult();
  });
  form.querySelectorAll('input[name="tipo_extremo"]').forEach((input) => {
    input.addEventListener('change', clearResult);
  });
  form.querySelectorAll('input[name="campos[]"]').forEach((input) => {
    input.addEventListener('change', updateScaleGroupHelp);
  });
  updateScaleGroupHelp();

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
    const axisLayout = astronomyLaboratoryBuildAxisLayout(selectedGroups, scaleDefinitions);
    const axes = axisLayout.axes.map((axis, index) => ({
      ...axis,
      axisLine: { show: true, lineStyle: { color: colors[index % colors.length] } },
      axisLabel: {
        color: '#aebbd8',
        formatter: (value) => astronomyLaboratoryFormatAxisLabel(
          value,
          axis.group,
          scaleDefinitions[axis.group] || {}
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
      const lines = [`<strong>${escapeHtml(parameters[0].axisValueLabel || parameters[0].name)}</strong>`];
      parameters.forEach((parameter) => {
        const field = fieldsByLabel[parameter.seriesName];
        const type = fieldTypes[field];
        const rawValue = Array.isArray(parameter.value) ? parameter.value.at(-1) : parameter.value;
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
      grid: axisLayout.grid,
      xAxis: {
        type: 'category',
        boundaryGap: false,
        data: dates,
        axisLabel: { color: '#aebbd8', hideOverlap: true },
        axisLine: { lineStyle: { color: '#52617e' } },
      },
      yAxis: axes,
      dataZoom: [
        { type: 'inside', xAxisIndex: 0, zoomOnMouseWheel: true, moveOnMouseWheel: true, moveOnMouseMove: true },
        { type: 'slider', xAxisIndex: 0, bottom: 22, height: 28, borderColor: '#405079', textStyle: { color: '#b9c9ed' } },
      ],
      series,
    }, true);
  };

  const renderExtremaChart = (payload) => {
    if (typeof echarts === 'undefined') {
      throw new Error('No se pudo cargar la biblioteca del gráfico.');
    }
    chart ||= echarts.init(chartElement, 'dark', { renderer: 'canvas' });
    const variable = config.extremaVariables[payload.variable];
    const series = astronomyLaboratoryBuildExtremaSeries(payload);
    const unit = payload.unidad ? ` ${payload.unidad}` : '';
    const formatValue = (value) => astronomyLaboratoryFormatSeriesValue(
      value,
      payload.field_type,
      unit
    );
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
      grid: { top: 90, right: 58, bottom: 92, left: 82, containLabel: true },
      xAxis: {
        type: 'time',
        axisLabel: { color: '#aebbd8', hideOverlap: true },
        axisLine: { lineStyle: { color: '#52617e' } },
      },
      yAxis: astronomyLaboratoryBuildExtremaAxis(payload, config.scaleGroups),
      dataZoom: [
        { type: 'inside', xAxisIndex: 0, zoomOnMouseWheel: true, moveOnMouseWheel: true, moveOnMouseMove: true },
        { type: 'slider', xAxisIndex: 0, bottom: 22, height: 28, borderColor: '#405079', textStyle: { color: '#b9c9ed' } },
      ],
      series,
    }, true);

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

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    showError();
    extremaSummary.hidden = true;
    const mode = analysisMode();
    const fields = mode === 'diario' ? selectedFields() : [];
    if (mode === 'diario') {
      if (fields.length === 0) {
        showError('Seleccioná al menos una variable.');
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
    setLoading(true);
    try {
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
      const response = await fetch(`${config.endpoint}?${parameters}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const payload = await response.json().catch(() => null);
      const validPayload = mode === 'diario'
        ? Array.isArray(payload?.rows)
        : payload?.modo === 'extremos' && payload?.series && payload?.conteos;
      if (!response.ok || !validPayload) {
        throw new Error(payload?.error || 'No se pudieron obtener los datos.');
      }
      if (mode === 'diario') {
        count.textContent = `${payload.rows.length.toLocaleString('es-AR')} registros obtenidos.`;
        renderChart(payload, fields);
      } else {
        const total = payload.conteos.maximos + payload.conteos.minimos;
        count.textContent = `${total.toLocaleString('es-AR')} extremos detectados.`;
        renderExtremaChart(payload);
      }
    } catch (exception) {
      count.textContent = 'No se obtuvieron registros.';
      showError(exception.message || 'No se pudo actualizar el gráfico.');
    } finally {
      setLoading(false);
    }
  });

  window.addEventListener('resize', () => chart?.resize());
  updateAnalysisMode();
  form.requestSubmit();
});
