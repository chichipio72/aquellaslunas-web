<?php

declare(strict_types=1);

putenv('APP_ENV=local');
putenv('CONTENT_ENABLED_IN_PRODUCTION=false');

require_once __DIR__ . '/../includes/content-system.php';

function homeContentAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$GLOBALS['home_page_profile_content_detail_enabled'] = true;
$catalog = astronomyLoadHomeContentCatalog(true, true);
homeContentAssert(($GLOBALS['home_content_query_count'] ?? null) === 3, 'La carga normal de portada no usó exactamente tres consultas.');
homeContentAssert(count($catalog['articles']) <= 2, 'La portada cargó referencias de artículos no seleccionados.');
homeContentAssert(count($catalog['trivias']) === 1, 'No se obtuvo una única trivia.');
homeContentAssert(count($catalog['facts']) === 1, 'No se obtuvo un único “Sabías que…”.');

ob_start();
renderAstronomyHomeContentCards($catalog, true, true);
$html = ob_get_clean();
homeContentAssert(substr_count($html, 'class="home-v2-card content-home-card"') === 2, 'No se conservaron ambos tipos de tarjeta.');
homeContentAssert(str_contains($html, 'Leer más sobre este tema'), 'La trivia perdió su enlace al artículo.');
homeContentAssert(str_contains($html, 'Ver artículo'), 'El “Sabías que…” perdió su enlace al artículo.');

$connection = getWebDatabaseConnection();
$connection->beginTransaction();
try {
    $connection->exec('UPDATE contenido_trivias SET visible=0');
    $connection->exec('UPDATE contenido_sabias_que SET visible=0');
    $GLOBALS['home_page_profile_content_detail_enabled'] = true;
    $empty = astronomyLoadHomeContentCatalog(true, true, static fn(): PDO => $connection);
    homeContentAssert($empty['trivias'] === [] && $empty['facts'] === [], 'El estado sin entidades visibles no quedó vacío.');
    ob_start();
    renderAstronomyHomeContentCards($empty, true, true);
    homeContentAssert(ob_get_clean() === '', 'El estado vacío dejó contenedores de tarjetas.');
} finally {
    $connection->rollBack();
}

echo "Carga editorial focalizada de portada: OK\n";
