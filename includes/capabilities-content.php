<?php

declare(strict_types=1);

require_once __DIR__ . '/api-config.php';
require_once __DIR__ . '/astronomy-icon.php';

const ASTRONOMY_CAPABILITIES_CONTENT_PATH = __DIR__ . '/../content-data/que-podes-hacer.json';

function astronomyCapabilitiesString(mixed $value, int $maximum = 2000): ?string
{
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '' || strlen($value) > $maximum || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) return null;
    return $value;
}

function astronomyCapabilitiesUrl(mixed $value): ?string
{
    $url = astronomyCapabilitiesString($value, 300);
    if ($url === null || str_contains($url, '..') || str_contains($url, '://') || str_starts_with($url, '//')) return null;
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/?=&%#-]*$/', $url) === 1 ? $url : null;
}

/** @return list<array<string,mixed>>|null */
function astronomyCapabilitiesBlocks(mixed $blocks): ?array
{
    if (!is_array($blocks) || $blocks === []) return null;
    $validated = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) return null;
        $type = (string) ($block['type'] ?? '');
        if ($type === 'paragraph') {
            $text = astronomyCapabilitiesString($block['text'] ?? null);
            if ($text === null) return null;
            $validated[] = ['type' => 'paragraph', 'text' => $text];
            continue;
        }
        if ($type === 'list' && is_array($block['items'] ?? null) && $block['items'] !== []) {
            $items = [];
            foreach ($block['items'] as $item) {
                $text = astronomyCapabilitiesString($item);
                if ($text === null) return null;
                $items[] = $text;
            }
            $validated[] = ['type' => 'list', 'items' => $items];
            continue;
        }
        return null;
    }
    return $validated;
}

/** @return array<string,mixed>|null */
function astronomyCapabilitiesContentLoad(?string $path = null): ?array
{
    $path ??= ASTRONOMY_CAPABILITIES_CONTENT_PATH;
    if (!is_file($path) || !is_readable($path)) {
        error_log('Aquellas Lunas capabilities content missing: ' . $path);
        return null;
    }
    try {
        $raw = file_get_contents($path);
        if (!is_string($raw)) throw new RuntimeException('No se pudo leer el archivo.');
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['version'] ?? null) !== 1) throw new RuntimeException('Versión o raíz inválida.');
        $page = $data['page'] ?? null;
        if (!is_array($page)) throw new RuntimeException('Falta page.');
        $eyebrow = astronomyCapabilitiesString($page['eyebrow'] ?? null, 120);
        $title = astronomyCapabilitiesString($page['title'] ?? null, 250);
        $introduction = $page['introduction'] ?? null;
        if ($eyebrow === null || $title === null || !is_array($introduction) || $introduction === []) throw new RuntimeException('Hero inválido.');
        $intro = [];
        foreach ($introduction as $paragraph) {
            $text = astronomyCapabilitiesString($paragraph);
            if ($text === null) throw new RuntimeException('Introducción inválida.');
            $intro[] = $text;
        }
        $mainLabel = astronomyCapabilitiesString($data['main_label'] ?? null, 180);
        $featureLabel = astronomyCapabilitiesString($data['feature_label'] ?? null, 180);
        if ($mainLabel === null || $featureLabel === null || !is_array($data['main_cards'] ?? null) || $data['main_cards'] === [] || !is_array($data['feature_cards'] ?? null) || $data['feature_cards'] === []) throw new RuntimeException('Colecciones inválidas.');
        $mainCards = [];
        foreach ($data['main_cards'] as $card) {
            if (!is_array($card)) throw new RuntimeException('Tarjeta principal inválida.');
            $id = astronomyCapabilitiesString($card['id'] ?? null, 80);
            $cardTitle = astronomyCapabilitiesString($card['title'] ?? null, 180);
            $blocks = astronomyCapabilitiesBlocks($card['blocks'] ?? null);
            $icon = $card['icon'] ?? null;
            $link = $card['link'] ?? null;
            if ($id === null || preg_match('/^[a-z0-9-]+$/', $id) !== 1 || $cardTitle === null || $blocks === null || !is_array($icon) || ($link !== null && !is_array($link))) throw new RuntimeException('Tarjeta principal incompleta.');
            $iconType = astronomyCapabilitiesString($icon['type'] ?? null, 60);
            $iconSubtype = isset($icon['subtype']) ? astronomyCapabilitiesString($icon['subtype'], 60) : null;
            if ($iconType === null || preg_match('/^[a-z0-9_]+$/', $iconType) !== 1 || ($iconSubtype !== null && preg_match('/^[a-z0-9_]+$/', $iconSubtype) !== 1)) throw new RuntimeException('Icono inválido.');
            $validatedCard = ['id' => $id, 'title' => $cardTitle, 'icon' => ['type' => $iconType] + ($iconSubtype !== null ? ['subtype' => $iconSubtype] : []), 'blocks' => $blocks];
            if ($link !== null) {
                $linkText = astronomyCapabilitiesString($link['text'] ?? null, 180);
                $linkUrl = astronomyCapabilitiesUrl($link['url'] ?? null);
                if ($linkText === null || $linkUrl === null) throw new RuntimeException('Enlace inválido.');
                $validatedCard['link'] = ['text' => $linkText, 'url' => $linkUrl];
            }
            $mainCards[] = $validatedCard;
        }
        $allowedStyles = ['location', 'calculation', 'forecast', 'technical', 'calendar', 'install'];
        $featureCards = [];
        foreach ($data['feature_cards'] as $card) {
            if (!is_array($card)) throw new RuntimeException('Tarjeta especial inválida.');
            $id = astronomyCapabilitiesString($card['id'] ?? null, 80);
            $style = (string) ($card['style'] ?? '');
            $cardTitle = astronomyCapabilitiesString($card['title'] ?? null, 180);
            $blocks = astronomyCapabilitiesBlocks($card['blocks'] ?? null);
            $action = $card['action'] ?? null;
            if ($id === null || preg_match('/^[a-z0-9-]+$/', $id) !== 1 || !in_array($style, $allowedStyles, true) || $cardTitle === null || $blocks === null || !is_array($action)) throw new RuntimeException('Tarjeta especial incompleta.');
            $kind = (string) ($action['kind'] ?? '');
            $actionText = astronomyCapabilitiesString($action['text'] ?? null, 180);
            if ($actionText === null || !in_array($kind, ['link', 'install'], true)) throw new RuntimeException('Acción inválida.');
            $validatedAction = ['kind' => $kind, 'text' => $actionText];
            if ($kind === 'link') {
                $actionUrl = astronomyCapabilitiesUrl($action['url'] ?? null);
                if ($actionUrl === null) throw new RuntimeException('URL de acción inválida.');
                $validatedAction['url'] = $actionUrl;
            }
            $featureCards[] = ['id' => $id, 'style' => $style, 'title' => $cardTitle, 'blocks' => $blocks, 'action' => $validatedAction];
        }
        $closing = $data['closing'] ?? null;
        if (!is_array($closing)) throw new RuntimeException('Falta cierre.');
        $closingTitle = astronomyCapabilitiesString($closing['title'] ?? null, 180);
        $closingParagraphs = $closing['paragraphs'] ?? null;
        if ($closingTitle === null || !is_array($closingParagraphs) || $closingParagraphs === []) throw new RuntimeException('Cierre inválido.');
        $validatedClosing = [];
        foreach ($closingParagraphs as $paragraph) {
            $text = astronomyCapabilitiesString($paragraph);
            if ($text === null) throw new RuntimeException('Párrafo final inválido.');
            $validatedClosing[] = $text;
        }
        return ['version' => 1, 'page' => ['eyebrow' => $eyebrow, 'title' => $title, 'introduction' => $intro], 'main_label' => $mainLabel, 'feature_label' => $featureLabel, 'main_cards' => $mainCards, 'feature_cards' => $featureCards, 'closing' => ['title' => $closingTitle, 'paragraphs' => $validatedClosing]];
    } catch (Throwable $exception) {
        error_log('Aquellas Lunas capabilities content invalid: ' . $exception->getMessage());
        return null;
    }
}

function renderAstronomyCapabilitiesBlocks(array $blocks): void
{
    foreach ($blocks as $block) {
        if ($block['type'] === 'paragraph') {
            ?><p><?= htmlspecialchars($block['text'], ENT_QUOTES, 'UTF-8') ?></p><?php
        } elseif ($block['type'] === 'list') {
            ?><ul><?php foreach ($block['items'] as $item): ?><li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul><?php
        }
    }
}

function renderAstronomyCapabilitiesContent(array $content): void
{
    ?><header class="capabilities-hero">
        <p class="eyebrow"><?= htmlspecialchars($content['page']['eyebrow'], ENT_QUOTES, 'UTF-8') ?></p>
        <h1><?= htmlspecialchars($content['page']['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <?php foreach ($content['page']['introduction'] as $paragraph): ?><p><?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?>
    </header>
    <section class="capabilities-grid" aria-label="<?= htmlspecialchars($content['main_label'], ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($content['main_cards'] as $card): ?><article class="card capability-card">
            <header class="capability-card__heading"><?php renderAstronomyIcon($card['icon'], -34.53, 'capability-card__icon'); ?><h2><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h2></header>
            <?php renderAstronomyCapabilitiesBlocks($card['blocks']); ?>
            <?php if (isset($card['link'])): ?><a class="capability-card__link" href="<?= htmlspecialchars(astronomyInternalUrl($card['link']['url']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($card['link']['text'], ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
        </article><?php endforeach; ?>
    </section>
    <section class="capabilities-special" aria-label="<?= htmlspecialchars($content['feature_label'], ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($content['feature_cards'] as $card): ?><article class="card capability-feature capability-feature--<?= htmlspecialchars($card['style'], ENT_QUOTES, 'UTF-8') ?>">
            <h2><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <?php renderAstronomyCapabilitiesBlocks($card['blocks']); ?>
            <?php if ($card['action']['kind'] === 'install'): ?><button class="button" type="button" data-install-trigger data-install-source="capabilities" hidden><?= htmlspecialchars($card['action']['text'], ENT_QUOTES, 'UTF-8') ?></button><?php else: ?><a href="<?= htmlspecialchars(astronomyInternalUrl($card['action']['url']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($card['action']['text'], ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
        </article><?php endforeach; ?>
    </section>
    <section class="card capabilities-closing"><h2><?= htmlspecialchars($content['closing']['title'], ENT_QUOTES, 'UTF-8') ?></h2><?php foreach ($content['closing']['paragraphs'] as $paragraph): ?><p><?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?></section><?php
}
