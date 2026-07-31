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
    header('Location: login.php', true, 303);
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
