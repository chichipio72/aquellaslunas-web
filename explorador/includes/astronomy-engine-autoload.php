<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'AstronomyEngine\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = dirname(__DIR__, 2) . '/astronomy-engine/src/'
        . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
