<?php

function astronomyInstagramProfile(): array
{
    return [
        'url' => 'https://www.instagram.com/aquellas_lunas/',
        'username' => '@aquellas_lunas',
    ];
}

function renderAstronomyInstagramLink(string $className = ''): void
{
    $profile = astronomyInstagramProfile();
    ?>
    <a<?= $className !== '' ? ' class="' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '"' : '' ?> href="<?= htmlspecialchars($profile['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Instagram · <?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?></a>
    <?php
}

function renderAstronomySiteFooter(): void
{
    ?>
    <footer class="site-footer">
        <div class="container site-footer__content">
            <small class="site-footer__social"><?php renderAstronomyInstagramLink(); ?></small>
            <small>Datos de localidad: <a href="https://www.openstreetmap.org/copyright" rel="external">© OpenStreetMap contributors</a></small>
        </div>
    </footer>
    <?php
}
