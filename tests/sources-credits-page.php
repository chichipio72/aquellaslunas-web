<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/sources-credits-content.php';

function sourcesCreditsAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$content = astronomySourcesCreditsContentLoad();
sourcesCreditsAssert(is_array($content), 'No se pudo validar el contenido JSON vigente.');
sourcesCreditsAssert(count($content['sections']) === 7, 'La estructura editorial no contiene las siete secciones esperadas.');

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/fuentes-y-creditos.php';
ob_start();
require __DIR__ . '/../fuentes-y-creditos.php';
$html = (string) ob_get_clean();
$text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
sourcesCreditsAssert(is_string($text), 'No se pudo obtener el texto visible.');

foreach ($content['sections'] as $section) {
    sourcesCreditsAssert(str_contains($text, $section['title']), 'Falta la sección ' . $section['title'] . '.');
    foreach ($section['sources'] as $source) foreach ($source['links'] as $link) {
        sourcesCreditsAssert(str_contains($html, 'href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '"'), 'Falta el enlace ' . $link['url'] . '.');
    }
}
sourcesCreditsAssert(str_contains($html, 'aria-current="page">Fuentes y créditos</a>'), 'El menú no marca la página activa.');
sourcesCreditsAssert(str_contains($html, 'https://aquellaslunas.com.ar/astro/fuentes-y-creditos.php'), 'Falta canonical.');

$temporaryDirectory = sys_get_temp_dir() . '/sources-credits-' . bin2hex(random_bytes(6));
mkdir($temporaryDirectory, 0700);
try {
    sourcesCreditsAssert(astronomySourcesCreditsContentLoad($temporaryDirectory . '/missing.json') === null, 'La ausencia del archivo no produce fallback.');
    $unsafe = json_decode((string) file_get_contents(ASTRONOMY_SOURCES_CREDITS_CONTENT_PATH), true, 64, JSON_THROW_ON_ERROR);
    $unsafe['page']['title'] = '<script>alert("x")</script>';
    $unsafePath = $temporaryDirectory . '/unsafe.json';
    file_put_contents($unsafePath, json_encode($unsafe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $unsafeContent = astronomySourcesCreditsContentLoad($unsafePath);
    sourcesCreditsAssert(is_array($unsafeContent), 'El texto editorial de prueba no pudo cargarse.');
    ob_start(); renderAstronomySourcesCreditsContent($unsafeContent); $unsafeHtml = (string) ob_get_clean();
    sourcesCreditsAssert(!str_contains($unsafeHtml, '<script>') && str_contains($unsafeHtml, '&lt;script&gt;'), 'Un texto editorial pudo ejecutar HTML.');
    $unsafe['sections'][1]['sources'][0]['links'][0]['url'] = 'javascript:alert(1)';
    file_put_contents($unsafePath, json_encode($unsafe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    sourcesCreditsAssert(astronomySourcesCreditsContentLoad($unsafePath) === null, 'Se aceptó un enlace externo inseguro.');
} finally {
    foreach (glob($temporaryDirectory . '/*') ?: [] as $file) unlink($file);
    rmdir($temporaryDirectory);
}

echo "Página Fuentes y créditos: OK\n";
