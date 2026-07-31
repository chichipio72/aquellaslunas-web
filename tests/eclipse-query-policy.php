<?php

require_once __DIR__ . '/../includes/eclipse-query-policy.php';

function eclipseQueryPolicyAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$timezone = new DateTimeZone('America/Argentina/Buenos_Aires');
$start = new DateTimeImmutable('2026-07-28', $timezone);
eclipseQueryPolicyAssert(eclipsesRangeIsAllowed($start, $start->modify('+4 years 364 days')), 'Se rechazó un rango menor a cinco años.');
eclipseQueryPolicyAssert(eclipsesRangeIsAllowed($start, $start->modify('+5 years')), 'Se rechazó el límite exacto de cinco años.');
eclipseQueryPolicyAssert(!eclipsesRangeIsAllowed($start, $start->modify('+5 years 1 day')), 'Se aceptó un rango mayor a cinco años.');
eclipseQueryPolicyAssert(!eclipsesRangeIsAllowed($start, $start->modify('-1 day')), 'Se aceptó un rango invertido.');

echo "Política de consultas de eclipses: OK\n";
