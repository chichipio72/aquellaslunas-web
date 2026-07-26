<?php

require_once __DIR__ . '/../includes/api-config.php';

function mercadoPagoConfigAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$names = [
    'MERCADO_PAGO_MODE', 'MERCADO_PAGO_ACCESS_TOKEN', 'MERCADO_PAGO_PUBLIC_KEY',
    'MERCADO_PAGO_WEBHOOK_SECRET', 'MERCADO_PAGO_SUCCESS_URL', 'MERCADO_PAGO_PENDING_URL',
    'MERCADO_PAGO_FAILURE_URL', 'MERCADO_PAGO_NOTIFICATION_URL',
];
$previous = array_fill_keys($names, false);
$temporaryConfigPath = null;
foreach ($names as $name) {
    $previous[$name] = getenv($name);
}

try {
    putenv('MERCADO_PAGO_MODE=test');
    putenv('MERCADO_PAGO_ACCESS_TOKEN= token-exacto ');
    putenv('MERCADO_PAGO_PUBLIC_KEY= public-key-exacta ');
    putenv('MERCADO_PAGO_WEBHOOK_SECRET= firma-exacta ');
    putenv('MERCADO_PAGO_SUCCESS_URL=https://aquellaslunas.com.ar/astro/tienda/pago-exitoso.php');
    putenv('MERCADO_PAGO_PENDING_URL=https://aquellaslunas.com.ar/astro/tienda/pago-pendiente.php');
    putenv('MERCADO_PAGO_FAILURE_URL=https://aquellaslunas.com.ar/astro/tienda/pago-fallido.php');
    putenv('MERCADO_PAGO_NOTIFICATION_URL=https://aquellaslunas.com.ar/astro/webhooks/mercado-pago.php');
    $config = loadMercadoPagoConfig('/missing-production-config.php');
    mercadoPagoConfigAssert($config['mode'] === 'test', 'El modo válido no fue cargado.');
    mercadoPagoConfigAssert($config['access_token'] === ' token-exacto ', 'El access token fue modificado.');
    mercadoPagoConfigAssert($config['public_key'] === ' public-key-exacta ', 'La public key fue modificada.');
    mercadoPagoConfigAssert($config['webhook_secret'] === ' firma-exacta ', 'El secreto webhook fue modificado.');
    mercadoPagoConfigAssert($config['_sources']['webhook_secret'] === 'environment', 'No se identificó el origen del secreto de entorno.');

    putenv('MERCADO_PAGO_WEBHOOK_SECRET=');
    $testWithoutWebhookSecret = loadMercadoPagoConfig('/missing-production-config.php');
    mercadoPagoConfigAssert($testWithoutWebhookSecret['mode'] === 'test', 'El modo test cambió al omitir el secreto webhook.');
    mercadoPagoConfigAssert($testWithoutWebhookSecret['webhook_secret'] === '', 'El secreto webhook vacío de test no fue preservado.');
    putenv('MERCADO_PAGO_WEBHOOK_SECRET= firma-exacta ');

    foreach ([
        ['MERCADO_PAGO_MODE', 'sandbox'],
        ['MERCADO_PAGO_SUCCESS_URL', 'http://aquellaslunas.com.ar/retorno'],
        ['MERCADO_PAGO_ACCESS_TOKEN', '   '],
    ] as [$name, $invalidValue]) {
        $validValue = getenv($name);
        putenv($name . '=' . $invalidValue);
        try {
            loadMercadoPagoConfig('/missing-production-config.php');
            throw new RuntimeException('Se aceptó configuración inválida de Mercado Pago.');
        } catch (RuntimeException $exception) {
            mercadoPagoConfigAssert($exception->getMessage() === 'La configuración de Mercado Pago no está disponible.', 'El error de Mercado Pago filtró detalles.');
        }
        putenv($name . '=' . $validValue);
    }

    foreach ($names as $name) {
        putenv($name);
    }
    $temporaryConfigPath = tempnam(sys_get_temp_dir(), 'mercado-pago-config-');
    mercadoPagoConfigAssert(is_string($temporaryConfigPath), 'No se pudo crear la configuración externa temporal.');
    $externalConfig = [
        'mercado_pago_mode' => 'production',
        'mercado_pago_access_token' => 'external-token',
        'mercado_pago_public_key' => 'external-public-key',
        'mercado_pago_webhook_secret' => 'external-signature',
        'mercado_pago_success_url' => 'https://aquellaslunas.com.ar/astro/tienda/pago-exitoso.php',
        'mercado_pago_pending_url' => 'https://aquellaslunas.com.ar/astro/tienda/pago-pendiente.php',
        'mercado_pago_failure_url' => 'https://aquellaslunas.com.ar/astro/tienda/pago-fallido.php',
        'mercado_pago_notification_url' => 'https://aquellaslunas.com.ar/astro/webhooks/mercado-pago.php',
    ];
    file_put_contents($temporaryConfigPath, "<?php\nreturn " . var_export($externalConfig, true) . ";\n");
    $external = loadMercadoPagoConfig($temporaryConfigPath);
    mercadoPagoConfigAssert($external['_sources']['webhook_secret'] === 'private_config', 'No se identificó el origen del secreto privado.');
    mercadoPagoConfigAssert($external['mode'] === 'production' && $external['access_token'] === 'external-token', 'No se cargaron las claves externas equivalentes.');

    $externalConfig['mercado_pago_webhook_secret'] = '';
    file_put_contents($temporaryConfigPath, "<?php\nreturn " . var_export($externalConfig, true) . ";\n");
    try {
        loadMercadoPagoConfig($temporaryConfigPath);
        throw new RuntimeException('Se aceptó producción sin secreto webhook.');
    } catch (RuntimeException $exception) {
        mercadoPagoConfigAssert($exception->getMessage() === 'La configuración de Mercado Pago no está disponible.', 'El error de secreto webhook filtró detalles.');
    }
    fwrite(STDOUT, "mercado pago config tests: ok\n");
} finally {
    if (is_string($temporaryConfigPath) && is_file($temporaryConfigPath)) {
        unlink($temporaryConfigPath);
    }
    foreach ($previous as $name => $value) {
        is_string($value) ? putenv($name . '=' . $value) : putenv($name);
    }
}
