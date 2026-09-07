(() => {
  'use strict';
  const sceneSelector = document.querySelector('[data-scene-selector]');
  sceneSelector?.addEventListener('change', () => sceneSelector.form?.requestSubmit());
  const importForm = document.querySelector('[data-photography-import]');
  const importButton = importForm?.querySelector('[data-package-import-button]');
  const importModes = [...(importForm?.querySelectorAll('[name="package_mode"]') || [])];
  const replaceConfirmation = importForm?.querySelector('[data-replace-confirmation]');
  const replaceCheckbox = importForm?.querySelector('[data-confirm-replace]');
  const invalidateImport = () => { if (importButton) importButton.disabled = true; };
  importForm?.querySelector('[data-package-json]')?.addEventListener('input', invalidateImport);
  importForm?.querySelector('[data-package-file]')?.addEventListener('change', invalidateImport);
  const syncImportMode = () => {
    const replace = importModes.some((input) => input.checked && input.value === 'replace');
    if (replaceConfirmation) replaceConfirmation.hidden = !replace;
    if (replaceCheckbox) replaceCheckbox.required = replace;
  };
  importModes.forEach((input) => input.addEventListener('change', () => { invalidateImport(); syncImportMode(); }));
  importForm?.addEventListener('submit', (event) => {
    if (event.submitter?.value !== 'import' || !importModes.some((input) => input.checked && input.value === 'replace')) return;
    if (!window.confirm('Esto eliminará todas las escenas y variantes actuales de Fotografía y las reemplazará por el contenido del JSON.')) event.preventDefault();
  });
  syncImportMode();
  const simulationPreview = document.querySelector('[data-simulation-preview]');
  const previewFocal = document.querySelector('[data-preview-focal]');
  const previewFocalNumber = document.querySelector('[data-preview-focal-number]');
  const moonProfileSelector = document.querySelector('[data-moon-profile-selector]');
  const moonProfiles = [...document.querySelectorAll('[data-moon-profile]')];
  const simulationForm = document.querySelector('form input[name="mode"][value="photography_simulation"]')?.form;
  const updateSimulationPreview = () => {
    if (!simulationPreview?.contentWindow || !simulationForm) return;
    const simulation = {};
    new FormData(simulationForm).forEach((value, key) => {
      const match = /^simulation\[([^\]]+)\]$/.exec(key);
      if (match) simulation[match[1]] = value;
    });
    simulationPreview.contentWindow.postMessage({ type: 'photography-simulation-preview', simulation }, window.location.origin);
  };
  const updatePreviewFocal = (value) => {
    const focal = Math.max(200, Math.min(3000, Math.round(Number(value) / 10) * 10));
    if (!Number.isFinite(focal)) return;
    if (previewFocal) previewFocal.value = String(focal);
    if (previewFocalNumber) previewFocalNumber.value = String(focal);
    simulationPreview?.contentWindow?.postMessage({ type: 'photography-preview-camera', focal }, window.location.origin);
  };
  previewFocal?.addEventListener('input', () => updatePreviewFocal(previewFocal.value));
  previewFocalNumber?.addEventListener('change', () => updatePreviewFocal(previewFocalNumber.value));
  let simulationPreviewFrame = 0;
  const scheduleSimulationPreview = () => {
    window.cancelAnimationFrame(simulationPreviewFrame);
    simulationPreviewFrame = window.requestAnimationFrame(updateSimulationPreview);
  };
  const showMoonProfile = (event) => {
    const selected = moonProfileSelector?.value;
    moonProfiles.forEach((profile) => { profile.hidden = profile.dataset.moonProfile !== selected; });
    const previewUrl = moonProfileSelector?.selectedOptions[0]?.dataset.previewUrl;
    if (event && previewUrl && simulationPreview) simulationPreview.src = previewUrl;
  };
  moonProfileSelector?.addEventListener('change', showMoonProfile);
  simulationForm?.addEventListener('input', scheduleSimulationPreview);
  simulationForm?.addEventListener('change', scheduleSimulationPreview);
  showMoonProfile();
  let simulationPreviewObserver = null;
  const resizeSimulationPreview = () => {
    if (!simulationPreview?.contentDocument) return;
    const documentElement = simulationPreview.contentDocument.documentElement;
    const body = simulationPreview.contentDocument.body;
    const height = Math.max(documentElement?.scrollHeight || 0, body?.scrollHeight || 0);
    if (height > 0) simulationPreview.style.height = `${height + 2}px`;
  };
  simulationPreview?.addEventListener('load', () => {
    simulationPreviewObserver?.disconnect();
    resizeSimulationPreview();
    updateSimulationPreview();
    updatePreviewFocal(previewFocal?.value || 1200);
    window.setTimeout(resizeSimulationPreview, 250);
    window.setTimeout(resizeSimulationPreview, 1000);
    if ('ResizeObserver' in window && simulationPreview.contentDocument?.documentElement) {
      simulationPreviewObserver = new ResizeObserver(resizeSimulationPreview);
      simulationPreviewObserver.observe(simulationPreview.contentDocument.documentElement);
      if (simulationPreview.contentDocument.body) simulationPreviewObserver.observe(simulationPreview.contentDocument.body);
    }
  });
  const dialog = document.querySelector('[data-image-dialog]');
  if (!dialog) return;
  document.addEventListener('click', (event) => {
    const chooseButton = event.target.closest('[data-image-choose]');
    if (chooseButton) {
      const selector = chooseButton.closest('[data-image-selector]');
      const selected = selector.querySelector('[data-image-value]').value;
      dialog.imageTarget = selector;
      dialog.querySelectorAll('[data-image-option]').forEach((option) => {
        const active = option.dataset.imageOption === selected;
        option.setAttribute('aria-pressed', String(active));
        option.querySelector('[data-image-selected-label]').hidden = !active;
      });
      dialog.showModal();
      (dialog.querySelector('[data-image-option][aria-pressed="true"]') || dialog.querySelector('[data-image-option]') || dialog.querySelector('[data-image-close]'))?.focus();
      return;
    }
    if (event.target.closest('[data-image-close]')) { dialog.close(); return; }
    const imageOption = event.target.closest('[data-image-option]');
    if (imageOption && dialog.imageTarget) {
      const selector = dialog.imageTarget;
      const preview = selector.querySelector('[data-image-preview]');
      selector.querySelector('[data-image-value]').value = imageOption.dataset.imageOption;
      selector.querySelector('[data-image-name]').textContent = imageOption.dataset.imageName;
      selector.querySelector('[data-image-current]').hidden = false;
      selector.querySelector('[data-image-remove]').disabled = false;
      selector.querySelector('[data-image-choose]').textContent = 'Cambiar imagen';
      preview.src = imageOption.dataset.imageUrl;
      preview.hidden = false;
      const camera = selector.querySelector('[data-image-camera]');
      if (camera) {
        camera.querySelector('span').textContent = imageOption.dataset.exifCamera || '';
        camera.hidden = !imageOption.dataset.exifCamera;
      }
      const variantForm = selector.closest('.photography-admin-variant');
      if (variantForm) {
        const exifFields = { reference_focal_mm: imageOption.dataset.exifFocal || '', aperture: imageOption.dataset.exifAperture || '', shutter_speed: imageOption.dataset.exifShutter || '', iso_value: imageOption.dataset.exifIso || '' };
        Object.entries(exifFields).forEach(([name, value]) => {
          const input = variantForm.querySelector(`[name="${name}"]`);
          if (input) input.value = value;
        });
      }
      dialog.close();
      return;
    }
    const removeButton = event.target.closest('[data-image-remove]');
    if (removeButton) {
      const selector = removeButton.closest('[data-image-selector]');
      const preview = selector.querySelector('[data-image-preview]');
      selector.querySelector('[data-image-value]').value = '';
      selector.querySelector('[data-image-name]').textContent = '';
      const camera = selector.querySelector('[data-image-camera]');
      if (camera) { camera.hidden = true; camera.querySelector('span').textContent = ''; }
      selector.querySelector('[data-image-current]').hidden = true;
      selector.querySelector('[data-image-choose]').textContent = 'Elegir imagen';
      removeButton.disabled = true;
      preview.hidden = true;
      preview.removeAttribute('src');
    }
  });
})();
