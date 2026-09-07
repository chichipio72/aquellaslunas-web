<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/api-config.php';
require_once dirname(__DIR__, 2) . '/includes/site-configuration.php';
require_once dirname(__DIR__, 2) . '/includes/site-menu.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function runSiteMenuConfigurationMigration(PDO $connection): void
{
    $connection->exec('CREATE TABLE IF NOT EXISTS site_menu_groups (id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,label VARCHAR(120) NOT NULL,sort_order INT NOT NULL DEFAULT 0,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY idx_site_menu_groups_order (sort_order,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $connection->exec('CREATE TABLE IF NOT EXISTS site_menu_sections (section_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,group_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,sort_order INT NOT NULL DEFAULT 0,public_visible TINYINT(1) NOT NULL DEFAULT 1,admin_visible TINYINT(1) NOT NULL DEFAULT 1,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY idx_site_menu_sections_group_order (group_id,sort_order,section_id),CONSTRAINT fk_site_menu_sections_group FOREIGN KEY (group_id) REFERENCES site_menu_groups(id) ON UPDATE CASCADE ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $insertGroup = $connection->prepare('INSERT INTO site_menu_groups (id,label,sort_order) VALUES (:id,:label,:sort_order) ON DUPLICATE KEY UPDATE id=id');
    foreach (astronomySiteMenuDefaultGroups() as $group) $insertGroup->execute($group);
    $insertSection = $connection->prepare('INSERT INTO site_menu_sections (section_id,group_id,sort_order,public_visible,admin_visible) VALUES (:section_id,:group_id,:sort_order,:public_visible,:admin_visible) ON DUPLICATE KEY UPDATE section_id=section_id');
    foreach (astronomySiteMenuDefaultAssignments() as $sectionId => $row) $insertSection->execute(['section_id' => $sectionId] + $row);
    astronomySiteMenuResetCache();
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    runSiteMenuConfigurationMigration(getWebDatabaseConnection());
    echo "Configuración del menú principal disponible.\n";
}
