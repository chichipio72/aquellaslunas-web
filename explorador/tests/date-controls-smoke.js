'use strict';

const tools = window.ExplorerDateTools;
const expect = (actual, expected, label) => {
    if (actual !== expected) throw new Error(`${label}: ${actual} !== ${expected}`);
};

expect(tools.yearBoundary('2000', '01-01'), '2000-01-01', 'Año desde');
expect(tools.yearBoundary('2020', '12-31'), '2020-12-31', 'Año hasta');
expect(tools.yearBoundary('20.5', '01-01'), null, 'Año decimal inválido');
expect(tools.backwardRangeStart('2026-12-31', 1), '2026-01-01', '1 año hacia atrás');
expect(tools.backwardRangeStart('2026-12-31', 10), '2017-01-01', '10 años hacia atrás');
expect(tools.forwardRangeEnd('2026-01-01', 5), '2030-12-31', '5 años hacia adelante');
expect(tools.forwardRangeEnd('2026-01-01', 20), '2045-12-31', '20 años hacia adelante');
expect(tools.forwardRangeEnd('2024-02-29', 1), '2025-02-28', '29 de febrero hacia adelante');
expect(tools.backwardRangeStart('2025-02-28', 1), '2024-03-01', '29 de febrero hacia atrás');
expect(tools.backwardRangeStart('2026-12-31', 2), '2025-01-01', 'Dos años inclusivos al cierre del año');
expect(tools.backwardRangeStart('2026-08-03', 2), '2024-08-04', 'Dos años inclusivos con fecha intermedia');

const modes = tools.createModeDateState('2026-07-05', '2026-08-03');
let dates = modes.switchTo('extremos', '2026-07-05', '2026-08-03');
expect(dates.from, '2024-08-04', 'Primera entrada a Extremos');
expect(dates.to, '2026-08-03', 'Extremo final inicial');
dates = modes.switchTo('diario', '2024-09-10', '2026-09-09');
expect(dates.from, '2026-07-05', 'Restauración de Serie diaria');
expect(dates.to, '2026-08-03', 'Fecha final de Serie diaria');
dates = modes.switchTo('extremos', '2020-01-01', '2020-12-31');
expect(dates.from, '2024-09-10', 'Restauración del rango propio de Extremos');
expect(dates.to, '2026-09-09', 'Fecha final propia de Extremos');
dates = modes.switchTo('diario', '2024-09-10', '2026-09-09');
expect(dates.from, '2020-01-01', 'Persistencia del rango modificado de Serie diaria');
