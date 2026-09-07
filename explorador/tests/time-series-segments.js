'use strict';

global.window = global;
require('../assets/time-series-segments.js');
const split = global.ExplorerTimeSeriesSegments.splitAtMidnight;
const splitCircular = global.ExplorerTimeSeriesSegments.splitCircular;
const check = (condition, message) => { if (!condition) throw new Error(message); };

check(split([10, 10.5, 11]).length === 1, 'serie normal');
let segments = split([23 + 55 / 60, 5 / 60]);
check(segments.length === 2 && segments[0][0] !== null && segments[1][1] !== null, 'cruce ascendente');
segments = split([5 / 60, 23 + 55 / 60]);
check(segments.length === 2 && segments[0][0] !== null && segments[1][1] !== null, 'cruce descendente');
segments = split([10, null, 10.2]);
check(segments.length === 2 && segments[0][1] === null && segments[1][1] === null, 'hueco real');
check(split([null, null]).length === 0, 'período sin eventos');
check(split([0, 12]).length === 1 && split([0, 12.01]).length === 2, 'umbral estricto');
segments = splitCircular([350, 359, 1, 12]);
check(segments.length === 2 && segments[0][1] === 359 && segments[1][2] === 1, 'cruce circular 360 a 0');
check(splitCircular([10, null, 20]).length === 2, 'hueco circular real');

console.log('OK time-series-segments');
