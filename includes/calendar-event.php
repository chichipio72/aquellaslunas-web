<?php

const ASTRONOMY_CALENDAR_INSTANT_DURATION_MINUTES = 15;
const ASTRONOMY_CALENDAR_OBSERVATION_DURATION_MINUTES = 30;
const ASTRONOMY_CALENDAR_ECLIPSE_FALLBACK_DURATION_MINUTES = 60;

function astronomyCalendarDateTime($value, string $timezoneName): ?DateTimeImmutable
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezoneName));
    } catch (Throwable) {
        return null;
    }
}

function astronomyCalendarEclipseInterval(array $event, string $timezoneName): ?array
{
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $subtype = (string) ($event['subtype'] ?? '');
    $local = $subtype === 'solar_eclipse'
        ? (is_array($details['solar_eclipse_local'] ?? null) ? $details['solar_eclipse_local'] : [])
        : (is_array($details['eclipse_local'] ?? null) ? $details['eclipse_local'] : []);
    $global = $subtype === 'solar_eclipse'
        ? (is_array($details['solar_eclipse_global'] ?? null) ? $details['solar_eclipse_global'] : [])
        : (is_array($details['eclipse_global'] ?? null) ? $details['eclipse_global'] : []);
    $start = astronomyCalendarDateTime($local['first_visible_instant'] ?? null, $timezoneName);
    $end = astronomyCalendarDateTime($local['last_visible_instant'] ?? null, $timezoneName);
    if ($start !== null && $end !== null && $end > $start) {
        return [$start, $end];
    }
    $contacts = [];
    $sourceContacts = is_array($local['contacts'] ?? null) && $local['contacts'] !== []
        ? $local['contacts']
        : (is_array($global['contacts'] ?? null) ? $global['contacts'] : []);
    foreach ($sourceContacts as $contact) {
        $date = is_array($contact) ? astronomyCalendarDateTime($contact['datetime'] ?? null, $timezoneName) : null;
        if ($date !== null) {
            $contacts[] = $date;
        }
    }
    if (count($contacts) >= 2) {
        usort($contacts, static fn(DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);
        return [$contacts[0], $contacts[array_key_last($contacts)]];
    }
    $maximum = astronomyCalendarDateTime($event['datetime'] ?? null, $timezoneName);
    return $maximum !== null
        ? [$maximum, $maximum->modify('+' . ASTRONOMY_CALENDAR_ECLIPSE_FALLBACK_DURATION_MINUTES . ' minutes')]
        : null;
}

function astronomyCalendarEventData(
    array $event,
    array $presentation,
    string $timezoneName,
    string $location,
    string $url
): ?array {
    $type = (string) ($event['type'] ?? '');
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $start = null;
    $end = null;

    if ($type === 'earthshine') {
        $start = astronomyCalendarDateTime($details['start_time'] ?? ($event['datetime'] ?? null), $timezoneName);
        $end = astronomyCalendarDateTime($details['end_time'] ?? ($event['end_datetime'] ?? null), $timezoneName);
    } elseif ($type === 'full_moon_observation') {
        $start = astronomyCalendarDateTime($details['moon_event_time'] ?? ($event['datetime'] ?? null), $timezoneName);
        $end = $start?->modify('+' . ASTRONOMY_CALENDAR_OBSERVATION_DURATION_MINUTES . ' minutes');
    } elseif ($type === 'eclipse') {
        [$start, $end] = astronomyCalendarEclipseInterval($event, $timezoneName) ?? [null, null];
    } elseif (in_array($type, ['moon_phase', 'conjunction', 'apsis', 'libration'], true)) {
        $start = astronomyCalendarDateTime($event['datetime'] ?? null, $timezoneName);
        $end = $start?->modify('+' . ASTRONOMY_CALENDAR_INSTANT_DURATION_MINUTES . ' minutes');
    }

    if ($start === null || $end === null || $end <= $start) {
        return null;
    }
    $title = trim((string) ($presentation['title'] ?? $event['title'] ?? 'Evento astronómico'));
    $descriptionParts = array_filter([
        trim((string) ($presentation['summary'] ?? '')),
        trim((string) ($presentation['explanation'] ?? '')),
    ]);
    return [
        'title' => $title !== '' ? $title : 'Evento astronómico',
        'start' => $start->format(DateTimeInterface::ATOM),
        'end' => $end->format(DateTimeInterface::ATOM),
        'timezone' => $timezoneName,
        'description' => implode(' ', $descriptionParts),
        'location' => trim($location),
        'url' => $url,
    ];
}

function astronomyCalendarPayload(array $calendarEvent): string
{
    return rtrim(strtr(base64_encode(json_encode($calendarEvent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
}

function astronomyCalendarDownloadUrl(?array $calendarEvent): ?string
{
    return $calendarEvent === null ? null : 'calendar-event.php?event=' . rawurlencode(astronomyCalendarPayload($calendarEvent));
}

function astronomyCalendarGoogleUrl(array $event): string
{
    $start = (new DateTimeImmutable($event['start']))->setTimezone(new DateTimeZone('UTC'));
    $end = (new DateTimeImmutable($event['end']))->setTimezone(new DateTimeZone('UTC'));
    $description = trim($event['description']
        . ($event['timezone'] !== '' ? "\nZona horaria: " . $event['timezone'] : '')
        . ($event['url'] !== '' ? "\n\n" . $event['url'] : ''));
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE',
        'text' => $event['title'],
        'dates' => $start->format('Ymd\THis\Z') . '/' . $end->format('Ymd\THis\Z'),
        'details' => $description,
        'location' => $event['location'],
        'ctz' => $event['timezone'],
    ], '', '&', PHP_QUERY_RFC3986);
}

function astronomyCalendarOutlookUrl(array $event): string
{
    $description = trim($event['description']
        . ($event['timezone'] !== '' ? "\nZona horaria: " . $event['timezone'] : '')
        . ($event['url'] !== '' ? "\n\n" . $event['url'] : ''));
    return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query([
        'path' => '/calendar/action/compose',
        'rru' => 'addevent',
        'subject' => $event['title'],
        'startdt' => (new DateTimeImmutable($event['start']))->format(DateTimeInterface::ATOM),
        'enddt' => (new DateTimeImmutable($event['end']))->format(DateTimeInterface::ATOM),
        'body' => $description,
        'location' => $event['location'],
    ], '', '&', PHP_QUERY_RFC3986);
}

function astronomyCalendarPageUrl(string $path): string
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on';
    $scheme = $https ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'aquellaslunas.com.ar'));
    $directory = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/.');
    return $scheme . '://' . ($host !== '' ? $host : 'aquellaslunas.com.ar')
        . ($directory !== '' ? $directory : '') . '/' . ltrim($path, '/');
}

function renderAstronomyCalendarLink(?array $calendarEvent, string $className = ''): void
{
    $icsUrl = astronomyCalendarDownloadUrl($calendarEvent);
    if ($calendarEvent === null || $icsUrl === null) {
        return;
    }
    $class = trim('calendar-scheduler ' . $className);
    $id = 'calendar-menu-' . substr(hash('sha256', $calendarEvent['title'] . '|' . $calendarEvent['start'] . '|' . $className), 0, 12);
    echo '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" data-calendar-scheduler>'
        . '<button type="button" class="calendar-action" data-calendar-menu-trigger aria-expanded="false" aria-controls="' . $id . '">'
        . '<span class="calendar-action__icon" aria-hidden="true"></span><span>Agendar evento</span></button>'
        . '<div class="calendar-menu" id="' . $id . '" data-calendar-menu hidden>'
        . '<a href="' . htmlspecialchars(astronomyCalendarGoogleUrl($calendarEvent), ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" data-calendar-provider="google">Google Calendar</a>'
        . '<a href="' . htmlspecialchars(astronomyCalendarOutlookUrl($calendarEvent), ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" data-calendar-provider="outlook">Outlook</a>'
        . '<a href="' . htmlspecialchars($icsUrl, ENT_QUOTES, 'UTF-8') . '" data-calendar-provider="ics">Apple Calendar / Otros calendarios (.ics)</a>'
        . '</div></div>';
}

function astronomyCalendarDecodePayload(string $payload): ?array
{
    if ($payload === '' || strlen($payload) > 12000 || !preg_match('/^[A-Za-z0-9_-]+$/', $payload)) {
        return null;
    }
    $decoded = base64_decode(strtr($payload, '-_', '+/') . str_repeat('=', (4 - strlen($payload) % 4) % 4), true);
    $data = is_string($decoded) ? json_decode($decoded, true) : null;
    if (!is_array($data)) {
        return null;
    }
    foreach (['title', 'start', 'end', 'timezone', 'description', 'location', 'url'] as $key) {
        if (!is_string($data[$key] ?? null)) {
            return null;
        }
    }
    $start = astronomyCalendarDateTime($data['start'], $data['timezone']);
    $end = astronomyCalendarDateTime($data['end'], $data['timezone']);
    return $start !== null && $end !== null && $end > $start ? $data : null;
}

function astronomyIcsEscape(string $value): string
{
    return str_replace(["\\", "\r\n", "\r", "\n", ';', ','], ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'], $value);
}

function astronomyIcsFold(string $line): string
{
    $result = '';
    while (strlen($line) > 73) {
        $length = 73;
        while ($length > 0 && (ord($line[$length]) & 0xC0) === 0x80) {
            $length--;
        }
        $result .= substr($line, 0, $length) . "\r\n ";
        $line = substr($line, $length);
    }
    return $result . $line;
}

function astronomyCalendarIcs(array $data): string
{
    $start = new DateTimeImmutable($data['start']);
    $end = new DateTimeImmutable($data['end']);
    $stamp = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $uid = hash('sha256', $data['title'] . '|' . $data['start'] . '|' . $data['location']) . '@aquellaslunas';
    $description = $data['description'];
    if ($data['timezone'] !== '') {
        $description .= ($description !== '' ? "\n" : '') . 'Zona horaria: ' . $data['timezone'];
    }
    $lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Aquellas Lunas//Eventos astronomicos//ES',
        'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:Aquellas Lunas',
        'X-WR-TIMEZONE:' . astronomyIcsEscape($data['timezone']), 'BEGIN:VEVENT',
        'UID:' . $uid, 'DTSTAMP:' . $stamp->format('Ymd\THis\Z'),
        'DTSTART:' . $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
        'DTEND:' . $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
        'SUMMARY:' . astronomyIcsEscape($data['title']),
        'DESCRIPTION:' . astronomyIcsEscape($description),
        'LOCATION:' . astronomyIcsEscape($data['location']),
        'URL:' . astronomyIcsEscape($data['url']),
        'END:VEVENT', 'END:VCALENDAR',
    ];
    return implode("\r\n", array_map('astronomyIcsFold', $lines)) . "\r\n";
}
