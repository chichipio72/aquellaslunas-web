<?php

declare(strict_types=1);

require_once __DIR__ . '/timezone-geo/GeoBuf/PbfReader.php';
require_once __DIR__ . '/timezone-geo/GeoBuf/Decoder.php';
require_once __DIR__ . '/timezone-geo/GeometryUtils.php';
require_once __DIR__ . '/timezone-geo/OceanUtils.php';
require_once __DIR__ . '/timezone-geo/Finder.php';
require_once __DIR__ . '/timezone-geo/FinderFactory.php';

/**
 * Resuelve el identificador IANA vigente para coordenadas geográficas.
 *
 * En una frontera el dataset puede devolver más de una zona. El primer
 * polígono del índice es la resolución determinista usada por geo-tz.
 */
function astronomyResolveTimezone(float $latitude, float $longitude): string
{
    $zones = \GeoTz\FinderFactory::forDataset(\GeoTz\FinderFactory::DATASET_1970)
        ->find($latitude, $longitude);
    foreach ($zones as $zone) {
        $canonical = astronomyLocationTimezone($zone);
        if ($canonical !== null) {
            return $canonical;
        }
    }

    throw new RuntimeException('No se pudo resolver una zona horaria IANA para las coordenadas.');
}
