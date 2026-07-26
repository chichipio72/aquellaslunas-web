<?php

require_once __DIR__ . '/asset-url.php';

function renderStorePaymentReturn(string $title, string $message): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title><link rel="stylesheet" href="<?= htmlspecialchars('../' . versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>"></head><body><main class="page payment-return"><section class="container card payment-return__card"><p class="eyebrow">Tienda</p><h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1><p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><p>El estado definitivo se confirmará posteriormente. Por ahora no hay descargas habilitadas.</p><p><a href="../galeria.php">Volver a la galería</a></p></section></main></body></html>
<?php
}
