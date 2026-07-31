(() => {
  function close(scheduler, restoreFocus = false) {
    const button = scheduler?.querySelector('[data-calendar-menu-trigger]');
    const menu = scheduler?.querySelector('[data-calendar-menu]');
    if (!button || !menu || menu.hidden) return;
    menu.hidden = true;
    button.setAttribute('aria-expanded', 'false');
    if (restoreFocus) button.focus();
  }

  function openSchedulers() {
    return Array.from(document.querySelectorAll('[data-calendar-scheduler]'))
      .filter((scheduler) => !scheduler.querySelector('[data-calendar-menu]')?.hidden);
  }

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    const trigger = event.target.closest('[data-calendar-menu-trigger]');
    if (trigger) {
      const scheduler = trigger.closest('[data-calendar-scheduler]');
      const menu = scheduler?.querySelector('[data-calendar-menu]');
      if (!scheduler || !menu) return;
      const willOpen = menu.hidden;
      openSchedulers().forEach((item) => {
        if (item !== scheduler) close(item);
      });
      menu.hidden = !willOpen;
      trigger.setAttribute('aria-expanded', String(willOpen));
      if (willOpen) menu.querySelector('a')?.focus();
      return;
    }
    if (event.target.closest('[data-calendar-menu] a')) {
      close(event.target.closest('[data-calendar-scheduler]'));
      return;
    }
    openSchedulers().forEach((scheduler) => {
      if (!scheduler.contains(event.target)) close(scheduler);
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const [open] = openSchedulers();
    if (open) close(open, true);
  });
})();
