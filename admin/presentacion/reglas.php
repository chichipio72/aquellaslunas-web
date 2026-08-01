<?php
declare(strict_types=1);

define('STORE_ADMIN_LOGIN_PATH', '../login.php');
require_once __DIR__ . '/../../includes/store-admin-auth.php';
require_once __DIR__ . '/../../includes/store-admin-navigation.php';
require_once __DIR__ . '/../../includes/editorial-configuration.php';
require_once __DIR__ . '/../../includes/asset-url.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();
function editorialAdminHtml(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function editorialAdminParameterTextMap(): array
{
    return [
        'home.moon.new_impossible' => 'home.new_moon.impossible_days', 'home.moon.new_thin' => 'home.new_moon.thin_days',
        'home.moon.very_low' => 'home.altitude.very_low_max', 'home.moon.low' => 'home.altitude.low_max',
        'home.moon.high' => 'home.altitude.medium_max', 'home.moon.overhead' => 'home.altitude.high_max',
        'home.moonrise.clock' => 'home.moonrise.clock_minutes', 'home.moonrise.minutes' => 'home.moonrise.soon_minutes',
        'home.moonrise.imminent' => 'home.moonrise.imminent_minutes',
        'event.conjunction.very_close' => 'event.conjunction.very_close_degrees', 'event.conjunction.close' => 'event.conjunction.close_degrees',
        'event.libration.strong' => 'event.libration.strong_degrees', 'event.supermoon.title' => 'event.supermoon.min_percent',
        'today.moon.sets_soon' => 'today.visibility.soon_minutes', 'today.venus_belt.message' => 'today.venus_belt.min_illumination_percent',
        'tonight.moment.dusk' => 'tonight.dusk_max_ratio', 'tonight.moment.dawn' => 'tonight.dawn_min_ratio',
        'tonight.visible.until_dawn' => 'tonight.dawn_tolerance_minutes', 'tonight.visible.long_until_time' => 'tonight.long_remaining_minutes',
        'tonight.future.long' => 'tonight.long_window_minutes', 'cloud.clear' => 'cloud.clear_max_percent',
        'cloud.some' => 'cloud.some_max_percent', 'cloud.mostly' => 'cloud.mostly_max_percent',
    ];
}

$errors = [];
$notice = isset($_GET['saved']) ? 'Las reglas y mensajes se guardaron correctamente.' : (isset($_GET['restored']) ? 'El bloque recuperó sus valores predeterminados.' : '');
$catalog = astronomyEditorialCatalog();
try {
    $connection = getWebDatabaseConnection();
    astronomyEditorialInitialize($connection);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('La sesión expiró o el token CSRF no es válido. Recargá la página.');
        if (isset($_POST['restore_block'])) {
            astronomyEditorialRestoreBlock($connection, (string) $_POST['restore_block']);
            header('Location: reglas.php?restored=1', true, 303); exit;
        }
        $parameters = is_array($_POST['parameters'] ?? null) ? $_POST['parameters'] : [];
        $texts = is_array($_POST['texts'] ?? null) ? $_POST['texts'] : [];
        $definitions = astronomyEditorialDefinitions();
        if (array_diff(array_keys($parameters), array_keys($definitions['parameters'])) !== [] || array_diff(array_keys($texts), array_keys($definitions['texts'])) !== []) throw new InvalidArgumentException('Se recibió una clave editorial desconocida.');
        if (count($parameters) !== count($definitions['parameters']) || count($texts) !== count($definitions['texts'])) throw new InvalidArgumentException('Faltan valores editoriales obligatorios.');
        astronomyEditorialSave($connection, $parameters, $texts);
        header('Location: reglas.php?saved=1', true, 303); exit;
    }
} catch (InvalidArgumentException $exception) { $errors[] = $exception->getMessage(); }
catch (Throwable $exception) { error_log('Aquellas Lunas editorial admin error: ' . $exception->getMessage()); $connection = null; $errors[] = 'No se pudo preparar la configuración editorial.'; }
$previewValues = ['objeto' => 'Venus', 'hora' => '20:15', 'minutos' => '12 minutos', 'direccion' => 'este', 'altura' => '24°', 'inicio' => '20:30', 'fin' => '05:42', 'separacion' => '1,2°', 'iluminacion' => '8%', 'parte_dia' => 'la tarde'];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Reglas y mensajes · Aquellas Lunas</title><link rel="stylesheet" href="<?= editorialAdminHtml('../../' . versionedAssetUrl('assets/css/styles.css')) ?>"><link rel="stylesheet" href="<?= editorialAdminHtml('../../' . versionedAssetUrl('assets/css/admin-presentation.css')) ?>"></head>
<body class="store-admin"><?php renderStoreAdminNavigation('presentation', 'Presentación del sitio'); ?><main class="store-admin-main presentation-admin">
<section class="card presentation-admin__intro"><p class="eyebrow">PRESENTACIÓN EDITORIAL</p><h2>Reglas y mensajes</h2><p>Editá cómo la web interpreta y cuenta datos ya calculados. Las condiciones, unidades y placeholders disponibles están controlados por el sistema.</p><nav class="presentation-admin__areas" aria-label="Áreas de presentación"><a href="index.php">Tipos de eventos</a><a aria-current="page" href="reglas.php">Reglas y mensajes</a></nav></section>
<?php if ($notice !== ''): ?><p class="status-info presentation-admin__status" role="status"><?= editorialAdminHtml($notice) ?></p><?php endif; ?>
<?php if ($errors !== []): ?><section class="api-error-notice presentation-admin__status" role="alert"><h2>No se pudo completar la operación</h2><ul><?php foreach ($errors as $error): ?><li><?= editorialAdminHtml($error) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php if ($connection instanceof PDO): ?><form method="post" class="presentation-admin__form"><input type="hidden" name="csrf_token" value="<?= editorialAdminHtml(storeAdminCsrfToken()) ?>">
<?php foreach ($catalog as $blockKey => $block): ?><section class="presentation-category editorial-rule-block" aria-labelledby="rule-<?= editorialAdminHtml($blockKey) ?>"><p class="eyebrow"><?= editorialAdminHtml($block['context']) ?></p><h3 id="rule-<?= editorialAdminHtml($blockKey) ?>"><?= editorialAdminHtml($block['label']) ?></h3>
<?php if (($block['precedence'] ?? false) === true): ?><p class="presentation-admin__tip">Se evalúan de arriba hacia abajo. Cuando una regla coincide, las siguientes no se revisan. Este orden es fijo para proteger la lógica conocida.</p><?php endif; ?><div class="editorial-fields">
<?php $pairedParameters = []; $parameterTextMap = editorialAdminParameterTextMap(); ?>
<?php foreach ($block['texts'] ?? [] as $textKey => $textDefinition): $allowed = $textDefinition['allowed'] ?? []; $textState = astronomyEditorialValueState('texts', $textKey); $parameterKey = $parameterTextMap[$textKey] ?? null; $parameterDefinition = is_string($parameterKey) ? ($block['parameters'][$parameterKey] ?? null) : null; $parameterState = is_array($parameterDefinition) ? astronomyEditorialValueState('parameters', $parameterKey) : null; $ruleModified = $textState['modified'] || ($parameterState['modified'] ?? false); if (is_array($parameterDefinition)) $pairedParameters[$parameterKey] = true; ?>
<article class="editorial-rule" data-editorial-origin="<?= $ruleModified ? 'modified' : 'default' ?>"><header><h4><?= editorialAdminHtml($textDefinition['label']) ?></h4><span class="editorial-origin<?= $ruleModified ? ' is-modified' : '' ?>"><?= $ruleModified ? 'Modificado' : 'Predeterminado' ?></span></header>
<?php if (is_array($parameterDefinition)): ?><label class="editorial-field editorial-field--threshold"><span><?= editorialAdminHtml($parameterDefinition['label']) ?></span><span class="editorial-field__control"><input type="number" name="parameters[<?= editorialAdminHtml($parameterKey) ?>]" value="<?= editorialAdminHtml($parameterState['value']) ?>" min="<?= editorialAdminHtml($parameterDefinition['min']) ?>" max="<?= editorialAdminHtml($parameterDefinition['max']) ?>" step="<?= editorialAdminHtml($parameterDefinition['step']) ?>" required><small><?= editorialAdminHtml($parameterDefinition['unit']) ?> · <?= editorialAdminHtml($parameterState['status']) ?></small></span></label><?php endif; ?>
<label class="editorial-field editorial-field--text"><span>Mensaje</span><textarea name="texts[<?= editorialAdminHtml($textKey) ?>]" maxlength="1000" rows="2" required><?= editorialAdminHtml($textState['value']) ?></textarea><?php if ($allowed !== []): ?><small>Podés usar: <?= editorialAdminHtml(implode(', ', array_map(static fn($name) => '{' . $name . '}', $allowed))) ?></small><?php endif; ?><span class="editorial-preview"><strong>Vista previa:</strong> <?= editorialAdminHtml(astronomyEditorialText($textKey, $previewValues)) ?></span></label></article>
<?php endforeach; ?>
<?php foreach ($block['parameters'] ?? [] as $parameterKey => $parameterDefinition): if (isset($pairedParameters[$parameterKey])) continue; $parameterState = astronomyEditorialValueState('parameters', $parameterKey); ?><article class="editorial-rule editorial-rule--parameter" data-editorial-origin="<?= $parameterState['modified'] ? 'modified' : 'default' ?>"><header><h4><?= editorialAdminHtml($parameterDefinition['label']) ?></h4><span class="editorial-origin<?= $parameterState['modified'] ? ' is-modified' : '' ?>"><?= editorialAdminHtml($parameterState['status']) ?></span></header><label class="editorial-field editorial-field--threshold"><span>Valor efectivo</span><span class="editorial-field__control"><input type="number" name="parameters[<?= editorialAdminHtml($parameterKey) ?>]" value="<?= editorialAdminHtml($parameterState['value']) ?>" min="<?= editorialAdminHtml($parameterDefinition['min']) ?>" max="<?= editorialAdminHtml($parameterDefinition['max']) ?>" step="<?= editorialAdminHtml($parameterDefinition['step']) ?>" required><small><?= editorialAdminHtml($parameterDefinition['unit']) ?></small></span></label></article><?php endforeach; ?>
</div><button class="button compact-secondary-button editorial-restore" type="submit" name="restore_block" value="<?= editorialAdminHtml($blockKey) ?>" formnovalidate onclick="return confirm('¿Restaurar todos los valores predeterminados de este bloque?')">Restaurar valores predeterminados</button></section><?php endforeach; ?>
<div class="presentation-admin__actions"><button class="button button-primary" type="submit">Guardar reglas y mensajes</button><a class="button compact-secondary-button" href="../index.php">Volver al panel</a></div></form><?php endif; ?>
</main></body></html>
