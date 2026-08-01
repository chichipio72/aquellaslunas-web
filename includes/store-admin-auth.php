<?php

require_once __DIR__ . '/api-config.php';

const STORE_ADMIN_SESSION_KEY = 'store_admin_authenticated';
const STORE_ADMIN_CSRF_KEY = 'store_admin_csrf_token';
const STORE_ADMIN_HOME_PATH = 'index.php';

function sendStoreAdminHeaders(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

function startStoreAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('aquellas_lunas_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) {
        throw new RuntimeException('No se pudo iniciar la sesión administrativa.');
    }
}

function storeAdminIsAuthenticated(): bool
{
    return ($_SESSION[STORE_ADMIN_SESSION_KEY] ?? false) === true;
}

function storeAdminSessionStoragePath(): string
{
    $configured = (string) session_save_path();
    if (str_contains($configured, ';')) {
        $parts = explode(';', $configured);
        $configured = (string) end($parts);
    }
    $configured = trim($configured);
    if ($configured === '') {
        $configured = sys_get_temp_dir();
    }
    return $configured;
}

function storeAdminSessionDataFromId(string $sessionId): ?string
{
    if (preg_match('/^[A-Za-z0-9,-]{8,128}$/', $sessionId) !== 1) {
        return null;
    }
    $path = rtrim(storeAdminSessionStoragePath(), '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
    if (!is_readable($path) || !is_file($path)) {
        return null;
    }
    $data = @file_get_contents($path);
    return is_string($data) ? $data : null;
}

function storeAdminExtractAuthenticatedFromSessionData(string $data): bool
{
    $handler = (string) ini_get('session.serialize_handler');
    if ($handler === 'php_serialize') {
        $decoded = @unserialize($data, ['allowed_classes' => false]);
        return is_array($decoded) && (($decoded[STORE_ADMIN_SESSION_KEY] ?? false) === true);
    }

    if ($handler === 'php') {
        if (str_contains($data, STORE_ADMIN_SESSION_KEY . '|b:1;')) {
            return true;
        }
        if (str_contains($data, STORE_ADMIN_SESSION_KEY . '|i:1;')) {
            return true;
        }
        return false;
    }

    return str_contains($data, STORE_ADMIN_SESSION_KEY);
}

function storeAdminHasValidSessionCookie(): bool
{
    if (session_status() === PHP_SESSION_ACTIVE && session_name() === 'aquellas_lunas_admin') {
        return storeAdminIsAuthenticated();
    }

    $sessionId = (string) ($_COOKIE['aquellas_lunas_admin'] ?? '');
    if ($sessionId === '') {
        return false;
    }

    $data = storeAdminSessionDataFromId($sessionId);
    if (!is_string($data) || $data === '') {
        return false;
    }

    return storeAdminExtractAuthenticatedFromSessionData($data);
}

function attemptStoreAdminLogin(string $user, string $password, ?string $productionConfigPath = null): bool
{
    $config = loadStoreAdminConfig($productionConfigPath);
    $valid = hash_equals($config['user'], trim($user)) && password_verify($password, $config['password_hash']);
    if (!$valid) {
        return false;
    }
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('No se pudo proteger la sesión administrativa.');
    }
    $_SESSION[STORE_ADMIN_SESSION_KEY] = true;
    unset($_SESSION[STORE_ADMIN_CSRF_KEY]);
    return true;
}

function requireStoreAdminAuthentication(): void
{
    if (storeAdminIsAuthenticated()) {
        return;
    }
    $loginPath = defined('STORE_ADMIN_LOGIN_PATH') && is_string(STORE_ADMIN_LOGIN_PATH) && STORE_ADMIN_LOGIN_PATH !== ''
        ? STORE_ADMIN_LOGIN_PATH
        : 'login.php';
    header('Location: ' . $loginPath, true, 303);
    exit;
}

function storeAdminCsrfToken(): string
{
    $token = $_SESSION[STORE_ADMIN_CSRF_KEY] ?? null;
    if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        $_SESSION[STORE_ADMIN_CSRF_KEY] = $token;
    }
    return $token;
}

function storeAdminCsrfIsValid(mixed $token): bool
{
    $stored = $_SESSION[STORE_ADMIN_CSRF_KEY] ?? null;
    return is_string($token) && is_string($stored) && $stored !== '' && hash_equals($stored, $token);
}

function destroyStoreAdminSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}
