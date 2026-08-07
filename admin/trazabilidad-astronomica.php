<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/store-admin-auth.php';
require_once __DIR__ . '/../includes/store-admin-navigation.php';
require_once __DIR__ . '/../includes/web-database.php';
require_once __DIR__ . '/../includes/astronomy-trace-maintenance.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();

function traceAdminHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function traceAdminDate(mixed $value): string
{
    $value = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
}

function traceAdminIdentifier(mixed $value): string
{
    $value = strtolower(trim((string) $value));
    return preg_match('/^[a-f0-9]{32}$/', $value) === 1 ? $value : '';
}

function traceAdminJson(mixed $value): string
{
    $raw = (string) $value;
    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $formatted = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return is_string($formatted) ? $formatted : $raw;
    } catch (Throwable) {
        return $raw;
    }
}

$filters = [
    'from' => traceAdminDate($_GET['from'] ?? ''),
    'to' => traceAdminDate($_GET['to'] ?? ''),
    'operation' => substr(trim((string) ($_GET['operation'] ?? '')), 0, 100),
    'status' => in_array($_GET['status'] ?? '', ['success', 'no_results', 'failed', 'disabled'], true) ? (string) $_GET['status'] : '',
    'request_id' => traceAdminIdentifier($_GET['request_id'] ?? ''),
    'session_trace_id' => traceAdminIdentifier($_GET['session_trace_id'] ?? ''),
    'source' => substr(trim((string) ($_GET['source'] ?? '')), 0, 190),
];
$detailId = traceAdminIdentifier($_GET['detail'] ?? '');
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$perPage = 40;
$messages = [];
$errors = [];
$rows = [];
$detail = null;
$operations = [];
$summary = ['total' => 0, 'payload_bytes' => 0];
$daily = [];
$connection = null;

$filterQuery = array_filter($filters, static fn(string $value): bool => $value !== '');
$filterQueryString = http_build_query($filterQuery);

try {
    $connection = getWebDatabaseConnection();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('La sesión expiró o el token CSRF no es válido. Recargá la página.');
        }
        $daysValue = (string) ($_POST['days'] ?? '30');
        if (preg_match('/^\d+$/', $daysValue) !== 1) throw new InvalidArgumentException('La retención no es válida.');
        $days = (int) $daysValue;
        $deleted = astronomyTraceCleanup($connection, $days);
        $messages[] = 'Limpieza completada: ' . $deleted . ' registros eliminados; se conservaron los últimos ' . $days . ' días.';
    }

    $where = [];
    $parameters = [];
    if ($filters['from'] !== '') { $where[] = 'created_at >= :from_date'; $parameters['from_date'] = $filters['from'] . ' 00:00:00'; }
    if ($filters['to'] !== '') { $where[] = 'created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)'; $parameters['to_date'] = $filters['to'] . ' 00:00:00'; }
    if ($filters['operation'] !== '') { $where[] = 'operation = :operation'; $parameters['operation'] = $filters['operation']; }
    if ($filters['status'] !== '') { $where[] = 'status = :status'; $parameters['status'] = $filters['status']; }
    if ($filters['request_id'] !== '') { $where[] = 'request_id = :request_id'; $parameters['request_id'] = $filters['request_id']; }
    if ($filters['session_trace_id'] !== '') { $where[] = 'session_trace_id = :session_trace_id'; $parameters['session_trace_id'] = $filters['session_trace_id']; }
    if ($filters['source'] !== '') { $where[] = 'source LIKE :source'; $parameters['source'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['source']) . '%'; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    $summaryStatement = $connection->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(OCTET_LENGTH(input_json)+OCTET_LENGTH(response_json)+COALESCE(OCTET_LENGTH(technical_error),0)),0) AS payload_bytes FROM astronomy_request_log' . $whereSql);
    $summaryStatement->execute($parameters);
    $summary = $summaryStatement->fetch(PDO::FETCH_ASSOC) ?: $summary;
    $total = (int) $summary['total'];
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $listStatement = $connection->prepare('SELECT id,request_id,session_trace_id,created_at,source,operation,status,latitude,longitude,elevation_meters,timezone,total_ms,technical_error FROM astronomy_request_log' . $whereSql . ' ORDER BY created_at DESC,id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
    $listStatement->execute($parameters);
    $rows = $listStatement->fetchAll(PDO::FETCH_ASSOC);

    $operations = $connection->query('SELECT DISTINCT operation FROM astronomy_request_log ORDER BY operation')->fetchAll(PDO::FETCH_COLUMN);
    $dailyStatement = $connection->prepare('SELECT DATE(created_at) AS day,COUNT(*) AS amount FROM astronomy_request_log' . $whereSql . ' GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 14');
    $dailyStatement->execute($parameters);
    $daily = $dailyStatement->fetchAll(PDO::FETCH_ASSOC);

    if ($detailId !== '') {
        $detailStatement = $connection->prepare('SELECT * FROM astronomy_request_log WHERE request_id = :request_id LIMIT 1');
        $detailStatement->execute(['request_id' => $detailId]);
        $detail = $detailStatement->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($detail === null) $errors[] = 'No se encontró la consulta solicitada.';
    }
} catch (Throwable $exception) {
    error_log('Astronomy trace admin failed [type=' . get_debug_type($exception) . ']: ' . $exception->getMessage());
    $errors[] = $exception instanceof InvalidArgumentException || $exception instanceof RuntimeException
        ? $exception->getMessage() : 'No se pudo consultar la trazabilidad astronómica.';
    $pages = 1;
}

$pages ??= 1;
$backUrl = 'trazabilidad-astronomica.php' . ($filterQueryString !== '' ? '?' . $filterQueryString : '');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Trazabilidad astronómica · Aquellas Lunas</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= traceAdminHtml('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <style>
        .trace-admin{display:grid;gap:1rem;max-width:100rem}.trace-admin__panel{padding:clamp(1rem,2vw,1.35rem)}
        .trace-admin__filters{display:grid;grid-template-columns:repeat(4,minmax(10rem,1fr));gap:.8rem;align-items:end}.trace-admin__field{display:grid;gap:.35rem;color:#cbd3e2;font-size:.82rem;font-weight:700}.trace-admin__field input,.trace-admin__field select{width:100%;min-width:0;min-height:2.6rem;padding:.55rem .65rem;border:1px solid rgba(151,172,213,.34);border-radius:.6rem;background:#09111f;color:#eef2fa}.trace-admin__field :focus-visible,.trace-admin button:focus-visible,.trace-admin a:focus-visible{outline:2px solid #e2bd5f;outline-offset:2px}.trace-admin__filter-actions{display:flex;gap:.55rem;flex-wrap:wrap}
        .trace-admin__summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem}.trace-admin__metric{padding:.8rem;border:1px solid rgba(151,172,213,.2);border-radius:.65rem;background:rgba(5,10,21,.38)}.trace-admin__metric span{display:block;color:#8f9bb0;font-size:.74rem}.trace-admin__metric strong{display:block;margin-top:.2rem;color:#eef2fa}.trace-admin__table-wrap{overflow:auto}.trace-admin__table{width:100%;border-collapse:collapse;font-size:.78rem}.trace-admin__table th,.trace-admin__table td{padding:.65rem;border-bottom:1px solid rgba(151,172,213,.16);text-align:left;vertical-align:top}.trace-admin__table th{color:#ddb957;white-space:nowrap}.trace-admin__table a{color:#cbd8f1;text-decoration:none}.trace-admin__id{font-family:ui-monospace,SFMono-Regular,Consolas,monospace}.trace-admin__status{display:inline-flex;padding:.16rem .42rem;border:1px solid rgba(151,172,213,.3);border-radius:999px}.trace-admin__error{color:#ef9a9a}.trace-admin__pagination{display:flex;align-items:center;justify-content:center;gap:.6rem;margin-top:1rem}.trace-admin__pagination a{color:#dbe3f2;text-decoration:none}.trace-admin__detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem;margin:1rem 0}.trace-admin__detail-grid div{padding:.7rem;border:1px solid rgba(151,172,213,.18);border-radius:.6rem;min-width:0}.trace-admin__detail-grid dt{color:#8f9bb0;font-size:.72rem}.trace-admin__detail-grid dd{margin:.25rem 0 0;overflow-wrap:anywhere}.trace-admin__json{max-height:34rem;overflow:auto;padding:1rem;border:1px solid rgba(151,172,213,.2);border-radius:.65rem;background:#060b15;color:#cbd8e8;white-space:pre-wrap;overflow-wrap:anywhere;font-size:.76rem}.trace-admin__detail-actions,.trace-admin__maintenance{display:flex;flex-wrap:wrap;gap:.6rem;align-items:end}.trace-admin__daily{color:#9eabc0;font-size:.78rem;line-height:1.65}.trace-admin__copy-status{min-width:7rem;color:#b7d6a9;font-size:.76rem}
        @media(max-width:68rem){.trace-admin__filters{grid-template-columns:repeat(2,minmax(0,1fr))}.trace-admin__detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:40rem){.trace-admin__filters,.trace-admin__summary,.trace-admin__detail-grid{grid-template-columns:1fr}.trace-admin__filter-actions>*{flex:1}.trace-admin__table{min-width:62rem}}
    </style>
</head>
<body class="store-admin">
<?php renderStoreAdminNavigation('astronomy_trace', 'Trazabilidad astronómica'); ?>
<main class="store-admin-main trace-admin">
<?php foreach ($messages as $message): ?><section class="store-admin-success" role="status"><?= traceAdminHtml($message) ?></section><?php endforeach; ?>
<?php if ($errors !== []): ?><section class="store-admin-alert" role="alert"><?php foreach ($errors as $error): ?><p><?= traceAdminHtml($error) ?></p><?php endforeach; ?></section><?php endif; ?>

<?php if ($detail !== null): ?>
<section class="card trace-admin__panel">
    <div class="trace-admin__detail-actions"><a class="button compact-secondary-button" href="<?= traceAdminHtml($backUrl) ?>">Volver al listado</a><button class="button compact-secondary-button" type="button" data-copy-request="<?= traceAdminHtml($detail['request_id']) ?>">Copiar request_id</button><span class="trace-admin__copy-status" role="status" aria-live="polite"></span><a class="button compact-secondary-button" href="trazabilidad-astronomica.php?session_trace_id=<?= traceAdminHtml($detail['session_trace_id']) ?>">Ver esta sesión</a></div>
    <h2>Consulta <span class="trace-admin__id"><?= traceAdminHtml($detail['request_id']) ?></span></h2>
    <dl class="trace-admin__detail-grid">
        <div><dt>Registro / fecha y hora</dt><dd>#<?= (int) $detail['id'] ?> · <?= traceAdminHtml($detail['created_at']) ?></dd></div><div><dt>Sesión anónima</dt><dd class="trace-admin__id"><?= traceAdminHtml($detail['session_trace_id']) ?></dd></div><div><dt>Origen / operación</dt><dd><?= traceAdminHtml($detail['source']) ?> · <?= traceAdminHtml($detail['operation']) ?></dd></div>
        <div><dt>Estado / duración</dt><dd><?= traceAdminHtml($detail['status']) ?> · <?= traceAdminHtml(number_format((float) $detail['total_ms'], 3, ',', '.')) ?> ms</dd></div><div><dt>Ubicación</dt><dd><?= traceAdminHtml($detail['latitude']) ?>, <?= traceAdminHtml($detail['longitude']) ?> · <?= traceAdminHtml($detail['elevation_meters']) ?> m</dd></div><div><dt>Zona horaria</dt><dd><?= traceAdminHtml($detail['timezone']) ?></dd></div>
        <div><dt>Reloj efectivo</dt><dd><?= traceAdminHtml($detail['effective_clock']) ?><?= (int) $detail['clock_simulated'] === 1 ? ' · simulado' : ' · real' ?></dd></div><div><dt>Versión del código</dt><dd class="trace-admin__id"><?= traceAdminHtml($detail['code_version']) ?></dd></div><div><dt>Sesión administrativa</dt><dd><?= (int) $detail['is_admin_session'] === 1 ? 'Sí' : 'No' ?></dd></div>
        <div><dt>Error técnico</dt><dd class="<?= $detail['technical_error'] !== null ? 'trace-admin__error' : '' ?>"><?= traceAdminHtml($detail['technical_error'] ?? 'Sin error') ?></dd></div>
    </dl>
    <h3>Parámetros de entrada</h3><pre class="trace-admin__json"><?= traceAdminHtml(traceAdminJson($detail['input_json'])) ?></pre>
    <h3>Respuesta completa</h3><pre class="trace-admin__json"><?= traceAdminHtml(traceAdminJson($detail['response_json'])) ?></pre>
</section>
<?php else: ?>
<section class="card trace-admin__panel">
    <form method="get" class="trace-admin__filters">
        <label class="trace-admin__field">Desde<input type="date" name="from" value="<?= traceAdminHtml($filters['from']) ?>"></label><label class="trace-admin__field">Hasta<input type="date" name="to" value="<?= traceAdminHtml($filters['to']) ?>"></label>
        <label class="trace-admin__field">Operación<select name="operation"><option value="">Todas</option><?php foreach ($operations as $operation): ?><option value="<?= traceAdminHtml($operation) ?>"<?= $filters['operation'] === $operation ? ' selected' : '' ?>><?= traceAdminHtml($operation) ?></option><?php endforeach; ?></select></label>
        <label class="trace-admin__field">Estado<select name="status"><option value="">Todos</option><?php foreach (['success','no_results','failed','disabled'] as $status): ?><option value="<?= $status ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label>
        <label class="trace-admin__field">request_id<input name="request_id" value="<?= traceAdminHtml($filters['request_id']) ?>" maxlength="32"></label><label class="trace-admin__field">session_trace_id<input name="session_trace_id" value="<?= traceAdminHtml($filters['session_trace_id']) ?>" maxlength="32"></label><label class="trace-admin__field">Origen<input name="source" value="<?= traceAdminHtml($filters['source']) ?>" maxlength="190"></label>
        <div class="trace-admin__filter-actions"><button class="button button-primary" type="submit">Filtrar</button><a class="button compact-secondary-button" href="trazabilidad-astronomica.php">Limpiar filtros</a></div>
    </form>
</section>
<section class="trace-admin__summary"><div class="trace-admin__metric"><span>Registros encontrados</span><strong><?= (int) $summary['total'] ?></strong></div><div class="trace-admin__metric"><span>Datos JSON y errores</span><strong><?= traceAdminHtml(number_format((int) $summary['payload_bytes'] / 1048576, 2, ',', '.')) ?> MiB aprox.</strong></div><div class="trace-admin__metric"><span>Últimos días del resultado</span><div class="trace-admin__daily"><?php foreach ($daily as $day): ?><?= traceAdminHtml($day['day']) ?>: <?= (int) $day['amount'] ?><br><?php endforeach; ?><?= $daily === [] ? 'Sin registros' : '' ?></div></div></section>
<section class="card trace-admin__panel"><div class="trace-admin__table-wrap"><table class="trace-admin__table"><thead><tr><th>Fecha registrada</th><th>request_id</th><th>Sesión</th><th>Origen</th><th>Operación</th><th>Estado</th><th>Ubicación</th><th>Tiempo</th><th>Error</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= traceAdminHtml($row['created_at']) ?></td><td><a class="trace-admin__id" href="trazabilidad-astronomica.php?<?= traceAdminHtml(http_build_query(array_merge($filterQuery, ['detail' => $row['request_id']]))) ?>"><?= traceAdminHtml($row['request_id']) ?></a></td><td><a class="trace-admin__id" href="trazabilidad-astronomica.php?session_trace_id=<?= traceAdminHtml($row['session_trace_id']) ?>"><?= traceAdminHtml(substr($row['session_trace_id'], 0, 10)) ?>…</a></td><td><?= traceAdminHtml($row['source']) ?></td><td><?= traceAdminHtml($row['operation']) ?></td><td><span class="trace-admin__status"><?= traceAdminHtml($row['status']) ?></span></td><td><?= traceAdminHtml(number_format((float) $row['latitude'], 4, ',', '.')) ?>, <?= traceAdminHtml(number_format((float) $row['longitude'], 4, ',', '.')) ?><br><small><?= traceAdminHtml($row['timezone']) ?></small></td><td><?= traceAdminHtml(number_format((float) $row['total_ms'], 2, ',', '.')) ?> ms</td><td class="<?= $row['technical_error'] !== null ? 'trace-admin__error' : '' ?>"><?= $row['technical_error'] !== null ? 'Sí' : 'No' ?></td></tr><?php endforeach; ?>
<?php if ($rows === []): ?><tr><td colspan="9">No hay consultas que coincidan con los filtros.</td></tr><?php endif; ?></tbody></table></div>
<?php if ($pages > 1): ?><nav class="trace-admin__pagination" aria-label="Paginación"><?php if ($page > 1): ?><a href="?<?= traceAdminHtml(http_build_query(array_merge($filterQuery, ['page' => $page - 1]))) ?>">← Anterior</a><?php endif; ?><span>Página <?= $page ?> de <?= $pages ?></span><?php if ($page < $pages): ?><a href="?<?= traceAdminHtml(http_build_query(array_merge($filterQuery, ['page' => $page + 1]))) ?>">Siguiente →</a><?php endif; ?></nav><?php endif; ?>
</section>
<section class="card trace-admin__panel"><h2>Mantenimiento</h2><form method="post" action="<?= traceAdminHtml($backUrl) ?>" class="trace-admin__maintenance"><input type="hidden" name="csrf_token" value="<?= traceAdminHtml(storeAdminCsrfToken()) ?>"><label class="trace-admin__field">Conservar los últimos días<input type="number" name="days" value="30" min="1" max="3650" required></label><button class="button compact-secondary-button" type="submit">Ejecutar limpieza</button></form></section>
<?php endif; ?>
</main>
<script>(()=>{const button=document.querySelector('[data-copy-request]');if(!button)return;button.addEventListener('click',async()=>{const status=document.querySelector('.trace-admin__copy-status');try{await navigator.clipboard.writeText(button.dataset.copyRequest||'');status.textContent='Copiado';}catch(error){const input=document.createElement('input');input.value=button.dataset.copyRequest||'';document.body.append(input);input.select();const copied=document.execCommand('copy');input.remove();status.textContent=copied?'Copiado':'No se pudo copiar';}});})();</script>
</body></html>
