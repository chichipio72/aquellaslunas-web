<?php

declare(strict_types=1);

require_once __DIR__ . '/tonight.php';
require_once __DIR__ . '/moon-images.php';
require_once __DIR__ . '/photography-links.php';
require_once __DIR__ . '/home-eclipse-notice.php';
require_once __DIR__ . '/event-presentation.php';

/** @return array<string,mixed>|null */
function homeTonightMoonSceneModel(?array $data, array $events, DateTimeImmutable $now, string $timezone, float $latitude, float $longitude, ?array $photographyLocation = null): ?array
{
    if ($data === null) return null;
    $encounters = astronomyTonightPrioritizedMoonEncounters($data, $events, $now, $timezone);
    $anchorId = (string) ($encounters[0]['id'] ?? '');
    if ($anchorId === '') {
        $formalIds = astronomyTonightFormalConjunctionTargetIds($data, $events, $timezone);
        $formalEncounters = [];
        foreach (is_array($data['moon_encounters'] ?? null) ? $data['moon_encounters'] : [] as $encounter) {
            $id = is_array($encounter) && is_string($encounter['id'] ?? null) ? trim($encounter['id']) : '';
            if ($id !== '' && isset($formalIds[$id]) && in_array($encounter['object_kind'] ?? '', ['planet', 'star'], true)) {
                $formalEncounters[] = $encounter;
            }
        }
        usort($formalEncounters, static fn(array $first, array $second): int =>
            [($first['object_kind'] ?? '') === 'planet' ? 0 : 1, (float) ($first['minimum_separation_degrees'] ?? INF)]
            <=> [($second['object_kind'] ?? '') === 'planet' ? 0 : 1, (float) ($second['minimum_separation_degrees'] ?? INF)]
        );
        $anchorId = (string) ($formalEncounters[0]['id'] ?? '');
    }
    if ($anchorId === '') return null;
    $scene = null;
    foreach (is_array($data['moon_scenes'] ?? null) ? $data['moon_scenes'] : [] as $candidate) {
        if (is_array($candidate) && ($candidate['anchor_id'] ?? '') === $anchorId) { $scene = $candidate; break; }
    }
    if (!is_array($scene) || !is_array($scene['moon'] ?? null)) return null;
    $objects = array_values(array_filter(is_array($scene['objects'] ?? null) ? $scene['objects'] : [], static function ($object): bool {
        return is_array($object) && in_array($object['object_kind'] ?? '', ['planet', 'star'], true)
            && is_numeric($object['separation_degrees'] ?? null) && (float) $object['separation_degrees'] <= 10.0
            && is_numeric($object['relative_x_degrees'] ?? null) && is_numeric($object['relative_y_degrees'] ?? null);
    }));
    $starMagnitudes = [];
    foreach (is_array($data['stars'] ?? null) ? $data['stars'] : [] as $star) {
        $id = is_array($star) && is_string($star['id'] ?? null) ? trim($star['id']) : '';
        if ($id !== '' && is_numeric($star['magnitude'] ?? null)) $starMagnitudes[$id] = (float) $star['magnitude'];
    }
    foreach ($objects as &$object) {
        $id = is_string($object['id'] ?? null) ? trim($object['id']) : '';
        if (($object['object_kind'] ?? '') === 'star' && isset($starMagnitudes[$id])) $object['magnitude'] = $starMagnitudes[$id];
    }
    unset($object);
    if ($objects === []) return null;
    $moon = $scene['moon'];
    $image = moonPhaseThumbnail($moon['illumination_percent'] ?? null, $moon['age_days'] ?? null, $latitude);
    $instant = astronomyTonightDateTime($scene['datetime'] ?? null, $timezone);
    if ($image === null || $instant === null || $instant < $now || !is_numeric($moon['altitude_degrees'] ?? null)) return null;
    $diameter = is_numeric($moon['angular_diameter_degrees'] ?? null) ? (float) $moon['angular_diameter_degrees'] : 0.5;
    $radius = max(0.2, $diameter / 2);
    $xs = [-$radius, $radius]; $ys = [-$radius, $radius];
    foreach ($objects as $object) { $xs[] = (float) $object['relative_x_degrees']; $ys[] = (float) $object['relative_y_degrees']; }
    $margin = max(0.7, $diameter * 1.5);
    $minX = min($xs) - $margin; $maxX = max($xs) + $margin;
    $minY = min($ys) - $margin; $maxY = max($ys) + $margin;
    $moonAltitude = (float) $moon['altitude_degrees'];
    $horizonVisible = $moonAltitude <= max(3.0, max(array_map('abs', $ys)) + 1.5);
    if ($horizonVisible) $maxY = max($maxY, $moonAltitude + 0.35);
    $width = max(3.0, $maxX - $minX); $height = max(2.4, $maxY - $minY); $aspect = 1.65;
    if ($width / $height < $aspect) { $extra = ($height * $aspect - $width) / 2; $minX -= $extra; $maxX += $extra; }
    else { $extra = ($width / $aspect - $height) / 2; $minY -= $extra; $maxY += $extra; }
    $rotation = moonApparentRotation($instant, $latitude, $longitude, $timezone);
    $model = ['datetime' => $instant, 'anchor_id' => $anchorId, 'moon' => $moon, 'moon_image' => $image['url'], 'moon_rotation' => (float) ($rotation['css_degrees'] ?? 0),
        'objects' => $objects, 'bounds' => [$minX, $minY, $maxX - $minX, $maxY - $minY],
        'font_size' => max(0.09, ($maxX - $minX) * 0.026), 'horizon_visible' => $horizonVisible,
        'horizon_y' => $moonAltitude, 'moon_diameter' => $diameter];
    $model['photography_url'] = $photographyLocation !== null ? photographyTonightSceneUrl($model, $photographyLocation) : null;
    return $model;
}

/** @return array{kind:string,text:?string,eclipse_text:?string,scene:?array,eclipse:?array} */
function homeTonightHighlightModel(
    ?array $data,
    array $events,
    array $eclipseEvents,
    DateTimeImmutable $now,
    string $timezone,
    float $latitude,
    float $longitude,
    array $location
): array {
    $timezoneObject = new DateTimeZone($timezone);
    $usualText = astronomyTonightCardText($data, $events, $now, $timezone);
    $candidates = [];
    foreach ($eclipseEvents as $event) {
        if (!is_array($event) || ($event['type'] ?? '') !== 'eclipse' || ($event['subtype'] ?? '') !== 'lunar_eclipse') continue;
        if (homeEclipseVisibilityClassification($event) === 'not_visible' || homeEclipseVisibilityClassification($event) === '') continue;
        try {
            $date = (new DateTimeImmutable((string) ($event['datetime'] ?? '')))->setTimezone($timezoneObject);
        } catch (Throwable) {
            continue;
        }
        if (!astronomyEventIsFuture($date, $now)) continue;
        if (astronomyEventObservationalPeriod($date, $now) === 'tonight') $candidates[] = ['event' => $event, 'date' => $date];
    }
    usort($candidates, static fn(array $first, array $second): int => $first['date'] <=> $second['date']);
    if ($candidates !== []) {
        $candidate = $candidates[0];
        $presentation = astronomyEventPresentation($candidate['event'], $timezone);
        $visibility = astronomyEclipseVisibilityKey('lunar_eclipse', homeEclipseVisibilityClassification($candidate['event']));
        $title = trim((string) ($presentation['title'] ?? 'Eclipse de Luna'));
        $eclipseText = astronomyEditorialText('tonight.card.lunar_eclipse', [
            'eclipse' => 'un ' . lcfirst($title),
            'hora' => $candidate['date']->format('H:i'),
        ]);
        if ($visibility !== null) $eclipseText .= ' ' . $visibility . '.';
        return ['kind' => 'lunar_eclipse', 'text' => homeTonightMergeEclipseText($eclipseText, $usualText), 'eclipse_text' => $eclipseText, 'scene' => null, 'eclipse' => $candidate['event']];
    }
    return [
        'kind' => 'usual',
        'text' => $usualText,
        'eclipse_text' => null,
        'scene' => homeTonightMoonSceneModel($data, $events, $now, $timezone, $latitude, $longitude, $location),
        'eclipse' => null,
    ];
}

function renderHomeTonightMoonScene(array $model, bool $includePhotographyLink = true): void
{
    [$x, $y, $width, $height] = $model['bounds']; $font = (float) $model['font_size']; $diameter = (float) $model['moon_diameter'];
    $format = static fn(float $value): string => number_format($value, 4, '.', '');
    static $renderCount = 0;
    $gradientPrefix = 'tonight-star-' . (++$renderCount);
    ?>
    <figure class="home-tonight-scene">
        <svg viewBox="<?= $format($x) ?> <?= $format($y) ?> <?= $format($width) ?> <?= $format($height) ?>" role="img" aria-labelledby="home-tonight-scene-title home-tonight-scene-desc">
            <title id="home-tonight-scene-title">Posición relativa de la Luna y los astros cercanos</title>
            <desc id="home-tonight-scene-desc">Esquema angular calculado para las <?= htmlspecialchars($model['datetime']->format('H:i'), ENT_QUOTES, 'UTF-8') ?>. El diámetro lunar sirve como referencia de escala.</desc>
            <defs>
                <linearGradient id="<?= $gradientPrefix ?>-horizontal" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#eef6ff" stop-opacity="0"/><stop offset=".42" stop-color="#f7fbff" stop-opacity=".12"/><stop offset=".5" stop-color="#fff" stop-opacity=".95"/><stop offset=".58" stop-color="#f7fbff" stop-opacity=".12"/><stop offset="1" stop-color="#eef6ff" stop-opacity="0"/></linearGradient>
                <linearGradient id="<?= $gradientPrefix ?>-vertical" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#eef6ff" stop-opacity="0"/><stop offset=".42" stop-color="#f7fbff" stop-opacity=".12"/><stop offset=".5" stop-color="#fff" stop-opacity=".95"/><stop offset=".58" stop-color="#f7fbff" stop-opacity=".12"/><stop offset="1" stop-color="#eef6ff" stop-opacity="0"/></linearGradient>
            </defs>
            <?php if ($model['horizon_visible']): ?><g class="home-tonight-scene__horizon"><line x1="<?= $format($x) ?>" y1="<?= $format($model['horizon_y']) ?>" x2="<?= $format($x + $width) ?>" y2="<?= $format($model['horizon_y']) ?>"/><text x="<?= $format($x + .2) ?>" y="<?= $format($model['horizon_y'] - .12) ?>" style="font-size:<?= $format($font * .72) ?>px">horizonte</text></g><?php endif; ?>
            <image class="home-tonight-scene__moon" href="<?= htmlspecialchars($model['moon_image'], ENT_QUOTES, 'UTF-8') ?>" x="<?= $format(-$diameter / 2) ?>" y="<?= $format(-$diameter / 2) ?>" width="<?= $format($diameter) ?>" height="<?= $format($diameter) ?>" transform="rotate(<?= $format($model['moon_rotation']) ?> 0 0)"/>
            <text class="home-tonight-scene__moon-label" x="<?= $format($diameter * .7) ?>" y="<?= $format(-$diameter * .65) ?>" style="font-size:<?= $format($font) ?>px">Luna</text>
            <?php foreach ($model['objects'] as $object): $ox = (float) $object['relative_x_degrees']; $oy = (float) $object['relative_y_degrees']; $id = (string) $object['id']; $kind = (string) $object['object_kind']; $symbol = max(.07, min(.14, $width * .012)); $right = $ox >= 0; $magnitude = is_numeric($object['magnitude'] ?? null) ? (float) $object['magnitude'] : 1.0; $starStrength = max(0.0, min(1.0, (3.0 - $magnitude) / 4.5)); $starCore = $symbol * (.22 + .28 * $starStrength); $starHalo = $symbol * (.7 + 1.15 * $starStrength); $starRay = $symbol * (.7 + 2.15 * $starStrength); $starRayWidth = $symbol * (.035 + .055 * $starStrength); ?>
                <g class="home-tonight-scene__object home-tonight-scene__object--<?= htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') ?> home-tonight-scene__object--<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" transform="translate(<?= $format($ox) ?> <?= $format($oy) ?>)">
                    <?php if ($id === 'saturn'): ?><ellipse class="home-tonight-scene__saturn-ring" rx="<?= $format($symbol * 1.8) ?>" ry="<?= $format($symbol * .65) ?>"/><circle r="<?= $format($symbol * .72) ?>"/>
                    <?php elseif ($kind === 'star'): ?><g class="home-tonight-scene__star" transform="rotate(11)"><circle class="home-tonight-scene__star-halo" r="<?= $format($starHalo) ?>" style="opacity:<?= $format(.08 + .24 * $starStrength) ?>"/><?php if ($starStrength > .12): ?><path class="home-tonight-scene__star-ray" d="M <?= $format(-$starRay) ?> 0 L 0 <?= $format(-$starRayWidth) ?> L <?= $format($starRay) ?> 0 L 0 <?= $format($starRayWidth) ?> Z" fill="url(#<?= $gradientPrefix ?>-horizontal)" style="opacity:<?= $format(.15 + .65 * $starStrength) ?>"/><path class="home-tonight-scene__star-ray" d="M 0 <?= $format(-$starRay) ?> L <?= $format($starRayWidth) ?> 0 L 0 <?= $format($starRay) ?> L <?= $format(-$starRayWidth) ?> 0 Z" fill="url(#<?= $gradientPrefix ?>-vertical)" style="opacity:<?= $format(.15 + .65 * $starStrength) ?>"/><?php endif; ?><circle class="home-tonight-scene__star-core" r="<?= $format($starCore) ?>"/></g>
                    <?php else: ?><circle r="<?= $format($symbol) ?>"/><?php endif; ?>
                    <text x="<?= $format($right ? $symbol * 2.2 : -$symbol * 2.2) ?>" y="<?= $format($oy <= 0 ? -$symbol * 1.35 : $font) ?>" text-anchor="<?= $right ? 'start' : 'end' ?>" style="font-size:<?= $format($font) ?>px"><?= htmlspecialchars((string) $object['name'], ENT_QUOTES, 'UTF-8') ?></text>
                </g>
            <?php endforeach; ?>
        </svg>
        <figcaption><span>Posiciones a las <?= htmlspecialchars($model['datetime']->format('H:i'), ENT_QUOTES, 'UTF-8') ?> · escala angular</span><?php if ($includePhotographyLink) renderPhotographyEventLink(is_string($model['photography_url'] ?? null) ? $model['photography_url'] : null, 'home-tonight-scene__photography'); ?></figcaption>
    </figure>
    <?php
}

function homeTonightMergeEclipseText(string $eclipseText, ?string $usualText): string
{
    $usualText = trim((string) $usualText);
    if ($usualText === '') return $eclipseText;
    $tonightPrefix = trim(astronomyEditorialText('tonight.card.prefix.tonight'));
    $additionalPrefix = trim(astronomyEditorialText('tonight.card.prefix.also'));
    $andConnector = trim(astronomyEditorialText('tonight.card.connector.and'));
    if ($tonightPrefix !== '' && str_starts_with($usualText, $tonightPrefix . ' ')) {
        $usualText = substr($usualText, strlen($tonightPrefix) + 1);
    }
    if ($additionalPrefix !== '' && $andConnector !== '') {
        $usualText = str_replace('. ' . $additionalPrefix . ' ', ' ' . $andConnector . ' ', $usualText);
    }
    return trim($eclipseText . ' ' . $additionalPrefix . ' ' . $usualText);
}
