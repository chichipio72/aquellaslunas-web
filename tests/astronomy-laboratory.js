const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

assert(astronomyLaboratoryFormatDayFraction(0) === '00:00', 'No formateó 00:00.');
assert(astronomyLaboratoryFormatDayFraction(0.5) === '12:00', 'No formateó 12:00.');
assert(astronomyLaboratoryFormatDayFraction(0.772222) === '18:32', 'No normalizó los minutos.');
assert(astronomyLaboratoryFormatDayFraction(1) === '24:00', 'No formateó una duración diaria.');
assert(astronomyLaboratoryFormatDayFraction(null) === 'Sin dato', 'No conservó el valor nulo.');
assert(astronomyLaboratoryFormatNumber(1234567.8) === '1.234.567,80', 'No aplicó miles y dos decimales.');
assert(astronomyLaboratoryFormatNumber(-12.5) === '-12,50', 'No formateó correctamente un valor negativo.');

assert(astronomyLaboratoryDateForYear('2020', 'start') === '2020-01-01', 'Falló el año desde.');
assert(astronomyLaboratoryDateForYear('2025', 'end') === '2025-12-31', 'Falló el año hasta.');
assert(astronomyLaboratoryYearFromDate('2024-08-17') === '2024', 'No sincronizó el año de una fecha manual.');

assert(astronomyLaboratorySubtractYears('2026-07-30', 1, 1900) === '2025-07-30', 'Falló el último año.');
assert(astronomyLaboratorySubtractYears('2026-07-30', 5, 1900) === '2021-07-30', 'Fallaron los últimos cinco años.');
assert(astronomyLaboratorySubtractYears('2026-07-30', 10, 1900) === '2016-07-30', 'Fallaron los últimos diez años.');
assert(astronomyLaboratorySubtractYears('2024-02-29', 1, 1900) === '2023-02-28', 'No ajustó el 29 de febrero.');
assert(astronomyLaboratorySubtractYears('1905-06-10', 10, 1900) === '1900-06-10', 'No respetó el año mínimo.');
assert(astronomyLaboratoryAddYears('2026-07-30', 1, 2100) === '2027-07-30', 'Falló el próximo año.');
assert(astronomyLaboratoryAddYears('2026-07-30', 20, 2100) === '2046-07-30', 'Fallaron los próximos veinte años.');
assert(astronomyLaboratoryAddYears('2024-02-29', 1, 2100) === '2025-02-28', 'No ajustó el 29 de febrero al sumar.');
assert(astronomyLaboratoryAddYears('2095-06-10', 10, 2100) === '2100-06-10', 'No respetó el año máximo.');

const fieldScales = {
  distancia_luna_km: 'lunar_distance_km',
  distancia_sol_km: 'solar_distance_km',
  iluminacion_porc: 'percentage',
  hora_salida_luna: 'time_fraction',
  hora_puesta_sol: 'time_fraction',
  duracion_dia: 'time_duration',
  azimut_salida_luna: 'angle_degrees',
  amplitud_puesta_sol: 'signed_angle_degrees',
  diferencia_salida_luna_min: 'difference_minutes',
  dia_ciclo_lunar: 'cycle_days',
};
const scaleDefinitions = {
  lunar_distance_km: { label: 'Distancia lunar (km)', format: 'integer', center_zero: false, steps: [500, 1000, 2000, 5000, 10000], intervals: 8 },
  solar_distance_km: { label: 'Distancia Tierra–Sol (km)', format: 'integer', center_zero: false, steps: [100000, 250000, 500000, 1000000, 2000000], intervals: 8 },
  percentage: { label: 'Porcentaje (%)', format: 'number', center_zero: false, steps: [5, 10, 20, 25], natural_min: 0, natural_max: 100, intervals: 8 },
  time_fraction: { label: 'Hora', format: 'HH:MM', center_zero: false, steps: [1 / 96, 1 / 48, 1 / 24, 1 / 12, 1 / 8, 1 / 4], natural_min: 0, natural_max: 1, intervals: 8 },
  time_duration: { label: 'Duración', format: 'HH:MM', center_zero: false, steps: [1 / 96, 1 / 48, 1 / 24, 1 / 12, 1 / 8, 1 / 4], natural_min: 0, natural_max: 1, intervals: 8 },
  angle_degrees: { label: 'Ángulo (°)', format: 'number', center_zero: false, steps: [1, 2, 5, 10, 15, 20, 30, 45], intervals: 8 },
  signed_angle_degrees: { label: 'Ángulo respecto de cero (°)', format: 'number', center_zero: true, steps: [1, 2, 5, 10, 15, 20, 30, 45], intervals: 8 },
  difference_minutes: { label: 'Diferencia (min)', format: 'number', center_zero: true, steps: [1, 2, 5, 10, 15, 30, 60], intervals: 8 },
  cycle_days: { label: 'Días', format: 'number', center_zero: false, steps: [.25, .5, 1, 2, 5, 10], intervals: 8 },
};

assert(
  astronomyLaboratorySelectedScaleGroups(['distancia_luna_km', 'iluminacion_porc'], fieldScales).join(',') === 'lunar_distance_km,percentage',
  'Distancia e iluminación no generaron dos ejes.'
);
assert(
  astronomyLaboratorySelectedScaleGroups(['distancia_luna_km', 'distancia_sol_km'], fieldScales).join(',') === 'lunar_distance_km,solar_distance_km',
  'Las distancias lunar y solar no recibieron escalas independientes.'
);
assert(
  astronomyLaboratorySelectedScaleGroups(['hora_salida_luna', 'hora_puesta_sol'], fieldScales).join(',') === 'time_fraction',
  'Las salidas y puestas no compartieron eje horario.'
);
assert(
  astronomyLaboratorySelectedScaleGroups(['hora_salida_luna', 'duracion_dia'], fieldScales).join(',') === 'time_fraction,time_duration',
  'Hora y duración compartieron eje.'
);
assert(
  astronomyLaboratorySelectedScaleGroups(['azimut_salida_luna', 'amplitud_puesta_sol'], fieldScales).join(',') === 'angle_degrees,signed_angle_degrees',
  'Azimutes absolutos y amplitudes no recibieron escalas físicas independientes.'
);
assert(
  astronomyLaboratorySelectedScaleGroups(['diferencia_salida_luna_min'], fieldScales)[0] === 'difference_minutes',
  'Las diferencias no recibieron su propio eje.'
);

const threeAxes = astronomyLaboratoryBuildAxisLayout(
  ['lunar_distance_km', 'percentage', 'angle_degrees'],
  scaleDefinitions
);
assert(
  threeAxes.axes[0].position === 'left' && threeAxes.axes[0].offset === 0
    && threeAxes.axes[1].position === 'right' && threeAxes.axes[1].offset === 0
    && threeAxes.axes[2].position === 'left' && threeAxes.axes[2].offset === 76,
  'Los índices u offsets de tres ejes son incorrectos.'
);
assert(threeAxes.grid.left === 152 && threeAxes.grid.right === 58, 'El grid de tres ejes no reservó margen.');
assert(threeAxes.grid.top === 124, 'El título del eje derecho no reservó la franja de la toolbox.');

const fourAxes = astronomyLaboratoryBuildAxisLayout(
  ['lunar_distance_km', 'percentage', 'angle_degrees', 'difference_minutes'],
  scaleDefinitions
);
assert(
  fourAxes.axes[3].position === 'right' && fourAxes.axes[3].offset === 76
    && fourAxes.indexes.difference_minutes === 3,
  'Los índices u offsets de cuatro ejes son incorrectos.'
);
assert(fourAxes.grid.left === 152 && fourAxes.grid.right === 140, 'El grid de cuatro ejes puede recortar etiquetas.');
const percentageAxis = astronomyLaboratoryBuildAxisLayout(['percentage'], scaleDefinitions).axes[0];
assert(
  percentageAxis.max - percentageAxis.min === percentageAxis.interval * 8,
  'El porcentaje inicial no tiene ocho intervalos.'
);
assert(
  astronomyLaboratoryFormatAxisLabel(0, 'percentage', { ...scaleDefinitions.percentage, current_interval: 20 }) === '0'
    && astronomyLaboratoryFormatAxisLabel(100, 'percentage', { ...scaleDefinitions.percentage, current_interval: 20 }) === '100',
  'El eje porcentual no usa etiquetas enteras.'
);
assert(
  astronomyLaboratoryFormatAxisLabel(0, 'time_fraction', scaleDefinitions.time_fraction) === '00:00'
    && astronomyLaboratoryFormatAxisLabel(1, 'time_fraction', scaleDefinitions.time_fraction) === '24:00',
  'El eje horario perdió HH:MM.'
);
const distanceAxis = astronomyLaboratoryBuildAxisLayout(
  ['lunar_distance_km'], scaleDefinitions, { lunar_distance_km: [356000, 371000] }
).axes[0];
assert(
  distanceAxis.min > 0 && distanceAxis.max - distanceAxis.min === distanceAxis.interval * 8,
  'La distancia lunar incluyó cero o no tiene ocho intervalos.'
);
const signedAxis = astronomyLaboratoryBuildAxisLayout(
  ['signed_angle_degrees'], scaleDefinitions, { signed_angle_degrees: [-23, 31] }
).axes[0];
assert(signedAxis.min === -40 && signedAxis.max === 40 && signedAxis.interval === 10, 'La amplitud no quedó simétrica en cero.');
const solarAxis = astronomyLaboratoryBuildAxisLayout(
  ['solar_distance_km'], scaleDefinitions, { solar_distance_km: [147000000, 152000000] }
).axes[0];
assert(solarAxis.min > 0 && astronomyLaboratoryFormatAxisLabel(149000000, 'solar_distance_km', scaleDefinitions.solar_distance_km) === '149.000.000', 'La distancia solar incluyó cero o mostró decimales.');
assert(astronomyLaboratoryFormatAxisLabel(23.5, 'cycle_days', { ...scaleDefinitions.cycle_days, current_interval: .5 }) === '23,5', 'Un salto decimal pequeño perdió precisión útil.');
assert(astronomyLaboratoryFormatAxisLabel(384500, 'lunar_distance_km', scaleDefinitions.lunar_distance_km) === '384.500', 'Los kilómetros no usan miles enteros.');
assert(
  astronomyLaboratorySelectedScaleGroups(
    ['distancia_luna_km', 'iluminacion_porc', 'azimut_salida_luna', 'diferencia_salida_luna_min', 'dia_ciclo_lunar'],
    fieldScales
  ).length === 5,
  'No se detectaron más de cuatro grupos.'
);
assert(astronomyLaboratoryFormatSeriesValue(42, 'number', '%') === '42,00%', 'El tooltip perdió porcentaje.');
assert(astronomyLaboratoryFormatSeriesValue(-12.5, 'number', '°') === '-12,50°', 'El tooltip perdió grados.');
assert(astronomyLaboratoryFormatSeriesValue(0.5, 'time_duration') === '12:00', 'El tooltip perdió duración.');

const lunarExtremaVariable = {
  body: 'moon',
  minimumYears: 2,
  recommendedYears: 5,
};
assert(
  astronomyLaboratoryExtremaRangeAssessment('2025-01-01', '2026-12-31', lunarExtremaVariable).status === 'blocked',
  'El cliente no bloqueó un rango lunar demasiado corto.'
);
assert(
  astronomyLaboratoryExtremaRangeAssessment('2022-01-01', '2025-12-31', lunarExtremaVariable).status === 'warning',
  'El cliente no advirtió un rango lunar inferior al recomendado.'
);
assert(
  astronomyLaboratoryExtremaRangeAssessment('2020-01-01', '2025-12-31', lunarExtremaVariable).status === 'ok',
  'El cliente rechazó un rango lunar recomendado.'
);
const extremaPayload = {
  series_labels: { maximos: 'Apogeos', minimos: 'Perigeos' },
  series: {
    maximos: [{ fecha: '2020-01-03', valor: 405000 }, { fecha: '2020-02-01', valor: 404000 }],
    minimos: [{ fecha: '2020-01-15', valor: 356000 }],
  },
};
const extremaSeries = astronomyLaboratoryBuildExtremaSeries(extremaPayload);
assert(
  extremaSeries.length === 2
    && extremaSeries[0].name === 'Apogeos'
    && extremaSeries[1].name === 'Perigeos',
  'Ambos no creó series independientes con nombres astronómicos.'
);
assert(
  extremaSeries.every((series) => series.type === 'line' && series.smooth === false && series.showSymbol === true),
  'Los extremos no usan líneas rectas con puntos visibles.'
);
assert(
  extremaSeries[0].data.length === 2 && extremaSeries[1].data.length === 1,
  'Los conteos visuales de extremos no son coherentes.'
);
assert(
  astronomyLaboratoryBuildExtremaSeries({
    series_labels: extremaPayload.series_labels,
    series: { maximos: extremaPayload.series.maximos, minimos: [] },
  }).length === 1,
  'Máximos solamente generó una serie incompatible.'
);
const extremaAxis = astronomyLaboratoryBuildExtremaAxis(
  {
    scale_group: 'lunar_distance_km',
    variable_label: 'Distancia de la Luna (km)',
    field_type: 'number',
    series: extremaPayload.series,
  },
  scaleDefinitions
);
assert(
  extremaAxis.scale === true && extremaAxis.min > 0
    && extremaAxis.max - extremaAxis.min === extremaAxis.interval * 8,
  'El eje de extremos fuerza cero o no tiene ocho intervalos.'
);
const zoomed = astronomyLaboratoryZoomScale({ min: 0, max: 100, interval: 20 }, 20, -1, scaleDefinitions.percentage);
assert(zoomed.max - zoomed.min < 100 && zoomed.anchorRatio === .2, 'El zoom vertical no usa el puntero como centro.');
const panned = astronomyLaboratoryPanScale({ min: 20, max: 60, interval: 5 }, 15, scaleDefinitions.percentage);
assert(panned.min === 35 && panned.max === 75 && panned.interval === 5, 'El paneo vertical cambió el zoom actual.');
const clampedPan = astronomyLaboratoryPanScale({ min: 60, max: 100, interval: 5 }, 30, scaleDefinitions.percentage);
assert(clampedPan.min === 60 && clampedPan.max === 100, 'El paneo vertical excedió el límite porcentual.');
assert(astronomyLaboratoryNearestAxisIndex(312, [80, 300, 620]) === 1, 'El clic sobre etiquetas/título no selecciona el eje correcto.');
assert(astronomyLaboratoryNearestAxisIndex(450, [80, 300, 620]) === null, 'Un clic ambiguo dentro del gráfico activó un eje por proximidad.');
assert(astronomyLaboratorySeriesAxisIndex({ series: [{ yAxisIndex: 0 }, { yAxisIndex: 3 }] }, 1) === 3, 'El clic en una serie no identifica su eje.');
const durationScale = astronomyLaboratoryNiceScale([.4, .6], scaleDefinitions.time_duration);
assert(Math.abs((durationScale.max - durationScale.min) - durationScale.interval * 8) < 1e-9 && [15, 30, 60, 120, 180, 360].includes(Math.round(durationScale.interval * 1440)), 'La duración no usa ocho saltos horarios permitidos.');
const differenceScale = astronomyLaboratoryNiceScale([8, 47], scaleDefinitions.difference_minutes);
assert([1, 2, 5, 10, 15, 30, 60].includes(differenceScale.interval), 'La diferencia no usa un salto permitido.');
assert(astronomyLaboratoryRelationControlState(['a']).enabled === false, 'Una variable no deshabilitó el análisis de relación.');
const suspendedRelation = astronomyLaboratoryRelationControlState(['a'], ['product', 'average']);
assert(
  suspendedRelation.requestedMethods.join(',') === 'product,average'
    && suspendedRelation.renderedMethods.length === 0,
  'Al suspender la relación se perdieron las dos modalidades solicitadas.'
);
const twoVariableRelation = astronomyLaboratoryRelationControlState(['a', 'b']);
assert(twoVariableRelation.automatic === true && twoVariableRelation.fields.join(',') === 'a,b', 'Dos variables no se eligieron automáticamente.');
const restoredRelation = astronomyLaboratoryRelationControlState(['a', 'c'], suspendedRelation.requestedMethods);
assert(
  restoredRelation.renderedMethods.join(',') === 'product,average',
  'Las modalidades solicitadas no reaparecieron al volver a dos variables.'
);
assert(astronomyLaboratoryRelationControlState(['a', 'b', 'c']).showSelectors === true, 'Más de dos variables no habilitaron los selectores.');

let debouncedCalls = 0;
let nextTimerId = 0;
const pendingTimers = new Map();
const fakeTimers = {
  setTimeout(callback) {
    nextTimerId += 1;
    pendingTimers.set(nextTimerId, callback);
    return nextTimerId;
  },
  clearTimeout(timerId) { pendingTimers.delete(timerId); },
};
const debouncedRequest = astronomyLaboratoryCreateDebouncedRequest(
  () => { debouncedCalls += 1; },
  250,
  fakeTimers
);
debouncedRequest.schedule();
debouncedRequest.schedule();
debouncedRequest.schedule();
assert(pendingTimers.size === 1 && debouncedCalls === 0, 'El debounce generó solicitudes duplicadas.');
[...pendingTimers.values()][0]();
assert(debouncedCalls === 1 && !debouncedRequest.pending(), 'El cambio agrupado no produjo una única solicitud.');
const normalizedRelationValues = astronomyLaboratoryNormalizeValues([10, 20, 30, null]);
assert(
  normalizedRelationValues.values[0] === -1
    && normalizedRelationValues.values[1] === 0
    && normalizedRelationValues.values[2] === 1
    && normalizedRelationValues.values[3] === null,
  'La normalización estable min–max es incorrecta.'
);
const relationRows = [
  { fecha: '2026-01-01', a: 0, b: 10 },
  { fecha: '2026-01-02', a: 5, b: 5 },
  { fecha: '2026-01-03', a: 10, b: null },
];
const relation = astronomyLaboratoryBuildRelation(relationRows, 'a', 'b');
assert(
  relation[0].relation.index === -1
    && relation[1].relation.index === 0
    && relation[2].relation.index === null,
  'El producto normalizado o el tratamiento de NULL es incorrecto.'
);
assert(
  relation.map((point) => point.value[0]).join(',') === relationRows.map((row) => row.fecha).join(','),
  'La banda no conserva la alineación temporal.'
);
assert(
  astronomyLaboratoryInterpretRelation(.8) === 'Coincidencia fuerte'
    && astronomyLaboratoryInterpretRelation(-.8) === 'Oposición fuerte'
    && astronomyLaboratoryInterpretRelation(0) === 'Neutro',
  'Las interpretaciones del tooltip son incorrectas.'
);
const averageRelation = astronomyLaboratoryBuildRelation(relationRows, 'a', 'b', 'average');
assert(
  averageRelation[0].relation.index === 0
    && averageRelation[1].relation.index === -.5
    && averageRelation[2].relation.index === null,
  'El promedio normalizado o el tratamiento de NULL es incorrecto.'
);
assert(
  astronomyLaboratoryInterpretRelation(.8, 'average') === 'Refuerzo positivo fuerte'
    && astronomyLaboratoryInterpretRelation(-.8, 'average') === 'Refuerzo negativo fuerte'
    && astronomyLaboratoryInterpretRelation(0, 'average') === 'Equilibrio',
  'La interpretación del promedio normalizado es incorrecta.'
);
const alignedRelationGrid = astronomyLaboratoryRelationGridEdges(1200, { x: 184, width: 870 });
assert(
  alignedRelationGrid.left === 184 && alignedRelationGrid.right === 146,
  'La banda no reutiliza los límites reales del grid principal.'
);

testLog('astronomy laboratory JavaScript tests: ok');
