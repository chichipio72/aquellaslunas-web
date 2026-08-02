<?php

require_once __DIR__ . '/../../includes/api-config.php';

if (!isLocalEnvironment()) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/store-database.php';
require_once __DIR__ . '/../../includes/store-photo-sync.php';
require_once __DIR__ . '/../../includes/store-preview-generator.php';
require_once __DIR__ . '/../../includes/store-photo-upload.php';
require_once __DIR__ . '/editor-core.php';

sendStoreAdminHeaders();
header('Content-Type: application/json; charset=utf-8');
startStoreAdminSession();

function photoUploadResponse(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!storeAdminIsAuthenticated()) {
    photoUploadResponse(401, ['error' => 'La sesión administrativa no está activa.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    photoUploadResponse(405, ['error' => 'Método no permitido.']);
}
if (!contentEditorCsrfValid($_POST['csrf_token'] ?? null)) {
    photoUploadResponse(403, ['error' => 'El token CSRF no es válido.']);
}

$uploads = storePhotoUploadFiles($_FILES['photos'] ?? []);
if ($uploads === [] || count($uploads) > STORE_PHOTO_UPLOAD_MAX_FILES) {
    photoUploadResponse(422, ['error' => 'Seleccioná entre 1 y 20 imágenes.']);
}

$results = [];
$accepted = [];
try {
    $config = loadStoreConfig();
    $originalsPath = $config['originals_path'];
    if (!is_writable($originalsPath) || is_link($originalsPath)) {
        throw new RuntimeException('El almacenamiento privado no está disponible para escritura.');
    }
    $connection = getStoreDatabaseConnection();
    $initialPrice = loadStoreInitialPrice();

    foreach ($uploads as $index => $upload) {
        try {
            $details = storePhotoUploadValidate($upload);
            $existing = storePhotoRecordsByIds($connection, [$details['photo_id']]);
            if ($existing !== []) {
                $accepted[$details['photo_id']] = $index;
                $results[$index] = ['name' => $upload['name'], 'status' => 'duplicate', 'message' => 'La fotografía ya estaba incorporada.'];
                continue;
            }
            $destination = $originalsPath . DIRECTORY_SEPARATOR . $details['photo_id'] . '.' . $details['extension'];
            if (is_link($destination)) {
                throw new RuntimeException('El destino privado no es seguro.');
            }
            if (!is_file($destination) && !move_uploaded_file($upload['tmp_name'], $destination)) {
                throw new RuntimeException('No se pudo guardar el original privado.');
            }
            @chmod($destination, 0660);
            $accepted[$details['photo_id']] = $index;
            $results[$index] = ['name' => $upload['name'], 'status' => 'processing', 'message' => 'Original guardado; procesando derivados.'];
        } catch (Throwable $exception) {
            $results[$index] = ['name' => $upload['name'], 'status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    if ($accepted !== []) {
        synchronizeStorePhotos($connection, $originalsPath, $initialPrice);
        $photos = storePhotoRecordsByIds($connection, array_keys($accepted));
        if ($photos !== []) {
            generatePendingStorePreviews($connection, $photos, $originalsPath, $config['previews_path'], storePreviewVariants());
            $photos = storePhotoRecordsByIds($connection, array_keys($accepted));
        }
        $photosById = array_column($photos, null, 'foto_id');
        foreach ($accepted as $photoId => $index) {
            $photo = $photosById[$photoId] ?? null;
            if (
                !is_array($photo)
                || trim((string) ($photo['archivo_preview_tienda'] ?? '')) === ''
                || trim((string) ($photo['archivo_preview_contenido'] ?? '')) === ''
            ) {
                $results[$index]['status'] = 'error';
                $results[$index]['message'] = 'El original quedó guardado, pero no se pudo completar su preview.';
                continue;
            }
            $results[$index]['status'] = $results[$index]['status'] === 'duplicate' ? 'duplicate' : 'success';
            $results[$index]['message'] = $results[$index]['status'] === 'duplicate'
                ? 'La fotografía ya estaba incorporada.'
                : 'Original, base y previews actualizados.';
        }
    }

    photoUploadResponse(200, ['results' => array_values($results), 'gallery' => contentEditorImageGallery()]);
} catch (Throwable $exception) {
    error_log('Local photo upload failed: ' . get_debug_type($exception));
    photoUploadResponse(500, ['error' => $exception->getMessage(), 'results' => array_values($results)]);
}
