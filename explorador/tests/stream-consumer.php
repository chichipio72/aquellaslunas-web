<?php

declare(strict_types=1);

$mode = $argv[1] ?? 'contract';
$traditionalUrl = $argv[2] ?? '';
$started = hrtime(true);
$firstProgressAt = null;
$completeAt = null;
$messages = [];
$result = null;
$previousOverall = 0.0;
$sawIntermediateOverall = false;

while (($line = fgets(STDIN)) !== false) {
    $message = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $messages[] = $message['type'] ?? '';
    if (($message['type'] ?? '') === 'error') {
        throw new RuntimeException((string) ($message['message'] ?? 'Error NDJSON sin detalle.'));
    }
    if (($message['type'] ?? '') === 'progress') {
        $firstProgressAt ??= hrtime(true);
        $overall = (float) ($message['overall_percent'] ?? -1);
        if ($overall < $previousOverall) throw new RuntimeException('El progreso global retrocedió.');
        if ($overall >= 40.0 && $overall <= 60.0) $sawIntermediateOverall = true;
        $previousOverall = $overall;
    }
    if (($message['type'] ?? '') === 'complete') {
        $completeAt = hrtime(true);
        $result = $message['result'] ?? null;
    }
}

if (($messages[0] ?? '') !== 'start' || !in_array('progress', $messages, true)
    || !in_array('stage', $messages, true) || end($messages) !== 'complete' || !is_array($result)) {
    throw new RuntimeException('Secuencia NDJSON incompleta o desordenada.');
}

if ($mode === 'contract') {
    $traditional = json_decode((string) file_get_contents($traditionalUrl), true, 512, JSON_THROW_ON_ERROR);
    foreach (['columns', 'field_metadata', 'request', 'rows'] as $key) {
        if (($result[$key] ?? null) !== ($traditional[$key] ?? null)) {
            throw new RuntimeException('El resultado progresivo difiere del tradicional en ' . $key . '.');
        }
    }
}

if ($mode === 'timing') {
    if ($firstProgressAt === null || $completeAt === null || $completeAt <= $firstProgressAt) {
        throw new RuntimeException('No hubo progreso antes del resultado final.');
    }
    $afterFirstProgressMs = ($completeAt - $firstProgressAt) / 1_000_000;
    if ($afterFirstProgressMs < 20.0) {
        throw new RuntimeException('Los eventos parecen haber llegado juntos al finalizar (' . $afterFirstProgressMs . ' ms).');
    }
    echo 'Primer progreso a los ', number_format(($firstProgressAt - $started) / 1_000_000, 1, '.', ''),
        ' ms; final ', number_format($afterFirstProgressMs, 1, '.', ''), " ms después.\n";
}

if ($mode === 'combined' && (!$sawIntermediateOverall || $previousOverall !== 100.0)) {
    throw new RuntimeException('El progreso combinado no reflejó unidades de ambos proveedores.');
}

echo 'NDJSON ', $mode, ": OK\n";
