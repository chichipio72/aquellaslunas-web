<?php

require_once __DIR__ . '/../includes/store-admin-auth.php';
require_once __DIR__ . '/../includes/store-admin-photos.php';
require_once __DIR__ . '/../includes/store-database.php';

function storeAdminAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$previousUser = getenv('STORE_ADMIN_USER');
$previousHash = getenv('STORE_ADMIN_PASSWORD_HASH');
$connection = null;

try {
    putenv('STORE_ADMIN_USER=test-admin');
    putenv('STORE_ADMIN_PASSWORD_HASH=' . password_hash('correct-password', PASSWORD_DEFAULT));
    storeAdminAssert(loadStoreAdminConfig()['user'] === 'test-admin', 'No se cargó la configuración administrativa desde el entorno.');
    putenv('STORE_ADMIN_PASSWORD_HASH=invalid-hash');
    try {
        loadStoreAdminConfig('/missing-production-config.php');
        throw new RuntimeException('Se aceptó un hash administrativo inválido.');
    } catch (RuntimeException $exception) {
        storeAdminAssert($exception->getMessage() === 'La configuración de administración de la tienda no está disponible.', 'El error de configuración filtró detalles.');
    }
    putenv('STORE_ADMIN_PASSWORD_HASH=' . password_hash('correct-password', PASSWORD_DEFAULT));
    startStoreAdminSession();
    $beforeLoginId = session_id();
    storeAdminAssert(!attemptStoreAdminLogin('test-admin', 'incorrect-password'), 'Se aceptó una contraseña incorrecta.');
    storeAdminAssert(!storeAdminIsAuthenticated(), 'Un login incorrecto autenticó la sesión.');
    storeAdminAssert(attemptStoreAdminLogin('test-admin', 'correct-password'), 'Se rechazó el login correcto.');
    storeAdminAssert(storeAdminIsAuthenticated(), 'La sesión no quedó autenticada.');
    storeAdminAssert(STORE_ADMIN_HOME_PATH === 'index.php', 'El login correcto no tiene configurado el panel como destino.');
    storeAdminAssert(session_id() !== $beforeLoginId, 'No se regeneró session_id al autenticar.');

    $csrf = storeAdminCsrfToken();
    storeAdminAssert(storeAdminCsrfIsValid($csrf), 'El CSRF correcto fue rechazado.');
    storeAdminAssert(!storeAdminCsrfIsValid(str_repeat('0', 64)), 'Se aceptó un CSRF inválido.');
    storeAdminAssert(normalizeStorePhotoPrice('1234,5') === '1234.50', 'No se normalizó un precio válido.');
    foreach (['0', '0.00', '-1', '', 'texto', '1.234', '1e3'] as $invalidPrice) {
        storeAdminAssert(normalizeStorePhotoPrice($invalidPrice) === null, "Se aceptó el precio inválido: {$invalidPrice}");
    }
    storeAdminAssert(normalizeStorePhotoEditorialText('  Título lunar  ', 255) === 'Título lunar', 'No se recortó el texto editorial.');
    storeAdminAssert(normalizeStorePhotoEditorialText('   ', 255) === null, 'Un texto vacío no se convirtió en NULL.');

    $connection = getStoreDatabaseConnection();
    $photosBefore = (int) $connection->query('SELECT COUNT(*) FROM fotos')->fetchColumn();
    $ordersBefore = (int) $connection->query('SELECT COUNT(*) FROM pedido_fotos')->fetchColumn();
    $connection->beginTransaction();
    $insert = $connection->prepare('INSERT INTO fotos (foto_id, nombre_archivo, archivo_original, titulo, precio, disponible) VALUES (:foto_id, :nombre, :original, NULL, 100.00, 1)');
    $insert->execute(['foto_id' => str_repeat('a', 64), 'nombre' => 'admin-test.jpg', 'original' => 'admin-test.jpg']);
    $photoId = (int) $connection->lastInsertId();
    $connection->exec("UPDATE fotos SET camara = 'Cámara de prueba', metadatos_json = '{\"origen\":\"exif\"}' WHERE id = " . $photoId);
    $insert->execute(['foto_id' => str_repeat('b', 64), 'nombre' => 'admin-test-2.jpg', 'original' => 'admin-test-2.jpg']);
    $secondPhotoId = (int) $connection->lastInsertId();
    storeAdminAssert(setStorePhotoAvailability($connection, $photoId, false), 'No se pudo ocultar la foto.');
    storeAdminAssert((int) $connection->query('SELECT disponible FROM fotos WHERE id = ' . $photoId)->fetchColumn() === 0, 'La foto no quedó oculta.');
    storeAdminAssert(setStorePhotoAvailability($connection, $photoId, true), 'No se pudo publicar la foto.');
    storeAdminAssert((int) $connection->query('SELECT disponible FROM fotos WHERE id = ' . $photoId)->fetchColumn() === 1, 'La foto no volvió a publicarse.');
    storeAdminAssert(setStorePhotoPrice($connection, $photoId, '245.90'), 'No se actualizó el precio individual.');
    storeAdminAssert((string) $connection->query('SELECT precio FROM fotos WHERE id = ' . $photoId)->fetchColumn() === '245.90', 'El precio individual guardado es incorrecto.');
    foreach (['0.00', '-5.00', '', 'inválido'] as $invalidStoredPrice) {
        storeAdminAssert(!setStorePhotoPrice($connection, $photoId, $invalidStoredPrice), 'La actualización individual aceptó un precio inválido.');
    }
    storeAdminAssert((string) $connection->query('SELECT precio FROM fotos WHERE id = ' . $photoId)->fetchColumn() === '245.90', 'Un precio inválido modificó la foto.');
    storeAdminAssert(setStorePhotoPrices($connection, [$photoId, $secondPhotoId], '399.50') === 2, 'El lote no actualizó las dos fotos.');
    $batchPrices = $connection->query('SELECT precio FROM fotos WHERE id IN (' . $photoId . ', ' . $secondPhotoId . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    storeAdminAssert($batchPrices === ['399.50', '399.50'], 'El lote modificó precios incorrectamente.');
    try {
        setStorePhotoPrices($connection, [], '200.00');
        throw new RuntimeException('Se aceptó un lote sin selección.');
    } catch (InvalidArgumentException $exception) {
        storeAdminAssert(true, 'El lote vacío fue rechazado.');
    }
    storeAdminAssert(
        setStorePhotoEditorialMetadata($connection, $photoId, '  <Luna llena>  ', '  Descripción de prueba  ', ' luna, noche, telescopio '),
        'No se guardaron los tres campos editoriales.'
    );
    $editorial = $connection->query('SELECT titulo, descripcion, palabras_clave, camara, metadatos_json FROM fotos WHERE id = ' . $photoId)->fetch();
    storeAdminAssert($editorial['titulo'] === '<Luna llena>' && $editorial['descripcion'] === 'Descripción de prueba' && $editorial['palabras_clave'] === 'luna, noche, telescopio', 'Los campos editoriales no se normalizaron correctamente.');
    storeAdminAssert($editorial['camara'] === 'Cámara de prueba' && $editorial['metadatos_json'] === '{"origen":"exif"}', 'La edición alteró metadatos técnicos.');

    storeAdminAssert(setStorePhotoEditorialMetadata($connection, $photoId, null, 'Sólo descripción', null), 'No se guardó una edición parcial.');
    $partial = $connection->query('SELECT titulo, descripcion, palabras_clave FROM fotos WHERE id = ' . $photoId)->fetch();
    storeAdminAssert($partial['titulo'] === null && $partial['descripcion'] === 'Sólo descripción' && $partial['palabras_clave'] === null, 'La edición parcial guardó valores incorrectos.');

    storeAdminAssert(setStorePhotoEditorialMetadata($connection, $photoId, '', '  ', "\n"), 'No se pudieron vaciar los campos editoriales.');
    $empty = $connection->query('SELECT titulo, descripcion, palabras_clave FROM fotos WHERE id = ' . $photoId)->fetch();
    storeAdminAssert($empty['titulo'] === null && $empty['descripcion'] === null && $empty['palabras_clave'] === null, 'Los campos vacíos no persistieron como NULL.');

    foreach ([[str_repeat('á', 256), 255], [str_repeat('d', 5001), 5000], [str_repeat('k', 2001), 2000]] as [$tooLong, $limit]) {
        try {
            normalizeStorePhotoEditorialText($tooLong, $limit);
            throw new RuntimeException('Se aceptó un texto editorial excesivo.');
        } catch (InvalidArgumentException $exception) {
            storeAdminAssert(true, 'La longitud excesiva fue rechazada.');
        }
    }
    $presented = storeAdminPhotoPresentation(['id' => $photoId, 'titulo' => null, 'descripcion' => null, 'palabras_clave' => null, 'archivo_preview_tienda' => '../private.jpg', 'disponible' => 1]);
    storeAdminAssert($presented['title'] === 'Foto sin título' && $presented['preview_url'] === null, 'La presentación expuso una ruta insegura o un nombre técnico.');
    $connection->rollBack();
    storeAdminAssert((int) $connection->query('SELECT COUNT(*) FROM fotos')->fetchColumn() === $photosBefore, 'La prueba dejó cambios en fotos.');
    storeAdminAssert((int) $connection->query('SELECT COUNT(*) FROM pedido_fotos')->fetchColumn() === $ordersBefore, 'La prueba modificó pedido_fotos.');

    destroyStoreAdminSession();
    storeAdminAssert(session_status() !== PHP_SESSION_ACTIVE, 'Logout no destruyó la sesión.');
    fwrite(STDOUT, "store admin tests: ok\n");
} finally {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }
    is_string($previousUser) ? putenv('STORE_ADMIN_USER=' . $previousUser) : putenv('STORE_ADMIN_USER');
    is_string($previousHash) ? putenv('STORE_ADMIN_PASSWORD_HASH=' . $previousHash) : putenv('STORE_ADMIN_PASSWORD_HASH');
}
