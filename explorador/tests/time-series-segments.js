'use strict';

global.window = global;
require('../assets/time-series-segments.js');
const split = global.ExplorerTimeSeriesSegments.splitAtMidnight;
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

console.log('OK time-series-segments');
