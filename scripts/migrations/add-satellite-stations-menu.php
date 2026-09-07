<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function runSatelliteStationsMenuMigration(PDO $connection): void
{
    $statement = $connection->prepare("INSERT INTO site_menu_sections (section_id,group_id,sort_order,public_visible,admin_visible) VALUES ('satellite_stations','explore',35,1,1) ON DUPLICATE KEY UPDATE section_id=section_id");
    $statement->execute();
}
