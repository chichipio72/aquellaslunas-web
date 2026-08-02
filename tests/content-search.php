<?php

require_once __DIR__ . '/../includes/content-system.php';

function contentSearchAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function contentSearchArticle(string $slug, string $title, array $keywords, string $summary, string $markdown, bool $visible = true): array
{
    return [
        'slug' => $slug,
        'valid' => true,
        'visible' => $visible,
        'raw' => ['titulo' => $title, 'palabras_clave' => $keywords, 'resumen' => $summary, 'articulo' => $markdown],
    ];
}

$articles = [
    contentSearchArticle('markdown', 'Historia lunar', ['historia'], 'Un recorrido general.', 'Incluye una observación de eclipse.'),
    contentSearchArticle('summary', 'Fenómenos del cielo', ['observación'], 'Cómo reconocer un eclipse.', 'Texto general.'),
    contentSearchArticle('keywords', 'Guía astronómica', ['eclipses', 'Luna'], 'Consejos generales.', 'Texto general.'),
    contentSearchArticle('title', 'Eclipse total de Luna', ['astronomía'], 'Guía práctica.', 'Texto general.'),
    contentSearchArticle('hidden', 'Eclipse oculto', ['eclipse'], 'Borrador.', 'No publicar.', false),
];

$ranked = astronomyContentSearchArticles($articles, 'ECLIPSE');
contentSearchAssert(array_column($ranked, 'slug') === ['title', 'keywords', 'summary', 'markdown'], 'La relevancia no respeta título, palabras clave, resumen y markdown.');
contentSearchAssert(!in_array('hidden', array_column($ranked, 'slug'), true), 'La búsqueda expuso un borrador oculto.');

$accented = [
    contentSearchArticle('orbit', 'La órbita lunar', ['movimiento'], 'Descripción.', 'Contenido.'),
];
contentSearchAssert(array_column(astronomyContentSearchArticles($accented, 'orbita'), 'slug') === ['orbit'], 'La búsqueda sin tilde no encontró texto acentuado.');
contentSearchAssert(array_column(astronomyContentSearchArticles($accented, 'LUNAR'), 'slug') === ['orbit'], 'La búsqueda distinguió mayúsculas.');
contentSearchAssert(astronomyContentSearchLimitQuery(str_repeat('á', 121)) === str_repeat('á', 120), 'El límite de consulta cortó incorrectamente UTF-8.');
contentSearchAssert(astronomyContentSearchArticles($articles, '') === $articles, 'La consulta vacía no restauró el listado original.');
contentSearchAssert(astronomyContentSearchArticles($articles, 'inexistente') === [], 'Una consulta sin coincidencias devolvió artículos.');

fwrite(STDOUT, "content search tests: ok\n");
