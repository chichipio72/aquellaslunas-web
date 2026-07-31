<?php

require_once __DIR__ . '/../includes/calendar-event.php';

function calendarAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$presentation = ['title' => 'Conjunción Luna–Venus', 'summary' => 'Se verán juntas.', 'explanation' => ''];
$instant = astronomyCalendarEventData(
    ['type' => 'conjunction', 'datetime' => '2026-08-10T23:55:00-03:00'],
    $presentation,
    'America/Argentina/Buenos_Aires',
    'Buenos Aires',
    'https://example.test/eventos.php'
);
calendarAssert($instant !== null, 'No se construyó el evento instantáneo.');
calendarAssert(
    (new DateTimeImmutable($instant['end']))->getTimestamp() - (new DateTimeImmutable($instant['start']))->getTimestamp() === 900,
    'La duración instantánea no es de 15 minutos.'
);

$earthshine = astronomyCalendarEventData(
    [
        'type' => 'earthshine',
        'datetime' => '2026-08-10T23:40:00-03:00',
        'details' => [
            'start_time' => '2026-08-10T23:40:00-03:00',
            'end_time' => '2026-08-11T00:20:00-03:00',
        ],
    ],
    ['title' => 'Luna fina', 'summary' => 'Intervalo útil.', 'explanation' => ''],
    'America/Argentina/Buenos_Aires',
    'Buenos Aires',
    'https://example.test/eventos.php'
);
calendarAssert($earthshine !== null, 'No se construyó el intervalo de Luna fina.');
calendarAssert(substr($earthshine['start'], 0, 10) !== substr($earthshine['end'], 0, 10), 'No se conservó el cruce de medianoche.');

$observation = astronomyCalendarEventData(
    ['type' => 'full_moon_observation', 'details' => ['moon_event_time' => '2026-09-01T06:20:00+09:00']],
    ['title' => 'Luna llena al amanecer', 'summary' => '', 'explanation' => ''],
    'Asia/Tokyo',
    'Tokio',
    'https://example.test/eventos.php'
);
calendarAssert($observation !== null && str_contains($observation['start'], '+09:00'), 'No se conservó la zona horaria activa.');

$eclipse = astronomyCalendarEventData(
    ['type' => 'eclipse', 'subtype' => 'lunar_eclipse', 'datetime' => '2026-03-03T12:00:00Z', 'details' => []],
    ['title' => 'Eclipse lunar', 'summary' => '', 'explanation' => ''],
    'Europe/Madrid',
    'Madrid',
    'https://example.test/eclipses.php'
);
calendarAssert(
    $eclipse !== null
    && (new DateTimeImmutable($eclipse['end']))->getTimestamp() - (new DateTimeImmutable($eclipse['start']))->getTimestamp() === 3600,
    'El fallback de eclipse no dura 60 minutos.'
);

$ics = astronomyCalendarIcs($earthshine);
foreach (['BEGIN:VCALENDAR', 'VERSION:2.0', 'BEGIN:VEVENT', 'UID:', 'DTSTAMP:', 'DTSTART:', 'DTEND:', 'SUMMARY:', 'DESCRIPTION:', 'LOCATION:', 'URL:', 'END:VEVENT', 'END:VCALENDAR'] as $field) {
    calendarAssert(str_contains($ics, $field), 'Falta el campo ICS ' . $field);
}
calendarAssert(str_contains($ics, "\r\n"), 'El ICS no usa finales CRLF.');
calendarAssert(str_contains($ics, 'X-WR-TIMEZONE:America/Argentina/Buenos_Aires'), 'Falta la zona horaria activa.');
calendarAssert(!str_contains($ics, 'VALUE=DATE'), 'Se generó incorrectamente un evento de día completo.');

$payload = astronomyCalendarPayload($earthshine);
calendarAssert(astronomyCalendarDecodePayload($payload) !== null, 'El payload no puede recuperarse.');

$googleUrl = astronomyCalendarGoogleUrl($earthshine);
parse_str((string) parse_url($googleUrl, PHP_URL_QUERY), $googleQuery);
calendarAssert(str_starts_with($googleUrl, 'https://calendar.google.com/calendar/render?'), 'URL de Google incorrecta.');
calendarAssert(($googleQuery['action'] ?? null) === 'TEMPLATE', 'Google no abre el formulario de confirmación.');
calendarAssert(($googleQuery['dates'] ?? null) === '20260811T024000Z/20260811T032000Z', 'Google no conserva el cruce de medianoche.');
calendarAssert(($googleQuery['ctz'] ?? null) === 'America/Argentina/Buenos_Aires', 'Google no recibe la zona activa.');
calendarAssert(str_contains((string) ($googleQuery['details'] ?? ''), 'Zona horaria: America/Argentina/Buenos_Aires'), 'Google no describe la zona activa.');

$outlookUrl = astronomyCalendarOutlookUrl($earthshine);
parse_str((string) parse_url($outlookUrl, PHP_URL_QUERY), $outlookQuery);
calendarAssert(str_starts_with($outlookUrl, 'https://outlook.live.com/calendar/0/deeplink/compose?'), 'URL de Outlook incorrecta.');
calendarAssert(($outlookQuery['rru'] ?? null) === 'addevent', 'Outlook no abre el formulario de alta.');
calendarAssert(($outlookQuery['startdt'] ?? null) === $earthshine['start'], 'Outlook alteró el inicio.');
calendarAssert(($outlookQuery['enddt'] ?? null) === $earthshine['end'], 'Outlook alteró el final.');
calendarAssert(str_contains((string) ($outlookQuery['body'] ?? ''), 'Zona horaria: America/Argentina/Buenos_Aires'), 'Outlook no describe la zona activa.');

echo "Calendario ICS: OK\n";
