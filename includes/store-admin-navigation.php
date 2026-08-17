<?php

require_once __DIR__ . '/store-admin-auth.php';

function storeAdminNavigationRootPrefix(?string $scriptName = null): string
{
    $scriptName ??= (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $normalizedScriptName = str_replace('\\', '/', $scriptName);
    $adminMarker = '/admin/';
    $adminPosition = strpos($normalizedScriptName, $adminMarker);
    if ($adminPosition === false) {
        return '';
    }

    $relativePath = substr($normalizedScriptName, $adminPosition + strlen($adminMarker));
    if (!is_string($relativePath) || $relativePath === '') {
        return '';
    }

    $directory = trim(dirname($relativePath), '/.');
    if ($directory === '') {
        return '';
    }

    $segments = array_values(array_filter(explode('/', $directory), static fn(string $segment): bool => $segment !== ''));
    return str_repeat('../', count($segments));
}

function renderStoreAdminNavigation(string $activeSection, string $title): void
{
    $rootPrefix = storeAdminNavigationRootPrefix();

    $sections = [
        'home' => ['label' => 'Inicio', 'href' => './'],
        'contents' => ['label' => 'Contenidos', 'href' => 'contenidos/'],
        'site_configuration' => ['label' => 'Visibilidad de secciones', 'href' => 'configuracion-sitio/'],
        'presentation' => ['label' => 'Visibilidad de eventos', 'href' => 'presentacion/'],
        'astronomy_sources' => ['label' => 'Fuentes astronómicas', 'href' => 'fuentes-astronomicas/'],
        'astronomy_trace' => ['label' => 'Trazabilidad astronómica', 'href' => 'trazabilidad-astronomica.php'],
        'gallery' => ['label' => 'Galería', 'href' => 'fotos.php'],
        'laboratory' => ['label' => 'Laboratorio', 'href' => 'laboratorio-astronomico.php'],
        'notifications' => ['label' => 'Suscripciones', 'href' => 'notificaciones-prueba.php'],
        'astronomy_notifications' => ['label' => 'Notificaciones', 'href' => 'notificaciones-astronomicas.php'],
    ];
    if ($activeSection === 'notification_types') $activeSection = 'astronomy_notifications';
    if (!array_key_exists($activeSection, $sections)) {
        throw new InvalidArgumentException('La sección administrativa activa no es válida.');
    }
    ?>
    <header class="store-admin-header store-admin-header--navigation">
        <div class="store-admin-header__title">
            <p class="eyebrow">Área privada</p>
            <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <nav class="store-admin-navigation" aria-label="Administración" data-store-admin-navigation>
            <a class="store-admin-navigation__public-link" href="<?= htmlspecialchars($rootPrefix . '../', ENT_QUOTES, 'UTF-8') ?>">Ver sitio público</a>
            <div class="store-admin-navigation__menu">
                <button type="button" class="store-admin-navigation__menu-button" aria-expanded="false" aria-haspopup="true" aria-controls="store-admin-navigation-menu" data-store-admin-menu-button>
                    <span>Menú</span><strong><?= htmlspecialchars($sections[$activeSection]['label'], ENT_QUOTES, 'UTF-8') ?></strong><span class="store-admin-navigation__chevron" aria-hidden="true"></span>
                </button>
                <div id="store-admin-navigation-menu" class="store-admin-navigation__links" role="menu" hidden data-store-admin-menu>
            <?php foreach ($sections as $section => $item): ?>
                <a class="store-admin-navigation__link<?= $section === $activeSection ? ' is-active' : '' ?>" href="<?= htmlspecialchars($rootPrefix . $item['href'], ENT_QUOTES, 'UTF-8') ?>"<?= $section === $activeSection ? ' aria-current="page"' : '' ?> role="menuitem"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
                </div>
            </div>
            <form method="post" action="<?= htmlspecialchars($rootPrefix . 'logout.php', ENT_QUOTES, 'UTF-8') ?>" class="store-admin-navigation__logout">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(storeAdminCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="store-admin-navigation__logout-button">Cerrar sesión</button>
            </form>
        </nav>
    </header>
    <script>
    (() => {
        const navigation = document.querySelector('[data-store-admin-navigation]');
        const button = navigation?.querySelector('[data-store-admin-menu-button]');
        const menu = navigation?.querySelector('[data-store-admin-menu]');
        if (!navigation || !button || !menu) return;
        const links = [...menu.querySelectorAll('[role="menuitem"]')];
        const close = (restoreFocus = false) => {
            menu.hidden = true;
            button.setAttribute('aria-expanded', 'false');
            if (restoreFocus) button.focus();
        };
        const open = (focusFirst = false) => {
            menu.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            if (focusFirst) (menu.querySelector('[aria-current="page"]') || links[0])?.focus();
        };
        button.addEventListener('click', () => menu.hidden ? open() : close());
        button.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') { event.preventDefault(); open(true); }
        });
        menu.addEventListener('keydown', (event) => {
            const current = links.indexOf(document.activeElement);
            if (event.key === 'Escape') { event.preventDefault(); close(true); return; }
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : (event.key === 'End' ? links.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length);
            links[next]?.focus();
        });
        links.forEach((link) => link.addEventListener('click', () => close()));
        document.addEventListener('click', (event) => { if (!navigation.contains(event.target)) close(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !menu.hidden) close(true); });
    })();
    </script>
    <?php
}
