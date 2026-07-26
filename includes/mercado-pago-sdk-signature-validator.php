<?php

/**
 * Comparador diagnóstico compatible con WebhookSignatureValidator del SDK PHP
 * oficial de Mercado Pago 3.12.0. No realiza llamadas de red.
 * Fuente de referencia: mercadopago/sdk-php, licencia MIT.
 */
function validateMercadoPagoWebhookSignatureWithOfficialSdk(
    ?string $signature,
    ?string $requestId,
    ?string $dataId,
    string $secret
): bool {
    if ($secret === '') {
        return false;
    }
    $normalize = static function (?string $value): ?string {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    };
    $signature = $normalize($signature);
    $requestId = $normalize($requestId);
    $dataId = $normalize($dataId);
    if ($signature === null) {
        return false;
    }
    $timestamp = null;
    $hashes = [];
    foreach (explode(',', $signature) as $part) {
        $pieces = explode('=', $part, 2);
        if (count($pieces) !== 2) {
            continue;
        }
        $key = strtolower(trim($pieces[0]));
        $value = trim($pieces[1]);
        if ($key === '' || $value === '') {
            continue;
        }
        if ($key === 'ts') {
            $timestamp = $value;
        } elseif (preg_match('/^v\d+$/', $key) === 1) {
            $hashes[$key] = $value;
        }
    }
    if ($timestamp === null || !ctype_digit($timestamp) || !isset($hashes['v1'])) {
        return false;
    }
    $manifest = '';
    if ($dataId !== null) {
        $manifest .= 'id:' . $dataId . ';';
    }
    if ($requestId !== null) {
        $manifest .= 'request-id:' . $requestId . ';';
    }
    $manifest .= 'ts:' . $timestamp . ';';
    return hash_equals(hash_hmac('sha256', $manifest, $secret), $hashes['v1']);
}
