<?php

declare(strict_types=1);

const ASTRONOMY_PAGE_FRESHNESS_THRESHOLD_SECONDS = 3 * 60 * 60;

function renderAstronomyPageFreshnessNotice(): void
{
    ?>
    <aside class="page-freshness-notice" data-page-freshness data-page-freshness-threshold-seconds="<?= ASTRONOMY_PAGE_FRESHNESS_THRESHOLD_SECONDS ?>" role="status" hidden>
        <div>
            <strong>Estos datos pueden estar desactualizados.</strong>
            <p>Esta página se cargó hace más de 3 horas.</p>
        </div>
        <button type="button" class="button button-primary" data-page-freshness-reload>Actualizar</button>
    </aside>
    <?php
}
