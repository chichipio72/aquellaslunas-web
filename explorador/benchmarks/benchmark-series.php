<?php

declare(strict_types=1);

$processStarted = benchmarkNowNanoseconds();
require_once dirname(__DIR__) . '/includes/astronomy-engine-autoload.php';

use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Facade\AstronomyRangeFacade;
use AstronomyEngine\Facade\DailyAstronomyFacade;
use AstronomyEngine\LunarDayCalculator;
use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\MeeusSolarPositionCalculator;
use AstronomyEngine\SolarDayCalculator;

const BENCHMARK_SCENARIOS = [
    'range-facade', 'daily-full', 'moon-instant', 'sun-instant', 'moon-events', 'sun-events',
];
const BENCHMARK_DEFAULTS = [
    'scenario' => 'moon-instant',
    'preset' => '1y',
    'lat' => '-34.6037',
    'lon' => '-58.3816',
    'timezone' => 'America/Argentina/Buenos_Aires',
    'repeat' => '3',
    'warmup' => '1',
];

final class BenchmarkInterrupted extends RuntimeException {}

/** @return int|float */
function benchmarkNowNanoseconds()
{
    return function_exists('hrtime') ? hrtime(true) : microtime(true) * 1_000_000_000;
}

/** @param int|float $started */
function benchmarkElapsedMilliseconds($started): float
{
    return max(0.0, (benchmarkNowNanoseconds() - $started) / 1_000_000.0);
}

function benchmarkParseDate(string $value, DateTimeZone $timezone, string $option): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
        || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($option . ' debe ser una fecha real con formato YYYY-MM-DD.');
    }
    return $date;
}

/** @return array<string,mixed> */
function benchmarkOptions(): array
{
    $raw = getopt('', [
        'scenario:', 'preset:', 'from:', 'to:', 'lat:', 'lon:', 'timezone:',
        'repeat:', 'warmup:', 'save-series', 'self-test', 'help',
    ]);
    if ($raw === false) {
        throw new InvalidArgumentException('No fue posible interpretar los argumentos.');
    }
    if (isset($raw['help'])) {
        benchmarkPrintHelp();
        exit(0);
    }
    if (isset($raw['self-test'])) {
        return ['self_test' => true];
    }
    $values = BENCHMARK_DEFAULTS;
    foreach (['scenario', 'preset', 'lat', 'lon', 'timezone', 'repeat', 'warmup'] as $name) {
        if (isset($raw[$name])) {
            if (!is_string($raw[$name])) {
                throw new InvalidArgumentException('--' . $name . ' debe indicarse una sola vez.');
            }
            $values[$name] = $raw[$name];
        }
    }
    $hasFrom = isset($raw['from']);
    $hasTo = isset($raw['to']);
    if ($hasFrom !== $hasTo) {
        throw new InvalidArgumentException('--from y --to deben proporcionarse juntos.');
    }
    if ($hasFrom) {
        if (!is_string($raw['from']) || !is_string($raw['to'])) {
            throw new InvalidArgumentException('--from y --to deben indicarse una sola vez.');
        }
        $values['from'] = $raw['from'];
        $values['to'] = $raw['to'];
    }
    $values['save_series'] = isset($raw['save-series']);
    return benchmarkValidateOptions($values);
}

/** @param array<string,mixed> $values @return array<string,mixed> */
function benchmarkValidateOptions(array $values): array
{
    $scenario = (string) $values['scenario'];
    if ($scenario !== 'all' && !in_array($scenario, BENCHMARK_SCENARIOS, true)) {
        throw new InvalidArgumentException('Escenario inválido: ' . $scenario . '.');
    }
    $presets = require __DIR__ . '/scenarios.php';
    $preset = (string) ($values['preset'] ?? '1y');
    if (!isset($presets[$preset])) {
        throw new InvalidArgumentException('Preset inválido: ' . $preset . '.');
    }
    $fromValue = isset($values['from']) ? (string) $values['from'] : $presets[$preset]['from'];
    $toValue = isset($values['to']) ? (string) $values['to'] : $presets[$preset]['to'];
    if (!is_numeric($values['lat']) || !is_numeric($values['lon'])) {
        throw new InvalidArgumentException('Latitud y longitud deben ser numéricas.');
    }
    $latitude = (float) $values['lat'];
    $longitude = (float) $values['lon'];
    if (!is_finite($latitude) || $latitude < -90 || $latitude > 90) {
        throw new InvalidArgumentException('La latitud debe estar entre -90 y 90.');
    }
    if (!is_finite($longitude) || $longitude < -180 || $longitude > 180) {
        throw new InvalidArgumentException('La longitud debe estar entre -180 y 180.');
    }
    try {
        $timezone = new DateTimeZone((string) $values['timezone']);
    } catch (Throwable $exception) {
        throw new InvalidArgumentException('Zona horaria inválida.', 0, $exception);
    }
    $from = benchmarkParseDate($fromValue, $timezone, '--from');
    $to = benchmarkParseDate($toValue, $timezone, '--to');
    if ($from > $to) {
        throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la final.');
    }
    foreach (['repeat', 'warmup'] as $integerOption) {
        $raw = (string) $values[$integerOption];
        if (!preg_match('/^\d+$/', $raw)) {
            throw new InvalidArgumentException('--' . $integerOption . ' debe ser un entero no negativo.');
        }
    }
    $repeat = (int) $values['repeat'];
    $warmup = (int) $values['warmup'];
    if ($repeat < 1) {
        throw new InvalidArgumentException('--repeat debe ser al menos 1.');
    }
    $days = (int) $from->diff($to)->days + 1;
    if ($scenario === 'range-facade' && $days > 366) {
        throw new InvalidArgumentException('range-facade admite como máximo 366 días.');
    }
    return [
        'scenario' => $scenario, 'preset' => $preset, 'from' => $from, 'to' => $to,
        'days' => $days, 'latitude' => $latitude, 'longitude' => $longitude,
        'timezone' => $timezone, 'repeat' => $repeat, 'warmup' => $warmup,
        'save_series' => (bool) ($values['save_series'] ?? false),
    ];
}

function benchmarkPrintHelp(): void
{
    fwrite(STDOUT, "Uso: php benchmark-series.php [opciones]\n"
        . "  --scenario=range-facade|daily-full|moon-instant|sun-instant|moon-events|sun-events|all\n"
        . "  --preset=1y|10y|50y|150y (un par --from/--to tiene prioridad)\n"
        . "  --from=YYYY-MM-DD --to=YYYY-MM-DD --lat=NUM --lon=NUM --timezone=ZONA\n"
        . "  --repeat=N --warmup=N --save-series --self-test\n");
}

function benchmarkInstallSignalHandler(): void
{
    $GLOBALS['benchmark_interrupted'] = false;
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGINT, static function (): void { $GLOBALS['benchmark_interrupted'] = true; });
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }
    }
}

function benchmarkCheckInterrupted(): void
{
    if (function_exists('pcntl_signal_dispatch') && !function_exists('pcntl_async_signals')) {
        pcntl_signal_dispatch();
    }
    if (($GLOBALS['benchmark_interrupted'] ?? false) === true) {
        throw new BenchmarkInterrupted('Ejecución interrumpida por SIGINT.');
    }
}

/** @param int|float $started */
function benchmarkProgress(string $label, int $processed, int $days, $started, bool $force = false): void
{
    static $lastUpdate = [];
    $now = microtime(true);
    $step = max(100, (int) ceil($days / 100));
    $last = $lastUpdate[$label] ?? 0.0;
    if (!$force && $processed !== 1 && $processed % $step !== 0 && $now - $last < 3.0) {
        return;
    }
    $lastUpdate[$label] = $now;
    fwrite(STDERR, sprintf("[%s] %d/%d días (%.1f%%), %.2f s\n", $label, $processed, $days,
        $days ? $processed / $days * 100 : 100, benchmarkElapsedMilliseconds($started) / 1000));
}

function benchmarkBuildCalculator(string $scenario): object
{
    return match ($scenario) {
        'range-facade' => new AstronomyRangeFacade(),
        'daily-full' => new DailyAstronomyFacade(),
        'moon-instant' => new MeeusLunarCalculator(),
        'sun-instant' => new MeeusSolarPositionCalculator(),
        'moon-events' => new LunarDayCalculator(new MeeusLunarCalculator()),
        default => new SolarDayCalculator(new MeeusSolarPositionCalculator()),
    };
}

/** @return array{0:array<int,array<string,mixed>>,1:float,2:float} */
function benchmarkCalculateRows(string $scenario, array $options, AstronomyObserver $observer, object $calculator, string $label): array
{
    $rows = [];
    $calculationMs = 0.0;
    $extractionMs = 0.0;
    $date = $options['from'];
    $processed = 0;
    $progressStarted = benchmarkNowNanoseconds();
    if ($scenario === 'range-facade') {
        $started = benchmarkNowNanoseconds();
        $result = $calculator->calculate($date->format('Y-m-d'), $options['days'], $observer);
        $calculationMs += benchmarkElapsedMilliseconds($started);
        $started = benchmarkNowNanoseconds();
        foreach ($result['items'] as $item) {
            $rows[] = [
                'date' => $item['date'], 'moon_illumination_percent' => $item['moon']['illumination_percent'],
                'moonrise' => $item['moon']['rise'], 'moonset' => $item['moon']['set'],
                'sunrise' => $item['sun']['rise'], 'sunset' => $item['sun']['set'],
                'day_length_seconds' => $item['sun']['day_length_seconds'],
            ];
        }
        $extractionMs += benchmarkElapsedMilliseconds($started);
        benchmarkProgress($label, $options['days'], $options['days'], $progressStarted, true);
        return [$rows, $calculationMs, $extractionMs];
    }
    while ($date <= $options['to']) {
        benchmarkCheckInterrupted();
        $dateString = $date->format('Y-m-d');
        $instant = new DateTimeImmutable($dateString . ' 00:00:00', $options['timezone']);
        $started = benchmarkNowNanoseconds();
        $value = match ($scenario) {
            'daily-full' => $calculator->calculate($dateString, $observer, false),
            'moon-instant', 'sun-instant' => $calculator->calculate($instant, $observer->latitudeDegrees,
                $observer->longitudeDegrees, $observer->elevationMeters),
            default => $calculator->calculate($date, $observer->latitudeDegrees,
                $observer->longitudeDegrees, $observer->elevationMeters),
        };
        $calculationMs += benchmarkElapsedMilliseconds($started);
        $started = benchmarkNowNanoseconds();
        $rows[] = benchmarkExtractRow($scenario, $dateString, $value);
        $extractionMs += benchmarkElapsedMilliseconds($started);
        $processed++;
        benchmarkProgress($label, $processed, $options['days'], $progressStarted, $processed === $options['days']);
        $date = $date->modify('+1 day');
    }
    return [$rows, $calculationMs, $extractionMs];
}

/** @param mixed $value @return array<string,mixed> */
function benchmarkExtractRow(string $scenario, string $date, $value): array
{
    if ($scenario === 'daily-full') {
        return ['date' => $date, 'moon_illumination_percent' => $value['moon']['illumination_percent'],
            'moon_distance_km' => $value['moon']['distance_km'], 'moonrise' => $value['moon']['rise'],
            'moonset' => $value['moon']['set'], 'sunrise' => $value['sun']['rise'],
            'sunset' => $value['sun']['set'], 'day_length_seconds' => $value['sun']['day_length_seconds']];
    }
    if ($scenario === 'moon-instant') {
        return ['date' => $date, 'illumination_fraction' => $value->illuminationFraction,
            'phase_cycle_angle_degrees' => $value->cycleAngleDegrees,
            'distance_geocentric_km' => $value->distanceKilometers,
            'distance_topocentric_km' => $value->topocentricDistanceKilometers,
            'ecliptic_latitude_degrees' => $value->eclipticLatitudeDegrees,
            'right_ascension_degrees' => $value->rightAscensionDegrees,
            'declination_degrees' => $value->declinationDegrees,
            'altitude_degrees' => $value->altitudeDegrees, 'azimuth_degrees' => $value->azimuthDegrees];
    }
    if ($scenario === 'sun-instant') {
        return ['date' => $date, 'altitude_degrees' => $value->altitudeDegrees,
            'azimuth_degrees' => $value->azimuthDegrees];
    }
    if ($scenario === 'moon-events') {
        return ['date' => $date, 'rise' => benchmarkDate($value->moonrise),
            'set' => benchmarkDate($value->moonset),
            'visibility_intervals' => benchmarkIntervals($value->visibilityIntervals)];
    }
    $periods = [];
    foreach ($value->periods as $name => $period) {
        $periods[$name] = ['start' => benchmarkDate($period->start), 'end' => benchmarkDate($period->end),
            'duration_seconds' => $period->durationSeconds];
    }
    return ['date' => $date, 'rise' => benchmarkDate($value->sunrise), 'set' => benchmarkDate($value->sunset),
        'solar_noon' => benchmarkDate($value->solarNoon), 'periods' => $periods];
}

function benchmarkDate(?DateTimeImmutable $date): ?string { return $date?->format(DATE_ATOM); }

/** @param array<int,object> $intervals @return array<int,array{start:?string,end:?string}> */
function benchmarkIntervals(array $intervals): array
{
    $result = [];
    foreach ($intervals as $interval) {
        $result[] = ['start' => benchmarkDate($interval->start), 'end' => benchmarkDate($interval->end)];
    }
    return $result;
}

/** @param mixed $value */
function benchmarkCountValues($value): int
{
    if (!is_array($value)) return 1;
    $count = 0;
    foreach ($value as $item) $count += benchmarkCountValues($item);
    return $count;
}

/** @return array<string,mixed> */
function benchmarkEnvironment(): array
{
    $extensions = get_loaded_extensions();
    sort($extensions, SORT_STRING);
    $cpu = ['logical_processors' => null, 'model' => null];
    if (is_readable('/proc/cpuinfo')) {
        $contents = file_get_contents('/proc/cpuinfo');
        if (is_string($contents)) {
            preg_match_all('/^processor\s*:/m', $contents, $matches);
            $cpu['logical_processors'] = count($matches[0]) ?: null;
            if (preg_match('/^model name\s*:\s*(.+)$/m', $contents, $match)) $cpu['model'] = trim($match[1]);
        }
    }
    if ($cpu['logical_processors'] === null && is_numeric(getenv('NUMBER_OF_PROCESSORS'))) {
        $cpu['logical_processors'] = (int) getenv('NUMBER_OF_PROCESSORS');
    }
    $opcache = benchmarkIniBoolean('opcache.enable') || benchmarkIniBoolean('opcache.enable_cli');
    $jitValue = ini_get('opcache.jit');
    $jitBuffer = ini_get('opcache.jit_buffer_size');
    return [
        'operating_system' => php_uname('s') ?: null,
        'os_release' => php_uname('r') ?: null,
        'architecture' => php_uname('m') ?: null,
        'php_version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'loaded_php_ini' => php_ini_loaded_file() ?: null,
        'loaded_extensions' => $extensions,
        'cpu' => $cpu,
        'memory_limit' => ini_get('memory_limit') !== false ? ini_get('memory_limit') : null,
        'opcache_enabled' => $opcache,
        'jit_enabled' => $opcache && is_string($jitBuffer) && benchmarkIniBytes($jitBuffer) > 0
            && is_string($jitValue) && !in_array(strtolower($jitValue), ['', '0', 'off', 'disable', 'disabled'], true),
    ];
}

function benchmarkIniBoolean(string $name): bool
{
    $value = ini_get($name);
    return is_string($value) && in_array(strtolower($value), ['1', 'on', 'yes', 'true'], true);
}

function benchmarkIniBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') return 0;
    $number = (float) $value;
    $suffix = strtolower(substr($value, -1));
    return (int) ($number * match ($suffix) {'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1});
}

/** @return array{summary:array<string,mixed>,series_json:string} */
function benchmarkRunOnce(string $scenario, array $options, float $preparationMs, string $label): array
{
    if ($scenario === 'range-facade' && $options['days'] > 366) {
        throw new InvalidArgumentException('range-facade admite como máximo 366 días.');
    }
    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }
    $totalStarted = benchmarkNowNanoseconds();
    $memoryInitial = memory_get_usage(true);
    $started = benchmarkNowNanoseconds();
    $observer = new AstronomyObserver($options['latitude'], $options['longitude'], $options['timezone']->getName());
    $observerMs = benchmarkElapsedMilliseconds($started);
    $started = benchmarkNowNanoseconds();
    $calculator = benchmarkBuildCalculator($scenario);
    $calculatorMs = benchmarkElapsedMilliseconds($started);
    [$rows, $calculationMs, $extractionMs] = benchmarkCalculateRows($scenario, $options, $observer, $calculator, $label);
    $started = benchmarkNowNanoseconds();
    $seriesJson = json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $serializationMs = benchmarkElapsedMilliseconds($started);
    $compressionMs = null;
    $gzipBytes = null;
    if (function_exists('gzencode')) {
        $started = benchmarkNowNanoseconds();
        $compressed = gzencode($seriesJson, 6);
        $compressionMs = benchmarkElapsedMilliseconds($started);
        if ($compressed !== false) $gzipBytes = strlen($compressed);
        unset($compressed);
    }
    $totalMs = benchmarkElapsedMilliseconds($totalStarted) + $preparationMs;
    $rowCount = count($rows);
    $valueCount = benchmarkCountValues($rows);
    $jsonBytes = strlen($seriesJson);
    $summary = [
        'status' => 'success', 'scenario' => $scenario,
        'from' => $options['from']->format('Y-m-d'), 'to' => $options['to']->format('Y-m-d'),
        'days' => $options['days'], 'latitude' => $options['latitude'], 'longitude' => $options['longitude'],
        'timezone' => $options['timezone']->getName(),
        'instant_local_time' => in_array($scenario, ['moon-instant', 'sun-instant'], true) ? '00:00:00' : null,
        'executed_at' => gmdate(DATE_ATOM), 'environment' => benchmarkEnvironment(),
        'metrics_ms' => ['load_and_preparation' => $preparationMs, 'observer_construction' => $observerMs,
            'calculator_construction' => $calculatorMs, 'astronomical_calculation' => $calculationMs,
            'row_extraction' => $extractionMs, 'json_serialization' => $serializationMs,
            'gzip_compression' => $compressionMs, 'total' => $totalMs],
        'derived_metrics' => [
            'average_microseconds_per_day' => $totalMs * 1000 / $options['days'],
            'rows_per_second' => $totalMs > 0 ? $rowCount / ($totalMs / 1000) : null,
            'values_per_second' => $totalMs > 0 ? $valueCount / ($totalMs / 1000) : null,
            'json_bytes_per_row' => $rowCount ? $jsonBytes / $rowCount : null,
            'gzip_bytes_per_row' => $rowCount && $gzipBytes !== null ? $gzipBytes / $rowCount : null,
            'compression_ratio' => $gzipBytes !== null && $jsonBytes ? $gzipBytes / $jsonBytes : null,
            'astronomy_percent' => $totalMs > 0 ? $calculationMs / $totalMs * 100 : null,
            'json_percent' => $totalMs > 0 ? $serializationMs / $totalMs * 100 : null,
            'gzip_percent' => $totalMs > 0 && $compressionMs !== null ? $compressionMs / $totalMs * 100 : null,
        ],
        'memory_bytes' => ['initial' => $memoryInitial, 'final' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true)],
        'row_count' => $rowCount, 'value_count' => $valueCount,
        'json_bytes' => $jsonBytes, 'gzip_bytes' => $gzipBytes,
        'sample' => ['first' => array_slice($rows, 0, 3), 'last' => array_slice($rows, -3)],
    ];
    unset($rows, $calculator, $observer);
    return ['summary' => $summary, 'series_json' => $seriesJson];
}

/** @param list<float|int> $values @return array{min:float,max:float,average:float,median:float,stddev:float} */
function benchmarkStatistics(array $values): array
{
    if ($values === []) throw new InvalidArgumentException('No hay valores para calcular estadísticas.');
    $numbers = array_map('floatval', $values);
    sort($numbers, SORT_NUMERIC);
    $count = count($numbers);
    $average = array_sum($numbers) / $count;
    $middle = intdiv($count, 2);
    $median = $count % 2 ? $numbers[$middle] : ($numbers[$middle - 1] + $numbers[$middle]) / 2;
    $variance = 0.0;
    foreach ($numbers as $number) $variance += ($number - $average) ** 2;
    return ['min' => $numbers[0], 'max' => $numbers[$count - 1], 'average' => $average,
        'median' => $median, 'stddev' => sqrt($variance / $count)];
}

/** @param list<array<string,mixed>> $runs @return array<string,mixed> */
function benchmarkAggregateStatistics(array $runs): array
{
    $groups = ['metrics_ms' => [], 'derived_metrics' => [], 'memory_bytes' => []];
    foreach ($groups as $group => $_) {
        $names = array_keys($runs[0][$group]);
        foreach ($names as $name) {
            $values = [];
            foreach ($runs as $run) if ($run[$group][$name] !== null) $values[] = $run[$group][$name];
            $groups[$group][$name] = $values === [] ? null : benchmarkStatistics($values);
        }
    }
    foreach (['row_count', 'value_count', 'json_bytes', 'gzip_bytes'] as $name) {
        $values = [];
        foreach ($runs as $run) if ($run[$name] !== null) $values[] = $run[$name];
        $groups['output'][$name] = $values === [] ? null : benchmarkStatistics($values);
    }
    return $groups;
}

function benchmarkResultBase(string $scenario, array $options): string
{
    $stamp = gmdate('Ymd-His') . '-' . substr(str_replace('.', '', sprintf('%.6F', microtime(true))), -6);
    return __DIR__ . '/results/' . $scenario . '-' . $options['from']->format('Y-m-d') . '-'
        . $options['to']->format('Y-m-d') . '-' . $stamp;
}

function benchmarkWriteJson(string $path, array $value): void
{
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json . "\n") === false) throw new RuntimeException('No se pudo guardar ' . $path);
}

/** @param list<array<string,mixed>> $runs */
function benchmarkWriteCsv(string $path, array $runs): void
{
    $handle = fopen($path, 'wb');
    if ($handle === false) throw new RuntimeException('No se pudo crear ' . $path);
    $header = ['scenario', 'from', 'to', 'days', 'repetition', 'total_ms', 'astronomical_calculation_ms',
        'row_extraction_ms', 'json_serialization_ms', 'gzip_compression_ms', 'average_microseconds_per_day',
        'rows_per_second', 'values_per_second', 'json_bytes', 'gzip_bytes', 'peak_memory_bytes'];
    fputcsv($handle, $header, ',', '"', '');
    foreach ($runs as $run) {
        fputcsv($handle, [$run['scenario'], $run['from'], $run['to'], $run['days'], $run['repetition'],
            $run['metrics_ms']['total'], $run['metrics_ms']['astronomical_calculation'],
            $run['metrics_ms']['row_extraction'], $run['metrics_ms']['json_serialization'],
            $run['metrics_ms']['gzip_compression'], $run['derived_metrics']['average_microseconds_per_day'],
            $run['derived_metrics']['rows_per_second'], $run['derived_metrics']['values_per_second'],
            $run['json_bytes'], $run['gzip_bytes'], $run['memory_bytes']['peak']], ',', '"', '');
    }
    fclose($handle);
}

/** @return array<string,mixed> */
function benchmarkRunSet(string $scenario, array $options, float $preparationMs): array
{
    benchmarkInstallSignalHandler();
    for ($warmup = 1; $warmup <= $options['warmup']; $warmup++) {
        fwrite(STDERR, sprintf("[%s] calentamiento %d/%d\n", $scenario, $warmup, $options['warmup']));
        $result = benchmarkRunOnce($scenario, $options, $preparationMs, $scenario . ':warmup-' . $warmup);
        unset($result);
        gc_collect_cycles();
    }
    $base = benchmarkResultBase($scenario, $options);
    $runs = [];
    for ($repetition = 1; $repetition <= $options['repeat']; $repetition++) {
        fwrite(STDERR, sprintf("[%s] repetición %d/%d\n", $scenario, $repetition, $options['repeat']));
        $result = benchmarkRunOnce($scenario, $options, $preparationMs, $scenario . ':repeat-' . $repetition);
        $summary = $result['summary'];
        $summary['repetition'] = $repetition;
        $summaryPath = $base . '-run-' . $repetition . '.json';
        $seriesPath = null;
        if ($options['save_series']) {
            $seriesPath = $base . '-run-' . $repetition . '-series.json';
            if (file_put_contents($seriesPath, $result['series_json'] . "\n") === false) {
                throw new RuntimeException('No se pudo guardar ' . $seriesPath);
            }
        }
        $summary['result_file'] = $summaryPath;
        $summary['series_file'] = $seriesPath;
        benchmarkWriteJson($summaryPath, $summary);
        $runs[] = $summary;
        unset($result);
        gc_collect_cycles();
    }
    $csvPath = $base . '-runs.csv';
    benchmarkWriteCsv($csvPath, $runs);
    $aggregate = [
        'status' => 'success', 'scenario' => $scenario,
        'from' => $options['from']->format('Y-m-d'), 'to' => $options['to']->format('Y-m-d'),
        'days' => $options['days'], 'preset' => $options['preset'],
        'repeat' => $options['repeat'], 'warmup' => $options['warmup'],
        'latitude' => $options['latitude'], 'longitude' => $options['longitude'],
        'timezone' => $options['timezone']->getName(), 'environment' => benchmarkEnvironment(),
        'statistics' => benchmarkAggregateStatistics($runs),
        'executions' => array_map(static fn(array $run): array => [
            'repetition' => $run['repetition'], 'metrics_ms' => $run['metrics_ms'],
            'derived_metrics' => $run['derived_metrics'], 'json_bytes' => $run['json_bytes'],
            'gzip_bytes' => $run['gzip_bytes'], 'result_file' => $run['result_file'],
            'series_file' => $run['series_file'],
        ], $runs),
        'csv_file' => $csvPath,
    ];
    $aggregatePath = $base . '-aggregate.json';
    $aggregate['aggregate_file'] = $aggregatePath;
    benchmarkWriteJson($aggregatePath, $aggregate);
    return $aggregate;
}

function benchmarkSelfTest(): void
{
    $stats = benchmarkStatistics([1, 2, 3, 4]);
    if ($stats['average'] !== 2.5 || $stats['median'] !== 2.5) {
        throw new RuntimeException('Self-test: promedio o mediana incorrectos.');
    }
    $raw = BENCHMARK_DEFAULTS;
    $raw['scenario'] = 'moon-instant'; $raw['preset'] = '10y';
    $raw['from'] = '2020-01-01'; $raw['to'] = '2020-01-03';
    $raw['repeat'] = '2'; $raw['warmup'] = '1'; $raw['save_series'] = false;
    $options = benchmarkValidateOptions($raw);
    if ($options['days'] !== 3 || $options['from']->format('Y-m-d') !== '2020-01-01') {
        throw new RuntimeException('Self-test: preset o prioridad de fechas incorrectos.');
    }
    $aggregate = benchmarkRunSet('moon-instant', $options, 0.0);
    if (count($aggregate['executions']) !== 2 || $aggregate['warmup'] !== 1) {
        throw new RuntimeException('Self-test: warmup incluido o repeticiones incorrectas.');
    }
    foreach ($aggregate['executions'] as $execution) {
        if (!is_file($execution['result_file'])) throw new RuntimeException('Self-test: falta JSON individual.');
        foreach ($execution['metrics_ms'] as $metric) {
            if ($metric !== null && (!is_numeric($metric) || $metric < 0)) {
                throw new RuntimeException('Self-test: métrica inválida.');
            }
        }
    }
    if (!is_file($aggregate['aggregate_file']) || !is_file($aggregate['csv_file'])) {
        throw new RuntimeException('Self-test: falta JSON agregado o CSV.');
    }
    $csv = file($aggregate['csv_file'], FILE_IGNORE_NEW_LINES);
    if (!is_array($csv) || count($csv) !== 3) throw new RuntimeException('Self-test: CSV incorrecto.');
    $files = [__FILE__, __DIR__ . '/scenarios.php', dirname(__DIR__) . '/includes/astronomy-engine-autoload.php'];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        if ($contents === false || preg_match('/\bmb_[A-Za-z0-9_]+\s*\(/', $contents)) {
            throw new RuntimeException('Self-test: lectura fallida o llamada mb_* encontrada en ' . $file);
        }
    }
    fwrite(STDOUT, json_encode(['self_test' => 'ok', 'aggregate_file' => $aggregate['aggregate_file'],
        'csv_file' => $aggregate['csv_file']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
}

try {
    $options = benchmarkOptions();
    if (($options['self_test'] ?? false) === true) {
        benchmarkSelfTest();
        exit(0);
    }
    $preparationMs = benchmarkElapsedMilliseconds($processStarted);
    $scenarios = $options['scenario'] === 'all' ? BENCHMARK_SCENARIOS : [$options['scenario']];
    $outputs = [];
    $hadError = false;
    foreach ($scenarios as $scenario) {
        try {
            $outputs[] = benchmarkRunSet($scenario, $options, $preparationMs);
        } catch (Throwable $exception) {
            $hadError = true;
            $outputs[] = ['status' => 'error', 'scenario' => $scenario, 'error' => get_debug_type($exception) . ': ' . $exception->getMessage()];
            fwrite(STDERR, '[' . $scenario . '] ERROR: ' . $exception->getMessage() . "\n");
            if ($options['scenario'] !== 'all') break;
        }
    }
    fwrite(STDOUT, json_encode($options['scenario'] === 'all' ? $outputs : $outputs[0],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    exit($hadError ? 1 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . "\n");
    exit(1);
}
