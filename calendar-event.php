<?php
require_once __DIR__ . '/includes/calendar-event.php';

$event = astronomyCalendarDecodePayload(trim((string) ($_GET['event'] ?? '')));
if ($event === null) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No se pudo generar el evento de calendario.';
    exit;
}

$filename = preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $event['title']) ?: 'evento-astronomico');
$filename = trim(strtolower((string) $filename), '-') ?: 'evento-astronomico';
header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . substr($filename, 0, 80) . '.ics"');
header('Cache-Control: private, no-store, max-age=0');
echo astronomyCalendarIcs($event);
