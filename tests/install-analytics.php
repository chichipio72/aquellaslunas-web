<?php

require_once __DIR__ . '/../includes/analytics.php';
require_once __DIR__ . '/../includes/site-sections.php';

function installAnalyticsAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

ob_start();
renderAstronomySiteNavigation('home');
$navigation = ob_get_clean();

$installPosition = strpos($navigation, '>Instalar Aquellas Lunas</button>');
$aboutPosition = strpos($navigation, '>Acerca del sitio</a>');
installAnalyticsAssert($installPosition !== false, 'El menú no usa Instalar Aquellas Lunas.');
installAnalyticsAssert($aboutPosition !== false && $installPosition < $aboutPosition, 'Instalar Aquellas Lunas no está antes de Acerca del sitio.');
installAnalyticsAssert(!str_contains($navigation, 'Guardar Aquellas Lunas'), 'El menú conserva el nombre anterior.');
installAnalyticsAssert(str_contains($navigation, 'data-install-source="menu"'), 'El menú no identifica el origen de Analytics.');

ob_start();
renderAnalyticsTracking();
renderAnalyticsTracking();
$analytics = ob_get_clean();
installAnalyticsAssert(substr_count($analytics, 'googletagmanager.com/gtag/js') === 1, 'Analytics se carga más de una vez.');
installAnalyticsAssert(str_contains($analytics, 'aquellasLunasTrackAnalyticsEvent'), 'Falta el helper seguro de eventos.');

$installScript = file_get_contents(__DIR__ . '/../assets/js/install-prompt.js');
installAnalyticsAssert(is_string($installScript), 'No se pudo leer el script de instalación.');
foreach ([
    'pwa_install_open',
    'pwa_install_prompt',
    'pwa_install_accepted',
    'pwa_install_dismissed',
    'pwa_ios_instructions',
    'pwa_favorite_help',
    'pwa_promo_closed',
] as $eventName) {
    installAnalyticsAssert(str_contains($installScript, "'$eventName'"), 'Falta el evento ' . $eventName . '.');
}
foreach (['source', 'platform', 'browser', 'display_mode', 'action'] as $parameter) {
    installAnalyticsAssert(str_contains($installScript, $parameter . ':'), 'Falta el parámetro ' . $parameter . '.');
}
installAnalyticsAssert(str_contains($installScript, "choice?.outcome === 'accepted'"), 'No se distingue la aceptación del diálogo.');
installAnalyticsAssert(str_contains($installScript, "choice?.outcome === 'dismissed'"), 'No se distingue el rechazo del diálogo.');
installAnalyticsAssert(str_contains($installScript, "isInstalledApp()"), 'No se contempla el modo instalado.');

echo "Instalación y Analytics: OK\n";
