<?php

require_once __DIR__ . '/../includes/content-system.php';

function contentCardRelationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$catalog = ['articles' => [
    'visible-parent' => ['valid' => true, 'visible' => true],
    'hidden-parent' => ['valid' => true, 'visible' => false],
    'invalid-parent' => ['valid' => false, 'visible' => true],
]];

contentCardRelationAssert(
    astronomyContentEntryArticleUrl($catalog, ['source_slug' => 'visible-parent']) === astronomyContentArticleUrl('visible-parent'),
    'No se generó la URL del artículo padre público.'
);
contentCardRelationAssert(astronomyContentEntryArticleUrl($catalog, ['source_slug' => 'hidden-parent']) === null, 'Se enlazó un artículo padre oculto.');
contentCardRelationAssert(astronomyContentEntryArticleUrl($catalog, ['source_slug' => 'invalid-parent']) === null, 'Se enlazó un artículo padre inválido.');
contentCardRelationAssert(astronomyContentEntryArticleUrl($catalog, ['source_slug' => 'missing-parent']) === null, 'Se enlazó un artículo padre inexistente.');
contentCardRelationAssert(astronomyContentEntryArticleUrl($catalog, ['source_slug' => '../unsafe']) === null, 'Se aceptó un slug inseguro.');

$source = file_get_contents(__DIR__ . '/../includes/content-system.php');
contentCardRelationAssert(is_string($source), 'No se pudo inspeccionar el render de tarjetas.');
contentCardRelationAssert(
    preg_match('/interactive-feedback[^>]*data-trivia-feedback[^>]*hidden>.*?Leer más sobre este tema/s', $source) === 1,
    'El enlace de trivia no está dentro del feedback oculto.'
);
contentCardRelationAssert(str_contains($source, '>Ver artículo <'), 'La tarjeta “Sabías que…” no incluye el enlace relacionado.');

fwrite(STDOUT, "content card relation tests: ok\n");
