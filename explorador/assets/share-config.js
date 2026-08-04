(() => {
    'use strict';

    const split = value => value === null || value === '' ? [] : value.split(',').filter(Boolean);
    const uniqueAllowed = (value, allowed) => [...new Set(split(value))].filter(item => allowed.includes(item));
    const realDate = value => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return false;
        const date = new Date(`${value}T00:00:00Z`);
        return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
    };
    const coordinate = (value, minimum, maximum) => {
        if (value === null || value.trim() === '') return null;
        const number = Number(value);
        return Number.isFinite(number) && number >= minimum && number <= maximum ? number : null;
    };

    function parse(search, schema, defaults) {
        const params = new URLSearchParams(search);
        if (!params.has('v')) return {present: false, valid: false, warnings: [], config: defaults};
        if (params.get('v') !== '1') return {present: true, valid: false,
            warnings: ['La versión del enlace compartido no es compatible.'], config: defaults};
        const warnings = [];
        let valid = true;
        const reject = message => { warnings.push(message); valid = false; };
        const config = {...defaults};
        const mode = params.get('modo');
        if (schema.modes.includes(mode)) config.mode = mode; else reject('No se pudo aplicar el modo del enlace.');
        const from = params.get('desde'); const to = params.get('hasta');
        if (realDate(from) && realDate(to) && from <= to
            && (new Date(`${to}T00:00:00Z`) - new Date(`${from}T00:00:00Z`)) / 86400000 + 1 <= schema.maximumDays) {
            config.from = from; config.to = to;
        }
        else reject('No se pudo aplicar el período del enlace.');
        const latitude = coordinate(params.get('lat'), -90, 90);
        const longitude = coordinate(params.get('lon'), -180, 180);
        const timezone = params.get('tz');
        if (latitude !== null && longitude !== null && schema.validTimezone(timezone)) {
            config.latitude = latitude; config.longitude = longitude; config.timezone = timezone;
            config.locationLabel = (params.get('ubicacion') || '').replace(/[\x00-\x1F\x7F]/g, '').slice(0, 80);
            config.sharedLocation = true;
        } else reject('No se pudo aplicar la ubicación del enlace.');
        if (config.mode === 'diario') {
            const rawFields = split(params.get('campos'));
            const fields = uniqueAllowed(params.get('campos'), schema.fields);
            if (fields.length && fields.length === rawFields.length) config.fields = fields;
            else reject('Una o más variables del enlace no pudieron aplicarse.');
            const rawPhases = split(params.get('fases'));
            const phases = uniqueAllowed(params.get('fases'), schema.phases);
            if (rawPhases.length === phases.length) config.phases = phases;
            else reject('Una o más fases del enlace no pudieron aplicarse.');
            if (phases.length && (config.from < '1900-01-01' || config.to > '2050-12-31')) {
                config.phases = []; reject('El período del filtro de fases queda fuera de su cobertura disponible.');
            }
            const rawMethods = split(params.get('relacion'));
            const methods = uniqueAllowed(params.get('relacion'), schema.methods);
            if (rawMethods.length === methods.length) config.methods = methods;
            else reject('Un método de relación del enlace no pudo aplicarse.');
            const numeric = fields.filter(field => schema.numericFields.includes(field));
            const a = params.get('a'); const b = params.get('b');
            if (methods.length && numeric.length >= 2) {
                if (numeric.includes(a) && numeric.includes(b) && a !== b) { config.a = a; config.b = b; }
                else { config.a = numeric[0]; config.b = numeric[1]; reject('Las variables A y B se reemplazaron por una combinación segura.'); }
            }
        } else {
            if (params.has('fases')) reject('El filtro de fases no es compatible con Extremos locales.');
            const variable = params.get('variable'); const type = params.get('tipo');
            if (schema.extremaFields.includes(variable)) config.extremaField = variable;
            else reject('No se pudo aplicar la variable de Extremos locales.');
            if (schema.extremaTypes.includes(type)) config.extremaType = type;
            else reject('No se pudo aplicar el tipo de extremo.');
        }
        return {present: true, valid, warnings, config};
    }

    function build(config, baseUrl) {
        const params = new URLSearchParams();
        params.set('v', '1'); params.set('modo', config.mode);
        params.set('desde', config.from); params.set('hasta', config.to);
        params.set('lat', String(config.latitude)); params.set('lon', String(config.longitude));
        params.set('tz', config.timezone);
        if (config.locationLabel) params.set('ubicacion', config.locationLabel.slice(0, 80));
        if (config.mode === 'diario') {
            params.set('campos', config.fields.join(','));
            if (config.phases.length) params.set('fases', config.phases.join(',')); else params.set('dias', 'todos');
            if (config.methods.length) {
                params.set('relacion', config.methods.join(','));
                if (config.a && config.b) { params.set('a', config.a); params.set('b', config.b); }
            }
        } else {
            params.set('variable', config.extremaField); params.set('tipo', config.extremaType);
        }
        return `${baseUrl}?${params.toString()}`;
    }

    window.ExplorerShareConfig = {parse, build, realDate, coordinate};
})();
