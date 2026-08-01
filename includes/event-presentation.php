<?php

require_once __DIR__ . '/presentation.php';
require_once __DIR__ . '/api-config.php';
require_once __DIR__ . '/event-type-configuration.php';
require_once __DIR__ . '/editorial-configuration.php';

function astronomyEventApplyConfiguredBaseName(array $event, array $presentation): array
{
    if (astronomyEventFriendlyNameIsCustomized($event)) {
        $name = astronomyEventFriendlyName($event);
        if ($name !== null) {
            $presentation['title'] = $name;
        }
    }
    return $presentation;
}

function astronomySupermoonMinApparentSizePercent(): float
{
    return astronomyEditorialNumber('event.supermoon.min_percent');
}

function astronomyEventDateTime($value, string $timezoneName): ?DateTimeImmutable
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezoneName));
    } catch (Exception $exception) {
        return null;
    }
}

function astronomyEventNumber($value, int $decimals = 1): ?string
{
    return is_numeric($value) && is_finite((float) $value)
        ? number_format((float) $value, $decimals, ',', '.')
        : null;
}

function astronomyEventPercent($value): ?string
{
    if (!is_numeric($value) || !is_finite((float) $value)) {
        return null;
    }
    $number = (float) $value;
    $decimals = abs($number - round($number)) < 0.05 ? 0 : 1;
    return number_format($number, $decimals, ',', '.') . '%';
}

function astronomyEventConjunctionObject(array $event): string
{
    $title = is_string($event['title'] ?? null) ? trim($event['title']) : '';
    $name = preg_replace('/^Conjunción\s+Luna\s*[–-]\s*/u', '', $title);
    return is_string($name) && $name !== '' && $name !== $title ? $name : 'Otro astro';
}

function astronomyEventMinuteQuantity(int $minutes): string
{
    return $minutes . ' ' . ($minutes === 1 ? 'minuto' : 'minutos');
}

function astronomyFullMoonObservationSummary(string $subtype, array $details): string
{
    if (!is_numeric($details['difference_minutes'] ?? null) || !is_string($details['temporal_classification'] ?? null)) {
        return '';
    }
    $minutes = abs((int) round((float) $details['difference_minutes']));
    if ($minutes === 0) {
        return $subtype === 'morning'
            ? 'La Luna se pondrá al mismo tiempo que salga el Sol.'
            : ($subtype === 'evening' ? 'La Luna saldrá al mismo tiempo que se ponga el Sol.' : '');
    }
    $quantity = astronomyEventMinuteQuantity($minutes);
    return match ($details['temporal_classification']) {
        'before_sunrise' => 'La Luna se pondrá ' . $quantity . ' antes de la salida del Sol.',
        'after_sunrise' => 'La Luna se pondrá ' . $quantity . ' después de la salida del Sol.',
        'before_sunset' => 'La Luna saldrá ' . $quantity . ' antes de la puesta del Sol.',
        'after_sunset' => 'La Luna saldrá ' . $quantity . ' después de la puesta del Sol.',
        default => '',
    };
}

function astronomyFullMoonObservationMoment(array $event, string $timezoneName): ?array
{
    if (($event['type'] ?? null) !== 'full_moon_observation') {
        return null;
    }
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $kind = is_string($details['moon_event'] ?? null) ? trim($details['moon_event']) : '';
    if (!in_array($kind, ['moonrise', 'moonset'], true)) {
        return null;
    }
    $date = astronomyEventDateTime($details['moon_event_time'] ?? null, $timezoneName);
    if ($date === null) {
        return null;
    }
    return [
        'label' => $kind === 'moonrise' ? 'Salida de la Luna' : 'Puesta de la Luna',
        'date' => $date,
    ];
}

function astronomyEarthshineEventIsDisplayable(array $event): bool
{
    if (($event['type'] ?? null) !== 'earthshine') {
        return true;
    }
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $offsetDays = is_numeric($details['offset_days'] ?? null) ? (int) $details['offset_days'] : null;
    $illumination = is_numeric($details['illumination_percent'] ?? null)
        && is_finite((float) $details['illumination_percent'])
        ? (float) $details['illumination_percent']
        : null;
    return !($offsetDays === 0 && $illumination !== null && $illumination < astronomyEditorialNumber('event.new_moon.max_illumination_percent'));
}

function astronomyEventRelationText(int $minutes, string $before, string $after): string
{
    $absolute = abs($minutes);
    $quantity = $absolute . ' min';
    if ($minutes < 0) {
        return $quantity . ' ' . $before;
    }
    if ($minutes > 0) {
        return $quantity . ' ' . $after;
    }
    return 'al mismo tiempo';
}

function astronomyEarthshineObservationDetails(array $event, string $timezoneName): ?array
{
    if (($event['type'] ?? null) !== 'earthshine') {
        return null;
    }
    $subtype = is_string($event['subtype'] ?? null) ? $event['subtype'] : '';
    if (!in_array($subtype, ['morning', 'evening'], true)) {
        return null;
    }
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $moonTime = astronomyEventDateTime($details['moon_event_time'] ?? null, $timezoneName);
    $solarTime = astronomyEventDateTime($details['solar_event_time'] ?? null, $timezoneName);
    $start = astronomyEventDateTime($details['start_time'] ?? ($event['datetime'] ?? null), $timezoneName);
    $end = astronomyEventDateTime($details['end_time'] ?? ($event['end_datetime'] ?? null), $timezoneName);
    $bestTime = astronomyEventDateTime($details['best_visible_time'] ?? null, $timezoneName) ?? $start;
    $illumination = is_numeric($details['illumination_percent'] ?? null)
        ? (float) $details['illumination_percent']
        : null;
    $separation = is_numeric($details['separation_degrees'] ?? null)
        ? (float) $details['separation_degrees']
        : null;
    $difference = is_numeric($details['difference_minutes'] ?? null)
        ? (int) round((float) $details['difference_minutes'])
        : null;
    if ($moonTime === null || $solarTime === null || $start === null || $end === null
        || $illumination === null || $separation === null || $difference === null) {
        return null;
    }

    $illuminationLabel = astronomyEventNumber($illumination, 1);
    $separationLabel = astronomyEventNumber($separation, 1);
    if ($illuminationLabel === null || $separationLabel === null) {
        return null;
    }
    $morning = $subtype === 'morning';
    $relation = astronomyEventRelationText($difference, 'antes', 'después');
    $moonLabel = $morning ? 'Salida de la Luna' : 'Puesta de la Luna';
    $solarLabel = $morning ? 'Salida del Sol' : 'Puesta del Sol';
    return [
        'period_label' => $morning ? 'Antes del amanecer' : 'Después del atardecer',
        'summary' => 'Iluminación ' . $illuminationLabel . ' % · Separación del Sol '
            . $separationLabel . '° · La Luna ' . ($morning ? 'saldrá ' : 'se pondrá ') . $relation,
        'moon_label' => $moonLabel,
        'moon_time' => $moonTime,
        'solar_label' => $solarLabel,
        'solar_time' => $solarTime,
        'first_label' => $morning ? $moonLabel : $solarLabel,
        'first_time' => $morning ? $moonTime : $solarTime,
        'second_label' => $morning ? $solarLabel : $moonLabel,
        'second_time' => $morning ? $solarTime : $moonTime,
        'start' => $start,
        'end' => $end,
        'cloud_time' => $bestTime,
        'earthshine_visible' => ($details['earthshine_visible'] ?? false) === true,
    ];
}

function astronomyEventTechnicalDetails(array $fields): array
{
    $result = [];
    foreach ($fields as $label => $value) {
        if ($value !== null && $value !== '') {
            $result[$label] = $value;
        }
    }
    return $result;
}

function astronomyLibrationDirectionMetadata(string $subtype, array $details): ?array
{
    $fromSubtype = match ($subtype) {
        'libration_east' => ['direction' => 'este', 'title' => 'Libración favorable hacia el este'],
        'libration_west' => ['direction' => 'oeste', 'title' => 'Libración favorable hacia el oeste'],
        'libration_north' => ['direction' => 'norte', 'title' => 'Libración favorable hacia el norte'],
        'libration_south' => ['direction' => 'sur', 'title' => 'Libración favorable hacia el sur'],
        default => null,
    };
    if ($fromSubtype !== null) {
        return $fromSubtype;
    }

    $direction = is_string($details['direction'] ?? null) ? trim(strtolower((string) $details['direction'])) : '';
    return match ($direction) {
        'east', 'este' => ['direction' => 'este', 'title' => 'Libración favorable hacia el este'],
        'west', 'oeste' => ['direction' => 'oeste', 'title' => 'Libración favorable hacia el oeste'],
        'north', 'norte' => ['direction' => 'norte', 'title' => 'Libración favorable hacia el norte'],
        'south', 'sur' => ['direction' => 'sur', 'title' => 'Libración favorable hacia el sur'],
        default => null,
    };
}

function astronomyMoonPhaseFriendlyLabel($phaseName): ?string
{
    if (!is_string($phaseName) || trim($phaseName) === '') {
        return null;
    }
    $normalized = strtolower(trim($phaseName));
    $normalized = str_replace(['-', '_'], ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    if (!is_string($normalized) || $normalized === '') {
        return null;
    }
    $labels = [
        'new moon' => 'Luna nueva',
        'first quarter' => 'Cuarto creciente',
        'full moon' => 'Luna llena',
        'last quarter' => 'Cuarto menguante',
        'waxing crescent' => 'Luna creciente fina',
        'waning crescent' => 'Luna menguante fina',
        'waxing gibbous' => 'Luna gibosa creciente',
        'waning gibbous' => 'Luna gibosa menguante',
    ];
    return $labels[$normalized] ?? capitalizeVisibleText($normalized);
}

function astronomyLibrationPhaseSummary(array $details): string
{
    $moonPhase = is_array($details['moon_phase'] ?? null) ? $details['moon_phase'] : [];
    $phaseLabel = astronomyMoonPhaseFriendlyLabel($moonPhase['name'] ?? null);
    $illumination = astronomyEventPercent($moonPhase['illumination_percent'] ?? null);
    if ($phaseLabel !== null && $illumination !== null) {
        return $phaseLabel . ', ' . $illumination . ' iluminada.';
    }
    if ($phaseLabel !== null) {
        return $phaseLabel . '.';
    }
    if ($illumination !== null) {
        return $illumination . ' iluminada.';
    }
    return '';
}

function astronomyEventAzimuthDirection($azimuthDegrees): ?string
{
    if (!is_numeric($azimuthDegrees) || !is_finite((float) $azimuthDegrees)) {
        return null;
    }
    $directions = ['norte', 'noreste', 'este', 'sudeste', 'sur', 'sudoeste', 'oeste', 'noroeste'];
    $normalized = fmod(fmod((float) $azimuthDegrees, 360.0) + 360.0, 360.0);
    return $directions[((int) floor(($normalized + 22.5) / 45.0)) % 8];
}

function astronomyEclipseContactCodeLabel(string $code): string
{
    return match ($code) {
        'MAX' => 'Máximo',
        default => $code,
    };
}

function astronomyEclipseContactCodesBySubtype(string $subtype): array
{
    return $subtype === 'lunar_eclipse'
        ? ['P1', 'U1', 'U2', 'MAX', 'U3', 'U4', 'P4']
        : ['C1', 'C2', 'MAX', 'C3', 'C4'];
}

function astronomyEclipseContactLines(array $localContacts, array $globalContacts, string $subtype, string $timezoneName): array
{
    $localTimes = [];
    foreach ($localContacts as $contact) {
        if (!is_array($contact)) {
            continue;
        }
        $code = is_string($contact['code'] ?? null) ? strtoupper(trim($contact['code'])) : '';
        $date = astronomyEventDateTime($contact['datetime'] ?? null, $timezoneName);
        if ($code !== '' && $date !== null) {
            $localTimes[$code] = $date->format('H:i');
        }
    }

    $globalTimes = [];
    foreach ($globalContacts as $code => $datetime) {
        $normalizedCode = is_string($code) ? strtoupper(trim($code)) : '';
        $date = astronomyEventDateTime($datetime, $timezoneName);
        if ($normalizedCode !== '' && $date !== null) {
            $globalTimes[$normalizedCode] = $date->format('H:i');
        }
    }

    $lines = [];
    foreach (astronomyEclipseContactCodesBySubtype($subtype) as $code) {
        $time = $localTimes[$code] ?? $globalTimes[$code] ?? null;
        if ($time !== null) {
            $lines[] = astronomyEclipseContactCodeLabel($code) . ' — ' . $time;
        }
    }
    return $lines;
}

function astronomyEclipseVisibilityKey(string $subtype, ?string $classification): ?string
{
    if ($classification === null || trim($classification) === '') {
        return null;
    }
    $normalized = strtolower(trim($classification));
    return match ($subtype) {
        'lunar_eclipse' => match ($normalized) {
            'not_visible' => 'No visible',
            'visible_penumbral_only' => 'Sólo fase penumbral',
            'visible_partial' => 'Visible parcialmente',
            'visible_total' => 'Visible en totalidad',
            default => null,
        },
        'solar_eclipse' => match ($normalized) {
            'not_visible' => 'No visible',
            'visible_partial', 'partial' => 'Parcial',
            'visible_annular', 'annular' => 'Anular',
            'visible_total', 'total' => 'Total',
            'visible_hybrid', 'hybrid' => 'Híbrido',
            default => null,
        },
        default => null,
    };
}

function astronomyEclipseVisibilityMessage(string $subtype, ?string $classification): string
{
    if ($classification === null || trim($classification) === '') {
        return astronomyEditorialText('eclipse.visibility.unknown');
    }
    $normalized = strtolower(trim($classification));
    if ($subtype === 'lunar_eclipse') {
        return match ($normalized) {
            'not_visible' => astronomyEditorialText('eclipse.lunar.not_visible'),
            'visible_penumbral_only' => astronomyEditorialText('eclipse.lunar.penumbral'),
            'visible_partial' => astronomyEditorialText('eclipse.lunar.partial'),
            'visible_total' => astronomyEditorialText('eclipse.lunar.total'),
            default => astronomyEditorialText('eclipse.visibility.unknown'),
        };
    }

    return match ($normalized) {
        'not_visible' => astronomyEditorialText('eclipse.solar.not_visible'),
        'visible_partial', 'partial' => astronomyEditorialText('eclipse.solar.partial'),
        'visible_annular', 'annular' => astronomyEditorialText('eclipse.solar.annular'),
        'visible_total', 'total' => astronomyEditorialText('eclipse.solar.total'),
        'visible_hybrid', 'hybrid' => astronomyEditorialText('eclipse.solar.hybrid'),
        default => astronomyEditorialText('eclipse.visibility.unknown'),
    };
}

function astronomyEclipseSolarObscurationPercent($value): ?string
{
    if (!is_numeric($value) || !is_finite((float) $value)) {
        return null;
    }
    $number = (float) $value;
    if ($number < 0) {
        return null;
    }
    if ($number <= 1.0) {
        $number *= 100.0;
    }
    return number_format($number, 1, ',', '.') . '%';
}

function astronomyEclipseDurationLabel($seconds): ?string
{
    if (!is_numeric($seconds) || !is_finite((float) $seconds)) {
        return null;
    }
    $totalSeconds = (int) round((float) $seconds);
    if ($totalSeconds <= 0) {
        return null;
    }
    $hours = intdiv($totalSeconds, 3600);
    $minutes = intdiv($totalSeconds % 3600, 60);
    $remainingSeconds = $totalSeconds % 60;
    if ($hours > 0) {
        return $hours . ' h ' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . ' min';
    }
    if ($minutes > 0) {
        return $minutes . ' min ' . $remainingSeconds . ' s';
    }
    return $remainingSeconds . ' s';
}

function astronomyLunarEclipseTitle(?string $globalType): string
{
    return match ($globalType) {
        'penumbral' => 'Eclipse lunar penumbral',
        'partial' => 'Eclipse lunar parcial',
        'total' => 'Eclipse lunar total',
        default => 'Eclipse lunar',
    };
}

function astronomySolarEclipseTitle(?string $globalType): string
{
    return match ($globalType) {
        'partial' => 'Eclipse solar parcial',
        'annular' => 'Eclipse solar anular',
        'total' => 'Eclipse solar total',
        'hybrid' => 'Eclipse solar híbrido',
        default => 'Eclipse solar',
    };
}

function astronomyEventPresentation(array $event, string $timezoneName): array
{
    $type = is_string($event['type'] ?? null) ? $event['type'] : '';
    $subtype = is_string($event['subtype'] ?? null) ? $event['subtype'] : '';
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $title = capitalizeVisibleText(is_string($event['title'] ?? null) && trim($event['title']) !== '' ? trim($event['title']) : 'Evento lunar');
    $date = astronomyEventDateTime($event['datetime'] ?? null, $timezoneName);
    $endDate = astronomyEventDateTime($event['end_datetime'] ?? null, $timezoneName);
    $timeLabel = $date?->format('H:i') ?? 'Hora no disponible';
    $presentation = [
        'title' => $title,
        'summary' => '',
        'show_time' => true,
        'time_label' => $timeLabel,
        'technical_details' => [],
        'explanation' => '',
        'public_details' => [],
        'contact_points' => [],
        'alert' => '',
        'observation' => null,
    ];

    if ($type === 'conjunction') {
        $objectName = astronomyEventConjunctionObject($event);
        $separation = is_numeric($details['separation_degrees'] ?? null) ? (float) $details['separation_degrees'] : null;
        if ($separation !== null && $separation <= astronomyEditorialNumber('event.conjunction.very_close_degrees')) {
            $presentation['title'] = astronomyEditorialText('event.conjunction.very_close', ['objeto' => $objectName]);
        } elseif ($separation !== null && $separation <= astronomyEditorialNumber('event.conjunction.close_degrees')) {
            $presentation['title'] = astronomyEditorialText('event.conjunction.close', ['objeto' => $objectName]);
        } elseif ($separation !== null) {
            $presentation['title'] = astronomyEditorialText('event.conjunction.other', ['objeto' => $objectName]);
        }
        $bothVisible = ($details['both_above_horizon'] ?? false) === true;
        $presentation['summary'] = $bothVisible ? astronomyEditorialText('event.conjunction.visible') : '';
        $presentation['explanation'] = $bothVisible
            ? astronomyEditorialText('event.conjunction.both_above')
            : astronomyEditorialText('event.conjunction.not_together');
        $presentation['technical_details'] = astronomyEventTechnicalDetails([
            'Separación angular' => ($value = astronomyEventNumber($details['separation_degrees'] ?? null, 2)) !== null ? $value . '°' : null,
            'Iluminación lunar' => astronomyEventPercent($details['illumination_percent'] ?? null),
            'Altura lunar' => ($value = astronomyEventNumber($details['moon_altitude_degrees'] ?? null, 1)) !== null ? $value . '°' : null,
        ]);
        return astronomyEventApplyConfiguredBaseName($event, $presentation);
    }

    if ($type === 'apsis' && in_array($subtype, ['perigee', 'apogee'], true)) {
        $isPerigee = $subtype === 'perigee';
        $presentation['title'] = $isPerigee
            ? 'La Luna estará en su punto más cercano a la Tierra'
            : 'La Luna estará en su punto más lejano de la Tierra';
        $presentation['summary'] = $isPerigee
            ? astronomyEditorialText('event.perigee.summary')
            : astronomyEditorialText('event.apogee.summary');
        $presentation['show_time'] = false;
        $presentation['time_label'] = '';
        $presentation['technical_details'] = astronomyEventTechnicalDetails([
            'Distancia' => ($value = astronomyEventNumber($details['distance_km'] ?? null, 0)) !== null ? $value . ' km' : null,
            'Tamaño relativo' => ($value = astronomyEventNumber($details['apparent_size_percent'] ?? null, 1)) !== null ? $value . '%' : null,
            'Iluminación' => astronomyEventPercent($details['illumination_percent'] ?? null),
        ]);
        return astronomyEventApplyConfiguredBaseName($event, $presentation);
    }

    if ($type === 'earthshine') {
        $observation = astronomyEarthshineObservationDetails($event, $timezoneName);
        $earthshineVisible = ($details['earthshine_visible'] ?? null) !== false;
        $presentation['title'] = $earthshineVisible
            ? 'La parte oscura de la Luna también será visible'
            : ($subtype === 'morning' ? 'Luna fina antes del amanecer' : 'Luna fina después del atardecer');
        $presentation['summary'] = $observation['summary'] ?? match ($subtype) {
            'morning' => 'Poco antes del amanecer, hacia el este.',
            'evening' => 'Poco después del atardecer, hacia el oeste.',
            default => '',
        };
        $presentation['time_label'] = $timeLabel . ($endDate !== null ? '–' . $endDate->format('H:i') : '');
        $presentation['observation'] = $observation;
        if ($observation !== null && $observation['earthshine_visible']) {
            $presentation['explanation'] = astronomyEditorialText('event.earthshine.explanation');
        }
        $presentation['technical_details'] = astronomyEventTechnicalDetails([
            'Carácter' => 'Ventana observacional estimada.',
            'Iluminación lunar' => astronomyEventPercent($details['illumination_percent'] ?? null),
            'Separación del Sol' => ($value = astronomyEventNumber($details['separation_degrees'] ?? null, 1)) !== null ? $value . '°' : null,
            'Salida o puesta de la Luna' => $observation !== null ? $observation['moon_time']->format('H:i') : null,
            'Salida o puesta del Sol' => $observation !== null ? $observation['solar_time']->format('H:i') : null,
            'Diferencia temporal' => is_numeric($details['difference_minutes'] ?? null)
                ? astronomyEventMinuteQuantity(abs((int) round((float) $details['difference_minutes'])))
                : null,
            'Altura lunar' => ($value = astronomyEventNumber($details['moon_altitude_degrees'] ?? null, 1)) !== null ? $value . '°' : null,
        ]);
        return astronomyEventApplyConfiguredBaseName($event, $presentation);
    }

    if ($type === 'moon_phase') {
        $apparentSizePercent = is_numeric($details['apparent_size_percent'] ?? null)
            && is_finite((float) $details['apparent_size_percent'])
            ? (float) $details['apparent_size_percent']
            : null;
        if (
            $subtype === 'full_moon'
            && $apparentSizePercent !== null
            && $apparentSizePercent >= astronomySupermoonMinApparentSizePercent()
        ) {
            $presentation['title'] = astronomyEditorialText('event.supermoon.title');
            $presentation['summary'] = astronomyEditorialText('event.supermoon.summary');
        }
        if (in_array($subtype, ['first_quarter', 'last_quarter'], true)) {
            $presentation['summary'] = astronomyEditorialText('event.quarter.summary');
        }
        $presentation['technical_details'] = astronomyEventTechnicalDetails([
            'Iluminación' => astronomyEventPercent($details['illumination_percent'] ?? null),
            'Distancia' => ($value = astronomyEventNumber($details['distance_km'] ?? null, 0)) !== null ? $value . ' km' : null,
            'Tamaño relativo' => ($value = astronomyEventNumber($apparentSizePercent, 1)) !== null ? $value . '%' : null,
        ]);
        return astronomyEventApplyConfiguredBaseName($event, $presentation);
    }

    if ($type === 'libration') {
        $directionMeta = astronomyLibrationDirectionMetadata($subtype, $details);
        $directionLabel = $directionMeta['direction'] ?? null;
        if ($directionMeta !== null) {
            $presentation['title'] = $directionMeta['title'];
        }

        $amplitudeValue = null;
        if (is_numeric($details['absolute_value_degrees'] ?? null) && is_finite((float) $details['absolute_value_degrees'])) {
            $amplitudeValue = (float) $details['absolute_value_degrees'];
        } elseif (is_numeric($details['value_degrees'] ?? null) && is_finite((float) $details['value_degrees'])) {
            $amplitudeValue = abs((float) $details['value_degrees']);
        }
        $amplitudeLabel = astronomyEventNumber($amplitudeValue, 1);

        if ($directionLabel !== null) {
            $summaryParts = ['En estos días la Luna deja ver un poco más de su borde ' . $directionLabel . '.'];
            if ($amplitudeLabel !== null) {
                $summaryParts[] = 'Amplitud aproximada: ' . $amplitudeLabel . '°.';
            }
            $presentation['summary'] = implode(' ', $summaryParts);
        } elseif ($amplitudeLabel !== null) {
            $presentation['summary'] = 'Amplitud aproximada: ' . $amplitudeLabel . '°.';
        }

        $phaseSummary = astronomyLibrationPhaseSummary($details);
        $observationNote = ($amplitudeValue !== null && $amplitudeValue >= astronomyEditorialNumber('event.libration.strong_degrees'))
            ? astronomyEditorialText('event.libration.strong')
            : astronomyEditorialText('event.libration.subtle');
        $presentation['explanation'] = trim($phaseSummary . ' ' . $observationNote);
        return astronomyEventApplyConfiguredBaseName($event, $presentation);
    }

    if ($type === 'eclipse' && in_array($subtype, ['lunar_eclipse', 'solar_eclipse'], true)) {
        $global = [];
        $local = [];
        if ($subtype === 'lunar_eclipse') {
            $global = is_array($details['eclipse_global'] ?? null) ? $details['eclipse_global'] : [];
            $local = is_array($details['eclipse_local'] ?? null) ? $details['eclipse_local'] : [];
            $globalType = is_string($global['global_type'] ?? null) ? strtolower(trim($global['global_type'])) : null;
            $presentation['title'] = astronomyLunarEclipseTitle($globalType);
        } else {
            $global = is_array($details['solar_eclipse_global'] ?? null) ? $details['solar_eclipse_global'] : [];
            $local = is_array($details['solar_eclipse_local'] ?? null) ? $details['solar_eclipse_local'] : [];
            $globalType = is_string($global['global_type'] ?? null) ? strtolower(trim($global['global_type'])) : null;
            $presentation['title'] = astronomySolarEclipseTitle($globalType);
        }

        $visibilityClassification = is_string($local['visibility_classification'] ?? null)
            ? trim((string) $local['visibility_classification'])
            : null;
        $presentation['summary'] = astronomyEclipseVisibilityMessage($subtype, $visibilityClassification);

        $publicDetails = [];
        $visibilityKey = astronomyEclipseVisibilityKey($subtype, $visibilityClassification);
        if ($visibilityKey !== null) {
            $publicDetails[] = ['label' => 'Visibilidad local', 'value' => $visibilityKey];
        }

        if ($subtype === 'lunar_eclipse') {
            $umbralMagnitude = astronomyEventNumber($global['magnitudes']['umbral'] ?? null, 3);
            if ($umbralMagnitude !== null) {
                $publicDetails[] = ['label' => 'Magnitud umbral', 'value' => $umbralMagnitude];
            }
        } else {
            $maxMagnitudeRaw = is_numeric($local['max_magnitude'] ?? null) && is_finite((float) $local['max_magnitude'])
                ? (float) $local['max_magnitude']
                : null;
            $maxMagnitude = astronomyEventNumber($maxMagnitudeRaw, 3);
            if ($maxMagnitude !== null && $maxMagnitudeRaw !== null && $maxMagnitudeRaw > 0.0) {
                $publicDetails[] = ['label' => 'Magnitud', 'value' => $maxMagnitude];
            }
            $obscuration = astronomyEclipseSolarObscurationPercent($local['max_obscuration'] ?? null);
            if ($obscuration !== null && $obscuration !== '0,0%') {
                $publicDetails[] = ['label' => 'Oscurecimiento', 'value' => $obscuration];
            }

            if ($maxMagnitudeRaw !== null && $maxMagnitudeRaw > 0.0) {
                $maximumContact = null;
                foreach ((is_array($local['contacts'] ?? null) ? $local['contacts'] : []) as $contact) {
                    if (is_array($contact) && strtoupper((string) ($contact['code'] ?? '')) === 'MAX') {
                        $maximumContact = $contact;
                        break;
                    }
                }
                if (is_array($maximumContact)) {
                    $sunAltitude = astronomyEventNumber($maximumContact['sun']['altitude_degrees'] ?? null, 1);
                    if ($sunAltitude !== null) {
                        $publicDetails[] = ['label' => 'Altura del Sol en el máximo', 'value' => $sunAltitude . '°'];
                    }
                    $sunDirection = astronomyEventAzimuthDirection($maximumContact['sun']['azimuth_degrees'] ?? null);
                    if ($sunDirection !== null) {
                        $publicDetails[] = ['label' => 'Dirección del Sol en el máximo', 'value' => capitalizeVisibleText($sunDirection)];
                    }
                }
            }

            $centralDuration = astronomyEclipseDurationLabel($global['central_duration_seconds'] ?? null);
            $localVisibility = strtolower((string) ($visibilityClassification ?? ''));
            if (
                $centralDuration !== null
                && in_array($globalType, ['annular', 'total', 'hybrid'], true)
                && in_array($localVisibility, ['visible_annular', 'annular', 'visible_total', 'total', 'visible_hybrid', 'hybrid'], true)
            ) {
                $publicDetails[] = ['label' => 'Duración de la fase central', 'value' => $centralDuration];
            }
        }

        $presentation['public_details'] = $publicDetails;
        $presentation['contact_points'] = astronomyEclipseContactLines(
            is_array($local['contacts'] ?? null) ? $local['contacts'] : [],
            is_array($global['contacts'] ?? null) ? $global['contacts'] : [],
            $subtype,
            $timezoneName
        );

        if (($local['near_central_path_boundary'] ?? false) === true) {
            $presentation['alert'] = 'Tu ubicación está muy cerca del límite calculado de la franja central. La duración y el tipo observado pueden variar con pequeños cambios de ubicación.';
        }

        if ($visibilityClassification === null || trim($visibilityClassification) === '') {
            $presentation['explanation'] = 'No se pudo determinar la visibilidad local.';
        }
        return astronomyEventApplyConfiguredBaseName($event, $presentation);
    }

    if ($type === 'full_moon_observation' && in_array($subtype, ['morning', 'evening'], true)) {
        $presentation['title'] = $subtype === 'morning'
            ? 'Luna llena cerca de la salida del Sol'
            : 'Luna llena cerca de la puesta del Sol';
        $presentation['summary'] = astronomyFullMoonObservationSummary($subtype, $details);
        $presentation['explanation'] = $subtype === 'morning'
            ? 'Una oportunidad para observar la Luna llena baja mientras comienza el día.'
            : 'Una oportunidad para observar la Luna llena baja mientras termina el día.';
        $difference = is_numeric($details['difference_minutes'] ?? null)
            ? astronomyEventMinuteQuantity(abs((int) round((float) $details['difference_minutes'])))
            : null;
        $presentation['technical_details'] = astronomyEventTechnicalDetails([
            'Diferencia temporal' => $difference,
            'Iluminación lunar' => astronomyEventPercent($details['illumination_percent'] ?? null),
            'Clasificación temporal' => is_string($details['temporal_classification'] ?? null) ? $details['temporal_classification'] : null,
        ]);
        return astronomyEventApplyConfiguredBaseName($event, $presentation);
    }

    return astronomyEventApplyConfiguredBaseName($event, $presentation);
}
