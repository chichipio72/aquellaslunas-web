(() => {
  'use strict';

  document.addEventListener('click', (event) => {
    const option = event.target.closest('[data-trivia-option]');
    if (!option) {
      return;
    }

    const trivia = option.closest('[data-content-trivia]');
    if (!trivia || trivia.dataset.answered === 'true') {
      return;
    }

    trivia.dataset.answered = 'true';
    const selectedIsCorrect = option.dataset.correct === 'true';
    const options = trivia.querySelectorAll('[data-trivia-option]');

    options.forEach((candidate) => {
      const status = candidate.querySelector('[data-trivia-option-status]');
      candidate.setAttribute('aria-disabled', 'true');

      if (candidate.dataset.correct === 'true') {
        candidate.classList.add('is-correct');
        if (status) {
          status.textContent = '✓ Correcta';
          status.hidden = false;
        }
      } else if (candidate === option) {
        candidate.classList.add('is-incorrect');
        if (status) {
          status.textContent = '✕ Tu respuesta';
          status.hidden = false;
        }
      }
    });

    const feedback = trivia.querySelector('[data-trivia-feedback]');
    const result = trivia.querySelector('[data-trivia-result]');
    if (result) {
      result.textContent = selectedIsCorrect
        ? 'Correcto.'
        : 'Incorrecto. La respuesta correcta está marcada.';
    }
    if (feedback) {
      feedback.hidden = false;
      feedback.focus({ preventScroll: true });
    }
  });
})();
