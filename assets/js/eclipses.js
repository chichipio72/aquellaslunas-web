document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('eclipses-query-form');
  const loading = document.getElementById('eclipses-loading');
  const results = document.getElementById('eclipses-results');
  const resultsOverlay = document.getElementById('eclipses-results-overlay');
  const submitButton = form?.querySelector('button[type="submit"]');
  const modal = document.getElementById('eclipses-modal');
  const modalContent = document.getElementById('eclipses-modal-content');
  const closeButton = modal?.querySelector('[data-eclipse-modal-close]');
  let submitting = false;
  let opener = null;

  const setLoadingState = (isLoading) => {
    if (results instanceof HTMLElement) {
      results.classList.toggle('is-loading', isLoading);
      results.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }
    if (resultsOverlay instanceof HTMLElement) {
      resultsOverlay.hidden = !isLoading;
    }
    if (loading instanceof HTMLElement) {
      loading.hidden = !isLoading;
      loading.setAttribute('role', 'status');
      loading.setAttribute('aria-live', 'polite');
      loading.textContent = 'Buscando eclipses… La consulta puede tardar unos segundos.';
    }
    if (submitButton instanceof HTMLButtonElement) {
      submitButton.disabled = isLoading;
      submitButton.setAttribute('aria-disabled', isLoading ? 'true' : 'false');
      submitButton.textContent = isLoading ? 'Consultando…' : 'Buscar eclipses';
    }
  };

  if (form instanceof HTMLFormElement && loading instanceof HTMLElement) {
    form.addEventListener('submit', (event) => {
      if (!form.checkValidity()) {
        return;
      }
      if (submitting) {
        event.preventDefault();
        return;
      }
      submitting = true;
      setLoadingState(true);
    });

    // Permite que futuros atajos de fecha (botones o enlaces) usen el mismo estado de carga.
    document.addEventListener('click', (event) => {
      if (!(event.target instanceof Element)) {
        return;
      }
      const quickTrigger = event.target.closest('[data-eclipses-quick-range], [data-eclipses-quick-submit]');
      if (!quickTrigger || submitting) {
        return;
      }
      if (
        quickTrigger instanceof HTMLButtonElement
        && quickTrigger.form === form
        && (quickTrigger.type === 'submit' || quickTrigger.type === '')
      ) {
        return;
      }
      setLoadingState(true);
    });

    window.addEventListener('pageshow', () => {
      submitting = false;
      setLoadingState(false);
    });
  }

  const closeModal = () => {
    if (modal instanceof HTMLDialogElement && modal.open) {
      modal.close();
    }
  };

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
      return;
    }

    const trigger = event.target.closest('[data-eclipse-modal-open]');
    if (!(trigger instanceof HTMLButtonElement)) {
      return;
    }
    if (!(modal instanceof HTMLDialogElement) || !(modalContent instanceof HTMLElement)) {
      return;
    }

    const templateId = trigger.dataset.templateId;
    if (!templateId) {
      return;
    }
    const template = document.getElementById(templateId);
    if (!(template instanceof HTMLTemplateElement)) {
      return;
    }

    opener = trigger;
    modalContent.replaceChildren(template.content.cloneNode(true));
    modal.showModal();

    const heading = modalContent.querySelector('h2');
    if (heading && heading.id === '') {
      heading.id = 'eclipses-modal-title';
    }
  });

  if (modal instanceof HTMLDialogElement) {
    closeButton?.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        closeModal();
      }
    });

    modal.addEventListener('close', () => {
      if (modalContent instanceof HTMLElement) {
        modalContent.replaceChildren();
      }
      if (opener instanceof HTMLElement) {
        opener.focus();
      }
      opener = null;
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && modal.open) {
        closeModal();
      }
    });
  }

  setLoadingState(false);
});
