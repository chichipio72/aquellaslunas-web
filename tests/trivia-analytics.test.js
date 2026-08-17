'use strict';

const fs = require('node:fs');
const vm = require('node:vm');

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

const events = [];
const listeners = {};
let observerCallback = null;

const makeOption = (trivia, correct, index) => {
  const status = {textContent: '', hidden: true};
  return {
    dataset: {correct: correct ? 'true' : 'false', triviaOptionIndex: String(index)},
    classList: {values: [], add(value) { this.values.push(value); }},
    setAttribute() {},
    querySelector(selector) { return selector === '[data-trivia-option-status]' ? status : null; },
    closest(selector) { return selector === '[data-content-trivia]' ? trivia : null; },
    status,
  };
};

const makeTrivia = (code, slug = '') => {
  const result = {textContent: ''};
  const feedback = {hidden: true, focusCalled: false, focus() { this.focusCalled = true; }};
  const trivia = {
    dataset: {triviaCode: code, ...(slug ? {articleSlug: slug} : {})},
    options: [],
    querySelectorAll(selector) { return selector === '[data-trivia-option]' ? this.options : []; },
    querySelector(selector) {
      if (selector === '[data-trivia-feedback]') return feedback;
      if (selector === '[data-trivia-result]') return result;
      return null;
    },
    feedback,
    result,
  };
  trivia.options = [makeOption(trivia, false, 1), makeOption(trivia, true, 2)];
  return trivia;
};

const visible = makeTrivia('visible-1', 'articulo-visible');
const neverVisible = makeTrivia('hidden-1');

global.window = {
  aquellasLunasTrackAnalyticsEvent(name, parameters) {
    events.push({name, parameters});
    return true;
  },
};
global.document = {
  addEventListener(name, callback) { listeners[name] = callback; },
  querySelectorAll(selector) { return selector === '[data-content-trivia]' ? [visible, neverVisible] : []; },
};
global.IntersectionObserver = class {
  constructor(callback, options) { observerCallback = callback; this.options = options; }
  observe() {}
  unobserve() {}
};
window.IntersectionObserver = global.IntersectionObserver;

vm.runInThisContext(fs.readFileSync('assets/js/content-trivia.js', 'utf8'), {filename: 'content-trivia.js'});

observerCallback([{target: visible, isIntersecting: true}, {target: neverVisible, isIntersecting: false}]);
observerCallback([{target: visible, isIntersecting: true}]);
assert(events.filter((event) => event.name === 'trivia_view').length === 1, 'La vista visible se duplicó.');
assert(!events.some((event) => event.name === 'trivia_view' && event.parameters.trivia_codigo === 'hidden-1'), 'Se midió una trivia fuera del viewport.');

const clickOption = (option) => listeners.click({target: {closest(selector) {
  if (selector === '[data-trivia-article-link]') return null;
  if (selector === '[data-trivia-option]') return option;
  return null;
}}});

clickOption(visible.options[1]);
clickOption(visible.options[1]);
const correctAnswers = events.filter((event) => event.name === 'trivia_answer' && event.parameters.trivia_codigo === 'visible-1');
assert(correctAnswers.length === 1, 'La respuesta correcta se duplicó.');
assert(correctAnswers[0].parameters.correcta === true && correctAnswers[0].parameters.opcion === 2, 'La respuesta correcta tiene parámetros incorrectos.');
assert(correctAnswers[0].parameters.articulo_slug === 'articulo-visible', 'Falta el slug relacionado.');

const incorrect = makeTrivia('incorrect-1');
clickOption(incorrect.options[0]);
const incorrectAnswer = events.find((event) => event.name === 'trivia_answer' && event.parameters.trivia_codigo === 'incorrect-1');
assert(incorrectAnswer?.parameters.correcta === false, 'La respuesta incorrecta no se identificó.');
assert(!('articulo_slug' in incorrectAnswer.parameters), 'Se inventó una relación inexistente.');

const articleLink = {closest(selector) { return selector === '[data-content-trivia]' ? visible : null; }};
listeners.click({target: {closest(selector) { return selector === '[data-trivia-article-link]' ? articleLink : null; }}});
assert(events.some((event) => event.name === 'trivia_article_click' && event.parameters.articulo_slug === 'articulo-visible'), 'No se midió el clic al artículo.');

delete window.aquellasLunasTrackAnalyticsEvent;
const blocked = makeTrivia('analytics-blocked');
clickOption(blocked.options[1]);
assert(blocked.dataset.answered === 'true' && blocked.feedback.hidden === false, 'La trivia depende de Analytics disponible.');

console.log('Analytics de trivias en navegador simulado: OK');
