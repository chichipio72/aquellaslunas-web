<?php

declare(strict_types=1);

function astronomyTraceCleanup(PDO $connection, int $days): int
{
    if ($days < 1 || $days > 3650) {
        throw new InvalidArgumentException('La retención debe estar entre 1 y 3650 días.');
    }
    return (int) $connection->exec(
        'DELETE FROM astronomy_request_log WHERE created_at < UTC_TIMESTAMP(6) - INTERVAL ' . $days . ' DAY'
    );
}
