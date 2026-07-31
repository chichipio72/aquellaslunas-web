<?php

if (!function_exists('aquellasLunasAnalyticsMeasurementId')) {
    function aquellasLunasAnalyticsMeasurementId(): string
    {
        return 'G-GFZJ3D3MF3';
    }
}

if (!function_exists('renderAnalyticsTracking')) {
    function renderAnalyticsTracking(): void
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }

        $rendered = true;
        $measurementId = aquellasLunasAnalyticsMeasurementId();

        echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . htmlspecialchars($measurementId, ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
        echo '<script>' . PHP_EOL;
        echo "window.dataLayer = window.dataLayer || [];" . PHP_EOL;
        echo "function gtag(){dataLayer.push(arguments);}" . PHP_EOL;
        echo "gtag('js', new Date());" . PHP_EOL;
        echo "gtag('config', '" . htmlspecialchars($measurementId, ENT_QUOTES, 'UTF-8') . "');" . PHP_EOL;
        echo "window.aquellasLunasTrackAnalyticsEvent = function(name, params) {" . PHP_EOL;
        echo "    if (typeof window.gtag !== 'function') { return false; }" . PHP_EOL;
        echo "    try { window.gtag('event', name, params || {}); return true; } catch (error) { return false; }" . PHP_EOL;
        echo "};" . PHP_EOL;
        echo '</script>' . PHP_EOL;
    }
}
