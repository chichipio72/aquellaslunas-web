<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/web-database.php';
require_once dirname(__DIR__, 2) . '/includes/site-configuration.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function runPhotographySimulatedDirectionalTwilightMigration(PDO $connection): void
{
    $mappings = ['photography.simulated.twilight_horizon_antisolar' => 'photography.simulated.twilight_horizon'];
    foreach (['lit_color', 'lit_brightness', 'dark_brightness', 'dark_sky_mix', 'contrast', 'texture_visibility', 'lit_sky_mix', 'terminator_detail', 'limb_softness'] as $parameter) {
        $mappings['photography.simulated.moon_twilight_low_antisolar_' . $parameter] = 'photography.simulated.moon_twilight_low_' . $parameter;
    }
    $catalog = astronomySiteConfigCatalog();
    $statement = $connection->prepare(
        'INSERT INTO admin_configuracion_sitio (clave, valor, descripcion) '
        . 'SELECT :new_key, valor, :description FROM admin_configuracion_sitio WHERE clave = :old_key '
        . 'ON DUPLICATE KEY UPDATE clave = VALUES(clave)'
    );
    foreach ($mappings as $newKey => $oldKey) {
        $statement->execute([
            'new_key' => $newKey, 'old_key' => $oldKey,
            'description' => (string) ($catalog[$newKey]['description'] ?? ''),
        ]);
    }
    astronomySiteConfigResetCache();
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    runPhotographySimulatedDirectionalTwilightMigration(getWebDatabaseConnection());
    echo "Anclas direccionales del crepúsculo fotográfico disponibles.\n";
}
