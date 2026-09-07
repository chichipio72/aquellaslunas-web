<?php

declare(strict_types=1);

require_once __DIR__ . '/site-configuration.php';

function astronomyEclipseWidgetKind(array $event): ?string
{
    return match ((string) ($event['subtype'] ?? '')) {
        'solar_eclipse' => 'solar',
        'lunar_eclipse' => 'lunar',
        default => null,
    };
}

function astronomyEclipseWidgetExpectedPath(string $kind): string
{
    return '/astro/embeds/eclipse-' . $kind . '-real.php';
}

function astronomyEclipseWidgetTemplateKey(string $kind): string
{
    return 'eclipse.widget.' . $kind . '_url';
}

/**
 * Conserva íntegra la consulta configurada y sustituye sólo el contexto del evento.
 */
function astronomyEclipseWidgetContextualizeUrl(string $template, string $kind, string $date, array $location): ?string
{
    if (!in_array($kind, ['solar', 'lunar'], true)) {
        return null;
    }
    $parts = parse_url($template);
    if (!is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || strtolower((string) ($parts['host'] ?? '')) !== 'aquellaslunas.com.ar'
        || (string) ($parts['path'] ?? '') !== astronomyEclipseWidgetExpectedPath($kind)) {
        return null;
    }
    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    $latitude = filter_var($location['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($location['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $elevation = filter_var($location['elevation_meters'] ?? $location['elevation'] ?? 0, FILTER_VALIDATE_FLOAT);
    if ($latitude === false || $longitude === false || $elevation === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        return null;
    }
    $query['date'] = $date;
    $query['lat'] = (string) $latitude;
    $query['lon'] = (string) $longitude;
    $query['elevation'] = (string) $elevation;
    return 'https://aquellaslunas.com.ar' . astronomyEclipseWidgetExpectedPath($kind) . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function astronomyEclipseWidgetUrl(string $kind, string $date, array $location): ?string
{
    if (!in_array($kind, ['solar', 'lunar'], true)) return null;
    $key = astronomyEclipseWidgetTemplateKey($kind);
    $configured = (string) astronomySiteConfigValue($key);
    $default = (string) (astronomySiteConfigCatalog()[$key]['default'] ?? '');
    foreach (array_unique([$configured, $default]) as $template) {
        $url = astronomyEclipseWidgetContextualizeUrl($template, $kind, $date, $location);
        if ($url !== null) {
            $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');
            $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            $basePath = str_contains($scriptName, '/astro/') ? '/astro' : '';
            return $basePath . '/embeds/eclipse-' . $kind . '-real.php' . ($query !== '' ? '?' . $query : '');
        }
    }
    return null;
}

function astronomyEclipseWidgetUrlForEvent(array $event, string $timezoneName, array $location, array $playback = []): ?string
{
    $kind = astronomyEclipseWidgetKind($event);
    if ($kind === null || !is_string($event['datetime'] ?? null)) {
        return null;
    }
    try {
        $date = (new DateTimeImmutable((string) $event['datetime']))
            ->setTimezone(new DateTimeZone($timezoneName))
            ->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
    $url = astronomyEclipseWidgetUrl($kind, $date, $location);
    if ($url === null || $playback === []) return $url;
    $query = [];
    parse_str((string) (parse_url($url, PHP_URL_QUERY) ?? ''), $query);
    foreach (['autoplay', 'controls'] as $key) {
        if (array_key_exists($key, $playback)) $query[$key] = !empty($playback[$key]) ? '1' : '0';
    }
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    return $path !== '' ? $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : null;
}

function renderAstronomyEclipseWidget(array $event, string $timezoneName, array $location, string $title, array $playback = []): bool
{
    $url = astronomyEclipseWidgetUrlForEvent($event, $timezoneName, $location, $playback);
    if ($url === null) {
        return false;
    }
    ?><figure class="eclipse-detail-widget"><iframe src="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" allow="fullscreen" referrerpolicy="strict-origin-when-cross-origin"></iframe><figcaption>Simulación del eclipse desde tu ubicación</figcaption></figure><?php
    return true;
}
