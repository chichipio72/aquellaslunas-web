'use strict';

global.window = global;
require('../assets/share-config.js');
const tools = global.ExplorerShareConfig;
const schema = {
    modes: ['diario', 'extremos'], fields: ['moon_illumination', 'moon_distance_geocentric', 'sun_altitude'],
    numericFields: ['moon_illumination', 'moon_distance_geocentric', 'sun_altitude'],
    phases: ['new_moon', 'first_quarter', 'full_moon', 'last_quarter'], methods: ['product', 'average'],
    extremaFields: ['moon_distance_geocentric'], extremaTypes: ['maximo', 'minimo', 'ambos'], maximumDays: 73050,
    validTimezone: value => ['UTC', 'America/Argentina/Buenos_Aires'].includes(value),
};
const defaults = {mode: 'diario', from: '2026-07-05', to: '2026-08-03', latitude: -34.53,
    longitude: -58.48, timezone: 'America/Argentina/Buenos_Aires', fields: ['moon_illumination'],
    phases: [], methods: [], a: '', b: '', extremaField: 'moon_distance_geocentric', extremaType: 'ambos'};
const check = (condition, message) => { if (!condition) throw new Error(message); };

const dailyUrl = tools.build({...defaults, fields: ['moon_illumination', 'moon_distance_geocentric'],
    phases: schema.phases, methods: ['product', 'average'], a: 'moon_illumination', b: 'moon_distance_geocentric',
    locationLabel: 'CABA'}, 'https://example.test/explorador/');
const daily = tools.parse(new URL(dailyUrl).search, schema, defaults);
check(daily.valid && daily.config.phases.length === 4 && daily.config.methods.length === 2, 'daily round trip');
check(daily.config.a === 'moon_illumination' && daily.config.locationLabel === 'CABA', 'relation and label');

const extremaUrl = tools.build({...defaults, mode: 'extremos', locationLabel: '', extremaField: 'moon_distance_geocentric',
    extremaType: 'ambos'}, 'https://example.test/explorador/');
const extrema = tools.parse(new URL(extremaUrl).search, schema, defaults);
check(extrema.valid && extrema.config.extremaType === 'ambos', 'extrema round trip');
check(!new URL(extremaUrl).searchParams.has('campos'), 'hidden mode state omitted');

check(!tools.parse('?v=2&modo=diario', schema, defaults).valid, 'unknown version');
check(!tools.parse('?v=1&modo=diario&desde=2026-01-01&hasta=2026-01-02&lat=0&lon=0&tz=Bad&campos=bad', schema, defaults).valid,
    'invalid timezone and field');
check(!tools.parse('?v=1&modo=extremos&desde=2026-01-01&hasta=2026-01-02&lat=0&lon=0&tz=UTC&variable=moon_distance_geocentric&tipo=ambos&fases=full_moon', schema, defaults).valid,
    'phases incompatible with extrema');
check(!tools.parse('', schema, defaults).present, 'normal URL');

console.log('OK share-config');
