(function () {
  'use strict';

  function field(name) {
    return document.querySelector('[data-swipe-diagnostic="' + name + '"]').textContent;
  }

  function pointer(type, properties, target) {
    const event = new Event(type, { bubbles: true });
    Object.keys(properties).forEach(function (key) {
      Object.defineProperty(event, key, { value: properties[key] });
    });
    (target || document.querySelector('main')).dispatchEvent(event);
  }

  function touch(type, touches, changedTouches) {
    const event = new Event(type, { bubbles: true });
    Object.defineProperty(event, 'touches', { value: touches || [] });
    Object.defineProperty(event, 'changedTouches', { value: changedTouches || [] });
    document.querySelector('main').dispatchEvent(event);
  }

  function touchPoint(identifier, clientX, clientY) {
    return { identifier: identifier, clientX: clientX, clientY: clientY };
  }

  function start() {
    pointer('pointerdown', { pointerId: 7, pointerType: 'touch', isPrimary: true, clientX: 345, clientY: 385 });
  }

  function cancel(properties) {
    pointer('pointercancel', Object.assign({ pointerId: 7, pointerType: 'touch', isPrimary: true }, properties));
  }

  function assert(name, condition, detail) {
    const item = document.createElement('li');
    item.textContent = (condition ? 'OK: ' : 'ERROR: ') + name + (detail ? ' — ' + detail : '');
    item.dataset.result = condition ? 'ok' : 'error';
    document.getElementById('results').appendChild(item);
    if (!condition) throw new Error(name + ': ' + detail);
  }

  window.addEventListener('DOMContentLoaded', function () {
    start();
    pointer('pointermove', { pointerId: 7, pointerType: 'touch', isPrimary: true, clientX: 200, clientY: 390 });
    cancel({ clientX: 0, clientY: 0 });
    assert('pointercancel 0,0 no contamina los deltas', field('deltaX') === '-145' && field('deltaY') === '5', field('deltaX') + ', ' + field('deltaY'));
    assert('gesto horizontal cancelado se acepta', field('result') === 'aceptado', field('result'));

    start();
    pointer('pointermove', { pointerId: 7, pointerType: 'touch', isPrimary: true, clientX: 190, clientY: 388 });
    cancel({ clientX: undefined, clientY: undefined });
    assert('coordenadas ausentes usan el último move', field('effectiveEnd') === '190, 388' && field('deltaX') === '-155', field('effectiveEnd'));

    start();
    pointer('pointermove', { pointerId: 7, pointerType: 'touch', isPrimary: true, clientX: 330, clientY: 230 });
    cancel({ clientX: 0, clientY: 0 });
    assert('gesto vertical cancelado se rechaza', field('result') === 'rechazado' && field('reason') === 'movimiento vertical o diagonal', field('reason'));

    start();
    cancel({ clientX: 0, clientY: 0 });
    assert('cancelación sin pointermove sólo cancela', field('result') === 'rechazado' && field('reason') === 'pointercancel' && field('destination') === '—', field('reason'));

    pointer('pointerdown', { pointerId: 9, pointerType: 'touch', isPrimary: true, clientX: 661, clientY: 911 });
    assert('touch-action efectivo en el detector', field('touchActionTarget') === 'pan-y pinch-zoom' && field('touchActionMain') === 'pan-y pinch-zoom' && field('touchActionBody') === 'pan-y pinch-zoom' && field('touchActionHtml') === 'pan-y pinch-zoom', field('touchActionTarget') + ' / ' + field('touchActionMain') + ' / ' + field('touchActionBody') + ' / ' + field('touchActionHtml'));
    touch('touchstart', [touchPoint(21, 661, 911)], [touchPoint(21, 661, 911)]);
    touch('touchmove', [touchPoint(21, 560, 908)], [touchPoint(21, 560, 908)]);
    pointer('pointermove', { pointerId: 9, pointerType: 'touch', isPrimary: true, clientX: 650, clientY: 909 });
    pointer('pointercancel', { pointerId: 9, pointerType: 'touch', isPrimary: true, clientX: 0, clientY: 0 });
    touch('touchmove', [touchPoint(21, 410, 905)], [touchPoint(21, 410, 905)]);
    touch('touchend', [], [touchPoint(21, 350, 904)]);
    assert('Touch Events conserva la trayectoria tras pointercancel', field('source') === 'Touch Events' && field('deltaX') === '-311' && field('deltaY') === '-7', field('source') + ' ' + field('deltaX') + ', ' + field('deltaY'));
    assert('pointercancel no duplica ni interrumpe el gesto touch', field('pointerContinued') === 'sí; Touch Events continuó' && field('result') === 'aceptado', field('pointerContinued'));

    const destinationButton = document.querySelector('.swipe-diagnostics button');
    const acceptedDestination = field('destination');
    pointer('pointerdown', { pointerId: 31, pointerType: 'touch', isPrimary: true, clientX: 20, clientY: 20 }, destinationButton);
    assert('tocar el botón no borra el destino aceptado', !destinationButton.disabled && field('destination') === acceptedDestination, field('destination'));
  }, { once: true });
}());
