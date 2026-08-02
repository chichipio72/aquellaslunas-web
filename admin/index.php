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
            <a class="card store-admin-dashboard__card" href="contenidos/">
                <span class="store-admin-dashboard__card-label">Contenidos editoriales</span>
                <span>Administrá artículos, trivias y bloques “Sabías que…” directamente en MySQL.</span>
                <strong>Entrar a Contenidos</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="configuracion-sitio/">
                <span class="store-admin-dashboard__card-label">Configuración del sitio</span>
                <span>Activá o desactivá visibilidad de contenidos, entradas del menú principal y tarjetas de portada.</span>
                <strong>Entrar a Configuración del sitio</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="presentacion/">
                <span class="store-admin-dashboard__card-label">Presentación del sitio</span>
                <span>Configurá nombres y superficies públicas para los tipos de eventos astronómicos.</span>
                <strong>Entrar a Presentación</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="fuentes-astronomicas/">
                <span class="store-admin-dashboard__card-label">Fuentes astronómicas</span>
                <span>Elegí la fuente disponible para cada grupo de eventos astronómicos.</span>
                <strong>Configurar fuentes</strong>
            </a>
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
            <a class="card store-admin-dashboard__card" href="notificaciones-prueba.php">
                <span class="store-admin-dashboard__card-label">Notificaciones de prueba</span>
                <span>Suscribí este dispositivo y enviá notificaciones Web Push manuales.</span>
                <strong>Probar notificaciones</strong>
            </a>
        </div>
    </main>
</body>
</html>
