<?php

require_once __DIR__ . '/store-admin-auth.php';

function renderStoreAdminNavigation(string $activeSection, string $title): void
{
    $sections = [
        'home' => ['label' => 'Inicio', 'href' => 'index.php'],
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
                <a class="store-admin-navigation__link<?= $section === $activeSection ? ' is-active' : '' ?>" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"<?= $section === $activeSection ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
            </div>
            <form method="post" action="logout.php" class="store-admin-navigation__logout">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(storeAdminCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="store-admin-navigation__logout-button">Cerrar sesión</button>
            </form>
        </nav>
    </header>
    <?php
}
