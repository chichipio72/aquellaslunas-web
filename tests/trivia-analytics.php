<?php

declare(strict_types=1);

function triviaAnalyticsAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$script = file_get_contents(dirname(__DIR__) . '/assets/js/content-trivia.js');
triviaAnalyticsAssert(is_string($script), 'No se pudo leer el script de trivias.');

foreach (['trivia_view', 'trivia_answer', 'trivia_article_click'] as $eventName) {
    triviaAnalyticsAssert(substr_count($script, "'$eventName'") === 1, 'El evento ' . $eventName . ' falta o está duplicado.');
}
foreach (['trivia_codigo', 'articulo_slug', 'correcta', 'opcion'] as $parameter) {
    triviaAnalyticsAssert(str_contains($script, $parameter), 'Falta el parámetro ' . $parameter . '.');
}
triviaAnalyticsAssert(str_contains($script, "'IntersectionObserver' in window"), 'La visualización no usa IntersectionObserver.');
triviaAnalyticsAssert(str_contains($script, 'entry.isIntersecting'), 'Una trivia fuera del viewport podría contarse como vista.');
triviaAnalyticsAssert(str_contains($script, 'observedTrivias.has(trivia)'), 'No se deduplican las visualizaciones.');
triviaAnalyticsAssert(str_contains($script, "trivia.dataset.answered === 'true'"), 'No se deduplican las respuestas.');
triviaAnalyticsAssert(str_contains($script, 'selectedIsCorrect'), 'La respuesta no distingue correcta e incorrecta.');
triviaAnalyticsAssert(str_contains($script, "typeof window.aquellasLunasTrackAnalyticsEvent !== 'function'"), 'La trivia depende de Analytics disponible.');
triviaAnalyticsAssert(!str_contains($script, 'preventDefault'), 'Analytics no debe demorar la navegación al artículo.');

echo "Analytics de trivias: OK\n";
