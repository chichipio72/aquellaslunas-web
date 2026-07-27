<?php

require_once __DIR__ . '/site-sections.php';
require_once __DIR__ . '/location-context.php';
require_once __DIR__ . '/current-datetime.php';

function astronomyHeaderLocalDate(DateTimeImmutable $date): string
{
    $months = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
    return (int) $date->format('j') . ' de ' . $months[(int) $date->format('n')] . ' de ' . $date->format('Y');
}

function renderAstronomySiteHeader(string $currentSectionId, ?array $location = null): void
{
    $location ??= astronomyLocationContext();
    $localDate = get_current_datetime($location['timezone']);
    $simulationEnabled = astronomyLocalTimeSimulationEnabled();
    $simulationActive = astronomyCurrentDateTimeIsSimulated();
    ?>
    <header class="site-header">
        <div class="container header-inner">
            <a href="<?= htmlspecialchars(astronomySiteSectionUrl('home'), ENT_QUOTES, 'UTF-8') ?>" class="brand">
                <span class="brand-name">Aquellas Lunas</span>
                <span class="brand-tagline">Una Luna diferente cada noche</span>
            </a>
            <?php if ($simulationEnabled): ?>
            <details class="header-time-simulation" data-swipe-navigation-ignore>
                <summary><?= $simulationActive ? 'Tiempo simulado' : 'Modo de prueba' ?></summary>
                <form method="post" action="<?= htmlspecialchars((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'), ENT_QUOTES, 'UTF-8') ?>">
                    <label><span class="visually-hidden">Fecha simulada</span><input type="date" name="site_time_date" value="<?= htmlspecialchars($localDate->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label><span class="visually-hidden">Hora simulada</span><input type="time" name="site_time_clock" value="<?= htmlspecialchars($localDate->format('H:i'), ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <button type="submit">Aplicar</button>
                    <span class="header-time-simulation__shifts" aria-label="Mover fecha simulada">
                        <button type="submit" name="site_time_shift" value="-7" data-site-time-shift="-7" aria-label="Retroceder una semana">−1 sem</button>
                        <button type="submit" name="site_time_shift" value="-1" data-site-time-shift="-1" aria-label="Retroceder un día">−1 día</button>
                        <button type="submit" name="site_time_shift" value="1" data-site-time-shift="1" aria-label="Avanzar un día">+1 día</button>
                        <button type="submit" name="site_time_shift" value="7" data-site-time-shift="7" aria-label="Avanzar una semana">+1 sem</button>
                    </span>
                    <?php if ($simulationActive): ?><button type="submit" name="site_time_reset" value="1" formnovalidate>Usar hora real</button><?php endif; ?>
                </form>
            </details>
            <?php endif; ?>
            <div class="header-context">
                <a class="header-location" href="<?= htmlspecialchars(astronomyInternalUrl('ubicacion.php'), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="visually-hidden">Ubicación activa: </span><?= htmlspecialchars($location['name'], ENT_QUOTES, 'UTF-8') ?>
                </a>
                <span class="header-context-separator" aria-hidden="true">·</span>
                <time datetime="<?= htmlspecialchars($localDate->format(DateTimeInterface::ATOM), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(astronomyHeaderLocalDate($localDate) . ', ' . $localDate->format('H:i'), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="header-date-long"><?= htmlspecialchars(astronomyHeaderLocalDate($localDate), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="header-date-short" aria-hidden="true"><?= htmlspecialchars($localDate->format('d/m/Y'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span aria-hidden="true"> · </span><?= htmlspecialchars($localDate->format('H:i'), ENT_QUOTES, 'UTF-8') ?>
                </time>
            </div>
            <button class="menu-toggle" type="button" aria-controls="site-menu-panel" aria-expanded="false" data-swipe-navigation-ignore>
                <span aria-hidden="true">☰</span><span class="visually-hidden">Abrir menú</span>
            </button>
        </div>
    </header>
    <div class="menu-backdrop" data-menu-close data-swipe-navigation-ignore hidden></div>
    <aside id="site-menu-panel" class="site-menu-panel" aria-label="Menú del sitio" aria-hidden="true" data-swipe-navigation-ignore>
        <button class="menu-close" type="button" data-menu-close aria-label="Cerrar menú">×</button>
        <?php renderAstronomySiteNavigation($currentSectionId); ?>
    </aside>
    <script>window.siteTimeContext=<?= json_encode([
        'simulated' => $simulationActive,
        'now' => $localDate->format(DateTimeInterface::ATOM),
        'timezone' => $location['timezone'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    document.querySelectorAll('[data-site-time-shift]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const form = button.closest('form');
            const input = form?.elements.site_time_date;
            const match = input?.value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            const shift = Number(button.dataset.siteTimeShift);
            if (!form || !match || !Number.isInteger(shift)) return;
            const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
            date.setUTCDate(date.getUTCDate() + shift);
            input.value = date.toISOString().slice(0, 10);
            form.requestSubmit();
        });
    });</script>
    <?php
}
