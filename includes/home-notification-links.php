<?php

declare(strict_types=1);

require_once __DIR__ . '/current-datetime.php';

/** @return array<string,array{label:string,aria:string}> */
function homeNotificationCategories(): array
{
    return [
        'moonrise' => ['label' => 'Salida de la Luna', 'aria' => 'salida de la Luna'],
        'eclipse' => ['label' => 'Eclipses', 'aria' => 'eclipses'],
        'lunar_conjunction' => ['label' => 'Conjunciones cercanas', 'aria' => 'conjunciones cercanas'],
        'satellite_transit' => ['label' => 'Tránsitos ISS/Tiangong', 'aria' => 'tránsitos ISS/Tiangong'],
    ];
}

function homeNotificationCategory(string $notificationType): ?array
{
    return homeNotificationCategories()[$notificationType] ?? null;
}

function homeNotificationTypeForEvent(array $event): ?string
{
    return match ((string) ($event['type'] ?? '')) {
        'eclipse' => 'eclipse',
        'conjunction' => 'lunar_conjunction',
        default => null,
    };
}

function renderHomeNotificationLink(string $notificationType): void
{
    $category = homeNotificationCategory($notificationType);
    if ($category === null) return;
    $label = 'Configurar avisos de ' . $category['aria'];
    $url = astronomyInternalUrl('notificaciones.php') . '?notification_type=' . rawurlencode($notificationType)
        . '#tipos-de-aviso';
    ?>
    <a class="home-notification-link" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
       data-home-notification-link data-notification-type="<?= htmlspecialchars($notificationType, ENT_QUOTES, 'UTF-8') ?>"
       data-notification-label="<?= htmlspecialchars($category['aria'], ENT_QUOTES, 'UTF-8') ?>"
       data-notification-state="unknown" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
       title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
    </a>
    <?php
}
