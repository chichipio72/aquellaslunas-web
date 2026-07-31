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
  distancia_luna_km: 'distance_km',
  distancia_sol_km: 'distance_km',
  iluminacion_porc: 'percentage',
  hora_salida_luna: 'time_fraction',
  hora_puesta_sol: 'time_fraction',
  duracion_dia: 'time_duration',
  azimut_salida_luna: 'angle_degrees',
  amplitud_puesta_sol: 'angle_degrees',
  diferencia_salida_luna_min: 'difference_minutes',
  dia_ciclo_lunar: 'cycle_days',
};
const scaleDefinitions = {
  distance_km: { label: 'Distancia (km)' },
  percentage: { label: 'Porcentaje (%)', min: -2, max: 102, data_min: 0, data_max: 100, interval: 25 },
  time_fraction: { label: 'Hora', min: -0.02, max: 1.02, data_min: 0, data_max: 1, interval: 0.25 },
  time_duration: { label: 'Duración', min: -0.02, max: 1.02, data_min: 0, data_max: 1, interval: 0.25 },
  angle_degrees: { label: 'Ángulo (°)' },
  difference_minutes: { label: 'Diferencia (min)' },
  cycle_days: { label: 'Días' },
};

assert(
  astronomyLaboratorySelectedScaleGroups(['distancia_luna_km', 'iluminacion_porc'], fieldScales).join(',') === 'distance_km,percentage',
  'Distancia e iluminación no generaron dos ejes.'
);
assert(
  astronomyLaboratorySelectedScaleGroups(['distancia_luna_km', 'distancia_sol_km'], fieldScales).join(',') === 'distance_km',
  'Las dos distancias no compartieron eje.'
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
  astronomyLaboratorySelectedScaleGroups(['azimut_salida_luna', 'amplitud_puesta_sol'], fieldScales).join(',') === 'angle_degrees',
  'Azimutes y amplitudes no compartieron eje angular.'
);
assert(
  astronomyLaboratorySelectedScaleGroups(['diferencia_salida_luna_min'], fieldScales)[0] === 'difference_minutes',
  'Las diferencias no recibieron su propio eje.'
);

const threeAxes = astronomyLaboratoryBuildAxisLayout(
  ['distance_km', 'percentage', 'angle_degrees'],
  scaleDefinitions
);
assert(
  threeAxes.axes[0].position === 'left' && threeAxes.axes[0].offset === 0
    && threeAxes.axes[1].position === 'right' && threeAxes.axes[1].offset === 0
    && threeAxes.axes[2].position === 'left' && threeAxes.axes[2].offset === 76,
  'Los índices u offsets de tres ejes son incorrectos.'
);
assert(threeAxes.grid.left === 152 && threeAxes.grid.right === 58, 'El grid de tres ejes no reservó margen.');

const fourAxes = astronomyLaboratoryBuildAxisLayout(
  ['distance_km', 'percentage', 'angle_degrees', 'difference_minutes'],
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
  percentageAxis.min === -2 && percentageAxis.max === 102,
  'Iluminación al mínimo o al 100 % continúa pegada al borde.'
);
assert(
  astronomyLaboratoryFormatAxisLabel(-2, 'percentage', scaleDefinitions.percentage) === ''
    && astronomyLaboratoryFormatAxisLabel(0, 'percentage', scaleDefinitions.percentage) === '0,00'
    && astronomyLaboratoryFormatAxisLabel(100, 'percentage', scaleDefinitions.percentage) === '100,00'
    && astronomyLaboratoryFormatAxisLabel(102, 'percentage', scaleDefinitions.percentage) === '',
  'El eje porcentual muestra etiquetas fuera de 0–100.'
);
assert(
  astronomyLaboratoryFormatAxisLabel(-0.02, 'time_fraction', scaleDefinitions.time_fraction) === ''
    && astronomyLaboratoryFormatAxisLabel(0, 'time_fraction', scaleDefinitions.time_fraction) === '00:00'
    && astronomyLaboratoryFormatAxisLabel(1, 'time_fraction', scaleDefinitions.time_fraction) === '24:00'
    && astronomyLaboratoryFormatAxisLabel(1.02, 'time_fraction', scaleDefinitions.time_fraction) === '',
  'El eje horario muestra etiquetas fuera de 00:00–24:00.'
);
const automaticMargin = astronomyLaboratoryAutomaticAxisMargin({ min: 100, max: 200 });
assert(automaticMargin.min === 95 && automaticMargin.max === 205, 'El margen automático no es del 5 %.');
const constantMargin = astronomyLaboratoryAutomaticAxisMargin({ min: 10, max: 10 });
assert(constantMargin.min === 9 && constantMargin.max === 11, 'Una serie constante quedó sin margen.');
const distanceAxis = astronomyLaboratoryBuildAxisLayout(['distance_km'], scaleDefinitions).axes[0];
assert(
  typeof distanceAxis.min === 'function'
    && distanceAxis.min({ min: 350000, max: 400000 }) === 347500
    && distanceAxis.max({ min: 350000, max: 400000 }) === 402500,
  'El eje automático no calcula márgenes superior e inferior.'
);
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
    scale_group: 'distance_km',
    variable_label: 'Distancia de la Luna (km)',
    field_type: 'number',
  },
  scaleDefinitions
);
assert(
  extremaAxis.scale === true
    && typeof extremaAxis.min === 'function'
    && typeof extremaAxis.max === 'function'
    && extremaAxis.min({ min: 350000, max: 405000 }) < 350000
    && extremaAxis.max({ min: 350000, max: 405000 }) > 405000,
  'El eje de extremos fuerza cero o no agrega margen vertical.'
);

testLog('astronomy laboratory JavaScript tests: ok');
