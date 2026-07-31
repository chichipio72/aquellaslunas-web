(() => {
  'use strict';

  const form = document.querySelector('[data-content-editor]');
  if (!form) return;

  window.addEventListener('pageshow', (event) => {
    if (event.persisted) window.location.reload();
  });

  let dirty = false;
  const markDirty = () => { dirty = true; };
  const nextIndex = (container, selector, pattern) => {
    const values = Array.from(container.querySelectorAll(selector))
      .map((input) => input.name.match(pattern)).filter(Boolean).map((match) => Number(match[1]));
    return values.length === 0 ? 0 : Math.max(...values) + 1;
  };
  const uniqueId = (collection, prefix) => {
    const used = new Set(Array.from(collection.querySelectorAll('input[name$="[id]"]')).map((input) => input.value));
    let number = 1;
    while (used.has(`${prefix}-${String(number).padStart(2, '0')}`)) number += 1;
    return `${prefix}-${String(number).padStart(2, '0')}`;
  };
  const imageFieldMarkup = (name) => `
    <div class="editor-image-field" data-image-selector>
      <span class="editor-image-field__label">Imagen opcional</span>
      <input type="hidden" name="${name}" value="" data-image-value>
      <div class="editor-image-current"><img alt="" loading="lazy" data-image-preview hidden><span data-image-empty>Sin imagen</span><code data-image-name></code></div>
      <div class="editor-image-actions">
        <button class="editor-button editor-button--quiet" type="button" data-image-choose>Elegir imagen</button>
        <button class="editor-button editor-button--quiet" type="button" data-image-remove disabled>Quitar imagen</button>
      </div>
    </div>`;
  const optionMarkup = (triviaIndex, optionIndex) => `
    <div class="editor-option" data-option>
      <label class="editor-correct"><input type="radio" name="trivias[${triviaIndex}][correct_option]" value="${optionIndex}"> Correcta</label>
      <label>Opción<input name="trivias[${triviaIndex}][opciones][${optionIndex}][texto]" required></label>
      <button class="editor-button editor-button--quiet" type="button" data-remove-option>Quitar</button>
    </div>`;
  const triviaMarkup = (index, id, slug) => `
    <fieldset id="trivia-editor-${index}" class="editor-block" data-trivia-block data-item-editor="${index}">
      <legend>Editar trivia</legend>
      <div class="editor-grid">
        <label>ID<input name="trivias[${index}][id]" value="${id}" required></label>
        <label class="editor-check"><input type="checkbox" name="trivias[${index}][visible]" checked> Visible</label>
        <label class="editor-wide">Pregunta<textarea name="trivias[${index}][pregunta]" rows="2" required></textarea></label>
        ${imageFieldMarkup(`trivias[${index}][imagen]`)}
        <label>Artículo de referencia<input name="trivias[${index}][referencia_articulo]" value="${slug}" required></label>
        <label>Ancla de referencia<input name="trivias[${index}][referencia_ancla]"></label>
      </div>
      <div class="editor-options" data-options>${optionMarkup(index, 0)}${optionMarkup(index, 1)}</div>
      <button class="editor-button editor-button--quiet" type="button" data-add-option>Agregar opción</button>
      <label class="editor-explanation">Explicación de la respuesta correcta<textarea name="trivias[${index}][explicacion]" rows="3" required></textarea></label>
      <button class="editor-button editor-button--danger" type="button" data-remove-block>Eliminar trivia</button>
    </fieldset>`;
  const factMarkup = (index, id, slug) => `
    <fieldset id="fact-editor-${index}" class="editor-block" data-fact-block data-item-editor="${index}">
      <legend>Editar “Sabías que…”</legend>
      <div class="editor-grid">
        <label>ID<input name="sabias_que[${index}][id]" value="${id}" required></label>
        <label class="editor-check"><input type="checkbox" name="sabias_que[${index}][visible]" checked> Visible</label>
        <label class="editor-wide">Título<textarea name="sabias_que[${index}][titulo]" rows="2" required></textarea></label>
        <label class="editor-wide">Respuesta<textarea name="sabias_que[${index}][respuesta]" rows="3" required></textarea></label>
        ${imageFieldMarkup(`sabias_que[${index}][imagen]`)}
        <label>Artículo de referencia<input name="sabias_que[${index}][referencia_articulo]" value="${slug}" required></label>
        <label>Ancla de referencia<input name="sabias_que[${index}][referencia_ancla]"></label>
      </div>
      <button class="editor-button editor-button--danger" type="button" data-remove-block>Eliminar bloque</button>
    </fieldset>`;

  const activateTab = (tab, focus = false) => {
    document.querySelectorAll('[role="tab"]').forEach((candidate) => {
      const active = candidate === tab;
      candidate.classList.toggle('is-active', active);
      candidate.setAttribute('aria-selected', String(active));
      candidate.tabIndex = active ? 0 : -1;
      document.getElementById(candidate.getAttribute('aria-controls')).hidden = !active;
    });
    const activeTab = form.querySelector('[data-active-tab]');
    if (activeTab) activeTab.value = tab.id.replace('editor-tab-', '');
    if (focus) tab.focus();
  };
  document.querySelector('[role="tablist"]')?.addEventListener('keydown', (event) => {
    const tabs = Array.from(event.currentTarget.querySelectorAll('[role="tab"]'));
    const current = tabs.indexOf(document.activeElement);
    if (current < 0) return;
    let next = null;
    if (event.key === 'ArrowRight') next = (current + 1) % tabs.length;
    if (event.key === 'ArrowLeft') next = (current - 1 + tabs.length) % tabs.length;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = tabs.length - 1;
    if (next !== null) {
      event.preventDefault();
      activateTab(tabs[next], true);
    }
  });
  document.querySelectorAll('[data-item-list]').forEach((list) => {
    list.addEventListener('keydown', (event) => {
      const items = Array.from(list.querySelectorAll('[data-item-select]'));
      const current = items.indexOf(document.activeElement);
      if (current < 0) return;
      let next = null;
      if (event.key === 'ArrowDown' || event.key === 'ArrowRight') next = (current + 1) % items.length;
      if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') next = (current - 1 + items.length) % items.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = items.length - 1;
      if (next !== null) {
        event.preventDefault();
        selectItem(list.closest('[data-collection]'), items[next].dataset.itemSelect, true);
      }
    });
  });

  const selectItem = (collection, index, focus = false) => {
    collection.querySelectorAll('[data-item-select]').forEach((button) => {
      const active = button.dataset.itemSelect === String(index);
      button.classList.toggle('is-selected', active);
      button.setAttribute('aria-pressed', String(active));
      if (active && focus) button.focus();
    });
    collection.querySelectorAll('[data-item-editor]').forEach((editor) => {
      editor.hidden = editor.dataset.itemEditor !== String(index);
    });
  };
  const refreshEmpty = (collection) => {
    const empty = collection.querySelector('[data-empty-state]');
    const hasItems = collection.querySelector('[data-item-select]') !== null;
    empty.hidden = hasItems;
    collection.querySelector('.editor-collection__detail').hidden = !hasItems;
  };
  const addListItem = (collection, index, label, visibility) => {
    const kind = collection.dataset.collection;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'editor-item';
    button.dataset.itemSelect = String(index);
    button.setAttribute('aria-controls', `${kind}-editor-${index}`);
    button.setAttribute('aria-pressed', 'false');
    button.innerHTML = `<strong data-item-label>${label}</strong><span data-item-visibility>${visibility}</span>`;
    collection.querySelector('[data-item-list]').append(button);
  };
  const currentSlug = () => form.elements.original_slug?.value || form.elements.slug?.value || '';
  const updateFocal = (x, y) => {
    const focal = form.querySelector('[data-focal-editor]');
    if (!focal) return;
    const safeX = Math.max(0, Math.min(100, Math.round(x)));
    const safeY = Math.max(0, Math.min(100, Math.round(y)));
    focal.querySelector('[data-focal-x]').value = String(safeX);
    focal.querySelector('[data-focal-y]').value = String(safeY);
    focal.querySelector('[data-focal-surface]').style.setProperty('--focal-x', `${safeX}%`);
    focal.querySelector('[data-focal-surface]').style.setProperty('--focal-y', `${safeY}%`);
    focal.querySelector('[data-focal-value]').textContent = `${safeX}% / ${safeY}%`;
    markDirty();
  };
  const configureFocal = (imageOption) => {
    const focal = form.querySelector('[data-focal-editor]');
    if (!focal) return;
    const width = Number(imageOption.dataset.imageWidth);
    const height = Number(imageOption.dataset.imageHeight);
    const ratio = width > 0 && height > 0 ? width / height : 16 / 9;
    const requiresFocalPoint = width > 0 && height > 0 && (ratio < 1.70 || ratio > 1.85);
    const surface = focal.querySelector('[data-focal-surface]');
    surface.disabled = !requiresFocalPoint;
    surface.classList.toggle('is-disabled', !requiresFocalPoint);
    surface.setAttribute('aria-label', requiresFocalPoint
      ? 'Elegir punto focal de la cabecera 16:9'
      : 'Vista previa de cabecera 16:9 sin recorte significativo');
    focal.querySelector('.editor-focal__marker').hidden = !requiresFocalPoint;
    focal.querySelector('[data-focal-controls]').hidden = !requiresFocalPoint;
    focal.querySelector('[data-focal-status]').textContent = requiresFocalPoint
      ? 'La cabecera 16:9 recorta la imagen. Elegí el motivo principal.'
      : 'La proporción coincide con 16:9; no se necesita ajustar el punto focal.';
    const ratioWarning = form.querySelector('[data-featured-ratio-warning]');
    if (ratioWarning) ratioWarning.hidden = width <= 0 || height <= 0 || (ratio >= 1.70 && ratio <= 1.85);
  };
  const removeEditor = (editor) => {
    const collection = editor.closest('[data-collection]');
    const index = editor.dataset.itemEditor;
    const listButton = collection.querySelector(`[data-item-select="${index}"]`);
    const next = listButton.nextElementSibling || listButton.previousElementSibling;
    editor.remove();
    listButton.remove();
    refreshEmpty(collection);
    if (next) selectItem(collection, next.dataset.itemSelect, true);
    markDirty();
  };

  form.addEventListener('input', (event) => {
    markDirty();
    const editor = event.target.closest('[data-item-editor]');
    if (!editor) return;
    const collection = editor.closest('[data-collection]');
    const button = collection.querySelector(`[data-item-select="${editor.dataset.itemEditor}"]`);
    if (event.target.matches('[name$="[titulo]"], [name$="[pregunta]"]')) {
      button.querySelector('[data-item-label]').textContent = event.target.value.trim() || (collection.dataset.collection === 'fact' ? 'Sin título' : 'Sin pregunta');
    }
  });
  form.addEventListener('change', (event) => {
    markDirty();
    const editor = event.target.closest('[data-item-editor]');
    if (!editor || !event.target.matches('[name$="[visible]"]')) return;
    const collection = editor.closest('[data-collection]');
    collection.querySelector(`[data-item-select="${editor.dataset.itemEditor}"] [data-item-visibility]`).textContent =
      event.target.checked ? 'Visible' : (collection.dataset.collection === 'fact' ? 'Oculto' : 'Oculta');
  });
  form.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', (event) => {
    if (!dirty) return;
    event.preventDefault();
    event.returnValue = '';
  });

  document.addEventListener('click', (event) => {
    const warningAction = event.target.closest('[data-goto-markdown-line]');
    if (warningAction) {
      const tab = document.getElementById('editor-tab-content');
      const textarea = form.querySelector('[data-markdown-editor]');
      activateTab(tab);
      const line = Math.max(1, Number(warningAction.dataset.gotoMarkdownLine) || 1);
      const lines = textarea.value.split('\n');
      const position = lines.slice(0, line - 1).reduce((total, value) => total + value.length + 1, 0);
      textarea.focus();
      textarea.setSelectionRange(position, position + (lines[line - 1]?.length || 0));
      textarea.scrollTop = Math.max(0, ((line - 3) / lines.length) * textarea.scrollHeight);
      return;
    }
    const confirmDialog = document.querySelector('[data-confirm-dialog]');
    if (event.target.closest('[data-confirm-cancel]') && confirmDialog) {
      confirmDialog.close();
      return;
    }
    if (event.target.closest('[data-confirm-accept]') && confirmDialog?.pendingEditor) {
      const editor = confirmDialog.pendingEditor;
      confirmDialog.pendingEditor = null;
      confirmDialog.close();
      removeEditor(editor);
      return;
    }
    const tab = event.target.closest('[role="tab"]');
    if (tab) { activateTab(tab); return; }
    const item = event.target.closest('[data-item-select]');
    if (item) { selectItem(item.closest('[data-collection]'), item.dataset.itemSelect); return; }
    const focalSurface = event.target.closest('[data-focal-surface]');
    if (focalSurface) {
      if (event.detail === 0) {
        updateFocal(50, 50);
        return;
      }
      const bounds = focalSurface.getBoundingClientRect();
      updateFocal(
        ((event.clientX - bounds.left) / bounds.width) * 100,
        ((event.clientY - bounds.top) / bounds.height) * 100,
      );
      return;
    }
    if (event.target.closest('[data-focal-center]')) {
      updateFocal(50, 50);
      return;
    }

    const dialog = document.querySelector('[data-image-dialog]');
    const chooseImage = event.target.closest('[data-image-choose]');
    if (chooseImage && dialog) {
      const selector = chooseImage.closest('[data-image-selector]');
      const selected = selector.querySelector('[data-image-value]').value;
      dialog.imageTarget = selector;
      dialog.querySelectorAll('[data-image-option]').forEach((option) => {
        const active = option.dataset.imageOption === selected;
        option.setAttribute('aria-pressed', String(active));
        option.querySelector('[data-image-selected-label]').hidden = !active;
      });
      dialog.showModal();
      (dialog.querySelector('[aria-pressed="true"]') || dialog.querySelector('[data-image-option]') || dialog.querySelector('[data-image-close]')).focus();
      return;
    }
    if (event.target.closest('[data-image-close]') && dialog) { dialog.close(); return; }
    const imageOption = event.target.closest('[data-image-option]');
    if (imageOption && dialog?.imageTarget) {
      const selector = dialog.imageTarget;
      const preview = selector.querySelector('[data-image-preview]');
      selector.querySelector('[data-image-value]').value = imageOption.dataset.imageOption;
      selector.querySelector('[data-image-name]').textContent = imageOption.dataset.imageOption;
      selector.querySelector('[data-image-empty]').hidden = true;
      selector.querySelector('[data-image-remove]').disabled = false;
      preview.src = imageOption.dataset.imageUrl;
      preview.hidden = false;
      if (selector.querySelector('[name="imagen"]')) {
        const focal = form.querySelector('[data-focal-editor]');
        focal.hidden = false;
        focal.querySelector('[data-focal-image]').src = imageOption.dataset.imageUrl;
        configureFocal(imageOption);
      }
      markDirty();
      dialog.close();
      return;
    }
    const removeImage = event.target.closest('[data-image-remove]');
    if (removeImage) {
      const selector = removeImage.closest('[data-image-selector]');
      const preview = selector.querySelector('[data-image-preview]');
      selector.querySelector('[data-image-value]').value = '';
      selector.querySelector('[data-image-name]').textContent = '';
      selector.querySelector('[data-image-empty]').hidden = false;
      removeImage.disabled = true;
      preview.hidden = true;
      preview.removeAttribute('src');
      if (selector.querySelector('[name="imagen"]')) {
        form.querySelector('[data-focal-editor]').hidden = true;
        const ratioWarning = form.querySelector('[data-featured-ratio-warning]');
        if (ratioWarning) ratioWarning.hidden = true;
      }
      markDirty();
      return;
    }
    const snippetButton = event.target.closest('[data-insert-snippet]');
    if (snippetButton) {
      const textarea = form.querySelector('[data-markdown-editor]');
      textarea.setRangeText(snippetButton.dataset.insertSnippet, textarea.selectionStart, textarea.selectionEnd, 'end');
      textarea.focus();
      markDirty();
      return;
    }
    const insertImageBlock = event.target.closest('[data-insert-image-block]');
    if (insertImageBlock) {
      const builder = insertImageBlock.closest('[data-image-block-builder]');
      const image = builder.querySelector('[data-image-value]').value;
      const status = builder.querySelector('[data-image-block-status]');
      if (!image) {
        status.textContent = 'Elegí una imagen antes de insertar el bloque.';
        builder.querySelector('[data-image-choose]').focus();
        return;
      }
      const alt = builder.querySelector('[data-image-block-alt]').value.replaceAll('"', '”').trim();
      const position = builder.querySelector('[data-image-block-position]').value === 'izquierda' ? 'izquierda' : 'derecha';
      const snippet = `[[bloque-imagen src="${image}" alt="${alt}" posicion="${position}"]]\n\nEscribí aquí el texto que acompañará a la imagen.\n\n[[/bloque-imagen]]`;
      const textarea = form.querySelector('[data-markdown-editor]');
      textarea.setRangeText(snippet, textarea.selectionStart, textarea.selectionEnd, 'end');
      textarea.focus();
      status.textContent = 'Bloque insertado.';
      markDirty();
      return;
    }
    const addTrivia = event.target.closest('[data-add-trivia]');
    if (addTrivia) {
      const collection = addTrivia.closest('[data-collection]');
      const detail = collection.querySelector('[data-trivia-list]');
      const index = nextIndex(detail, '[name^="trivias["]', /^trivias\[(\d+)\]/);
      detail.insertAdjacentHTML('beforeend', triviaMarkup(index, uniqueId(collection, 'tr'), currentSlug()));
      addListItem(collection, index, 'Sin pregunta', 'Visible');
      refreshEmpty(collection);
      selectItem(collection, index, true);
      markDirty();
      return;
    }
    const addFact = event.target.closest('[data-add-fact]');
    if (addFact) {
      const collection = addFact.closest('[data-collection]');
      const detail = collection.querySelector('[data-fact-list]');
      const index = nextIndex(detail, '[name^="sabias_que["]', /^sabias_que\[(\d+)\]/);
      detail.insertAdjacentHTML('beforeend', factMarkup(index, uniqueId(collection, 'sq'), currentSlug()));
      addListItem(collection, index, 'Sin título', 'Visible');
      refreshEmpty(collection);
      selectItem(collection, index, true);
      markDirty();
      return;
    }
    const addOption = event.target.closest('[data-add-option]');
    if (addOption) {
      const block = addOption.closest('[data-trivia-block]');
      const options = block.querySelector('[data-options]');
      const triviaIndex = Number(block.dataset.itemEditor);
      const optionIndex = nextIndex(options, '[name*="[opciones]"]', /\[opciones\]\[(\d+)\]/);
      options.insertAdjacentHTML('beforeend', optionMarkup(triviaIndex, optionIndex));
      markDirty();
      return;
    }
    const removeOption = event.target.closest('[data-remove-option]');
    if (removeOption) {
      const options = removeOption.closest('[data-options]');
      if (options.querySelectorAll('[data-option]').length > 2) {
        removeOption.closest('[data-option]').remove();
        markDirty();
      }
      return;
    }
    const removeBlock = event.target.closest('[data-remove-block]');
    if (removeBlock) {
      const editor = removeBlock.closest('[data-item-editor]');
      const collection = editor.closest('[data-collection]');
      const kind = collection.dataset.collection === 'trivia' ? 'esta trivia' : 'este bloque “Sabías que…”';
      const confirmDialog = document.querySelector('[data-confirm-dialog]');
      confirmDialog.pendingEditor = editor;
      confirmDialog.returnFocus = removeBlock;
      confirmDialog.querySelector('[data-confirm-message]').textContent =
        `¿Eliminar ${kind}? El cambio se aplicará al guardar el artículo.`;
      confirmDialog.showModal();
      confirmDialog.querySelector('[data-confirm-cancel]').focus();
    }
  });

  const dialog = document.querySelector('[data-image-dialog]');
  dialog?.addEventListener('close', () => {
    const trigger = dialog.imageTarget?.querySelector('[data-image-choose]');
    dialog.imageTarget = null;
    trigger?.focus();
  });
  const confirmDialog = document.querySelector('[data-confirm-dialog]');
  confirmDialog?.addEventListener('close', () => {
    const returnFocus = confirmDialog.returnFocus;
    confirmDialog.pendingEditor = null;
    confirmDialog.returnFocus = null;
    if (returnFocus?.isConnected) returnFocus.focus();
  });
})();
