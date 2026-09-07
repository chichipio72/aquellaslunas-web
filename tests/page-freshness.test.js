(function () {
  'use strict';

  function assert(name, condition) {
    const item = document.createElement('li');
    item.dataset.result = condition ? 'ok' : 'error';
    item.textContent = (condition ? 'OK: ' : 'ERROR: ') + name;
    document.getElementById('results').appendChild(item);
    if (!condition) throw new Error(name);
  }

  function check(notice) {
    document.dispatchEvent(new Event('visibilitychange'));
    return !notice.hidden;
  }

  window.addEventListener('DOMContentLoaded', function () {
    const notice = document.querySelector('[data-page-freshness]');
    assert('una página recién cargada a las 20:00 simuladas no muestra aviso', notice.hidden);

    window.siteTimeContext.now = '2026-08-16T22:59:00-03:00';
    assert('a las 2 h 59 min simuladas sigue oculto', !check(notice));

    window.siteTimeContext.now = '2026-08-16T23:00:00-03:00';
    assert('exactamente a las 3 horas simuladas sigue oculto', !check(notice));

    window.siteTimeContext.now = '2026-08-16T23:30:00-03:00';
    assert('a las 23:30 simuladas aparece con 3 h 30 min', check(notice));

    document.dispatchEvent(new Event('visibilitychange'));
    window.dispatchEvent(new PageTransitionEvent('pageshow'));
    assert('varias comprobaciones no duplican el aviso', document.querySelectorAll('[data-page-freshness]').length === 1 && !notice.hidden);

    notice.hidden = true;
    window.siteTimeContext.now = '2026-08-16T20:00:00-03:00';
    window.dispatchEvent(new PageTransitionEvent('pageshow'));
    assert('debug detenido en las 20:00 no envejece por tiempo real', notice.hidden);

    window.siteTimeContext.simulated = false;
    document.dispatchEvent(new Event('visibilitychange'));
    assert('salir del modo debug reinicia naturalmente la escala normal', notice.hidden && notice.dataset.pageLoadedWithSimulatedClock === 'false');

    notice.dataset.pageLoadedAt = String(Date.now() - (3 * 60 * 60 * 1000 + 1));
    assert('en modo normal más de 3 horas muestra el aviso', check(notice));
  }, { once: true });
}());
