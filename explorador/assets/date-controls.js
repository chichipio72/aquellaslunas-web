(() => {
    'use strict';

    function parseDate(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
        if (!match) return null;
        const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
        return formatDate(date) === value ? date : null;
    }

    function formatDate(date) {
        return `${String(date.getUTCFullYear()).padStart(4, '0')}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
    }

    function addDays(date, days) {
        const result = new Date(date.getTime());
        result.setUTCDate(result.getUTCDate() + days);
        return result;
    }

    function shiftYears(date, years) {
        const result = new Date(date.getTime());
        result.setUTCFullYear(result.getUTCFullYear() + years);
        return result;
    }

    function backwardRangeStart(endValue, years) {
        const end = parseDate(endValue);
        if (!end || !Number.isInteger(years) || years < 1) return null;
        return formatDate(shiftYears(addDays(end, 1), -years));
    }

    function forwardRangeEnd(startValue, years) {
        const start = parseDate(startValue);
        if (!start || !Number.isInteger(years) || years < 1) return null;
        return formatDate(addDays(shiftYears(start, years), -1));
    }

    function validYear(value) {
        return /^\d{4}$/.test(value) && Number(value) >= 1000 && Number(value) <= 9999;
    }

    function yearBoundary(value, boundary) {
        return validYear(value) ? `${value}-${boundary}` : null;
    }

    function createModeDateState(from, to) {
        const states = {diario: {from, to}, extremos: null};
        let active = 'diario';
        return {
            switchTo(next, currentFrom, currentTo) {
                states[active] = {from: currentFrom, to: currentTo};
                if (next === 'extremos' && states.extremos === null) {
                    states.extremos = {from: backwardRangeStart(currentTo, 2), to: currentTo};
                }
                active = next;
                return states[next] ? {...states[next]} : null;
            },
        };
    }

    window.ExplorerDateTools = {parseDate, backwardRangeStart, forwardRangeEnd, validYear, yearBoundary,
        createModeDateState};
})();
