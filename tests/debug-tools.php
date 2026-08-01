<?php

declare(strict_types=1);

function debugToolsAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$sessionDirectory = sys_get_temp_dir() . '/aquellas-lunas-debug-tools-' . bin2hex(random_bytes(6));
if (!mkdir($sessionDirectory, 0700) && !is_dir($sessionDirectory)) {
    throw new RuntimeException('No se pudo crear el directorio temporal de sesiones.');
}
session_save_path($sessionDirectory);
putenv('APP_ENV=production');
putenv('LOCAL_TIME_SIMULATION_ENABLED=false');
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once __DIR__ . '/../includes/current-datetime.php';

try {
    debugToolsAssert(!canUseSiteDebugTools(), 'Producción sin sesión admin habilitó debug.');
    debugToolsAssert(!astronomyLocalTimeSimulationEnabled(), 'Producción sin sesión admin habilitó simulación.');

    $adminSessionId = 'admin-test-' . bin2hex(random_bytes(8));
    file_put_contents(
        $sessionDirectory . '/sess_' . $adminSessionId,
        STORE_ADMIN_SESSION_KEY . '|b:1;'
    );
    $_COOKIE['aquellas_lunas_admin'] = $adminSessionId;

    debugToolsAssert(canUseSiteDebugTools(), 'Producción con sesión admin no habilitó debug.');
    debugToolsAssert(
        astronomyLocalTimeSimulationEnabled(),
        'LOCAL_TIME_SIMULATION_ENABLED=false impidió la simulación del admin en producción.'
    );

    $_COOKIE[ASTRONOMY_SIMULATED_TIME_COOKIE] = '2040-01-02 03:04';
    debugToolsAssert(astronomySimulatedLocalWallTime() === null, 'Se confió en una cookie de hora inventada por el cliente.');

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $firstVisitorSessionId = 'local-test-' . bin2hex(random_bytes(8));
    $_COOKIE['aquellas_lunas_local'] = $firstVisitorSessionId;
    session_name('aquellas_lunas_local');
    session_id($firstVisitorSessionId);
    debugToolsAssert(astronomyTimeSimulationSession(), 'No se inició la sesión separada de simulación.');
    $simulationToken = astronomyTimeSimulationCsrfToken();
    debugToolsAssert(astronomyTimeSimulationCsrfIsValid($simulationToken), 'El CSRF válido del simulador fue rechazado.');
    debugToolsAssert(!astronomyTimeSimulationCsrfIsValid(str_repeat('0', 64)), 'El simulador aceptó un CSRF inventado.');
    $_SESSION[ASTRONOMY_SIMULATED_TIME_SESSION_KEY] = '2040-01-02 03:04';
    session_write_close();

    unset($_COOKIE['aquellas_lunas_local']);
    session_id('');
    debugToolsAssert(astronomySimulatedLocalWallTime() === null, 'La simulación de un usuario afectó a otro visitante.');
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $_COOKIE['aquellas_lunas_local'] = $firstVisitorSessionId;
    session_id($firstVisitorSessionId);
    debugToolsAssert(
        astronomySimulatedLocalWallTime() === '2040-01-02 03:04',
        'El usuario que configuró la simulación no recuperó su valor de sesión.'
    );
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $GLOBALS['astronomy_api_timings'] = [[
        'label' => 'Prueba', 'http_code' => 200, 'total_ms' => 1.0, 'api_ms' => 0.5,
        'server_timing' => null, 'attempts' => 1, 'retried' => false,
        'curl_error' => false, 'curl_errno' => 0, 'json_valid' => true, 'outcome' => 'success',
    ]];
    ob_start();
    renderAstronomyTimings();
    $adminTimings = (string) ob_get_clean();
    debugToolsAssert(str_contains($adminTimings, 'api-diagnostics'), 'El admin no recibió los tiempos de API.');

    unset($_COOKIE['aquellas_lunas_admin']);
    ob_start();
    renderAstronomyTimings();
    $visitorTimings = (string) ob_get_clean();
    debugToolsAssert($visitorTimings === '', 'Un visitante normal recibió HTML de tiempos de API.');

    putenv('APP_ENV=local');
    debugToolsAssert(canUseSiteDebugTools(), 'Local sin sesión admin no habilitó debug.');
    debugToolsAssert(astronomyTimingsEnabled(), 'Local sin sesión admin no habilitó tiempos de API.');
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    foreach (glob($sessionDirectory . '/sess_*') ?: [] as $sessionFile) {
        unlink($sessionFile);
    }
    rmdir($sessionDirectory);
    putenv('APP_ENV');
    putenv('LOCAL_TIME_SIMULATION_ENABLED');
}

echo "debug-tools: ok\n";
