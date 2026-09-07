<?php

declare(strict_types=1);

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\MoonDiskAppearanceCalculator;

/** @return array{0:float,1:float,2:float} */
function moonCraterBodyVector(float $longitude, float $latitude): array
{
    $lon = deg2rad($longitude);
    $lat = deg2rad($latitude);
    return [cos($lat) * sin($lon), sin($lat), cos($lat) * cos($lon)];
}

/** @param array{0:float,1:float,2:float} $first @param array{0:float,1:float,2:float} $second */
function moonCraterDot(array $first, array $second): float
{
    return $first[0] * $second[0] + $first[1] * $second[1] + $first[2] * $second[2];
}

/** @return array<string,mixed> */
function moonCraterObservationCatalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) return $catalog;
    $contents = file_get_contents(__DIR__ . '/../assets/data/moon-crater-observation.json');
    if (!is_string($contents)) throw new RuntimeException('No se pudo leer el catálogo de observación lunar.');
    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || !is_array($decoded['craters'] ?? null)) {
        throw new RuntimeException('El catálogo de observación lunar no es válido.');
    }
    return $catalog = $decoded;
}

/** @return list<array<string,mixed>> */
function moonCraterRecommendations(?array $tonightData, array $location, DateTimeImmutable $now, int $limit = 5): array
{
    if ($tonightData === null || $limit < 1) return [];
    $timezone = (string) ($location['timezone'] ?? 'UTC');
    $start = astronomyTonightDateTime($tonightData['night']['start'] ?? null, $timezone);
    $end = astronomyTonightDateTime($tonightData['night']['end'] ?? null, $timezone);
    if ($start === null || $end === null || $end <= $start) return [];

    $observer = new AstronomyObserver(
        (float) ($location['latitude'] ?? 0),
        (float) ($location['longitude'] ?? 0),
        $timezone,
        (float) ($location['elevation_meters'] ?? 0),
    );
    $moonCalculator = new MeeusLunarCalculator();
    $bestInstant = null;
    $bestAltitude = -90.0;
    $sampleStart = $now > $start ? $now : $start;
    for ($instant = $sampleStart; $instant <= $end; $instant = $instant->modify('+30 minutes')) {
        $position = $moonCalculator->calculate(
            $instant,
            $observer->latitudeDegrees,
            $observer->longitudeDegrees,
            $observer->elevationMeters,
        );
        if ($position->altitudeDegrees >= 10.0 && $position->altitudeDegrees > $bestAltitude) {
            $bestAltitude = $position->altitudeDegrees;
            $bestInstant = $instant;
        }
    }
    if ($bestInstant === null) return [];

    $appearance = (new MoonDiskAppearanceCalculator())->calculate($bestInstant, $observer);
    $observerVector = array_values($appearance['surface_geometry']['subobserver']['body_fixed_unit_vector']);
    $solarVector = array_values($appearance['surface_geometry']['subsolar']['body_fixed_unit_vector']);
    $recommendations = [];
    foreach (moonCraterObservationCatalog()['craters'] as $crater) {
        $surface = moonCraterBodyVector((float) $crater['longitude'], (float) $crater['latitude']);
        $solarElevation = rad2deg(asin(max(-1.0, min(1.0, moonCraterDot($surface, $solarVector)))));
        $limbDistance = rad2deg(asin(max(-1.0, min(1.0, moonCraterDot($surface, $observerVector)))));
        if ($solarElevation < 0.7 || $solarElevation > 18.0 || $limbDistance < 8.0) continue;

        $terminatorScore = $solarElevation <= 4.0
            ? 35.0 + $solarElevation * 2.5
            : 45.0 - ($solarElevation - 4.0) * 2.5;
        $diameter = (float) $crater['diameter_km'];
        $score = $terminatorScore
            + min(22.0, 6.0 + log(max(1.0, $diameter / 20.0)) * 10.0)
            + min(12.0, max(0.0, ($limbDistance - 8.0) * 0.25))
            + (!empty($crater['has_depth']) ? 7.0 : 0.0)
            + (!empty($crater['has_central_peak']) ? 5.0 : 0.0)
            + (!empty($crater['has_rays']) ? 3.0 : 0.0)
            + (!empty($crater['scientific_profile']) ? 5.0 : 0.0)
            + (!empty($crater['curated']) ? 7.0 : 0.0);
        if ($score < 58.0) continue;

        if (!empty($crater['has_central_peak'])) {
            $reason = 'Buen momento para distinguir paredes y pico central.';
        } elseif (!empty($crater['has_depth'])) {
            $reason = 'La luz rasante favorece el contraste entre el borde y el fondo.';
        } elseif ($diameter >= 100.0) {
            $reason = 'Su gran diámetro y la iluminación rasante favorecen el relieve.';
        } else {
            $reason = 'Muy cerca del terminador: sombras favorables para apreciar el relieve.';
        }
        $recommendations[] = $crater + [
            'score' => round($score, 1),
            'favorability' => $score >= 82.0 ? 'Excelente' : ($score >= 70.0 ? 'Muy favorable' : 'Favorable'),
            'terminator_distance_degrees' => round($solarElevation, 1),
            'limb_distance_degrees' => round($limbDistance, 1),
            'reason' => $reason,
            'observation_time' => $bestInstant,
            'moon_altitude_degrees' => round($bestAltitude, 1),
            'url' => astronomyInternalUrl('luna-interactiva.php?' . http_build_query([
                'feature' => $crater['id'],
                'fecha' => $bestInstant->format('Y-m-d'),
                'hora' => $bestInstant->format('H:i'),
            ])),
        ];
    }
    usort($recommendations, static fn(array $a, array $b): int => [$b['score'], $b['diameter_km']] <=> [$a['score'], $a['diameter_km']]);
    return array_slice($recommendations, 0, $limit);
}

function renderMoonCraterRecommendations(array $recommendations, bool $compact = false): void
{
    if ($recommendations === []) return;
    if ($compact) {
        $primary = $recommendations[0];
        $also = array_slice($recommendations, 1, 2);
        ?>
        <aside class="moon-crater-recommendations moon-crater-recommendations--compact" aria-labelledby="home-crater-recommendations-title">
            <h3 id="home-crater-recommendations-title">Cráteres para mirar esta noche</h3>
            <p><a href="<?= htmlspecialchars($primary['url'], ENT_QUOTES, 'UTF-8') ?>"><strong><?= htmlspecialchars($primary['name']) ?></strong></a><br><?= number_format((float) $primary['diameter_km'], 0, ',', '.') ?> km · cerca del terminador<br><?= htmlspecialchars($primary['reason']) ?></p>
            <?php if ($also !== []): ?><p class="moon-crater-recommendations__also">También: <?php foreach ($also as $index => $crater): ?><?= $index > 0 ? ' · ' : '' ?><a href="<?= htmlspecialchars($crater['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($crater['name']) ?></a><?php endforeach; ?></p><?php endif; ?>
        </aside>
        <?php
        return;
    }
    ?>
    <section class="tonight-section moon-crater-recommendations" aria-labelledby="crater-recommendations-title">
        <h2 id="crater-recommendations-title">Cráteres para mirar esta noche</h2>
        <p class="moon-crater-recommendations__time">Selección para alrededor de las <?= htmlspecialchars($recommendations[0]['observation_time']->format('H:i')) ?>, con la Luna a <?= number_format((float) $recommendations[0]['moon_altitude_degrees'], 0, ',', '.') ?>° de altura.</p>
        <div class="tonight-object-list moon-crater-recommendations__list">
            <?php foreach ($recommendations as $crater): ?><article class="tonight-object moon-crater-recommendation">
                <p class="moon-crater-recommendation__favorability"><?= htmlspecialchars($crater['favorability']) ?></p>
                <h3><a href="<?= htmlspecialchars($crater['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($crater['name']) ?></a></h3>
                <p><?= number_format((float) $crater['diameter_km'], 0, ',', '.') ?> km · <?= number_format((float) $crater['terminator_distance_degrees'], 1, ',', '.') ?>° dentro del lado iluminado.</p>
                <p><?= htmlspecialchars($crater['reason']) ?></p>
            </article><?php endforeach; ?>
        </div>
    </section>
    <?php
}
