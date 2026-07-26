<?php

function renderAstronomyLocationMap(array $location, string $label = 'Mapa para elegir la ubicación del observador', array $attributes = []): void
{
    $extraAttributes = '';
    foreach ($attributes as $name => $value) {
        if (preg_match('/^data-[a-z0-9-]+$/', (string) $name) === 1) {
            $extraAttributes .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
        }
    }
    ?>
    <div
        id="observer-location-map"
        class="location-map"
        data-observer-map
        data-swipe-navigation-ignore
        data-latitude="<?= htmlspecialchars((string) $location['latitude'], ENT_QUOTES, 'UTF-8') ?>"
        data-longitude="<?= htmlspecialchars((string) $location['longitude'], ENT_QUOTES, 'UTF-8') ?>"
        aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
        <?= $extraAttributes ?>
    ></div>
    <?php
}
