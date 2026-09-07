<?php

declare(strict_types=1);

require_once __DIR__ . '/home-sky.php';

/**
 * Modelo editorial acotado para infografías de acercamientos Luna-planeta y eclipses.
 * No consulta ni recalcula astronomía: recibe exclusivamente eventos ya normalizados.
 */

function astronomyEventInfographicDateTime($value, string $timezoneName): ?DateTimeImmutable
{
    if (!is_string($value) || trim($value) === '') return null;
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezoneName));
    } catch (Throwable) {
        return null;
    }
}

function astronomyEventInfographicHasUsableLocation(array $location): bool
{
    $name = is_string($location['name'] ?? null) ? trim($location['name']) : '';
    if ($name === '' || !is_numeric($location['latitude'] ?? null) || !is_numeric($location['longitude'] ?? null)) return false;
    $latitude = (float) $location['latitude'];
    $longitude = (float) $location['longitude'];
    if (!is_finite($latitude) || !is_finite($longitude) || $latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) return false;
    $timezoneName = is_string($location['timezone'] ?? null) ? trim($location['timezone']) : '';
    if ($timezoneName === '') return false;
    try {
        new DateTimeZone($timezoneName);
    } catch (Throwable) {
        return false;
    }
    return true;
}

function astronomyEventInfographicPlanetId(array $event): ?string
{
    if (($event['type'] ?? null) !== 'conjunction') return null;
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    // La API histórica no incluye object_kind; en ese contrato, la allowlist cerrada
    // de IDs planetarios aporta la misma discriminación sin aceptar estrellas.
    if (array_key_exists('object_kind', $details) && ($details['object_kind'] ?? null) !== 'planet') return null;
    $planet = is_string($details['planet'] ?? null) ? strtolower(trim($details['planet'])) : '';
    if ($planet === '' && is_string($event['subtype'] ?? null)) $planet = strtolower(trim($event['subtype']));
    return array_key_exists($planet, astronomyEventInfographicPlanetNames()) ? $planet : null;
}

/** @return array<string,string> */
function astronomyEventInfographicPlanetNames(): array
{
    return [
        'mercury' => 'Mercurio', 'venus' => 'Venus', 'mars' => 'Marte',
        'jupiter' => 'Júpiter', 'saturn' => 'Saturno', 'uranus' => 'Urano',
        'neptune' => 'Neptuno',
    ];
}

function astronomyEventInfographicIsEligible(array $event): bool
{
    if (($event['type'] ?? null) === 'eclipse') {
        return astronomyEclipseInfographicIsEligible($event);
    }
    $planet = astronomyEventInfographicPlanetId($event);
    if ($planet === null) return false;
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $classification = $details['visibility_classification'] ?? null;
    if ($classification === 'visible_at_closest_approach') {
        return astronomyEventInfographicValidInstant($event['datetime'] ?? null);
    }
    if ($classification === 'visible_nearby') {
        return astronomyEventInfographicValidInstant($details['best_visible_time'] ?? null);
    }
    return false;
}

function astronomyEclipseInfographicKind(array $event): ?string
{
    if (($event['type'] ?? null) !== 'eclipse') return null;
    return match ((string) ($event['subtype'] ?? '')) {
        'lunar_eclipse' => 'lunar_eclipse',
        'solar_eclipse' => 'solar_eclipse',
        default => null,
    };
}

/** @return array<string,mixed> */
function astronomyEclipseInfographicLocal(array $event): array
{
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $key = astronomyEclipseInfographicKind($event) === 'solar_eclipse' ? 'solar_eclipse_local' : 'eclipse_local';
    return is_array($details[$key] ?? null) ? $details[$key] : [];
}

function astronomyEclipseInfographicIsEligible(array $event): bool
{
    if (astronomyEclipseInfographicKind($event) === null) return false;
    $local = astronomyEclipseInfographicLocal($event);
    $classification = strtolower(trim((string) ($local['visibility_classification'] ?? '')));
    return $classification !== ''
        && $classification !== 'not_visible'
        && astronomyEventInfographicValidInstant($local['first_visible_instant'] ?? null)
        && astronomyEventInfographicValidInstant($local['last_visible_instant'] ?? null)
        && astronomyEventInfographicValidInstant($event['datetime'] ?? null);
}

function astronomyEclipseInfographicVisualMoment(array $event, string $timezoneName): ?DateTimeImmutable
{
    if (!astronomyEclipseInfographicIsEligible($event)) return null;
    $local = astronomyEclipseInfographicLocal($event);
    $maximum = astronomyEventInfographicDateTime($event['datetime'] ?? null, $timezoneName);
    $start = astronomyEventInfographicDateTime($local['first_visible_instant'] ?? null, $timezoneName);
    $end = astronomyEventInfographicDateTime($local['last_visible_instant'] ?? null, $timezoneName);
    if ($maximum === null || $start === null || $end === null || $end < $start) return null;
    if ($maximum < $start) return $start;
    if ($maximum > $end) return $end;
    return $maximum;
}

function astronomyEclipseInfographicVisibilityLabel(array $event): string
{
    $classification = strtolower(trim((string) (astronomyEclipseInfographicLocal($event)['visibility_classification'] ?? '')));
    return match ($classification) {
        'visible_total', 'total', 'visible_annular', 'annular', 'visible_hybrid', 'hybrid' => 'Visible completo',
        'visible_penumbral_only', 'penumbral' => 'Sólo es visible la fase penumbral',
        'visible_partial', 'partial' => 'Visible parcialmente',
        'not_visible' => 'No visible desde esta ubicación',
        default => 'Visibilidad no determinada',
    };
}

function astronomyEclipseInfographicTitle(array $event): string
{
    $kind = astronomyEclipseInfographicKind($event);
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $globalKey = $kind === 'solar_eclipse' ? 'solar_eclipse_global' : 'eclipse_global';
    $global = is_array($details[$globalKey] ?? null) ? $details[$globalKey] : [];
    $type = strtolower(trim((string) ($global['global_type'] ?? '')));
    $label = ['total' => 'total', 'partial' => 'parcial', 'penumbral' => 'penumbral', 'annular' => 'anular', 'hybrid' => 'híbrido'][$type] ?? '';
    return trim(($kind === 'solar_eclipse' ? 'Eclipse solar' : 'Eclipse lunar') . ($label !== '' ? ' ' . $label : ''));
}

/** @return array<string,mixed>|null */
function astronomyEclipseInfographicModel(array $event, array $location): ?array
{
    if (!astronomyEventInfographicHasUsableLocation($location)) return null;
    $kind = astronomyEclipseInfographicKind($event);
    $timezoneName = is_string($location['timezone'] ?? null) ? $location['timezone'] : '';
    $moment = astronomyEclipseInfographicVisualMoment($event, $timezoneName);
    $local = astronomyEclipseInfographicLocal($event);
    $start = astronomyEventInfographicDateTime($local['first_visible_instant'] ?? null, $timezoneName);
    $end = astronomyEventInfographicDateTime($local['last_visible_instant'] ?? null, $timezoneName);
    $city = is_string($location['name'] ?? null) ? trim($location['name']) : '';
    if ($kind === null || $moment === null || $start === null || $end === null || $city === '') return null;
    $solar = $kind === 'solar_eclipse';
    return [
        'kind' => $kind,
        'title' => astronomyEclipseInfographicTitle($event),
        'date' => astronomyEventInfographicSpanishDate($moment),
        'date_iso' => $moment->format('Y-m-d'),
        'city' => $city,
        'visibility' => astronomyEclipseInfographicVisibilityLabel($event),
        'explanation' => $solar
            ? 'La Luna pasará delante del Sol y ocultará una parte de su disco desde tu ubicación.'
            : 'La Luna atravesará la sombra de la Tierra y cambiará de brillo y color durante el eclipse.',
        'moments' => [
            ['label' => 'Inicio', 'time' => $start->format('H:i'), 'moment' => $start->format(DateTimeInterface::ATOM)],
            ['label' => 'Máximo', 'time' => $moment->format('H:i'), 'moment' => $moment->format(DateTimeInterface::ATOM)],
            ['label' => 'Fin', 'time' => $end->format('H:i'), 'moment' => $end->format(DateTimeInterface::ATOM)],
        ],
        'tip' => $solar
            ? 'Nunca mires el Sol sin anteojos para eclipses certificados.'
            : 'Buscá un lugar despejado y alejado de luces directas.',
        'visual' => ['kind' => $solar ? 'solar' : 'lunar', 'moment' => $moment->format(DateTimeInterface::ATOM)],
        'branding' => ['name' => 'Aquellas Lunas', 'domain' => 'aquellaslunas.com.ar'],
    ];
}

function astronomyEventInfographicValidInstant($value): bool
{
    if (!is_string($value) || trim($value) === '') return false;
    try {
        new DateTimeImmutable($value);
        return true;
    } catch (Throwable) {
        return false;
    }
}

function astronomyEventInfographicUnavailableReason(array $event): string
{
    if (astronomyEclipseInfographicKind($event) !== null) {
        $classification = strtolower(trim((string) (astronomyEclipseInfographicLocal($event)['visibility_classification'] ?? '')));
        if ($classification === 'not_visible') return 'Este eclipse no es visible desde la ubicación actual.';
        return 'No hay un intervalo local observable suficientemente definido para generar esta infografía.';
    }
    if (astronomyEventInfographicPlanetId($event) === null) {
        return 'Este evento no es un acercamiento entre la Luna y un planeta admitido.';
    }
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    if (($details['visibility_classification'] ?? null) === 'not_observable') {
        return 'La Luna y el planeta no coinciden en condiciones razonables de observación desde esta ubicación.';
    }
    if (($details['visibility_classification'] ?? null) === 'visible_nearby') {
        return 'No hay un momento cercano de observación suficientemente definido para generar esta infografía.';
    }
    return 'No hay suficiente información observacional para generar una infografía clara desde esta ubicación.';
}

function astronomyEventInfographicRecommendedMoment(array $event, string $timezoneName): ?DateTimeImmutable
{
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    if (($details['visibility_classification'] ?? null) === 'visible_nearby') {
        return astronomyEventInfographicDateTime($details['best_visible_time'] ?? null, $timezoneName);
    }
    if (($details['visibility_classification'] ?? null) === 'visible_at_closest_approach') {
        return astronomyEventInfographicDateTime($event['datetime'] ?? null, $timezoneName);
    }
    return null;
}

function astronomyEventInfographicTimeContext(DateTimeImmutable $moment): string
{
    $hour = (int) $moment->format('G');
    if ($hour >= 4 && $hour < 7) return 'Antes del amanecer';
    if ($hour >= 17 && $hour < 21) return 'Después del atardecer';
    if ($hour >= 21) return 'Durante la primera parte de la noche';
    if ($hour < 4) return 'Durante la madrugada';
    return 'Cuando el cielo empiece a oscurecer';
}

function astronomyEventInfographicSpanishDate(DateTimeImmutable $date): string
{
    $months = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return (int) $date->format('j') . ' de ' . $months[(int) $date->format('n')] . ' de ' . $date->format('Y');
}

function astronomyEventInfographicTip(string $planet): string
{
    return match ($planet) {
        'mercury' => 'Buscá un lugar abierto y mirá apenas el cielo comience a oscurecer.',
        'venus' => 'Elegí un lugar despejado y dejá que Venus te ayude a encontrar la Luna.',
        'mars' => 'Dales unos minutos a tus ojos para acostumbrarse a la oscuridad.',
        'jupiter', 'saturn' => 'Alejate de luces directas para disfrutar mejor el encuentro.',
        default => 'Buscá un cielo despejado y un lugar con pocas luces alrededor.',
    };
}

function astronomyEventInfographicObservationGuide(float $altitude,float $azimuth):string
{
    $configured=trim(homeMoonVisibleSituation($altitude,$azimuth));
    if($configured!=='')return$configured;
    $directions=['norte','noreste','este','sudeste','sur','sudoeste','oeste','noroeste'];$normalized=fmod(fmod($azimuth,360.0)+360.0,360.0);$direction=$directions[((int)floor(($normalized+22.5)/45.0))%8];
    if($altitude>=80.0)return'Está visible, prácticamente sobre tu cabeza.';
    if($altitude>=60.0)return'Está visible, muy alta. Mirá casi hacia arriba.';
    if($altitude<15.0)return'Está visible, muy baja hacia '.$direction.'.';
    if($altitude<35.0)return'Está visible, baja hacia '.$direction.'.';
    return'Está visible, a media altura hacia '.$direction.'.';
}

/** @return array<string,mixed>|null */
function astronomyConjunctionInfographicModel(array $event, array $location): ?array
{
    if (!astronomyEventInfographicHasUsableLocation($location)) return null;
    $timezoneName = is_string($location['timezone'] ?? null) ? $location['timezone'] : '';
    $planet = astronomyEventInfographicPlanetId($event);
    $moment = astronomyEventInfographicRecommendedMoment($event, $timezoneName);
    if ($planet === null || $moment === null || !astronomyEventInfographicIsEligible($event)) return null;
    $name = astronomyEventInfographicPlanetNames()[$planet];
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $city = is_string($location['name'] ?? null) ? trim($location['name']) : '';
    if ($city === '') return null;
    $illumination = is_numeric($details['illumination_percent'] ?? null) && is_finite((float) $details['illumination_percent'])
        ? max(0.0, min(100.0, (float) $details['illumination_percent'])) : 50.0;
    $relativeX = is_numeric($details['visual_relative_x_degrees'] ?? null) ? (float) $details['visual_relative_x_degrees'] : 1.0;
    $relativeY = is_numeric($details['visual_relative_y_degrees'] ?? null) ? (float) $details['visual_relative_y_degrees'] : 0.0;
    $relativeLength = hypot($relativeX, $relativeY);
    if (!is_finite($relativeLength) || $relativeLength < 1.0e-9) return null;
    $moonAltitude = is_numeric($details['visual_moon_altitude_degrees'] ?? null) ? (float) $details['visual_moon_altitude_degrees'] : null;
    $moonAzimuth = is_numeric($details['visual_moon_azimuth_degrees'] ?? null) ? (float) $details['visual_moon_azimuth_degrees'] : null;
    $observationGuide = $moonAltitude !== null && $moonAzimuth !== null && is_finite($moonAltitude) && is_finite($moonAzimuth)
        ? astronomyEventInfographicObservationGuide($moonAltitude, $moonAzimuth) : '';

    return [
        'kind' => 'lunar_conjunction',
        'title' => 'La Luna cerca de ' . $name,
        'date' => astronomyEventInfographicSpanishDate($moment),
        'date_iso' => $moment->format('Y-m-d'),
        'city' => $city,
        'explanation' => 'La Luna y ' . $name . ' compartirán una zona pequeña del cielo y formarán una escena fácil de reconocer.',
        'recommended_time' => 'Mirá alrededor de las ' . $moment->format('H:i'),
        'time_context' => astronomyEventInfographicTimeContext($moment),
        'observation_guide' => $observationGuide,
        'tip' => astronomyEventInfographicTip($planet),
        'visual' => [
            'planet' => $planet,
            'planet_name' => $name,
            'moon_illumination' => round($illumination, 1),
            'direction_x' => $relativeX / $relativeLength,
            'direction_y' => $relativeY / $relativeLength,
            // Fija la escena editorial al mismo instante recomendado. El renderer
            // sigue siendo didáctico y no interpreta este valor como coordenadas.
            'moment' => $moment->format(DateTimeInterface::ATOM),
        ],
        'branding' => ['name' => 'Aquellas Lunas', 'domain' => 'aquellaslunas.com.ar'],
    ];
}

/** @return array<string,mixed>|null */
function astronomyEventInfographicFind(array $items, string $localDate, string $planet, string $timezoneName): ?array
{
    foreach ($items as $event) {
        if (!is_array($event) || astronomyEventInfographicPlanetId($event) !== $planet) continue;
        $date = astronomyEventInfographicDateTime($event['datetime'] ?? null, $timezoneName);
        if ($date !== null && $date->format('Y-m-d') === $localDate) return $event;
    }
    return null;
}

/** @return array{type:string,date:string,target:string,date_value:DateTimeImmutable}|null */
function astronomyEventInfographicRequest(array $query, string $timezoneName): ?array
{
    $type = is_string($query['type'] ?? null) ? trim($query['type']) : '';
    $dateText = is_string($query['date'] ?? null) ? trim($query['date']) : '';
    // target es el nombre canónico. planet se conserva para URLs emitidas por la
    // primera versión del MVP y nunca se interpreta fuera de la allowlist.
    $targetValue = $query['target'] ?? $query['planet'] ?? null;
    $target = is_string($targetValue) ? strtolower(trim($targetValue)) : '';
    $validConjunction = $type === 'lunar_conjunction' && array_key_exists($target, astronomyEventInfographicPlanetNames());
    $validEclipse = in_array($type, ['lunar_eclipse', 'solar_eclipse'], true);
    if (!$validConjunction && !$validEclipse) return null;
    try {
        $timezone = new DateTimeZone($timezoneName);
    } catch (Throwable) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateText, $timezone);
    if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $dateText) return null;
    return ['type' => $type, 'date' => $dateText, 'target' => $validConjunction ? $target : '', 'date_value' => $date];
}

function astronomyEventInfographicUrl(array $event, string $timezoneName): ?string
{
    $eclipseKind = astronomyEclipseInfographicKind($event);
    if ($eclipseKind !== null) {
        if (!astronomyEclipseInfographicIsEligible($event) || !is_string($event['datetime'] ?? null)) return null;
        try {
            $date = (new DateTimeImmutable($event['datetime']))->setTimezone(new DateTimeZone($timezoneName))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
        return 'infografia-evento.php?' . http_build_query(['type' => $eclipseKind, 'date' => $date], '', '&', PHP_QUERY_RFC3986);
    }
    $planet = astronomyEventInfographicPlanetId($event);
    if ($planet === null || !is_string($event['datetime'] ?? null)) return null;
    try {
        $date = (new DateTimeImmutable($event['datetime']))->setTimezone(new DateTimeZone($timezoneName))->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
    return 'infografia-evento.php?' . http_build_query([
        'type' => 'lunar_conjunction', 'date' => $date, 'target' => $planet,
    ], '', '&', PHP_QUERY_RFC3986);
}

/** @return array<string,mixed>|null */
function astronomyEclipseInfographicFind(array $items, string $localDate, string $kind, string $timezoneName): ?array
{
    foreach ($items as $event) {
        if (!is_array($event) || astronomyEclipseInfographicKind($event) !== $kind) continue;
        $date = astronomyEventInfographicDateTime($event['datetime'] ?? null, $timezoneName);
        if ($date !== null && $date->format('Y-m-d') === $localDate) return $event;
    }
    return null;
}
