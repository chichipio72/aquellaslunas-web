<?php

require_once __DIR__ . '/site-sections.php';
require_once __DIR__ . '/location-context.php';

function renderAstronomySiteHeader(string $currentSectionId, ?array $location = null): void
{
    $location ??= astronomyLocationContext();
    ?>
    <header class="site-header">
        <div class="container header-inner">
            <a href="<?= htmlspecialchars(astronomySiteSectionUrl('home'), ENT_QUOTES, 'UTF-8') ?>" class="brand">
                <span class="brand-name">Aquellas Lunas</span>
                <span class="brand-tagline">Una Luna diferente cada noche</span>
            </a>
            <a class="header-location" href="<?= htmlspecialchars(astronomyInternalUrl('ubicacion.php'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="visually-hidden">Ubicación activa: </span><?= htmlspecialchars($location['name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
            <button class="menu-toggle" type="button" aria-controls="site-menu-panel" aria-expanded="false">
                <span aria-hidden="true">☰</span><span class="visually-hidden">Abrir menú</span>
            </button>
            <div class="menu-backdrop" data-menu-close hidden></div>
            <aside id="site-menu-panel" class="site-menu-panel" aria-label="Menú del sitio" aria-hidden="true">
                <button class="menu-close" type="button" data-menu-close aria-label="Cerrar menú">×</button>
                <?php renderAstronomySiteNavigation($currentSectionId); ?>
                <section class="menu-location" aria-labelledby="menu-location-title">
                    <h2 id="menu-location-title">Ubicación</h2>
                    <p><?= htmlspecialchars($location['name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <a href="<?= htmlspecialchars(astronomyInternalUrl('ubicacion.php'), ENT_QUOTES, 'UTF-8') ?>">Configurar ubicación</a>
                </section>
            </aside>
        </div>
    </header>
    <?php
}
