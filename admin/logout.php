<?php

require_once __DIR__ . '/../includes/store-admin-auth.php';

sendStoreAdminHeaders();
startStoreAdminSession();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
if (!storeAdminIsAuthenticated() || !storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit;
}
destroyStoreAdminSession();
header('Location: login.php', true, 303);
exit;
