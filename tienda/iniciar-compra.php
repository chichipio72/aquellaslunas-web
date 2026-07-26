<?php

require_once __DIR__ . '/../includes/api-config.php';
require_once __DIR__ . '/../includes/store-database.php';
require_once __DIR__ . '/../includes/store-checkout-session.php';
require_once __DIR__ . '/../includes/store-checkout.php';
require_once __DIR__ . '/../includes/mercado-pago-client.php';
require_once __DIR__ . '/../includes/asset-url.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
startStoreCheckoutSession();

$status = 400;
$publicMessage = 'No pudimos iniciar la compra. Revisá la selección e intentá nuevamente.';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    $status = 405;
} elseif (!storeCheckoutCsrfIsValid($_POST['csrf_token'] ?? null)) {
    $publicMessage = 'No pudimos validar la solicitud. Volvé a la galería e intentá nuevamente.';
} else {
    try {
        $config = loadMercadoPagoConfig();
        $publicPhotoIds = normalizeStoreCheckoutPhotoIds($_POST['photos'] ?? null);
        $order = createStorePendingOrder(getStoreDatabaseConnection(), $publicPhotoIds);
        $payload = buildMercadoPagoPreferencePayload($order, $config);
        $preference = createMercadoPagoPreference($config, $payload, $order['public_code']);
        recordStoreMercadoPagoPreference(getStoreDatabaseConnection(), $order, $preference);
        header('Location: ' . $preference['checkout_url'], true, 303);
        exit;
    } catch (Throwable $exception) {
        $code = strtoupper((string) $exception->getCode());
        $code = preg_match('/^[A-Z0-9_-]{1,32}$/', $code) === 1 ? $code : 'UNAVAILABLE';
        error_log('Store checkout failed [type=' . get_debug_type($exception) . ' code=' . $code . '].');
        $status = 503;
    }
}
http_response_code($status);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>No se pudo iniciar la compra</title><link rel="stylesheet" href="<?= htmlspecialchars('../' . versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>"></head><body><main class="page payment-return"><section class="container card payment-return__card"><p class="eyebrow">Tienda</p><h1>No se pudo iniciar la compra</h1><p><?= htmlspecialchars($publicMessage, ENT_QUOTES, 'UTF-8') ?></p><p><a href="../galeria.php">Volver a la galería</a></p></section></main></body></html>
