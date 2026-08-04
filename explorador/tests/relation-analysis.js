'use strict';

global.window = global;
require('../assets/relation-analysis.js');

const relation = global.ExplorerRelationAnalysis;
const assert = (condition, message) => { if (!condition) throw new Error(message); };

assert(!relation.controlState(['a']).enabled, 'Menos de dos variables no deshabilitó el análisis.');
assert(relation.controlState(['a', 'b']).automatic, 'Dos variables no activaron selección automática.');
assert(!relation.controlState(['a', 'b'], true).showSelectors, 'Dos variables mostraron selectores A/B.');
assert(!relation.controlState(['a', 'b', 'c'], false).showSelectors, 'Sin método activo se mostraron selectores A/B.');
assert(relation.controlState(['a', 'b', 'c'], true).showSelectors, 'Más de dos variables con método no mostró selectores.');
assert(!relation.controlState(['a', 'b', 'c'], true, false).showSelectors, 'Extremos mostró selectores A/B.');

const normalized = relation.normalize([10, 20, 30, null, '', 'texto', Infinity]);
assert(normalized.midpoint === 20 && normalized.values[0] === -1 && normalized.values[1] === 0
    && normalized.values[2] === 1 && normalized.values.slice(3).every(value => value === null),
    'La normalización o exclusión de valores inválidos es incorrecta.');
assert(relation.normalize([5, 5, 5]).values.every(value => value === 0), 'La serie constante no se normalizó a cero.');

const rows = [
    {date: '2026-01-01', a: 0, b: 10},
    {date: '2026-01-02', a: 5, b: 5},
    {date: '2026-01-03', a: 10, b: null},
];
const product = relation.build(rows, 'a', 'b', 'product');
const average = relation.build(rows, 'a', 'b', 'average');
assert(product[0].relation.index === -1 && product[1].relation.index === 0
    && product[2].relation.index === null, 'Coincidencia/oposición o null incorrectos.');
assert(average[0].relation.index === 0 && average[1].relation.index === -0.5
    && average[2].relation.index === null, 'Refuerzo o null incorrectos.');
assert(relation.interpret(0.8, 'product') === 'Coincidencia fuerte'
    && relation.interpret(-0.8, 'product') === 'Oposición fuerte'
    && relation.interpret(0.8, 'average') === 'Refuerzo positivo fuerte', 'Clasificaciones incorrectas.');

const state = relation.captureInteraction({legend: [{selected: {A: false, B: true}}],
    dataZoom: [{start: 20, end: 70}, {startValue: 4, endValue: 12}]});
const restored = relation.restoreInteraction(state, [0, 1, 2]);
assert(restored.legend.selected.A === false && restored.legend.selected.B === true,
    'No se conservó el estado de leyenda.');
assert(restored.dataZoom[0].start === 20 && restored.dataZoom[0].end === 70
    && restored.dataZoom[0].xAxisIndex.length === 3, 'No se conservó el zoom sincronizado.');

console.log('Relation analysis JavaScript smoke tests: OK');
