const assert = (condition, message) => { if (!condition) throw new Error(message); };

assert(astronomyEditorialSearchMatches('más grande de lo habitual', 'más grande de lo habitual'), 'exact search');
assert(astronomyEditorialSearchMatches('más grande de lo habitual', 'grande de lo'), 'partial search');
assert(astronomyEditorialSearchMatches('LUNA LLENA', 'luna llena'), 'case-insensitive search');
assert(astronomyEditorialSearchMatches('más grande de lo habitual', 'mas grande'), 'accent-insensitive search');

const sections = [
  { wasOpen: false, title: 'Órbita lunar', items: [{ text: 'Superluna Mensaje más grande de lo habitual', fields: ['Mensaje más grande de lo habitual'] }] },
  { wasOpen: true, title: 'Eclipses', items: [{ text: 'Visibilidad local', fields: ['Mensaje No será visible'] }] },
];
const found = astronomyEditorialSearchEvaluate(sections, 'mas grande');
assert(found.matchCount === 1 && found.sectionCount === 1, 'counts cards and sections');
assert(found.sections[0].open && !found.sections[1].open, 'opens only matching sections');
assert(found.sections[0].items[0].fieldMatches[0], 'marks the concrete matching field');

const cleared = astronomyEditorialSearchEvaluate(sections, '');
assert(!cleared.sections[0].open && cleared.sections[1].open, 'clearing restores previous open state');
