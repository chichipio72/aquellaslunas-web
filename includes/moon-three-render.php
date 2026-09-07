<?php

declare(strict_types=1);

require_once __DIR__ . '/web-database.php';
require_once __DIR__ . '/asset-url.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\MoonDiskAppearanceCalculator;

/** @return array<string,array<string,mixed>> */
function moonThreeRenderConfigurationCatalog(): array
{
    return [
        'home.moon_three.sun_intensity' => ['label' => 'Intensidad de luz principal', 'type' => 'number', 'default' => 3.2, 'min' => 0.0, 'max' => 8.0, 'step' => 0.1],
        'home.moon_three.ambient_intensity' => ['label' => 'Intensidad de luz ambiente', 'type' => 'number', 'default' => 0.01, 'min' => 0.0, 'max' => 1.0, 'step' => 0.01],
        'home.moon_three.relief_mode' => ['label' => 'Relieve', 'type' => 'enum', 'default' => 'normal', 'options' => ['none' => 'Sin relieve', 'bump' => 'Bump map', 'normal' => 'Normal map']],
        'home.moon_three.dem_resolution' => ['label' => 'Resolución del DEM', 'type' => 'enum', 'default' => 'medium', 'options' => ['low' => 'LOLA 1024 × 512', 'medium' => 'LOLA 1440 × 720', 'high' => 'LOLA 2880 × 1440']],
        'home.moon_three.bump_scale' => ['label' => 'bumpScale', 'type' => 'number', 'default' => 0.035, 'min' => 0.0, 'max' => 0.2, 'step' => 0.005],
        'home.moon_three.normal_scale_x' => ['label' => 'normalScale X', 'type' => 'number', 'default' => 1.0, 'min' => -4.0, 'max' => 4.0, 'step' => 0.1],
        'home.moon_three.normal_scale_y' => ['label' => 'normalScale Y', 'type' => 'number', 'default' => 1.0, 'min' => -4.0, 'max' => 4.0, 'step' => 0.1],
        'home.moon_three.texture_contrast' => ['label' => 'Contraste de textura', 'type' => 'number', 'default' => 1.15, 'min' => 0.5, 'max' => 2.0, 'step' => 0.05],
        'home.moon_three.texture_brightness' => ['label' => 'Brillo de textura', 'type' => 'number', 'default' => 0.95, 'min' => 0.5, 'max' => 1.5, 'step' => 0.05],
        'home.moon_three.texture_gamma' => ['label' => 'Gamma de textura', 'type' => 'number', 'default' => 0.95, 'min' => 0.5, 'max' => 2.0, 'step' => 0.05],
        'home.moon_three.texture_saturation' => ['label' => 'Saturación de textura', 'type' => 'number', 'default' => 0.8, 'min' => 0.0, 'max' => 2.0, 'step' => 0.05],
        'home.moon_three.exposure' => ['label' => 'Exposición del render', 'type' => 'number', 'default' => 1.15, 'min' => 0.5, 'max' => 2.0, 'step' => 0.05],
        'home.moon_three.size_percent' => ['label' => 'Tamaño de la Luna en la tarjeta (%)', 'type' => 'number', 'default' => 100.0, 'min' => 85.0, 'max' => 120.0, 'step' => 1.0],
    ];
}

/** @return array<string,array<string,mixed>> */
function favoriteMoonThreeRenderConfigurationCatalog(): array
{
    $catalog = [];
    foreach (moonThreeRenderConfigurationCatalog() as $key => $definition) {
        $suffix = substr($key, strlen('home.moon_three.'));
        $definition['label'] = $suffix === 'size_percent' ? 'Tamaño de la Luna (%)' : $definition['label'];
        $catalog['favorite.moon_three.' . $suffix] = $definition;
    }
    $catalog['favorite.moon_three.background_color'] = ['label' => 'Color del fondo', 'type' => 'color', 'default' => '#000000'];
    $catalog['favorite.moon_three.background_brightness'] = ['label' => 'Brillo del fondo', 'type' => 'number', 'default' => 0.0, 'min' => 0.0, 'max' => 0.3, 'step' => 0.01];
    $catalog['favorite.moon_three.background_gradient'] = ['label' => 'Degradado horizontal', 'type' => 'number', 'default' => 0.04, 'min' => 0.0, 'max' => 0.3, 'step' => 0.01];
    $catalog['favorite.moon_three.glow_mode'] = ['label' => 'Tipo de resplandor', 'type' => 'enum', 'default' => 'sky_diffusion', 'options' => ['off' => 'Desactivado', 'halo' => 'Halo', 'sky_diffusion' => 'Difusión de cielo']];
    $catalog['favorite.moon_three.glow_intensity'] = ['label' => 'Intensidad del resplandor', 'type' => 'number', 'default' => 0.18, 'min' => 0.0, 'max' => 1.0, 'step' => 0.01];
    $catalog['favorite.moon_three.glow_radius'] = ['label' => 'Radio del resplandor', 'type' => 'number', 'default' => 1.65, 'min' => 1.05, 'max' => 3.0, 'step' => 0.05];
    $catalog['favorite.moon_three.glow_softness'] = ['label' => 'Suavidad del resplandor', 'type' => 'number', 'default' => 0.75, 'min' => 0.1, 'max' => 1.0, 'step' => 0.05];
    $catalog['favorite.moon_three.glow_color'] = ['label' => 'Color del resplandor', 'type' => 'color', 'default' => '#dbe5ff'];
    $catalog['favorite.moon_three.glow_directional_weight'] = ['label' => 'Peso hacia el lado iluminado', 'type' => 'number', 'default' => 1.0, 'min' => 0.0, 'max' => 2.0, 'step' => 0.05];
    $catalog['favorite.moon_three.glow_symmetric_weight'] = ['label' => 'Peso simétrico', 'type' => 'number', 'default' => 0.7, 'min' => 0.0, 'max' => 2.0, 'step' => 0.05];
    $catalog['favorite.moon_three.glow_full_moon_boost'] = ['label' => 'Refuerzo en Luna llena', 'type' => 'number', 'default' => 0.35, 'min' => 0.0, 'max' => 2.0, 'step' => 0.05];
    return $catalog;
}

/** @return array<string,array<string,mixed>> */
function interactiveMoonThreeRenderConfigurationCatalog(): array
{
    $catalog = [];
    foreach (moonThreeRenderConfigurationCatalog() as $key => $definition) {
        $suffix = substr($key, strlen('home.moon_three.'));
        if ($suffix === 'size_percent') $definition['label'] = 'Tamaño base de la Luna (%)';
        $catalog['interactive.moon_three.' . $suffix] = $definition;
    }
    $catalog['interactive.moon_three.high_res_relief_enabled'] = ['label' => 'Relieve de alta resolución', 'type' => 'enum', 'default' => 'on', 'options' => ['off' => 'Desactivado', 'on' => 'Activado']];
    $catalog['interactive.moon_three.high_res_mode'] = ['label' => 'Selección de normal map', 'type' => 'enum', 'default' => 'auto', 'options' => ['standard' => 'Forzar normal estándar', 'high' => 'Forzar normal 8K', 'auto' => 'Automático según zoom']];
    $catalog['interactive.moon_three.high_res_zoom_threshold'] = ['label' => 'Zoom para cargar 8K', 'type' => 'number', 'default' => 1.3, 'min' => 1.0, 'max' => 1.7, 'step' => 0.05];
    $catalog['interactive.moon_three.high_res_normal_scale_x'] = ['label' => 'normalScale X del mapa 8K', 'type' => 'number', 'default' => 1.0, 'min' => -4.0, 'max' => 4.0, 'step' => 0.1];
    $catalog['interactive.moon_three.high_res_normal_scale_y'] = ['label' => 'normalScale Y del mapa 8K', 'type' => 'number', 'default' => 1.0, 'min' => -4.0, 'max' => 4.0, 'step' => 0.1];
    $catalog['interactive.moon_three.terminator_attenuation_enabled'] = ['label' => 'Atenuar relieve junto al terminador', 'type' => 'enum', 'default' => 'on', 'options' => ['off' => 'Desactivado', 'on' => 'Activado']];
    $catalog['interactive.moon_three.terminator_attenuation_width_degrees'] = ['label' => 'Anchura de atenuación (grados)', 'type' => 'number', 'default' => 8.0, 'min' => 1.0, 'max' => 25.0, 'step' => 0.5];
    $catalog['interactive.moon_three.terminator_attenuation_minimum_percent'] = ['label' => 'Relieve mínimo en el terminador (%)', 'type' => 'number', 'default' => 10.0, 'min' => 0.0, 'max' => 100.0, 'step' => 1.0];
    $catalog['interactive.moon_three.terminator_attenuation_curve'] = ['label' => 'Suavidad de la transición', 'type' => 'number', 'default' => 1.5, 'min' => 0.25, 'max' => 4.0, 'step' => 0.05];
    return $catalog;
}

/** @return array<string,float|string> */
function moonThreeRenderConfigurationDefaults(): array
{
    $defaults = [];
    foreach (moonThreeRenderConfigurationCatalog() as $key => $definition) {
        $defaults[$key] = $definition['default'];
    }
    return $defaults;
}

function moonThreeRenderValidatedValue(string $key, mixed $value): float|string
{
    $definition = (moonThreeRenderConfigurationCatalog() + favoriteMoonThreeRenderConfigurationCatalog() + interactiveMoonThreeRenderConfigurationCatalog())[$key] ?? null;
    if (!is_array($definition)) {
        throw new InvalidArgumentException('Parámetro lunar desconocido.');
    }
    if (($definition['type'] ?? '') === 'enum') {
        $normalized = trim((string) $value);
        if (!array_key_exists($normalized, $definition['options'] ?? [])) {
            throw new InvalidArgumentException('Opción lunar inválida para ' . $key . '.');
        }
        return $normalized;
    }
    if (($definition['type'] ?? '') === 'color') {
        $normalized = strtolower(trim((string) $value));
        if (preg_match('/^#[0-9a-f]{6}$/', $normalized) !== 1) throw new InvalidArgumentException('Color lunar inválido.');
        return $normalized;
    }
    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Valor numérico lunar inválido para ' . $key . '.');
    }
    $number = (float) $value;
    if (!is_finite($number) || $number < (float) $definition['min'] || $number > (float) $definition['max']) {
        throw new InvalidArgumentException('Valor lunar fuera de rango para ' . $key . '.');
    }
    $step = (float) ($definition['step'] ?? 0.0);
    if ($step > 0.0) {
        $steps = round(($number - (float) $definition['min']) / $step);
        $number = (float) $definition['min'] + $steps * $step;
    }
    return $number;
}

/** @param array<string,array<string,mixed>> $catalog @return array<string,float|string> */
function moonThreeRenderCatalogDefaults(array $catalog): array
{
    $defaults = [];
    foreach ($catalog as $key => $definition) $defaults[$key] = $definition['default'];
    return $defaults;
}

/** @param array<string,array<string,mixed>> $catalog @return array<string,float|string> */
function moonThreeRenderCatalogLoad(array $catalog, PDO $connection): array
{
    $values = moonThreeRenderCatalogDefaults($catalog);
    $keys = array_keys($values);
    $statement = $connection->prepare('SELECT clave, valor FROM admin_configuracion_sitio WHERE clave IN (' . implode(',', array_fill(0, count($keys), '?')) . ')');
    $statement->execute($keys);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (string) ($row['clave'] ?? '');
        if (!array_key_exists($key, $values)) continue;
        try {
            $values[$key] = moonThreeRenderValidatedValue($key, $row['valor'] ?? '');
        } catch (InvalidArgumentException) {
        }
    }
    return $values;
}

/** @return array<string,float|string> */
function moonThreeRenderConfigurationLoad(?callable $connectionFactory = null): array
{
    $values = moonThreeRenderConfigurationDefaults();
    try {
        $connection = $connectionFactory !== null ? $connectionFactory() : getWebDatabaseConnection();
        if (!$connection instanceof PDO) throw new RuntimeException('Se esperaba una conexión PDO.');
        $keys = array_keys($values);
        $statement = $connection->prepare('SELECT clave, valor FROM admin_configuracion_sitio WHERE clave IN (' . implode(',', array_fill(0, count($keys), '?')) . ')');
        $statement->execute($keys);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) ($row['clave'] ?? '');
            if (!array_key_exists($key, $values)) continue;
            try {
                $values[$key] = moonThreeRenderValidatedValue($key, $row['valor'] ?? '');
            } catch (InvalidArgumentException) {
                // Un valor persistido inválido nunca debe romper la portada.
            }
        }
    } catch (Throwable $exception) {
        error_log('Aquellas Lunas moon render config load error: ' . $exception->getMessage());
    }
    return $values;
}

function moonThreeRenderConfigurationInitialize(PDO $connection): void
{
    $statement = $connection->prepare(
        'INSERT INTO admin_configuracion_sitio (clave,valor,descripcion) VALUES (:key,:value,:description) '
        . 'ON DUPLICATE KEY UPDATE clave=clave'
    );
    foreach (moonThreeRenderConfigurationCatalog() as $key => $definition) {
        $statement->execute([
            ':key' => $key,
            ':value' => (string) $definition['default'],
            ':description' => 'Apariencia de la Luna Three.js de portada: ' . $definition['label'],
        ]);
    }
}

function favoriteMoonThreeRenderConfigurationInitialize(PDO $connection): void
{
    $home = moonThreeRenderCatalogLoad(moonThreeRenderConfigurationCatalog(), $connection);
    $statement = $connection->prepare(
        'INSERT INTO admin_configuracion_sitio (clave,valor,descripcion) VALUES (:key,:value,:description) '
        . 'ON DUPLICATE KEY UPDATE clave=clave'
    );
    foreach (favoriteMoonThreeRenderConfigurationCatalog() as $key => $definition) {
        $suffix = substr($key, strlen('favorite.moon_three.'));
        $homeKey = 'home.moon_three.' . $suffix;
        $initialValue = $home[$homeKey] ?? $definition['default'];
        $statement->execute([
            ':key' => $key,
            ':value' => (string) $initialValue,
            ':description' => 'Apariencia de La Luna de tu fecha favorita: ' . $definition['label'],
        ]);
    }
}

function interactiveMoonThreeRenderConfigurationInitialize(PDO $connection): void
{
    $home = moonThreeRenderCatalogLoad(moonThreeRenderConfigurationCatalog(), $connection);
    $statement = $connection->prepare(
        'INSERT INTO admin_configuracion_sitio (clave,valor,descripcion) VALUES (:key,:value,:description) '
        . 'ON DUPLICATE KEY UPDATE clave=clave'
    );
    foreach (interactiveMoonThreeRenderConfigurationCatalog() as $key => $definition) {
        $suffix = substr($key, strlen('interactive.moon_three.'));
        $statement->execute([
            ':key' => $key,
            ':value' => (string) ($home['home.moon_three.' . $suffix] ?? $definition['default']),
            ':description' => 'Apariencia de la Luna interactiva: ' . $definition['label'],
        ]);
    }
}

/** @return array<string,float|string> */
function favoriteMoonThreeRenderConfigurationLoad(?callable $connectionFactory = null): array
{
    $catalog = favoriteMoonThreeRenderConfigurationCatalog();
    try {
        $connection = $connectionFactory !== null ? $connectionFactory() : getWebDatabaseConnection();
        if (!$connection instanceof PDO) throw new RuntimeException('Se esperaba una conexión PDO.');
        favoriteMoonThreeRenderConfigurationInitialize($connection);
        return moonThreeRenderCatalogLoad($catalog, $connection);
    } catch (Throwable $exception) {
        error_log('Aquellas Lunas favorite Moon render config load error: ' . $exception->getMessage());
        return moonThreeRenderCatalogDefaults($catalog);
    }
}

/** @return array<string,float|string> */
function interactiveMoonThreeRenderConfigurationLoad(?callable $connectionFactory = null): array
{
    $catalog = interactiveMoonThreeRenderConfigurationCatalog();
    try {
        $connection = $connectionFactory !== null ? $connectionFactory() : getWebDatabaseConnection();
        if (!$connection instanceof PDO) throw new RuntimeException('Se esperaba una conexión PDO.');
        $home = moonThreeRenderCatalogLoad(moonThreeRenderConfigurationCatalog(), $connection);
        $values = [];
        foreach ($catalog as $key => $definition) {
            $suffix = substr($key, strlen('interactive.moon_three.'));
            $values[$key] = $home['home.moon_three.' . $suffix] ?? $definition['default'];
        }
        $stored = moonThreeRenderCatalogLoad($catalog, $connection);
        $keys = array_keys($catalog);
        $statement = $connection->prepare('SELECT clave FROM admin_configuracion_sitio WHERE clave IN (' . implode(',', array_fill(0, count($keys), '?')) . ')');
        $statement->execute($keys);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $key) {
            if (array_key_exists($key, $stored)) $values[$key] = $stored[$key];
        }
        return $values;
    } catch (Throwable $exception) {
        error_log('Aquellas Lunas interactive Moon render config load error: ' . $exception->getMessage());
        return moonThreeRenderCatalogDefaults($catalog);
    }
}

/** @param array<string,mixed> $updates */
function moonThreeRenderConfigurationUpdate(PDO $connection, array $updates): void
{
    $catalog = moonThreeRenderConfigurationCatalog();
    if (array_diff_key($updates, $catalog) !== [] || array_diff_key($catalog, $updates) !== []) {
        throw new InvalidArgumentException('El formulario lunar no contiene el conjunto exacto de parámetros.');
    }
    $validated = [];
    foreach ($updates as $key => $value) $validated[$key] = moonThreeRenderValidatedValue($key, $value);
    $connection->beginTransaction();
    try {
        moonThreeRenderConfigurationInitialize($connection);
        $statement = $connection->prepare('UPDATE admin_configuracion_sitio SET valor=:value WHERE clave=:key');
        foreach ($validated as $key => $value) $statement->execute([':key' => $key, ':value' => (string) $value]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

/** @param array<string,mixed> $updates */
function favoriteMoonThreeRenderConfigurationUpdate(PDO $connection, array $updates): void
{
    $catalog = favoriteMoonThreeRenderConfigurationCatalog();
    if (array_diff_key($updates, $catalog) !== [] || array_diff_key($catalog, $updates) !== []) {
        throw new InvalidArgumentException('El formulario lunar favorito no contiene el conjunto exacto de parámetros.');
    }
    $validated = [];
    foreach ($updates as $key => $value) $validated[$key] = moonThreeRenderValidatedValue($key, $value);
    $connection->beginTransaction();
    try {
        favoriteMoonThreeRenderConfigurationInitialize($connection);
        $statement = $connection->prepare('UPDATE admin_configuracion_sitio SET valor=:value WHERE clave=:key');
        foreach ($validated as $key => $value) $statement->execute([':key' => $key, ':value' => (string) $value]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

/** @param array<string,mixed> $updates */
function interactiveMoonThreeRenderConfigurationUpdate(PDO $connection, array $updates): void
{
    $catalog = interactiveMoonThreeRenderConfigurationCatalog();
    if (array_diff_key($updates, $catalog) !== [] || array_diff_key($catalog, $updates) !== []) {
        throw new InvalidArgumentException('El formulario lunar interactivo no contiene el conjunto exacto de parámetros.');
    }
    $validated = [];
    foreach ($updates as $key => $value) $validated[$key] = moonThreeRenderValidatedValue($key, $value);
    $connection->beginTransaction();
    try {
        interactiveMoonThreeRenderConfigurationInitialize($connection);
        $statement = $connection->prepare('UPDATE admin_configuracion_sitio SET valor=:value WHERE clave=:key');
        foreach ($validated as $key => $value) $statement->execute([':key' => $key, ':value' => (string) $value]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

/** @return array<string,mixed> */
function moonThreeRenderPayload(DateTimeImmutable $instant, AstronomyObserver $observer, ?array $configuration = null, string $configurationPrefix = 'home.moon_three.'): array
{
    $configuration ??= moonThreeRenderConfigurationLoad();
    $geometry = (new MoonDiskAppearanceCalculator())->calculate($instant, $observer);
    $short = static fn(string $key): float|string => $configuration[$configurationPrefix . $key];
    $dem = (string) $short('dem_resolution');
    $relief = (string) $short('relief_mode');
    $maps = [
        'low' => ['height' => 'assets/images/moon-three/ldem_3_8bit.jpg', 'normal' => 'assets/images/moon-three/ldem_3_normal.png'],
        'medium' => ['height' => 'assets/images/moon-three/ldem_4_height.png', 'normal' => 'assets/images/moon-three/ldem_4_normal.png'],
        'high' => ['height' => 'assets/images/moon-three/ldem_8_height.png', 'normal' => 'assets/images/moon-three/ldem_8_normal.png'],
    ];
    $highResolution = null;
    if ($configurationPrefix === 'interactive.moon_three.') {
        $highResolution = [
            'enabled' => $configuration[$configurationPrefix . 'high_res_relief_enabled'] ?? 'on',
            'mode' => $configuration[$configurationPrefix . 'high_res_mode'] ?? 'auto',
            'zoom_threshold' => $configuration[$configurationPrefix . 'high_res_zoom_threshold'] ?? 1.3,
            'normal_scale_x' => $configuration[$configurationPrefix . 'high_res_normal_scale_x'] ?? 1.0,
            'normal_scale_y' => $configuration[$configurationPrefix . 'high_res_normal_scale_y'] ?? 1.0,
        ];
    } elseif ($configurationPrefix === 'favorite.moon_three.') {
        // La vista conserva el mapa configurado; el 8K se reserva para la exportación.
        $highResolution = [
            'enabled' => 'on',
            'mode' => 'export',
            'normal_scale_x' => $short('normal_scale_x'),
            'normal_scale_y' => $short('normal_scale_y'),
        ];
    }
    $interactiveTerminatorAttenuation = $configurationPrefix === 'interactive.moon_three.' ? [
        'terminator_attenuation_enabled' => $configuration[$configurationPrefix . 'terminator_attenuation_enabled'] ?? 'on',
        'terminator_attenuation_width_degrees' => $configuration[$configurationPrefix . 'terminator_attenuation_width_degrees'] ?? 8.0,
        'terminator_attenuation_minimum_percent' => $configuration[$configurationPrefix . 'terminator_attenuation_minimum_percent'] ?? 10.0,
        'terminator_attenuation_curve' => $configuration[$configurationPrefix . 'terminator_attenuation_curve'] ?? 1.5,
    ] : [];
    return [
        'geometry' => $geometry,
        'appearance' => [
            'sun_intensity' => $short('sun_intensity'),
            'ambient_intensity' => $short('ambient_intensity'),
            'relief_mode' => $relief,
            'bump_scale' => $short('bump_scale'),
            'normal_scale_x' => $short('normal_scale_x'),
            'normal_scale_y' => $short('normal_scale_y'),
            'texture_contrast' => $short('texture_contrast'),
            'texture_brightness' => $short('texture_brightness'),
            'texture_gamma' => $short('texture_gamma'),
            'texture_saturation' => $short('texture_saturation'),
            'exposure' => $short('exposure'),
            'size_percent' => $short('size_percent'),
            'high_resolution' => $highResolution,
        ] + $interactiveTerminatorAttenuation,
        'textures' => [
            'albedo' => versionedAssetUrl('assets/images/moon-three/lroc_color_2k.jpg'),
            'relief' => $relief === 'none' ? null : versionedAssetUrl($maps[$dem][$relief === 'bump' ? 'height' : 'normal']),
            'relief_high' => $highResolution !== null ? versionedAssetUrl('assets/images/moon-three/lola_64ppd_normal_8k.png') : null,
        ],
        'wallpaper' => [
            'background_color' => $configuration[$configurationPrefix . 'background_color'] ?? '#000000',
            'background_brightness' => $configuration[$configurationPrefix . 'background_brightness'] ?? 0.0,
            'background_gradient' => $configuration[$configurationPrefix . 'background_gradient'] ?? 0.0,
            'glow_mode' => $configuration[$configurationPrefix . 'glow_mode']
                ?? (($configuration[$configurationPrefix . 'glow_enabled'] ?? 'off') === 'on' ? 'halo' : 'off'),
            'glow_intensity' => $configuration[$configurationPrefix . 'glow_intensity'] ?? 0.0,
            'glow_radius' => $configuration[$configurationPrefix . 'glow_radius'] ?? 1.65,
            'glow_softness' => $configuration[$configurationPrefix . 'glow_softness'] ?? 0.75,
            'glow_color' => $configuration[$configurationPrefix . 'glow_color'] ?? '#dbe5ff',
            'glow_directional_weight' => $configuration[$configurationPrefix . 'glow_directional_weight'] ?? 1.0,
            'glow_symmetric_weight' => $configuration[$configurationPrefix . 'glow_symmetric_weight'] ?? 0.7,
            'glow_full_moon_boost' => $configuration[$configurationPrefix . 'glow_full_moon_boost'] ?? 0.35,
        ],
    ];
}
