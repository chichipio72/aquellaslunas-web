<?php

require_once __DIR__ . '/api-config.php';
require_once __DIR__ . '/current-datetime.php';
require_once __DIR__ . '/asset-url.php';

function astronomySiteSections(): array
{
    $sections = [
        'home' => ['id' => 'home', 'label' => 'Inicio', 'url' => 'index.php', 'order' => 10, 'menu_enabled' => true, 'swipe_enabled' => true],
        'tonight' => ['id' => 'tonight', 'label' => 'Esta noche', 'url' => 'cielo-de-esta-noche.php', 'order' => 15, 'menu_enabled' => true, 'swipe_enabled' => true],
        'sun_moon' => ['id' => 'sun_moon', 'label' => 'Sol y Luna', 'url' => 'sol-y-luna.php', 'order' => 20, 'menu_enabled' => true, 'swipe_enabled' => true],
        'planner' => ['id' => 'planner', 'label' => 'Planificador', 'url' => 'planificador.php', 'order' => 30, 'menu_enabled' => true, 'swipe_enabled' => true],
        'events' => ['id' => 'events', 'label' => 'Eventos', 'url' => 'eventos.php', 'order' => 40, 'menu_enabled' => true, 'swipe_enabled' => true],
        'eclipses' => ['id' => 'eclipses', 'label' => 'Eclipses', 'url' => 'eclipses.php', 'order' => 42, 'menu_enabled' => true, 'swipe_enabled' => false],
        'location' => ['id' => 'location', 'label' => 'Ubicación', 'url' => 'ubicacion.php', 'order' => 45, 'menu_enabled' => false, 'swipe_enabled' => false],
        // Ocultación temporal mientras la tienda continúa en pruebas; la URL directa se conserva.
        'gallery' => ['id' => 'gallery', 'label' => 'Galería', 'url' => 'galeria.php', 'order' => 50, 'menu_enabled' => false, 'swipe_enabled' => false],
        'about' => ['id' => 'about', 'label' => 'Acerca del sitio', 'url' => 'acerca-del-sitio.php', 'order' => 60, 'menu_enabled' => true, 'swipe_enabled' => false],
    ];
    uasort($sections, static fn(array $first, array $second): int => $first['order'] <=> $second['order']);
    return $sections;
}

function astronomySiteSection(string $sectionId): ?array
{
    return astronomySiteSections()[$sectionId] ?? null;
}

function astronomySiteSectionUrl(string $sectionId): string
{
    $section = astronomySiteSection($sectionId);
    return $section !== null ? astronomyInternalUrl($section['url']) : astronomyInternalUrl('index.php');
}

function renderAstronomySiteNavigation(string $currentSectionId): void
{
    ?><nav class="site-nav" aria-label="Navegación principal"><?php
    foreach (astronomySiteSections() as $section) {
        if ($section['menu_enabled'] !== true) {
            continue;
        }
        $isCurrent = $section['id'] === $currentSectionId;
        ?><a href="<?= htmlspecialchars(astronomyInternalUrl($section['url']), ENT_QUOTES, 'UTF-8') ?>"<?= $isCurrent ? ' class="is-active" aria-current="page"' : '' ?>><?= htmlspecialchars($section['label']) ?></a><?php
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
