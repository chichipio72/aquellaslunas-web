<?php

require_once __DIR__ . '/../includes/store-admin-auth.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendStoreAdminHeaders();
startStoreAdminSession();
if (storeAdminIsAuthenticated()) {
    header('Location: ' . STORE_ADMIN_HOME_PATH, true, 303);
    exit;
}
$error = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = true;
    } else {
        try {
            if (attemptStoreAdminLogin((string) ($_POST['user'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                header('Location: ' . STORE_ADMIN_HOME_PATH, true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Store admin login unavailable [type=' . get_debug_type($exception) . '].');
        }
        $error = true;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive"><title>Administración · Aquellas Lunas</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= htmlspecialchars('../' . versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="store-admin"><main class="store-admin-login"><section class="card store-admin-panel">
    <p class="eyebrow">Área privada</p><h1>Administración</h1>
    <?php if ($error): ?><p class="store-admin-alert" role="alert">No se pudo iniciar sesión.</p><?php endif; ?>
    <form method="post" class="store-admin-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(storeAdminCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <label>Usuario<input name="user" autocomplete="username" required></label>
        <label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label>
        <button type="submit">Ingresar</button>
    </form>
</section></main></body></html>
