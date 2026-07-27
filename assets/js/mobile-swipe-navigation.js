(function () {
  'use strict';

  const body = document.body;
  if (!body || body.dataset.mobileSwipeNavigation !== 'enabled') return;

  document.documentElement.classList.add('mobile-swipe-navigation-enabled');
  const gestureDetector = document.documentElement;
  const mobileViewport = window.matchMedia('(max-width: 767px)');
  const coarsePointer = window.matchMedia('(pointer: coarse)');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const diagnosticsEnabled = body.dataset.swipeDiagnosticsEnabled === 'true';
  const exclusionRules = [
    'a', 'button', 'input', 'select', 'textarea', '[contenteditable]',
    'iframe', '[data-swipe-navigation-ignore]', 'dialog', '[role="dialog"]',
    '[role="tablist"]', '[role="tabpanel"]', '.leaflet-container', '.location-map',
    '[data-altitude-profile]', '.home-altitude-profile', '.modal', '.carousel',
    '.table-scroll', 'table', 'input[type="range"]'
  ];
  const hintStorageKey = 'aquellas-lunas-mobile-swipe-hint-seen-v1';
  let pointerGesture = null;
  let touchGesture = null;
  let navigationStarted = false;
  let hint = null;
  let diagnosticFields = null;
  let lastAcceptedDestination = '';

  function eventPoint(value) {
    if (!value || !Number.isFinite(value.clientX) || !Number.isFinite(value.clientY)) return null;
    return { x: value.clientX, y: value.clientY };
  }

  function pointLabel(point) {
    return point ? Math.round(point.x) + ', ' + Math.round(point.y) : null;
  }

  function displayValue(value) {
    return value === null || value === undefined || value === '' ? '—' : String(value);
  }

  function updateDiagnostics(values) {
    if (!diagnosticFields) return;
    Object.keys(values).forEach(function (key) {
      if (diagnosticFields[key]) diagnosticFields[key].textContent = displayValue(values[key]);
    });
  }

  function reportResult(result, reason, destination) {
    if (result === 'aceptado' && destination) {
      lastAcceptedDestination = destination;
      updateDiagnostics({ destination: lastAcceptedDestination });
    }
    updateDiagnostics({ result: result, reason: reason });
    if (diagnosticFields) diagnosticFields.go.disabled = !lastAcceptedDestination;
  }

  function isDiagnosticsPanelTarget(target) {
    return target instanceof Element && target.closest('.swipe-diagnostics') !== null;
  }

  function touchAction(element) {
    return element instanceof Element ? window.getComputedStyle(element).touchAction : 'no disponible';
  }

  function reportTouchActions(target) {
    const main = document.querySelector('main');
    updateDiagnostics({
      touchActionTarget: touchAction(target),
      touchActionDetector: touchAction(gestureDetector),
      touchActionMain: touchAction(main),
      touchActionBody: touchAction(body),
      touchActionHtml: touchAction(document.documentElement)
    });
  }

  function createDiagnosticsPanel() {
    if (!diagnosticsEnabled) return;
    const panel = document.createElement('aside');
    panel.className = 'swipe-diagnostics';
    panel.dataset.swipeNavigationIgnore = '';
    panel.setAttribute('aria-label', 'Diagnóstico del gesto táctil');
    panel.innerHTML = '<details open data-swipe-navigation-ignore><summary data-swipe-navigation-ignore>Diagnóstico swipe</summary><dl>' + [
      ['enabled', 'Habilitado'], ['section', 'Sección'], ['previous', 'Anterior'], ['next', 'Siguiente'],
      ['source', 'Fuente usada'], ['pointerdown', 'pointerdown'], ['pointerType', 'pointerType'], ['pointerId', 'pointerId'],
      ['start', 'Inicio'], ['pointerLast', 'Último pointer'], ['touchLast', 'Último touch'],
      ['pointerMoves', 'pointermove'], ['touchMoves', 'touchmove'], ['pointerContinued', 'Pointer cancelado'],
      ['eventType', 'Cierre'], ['eventEnd', 'Evento final'], ['effectiveEnd', 'Final efectivo'],
      ['deltaX', 'deltaX efectivo'], ['deltaY', 'deltaY efectivo'], ['duration', 'Duración'],
      ['direction', 'Dirección'], ['touchActionTarget', 'touch-action target'],
      ['touchActionDetector', 'touch-action detector'], ['touchActionMain', 'touch-action main'],
      ['touchActionBody', 'touch-action body'], ['touchActionHtml', 'touch-action html'],
      ['result', 'Resultado'], ['reason', 'Motivo'], ['exclusionRule', 'Regla exclusión'], ['destination', 'Destino'],
      ['clickStatus', 'Click destino'], ['clickUrl', 'URL intentada'], ['clickError', 'Error navegación']
    ].map(function (field) {
      return '<div><dt>' + field[1] + '</dt><dd data-swipe-diagnostic="' + field[0] + '">—</dd></div>';
    }).join('') + '</dl><button type="button" data-swipe-navigation-ignore disabled>Ir al destino detectado</button></details>';
    body.appendChild(panel);
    diagnosticFields = {};
    panel.querySelectorAll('[data-swipe-diagnostic]').forEach(function (element) {
      diagnosticFields[element.dataset.swipeDiagnostic] = element;
    });
    diagnosticFields.go = panel.querySelector('button');
    diagnosticFields.go.addEventListener('click', function () {
      updateDiagnostics({ clickStatus: 'click recibido', clickUrl: lastAcceptedDestination, clickError: null });
      if (!lastAcceptedDestination || navigationStarted) return;
      navigationStarted = true;
      try {
        window.location.assign(lastAcceptedDestination);
      } catch (error) {
        navigationStarted = false;
        updateDiagnostics({ clickError: error instanceof Error ? error.message : String(error) });
      }
    });
    updateDiagnostics({
      enabled: 'sí', section: body.dataset.swipeSection, previous: body.dataset.swipePreviousUrl,
      next: body.dataset.swipeNextUrl, pointerdown: 'no', pointerMoves: 0, touchMoves: 0,
      pointerContinued: 'no'
    });
  }

  function isTouchNavigationAvailable() {
    return mobileViewport.matches && (coarsePointer.matches || navigator.maxTouchPoints > 0);
  }

  function horizontallyScrollableAncestors(element) {
    const containers = [];
    for (let current = element; current && current !== body; current = current.parentElement) {
      const style = window.getComputedStyle(current);
      if (/(auto|scroll)/.test(style.overflowX) && current.scrollWidth > current.clientWidth + 1) containers.push(current);
    }
    return containers;
  }

  function matchingExclusionRule(target) {
    if (!(target instanceof Element)) return 'target no es un elemento';
    for (let index = 0; index < exclusionRules.length; index += 1) {
      if (target.closest(exclusionRules[index])) return exclusionRules[index];
    }
    return null;
  }

  function removeHint() {
    if (hint) hint.remove();
    hint = null;
  }

  function showHintOnce() {
    if (body.dataset.swipeHintEnabled !== 'true' || !isTouchNavigationAvailable()) return;
    try {
      if (window.localStorage.getItem(hintStorageKey)) return;
      window.localStorage.setItem(hintStorageKey, '1');
    } catch (error) {
      return;
    }
    hint = document.createElement('div');
    hint.className = 'mobile-swipe-hint';
    hint.setAttribute('role', 'status');
    hint.textContent = 'Deslizá hacia los lados para cambiar de sección';
    body.appendChild(hint);
    window.setTimeout(removeHint, 4500);
  }

  function newGesture(identifier, point, target, source) {
    return {
      identifier: identifier, source: source, startX: point.x, startY: point.y,
      lastX: point.x, lastY: point.y, endX: null, endY: null,
      moveCount: 0, startedAt: performance.now(),
      scrollContainers: horizontallyScrollableAncestors(target)
    };
  }

  function findTouch(list, identifier) {
    if (!list) return null;
    for (let index = 0; index < list.length; index += 1) {
      if (list[index].identifier === identifier) return list[index];
    }
    return null;
  }

  function evaluateGesture(currentGesture, endPoint, duration) {
    const horizontalDelta = endPoint.x - currentGesture.startX;
    const verticalDelta = endPoint.y - currentGesture.startY;
    const horizontalDistance = Math.abs(horizontalDelta);
    const verticalDistance = Math.abs(verticalDelta);
    const direction = horizontalDistance < 1 ? 'sin dirección' : (horizontalDelta < 0 ? 'izquierda' : 'derecha');
    updateDiagnostics({
      source: currentGesture.source, effectiveEnd: pointLabel(endPoint),
      deltaX: Math.round(horizontalDelta), deltaY: Math.round(verticalDelta),
      duration: Math.round(duration) + ' ms', direction: direction
    });
    if (currentGesture.scrollContainers.length > 0) {
      updateDiagnostics({
        exclusionRule: 'contenedor con desplazamiento horizontal'
      });
      return reportResult('rechazado', 'contenedor con scroll horizontal', null);
    }
    if (duration > 700) return reportResult('rechazado', 'duración excesiva', null);
    if (horizontalDistance < verticalDistance * 1.5) return reportResult('rechazado', 'movimiento vertical o diagonal', null);
    if (horizontalDistance < 80) return reportResult('rechazado', 'distancia insuficiente', null);
    const destination = horizontalDelta < 0 ? body.dataset.swipeNextUrl : body.dataset.swipePreviousUrl;
    if (!destination) return reportResult('rechazado', 'sin URL anterior/siguiente', null);
    reportResult('aceptado', null, destination);
    removeHint();
    if (diagnosticsEnabled) return;
    navigationStarted = true;
    body.classList.add('is-swipe-navigating');
    window.setTimeout(function () { window.location.assign(destination); }, reducedMotion.matches ? 0 : 90);
  }

  function resetDiagnosticGesture(source, point) {
    updateDiagnostics({
      source: source, start: pointLabel(point), pointerLast: null, touchLast: null,
      pointerMoves: 0, touchMoves: 0, pointerContinued: 'no', eventType: null,
      eventEnd: null, effectiveEnd: null, deltaX: null, deltaY: null,
      duration: null, direction: null, exclusionRule: null
    });
    reportResult('evaluando', null, null);
  }

  gestureDetector.addEventListener('pointerdown', function (event) {
    if (isDiagnosticsPanelTarget(event.target)) return;
    const point = eventPoint(event);
    updateDiagnostics({ pointerdown: 'sí', pointerType: event.pointerType, pointerId: event.pointerId, exclusionRule: null });
    reportTouchActions(event.target);
    if (navigationStarted || body.classList.contains('site-menu-open')) return reportResult('rechazado', 'navegación bloqueada', null);
    if (!mobileViewport.matches) return reportResult('rechazado', 'viewport demasiado ancho', null);
    if (event.pointerType !== 'touch' || !event.isPrimary) return reportResult('rechazado', 'no es touch', null);
    if (point === null) return reportResult('rechazado', 'coordenadas iniciales inválidas', null);
    const exclusionRule = matchingExclusionRule(event.target);
    if (exclusionRule !== null) {
      updateDiagnostics({ exclusionRule: exclusionRule });
      return reportResult('rechazado', 'gesto iniciado en elemento excluido', null);
    }
    pointerGesture = newGesture(event.pointerId, point, event.target, 'Pointer Events');
    resetDiagnosticGesture('Pointer Events', point);
  }, { passive: true });

  gestureDetector.addEventListener('pointermove', function (event) {
    if (!pointerGesture || event.pointerId !== pointerGesture.identifier) return;
    const point = eventPoint(event);
    if (point === null) return;
    pointerGesture.lastX = point.x;
    pointerGesture.lastY = point.y;
    pointerGesture.moveCount += 1;
    updateDiagnostics({ pointerLast: pointLabel(point), pointerMoves: pointerGesture.moveCount });
  }, { passive: true });

  gestureDetector.addEventListener('pointercancel', function (event) {
    if (!pointerGesture || event.pointerId !== pointerGesture.identifier) return;
    const currentGesture = pointerGesture;
    pointerGesture = null;
    const cancelPoint = eventPoint(event);
    updateDiagnostics({
      eventType: 'pointercancel',
      eventEnd: (cancelPoint ? pointLabel(cancelPoint) : 'ausentes') + ' — coordenadas de cancelación ignoradas',
      pointerContinued: touchGesture ? 'sí; Touch Events continuó' : 'no'
    });
    if (touchGesture) return;
    if (currentGesture.moveCount === 0) return reportResult('rechazado', 'pointercancel', null);
    evaluateGesture(currentGesture, { x: currentGesture.lastX, y: currentGesture.lastY }, performance.now() - currentGesture.startedAt);
  }, { passive: true });

  gestureDetector.addEventListener('pointerup', function (event) {
    if (!pointerGesture || event.pointerId !== pointerGesture.identifier || touchGesture) return;
    const currentGesture = pointerGesture;
    pointerGesture = null;
    const upPoint = eventPoint(event);
    const effectivePoint = upPoint || { x: currentGesture.lastX, y: currentGesture.lastY };
    updateDiagnostics({ eventType: 'pointerup', eventEnd: upPoint ? pointLabel(upPoint) : 'coordenadas inválidas' });
    evaluateGesture(currentGesture, effectivePoint, performance.now() - currentGesture.startedAt);
  }, { passive: true });

  gestureDetector.addEventListener('touchstart', function (event) {
    if (isDiagnosticsPanelTarget(event.target)) return;
    if (navigationStarted || body.classList.contains('site-menu-open') || touchGesture || !mobileViewport.matches) return;
    updateDiagnostics({ exclusionRule: null });
    const exclusionRule = matchingExclusionRule(event.target);
    if (exclusionRule !== null) {
      updateDiagnostics({ source: 'Touch Events', exclusionRule: exclusionRule });
      return reportResult('rechazado', 'gesto iniciado en elemento excluido', null);
    }
    const touch = event.changedTouches && event.changedTouches.length ? event.changedTouches[0] : null;
    const point = eventPoint(touch);
    if (!touch || point === null) return;
    touchGesture = newGesture(touch.identifier, point, event.target, 'Touch Events');
    pointerGesture = pointerGesture ? Object.assign(pointerGesture, { suppressedByTouch: true }) : null;
    resetDiagnosticGesture('Touch Events', point);
    reportTouchActions(event.target);
  }, { passive: true });

  gestureDetector.addEventListener('touchmove', function (event) {
    if (!touchGesture) return;
    const touch = findTouch(event.touches, touchGesture.identifier);
    const point = eventPoint(touch);
    if (point === null) return;
    touchGesture.lastX = point.x;
    touchGesture.lastY = point.y;
    touchGesture.moveCount += 1;
    updateDiagnostics({ touchLast: pointLabel(point), touchMoves: touchGesture.moveCount });
  }, { passive: true });

  gestureDetector.addEventListener('touchend', function (event) {
    if (!touchGesture) return;
    const endedTouch = findTouch(event.changedTouches, touchGesture.identifier);
    if (!endedTouch && findTouch(event.touches, touchGesture.identifier)) return;
    const currentGesture = touchGesture;
    touchGesture = null;
    pointerGesture = null;
    const endPoint = eventPoint(endedTouch);
    const effectivePoint = endPoint || { x: currentGesture.lastX, y: currentGesture.lastY };
    updateDiagnostics({
      eventType: 'touchend',
      eventEnd: endPoint ? pointLabel(endPoint) : 'changedTouches sin coordenadas válidas'
    });
    evaluateGesture(currentGesture, effectivePoint, performance.now() - currentGesture.startedAt);
  }, { passive: true });

  gestureDetector.addEventListener('touchcancel', function () {
    if (!touchGesture) return;
    const currentGesture = touchGesture;
    touchGesture = null;
    pointerGesture = null;
    updateDiagnostics({ eventType: 'touchcancel', eventEnd: 'sin coordenadas finales' });
    if (currentGesture.moveCount === 0) return reportResult('rechazado', 'touchcancel', null);
    evaluateGesture(currentGesture, { x: currentGesture.lastX, y: currentGesture.lastY }, performance.now() - currentGesture.startedAt);
  }, { passive: true });

  function initialize() {
    createDiagnosticsPanel();
    showHintOnce();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
  else initialize();
}());
