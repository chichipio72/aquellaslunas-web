<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/api-config.php';
require_once dirname(__DIR__, 2) . '/includes/site-configuration.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function runEclipseWidgetTemplateStorageMigration(PDO $connection): void
{
    $connection->exec('ALTER TABLE admin_configuracion_sitio MODIFY COLUMN valor TEXT NOT NULL');

    $catalog = astronomySiteConfigCatalog();
    $select = $connection->prepare('SELECT valor FROM admin_configuracion_sitio WHERE clave = :clave LIMIT 1');
    $update = $connection->prepare('UPDATE admin_configuracion_sitio SET valor = :valor WHERE clave = :clave');
    foreach (['eclipse.widget.solar_url', 'eclipse.widget.lunar_url'] as $key) {
        $select->execute([':clave' => $key]);
        $stored = $select->fetchColumn();
        // VARCHAR(255) cortó silenciosamente las URLs iniciales. Sólo esas filas
        // inequívocamente truncadas se recuperan desde el catálogo central.
        if (is_string($stored) && strlen($stored) === 255) {
            $update->execute([':clave' => $key, ':valor' => (string) ($catalog[$key]['default'] ?? '')]);
        }
    }
    astronomySiteConfigResetCache();
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    runEclipseWidgetTemplateStorageMigration(getWebDatabaseConnection());
    echo "Almacenamiento de presets de eclipses actualizado.\n";
}
