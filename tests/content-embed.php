<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/content-system.php';

function contentEmbedAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$url = 'https://chichipiosblog.com.ar/astronomia/sol-tierra-luna/?embed=1&view=eclipse&datetime=2026-08-12T15:07:21.756Z';
$directive = '[[embed url="' . $url . '"]]';
$html = astronomyContentRenderMarkdown($directive);

contentEmbedAssert(str_contains($html, '<div class="content-embed"'), 'La URL válida no generó el contenedor del embed.');
contentEmbedAssert(str_contains($html, '<iframe '), 'La URL válida no generó iframe.');
contentEmbedAssert(str_contains($html, 'src="https://chichipiosblog.com.ar/astronomia/sol-tierra-luna/?embed=1&amp;view=eclipse&amp;datetime=2026-08-12T15:07:21.756Z"'), 'No se conservó y escapó la query completa.');
contentEmbedAssert(str_contains($html, 'title="Simulador astronómico interactivo"'), 'El iframe no tiene título accesible.');
contentEmbedAssert(str_contains($html, 'loading="lazy"'), 'El iframe no usa carga diferida.');
contentEmbedAssert(str_contains($html, 'sandbox="allow-scripts allow-same-origin"'), 'El sandbox no está controlado por el renderer.');

$rejectedUrls = [
    'http://chichipiosblog.com.ar/astronomia/sol-tierra-luna/?embed=1',
    'https://otro.example/astronomia/sol-tierra-luna/?embed=1',
    'https://evil.chichipiosblog.com.ar/astronomia/sol-tierra-luna/?embed=1',
    'https://chichipiosblog.com.ar/otra-ruta/?embed=1',
    'https://chichipiosblog.com.ar/astronomia/../admin/',
    'https://chichipiosblog.com.ar/astronomia/%2e%2e/admin/',
    'https://chichipiosblog.com.ar:443/astronomia/sol-tierra-luna/?embed=1',
    'https://usuario@chichipiosblog.com.ar/astronomia/sol-tierra-luna/?embed=1',
    'javascript:alert(1)',
    'data:text/html,test',
    '/astronomia/sol-tierra-luna/?embed=1',
];
foreach ($rejectedUrls as $rejectedUrl) {
    contentEmbedAssert(astronomyContentResolveEmbedUrl($rejectedUrl) === null, 'Se aceptó una URL no permitida: ' . $rejectedUrl);
    contentEmbedAssert(!str_contains(astronomyContentRenderMarkdown('[[embed url="' . $rejectedUrl . '"]]'), '<iframe '), 'Una URL no permitida generó iframe.');
}

$injectionDirectives = [
    '[[embed url="' . $url . '" onload="alert(1)"]] ',
    '[[embed url="' . $url . '" sandbox="allow-forms"]]',
    '[[embed url="' . $url . '"><script>alert(1)</script>]]',
];
foreach ($injectionDirectives as $injectionDirective) {
    $injectionHtml = astronomyContentRenderMarkdown(trim($injectionDirective));
    contentEmbedAssert(!str_contains($injectionHtml, '<iframe '), 'Una inyección de atributos o HTML generó iframe.');
    contentEmbedAssert(!str_contains($injectionHtml, '<script'), 'Se emitió HTML inyectado desde Markdown.');
}

$malformedHtml = astronomyContentRenderMarkdown("Texto anterior.\n\n[[embed url=\"https://chichipiosblog.com.ar/astronomia/\"\n\nTexto posterior.");
contentEmbedAssert(!str_contains($malformedHtml, '[[embed'), 'La directiva mal formada quedó visible.');
contentEmbedAssert(str_contains($malformedHtml, '<p>Texto anterior.</p>') && str_contains($malformedHtml, '<p>Texto posterior.</p>'), 'Una directiva mal formada rompió los párrafos vecinos.');

$multipleHtml = astronomyContentRenderMarkdown(
    "Antes.\n\n" . $directive . "\n\nEntre ambos.\n\n"
    . '[[embed url="https://chichipiosblog.com.ar/astronomia/sol-tierra-luna/?embed=1&view=months"]]'
    . "\n\nDespués."
);
contentEmbedAssert(substr_count($multipleHtml, '<iframe ') === 2, 'No se renderizaron dos embeds válidos.');
contentEmbedAssert(str_contains($multipleHtml, '<p>Antes.</p>') && str_contains($multipleHtml, '<p>Entre ambos.</p>') && str_contains($multipleHtml, '<p>Después.</p>'), 'Los embeds alteraron los párrafos contiguos.');
contentEmbedAssert(substr_count($multipleHtml, '<h1') === 0, 'El embed introdujo un H1.');

$articleRaw = [
    'version' => 1,
    'visible' => true,
    'imagen' => null,
    'titulo' => 'Artículo con embed',
    'resumen' => 'Resumen',
    'palabras_clave' => [],
    'relaciones' => [],
    'sabias_que' => [],
    'trivias' => [],
    'articulo' => "# Artículo con embed\n\n[[embed url=\"https://otro.example/astronomia/\"]]\n\nTexto.",
];
$validated = astronomyContentValidateArticle('articulo-con-embed', $articleRaw, 'articulo-con-embed.php');
contentEmbedAssert($validated['valid'] === true, 'Un embed inválido invalidó el resto del artículo.');
contentEmbedAssert(count(array_filter($validated['warnings'], static fn(array $warning): bool => ($warning['kind'] ?? '') === 'invalid_embed')) === 1, 'Un embed inválido no produjo diagnóstico editorial.');
$articleRaw['articulo'] = "# Artículo con embed\n\n[[embed url=\"https://chichipiosblog.com.ar/astronomia/\"\n\nTexto.";
$malformedValidated = astronomyContentValidateArticle('embed-malformado', $articleRaw, 'embed-malformado.php');
contentEmbedAssert($malformedValidated['valid'] === true, 'Una directiva mal formada invalidó el artículo.');
contentEmbedAssert(count(array_filter($malformedValidated['warnings'], static fn(array $warning): bool => ($warning['kind'] ?? '') === 'invalid_embed')) === 1, 'Una directiva mal formada no produjo diagnóstico editorial.');

$styles = file_get_contents(__DIR__ . '/../assets/css/styles.css');
contentEmbedAssert(is_string($styles) && str_contains($styles, '.content-embed {') && str_contains($styles, 'overflow: hidden;'), 'El contenedor no protege el ancho/overflow de la página.');
contentEmbedAssert(str_contains((string) $styles, '.content-embed iframe') && str_contains((string) $styles, 'max-width: 100%;'), 'El iframe no está limitado al ancho disponible.');

fwrite(STDOUT, "content embed tests: ok\n");
