<?php

require_once __DIR__ . '/event-presentation.php';
require_once __DIR__ . '/calendar-event.php';
require_once __DIR__ . '/asset-url.php';
require_once __DIR__ . '/date-format.php';

function astronomyEclipseDetailModel(array $event, string $timezoneName): array
{
    $presentation = astronomyEventPresentation($event, $timezoneName);
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    $subtype = (string) ($event['subtype'] ?? '');
    $solar = $subtype === 'solar_eclipse';
    $global = $solar
        ? (is_array($details['solar_eclipse_global'] ?? null) ? $details['solar_eclipse_global'] : [])
        : (is_array($details['eclipse_global'] ?? null) ? $details['eclipse_global'] : []);
    $local = $solar
        ? (is_array($details['solar_eclipse_local'] ?? null) ? $details['solar_eclipse_local'] : [])
        : (is_array($details['eclipse_local'] ?? null) ? $details['eclipse_local'] : []);
    $maximum = astronomyEventDateTime($event['datetime'] ?? null, $timezoneName);
    $visibilityClassification = strtolower(trim((string) ($local['visibility_classification'] ?? '')));

    $general = [];
    $globalType = is_string($global['global_type'] ?? null) ? trim((string) $global['global_type']) : '';
    if ($globalType !== '') {
        $general[] = ['label' => 'Tipo global', 'value' => ucfirst($globalType)];
    }
    if ($maximum !== null) {
        $general[] = ['label' => 'Máximo (hora local)', 'value' => astronomyEclipseDateTime($maximum)];
    }

    $localRows = is_array($presentation['public_details'] ?? null) ? $presentation['public_details'] : [];
    foreach ([
        'first_visible_instant' => 'Inicio visible',
        'last_visible_instant' => 'Final visible',
        'sunrise_during_eclipse' => 'Amanecer durante eclipse',
        'sunset_during_eclipse' => 'Atardecer durante eclipse',
        'moonrise_during_eclipse' => 'Salida de la Luna durante eclipse',
        'moonset_during_eclipse' => 'Puesta de la Luna durante eclipse',
    ] as $key => $label) {
        $date = astronomyEventDateTime($local[$key] ?? null, $timezoneName);
        if ($date !== null) {
            $localRows[] = ['label' => $label, 'value' => astronomyEclipseDateTime($date)];
        }
    }

    $contacts = [];
    foreach ((is_array($local['contacts'] ?? null) ? $local['contacts'] : []) as $contact) {
        if (!is_array($contact)) {
            continue;
        }
        $code = strtoupper(trim((string) ($contact['code'] ?? '')));
        $date = astronomyEventDateTime($contact['datetime'] ?? null, $timezoneName);
        if ($code === '' || $date === null) {
            continue;
        }
        $body = is_array($contact[$solar ? 'sun' : 'moon'] ?? null) ? $contact[$solar ? 'sun' : 'moon'] : [];
        $extra = [];
        $altitude = astronomyEventNumber($body['altitude_degrees'] ?? null, 1);
        $azimuth = astronomyEventNumber($body['azimuth_degrees'] ?? null, 1);
        if ($altitude !== null) {
            $extra[] = 'Alt. ' . $altitude . '°';
        }
        if ($azimuth !== null) {
            $extra[] = 'Az. ' . $azimuth . '°';
        }
        $contacts[] = [
            'label' => astronomyEclipseContactCodeLabel($code),
            'time' => astronomyEclipseDateTime($date),
            'body' => $solar ? 'Sol' : 'Luna',
            'extra' => $extra,
        ];
    }
    if ($contacts === []) {
        foreach ((is_array($global['contacts'] ?? null) ? $global['contacts'] : []) as $code => $datetime) {
            $date = astronomyEventDateTime($datetime, $timezoneName);
            $normalizedCode = is_string($code) ? strtoupper(trim($code)) : '';
            if ($normalizedCode !== '' && $date !== null) {
                $contacts[] = [
                    'label' => astronomyEclipseContactCodeLabel($normalizedCode),
                    'time' => astronomyEclipseDateTime($date),
                    'body' => $solar ? 'Sol' : 'Luna',
                    'extra' => [],
                ];
            }
        }
    }

    $map = is_array($global['visibility_map'] ?? null) ? $global['visibility_map'] : [];
    $filename = is_string($map['local_filename'] ?? null) ? trim((string) $map['local_filename']) : '';
    $visibilityMap = null;
    if (
        ($map['available'] ?? null) === true
        && strtolower(trim((string) ($map['status'] ?? ''))) === 'available'
        && basename($filename) === $filename
        && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $filename) === 1
        && !str_contains($filename, '..')
    ) {
        $source = null;
        foreach (['catalog_url', 'source_url'] as $key) {
            $candidate = is_string($map[$key] ?? null) ? trim((string) $map[$key]) : '';
            if (filter_var($candidate, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) parse_url($candidate, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                $source = $candidate;
                break;
            }
        }
        $visibilityMap = [
            'url' => versionedAssetUrl('assets/images/eclipses/' . rawurlencode($filename)),
            'alt' => 'Mapa de visibilidad mundial de ' . strtolower($presentation['title']),
            'attribution' => trim((string) ($map['attribution'] ?? '')),
            'source_link' => $source,
        ];
    }

    return [
        'title' => $presentation['title'],
        'date_label' => $maximum !== null ? astronomyEclipseDate($maximum) : 'Fecha no disponible',
        'time_label' => $maximum?->format('H:i') ?? 'Hora no disponible',
        'visibility' => $presentation['summary'] !== '' ? $presentation['summary'] : 'Visibilidad sin determinar',
        'not_visible' => $visibilityClassification === 'not_visible',
        'general_rows' => $general,
        'local_rows' => $localRows,
        'contacts' => $contacts,
        'visibility_map' => $visibilityMap,
        'observation' => trim(implode(' ', array_filter([
            (string) ($presentation['explanation'] ?? ''),
            (string) ($presentation['alert'] ?? ''),
        ]))),
        'presentation' => $presentation,
    ];
}

function astronomyEclipseDetailId(array $event): string
{
    $identifier = (string) ($event['id'] ?? $event['event_id'] ?? '');
    if ($identifier === '') {
        $identifier = (string) ($event['datetime'] ?? '') . '|' . (string) ($event['subtype'] ?? '');
    }
    return substr(hash('sha256', $identifier), 0, 16);
}

function astronomyEclipseDetailImage(array $event): ?array
{
    $details = is_array($event['details'] ?? null) ? $event['details'] : [];
    foreach ([
        $details['image_preview_url'] ?? null,
        $details['photo_preview_url'] ?? null,
        $details['image']['preview_url'] ?? null,
        $details['photo']['preview_url'] ?? null,
    ] as $candidate) {
        if (is_string($candidate) && trim($candidate) !== ''
            && (preg_match('#^https?://#i', trim($candidate)) === 1 || str_starts_with(trim($candidate), '/'))) {
            return ['url' => trim($candidate), 'label' => 'Foto específica'];
        }
    }
    $subtype = (string) ($event['subtype'] ?? '');
    if ($subtype === 'solar_eclipse') {
        $globalType = strtolower(trim((string) ($details['solar_eclipse_global']['global_type'] ?? '')));
        $localType = strtolower(trim((string) ($details['solar_eclipse_local']['visibility_classification'] ?? '')));
        $path = in_array($localType, ['visible_partial', 'partial'], true) || $globalType === 'partial'
            ? 'assets/images/contenido/SolParcial.jpg'
            : 'assets/images/contenido/SolTotal.jpg';
    } else {
        $globalType = strtolower(trim((string) ($details['eclipse_global']['global_type'] ?? '')));
        $path = $globalType === 'total'
            ? 'assets/images/contenido/LunaTotal.jpg'
            : 'assets/images/contenido/LunaParcial.jpg';
    }
    return is_file(dirname(__DIR__) . '/' . $path)
        ? ['url' => versionedAssetUrl($path), 'label' => 'Imagen representativa']
        : null;
}

function renderAstronomyEclipseDetailTrigger(array $event, string $label = 'Ver detalles'): void
{
    $id = astronomyEclipseDetailId($event);
    echo '<button type="button" class="eclipse-detail-trigger" data-eclipse-modal-open data-eclipse-id="'
        . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" data-template-id="eclipse-detail-' . $id . '">'
        . htmlspecialchars($label) . '</button>';
}

function renderAstronomyEclipseDetailTemplate(
    array $event,
    string $timezoneName,
    string $locationLabel,
    string $pageUrl,
    ?array $image = null
): void {
    $id = astronomyEclipseDetailId($event);
    $templateId = 'eclipse-detail-' . $id;
    $model = astronomyEclipseDetailModel($event, $timezoneName);
    $image ??= astronomyEclipseDetailImage($event);
    $calendarEvent = astronomyCalendarEventData($event, $model['presentation'], $timezoneName, $locationLabel, $pageUrl);
    ?>
    <template id="<?= htmlspecialchars($templateId) ?>">
        <article class="eclipse-detail">
            <header class="eclipse-detail-header">
                <h2><?= htmlspecialchars($model['title']) ?></h2>
                <p><?= htmlspecialchars($model['date_label']) ?> · <?= htmlspecialchars($model['time_label']) ?> (hora local)</p>
            </header>
            <?php if ($image !== null): ?><figure class="eclipse-detail-image"><img src="<?= htmlspecialchars($image['url']) ?>" alt="<?= htmlspecialchars($model['title']) ?>" loading="lazy"><figcaption><?= htmlspecialchars($image['label']) ?></figcaption></figure><?php endif; ?>
            <?php if ($model['general_rows'] !== []): ?><section class="eclipse-detail-general" aria-labelledby="<?= $templateId ?>-general"><h3 id="<?= $templateId ?>-general">Datos generales</h3><dl class="eclipse-detail-facts"><?php foreach ($model['general_rows'] as $row): ?><div><dt><?= htmlspecialchars($row['label']) ?></dt><dd><?= htmlspecialchars($row['value']) ?></dd></div><?php endforeach; ?></dl></section><?php endif; ?>
            <section class="eclipse-detail-local" aria-labelledby="<?= $templateId ?>-local">
                <h3 id="<?= $templateId ?>-local">Desde tu ubicación</h3>
                <p class="eclipse-visibility"><?= htmlspecialchars($model['visibility']) ?></p>
                <?php if ($model['not_visible']): ?><p class="eclipse-badge">No visible desde tu ubicación</p><?php endif; ?>
                <?php if ($model['local_rows'] !== []): ?><dl class="eclipse-detail-facts"><?php foreach ($model['local_rows'] as $row): ?><div><dt><?= htmlspecialchars($row['label']) ?></dt><dd><?= htmlspecialchars($row['value']) ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
                <?php if ($model['contacts'] !== []): ?><section class="eclipse-detail-contacts"><h3>Contactos</h3><ul><?php foreach ($model['contacts'] as $contact): ?><li><strong><?= htmlspecialchars($contact['label']) ?>:</strong> <span><?= htmlspecialchars($contact['time']) ?></span><?php if ($contact['extra'] !== []): ?> <span class="eclipse-contact-extra">(<?= htmlspecialchars($contact['body'] . ' ' . implode(' · ', $contact['extra'])) ?>)</span><?php endif; ?></li><?php endforeach; ?></ul></section><?php endif; ?>
                <?php if ($model['observation'] !== ''): ?><p class="event-note"><?= htmlspecialchars($model['observation']) ?></p><?php endif; ?>
            </section>
            <?php if ($model['visibility_map'] !== null): ?><section class="eclipse-detail-world-map"><h3>Visibilidad mundial</h3><p>Este mapa muestra las regiones del mundo desde las que puede observarse el eclipse.</p><a class="eclipse-world-map__image-link" href="<?= htmlspecialchars($model['visibility_map']['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars($model['visibility_map']['url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($model['visibility_map']['alt']) ?>" loading="lazy"></a><?php if ($model['visibility_map']['attribution'] !== ''): ?><p class="eclipse-world-map__attribution"><?= htmlspecialchars($model['visibility_map']['attribution']) ?></p><?php endif; ?><?php if ($model['visibility_map']['source_link'] !== null): ?><p class="eclipse-world-map__source"><a href="<?= htmlspecialchars($model['visibility_map']['source_link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Consultar fuente del mapa</a></p><?php endif; ?></section><?php endif; ?>
            <?php renderAstronomyCalendarLink($calendarEvent, 'calendar-action--eclipse-detail'); ?>
        </article>
    </template>
    <?php
}

function renderAstronomyEclipseModal(): void
{
    ?><dialog id="eclipses-modal" class="eclipses-modal" aria-labelledby="eclipses-modal-title"><div class="eclipses-modal__surface"><button type="button" class="eclipses-modal__close" data-eclipse-modal-close aria-label="Cerrar detalle">×</button><div id="eclipses-modal-content"></div></div></dialog><?php
}
