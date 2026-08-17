<?php

declare(strict_types=1);

const ASTRONOMY_PUSH_SUPPORT_ID_PREFIX = 'AL-';
const ASTRONOMY_PUSH_SUPPORT_ID_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
const ASTRONOMY_PUSH_SUPPORT_ID_LENGTH = 8;

function astronomyPushGenerateSupportId(): string
{
    $value = ASTRONOMY_PUSH_SUPPORT_ID_PREFIX;
    $maximum = strlen(ASTRONOMY_PUSH_SUPPORT_ID_ALPHABET) - 1;
    for ($index = 0; $index < ASTRONOMY_PUSH_SUPPORT_ID_LENGTH; $index++) {
        $value .= ASTRONOMY_PUSH_SUPPORT_ID_ALPHABET[random_int(0, $maximum)];
    }
    return $value;
}

function astronomyPushNormalizeSupportId(mixed $value): ?string
{
    $value = strtoupper((string) preg_replace('/\s+/', '', trim((string) $value)));
    if (preg_match('/^AL-[' . ASTRONOMY_PUSH_SUPPORT_ID_ALPHABET . ']{8}$/', $value) !== 1) return null;
    return $value;
}

function astronomyPushAllocateSupportId(PDO $connection): string
{
    $check = $connection->prepare('SELECT 1 FROM web_push_device_config WHERE support_id = :support_id');
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $supportId = astronomyPushGenerateSupportId();
        $check->execute(['support_id' => $supportId]);
        if ($check->fetchColumn() === false) return $supportId;
    }
    throw new RuntimeException('No se pudo generar un identificador de soporte único.');
}
