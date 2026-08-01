<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/editorial-configuration.php';
require_once __DIR__ . '/../includes/home-sky.php';
require_once __DIR__ . '/../includes/event-presentation.php';

function editorialAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function editorialRejects(callable $callback, string $message): void { try { $callback(); } catch (InvalidArgumentException) { return; } throw new RuntimeException($message); }

$unavailable = static function (): PDO { throw new RuntimeException('unavailable'); };
editorialAssert(astronomyEditorialNumber('home.altitude.very_low_max', $unavailable) === 15.0, 'El fallback no devolvió el umbral histórico.');
editorialAssert(astronomyEditorialText('event.conjunction.visible', [], $unavailable) === 'Se podrán ver juntos alrededor de esa hora.', 'El fallback no devolvió el texto histórico.');
editorialRejects(fn() => astronomyEditorialValidateText('event.conjunction.very_close', '{objeto} y {planeta}'), 'Se aceptó un placeholder desconocido.');
editorialRejects(fn() => astronomyEditorialValidateText('event.conjunction.very_close', 'Estarán muy juntas'), 'Se eliminó un placeholder obligatorio.');
editorialRejects(fn() => astronomyEditorialValidateText('cloud.today.best_moon', 'Mirar {codigo}'), 'La recomendación de nubosidad aceptó lógica o placeholders libres.');
editorialRejects(fn() => astronomyEditorialValidateValues(['home.altitude.very_low_max' => 40, 'home.altitude.low_max' => 30], []), 'Se aceptaron bandas fuera de orden.');
editorialRejects(fn() => astronomyEditorialValidateValues(['event.supermoon.min_percent' => 200], []), 'Se aceptó un límite físico inválido.');

$connection = getWebDatabaseConnection();
astronomyEditorialInitialize($connection);
$parameterRows = $connection->query('SELECT clave,valor_decimal FROM admin_parametros_editoriales')->fetchAll(PDO::FETCH_KEY_PAIR);
$textRows = $connection->query('SELECT clave,valor_texto FROM admin_textos_editoriales')->fetchAll(PDO::FETCH_KEY_PAIR);
try {
    astronomyEditorialRestoreBlock($connection, 'home_moon');
    $defaultState = astronomyEditorialValueState('texts', 'home.moon.very_low');
    editorialAssert($defaultState === ['value' => 'Está visible, muy baja hacia {direccion}.', 'modified' => false, 'status' => 'Predeterminado'], 'El control no recibe el default efectivo sin override.');
    astronomyEditorialSave($connection, ['event.conjunction.very_close_degrees' => 2.0], ['event.conjunction.very_close' => '{objeto} y la Luna, pegadísimas']);
    $modifiedParameterState = astronomyEditorialValueState('parameters', 'event.conjunction.very_close_degrees');
    $modifiedTextState = astronomyEditorialValueState('texts', 'event.conjunction.very_close');
    editorialAssert($modifiedParameterState['value'] === 2.0 && $modifiedParameterState['modified'] && $modifiedParameterState['status'] === 'Modificado', 'El parámetro guardado no figura como override efectivo.');
    editorialAssert($modifiedTextState['value'] === '{objeto} y la Luna, pegadísimas' && $modifiedTextState['modified'] && $modifiedTextState['status'] === 'Modificado', 'El mensaje guardado no figura como override efectivo.');
    editorialAssert((int) $connection->query("SELECT COUNT(*) FROM admin_textos_editoriales WHERE clave='event.conjunction.very_close'")->fetchColumn() === 1, 'Guardar no creó el override de texto.');
    editorialAssert(astronomyEditorialNumber('event.conjunction.very_close_degrees') === 2.0, 'No se aplicó el override numérico.');
    $presentation = astronomyEventPresentation(['type' => 'conjunction', 'subtype' => 'venus', 'title' => 'Conjunción Luna–Venus', 'datetime' => '2026-01-01T20:00:00Z', 'details' => ['separation_degrees' => 1.5]], 'UTC');
    editorialAssert($presentation['title'] === 'Venus y la Luna, pegadísimas', 'El texto editado no llegó a la presentación pública.');
    astronomyEditorialRestoreBlock($connection, 'events');
    $restoredState = astronomyEditorialValueState('texts', 'event.conjunction.very_close');
    editorialAssert(!$restoredState['modified'] && $restoredState['status'] === 'Predeterminado' && $restoredState['value'] === '{objeto} y la Luna estarán muy juntas', 'Restaurar no volvió a exponer el default en el control.');
    editorialAssert((int) $connection->query("SELECT COUNT(*) FROM admin_textos_editoriales WHERE clave='event.conjunction.very_close'")->fetchColumn() === 0, 'Restaurar no eliminó el override.');
    editorialAssert(astronomyEditorialNumber('event.conjunction.very_close_degrees') === 1.0, 'La restauración no recuperó el umbral predeterminado.');
    editorialAssert(astronomyEditorialText('event.conjunction.very_close', ['objeto' => 'Venus']) === 'Venus y la Luna estarán muy juntas', 'La restauración no recuperó el texto predeterminado.');
    editorialAssert(astronomyEditorialNumber('today.venus_belt.max_cloud_percent') === 80.0, 'Cambió el umbral histórico de nubosidad del cinturón de Venus.');
    editorialAssert(astronomyEditorialFrontendConfiguration()['today']['cloudBestMoon'] === 'La menor nubosidad mientras la Luna esté sobre el horizonte se prevé cerca de las {hora}:00 ({porcentaje} %).', 'El frontend no recibió el mensaje validado de nubosidad.');
    astronomyEditorialSave($connection, ['tonight.highlights.planets_max' => 1], []);
    editorialAssert(astronomyEditorialNumber('tonight.highlights.planets_max') === 1.0, 'El cupo editable de planetas no creó un override.');
    astronomyEditorialRestoreBlock($connection, 'tonight');
    editorialAssert(astronomyEditorialNumber('tonight.highlights.planets_max') === 2.0, 'Restaurar no recuperó el cupo nocturno histórico.');
    $precedence = homeMoonSituationPresentation(['above_horizon' => true, 'altitude_degrees' => 85.0, 'azimuth_degrees' => 90.0, 'instant' => new DateTimeImmutable('2026-01-01T20:00:00Z')], .5);
    editorialAssert($precedence['text'] === 'Está sobre el horizonte, pero es prácticamente imposible verla.', 'Una regla posterior de altura desplazó la precedencia de Luna nueva.');
} finally {
    $connection->exec('DELETE FROM admin_parametros_editoriales');
    $connection->exec('DELETE FROM admin_textos_editoriales');
    $parameterInsert = $connection->prepare('INSERT INTO admin_parametros_editoriales (clave,valor_decimal) VALUES (:key,:value)');
    foreach ($parameterRows as $key => $value) $parameterInsert->execute(['key' => $key, 'value' => $value]);
    $textInsert = $connection->prepare('INSERT INTO admin_textos_editoriales (clave,valor_texto) VALUES (:key,:value)');
    foreach ($textRows as $key => $value) $textInsert->execute(['key' => $key, 'value' => $value]);
    astronomyEditorialResetCache();
}
echo "Editorial configuration checks passed.\n";
