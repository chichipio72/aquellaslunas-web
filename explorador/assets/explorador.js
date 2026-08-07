(() => {
    'use strict';
    const form = document.querySelector('#explorer-form');
    const status = document.querySelector('#request-status');
    const metrics = document.querySelector('#metrics');
    const technicalMetrics = document.querySelector('#technical-metrics');
    const chartElement = document.querySelector('#chart');
    const chartMessage = document.querySelector('#chart-message');
    const localTimeChartHelp = document.querySelector('#local-time-chart-help');
    const dateError = document.querySelector('#date-control-error');
    const processPanel = document.querySelector('#process-panel');
    const processStage = document.querySelector('#process-stage');
    const processDays = document.querySelector('#process-days');
    const processPercent = document.querySelector('#process-percent');
    const processTrack = document.querySelector('#process-track');
    const processFill = document.querySelector('#process-fill');
    const processElapsed = document.querySelector('#process-elapsed');
    const cancelButton = document.querySelector('#cancel-calculation');
    const allDays = document.querySelector('#all-days');
    const phaseInputs = [...form.querySelectorAll('input[name="fases[]"]')];
    const relationMethods = [...form.querySelectorAll('[data-relation-method]')];
    const relationHelp = document.querySelector('#relation-help');
    const relationSelectors = document.querySelector('#relation-selectors');
    const relationSelectA = document.querySelector('#relation-variable-a');
    const relationSelectB = document.querySelector('#relation-variable-b');
    const relationPhaseNote = document.querySelector('#relation-phase-note');
    const modeInputs = [...form.querySelectorAll('input[name="modo"]')];
    const dailyControls = [...form.querySelectorAll('[data-daily-controls]')];
    const extremaPanel = document.querySelector('#extrema-panel');
    const extremaVariable = form.elements.variable;
    const extremaGuidance = document.querySelector('#extrema-range-guidance');
    const modeNotice = document.querySelector('#mode-notice');
    const fromDate = form.elements.fecha_desde;
    const toDate = form.elements.fecha_hasta;
    const fromYear = form.elements.anio_desde;
    const toYear = form.elements.anio_hasta;
    const locationPanel = document.querySelector('.observation-location');
    const locationValue = document.querySelector('#calculation-location-value');
    const sharedLocationSource = document.querySelector('#shared-location-source');
    const changeLocation = document.querySelector('#change-location');
    const officialLocation = JSON.parse(locationPanel.dataset.officialLocation);
    const shareButton = document.querySelector('#share-configuration');
    const shareStatus = document.querySelector('#shared-configuration-status');
    const shareDialog = document.querySelector('#share-dialog');
    const shareUrlInput = document.querySelector('#share-url');
    const shareDialogStatus = document.querySelector('#share-dialog-status');
    const shareDialogCopy = document.querySelector('#share-dialog-copy');
    const shareDialogClose = document.querySelector('#share-dialog-close');
    const sharedNotice = document.querySelector('#shared-configuration-notice');
    const shareTools = window.ExplorerShareConfig;
    const timeSeriesSegments = window.ExplorerTimeSeriesSegments;
    const dateTools = window.ExplorerDateTools;
    const verticalAxisTools = window.ExplorerVerticalAxisControl;
    const scaleGroups = window.ExplorerScaleGroups || {};
    let chart = null;
    let verticalAxisController = null;
    let activeController = null;
    let elapsedTimer = null;
    let processStartedAt = 0;
    let lastOverallPercent = 0;
    let lastData = null;
    let sharedFieldOrder = null;
    let activeLocation = {...officialLocation};
    let sharedLocationActive = false;
    let alignRelationBands = () => {};
    const preferredRelationFields = {a: '', b: ''};
    const relationAnalysis = window.ExplorerRelationAnalysis;
    const currentMode = () => form.elements.modo.value;
    const modeDateState = dateTools.createModeDateState(fromDate.value, toDate.value);
    const verticalAxisBounds = {
        percent: {min: 0, max: 100},
        local_time: {min: 0, max: 24},
        duration: {min: 0, max: 26},
        azimuth: {min: 0, max: 360},
        hours_angle: {min: 0, max: 24},
    };
    const scaleTitle = group => {
        const presentation = scaleGroups[group];
        if (!presentation) return 'Magnitud';
        return presentation.unit ? `${presentation.label} (${presentation.unit})` : presentation.label;
    };
    const chartColors = ['#dfc47d', '#76a9fa', '#d28cff', '#6dd8b0', '#ff8f70', '#a8c76f', '#dca3c6'];

    function ensureChart() {
        if (chart) return chart;
        chart = window.echarts.init(chartElement, null, {renderer: 'canvas'});
        verticalAxisController = verticalAxisTools?.createController(chart, () => alignRelationBands());
        return chart;
    }

    const metric = (label, value) => `<div><dt>${label}</dt><dd>${value}</dd></div>`;
    const number = (value, digits = 1) => Number(value).toLocaleString('es-AR', {maximumFractionDigits: digits});
    const roundedAxisDigits = {solar_distance: 0, equation_of_time: 0,
        angular_separation: 0, lunar_angular_diameter: 1};
    const roundedAxisStep = {solar_distance: 1000, equation_of_time: 1,
        angular_separation: 1, lunar_angular_diameter: 0.1};
    const localTime = value => {
        if (value === null || value === undefined) return '—';
        let minutes = Math.round(Number(value) * 60);
        minutes = ((minutes % 1440) + 1440) % 1440;
        return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
    };
    const localTimeAxis = value => Number(value) === 24 ? '24:00' : localTime(value);

    function showDateError(message = '') {
        dateError.textContent = message;
        dateError.hidden = message === '';
    }

    function updateYearFromDate(dateInput, yearInput) {
        const date = dateTools.parseDate(dateInput.value);
        if (date) {
            yearInput.value = dateInput.value.slice(0, 4);
            showDateError();
        }
    }

    function applyYear(yearInput, dateInput, boundary) {
        const value = dateTools.yearBoundary(yearInput.value, boundary);
        if (!value) {
            showDateError('Ingresá un año entero entre 1000 y 9999.');
            yearInput.focus();
            return;
        }
        dateInput.value = value;
        showDateError();
    }

    function validateDateOrder() {
        if (!dateTools.validYear(fromYear.value) || !dateTools.validYear(toYear.value)) {
            showDateError('Ingresá años enteros entre 1000 y 9999.');
            return false;
        }
        if (!dateTools.parseDate(fromDate.value) || !dateTools.parseDate(toDate.value)) {
            showDateError('Completá dos fechas reales.');
            return false;
        }
        if (fromDate.value > toDate.value) {
            showDateError('La fecha desde no puede ser posterior a la fecha hasta.');
            return false;
        }
        showDateError();
        return true;
    }

    const clockNow = () => typeof performance !== 'undefined' ? performance.now() : Date.now();

    function formatElapsed(milliseconds) {
        const tenths = Math.max(0, Math.floor(milliseconds / 100));
        const minutes = Math.floor(tenths / 600);
        const seconds = Math.floor(tenths / 10) % 60;
        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')},${tenths % 10}`;
    }

    function stopElapsedTimer() {
        if (elapsedTimer !== null) window.clearInterval(elapsedTimer);
        elapsedTimer = null;
        if (processStartedAt) processElapsed.textContent = formatElapsed(clockNow() - processStartedAt);
    }

    function startElapsedTimer() {
        stopElapsedTimer();
        processStartedAt = clockNow();
        processElapsed.textContent = '00:00,0';
        elapsedTimer = window.setInterval(() => {
            processElapsed.textContent = formatElapsed(clockNow() - processStartedAt);
        }, 100);
    }

    function updateProcess({state = 'running', stage, days, percent, indeterminate = false}) {
        processPanel.dataset.state = state;
        processPanel.hidden = false;
        if (stage !== undefined) processStage.textContent = stage;
        if (days !== undefined) processDays.textContent = days;
        processTrack.classList.toggle('is-indeterminate', indeterminate);
        if (indeterminate) {
            processTrack.removeAttribute('aria-valuenow');
            processPercent.textContent = '—';
            return;
        }
        const safePercent = Math.max(lastOverallPercent, Math.min(100, Number(percent) || 0));
        lastOverallPercent = safePercent;
        processTrack.setAttribute('aria-valuenow', String(Math.round(safePercent)));
        processFill.style.width = `${safePercent}%`;
        processPercent.textContent = `${number(safePercent, 1)} %`;
    }

    function beginProcess(indeterminate) {
        lastOverallPercent = 0;
        processFill.style.width = '0%';
        cancelButton.disabled = false;
        cancelButton.hidden = false;
        updateProcess({stage: 'Preparando cálculo…', days: '', percent: 0, indeterminate});
        startElapsedTimer();
    }

    function cancelActiveRequest(message = 'Solicitud cancelada por un cambio en los controles.') {
        if (!activeController) return;
        activeController.abort();
        activeController = null;
        form.querySelector('button[type="submit"]').disabled = false;
        cancelButton.disabled = true;
        cancelButton.hidden = true;
        stopElapsedTimer();
        updateProcess({state: 'cancelled', stage: 'Cálculo cancelado', days: '', percent: lastOverallPercent});
        status.className = '';
        status.textContent = message;
    }

    const coordinate = value => Number(value).toLocaleString('es-AR', {
        minimumFractionDigits: 4, maximumFractionDigits: 4,
    }).replace('-', '−');

    function updateLocationDisplay() {
        const name = activeLocation.name ? `${activeLocation.name} · ` : '';
        locationValue.textContent = `${name}${coordinate(activeLocation.latitude)}°, ${coordinate(activeLocation.longitude)}° · ${activeLocation.timezone}`;
        sharedLocationSource.hidden = !sharedLocationActive;
    }

    changeLocation.addEventListener('click', () => cancelActiveRequest('Solicitud cancelada para cambiar la ubicación.'));

    fromYear.addEventListener('change', () => { cancelActiveRequest(); applyYear(fromYear, fromDate, '01-01'); });
    toYear.addEventListener('change', () => { cancelActiveRequest(); applyYear(toYear, toDate, '12-31'); });
    fromDate.addEventListener('input', () => updateYearFromDate(fromDate, fromYear));
    toDate.addEventListener('input', () => updateYearFromDate(toDate, toYear));
    form.querySelectorAll('[data-range-direction]').forEach(button => {
        button.addEventListener('click', () => {
            cancelActiveRequest();
            const years = Number(button.dataset.rangeYears);
            const direction = button.dataset.rangeDirection;
            const value = direction === 'backward'
                ? dateTools.backwardRangeStart(toDate.value, years)
                : dateTools.forwardRangeEnd(fromDate.value, years);
            if (!value) {
                showDateError('No se pudo construir el rango con la fecha indicada.');
                return;
            }
            if (direction === 'backward') {
                fromDate.value = value;
                updateYearFromDate(fromDate, fromYear);
            } else {
                toDate.value = value;
                updateYearFromDate(toDate, toYear);
            }
            validateDateOrder();
        });
    });
    allDays.addEventListener('change', () => {
        if (allDays.checked) phaseInputs.forEach(input => { input.checked = false; });
        else if (!phaseInputs.some(input => input.checked)) allDays.checked = true;
        relationPhaseNote.hidden = allDays.checked;
    });
    phaseInputs.forEach(input => input.addEventListener('change', () => {
        allDays.checked = !phaseInputs.some(option => option.checked);
        relationPhaseNote.hidden = allDays.checked;
    }));

    const orderedSelectedFieldInputs = () => {
        const selected = [...form.querySelectorAll('input[name="campos[]"]:checked')];
        if (!sharedFieldOrder) return selected;
        const byValue = new Map(selected.map(input => [input.value, input]));
        return [...sharedFieldOrder.map(field => byValue.get(field)).filter(Boolean),
            ...selected.filter(input => !sharedFieldOrder.includes(input.value))];
    };
    const selectedNumericFields = () => orderedSelectedFieldInputs()
        .filter(input => input.dataset.valueType === 'number').map(input => input.value);

    function fillRelationSelect(select, fields, preferred) {
        select.replaceChildren(...fields.map(field => {
            const option = document.createElement('option');
            option.value = field;
            option.textContent = lastData?.field_metadata?.[field]?.shortName
                || form.querySelector(`input[name="campos[]"][value="${field}"]`)?.closest('label')?.querySelector('strong')?.textContent
                || field;
            return option;
        }));
        if (fields.includes(preferred)) select.value = preferred;
    }

    function preventDuplicateRelationVariables(changedSelect = null) {
        if (relationSelectA.value === relationSelectB.value) {
            const target = changedSelect === relationSelectB ? relationSelectA : relationSelectB;
            const forbidden = target === relationSelectA ? relationSelectB.value : relationSelectA.value;
            target.value = [...target.options].find(option => option.value !== forbidden)?.value || '';
        }
        [...relationSelectA.options].forEach(option => { option.disabled = option.value === relationSelectB.value; });
        [...relationSelectB.options].forEach(option => { option.disabled = option.value === relationSelectA.value; });
    }

    function updateRelationControls() {
        const fields = selectedNumericFields();
        const suspended = currentMode() !== 'diario';
        relationMethods.forEach(method => { method.disabled = suspended || fields.length < 2; });
        const activeMethod = relationMethods.some(method => method.checked && !method.disabled);
        const controlState = relationAnalysis.controlState(fields, activeMethod, !suspended);
        if (suspended) {
            relationSelectors.hidden = true;
            relationHelp.textContent = 'El análisis de relación no se aplica en Extremos locales.';
            return;
        }
        if (fields.length < 2) {
            relationSelectors.hidden = true;
            relationHelp.textContent = 'Seleccioná al menos dos variables.';
            return;
        }
        if (fields.length === 2) {
            relationSelectors.hidden = true;
            relationHelp.textContent = `Se analizarán ${fields.map(field => lastData?.field_metadata?.[field]?.shortName || field).join(' y ')}.`;
            return;
        }
        if (!controlState.showSelectors) {
            relationSelectors.hidden = true;
            relationHelp.textContent = 'Activá un método para elegir las variables de la comparación.';
            return;
        }
        fillRelationSelect(relationSelectA, fields, preferredRelationFields.a || fields[0]);
        fillRelationSelect(relationSelectB, fields, preferredRelationFields.b || fields[1]);
        preventDuplicateRelationVariables();
        preferredRelationFields.a = relationSelectA.value;
        preferredRelationFields.b = relationSelectB.value;
        relationSelectors.hidden = false;
        relationHelp.textContent = 'Elegí dos variables seleccionadas para construir las bandas.';
    }

    function selectedRelationPair(fields) {
        if (fields.length === 2) {
            return fields.includes(preferredRelationFields.a) && fields.includes(preferredRelationFields.b)
                && preferredRelationFields.a !== preferredRelationFields.b
                ? [preferredRelationFields.a, preferredRelationFields.b] : fields;
        }
        if (fields.length > 2) return [relationSelectA.value, relationSelectB.value];
        return [];
    }

    function rerenderRelationAnalysis() {
        if (lastData) draw(lastData, true);
    }

    [relationSelectA, relationSelectB].forEach(select => select.addEventListener('change', () => {
        preventDuplicateRelationVariables(select);
        preferredRelationFields.a = relationSelectA.value;
        preferredRelationFields.b = relationSelectB.value;
        rerenderRelationAnalysis();
    }));
    relationMethods.forEach(method => method.addEventListener('change', () => {
        updateRelationControls();
        rerenderRelationAnalysis();
    }));
    form.querySelectorAll('input[name="campos[]"]').forEach(input => input.addEventListener('change', () => {
        updateRelationControls();
        rerenderRelationAnalysis();
    }));
    updateRelationControls();

    function updateExtremaGuidance() {
        const option = extremaVariable.selectedOptions[0];
        if (!option || !dateTools.parseDate(fromDate.value) || !dateTools.parseDate(toDate.value)) {
            extremaGuidance.textContent = '';
            extremaGuidance.classList.remove('is-warning');
            return;
        }
        const minimumEnd = dateTools.forwardRangeEnd(fromDate.value, Number(option.dataset.minimumYears));
        const recommendedEnd = dateTools.forwardRangeEnd(fromDate.value, Number(option.dataset.recommendedYears));
        if (minimumEnd && toDate.value < minimumEnd) {
            extremaGuidance.textContent = `Rango corto: se sugieren al menos ${option.dataset.minimumYears} años para esta variable.`;
            extremaGuidance.classList.add('is-warning');
        } else if (recommendedEnd && toDate.value < recommendedEnd) {
            extremaGuidance.textContent = `Para observar mejor el patrón se recomiendan ${option.dataset.recommendedYears} años.`;
            extremaGuidance.classList.add('is-warning');
        } else {
            extremaGuidance.textContent = `El rango cumple la recomendación de ${option.dataset.recommendedYears} años.`;
            extremaGuidance.classList.remove('is-warning');
        }
    }

    function updateMode() {
        const nextMode = currentMode();
        const dates = modeDateState.switchTo(nextMode, fromDate.value, toDate.value);
        if (dates) {
            fromDate.value = dates.from;
            toDate.value = dates.to;
            updateYearFromDate(fromDate, fromYear);
            updateYearFromDate(toDate, toYear);
        }
        const extrema = nextMode === 'extremos';
        if (extrema) {
            const hadPhases = phaseInputs.some(input => input.checked);
            phaseInputs.forEach(input => { input.checked = false; });
            allDays.checked = true;
            relationPhaseNote.hidden = true;
            modeNotice.textContent = hadPhases
                ? 'Se restableció “Todos los días”: Extremos locales requiere una serie diaria continua.'
                : 'Extremos locales utiliza una serie diaria continua.';
        } else {
            modeNotice.textContent = '';
        }
        dailyControls.forEach(control => { control.hidden = extrema; });
        extremaPanel.hidden = !extrema;
        updateRelationControls();
        updateExtremaGuidance();
        if (lastData) draw(lastData, true);
    }

    modeInputs.forEach(input => input.addEventListener('change', updateMode));
    extremaVariable.addEventListener('change', updateExtremaGuidance);
    [fromDate, toDate].forEach(input => input.addEventListener('change', updateExtremaGuidance));

    function showMetrics(data) {
        if (!metrics || !technicalMetrics) return;
        const m = data.metrics;
        metrics.innerHTML = [
            metric('Fechas calculadas', number(m.processed_days ?? m.requested_days ?? m.days, 0)),
            metric('Período', `${fromDate.value} — ${toDate.value}`),
            metric('Ubicación', activeLocation.name || `${activeLocation.latitude}, ${activeLocation.longitude}`),
            metric('Tiempo total', `${number(m.total_ms, 2)} ms`),
            metric('Tamaño de la serie', `${number(m.approximate_json_bytes / 1024, 1)} KiB`),
        ].join('');
        technicalMetrics.innerHTML = [
            metric('Fechas solicitadas', number(m.requested_days ?? m.days, 0)),
            ...(m.calendar_days !== undefined ? [metric('Días calendario', number(m.calendar_days, 0))] : []),
            ...(m.phase_query_ms > 0 ? [metric('Consulta de fases', `${number(m.phase_query_ms, 2)} ms`)] : []),
            metric('Preparación', `${number(m.planning_ms, 2)} ms`),
            metric('Astronomía', `${number(m.astronomical_calculation_ms, 2)} ms`),
            metric('Variables combinadas', `${number(m.derived_calculation_ms, 2)} ms`),
            metric('Preparación de la serie', `${number(m.row_construction_ms, 2)} ms`),
            ...(m.detection_ms !== undefined ? [metric('Detección', `${number(m.detection_ms, 2)} ms`)] : []),
            metric('Generación de respuesta', `${number(m.json_serialization_ms, 2)} ms`),
            metric('Memoria máxima', `${number(m.peak_memory_bytes / 1048576, 1)} MiB`),
        ].join('');
    }

    function drawExtrema(data) {
        if (typeof window.echarts === 'undefined') {
            chartElement.hidden = true;
            chartMessage.style.display = 'block';
            chartMessage.textContent = 'ECharts no pudo cargarse desde la copia local del sitio.';
            return;
        }
        chartElement.hidden = false;
        chartMessage.style.display = 'none';
        localTimeChartHelp.hidden = true;
        ensureChart();
        alignRelationBands = () => {};
        const series = [
            {key: 'maximos', color: '#dfc47d', symbol: 'diamond'},
            {key: 'minimos', color: '#76a9fa', symbol: 'circle'},
        ].filter(definition => data.series[definition.key].length > 0).map(definition => ({
            name: data.series_labels[definition.key], type: 'scatter', symbol: definition.symbol,
            symbolSize: 9, itemStyle: {color: definition.color},
            data: data.series[definition.key].map(point => ({value: [point.fecha, point.valor], extremaType: definition.key})),
        }));
        chart.setOption({
            animation: false, backgroundColor: 'transparent', graphic: [], visualMap: [],
            legend: {type: 'scroll', top: 4, textStyle: {color: '#dce4f2'}, data: series.map(item => item.name)},
            tooltip: {trigger: 'item', formatter: item => {
                const value = item.value?.[1];
                return `<strong>${item.value?.[0] || ''}</strong><br>${item.marker}${item.seriesName}: ${number(value, data.precision)} ${data.unit}`;
            }},
            toolbox: {right: 8, top: 34, feature: {dataZoom: {yAxisIndex: 'none'}, restore: {}}},
            grid: {left: 82, right: 54, top: 70, bottom: 85},
            xAxis: {type: 'time', axisLabel: {color: '#9eabc0'}, axisLine: {lineStyle: {color: '#46516a'}}},
            yAxis: {type: 'value', name: scaleTitle(data.scale_group), nameLocation: 'middle',
                nameGap: 55, nameRotate: 90, scale: true,
                axisLabel: {color: '#9eabc0'}, nameTextStyle: {color: '#9eabc0'}, splitLine: {lineStyle: {color: '#252f43'}}},
            dataZoom: [{type: 'inside', xAxisIndex: 0}, {type: 'slider', xAxisIndex: 0, bottom: 18, textStyle: {color: '#9eabc0'}}],
            series,
        }, true);
        verticalAxisController?.update([{
            key: data.scale_group || data.variable,
            index: 0,
            bounds: verticalAxisBounds[data.scale_group] || {},
            seriesNames: series.map(item => item.name),
        }], false);
        const total = data.counts.maximos + data.counts.minimos;
        if (total === 0) {
            chartMessage.style.display = 'block';
            chartMessage.textContent = 'No se encontraron extremos locales en el rango solicitado.';
        }
    }

    function captureChartInteraction() {
        if (!chart) return null;
        return relationAnalysis.captureInteraction(chart.getOption());
    }

    function draw(data, preserveInteraction = false) {
        if (typeof window.echarts === 'undefined') {
            chartElement.hidden = true;
            chartMessage.style.display = 'block';
            chartMessage.textContent = 'ECharts no pudo cargarse desde la copia local del sitio.';
            return;
        }
        chartElement.hidden = false;
        chartMessage.style.display = 'none';
        ensureChart();
        const interaction = preserveInteraction ? captureChartInteraction() : null;
        const fields = data.columns.filter(column => column !== 'date');
        localTimeChartHelp.hidden = !fields.some(field => data.field_metadata[field].scaleGroup === 'local_time');
        const groups = [...new Set(fields.map(field => data.field_metadata[field].scaleGroup))];
        const axes = groups.map((group, index) => ({
            id: `explorer-y-${group}`,
            type: 'value',
            name: scaleTitle(group),
            position: index % 2 ? 'right' : 'left',
            offset: Math.floor(index / 2) * 54,
            nameLocation: 'middle',
            nameGap: 55,
            nameRotate: index % 2 ? -90 : 90,
            scale: true,
            ...(group === 'local_time' ? {min: 0, max: 24, interval: 4} : {}),
            ...(roundedAxisStep[group] !== undefined ? {minInterval: roundedAxisStep[group]} : {}),
            axisLabel: {color: '#9eabc0', formatter: group === 'local_time'
                ? localTimeAxis
                : (roundedAxisDigits[group] !== undefined
                    ? value => number(value, roundedAxisDigits[group]) : undefined)},
            nameTextStyle: {color: '#9eabc0'},
            splitLine: {show: index === 0, lineStyle: {color: '#252f43'}},
        }));
        const longSeries = data.rows.length > 1000;
        const series = fields.flatMap((field, fieldIndex) => {
            const metadata = data.field_metadata[field];
            const values = data.rows.map(row => row[field]);
            const visualSegments = metadata.scaleGroup === 'local_time'
                ? timeSeriesSegments.splitAtMidnight(values) : [values];
            return visualSegments.map((segment, segmentIndex) => ({
                id: `main-${field}-${segmentIndex}`,
                name: metadata.shortName,
                type: 'line',
                yAxisIndex: groups.indexOf(metadata.scaleGroup),
                data: segment,
                showSymbol: !longSeries || segment.filter(value => value !== null).length === 1,
                symbolSize: 5,
                connectNulls: false,
                sampling: longSeries ? 'lttb' : undefined,
                lineStyle: {color: chartColors[fieldIndex % chartColors.length]},
                itemStyle: {color: chartColors[fieldIndex % chartColors.length]},
            }));
        });
        const numericFields = selectedNumericFields().filter(field => fields.includes(field));
        const relationFields = selectedRelationPair(numericFields);
        const activeMethods = currentMode() === 'diario'
            ? relationMethods.filter(method => method.checked && !method.disabled).map(method => method.value) : [];
        const relationEnabled = relationAnalysis && activeMethods.length > 0 && relationFields.length === 2
            && relationFields[0] !== relationFields[1];
        const relationBands = relationEnabled ? activeMethods.map(method => ({
            method,
            label: method === 'product' ? 'Coincidencia / oposición' : 'Refuerzo positivo / negativo',
            data: relationAnalysis.build(data.rows, relationFields[0], relationFields[1], method),
        })) : [];
        relationBands.forEach((band, index) => {
            series.push({
                name: band.label,
                type: 'heatmap',
                xAxisIndex: index + 1,
                yAxisIndex: axes.length + index,
                data: band.data.filter(point => point.relation.index !== null),
                progressive: 1000,
                emphasis: {itemStyle: {borderColor: '#edf2fb', borderWidth: 1}},
            });
        });
        const mainGrid = {left: 70 + Math.floor((groups.length - 1) / 2) * 54,
            right: 70 + Math.floor(groups.length / 2) * 54, top: 70,
            bottom: 85 + relationBands.length * 55};
        const grids = relationEnabled ? [mainGrid, ...relationBands.map((band, index) => ({
            left: mainGrid.left, right: mainGrid.right,
            bottom: 65 + (relationBands.length - index - 1) * 55,
            height: 26,
        }))] : mainGrid;
        const dates = data.rows.map(row => row.date);
        const xAxes = relationEnabled ? [{type: 'category', data: dates, boundaryGap: false,
            axisLabel: {color: '#9eabc0'}, axisLine: {lineStyle: {color: '#46516a'}}},
        ...relationBands.map((band, index) => ({type: 'category', gridIndex: index + 1,
            data: dates, boundaryGap: false, axisLabel: {show: false}, axisTick: {show: false}, axisLine: {show: false}}))]
            : {type: 'category', data: dates, boundaryGap: false, axisLabel: {color: '#9eabc0'}, axisLine: {lineStyle: {color: '#46516a'}}};
        const yAxes = relationEnabled ? [...axes, ...relationBands.map((band, index) => ({
            type: 'category', gridIndex: index + 1, data: [band.method],
            axisLabel: {show: false}, axisTick: {show: false}, axisLine: {show: false}, splitLine: {show: false},
        }))] : axes;
        const synchronizedAxes = relationEnabled
            ? Array.from({length: relationBands.length + 1}, (unused, index) => index) : 0;
        chart.setOption({
            animation: !longSeries,
            backgroundColor: 'transparent',
            color: chartColors,
            legend: {type: 'scroll', textStyle: {color: '#dce4f2'}, top: 4,
                data: fields.map(field => data.field_metadata[field].shortName)},
            tooltip: {
                trigger: 'axis',
                valueFormatter: value => value === null ? '—' : number(value, 4),
                formatter: params => {
                    const relation = params.find(item => item.data?.relation)?.data?.relation;
                    if (relation) {
                        const formatRelationValue = (field, value) => {
                            if (value === null || value === undefined) return '—';
                            const meta = data.field_metadata[field];
                            return meta.scaleGroup === 'local_time' ? localTime(value) : `${number(value, meta.precision)} ${meta.unit}`;
                        };
                        const normalized = value => value === null ? 'Sin dato' : number(value, 3);
                        return [
                            `<strong>${relation.date}</strong>`,
                            `<strong>${relation.method === 'product' ? 'Coincidencia / oposición' : 'Refuerzo positivo / negativo'}</strong>`,
                            `${data.field_metadata[relation.fieldA].shortName}: ${formatRelationValue(relation.fieldA, relation.rawA)} · normalizado ${normalized(relation.normalizedA)}`,
                            `${data.field_metadata[relation.fieldB].shortName}: ${formatRelationValue(relation.fieldB, relation.rawB)} · normalizado ${normalized(relation.normalizedB)}`,
                            `Índice: ${normalized(relation.index)}`,
                            `<strong>${relation.interpretation}</strong>`,
                        ].join('<br>');
                    }
                    const row = data.rows[params[0]?.dataIndex] || {};
                    const date = row.date ?? '';
                    const shownFields = new Set();
                    const lines = params.map(item => {
                        const field = fields.find(candidate => data.field_metadata[candidate].shortName === item.seriesName);
                        if (!field || shownFields.has(field)) return null;
                        shownFields.add(field);
                        const meta = data.field_metadata[field];
                        const value = row[field];
                        const shown = meta.scaleGroup === 'local_time' ? localTime(value) : `${number(value, meta.precision)} ${meta.unit}`;
                        return `${item.marker}${meta.shortName}: ${value === null || value === undefined ? 'Sin dato' : shown}`;
                    }).filter(Boolean);
                    const phase = row.main_phase ? `<br>${row.main_phase} · ${row.phase_instant}` : '';
                    return `<strong>${date}</strong>${phase}<br>${lines.join('<br>')}`;
                },
            },
            grid: grids,
            xAxis: xAxes,
            yAxis: yAxes,
            dataZoom: [{type: 'inside', xAxisIndex: synchronizedAxes},
                {type: 'slider', xAxisIndex: synchronizedAxes, bottom: 18, textStyle: {color: '#9eabc0'}}],
            ...(relationEnabled ? {
                visualMap: relationBands.map((band, index) => ({
                    show: false, type: 'continuous', min: -1, max: 1, dimension: 2,
                    seriesIndex: series.length - relationBands.length + index,
                    inRange: {color: band.method === 'product'
                        ? ['#a95668', '#343b52', '#4f8a76']
                        : ['#557cac', '#343b52', '#b58b4a']},
                })),
                axisPointer: {link: [{xAxisIndex: synchronizedAxes}]},
            } : {visualMap: [], graphic: []}),
            series,
        }, true);
        if (interaction) {
            chart.setOption(relationAnalysis.restoreInteraction(interaction, synchronizedAxes));
        }
        if (relationEnabled) {
            alignRelationBands = () => {
                const mainRect = chart.getModel().getComponent('grid', 0)?.coordinateSystem?.getRect();
                if (!mainRect) return;
                const right = Math.max(0, chart.getWidth() - mainRect.x - mainRect.width);
                const wrap = mainRect.width < 520;
                chart.setOption({
                    grid: [{}, ...relationBands.map(() => ({left: mainRect.x, right}))],
                    graphic: relationBands.map((band, index) => {
                        const rect = chart.getModel().getComponent('grid', index + 1)?.coordinateSystem?.getRect();
                        const text = wrap ? (band.method === 'product' ? 'Coincidencia /\noposición' : 'Refuerzo positivo /\nnegativo') : band.label;
                        return {id: `relation-title-${band.method}`, type: 'text', silent: true,
                            left: mainRect.x, top: Math.max(0, (rect?.y || 0) - (wrap ? 31 : 18)),
                            style: {text, fill: '#9eabc0', font: '11px sans-serif', lineHeight: 12}};
                    }),
                });
            };
            alignRelationBands();
        } else {
            alignRelationBands = () => {};
        }
        verticalAxisController?.update(groups.map((group, index) => ({
            key: group,
            index,
            bounds: verticalAxisBounds[group] || {},
            seriesNames: fields.filter(field => data.field_metadata[field].scaleGroup === group)
                .map(field => data.field_metadata[field].shortName),
        })), preserveInteraction);
    }

    const canStream = typeof ReadableStream !== 'undefined'
        && typeof TextDecoder !== 'undefined'
        && typeof Response !== 'undefined'
        && 'body' in Response.prototype;

    async function readNdjson(response, onMessage) {
        if (!response.body || typeof response.body.getReader !== 'function') {
            throw new Error('STREAM_UNAVAILABLE');
        }
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let result = null;

        const consume = line => {
            if (!line.trim()) return;
            const message = JSON.parse(line);
            onMessage(message);
            if (message.type === 'error') throw new Error(message.message || 'La solicitud falló.');
            if (message.type === 'complete') result = message.result;
        };

        while (true) {
            const chunk = await reader.read();
            buffer += decoder.decode(chunk.value || new Uint8Array(), {stream: !chunk.done});
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';
            lines.forEach(consume);
            if (chunk.done) break;
        }
        consume(buffer);
        if (!response.ok) throw new Error('La solicitud falló.');
        if (!result) throw new Error('La respuesta progresiva terminó incompleta.');
        return result;
    }

    function handleStreamMessage(message) {
        if (message.type === 'start') {
            const days = message.mode === 'extremos'
                ? `${number(message.calendar_days, 0)} días solicitados · ${number(message.selected_dates, 0)} con auxiliares`
                : message.calendar_days !== message.selected_dates
                ? `${number(message.selected_dates, 0)} fechas seleccionadas de ${number(message.calendar_days, 0)} días`
                : `${number(message.total_days, 0)} días`;
            updateProcess({stage: 'Preparando cálculo…', days, percent: 0});
        } else if (message.type === 'progress') {
            updateProcess({
                stage: message.label || 'Calculando astronomía…',
                days: `${number(message.processed_days, 0)} de ${number(message.total_days, 0)} días en esta etapa`,
                percent: message.overall_percent,
            });
        } else if (message.type === 'stage') {
            updateProcess({stage: message.label || 'Procesando resultados…', percent: lastOverallPercent});
        }
    }

    async function requestTraditional(params, signal) {
        updateProcess({stage: 'Calculando sin progreso detallado…', days: '', indeterminate: true});
        const response = await fetch(`api/series.php?${params}`, {headers: {'Accept': 'application/json'}, signal});
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'La solicitud falló.');
        return data;
    }

    async function requestSeries(params, signal) {
        if (!canStream) return requestTraditional(params, signal);
        const response = await fetch(`api/series-stream.php?${params}`, {
            headers: {'Accept': 'application/x-ndjson'},
            signal,
        });
        if (!response.body || typeof response.body.getReader !== 'function') {
            return requestTraditional(params, signal);
        }
        return readNdjson(response, handleStreamMessage);
    }

    const shareSchema = {
        modes: ['diario', 'extremos'],
        fields: [...form.querySelectorAll('input[name="campos[]"]')].map(input => input.value),
        numericFields: [...form.querySelectorAll('input[name="campos[]"][data-value-type="number"]')].map(input => input.value),
        phases: phaseInputs.map(input => input.value),
        methods: relationMethods.map(input => input.value),
        extremaFields: [...extremaVariable.options].map(option => option.value),
        extremaTypes: [...form.querySelectorAll('input[name="tipo_extremo"]')].map(input => input.value),
        maximumDays: 73050,
        validTimezone(value) {
            if (typeof value !== 'string' || value.length > 100) return false;
            try { new Intl.DateTimeFormat('en', {timeZone: value}).format(); return true; }
            catch (error) { return false; }
        },
    };

    function visibleShareConfiguration() {
        const fields = orderedSelectedFieldInputs().map(input => input.value);
        const numeric = selectedNumericFields();
        const pair = selectedRelationPair(numeric);
        return {
            mode: currentMode(), from: fromDate.value, to: toDate.value,
            latitude: activeLocation.latitude, longitude: activeLocation.longitude,
            timezone: activeLocation.timezone, locationLabel: activeLocation.name || '',
            fields, phases: phaseInputs.filter(input => input.checked).map(input => input.value),
            methods: relationMethods.filter(input => input.checked && !input.disabled).map(input => input.value),
            a: pair[0] || '', b: pair[1] || '', extremaField: extremaVariable.value,
            extremaType: form.elements.tipo_extremo.value,
        };
    }

    function safeDefaults() {
        return {...visibleShareConfiguration(), latitude: Number(officialLocation.latitude),
            longitude: Number(officialLocation.longitude), timezone: officialLocation.timezone,
            locationLabel: officialLocation.name || '', sharedLocation: false};
    }

    function canonicalShareUrl() {
        const visible = visibleShareConfiguration();
        const sharePath = document.body.dataset.explorerSharePath || window.location.pathname;
        const url = shareTools.build(visible, new URL(sharePath, window.location.origin).toString());
        const checked = shareTools.parse(new URL(url).search, shareSchema, safeDefaults());
        return checked.valid ? {url, warning: ''} : {url: '', warning: checked.warnings.join(' ')};
    }

    function openShareDialog(url, message = '') {
        shareUrlInput.value = url;
        shareDialogStatus.textContent = message;
        if (!shareDialog.open) shareDialog.showModal();
        shareUrlInput.focus();
        shareUrlInput.select();
    }

    function closeShareDialog() {
        if (shareDialog.open) shareDialog.close();
    }

    async function copyShareUrl(url) {
        if (!navigator.clipboard?.writeText) throw new Error('CLIPBOARD_UNAVAILABLE');
        await navigator.clipboard.writeText(url);
    }

    shareButton.addEventListener('click', async () => {
        shareStatus.textContent = '';
        const result = canonicalShareUrl();
        if (!result.url) { shareStatus.textContent = result.warning || 'La configuración actual no es válida.'; return; }
        if (result.url.length > 4000) {
            shareStatus.textContent = 'El enlace supera los 4.000 caracteres y no puede compartirse.';
            return;
        }
        window.history.replaceState(null, '', result.url);
        try {
            await copyShareUrl(result.url);
            shareStatus.textContent = 'Enlace copiado.';
            window.setTimeout(() => {
                if (shareStatus.textContent === 'Enlace copiado.') shareStatus.textContent = '';
            }, 3000);
        } catch (error) {
            openShareDialog(result.url, 'No se pudo copiar automáticamente. Podés copiarlo desde este campo.');
        }
    });

    shareDialogCopy.addEventListener('click', async () => {
        try {
            await copyShareUrl(shareUrlInput.value);
            shareDialogStatus.textContent = 'Enlace copiado.';
        } catch (error) {
            shareUrlInput.focus();
            shareUrlInput.select();
            shareDialogStatus.textContent = 'Seleccioná el enlace y copialo manualmente.';
        }
    });
    shareDialogClose.addEventListener('click', closeShareDialog);
    shareDialog.addEventListener('cancel', event => {
        event.preventDefault();
        closeShareDialog();
    });
    shareDialog.addEventListener('close', () => shareButton.focus());
    shareDialog.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const focusable = [...shareDialog.querySelectorAll('input, button')]
            .filter(element => !element.disabled && !element.hidden);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault(); last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault(); first.focus();
        }
    });

    function applySharedConfiguration() {
        const parsed = shareTools.parse(window.location.search, shareSchema, safeDefaults());
        if (!parsed.present) return;
        sharedNotice.hidden = false;
        if (!parsed.valid) {
            sharedNotice.textContent = parsed.warnings.join(' ');
        } else {
            sharedNotice.textContent = 'Configuración restaurada desde un enlace compartido.';
        }
        if (new URLSearchParams(window.location.search).get('v') !== '1') return;
        const config = parsed.config;
        modeInputs.forEach(input => { input.checked = input.value === config.mode; });
        updateMode();
        fromDate.value = config.from; toDate.value = config.to;
        updateYearFromDate(fromDate, fromYear); updateYearFromDate(toDate, toYear);
        activeLocation = {latitude: Number(config.latitude), longitude: Number(config.longitude),
            timezone: config.timezone, name: config.locationLabel || ''};
        sharedLocationActive = config.sharedLocation === true;
        updateLocationDisplay();
        if (config.mode === 'diario') {
            form.querySelectorAll('input[name="campos[]"]').forEach(input => { input.checked = config.fields.includes(input.value); });
            sharedFieldOrder = [...config.fields];
            phaseInputs.forEach(input => { input.checked = config.phases.includes(input.value); });
            allDays.checked = config.phases.length === 0;
            relationPhaseNote.hidden = allDays.checked;
            preferredRelationFields.a = config.a || ''; preferredRelationFields.b = config.b || '';
            relationMethods.forEach(input => { input.checked = config.methods.includes(input.value); });
            updateRelationControls();
            if (config.a) relationSelectA.value = config.a;
            if (config.b) relationSelectB.value = config.b;
            preventDuplicateRelationVariables();
        } else {
            extremaVariable.value = config.extremaField;
            [...form.querySelectorAll('input[name="tipo_extremo"]')].forEach(input => { input.checked = input.value === config.extremaType; });
            updateExtremaGuidance();
        }
        if (parsed.valid) window.setTimeout(() => form.requestSubmit(), 0);
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!validateDateOrder()) return;
        const mode = currentMode();
        const fields = orderedSelectedFieldInputs().map(input => input.value);
        if (mode === 'diario' && !fields.length) {
            status.className = 'error'; status.textContent = 'Seleccioná al menos una variable.'; return;
        }
        const button = form.querySelector('button[type="submit"]');
        const params = new URLSearchParams(new FormData(form));
        params.delete('campos[]');
        params.delete('fases[]');
        params.delete('anio_desde');
        params.delete('anio_hasta');
        params.set('lat', String(activeLocation.latitude));
        params.set('lon', String(activeLocation.longitude));
        params.set('timezone', activeLocation.timezone);
        if (mode === 'diario') {
            params.delete('variable');
            params.delete('tipo_extremo');
            params.set('campos', fields.join(','));
            const phases = phaseInputs.filter(input => input.checked).map(input => input.value);
            if (phases.length) params.set('fases', phases.join(','));
        } else {
            params.delete('campos');
            params.delete('fases');
        }
        activeController?.abort();
        const controller = new AbortController();
        activeController = controller;
        button.disabled = true; status.className = ''; status.textContent = 'Calculando…';
        beginProcess(!canStream);
        try {
            const data = await requestSeries(params, controller.signal);
            if (data.modo === 'extremos') {
                drawExtrema(data);
                const total = data.counts.maximos + data.counts.minimos;
                status.textContent = total > 0 ? `${number(total, 0)} extremos detectados.` : 'No se encontraron extremos en el rango.';
                if (data.warning) status.textContent += ` ${data.warning}`;
            } else {
                lastData = data;
                updateRelationControls();
                draw(data);
                status.textContent = `${number(data.rows.length, 0)} filas calculadas.`;
            }
            showMetrics(data);
            stopElapsedTimer();
            cancelButton.disabled = true;
            cancelButton.hidden = true;
            const processed = data.modo === 'extremos' ? data.metrics.processed_days : data.rows.length;
            updateProcess({state: 'complete', stage: 'Cálculo completo', days: `${number(processed, 0)} días procesados`, percent: 100});
        } catch (error) {
            if (error && error.name === 'AbortError') return;
            stopElapsedTimer();
            cancelButton.disabled = true;
            cancelButton.hidden = true;
            updateProcess({state: 'error', stage: 'El cálculo no pudo completarse', days: '', percent: lastOverallPercent});
            status.className = 'error'; status.textContent = error instanceof Error ? error.message : 'Error inesperado.';
        } finally {
            if (activeController === controller) {
                activeController = null;
                button.disabled = false;
            }
        }
    });
    form.addEventListener('input', event => {
        if (activeController && event.target instanceof HTMLInputElement && !event.target.matches('[data-relation-method]')) {
            cancelActiveRequest();
        }
    });
    const contextHelpButtons = [...document.querySelectorAll('.context-help')];
    const closeContextHelp = except => contextHelpButtons.forEach(button => {
        if (button !== except) button.setAttribute('aria-expanded', 'false');
    });
    contextHelpButtons.forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault(); event.stopPropagation();
            const opening = button.getAttribute('aria-expanded') !== 'true';
            closeContextHelp(button);
            button.setAttribute('aria-expanded', opening ? 'true' : 'false');
        });
        button.addEventListener('keydown', event => {
            if (event.key === 'Escape') { button.setAttribute('aria-expanded', 'false'); button.focus(); }
        });
    });
    document.addEventListener('click', () => closeContextHelp());
    cancelButton.addEventListener('click', () => cancelActiveRequest('Cálculo cancelado.'));
    window.addEventListener('resize', () => { chart?.resize(); alignRelationBands(); });
    applySharedConfiguration();
})();
