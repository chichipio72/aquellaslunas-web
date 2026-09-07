<?php

declare(strict_types=1);

const ASTRONOMY_SOURCES_CREDITS_CONTENT_PATH = __DIR__ . '/../content-data/fuentes-y-creditos.json';

function astronomySourcesCreditsString(mixed $value, int $maximum = 2500): ?string
{
    if (!is_string($value)) return null;
    $value = trim($value);
    if ($value === '' || strlen($value) > $maximum || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) return null;
    return $value;
}

function astronomySourcesCreditsExternalUrl(mixed $value): ?string
{
    $url = astronomySourcesCreditsString($value, 500);
    if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || ($parts['host'] ?? '') === '' || isset($parts['user']) || isset($parts['pass'])) return null;
    return $url;
}

/** @return list<array{type:string,text?:string,items?:list<string>}>|null */
function astronomySourcesCreditsBlocks(mixed $blocks): ?array
{
    if ($blocks === null) return [];
    if (!is_array($blocks)) return null;
    $validated = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) return null;
        $type = (string) ($block['type'] ?? '');
        if (in_array($type, ['paragraph', 'notice'], true)) {
            $text = astronomySourcesCreditsString($block['text'] ?? null);
            if ($text === null) return null;
            $validated[] = ['type' => $type, 'text' => $text];
            continue;
        }
        if ($type !== 'list' || !is_array($block['items'] ?? null) || $block['items'] === []) return null;
        $items = [];
        foreach ($block['items'] as $item) {
            $text = astronomySourcesCreditsString($item, 500);
            if ($text === null) return null;
            $items[] = $text;
        }
        $validated[] = ['type' => 'list', 'items' => $items];
    }
    return $validated;
}

/** @return array<string,mixed>|null */
function astronomySourcesCreditsContentLoad(?string $path = null): ?array
{
    $path ??= ASTRONOMY_SOURCES_CREDITS_CONTENT_PATH;
    if (!is_file($path) || !is_readable($path)) {
        error_log('Aquellas Lunas sources and credits content missing: ' . $path);
        return null;
    }
    try {
        $raw = file_get_contents($path);
        if (!is_string($raw)) throw new RuntimeException('No se pudo leer el archivo.');
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['version'] ?? null) !== 1 || !is_array($data['page'] ?? null)) throw new RuntimeException('Versión o raíz inválida.');
        $page = $data['page'];
        $eyebrow = astronomySourcesCreditsString($page['eyebrow'] ?? null, 120);
        $title = astronomySourcesCreditsString($page['title'] ?? null, 180);
        if ($eyebrow === null || $title === null || !is_array($page['introduction'] ?? null) || $page['introduction'] === []) throw new RuntimeException('Encabezado inválido.');
        $introduction = [];
        foreach ($page['introduction'] as $paragraph) {
            $text = astronomySourcesCreditsString($paragraph);
            if ($text === null) throw new RuntimeException('Introducción inválida.');
            $introduction[] = $text;
        }
        if (!is_array($data['sections'] ?? null) || $data['sections'] === []) throw new RuntimeException('Faltan secciones.');
        $sections = [];
        $ids = [];
        foreach ($data['sections'] as $section) {
            if (!is_array($section)) throw new RuntimeException('Sección inválida.');
            $id = astronomySourcesCreditsString($section['id'] ?? null, 80);
            $sectionTitle = astronomySourcesCreditsString($section['title'] ?? null, 180);
            $blocks = astronomySourcesCreditsBlocks($section['blocks'] ?? null);
            if ($id === null || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) !== 1 || isset($ids[$id]) || $sectionTitle === null || $blocks === null) throw new RuntimeException('Sección incompleta.');
            $ids[$id] = true;
            $sources = [];
            if (isset($section['sources'])) {
                if (!is_array($section['sources']) || $section['sources'] === []) throw new RuntimeException('Fuentes inválidas.');
                foreach ($section['sources'] as $source) {
                    if (!is_array($source)) throw new RuntimeException('Fuente inválida.');
                    $name = astronomySourcesCreditsString($source['name'] ?? null, 200);
                    if ($name === null || !is_array($source['description'] ?? null) || $source['description'] === [] || !is_array($source['links'] ?? null) || $source['links'] === []) throw new RuntimeException('Fuente incompleta.');
                    $description = [];
                    foreach ($source['description'] as $paragraph) {
                        $text = astronomySourcesCreditsString($paragraph);
                        if ($text === null) throw new RuntimeException('Descripción inválida.');
                        $description[] = $text;
                    }
                    $links = [];
                    foreach ($source['links'] as $link) {
                        if (!is_array($link)) throw new RuntimeException('Enlace inválido.');
                        $label = astronomySourcesCreditsString($link['label'] ?? null, 180);
                        $url = astronomySourcesCreditsExternalUrl($link['url'] ?? null);
                        if ($label === null || $url === null) throw new RuntimeException('Enlace externo inválido.');
                        $links[] = ['label' => $label, 'url' => $url];
                    }
                    $assets = [];
                    if (isset($source['assets'])) {
                        if (!is_array($source['assets']) || $source['assets'] === []) throw new RuntimeException('Recursos inválidos.');
                        foreach ($source['assets'] as $asset) {
                            $text = astronomySourcesCreditsString($asset, 240);
                            if ($text === null) throw new RuntimeException('Recurso inválido.');
                            $assets[] = $text;
                        }
                    }
                    $sources[] = ['name' => $name, 'description' => $description, 'links' => $links, 'assets' => $assets];
                }
            }
            if ($blocks === [] && $sources === []) throw new RuntimeException('Sección vacía.');
            $sections[] = ['id' => $id, 'title' => $sectionTitle, 'blocks' => $blocks, 'sources' => $sources];
        }
        return ['version' => 1, 'page' => ['eyebrow' => $eyebrow, 'title' => $title, 'introduction' => $introduction], 'sections' => $sections];
    } catch (Throwable $exception) {
        error_log('Aquellas Lunas sources and credits content invalid: ' . $exception->getMessage());
        return null;
    }
}

function renderAstronomySourcesCreditsBlocks(array $blocks): void
{
    foreach ($blocks as $block) {
        if ($block['type'] === 'paragraph') {
            ?><p><?= htmlspecialchars($block['text'], ENT_QUOTES, 'UTF-8') ?></p><?php
        } elseif ($block['type'] === 'notice') {
            ?><p class="sources-credits__notice"><?= htmlspecialchars($block['text'], ENT_QUOTES, 'UTF-8') ?></p><?php
        } else {
            ?><ul class="sources-credits__list"><?php foreach ($block['items'] as $item): ?><li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul><?php
        }
    }
}

function renderAstronomySourcesCreditsContent(array $content): void
{
    ?><header class="capabilities-hero sources-credits__hero">
        <p class="eyebrow"><?= htmlspecialchars($content['page']['eyebrow'], ENT_QUOTES, 'UTF-8') ?></p>
        <h1><?= htmlspecialchars($content['page']['title'], ENT_QUOTES, 'UTF-8') ?></h1>
        <?php foreach ($content['page']['introduction'] as $paragraph): ?><p><?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?>
    </header>
    <div class="sources-credits__sections"><?php foreach ($content['sections'] as $section): ?>
        <section class="card sources-credits__section" id="<?= htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8') ?>">
            <h2><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <?php renderAstronomySourcesCreditsBlocks($section['blocks']); ?>
            <?php if ($section['sources'] !== []): ?><div class="sources-credits__sources"><?php foreach ($section['sources'] as $source): ?>
                <article class="sources-credits__source">
                    <h3><?= htmlspecialchars($source['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <?php foreach ($source['description'] as $paragraph): ?><p><?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?>
                    <?php if ($source['assets'] !== []): ?><ul class="sources-credits__assets"><?php foreach ($source['assets'] as $asset): ?><li><code><?= htmlspecialchars($asset, ENT_QUOTES, 'UTF-8') ?></code></li><?php endforeach; ?></ul><?php endif; ?>
                    <div class="sources-credits__links"><?php foreach ($source['links'] as $link): ?><a href="<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">↗</span></a><?php endforeach; ?></div>
                </article>
            <?php endforeach; ?></div><?php endif; ?>
        </section>
    <?php endforeach; ?></div><?php
}
