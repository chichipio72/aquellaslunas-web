'use strict';

global.window = global;
require('../assets/vertical-axis-control.js');

const tools = global.ExplorerVerticalAxisControl;
const close = (actual, expected, message) => {
    if (Math.abs(actual - expected) > 1e-9) throw new Error(`${message}: ${actual} != ${expected}`);
};

const zoomed = tools.zoomRange({min: 0, max: 100}, 25, 0.8);
close(zoomed.min, 5, 'zoom min');
close(zoomed.max, 85, 'zoom max');

const restored = tools.zoomRange(zoomed, 25, 1.25);
close(restored.min, 0, 'inverse zoom min');
close(restored.max, 100, 'inverse zoom max');

const panned = tools.panRange({min: 10, max: 30}, 50, 100);
close(panned.min, 20, 'pan min');
close(panned.max, 40, 'pan max');

const bounded = tools.panRange({min: 80, max: 100}, 50, 100, {min: 0, max: 100});
close(bounded.min, 80, 'bounded pan min');
close(bounded.max, 100, 'bounded pan max');

const axes = [{key: 'left', pixelX: 50}, {key: 'right', pixelX: 450}];
if (tools.nearestAxis(430, axes, true).key !== 'right') throw new Error('nearest visible axis');
if (tools.nearestAxis(250, [{key: 'only', pixelX: 50}], true).key !== 'only') throw new Error('single axis in grid');
if (tools.nearestAxis(250, axes, false) !== null) throw new Error('outside tolerance');

console.log('OK vertical-axis-control');
