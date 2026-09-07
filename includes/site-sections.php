<?php

require_once __DIR__ . '/api-config.php';
require_once __DIR__ . '/current-datetime.php';
require_once __DIR__ . '/asset-url.php';
require_once __DIR__ . '/site-configuration.php';
require_once __DIR__ . '/site-menu.php';

function astronomySiteSections(?bool $adminContext = null): array
{
    $adminContext ??= storeAdminHasValidSessionCookie();
    $menu = astronomySiteMenuLoad();
    $sections = [];
    foreach (astronomySiteSectionCatalog() as $sectionId => $definition) {
        if ($sectionId === 'home') {
            $sections[$sectionId] = $definition + ['order' => PHP_INT_MIN, 'group_id' => null, 'group_label' => null, 'group_order' => PHP_INT_MIN, 'menu_enabled' => true];
            continue;
        }
        $settings = $menu['sections'][$sectionId] ?? null;
        $group = is_array($settings) ? ($menu['groups'][$settings['group_id']] ?? null) : null;
        $visible = is_array($settings) && is_array($group)
            ? (($adminContext ? $settings['admin_visible'] : $settings['public_visible']) === true)
            : false;
        $sections[$sectionId] = $definition + [
            'order' => (int) ($settings['sort_order'] ?? PHP_INT_MAX),
            'group_id' => $settings['group_id'] ?? null,
            'group_label' => $group['label'] ?? null,
            'group_order' => (int) ($group['sort_order'] ?? PHP_INT_MAX),
            'menu_enabled' => $visible,
        ];
    }
    uasort($sections, static fn(array $first, array $second): int => [$first['group_order'], $first['order'], $first['id']] <=> [$second['group_order'], $second['order'], $second['id']]);
    return $sections;
}

function astronomySiteSection(string $sectionId): ?array
{
    return astronomySiteSections()[$sectionId] ?? null;
}

function astronomySiteSectionLabel(string $sectionId): string
{
    $section = astronomySiteSection($sectionId);
    return $section !== null ? (string) $section['label'] : '';
}

function astronomySiteSectionUrl(string $sectionId, string $rootPrefix = ''): string
{
    $section = astronomySiteSection($sectionId);
    return $section !== null ? astronomyInternalUrl($rootPrefix . $section['url']) : astronomyInternalUrl($rootPrefix . 'index.php');
}

function renderAstronomySiteNavigation(string $currentSectionId, string $rootPrefix = '', ?bool $adminContext = null): void
{
    ?><nav class="site-nav" aria-label="Navegación principal"><?php
    $currentGroup = null;
    foreach (astronomySiteSections($adminContext) as $section) {
        if ($section['menu_enabled'] !== true) {
            continue;
        }
        if ($section['id'] !== 'home' && $section['group_id'] !== $currentGroup) {
            $currentGroup = $section['group_id'];
            ?><span class="site-nav__group-title"><?= htmlspecialchars((string) $section['group_label'], ENT_QUOTES, 'UTF-8') ?></span><?php
        }
        if (($section['type'] ?? '') === 'install') {
            ?><button class="site-nav__action site-nav__group-item" type="button" data-install-trigger data-install-source="menu" aria-label="Instalar Aquellas Lunas" hidden>Instalar Aquellas Lunas</button><?php
            continue;
        }
        $isCurrent = $section['id'] === $currentSectionId;
        $linkClass = trim(($section['id'] === 'home' ? '' : 'site-nav__group-item ') . ($isCurrent ? 'is-active' : ''));
        ?>
        <a href="<?= htmlspecialchars(astronomyInternalUrl($rootPrefix . $section['url']), ENT_QUOTES, 'UTF-8') ?>"<?= $linkClass !== '' ? ' class="' . htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?><?= $isCurrent ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($section['label']) ?></a><?php
    }
    ?></nav><?php
}

function astronomyMobileSwipeFeatureEnabled(): bool
{
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }
    try {
        $enabled = loadMobileSwipeNavigationEnabled();
    } catch (RuntimeException $exception) {
        error_log('Aquellas Lunas mobile swipe configuration error: ' . $exception->getMessage());
        $enabled = false;
    }
    return $enabled;
}

function astronomyMobileSwipeHintEnabled(): bool
{
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }
    try {
        $enabled = loadMobileSwipeNavigationHintEnabled();
    } catch (RuntimeException $exception) {
        error_log('Aquellas Lunas mobile swipe hint configuration error: ' . $exception->getMessage());
        $enabled = false;
    }
    return $enabled;
}

function astronomyMobileSwipeDebugEnabled(): bool
{
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }
    try {
        $enabled = loadMobileSwipeNavigationDebugEnabled();
    } catch (RuntimeException $exception) {
        error_log('Aquellas Lunas mobile swipe debug configuration error: ' . $exception->getMessage());
        $enabled = false;
    }
    return $enabled;
}

function astronomyMobileSwipeContext(string $currentSectionId): ?array
{
    if (!astronomyMobileSwipeFeatureEnabled()) {
        return null;
    }
    $swipeSections = array_values(array_filter(
        astronomySiteSections(),
        static fn(array $section): bool => $section['swipe_enabled'] === true
    ));
    usort($swipeSections, static fn(array $first, array $second): int => ($first['swipe_order'] ?? $first['order']) <=> ($second['swipe_order'] ?? $second['order']));
    $currentIndex = null;
    foreach ($swipeSections as $index => $section) {
        if ($section['id'] === $currentSectionId) {
            $currentIndex = $index;
            break;
        }
    }
    if ($currentIndex === null) {
        return null;
    }
    return [
        'section_id' => $swipeSections[$currentIndex]['id'],
        'section_label' => $swipeSections[$currentIndex]['label'],
        'previous_url' => $currentIndex > 0 ? astronomyInternalUrl($swipeSections[$currentIndex - 1]['url']) : null,
        'next_url' => $currentIndex < count($swipeSections) - 1 ? astronomyInternalUrl($swipeSections[$currentIndex + 1]['url']) : null,
        'hint_enabled' => astronomyMobileSwipeHintEnabled(),
        'diagnostics_enabled' => astronomyTimingsEnabled() && astronomyMobileSwipeDebugEnabled(),
    ];
}

function astronomyMobileSwipeNavigationAttributes(string $currentSectionId): string
{
    $context = astronomyMobileSwipeContext($currentSectionId);
    if ($context === null) {
        return '';
    }
    $attributes = ' data-mobile-swipe-navigation="enabled"';
    $attributes .= ' data-swipe-section="' . htmlspecialchars($context['section_label'], ENT_QUOTES, 'UTF-8') . '"';
    if ($context['previous_url'] !== null) {
        $attributes .= ' data-swipe-previous-url="' . htmlspecialchars($context['previous_url'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if ($context['next_url'] !== null) {
        $attributes .= ' data-swipe-next-url="' . htmlspecialchars($context['next_url'], ENT_QUOTES, 'UTF-8') . '"';
    }
    $attributes .= ' data-swipe-hint-enabled="' . ($context['hint_enabled'] ? 'true' : 'false') . '"';
    $attributes .= ' data-swipe-diagnostics-enabled="' . ($context['diagnostics_enabled'] ? 'true' : 'false') . '"';
    return $attributes;
}

function renderAstronomyMobileSwipeNavigationScript(string $currentSectionId): void
{
    if (astronomyMobileSwipeContext($currentSectionId) === null) {
        return;
    }
    ?><script src="<?= htmlspecialchars(versionedAssetUrl('assets/js/mobile-swipe-navigation.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script><?php
}
