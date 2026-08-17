(() => {
  'use strict';

  const analyticsParameters = (trivia) => {
    const parameters = {trivia_codigo: trivia.dataset.triviaCode || ''};
    if (trivia.dataset.articleSlug) parameters.articulo_slug = trivia.dataset.articleSlug;
    return parameters;
  };

  const track = (name, parameters) => {
    if (typeof window.aquellasLunasTrackAnalyticsEvent !== 'function') return false;
    return window.aquellasLunasTrackAnalyticsEvent(name, parameters);
  };

  const observedTrivias = new WeakSet();
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        const trivia = entry.target;
        if (!entry.isIntersecting || observedTrivias.has(trivia)) return;
        observedTrivias.add(trivia);
        observer.unobserve(trivia);
        track('trivia_view', analyticsParameters(trivia));
      });
    }, {threshold: 0.25});
    document.querySelectorAll('[data-content-trivia]').forEach((trivia) => observer.observe(trivia));
  }

  document.addEventListener('click', (event) => {
    const articleLink = event.target.closest('[data-trivia-article-link]');
    if (articleLink) {
      const trivia = articleLink.closest('[data-content-trivia]');
      if (trivia) track('trivia_article_click', analyticsParameters(trivia));
      return;
    }

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
    const answerParameters = {...analyticsParameters(trivia), correcta: selectedIsCorrect};
    const optionIndex = Number.parseInt(option.dataset.triviaOptionIndex || '', 10);
    if (Number.isInteger(optionIndex)) answerParameters.opcion = optionIndex;
    track('trivia_answer', answerParameters);
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
