<?php

require_once __DIR__ . '/api-config.php';

function sendDynamicNoCacheHeaders(): void
{
    header('Cache-Control: private, no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0, s-maxage=0');
    header('CDN-Cache-Control: no-store');
    header('Cloudflare-CDN-Cache-Control: no-store');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function astronomyTimingsEnabled(?string $productionConfigPath = null): bool
{
    return canUseSiteDebugTools();
}

function astronomyApiRequest(string $url, string $label, int $timeout = 12): array
{
    $attempt = 0;
    $retried = false;
    $elapsedMilliseconds = 0.0;
    do {
        $attempt++;
        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeout));
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curl, string $header) use (&$responseHeaders): int {
            $length = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $length;
        });
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $curlError = curl_error($ch);
        $curlErrorNumber = curl_errno($ch);
        $httpCode = (int) ($info['http_code'] ?? 0);
        $elapsedMilliseconds += (float) ($info['total_time'] ?? 0) * 1000;
        $isTransientFailure = $response === false || $curlErrorNumber !== 0 || in_array($httpCode, [502, 503, 504], true);
        if ($attempt === 1 && $isTransientFailure) {
            $retried = true;
            usleep(500000);
        }
    } while ($attempt < 2 && $isTransientFailure);

    $apiMilliseconds = isset($responseHeaders['x-response-time-ms']) && is_numeric($responseHeaders['x-response-time-ms'])
        ? (float) $responseHeaders['x-response-time-ms']
        : null;
    $serverTiming = $responseHeaders['server-timing'] ?? null;
    $GLOBALS['astronomy_api_timings'][] = [
        'label' => $label,
        'http_code' => $httpCode,
        'total_ms' => $elapsedMilliseconds,
        'api_ms' => $apiMilliseconds,
        'server_timing' => is_string($serverTiming) ? $serverTiming : null,
        'attempts' => $attempt,
        'retried' => $retried,
        'curl_error' => $curlError !== '',
        'curl_errno' => $curlErrorNumber,
        'json_valid' => null,
        'outcome' => ($response !== false && $httpCode >= 200 && $httpCode < 300) ? 'pending_validation' : 'error',
    ];

    error_log(sprintf(
        'Aquellas Lunas API timing: %s HTTP %d total %.1f ms%s%s',
        $label,
        $httpCode,
        $elapsedMilliseconds,
        $apiMilliseconds !== null ? sprintf(' API %.1f ms', $apiMilliseconds) : '',
        $serverTiming !== null ? ' Server-Timing available' : ''
    ));
    if ($curlError !== '') {
        error_log('Aquellas Lunas API ' . $label . ' cURL error: ' . $curlError);
    }

    return ['body' => $response, 'http_code' => $httpCode, 'content_type' => $info['content_type'] ?? null, 'curl_error' => $curlError, 'curl_errno' => $curlErrorNumber, 'attempts' => $attempt, 'retried' => $retried];
}

function astronomyApiRecordValidation(string $label, ?bool $jsonValid, bool $success): void
{
    if (!isset($GLOBALS['astronomy_api_timings']) || !is_array($GLOBALS['astronomy_api_timings'])) {
        return;
    }
    for ($index = count($GLOBALS['astronomy_api_timings']) - 1; $index >= 0; $index--) {
        if (($GLOBALS['astronomy_api_timings'][$index]['label'] ?? null) === $label) {
            $GLOBALS['astronomy_api_timings'][$index]['json_valid'] = $jsonValid;
            $GLOBALS['astronomy_api_timings'][$index]['outcome'] = $success ? 'success' : 'error';
            return;
        }
    }
}

function renderAstronomyTimings(): void
{
    if (!astronomyTimingsEnabled()) {
        return;
    }
    $timings = $GLOBALS['astronomy_api_timings'] ?? [];
    if (!is_array($timings) || $timings === []) {
        return;
    }
    ?>
    <aside class="api-diagnostics" aria-labelledby="api-diagnostics-title">
        <h2 id="api-diagnostics-title">Diagnóstico</h2>
        <ul>
            <?php foreach ($timings as $timing): ?>
                <li><strong><?= htmlspecialchars((string) $timing['label']) ?></strong>:
                    <?php if ($timing['api_ms'] !== null): ?>API <?= htmlspecialchars(number_format((float) $timing['api_ms'], 1, ',', '.')) ?> ms · <?php endif; ?>
                    Total <?= htmlspecialchars(number_format((float) $timing['total_ms'], 1, ',', '.')) ?> ms · HTTP <?= htmlspecialchars((string) $timing['http_code']) ?>
                    · Intentos <?= htmlspecialchars((string) ($timing['attempts'] ?? 1)) ?><?= ($timing['retried'] ?? false) ? ' (con reintento)' : '' ?>
                    · cURL <?= ($timing['curl_error'] ?? false) ? 'error ' . htmlspecialchars((string) ($timing['curl_errno'] ?? '')) : 'sin error' ?>
                    <?php if (($timing['json_valid'] ?? null) !== null): ?> · JSON <?= $timing['json_valid'] ? 'válido' : 'inválido' ?><?php endif; ?>
                    · Resultado <?= ($timing['outcome'] ?? 'error') === 'success' ? 'datos correctos' : 'estado de error' ?>
                    <?php if ($timing['server_timing'] !== null): ?> · Server-Timing: <?= htmlspecialchars((string) $timing['server_timing']) ?><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>
    <?php
}
