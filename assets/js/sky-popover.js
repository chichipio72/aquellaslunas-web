document.addEventListener('DOMContentLoaded', () => {
  const popover = document.createElement('div');
  popover.id = 'sky-popover';
  popover.className = 'sky-popover';
  popover.setAttribute('role', 'dialog');
  popover.setAttribute('aria-modal', 'false');
  popover.setAttribute('aria-label', 'Horarios del cielo diario');
  popover.setAttribute('aria-live', 'polite');
  popover.hidden = true;
  popover.innerHTML = `
    <header class="sky-popover__header">
      <h3 class="sky-popover__title"></h3>
    </header>
    <div class="sky-popover__data">
      <section class="sky-popover__section sky-popover__section--sun">
        <h4><span class="astro-symbol astro-symbol--sun" aria-hidden="true">☀</span> Sol</h4>
        <p><span class="astro-arrow astro-arrow--rise" aria-hidden="true">↑</span><span>Salida</span><strong data-field="sun-rise"></strong></p>
        <p><span class="astro-arrow astro-arrow--set" aria-hidden="true">↓</span><span>Puesta</span><strong data-field="sun-set"></strong></p>
        <small data-field="sun-visibility"></small>
      </section>
      <section class="sky-popover__section sky-popover__section--moon">
        <h4><span class="astro-symbol astro-symbol--moon" aria-hidden="true">🌙</span> Luna</h4>
        <p><span class="astro-arrow astro-arrow--rise" aria-hidden="true">↑</span><span>Salida</span><strong data-field="moon-rise"></strong></p>
        <p><span class="astro-arrow astro-arrow--set" aria-hidden="true">↓</span><span>Puesta</span><strong data-field="moon-set"></strong></p>
        <small data-field="moon-visibility"></small>
      </section>
    </div>
    <div class="sky-popover__chart"></div>
  `;
  document.body.appendChild(popover);

  const title = popover.querySelector('.sky-popover__title');
  const fields = Object.fromEntries(
    Array.from(popover.querySelectorAll('[data-field]')).map((element) => [element.dataset.field, element]),
  );
  let activeTrigger = null;
  let closeTimer = null;
  let positionAnimation = null;

  const cancelScheduledClose = () => {
    if (closeTimer !== null) {
      window.clearTimeout(closeTimer);
      closeTimer = null;
    }
  };

  const closePopover = ({ restoreFocus = false } = {}) => {
    cancelScheduledClose();
    if (!activeTrigger) return;
    const previousTrigger = activeTrigger;
    previousTrigger.setAttribute('aria-expanded', 'false');
    activeTrigger = null;
    popover.hidden = true;
    positionAnimation?.cancel();
    positionAnimation = null;
    if (restoreFocus) previousTrigger.focus();
  };

  const positionPopover = () => {
    if (!activeTrigger || popover.hidden) return;
    const margin = 8;
    const gap = 7;
    const triggerRect = activeTrigger.getBoundingClientRect();
    const popoverRect = popover.getBoundingClientRect();
    const fitsBelow = triggerRect.bottom + gap + popoverRect.height <= window.innerHeight - margin;
    const top = fitsBelow
      ? triggerRect.bottom + gap
      : Math.max(margin, triggerRect.top - gap - popoverRect.height);
    const preferredLeft = triggerRect.left + triggerRect.width / 2 - popoverRect.width / 2;
    const left = Math.max(margin, Math.min(preferredLeft, window.innerWidth - popoverRect.width - margin));

    popover.classList.toggle('sky-popover--above', !fitsBelow);
    positionAnimation?.cancel();
    positionAnimation = popover.animate(
      [{ left: `${left}px`, top: `${top}px` }],
      { duration: 0, fill: 'forwards' },
    );
  };

  const populatePopover = (trigger) => {
    const data = trigger.closest('.timeline-cell')?.dataset;
    if (!data) return false;
    title.textContent = data.popoverDate || 'Fecha no disponible';
    fields['sun-rise'].textContent = data.popoverSunRise || 'Sin salida este día';
    fields['sun-set'].textContent = data.popoverSunSet || 'Sin puesta este día';
    fields['sun-visibility'].textContent = data.popoverSunVisibility || 'Sin datos de visibilidad';
    fields['moon-rise'].textContent = data.popoverMoonRise || 'Sin salida este día';
    fields['moon-set'].textContent = data.popoverMoonSet || 'Sin puesta este día';
    fields['moon-visibility'].textContent = data.popoverMoonVisibility || 'Sin datos de visibilidad';
    return true;
  };

  const openPopover = (trigger) => {
    cancelScheduledClose();
    if (!populatePopover(trigger)) return;
    if (activeTrigger && activeTrigger !== trigger) {
      activeTrigger.setAttribute('aria-expanded', 'false');
    }
    activeTrigger = trigger;
    activeTrigger.setAttribute('aria-expanded', 'true');
    popover.hidden = false;
    positionPopover();
  };

  const scheduleClose = () => {
    cancelScheduledClose();
    closeTimer = window.setTimeout(() => closePopover(), 120);
  };

  document.addEventListener('mouseover', (event) => {
    const trigger = event.target.closest?.('.timeline-chart');
    if (trigger && !trigger.contains(event.relatedTarget)) openPopover(trigger);
  });

  document.addEventListener('mouseout', (event) => {
    const trigger = event.target.closest?.('.timeline-chart');
    if (trigger && !trigger.contains(event.relatedTarget) && !popover.contains(event.relatedTarget)) scheduleClose();
  });

  document.addEventListener('focusin', (event) => {
    const trigger = event.target.closest?.('.timeline-chart');
    if (trigger) openPopover(trigger);
  });

  document.addEventListener('focusout', (event) => {
    const trigger = event.target.closest?.('.timeline-chart');
    if (trigger && !popover.contains(event.relatedTarget)) scheduleClose();
  });

  document.addEventListener('keydown', (event) => {
    const trigger = event.target.closest?.('.timeline-chart');
    if (event.key === 'Escape' && activeTrigger) {
      event.preventDefault();
      closePopover({ restoreFocus: true });
    } else if (trigger && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      activeTrigger === trigger ? closePopover() : openPopover(trigger);
    }
  });

  document.addEventListener('pointerup', (event) => {
    if (event.pointerType !== 'touch') return;
    const trigger = event.target.closest?.('.timeline-chart');
    if (trigger) {
      event.preventDefault();
      activeTrigger === trigger ? closePopover() : openPopover(trigger);
    }
  });

  document.addEventListener('pointerdown', (event) => {
    if (activeTrigger && !activeTrigger.contains(event.target) && !popover.contains(event.target)) closePopover();
  });

  popover.addEventListener('mouseenter', cancelScheduledClose);
  popover.addEventListener('mouseleave', scheduleClose);
  window.addEventListener('resize', positionPopover);
  window.addEventListener('scroll', positionPopover, true);
});
