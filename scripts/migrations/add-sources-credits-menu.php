<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/api-config.php';
require_once dirname(__DIR__, 2) . '/includes/site-menu.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function runSourcesCreditsMenuMigration(PDO $connection): void
{
    $statement = $connection->prepare("INSERT INTO site_menu_sections (section_id,group_id,sort_order,public_visible,admin_visible) VALUES ('sources_credits','about',15,1,1) ON DUPLICATE KEY UPDATE section_id=section_id");
    $statement->execute();
    astronomySiteMenuResetCache();
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    runSourcesCreditsMenuMigration(getWebDatabaseConnection());
    echo "Fuentes y créditos incorporado al menú principal.\n";
}
