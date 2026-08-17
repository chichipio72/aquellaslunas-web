<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/store-admin-auth.php';
require_once __DIR__ . '/../includes/store-admin-navigation.php';
require_once __DIR__ . '/../includes/web-push-astronomy.php';
require_once __DIR__ . '/../includes/scheduled-tasks.php';
require_once __DIR__ . '/../includes/asset-url.php';
require_once __DIR__ . '/../includes/favicon-links.php';

sendStoreAdminHeaders();
startStoreAdminSession();
requireStoreAdminAuthentication();

function astronomyPushAdminHtml(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function astronomyPushAdminAgent(mixed $value): string
{
    $value = preg_replace('/\s+/', ' ', trim((string) $value));
    if (!is_string($value) || $value === '') return 'Dispositivo no identificado';
    if (stripos($value, 'Android') !== false) $label = 'Android';
    elseif (stripos($value, 'iPhone') !== false || stripos($value, 'iOS') !== false) $label = 'iPhone';
    elseif (stripos($value, 'Edg/') !== false) $label = 'Microsoft Edge';
    elseif (stripos($value, 'Firefox') !== false) $label = 'Firefox';
    elseif (stripos($value, 'Chrome') !== false) $label = 'Chrome';
    else $label = $value;
    return strlen($label) > 100 ? substr($label, 0, 97) . '…' : $label;
}

function astronomyPushAdminStatusLabel(?string $status): string
{
    return [
        'success' => 'Correcto', 'running' => 'En ejecución',
        'sent' => 'Enviada', 'failed' => 'Fallida', 'processing' => 'Procesando',
        'skipped_quiet_hours' => 'Omitida por No molestar',
        'skipped_unavailable' => 'Tipo no disponible', 'expired' => 'Vencida',
        'cancelled' => 'Cancelada', 'pending' => 'Programada',
    ][$status ?? ''] ?? ($status ?: 'Sin datos');
}

function astronomyPushAdminDate(?string $value, ?string $timezone = null, string $format = 'd/m H:i'): string
{
    if (!$value) return '—';
    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone($timezone ?: 'UTC'))->format($format);
    }
    catch (Throwable $exception) { return $value; }
}

function astronomyPushAdminShortDate(?string $value, ?string $timezone = null): string
{
    return astronomyPushAdminDate($value, $timezone, 'd/m H:i');
}

$errors = [];
$success = '';
$connection = null;
$subscriptions = [];
$device = null;
$logs = [];
$scheduledTests = [];
$devicePreferences = [];
$notificationTypes = [];
$testNotificationType = null;
$upcoming = [];
$systemState = null;
$selectedId = filter_var($_GET['subscription_id'] ?? $_POST['subscription_id'] ?? null,
    FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
try {
    $connection = getWebDatabaseConnection();
    $migrationRegistry = require __DIR__ . '/../scripts/migrations/registry.php';
    if (is_array($migrationRegistry)) $systemState = scheduledTaskAdminState($connection, $migrationRegistry);
    try { $testNotificationType = astronomyPushNotificationType($connection, 'test'); }
    catch (Throwable $exception) { $testNotificationType = null; }
    $notificationTypes = astronomyPushNotificationTypes($connection);
    $subscriptions = astronomyPushAdminSubscriptions($connection);
    if ($selectedId === false || $selectedId === null) {
        $selectedId = isset($subscriptions[0]) ? (int) $subscriptions[0]['subscription_id'] : null;
    } else {
        $selectedId = (int) $selectedId;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!storeAdminCsrfIsValid($_POST['csrf_token'] ?? null)) {
            throw new InvalidArgumentException('La sesión expiró o el token CSRF no es válido. Recargá la página.');
        }
        if ($selectedId === null) throw new InvalidArgumentException('Elegí una suscripción válida.');
        $action = (string) ($_POST['action'] ?? 'save_device');
        if ($action === 'schedule_test') {
            $delay = filter_var($_POST['delay_minutes'] ?? null, FILTER_VALIDATE_INT);
            if ($delay === false) throw new InvalidArgumentException('Elegí cuándo enviar la prueba.');
            $scheduled = astronomyPushScheduleTest(
                $connection, $selectedId, (int) $delay, isset($_POST['respect_quiet_hours'])
            );
            $success = 'Prueba ' . $scheduled['id'] . ' programada. El cron general realizará el envío.';
        } elseif ($action === 'cancel_test') {
            $testId = filter_var($_POST['test_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($testId === false || !astronomyPushCancelScheduledTest($connection, $selectedId, (int) $testId)) {
                throw new InvalidArgumentException('La prueba ya no está pendiente o no pertenece al dispositivo elegido.');
            }
            $success = 'Prueba pendiente cancelada.';
        } elseif ($action === 'save_device') {
            astronomyPushSaveDevice($connection, $selectedId, $_POST);
            $success = 'Configuración guardada.';
        } else {
            throw new InvalidArgumentException('La acción solicitada no es válida.');
        }
        $subscriptions = astronomyPushAdminSubscriptions($connection);
    }
    if ($selectedId !== null) {
        $device = astronomyPushAdminDevice($connection, $selectedId);
        if ($device === null) throw new InvalidArgumentException('La suscripción elegida no existe.');
        $logs = astronomyPushRecentLog($connection, $selectedId);
        $scheduledTests = astronomyPushScheduledTests($connection, $selectedId);
        $devicePreferences = astronomyPushDeviceNotificationPreferences($connection, $selectedId);
        if ($device['device_name'] !== null && $device['timezone'] !== null) {
            $upcoming = astronomyPushAdminUpcomingEvents($device, $devicePreferences,
                new DateTimeImmutable('now', new DateTimeZone('UTC')));
        }
    }
} catch (InvalidArgumentException $exception) {
    $errors[] = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Astronomy Web Push admin failed [type=' . get_debug_type($exception) . '].');
    $errors[] = 'No se pudo cargar o guardar la configuración de notificaciones astronómicas.';
}

$configured = is_array($device) && $device['device_name'] !== null;
$form = [
    'device_name' => $configured ? $device['device_name'] : '',
    'notifications_enabled' => $configured ? (int) $device['notifications_enabled'] : 1,
    'location_name' => $configured ? $device['location_name'] : '',
    'latitude' => $configured ? $device['latitude'] : '',
    'longitude' => $configured ? $device['longitude'] : '',
    'timezone' => $configured ? $device['timezone'] : '',
    'quiet_hours_enabled' => $configured ? (int) $device['quiet_hours_enabled'] : 0,
    'quiet_start_local' => $configured && $device['quiet_start_local'] ? substr((string) $device['quiet_start_local'], 0, 5) : '23:00',
    'quiet_end_local' => $configured && $device['quiet_end_local'] ? substr((string) $device['quiet_end_local'], 0, 5) : '08:00',
    'moonrise_enabled' => $configured ? (int) ($device['moonrise_enabled'] ?? 0) : 0,
];
$deviceTimezone = $configured && is_string($device['timezone']) ? $device['timezone'] : 'UTC';
usort($upcoming, static function (array $left, array $right): int {
    $leftTime = $left['effective_time_utc'] ?? $left['notification_time_utc'] ?? null;
    $rightTime = $right['effective_time_utc'] ?? $right['notification_time_utc'] ?? null;
    if ($leftTime === null) return $rightTime === null ? 0 : 1;
    if ($rightTime === null) return -1;
    return $leftTime <=> $rightTime;
});
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Notificaciones · Aquellas Lunas</title>
    <?php renderFaviconLinks('../'); ?>
    <link rel="stylesheet" href="<?= astronomyPushAdminHtml('../' . versionedAssetUrl('assets/css/styles.css')) ?>">
    <script src="<?= astronomyPushAdminHtml('../' . versionedAssetUrl('assets/js/admin-notification-status.js')) ?>" defer></script>
    <style>
        .astronomy-notifications-admin { display: grid; gap: 1.15rem; max-width: 96rem; }
        .astronomy-notifications-admin > :is(.store-admin-alert, .store-admin-success) { margin: 0; }
        .astronomy-notifications-admin__operational-head {
            display: grid; grid-template-columns: minmax(0, 2fr) minmax(18rem, 1fr); gap: 1.15rem; align-items: stretch;
        }
        .astronomy-notifications-admin__panel { display: grid; align-content: start; gap: 1rem; min-width: 0; padding: clamp(1rem, 2vw, 1.35rem); }
        .astronomy-notifications-admin__panel-heading :is(h2, h3, p) { margin: 0; }
        .astronomy-notifications-admin__panel-heading h2 { margin-top: .2rem; }
        .astronomy-notifications-admin__system-grid {
            display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .55rem; margin: 0;
        }
        .astronomy-notifications-admin__metric {
            min-width: 0; padding: .7rem .75rem; border: 1px solid rgba(170, 178, 197, .18);
            border-radius: .62rem; background: rgba(5, 10, 21, .38);
        }
        .astronomy-notifications-admin__metric dt { color: #9facbf; font-size: .72rem; line-height: 1.3; }
        .astronomy-notifications-admin__metric dd {
            margin: .25rem 0 0; color: #f2f5ff; font-size: .88rem; font-weight: 750;
            line-height: 1.35; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .astronomy-notifications-admin__metric--error dd { color: #e0c6c6; }
        .astronomy-notifications-admin__selector { align-content: start; }
        .astronomy-notifications-admin__selector-form { display: grid; gap: .45rem; }
        .astronomy-notifications-admin__field {
            display: grid; align-content: start; gap: .4rem; min-width: 0;
            color: #cbd3e2; font-size: .84rem; font-weight: 680;
        }
        .astronomy-notifications-admin__field :is(input, select, textarea) {
            width: 100%; min-width: 0; min-height: 2.75rem; margin: 0; padding: .62rem .72rem;
            border: 1px solid rgba(151, 172, 213, .34); border-radius: .62rem;
            background: #09111f; color: #eef2fa; font: inherit;
        }
        .astronomy-notifications-admin__field :is(input, select, textarea):focus-visible {
            border-color: #e2bd5f; outline: 2px solid #e2bd5f; outline-offset: 2px;
        }
        .astronomy-notifications-admin__configuration { width: 100%; }
        .astronomy-notifications-admin__configuration-head {
            display: flex; align-items: end; justify-content: space-between; gap: 1rem;
        }
        .astronomy-notifications-admin__configuration-head :is(h2, p) { margin: 0; }
        .astronomy-notifications-admin__configuration-head h2 { margin-top: .2rem; }
        .astronomy-notifications-admin__unconfigured {
            margin: 0; padding: .7rem .8rem; border: 1px solid rgba(226, 189, 95, .3);
            border-radius: .6rem; background: rgba(226, 189, 95, .08); color: #e6cf91;
        }
        .astronomy-notifications-admin__configuration-form { display: grid; gap: 1rem; }
        .astronomy-notifications-admin__section {
            min-width: 0; padding: 1rem; border: 1px solid rgba(151, 172, 213, .2);
            border-radius: .8rem; background: rgba(8, 15, 29, .4);
        }
        .astronomy-notifications-admin__section-title {
            display: block; width: 100%; margin: 0 0 .85rem; padding: 0; color: #f0d27d;
            font-size: .95rem; font-weight: 780;
        }
        .astronomy-notifications-admin__status-grid {
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .55rem; margin: 0 0 .9rem;
        }
        .astronomy-notifications-admin__status-grid .astronomy-notifications-admin__metric dd { white-space: normal; overflow-wrap: anywhere; }
        .astronomy-notifications-admin__fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem 1rem; align-items: start; }
        .astronomy-notifications-admin__fields--location { grid-template-columns: minmax(15rem, 1.4fr) repeat(2, minmax(9rem, .65fr)) minmax(18rem, 1.35fr); }
        .astronomy-notifications-admin__paired-sections { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        fieldset.astronomy-notifications-admin__section { margin: 0; min-inline-size: 0; }
        fieldset.astronomy-notifications-admin__section > legend { float: left; }
        fieldset.astronomy-notifications-admin__section > legend + * { clear: both; }
        .astronomy-notifications-admin__quiet-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
        .astronomy-notifications-admin__quiet-grid .astronomy-notifications-admin__switch { grid-column: 1 / -1; }
        .astronomy-notifications-admin__help { display: block; margin-top: .75rem; color: #9facbf; font-size: .78rem; line-height: 1.45; }
        .astronomy-notifications-admin__preferences { display: grid; gap: .65rem; }
        .astronomy-notifications-admin__preference {
            padding: .7rem .75rem; border: 1px solid rgba(151, 172, 213, .18);
            border-radius: .65rem; background: rgba(21, 31, 51, .45);
        }
        .astronomy-notifications-admin__preference p { margin: .45rem 0 0; color: #9facbf; font-size: .78rem; line-height: 1.4; }
        .astronomy-notifications-admin__switch {
            position: relative; display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            min-width: 0; min-height: 2.75rem; padding: .68rem .78rem;
            border: 1px solid rgba(151, 172, 213, .24); border-radius: .65rem;
            background: rgba(21, 31, 51, .52); color: #dce3f0; font-size: .84rem; font-weight: 680; cursor: pointer;
        }
        .astronomy-notifications-admin__switch input { position: absolute; inline-size: 1px; block-size: 1px; opacity: 0; }
        .astronomy-notifications-admin__switch-control {
            position: relative; flex: 0 0 2.3rem; width: 2.3rem; height: 1.3rem;
            border: 1px solid #60708e; border-radius: 999px; background: #111b2c;
            transition: background .18s ease, border-color .18s ease;
        }
        .astronomy-notifications-admin__switch-control::after {
            content: ''; position: absolute; top: .16rem; left: .18rem; width: .82rem; height: .82rem;
            border-radius: 50%; background: #aeb8ca; transition: transform .18s ease, background .18s ease;
        }
        .astronomy-notifications-admin__switch input:checked + .astronomy-notifications-admin__switch-control { border-color: #e2bd5f; background: rgba(226, 189, 95, .3); }
        .astronomy-notifications-admin__switch input:checked + .astronomy-notifications-admin__switch-control::after { transform: translateX(.98rem); background: #f0cf70; }
        .astronomy-notifications-admin__switch input:focus-visible + .astronomy-notifications-admin__switch-control { outline: 2px solid #e2bd5f; outline-offset: 3px; }
        .astronomy-notifications-admin__switch input:disabled + .astronomy-notifications-admin__switch-control { opacity: .48; }
        .astronomy-notifications-admin__actions { display: flex; justify-content: flex-end; }
        .astronomy-notifications-admin__actions .button-primary { min-width: 14rem; }
        .astronomy-notifications-admin__lower-grid {
            display: grid; grid-template-columns: minmax(0, 1.35fr) repeat(2, minmax(0, 1fr)); gap: 1.15rem; align-items: start;
        }
        .astronomy-notifications-admin__lower-grid > .card { min-width: 0; }
        .astronomy-notifications-admin__test-form { display: grid; gap: .8rem; }
        .astronomy-notifications-admin__choices { display: flex; flex-wrap: wrap; gap: .5rem; margin: 0; padding: 0; border: 0; min-width: 0; }
        .astronomy-notifications-admin__choices legend { width: 100%; margin-bottom: .15rem; color: #cbd3e2; font-size: .82rem; font-weight: 680; }
        .astronomy-notifications-admin__choice { position: relative; display: inline-flex; align-items: center; cursor: pointer; }
        .astronomy-notifications-admin__choice input { position: absolute; inline-size: 1px; block-size: 1px; opacity: 0; }
        .astronomy-notifications-admin__choice span {
            display: inline-flex; min-height: 2.4rem; align-items: center; padding: .48rem .7rem;
            border: 1px solid rgba(151, 172, 213, .3); border-radius: .58rem;
            background: #09111f; color: #dce3f0; font-size: .78rem; font-weight: 680;
        }
        .astronomy-notifications-admin__choice input:checked + span { border-color: #e2bd5f; background: rgba(226, 189, 95, .13); color: #f2d887; }
        .astronomy-notifications-admin__choice input:focus-visible + span { outline: 2px solid #e2bd5f; outline-offset: 2px; }
        .astronomy-notifications-admin__scroll-region { max-height: 22rem; overflow: auto; scrollbar-color: #465676 #0b1323; }
        .astronomy-notifications-admin__scroll-region .push-admin__table { min-width: 36rem; }
        .astronomy-notifications-admin__compact-state { grid-template-columns: 1fr; }
        .astronomy-notifications-admin__coming { width: 100%; }
        @media (max-width: 72rem) {
            .astronomy-notifications-admin__system-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .astronomy-notifications-admin__fields--location { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .astronomy-notifications-admin__lower-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .astronomy-notifications-admin__lower-grid > :first-child { grid-column: 1 / -1; }
        }
        @media (max-width: 52rem) {
            .astronomy-notifications-admin__operational-head,
            .astronomy-notifications-admin__paired-sections { grid-template-columns: minmax(0, 1fr); }
            .astronomy-notifications-admin__status-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 40rem) {
            .astronomy-notifications-admin__system-grid,
            .astronomy-notifications-admin__status-grid,
            .astronomy-notifications-admin__fields,
            .astronomy-notifications-admin__fields--location,
            .astronomy-notifications-admin__quiet-grid,
            .astronomy-notifications-admin__lower-grid { grid-template-columns: minmax(0, 1fr); }
            .astronomy-notifications-admin__lower-grid > :first-child { grid-column: auto; }
            .astronomy-notifications-admin__configuration-head { display: grid; align-items: start; }
            .astronomy-notifications-admin__actions .button-primary { width: 100%; min-width: 0; }
            .astronomy-notifications-admin__scroll-region { max-width: 100%; }
        }
        .astronomy-notifications-admin__automation { width: 100%; padding-block: 1rem; }
        .astronomy-notifications-admin__automation-metrics { display: flex; flex-wrap: wrap; gap: .65rem 1.5rem; margin: 0; }
        .astronomy-notifications-admin__automation-metrics div { min-width: 8rem; }
        .astronomy-notifications-admin__automation-metrics dt { color: #98a6bd; font-size: .72rem; }
        .astronomy-notifications-admin__automation-metrics dd { margin: .18rem 0 0; color: #edf2fa; font-size: .88rem; font-weight: 750; }
        .astronomy-notifications-admin__automation-metrics .status-success { color: #a8d9ba; }
        .astronomy-notifications-admin__automation-metrics .status-failed,
        .astronomy-notifications-admin__automation-error dd { color: #efb0b0; }
        .astronomy-notifications-admin__automation-error { flex: 1 1 100%; padding: .65rem .75rem; border: 1px solid rgba(215,142,142,.5); border-radius: .6rem; background: rgba(143,55,55,.12); }
        .astronomy-notifications-admin__test-tools { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; }
        .astronomy-notifications-admin__tool { min-width: 0; padding: 0; overflow: hidden; }
        .astronomy-notifications-admin__tool > summary,
        .astronomy-notifications-admin__device-details > summary,
        .astronomy-notifications-admin__parameters > summary { cursor: pointer; list-style: none; }
        .astronomy-notifications-admin__tool > summary::-webkit-details-marker,
        .astronomy-notifications-admin__device-details > summary::-webkit-details-marker,
        .astronomy-notifications-admin__parameters > summary::-webkit-details-marker { display: none; }
        .astronomy-notifications-admin__tool > summary { display: flex; justify-content: space-between; align-items: center; min-height: 4rem; padding: 1rem 1.2rem; color: #eef2fa; font-weight: 780; }
        .astronomy-notifications-admin__summary-action { color: #e2bd5f; font-size: .78rem; }
        .astronomy-notifications-admin__tool[open] .astronomy-notifications-admin__summary-action { font-size: 0; }
        .astronomy-notifications-admin__tool[open] .astronomy-notifications-admin__summary-action::after { content: 'Cerrar'; font-size: .78rem; }
        .astronomy-notifications-admin__tool-body { display: grid; gap: 1rem; padding: 0 1.2rem 1.2rem; border-top: 1px solid rgba(151,172,213,.17); }
        .astronomy-notifications-admin__tool-body > :first-child { margin-top: 1rem; }
        .astronomy-notifications-admin__catalog-link { color: #e8cf8c; text-decoration: none; font-size: .82rem; font-weight: 700; text-align: right; }
        .astronomy-notifications-admin__catalog-link small { display: block; margin-top: .2rem; color: #93a2b9; font-weight: 500; }
        .astronomy-notifications-admin__device-summary { display: grid; grid-template-columns: 1.4fr repeat(2,minmax(0,1fr)); gap: .7rem; }
        .astronomy-notifications-admin__device-summary > div { display: grid; gap: .25rem; min-width: 0; padding: .8rem; border: 1px solid rgba(151,172,213,.18); border-radius: .65rem; background: rgba(5,10,21,.38); }
        .astronomy-notifications-admin__device-summary span { color: #99a7bc; font-size: .76rem; }
        .astronomy-notifications-admin__device-summary strong { color: #eef2fa; font-size: .9rem; overflow-wrap: anywhere; }
        .astronomy-notifications-admin__preferences-section > h3,
        .astronomy-notifications-admin__quiet-compact h3 { margin: 0; font-size: .98rem; color: #f0d27d; }
        .astronomy-notifications-admin__preferences { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .astronomy-notifications-admin__preference { display: flex; align-items: center; justify-content: space-between; gap: .8rem; }
        .astronomy-notifications-admin__preference p { margin: .2rem 0 0; }
        .astronomy-notifications-admin__switch--compact { min-height: 0; padding: 0; border: 0; background: transparent; }
        .astronomy-notifications-admin__quiet-compact { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: .8rem; align-items: center; padding: .8rem; border: 1px solid rgba(151,172,213,.18); border-radius: .7rem; background: rgba(8,15,29,.4); }
        .astronomy-notifications-admin__quiet-compact p { margin: .25rem 0 0; color: #aeb9ce; font-size: .8rem; }
        .astronomy-notifications-admin__quiet-times { display: grid; grid-template-columns: repeat(2,minmax(8rem,1fr)); gap: .7rem; grid-column: 1/-1; }
        .astronomy-notifications-admin__quiet-times[hidden] { display: none !important; }
        .astronomy-notifications-admin__device-details { border: 1px solid rgba(151,172,213,.22); border-radius: .7rem; overflow: hidden; }
        .astronomy-notifications-admin__device-details > summary { padding: .8rem 1rem; color: #cbd5e6; font-weight: 700; }
        .astronomy-notifications-admin__details-body { display: grid; gap: 1rem; padding: 0 1rem 1rem; }
        .astronomy-notifications-admin__technical-state { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .6rem; margin: 0; }
        .astronomy-notifications-admin__technical-state div { padding: .65rem; background: rgba(5,10,21,.38); border-radius: .55rem; }
        .astronomy-notifications-admin__technical-state dt { color: #94a2b8; font-size: .7rem; }
        .astronomy-notifications-admin__technical-state dd { margin: .2rem 0 0; overflow-wrap: anywhere; font-size: .8rem; }
        .astronomy-notifications-admin__parameters { padding: .7rem; border: 1px solid rgba(151,172,213,.16); border-radius: .6rem; }
        .astronomy-notifications-admin__parameters dl { display: grid; gap: .5rem; }
        .astronomy-notifications-admin__parameters dd { margin: .2rem 0 0; overflow-wrap: anywhere; color: #9facbf; font-size: .72rem; }
        .astronomy-notifications-admin__upcoming-list { display: grid; gap: .65rem; }
        .astronomy-notifications-admin__upcoming-item { display: grid; grid-template-columns: 8rem minmax(0,1fr); gap: 1rem; align-items: center; padding: .75rem .85rem; border: 1px solid rgba(151,172,213,.17); border-radius: .65rem; background: rgba(8,15,29,.36); }
        .astronomy-notifications-admin__upcoming-item time { color: #f0d27d; font-weight: 760; }
        .astronomy-notifications-admin__upcoming-item h3,
        .astronomy-notifications-admin__upcoming-item p { margin: 0; }
        .astronomy-notifications-admin__upcoming-item p { margin-top: .2rem; color: #aab6ca; font-size: .8rem; }
        .astronomy-notifications-admin__responsive-table { max-width: 100%; overflow-x: auto; }
        .astronomy-notifications-admin__row-details { margin-top: .35rem; font-size: .75rem; }
        .astronomy-notifications-admin__row-details summary { color: #e2bd5f; cursor: pointer; }
        .astronomy-notifications-admin__row-details dl { display: grid; gap: .35rem; min-width: 16rem; }
        .astronomy-notifications-admin__row-details dt { color: #8f9db4; }
        .astronomy-notifications-admin__row-details dd { margin: .1rem 0 0; white-space: normal; overflow-wrap: anywhere; }
        @media (max-width: 52rem) {
            .astronomy-notifications-admin__test-tools,
            .astronomy-notifications-admin__device-summary,
            .astronomy-notifications-admin__preferences,
            .astronomy-notifications-admin__technical-state { grid-template-columns: 1fr; }
            .astronomy-notifications-admin__configuration-head { align-items: start; }
            .astronomy-notifications-admin__catalog-link { text-align: left; }
        }
        @media (max-width: 40rem) {
            .astronomy-notifications-admin__automation-metrics { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .7rem; }
            .astronomy-notifications-admin__automation-metrics div { min-width: 0; }
            .astronomy-notifications-admin__automation-error { grid-column: 1/-1; }
            .astronomy-notifications-admin__upcoming-item { grid-template-columns: 1fr; gap: .25rem; }
            .astronomy-notifications-admin__responsive-table { overflow: visible; }
            .astronomy-notifications-admin__responsive-table table,
            .astronomy-notifications-admin__responsive-table tbody,
            .astronomy-notifications-admin__responsive-table tr,
            .astronomy-notifications-admin__responsive-table td { display: block; width: 100%; }
            .astronomy-notifications-admin__responsive-table thead { display: none; }
            .astronomy-notifications-admin__responsive-table tr { margin-bottom: .7rem; padding: .65rem; border: 1px solid rgba(151,172,213,.18); border-radius: .65rem; background: rgba(8,15,29,.36); }
            .astronomy-notifications-admin__responsive-table td { display: grid; grid-template-columns: 7rem minmax(0,1fr); gap: .55rem; padding: .28rem 0; border: 0; white-space: normal; overflow-wrap: anywhere; }
            .astronomy-notifications-admin__responsive-table td::before { content: attr(data-label); color: #8f9db4; font-size: .72rem; font-weight: 700; }
        }
    </style>
</head>
<body class="store-admin">
<?php renderStoreAdminNavigation('astronomy_notifications', 'Notificaciones'); ?>
<main class="store-admin-main astronomy-notifications-admin" data-live-admin-status data-status-kind="notifications" data-status-url="api/notificaciones.php?subscription_id=<?= (int) ($selectedId ?? 0) ?>" data-subscription-id="<?= (int) ($selectedId ?? 0) ?>" data-device-timezone="<?= astronomyPushAdminHtml($deviceTimezone) ?>" data-csrf-token="<?= astronomyPushAdminHtml(storeAdminCsrfToken()) ?>">
    <?php if ($errors !== []): ?><section class="store-admin-alert" role="alert"><strong>No se pudo completar la operación</strong><ul><?php foreach ($errors as $error): ?><li><?= astronomyPushAdminHtml($error) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
    <?php if ($success !== ''): ?><section class="store-admin-success" role="status"><strong><?= astronomyPushAdminHtml($success) ?></strong></section><?php endif; ?>

    <?php define('AQUELLAS_LUNAS_NOTIFICATIONS_ADMIN_VIEW', true); require __DIR__ . '/partials/notificaciones-operativas.php'; ?>

    <?php if (false): // Vista anterior conservada temporalmente como referencia durante la consolidación. ?>
    <div class="astronomy-notifications-admin__operational-head">
    <section class="card astronomy-notifications-admin__panel" data-scheduler-block>
        <div class="astronomy-notifications-admin__panel-heading admin-live-status__heading"><div><p class="eyebrow">Automatización</p><h2>Estado del scheduler</h2></div><div class="admin-live-status__controls"><button type="button" class="button compact-secondary-button" data-refresh-button>Actualizar</button><span data-refresh-feedback aria-live="polite">Datos cargados con la página</span></div></div>
        <?php if (!is_array($systemState)): ?><p class="store-admin-empty">El estado del orquestador todavía no está disponible.</p>
        <?php else:
            $orchestratorState = $systemState['orchestrator'];
            $lockBusy = scheduledTaskLockIsBusy();
        ?><dl class="astronomy-notifications-admin__system-grid">
            <div class="astronomy-notifications-admin__metric"><dt>Último inicio</dt><dd data-scheduler-last-started-at><?= astronomyPushAdminHtml($orchestratorState['last_started_at'] ?? '—') ?></dd></div>
            <div class="astronomy-notifications-admin__metric"><dt>Última finalización</dt><dd data-scheduler-last-finished-at><?= astronomyPushAdminHtml($orchestratorState['last_finished_at'] ?? '—') ?></dd></div>
            <div class="astronomy-notifications-admin__metric"><dt>Último éxito</dt><dd data-scheduler-last-success-at><?= astronomyPushAdminHtml($orchestratorState['last_success_at'] ?? '—') ?></dd></div>
            <div class="astronomy-notifications-admin__metric"><dt>Estado</dt><dd data-scheduler-status><?= astronomyPushAdminHtml($orchestratorState['last_status'] ?? '—') ?></dd></div>
            <div class="astronomy-notifications-admin__metric"><dt>Estado del lock</dt><dd><?= $lockBusy === null ? 'No disponible' : ($lockBusy ? 'En ejecución' : 'Libre') ?></dd></div>
            <div class="astronomy-notifications-admin__metric astronomy-notifications-admin__metric--error"><dt>Último error</dt><dd data-scheduler-last-error><?= astronomyPushAdminHtml(
                !empty($orchestratorState['last_error'])
                    ? astronomyWebPushSanitizeError((string) $orchestratorState['last_error']) : '—'
            ) ?></dd></div>
        </dl>
        <?php if ($systemState['manual_pending'] !== []): ?><p class="push-admin__note">Hay migraciones manuales pendientes; el orquestador informará si alguna bloquea tareas.</p><?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card astronomy-notifications-admin__panel astronomy-notifications-admin__selector">
        <div class="astronomy-notifications-admin__panel-heading"><p class="eyebrow">Catálogo global</p><h2>Tipos de notificación</h2></div>
        <?php if ($notificationTypes === []): ?><p class="store-admin-empty">No hay tipos registrados.</p><?php else: ?>
        <dl class="push-admin__device-state astronomy-notifications-admin__compact-state">
            <?php foreach ($notificationTypes as $notificationType): ?><div><dt><?= astronomyPushAdminHtml($notificationType['display_name']) ?> <code><?= astronomyPushAdminHtml($notificationType['notification_type']) ?></code></dt><dd><?= (int) $notificationType['available'] === 1 ? 'Disponible' : 'No disponible' ?> · <?= (int) $notificationType['admin_only'] === 1 ? 'Sólo administración' : 'Dispositivos' ?> · <?= astronomyPushAdminHtml($notificationType['default_schedule_mode']) ?></dd></div><?php endforeach; ?>
        </dl><?php endif; ?>
        <a class="button compact-secondary-button" href="tipos-notificaciones.php">Ver y editar catálogo</a>
    </section>

    <section class="card astronomy-notifications-admin__panel astronomy-notifications-admin__selector">
        <div class="astronomy-notifications-admin__panel-heading"><p class="eyebrow">Dispositivos privados</p><h2>Dispositivo administrado</h2></div>
        <?php if ($subscriptions === []): ?><p class="store-admin-empty">No hay suscripciones Web Push guardadas.</p><?php else: ?>
        <form method="get" class="astronomy-notifications-admin__selector-form">
            <label class="astronomy-notifications-admin__field">Suscripción<select name="subscription_id" onchange="this.form.submit()">
                <?php foreach ($subscriptions as $subscription): $id = (int) $subscription['subscription_id']; ?>
                <option value="<?= $id ?>"<?= $selectedId === $id ? ' selected' : '' ?>><?php if ($subscription['device_name']): ?><?= astronomyPushAdminHtml($subscription['device_name']) ?> — <?= astronomyPushAdminHtml(astronomyPushAdminAgent($subscription['user_agent'])) ?> — ID <?= $id ?><?php else: ?>Suscripción <?= $id ?> — sin configurar · <?= astronomyPushAdminHtml(astronomyPushAdminAgent($subscription['user_agent'])) ?><?php endif; ?> · <?= (int) $subscription['active'] === 1 ? 'activa' : 'inactiva' ?></option>
                <?php endforeach; ?>
            </select></label>
            <noscript><button class="button compact-secondary-button" type="submit">Cargar</button></noscript>
        </form>
        <?php endif; ?>
    </section>
    </div>

    <?php if (is_array($device)): ?>
    <section class="card astronomy-notifications-admin__panel">
        <div class="astronomy-notifications-admin__panel-heading"><p class="eyebrow">Envío inmediato</p><h2>Enviar prueba manual</h2></div>
        <p class="push-admin__note">Envía ahora una notificación al dispositivo seleccionado, sin esperar al scheduler.</p>
        <form method="post" class="astronomy-notifications-admin__configuration-form" data-notification-action>
            <input type="hidden" name="csrf_token" value="<?= astronomyPushAdminHtml(storeAdminCsrfToken()) ?>">
            <input type="hidden" name="subscription_id" value="<?= (int) $device['subscription_id'] ?>">
            <input type="hidden" name="action" value="send_manual_test">
            <div class="astronomy-notifications-admin__fields">
                <label class="astronomy-notifications-admin__field">Título<input type="text" name="title" maxlength="120" required value="Aquellas Lunas"></label>
                <label class="astronomy-notifications-admin__field">URL a abrir<input type="text" name="target_url" maxlength="2048" required value="./"></label>
                <label class="astronomy-notifications-admin__field astronomy-notifications-admin__field--full">Mensaje<textarea name="body" maxlength="500" rows="3" required>Esta es una notificación de prueba.</textarea></label>
            </div>
            <div class="astronomy-notifications-admin__actions"><button type="submit" class="button button-primary">Enviar ahora</button></div>
        </form>
    </section>
    <?php endif; ?>

    <?php if (is_array($device)): ?>
    <section class="card astronomy-notifications-admin__panel astronomy-notifications-admin__configuration">
        <div class="astronomy-notifications-admin__configuration-head"><div><p class="eyebrow">Configuración</p><h2>Configuración del dispositivo</h2></div><?php if ($configured): ?><strong><?= astronomyPushAdminHtml($device['device_name']) ?></strong><?php endif; ?></div>
        <?php if (!$configured): ?><p class="astronomy-notifications-admin__unconfigured">Esta suscripción todavía no tiene configuración. Completá los datos y guardalos antes de programar pruebas o calcular eventos.</p><?php endif; ?>
        <form method="post" class="astronomy-notifications-admin__configuration-form">
            <input type="hidden" name="csrf_token" value="<?= astronomyPushAdminHtml(storeAdminCsrfToken()) ?>">
            <input type="hidden" name="subscription_id" value="<?= (int) $device['subscription_id'] ?>">
            <input type="hidden" name="action" value="save_device">
            <section class="astronomy-notifications-admin__section" aria-labelledby="device-status-heading">
                <h3 class="astronomy-notifications-admin__section-title" id="device-status-heading">Estado y nombre</h3>
                <dl class="astronomy-notifications-admin__status-grid">
                    <div class="astronomy-notifications-admin__metric"><dt>Suscripción</dt><dd>ID <?= (int) $device['subscription_id'] ?></dd></div>
                    <div class="astronomy-notifications-admin__metric"><dt>Estado técnico</dt><dd><?= (int) $device['active'] === 1 ? 'Activa' : 'Inactiva' ?></dd></div>
                    <div class="astronomy-notifications-admin__metric"><dt>Último éxito</dt><dd><?= astronomyPushAdminHtml($device['last_success_at'] ?: '—') ?></dd></div>
                    <div class="astronomy-notifications-admin__metric"><dt>Último error</dt><dd><?= astronomyPushAdminHtml($device['last_error_at'] ?: '—') ?><?php if ($device['last_error_message']): ?><small><?= astronomyPushAdminHtml(astronomyWebPushSanitizeError((string) $device['last_error_message'])) ?></small><?php endif; ?></dd></div>
                </dl>
                <div class="astronomy-notifications-admin__fields">
                    <label class="astronomy-notifications-admin__field">Nombre del dispositivo<input type="text" name="device_name" maxlength="100" required value="<?= astronomyPushAdminHtml($form['device_name']) ?>"></label>
                    <label class="astronomy-notifications-admin__switch"><span>Habilitar notificaciones astronómicas</span><input type="checkbox" name="notifications_enabled" value="1"<?= $form['notifications_enabled'] ? ' checked' : '' ?>><span class="astronomy-notifications-admin__switch-control" aria-hidden="true"></span></label>
                </div>
            </section>

            <section class="astronomy-notifications-admin__section" aria-labelledby="device-location-heading">
                <h3 class="astronomy-notifications-admin__section-title" id="device-location-heading">Ubicación</h3>
                <div class="astronomy-notifications-admin__fields astronomy-notifications-admin__fields--location">
                    <label class="astronomy-notifications-admin__field">Nombre de la ubicación<input type="text" name="location_name" maxlength="150" required value="<?= astronomyPushAdminHtml($form['location_name']) ?>"></label>
                    <label class="astronomy-notifications-admin__field">Latitud<input type="number" name="latitude" min="-90" max="90" step="0.000001" required value="<?= astronomyPushAdminHtml($form['latitude']) ?>"></label>
                    <label class="astronomy-notifications-admin__field">Longitud<input type="number" name="longitude" min="-180" max="180" step="0.000001" required value="<?= astronomyPushAdminHtml($form['longitude']) ?>"></label>
                    <label class="astronomy-notifications-admin__field">Zona horaria<input type="text" name="timezone" maxlength="64" required placeholder="America/Argentina/Buenos_Aires" value="<?= astronomyPushAdminHtml($form['timezone']) ?>"></label>
                </div>
            </section>

            <div class="astronomy-notifications-admin__paired-sections">
            <fieldset class="astronomy-notifications-admin__section"><legend class="astronomy-notifications-admin__section-title">No molestar</legend>
                <div class="astronomy-notifications-admin__quiet-grid">
                    <label class="astronomy-notifications-admin__switch"><span>Activar horario de silencio</span><input type="checkbox" name="quiet_hours_enabled" value="1"<?= $form['quiet_hours_enabled'] ? ' checked' : '' ?>><span class="astronomy-notifications-admin__switch-control" aria-hidden="true"></span></label>
                    <label class="astronomy-notifications-admin__field">Desde<input type="time" name="quiet_start_local" value="<?= astronomyPushAdminHtml($form['quiet_start_local']) ?>"></label>
                    <label class="astronomy-notifications-admin__field">Hasta<input type="time" name="quiet_end_local" value="<?= astronomyPushAdminHtml($form['quiet_end_local']) ?>"></label>
                </div>
                <small class="astronomy-notifications-admin__help">Intervalo semiabierto: incluye la hora inicial y termina justo antes de la hora final.</small>
            </fieldset>
            <fieldset class="astronomy-notifications-admin__section"><legend class="astronomy-notifications-admin__section-title">Tipos de aviso</legend>
                <div class="astronomy-notifications-admin__preferences">
                <?php foreach ($devicePreferences as $preference): ?>
                <div class="astronomy-notifications-admin__preference">
                    <label class="astronomy-notifications-admin__switch"><span><?= astronomyPushAdminHtml($preference['display_name']) ?><?php if ((int) $preference['available'] !== 1): ?> — no disponible globalmente<?php endif; ?></span><input type="checkbox" name="notification_preferences[<?= astronomyPushAdminHtml($preference['notification_type']) ?>]" value="1"
                        <?= (int) ($preference['enabled'] ?? 0) === 1 ? ' checked' : '' ?><?= (int) $preference['available'] !== 1 ? ' disabled' : '' ?>><span class="astronomy-notifications-admin__switch-control" aria-hidden="true"></span></label>
                    <p><?= astronomyPushAdminHtml($preference['description'] ?: '') ?><?php $parameters = astronomyPushEventParameters($preference); if ($parameters !== []): ?> Parámetros: <?= astronomyPushAdminHtml(json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>.<?php endif; ?></p>
                </div>
                <?php endforeach; ?>
                </div>
            </fieldset>
            </div>
            <div class="astronomy-notifications-admin__actions"><button type="submit" class="button button-primary">Guardar configuración</button></div>
        </form>
    </section>

    <div class="astronomy-notifications-admin__lower-grid">
    <section class="card astronomy-notifications-admin__panel">
        <div class="astronomy-notifications-admin__panel-heading"><p class="eyebrow">Cadena completa</p><h2>Probar notificaciones</h2></div>
        <p class="push-admin__note">La prueba queda programada en MySQL. La enviará el cron general; esta página no realiza el envío.</p>
        <?php if (!$configured): ?>
            <p class="store-admin-empty">Guardá primero la configuración del dispositivo.</p>
        <?php elseif (!is_array($testNotificationType) || (int) $testNotificationType['available'] !== 1 || (int) $testNotificationType['admin_only'] !== 1): ?>
            <p class="store-admin-empty">Las pruebas reales no están disponibles globalmente.</p>
        <?php else:
            $testTimezone = new DateTimeZone((string) $device['timezone']);
            $testNow = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        ?>
        <form method="post" class="astronomy-notifications-admin__test-form" data-notification-action>
            <input type="hidden" name="csrf_token" value="<?= astronomyPushAdminHtml(storeAdminCsrfToken()) ?>">
            <input type="hidden" name="subscription_id" value="<?= (int) $device['subscription_id'] ?>">
            <input type="hidden" name="action" value="schedule_test">
            <fieldset class="astronomy-notifications-admin__choices"><legend>Enviar dentro de</legend>
                <?php foreach ([1, 3, 5] as $delay): ?>
                <label class="astronomy-notifications-admin__choice"><input type="radio" name="delay_minutes" value="<?= $delay ?>"<?= $delay === 1 ? ' checked' : '' ?>><span>
                    <?= $delay ?> <?= $delay === 1 ? 'minuto' : 'minutos' ?> · <?= astronomyPushAdminHtml($testNow->modify('+' . $delay . ' minutes')->setTimezone($testTimezone)->format('H:i')) ?>
                </span></label>
                <?php endforeach; ?>
            </fieldset>
            <label class="astronomy-notifications-admin__switch"><span>Respetar No molestar</span><input type="checkbox" name="respect_quiet_hours" value="1" checked><span class="astronomy-notifications-admin__switch-control" aria-hidden="true"></span></label>
            <button type="submit" class="button button-primary">Programar prueba real</button>
        </form>
        <?php endif; ?>

        <div><h3>Pruebas pendientes y recientes</h3></div>
        <?php if ($scheduledTests === []): ?><p class="store-admin-empty">Todavía no hay pruebas programadas para este dispositivo.</p><?php endif; ?>
        <div class="push-admin__table-wrap astronomy-notifications-admin__scroll-region"><table class="push-admin__table"><thead><tr><th>Programada</th><th>No molestar</th><th>Estado</th><th>Procesada</th><th>Resultado</th><th>Acción</th></tr></thead><tbody data-tests-body>
        <?php foreach ($scheduledTests as $test):
            $testLocal = $configured
                ? (new DateTimeImmutable((string) $test['scheduled_at_utc'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone((string) $device['timezone']))->format('Y-m-d H:i T')
                : (string) $test['scheduled_at_utc'] . ' UTC';
            $statusLabels = ['pending' => 'Programada', 'processing' => 'Procesando', 'sent' => 'Enviada',
                'failed' => 'Fallida', 'skipped_quiet_hours' => 'Omitida',
                'skipped_unavailable' => 'No disponible', 'expired' => 'Vencida', 'cancelled' => 'Cancelada'];
        ?><tr>
            <td><?= astronomyPushAdminHtml($testLocal) ?></td>
            <td><?= (int) $test['respect_quiet_hours'] === 1 ? 'Sí' : 'No' ?></td>
            <td><?= astronomyPushAdminHtml($statusLabels[(string) $test['status']] ?? $test['status']) ?></td>
            <td><?= astronomyPushAdminHtml($test['processed_at'] ?: '—') ?></td>
            <td><?= astronomyPushAdminHtml($test['error_message'] ? astronomyWebPushSanitizeError((string) $test['error_message']) : ($test['notification_log_id'] ? 'Log #' . (int) $test['notification_log_id'] : '—')) ?></td>
            <td><?php if ($test['status'] === 'pending'): ?><form method="post" data-notification-action>
                <input type="hidden" name="csrf_token" value="<?= astronomyPushAdminHtml(storeAdminCsrfToken()) ?>">
                <input type="hidden" name="subscription_id" value="<?= (int) $device['subscription_id'] ?>">
                <input type="hidden" name="action" value="cancel_test">
                <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
                <button type="submit" class="button compact-secondary-button">Cancelar</button>
            </form><?php else: ?>—<?php endif; ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section class="card astronomy-notifications-admin__panel">
        <div class="astronomy-notifications-admin__panel-heading"><p class="eyebrow">Próximos eventos</p><h2>Proveedores astronómicos</h2></div>
        <?php if (!$configured): ?><p class="store-admin-empty">Guardá una ubicación para calcular próximos eventos.</p>
        <?php elseif ($upcoming === []): ?><p class="store-admin-empty">No hay tipos disponibles para calcular.</p>
        <?php else: ?><dl class="push-admin__device-state astronomy-notifications-admin__compact-state">
        <?php foreach ($upcoming as $next): $event = $next['event'] ?? null; ?>
            <div><dt><?= astronomyPushAdminHtml($next['display_name']) ?></dt><dd>
                <?= $next['enabled'] ? 'Habilitada' : 'Deshabilitada' ?> ·
                <?php if (!empty($next['not_calculated'])): ?>cálculo omitido mientras está deshabilitada
                <?php elseif (!empty($next['error'])): ?>error: <?= astronomyPushAdminHtml($next['error']) ?>
                <?php elseif (!is_array($event)): ?>sin evento en el horizonte
                <?php else: ?>evento <?= astronomyPushAdminHtml($event['event_time_local']->format('Y-m-d H:i T')) ?> · aviso <?= astronomyPushAdminHtml($next['notification_time_utc']->setTimezone(new DateTimeZone((string) $device['timezone']))->format('Y-m-d H:i T')) ?> · <?= $next['quiet'] ? ((string) $next['quiet_policy'] === 'postpone' ? 'se postergaría' : 'se omitiría') : 'fuera de No molestar' ?><?php endif; ?>
            </dd></div>
        <?php endforeach; ?>
        </dl><?php endif; ?>
    </section>

    <section class="card astronomy-notifications-admin__panel">
        <div class="astronomy-notifications-admin__panel-heading"><p class="eyebrow">Historial</p><h2>Últimos resultados</h2></div>
        <?php if ($logs === []): ?><p class="store-admin-empty">Todavía no hay registros para este dispositivo.</p><?php endif; ?>
        <div class="push-admin__table-wrap astronomy-notifications-admin__scroll-region"><table class="push-admin__table"><thead><tr><th>Evento</th><th>Fecha UTC</th><th>Estado</th><th>Decisión</th><th>Intento</th><th>Resultado</th></tr></thead><tbody data-logs-body>
        <?php foreach ($logs as $row): ?><tr><td><?= astronomyPushAdminHtml($row['notification_type']) ?></td><td><?= astronomyPushAdminHtml($row['event_time_utc']) ?></td><td><?= astronomyPushAdminHtml($row['status']) ?></td><td><?= astronomyPushAdminHtml($row['decision_reason'] ?: '—') ?></td><td><?= astronomyPushAdminHtml($row['attempted_at']) ?></td><td><?= astronomyPushAdminHtml($row['sent_at'] ?: ($row['error_message'] ? astronomyWebPushSanitizeError((string) $row['error_message']) : '—')) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    </div>

    <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
