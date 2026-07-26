<?php

const STORE_CHECKOUT_CSRF_KEY = 'store_checkout_csrf_token';

function startStoreCheckoutSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('aquellas_lunas_store');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) {
        throw new RuntimeException('No se pudo iniciar la sesión de compra.');
    }
}

function storeCheckoutCsrfToken(): string
{
    $token = $_SESSION[STORE_CHECKOUT_CSRF_KEY] ?? null;
    if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        $_SESSION[STORE_CHECKOUT_CSRF_KEY] = $token;
    }
    return $token;
}

function storeCheckoutCsrfIsValid(mixed $token): bool
{
    $stored = $_SESSION[STORE_CHECKOUT_CSRF_KEY] ?? null;
    return is_string($token) && is_string($stored) && $stored !== '' && hash_equals($stored, $token);
}
