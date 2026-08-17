<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-client.php';
require_once dirname(__DIR__) . '/includes/home-notification-links.php';

function homeNotificationAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

homeNotificationAssert(homeNotificationTypeForEvent(['type' => 'eclipse']) === 'eclipse', 'Debe mapear eclipses.');
homeNotificationAssert(homeNotificationTypeForEvent(['type' => 'conjunction']) === 'lunar_conjunction', 'Debe mapear conjunciones.');
foreach (['moon_phase', 'apsis', 'lunar_nodes', 'libration'] as $type) {
    homeNotificationAssert(homeNotificationTypeForEvent(['type' => $type]) === null, 'No debe mapear ' . $type . '.');
}

ob_start();
renderHomeNotificationLink('moonrise');
$moonrise = (string) ob_get_clean();
homeNotificationAssert(str_contains($moonrise, 'notification_type=moonrise'), 'Salida lunar debe enlazar su categoría.');
homeNotificationAssert(str_contains($moonrise, 'data-notification-state="unknown"'), 'El estado inicial debe ser neutral.');

ob_start();
renderHomeNotificationLink('satellite_transit');
$satellite = (string) ob_get_clean();
homeNotificationAssert(str_contains($satellite, 'notification_type=satellite_transit'), 'Tránsitos deben enlazar su categoría.');

foreach (['eclipse', 'lunar_conjunction'] as $type) {
    ob_start();
    renderHomeNotificationLink($type);
    $link = (string) ob_get_clean();
    homeNotificationAssert(str_contains($link, 'notification_type=' . $type), 'Debe enlazar la categoría ' . $type . '.');
    homeNotificationAssert(!str_contains($link, 'checkbox'), 'El acceso contextual no debe cambiar preferencias.');
}

ob_start();
renderHomeNotificationLink('not_available');
$unsupported = (string) ob_get_clean();
homeNotificationAssert($unsupported === '', 'No debe renderizar categorías inexistentes.');

echo "OK: accesos contextuales de notificaciones\n";
