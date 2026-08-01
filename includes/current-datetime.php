<?php

require_once __DIR__ . '/api-client.php';

const ASTRONOMY_SIMULATED_TIME_SESSION_KEY = 'astronomy_simulated_local_time';
const ASTRONOMY_SIMULATED_TIME_COOKIE = 'astronomy_simulated_local_time';
const ASTRONOMY_SIMULATED_TIME_CSRF_KEY = 'astronomy_simulated_time_csrf';

function astronomyLocalTimeSimulationEnabled(): bool
{
    if (!canUseSiteDebugTools()) {
        return false;
    }
    // En producción la sesión admin es el interruptor seguro. La bandera se
    // conserva como interruptor técnico del entorno de desarrollo local.
    if (isProductionEnvironment()) {
        return true;
    }
    $environmentValue = getenv('LOCAL_TIME_SIMULATION_ENABLED');
    if (is_string($environmentValue) && trim($environmentValue) !== '') {
        return filter_var($environmentValue, FILTER_VALIDATE_BOOLEAN) === true;
    }
    $productionConfig = loadAstronomyProductionConfig();
    return filter_var(
        $productionConfig['local_time_simulation_enabled'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    ) === true;
}

function astronomyTimeSimulationSession(): bool
{
    if (!astronomyLocalTimeSimulationEnabled()) {
        return false;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        return session_name() === 'aquellas_lunas_local';
    }
    session_name('aquellas_lunas_local');
    $configuredSavePath = trim((string) session_save_path());
    if ($configuredSavePath === '' || !is_dir($configuredSavePath) || !is_writable($configuredSavePath)) {
        session_save_path(sys_get_temp_dir());
    }
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
        'path' => '/',
    ]);
    return session_start();
}

function astronomyTimeSimulationCsrfToken(): string
{
    if (!astronomyTimeSimulationSession()) {
        return '';
    }
    $token = $_SESSION[ASTRONOMY_SIMULATED_TIME_CSRF_KEY] ?? null;
    if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        $_SESSION[ASTRONOMY_SIMULATED_TIME_CSRF_KEY] = $token;
    }
    return $token;
}

function astronomyTimeSimulationCsrfIsValid(mixed $token): bool
{
    $stored = $_SESSION[ASTRONOMY_SIMULATED_TIME_CSRF_KEY] ?? null;
    return is_string($token) && is_string($stored) && $stored !== '' && hash_equals($stored, $token);
}

function astronomyValidLocalWallTime(string $date, string $time): ?string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
        return null;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    if ($parsed === false || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
        return null;
    }
    return $parsed->format('Y-m-d H:i') === $date . ' ' . $time ? $date . ' ' . $time : null;
}

function astronomyTimeSimulationRedirect(): never
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($requestUri, PHP_URL_PATH);
    $query = $_GET;
    unset($query['debug_now']);
    $target = (is_string($path) && $path !== '' ? $path : '/')
        . ($query !== [] ? '?' . http_build_query($query) : '');
    header('Location: ' . $target, true, 303);
    exit;
}

function astronomyRemoveLegacySimulatedTimeCookie(): void
{
    if (!isset($_COOKIE[ASTRONOMY_SIMULATED_TIME_COOKIE])) {
        return;
    }
    setcookie(ASTRONOMY_SIMULATED_TIME_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[ASTRONOMY_SIMULATED_TIME_COOKIE]);
}

function astronomyBootstrapTimeSimulation(): void
{
    if (!astronomyLocalTimeSimulationEnabled()) {
        return;
    }
    $sessionStarted = astronomyTimeSimulationSession();
    astronomyRemoveLegacySimulatedTimeCookie();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    if (!$sessionStarted || !astronomyTimeSimulationCsrfIsValid($_POST['site_time_token'] ?? null)) {
        return;
    }
    if (($_POST['site_time_reset'] ?? '') === '1') {
        if ($sessionStarted) {
            unset($_SESSION[ASTRONOMY_SIMULATED_TIME_SESSION_KEY]);
            session_write_close();
        }
        astronomyTimeSimulationRedirect();
    }
    if (isset($_POST['site_time_date'], $_POST['site_time_clock'])) {
        $value = astronomyValidLocalWallTime(trim((string) ($_POST['site_time_date'] ?? '')), trim((string) ($_POST['site_time_clock'] ?? '')));
        $shiftDays = filter_var($_POST['site_time_shift'] ?? null, FILTER_VALIDATE_INT);
        if ($value !== null && in_array($shiftDays, [-7, -1, 1, 7], true)) {
            $value = (new DateTimeImmutable($value, new DateTimeZone('UTC')))
                ->modify(($shiftDays > 0 ? '+' : '') . $shiftDays . ' days')
                ->format('Y-m-d H:i');
        }
        if ($value !== null) {
            if ($sessionStarted) {
                $_SESSION[ASTRONOMY_SIMULATED_TIME_SESSION_KEY] = $value;
            }
        }
        if ($sessionStarted) {
            session_write_close();
        }
        astronomyTimeSimulationRedirect();
    }
}

astronomyBootstrapTimeSimulation();

function astronomySimulatedLocalWallTime(): ?string
{
    if (!astronomyLocalTimeSimulationEnabled()) {
        return null;
    }
    if (!astronomyTimeSimulationSession()) {
        return null;
    }
    $value = $_SESSION[ASTRONOMY_SIMULATED_TIME_SESSION_KEY] ?? null;
    return is_string($value) && astronomyValidLocalWallTime(substr($value, 0, 10), substr($value, 11, 5)) === $value
        ? $value
        : null;
}

function astronomyDebugClockValue(): ?string
{
    $localValue = astronomySimulatedLocalWallTime();
    if ($localValue !== null) {
        return $localValue;
    }
    if (!astronomyLocalTimeSimulationEnabled()) {
        return null;
    }
    $value = trim((string) ($_GET['debug_now'] ?? ''));
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
        return null;
    }
    try {
        return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
    } catch (Exception) {
        return null;
    }
}

function get_current_datetime(string $timezoneName): DateTimeImmutable
{
    $timezone = new DateTimeZone($timezoneName);
    $localValue = astronomySimulatedLocalWallTime();
    if ($localValue !== null) {
        return new DateTimeImmutable($localValue, $timezone);
    }
    $legacyValue = astronomyDebugClockValue();
    return $legacyValue !== null
        ? (new DateTimeImmutable($legacyValue))->setTimezone($timezone)
        : new DateTimeImmutable('now', $timezone);
}

function astronomyCurrentDateTimeIsSimulated(): bool
{
    return astronomyDebugClockValue() !== null;
}

function astronomyInternalUrl(string $url): string
{
    $debugValue = astronomySimulatedLocalWallTime() === null ? astronomyDebugClockValue() : null;
    if ($debugValue === null) {
        return $url;
    }
    $parts = parse_url($url);
    if ($parts === false) {
        return $url;
    }
    $query = [];
    if (is_string($parts['query'] ?? null)) {
        parse_str($parts['query'], $query);
    }
    $query['debug_now'] = $debugValue;
    $queryString = http_build_query($query);
    return (string) ($parts['path'] ?? '') . ($queryString !== '' ? '?' . $queryString : '')
        . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

function astronomyDebugClockUrl(?DateTimeImmutable $dateTime): string
{
    return astronomyInternalUrl((string) ($_SERVER['REQUEST_URI'] ?? '/'));
}

function renderAstronomyDebugClock(DateTimeImmutable $currentDateTime): void
{
    // El control persistente vive en el encabezado compartido.
}

function renderAstronomyDebugClockInput(): void
{
    // La sesión local evita propagar parámetros por formularios.
}
