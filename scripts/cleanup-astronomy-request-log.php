<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/web-database.php';
require_once dirname(__DIR__) . '/includes/astronomy-trace-maintenance.php';

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) try {
    $options = getopt('', ['days:', 'help']);
    if (isset($options['help'])) {
        echo "Uso: php scripts/cleanup-astronomy-request-log.php [--days=30]\n";
        exit(0);
    }
    $configured = $options['days'] ?? (getenv('ASTRONOMY_TRACE_RETENTION_DAYS') ?: '30');
    if (!is_string($configured) || preg_match('/^\d+$/', $configured) !== 1) {
        throw new InvalidArgumentException('La retención debe ser una cantidad entera de días.');
    }
    $days = (int) $configured;
    $deleted = astronomyTraceCleanup(getWebDatabaseConnection(), $days);
    echo 'Registros eliminados: ' . (int) $deleted . '. Retención: ' . $days . " días.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'No se pudo limpiar la trazabilidad: ' . $exception->getMessage() . "\n");
    exit(1);
}
