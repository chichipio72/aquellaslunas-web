<?php

declare(strict_types=1);

require_once __DIR__ . '/editorial-configuration.php';
require_once __DIR__ . '/event-date-header.php';

function homeEclipseVisibilityClassification(array $event): string
{
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $key = ($event['subtype'] ?? '') === 'solar_eclipse' ? 'solar_eclipse_local' : 'eclipse_local';
    $local = is_array($details[$key] ?? null) ? $details[$key] : [];
    return strtolower(trim((string) ($local['visibility_classification'] ?? '')));
}

function homeUpcomingVisibleEclipse(array $events, DateTimeImmutable $now, string $timezoneName, int $days): ?array
{
    $end = $now->modify('+' . max(1, $days) . ' days');
    $visible = [];
    foreach ($events as $event) {
        if (!is_array($event) || ($event['type'] ?? '') !== 'eclipse') continue;
        $classification = homeEclipseVisibilityClassification($event);
        if ($classification === '' || $classification === 'not_visible') continue;
        try {
            $date = (new DateTimeImmutable((string) ($event['datetime'] ?? '')))->setTimezone(new DateTimeZone($timezoneName));
        } catch (Throwable) {
            continue;
        }
        if (astronomyEventIsFuture($date, $now) && $date <= $end) $visible[] = ['event' => $event, 'date' => $date];
    }
    usort($visible, static fn(array $a, array $b): int => $a['date'] <=> $b['date']);
    return $visible[0] ?? null;
}

function homeEclipseNoticeText(array $event, DateTimeImmutable $date, DateTimeImmutable $now): string
{
    $solar = ($event['subtype'] ?? '') === 'solar_eclipse';
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $globalKey = $solar ? 'solar_eclipse_global' : 'eclipse_global';
    $type = strtolower(trim((string) ($details[$globalKey]['global_type'] ?? '')));
    if ($solar) {
        $localType = homeEclipseVisibilityClassification($event);
        $type = match ($localType) {
            'visible_partial', 'partial' => 'partial',
            'visible_total', 'total' => 'total',
            'visible_annular', 'annular' => 'annular',
            'visible_hybrid', 'hybrid' => 'hybrid',
            default => $type,
        };
    }
    $typeLabel = match ($type) {
        'total' => 'total', 'partial' => 'parcial', 'annular' => 'anular',
        'hybrid' => 'híbrido', 'penumbral' => 'penumbral', default => '',
    };
    $subject = 'un eclipse' . ($typeLabel !== '' ? ' ' . $typeLabel : '') . ($solar ? ' de Sol' : ' de Luna');
    $values = ['eclipse' => $subject];
    $period = astronomyEventObservationalPeriod($date, $now);
    if ($period === 'tonight') {
        return astronomyEditorialText('home.eclipse.notice.today', $values + ['momento' => 'Esta noche']);
    }
    if ($period === 'tomorrow') {
        return astronomyEditorialText('home.eclipse.notice.tomorrow', $values);
    }
    $days = (int) $now->setTime(0, 0)->diff($date->setTime(0, 0))->days;
    return astronomyEditorialText('home.eclipse.notice.future', $values + ['dias' => (string) $days]);
}
