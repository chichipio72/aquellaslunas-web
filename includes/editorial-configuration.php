<?php

declare(strict_types=1);

require_once __DIR__ . '/web-database.php';

function astronomyEditorialCatalog(): array
{
    return [
        'home_moon' => ['context' => 'Inicio', 'label' => 'Cómo describir la Luna', 'precedence' => true, 'parameters' => [
            'home.new_moon.impossible_days' => ['label' => 'Prácticamente invisible hasta', 'default' => 1.0, 'min' => 0.0, 'max' => 5.0, 'step' => .1, 'unit' => 'días'],
            'home.new_moon.thin_days' => ['label' => 'Luna muy fina hasta', 'default' => 3.0, 'min' => .1, 'max' => 8.0, 'step' => .1, 'unit' => 'días'],
            'home.altitude.very_low_max' => ['label' => 'Muy baja: hasta', 'default' => 15.0, 'min' => -10.0, 'max' => 89.0, 'step' => .5, 'unit' => '°'],
            'home.altitude.low_max' => ['label' => 'Baja: hasta', 'default' => 35.0, 'min' => -5.0, 'max' => 89.0, 'step' => .5, 'unit' => '°'],
            'home.altitude.medium_max' => ['label' => 'Media: hasta', 'default' => 60.0, 'min' => 0.0, 'max' => 89.0, 'step' => .5, 'unit' => '°'],
            'home.altitude.high_max' => ['label' => 'Muy alta: hasta', 'default' => 80.0, 'min' => 1.0, 'max' => 90.0, 'step' => .5, 'unit' => '°'],
        ], 'texts' => [
            'home.moon.below' => ['label' => 'Debajo del horizonte', 'default' => 'No está sobre el horizonte.'],
            'home.moon.new_impossible' => ['label' => 'Muy próxima a Luna nueva', 'default' => 'Está sobre el horizonte, pero es prácticamente imposible verla.'],
            'home.moon.new_thin' => ['label' => 'Luna muy fina', 'default' => 'Está muy finita y cuesta encontrarla a simple vista.'],
            'home.moon.very_low' => ['label' => 'Altura muy baja', 'default' => 'Está visible, muy baja hacia {direccion}.', 'allowed' => ['direccion'], 'required' => ['direccion']],
            'home.moon.low' => ['label' => 'Altura baja', 'default' => 'Está visible, baja hacia {direccion}.', 'allowed' => ['direccion'], 'required' => ['direccion']],
            'home.moon.medium' => ['label' => 'Altura media', 'default' => 'Está visible, a media altura hacia {direccion}.', 'allowed' => ['direccion'], 'required' => ['direccion']],
            'home.moon.high' => ['label' => 'Altura muy alta', 'default' => 'Está visible, muy alta. Mirá casi hacia arriba.'],
            'home.moon.overhead' => ['label' => 'Sobre la cabeza', 'default' => 'Está visible, prácticamente sobre tu cabeza.'],
        ]],
        'moonrise' => ['context' => 'Inicio', 'label' => 'Próxima salida de la Luna', 'precedence' => true, 'parameters' => [
            'home.moonrise.max_minutes' => ['label' => 'Ventana máxima', 'default' => 120, 'min' => 1, 'max' => 1440, 'step' => 1, 'unit' => 'min'],
            'home.moonrise.clock_minutes' => ['label' => 'Mostrar horario desde', 'default' => 60, 'min' => 16, 'max' => 1439, 'step' => 1, 'unit' => 'min'],
            'home.moonrise.soon_minutes' => ['label' => 'Aviso cercano desde', 'default' => 15, 'min' => 6, 'max' => 120, 'step' => 1, 'unit' => 'min'],
            'home.moonrise.imminent_minutes' => ['label' => 'Aviso inminente desde', 'default' => 5, 'min' => 1, 'max' => 60, 'step' => 1, 'unit' => 'min'],
        ], 'texts' => [
            'home.moonrise.clock' => ['label' => 'Con horario', 'default' => 'La Luna saldrá a las {hora}.', 'allowed' => ['hora'], 'required' => ['hora']],
            'home.moonrise.minutes' => ['label' => 'En minutos', 'default' => 'La Luna saldrá en {minutos}.', 'allowed' => ['minutos'], 'required' => ['minutos']],
            'home.moonrise.imminent' => ['label' => 'Por salir', 'default' => 'La Luna está por salir.'],
            'home.moonrise.now' => ['label' => 'Salida inmediata', 'default' => 'Preparate: la Luna está por salir.'],
        ]],
        'events' => ['context' => 'Eventos', 'label' => 'Conjunciones, fases y órbita', 'parameters' => [
            'event.conjunction.very_close_degrees' => ['label' => 'Conjunción muy junta hasta', 'default' => 1.0, 'min' => 0.0, 'max' => 20.0, 'step' => .1, 'unit' => '°'],
            'event.conjunction.close_degrees' => ['label' => 'Conjunción muy cerca hasta', 'default' => 3.0, 'min' => .1, 'max' => 30.0, 'step' => .1, 'unit' => '°'],
            'event.libration.strong_degrees' => ['label' => 'Libración destacable desde', 'default' => 7.2, 'min' => 0.0, 'max' => 10.0, 'step' => .1, 'unit' => '°'],
            'event.supermoon.min_percent' => ['label' => 'Superluna desde', 'default' => 105.0, 'min' => 90.0, 'max' => 120.0, 'step' => .1, 'unit' => '%'],
            'event.new_moon.max_illumination_percent' => ['label' => 'Ocultar Luna nueva debajo de', 'default' => .4, 'min' => 0.0, 'max' => 5.0, 'step' => .1, 'unit' => '%'],
            'event.full_moon.max_difference_minutes' => ['label' => 'Tolerancia de observación de Luna llena', 'default' => 70, 'min' => 1, 'max' => 240, 'step' => 1, 'unit' => 'min'],
        ], 'texts' => [
            'event.conjunction.very_close' => ['label' => 'Título: muy juntas', 'default' => '{objeto} y la Luna estarán muy juntas', 'allowed' => ['objeto'], 'required' => ['objeto']],
            'event.conjunction.close' => ['label' => 'Título: muy cerca', 'default' => '{objeto} y la Luna estarán muy cerca', 'allowed' => ['objeto'], 'required' => ['objeto']],
            'event.conjunction.other' => ['label' => 'Título: resto', 'default' => '{objeto} pasará cerca de la Luna', 'allowed' => ['objeto'], 'required' => ['objeto']],
            'event.conjunction.visible' => ['label' => 'Descripción si ambos son visibles', 'default' => 'Se podrán ver juntos alrededor de esa hora.'],
            'event.conjunction.both_above' => ['label' => 'Explicación visible', 'default' => 'Ambos estarán sobre el horizonte desde esta ubicación.'],
            'event.conjunction.not_together' => ['label' => 'Explicación no simultánea', 'default' => 'No estarán visibles simultáneamente desde esta ubicación.'],
            'event.perigee.summary' => ['label' => 'Descripción de perigeo', 'default' => 'Se verá un poco más grande de lo habitual.'],
            'event.apogee.summary' => ['label' => 'Descripción de apogeo', 'default' => 'Se verá un poco más pequeña de lo habitual.'],
            'event.quarter.summary' => ['label' => 'Descripción de cuartos', 'default' => 'Un buen momento para observar cráteres, montañas y sombras en la superficie lunar.'],
            'event.supermoon.title' => ['label' => 'Título de superluna', 'default' => 'Superluna'],
            'event.supermoon.summary' => ['label' => 'Descripción de superluna', 'default' => 'La Luna llena se verá más grande de lo habitual.'],
            'event.earthshine.explanation' => ['label' => 'Luz cenicienta', 'default' => 'También puede verse la parte oscura del disco lunar.'],
            'event.libration.strong' => ['label' => 'Libración destacable', 'default' => 'Con telescopio o una fotografía detallada puede notarse mejor cerca del borde favorecido.'],
            'event.libration.subtle' => ['label' => 'Libración sutil', 'default' => 'Es un efecto sutil, más fácil de apreciar comparando fotografías tomadas en distintas fechas.'],
        ]],
        'today' => ['context' => 'El cielo hoy', 'label' => 'Visibilidad lunar y partes del día', 'parameters' => [
            'today.visibility.soon_minutes' => ['label' => 'Aviso cercano hasta', 'default' => 60, 'min' => 1, 'max' => 240, 'step' => 1, 'unit' => 'min'],
            'today.daypart.morning_hour' => ['label' => 'La mañana comienza a las', 'default' => 6, 'min' => 1, 'max' => 10, 'step' => 1, 'unit' => 'h'],
            'today.daypart.afternoon_hour' => ['label' => 'La tarde comienza a las', 'default' => 12, 'min' => 7, 'max' => 16, 'step' => 1, 'unit' => 'h'],
            'today.daypart.night_hour' => ['label' => 'La noche comienza a las', 'default' => 19, 'min' => 13, 'max' => 23, 'step' => 1, 'unit' => 'h'],
            'today.venus_belt.min_illumination_percent' => ['label' => 'Cinturón de Venus: iluminación mínima', 'default' => 95, 'min' => 80, 'max' => 100, 'step' => 1, 'unit' => '%'],
            'today.venus_belt.max_difference_minutes' => ['label' => 'Cinturón de Venus: diferencia máxima', 'default' => 90, 'min' => 1, 'max' => 240, 'step' => 1, 'unit' => 'min'],
        ], 'texts' => [
            'today.moon.no_intervals' => ['label' => 'Sin intervalos', 'default' => 'No estará sobre el horizonte durante esta fecha.'],
            'today.moon.unavailable' => ['label' => 'Datos no disponibles', 'default' => 'La visibilidad lunar no está disponible para esta fecha.'],
            'today.moon.sets_soon' => ['label' => 'Se pondrá pronto', 'default' => 'Ya está visible y se pondrá dentro de {minutos}.', 'allowed' => ['minutos'], 'required' => ['minutos']],
            'today.moon.visible_part' => ['label' => 'Visible durante una parte del día', 'default' => 'Ya está visible y seguirá viéndose durante {parte_dia}.', 'allowed' => ['parte_dia'], 'required' => ['parte_dia']],
            'today.moon.rises_soon' => ['label' => 'Saldrá pronto', 'default' => 'Ahora no está sobre el horizonte; saldrá dentro de {minutos}.', 'allowed' => ['minutos'], 'required' => ['minutos']],
            'today.moon.returns_part' => ['label' => 'Volverá durante una parte del día', 'default' => 'Ahora no está sobre el horizonte; volverá a verse durante {parte_dia}.', 'allowed' => ['parte_dia'], 'required' => ['parte_dia']],
            'today.moon.finished' => ['label' => 'Ya terminó', 'default' => 'Ya no volverá a estar sobre el horizonte durante esta fecha.'],
            'today.venus_belt.message' => ['label' => 'Oportunidad del cinturón de Venus', 'default' => 'Al atardecer, mirá hacia el este: si el cielo acompaña, la Luna podría aparecer sobre el cinturón de Venus, la franja rosada que a veces aparece sobre el horizonte opuesto al Sol.'],
        ]],
        'upcoming' => ['context' => 'Inicio', 'label' => 'Límites de Lo próximo', 'parameters' => [
            'home.upcoming.max_days' => ['label' => 'Ventana máxima', 'default' => 30, 'min' => 14, 'max' => 90, 'step' => 1, 'unit' => 'días'],
            'home.upcoming.max_items' => ['label' => 'Cantidad máxima de destacados', 'default' => 6, 'min' => 1, 'max' => 20, 'step' => 1, 'unit' => 'eventos'],
        ]],
        'tonight' => ['context' => 'El cielo esta noche', 'label' => 'Momentos y visibilidad nocturna', 'parameters' => [
            'tonight.dusk_max_ratio' => ['label' => 'Al anochecer: primer tramo', 'default' => .20, 'min' => 0.0, 'max' => .5, 'step' => .01, 'unit' => 'proporción'],
            'tonight.dawn_min_ratio' => ['label' => 'Antes del amanecer: desde', 'default' => .72, 'min' => .5, 'max' => 1.0, 'step' => .01, 'unit' => 'proporción'],
            'tonight.dawn_tolerance_minutes' => ['label' => 'Tolerancia al amanecer', 'default' => 10, 'min' => 0, 'max' => 120, 'step' => 1, 'unit' => 'min'],
            'tonight.dusk_tolerance_minutes' => ['label' => 'Tolerancia al anochecer', 'default' => 45, 'min' => 0, 'max' => 180, 'step' => 1, 'unit' => 'min'],
            'tonight.long_remaining_minutes' => ['label' => 'Varias horas desde', 'default' => 180, 'min' => 30, 'max' => 720, 'step' => 1, 'unit' => 'min'],
            'tonight.long_window_minutes' => ['label' => 'Gran parte de la noche desde', 'default' => 240, 'min' => 30, 'max' => 720, 'step' => 1, 'unit' => 'min'],
            'tonight.highlights.max' => ['label' => 'Máximo de destacados', 'default' => 3, 'min' => 1, 'max' => 6, 'step' => 1, 'unit' => 'eventos'],
        ], 'texts' => [
            'tonight.moment.dusk' => ['label' => 'Momento: anochecer', 'default' => 'Al anochecer'],
            'tonight.moment.night' => ['label' => 'Momento: durante la noche', 'default' => 'Durante la noche'],
            'tonight.moment.dawn' => ['label' => 'Momento: amanecer', 'default' => 'Antes del amanecer'],
            'tonight.visible.until_dawn' => ['label' => 'Visible hasta el amanecer', 'default' => 'Está visible ahora y seguirá viéndose hasta el amanecer.'],
            'tonight.visible.until_time' => ['label' => 'Visible ahora hasta una hora', 'default' => 'Está visible ahora hasta las {fin}.', 'allowed' => ['fin'], 'required' => ['fin']],
            'tonight.visible.long_until_time' => ['label' => 'Visible varias horas', 'default' => 'Seguirá visible hasta las {fin}.', 'allowed' => ['fin'], 'required' => ['fin']],
            'tonight.future.all_night' => ['label' => 'Toda la noche', 'default' => 'Estará visible desde el anochecer hasta el amanecer.'],
            'tonight.future.long' => ['label' => 'Gran parte de la noche', 'default' => 'Podrá verse durante gran parte de la noche, desde las {inicio}.', 'allowed' => ['inicio'], 'required' => ['inicio']],
            'tonight.state.hours' => ['label' => 'Quedan varias horas', 'default' => 'La noche ya comenzó. Quedan varias horas para observar.'],
            'tonight.state.until' => ['label' => 'Noche hasta una hora', 'default' => 'La noche ya comenzó y continuará hasta las {fin}.', 'allowed' => ['fin'], 'required' => ['fin']],
        ]],
        'clouds' => ['context' => 'Condiciones para observar', 'label' => 'Nubosidad', 'parameters' => [
            'cloud.clear_max_percent' => ['label' => 'Despejado hasta', 'default' => 20, 'min' => 0, 'max' => 99, 'step' => 1, 'unit' => '%'],
            'cloud.some_max_percent' => ['label' => 'Algunas nubes hasta', 'default' => 50, 'min' => 1, 'max' => 99, 'step' => 1, 'unit' => '%'],
            'cloud.mostly_max_percent' => ['label' => 'Mayormente nublado hasta', 'default' => 80, 'min' => 1, 'max' => 100, 'step' => 1, 'unit' => '%'],
            'cloud.event_tolerance_minutes' => ['label' => 'Tolerancia horaria para eventos', 'default' => 30, 'min' => 0, 'max' => 180, 'step' => 1, 'unit' => 'min'],
        ], 'texts' => [
            'cloud.clear' => ['label' => 'Despejado', 'default' => 'Despejado'],
            'cloud.some' => ['label' => 'Algunas nubes', 'default' => 'Algunas nubes'],
            'cloud.mostly' => ['label' => 'Mayormente nublado', 'default' => 'Mayormente nublado'],
            'cloud.overcast' => ['label' => 'Cubierto', 'default' => 'Cubierto'],
        ]],
        'eclipses' => ['context' => 'Eclipses', 'label' => 'Visibilidad local de eclipses', 'texts' => [
            'eclipse.visibility.unknown' => ['label' => 'Visibilidad desconocida', 'default' => 'No se pudo determinar la visibilidad local desde tu ubicación.'],
            'eclipse.lunar.not_visible' => ['label' => 'Lunar no visible', 'default' => 'No será visible desde tu ubicación.'],
            'eclipse.lunar.penumbral' => ['label' => 'Lunar penumbral', 'default' => 'Desde tu ubicación sólo será visible la fase penumbral.'],
            'eclipse.lunar.partial' => ['label' => 'Lunar parcial', 'default' => 'Desde tu ubicación se podrá ver parcialmente.'],
            'eclipse.lunar.total' => ['label' => 'Lunar total', 'default' => 'Desde tu ubicación se podrá ver la fase total.'],
            'eclipse.solar.not_visible' => ['label' => 'Solar no visible', 'default' => 'El eclipse ocurrirá, pero no será visible desde tu ubicación.'],
            'eclipse.solar.partial' => ['label' => 'Solar parcial', 'default' => 'Desde tu ubicación se verá como un eclipse parcial.'],
            'eclipse.solar.annular' => ['label' => 'Solar anular', 'default' => 'Desde tu ubicación se podrá observar la fase anular.'],
            'eclipse.solar.total' => ['label' => 'Solar total', 'default' => 'Desde tu ubicación se podrá observar la fase total.'],
            'eclipse.solar.hybrid' => ['label' => 'Solar híbrido', 'default' => 'Desde tu ubicación se podrá observar una fase central del eclipse.'],
        ]],
    ];
}

function astronomyEditorialDefinitions(): array
{
    $result = ['parameters' => [], 'texts' => []];
    foreach (astronomyEditorialCatalog() as $blockKey => $block) {
        foreach (['parameters', 'texts'] as $kind) {
            foreach ($block[$kind] ?? [] as $key => $definition) {
                $definition['block'] = $blockKey;
                $result[$kind][$key] = $definition;
            }
        }
    }
    return $result;
}

function astronomyEditorialInitialize(PDO $connection): void
{
    $connection->exec("CREATE TABLE IF NOT EXISTS admin_parametros_editoriales (clave VARCHAR(120) NOT NULL PRIMARY KEY, valor_decimal DECIMAL(14,4) NOT NULL, actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $connection->exec("CREATE TABLE IF NOT EXISTS admin_textos_editoriales (clave VARCHAR(120) NOT NULL PRIMARY KEY, valor_texto TEXT NOT NULL, actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    astronomyEditorialResetCache();
}

function &astronomyEditorialCache(): array
{
    static $cache = ['loaded' => false, 'available' => false, 'parameters' => [], 'texts' => []];
    return $cache;
}

function astronomyEditorialResetCache(): void
{
    $cache = &astronomyEditorialCache();
    $cache = ['loaded' => false, 'available' => false, 'parameters' => [], 'texts' => []];
}

function astronomyEditorialLoad(?callable $factory = null): array
{
    $cache = &astronomyEditorialCache();
    if ($factory === null && $cache['loaded']) return $cache;
    $state = ['loaded' => true, 'available' => false, 'parameters' => [], 'texts' => []];
    try {
        $connection = $factory ? $factory() : getWebDatabaseConnection();
        $state['parameters'] = $connection->query('SELECT clave,valor_decimal FROM admin_parametros_editoriales')->fetchAll(PDO::FETCH_KEY_PAIR);
        $state['texts'] = $connection->query('SELECT clave,valor_texto FROM admin_textos_editoriales')->fetchAll(PDO::FETCH_KEY_PAIR);
        $state['available'] = true;
    } catch (Throwable) {
        static $reported = false;
        if (!$reported) { error_log('Aquellas Lunas editorial configuration unavailable.'); $reported = true; }
    }
    if ($factory === null) $cache = $state;
    return $state;
}

function astronomyEditorialNumber(string $key, ?callable $factory = null): float
{
    $definition = astronomyEditorialDefinitions()['parameters'][$key] ?? null;
    if (!is_array($definition)) throw new InvalidArgumentException('Parámetro editorial desconocido.');
    $state = astronomyEditorialLoad($factory);
    return isset($state['parameters'][$key]) ? (float) $state['parameters'][$key] : (float) $definition['default'];
}

function astronomyEditorialValueState(string $kind, string $key, ?callable $factory = null): array
{
    $definitions = astronomyEditorialDefinitions();
    if (!in_array($kind, ['parameters', 'texts'], true) || !isset($definitions[$kind][$key])) throw new InvalidArgumentException('Valor editorial desconocido.');
    $state = astronomyEditorialLoad($factory);
    $modified = $state['available'] && array_key_exists($key, $state[$kind]);
    $value = $modified ? $state[$kind][$key] : $definitions[$kind][$key]['default'];
    return ['value' => $kind === 'parameters' ? (float) $value : (string) $value, 'modified' => $modified, 'status' => $modified ? 'Modificado' : 'Predeterminado'];
}

function astronomyEditorialTemplate(string $key, ?callable $factory = null): string
{
    $definition = astronomyEditorialDefinitions()['texts'][$key] ?? null;
    if (!is_array($definition)) throw new InvalidArgumentException('Texto editorial desconocido.');
    $state = astronomyEditorialLoad($factory);
    return isset($state['texts'][$key]) ? (string) $state['texts'][$key] : (string) $definition['default'];
}

function astronomyEditorialText(string $key, array $values = [], ?callable $factory = null): string
{
    $definition = astronomyEditorialDefinitions()['texts'][$key] ?? null;
    if (!is_array($definition)) throw new InvalidArgumentException('Texto editorial desconocido.');
    $template = astronomyEditorialTemplate($key, $factory);
    $replace = [];
    foreach ($definition['allowed'] ?? [] as $placeholder) $replace['{' . $placeholder . '}'] = (string) ($values[$placeholder] ?? '');
    return strtr($template, $replace);
}

function astronomyEditorialValidateText(string $key, string $value): string
{
    $definition = astronomyEditorialDefinitions()['texts'][$key] ?? null;
    $value = trim($value);
    if (!is_array($definition) || $value === '' || strlen($value) > 1000 || preg_match('//u', $value) !== 1) throw new InvalidArgumentException('El texto editorial no es válido.');
    preg_match_all('/\{([a-z_]+)\}/u', $value, $matches);
    $found = array_values(array_unique($matches[1] ?? []));
    if (str_contains(preg_replace('/\{[a-z_]+\}/u', '', $value) ?? $value, '{') || str_contains(preg_replace('/\{[a-z_]+\}/u', '', $value) ?? $value, '}')) throw new InvalidArgumentException('El texto contiene un placeholder no válido.');
    foreach ($found as $placeholder) if (!in_array($placeholder, $definition['allowed'] ?? [], true)) throw new InvalidArgumentException('El texto contiene un placeholder no permitido: {' . $placeholder . '}.');
    foreach ($definition['required'] ?? [] as $placeholder) if (!in_array($placeholder, $found, true)) throw new InvalidArgumentException('El texto debe conservar {' . $placeholder . '}.');
    return $value;
}

function astronomyEditorialValidateValues(array $parameters, array $texts): void
{
    $definitions = astronomyEditorialDefinitions();
    foreach ($parameters as $key => $value) {
        $definition = $definitions['parameters'][$key] ?? null;
        if (!is_array($definition) || !is_numeric($value) || !is_finite((float) $value) || (float) $value < $definition['min'] || (float) $value > $definition['max']) throw new InvalidArgumentException('Revisá los límites de los parámetros editoriales.');
    }
    foreach ($texts as $key => $value) astronomyEditorialValidateText((string) $key, (string) $value);
    $value = static fn(string $key): float => array_key_exists($key, $parameters) ? (float) $parameters[$key] : astronomyEditorialNumber($key);
    foreach ([
        ['home.altitude.very_low_max','home.altitude.low_max','home.altitude.medium_max','home.altitude.high_max'],
        ['home.new_moon.impossible_days','home.new_moon.thin_days'],
        ['home.moonrise.imminent_minutes','home.moonrise.soon_minutes','home.moonrise.clock_minutes','home.moonrise.max_minutes'],
        ['event.conjunction.very_close_degrees','event.conjunction.close_degrees'],
        ['today.daypart.morning_hour','today.daypart.afternoon_hour','today.daypart.night_hour'],
        ['tonight.dusk_max_ratio','tonight.dawn_min_ratio'],
        ['cloud.clear_max_percent','cloud.some_max_percent','cloud.mostly_max_percent'],
    ] as $ordered) {
        $last = null;
        foreach ($ordered as $key) { $current = $value($key); if ($last !== null && $current <= $last) throw new InvalidArgumentException('Los límites de una banda deben quedar ordenados de menor a mayor.'); $last = $current; }
    }
}

function astronomyEditorialSave(PDO $connection, array $parameters, array $texts): void
{
    astronomyEditorialValidateValues($parameters, $texts);
    $definitions = astronomyEditorialDefinitions();
    $connection->beginTransaction();
    try {
        $parameterUpsert = $connection->prepare('INSERT INTO admin_parametros_editoriales (clave,valor_decimal) VALUES (:key,:value) ON DUPLICATE KEY UPDATE valor_decimal=VALUES(valor_decimal)');
        $parameterDelete = $connection->prepare('DELETE FROM admin_parametros_editoriales WHERE clave=:key');
        foreach ($parameters as $key => $value) ((float) $value === (float) $definitions['parameters'][$key]['default'] ? $parameterDelete : $parameterUpsert)->execute(['key' => $key] + ((float) $value === (float) $definitions['parameters'][$key]['default'] ? [] : ['value' => $value]));
        $textUpsert = $connection->prepare('INSERT INTO admin_textos_editoriales (clave,valor_texto) VALUES (:key,:value) ON DUPLICATE KEY UPDATE valor_texto=VALUES(valor_texto)');
        $textDelete = $connection->prepare('DELETE FROM admin_textos_editoriales WHERE clave=:key');
        foreach ($texts as $key => $value) ((string) $value === (string) $definitions['texts'][$key]['default'] ? $textDelete : $textUpsert)->execute(['key' => $key] + ((string) $value === (string) $definitions['texts'][$key]['default'] ? [] : ['value' => trim((string) $value)]));
        $connection->commit();
    } catch (Throwable $exception) { if ($connection->inTransaction()) $connection->rollBack(); throw $exception; }
    astronomyEditorialResetCache();
}

function astronomyEditorialRestoreBlock(PDO $connection, string $block): void
{
    $catalog = astronomyEditorialCatalog();
    if (!isset($catalog[$block])) throw new InvalidArgumentException('Bloque editorial desconocido.');
    $connection->beginTransaction();
    try {
        foreach ([['admin_parametros_editoriales','parameters'], ['admin_textos_editoriales','texts']] as [$table,$kind]) {
            $statement = $connection->prepare('DELETE FROM ' . $table . ' WHERE clave=:key');
            foreach (array_keys($catalog[$block][$kind] ?? []) as $key) $statement->execute(['key' => $key]);
        }
        $connection->commit();
    } catch (Throwable $exception) { if ($connection->inTransaction()) $connection->rollBack(); throw $exception; }
    astronomyEditorialResetCache();
}

function astronomyEditorialFrontendConfiguration(): array
{
    return ['clouds' => [
        'clearMaxPercent' => astronomyEditorialNumber('cloud.clear_max_percent'),
        'someMaxPercent' => astronomyEditorialNumber('cloud.some_max_percent'),
        'mostlyMaxPercent' => astronomyEditorialNumber('cloud.mostly_max_percent'),
        'eventToleranceMinutes' => astronomyEditorialNumber('cloud.event_tolerance_minutes'),
        'labels' => ['clear' => astronomyEditorialText('cloud.clear'), 'some' => astronomyEditorialText('cloud.some'), 'mostly' => astronomyEditorialText('cloud.mostly'), 'overcast' => astronomyEditorialText('cloud.overcast')],
    ]];
}

function renderAstronomyEditorialFrontendConfiguration(): void
{
    $json = json_encode(astronomyEditorialFrontendConfiguration(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<script>globalThis.AstronomyEditorialConfiguration=' . ($json === false ? '{}' : $json) . ';</script>';
}
