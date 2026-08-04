(() => {
    'use strict';

    function splitAtMidnight(values, threshold = 12) {
        const segments = [];
        let segment = null;
        let previous = null;
        values.forEach((value, index) => {
            const numeric = value === null || value === undefined ? null : Number(value);
            if (numeric === null || !Number.isFinite(numeric)) {
                segment = null;
                previous = null;
                return;
            }
            if (segment === null || (previous !== null && Math.abs(numeric - previous) > threshold)) {
                segment = Array(values.length).fill(null);
                segments.push(segment);
            }
            segment[index] = numeric;
            previous = numeric;
        });
        return segments;
    }

    window.ExplorerTimeSeriesSegments = {splitAtMidnight};
})();
