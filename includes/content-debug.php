<?php

require_once __DIR__ . '/api-config.php';

const ASTRONOMY_CONTENT_DEBUG_SESSION_KEY = 'astronomy_content_debug';
const ASTRONOMY_CONTENT_DEBUG_CSRF_KEY = 'astronomy_content_debug_csrf';

function astronomyContentDebugAvailable(): bool
{
    return isLocalEnvironment() && isContentEnabled();
}

function astronomyContentDebugSession(): bool
{
    if (!astronomyContentDebugAvailable()) {
        return false;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    session_name('aquellas_lunas_local');
    $savePath = trim((string) session_save_path());
    if ($savePath === '' || !is_dir($savePath) || !is_writable($savePath)) {
        session_save_path(sys_get_temp_dir());
    }
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => false,
        'path' => '/',
    ]);
    return session_start();
}

function astronomyContentDebugEnabled(): bool
{
    if (!astronomyContentDebugSession()) {
        return false;
    }
    return ($_SESSION[ASTRONOMY_CONTENT_DEBUG_SESSION_KEY] ?? false) === true;
}

function astronomyContentDebugCsrfToken(): string
{
    if (!astronomyContentDebugSession()) {
        return '';
    }
    $token = $_SESSION[ASTRONOMY_CONTENT_DEBUG_CSRF_KEY] ?? null;
    if (!is_string($token) || strlen($token) !== 64) {
        $token = bin2hex(random_bytes(32));
        $_SESSION[ASTRONOMY_CONTENT_DEBUG_CSRF_KEY] = $token;
    }
    return $token;
}

function astronomyContentDebugRedirect(): never
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);
    $query = [];
    if (is_string(parse_url($uri, PHP_URL_QUERY))) {
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
    }
    unset($query['content_debug']);
    $safePath = is_string($path) && str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path : '/';
    $target = $safePath
        . ($query !== [] ? '?' . http_build_query($query) : '');
    header('Location: ' . $target, true, 303);
    exit;
}

function astronomyBootstrapContentDebug(): void
{
    if (!astronomyContentDebugAvailable() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    if (!isset($_POST['content_debug_toggle']) || !astronomyContentDebugSession()) {
        return;
    }
    $token = (string) ($_POST['content_debug_token'] ?? '');
    $expected = astronomyContentDebugCsrfToken();
    if ($expected !== '' && hash_equals($expected, $token)) {
        $_SESSION[ASTRONOMY_CONTENT_DEBUG_SESSION_KEY] = !astronomyContentDebugEnabled();
    }
    astronomyContentDebugRedirect();
}

astronomyBootstrapContentDebug();

function renderAstronomyContentDebugControl(): void
{
    if (!astronomyContentDebugAvailable()) {
        return;
    }
    $enabled = astronomyContentDebugEnabled();
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($requestUri, PHP_URL_PATH);
    $query = parse_url($requestUri, PHP_URL_QUERY);
    $safePath = is_string($path) && str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path : '/';
    $action = $safePath . (is_string($query) && $query !== '' ? '?' . $query : '');
    ?>
    <form class="header-content-debug" method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="content_debug_token" value="<?= htmlspecialchars(astronomyContentDebugCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" name="content_debug_toggle" value="1"><?= $enabled ? 'Ocultar errores de contenido' : 'Ver errores de contenido' ?></button>
    </form>
    <?php
}
