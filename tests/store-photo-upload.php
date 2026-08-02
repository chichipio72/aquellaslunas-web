<?php

require_once __DIR__ . '/../includes/store-photo-sync.php';
require_once __DIR__ . '/../includes/store-photo-upload.php';

function storeUploadAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = sys_get_temp_dir() . '/store-photo-upload-' . bin2hex(random_bytes(8));
$paths = [];
try {
    storeUploadAssert(mkdir($root, 0700), 'No se creó la carpeta temporal.');
    $formats = [
        'photo.jpg' => static fn (GdImage $image, string $path): bool => imagejpeg($image, $path),
        'photo.png' => static fn (GdImage $image, string $path): bool => imagepng($image, $path),
        'photo.webp' => static fn (GdImage $image, string $path): bool => imagewebp($image, $path),
    ];
    foreach ($formats as $name => $writer) {
        $path = $root . '/' . $name;
        $image = imagecreatetruecolor(24, 12);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 40, 80));
        storeUploadAssert($writer($image, $path), 'No se creó el fixture ' . $name);
        imagedestroy($image);
        $paths[] = $path;
        $details = storePhotoUploadValidate(['name' => $name, 'tmp_name' => $path, 'size' => filesize($path), 'error' => UPLOAD_ERR_OK]);
        storeUploadAssert($details['width'] === 24 && $details['height'] === 12, 'No se validaron las dimensiones.');
        storeUploadAssert($details['extension'] === pathinfo($name, PATHINFO_EXTENSION), 'No se normalizó el formato.');
    }
    foreach ([
        ['name' => '../photo.jpg', 'tmp_name' => $paths[0], 'size' => filesize($paths[0]), 'error' => UPLOAD_ERR_OK],
        ['name' => 'photo.png', 'tmp_name' => $paths[0], 'size' => filesize($paths[0]), 'error' => UPLOAD_ERR_OK],
        ['name' => 'photo.jpg', 'tmp_name' => $paths[0], 'size' => STORE_PHOTO_UPLOAD_MAX_BYTES + 1, 'error' => UPLOAD_ERR_OK],
    ] as $invalid) {
        try {
            storePhotoUploadValidate($invalid);
            throw new RuntimeException('Se aceptó una carga inválida.');
        } catch (RuntimeException $exception) {
            storeUploadAssert($exception->getMessage() !== 'Se aceptó una carga inválida.', $exception->getMessage());
        }
    }
    $normalized = storePhotoUploadFiles(['name' => ['a.jpg', 'b.png'], 'tmp_name' => ['/a', '/b'], 'size' => [1, 2], 'error' => [0, 0]]);
    storeUploadAssert(count($normalized) === 2 && $normalized[1]['name'] === 'b.png', 'No se normalizó la carga múltiple.');
    fwrite(STDOUT, "store photo upload tests: ok\n");
} finally {
    foreach ($paths as $path) {
        if (is_file($path)) unlink($path);
    }
    if (is_dir($root)) rmdir($root);
}
