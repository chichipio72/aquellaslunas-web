if (plannerHasPendingChanges(null, 'edited') !== false) {
  throw new Error('El estado inicial no debe marcar cambios pendientes.');
}
if (plannerHasPendingChanges('applied', 'edited') !== true) {
  throw new Error('Un valor editado distinto debe marcar cambios pendientes.');
}
if (plannerHasPendingChanges('applied', 'applied') !== false) {
  throw new Error('Volver al valor aplicado debe quitar el estado pendiente.');
}
if (
  plannerAppliedMomentSummary(
    '28 de julio de 2026',
    '18:04',
    'Puesta del Sol',
    'Buenos Aires',
  ) !== '28 de julio de 2026, 18:04 · Puesta del Sol · Buenos Aires'
) {
  throw new Error('El resumen no conserva el momento automático aplicado.');
}
if (
  plannerAppliedMomentSummary(
    '28 de julio de 2026',
    '18:04',
    null,
    'Buenos Aires',
  ) !== 'Mostrando el cielo del 28 de julio de 2026 a las 18:04 desde Buenos Aires.'
) {
  throw new Error('Cambió el resumen de la selección manual.');
}
for (const label of [
  'Ahora',
  'Salida del Sol',
  'Puesta del Sol',
  'Salida de la Luna',
  'Puesta de la Luna',
]) {
  let values = null;
  let pendingUpdates = 0;
  let requests = 0;
  let requestedLabel = null;
  const applied = plannerApplyCompleteMoment(
    { date: '2026-07-28', time: '18:04', label },
    {
      isBusy: () => false,
      setValues: (date, time) => { values = { date, time }; },
      markPending: () => { pendingUpdates += 1; },
      apply: (_trigger, momentLabel) => {
        requests += 1;
        requestedLabel = momentLabel;
      },
    },
  );
  if (
    !applied
    || values?.date !== '2026-07-28'
    || values?.time !== '18:04'
    || pendingUpdates !== 1
    || requests !== 1
    || requestedLabel !== label
  ) {
    throw new Error(`El momento automático ${label} no se aplicó exactamente una vez.`);
  }
}
let blockedRequests = 0;
if (plannerApplyCompleteMoment(
  { date: '2026-07-28', time: '18:04', label: 'Salida del Sol' },
  {
    isBusy: () => true,
    setValues: () => { throw new Error('Se cambiaron controles durante otra consulta.'); },
    markPending: () => {},
    apply: () => { blockedRequests += 1; },
  },
) !== false || blockedRequests !== 0) {
  throw new Error('Se inició una segunda consulta mientras había otra activa.');
}
const polarDayMoments = plannerQuickMoments({
  date: '2026-07-28',
  sun: { rise: null, set: null },
  moon: { rise: { time: '18:04:00' }, set: null },
});
if (
  polarDayMoments.length !== 1
  || polarDayMoments[0].label !== 'Salida de la Luna'
  || polarDayMoments[0].date !== '2026-07-28'
  || polarDayMoments[0].time !== '18:04'
) {
  throw new Error('Se inventaron horarios inexistentes o se perdió la fecha del día aplicado.');
}
console.log('Estado editado/aplicado del planificador: OK');
