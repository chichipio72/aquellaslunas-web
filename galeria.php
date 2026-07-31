<?php

require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/store-database.php';
require_once __DIR__ . '/includes/store-gallery.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/site-sections.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/store-checkout-session.php';

sendDynamicNoCacheHeaders();
startStoreCheckoutSession();
$galleryCurrentDateTime = get_current_datetime('America/Argentina/Buenos_Aires');
$pageSeo = aquellasLunasSeoPage(
    'Galería de fotografías de la Luna',
    'Fotografías de la Luna disponibles en la tienda de Aquellas Lunas.',
    '/galeria.php'
);
$pageSeo['robots'] = 'noindex, nofollow';
$galleryPhotos = [];
$galleryError = false;

try {
    foreach (loadAvailableStorePhotos(getStoreDatabaseConnection()) as $photo) {
        $presentation = storeGalleryPhotoPresentation($photo);
        if ($presentation !== null) {
            $galleryPhotos[] = $presentation;
        }
    }
} catch (Throwable $exception) {
    $errorCode = strtoupper((string) $exception->getCode());
    $errorCode = preg_match('/^[A-Z0-9_-]{1,32}$/', $errorCode) === 1 ? $errorCode : 'UNAVAILABLE';
    error_log(sprintf('Store gallery load failed [type=%s code=%s].', get_debug_type($exception), $errorCode));
    $galleryError = true;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<?php renderSeoHead($pageSeo); ?>
<?php renderAnalyticsTracking(); ?>
<?php renderFaviconLinks(); ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/location.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/navigation-indicator.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/gallery.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php renderAstronomyMobileSwipeNavigationScript('gallery'); ?>
</head>
<body<?= astronomyMobileSwipeNavigationAttributes('gallery') ?>>
    <?php require __DIR__ . '/includes/navigation-indicator.php'; ?>
    <?php renderAstronomyDebugClock($galleryCurrentDateTime); ?>
    <?php renderAstronomySiteHeader('gallery'); ?>

    <main class="page gallery-page">
        <div class="container gallery-container">
            <header class="gallery-heading">
                <p class="eyebrow">Tienda</p>
                <h1>Galería</h1>
                <p>Fotografías de la Luna disponibles.</p>
            </header>

            <?php if ($galleryError): ?>
                <p class="card gallery-message" role="alert">No pudimos cargar la galería. Intentá nuevamente más tarde.</p>
            <?php elseif ($galleryPhotos === []): ?>
                <p class="card gallery-message" role="status">Todavía no hay fotografías disponibles.</p>
            <?php else: ?>
                <form method="post" action="tienda/iniciar-compra.php" class="gallery-purchase" data-gallery-purchase>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(storeCheckoutCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="gallery-grid">
                    <?php foreach ($galleryPhotos as $index => $photo): ?>
                        <article class="gallery-item" data-gallery-item>
                            <label class="gallery-item__select"><input type="checkbox" name="photos[]" value="<?= htmlspecialchars($photo['public_id'], ENT_QUOTES, 'UTF-8') ?>" data-gallery-select data-price-cents="<?= $photo['price_cents'] ?>" data-currency="<?= htmlspecialchars($photo['currency'], ENT_QUOTES, 'UTF-8') ?>"><span>Seleccionar</span></label>
                            <button class="gallery-item__trigger" type="button" data-gallery-open aria-label="Ampliar <?= htmlspecialchars($photo['title'] ?? 'fotografía de la Luna', ENT_QUOTES, 'UTF-8') ?>">
                                <img src="<?= htmlspecialchars($photo['preview_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($photo['alt'], ENT_QUOTES, 'UTF-8') ?>"<?= $photo['width'] !== null ? ' width="' . $photo['width'] . '"' : '' ?><?= $photo['height'] !== null ? ' height="' . $photo['height'] . '"' : '' ?> loading="<?= $index < 4 ? 'eager' : 'lazy' ?>" decoding="async">
                            </button>
                            <div class="gallery-item__details">
                                <?php if ($photo['title'] !== null): ?><h2><?= htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8') ?></h2><?php endif; ?>
                                <p class="gallery-item__price"><?= htmlspecialchars($photo['price_label'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                    <aside class="card gallery-purchase-summary" aria-live="polite"><div><strong data-gallery-selection-count>0 fotos seleccionadas</strong><span data-gallery-selection-total>Total: 0,00</span></div><button type="submit" data-gallery-checkout disabled>Comprar seleccionadas</button></aside>
                </form>
            <?php endif; ?>
        </div>
    </main>
    <?php renderAstronomySiteFooter(); ?>

    <dialog class="gallery-modal" data-gallery-modal aria-label="Vista ampliada de fotografía">
        <div class="gallery-modal__surface">
            <button class="gallery-modal__close" type="button" data-gallery-close aria-label="Cerrar vista ampliada">×</button>
            <img data-gallery-modal-image alt="">
            <div class="gallery-modal__details">
                <h2 id="gallery-modal-title" data-gallery-modal-title hidden></h2>
                <p data-gallery-modal-price></p>
            </div>
        </div>
    </dialog>
</body>
</html>
