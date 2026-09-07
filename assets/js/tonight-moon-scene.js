document.addEventListener('DOMContentLoaded', () => {
  const trigger = document.querySelector('[data-tonight-moon-scene-open]');
  const dialog = document.querySelector('[data-tonight-moon-scene-dialog]');
  const content = dialog?.querySelector('[data-tonight-moon-scene-content]');
  const closeButton = dialog?.querySelector('[data-tonight-moon-scene-close]');
  if (!(trigger instanceof HTMLElement) || !(dialog instanceof HTMLDialogElement) || !(content instanceof HTMLElement)) return;

  const open = () => {
    const scene = trigger.querySelector('.home-tonight-scene');
    if (!(scene instanceof HTMLElement)) return;
    const expandedScene = scene.cloneNode(true);
    const svg = expandedScene.querySelector('svg');
    const title = expandedScene.querySelector('title');
    const description = expandedScene.querySelector('desc');
    if (svg instanceof SVGElement && title instanceof SVGElement && description instanceof SVGElement) {
      title.id = 'tonight-moon-scene-dialog-title';
      description.id = 'tonight-moon-scene-dialog-description';
      svg.setAttribute('aria-labelledby', `${title.id} ${description.id}`);
    }
    content.replaceChildren(expandedScene);
    dialog.showModal();
  };
  const close = () => { if (dialog.open) dialog.close(); };

  trigger.addEventListener('click', open);
  trigger.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    open();
  });
  closeButton?.addEventListener('click', close);
  dialog.addEventListener('click', (event) => { if (event.target === dialog) close(); });
  dialog.addEventListener('close', () => {
    content.replaceChildren();
    trigger.focus();
  });
});
