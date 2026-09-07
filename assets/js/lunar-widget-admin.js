document.querySelectorAll('[data-lunar-widget-builder]').forEach((builder) => {
  const kindControl = builder.querySelector('[data-widget-kind]');
  const preview = builder.querySelector('[data-widget-preview]');
  const urlOutput = builder.querySelector('[data-widget-url]');
  const iframeOutput = builder.querySelector('[data-widget-iframe]');
  const copyStatus = builder.querySelector('[data-widget-copy-status]');
  const previewFrame = builder.querySelector('[data-widget-preview-frame]');
  const videoActions = builder.querySelector('[data-libration-video-actions]');
  const videoStatus = builder.querySelector('[data-video-export-status]');
  const videoButtons = [...builder.querySelectorAll('[data-export-video]')];
  const presetsPanel = builder.querySelector('[data-lunar-scene-presets]');
  const presetSelect = builder.querySelector('[data-preset-select]');
  const presetName = builder.querySelector('[data-preset-name]');
  const presetDescription = builder.querySelector('[data-preset-description]');
  const presetStatus = builder.querySelector('[data-preset-status]');
  const presetButtons = [...builder.querySelectorAll('[data-preset-action]')];
  const csrfToken = String(builder.dataset.csrfToken || '');
  const publicBase = String(builder.dataset.publicBase || '').replace(/\/$/, '');
  if (!kindControl || !preview || !urlOutput || !iframeOutput) return;

  const kinds = {
    libration: { filename: 'libracion-lunar.php', title: 'Libración lunar' },
    'lunar-scene': { filename: 'escena-lunar.php', title: 'Escena lunar configurable' },
    interactive: { filename: 'luna-interactiva.php', title: 'Luna interactiva' },
    'earth-moon': { filename: 'fases-tierra-luna.php', title: 'Fases Tierra–Luna' },
    'full-moon-sizes': { filename: 'lunas-llenas-tamano.php', title: 'Tamaño de próximas lunas llenas' },
    eclipse: { filename: 'eclipse-lunar.php', title: 'Eclipse lunar' },
    'real-lunar-eclipse': { filename: 'eclipse-lunar-real.php', title: 'Eclipse lunar real' },
    'real-solar-eclipse': { filename: 'eclipse-solar-real.php', title: 'Eclipse solar real' },
    'solar-space-eclipse': { filename: 'eclipse-solar-espacio.php', title: 'Eclipse solar desde el espacio' },
  };
  const importedParameters = {};

  const storageKey = 'aquellas-lunas:lunar-widget-builder:v1';
  const restoreState = () => {
    try {
      const state = JSON.parse(localStorage.getItem(storageKey) || 'null');
      if (!state || typeof state !== 'object') return;
      if (typeof state.kind === 'string' && kindControl.querySelector(`option[value="${CSS.escape(state.kind)}"]`)) kindControl.value = state.kind;
      builder.querySelectorAll('[data-widget-options]').forEach((group) => {
        const values = state.options?.[group.dataset.widgetOptions];
        if (!values || typeof values !== 'object') return;
        group.querySelectorAll('[data-option]').forEach((control) => {
          const value = values[control.dataset.option];
          if (typeof value !== 'string') return;
          if (control instanceof HTMLSelectElement && ![...control.options].some((option) => option.value === value)) return;
          control.value = value;
        });
      });
    } catch (error) {
      // El generador sigue funcionando con sus defaults si el storage está bloqueado o dañado.
    }
  };
  const persistState = () => {
    const options = {};
    builder.querySelectorAll('[data-widget-options]').forEach((group) => {
      options[group.dataset.widgetOptions] = {};
      group.querySelectorAll('[data-option]').forEach((control) => { options[group.dataset.widgetOptions][control.dataset.option] = control.value; });
    });
    try { localStorage.setItem(storageKey, JSON.stringify({ kind: kindControl.value, options })); } catch (error) {
      // La persistencia local es una comodidad; nunca debe bloquear preview o generación.
    }
  };
  restoreState();
  let pendingExport = null;
  let previousKind = kindControl.value;
  const zoomControl = builder.querySelector('[data-libration-zoom]');
  const zoomOutput = builder.querySelector('[data-libration-zoom-value]');
  const updateZoomOutput = () => {
    if (zoomControl && zoomOutput) zoomOutput.textContent = `${Math.round(Number(zoomControl.value) * 100)} %`;
  };

  const activeOptions = () => builder.querySelector(`[data-widget-options="${kindControl.value}"]`);
  const build = () => {
    let previewProgress = null;
    if (kindControl.value === previousKind && ['real-lunar-eclipse', 'real-solar-eclipse', 'solar-space-eclipse'].includes(kindControl.value)) {
      try {
        const previewRoot = preview.contentDocument?.querySelector('[data-real-lunar-eclipse], [data-real-solar-eclipse], [data-solar-space-eclipse]');
        const progressControl = preview.contentDocument?.querySelector('[data-eclipse-progress]');
        if (previewRoot?.dataset.eclipseProgress) previewProgress = Number(previewRoot.dataset.eclipseProgress);
        else if (progressControl) previewProgress = Number(progressControl.value) / 1000;
      } catch (error) {
        previewProgress = null;
      }
    }
    builder.querySelectorAll('[data-widget-options]').forEach((group) => { group.hidden = group !== activeOptions(); });
    const kind = Object.hasOwn(kinds, kindControl.value) ? kindControl.value : 'libration';
    if (presetsPanel) presetsPanel.hidden = kind !== 'lunar-scene';
    if (videoActions) videoActions.hidden = kind !== 'libration';
    if (previewFrame) previewFrame.dataset.aspect = kind === 'libration' ? (previewFrame.dataset.aspect || '16:9') : 'default';
    const { filename, title } = kinds[kind];
    const parameters = new URLSearchParams(importedParameters[kind] || '');
    activeOptions()?.querySelectorAll('[data-option]').forEach((control) => parameters.set(control.dataset.option, control.value));
    const previewParameters = new URLSearchParams(parameters);
    if (Number.isFinite(previewProgress)) previewParameters.set('_preview_progress', String(previewProgress));
    const relativeUrl = `../../embeds/${filename}?${previewParameters}`;
    const publicUrl = `${publicBase}/embeds/${filename}?${parameters}`;
    const iframe = `<iframe src="${publicUrl}" title="${title}" loading="lazy" width="100%" height="560" style="border:0" allowfullscreen></iframe>`;
    preview.src = relativeUrl;
    urlOutput.value = publicUrl;
    iframeOutput.value = iframe;
    previousKind = kind;
  };
  let timer = null;
  const scheduleBuild = () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(build, 180);
  };
  const importUrl = () => {
    let parsed;
    try { parsed = new URL(urlOutput.value.trim(), window.location.href); } catch (error) {
      if (copyStatus) copyStatus.textContent = 'La URL pegada no es válida.';
      return;
    }
    const match = Object.entries(kinds).find(([, definition]) => parsed.pathname.endsWith(`/embeds/${definition.filename}`));
    if (!match) {
      if (copyStatus) copyStatus.textContent = 'La URL no corresponde a uno de los widgets disponibles.';
      return;
    }
    const [kind] = match;
    kindControl.value = kind;
    const group = builder.querySelector(`[data-widget-options="${kind}"]`);
    const extras = new URLSearchParams(parsed.searchParams);
    group?.querySelectorAll('[data-option]').forEach((control) => {
      const key = control.dataset.option;
      if (!key || !parsed.searchParams.has(key)) return;
      const value = parsed.searchParams.get(key);
      if (control instanceof HTMLSelectElement && ![...control.options].some((option) => option.value === value)) return;
      control.value = value;
      extras.delete(key);
    });
    extras.delete('_preview_progress');
    importedParameters[kind] = extras.toString();
    updateZoomOutput();
    persistState();
    build();
    if (copyStatus) copyStatus.textContent = `Configuración cargada desde la URL de ${kinds[kind].title}.`;
  };
  let importTimer = null;
  urlOutput.addEventListener('input', () => {
    window.clearTimeout(importTimer);
    importTimer = window.setTimeout(importUrl, 350);
  });
  urlOutput.addEventListener('change', importUrl);
  builder.addEventListener('input', (event) => { if (event.target === urlOutput) return; persistState(); scheduleBuild(); });
  builder.addEventListener('change', (event) => { if (event.target === urlOutput) return; persistState(); build(); });
  zoomControl?.addEventListener('input', updateZoomOutput);
  builder.querySelectorAll('[data-copy-target]').forEach((button) => button.addEventListener('click', async () => {
    const value = button.dataset.copyTarget === 'iframe' ? iframeOutput.value : urlOutput.value;
    try {
      await navigator.clipboard.writeText(value);
      if (copyStatus) copyStatus.textContent = button.dataset.copyTarget === 'iframe' ? 'Iframe copiado.' : 'URL copiada.';
    } catch (error) {
      const target = button.dataset.copyTarget === 'iframe' ? iframeOutput : urlOutput;
      target.focus();
      target.select();
      if (copyStatus) copyStatus.textContent = 'Seleccionamos el texto para que puedas copiarlo.';
    }
  }));
  let presets = [];
  const selectedPreset = () => presets.find((preset) => String(preset.id) === presetSelect?.value) || null;
  const updatePresetButtons = () => {
    const selected = selectedPreset();
    presetButtons.forEach((button) => {
      if (button.dataset.presetAction !== 'create') button.disabled = !selected;
    });
  };
  const renderPresets = (selectedId = '') => {
    if (!presetSelect) return;
    presetSelect.replaceChildren(new Option('Seleccionar preset…', ''));
    presets.forEach((preset) => presetSelect.add(new Option(preset.name, String(preset.id))));
    if (selectedId && presets.some((preset) => String(preset.id) === String(selectedId))) presetSelect.value = String(selectedId);
    updatePresetButtons();
  };
  const sceneConfiguration = () => {
    const configuration = {};
    builder.querySelectorAll('[data-widget-options="lunar-scene"] [data-option]').forEach((control) => { configuration[control.dataset.option] = control.value; });
    return configuration;
  };
  const applyPreset = (preset) => {
    const group = builder.querySelector('[data-widget-options="lunar-scene"]');
    group?.querySelectorAll('[data-option]').forEach((control) => {
      const value = preset.configuration?.[control.dataset.option];
      if (value === undefined) return;
      if (control instanceof HTMLSelectElement && ![...control.options].some((option) => option.value === String(value))) return;
      control.value = String(value);
    });
    if (presetName) presetName.value = preset.name || '';
    if (presetDescription) presetDescription.value = preset.description || '';
    persistState();
    build();
  };
  const loadPresets = async (selectedId = '') => {
    if (!presetsPanel) return;
    try {
      const response = await fetch('../api/lunar-scene-presets.php', { headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'No se pudieron cargar los presets.');
      presets = Array.isArray(result.presets) ? result.presets : [];
      renderPresets(selectedId);
    } catch (error) {
      if (presetStatus) presetStatus.textContent = error instanceof Error ? error.message : 'No se pudieron cargar los presets.';
    }
  };
  presetSelect?.addEventListener('change', () => {
    const preset = selectedPreset();
    updatePresetButtons();
    if (preset) {
      applyPreset(preset);
      if (presetStatus) presetStatus.textContent = `Preset “${preset.name}” cargado.`;
    }
  });
  presetButtons.forEach((button) => button.addEventListener('click', async () => {
    const action = String(button.dataset.presetAction || '');
    const selected = selectedPreset();
    if (action === 'delete' && selected && !window.confirm(`¿Eliminar el preset “${selected.name}”?`)) return;
    const form = new FormData();
    form.set('csrf_token', csrfToken);
    form.set('action', action);
    if (selected) form.set('id', String(selected.id));
    if (action === 'create' || action === 'update') {
      form.set('name', presetName?.value || '');
      form.set('description', presetDescription?.value || '');
      form.set('configuration', JSON.stringify(sceneConfiguration()));
    }
    presetButtons.forEach((candidate) => { candidate.disabled = true; });
    try {
      const response = await fetch('../api/lunar-scene-presets.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'No se pudo completar la acción.');
      presets = Array.isArray(result.presets) ? result.presets : [];
      const nextId = result.preset?.id || '';
      renderPresets(nextId);
      if (result.preset) applyPreset(result.preset);
      else {
        if (presetName) presetName.value = '';
        if (presetDescription) presetDescription.value = '';
      }
      const messages = { create: 'Preset guardado.', update: 'Preset actualizado.', duplicate: 'Preset duplicado.', delete: 'Preset eliminado.' };
      if (presetStatus) presetStatus.textContent = messages[action] || 'Acción completada.';
    } catch (error) {
      if (presetStatus) presetStatus.textContent = error instanceof Error ? error.message : 'No se pudo completar la acción.';
      updatePresetButtons();
    }
  }));
  videoButtons.forEach((button) => button.addEventListener('click', () => {
    if (kindControl.value !== 'libration') return;
    videoButtons.forEach((candidate) => { candidate.disabled = true; });
    pendingExport = button.dataset.exportVideo === '9:16' ? '9:16' : '16:9';
    if (previewFrame) previewFrame.dataset.aspect = pendingExport;
    if (videoStatus) videoStatus.textContent = 'Preparando la vista previa…';
    build();
  }));
  window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin || event.source !== preview.contentWindow) return;
    if (event.data?.type === 'lunar-libration-ready' && pendingExport) {
      preview.contentWindow?.postMessage({ type: 'lunar-libration-export', aspect: pendingExport }, window.location.origin);
    }
    if (event.data?.type === 'lunar-libration-export-started') {
      pendingExport = null;
      if (videoStatus) videoStatus.textContent = 'Grabando un ciclo completo en tiempo real…';
    }
    if (event.data?.type === 'lunar-libration-export-complete' && event.data.blob instanceof Blob) {
      const objectUrl = URL.createObjectURL(event.data.blob);
      const link = document.createElement('a');
      link.href = objectUrl;
      link.download = `libracion-lunar-${event.data.aspect === '9:16' ? 'vertical' : 'horizontal'}.webm`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 30000);
      videoButtons.forEach((button) => { button.disabled = false; });
      if (videoStatus) videoStatus.textContent = 'Video WebM generado y descargado.';
    }
    if (event.data?.type === 'lunar-libration-export-error') {
      pendingExport = null;
      videoButtons.forEach((button) => { button.disabled = false; });
      if (videoStatus) videoStatus.textContent = String(event.data.message || 'No se pudo exportar el video.');
    }
  });
  updateZoomOutput();
  loadPresets();
  build();
});
