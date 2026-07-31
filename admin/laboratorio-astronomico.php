<?php

require_once __DIR__ . '/../includes/store-admin-auth.php';
require_once __DIR__ . '/../includes/store-admin-navigation.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/astronomy-laboratory.php';
require_once __DIR__ . '/../includes/astronomy-laboratory-extrema.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();
$today = new DateTimeImmutable('today');
$from = $today->modify('-1 year');
$fields = astronomyLaboratoryFields();
$fieldGroups = astronomyLaboratoryFieldGroups();
$phases = astronomyLaboratoryPhases();
$initialFields = ['distancia_luna_km', 'iluminacion_porc'];
$availableYears = range(ASTRONOMY_LABORATORY_MIN_YEAR, ASTRONOMY_LABORATORY_MAX_YEAR);
$extremaVariables = astronomyLaboratoryExtremaVariables();
$extremaClientVariables = array_map(
    static fn(array $variable): array => [
        'label' => $variable['label'],
        'analysisLabel' => $variable['analysis_label'],
        'body' => $variable['body'],
        'type' => $variable['type'],
        'unit' => $variable['unit'],
        'scaleGroup' => $variable['scale_group'],
        'minimumYears' => $variable['minimum_years'],
        'recommendedYears' => $variable['recommended_years'],
        'maximumLabel' => $variable['maximum_label'],
        'minimumLabel' => $variable['minimum_label'],
    ],
    $extremaVariables
);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Laboratorio astronómico · Área privada</title>
    <link rel="stylesheet" href="<?= htmlspecialchars('../' . versionedAssetUrl('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="https://cdn.jsdelivr.net/npm/echarts@6.1.0/dist/echarts.min.js" defer></script>
    <script src="<?= htmlspecialchars('../' . versionedAssetUrl('assets/js/astronomy-laboratory.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body class="store-admin">
    <?php renderStoreAdminNavigation('laboratory', 'Laboratorio astronómico'); ?>
    <main class="store-admin-main astronomy-laboratory">
        <section class="card astronomy-laboratory__controls">
            <form data-astronomy-laboratory-form>
                <fieldset class="astronomy-laboratory__analysis-mode">
                    <legend>Tipo de análisis</legend>
                    <div class="astronomy-laboratory__segmented-control">
                        <label><input type="radio" name="modo" value="diario" checked><span>Serie diaria</span></label>
                        <label><input type="radio" name="modo" value="extremos"><span>Extremos locales</span></label>
                    </div>
                </fieldset>
                <div class="astronomy-laboratory__dates">
                    <div class="astronomy-laboratory__date-group">
                        <label>Fecha desde<input type="date" name="fecha_desde" min="<?= ASTRONOMY_LABORATORY_MIN_YEAR ?>-01-01" max="<?= ASTRONOMY_LABORATORY_MAX_YEAR ?>-12-31" value="<?= $from->format('Y-m-d') ?>" required></label>
                        <label>Selector de año desde
                            <select name="anio_desde" data-year-select="fecha_desde">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?= $year ?>"<?= $year === (int) $from->format('Y') ? ' selected' : '' ?>><?= $year ?></option>
                            <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="astronomy-laboratory__date-group">
                        <label>Fecha hasta<input type="date" name="fecha_hasta" min="<?= ASTRONOMY_LABORATORY_MIN_YEAR ?>-01-01" max="<?= ASTRONOMY_LABORATORY_MAX_YEAR ?>-12-31" value="<?= $today->format('Y-m-d') ?>" required></label>
                        <label>Selector de año hasta
                            <select name="anio_hasta" data-year-select="fecha_hasta">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?= $year ?>"<?= $year === (int) $today->format('Y') ? ' selected' : '' ?>><?= $year ?></option>
                            <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="astronomy-laboratory__range-shortcuts" aria-label="Rangos rápidos" data-daily-range-shortcuts>
                    <span>Rango rápido:</span>
                    <button type="button" class="compact-secondary-button" data-range-years="1">Último año</button>
                    <button type="button" class="compact-secondary-button" data-range-years="5">Últimos 5 años</button>
                    <button type="button" class="compact-secondary-button" data-range-years="10">Últimos 10 años</button>
                    <button type="button" class="compact-secondary-button" data-range-years="20">Últimos 20 años</button>
                    <span class="astronomy-laboratory__future-ranges">
                        <button type="button" class="compact-secondary-button" data-future-range-years="1">Próximo año</button>
                        <button type="button" class="compact-secondary-button" data-future-range-years="5">Próximos 5 años</button>
                        <button type="button" class="compact-secondary-button" data-future-range-years="10">Próximos 10 años</button>
                        <button type="button" class="compact-secondary-button" data-future-range-years="20">Próximos 20 años</button>
                    </span>
                </div>
                <div class="astronomy-laboratory__range-shortcuts" aria-label="Rangos rápidos para extremos" data-extrema-range-shortcuts hidden>
                    <span>Rango rápido:</span>
                    <button type="button" class="compact-secondary-button" data-range-years="5">Últimos 5 años</button>
                    <button type="button" class="compact-secondary-button" data-range-years="10">Últimos 10 años</button>
                    <button type="button" class="compact-secondary-button" data-range-years="20">Últimos 20 años</button>
                    <button type="button" class="compact-secondary-button" data-range-all>Todo el período</button>
                    <span class="astronomy-laboratory__future-ranges">
                        <button type="button" class="compact-secondary-button" data-future-range-years="5">Próximos 5 años</button>
                        <button type="button" class="compact-secondary-button" data-future-range-years="10">Próximos 10 años</button>
                        <button type="button" class="compact-secondary-button" data-future-range-years="20">Próximos 20 años</button>
                    </span>
                </div>
                <fieldset data-analysis-daily>
                    <legend>Variables</legend>
                    <p class="astronomy-laboratory__filter-help" data-scale-group-help aria-live="polite">Podés seleccionar varias variables de hasta cuatro grupos de escala. Las variables del mismo grupo comparten eje.</p>
                    <div class="astronomy-laboratory__bodies">
                    <?php foreach (['moon' => 'Luna', 'sun' => 'Sol'] as $body => $bodyLabel): ?>
                        <section class="astronomy-laboratory__body astronomy-laboratory__body--<?= $body ?>" aria-labelledby="astronomy-laboratory-<?= $body ?>">
                            <h3 id="astronomy-laboratory-<?= $body ?>"><?= $bodyLabel ?></h3>
                            <?php foreach ($fieldGroups[$body] as $group => $groupData): ?>
                                <?php if ($groupData['fields'] === []) continue; ?>
                                <div class="astronomy-laboratory__variable-group astronomy-laboratory__variable-group--<?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?>">
                                    <h4><?= htmlspecialchars($groupData['label'], ENT_QUOTES, 'UTF-8') ?></h4>
                                    <div class="astronomy-laboratory__field-pairs">
                                    <?php foreach (astronomyLaboratoryLayoutPairs($groupData['fields']) as $pair): ?>
                                        <div class="astronomy-laboratory__field-pair">
                                        <?php foreach ($pair as $field): ?>
                                            <label><input type="checkbox" name="campos[]" value="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"<?= in_array($field, $initialFields, true) ? ' checked' : '' ?>><span><?= htmlspecialchars($groupData['fields'][$field], ENT_QUOTES, 'UTF-8') ?></span></label>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </section>
                    <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset class="astronomy-laboratory__extrema-controls" data-analysis-extrema hidden>
                    <legend>Variable para extremos locales</legend>
                    <div class="astronomy-laboratory__extrema-fields">
                        <label>Variable
                            <select name="variable_extremos">
                                <optgroup label="Luna">
                                <?php foreach ($extremaVariables as $field => $metadata): ?>
                                    <?php if ($metadata['body'] !== 'moon') continue; ?>
                                    <option value="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($metadata['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Sol">
                                <?php foreach ($extremaVariables as $field => $metadata): ?>
                                    <?php if ($metadata['body'] !== 'sun') continue; ?>
                                    <option value="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($metadata['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </label>
                        <fieldset>
                            <legend>Extremos a mostrar</legend>
                            <div class="astronomy-laboratory__segmented-control astronomy-laboratory__segmented-control--compact">
                                <label><input type="radio" name="tipo_extremo" value="maximo"><span>Máximos</span></label>
                                <label><input type="radio" name="tipo_extremo" value="minimo"><span>Mínimos</span></label>
                                <label><input type="radio" name="tipo_extremo" value="ambos" checked><span>Ambos</span></label>
                            </div>
                        </fieldset>
                    </div>
                    <p class="astronomy-laboratory__filter-help" data-extrema-range-guidance aria-live="polite"></p>
                </fieldset>
                <fieldset data-analysis-daily>
                    <legend>Fases lunares</legend>
                    <p class="astronomy-laboratory__filter-help">Sin selección se incluyen todas las fechas. Podés elegir varias fases.</p>
                    <div class="astronomy-laboratory__fields astronomy-laboratory__phases">
                    <?php foreach ($phases as $phase): ?>
                        <label><input type="checkbox" name="fases[]" value="<?= htmlspecialchars($phase, ENT_QUOTES, 'UTF-8') ?>"><span><?= htmlspecialchars($phase, ENT_QUOTES, 'UTF-8') ?></span></label>
                    <?php endforeach; ?>
                    </div>
                </fieldset>
                <button type="submit">Actualizar gráfico</button>
            </form>
        </section>
        <section class="card astronomy-laboratory__results" aria-labelledby="astronomy-laboratory-chart-title">
            <div class="astronomy-laboratory__result-heading">
                <div><h2 id="astronomy-laboratory-chart-title">Serie temporal</h2><p data-astronomy-laboratory-count>Sin datos consultados.</p></div>
                <p class="astronomy-laboratory__loading" role="status" aria-live="polite" data-astronomy-laboratory-loading hidden>Cargando datos…</p>
            </div>
            <p class="store-admin-alert" role="alert" data-astronomy-laboratory-error hidden></p>
            <dl class="astronomy-laboratory__extrema-summary" data-extrema-summary hidden></dl>
            <div class="astronomy-laboratory__chart" role="img" aria-label="Gráfico temporal de datos astronómicos" data-astronomy-laboratory-chart></div>
        </section>
    </main>
    <script>window.astronomyLaboratoryConfig=<?= json_encode([
        'endpoint' => 'api/datos-astronomicos.php',
        'labels' => $fields,
        'fieldTypes' => astronomyLaboratoryFieldTypes(array_keys($fields)),
        'fieldUnits' => astronomyLaboratoryFieldUnits(array_keys($fields)),
        'fieldScales' => astronomyLaboratoryFieldScales(array_keys($fields)),
        'scaleGroups' => astronomyLaboratoryScaleGroups(),
        'availableYears' => [
            'min' => ASTRONOMY_LABORATORY_MIN_YEAR,
            'max' => ASTRONOMY_LABORATORY_MAX_YEAR,
        ],
        'extremaVariables' => $extremaClientVariables,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
</body>
</html>
