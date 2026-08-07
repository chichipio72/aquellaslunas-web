<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class TleParser
{
    private const TWO_PI = 2.0 * M_PI;

    public function parse(string $name, string $line1, string $line2): Tle
    {
        $name = trim($name);
        $line1 = rtrim($line1, "\r\n");
        $line2 = rtrim($line2, "\r\n");
        if ($name === '' || strlen($line1) !== 69 || strlen($line2) !== 69
            || !str_starts_with($line1, '1 ') || !str_starts_with($line2, '2 ')) {
            throw new InvalidArgumentException('Invalid TLE structure.');
        }
        $this->validateChecksum($line1);
        $this->validateChecksum($line2);
        $catalog1 = $this->integer(substr($line1, 2, 5), 'catalog number');
        $catalog2 = $this->integer(substr($line2, 2, 5), 'catalog number');
        if ($catalog1 !== $catalog2) {
            throw new InvalidArgumentException('TLE catalog numbers do not match.');
        }

        $epochYear = $this->integer(substr($line1, 18, 2), 'epoch year');
        $year = $epochYear < 57 ? 2000 + $epochYear : 1900 + $epochYear;
        $epochDay = $this->number(substr($line1, 20, 12), 'epoch day');
        $daysInYear = (int) (new DateTimeImmutable("{$year}-12-31", new DateTimeZone('UTC')))->format('z') + 1;
        if ($epochDay < 1.0 || $epochDay >= $daysInYear + 1.0) {
            throw new InvalidArgumentException('TLE epoch day is outside the selected year.');
        }
        $epochSeconds = ($epochDay - 1.0) * 86400.0;
        $yearStart = new DateTimeImmutable("{$year}-01-01 00:00:00", new DateTimeZone('UTC'));
        $timestamp = (float) $yearStart->format('U.u') + $epochSeconds;
        $epoch = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $timestamp), new DateTimeZone('UTC'));
        if (!$epoch instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('Invalid TLE epoch.');
        }

        $inclination = deg2rad($this->number(substr($line2, 8, 8), 'inclination'));
        $node = deg2rad($this->number(substr($line2, 17, 8), 'ascending node'));
        $eccentricity = $this->number('0.' . str_replace(' ', '0', substr($line2, 26, 7)), 'eccentricity');
        $argumentPerigee = deg2rad($this->number(substr($line2, 34, 8), 'argument of perigee'));
        $meanAnomaly = deg2rad($this->number(substr($line2, 43, 8), 'mean anomaly'));
        $revolutionsPerDay = $this->number(substr($line2, 52, 11), 'mean motion');
        if ($eccentricity < 0.0 || $eccentricity >= 1.0 || $revolutionsPerDay <= 0.0) {
            throw new InvalidArgumentException('TLE orbital elements are outside SGP4 limits.');
        }

        return new Tle(
            $name,
            $line1,
            $line2,
            $catalog1,
            $epoch->setTimezone(new DateTimeZone('UTC')),
            $this->impliedExponent(substr($line1, 53, 8), 'BSTAR'),
            $inclination,
            $node,
            $eccentricity,
            $argumentPerigee,
            $meanAnomaly,
            $revolutionsPerDay * self::TWO_PI / 1440.0,
        );
    }

    private function validateChecksum(string $line): void
    {
        $sum = 0;
        for ($index = 0; $index < 68; $index++) {
            $character = $line[$index];
            if ($character >= '0' && $character <= '9') $sum += (int) $character;
            elseif ($character === '-') $sum++;
        }
        if ($sum % 10 !== (int) $line[68]) {
            throw new InvalidArgumentException('Invalid TLE checksum.');
        }
    }

    private function impliedExponent(string $value, string $field): float
    {
        if (preg_match('/^([ +-])(\d{5})([+-]\d)$/', $value, $match) !== 1) {
            throw new InvalidArgumentException("Invalid TLE {$field} field.");
        }
        $sign = $match[1] === '-' ? -1.0 : 1.0;
        return $sign * (float) ('0.' . $match[2]) * 10.0 ** (int) $match[3];
    }

    private function integer(string $value, string $field): int
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
            throw new InvalidArgumentException("Invalid TLE {$field}.");
        }
        return (int) $value;
    }

    private function number(string $value, string $field): float
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            throw new InvalidArgumentException("Invalid TLE {$field}.");
        }
        return (float) $value;
    }
}
