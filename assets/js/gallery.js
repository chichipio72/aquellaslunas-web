(() => {
  const modal = document.querySelector('[data-gallery-modal]');
  const modalImage = modal?.querySelector('[data-gallery-modal-image]');
  const modalTitle = modal?.querySelector('[data-gallery-modal-title]');
  const modalPrice = modal?.querySelector('[data-gallery-modal-price]');
  const closeButton = modal?.querySelector('[data-gallery-close]');
  let opener = null;

  const closeModal = () => {
    if (modal instanceof HTMLDialogElement && modal.open) modal.close();
  };

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    const trigger = event.target.closest('[data-gallery-open]');
    if (!(trigger instanceof HTMLButtonElement)) return;
    if (!(modal instanceof HTMLDialogElement)) return;
    const item = trigger.closest('.gallery-item');
    const image = trigger.querySelector('img');
    const title = item?.querySelector('.gallery-item__details h2');
    const price = item?.querySelector('.gallery-item__price');
    if (!(image instanceof HTMLImageElement) || !(modalImage instanceof HTMLImageElement)) return;

    opener = trigger;
    modalImage.src = image.currentSrc || image.src;
    modalImage.alt = image.alt;
    if (modalTitle instanceof HTMLElement) {
      modalTitle.textContent = title?.textContent?.trim() || '';
      modalTitle.hidden = modalTitle.textContent === '';
    }
    if (modalPrice instanceof HTMLElement) modalPrice.textContent = price?.textContent?.trim() || '';
    modal.showModal();
  });

  if (modal instanceof HTMLDialogElement) {
    closeButton?.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
      if (event.target === modal) closeModal();
    });
    modal.addEventListener('close', () => {
      if (modalImage instanceof HTMLImageElement) {
        modalImage.removeAttribute('src');
        modalImage.alt = '';
      }
      opener?.focus();
      opener = null;
    });
  }

  const purchase = document.querySelector('[data-gallery-purchase]');
  if (!(purchase instanceof HTMLFormElement)) return;
  const checkboxes = Array.from(purchase.querySelectorAll('[data-gallery-select]'));
  const count = purchase.querySelector('[data-gallery-selection-count]');
  const total = purchase.querySelector('[data-gallery-selection-total]');
  const checkout = purchase.querySelector('[data-gallery-checkout]');
  const formatMoney = (cents, currency) => new Intl.NumberFormat('es-AR', {
    style: 'currency', currency, minimumFractionDigits: 2
  }).format(cents / 100);
  const updateSelection = () => {
    const selected = checkboxes.filter((checkbox) => checkbox instanceof HTMLInputElement && checkbox.checked);
    let cents = 0;
    const currencies = new Set();
    selected.forEach((checkbox) => {
      cents += Number.parseInt(checkbox.dataset.priceCents || '0', 10) || 0;
      currencies.add(checkbox.dataset.currency || '');
      checkbox.closest('[data-gallery-item]')?.classList.add('is-selected');
    });
    checkboxes.filter((checkbox) => !checkbox.checked).forEach((checkbox) => {
      checkbox.closest('[data-gallery-item]')?.classList.remove('is-selected');
    });
    if (count instanceof HTMLElement) count.textContent = `${selected.length} ${selected.length === 1 ? 'foto seleccionada' : 'fotos seleccionadas'}`;
    const currency = currencies.size === 1 ? Array.from(currencies)[0] : '';
    if (total instanceof HTMLElement) total.textContent = currencies.size > 1 ? 'Total: las monedas deben coincidir' : `Total: ${currency ? formatMoney(cents, currency) : '0,00'}`;
    if (checkout instanceof HTMLButtonElement) checkout.disabled = selected.length === 0 || currencies.size > 1;
  };
  purchase.addEventListener('change', updateSelection);
  updateSelection();
})();
