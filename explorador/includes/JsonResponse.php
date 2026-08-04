<?php

declare(strict_types=1);

namespace Explorador;

final class JsonResponse
{
    /** @param array<string,mixed> $payload @param int|float $requestStarted */
    public static function send(array $payload, $requestStarted, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store, max-age=0');
        $started = self::now();
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $serializationMs = self::elapsed($started);
        if (isset($payload['metrics']) && is_array($payload['metrics'])) {
            $payload['metrics']['json_serialization_ms'] = $serializationMs;
            $payload['metrics']['total_ms'] = self::elapsed($requestStarted);
            $payload['metrics']['approximate_json_bytes'] = strlen($json);
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $payload['metrics']['approximate_json_bytes'] = strlen($json);
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        echo $json;
        exit;
    }

    /** @return int|float */
    public static function now() { return function_exists('hrtime') ? hrtime(true) : microtime(true) * 1_000_000_000; }
    /** @param int|float $started */
    public static function elapsed($started): float { return max(0.0, (self::now() - $started) / 1_000_000); }
}
