document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.astronomy-sources-form');
  if (!form) return;

  const status = form.querySelector('[data-astronomy-source-status]');
  const selects = Array.from(form.querySelectorAll('[data-astronomy-source-select]'));

  form.querySelectorAll('[data-astronomy-source-all]').forEach((button) => {
    button.addEventListener('click', () => {
      const requestedSource = button.dataset.astronomySourceAll;
      let changed = 0;
      let unsupported = 0;

      selects.forEach((select) => {
        const option = Array.from(select.options).find((item) => item.value === requestedSource);
        if (!option) {
          unsupported += 1;
          return;
        }
        if (select.value !== requestedSource) changed += 1;
        select.value = requestedSource;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      });

      if (status) {
        const label = requestedSource === 'api' ? 'API' : 'PHP';
        status.textContent = `${changed} selector${changed === 1 ? '' : 'es'} preparado${changed === 1 ? '' : 's'} para ${label}. `
          + (unsupported > 0 ? `${unsupported} selector${unsupported === 1 ? '' : 'es'} sin ${label}, como moon/image para PHP, conserva${unsupported === 1 ? '' : 'n'} su fuente actual. ` : '')
          + 'Guardá para aplicar los cambios.';
      }
    });
  });
});
