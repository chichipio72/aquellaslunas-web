<?php

require_once __DIR__ . '/../includes/store-admin-auth.php';
require_once __DIR__ . '/../includes/store-admin-navigation.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

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
    <?php renderFaviconLinks('../'); ?>
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
                <span class="store-admin-dashboard__card-label">Contenidos</span>
                <span>Administrá artículos, trivias y bloques “Sabías que…” directamente en MySQL.</span>
                <strong>Entrar a Contenidos</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="configuracion-sitio/">
                <span class="store-admin-dashboard__card-label">Menú y secciones</span>
                <span>Activá o desactivá visibilidad de contenidos, entradas del menú principal y tarjetas de portada.</span>
                <strong>Entrar a Configuración del sitio</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="luna-portada/">
                <span class="store-admin-dashboard__card-label">Luna de portada</span>
                <span>Ajustá iluminación, relieve y tratamiento de textura del render Three.js de “El cielo hoy”.</span>
                <strong>Configurar Luna</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="widgets-lunares/">
                <span class="store-admin-dashboard__card-label">Widgets lunares</span>
                <span>Probá los embeds de libración y Luna interactiva y copiá su código iframe.</span>
                <strong>Abrir generador de widgets</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="fotografia/">
                <span class="store-admin-dashboard__card-label">Fotografía</span>
                <span>Administrá escenas, variantes y referencias fotográficas.</span>
                <strong>Entrar a Fotografía</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="presentacion/">
                <span class="store-admin-dashboard__card-label">Visibilidad de eventos</span>
                <span>Configurá nombres y superficies públicas para los tipos de eventos astronómicos.</span>
                <strong>Entrar a Presentación</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="fuentes-astronomicas/">
                <span class="store-admin-dashboard__card-label">Fuentes astronómicas</span>
                <span>Elegí la fuente disponible para cada grupo de eventos astronómicos.</span>
                <strong>Configurar fuentes</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="fotos.php">
                <span class="store-admin-dashboard__card-label">Galería</span>
                <span>Gestioná imágenes, publicaciones, productos y descargas.</span>
                <strong>Entrar a Galería</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="laboratorio-astronomico.php">
                <span class="store-admin-dashboard__card-label">Laboratorio</span>
                <span>Explorá y compará datos astronómicos mediante gráficos interactivos.</span>
                <strong>Entrar al Laboratorio</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="notificaciones-prueba.php">
                <span class="store-admin-dashboard__card-label">Suscripciones</span>
                <span>Revisá dispositivos, actividad y salud de las suscripciones Web Push.</span>
                <strong>Ver suscripciones</strong>
            </a>
            <a class="card store-admin-dashboard__card" href="notificaciones-astronomicas.php">
                <span class="store-admin-dashboard__card-label">Notificaciones</span>
                <span>Configurá avisos, revisá resultados y supervisá el scheduler.</span>
                <strong>Gestionar notificaciones</strong>
            </a>
        </div>
    </main>
</body>
</html>
