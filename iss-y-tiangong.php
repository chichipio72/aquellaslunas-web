<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/api-client.php';
require_once __DIR__ . '/includes/location-context.php';
require_once __DIR__ . '/includes/current-datetime.php';
require_once __DIR__ . '/includes/site-header.php';
require_once __DIR__ . '/includes/site-footer.php';
require_once __DIR__ . '/includes/asset-url.php';
require_once __DIR__ . '/includes/favicon-links.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/satellite-stations-visualization.php';

sendDynamicNoCacheHeaders();
$location = astronomyLocationContext();
$instant = get_current_datetime((string) $location['timezone']);
$requestedStation = in_array($_GET['station'] ?? null, ['iss', 'tiangong'], true) ? (string) $_GET['station'] : 'both';
$requestedEventInstant = false;
if (is_string($_GET['time'] ?? null) && strlen((string) $_GET['time']) <= 64) {
    $requestedInstant = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string) $_GET['time']);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if ($requestedInstant instanceof DateTimeImmutable
        && (!is_array($dateErrors) || (($dateErrors['warning_count'] ?? 0) === 0 && ($dateErrors['error_count'] ?? 0) === 0))) {
        $instant = $requestedInstant;
        $requestedEventInstant = true;
    }
}
$payload = null; $error = null;
try { $payload = satelliteStationsVisualizationPayload($location, $instant); }
catch (Throwable $exception) { error_log('Aquellas Lunas stations viewer: ' . $exception->getMessage()); $error = 'No pudimos preparar las órbitas en este momento.'; }
if ($payload !== null) $payload['start_paused'] = $requestedEventInstant;
$seo = aquellasLunasSeoPage('ISS y Tiangong | Aquellas Lunas', 'Seguí en 3D las órbitas de la ISS y Tiangong alrededor de la Tierra.', '/iss-y-tiangong.php', 'website');
$html = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$passes = is_array($payload['passes'] ?? null) ? $payload['passes'] : [];
$passTime = static fn(string $value): string => (new DateTimeImmutable($value))->format('d/m · H:i');
$passAzimuth = static function (float $degrees): string {
    $directions = ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO'];
    return $directions[(int) floor(fmod($degrees + 22.5, 360.0) / 45.0)] . ' · ' . number_format($degrees, 0, ',', '.') . '°';
};
$approachLabel = static function (array $approach): string {
    $body = ($approach['body'] ?? '') === 'moon' ? 'la Luna' : 'el Sol';
    return match ($approach['classification'] ?? '') {
        'transit' => 'Tránsito sobre ' . $body,
        'very_close', 'close' => 'Muy cercano a ' . $body,
        default => 'Paso cercano a ' . $body,
    };
};
$passViewerUrl = static function (array $pass, string $instant): string {
    return astronomyInternalUrl('iss-y-tiangong.php?' . http_build_query([
        'station' => ($pass['satellite'] ?? '') === 'tiangong' ? 'tiangong' : 'iss',
        'time' => $instant,
    ], '', '&', PHP_QUERY_RFC3986));
};
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php renderSeoHead($seo); renderAnalyticsTracking(); renderFaviconLinks(); ?>
<link rel="stylesheet" href="<?= $html(versionedAssetUrl('assets/css/styles.css')) ?>"><link rel="stylesheet" href="<?= $html(versionedAssetUrl('assets/css/satellite-stations.css')) ?>">
<script src="<?= $html(versionedAssetUrl('assets/js/location.js')) ?>" defer></script><?php if ($payload): ?><script type="module" src="<?= $html(versionedAssetUrl('assets/js/satellite-stations.js')) ?>"></script><?php endif; ?>
</head><body><?php renderAstronomySiteHeader('satellite_stations', $location); ?>
<main class="page stations-main"><div class="container stations-container">
<header class="stations-heading"><p class="eyebrow">Órbitas terrestres en tiempo real</p><h1>ISS y Tiangong</h1><p>Explorá las estaciones espaciales alrededor de la Tierra y su relación visual con la Luna.</p></header>
<section class="stations-controls card" aria-label="Controles del visor">
<label><span>Estaciones</span><select data-stations-selection><option value="both"<?= $requestedStation === 'both' ? ' selected' : '' ?>>Ambas</option><option value="iss"<?= $requestedStation === 'iss' ? ' selected' : '' ?>>ISS</option><option value="tiangong"<?= $requestedStation === 'tiangong' ? ' selected' : '' ?>>Tiangong</option></select></label>
<label><span>Vista</span><select data-stations-view><option value="moon">Desde la Luna</option><option value="iss">Desde la ISS</option><option value="tiangong">Desde Tiangong</option><option value="free">Libre</option></select></label>
<label data-stations-orientation-control hidden><span>Orientación</span><select data-stations-orientation><option value="horizon">Horizonte</option><option value="down">Hacia abajo</option></select></label>
<label><span>Iluminación</span><select data-stations-lighting><option value="real">Real</option><option value="full">Toda iluminada</option></select></label>
<label><span>Órbitas</span><select data-stations-orbits><option value="show">Mostrar</option><option value="hide">Ocultar</option></select></label>
<button class="button button-primary" type="button" data-stations-play aria-pressed="<?= $requestedEventInstant ? 'true' : 'false' ?>"><?= $requestedEventInstant ? 'Reproducir' : 'Pausa' ?></button>
<label><span>Velocidad</span><select data-stations-speed><option value="1">1×</option><option value="10">10×</option><option value="60" selected>60×</option><option value="300">300×</option></select></label>
<button class="button compact-secondary-button" type="button" data-stations-now>Ahora</button>
<p class="stations-clock"><span>Simulación</span><time data-stations-clock><?= $html($instant->format('d/m/Y · H:i:s')) ?></time></p>
</section>
<section class="stations-stage card">
<?php if ($payload): ?><div class="stations-render" data-satellite-stations><script type="application/json" data-stations-payload><?= json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script><p data-stations-loading>Cargando órbitas…</p><p class="stations-help" data-stations-help>Vista alineada con la dirección Tierra–Luna</p></div>
<aside class="stations-legend"><h2>En escena</h2><ul><li><i class="is-iss"></i> ISS</li><li><i class="is-tiangong"></i> Tiangong</li><li><i class="is-observer"></i> Tu ubicación</li></ul><p>Las estaciones y el grosor de las trayectorias están exagerados para hacerlos visibles.</p><p data-band-status><?= $payload['transit_bands'] ? $html($payload['band_note']) : 'No se detectó un tránsito lunar desde tu ubicación durante las tres horas representadas.' ?></p></aside>
<?php else: ?><p class="api-error-notice" role="alert"><?= $html($error) ?></p><?php endif; ?>
</section>
<section class="stations-passes card" aria-labelledby="stations-passes-title">
<header><p class="eyebrow">Próximas 48 horas</p><h2 id="stations-passes-title">Próximos pasajes</h2><p>Pasos de ISS y Tiangong que alcanzan al menos 10° sobre el horizonte para <?= $html($location['name']) ?>.</p></header>
<?php if ($passes !== []): ?><div class="stations-passes-list">
<?php foreach ($passes as $pass): ?><article class="stations-pass stations-pass--<?= $html($pass['satellite']) ?>">
<div class="stations-pass-name"><i aria-hidden="true"></i><h3><?= $pass['satellite'] === 'iss' ? 'ISS' : 'Tiangong' ?></h3><strong>Altura máx. <?= number_format((float) $pass['maximum_altitude_degrees'], 1, ',', '.') ?>°</strong></div>
<dl><div><dt>Inicio</dt><dd><a class="stations-pass-time-link" href="<?= $html($passViewerUrl($pass, $pass['start'])) ?>" aria-label="Ver <?= $pass['satellite'] === 'iss' ? 'ISS' : 'Tiangong' ?> al inicio del pasaje"><time datetime="<?= $html($pass['start']) ?>"><?= $html($passTime($pass['start'])) ?></time></a><small><?= $html($passAzimuth((float) $pass['appearance_azimuth_degrees'])) ?></small></dd></div><div><dt>Máxima altura</dt><dd><time datetime="<?= $html($pass['maximum']) ?>"><?= $html($passTime($pass['maximum'])) ?></time></dd></div><div><dt>Fin</dt><dd><a class="stations-pass-time-link" href="<?= $html($passViewerUrl($pass, $pass['end'])) ?>" aria-label="Ver <?= $pass['satellite'] === 'iss' ? 'ISS' : 'Tiangong' ?> al final del pasaje"><time datetime="<?= $html($pass['end']) ?>"><?= $html($passTime($pass['end'])) ?></time></a><small><?= $html($passAzimuth((float) $pass['disappearance_azimuth_degrees'])) ?></small></dd></div></dl>
<?php if (($pass['approaches'] ?? []) !== []): ?><p class="stations-pass-approaches"><?php foreach ($pass['approaches'] as $approach): ?><span><?= $html($approachLabel($approach)) ?></span><?php endforeach; ?></p><?php endif; ?>
</article><?php endforeach; ?>
</div><?php else: ?><p class="stations-passes-empty">No hay pasos de al menos 10° durante las próximas 48 horas.</p><?php endif; ?>
</section>
</div></main><?php renderAstronomySiteFooter(); ?></body></html>
