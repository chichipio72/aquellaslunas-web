<?php

require_once __DIR__ . '/asset-url.php';
require_once __DIR__ . '/site-footer.php';

function renderAstronomyContactBlock(string $source): void
{
    $allowedSources = ['about', 'features_page'];
    if (!in_array($source, $allowedSources, true)) {
        throw new InvalidArgumentException('Invalid contact analytics source.');
    }
    $profile = astronomyInstagramProfile();
    ?>
    <section class="card astronomy-contact" aria-labelledby="astronomy-contact-title-<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>">
        <div class="astronomy-contact__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="24" height="24" focusable="false">
                <rect x="3" y="3" width="18" height="18" rx="5"></rect>
                <circle cx="12" cy="12" r="4.25"></circle>
                <circle class="astronomy-contact__icon-dot" cx="17.4" cy="6.8" r="1"></circle>
            </svg>
        </div>
        <div class="astronomy-contact__content">
            <h2 id="astronomy-contact-title-<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>">Ayudame a mejorar Aquellas Lunas</h2>
            <p>Aquellas Lunas está pensada para ser útil, clara y fácil de usar. Si encontrás un error, algo que no se entiende o tenés una sugerencia, me interesa saberlo.</p>
            <p>Podés escribirme por Instagram:<br><strong><?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?></strong></p>
            <a class="button astronomy-contact__action" href="<?= htmlspecialchars($profile['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" data-instagram-contact data-contact-source="<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" focusable="false" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="5"></rect>
                    <circle cx="12" cy="12" r="4.25"></circle>
                    <circle class="astronomy-contact__icon-dot" cx="17.4" cy="6.8" r="1"></circle>
                </svg>
                <span>Escribir por Instagram</span>
            </a>
        </div>
    </section>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/contact-analytics.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php
}
