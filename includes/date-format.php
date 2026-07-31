<?php

/**
 * Formatea fechas visibles con abreviaturas españolas estables, sin depender
 * de la configuración regional del servidor. La zona horaria es la del objeto.
 */
function astronomySpanishDate(DateTimeInterface $date, bool $withYear = false): string
{
    static $weekdays = [
        1 => 'lun',
        2 => 'mar',
        3 => 'mié',
        4 => 'jue',
        5 => 'vie',
        6 => 'sáb',
        7 => 'dom',
    ];
    static $months = [
        1 => 'ene',
        2 => 'feb',
        3 => 'mar',
        4 => 'abr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dic',
    ];

    $label = $weekdays[(int) $date->format('N')]
        . ' ' . (int) $date->format('j')
        . ' ' . $months[(int) $date->format('n')];

    return $withYear ? $label . ' ' . $date->format('Y') : $label;
}

function astronomyNearbyEventDate(DateTimeInterface $date): string
{
    return astronomySpanishDate($date);
}

function astronomyEclipseDate(DateTimeInterface $date): string
{
    return astronomySpanishDate($date, true);
}

function astronomyEclipseDateTime(DateTimeInterface $date): string
{
    return astronomyEclipseDate($date) . ' ' . $date->format('H:i');
}
