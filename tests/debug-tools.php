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
    $GLOBALS['home_page_profile'] = [
        'blocks' => ['Bloque desplegable' => 600.0, 'Hoja' => 25.0],
        'details' => ['Bloque desplegable' => ['Detalle uno' => 400.0, 'Detalle dos' => 200.0]],
        'total_ms' => 625.0,
    ];
    $GLOBALS['home_satellite_diagnostic'] = [
        'status' => 'ejecutado correctamente', 'total_ms' => 630.2,
        'tle_resolution_ms' => 0.2, 'calculation_ms' => 629.9,
        'tle_sources' => ['iss' => [
            'cache_status' => 'cache_hit', 'downloaded_at_utc' => '2026-08-13T10:00:00.000000+00:00',
            'epoch_utc' => '2026-08-13T00:00:00.000000+00:00', 'age_at_start_hours' => 12.0,
            'age_at_end_hours' => 60.0, 'visual_status' => 'warning', 'visual_label' => 'advertencia',
        ]],
    ];
    ob_start();
    renderAstronomyTimings();
    $adminTimings = (string) ob_get_clean();
    debugToolsAssert(str_contains($adminTimings, 'api-diagnostics'), 'El admin no recibió los tiempos de API.');
    debugToolsAssert(str_contains($adminTimings, '<h2 id="api-diagnostics-title">Diagnóstico</h2>'), 'Falta el título visible del diagnóstico.');
    debugToolsAssert(str_contains($adminTimings, 'Tiempo total de generación de la página:'), 'Falta el tiempo total visible.');
    debugToolsAssert(str_contains($adminTimings, 'data-satellite-diagnostic')
        && str_contains($adminTimings, 'Satélites: ejecutado correctamente')
        && str_contains($adminTimings, 'Resolución TLE 0,2 ms')
        && str_contains($adminTimings, 'Cálculo 629,9 ms'), 'Falta el diagnóstico satelital visible o sus subtareas.');
    debugToolsAssert(str_contains($adminTimings, 'TLE ISS')
        && str_contains($adminTimings, 'cache_hit · advertencia')
        && str_contains($adminTimings, 'Edad al inicio 12,0 h · al final 60,0 h')
        && str_contains($adminTimings, 'data-tle-status="warning"'),
        'Falta la metadata o el estado visual del TLE en el diagnóstico administrativo.');
    debugToolsAssert(str_contains($adminTimings, '<details class="api-diagnostics__page-profile">'), 'El perfil no quedó contraído inicialmente.');
    debugToolsAssert(!str_contains($adminTimings, '<details class="api-diagnostics__page-profile" open'), 'El perfil se abrió inicialmente.');
    debugToolsAssert(substr_count($adminTimings, 'api-diagnostics__page-profile-group') === 1, 'La jerarquía no distingue el único grupo desplegable.');
    debugToolsAssert(str_contains($adminTimings, 'Hoja</span><span>25,0 ms'), 'La hoja perdió su texto, orden o tiempo.');
    debugToolsAssert(!str_contains($adminTimings, '<summary><span>Hoja'), 'Una hoja recibió un expansor.');
    debugToolsAssert(str_contains($adminTimings, 'api-diagnostics__bar'), 'Las barras quedaron fuera del diagnóstico visible.');
    debugToolsAssert(
        strrpos($adminTimings, '</details>') < strpos($adminTimings, '<ul>'),
        'Las barras quedaron dentro del Perfil de página contraído.'
    );

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
