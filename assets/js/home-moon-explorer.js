(() => {
  const dialog = document.querySelector('[data-home-moon-explorer-dialog]');
  const opener = document.querySelector('[data-home-moon-explorer-open]');
  if (!(dialog instanceof HTMLDialogElement) || !opener) return;

  const surface = dialog.querySelector('[data-home-moon-explorer-surface]');
  const fullscreenButton = dialog.querySelector('[data-home-moon-explorer-fullscreen]');
  let pseudoFullscreen = false;
  const nativeFullscreen = () => document.fullscreenElement === surface;
  const updateFullscreenControl = () => {
    if (!fullscreenButton) return;
    const active = nativeFullscreen() || pseudoFullscreen;
    fullscreenButton.textContent = active ? 'Salir de pantalla completa' : 'Pantalla completa';
    fullscreenButton.setAttribute('aria-pressed', active ? 'true' : 'false');
    window.requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
  };
  const leavePseudoFullscreen = () => {
    pseudoFullscreen = false;
    dialog.classList.remove('is-pseudo-fullscreen');
    updateFullscreenControl();
  };
  const enterPseudoFullscreen = () => {
    pseudoFullscreen = true;
    dialog.classList.add('is-pseudo-fullscreen');
    updateFullscreenControl();
  };
  const open = () => {
    if (!dialog.open) dialog.showModal();
    document.body.classList.add('home-moon-explorer-open');
    dialog.querySelector('[data-interactive-moon]')?.dispatchEvent(new Event('moonexploreropen'));
    window.requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
  };
  const close = () => {
    if (nativeFullscreen() && document.exitFullscreen) document.exitFullscreen().catch(() => {});
    leavePseudoFullscreen();
    if (dialog.open) dialog.close();
  };

  opener.addEventListener('click', open);
  opener.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    open();
  });
  dialog.querySelectorAll('[data-home-moon-explorer-close]').forEach((button) => button.addEventListener('click', close));
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) close();
  });
  dialog.addEventListener('close', () => document.body.classList.remove('home-moon-explorer-open'));

  fullscreenButton?.addEventListener('click', async () => {
    if (pseudoFullscreen) {
      leavePseudoFullscreen();
      return;
    }
    if (nativeFullscreen() && document.exitFullscreen) {
      await document.exitFullscreen().catch(() => leavePseudoFullscreen());
      return;
    }
    if (!surface?.requestFullscreen || !document.exitFullscreen) {
      enterPseudoFullscreen();
      return;
    }
    try {
      await surface.requestFullscreen({ navigationUI: 'hide' });
    } catch (error) {
      console.warn('Pantalla completa nativa no disponible; se usa el modo ampliado.', error);
      enterPseudoFullscreen();
    }
  });
  document.addEventListener('fullscreenchange', updateFullscreenControl);
})();
