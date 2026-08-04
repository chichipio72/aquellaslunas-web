<?php

declare(strict_types=1);

namespace Explorador;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class PhaseSelectionException extends RuntimeException {}

final class PhaseEventDateProvider
{
    public const PHASES = [
        'new_moon' => 'Luna nueva',
        'first_quarter' => 'Cuarto creciente',
        'full_moon' => 'Luna llena',
        'last_quarter' => 'Cuarto menguante',
    ];

    /** @param callable():PDO $connectionFactory */
    public function __construct(private readonly mixed $connectionFactory) {}

    /** @param list<string> $phases @return array{items:list<array{date:string,phase:string,phase_label:string,instant:string}>,query_ms:float} */
    public function dates(DateTimeImmutable $from, DateTimeImmutable $to, DateTimeZone $timezone, array $phases): array
    {
        if ($from->format('Y-m-d') < '1900-01-01' || $to->format('Y-m-d') > '2050-12-31') {
            throw new PhaseSelectionException('El filtro de fases de MariaDB está disponible entre 1900 y 2050.');
        }
        $started = self::now();
        $utc = new DateTimeZone('UTC');
        $queryFrom = $from->setTimezone($utc)->modify('-1 day');
        $queryTo = $to->modify('+1 day')->setTimezone($utc)->modify('+1 day');
        $placeholders = implode(',', array_fill(0, count($phases), '?'));
        try {
            $connection = ($this->connectionFactory)();
            $statement = $connection->prepare(
                'SELECT event_type,event_time FROM astronomical_events '
                . "WHERE event_group='moon_phase' AND event_type IN ($placeholders) "
                . 'AND event_time>=? AND event_time<? ORDER BY event_time'
            );
            $statement->execute(array_merge($phases, [
                $queryFrom->format('Y-m-d H:i:s.u'),
                $queryTo->format('Y-m-d H:i:s.u'),
            ]));
            $items = self::localDates($statement, $from, $to, $timezone, $phases);
        } catch (Throwable $exception) {
            error_log('Explorador phase selection database failure [' . get_debug_type($exception) . '].');
            throw new PhaseSelectionException('No se pudieron consultar las fases lunares en MariaDB.', 0, $exception);
        }
        return ['items' => $items, 'query_ms' => self::elapsed($started)];
    }

    /** @param iterable<array<string,mixed>> $rows @param list<string> $phases @return list<array{date:string,phase:string,phase_label:string,instant:string}> */
    public static function localDates(iterable $rows, DateTimeImmutable $from, DateTimeImmutable $to,
        DateTimeZone $timezone, array $phases): array
    {
        $allowed = array_fill_keys($phases, true);
        $utc = new DateTimeZone('UTC');
        $byDate = [];
        foreach ($rows as $row) {
            $phase = (string) ($row['event_type'] ?? '');
            if (!isset($allowed[$phase], self::PHASES[$phase])) continue;
            $instant = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', (string) ($row['event_time'] ?? ''), $utc);
            if ($instant === false) throw new RuntimeException('MariaDB contiene una fecha de fase inválida.');
            $local = $instant->setTimezone($timezone);
            $date = $local->format('Y-m-d');
            if ($date < $from->format('Y-m-d') || $date > $to->format('Y-m-d') || isset($byDate[$date])) continue;
            $byDate[$date] = ['date' => $date, 'phase' => $phase,
                'phase_label' => self::PHASES[$phase], 'instant' => $local->format('Y-m-d\TH:i:sP')];
        }
        ksort($byDate);
        return array_values($byDate);
    }

    /** @return int|float */
    private static function now() { return function_exists('hrtime') ? hrtime(true) : microtime(true) * 1_000_000_000; }
    /** @param int|float $started */
    private static function elapsed($started): float { return max(0.0, (self::now() - $started) / 1_000_000); }
}
