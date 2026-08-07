<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/event-type-configuration.php';
require_once __DIR__ . '/../includes/astronomy-events.php';
require_once __DIR__ . '/../includes/editorial-configuration.php';

function configurationLoadingAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$connection = getWebDatabaseConnection();
$baseRows = $connection->query(
    'SELECT id,scope,event_group,event_type,public_type,public_subtype,category_key,nombre_amigable,habilitado,relevante_esta_noche,renderer_key '
    . 'FROM admin_tipos_eventos ORDER BY id'
)->fetchAll();
$surfaceStatement = $connection->prepare(
    'SELECT superficie,posicion FROM admin_tipos_eventos_superficies WHERE tipo_evento_id=:id ORDER BY posicion,superficie'
);
$legacyRows = [];
foreach ($baseRows as $row) {
    $surfaceStatement->execute(['id' => (int) $row['id']]);
    $row['surfaces'] = array_column($surfaceStatement->fetchAll(), 'superficie');
    if ($row['relevante_esta_noche'] === null) {
        $fallback = astronomyEventTypeCatalog()[$row['scope'] . '/' . $row['event_group'] . '/' . $row['event_type']] ?? null;
        $row['relevantTonight'] = ($fallback['relevantTonight'] ?? false) === true;
    }
    $legacyRows[] = $row;
}
astronomyEventTypeConfigResetCache();
configurationLoadingAssert(astronomyEventTypeConfigLoad()['rows'] === $legacyRows, 'El JOIN cambió el contrato de tipos o superficies.');

$legacySources = [];
foreach (astronomyEventSourceCatalog() as $group => $definition) {
    $statement = $connection->prepare('SELECT valor FROM admin_configuracion_sitio WHERE clave=:clave LIMIT 1');
    $statement->execute(['clave' => 'astronomy.event_source.' . $group]);
    $value = $statement->fetchColumn();
    $legacySources[$group] = is_string($value) && in_array($value, $definition['sources'], true)
        ? $value
        : astronomyEventSourceFallback($definition);
}
astronomyEventSourceResetRequestCache();
configurationLoadingAssert(
    astronomyEventSourcesForGroups(array_keys(astronomyEventSourceCatalog())) === $legacySources,
    'La resolución por lote cambió alguna fuente.'
);

$parameters = $connection->query('SELECT clave,valor_decimal FROM admin_parametros_editoriales')->fetchAll(PDO::FETCH_KEY_PAIR);
$texts = $connection->query('SELECT clave,valor_texto FROM admin_textos_editoriales')->fetchAll(PDO::FETCH_KEY_PAIR);
astronomyEditorialResetCache();
$parameterState = astronomyEditorialLoadKind('parameters');
configurationLoadingAssert($parameterState['parameters'] === $parameters, 'La carga numérica cambió valores.');
configurationLoadingAssert(astronomyEditorialCache()['texts_loaded'] === false, 'La carga numérica anticipó los textos.');
$textState = astronomyEditorialLoadKind('texts');
configurationLoadingAssert($textState['texts'] === $texts, 'La carga diferida de textos cambió valores.');

echo "Carga conjunta de configuraciones: OK\n";
