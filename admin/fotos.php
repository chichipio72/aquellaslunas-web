<?php

require_once __DIR__ . '/../includes/store-admin-auth.php';
require_once __DIR__ . '/../includes/store-admin-navigation.php';
require_once __DIR__ . '/../includes/store-database.php';
require_once __DIR__ . '/../includes/store-admin-photos.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();
$message = null;
$error = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = true;
    } else {
        $action = $_POST['action'] ?? '';
        try {
            $connection = getStoreDatabaseConnection();
            if ($action === 'availability') {
                $photoId = filter_var($_POST['photo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $availability = $_POST['availability'] ?? null;
                if ($photoId === false || !in_array($availability, ['0', '1'], true) || !setStorePhotoAvailability($connection, (int) $photoId, $availability === '1')) {
                    throw new RuntimeException('Cambio inválido.');
                }
                $message = $availability === '1' ? 'La foto fue publicada.' : 'La foto fue ocultada.';
            } elseif ($action === 'individual_price') {
                $photoId = filter_var($_POST['photo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $price = normalizeStorePhotoPrice($_POST['price'] ?? null);
                if ($photoId === false || $price === null || !setStorePhotoPrice($connection, (int) $photoId, $price)) {
                    throw new RuntimeException('Cambio inválido.');
                }
                $message = 'El precio fue actualizado.';
            } elseif ($action === 'batch_price') {
                $price = normalizeStorePhotoPrice($_POST['price'] ?? null);
                $selected = $_POST['selected_photos'] ?? null;
                if ($price === null || !is_array($selected) || $selected === []) {
                    throw new RuntimeException('Cambio inválido.');
                }
                $updated = setStorePhotoPrices($connection, $selected, $price);
                $message = $updated === 1 ? 'Se actualizó el precio de una foto.' : "Se actualizaron los precios de {$updated} fotos.";
            } elseif ($action === 'editorial_metadata') {
                $photoId = filter_var($_POST['photo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $title = normalizeStorePhotoEditorialText($_POST['title'] ?? null, 255);
                $description = normalizeStorePhotoEditorialText($_POST['description'] ?? null, 5000);
                $keywords = normalizeStorePhotoEditorialText($_POST['keywords'] ?? null, 2000);
                if ($photoId === false || !setStorePhotoEditorialMetadata($connection, (int) $photoId, $title, $description, $keywords)) {
                    throw new RuntimeException('Cambio inválido.');
                }
                $message = 'Los datos editoriales fueron actualizados.';
            } else {
                throw new RuntimeException('Cambio inválido.');
            }
        } catch (Throwable $exception) {
            error_log('Store admin update failed [type=' . get_debug_type($exception) . '].');
            $error = true;
        }
    }
}
$photos = [];
try {
    foreach (loadStoreAdminPhotos(getStoreDatabaseConnection()) as $photo) {
        $photos[] = storeAdminPhotoPresentation($photo);
    }
} catch (Throwable $exception) {
    error_log('Store admin photo list failed [type=' . get_debug_type($exception) . '].');
    $error = true;
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive"><title>Fotos · Administración</title><?php renderFaviconLinks('../'); ?>
<link rel="stylesheet" href="<?= htmlspecialchars('../' . versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>"></head>
<body class="store-admin"><?php renderStoreAdminNavigation('gallery', 'Galería'); ?>
<main class="store-admin-main">
<?php if ($message): ?><p class="store-admin-success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($error): ?><p class="store-admin-alert" role="alert">No se pudo completar la operación.</p><?php endif; ?>
<?php if ($photos !== []): ?><form method="post" id="batch-price-form" class="card store-admin-batch"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(storeAdminCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="batch_price"><label>Precio para las fotos seleccionadas <span class="store-admin-currency">misma moneda actual</span><input name="price" inputmode="decimal" placeholder="0,00" required></label><button type="submit">Asignar precio</button></form><?php endif; ?>
<div class="store-admin-photo-list">
<?php foreach ($photos as $photo): ?><article class="card store-admin-photo <?= $photo['available'] ? 'is-published' : 'is-hidden' ?>">
<label class="store-admin-select"><input type="checkbox" name="selected_photos[]" value="<?= $photo['id'] ?>" form="batch-price-form"><span class="visually-hidden">Seleccionar <?= htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8') ?></span></label>
<?php if ($photo['preview_url']): ?><img src="<?= htmlspecialchars($photo['preview_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"><?php else: ?><div class="store-admin-photo__placeholder">Sin preview comercial</div><?php endif; ?>
<div class="store-admin-photo__details"><h2><?= htmlspecialchars($photo['title'], ENT_QUOTES, 'UTF-8') ?></h2><p class="store-admin-status"><?= $photo['available'] ? 'Publicada' : 'Oculta' ?></p><p class="store-admin-current-price"><?= htmlspecialchars(str_replace('.', ',', $photo['price']) . ' ' . $photo['currency'], ENT_QUOTES, 'UTF-8') ?></p></div>
<div class="store-admin-photo__actions"><form method="post" class="store-admin-price-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(storeAdminCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="individual_price"><input type="hidden" name="photo_id" value="<?= $photo['id'] ?>"><label><span>Precio (<?= htmlspecialchars($photo['currency'], ENT_QUOTES, 'UTF-8') ?>)</span><input name="price" inputmode="decimal" value="<?= htmlspecialchars(str_replace('.', ',', $photo['price']), ENT_QUOTES, 'UTF-8') ?>" required></label><button type="submit">Guardar precio</button></form>
<form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(storeAdminCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="availability"><input type="hidden" name="photo_id" value="<?= $photo['id'] ?>"><input type="hidden" name="availability" value="<?= $photo['available'] ? '0' : '1' ?>"><button type="submit" class="secondary-button"><?= $photo['available'] ? 'Ocultar' : 'Publicar' ?></button></form></div>
<form method="post" class="store-admin-metadata-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(storeAdminCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="editorial_metadata"><input type="hidden" name="photo_id" value="<?= $photo['id'] ?>"><label>Título<input name="title" maxlength="255" value="<?= htmlspecialchars($photo['editorial_title'], ENT_QUOTES, 'UTF-8') ?>"></label><label>Descripción<textarea name="description" maxlength="5000" rows="4"><?= htmlspecialchars($photo['description'], ENT_QUOTES, 'UTF-8') ?></textarea></label><label>Palabras clave <span>separadas por comas</span><textarea name="keywords" maxlength="2000" rows="2"><?= htmlspecialchars($photo['keywords'], ENT_QUOTES, 'UTF-8') ?></textarea></label><button type="submit">Guardar datos editoriales</button></form>
</article><?php endforeach; ?>
<?php if ($photos === [] && !$error): ?><p class="card store-admin-empty">No hay fotos registradas.</p><?php endif; ?>
</div></main></body></html>
