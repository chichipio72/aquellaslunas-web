<?php

declare(strict_types=1);

namespace GeoTz;

use GeoTz\GeoBuf\Decoder as GeoBufDecoder;
final class Finder
{
    /** @var array<string, mixed> */
    private array $tzData;
    private string $featureFilePath;
    /** @var array<string, array<string, mixed>> */
    private array $featureCache = [];

    /**
     * @param array<string, mixed> $tzData
     */
    public function __construct(array $tzData, string $featureFilePath)
    {
        $this->tzData = $tzData;
        $this->featureFilePath = $featureFilePath;
    }

    /**
     * @return array<int, string>
     */
    public function find(float $lat, float $lon): array
    {
        return $this->findUsingDataset($lat, $lon);
    }

    /**
     * @return array<int, string>
     */
    private function findUsingDataset(float $lat, float $lon): array
    {
        $originalLon = $lon;

        if (is_nan($lat) || $lat > 90 || $lat < -90) {
            throw new \InvalidArgumentException('Invalid latitude: ' . $lat);
        }

        if (is_nan($lon) || $lon > 180 || $lon < -180) {
            throw new \InvalidArgumentException('Invalid longitude: ' . $lon);
        }

        if ($lat === 90.0) {
            return array_map(static fn (array $zone): string => $zone['tzid'], OceanUtils::getOceanZones());
        }

        if ($lat >= 89.9999) {
            $lat = 89.9999;
        } elseif ($lat <= -89.9999) {
            $lat = -89.9999;
        }

        if ($lon >= 179.9999) {
            $lon = 179.9999;
        } elseif ($lon <= -179.9999) {
            $lon = -179.9999;
        }

        $quadData = [
            'top' => 89.9999,
            'bottom' => -89.9999,
            'left' => -179.9999,
            'right' => 179.9999,
            'midLat' => 0.0,
            'midLon' => 0.0,
        ];
        $quadPos = '';
        $curTzData = $this->tzData['lookup'];

        while (true) {
            if ($lat >= $quadData['midLat'] && $lon >= $quadData['midLon']) {
                $nextQuad = 'a';
                $quadData['bottom'] = $quadData['midLat'];
                $quadData['left'] = $quadData['midLon'];
            } elseif ($lat >= $quadData['midLat'] && $lon < $quadData['midLon']) {
                $nextQuad = 'b';
                $quadData['bottom'] = $quadData['midLat'];
                $quadData['right'] = $quadData['midLon'];
            } elseif ($lat < $quadData['midLat'] && $lon < $quadData['midLon']) {
                $nextQuad = 'c';
                $quadData['top'] = $quadData['midLat'];
                $quadData['right'] = $quadData['midLon'];
            } else {
                $nextQuad = 'd';
                $quadData['top'] = $quadData['midLat'];
                $quadData['left'] = $quadData['midLon'];
            }

            $curTzData = is_array($curTzData) ? ($curTzData[$nextQuad] ?? null) : null;
            $quadPos .= $nextQuad;

            if ($curTzData === null) {
                return OceanUtils::getTimezoneAtSea($originalLon);
            }

            if (is_array($curTzData) && isset($curTzData['pos'], $curTzData['len']) && $curTzData['pos'] >= 0) {
                $geoJson = $this->featureCache[$quadPos] ?? null;
                if (!is_array($geoJson)) {
                    $geoJson = $this->loadFeatures((int) $curTzData['pos'], (int) $curTzData['len']);
                    $this->featureCache[$quadPos] = $geoJson;
                }

                $timezonesContainingPoint = [];
                $features = $geoJson['features'] ?? [];
                foreach ($features as $feature) {
                    $geometry = $feature['geometry'] ?? null;
                    if ($geometry && GeometryUtils::pointInGeometry([$lon, $lat], $geometry)) {
                        $timezonesContainingPoint[] = $feature['properties']['tzid'] ?? null;
                    }
                }

                $timezonesContainingPoint = array_values(array_filter($timezonesContainingPoint, static fn ($tzid) => $tzid !== null));

                return count($timezonesContainingPoint) > 0
                    ? $timezonesContainingPoint
                    : OceanUtils::getTimezoneAtSea($originalLon);
            }

            if (is_array($curTzData) && array_is_list($curTzData) && count($curTzData) > 0) {
                $timezones = [];
                foreach ($curTzData as $idx) {
                    $timezones[] = $this->tzData['timezones'][$idx] ?? null;
                }
                return array_values(array_filter($timezones, static fn ($tzid) => $tzid !== null));
            }

            if (!is_array($curTzData)) {
                throw new \RuntimeException('Unexpected data type');
            }

            $quadData['midLat'] = ($quadData['top'] + $quadData['bottom']) / 2;
            $quadData['midLon'] = ($quadData['left'] + $quadData['right']) / 2;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFeatures(int $pos, int $len): array
    {
        $handle = fopen($this->featureFilePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open geo.dat file');
        }

        if (fseek($handle, $pos) !== 0) {
            fclose($handle);
            throw new \RuntimeException('Failed to seek geo.dat file');
        }

        $data = fread($handle, $len);
        fclose($handle);

        if ($data === false || strlen($data) < $len) {
            throw new \RuntimeException('Failed to read geo.dat file');
        }

        return GeoBufDecoder::decode($data);
    }
}
