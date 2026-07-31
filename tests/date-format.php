<?php

require_once __DIR__ . '/../includes/date-format.php';

$timezone = new DateTimeZone('America/Argentina/Buenos_Aires');
$cases = [
    ['2026-07-27 12:00:00', 'lun 27 jul', 'lun 27 jul 2026'],
    ['2026-08-05 12:00:00', 'mié 5 ago', 'mié 5 ago 2026'],
    ['2028-08-12 12:00:00', 'sáb 12 ago', 'sáb 12 ago 2028'],
    ['2030-04-25 12:00:00', 'jue 25 abr', 'jue 25 abr 2030'],
    ['2027-01-31 23:30:00', 'dom 31 ene', 'dom 31 ene 2027'],
    ['2027-02-01 00:30:00', 'lun 1 feb', 'lun 1 feb 2027'],
];

foreach ($cases as [$input, $nearbyExpected, $eclipseExpected]) {
    $date = new DateTimeImmutable($input, $timezone);
    if (astronomyNearbyEventDate($date) !== $nearbyExpected) {
        throw new RuntimeException('Formato de evento cercano incorrecto para ' . $input);
    }
    if (astronomyEclipseDate($date) !== $eclipseExpected) {
        throw new RuntimeException('Formato de eclipse incorrecto para ' . $input);
    }
}

$weekdayExpected = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
$monday = new DateTimeImmutable('2026-07-27 12:00:00', $timezone);
foreach ($weekdayExpected as $offset => $expected) {
    $label = astronomyNearbyEventDate($monday->modify('+' . $offset . ' days'));
    if (!str_starts_with($label, $expected . ' ')) {
        throw new RuntimeException('Abreviatura de día incorrecta: ' . $label);
    }
}

$monthExpected = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
foreach ($monthExpected as $month => $expected) {
    $date = new DateTimeImmutable(sprintf('2026-%02d-15 12:00:00', $month + 1), $timezone);
    $label = astronomyNearbyEventDate($date);
    if (!str_ends_with($label, ' ' . $expected)) {
        throw new RuntimeException('Abreviatura de mes incorrecta: ' . $label);
    }
}

$utcInstant = new DateTimeImmutable('2027-02-01T02:30:00+00:00');
$localInstant = $utcInstant->setTimezone($timezone);
if (astronomyNearbyEventDate($localInstant) !== 'dom 31 ene') {
    throw new RuntimeException('El helper no respetó la zona horaria activa en un cambio de mes.');
}

echo "date-format: ok\n";
