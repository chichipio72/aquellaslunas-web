<?php

require_once __DIR__ . '/api-client.php';

function astronomyDebugClockValue(): ?string
{
    if (!astronomyTimingsEnabled()) {
        return null;
    }
    $value = isset($_GET['debug_now']) ? trim((string) $_GET['debug_now']) : '';
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
        return null;
    }
    $parsed = date_parse($value);
    if (($parsed['error_count'] ?? 1) !== 0 || ($parsed['warning_count'] ?? 1) !== 0 || ($parsed['is_localtime'] ?? false) !== true) {
        return null;
    }
    try {
        new DateTimeImmutable($value);
    } catch (Exception $exception) {
        return null;
    }
    return $value;
}

function get_current_datetime(string $timezoneName): DateTimeImmutable
{
    $timezone = new DateTimeZone($timezoneName);
    $debugValue = astronomyDebugClockValue();
    if ($debugValue === null) {
        return new DateTimeImmutable('now', $timezone);
    }
    return (new DateTimeImmutable($debugValue))->setTimezone($timezone);
}

function astronomyCurrentDateTimeIsSimulated(): bool
{
    return astronomyDebugClockValue() !== null;
}

function astronomyInternalUrl(string $url): string
{
    $debugValue = astronomyDebugClockValue();
    if ($debugValue === null) {
        return $url;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return $url;
    }
    $query = [];
    if (is_string($parts['query'] ?? null)) {
        parse_str($parts['query'], $query);
    }
    $query['debug_now'] = $debugValue;
    $queryString = http_build_query($query);
    $path = (string) ($parts['path'] ?? '');
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return $path . ($queryString !== '' ? '?' . $queryString : '') . $fragment;
}

function astronomyDebugClockUrl(?DateTimeImmutable $dateTime): string
{
    $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : '/';
    $query = $_GET;
    if ($dateTime === null) {
        unset($query['debug_now']);
    } else {
        $query['debug_now'] = $dateTime->format(DateTimeInterface::ATOM);
    }
    $queryString = http_build_query($query);
    return $path . ($queryString !== '' ? '?' . $queryString : '');
}

function renderAstronomyDebugClock(DateTimeImmutable $currentDateTime): void
{
    if (!astronomyCurrentDateTimeIsSimulated()) {
        return;
    }
    $controls = [
        '−1 día' => $currentDateTime->modify('-1 day'),
        '−1 hora' => $currentDateTime->modify('-1 hour'),
        '+1 hora' => $currentDateTime->modify('+1 hour'),
        '+1 día' => $currentDateTime->modify('+1 day'),
    ];
    ?>
    <aside class="debug-clock" aria-label="Controles del reloj simulado">
        <div class="container debug-clock__inner">
            <strong>Modo simulación: <?= htmlspecialchars($currentDateTime->format('d/m/Y H:i')) ?></strong>
            <nav aria-label="Cambiar hora simulada">
                <?php foreach ($controls as $label => $target): ?><a href="<?= htmlspecialchars(astronomyDebugClockUrl($target), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label) ?></a><?php endforeach; ?>
                <a href="<?= htmlspecialchars(astronomyDebugClockUrl(null), ENT_QUOTES, 'UTF-8') ?>">Hora real</a>
            </nav>
        </div>
    </aside>
    <?php
}

function renderAstronomyDebugClockInput(): void
{
    $value = astronomyDebugClockValue();
    if ($value !== null) {
        ?><input type="hidden" name="debug_now" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?php
    }
}
