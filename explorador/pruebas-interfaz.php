<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-config.php';
require_once dirname(__DIR__) . '/includes/store-admin-auth.php';

if (!isLocalEnvironment() && !storeAdminHasValidSessionCookie()) {
    http_response_code(404);
    exit;
}

$explorerLocationReturnPathOverride = (string) ($_SERVER['SCRIPT_NAME'] ?? '/explorador/pruebas-interfaz.php');
require __DIR__ . '/index.php';
