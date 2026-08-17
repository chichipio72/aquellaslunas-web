<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/web-push-astronomy.php';
require_once __DIR__ . '/../../includes/scheduled-tasks.php';

sendStoreAdminHeaders();
startStoreAdminSession();
if (!storeAdminIsAuthenticated()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['error' => 'Autenticación administrativa requerida.']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

try {
    $connection = getWebDatabaseConnection();
    $subscriptionId = filter_var($_POST['subscription_id'] ?? $_GET['subscription_id'] ?? null,
        FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($subscriptionId === false || $subscriptionId === null) {
        throw new InvalidArgumentException('La suscripción elegida no es válida.');
    }
    $subscriptionId = (int) $subscriptionId;
    $actionResult = null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
            throw new InvalidArgumentException('La sesión expiró o el token CSRF no es válido.');
        }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'schedule_test') {
            $delay = filter_var($_POST['delay_minutes'] ?? null, FILTER_VALIDATE_INT);
            if ($delay === false) throw new InvalidArgumentException('Elegí cuándo enviar la prueba.');
            $scheduled = astronomyPushScheduleTest($connection, $subscriptionId, (int) $delay,
                isset($_POST['respect_quiet_hours']));
            $actionResult = ['message' => 'Prueba ' . $scheduled['id'] . ' programada.'];
        } elseif ($action === 'cancel_test') {
            $testId = filter_var($_POST['test_id'] ?? null, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]);
            if ($testId === false || !astronomyPushCancelScheduledTest($connection, $subscriptionId, (int) $testId)) {
                throw new InvalidArgumentException('La prueba ya no está pendiente o no pertenece al dispositivo.');
            }
            $actionResult = ['message' => 'Prueba pendiente cancelada.'];
        } elseif ($action === 'send_manual_test') {
            $title = astronomyWebPushNormalizeMessage($_POST['title'] ?? '', 120, 'El título');
            $body = astronomyWebPushNormalizeMessage($_POST['body'] ?? '', 500, 'El mensaje');
            $targetUrl = astronomyWebPushNormalizeTargetUrl($_POST['target_url'] ?? '');
            $destination = (string) ($_POST['destination'] ?? $subscriptionId);
            $sendSubscriptionId = null;
            if ($destination !== 'all') {
                $validatedDestination = filter_var($destination, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($validatedDestination === false) throw new InvalidArgumentException('El destino elegido no es válido.');
                $sendSubscriptionId = (int) $validatedDestination;
            }
            $send = astronomyWebPushSend($connection, loadWebPushServerConfig(), $sendSubscriptionId, $title, $body, $targetUrl);
            $actionResult = ['message' => 'Envío finalizado: ' . (int) $send['success'] . ' exitoso(s), '
                . (int) $send['failed'] . ' fallido(s).'];
        } else {
            throw new InvalidArgumentException('La acción solicitada no es válida.');
        }
    }
    $registry = require __DIR__ . '/../../scripts/migrations/registry.php';
    $state = scheduledTaskAdminState($connection, is_array($registry) ? $registry : []);
    $orchestrator = is_array($state['orchestrator'] ?? null) ? $state['orchestrator'] : [];
    $tests = astronomyPushScheduledTests($connection, $subscriptionId);
    foreach ($tests as &$test) {
        $test['error_message'] = !empty($test['error_message'])
            ? astronomyWebPushSanitizeError((string) $test['error_message']) : null;
    }
    unset($test);
    $typeLabels = [];
    foreach (astronomyPushNotificationTypes($connection) as $notificationType) {
        $typeLabels[(string) $notificationType['notification_type']] = (string) $notificationType['display_name'];
    }
    $logs = astronomyPushRecentLog($connection, $subscriptionId, 50);
    foreach ($logs as &$log) $log['display_name'] = $typeLabels[(string) $log['notification_type']] ?? $log['notification_type'];
    unset($log);
    echo json_encode([
        'updated_at' => gmdate('c'),
        'action_result' => $actionResult,
        'scheduler' => [
            'last_started_at' => $orchestrator['last_started_at'] ?? null,
            'last_finished_at' => $orchestrator['last_finished_at'] ?? null,
            'last_success_at' => $orchestrator['last_success_at'] ?? null,
            'status' => $orchestrator['last_status'] ?? null,
            'last_error' => !empty($orchestrator['last_error'])
                ? astronomyWebPushSanitizeError((string) $orchestrator['last_error']) : null,
        ],
        'tests' => $tests,
        'logs' => $logs,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log('Admin notifications status failed [type=' . get_debug_type($exception) . '].');
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo actualizar el estado de las notificaciones.']);
}
