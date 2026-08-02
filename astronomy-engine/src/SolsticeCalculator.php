<?php

declare(strict_types=1);

namespace AstronomyEngine;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** June and December solstices using Meeus, Astronomical Algorithms, chapter 27. */
final class SolsticeCalculator
{
    private const TERMS = [
        [485, 324.96, 1934.136], [203, 337.23, 32964.467], [199, 342.08, 20.186],
        [182, 27.85, 445267.112], [156, 73.14, 45036.886], [136, 171.52, 22518.443],
        [77, 222.54, 65928.934], [74, 296.72, 3034.906], [70, 243.58, 9037.513],
        [58, 119.81, 33718.147], [52, 297.17, 150.678], [50, 21.02, 2281.226],
        [45, 247.54, 29929.562], [44, 325.15, 31555.956], [29, 60.93, 4443.417],
        [18, 155.12, 67555.328], [17, 288.79, 4562.452], [16, 198.04, 62894.029],
        [14, 199.76, 31436.921], [12, 95.39, 14577.848], [12, 287.11, 31931.756],
        [12, 320.81, 34777.259], [9, 227.73, 1222.114], [8, 15.45, 16859.074],
    ];

    /** @return array{june:DateTimeImmutable,december:DateTimeImmutable,calculation_model:string} */
    public function forYear(int $year): array
    {
        if ($year < 1000 || $year > 3000) {
            throw new InvalidArgumentException('Solstice year must be between 1000 and 3000.');
        }

        return [
            'june' => $this->calculate($year, 'june'),
            'december' => $this->calculate($year, 'december'),
            'calculation_model' => 'meeus-chapter-27-periodic-terms-delta-t',
        ];
    }

    private function calculate(int $year, string $month): DateTimeImmutable
    {
        $y = ($year - 2000) / 1000;
        $jde0 = match ($month) {
            'june' => 2451716.56767 + 365241.62603 * $y + 0.00325 * $y ** 2 + 0.00888 * $y ** 3 - 0.00030 * $y ** 4,
            'december' => 2451900.05952 + 365242.74049 * $y - 0.06223 * $y ** 2 - 0.00823 * $y ** 3 + 0.00032 * $y ** 4,
        };
        $t = ($jde0 - 2451545.0) / 36525;
        $w = deg2rad(35999.373 * $t - 2.47);
        $deltaLambda = 1 + 0.0334 * cos($w) + 0.0007 * cos(2 * $w);
        $s = 0.0;
        foreach (self::TERMS as [$a, $b, $c]) {
            $s += $a * cos(deg2rad($b + $c * $t));
        }
        $jdeTerrestrial = $jde0 + 0.00001 * $s / $deltaLambda;
        $utcJulianDay = $jdeTerrestrial - $this->deltaT($year + ($month === 'june' ? 0.47 : 0.97)) / 86400;
        $seconds = ($utcJulianDay - 2440587.5) * 86400;
        return DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $seconds), new DateTimeZone('UTC'))->setTimezone(new DateTimeZone('UTC'));
    }

    private function deltaT(float $year): float
    {
        if ($year >= 2005 && $year < 2050) {
            $t = $year - 2000;
            return 62.92 + 0.32217 * $t + 0.005589 * $t * $t;
        }
        if ($year >= 2050 && $year < 2150) {
            return -20 + 32 * (($year - 1820) / 100) ** 2 - 0.5628 * (2150 - $year);
        }
        $u = ($year - 2000) / 100;
        return 63.86 + 33.45 * $u - 603.74 * $u ** 2 + 1727.5 * $u ** 3 + 65181.4 * $u ** 4 + 237359.9 * $u ** 5;
    }
}
