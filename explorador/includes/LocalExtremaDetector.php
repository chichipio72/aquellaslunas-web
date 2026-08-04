<?php

declare(strict_types=1);

namespace Explorador;

use DateTimeImmutable;

final class LocalExtremaDetector
{
    /** @param list<array<string,mixed>> $rows @return array{maximos:list<array{fecha:string,valor:float}>,minimos:list<array{fecha:string,valor:float}>} */
    public function detect(array $rows, string $field, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $maxima = [];
        $minima = [];
        for ($index = 1, $last = count($rows) - 1; $index < $last; $index++) {
            $before = $rows[$index - 1];
            $current = $rows[$index];
            $after = $rows[$index + 1];
            $date = (string) ($current['date'] ?? '');
            if ($date < $from->format('Y-m-d') || $date > $to->format('Y-m-d')) continue;
            if (!$this->consecutive((string) ($before['date'] ?? ''), $date)
                || !$this->consecutive($date, (string) ($after['date'] ?? ''))) continue;
            $previousValue = $this->finite($before[$field] ?? null);
            $currentValue = $this->finite($current[$field] ?? null);
            $nextValue = $this->finite($after[$field] ?? null);
            if ($previousValue === null || $currentValue === null || $nextValue === null) continue;
            if ($currentValue > $previousValue && $currentValue >= $nextValue) {
                $maxima[] = ['fecha' => $date, 'valor' => $currentValue];
            }
            if ($currentValue < $previousValue && $currentValue <= $nextValue) {
                $minima[] = ['fecha' => $date, 'valor' => $currentValue];
            }
        }
        return ['maximos' => $maxima, 'minimos' => $minima];
    }

    private function consecutive(string $first, string $second): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $first);
        return $date !== false && $date->modify('+1 day')->format('Y-m-d') === $second;
    }

    private function finite(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) return null;
        $number = (float) $value;
        return is_finite($number) ? $number : null;
    }
}
