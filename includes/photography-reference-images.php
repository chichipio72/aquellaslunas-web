<?php

declare(strict_types=1);

const PHOTOGRAPHY_REFERENCE_IMAGE_DIRECTORY = __DIR__ . '/../assets/images/fotografia/referencias';
const PHOTOGRAPHY_REFERENCE_IMAGE_WEB_PREFIX = 'assets/images/fotografia/referencias/';

function photographyReferenceExifNumber(mixed $value): ?float
{
    if (is_int($value) || is_float($value)) return is_finite((float) $value) ? (float) $value : null;
    if (!is_string($value) || trim($value) === '') return null;
    if (preg_match('/^\s*(-?[0-9]+(?:\.[0-9]+)?)\s*\/\s*(-?[0-9]+(?:\.[0-9]+)?)\s*$/', $value, $match) === 1) {
        $denominator = (float) $match[2];
        return abs($denominator) > 0.0000001 ? (float) $match[1] / $denominator : null;
    }
    return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
}

function photographyReferenceFormatNumber(float $value, int $decimals = 2): string
{
    if ($decimals === 0) return number_format($value, 0, '.', '');
    return rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');
}

/** @return array{focal:?string,aperture:?string,shutter_speed:?string,iso:?string,camera_model:?string} */
function photographyReferenceImageExif(string $absolutePath): array
{
    $result = ['focal' => null, 'aperture' => null, 'shutter_speed' => null, 'iso' => null, 'camera_model' => null];
    if (!is_file($absolutePath) || !function_exists('exif_read_data')) return $result;
    $exif = @exif_read_data($absolutePath, null, true, false);
    if (!is_array($exif)) return $result;
    $exifSection = is_array($exif['EXIF'] ?? null) ? $exif['EXIF'] : [];
    $ifd = is_array($exif['IFD0'] ?? null) ? $exif['IFD0'] : [];
    $computed = is_array($exif['COMPUTED'] ?? null) ? $exif['COMPUTED'] : [];
    $focal = photographyReferenceExifNumber($exifSection['FocalLength'] ?? null);
    if ($focal !== null && $focal > 0) $result['focal'] = photographyReferenceFormatNumber($focal);
    $aperture = photographyReferenceExifNumber($exifSection['FNumber'] ?? null);
    if ($aperture !== null && $aperture > 0) $result['aperture'] = 'f/' . photographyReferenceFormatNumber($aperture, 1);
    elseif (is_string($computed['ApertureFNumber'] ?? null) && trim($computed['ApertureFNumber']) !== '') $result['aperture'] = trim($computed['ApertureFNumber']);
    $exposure = $exifSection['ExposureTime'] ?? null;
    if (is_string($exposure) && preg_match('/^[0-9.]+\/[0-9.]+$/', trim($exposure)) === 1) $result['shutter_speed'] = trim($exposure) . ' s';
    else {
        $exposureNumber = photographyReferenceExifNumber($exposure);
        if ($exposureNumber !== null && $exposureNumber > 0) $result['shutter_speed'] = photographyReferenceFormatNumber($exposureNumber, 4) . ' s';
    }
    $iso = $exifSection['ISOSpeedRatings'] ?? null;
    if (is_array($iso)) $iso = reset($iso);
    if (is_numeric($iso) && (float) $iso > 0) $result['iso'] = photographyReferenceFormatNumber((float) $iso, 0);
    $model = trim((string) ($ifd['Model'] ?? ''));
    $make = trim((string) ($ifd['Make'] ?? ''));
    if ($model !== '') $result['camera_model'] = $make !== '' && stripos($model, $make) !== 0 ? $make . ' ' . $model : $model;
    return $result;
}

/** @return list<array{filename:string,path:string,url:string,width:int,height:int,format_label:string,exif:array}> */
function photographyReferenceImageCatalog(?string $directory = null): array
{
    $directory ??= PHOTOGRAPHY_REFERENCE_IMAGE_DIRECTORY;
    if (!is_dir($directory)) return [];
    $filenames = [];
    foreach (scandir($directory) ?: [] as $filename) {
        if ($filename === '.' || $filename === '..' || preg_match('/\.(?:jpe?g|png|webp)$/i', $filename) !== 1 || !is_file($directory . '/' . $filename) || is_link($directory . '/' . $filename)) continue;
        $filenames[] = $filename;
    }
    natcasesort($filenames);
    $catalog = [];
    foreach ($filenames as $filename) {
        $absolutePath = $directory . '/' . $filename;
        $size = @getimagesize($absolutePath);
        $width = is_array($size) ? (int) $size[0] : 0;
        $height = is_array($size) ? (int) $size[1] : 0;
        $path = PHOTOGRAPHY_REFERENCE_IMAGE_WEB_PREFIX . $filename;
        $catalog[] = ['filename' => $filename, 'path' => $path, 'url' => $path, 'width' => $width, 'height' => $height,
            'format_label' => $width > $height ? 'Horizontal' : ($height > $width ? 'Vertical' : 'Cuadrada'),
            'exif' => photographyReferenceImageExif($absolutePath)];
    }
    return $catalog;
}
