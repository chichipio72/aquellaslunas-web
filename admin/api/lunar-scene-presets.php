<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/store-admin-auth.php';

sendStoreAdminHeaders();
startStoreAdminSession();
header('Content-Type: application/json; charset=utf-8');
if (!storeAdminIsAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Autenticación administrativa requerida.']);
    exit;
}
require_once __DIR__ . '/../../includes/web-database.php';
require_once __DIR__ . '/../../includes/lunar-embeds.php';
require_once __DIR__ . '/../../includes/lunar-scene-presets.php';

try {
    $connection = getWebDatabaseConnection();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        echo json_encode(['presets' => lunarScenePresets($connection)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: GET, POST');
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
        exit;
    }
    if (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('La sesión expiró o el token CSRF no es válido.');
    $action = (string) ($_POST['action'] ?? '');
    $configuration = json_decode((string) ($_POST['configuration'] ?? ''), true);
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($action === 'create') {
        if (!is_array($configuration)) throw new InvalidArgumentException('La configuración del preset no es válida.');
        $preset = lunarScenePresetCreate($connection, $_POST['name'] ?? '', $_POST['description'] ?? '', $configuration);
    } elseif ($action === 'update') {
        if ($id === false || $id === null || !is_array($configuration)) throw new InvalidArgumentException('El preset o su configuración no son válidos.');
        $preset = lunarScenePresetUpdate($connection, (int) $id, $_POST['name'] ?? '', $_POST['description'] ?? '', $configuration);
    } elseif ($action === 'duplicate') {
        if ($id === false || $id === null) throw new InvalidArgumentException('Elegí un preset para duplicar.');
        $source = lunarScenePreset($connection, (int) $id);
        if ($source === null) throw new InvalidArgumentException('El preset elegido no existe.');
        $preset = lunarScenePresetCreate($connection, substr(trim((string) $source['name']), 0, 92) . ' (copia)', $source['description'], $source['configuration']);
    } elseif ($action === 'delete') {
        if ($id === false || $id === null) throw new InvalidArgumentException('Elegí un preset para eliminar.');
        lunarScenePresetDelete($connection, (int) $id);
        $preset = null;
    } else {
        throw new InvalidArgumentException('La acción solicitada no es válida.');
    }
    echo json_encode(['preset' => $preset, 'presets' => lunarScenePresets($connection)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log('Lunar scene presets admin failed [type=' . get_debug_type($exception) . '].');
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron gestionar los presets de escenas lunares.']);
}
