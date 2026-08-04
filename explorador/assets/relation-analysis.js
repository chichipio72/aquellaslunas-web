(() => {
    'use strict';

    const finiteNumber = value => {
        if (value === null || value === undefined || value === '' || typeof value === 'boolean') return null;
        const converted = Number(value);
        return Number.isFinite(converted) ? converted : null;
    };

    const normalize = values => {
        const finite = values.map(finiteNumber).filter(value => value !== null);
        const min = finite.length ? finite.reduce((current, value) => Math.min(current, value), finite[0]) : null;
        const max = finite.length ? finite.reduce((current, value) => Math.max(current, value), finite[0]) : null;
        return {
            min,
            max,
            midpoint: min === null || max === null ? null : (min + max) / 2,
            values: values.map(value => {
                const numeric = finiteNumber(value);
                if (numeric === null) return null;
                if (min === max) return 0;
                return Math.max(-1, Math.min(1, (2 * (numeric - min) / (max - min)) - 1));
            }),
        };
    };

    const interpret = (value, method) => {
        if (value === null || !Number.isFinite(Number(value))) return 'Sin dato';
        if (method === 'average') {
            if (value >= 0.6) return 'Refuerzo positivo fuerte';
            if (value >= 0.15) return 'Refuerzo positivo moderado';
            if (value > -0.15) return 'Equilibrio';
            if (value > -0.6) return 'Refuerzo negativo moderado';
            return 'Refuerzo negativo fuerte';
        }
        if (value >= 0.6) return 'Coincidencia fuerte';
        if (value >= 0.15) return 'Coincidencia moderada';
        if (value > -0.15) return 'Neutro';
        if (value > -0.6) return 'Oposición moderada';
        return 'Oposición fuerte';
    };

    const build = (rows, fieldA, fieldB, method) => {
        const normalizedA = normalize(rows.map(row => row[fieldA]));
        const normalizedB = normalize(rows.map(row => row[fieldB]));
        return rows.map((row, index) => {
            const a = normalizedA.values[index];
            const b = normalizedB.values[index];
            const relationIndex = a === null || b === null
                ? null
                : method === 'average' ? (a + b) / 2 : a * b;
            return {
                value: [row.date, method, relationIndex],
                relation: {
                    date: row.date, fieldA, fieldB,
                    rawA: row[fieldA], rawB: row[fieldB],
                    normalizedA: a, normalizedB: b,
                    index: relationIndex, method,
                    interpretation: interpret(relationIndex, method),
                },
            };
        });
    };

    const controlState = (fields, methodActive = false, dailyMode = true) => ({
        enabled: dailyMode && fields.length >= 2,
        automatic: dailyMode && fields.length === 2,
        showSelectors: dailyMode && methodActive && fields.length > 2,
        fields: fields.length === 2 ? [...fields] : null,
    });

    const captureInteraction = option => ({
        legend: option?.legend?.[0]?.selected || {},
        zoom: (option?.dataZoom || []).map(zoom => ({
            start: zoom.start, end: zoom.end,
            startValue: zoom.startValue, endValue: zoom.endValue,
        })),
    });

    const restoreInteraction = (state, synchronizedAxes) => ({
        legend: {selected: state?.legend || {}},
        dataZoom: (state?.zoom || []).map(zoom => ({...zoom, xAxisIndex: synchronizedAxes})),
    });

    window.ExplorerRelationAnalysis = {finiteNumber, normalize, interpret, build,
        controlState, captureInteraction, restoreInteraction};
})();
