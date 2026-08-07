(() => {
    'use strict';

    const mobileQuery = window.matchMedia('(max-width: 620px)');
    const main = document.querySelector('.explorer-shell');
    const heading = document.querySelector('.explorer-heading');
    const parameterPanel = document.querySelector('#parameters-title')?.closest('.panel');
    const form = document.querySelector('#explorer-form');
    const chartPanel = document.querySelector('.chart-panel');
    const chartElement = document.querySelector('#chart');
    const metricsPanel = document.querySelector('.metrics-panel');
    const variablePicker = document.querySelector('.variable-picker');
    const originalCalculate = form?.querySelector('.calculate-button');
    const originalShare = form?.querySelector('#share-configuration');
    const processPanel = form?.querySelector('#process-panel');
    const locationValue = form?.querySelector('#calculation-location-value');
    const changeLocation = form?.querySelector('#change-location');
    const shareDialogTitle = document.querySelector('#share-dialog-title');

    if (!main || !heading || !parameterPanel || !form || !chartPanel || !chartElement
        || !originalCalculate || !originalShare || !processPanel) return;
    if (shareDialogTitle) shareDialogTitle.textContent = 'Compartir gráfico';

    const dialog = document.createElement('dialog');
    dialog.className = 'interface-config-dialog';
    dialog.setAttribute('aria-labelledby', 'interface-config-title');
    const surface = document.createElement('div');
    surface.className = 'interface-config-surface';
    const resizeHandle = document.createElement('div');
    resizeHandle.className = 'interface-config-resize-handle';
    resizeHandle.tabIndex = 0;
    resizeHandle.setAttribute('role', 'separator');
    resizeHandle.setAttribute('aria-orientation', 'vertical');
    resizeHandle.setAttribute('aria-label', 'Cambiar el ancho del panel');
    resizeHandle.setAttribute('aria-valuemin', '420');
    const dialogHeader = document.createElement('header');
    dialogHeader.className = 'interface-config-header';
    dialogHeader.innerHTML = '<h2 id="interface-config-title">Seleccionar datos</h2>';
    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'interface-config-close';
    closeButton.textContent = 'Cerrar';
    dialogHeader.append(closeButton);
    const dialogBody = document.createElement('div');
    dialogBody.className = 'interface-config-body';
    dialogBody.append(parameterPanel);
    const dialogFooter = document.createElement('footer');
    dialogFooter.className = 'interface-config-footer';
    const applyButton = document.createElement('button');
    applyButton.type = 'button';
    applyButton.textContent = 'Aplicar y cerrar';
    const dialogCalculateButton = document.createElement('button');
    dialogCalculateButton.type = 'button';
    dialogCalculateButton.textContent = 'Calcular';
    dialogFooter.append(applyButton, dialogCalculateButton);
    surface.append(resizeHandle, dialogHeader, dialogBody, dialogFooter);
    dialog.append(surface);
    document.body.append(dialog);

    const panelWidthStorageKey = 'astronomyExplorerPanelWidth';
    const minimumPanelWidth = 420;
    const maximumPanelViewportRatio = 0.82;
    const maximumPanelWidth = 1200;
    let resizingPanel = false;

    function panelWidthLimits() {
        const viewportMaximum = Math.floor(window.innerWidth * maximumPanelViewportRatio);
        return {
            minimum: Math.min(minimumPanelWidth, viewportMaximum),
            maximum: Math.max(Math.min(minimumPanelWidth, viewportMaximum), Math.min(maximumPanelWidth, viewportMaximum)),
        };
    }

    function storedPanelWidth() {
        try {
            const value = Number(sessionStorage.getItem(panelWidthStorageKey));
            return Number.isFinite(value) && value > 0 ? value : 760;
        } catch (error) {
            return 760;
        }
    }

    function setPanelWidth(width, persist = false) {
        if (mobileQuery.matches) {
            dialog.style.removeProperty('--interface-panel-width');
            return;
        }
        const limits = panelWidthLimits();
        const resolved = Math.round(Math.min(limits.maximum, Math.max(limits.minimum, width)));
        dialog.style.setProperty('--interface-panel-width', `${resolved}px`);
        resizeHandle.setAttribute('aria-valuenow', String(resolved));
        resizeHandle.setAttribute('aria-valuemax', String(limits.maximum));
        if (persist) {
            try { sessionStorage.setItem(panelWidthStorageKey, String(resolved)); } catch (error) { /* optional */ }
        }
    }

    function finishPanelResize(event) {
        if (!resizingPanel) return;
        resizingPanel = false;
        if (resizeHandle.hasPointerCapture?.(event.pointerId)) resizeHandle.releasePointerCapture(event.pointerId);
        document.body.classList.remove('is-resizing-config');
        setPanelWidth(dialog.getBoundingClientRect().width, true);
    }

    resizeHandle.addEventListener('pointerdown', event => {
        if (mobileQuery.matches || event.button !== 0) return;
        resizingPanel = true;
        resizeHandle.setPointerCapture?.(event.pointerId);
        document.body.classList.add('is-resizing-config');
        event.preventDefault();
    });
    resizeHandle.addEventListener('pointermove', event => {
        if (!resizingPanel) return;
        setPanelWidth(window.innerWidth - event.clientX);
    });
    resizeHandle.addEventListener('pointerup', finishPanelResize);
    resizeHandle.addEventListener('pointercancel', finishPanelResize);
    resizeHandle.addEventListener('keydown', event => {
        if (mobileQuery.matches || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const limits = panelWidthLimits();
        const current = dialog.getBoundingClientRect().width;
        const next = event.key === 'Home' ? limits.minimum : event.key === 'End' ? limits.maximum
            : current + (event.key === 'ArrowLeft' ? 32 : -32);
        setPanelWidth(next, true);
    });
    window.addEventListener('resize', () => setPanelWidth(dialog.getBoundingClientRect().width || storedPanelWidth()));
    mobileQuery.addEventListener?.('change', () => setPanelWidth(storedPanelWidth()));
    setPanelWidth(storedPanelWidth());

    const toolbar = document.createElement('section');
    toolbar.className = 'interface-main-bar';
    toolbar.setAttribute('aria-label', 'Datos y acciones del gráfico');
    const context = document.createElement('div');
    context.className = 'interface-context';
    const locationSummary = document.createElement('span');
    locationSummary.className = 'interface-location-summary';
    const rangeSummary = document.createElement('span');
    const variableSummary = document.createElement('span');
    context.append(locationSummary, rangeSummary, variableSummary);
    const configureButton = document.createElement('button');
    configureButton.type = 'button';
    configureButton.className = 'interface-configure-button interface-select-data-button';
    configureButton.textContent = 'Seleccionar datos';
    const calculateButton = document.createElement('button');
    calculateButton.type = 'button';
    calculateButton.textContent = 'Calcular';
    const shareButton = document.createElement('button');
    shareButton.type = 'button';
    shareButton.className = 'interface-share-button';
    shareButton.textContent = 'Compartir';
    const initialHelp = document.createElement('p');
    initialHelp.className = 'interface-initial-help';
    initialHelp.textContent = 'Empezá por Preparar gráfico para elegir fechas y variables.';
    toolbar.append(context, configureButton, calculateButton, processPanel, shareButton, initialHelp);
    heading.insertAdjacentElement('afterend', toolbar);
    toolbar.insertAdjacentElement('afterend', chartPanel);
    if (metricsPanel) chartPanel.insertAdjacentElement('afterend', metricsPanel);

    let selectDataAttentionTimer = 0;

    function sharedConfigurationWillAutoExecute() {
        if (!window.ExplorerShareConfig?.parse || new URLSearchParams(window.location.search).get('v') !== '1') return false;
        const fieldInputs = [...form.querySelectorAll('input[name="campos[]"]')];
        const phaseInputs = [...form.querySelectorAll('input[name="fases[]"]')];
        const extremaVariable = form.elements.variable;
        const schema = {
            modes: ['diario', 'extremos'],
            fields: fieldInputs.map(input => input.value),
            numericFields: fieldInputs.filter(input => input.dataset.valueType === 'number').map(input => input.value),
            phases: phaseInputs.map(input => input.value),
            methods: [...form.querySelectorAll('[data-relation-method]')].map(input => input.value),
            extremaFields: extremaVariable ? [...extremaVariable.options].map(option => option.value) : [],
            extremaTypes: [...form.querySelectorAll('input[name="tipo_extremo"]')].map(input => input.value),
            maximumDays: 73050,
            validTimezone(value) {
                if (typeof value !== 'string' || value.length > 100) return false;
                try { new Intl.DateTimeFormat('en', {timeZone: value}).format(); return true; }
                catch (error) { return false; }
            },
        };
        const defaults = {mode: 'diario', from: '', to: '', latitude: 0, longitude: 0, timezone: 'UTC',
            locationLabel: '', sharedLocation: false, fields: [], phases: [], methods: [], a: '', b: '',
            extremaField: schema.extremaFields[0] || '', extremaType: schema.extremaTypes[0] || ''};
        return window.ExplorerShareConfig.parse(window.location.search, schema, defaults).valid;
    }

    function cancelSelectDataAttention() {
        if (selectDataAttentionTimer) window.clearTimeout(selectDataAttentionTimer);
        selectDataAttentionTimer = 0;
        configureButton.classList.remove('is-attention-active');
    }

    function startSelectDataAttention() {
        if (dialog.open || sharedConfigurationWillAutoExecute() || processPanel.dataset.state !== 'idle') return;
        configureButton.classList.add('is-attention-active');
        selectDataAttentionTimer = window.setTimeout(cancelSelectDataAttention, 3000);
    }

    [configureButton, calculateButton, shareButton].forEach(button => {
        button.addEventListener('pointerdown', cancelSelectDataAttention, {passive: true});
        button.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') cancelSelectDataAttention();
        });
    });
    toolbar.addEventListener('click', event => {
        const action = event.target instanceof Element ? event.target.closest('button, a') : null;
        if (action && toolbar.contains(action)) cancelSelectDataAttention();
    });
    window.setTimeout(startSelectDataAttention, 80);

    function selectedVariableCount() {
        if (form.elements.modo?.value === 'extremos') return 1;
        return form.querySelectorAll('input[name="campos[]"]:checked').length;
    }

    function updateSummary() {
        locationSummary.textContent = `Ubicación: ${locationValue?.textContent.trim() || 'sin datos'}`;
        rangeSummary.textContent = `${form.elements.fecha_desde?.value || '—'} — ${form.elements.fecha_hasta?.value || '—'}`;
        const count = selectedVariableCount();
        variableSummary.textContent = `${count} variable${count === 1 ? '' : 's'}`;
    }

    function openConfiguration() {
        cancelSelectDataAttention();
        updateSummary();
        if (!dialog.open) dialog.showModal();
        document.body.classList.add('is-config-open');
        closeButton.focus();
    }

    function closeConfiguration() {
        if (dialog.open) dialog.close();
    }

    configureButton.addEventListener('click', openConfiguration);
    closeButton.addEventListener('click', closeConfiguration);
    applyButton.addEventListener('click', () => {
        updateSummary();
        closeConfiguration();
    });
    calculateButton.addEventListener('click', () => {
        updateSummary();
        closeConfiguration();
        originalCalculate.click();
    });
    dialogCalculateButton.addEventListener('click', () => {
        updateSummary();
        closeConfiguration();
        originalCalculate.click();
    });
    shareButton.addEventListener('click', () => originalShare.click());
    form.addEventListener('submit', () => {
        updateSummary();
        closeConfiguration();
    });
    form.addEventListener('input', updateSummary);
    form.addEventListener('change', updateSummary);
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        closeConfiguration();
    });
    dialog.addEventListener('click', event => {
        const bounds = surface.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right
            || event.clientY < bounds.top || event.clientY > bounds.bottom) closeConfiguration();
    });
    dialog.addEventListener('close', () => {
        document.body.classList.remove('is-config-open');
        configureButton.focus();
    });
    document.querySelector('#share-dialog')?.addEventListener('close', () => {
        window.setTimeout(() => shareButton.focus(), 0);
    });

    if (changeLocation) {
        const toolbarLocationLink = changeLocation.cloneNode(true);
        toolbarLocationLink.removeAttribute('id');
        toolbarLocationLink.textContent = 'Cambiar';
        locationSummary.insertAdjacentElement('afterend', toolbarLocationLink);
    }

    if (variablePicker) {
        const clearActions = document.createElement('div');
        clearActions.className = 'interface-variable-actions';
        const clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.className = 'interface-clear-selection';
        clearButton.textContent = 'Deseleccionar todo';
        clearButton.addEventListener('click', () => {
            const variableInputs = [...form.querySelectorAll('input[name="campos[]"]')];
            const phaseInputs = [...form.querySelectorAll('input[name="fases[]"]')];
            variableInputs.forEach(input => { input.checked = false; });
            phaseInputs.forEach(input => { input.checked = false; });
            const allDays = form.querySelector('#all-days');
            if (allDays) {
                allDays.checked = true;
                allDays.dispatchEvent(new Event('change', {bubbles: true}));
            }
            variableInputs[0]?.dispatchEvent(new Event('change', {bubbles: true}));
            updateSummary();
        });
        clearActions.append(clearButton);
        variablePicker.querySelector(':scope > legend')?.insertAdjacentElement('afterend', clearActions);
    }

    const suggestion = document.createElement('p');
    suggestion.className = 'interface-axis-suggestion';
    suggestion.hidden = true;
    suggestion.textContent = 'Hay varias escalas visibles. Seleccioná menos variables para ampliar el área de dibujo.';
    chartElement.insertAdjacentElement('beforebegin', suggestion);

    function compactChartOption(option) {
        if (!mobileQuery.matches || !option || typeof option !== 'object') return option;
        const grids = Array.isArray(option.grid) ? option.grid : option.grid ? [option.grid] : [];
        grids.forEach((grid, index) => {
            grid.left = 38;
            grid.right = 24;
            if (index === 0) {
                grid.top = 44;
                grid.bottom = Math.max(60, Number(grid.bottom) || 0);
            }
        });
        const axes = Array.isArray(option.yAxis) ? option.yAxis : option.yAxis ? [option.yAxis] : [];
        const valueAxes = axes.filter(axis => axis?.type === 'value');
        valueAxes.forEach((axis, index) => {
            axis.offset = Math.floor(index / 2) * 22;
            axis.nameGap = 33;
            axis.axisLabel = {...axis.axisLabel, fontSize: 9, margin: 3};
            axis.nameTextStyle = {...axis.nameTextStyle, fontSize: 9};
        });
        if (option.legend) {
            option.legend = {...option.legend, top: 0, itemWidth: 10, itemHeight: 7, itemGap: 5,
                textStyle: {...option.legend.textStyle, fontSize: 9}};
        }
        if (Array.isArray(option.dataZoom)) {
            option.dataZoom = option.dataZoom.map(zoom => zoom.type === 'slider'
                ? {...zoom, bottom: 6, height: 15, showDetail: false} : zoom);
        }
        if (option.toolbox) option.toolbox = {...option.toolbox, show: false};
        suggestion.hidden = valueAxes.length < 3;
        return option;
    }

    if (window.echarts?.init) {
        const originalInit = window.echarts.init.bind(window.echarts);
        window.echarts.init = (...argumentsList) => {
            const chart = originalInit(...argumentsList);
            const originalSetOption = chart.setOption.bind(chart);
            chart.setOption = (option, ...rest) => {
                if (option && Object.prototype.hasOwnProperty.call(option, 'series')) {
                    document.body.classList.add('interface-has-chart');
                    initialHelp.hidden = true;
                }
                return originalSetOption(compactChartOption(option), ...rest);
            };
            return chart;
        };
    }

    if ('IntersectionObserver' in window) {
        const chartObserver = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) updateSummary();
        }, {threshold: 0.05});
        chartObserver.observe(chartElement);
    }

    updateSummary();
    window.setTimeout(updateSummary, 0);
    window.setTimeout(updateSummary, 200);
})();
