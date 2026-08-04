(() => {
    'use strict';

    const finite = value => Number.isFinite(Number(value)) ? Number(value) : null;

    function clampRange(min, max, bounds = {}) {
        let low = finite(min);
        let high = finite(max);
        if (low === null || high === null || high <= low) return null;
        const lower = finite(bounds.min);
        const upper = finite(bounds.max);
        const span = high - low;
        if (lower !== null && low < lower) { low = lower; high = lower + span; }
        if (upper !== null && high > upper) { high = upper; low = upper - span; }
        if (lower !== null) low = Math.max(lower, low);
        if (upper !== null) high = Math.min(upper, high);
        return Number.isFinite(low) && Number.isFinite(high) && high > low ? {min: low, max: high} : null;
    }

    function zoomRange(range, anchor, factor, bounds = {}, maximumSpan = Infinity) {
        const min = finite(range?.min); const max = finite(range?.max); const center = finite(anchor);
        if (min === null || max === null || center === null || max <= min || !Number.isFinite(factor) || factor <= 0) return null;
        const span = max - min;
        const nextSpan = Math.min(maximumSpan, Math.max(span * factor, Math.max(Math.abs(center), 1) * 1e-10));
        const fraction = Math.max(0, Math.min(1, (center - min) / span));
        return clampRange(center - nextSpan * fraction, center + nextSpan * (1 - fraction), bounds);
    }

    function panRange(range, pixelDelta, pixelHeight, bounds = {}) {
        const min = finite(range?.min); const max = finite(range?.max);
        if (min === null || max === null || max <= min || !Number.isFinite(pixelDelta) || pixelHeight <= 0) return null;
        const shift = pixelDelta / pixelHeight * (max - min);
        return clampRange(min + shift, max + shift, bounds);
    }

    function nearestAxis(pointerX, axes, insideMainGrid, tolerance = 70) {
        if (!axes.length) return null;
        const ordered = axes.map(axis => ({axis, distance: Math.abs(pointerX - axis.pixelX)}))
            .sort((a, b) => a.distance - b.distance);
        if (ordered[0].distance <= tolerance) return ordered[0].axis;
        if (insideMainGrid && axes.length === 1) return axes[0];
        return insideMainGrid ? ordered[0].axis : null;
    }

    function createController(chart, afterChange = () => {}) {
        const element = chart.getDom();
        const states = new Map();
        let axes = [];
        let drag = null;

        const geometry = axis => {
            const selected = chart.getOption().legend?.[0]?.selected || {};
            if (axis.seriesNames?.length && !axis.seriesNames.some(name => selected[name] !== false)) return null;
            const model = chart.getModel().getComponent('yAxis', axis.index);
            const rect = model?.axis?.grid?.getRect();
            if (!model || !rect) return null;
            const position = model.get('position');
            const offset = Number(model.get('offset') || 0);
            return {axis, model, rect, pixelX: position === 'right' ? rect.x + rect.width + offset : rect.x - offset};
        };
        const activeAxis = (x, y) => {
            const available = axes.map(geometry).filter(Boolean);
            const mainRect = available[0]?.rect;
            const inside = mainRect && x >= mainRect.x && x <= mainRect.x + mainRect.width
                && y >= mainRect.y && y <= mainRect.y + mainRect.height;
            return nearestAxis(x, available.map(item => ({...item.axis, pixelX: item.pixelX})), Boolean(inside));
        };
        const stateFor = axis => {
            let state = states.get(axis.key);
            if (!state) {
                const model = chart.getModel().getComponent('yAxis', axis.index);
                const extent = model?.axis?.scale?.getExtent?.() || [];
                const min = finite(extent[0]); const max = finite(extent[1]);
                if (min === null || max === null || max <= min) return null;
                state = {base: {min, max}, current: {min, max}, bounds: axis.bounds || {}};
                states.set(axis.key, state);
            }
            return state;
        };
        const apply = (axis, range) => {
            if (!range) return;
            const state = stateFor(axis);
            if (!state) return;
            state.current = range;
            const updates = chart.getOption().yAxis.map((unused, index) => index === axis.index
                ? {min: range.min, max: range.max} : {});
            chart.setOption({yAxis: updates});
            afterChange();
        };
        const coordinates = event => {
            const rect = element.getBoundingClientRect();
            return {x: event.clientX - rect.left, y: event.clientY - rect.top};
        };
        const onWheel = event => {
            if (!event.shiftKey) return;
            const point = coordinates(event);
            const axis = activeAxis(point.x, point.y);
            if (!axis) return;
            const state = stateFor(axis);
            const anchor = chart.convertFromPixel({yAxisIndex: axis.index}, point.y);
            if (!state || !Number.isFinite(Number(anchor))) return;
            event.preventDefault(); event.stopPropagation();
            const baseSpan = state.base.max - state.base.min;
            apply(axis, zoomRange(state.current, Number(anchor), event.deltaY < 0 ? 0.8 : 1.25,
                state.bounds, baseSpan * 100));
        };
        const finishDrag = event => {
            if (!drag) return;
            if (event?.pointerId !== undefined && event.pointerId !== drag.pointerId) return;
            drag = null;
            element.style.cursor = '';
        };
        const onPointerDown = event => {
            if (!event.shiftKey || event.button !== 0) return;
            const point = coordinates(event);
            const axis = activeAxis(point.x, point.y);
            const state = axis ? stateFor(axis) : null;
            const rect = axis ? geometry(axis)?.rect : null;
            if (!axis || !state || !rect) return;
            event.preventDefault(); event.stopPropagation();
            element.style.cursor = 'ns-resize';
            drag = {axis, pointerId: event.pointerId, startY: point.y, startRange: {...state.current}, height: rect.height};
        };
        const onPointerMove = event => {
            if (!drag || event.pointerId !== drag.pointerId) return;
            event.preventDefault(); event.stopPropagation();
            const point = coordinates(event);
            const state = stateFor(drag.axis);
            apply(drag.axis, panRange(drag.startRange, point.y - drag.startY, drag.height, state?.bounds));
        };
        element.addEventListener('wheel', onWheel, {capture: true, passive: false});
        element.addEventListener('pointerdown', onPointerDown, true);
        window.addEventListener('pointermove', onPointerMove, true);
        window.addEventListener('pointerup', finishDrag, true);
        window.addEventListener('pointercancel', finishDrag, true);
        window.addEventListener('blur', finishDrag);
        chart.on('restore', () => {
            states.clear();
            const byIndex = new Map(axes.map(axis => [axis.index, axis]));
            const updates = chart.getOption().yAxis.map((unused, index) => byIndex.has(index)
                ? {min: byIndex.get(index).bounds?.min ?? null, max: byIndex.get(index).bounds?.max ?? null} : {});
            chart.setOption({yAxis: updates});
            afterChange();
        });
        return {
            update(nextAxes, preserve = false) {
                axes = nextAxes.map(axis => ({...axis}));
                const keys = new Set(axes.map(axis => axis.key));
                [...states.keys()].forEach(key => { if (!keys.has(key)) states.delete(key); });
                if (!preserve) states.clear();
                if (preserve) axes.forEach(axis => {
                    const state = states.get(axis.key);
                    if (state) apply(axis, state.current);
                });
            },
            reset() { states.clear(); },
            state: () => new Map(states),
        };
    }

    window.ExplorerVerticalAxisControl = {clampRange, zoomRange, panRange, nearestAxis, createController};
})();
