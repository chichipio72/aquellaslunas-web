<?php

function calendarVisibilityHeadingAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$markup = file_get_contents(__DIR__ . '/../sol-y-luna.php');
$styles = file_get_contents(__DIR__ . '/../assets/css/styles.css');
calendarVisibilityHeadingAssert(is_string($markup) && is_string($styles), 'No se pudieron leer los archivos.');
calendarVisibilityHeadingAssert(
    str_contains($markup, 'class="visibility-column-heading"')
        && str_contains($markup, '>Horarios de visibilidad</span>'),
    'Falta el título de la columna de visibilidad.'
);
calendarVisibilityHeadingAssert(
    preg_match('/\.visibility-column-heading span\s*\{[^}]*display:\s*block;/s', $styles) === 1,
    'El título no queda visible en escritorio.'
);
calendarVisibilityHeadingAssert(
    preg_match('/@media \(min-width: 641px\) and \(max-width: 900px\).*?\.visibility-column-heading span\s*\{[^}]*display:\s*none;/s', $styles) === 1,
    'El título no se oculta en anchos intermedios.'
);
calendarVisibilityHeadingAssert(
    preg_match('/@media \(max-width: 767px\).*?\.astro-table thead\s*\{[^}]*position:\s*absolute;/s', $styles) === 1,
    'La cabecera no permanece oculta en móvil.'
);

echo "Título responsive de horarios de visibilidad: OK\n";
