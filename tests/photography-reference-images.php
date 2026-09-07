<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/photography-reference-images.php';

function photographyReferenceAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$catalog = photographyReferenceImageCatalog();
photographyReferenceAssert($catalog !== [], 'El catálogo específico de referencias está vacío.');
foreach ($catalog as $image) {
    photographyReferenceAssert(str_starts_with($image['path'], PHOTOGRAPHY_REFERENCE_IMAGE_WEB_PREFIX), 'El catálogo incluyó una ruta ajena a Fotografía.');
    photographyReferenceAssert(!str_contains($image['path'], '/contenido/'), 'El catálogo mezcló imágenes de Contenidos.');
}
$reference = null;
foreach ($catalog as $image) if ($image['filename'] === 'Luna Historia 20260626 1839.jpg') $reference = $image;
photographyReferenceAssert(is_array($reference), 'No se encontró la referencia JPEG esperada.');
if (function_exists('exif_read_data')) {
    photographyReferenceAssert(($reference['exif']['focal'] ?? null) === '600', 'No se leyó la focal EXIF esperada.');
    photographyReferenceAssert(($reference['exif']['aperture'] ?? null) === 'f/9', 'No se leyó la apertura EXIF esperada.');
    photographyReferenceAssert(($reference['exif']['shutter_speed'] ?? null) === '1/320 s', 'No se leyó la velocidad EXIF esperada.');
    photographyReferenceAssert(($reference['exif']['iso'] ?? null) === '200', 'No se leyó el ISO EXIF esperado.');
    photographyReferenceAssert(($reference['exif']['camera_model'] ?? null) === 'Canon EOS 80D', 'No se leyó el modelo de cámara esperado.');
}
$withoutExif = photographyReferenceImageExif(__DIR__ . '/../assets/images/favicon/favicon-32x32.png');
photographyReferenceAssert(array_filter($withoutExif, static fn($value): bool => $value !== null) === [], 'Una imagen sin EXIF no devolvió un fallback vacío.');
$adminSource = file_get_contents(__DIR__ . '/../admin/fotografia/index.php');
$adminScript = file_get_contents(__DIR__ . '/../admin/fotografia/admin.js');
photographyReferenceAssert(str_contains((string) $adminSource, 'photographyReferenceImageCatalog()') && !str_contains((string) $adminSource, 'contentEditorImageGallery()'), 'El admin todavía consume el catálogo general.');
photographyReferenceAssert(str_contains((string) $adminSource, "photographyAdminRenderImageSelector('example_image_path', \$selectedScene['example_image_path']") && str_contains((string) $adminSource, "photographyAdminRenderImageSelector('example_image_path', \$variant['example_image_path']"), 'Escenas y variantes no comparten el selector específico de referencias.');
photographyReferenceAssert(str_contains((string) $adminScript, 'reference_focal_mm') && str_contains((string) $adminScript, 'dataset.exifAperture') && str_contains((string) $adminScript, 'dataset.exifShutter') && str_contains((string) $adminScript, 'dataset.exifIso'), 'La selección de variante no refresca todos los campos EXIF.');
echo "Photography reference images: OK\n";
