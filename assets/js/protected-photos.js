(() => {
  'use strict';

  const protectedPhoto = (target) => target instanceof Element && target.closest('.js-protected-photo');

  document.addEventListener('contextmenu', (event) => {
    if (protectedPhoto(event.target)) event.preventDefault();
  });

  document.addEventListener('dragstart', (event) => {
    if (protectedPhoto(event.target)) event.preventDefault();
  });
})();
