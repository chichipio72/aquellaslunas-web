<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/lunar-embeds.php';
require_once __DIR__ . '/../includes/full-moon-size-embed.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendDynamicNoCacheHeaders();
$location = astronomyLocationContext();
$timezone = new DateTimeZone((string) $location['timezone']);
$options = fullMoonSizeEmbedOptions($_GET, $timezone, get_current_datetime($timezone->getName()));
$moons = null;
try {
    $moons = nextFullMoonSizes($options['reference'], $location);
} catch (Throwable $exception) {
    error_log('Aquellas Lunas full moon size embed error: ' . $exception->getMessage());
}
$html = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$months = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$moonImage = '../assets/images/moon-phases-large/moon_100_waxing_north.png';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Próximas lunas llenas · Comparación de tamaño</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <link rel="stylesheet" href="<?= $html('../' . versionedAssetUrl('assets/css/lunar-widgets.css')) ?>">
</head>
<body class="lunar-widget lunar-widget--full-moon-sizes">
<main class="lunar-widget__frame full-moon-sizes" data-full-moon-sizes>
    <header class="full-moon-sizes__header">
        <div><strong>Próximas 12 lunas llenas</strong><span>100 % = diámetro aparente a la distancia lunar media (384.400 km)</span></div>
        <?php if ($options['controls']): ?>
            <form method="get" class="full-moon-sizes__controls">
                <label><span>Desde</span><input type="date" name="date" value="<?= $html($options['reference']->format('Y-m-d')) ?>"></label>
                <input type="hidden" name="controls" value="1">
                <button type="submit">Actualizar</button>
            </form>
        <?php endif; ?>
    </header>
    <?php if ($moons !== null): ?>
        <ol class="full-moon-sizes__grid">
            <?php foreach ($moons as $moon): $date = new DateTimeImmutable($moon['date']); ?>
                <li class="full-moon-sizes__item">
                    <div class="full-moon-sizes__reference" aria-hidden="true">
                        <div class="full-moon-sizes__disk" style="--size-scale: <?= $html(number_format($moon['size_percent'] / 100, 6, '.', '')) ?>">
                            <img src="<?= $html($moonImage) ?>" alt="" loading="eager">
                        </div>
                    </div>
                    <time datetime="<?= $html($moon['datetime']) ?>"><?= $html($date->format('j') . ' ' . $months[(int) $date->format('n')]) ?></time>
                    <strong><?= $html(number_format($moon['size_percent'], 1, ',', '.')) ?> %</strong>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php else: ?>
        <p class="lunar-widget__error" role="alert">No se pudo preparar la comparación de lunas llenas.</p>
    <?php endif; ?>
</main>
</body>
</html>
