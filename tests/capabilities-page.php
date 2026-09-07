<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/capabilities-content.php';

function capabilitiesAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$content = astronomyCapabilitiesContentLoad();
capabilitiesAssert(is_array($content), 'No se pudo validar el contenido JSON vigente.');
$mainCardCount = count($content['main_cards']);
$featureCardCount = count($content['feature_cards']);
capabilitiesAssert($mainCardCount > 0, 'El JSON no contiene tarjetas principales.');
capabilitiesAssert($featureCardCount > 0, 'El JSON no contiene tarjetas especiales.');

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/que-podes-hacer.php';
ob_start();
require __DIR__ . '/../que-podes-hacer.php';
$html = (string) ob_get_clean();
$text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
capabilitiesAssert(is_string($text), 'No se pudo obtener el texto visible.');

$editorialStrings = [];
$collect = static function (mixed $value, ?string $key = null) use (&$collect, &$editorialStrings): void {
    if (is_string($value) && in_array($key, ['eyebrow', 'title', 'text', 'items', 'introduction', 'paragraphs'], true)) $editorialStrings[] = $value;
    if (is_array($value)) foreach ($value as $childKey => $child) $collect($child, is_string($childKey) ? $childKey : $key);
};
$collect($content);
foreach ($editorialStrings as $string) capabilitiesAssert(str_contains($text, $string), 'Falta el texto extraído: ' . $string);

foreach (array_merge($content['main_cards'], $content['feature_cards']) as $card) {
    $target = $card['link'] ?? $card['action'] ?? null;
    if ($target === null) continue;
    if (($target['kind'] ?? 'link') !== 'link') continue;
    capabilitiesAssert(str_contains($html, 'href="' . htmlspecialchars(astronomyInternalUrl($target['url']), ENT_QUOTES, 'UTF-8') . '"'), 'Falta el enlace de ' . $card['title'] . '.');
}
foreach (['iss-tiangong', 'sabias-que', 'trivias'] as $cardId) {
    $matchingCards = array_values(array_filter($content['main_cards'], static fn (array $card): bool => $card['id'] === $cardId));
    capabilitiesAssert(count($matchingCards) === 1 && !isset($matchingCards[0]['link']), 'La tarjeta ' . $cardId . ' no debe generar un enlace.');
}
capabilitiesAssert(substr_count($html, 'class="card capability-card"') === $mainCardCount, 'Cantidad incorrecta de tarjetas principales renderizadas.');
capabilitiesAssert(substr_count($html, 'class="card capability-feature') === $featureCardCount, 'Cantidad incorrecta de tarjetas especiales renderizadas.');
capabilitiesAssert(str_contains($html, 'data-install-trigger data-install-source="capabilities"'), 'Cambió la acción de instalación.');
capabilitiesAssert(str_contains($html, 'aria-current="page">Qué ofrece Aquellas Lunas</a>'), 'El menú no marca la página activa.');
capabilitiesAssert(str_contains($html, 'https://aquellaslunas.com.ar/astro/que-podes-hacer.php'), 'Falta canonical.');

$styles = file_get_contents(__DIR__ . '/../assets/css/styles.css');
capabilitiesAssert(is_string($styles) && str_contains($styles, '@media (max-width: 760px)') && str_contains($styles, '.capabilities-grid,'), 'Se perdió el contrato responsive existente.');

$temporaryDirectory = sys_get_temp_dir() . '/capabilities-' . bin2hex(random_bytes(6));
mkdir($temporaryDirectory, 0700);
try {
    capabilitiesAssert(astronomyCapabilitiesContentLoad($temporaryDirectory . '/missing.json') === null, 'La ausencia del archivo no produce fallback.');
    $invalidPath = $temporaryDirectory . '/invalid.json';
    file_put_contents($invalidPath, '{');
    capabilitiesAssert(astronomyCapabilitiesContentLoad($invalidPath) === null, 'JSON inválido fue aceptado.');
    $incompletePath = $temporaryDirectory . '/incomplete.json';
    file_put_contents($incompletePath, '{"version":1}');
    capabilitiesAssert(astronomyCapabilitiesContentLoad($incompletePath) === null, 'JSON incompleto fue aceptado.');
    $unsafe = json_decode((string) file_get_contents(ASTRONOMY_CAPABILITIES_CONTENT_PATH), true, 64, JSON_THROW_ON_ERROR);
    $unsafe['page']['title'] = '<script>alert("x")</script>';
    $unsafePath = $temporaryDirectory . '/unsafe.json';
    file_put_contents($unsafePath, json_encode($unsafe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $unsafeContent = astronomyCapabilitiesContentLoad($unsafePath);
    capabilitiesAssert(is_array($unsafeContent), 'El texto de seguridad no pudo cargarse como dato editorial.');
    ob_start(); renderAstronomyCapabilitiesContent($unsafeContent); $unsafeHtml = (string) ob_get_clean();
    capabilitiesAssert(!str_contains($unsafeHtml, '<script>') && str_contains($unsafeHtml, '&lt;script&gt;'), 'Un texto editorial pudo ejecutar HTML o JavaScript.');
} finally {
    foreach (glob($temporaryDirectory . '/*') ?: [] as $file) unlink($file);
    rmdir($temporaryDirectory);
}

echo "Página Qué podés hacer: OK\n";
