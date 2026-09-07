<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/web-database.php';
require_once dirname(__DIR__, 2) . '/includes/photography-editorial.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function runPhotographyEditorialMigration(PDO $connection): void { photographyEditorialInitialize($connection); }
if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) { runPhotographyEditorialMigration(getWebDatabaseConnection()); echo "Modelo editorial de Fotografía disponible.\n"; }
