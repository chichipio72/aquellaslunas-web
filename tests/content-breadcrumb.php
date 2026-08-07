<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/content-system.php';

function contentBreadcrumbAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$longTitle = '¿Qué diferencia hay entre el mes sinódico, sideral, anomalístico y dracónico de la Luna?';
ob_start();
renderAstronomyContentBreadcrumb($longTitle);
$html = (string) ob_get_clean();

contentBreadcrumbAssert(str_contains($html, '<nav class="content-breadcrumb" aria-label="Ruta de navegación">'), 'El breadcrumb no usa nav con una etiqueta accesible.');
contentBreadcrumbAssert(str_contains($html, '<ol>'), 'El breadcrumb no usa una lista ordenada.');
contentBreadcrumbAssert(str_contains($html, '<a href="index.php">Inicio</a>'), 'Inicio no enlaza a la portada.');
contentBreadcrumbAssert(str_contains($html, '<a href="contenidos.php">Contenidos</a>'), 'Contenidos no enlaza al índice.');
contentBreadcrumbAssert(str_contains($html, '<li aria-current="page">' . $longTitle . '</li>'), 'El título actual no es el último elemento sin enlace.');
contentBreadcrumbAssert(substr_count($html, '<a ') === 2, 'El elemento actual generó un enlace alternativo.');
contentBreadcrumbAssert(substr_count($html, '<h1') === 0, 'El breadcrumb introdujo un H1.');

fwrite(STDOUT, "content breadcrumb tests: ok\n");
