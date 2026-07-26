<?php

function capitalizeVisibleText(?string $text): string
{
    if ($text === null || $text === '') {
        return '';
    }
    if (function_exists('mb_strtoupper') && function_exists('mb_substr')) {
        return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($text, 1, null, 'UTF-8');
    }

    if (preg_match('/^./us', $text, $match) !== 1) {
        return $text;
    }

    $firstCharacter = $match[0];
    $spanishUppercase = [
        'á' => 'Á', 'é' => 'É', 'í' => 'Í', 'ó' => 'Ó',
        'ú' => 'Ú', 'ü' => 'Ü', 'ñ' => 'Ñ',
    ];
    $capitalizedFirstCharacter = $spanishUppercase[$firstCharacter] ?? strtoupper($firstCharacter);

    return $capitalizedFirstCharacter . substr($text, strlen($firstCharacter));
}
