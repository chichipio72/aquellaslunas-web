document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('events-query-form');
  const loading = document.getElementById('events-loading');

  if (!form || !loading) {
    return;
  }

  form.addEventListener('submit', (event) => {
    const selectedTypes = form.querySelectorAll('input[name="types[]"]:checked');
    if (selectedTypes.length === 0) {
      event.preventDefault();
      loading.hidden = false;
      loading.textContent = 'Seleccioná al menos un tipo de evento.';
      loading.setAttribute('role', 'alert');
      return;
    }

    const submit = form.querySelector('button[type="submit"]');
    loading.hidden = false;
    loading.textContent = 'Cargando efemérides…';
    loading.setAttribute('role', 'status');
    if (submit) {
      submit.disabled = true;
      submit.textContent = 'Consultando…';
    }
  });
});
