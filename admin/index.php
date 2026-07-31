<?php

require_once __DIR__ . '/../includes/store-admin-auth.php';
require_once __DIR__ . '/../includes/store-admin-navigation.php';
require_once __DIR__ . '/../includes/asset-url.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Administración · Aquellas Lunas</title>
    <link rel="stylesheet" href="<?= htmlspecialchars('../' . versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="store-admin">
    <?php renderStoreAdminNavigation('home', 'Administración'); ?>
    <main class="store-admin-main store-admin-dashboard">
        <div class="store-admin-dashboard__intro">
            <p class="eyebrow">Herramientas</p>
            <h2>Panel general</h2>
            <p>Elegí el área que querés administrar.</p>
        </div>
        <div class="store-admin-dashboard__cards">
            <a class="card store-admin-dashboard__card" href="fotos.php">
                <span class="store-admin-dashboard__card-label">Galería y tienda</span>
                <span>Gestioná imágenes, publicaciones, productos y descargas.</span>
                <strong>Entrar a Galería</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="laboratorio-astronomico.php">
                <span class="store-admin-dashboard__card-label">Laboratorio astronómico</span>
                <span>Explorá y compará datos astronómicos mediante gráficos interactivos.</span>
                <strong>Entrar al Laboratorio</strong>
            </a>
        </div>
    </main>
</body>
</html>
