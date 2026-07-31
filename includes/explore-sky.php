<?php

function renderAstronomyExploreSky(?string $activeSection = null): void
{
    $items = [
        ['today', 'El cielo hoy', 'Luna, Sol y luz del día.', 'cielo-de-hoy.php'],
        ['tonight', 'El cielo esta noche', 'Planetas y estrellas visibles.', 'cielo-de-esta-noche.php'],
        ['calendar', 'Calendario solar y lunar', 'Horarios y próximas fases.', 'sol-y-luna.php'],
        ['events', 'Eventos lunares', 'Conjunciones y momentos destacados.', 'eventos.php'],
        ['eclipses', 'Eclipses', 'Cuándo ocurren y cómo se verán.', 'eclipses.php'],
        ['planner', 'Planificador', 'Direcciones para planificar tus fotos.', 'planificador.php'],
    ];
    ?>
    <section class="home-v2-card home-v2-explore" aria-labelledby="explore-sky-title">
        <div class="home-v2-card__heading"><h2 id="explore-sky-title">Explorá el cielo</h2></div>
        <p class="home-v2-explore__intro">Todo lo que necesitás para disfrutar, entender y fotografiar el cielo.</p>
        <div class="home-v2-explore__grid">
            <?php foreach ($items as [$theme, $title, $description, $url]): ?>
                <?php $isActive = $theme === $activeSection; ?>
                <a class="home-v2-explore__card home-v2-explore__card--<?= $theme ?><?= $isActive ? ' is-active' : '' ?>" href="<?= htmlspecialchars(astronomyInternalUrl($url), ENT_QUOTES, 'UTF-8') ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                    <span class="home-v2-explore__visual" aria-hidden="true"><span></span><i></i></span>
                    <span><strong><?= htmlspecialchars($title) ?></strong><small><?= htmlspecialchars($description) ?></small></span>
                    <span class="home-v2-explore__arrow" aria-hidden="true">→</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}
