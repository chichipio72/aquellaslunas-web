(() => {
  'use strict';

  const normalize = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('es');

  const matches = (value, query) => normalize(value).includes(normalize(query));

  const evaluate = (sections, query) => {
    const normalizedQuery = normalize(query).trim();
    let matchCount = 0;
    let sectionCount = 0;
    const evaluatedSections = sections.map((section) => {
      const items = section.items.map((item) => {
        const fieldMatches = item.fields.map((field) => normalizedQuery !== '' && matches(field, normalizedQuery));
        const matched = normalizedQuery !== '' && matches(`${section.title} ${item.text}`, normalizedQuery);
        if (matched) matchCount += 1;
        return { matched, fieldMatches };
      });
      const matched = items.some((item) => item.matched);
      if (matched) sectionCount += 1;
      return {
        matched,
        open: normalizedQuery === '' ? section.wasOpen : matched,
        items,
      };
    });

    return { query: normalizedQuery, matchCount, sectionCount, sections: evaluatedSections };
  };

  // Exposed as small pure helpers so search semantics can be regression-tested
  // without a browser or knowledge of the form's technical field names.
  globalThis.astronomyEditorialSearchNormalize = normalize;
  globalThis.astronomyEditorialSearchMatches = matches;
  globalThis.astronomyEditorialSearchEvaluate = evaluate;

  const initialize = () => {
    const search = document.querySelector('[data-editorial-search]');
    if (!search) return;
    const input = search.querySelector('[data-editorial-search-input]');
    const counter = search.querySelector('[data-editorial-search-count]');
    const sections = [...document.querySelectorAll('[data-editorial-search-section]')];
    let previousOpenState = null;

    const readableValue = (element) => {
      const controlValues = [...element.querySelectorAll('input:not([type="hidden"]), textarea, select')]
        .map((control) => control.value)
        .join(' ');
      return `${element.textContent || ''} ${element.dataset.searchDefault || ''} ${controlValues}`;
    };

    const update = () => {
      const query = input.value;
      const isSearching = normalize(query).trim() !== '';
      if (isSearching && previousOpenState === null) {
        previousOpenState = new Map(sections.map((section) => [section, section.open]));
      }

      const models = sections.map((section) => {
        const items = [...section.querySelectorAll('[data-editorial-search-item]')];
        return {
          wasOpen: previousOpenState?.get(section) ?? section.open,
          title: section.querySelector(':scope > summary')?.textContent || '',
          items: items.map((item) => ({
            text: readableValue(item),
            fields: [...item.querySelectorAll('[data-editorial-search-field]')].map(readableValue),
          })),
        };
      });
      const result = evaluate(models, query);

      sections.forEach((section, sectionIndex) => {
        const sectionResult = result.sections[sectionIndex];
        section.open = sectionResult.open;
        section.hidden = isSearching && !sectionResult.matched;
        [...section.querySelectorAll('[data-editorial-search-item]')].forEach((item, itemIndex) => {
          const itemResult = sectionResult.items[itemIndex];
          item.hidden = isSearching && !itemResult.matched;
          item.classList.toggle('is-search-match', itemResult.matched);
          [...item.querySelectorAll('[data-editorial-search-field]')].forEach((field, fieldIndex) => {
            field.classList.toggle('is-search-field-match', itemResult.fieldMatches[fieldIndex] || false);
          });
        });
      });

      if (!isSearching) {
        counter.textContent = 'Todas las reglas';
        previousOpenState = null;
        return;
      }
      const matchLabel = result.matchCount === 1 ? 'coincidencia' : 'coincidencias';
      const sectionLabel = result.sectionCount === 1 ? 'sección' : 'secciones';
      counter.textContent = `${result.matchCount} ${matchLabel} en ${result.sectionCount} ${sectionLabel}`;
    };

    input.addEventListener('input', update);
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
  else initialize();
})();
