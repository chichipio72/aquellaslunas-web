<?php

declare(strict_types=1);

namespace AstronomyEngine;

use AstronomyEngine\Facade\AstronomyObserver;
use DateTimeImmutable;
use DateTimeZone;
use GdImage;
use InvalidArgumentException;
use RuntimeException;

/** GD renderer and on-disk cache for the portable lunar phase image contract. */
final class MoonImageRenderer
{
    public const SYNODIC_MONTH_DAYS = 29.530588853;
    private const CACHE_VERSION = 'php-gd-v1';

    public function __construct(
        private readonly string $texturePath = __DIR__.'/../assets/Luna llena.png',
        private readonly string $cacheDirectory = __DIR__.'/../cache/moon',
    ) {
        foreach (['imagecreatefrompng', 'imagecopyresampled', 'imagerotate', 'imagepng'] as $function) {
            if (!function_exists($function)) throw new RuntimeException('PHP GD with PNG support is required.');
        }
    }

    /** @param array<string,mixed> $options */
    public function render(array $options): MoonImageRenderResult
    {
        $totalStart = hrtime(true);
        $astronomyStart = hrtime(true);
        $normalized = $this->normalizeOptions($options);
        $astronomyMs = $this->milliseconds($astronomyStart);
        $sourceHash = $this->textureHash();
        $orientationKey = $this->orientationKey($normalized);
        $inputs = sprintf(
            '%s|%s|%.9f|%d|%.6f|%.6f|%s|%s',
            self::CACHE_VERSION,
            substr($sourceHash, 0, 16),
            fmod($normalized['phase_angle_degrees'], 360.0),
            $normalized['size'],
            $normalized['shadow_floor'],
            $normalized['terminator_softness'],
            $normalized['orientation'],
            $orientationKey,
        );
        $key = hash('sha256', $inputs);
        $path = rtrim($this->cacheDirectory, '/').'/'.$key.'.png';
        if (is_file($path) && is_readable($path)) {
            $png = file_get_contents($path);
            if ($png === false) throw new RuntimeException('Could not read cached Moon image.');
            return new MoonImageRenderResult($png, $path, $key, true, $this->metadata($normalized, $sourceHash), [
                'astronomy_ms' => $astronomyMs,
                'shading_ms' => 0.0,
                'rotation_ms' => 0.0,
                'encoding_ms' => 0.0,
                'total_ms' => $this->milliseconds($totalStart),
            ]);
        }

        $shadingStart = hrtime(true);
        $image = $this->shade($normalized);
        $shadingMs = $this->milliseconds($shadingStart);
        $rotationStart = hrtime(true);
        $image = $this->orient($image, $normalized);
        $rotationMs = $this->milliseconds($rotationStart);
        if (!is_dir($this->cacheDirectory) && !mkdir($this->cacheDirectory, 0775, true) && !is_dir($this->cacheDirectory)) {
            throw new RuntimeException('Could not create Moon image cache directory.');
        }
        $temporary = tempnam($this->cacheDirectory, 'moon-');
        if ($temporary === false) throw new RuntimeException('Could not create temporary Moon image.');
        $encodingStart = hrtime(true);
        try {
            imagesavealpha($image, true);
            if (!imagepng($image, $temporary, 9)) throw new RuntimeException('Could not encode Moon PNG.');
            if (!chmod($temporary, 0644)) throw new RuntimeException('Could not set Moon PNG permissions.');
            if (!rename($temporary, $path)) throw new RuntimeException('Could not publish cached Moon PNG.');
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
        $encodingMs = $this->milliseconds($encodingStart);
        $png = file_get_contents($path);
        if ($png === false) throw new RuntimeException('Could not read rendered Moon PNG.');
        return new MoonImageRenderResult($png, $path, $key, false, $this->metadata($normalized, $sourceHash), [
            'astronomy_ms' => $astronomyMs,
            'shading_ms' => $shadingMs,
            'rotation_ms' => $rotationMs,
            'encoding_ms' => $encodingMs,
            'total_ms' => $this->milliseconds($totalStart),
        ]);
    }

    /** @param array<string,mixed> $options @return array<string,mixed> */
    private function normalizeOptions(array $options): array
    {
        $orientation = $options['orientation'] ?? 'fixed';
        $size = $options['size'] ?? 600;
        $shadow = $options['shadow_floor'] ?? 0.2;
        $softness = $options['terminator_softness'] ?? 0.02;
        if (!in_array($orientation, ['fixed', 'hemisphere', 'apparent'], true)) throw new InvalidArgumentException('Orientation must be fixed, hemisphere, or apparent.');
        if (!is_int($size) || $size < 32 || $size > 2000) throw new InvalidArgumentException('Size must be between 32 and 2000 pixels.');
        if (!is_numeric($shadow) || !is_finite((float) $shadow) || $shadow < 0 || $shadow > 1) throw new InvalidArgumentException('Shadow floor must be between 0 and 1.');
        if (!is_numeric($softness) || !is_finite((float) $softness) || $softness < 0 || $softness > 0.2) throw new InvalidArgumentException('Terminator softness must be between 0 and 0.2.');
        $byAge = array_key_exists('age_days', $options) && $options['age_days'] !== null;
        $byIllumination = (array_key_exists('illumination_percent', $options) && $options['illumination_percent'] !== null)
            || (array_key_exists('phase_direction', $options) && $options['phase_direction'] !== null);
        $latitude = $this->nullableFloat($options['latitude'] ?? null, -90, 90, 'Latitude');
        $longitude = $this->nullableFloat($options['longitude'] ?? null, -180, 180, 'Longitude');
        $timezone = isset($options['timezone']) ? (string) $options['timezone'] : null;
        $datetime = $options['datetime'] ?? null;
        $geometry = null;
        if ($orientation === 'apparent') {
            if ($byAge || $byIllumination) throw new InvalidArgumentException('Apparent orientation derives phase from datetime; omit phase parameters.');
            if ($latitude === null || $longitude === null || $timezone === null || $datetime === null) throw new InvalidArgumentException('Apparent orientation requires latitude, longitude, timezone, and datetime.');
            try { $zone = new DateTimeZone($timezone); } catch (\Throwable) { throw new InvalidArgumentException("Invalid IANA timezone: {$timezone}."); }
            try { $instant = $datetime instanceof DateTimeImmutable ? $datetime : new DateTimeImmutable((string) $datetime); } catch (\Throwable) { throw new InvalidArgumentException('Datetime must be valid and include a UTC offset.'); }
            if (!preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', (string) $datetime) && !$datetime instanceof DateTimeImmutable) throw new InvalidArgumentException('Datetime must include a UTC offset.');
            if ($instant->getOffset() !== $zone->getOffset($instant)) throw new InvalidArgumentException('Datetime offset does not match timezone at that instant.');
            $observer = new AstronomyObserver($latitude, $longitude, $timezone);
            $geometry = (new MoonBrightLimbCalculator())->calculate($instant, $observer);
            $angle = $geometry['phase_angle_degrees'];
        } else {
            if ($byAge === $byIllumination) throw new InvalidArgumentException('Provide exactly age_days, or both illumination_percent and phase_direction.');
            if ($byIllumination && (!isset($options['illumination_percent'], $options['phase_direction']))) throw new InvalidArgumentException('Illumination percent and phase direction must be provided together.');
            if ($orientation === 'hemisphere' && $latitude === null) throw new InvalidArgumentException('Hemisphere orientation requires latitude.');
            if ($orientation === 'fixed' && $latitude !== null) throw new InvalidArgumentException('Latitude is only valid with hemisphere or apparent orientation.');
            if ($longitude !== null || $timezone !== null || $datetime !== null) throw new InvalidArgumentException('Longitude, timezone, and datetime are only valid with apparent orientation.');
            if ($byAge) {
                if (!is_numeric($options['age_days']) || !is_finite((float) $options['age_days']) || $options['age_days'] < 0 || $options['age_days'] >= self::SYNODIC_MONTH_DAYS) throw new InvalidArgumentException('Age days must be within one synodic month.');
                $angle = (float) $options['age_days'] / self::SYNODIC_MONTH_DAYS * 360.0;
            } else {
                $illumination = $options['illumination_percent'];
                $direction = $options['phase_direction'];
                if (!is_numeric($illumination) || !is_finite((float) $illumination) || $illumination < 0 || $illumination > 100) throw new InvalidArgumentException('Illumination percent must be between 0 and 100.');
                if (!in_array($direction, ['waxing', 'waning'], true)) throw new InvalidArgumentException("Phase direction must be 'waxing' or 'waning'.");
                $principal = rad2deg(acos(1.0 - 2.0 * ((float) $illumination / 100.0)));
                $angle = $direction === 'waxing' ? $principal : fmod(-$principal + 360.0, 360.0);
            }
        }
        return [
            'age_days' => $byAge ? (float) $options['age_days'] : null,
            'illumination_percent' => $byIllumination ? (float) $options['illumination_percent'] : null,
            'phase_direction' => $byIllumination ? $options['phase_direction'] : null,
            'phase_angle_degrees' => $angle,
            'size' => $size,
            'shadow_floor' => (float) $shadow,
            'terminator_softness' => (float) $softness,
            'orientation' => $orientation,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'datetime' => isset($instant) ? $instant->format(DATE_ATOM) : null,
            'instant_utc' => isset($instant) ? $instant->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM) : null,
            'geometry' => $geometry,
        ];
    }

    /** @param array<string,mixed> $options */
    private function shade(array $options): GdImage
    {
        $source = @imagecreatefrompng($this->texturePath);
        if (!$source instanceof GdImage) throw new RuntimeException("Base Moon image not found or unreadable at {$this->texturePath}.");
        $size = $options['size'];
        $texture = imagecreatetruecolor($size, $size);
        $sourceSize = min(imagesx($source), imagesy($source));
        $sourceX = intdiv(imagesx($source) - $sourceSize, 2);
        $sourceY = intdiv(imagesy($source) - $sourceSize, 2);
        imagecopyresampled($texture, $source, 0, 0, $sourceX, $sourceY, $size, $size, $sourceSize, $sourceSize);
        $result = imagecreatetruecolor($size, $size);
        imagealphablending($result, false);
        imagesavealpha($result, true);
        $theta = deg2rad(fmod($options['phase_angle_degrees'], 360.0));
        $sx = -sin($theta);
        $sz = -cos($theta);
        for ($pixelY = 0; $pixelY < $size; $pixelY++) {
            $y = 1.0 - 2.0 * $pixelY / ($size - 1);
            for ($pixelX = 0; $pixelX < $size; $pixelX++) {
                $x = -1.0 + 2.0 * $pixelX / ($size - 1);
                $radiusSquared = $x * $x + $y * $y;
                if ($radiusSquared > 1.0) {
                    imagesetpixel($result, $pixelX, $pixelY, 0x7F000000);
                    continue;
                }
                $z = sqrt(max(0.0, 1.0 - $radiusSquared));
                $geometry = $x * $sx + $z * $sz;
                if ($options['terminator_softness'] == 0.0) {
                    $illuminated = $geometry >= 0.0 ? 1.0 : 0.0;
                } else {
                    $transition = max(0.0, min(1.0, ($geometry + $options['terminator_softness']) / (2.0 * $options['terminator_softness'])));
                    $illuminated = $transition * $transition * (3.0 - 2.0 * $transition);
                }
                $light = $options['shadow_floor'] + (1.0 - $options['shadow_floor']) * $illuminated;
                $color = imagecolorat($texture, $pixelX, $pixelY);
                $red = (int) floor((($color >> 16) & 0xFF) * $light);
                $green = (int) floor((($color >> 8) & 0xFF) * $light);
                $blue = (int) floor(($color & 0xFF) * $light);
                imagesetpixel($result, $pixelX, $pixelY, ($red << 16) | ($green << 8) | $blue);
            }
        }
        return $result;
    }

    /** @param array<string,mixed> $options */
    private function orient(GdImage $image, array $options): GdImage
    {
        if ($options['orientation'] === 'fixed') return $image;
        if ($options['orientation'] === 'hemisphere') {
            if ($options['latitude'] > 0) imageflip($image, IMG_FLIP_HORIZONTAL);
            return $image;
        }
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagesetinterpolation($image, IMG_BICUBIC);
        $rotated = imagerotate($image, $options['geometry']['rotation_degrees'], $transparent);
        if (!$rotated instanceof GdImage) throw new RuntimeException('Could not rotate Moon image.');
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);
        $size = $options['size'];
        $cropped = imagecreatetruecolor($size, $size);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        imagefill($cropped, 0, 0, imagecolorallocatealpha($cropped, 0, 0, 0, 127));
        imagecopy($cropped, $rotated, 0, 0, intdiv(imagesx($rotated) - $size, 2), intdiv(imagesy($rotated) - $size, 2), $size, $size);
        return $cropped;
    }

    /** @param array<string,mixed> $options */
    private function orientationKey(array $options): string
    {
        if ($options['orientation'] === 'hemisphere') return sprintf('latitude=%.8f', $options['latitude']);
        if ($options['orientation'] === 'apparent') return sprintf('latitude=%.8f|longitude=%.8f|timezone=%s|instant=%s|rotation=%.9f', $options['latitude'], $options['longitude'], $options['timezone'], $options['instant_utc'], $options['geometry']['rotation_degrees']);
        return '';
    }

    /** @param array<string,mixed> $options @return array<string,mixed> */
    private function metadata(array $options, string $sourceHash): array
    {
        return ['parameters' => $options, 'texture' => ['path' => $this->texturePath, 'sha256' => $sourceHash], 'renderer' => 'php-gd-unit-sphere-v1', 'transform' => match ($options['orientation']) {'fixed' => 'none', 'hemisphere' => $options['latitude'] > 0 ? 'horizontal_flip' : 'none', 'apparent' => 'gd_bicubic_rotate_crop'}];
    }

    private function textureHash(): string
    {
        if (!is_file($this->texturePath)) throw new RuntimeException("Base Moon image not found at {$this->texturePath}.");
        $hash = hash_file('sha256', $this->texturePath);
        if ($hash === false) throw new RuntimeException('Could not hash base Moon image.');
        return $hash;
    }

    private function nullableFloat(mixed $value, float $minimum, float $maximum, string $name): ?float
    {
        if ($value === null) return null;
        if (!is_numeric($value) || !is_finite((float) $value) || $value < $minimum || $value > $maximum) throw new InvalidArgumentException("{$name} is invalid.");
        return (float) $value;
    }

    private function milliseconds(int $start): float { return (hrtime(true) - $start) / 1e6; }
}
