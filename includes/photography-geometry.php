<?php

declare(strict_types=1);

function photographySensorPresets(): array
{
    return [
        'full_frame' => ['label' => 'Full Frame 36 × 24 mm', 'width_mm' => 36.0, 'height_mm' => 24.0],
        'aps_c' => ['label' => 'APS-C Nikon/Sony/Fuji 23,5 × 15,6 mm', 'width_mm' => 23.5, 'height_mm' => 15.6],
        'aps_c_canon' => ['label' => 'APS-C Canon 22,3 × 14,9 mm', 'width_mm' => 22.3, 'height_mm' => 14.9],
        'mft' => ['label' => 'Micro Four Thirds 17,3 × 13 mm', 'width_mm' => 17.3, 'height_mm' => 13.0],
        'one_inch' => ['label' => '1 pulgada 13,2 × 8,8 mm', 'width_mm' => 13.2, 'height_mm' => 8.8],
        'custom' => ['label' => 'Personalizado', 'width_mm' => null, 'height_mm' => null],
    ];
}

function photographyFieldOfView(float $sensorWidth, float $sensorHeight, float $focal, string $orientation): array
{
    if ($sensorWidth <= 0 || $sensorHeight <= 0 || $focal <= 0) throw new InvalidArgumentException('Sensor y focal deben ser positivos.');
    if ($orientation === 'vertical') [$sensorWidth, $sensorHeight] = [$sensorHeight, $sensorWidth];
    return ['horizontal_degrees' => rad2deg(2 * atan($sensorWidth / (2 * $focal))), 'vertical_degrees' => rad2deg(2 * atan($sensorHeight / (2 * $focal)))];
}

/** @return array{x:float,y:float} */
function photographyCameraRollOffset(float $x, float $y, float $rollDegrees): array
{
    $angle = deg2rad($rollDegrees);
    $cosine = cos($angle); $sine = sin($angle);
    return ['x' => $cosine * $x + $sine * $y, 'y' => -$sine * $x + $cosine * $y];
}

function photographyHorizonIntersectsFrame(float $horizonY, array $center, array $fov, float $roll): bool
{
    $halfWidth = (float) $fov['horizontal_degrees'] / 2.0;
    $halfHeight = (float) $fov['vertical_degrees'] / 2.0;
    $angle = deg2rad($roll); $sine = sin($angle); $cosine = cos($angle);
    $relativeY = $horizonY - (float) $center['y_degrees'];
    if (abs($cosine) < 1e-9) {
        return abs($sine) > 1e-9 && abs($relativeY / $sine) <= $halfWidth;
    }
    $firstY = ($relativeY - $sine * -$halfWidth) / $cosine;
    $secondY = ($relativeY - $sine * $halfWidth) / $cosine;
    return min($firstY, $secondY) <= $halfHeight && max($firstY, $secondY) >= -$halfHeight;
}

/** @return array{visibility:string,in_frame:bool,outside_by_degrees:float,camera_x:float,camera_y:float} */
function photographyFrameElementVisibility(array $point, array $center, array $fov, float $roll): array
{
    $offset = photographyCameraRollOffset((float) $point['x'] - (float) $center['x_degrees'], (float) $point['y'] - (float) $center['y_degrees'], $roll);
    $halfWidth = (float) $fov['horizontal_degrees'] / 2.0; $halfHeight = (float) $fov['vertical_degrees'] / 2.0;
    if (($point['id'] ?? '') === 'horizon') {
        $visible = photographyHorizonIntersectsFrame((float) $point['y'], $center, $fov, $roll);
        return ['visibility' => $visible ? 'partial' : 'outside', 'in_frame' => $visible, 'outside_by_degrees' => $visible ? 0.0 : hypot(max(0.0, abs($offset['x']) - $halfWidth), max(0.0, abs($offset['y']) - $halfHeight)), 'camera_x' => $offset['x'], 'camera_y' => $offset['y']];
    }
    $radius = ($point['id'] ?? '') === 'moon' ? max(0.0, (float) ($point['diameter'] ?? 0.0) / 2.0) : 0.0;
    $fullyInside = abs($offset['x']) + $radius <= $halfWidth && abs($offset['y']) + $radius <= $halfHeight;
    $visible = abs($offset['x']) - $radius <= $halfWidth && abs($offset['y']) - $radius <= $halfHeight;
    $visibility = $fullyInside ? 'inside' : ($visible ? 'partial' : 'outside');
    $outsideX = max(0.0, abs($offset['x']) - $radius - $halfWidth); $outsideY = max(0.0, abs($offset['y']) - $radius - $halfHeight);
    return ['visibility' => $visibility, 'in_frame' => $visible, 'outside_by_degrees' => hypot($outsideX, $outsideY), 'camera_x' => $offset['x'], 'camera_y' => $offset['y']];
}

function photographyFraming(array $state, array $includedIds, bool $includeHorizon, float $sensorWidth, float $sensorHeight, float $focal, string $orientation, string $aim = 'automatic', float $manualOffsetX = 0.0, float $manualOffsetY = 0.0, float $roll = 0.0): array
{
    $points = [['id' => 'moon', 'x' => 0.0, 'y' => 0.0, 'diameter' => (float) $state['moon']['angular_diameter_degrees']]];
    foreach ($state['objects'] as $object) if (in_array($object['id'], $includedIds, true)) $points[] = ['id' => $object['id'], 'x' => (float) $object['relative_x_degrees'], 'y' => (float) $object['relative_y_degrees'], 'diameter' => 0.08];
    if ($includeHorizon) $points[] = ['id' => 'horizon', 'x' => 0.0, 'y' => (float) $state['horizon']['relative_y_degrees'], 'diameter' => 0.0];
    $xs = array_column($points, 'x'); $ys = array_column($points, 'y');
    $automaticCenter = ['x_degrees' => (min($xs) + max($xs)) / 2.0, 'y_degrees' => (min($ys) + max($ys)) / 2.0];
    $aim = in_array($aim, ['moon', 'manual'], true) ? $aim : 'automatic';
    $center = match ($aim) {
        'moon' => ['x_degrees' => 0.0, 'y_degrees' => 0.0],
        'manual' => ['x_degrees' => $automaticCenter['x_degrees'] + $manualOffsetX, 'y_degrees' => $automaticCenter['y_degrees'] + $manualOffsetY],
        default => $automaticCenter,
    };
    $rolledBounds = array_map(static function (array $point) use ($automaticCenter, $roll): array {
        $offset = photographyCameraRollOffset($point['x'] - $automaticCenter['x_degrees'], $point['y'] - $automaticCenter['y_degrees'], $roll);
        $radius = max(0.0, (float) ($point['diameter'] ?? 0.0) / 2.0);
        return ['min_x' => $offset['x'] - $radius, 'max_x' => $offset['x'] + $radius, 'min_y' => $offset['y'] - $radius, 'max_y' => $offset['y'] + $radius];
    }, $points);
    $spanX = max(0.1, max(array_column($rolledBounds, 'max_x')) - min(array_column($rolledBounds, 'min_x')));
    $spanY = max(0.1, max(array_column($rolledBounds, 'max_y')) - min(array_column($rolledBounds, 'min_y')));
    $fov = photographyFieldOfView($sensorWidth, $sensorHeight, $focal, $orientation);
    $orientedWidth = $orientation === 'vertical' ? $sensorHeight : $sensorWidth;
    $orientedHeight = $orientation === 'vertical' ? $sensorWidth : $sensorHeight;
    $limitX = $orientedWidth / (2 * tan(deg2rad($spanX) / 2));
    $limitY = $orientedHeight / (2 * tan(deg2rad($spanY) / 2));
    $maximum = min($limitX, $limitY);
    foreach ($points as &$point) {
        $visibility = photographyFrameElementVisibility($point, $center, $fov, $roll);
        $point['in_frame'] = $visibility['in_frame'];
        $point['frame_visibility'] = $visibility['visibility'];
        $point['outside_by_degrees'] = $visibility['outside_by_degrees'];
    }
    unset($point);
    return ['field_of_view' => $fov, 'center' => $center, 'automatic_center' => $automaticCenter, 'manual_offset' => ['x_degrees' => $manualOffsetX, 'y_degrees' => $manualOffsetY], 'aim' => $aim, 'roll_degrees' => $roll, 'points' => $points, 'required_span' => ['horizontal_degrees' => $spanX, 'vertical_degrees' => $spanY], 'maximum_focal_mm' => $maximum, 'suggested_focal_mm' => $maximum * 0.82];
}

function photographyCompositionOrientation(array $state, array $includedIds, bool $includeHorizon, string $preferredOrientation = 'horizontal', float $dominanceRatio = 1.15): string
{
    $preferredOrientation = $preferredOrientation === 'vertical' ? 'vertical' : 'horizontal';
    $moonRadius = max(0.0, (float) ($state['moon']['angular_diameter_degrees'] ?? 0.0) / 2.0);
    $xs = [-$moonRadius, $moonRadius];
    $ys = [-$moonRadius, $moonRadius];
    foreach (is_array($state['objects'] ?? null) ? $state['objects'] : [] as $object) {
        if (!in_array($object['id'] ?? null, $includedIds, true)) continue;
        $xs[] = (float) ($object['relative_x_degrees'] ?? 0.0) - 0.04;
        $xs[] = (float) ($object['relative_x_degrees'] ?? 0.0) + 0.04;
        $ys[] = (float) ($object['relative_y_degrees'] ?? 0.0) - 0.04;
        $ys[] = (float) ($object['relative_y_degrees'] ?? 0.0) + 0.04;
    }
    if ($includeHorizon) {
        $xs[] = 0.0;
        $ys[] = (float) ($state['horizon']['relative_y_degrees'] ?? 0.0);
    }
    $width = max($xs) - min($xs);
    $height = max($ys) - min($ys);
    $dominanceRatio = max(1.0, $dominanceRatio);
    if ($width > $height * $dominanceRatio) return 'horizontal';
    if ($height > $width * $dominanceRatio) return 'vertical';
    return $preferredOrientation;
}
