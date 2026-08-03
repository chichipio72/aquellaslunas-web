<?php

require_once __DIR__ . '/api-config.php';

// El motor astronómico se despliega fuera de vendor/. Su carga no debe depender
// de que Composer haya regenerado el mapa PSR-4 en el servidor de producción.
spl_autoload_register(static function (string $class): void {
    $prefix = 'AstronomyEngine\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/astronomy-engine/src/'
        . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

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

/** @param array<string,mixed> $diagnostic */
function astronomyRecordDiagnostic(array $diagnostic): void
{
    if (!astronomyTimingsEnabled()) {
        return;
    }
    $GLOBALS['astronomy_api_timings'][] = $diagnostic + [
        'label' => 'astronomía',
        'requested_source' => 'unknown',
        'used_source' => 'unknown',
        'source_ms' => null,
        'total_ms' => 0.0,
        'fallback_from' => null,
        'fallback_to' => null,
        'technical_error' => null,
        'result_count' => null,
        'http_code' => null,
        'api_ms' => null,
        'server_timing' => null,
        'attempts' => null,
        'retried' => false,
        'curl_error' => null,
        'curl_errno' => null,
        'json_valid' => null,
        'outcome' => 'success',
    ];
}

/** @param array<string,mixed> $values */
function astronomyAnnotateLastDiagnostic(string $label, array $values): void
{
    if (!isset($GLOBALS['astronomy_api_timings']) || !is_array($GLOBALS['astronomy_api_timings'])) {
        return;
    }
    for ($index = count($GLOBALS['astronomy_api_timings']) - 1; $index >= 0; $index--) {
        if (($GLOBALS['astronomy_api_timings'][$index]['label'] ?? null) === $label) {
            $GLOBALS['astronomy_api_timings'][$index] = $values + $GLOBALS['astronomy_api_timings'][$index];
            return;
        }
    }
}

function astronomyApiRequest(string $url, string $label, int $timeout = 12): array
{
    $operationStarted = hrtime(true);
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
    $totalMilliseconds = (hrtime(true) - $operationStarted) / 1_000_000;
    $serverTiming = $responseHeaders['server-timing'] ?? null;
    astronomyRecordDiagnostic([
        'label' => $label,
        'requested_source' => 'api',
        'used_source' => 'api',
        'http_code' => $httpCode,
        'total_ms' => $totalMilliseconds,
        'source_ms' => $apiMilliseconds ?? $elapsedMilliseconds,
        'api_ms' => $apiMilliseconds,
        'server_timing' => is_string($serverTiming) ? $serverTiming : null,
        'attempts' => $attempt,
        'retried' => $retried,
        'curl_error' => $curlError !== '',
        'curl_errno' => $curlErrorNumber,
        'json_valid' => null,
        'outcome' => ($response !== false && $httpCode >= 200 && $httpCode < 300) ? 'pending_validation' : 'error',
    ]);

    error_log(sprintf(
        'Aquellas Lunas API timing: %s HTTP %d total %.1f ms%s%s',
        $label,
        $httpCode,
        $totalMilliseconds,
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
    $maximumSourceMilliseconds = 0.0;
    foreach ($timings as $timing) {
        if (is_numeric($timing['source_ms'] ?? null)) {
            $maximumSourceMilliseconds = max($maximumSourceMilliseconds, (float) $timing['source_ms']);
        }
    }
    $requestStartedAt = is_numeric($_SERVER['REQUEST_TIME_FLOAT'] ?? null)
        ? (float) $_SERVER['REQUEST_TIME_FLOAT']
        : microtime(true);
    $pageGenerationMilliseconds = max(0.0, (microtime(true) - $requestStartedAt) * 1000);
    ?>
    <aside class="api-diagnostics" aria-labelledby="api-diagnostics-title">
        <h2 id="api-diagnostics-title">Diagnóstico</h2>
        <p class="api-diagnostics__page-time">Tiempo total de generación de la página: <?= htmlspecialchars(number_format($pageGenerationMilliseconds, 1, ',', '.')) ?> ms</p>
        <?php $pageProfile = is_array($GLOBALS['home_page_profile'] ?? null) ? $GLOBALS['home_page_profile'] : []; ?>
        <?php if (is_array($pageProfile['blocks'] ?? null) && $pageProfile['blocks'] !== []): ?>
            <section class="api-diagnostics__page-profile" aria-labelledby="page-profile-title">
                <h3 id="page-profile-title">Perfil de página</h3>
                <dl>
                    <?php foreach ($pageProfile['blocks'] as $label => $milliseconds): ?>
                        <div><dt><?= htmlspecialchars((string) $label) ?></dt><dd><?= htmlspecialchars(number_format((float) $milliseconds, 1, ',', '.')) ?> ms</dd></div>
                        <?php if ((float) $milliseconds > 500.0 && is_array($pageProfile['details'][$label] ?? null)): ?>
                            <?php foreach ($pageProfile['details'][$label] as $detailLabel => $detailMilliseconds): ?>
                                <div class="api-diagnostics__page-profile-detail"><dt>↳ <?= htmlspecialchars((string) $detailLabel) ?></dt><dd><?= htmlspecialchars(number_format((float) $detailMilliseconds, 1, ',', '.')) ?> ms</dd></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div><dt>Total medido</dt><dd><?= htmlspecialchars(number_format((float) ($pageProfile['total_ms'] ?? 0.0), 1, ',', '.')) ?> ms</dd></div>
                </dl>
            </section>
        <?php endif; ?>
        <ul>
            <?php foreach ($timings as $timing): ?>
                <?php
                $sourceMilliseconds = is_numeric($timing['source_ms'] ?? null) ? max(0.0, (float) $timing['source_ms']) : null;
                $sourcePercentage = $sourceMilliseconds !== null && $maximumSourceMilliseconds > 0.0
                    ? ($sourceMilliseconds / $maximumSourceMilliseconds) * 100.0
                    : 0.0;
                $visiblePercentage = $sourceMilliseconds !== null ? max(2.0, min(100.0, $sourcePercentage)) : 0.0;
                $speedClass = $sourcePercentage <= 33.333
                    ? 'api-diagnostics__fill--fast'
                    : ($sourcePercentage <= 66.666 ? 'api-diagnostics__fill--medium' : 'api-diagnostics__fill--slow');
                ?>
                <li>
                    <span class="api-diagnostics__bar" aria-hidden="true"><span class="api-diagnostics__fill <?= $speedClass ?>" style="width: <?= htmlspecialchars(number_format($visiblePercentage, 3, '.', '')) ?>%"></span></span>
                    <span class="api-diagnostics__text"><strong><?= htmlspecialchars((string) $timing['label']) ?></strong>:
                    Solicitada <?= htmlspecialchars((string) ($timing['requested_source'] ?? 'api')) ?>
                    · Usada <?= htmlspecialchars((string) ($timing['used_source'] ?? 'api')) ?>
                    <?php if (($timing['source_ms'] ?? null) !== null): ?> · Fuente <?= htmlspecialchars(number_format((float) $timing['source_ms'], 1, ',', '.')) ?> ms<?php endif; ?>
                    · Total <?= htmlspecialchars(number_format((float) ($timing['total_ms'] ?? 0.0), 1, ',', '.')) ?> ms
                    <?php if (($timing['fallback_from'] ?? null) !== null): ?> · Fallback <?= htmlspecialchars((string) $timing['fallback_from']) ?> → <?= htmlspecialchars((string) ($timing['fallback_to'] ?? $timing['used_source'] ?? 'desconocida')) ?><?php endif; ?>
                    <?php if (($timing['result_count'] ?? null) !== null): ?> · Resultados <?= htmlspecialchars((string) $timing['result_count']) ?><?php endif; ?>
                    <?php if (($timing['http_code'] ?? null) !== null): ?> · HTTP <?= htmlspecialchars((string) $timing['http_code']) ?><?php endif; ?>
                    <?php if (($timing['attempts'] ?? null) !== null): ?> · Intentos <?= htmlspecialchars((string) $timing['attempts']) ?><?= ($timing['retried'] ?? false) ? ' (con reintento)' : '' ?><?php endif; ?>
                    <?php if (($timing['curl_error'] ?? null) !== null): ?> · cURL <?= $timing['curl_error'] ? 'error ' . htmlspecialchars((string) ($timing['curl_errno'] ?? '')) : 'sin error' ?><?php endif; ?>
                    <?php if (($timing['json_valid'] ?? null) !== null): ?> · JSON <?= $timing['json_valid'] ? 'válido' : 'inválido' ?><?php endif; ?>
                    · Resultado <?= ($timing['outcome'] ?? 'error') === 'success' ? 'datos correctos' : 'estado de error' ?>
                    <?php if (($timing['technical_error'] ?? null) !== null): ?> · Error: <?= htmlspecialchars((string) $timing['technical_error']) ?><?php endif; ?>
                    <?php if (($timing['server_timing'] ?? null) !== null): ?> · Server-Timing: <?= htmlspecialchars((string) $timing['server_timing']) ?><?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>
    <?php
}
