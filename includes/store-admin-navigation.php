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
        'home' => ['label' => 'Inicio', 'href' => 'index.php'],
        'contents' => ['label' => 'Contenidos', 'href' => 'contenidos/'],
        'site_configuration' => ['label' => 'Configuración del sitio', 'href' => 'configuracion-sitio/'],
        'gallery' => ['label' => 'Galería', 'href' => 'fotos.php'],
        'laboratory' => ['label' => 'Laboratorio', 'href' => 'laboratorio-astronomico.php'],
    ];
    if (!array_key_exists($activeSection, $sections)) {
        throw new InvalidArgumentException('La sección administrativa activa no es válida.');
    }
    ?>
    <header class="store-admin-header store-admin-header--navigation">
        <div class="store-admin-header__title">
            <p class="eyebrow">Área privada</p>
            <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <nav class="store-admin-navigation" aria-label="Administración">
            <div class="store-admin-navigation__links">
            <?php foreach ($sections as $section => $item): ?>
                <a class="store-admin-navigation__link<?= $section === $activeSection ? ' is-active' : '' ?>" href="<?= htmlspecialchars($rootPrefix . $item['href'], ENT_QUOTES, 'UTF-8') ?>"<?= $section === $activeSection ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
            </div>
            <form method="post" action="<?= htmlspecialchars($rootPrefix . 'logout.php', ENT_QUOTES, 'UTF-8') ?>" class="store-admin-navigation__logout">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(storeAdminCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="store-admin-navigation__logout-button">Cerrar sesión</button>
            </form>
        </nav>
    </header>
    <?php
}
