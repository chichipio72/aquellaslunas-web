<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/content-system.php';

function contentRelatedAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function contentRelatedArticle(string $slug, bool $visible = true, bool $valid = true): array
{
    return [
        'slug' => $slug,
        'visible' => $visible,
        'valid' => $valid,
        'raw' => ['titulo' => 'Título ' . $slug, 'resumen' => 'Resumen ' . $slug, 'relaciones' => []],
        'image' => ['url' => null],
        'image_position_x' => 50,
        'image_position_y' => 50,
    ];
}

$source = contentRelatedArticle('origen');
$source['raw']['relaciones'] = [
    'origen',
    'destino-uno',
    'destino-uno',
    'oculto',
    'invalido',
    'inexistente',
    'destino-dos',
    'destino-tres',
    'destino-cuatro',
];
$catalog = ['articles' => [
    'origen' => $source,
    'destino-uno' => contentRelatedArticle('destino-uno'),
    'destino-dos' => contentRelatedArticle('destino-dos'),
    'destino-tres' => contentRelatedArticle('destino-tres'),
    'destino-cuatro' => contentRelatedArticle('destino-cuatro'),
    'oculto' => contentRelatedArticle('oculto', false),
    'invalido' => contentRelatedArticle('invalido', true, false),
]];

$related = astronomyContentRelatedArticles($catalog, $source, 3);
contentRelatedAssert(array_column($related, 'slug') === ['destino-uno', 'destino-dos', 'destino-tres'], 'No se preservó el orden, el límite o el filtrado editorial.');
contentRelatedAssert(count(array_unique(array_column($related, 'slug'))) === count($related), 'Se devolvieron relaciones duplicadas.');
contentRelatedAssert(!in_array('origen', array_column($related, 'slug'), true), 'El artículo actual se relacionó consigo mismo.');
contentRelatedAssert(astronomyContentRelatedArticles($catalog, contentRelatedArticle('sin-relaciones'), 3) === [], 'Un artículo sin relaciones produjo un bloque no vacío.');

ob_start();
foreach ($related as $article) {
    renderAstronomyContentIndexCard($article);
}
$html = (string) ob_get_clean();
contentRelatedAssert(substr_count($html, '<article ') === 3, 'No se renderizó una tarjeta por relacionado.');
contentRelatedAssert(str_contains($html, 'href="contenido.php?slug=destino-uno"'), 'La tarjeta no usa un enlace HTML con la URL pública esperada.');
contentRelatedAssert(!str_contains($html, 'nofollow'), 'Los enlaces relacionados incluyen nofollow.');
contentRelatedAssert(substr_count($html, '<h1') === 0, 'Las tarjetas introdujeron un H1 adicional.');

fwrite(STDOUT, "content related tests: ok\n");
