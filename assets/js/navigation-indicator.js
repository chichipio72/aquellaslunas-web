document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const status = document.querySelector('.navigation-status');
  const submitState = new Map();
  const changedControls = new Set();
  let isNavigating = false;
  let statusTimer = null;

  document.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
    submitState.set(control, {
      disabled: control.disabled,
      label: control instanceof HTMLInputElement || control.childElementCount === 0
        ? (control instanceof HTMLInputElement ? control.value : control.textContent)
        : null
    });
  });

  const startNavigation = (submitControl = null) => {
    if (isNavigating) return false;
    isNavigating = true;
    body.classList.add('is-navigating');

    if (submitControl) {
      changedControls.add(submitControl);
      submitControl.disabled = true;
      if (submitControl instanceof HTMLInputElement) {
        submitControl.value = 'Consultando…';
      } else if (submitControl.childElementCount === 0) {
        submitControl.textContent = 'Consultando…';
      }
    }

    statusTimer = window.setTimeout(() => {
      if (!isNavigating || !status) return;
      status.hidden = false;
      body.classList.add('is-navigation-status-visible');
    }, 300);
    return true;
  };

  const resetNavigation = () => {
    isNavigating = false;
    window.clearTimeout(statusTimer);
    statusTimer = null;
    body.classList.remove('is-navigating', 'is-navigation-status-visible');
    if (status) status.hidden = true;

    changedControls.forEach((control) => {
      const state = submitState.get(control);
      if (!state) return;
      control.disabled = state.disabled;
      if (state.label === null) return;
      if (control instanceof HTMLInputElement) {
        control.value = state.label;
      } else {
        control.textContent = state.label;
      }
    });
    changedControls.clear();
  };

  document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.shiftKey || event.altKey || event.metaKey) return;

    const link = event.target.closest?.('a[href]');
    if (!link || link.hasAttribute('download') || (link.target && link.target.toLowerCase() !== '_self')) return;

    const rawHref = link.getAttribute('href')?.trim();
    if (!rawHref || rawHref === '#') return;

    let destination;
    try {
      destination = new URL(link.href, window.location.href);
    } catch (_) {
      return;
    }

    if (destination.origin !== window.location.origin || !['http:', 'https:'].includes(destination.protocol)) return;
    if (destination.pathname === window.location.pathname
      && destination.search === window.location.search
      && destination.hash) return;

    if (!startNavigation()) event.preventDefault();
  });

  document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'dialog') return;
    if (form.target && form.target.toLowerCase() !== '_self') return;

    const submitControl = event.submitter instanceof HTMLElement ? event.submitter : null;
    if (!startNavigation(submitControl)) event.preventDefault();
  });

  window.addEventListener('pageshow', resetNavigation);
});
