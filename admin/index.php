<?php

require_once __DIR__ . '/../includes/store-admin-auth.php';

sendStoreAdminHeaders();
startStoreAdminSession();
header('Location: ' . (storeAdminIsAuthenticated() ? 'fotos.php' : 'login.php'), true, 303);
exit;
