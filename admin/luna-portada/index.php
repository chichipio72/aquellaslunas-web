<?php

declare(strict_types=1);

define('STORE_ADMIN_LOGIN_PATH', '../login.php');
require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/store-admin-navigation.php';
require_once __DIR__ . '/../../includes/api-client.php';
require_once __DIR__ . '/../../includes/location-context.php';
require_once __DIR__ . '/../../includes/moon-three-render.php';
require_once __DIR__ . '/../../includes/asset-url.php';
require_once __DIR__ . '/../../includes/favicon-links.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();

function moonAdminHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$savedScope = is_string($_GET['saved'] ?? null) ? $_GET['saved'] : '';
$notice = match ($savedScope) {
    'home' => 'Los parámetros de la Luna de portada se guardaron correctamente.',
    'favorite' => 'Los parámetros de La Luna de tu fecha favorita se guardaron correctamente.',
    'interactive' => 'Los parámetros de la Luna interactiva se guardaron correctamente.',
    default => '',
};
$connection = null;
try {
    $connection = getWebDatabaseConnection();
    moonThreeRenderConfigurationInitialize($connection);
    favoriteMoonThreeRenderConfigurationInitialize($connection);
    interactiveMoonThreeRenderConfigurationInitialize($connection);
} catch (Throwable $exception) {
    error_log('Aquellas Lunas moon admin initialization error: ' . $exception->getMessage());
    $errors[] = 'No se pudo preparar la configuración de la Luna.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$connection instanceof PDO) {
        $errors[] = 'No hay conexión disponible para guardar cambios.';
    } elseif (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión expiró o el token CSRF no es válido. Recargá la página.';
    } else {
        try {
            $settings = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];
            $scope = (string) ($_POST['scope'] ?? '');
            if ($scope === 'home') moonThreeRenderConfigurationUpdate($connection, $settings);
            elseif ($scope === 'favorite') favoriteMoonThreeRenderConfigurationUpdate($connection, $settings);
            elseif ($scope === 'interactive') interactiveMoonThreeRenderConfigurationUpdate($connection, $settings);
            else throw new InvalidArgumentException('Sección de configuración lunar inválida.');
            header('Location: index.php?saved=' . rawurlencode($scope), true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            $errors[] = 'Revisá los valores: hay un parámetro ausente, inválido o fuera de rango.';
        } catch (Throwable $exception) {
            error_log('Aquellas Lunas moon admin save error: ' . $exception->getMessage());
            $errors[] = 'No se pudieron guardar los parámetros de la Luna.';
        }
    }
}

$connectionFactory = $connection instanceof PDO ? static fn(): PDO => $connection : null;
$values = moonThreeRenderConfigurationLoad($connectionFactory);
$catalog = moonThreeRenderConfigurationCatalog();
$favoriteValues = favoriteMoonThreeRenderConfigurationLoad($connectionFactory);
$favoriteCatalog = favoriteMoonThreeRenderConfigurationCatalog();
$interactiveValues = interactiveMoonThreeRenderConfigurationLoad($connectionFactory);
$interactiveCatalog = interactiveMoonThreeRenderConfigurationCatalog();
$previewPayloads = ['home' => null, 'favorite' => null, 'interactive' => null];
$favoritePreviewOffset = filter_var($_GET['preview_offset'] ?? 0, FILTER_VALIDATE_INT);
$favoritePreviewOffset = is_int($favoritePreviewOffset) ? max(-15, min(15, $favoritePreviewOffset)) : 0;
$favoritePreviewInstant = null;
try {
    $previewLocation = astronomyLocationContext();
    $favoritePreviewInstant = (new DateTimeImmutable('now', new DateTimeZone((string) $previewLocation['timezone'])))
        ->modify(($favoritePreviewOffset >= 0 ? '+' : '') . $favoritePreviewOffset . ' days');
    $previewObserver = new AstronomyEngine\Facade\AstronomyObserver(
            (float) $previewLocation['latitude'],
            (float) $previewLocation['longitude'],
            (string) $previewLocation['timezone'],
            (float) ($previewLocation['elevation_meters'] ?? 0.0),
    );
    $previewPayloads['home'] = moonThreeRenderPayload($favoritePreviewInstant, $previewObserver, $values, 'home.moon_three.');
    $previewPayloads['favorite'] = moonThreeRenderPayload($favoritePreviewInstant, $previewObserver, $favoriteValues, 'favorite.moon_three.');
    $previewPayloads['interactive'] = moonThreeRenderPayload($favoritePreviewInstant, $previewObserver, $interactiveValues, 'interactive.moon_three.');
    foreach ($previewPayloads as &$previewPayload) {
        foreach (['albedo', 'relief', 'relief_high'] as $textureKey) {
            if (is_string($previewPayload['textures'][$textureKey] ?? null)) {
                $previewPayload['textures'][$textureKey] = '../../' . ltrim($previewPayload['textures'][$textureKey], '/');
            }
        }
    }
    unset($previewPayload);
} catch (Throwable $exception) {
    error_log('Aquellas Lunas favorite Moon admin preview error: ' . $exception->getMessage());
}
if (($_GET['preview_json'] ?? '') === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    if ($previewPayloads['favorite'] === null || $favoritePreviewInstant === null) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo calcular la vista previa.']);
    } else {
        echo json_encode([
            'ok' => true,
            'offset' => $favoritePreviewOffset,
            'datetime' => $favoritePreviewInstant->format(DateTimeInterface::ATOM),
            'date_label' => $favoritePreviewInstant->format('d/m/Y H:i'),
            'payload' => $previewPayloads['favorite'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/** @param array<string,array<string,mixed>> $fieldCatalog @param array<string,float|string> $fieldValues */
function renderMoonAdminFields(array $fieldCatalog, array $fieldValues): void
{
    foreach ($fieldCatalog as $key => $definition) {
        ?><label class="moon-render-admin__field">
            <span><?= moonAdminHtml($definition['label']) ?></span>
            <?php if (($definition['type'] ?? '') === 'enum'): ?>
                <select name="settings[<?= moonAdminHtml($key) ?>]" data-moon-setting-key="<?= moonAdminHtml($key) ?>">
                    <?php foreach ($definition['options'] as $option => $label): ?><option value="<?= moonAdminHtml($option) ?>"<?= ($fieldValues[$key] ?? null) === $option ? ' selected' : '' ?>><?= moonAdminHtml($label) ?></option><?php endforeach; ?>
                </select>
            <?php elseif (($definition['type'] ?? '') === 'color'): ?>
                <input type="color" name="settings[<?= moonAdminHtml($key) ?>]" value="<?= moonAdminHtml($fieldValues[$key] ?? $definition['default']) ?>" data-moon-setting-key="<?= moonAdminHtml($key) ?>" required>
            <?php else: ?>
                <input type="number" name="settings[<?= moonAdminHtml($key) ?>]" value="<?= moonAdminHtml($fieldValues[$key] ?? $definition['default']) ?>" min="<?= moonAdminHtml($definition['min']) ?>" max="<?= moonAdminHtml($definition['max']) ?>" step="<?= moonAdminHtml($definition['step']) ?>" data-moon-setting-key="<?= moonAdminHtml($key) ?>" required>
                <small>Rango <?= moonAdminHtml($definition['min']) ?>–<?= moonAdminHtml($definition['max']) ?></small>
            <?php endif; ?>
        </label><?php
    }
}
$selectedScope = is_string($_GET['view'] ?? null) ? $_GET['view'] : ($savedScope !== '' ? $savedScope : 'home');
if (!in_array($selectedScope, ['home', 'favorite', 'interactive'], true)) $selectedScope = 'home';
$adminReliefTextures = [];
foreach ([
    'low' => ['bump' => 'assets/images/moon-three/ldem_3_8bit.jpg', 'normal' => 'assets/images/moon-three/ldem_3_normal.png'],
    'medium' => ['bump' => 'assets/images/moon-three/ldem_4_height.png', 'normal' => 'assets/images/moon-three/ldem_4_normal.png'],
    'high' => ['bump' => 'assets/images/moon-three/ldem_8_height.png', 'normal' => 'assets/images/moon-three/ldem_8_normal.png'],
] as $resolution => $modes) {
    foreach ($modes as $mode => $path) $adminReliefTextures[$resolution][$mode] = '../../' . versionedAssetUrl($path);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Luna de portada · Aquellas Lunas</title>
    <?php renderFaviconLinks('../../'); ?>
    <link rel="stylesheet" href="<?= moonAdminHtml('../../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <?php if ($previewPayloads['home'] !== null): ?><script type="module" src="<?= moonAdminHtml('../../' . versionedAssetUrl('assets/js/moon-three-render.js')) ?>"></script><?php endif; ?>
</head>
<body class="store-admin">
    <?php renderStoreAdminNavigation('home_moon', 'Luna de portada'); ?>
    <main class="store-admin-main">
        <section class="card moon-render-admin moon-render-admin__chooser">
            <h2>Configuración de lunas Three.js</h2>
            <p>Elegí una Luna para editar. Cada configuración es independiente y la vista previa responde antes de guardar.</p>
            <?php if ($notice !== ''): ?><p class="status-info" role="status"><?= moonAdminHtml($notice) ?></p><?php endif; ?>
            <?php if ($errors !== []): ?><div class="api-error-notice" role="alert"><p>No se pudo guardar la configuración.</p><ul><?php foreach ($errors as $error): ?><li><?= moonAdminHtml($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <label class="moon-render-admin__selector"><span>Configurar</span><select data-moon-admin-selector>
                <option value="home"<?= $selectedScope === 'home' ? ' selected' : '' ?>>Luna de la portada</option>
                <option value="favorite"<?= $selectedScope === 'favorite' ? ' selected' : '' ?>>La Luna de tu fecha favorita</option>
                <option value="interactive"<?= $selectedScope === 'interactive' ? ' selected' : '' ?>>Luna interactiva</option>
            </select></label>
        </section>

        <section class="card moon-render-admin moon-render-admin--editable" data-moon-admin-panel="home"<?= $selectedScope !== 'home' ? ' hidden' : '' ?>>
            <h2>Luna de la portada</h2>
            <p>Estos valores afectan únicamente la Luna grande de “El cielo hoy” en la portada.</p>
            <?php if (is_array($previewPayloads['home'])): ?><div class="moon-render-admin__preview-block"><h3>Vista previa</h3><p>Los cambios se muestran inmediatamente y no se guardan hasta confirmar.</p><div id="moon-admin-preview-home" class="moon-render-admin__preview" data-moon-three data-moon-admin-preview data-moon-three-state="loading" role="img" aria-label="Vista previa de la Luna de portada"><script type="application/json" data-moon-three-payload><?= json_encode($previewPayloads['home'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script><span data-moon-three-loading>Cargando vista previa…</span></div></div><?php endif; ?>
            <form method="post" class="moon-render-admin__form" data-moon-settings-form data-moon-scope="home" data-moon-preview-target="moon-admin-preview-home">
                <input type="hidden" name="csrf_token" value="<?= moonAdminHtml(storeAdminCsrfToken()) ?>">
                <input type="hidden" name="scope" value="home">
                <div class="moon-render-admin__grid">
                    <?php renderMoonAdminFields($catalog, $values); ?>
                </div>
                <div class="astronomy-sources-actions">
                    <button type="submit" class="button button-primary">Guardar parámetros</button>
                    <a class="button compact-secondary-button" href="../../">Ver portada</a>
                </div>
            </form>
        </section>

        <section class="card moon-render-admin moon-render-admin--editable" data-moon-admin-panel="favorite"<?= $selectedScope !== 'favorite' ? ' hidden' : '' ?>>
            <h2>La Luna de tu fecha favorita</h2>
            <p>Configuración independiente para la vista pública y sus fondos descargables. Los valores iniciales se copiaron de la Luna de portada.</p>
            <?php if (is_array($previewPayloads['favorite'])): ?>
                <div class="moon-render-admin__preview-block">
                    <h3>Vista previa</h3>
                    <p>Usa la configuración guardada, la ubicación actual y la misma hora del día seleccionado.</p>
                    <form method="get" class="moon-render-admin__preview-date" data-moon-preview-date-form>
                        <label for="moon-preview-offset">Simular fase entre −15 y +15 días</label>
                        <input id="moon-preview-offset" type="range" name="preview_offset" min="-15" max="15" step="1" value="<?= $favoritePreviewOffset ?>" data-moon-preview-offset data-base-date="<?= moonAdminHtml((new DateTimeImmutable('now', $favoritePreviewInstant?->getTimezone() ?? new DateTimeZone('UTC')))->format('Y-m-d')) ?>">
                        <div>
                            <output for="moon-preview-offset" data-moon-preview-offset-output><?= $favoritePreviewOffset === 0 ? 'Hoy' : ($favoritePreviewOffset > 0 ? '+' : '') . $favoritePreviewOffset . ' días' ?></output>
                            <?php if ($favoritePreviewInstant !== null): ?><time datetime="<?= moonAdminHtml($favoritePreviewInstant->format(DateTimeInterface::ATOM)) ?>" data-moon-preview-date-output><?= moonAdminHtml($favoritePreviewInstant->format('d/m/Y H:i')) ?></time><?php endif; ?>
                        </div>
                    </form>
                    <div id="moon-admin-preview-favorite" class="moon-render-admin__preview" data-moon-three data-moon-admin-preview data-moon-wallpaper-preview data-moon-three-state="loading" role="img" aria-label="Vista previa de La Luna de tu fecha favorita">
                        <script type="application/json" data-moon-three-payload><?= json_encode($previewPayloads['favorite'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                        <span data-moon-three-loading>Cargando vista previa…</span>
                    </div>
                </div>
            <?php endif; ?>
            <form method="post" class="moon-render-admin__form" data-moon-settings-form data-moon-scope="favorite" data-moon-preview-target="moon-admin-preview-favorite">
                <input type="hidden" name="csrf_token" value="<?= moonAdminHtml(storeAdminCsrfToken()) ?>">
                <input type="hidden" name="scope" value="favorite">
                <div class="moon-render-admin__grid">
                    <?php renderMoonAdminFields($favoriteCatalog, $favoriteValues); ?>
                </div>
                <div class="astronomy-sources-actions">
                    <button type="submit" class="button button-primary">Guardar Luna de fecha favorita</button>
                    <a class="button compact-secondary-button" href="../../luna-fecha-favorita.php">Ver sección pública</a>
                </div>
            </form>
        </section>

        <section class="card moon-render-admin moon-render-admin--editable" data-moon-admin-panel="interactive"<?= $selectedScope !== 'interactive' ? ' hidden' : '' ?>>
            <h2>Luna interactiva</h2>
            <p>Configuración independiente para la herramienta pública y su widget embebible. El zoom del visitante se aplica sobre este tamaño base.</p>
            <?php if (is_array($previewPayloads['interactive'])): ?><div class="moon-render-admin__preview-block"><h3>Vista previa</h3><p>Representa el aspecto base; el zoom del visitante se suma a este tamaño.</p><div id="moon-admin-preview-interactive" class="moon-render-admin__preview" data-moon-three data-moon-admin-preview data-moon-three-state="loading" role="img" aria-label="Vista previa de la Luna interactiva"><script type="application/json" data-moon-three-payload><?= json_encode($previewPayloads['interactive'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script><span data-moon-three-loading>Cargando vista previa…</span></div></div><?php endif; ?>
            <form method="post" class="moon-render-admin__form" data-moon-settings-form data-moon-scope="interactive" data-moon-preview-target="moon-admin-preview-interactive">
                <input type="hidden" name="csrf_token" value="<?= moonAdminHtml(storeAdminCsrfToken()) ?>">
                <input type="hidden" name="scope" value="interactive">
                <div class="moon-render-admin__grid">
                    <?php renderMoonAdminFields($interactiveCatalog, $interactiveValues); ?>
                </div>
                <div class="astronomy-sources-actions">
                    <button type="submit" class="button button-primary">Guardar Luna interactiva</button>
                    <a class="button compact-secondary-button" href="../../luna-interactiva.php">Ver Luna interactiva</a>
                </div>
            </form>
        </section>
    </main>
    <script>
    const moonReliefTextures = <?= json_encode($adminReliefTextures, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) ?>;
    const moonPreviewStates = new Map();
    const clonePayload = (payload) => typeof structuredClone === 'function'
        ? structuredClone(payload)
        : JSON.parse(JSON.stringify(payload));

    const refreshMoonSettingsPreview = (form) => {
        const preview = document.getElementById(form.dataset.moonPreviewTarget || '');
        const state = moonPreviewStates.get(form);
        if (!preview || !state?.base) return;
        const payload = clonePayload(state.base);
        const prefix = `${form.dataset.moonScope}.moon_three.`;
        let resolution = 'medium';
        form.querySelectorAll('[data-moon-setting-key]').forEach((control) => {
            const suffix = control.dataset.moonSettingKey.replace(prefix, '');
            const value = control.type === 'number' ? Number(control.value) : control.value;
            if (suffix === 'dem_resolution') resolution = String(value);
            else if (suffix.startsWith('background_') || suffix.startsWith('glow_')) payload.wallpaper[suffix] = value;
            else if (suffix.startsWith('high_res_')) {
                const highKey = suffix.replace('high_res_', '').replace('relief_enabled', 'enabled').replace('zoom_threshold', 'zoom_threshold');
                payload.appearance.high_resolution = payload.appearance.high_resolution || {};
                payload.appearance.high_resolution[highKey] = value;
            }
            else payload.appearance[suffix] = value;
        });
        const reliefMode = String(payload.appearance.relief_mode || 'none');
        payload.textures.relief = reliefMode === 'none' ? null : moonReliefTextures[resolution]?.[reliefMode] || null;
        if (form.dataset.moonScope === 'interactive' && reliefMode === 'normal'
            && payload.appearance.high_resolution?.enabled === 'on' && payload.appearance.high_resolution?.mode === 'high') {
            payload.textures.relief = payload.textures.relief_high;
            payload.appearance.normal_scale_x = payload.appearance.high_resolution.normal_scale_x;
            payload.appearance.normal_scale_y = payload.appearance.high_resolution.normal_scale_y;
        }
        preview.dispatchEvent(new CustomEvent('moon-three-update', { detail: payload }));
    };

    document.querySelectorAll('[data-moon-settings-form]').forEach((form) => {
        const preview = document.getElementById(form.dataset.moonPreviewTarget || '');
        const payloadElement = preview?.querySelector('[data-moon-three-payload]');
        if (!payloadElement) return;
        try { moonPreviewStates.set(form, { base: JSON.parse(payloadElement.textContent || '') }); } catch (error) { return; }
        form.addEventListener('input', () => refreshMoonSettingsPreview(form));
        form.addEventListener('change', () => refreshMoonSettingsPreview(form));
    });

    const moonSelector = document.querySelector('[data-moon-admin-selector]');
    const selectMoonPanel = () => {
        const selected = moonSelector?.value || 'home';
        document.querySelectorAll('[data-moon-admin-panel]').forEach((panel) => { panel.hidden = panel.dataset.moonAdminPanel !== selected; });
        const url = new URL(window.location.href);
        url.searchParams.set('view', selected);
        url.searchParams.delete('saved');
        history.replaceState(null, '', url);
    };
    moonSelector?.addEventListener('change', selectMoonPanel);

    document.querySelectorAll('[data-moon-preview-date-form]').forEach((form) => {
        const slider = form.querySelector('[data-moon-preview-offset]');
        const offsetOutput = form.querySelector('[data-moon-preview-offset-output]');
        const dateOutput = form.querySelector('[data-moon-preview-date-output]');
        if (!slider || !offsetOutput) return;
        const refreshLabels = () => {
            const offset = Number(slider.value);
            offsetOutput.value = offset === 0 ? 'Hoy' : `${offset > 0 ? '+' : ''}${offset} días`;
            const match = slider.dataset.baseDate?.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (dateOutput && match) {
                const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
                date.setUTCDate(date.getUTCDate() + offset);
                dateOutput.textContent = new Intl.DateTimeFormat('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' }).format(date);
            }
        };
        slider.addEventListener('input', refreshLabels);
        let requestController = null;
        slider.addEventListener('change', async () => {
            requestController?.abort();
            requestController = new AbortController();
            slider.disabled = true;
            form.classList.add('is-loading');
            try {
                const url = new URL(window.location.href);
                url.search = '';
                url.searchParams.set('preview_json', '1');
                url.searchParams.set('preview_offset', slider.value);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: requestController.signal });
                const result = await response.json();
                if (!response.ok || result.ok !== true || !result.payload) throw new Error(result.error || 'Respuesta inválida');
                const settingsForm = document.querySelector('[data-moon-settings-form][data-moon-scope="favorite"]');
                if (settingsForm) {
                    moonPreviewStates.set(settingsForm, { base: result.payload });
                    refreshMoonSettingsPreview(settingsForm);
                }
                if (dateOutput) {
                    dateOutput.textContent = result.date_label;
                    dateOutput.dateTime = result.datetime;
                }
                const visibleUrl = new URL(window.location.href);
                visibleUrl.searchParams.set('preview_offset', slider.value);
                visibleUrl.searchParams.delete('saved');
                history.replaceState(null, '', visibleUrl);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('No se pudo actualizar la vista previa lunar.', error);
                    offsetOutput.value = 'No se pudo actualizar';
                }
            } finally {
                slider.disabled = false;
                form.classList.remove('is-loading');
            }
        });
    });
    </script>
</body>
</html>
