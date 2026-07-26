<?php

const ASTRONOMY_PRODUCTION_CONFIG_PATH = '/home8/aquellaslunascom/config/astronomia.php';
const DEFAULT_ALTITUDE_PROFILE_INTERVAL_MINUTES = 15;
const MIN_ALTITUDE_PROFILE_INTERVAL_MINUTES = 5;
const MAX_ALTITUDE_PROFILE_INTERVAL_MINUTES = 60;
const DEFAULT_SUPERMOON_MIN_APPARENT_SIZE_PERCENT = 105.0;
const MIN_SUPERMOON_MIN_APPARENT_SIZE_PERCENT = 90.0;
const MAX_SUPERMOON_MIN_APPARENT_SIZE_PERCENT = 120.0;
const DEFAULT_MOONRISE_NOTICE_MAX_MINUTES = 120;
const MIN_MOONRISE_NOTICE_MAX_MINUTES = 1;
const MAX_MOONRISE_NOTICE_MAX_MINUTES = 1440;
const DEFAULT_MOBILE_SWIPE_NAVIGATION_ENABLED = true;
const DEFAULT_MOBILE_SWIPE_NAVIGATION_HINT_ENABLED = true;
const DEFAULT_MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED = false;
const DEFAULT_STORE_PREVIEW_TIENDA_MAX_SIZE = 800;
const DEFAULT_STORE_PREVIEW_CONTENIDO_MAX_SIZE = 400;
const MIN_STORE_PREVIEW_MAX_SIZE = 320;
const MAX_STORE_PREVIEW_MAX_SIZE = 8000;
const DEFAULT_STORE_PREVIEW_TIENDA_JPEG_QUALITY = 72;
const DEFAULT_STORE_PREVIEW_CONTENIDO_JPEG_QUALITY = 72;
const MIN_STORE_PREVIEW_JPEG_QUALITY = 30;
const MAX_STORE_PREVIEW_JPEG_QUALITY = 95;
const DEFAULT_STORE_DOWNLOAD_EXPIRY_HOURS = 72;
const MIN_STORE_DOWNLOAD_EXPIRY_HOURS = 1;
const MAX_STORE_DOWNLOAD_EXPIRY_HOURS = 8760;
const DEFAULT_STORE_DOWNLOAD_MAX_COUNT = 5;
const MIN_STORE_DOWNLOAD_MAX_COUNT = 1;
const MAX_STORE_DOWNLOAD_MAX_COUNT = 100;

function loadAstronomyProductionConfig(?string $productionConfigPath = null): array
{
    $configPath = $productionConfigPath ?? ASTRONOMY_PRODUCTION_CONFIG_PATH;
    if (!is_file($configPath) || !is_readable($configPath)) {
        return [];
    }
    try {
        $config = require $configPath;
    } catch (Throwable $exception) {
        throw new RuntimeException('No se pudo cargar la configuración de astronomía.', 0, $exception);
    }
    return is_array($config) ? $config : [];
}

function loadStoreConfig(?string $productionConfigPath = null): array
{
    $productionConfig = null;
    $definitions = [
        'originals_path' => [
            'environment' => 'STORE_ORIGINALS_PATH',
            'production' => 'store_originals_path',
            'writable' => false,
        ],
        'previews_path' => [
            'environment' => 'STORE_PREVIEWS_PATH',
            'production' => 'store_previews_path',
            'writable' => true,
        ],
        'catalog_path' => [
            'environment' => 'STORE_CATALOG_PATH',
            'production' => 'store_catalog_path',
            'writable' => true,
        ],
    ];
    $paths = [];

    foreach ($definitions as $key => $definition) {
        $environmentValue = getenv($definition['environment']);
        if (is_string($environmentValue) && trim($environmentValue) !== '') {
            $rawValue = trim($environmentValue);
        } else {
            $productionConfig ??= loadAstronomyProductionConfig($productionConfigPath);
            $rawValue = $productionConfig[$definition['production']] ?? '';
        }
        $path = is_string($rawValue) ? rtrim(trim($rawValue), DIRECTORY_SEPARATOR) : '';

        if (
            $path === ''
            || !str_starts_with($path, DIRECTORY_SEPARATOR)
            || !is_dir($path)
            || !is_readable($path)
            || ($definition['writable'] && !is_writable($path))
        ) {
            throw new RuntimeException('La configuración de la tienda no está disponible.');
        }

        $paths[$key] = $path;
    }

    return $paths;
}

function loadStoreDatabaseConfig(?string $productionConfigPath = null): array
{
    $productionConfig = null;
    $definitions = [
        'host' => ['environment' => 'STORE_DB_HOST', 'production' => 'store_db_host'],
        'port' => ['environment' => 'STORE_DB_PORT', 'production' => 'store_db_port'],
        'name' => ['environment' => 'STORE_DB_NAME', 'production' => 'store_db_name'],
        'user' => ['environment' => 'STORE_DB_USER', 'production' => 'store_db_user'],
        'password' => ['environment' => 'STORE_DB_PASSWORD', 'production' => 'store_db_password'],
    ];
    $databaseConfig = [];

    foreach ($definitions as $key => $definition) {
        $environmentValue = getenv($definition['environment']);
        if (is_string($environmentValue) && trim($environmentValue) !== '') {
            $rawValue = $key === 'password' ? $environmentValue : trim($environmentValue);
        } else {
            $productionConfig ??= loadAstronomyProductionConfig($productionConfigPath);
            $rawValue = $productionConfig[$definition['production']] ?? '';
        }

        if (!is_scalar($rawValue) || trim((string) $rawValue) === '') {
            throw new RuntimeException('La configuración de la base de datos de la tienda no está disponible.');
        }
        $databaseConfig[$key] = $key === 'password'
            ? (string) $rawValue
            : trim((string) $rawValue);
    }

    $validatedPort = filter_var($databaseConfig['port'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($validatedPort === false) {
        throw new RuntimeException('La configuración de la base de datos de la tienda no está disponible.');
    }
    $databaseConfig['port'] = (int) $validatedPort;

    return $databaseConfig;
}

function loadStoreAdminConfig(?string $productionConfigPath = null): array
{
    $productionConfig = null;
    $definitions = [
        'user' => ['environment' => 'STORE_ADMIN_USER', 'production' => 'store_admin_user'],
        'password_hash' => ['environment' => 'STORE_ADMIN_PASSWORD_HASH', 'production' => 'store_admin_password_hash'],
    ];
    $adminConfig = [];

    foreach ($definitions as $key => $definition) {
        $environmentValue = getenv($definition['environment']);
        if (is_string($environmentValue) && trim($environmentValue) !== '') {
            $rawValue = trim($environmentValue);
        } else {
            $productionConfig ??= loadAstronomyProductionConfig($productionConfigPath);
            $rawValue = $productionConfig[$definition['production']] ?? '';
        }
        if (!is_string($rawValue) || trim($rawValue) === '') {
            throw new RuntimeException('La configuración de administración de la tienda no está disponible.');
        }
        $adminConfig[$key] = trim($rawValue);
    }

    if (strlen($adminConfig['user']) > 190 || password_get_info($adminConfig['password_hash'])['algo'] === null) {
        throw new RuntimeException('La configuración de administración de la tienda no está disponible.');
    }
    return $adminConfig;
}

function loadMercadoPagoConfig(?string $productionConfigPath = null): array
{
    $productionConfig = null;
    $definitions = [
        'mode' => ['environment' => 'MERCADO_PAGO_MODE', 'production' => 'mercado_pago_mode', 'secret' => false],
        'access_token' => ['environment' => 'MERCADO_PAGO_ACCESS_TOKEN', 'production' => 'mercado_pago_access_token', 'secret' => true],
        'public_key' => ['environment' => 'MERCADO_PAGO_PUBLIC_KEY', 'production' => 'mercado_pago_public_key', 'secret' => true],
        'webhook_secret' => ['environment' => 'MERCADO_PAGO_WEBHOOK_SECRET', 'production' => 'mercado_pago_webhook_secret', 'secret' => true],
        'success_url' => ['environment' => 'MERCADO_PAGO_SUCCESS_URL', 'production' => 'mercado_pago_success_url', 'secret' => false],
        'pending_url' => ['environment' => 'MERCADO_PAGO_PENDING_URL', 'production' => 'mercado_pago_pending_url', 'secret' => false],
        'failure_url' => ['environment' => 'MERCADO_PAGO_FAILURE_URL', 'production' => 'mercado_pago_failure_url', 'secret' => false],
        'notification_url' => ['environment' => 'MERCADO_PAGO_NOTIFICATION_URL', 'production' => 'mercado_pago_notification_url', 'secret' => false],
    ];
    $config = [];
    $configSources = [];

    foreach ($definitions as $key => $definition) {
        $environmentValue = getenv($definition['environment']);
        if (is_string($environmentValue) && trim($environmentValue) !== '') {
            $rawValue = $environmentValue;
            $configSources[$key] = 'environment';
        } else {
            $productionConfig ??= loadAstronomyProductionConfig($productionConfigPath);
            $rawValue = $productionConfig[$definition['production']] ?? '';
            $configSources[$key] = 'private_config';
        }
        $emptyWebhookAllowed = $key === 'webhook_secret'
            && ($config['mode'] ?? null) === 'test'
            && is_string($rawValue);
        if ((!is_string($rawValue) || trim($rawValue) === '') && !$emptyWebhookAllowed) {
            throw new RuntimeException('La configuración de Mercado Pago no está disponible.');
        }
        $config[$key] = $definition['secret'] ? $rawValue : trim($rawValue);
    }

    if (!in_array($config['mode'], ['test', 'production'], true)) {
        throw new RuntimeException('La configuración de Mercado Pago no está disponible.');
    }
    foreach (['success_url', 'pending_url', 'failure_url', 'notification_url'] as $urlKey) {
        $url = $config[$urlKey];
        $parts = parse_url($url);
        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || !isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new RuntimeException('La configuración de Mercado Pago no está disponible.');
        }
    }
    $config['_sources'] = $configSources;
    return $config;
}

function loadStoreInitialPrice(?string $productionConfigPath = null): string
{
    $environmentValue = getenv('STORE_INITIAL_PRICE');
    $rawValue = is_string($environmentValue) && trim($environmentValue) !== ''
        ? trim($environmentValue)
        : (loadAstronomyProductionConfig($productionConfigPath)['store_initial_price'] ?? '');
    $price = is_scalar($rawValue) ? trim((string) $rawValue) : '';

    if (
        preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', $price) !== 1
        || (float) $price <= 0
    ) {
        throw new RuntimeException('La configuración del precio inicial de la tienda no está disponible.');
    }

    [$integerPart, $decimalPart] = array_pad(explode('.', $price, 2), 2, '');
    return $integerPart . '.' . str_pad($decimalPart, 2, '0');
}

function loadStorePreviewIntegerConfig(
    string $environmentName,
    string $productionKey,
    int $defaultValue,
    int $minimum,
    int $maximum,
    ?string $productionConfigPath = null
): int
{
    $environmentValue = getenv($environmentName);
    $rawValue = is_string($environmentValue) && trim($environmentValue) !== ''
        ? trim($environmentValue)
        : (loadAstronomyProductionConfig($productionConfigPath)[$productionKey] ?? $defaultValue);
    $validated = filter_var($rawValue, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $minimum, 'max_range' => $maximum],
    ]);
    if ($validated === false) {
        throw new RuntimeException('La configuración de vistas previas de la tienda no está disponible.');
    }
    return (int) $validated;
}

function loadStorePreviewTiendaMaxSize(?string $productionConfigPath = null): int
{
    return loadStorePreviewIntegerConfig('STORE_PREVIEW_TIENDA_MAX_SIZE', 'store_preview_tienda_max_size', DEFAULT_STORE_PREVIEW_TIENDA_MAX_SIZE, MIN_STORE_PREVIEW_MAX_SIZE, MAX_STORE_PREVIEW_MAX_SIZE, $productionConfigPath);
}

function loadStorePreviewTiendaJpegQuality(?string $productionConfigPath = null): int
{
    return loadStorePreviewIntegerConfig('STORE_PREVIEW_TIENDA_JPEG_QUALITY', 'store_preview_tienda_jpeg_quality', DEFAULT_STORE_PREVIEW_TIENDA_JPEG_QUALITY, MIN_STORE_PREVIEW_JPEG_QUALITY, MAX_STORE_PREVIEW_JPEG_QUALITY, $productionConfigPath);
}

function loadStorePreviewContenidoMaxSize(?string $productionConfigPath = null): int
{
    return loadStorePreviewIntegerConfig('STORE_PREVIEW_CONTENIDO_MAX_SIZE', 'store_preview_contenido_max_size', DEFAULT_STORE_PREVIEW_CONTENIDO_MAX_SIZE, MIN_STORE_PREVIEW_MAX_SIZE, MAX_STORE_PREVIEW_MAX_SIZE, $productionConfigPath);
}

function loadStorePreviewContenidoJpegQuality(?string $productionConfigPath = null): int
{
    return loadStorePreviewIntegerConfig('STORE_PREVIEW_CONTENIDO_JPEG_QUALITY', 'store_preview_contenido_jpeg_quality', DEFAULT_STORE_PREVIEW_CONTENIDO_JPEG_QUALITY, MIN_STORE_PREVIEW_JPEG_QUALITY, MAX_STORE_PREVIEW_JPEG_QUALITY, $productionConfigPath);
}

function loadStoreDownloadIntegerConfig(
    string $environmentName,
    string $productionKey,
    int $defaultValue,
    int $minimum,
    int $maximum,
    ?string $productionConfigPath = null
): int {
    $environmentValue = getenv($environmentName);
    $rawValue = is_string($environmentValue) && trim($environmentValue) !== ''
        ? trim($environmentValue)
        : (loadAstronomyProductionConfig($productionConfigPath)[$productionKey] ?? $defaultValue);
    $validated = filter_var($rawValue, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $minimum, 'max_range' => $maximum],
    ]);
    if ($validated === false) {
        throw new RuntimeException('La configuración de descargas de la tienda no está disponible.');
    }
    return (int) $validated;
}

function loadStoreDownloadExpiryHours(?string $productionConfigPath = null): int
{
    return loadStoreDownloadIntegerConfig(
        'STORE_DOWNLOAD_EXPIRY_HOURS',
        'store_download_expiry_hours',
        DEFAULT_STORE_DOWNLOAD_EXPIRY_HOURS,
        MIN_STORE_DOWNLOAD_EXPIRY_HOURS,
        MAX_STORE_DOWNLOAD_EXPIRY_HOURS,
        $productionConfigPath
    );
}

function loadStoreDownloadMaxCount(?string $productionConfigPath = null): int
{
    return loadStoreDownloadIntegerConfig(
        'STORE_DOWNLOAD_MAX_COUNT',
        'store_download_max_count',
        DEFAULT_STORE_DOWNLOAD_MAX_COUNT,
        MIN_STORE_DOWNLOAD_MAX_COUNT,
        MAX_STORE_DOWNLOAD_MAX_COUNT,
        $productionConfigPath
    );
}

function loadAltitudeProfileIntervalMinutes(?string $productionConfigPath = null): int
{
    $environmentValue = getenv('ALTITUDE_PROFILE_INTERVAL_MINUTES');
    $rawValue = is_string($environmentValue) && trim($environmentValue) !== ''
        ? trim($environmentValue)
        : (loadAstronomyProductionConfig($productionConfigPath)['altitude_profile_interval_minutes'] ?? DEFAULT_ALTITUDE_PROFILE_INTERVAL_MINUTES);

    $validated = filter_var($rawValue, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => MIN_ALTITUDE_PROFILE_INTERVAL_MINUTES,
            'max_range' => MAX_ALTITUDE_PROFILE_INTERVAL_MINUTES,
        ],
    ]);
    if ($validated === false) {
        throw new RuntimeException('ALTITUDE_PROFILE_INTERVAL_MINUTES debe ser un entero entre 5 y 60.');
    }
    return (int) $validated;
}

function loadSupermoonMinApparentSizePercent(?string $productionConfigPath = null): float
{
    $environmentValue = getenv('SUPERMOON_MIN_APPARENT_SIZE_PERCENT');
    $rawValue = is_string($environmentValue) && trim($environmentValue) !== ''
        ? trim($environmentValue)
        : (loadAstronomyProductionConfig($productionConfigPath)['supermoon_min_apparent_size_percent'] ?? DEFAULT_SUPERMOON_MIN_APPARENT_SIZE_PERCENT);

    $validated = filter_var($rawValue, FILTER_VALIDATE_FLOAT);
    if (
        $validated === false
        || !is_finite((float) $validated)
        || (float) $validated < MIN_SUPERMOON_MIN_APPARENT_SIZE_PERCENT
        || (float) $validated > MAX_SUPERMOON_MIN_APPARENT_SIZE_PERCENT
    ) {
        throw new RuntimeException('SUPERMOON_MIN_APPARENT_SIZE_PERCENT debe ser un número entre 90 y 120.');
    }
    return (float) $validated;
}

function loadMoonriseNoticeMaxMinutes(?string $productionConfigPath = null): int
{
    $environmentValue = getenv('MOONRISE_NOTICE_MAX_MINUTES');
    $rawValue = is_string($environmentValue) && trim($environmentValue) !== ''
        ? trim($environmentValue)
        : (loadAstronomyProductionConfig($productionConfigPath)['moonrise_notice_max_minutes'] ?? DEFAULT_MOONRISE_NOTICE_MAX_MINUTES);
    $validated = filter_var($rawValue, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => MIN_MOONRISE_NOTICE_MAX_MINUTES,
            'max_range' => MAX_MOONRISE_NOTICE_MAX_MINUTES,
        ],
    ]);
    if ($validated === false) {
        throw new RuntimeException('MOONRISE_NOTICE_MAX_MINUTES debe ser un entero entre 1 y 1440.');
    }
    return (int) $validated;
}

function loadAstronomyBooleanConfig(
    string $environmentName,
    string $productionKey,
    bool $defaultValue,
    ?string $productionConfigPath = null
): bool {
    $environmentValue = getenv($environmentName);
    $rawValue = is_string($environmentValue) && trim($environmentValue) !== ''
        ? trim($environmentValue)
        : (loadAstronomyProductionConfig($productionConfigPath)[$productionKey] ?? $defaultValue);
    $validated = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($validated === null) {
        throw new RuntimeException($environmentName . ' debe ser true/false, 1/0, yes/no u on/off.');
    }
    return $validated;
}

function loadMobileSwipeNavigationEnabled(?string $productionConfigPath = null): bool
{
    return loadAstronomyBooleanConfig(
        'MOBILE_SWIPE_NAVIGATION_ENABLED',
        'mobile_swipe_navigation_enabled',
        DEFAULT_MOBILE_SWIPE_NAVIGATION_ENABLED,
        $productionConfigPath
    );
}

function loadMobileSwipeNavigationHintEnabled(?string $productionConfigPath = null): bool
{
    return loadAstronomyBooleanConfig(
        'MOBILE_SWIPE_NAVIGATION_HINT_ENABLED',
        'mobile_swipe_navigation_hint_enabled',
        DEFAULT_MOBILE_SWIPE_NAVIGATION_HINT_ENABLED,
        $productionConfigPath
    );
}

function loadMobileSwipeNavigationDebugEnabled(?string $productionConfigPath = null): bool
{
    return loadAstronomyBooleanConfig(
        'MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED',
        'mobile_swipe_navigation_debug_enabled',
        DEFAULT_MOBILE_SWIPE_NAVIGATION_DEBUG_ENABLED,
        $productionConfigPath
    );
}

function loadAstronomyApiConfig(?string $productionConfigPath = null): array
{
    $apiBaseUrl = getenv('ASTRONOMY_API_BASE_URL');
    $apiBaseUrl = is_string($apiBaseUrl) ? trim($apiBaseUrl) : '';
    $source = 'environment';

    if ($apiBaseUrl === '') {
        $configPath = $productionConfigPath ?? ASTRONOMY_PRODUCTION_CONFIG_PATH;
        $source = 'external_file';

        if (is_file($configPath) && is_readable($configPath)) {
            $productionConfig = loadAstronomyProductionConfig($configPath);
            $configuredApiBaseUrl = is_array($productionConfig)
                ? ($productionConfig['astronomy_api_base_url'] ?? '')
                : '';
            $apiBaseUrl = is_string($configuredApiBaseUrl) ? trim($configuredApiBaseUrl) : '';
        }
    }

    $apiBaseUrl = rtrim($apiBaseUrl, '/');
    $scheme = is_string(parse_url($apiBaseUrl, PHP_URL_SCHEME))
        ? strtolower((string) parse_url($apiBaseUrl, PHP_URL_SCHEME))
        : '';

    if (
        $apiBaseUrl === ''
        || filter_var($apiBaseUrl, FILTER_VALIDATE_URL) === false
        || !in_array($scheme, ['http', 'https'], true)
    ) {
        throw new RuntimeException('No hay una URL base válida configurada para la API.');
    }

    return [
        'base_url' => $apiBaseUrl,
        'source' => $source,
    ];
}
