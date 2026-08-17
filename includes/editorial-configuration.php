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
            'event.supermoon.size' => ['label' => 'Tamaño aparente de superluna', 'default' => 'Tamaño aparente: {porcentaje}% del promedio', 'allowed' => ['porcentaje'], 'required' => ['porcentaje']],
            'event.earthshine.explanation' => ['label' => 'Luz cenicienta', 'default' => 'También puede verse la parte oscura del disco lunar.'],
            'event.fallback.title' => ['label' => 'Evento lunar genérico', 'default' => 'Evento lunar'],
            'event.fallback.object' => ['label' => 'Otro astro', 'default' => 'Otro astro'],
            'event.perigee.title' => ['label' => 'Título de perigeo', 'default' => 'La Luna estará en su punto más cercano a la Tierra'],
            'event.apogee.title' => ['label' => 'Título de apogeo', 'default' => 'La Luna estará en su punto más lejano de la Tierra'],
            'event.earthshine.title' => ['label' => 'Título con luz cenicienta', 'default' => 'La parte oscura de la Luna también será visible'],
            'event.earthshine.morning_title' => ['label' => 'Título matutino sin luz cenicienta', 'default' => 'Luna fina antes del amanecer'],
            'event.earthshine.evening_title' => ['label' => 'Título vespertino sin luz cenicienta', 'default' => 'Luna fina después del atardecer'],
            'event.earthshine.morning_summary' => ['label' => 'Resumen matutino', 'default' => 'Poco antes del amanecer, hacia el este.'],
            'event.earthshine.evening_summary' => ['label' => 'Resumen vespertino', 'default' => 'Poco después del atardecer, hacia el oeste.'],
            'event.earthshine.details' => ['label' => 'Resumen técnico amigable', 'default' => 'Iluminación {iluminacion} % · Separación del Sol {separacion}° · La Luna {accion} {relacion}', 'allowed' => ['iluminacion', 'separacion', 'accion', 'relacion'], 'required' => ['iluminacion', 'separacion', 'accion', 'relacion']],
            'event.earthshine.period_morning' => ['label' => 'Período matutino', 'default' => 'Antes del amanecer'],
            'event.earthshine.period_evening' => ['label' => 'Período vespertino', 'default' => 'Después del atardecer'],
            'event.earthshine.relation_before' => ['label' => 'Relación anterior', 'default' => '{cantidad} antes', 'allowed' => ['cantidad'], 'required' => ['cantidad']],
            'event.earthshine.relation_after' => ['label' => 'Relación posterior', 'default' => '{cantidad} después', 'allowed' => ['cantidad'], 'required' => ['cantidad']],
            'event.earthshine.relation_same' => ['label' => 'Relación simultánea', 'default' => 'al mismo tiempo'],
            'event.libration.title' => ['label' => 'Título de libración', 'default' => 'Libración favorable hacia el {direccion}', 'allowed' => ['direccion'], 'required' => ['direccion']],
            'event.libration.summary' => ['label' => 'Resumen de libración', 'default' => 'En estos días la Luna deja ver un poco más de su borde {direccion}.', 'allowed' => ['direccion'], 'required' => ['direccion']],
            'event.libration.amplitude' => ['label' => 'Amplitud de libración', 'default' => 'Amplitud aproximada: {amplitud}°.', 'allowed' => ['amplitud'], 'required' => ['amplitud']],
            'event.libration.phase_illumination' => ['label' => 'Fase e iluminación', 'default' => '{fase}, {iluminacion} iluminada.', 'allowed' => ['fase', 'iluminacion'], 'required' => ['fase', 'iluminacion']],
            'event.libration.phase' => ['label' => 'Solo fase', 'default' => '{fase}.', 'allowed' => ['fase'], 'required' => ['fase']],
            'event.libration.illumination' => ['label' => 'Solo iluminación', 'default' => '{iluminacion} iluminada.', 'allowed' => ['iluminacion'], 'required' => ['iluminacion']],
            'event.full_moon.morning_title' => ['label' => 'Título matutino de Luna llena', 'default' => 'Luna llena cerca de la salida del Sol'],
            'event.full_moon.evening_title' => ['label' => 'Título vespertino de Luna llena', 'default' => 'Luna llena cerca de la puesta del Sol'],
            'event.full_moon.morning_explanation' => ['label' => 'Descripción matutina de Luna llena', 'default' => 'Una oportunidad para observar la Luna llena baja mientras comienza el día.'],
            'event.full_moon.evening_explanation' => ['label' => 'Descripción vespertina de Luna llena', 'default' => 'Una oportunidad para observar la Luna llena baja mientras termina el día.'],
            'event.full_moon.simultaneous_morning' => ['label' => 'Coincidencia matutina', 'default' => 'La Luna se pondrá al mismo tiempo que salga el Sol.'],
            'event.full_moon.simultaneous_evening' => ['label' => 'Coincidencia vespertina', 'default' => 'La Luna saldrá al mismo tiempo que se ponga el Sol.'],
            'event.full_moon.before_sunrise' => ['label' => 'Antes de la salida solar', 'default' => 'La Luna se pondrá {diferencia} antes de la salida del Sol.', 'allowed' => ['diferencia'], 'required' => ['diferencia']],
            'event.full_moon.after_sunrise' => ['label' => 'Después de la salida solar', 'default' => 'La Luna se pondrá {diferencia} después de la salida del Sol.', 'allowed' => ['diferencia'], 'required' => ['diferencia']],
            'event.full_moon.before_sunset' => ['label' => 'Antes de la puesta solar', 'default' => 'La Luna saldrá {diferencia} antes de la puesta del Sol.', 'allowed' => ['diferencia'], 'required' => ['diferencia']],
            'event.full_moon.after_sunset' => ['label' => 'Después de la puesta solar', 'default' => 'La Luna saldrá {diferencia} después de la puesta del Sol.', 'allowed' => ['diferencia'], 'required' => ['diferencia']],
            'event.phase.waxing_crescent' => ['label' => 'Fase intermedia creciente', 'default' => 'Luna creciente'],
            'event.phase.waxing_gibbous' => ['label' => 'Fase intermedia gibosa creciente', 'default' => 'Luna gibosa creciente'],
            'event.phase.waning_gibbous' => ['label' => 'Fase intermedia gibosa menguante', 'default' => 'Luna gibosa menguante'],
            'event.phase.waning_crescent' => ['label' => 'Fase intermedia menguante', 'default' => 'Luna menguante'],
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
            'today.venus_belt.max_cloud_percent' => ['label' => 'Nubosidad máxima para recomendar el Cinturón de Venus', 'default' => 80, 'min' => 0, 'max' => 100, 'step' => 1, 'unit' => '%'],
        ], 'texts' => [
            'today.moon.no_intervals' => ['label' => 'Sin intervalos', 'default' => 'No estará sobre el horizonte durante esta fecha.'],
            'today.moon.unavailable' => ['label' => 'Datos no disponibles', 'default' => 'La visibilidad lunar no está disponible para esta fecha.'],
            'today.moon.sets_soon' => ['label' => 'Se pondrá pronto', 'default' => 'Ya está visible y se pondrá dentro de {minutos}.', 'allowed' => ['minutos'], 'required' => ['minutos']],
            'today.moon.visible_part' => ['label' => 'Visible durante una parte del día', 'default' => 'Ya está visible y seguirá viéndose durante {parte_dia}.', 'allowed' => ['parte_dia'], 'required' => ['parte_dia']],
            'today.moon.rises_soon' => ['label' => 'Saldrá pronto', 'default' => 'Ahora no está sobre el horizonte; saldrá dentro de {minutos}.', 'allowed' => ['minutos'], 'required' => ['minutos']],
            'today.moon.returns_part' => ['label' => 'Volverá durante una parte del día', 'default' => 'Ahora no está sobre el horizonte; volverá a verse durante {parte_dia}.', 'allowed' => ['parte_dia'], 'required' => ['parte_dia']],
            'today.moon.finished' => ['label' => 'Ya terminó', 'default' => 'Ya no volverá a estar sobre el horizonte durante esta fecha.'],
            'today.venus_belt.message' => ['label' => 'Oportunidad del cinturón de Venus', 'default' => 'Al atardecer, mirá hacia el este: si el cielo acompaña, la Luna podría aparecer sobre el cinturón de Venus, la franja rosada que a veces aparece sobre el horizonte opuesto al Sol.'],
            'today.venus_belt.help' => ['label' => 'Ayuda del cinturón de Venus', 'default' => 'Con el horizonte despejado, mirá en dirección opuesta al Sol: a veces puede distinguirse el cinturón de Venus.'],
            'today.venus_belt.full_moon' => ['label' => 'Luna llena y cinturón de Venus', 'default' => 'Buscala hacia el este: si el horizonte está despejado, puede aparecer sobre el cinturón de Venus.'],
            'today.moon.multiple_intervals' => ['label' => 'Varios intervalos lunares', 'default' => 'Se verá durante {primera_parte} y volverá a aparecer durante {ultima_parte}.', 'allowed' => ['primera_parte', 'ultima_parte'], 'required' => ['primera_parte', 'ultima_parte']],
            'today.moon.single_interval' => ['label' => 'Un intervalo lunar', 'default' => 'Se verá durante {parte_dia} desde tu ubicación.', 'allowed' => ['parte_dia'], 'required' => ['parte_dia']],
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
            'tonight.highlights.planets_max' => ['label' => 'Planetas: máximo', 'default' => 2, 'min' => 0, 'max' => 6, 'step' => 1, 'unit' => 'objetos'],
            'tonight.highlights.stars_max' => ['label' => 'Estrellas: máximo', 'default' => 1, 'min' => 0, 'max' => 6, 'step' => 1, 'unit' => 'objetos'],
            'tonight.highlights.moon_max' => ['label' => 'Luna: máximo', 'default' => 1, 'min' => 0, 'max' => 1, 'step' => 1, 'unit' => 'objeto'],
            'tonight.highlights.moon_fill_below' => ['label' => 'Considerar la Luna si todavía hay menos de', 'default' => 2, 'min' => 1, 'max' => 6, 'step' => 1, 'unit' => 'destacados'],
            'tonight.stars.display_max' => ['label' => 'Estrellas visibles: máximo', 'default' => 3, 'min' => 0, 'max' => 12, 'step' => 1, 'unit' => 'estrellas'],
            'tonight.card.planets_max' => ['label' => 'Resumen: máximo de planetas', 'default' => 2, 'min' => 0, 'max' => 6, 'step' => 1, 'unit' => 'planetas'],
            'tonight.card.stars_max' => ['label' => 'Resumen: máximo de estrellas', 'default' => 2, 'min' => 0, 'max' => 6, 'step' => 1, 'unit' => 'estrellas'],
            'tonight.highlights.planets_order' => ['label' => 'Orden de Planetas', 'default' => 1, 'min' => 1, 'max' => 3, 'step' => 1, 'unit' => 'posición'],
            'tonight.highlights.stars_order' => ['label' => 'Orden de Estrellas', 'default' => 2, 'min' => 1, 'max' => 3, 'step' => 1, 'unit' => 'posición'],
            'tonight.highlights.moon_order' => ['label' => 'Orden de Luna', 'default' => 3, 'min' => 1, 'max' => 3, 'step' => 1, 'unit' => 'posición'],
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
            'tonight.future.from_dusk' => ['label' => 'Desde el comienzo de la noche', 'default' => 'Estará visible al comenzar la noche, hasta las {fin}.', 'allowed' => ['fin'], 'required' => ['fin']],
            'tonight.future.until_dawn' => ['label' => 'Hasta el amanecer', 'default' => 'Aparecerá a las {inicio} y podrá verse hasta el amanecer.', 'allowed' => ['inicio'], 'required' => ['inicio']],
            'tonight.future.until_time' => ['label' => 'Ventana nocturna breve', 'default' => 'Aparecerá a las {inicio} y seguirá visible hasta las {fin}.', 'allowed' => ['inicio', 'fin'], 'required' => ['inicio', 'fin']],
            'tonight.proximity.very_close' => ['label' => 'Muy cerca de la Luna', 'default' => 'Se verá muy cerca de la Luna.'],
            'tonight.proximity.close' => ['label' => 'Cerca de la Luna', 'default' => 'Se verá cerca de la Luna.'],
            'tonight.moon_encounter.section_title' => ['label' => 'Encuentros lunares: título de sección', 'default' => 'Objetos cerca de la Luna'],
            'tonight.moon_encounter.title' => ['label' => 'Encuentro lunar: título de tarjeta', 'default' => '{objeto} cerca de la Luna', 'allowed' => ['objeto'], 'required' => ['objeto']],
            'tonight.moon_encounter.text' => ['label' => 'Encuentro lunar: descripción', 'default' => 'Esta noche {objeto} y la Luna se verán separados por unos {separacion}°.', 'allowed' => ['objeto', 'separacion'], 'required' => ['objeto', 'separacion']],
            'tonight.moon_encounter.two_planets_text' => ['label' => 'Encuentro lunar: dos planetas', 'default' => 'Esta noche la Luna estará cerca de {primer_objeto} ({primera_separacion}°) y de {segundo_objeto} ({segunda_separacion}°).', 'allowed' => ['primer_objeto', 'primera_separacion', 'segundo_objeto', 'segunda_separacion'], 'required' => ['primer_objeto', 'primera_separacion', 'segundo_objeto', 'segunda_separacion']],
            'tonight.direction.toward' => ['label' => 'Dirección', 'default' => 'hacia el {direccion}', 'allowed' => ['direccion'], 'required' => ['direccion']],
            'tonight.direction.overhead' => ['label' => 'Dirección sobre la cabeza', 'default' => 'arriba'],
            'tonight.aid.naked_eye' => ['label' => 'Ayuda: simple vista', 'default' => 'A simple vista'],
            'tonight.aid.binoculars' => ['label' => 'Ayuda: binoculares', 'default' => 'Mejor con binoculares'],
            'tonight.aid.telescope' => ['label' => 'Ayuda: telescopio', 'default' => 'Requiere telescopio'],
            'tonight.aid.fragment.naked_eye' => ['label' => 'Fragmento: simple vista', 'default' => ' a simple vista'],
            'tonight.aid.fragment.binoculars' => ['label' => 'Fragmento: binoculares', 'default' => ', preferentemente con binoculares,'],
            'tonight.aid.fragment.telescope' => ['label' => 'Fragmento: telescopio', 'default' => ', con telescopio,'],
            'tonight.window.current' => ['label' => 'Ventana de esta noche', 'default' => 'Esta noche: {inicio}–{fin}', 'allowed' => ['inicio', 'fin'], 'required' => ['inicio', 'fin']],
            'tonight.window.continuous_darkness' => ['label' => 'Oscuridad continua', 'default' => 'Oscuridad continua durante esta noche'],
            'tonight.window.no_civil_darkness' => ['label' => 'Sin oscuridad civil', 'default' => 'Esta noche no tendrá oscuridad civil'],
            'tonight.window.unavailable' => ['label' => 'Ventana no disponible', 'default' => 'Ventana nocturna no disponible'],
            'tonight.temporal.not_started' => ['label' => 'Noche no iniciada', 'default' => 'La noche todavía no comenzó.'],
            'tonight.temporal.in_progress' => ['label' => 'Noche en curso', 'default' => 'La noche está en curso.'],
            'tonight.temporal.finished' => ['label' => 'Noche terminada', 'default' => 'La ventana de esta noche ya terminó.'],
            'tonight.temporal.no_civil_darkness' => ['label' => 'Sin ventana civil', 'default' => 'No habrá una ventana de oscuridad civil para esta fecha.'],
            'tonight.temporal.unavailable' => ['label' => 'Estado nocturno no disponible', 'default' => 'La ventana nocturna no está disponible.'],
            'tonight.state.starts' => ['label' => 'Hora de comienzo', 'default' => 'La noche comenzará a las {inicio}.', 'allowed' => ['inicio'], 'required' => ['inicio']],
            'tonight.planet.now_until' => ['label' => 'Planeta visible ahora con fin', 'default' => '{nombre} está visible ahora{direccion}, hasta las {fin}.', 'allowed' => ['nombre', 'direccion', 'fin'], 'required' => ['nombre', 'direccion', 'fin']],
            'tonight.planet.now' => ['label' => 'Planeta visible ahora', 'default' => '{nombre} está visible ahora{direccion}.', 'allowed' => ['nombre', 'direccion'], 'required' => ['nombre', 'direccion']],
            'tonight.planet.later_at' => ['label' => 'Planeta visible más tarde con hora', 'default' => '{nombre} aparecerá desde las {inicio}.', 'allowed' => ['nombre', 'inicio'], 'required' => ['nombre', 'inicio']],
            'tonight.planet.later' => ['label' => 'Planeta visible más tarde', 'default' => '{nombre} aparecerá más tarde.', 'allowed' => ['nombre'], 'required' => ['nombre']],
            'tonight.summary.one_planet' => ['label' => 'Título con un planeta', 'default' => 'Esta noche se verá 1 planeta'],
            'tonight.summary.many_planets' => ['label' => 'Título con varios planetas', 'default' => 'Esta noche se verán {cantidad} planetas', 'allowed' => ['cantidad'], 'required' => ['cantidad']],
            'tonight.object.now_until' => ['label' => 'Objeto visible ahora con fin', 'default' => 'Visible ahora{ayuda}{direccion}{separador}hasta las {fin}.', 'allowed' => ['ayuda', 'direccion', 'separador', 'fin'], 'required' => ['ayuda', 'direccion', 'separador', 'fin']],
            'tonight.object.now_rest' => ['label' => 'Objeto visible el resto de la noche', 'default' => 'Visible ahora{ayuda}{direccion}{separador}durante el resto de la noche.', 'allowed' => ['ayuda', 'direccion', 'separador'], 'required' => ['ayuda', 'direccion', 'separador']],
            'tonight.object.later' => ['label' => 'Objeto visible más tarde', 'default' => 'Será visible{ayuda} más tarde esta noche.', 'allowed' => ['ayuda'], 'required' => ['ayuda']],
            'tonight.object.window' => ['label' => 'Objeto con ventana horaria', 'default' => 'Será visible{ayuda} desde las {inicio} hasta las {fin}.', 'allowed' => ['ayuda', 'inicio', 'fin'], 'required' => ['ayuda', 'inicio', 'fin']],
            'tonight.object.until_dawn' => ['label' => 'Objeto hasta el amanecer', 'default' => 'Será visible{ayuda} desde las {inicio} hasta el amanecer.', 'allowed' => ['ayuda', 'inicio'], 'required' => ['ayuda', 'inicio']],
            'tonight.relevant.current' => ['label' => 'Ventana relevante actual', 'default' => 'Esta noche'],
            'tonight.relevant.next' => ['label' => 'Próxima ventana relevante', 'default' => 'La próxima noche'],
            'tonight.relevant.range' => ['label' => 'Rango nocturno relevante', 'default' => '{periodo}: {inicio}–{fin}', 'allowed' => ['periodo', 'inicio', 'fin'], 'required' => ['periodo', 'inicio', 'fin']],
            'tonight.card.encounter' => ['label' => 'Encuentro nocturno', 'default' => '{objetos} podrán verse juntos alrededor de las {hora}.', 'allowed' => ['objetos', 'hora'], 'required' => ['objetos', 'hora']],
            'tonight.card.visible_one' => ['label' => 'Un planeta visible', 'default' => '{inicio} estará visible {objetos}.', 'allowed' => ['inicio', 'objetos'], 'required' => ['inicio', 'objetos']],
            'tonight.card.visible_many' => ['label' => 'Varios planetas visibles', 'default' => '{inicio} estarán visibles {objetos}.', 'allowed' => ['inicio', 'objetos'], 'required' => ['inicio', 'objetos']],
            'tonight.card.star_one' => ['label' => 'Una estrella notable', 'default' => '{objetos} será una estrella notable para buscar esta noche.', 'allowed' => ['objetos'], 'required' => ['objetos']],
            'tonight.card.star_two' => ['label' => 'Dos estrellas notables', 'default' => '{objetos} serán dos estrellas notables para buscar esta noche.', 'allowed' => ['objetos'], 'required' => ['objetos']],
            'tonight.card.star_many' => ['label' => 'Varias estrellas notables', 'default' => '{objetos} serán estrellas notables para buscar esta noche.', 'allowed' => ['objetos'], 'required' => ['objetos']],
            'tonight.card.no_planets' => ['label' => 'Sin planetas visibles', 'default' => 'Esta noche no habrá planetas visibles a simple vista desde tu ubicación.'],
            'tonight.card.prefix.also' => ['label' => 'Prefijo después de un encuentro', 'default' => 'También'],
            'tonight.card.prefix.tonight' => ['label' => 'Prefijo del resumen nocturno', 'default' => 'Esta noche'],
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
            'cloud.today.average' => ['label' => 'Nubosidad media prevista', 'default' => 'Nubosidad media prevista: {porcentaje} %.', 'allowed' => ['porcentaje'], 'required' => ['porcentaje']],
            'cloud.today.general' => ['label' => 'Nubosidad general prevista', 'default' => 'Nubosidad general prevista: {porcentaje} %.', 'allowed' => ['porcentaje'], 'required' => ['porcentaje']],
            'cloud.today.best_moon' => ['label' => 'Mejor momento con Luna visible', 'default' => 'La menor nubosidad mientras la Luna esté sobre el horizonte se prevé cerca de las {hora}:00 ({porcentaje} %).', 'allowed' => ['hora', 'porcentaje'], 'required' => ['hora', 'porcentaje']],
            'cloud.today.best_day' => ['label' => 'Mejor momento del día', 'default' => 'La menor nubosidad del día se prevé cerca de las {hora}:00 ({porcentaje} %).', 'allowed' => ['hora', 'porcentaje'], 'required' => ['hora', 'porcentaje']],
            'cloud.today.unavailable' => ['label' => 'Pronóstico no disponible', 'default' => 'El pronóstico de nubosidad no está disponible para esta fecha.'],
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
            'eclipse.title.lunar.generic' => ['label' => 'Título lunar genérico', 'default' => 'Eclipse lunar'],
            'eclipse.title.lunar.penumbral' => ['label' => 'Título lunar penumbral', 'default' => 'Eclipse lunar penumbral'],
            'eclipse.title.lunar.partial' => ['label' => 'Título lunar parcial', 'default' => 'Eclipse lunar parcial'],
            'eclipse.title.lunar.total' => ['label' => 'Título lunar total', 'default' => 'Eclipse lunar total'],
            'eclipse.title.solar.generic' => ['label' => 'Título solar genérico', 'default' => 'Eclipse solar'],
            'eclipse.title.solar.partial' => ['label' => 'Título solar parcial', 'default' => 'Eclipse solar parcial'],
            'eclipse.title.solar.annular' => ['label' => 'Título solar anular', 'default' => 'Eclipse solar anular'],
            'eclipse.title.solar.total' => ['label' => 'Título solar total', 'default' => 'Eclipse solar total'],
            'eclipse.title.solar.hybrid' => ['label' => 'Título solar híbrido', 'default' => 'Eclipse solar híbrido'],
            'eclipse.short.not_visible' => ['label' => 'Etiqueta corta no visible', 'default' => 'No visible'],
            'eclipse.short.penumbral' => ['label' => 'Etiqueta corta penumbral', 'default' => 'Sólo fase penumbral'],
            'eclipse.short.lunar_partial' => ['label' => 'Etiqueta corta lunar parcial', 'default' => 'Visible parcialmente'],
            'eclipse.short.lunar_total' => ['label' => 'Etiqueta corta lunar total', 'default' => 'Visible en totalidad'],
            'eclipse.short.partial' => ['label' => 'Etiqueta corta parcial', 'default' => 'Parcial'],
            'eclipse.short.total' => ['label' => 'Etiqueta corta total', 'default' => 'Total'],
            'eclipse.short.annular' => ['label' => 'Etiqueta corta anular', 'default' => 'Anular'],
            'eclipse.short.hybrid' => ['label' => 'Etiqueta corta híbrida', 'default' => 'Híbrido'],
            'eclipse.boundary_alert' => ['label' => 'Alerta de límite geográfico', 'default' => 'Tu ubicación está muy cerca del límite calculado de la franja central. La duración y el tipo observado pueden variar con pequeños cambios de ubicación.'],
            'eclipse.explanation.unknown' => ['label' => 'Explicación no determinada', 'default' => 'No se pudo determinar la visibilidad local.'],
            'eclipse.page.visibility.unknown' => ['label' => 'Listado: sin determinar', 'default' => 'Visibilidad sin determinar'],
            'eclipse.page.visibility.not_visible' => ['label' => 'Listado: no visible', 'default' => 'No visible desde tu ubicación'],
            'eclipse.page.visibility.penumbral' => ['label' => 'Listado: penumbral', 'default' => 'Visible: sólo fase penumbral'],
            'eclipse.page.visibility.partial' => ['label' => 'Listado: parcial', 'default' => 'Visible: fase parcial'],
            'eclipse.page.visibility.total' => ['label' => 'Listado: total', 'default' => 'Visible: fase total'],
            'eclipse.page.visibility.annular' => ['label' => 'Listado: anular', 'default' => 'Visible: fase anular'],
            'eclipse.page.visibility.hybrid' => ['label' => 'Listado: híbrida', 'default' => 'Visible: fase híbrida'],
            'eclipse.page.solar_local' => ['label' => 'Título solar local', 'default' => 'Eclipse solar {tipo} (local)', 'allowed' => ['tipo'], 'required' => ['tipo']],
            'eclipse.page.type.partial' => ['label' => 'Tipo local parcial', 'default' => 'parcial'],
            'eclipse.page.type.annular' => ['label' => 'Tipo local anular', 'default' => 'anular'],
            'eclipse.page.type.total' => ['label' => 'Tipo local total', 'default' => 'total'],
            'eclipse.page.type.hybrid' => ['label' => 'Tipo local híbrido', 'default' => 'híbrido'],
            'eclipse.page.generic' => ['label' => 'Eclipse genérico', 'default' => 'Eclipse'],
        ]],
        'sun_moon' => ['context' => 'Sol y Luna', 'label' => 'Resúmenes de visibilidad', 'texts' => [
            'sun_moon.not_visible' => ['label' => 'No visible', 'default' => 'No visible durante el día'],
            'sun_moon.all_day' => ['label' => 'Visible todo el día', 'default' => 'Visible todo el día'],
            'sun_moon.one_interval' => ['label' => 'Un intervalo', 'default' => '1 intervalo de visibilidad'],
            'sun_moon.many_intervals' => ['label' => 'Varios intervalos', 'default' => '{cantidad} intervalos de visibilidad', 'allowed' => ['cantidad'], 'required' => ['cantidad']],
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
    static $cache = [
        'loaded' => false, 'available' => false,
        'parameters_loaded' => false, 'parameters_available' => false,
        'texts_loaded' => false, 'texts_available' => false,
        'parameters' => [], 'texts' => [],
    ];
    return $cache;
}

function astronomyEditorialResetCache(): void
{
    $cache = &astronomyEditorialCache();
    $cache = [
        'loaded' => false, 'available' => false,
        'parameters_loaded' => false, 'parameters_available' => false,
        'texts_loaded' => false, 'texts_available' => false,
        'parameters' => [], 'texts' => [],
    ];
}

function astronomyEditorialLoadKind(string $kind, ?callable $factory = null): array
{
    if (!in_array($kind, ['parameters', 'texts'], true)) {
        throw new InvalidArgumentException('El tipo de configuración editorial no está soportado.');
    }
    $cache = &astronomyEditorialCache();
    $loadedKey = $kind . '_loaded';
    $availableKey = $kind . '_available';
    if ($factory === null && $cache[$loadedKey]) {
        $state = $cache;
        $state['loaded'] = true;
        $state['available'] = $cache[$availableKey];
        return $state;
    }
    $state = $factory === null ? $cache : astronomyEditorialCache();
    $state[$loadedKey] = true;
    $state[$availableKey] = false;
    $state[$kind] = [];
    try {
        $connection = $factory ? $factory() : getWebDatabaseConnection();
        $state[$kind] = $kind === 'parameters'
            ? $connection->query('SELECT clave,valor_decimal FROM admin_parametros_editoriales')->fetchAll(PDO::FETCH_KEY_PAIR)
            : $connection->query('SELECT clave,valor_texto FROM admin_textos_editoriales')->fetchAll(PDO::FETCH_KEY_PAIR);
        $state[$availableKey] = true;
    } catch (Throwable) {
        static $reported = false;
        if (!$reported) { error_log('Aquellas Lunas editorial configuration unavailable.'); $reported = true; }
    }
    $state['loaded'] = true;
    $state['available'] = $state[$availableKey];
    if ($factory === null) {
        $cache = $state;
    }
    return $state;
}

function astronomyEditorialLoad(?callable $factory = null): array
{
    $parameters = astronomyEditorialLoadKind('parameters', $factory);
    $texts = astronomyEditorialLoadKind('texts', $factory);
    $parameters['texts'] = $texts['texts'];
    $parameters['texts_loaded'] = $texts['texts_loaded'];
    $parameters['texts_available'] = $texts['texts_available'];
    $parameters['loaded'] = true;
    $parameters['available'] = $parameters['parameters_available'] && $texts['texts_available'];
    return $parameters;
}

function astronomyEditorialNumber(string $key, ?callable $factory = null): float
{
    $definition = astronomyEditorialDefinitions()['parameters'][$key] ?? null;
    if (!is_array($definition)) throw new InvalidArgumentException('Parámetro editorial desconocido.');
    $state = astronomyEditorialLoadKind('parameters', $factory);
    return isset($state['parameters'][$key]) ? (float) $state['parameters'][$key] : (float) $definition['default'];
}

function astronomyEditorialValueState(string $kind, string $key, ?callable $factory = null): array
{
    $definitions = astronomyEditorialDefinitions();
    if (!in_array($kind, ['parameters', 'texts'], true) || !isset($definitions[$kind][$key])) throw new InvalidArgumentException('Valor editorial desconocido.');
    $state = astronomyEditorialLoadKind($kind, $factory);
    $modified = $state['available'] && array_key_exists($key, $state[$kind]);
    $value = $modified ? $state[$kind][$key] : $definitions[$kind][$key]['default'];
    return ['value' => $kind === 'parameters' ? (float) $value : (string) $value, 'modified' => $modified, 'status' => $modified ? 'Modificado' : 'Predeterminado'];
}

function astronomyEditorialTemplate(string $key, ?callable $factory = null): string
{
    $definition = astronomyEditorialDefinitions()['texts'][$key] ?? null;
    if (!is_array($definition)) throw new InvalidArgumentException('Texto editorial desconocido.');
    $state = astronomyEditorialLoadKind('texts', $factory);
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
    $highlightOrder = array_map($value, ['tonight.highlights.planets_order', 'tonight.highlights.stars_order', 'tonight.highlights.moon_order']);
    if (count(array_unique($highlightOrder)) !== 3) throw new InvalidArgumentException('Cada categoría de destacados debe ocupar una posición diferente.');
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
    ], 'today' => [
        'venusBeltMaxCloudPercent' => astronomyEditorialNumber('today.venus_belt.max_cloud_percent'),
        'venusBeltHelp' => astronomyEditorialText('today.venus_belt.help'),
        'cloudAverage' => astronomyEditorialTemplate('cloud.today.average'),
        'cloudGeneral' => astronomyEditorialTemplate('cloud.today.general'),
        'cloudBestMoon' => astronomyEditorialTemplate('cloud.today.best_moon'),
        'cloudBestDay' => astronomyEditorialTemplate('cloud.today.best_day'),
        'cloudUnavailable' => astronomyEditorialText('cloud.today.unavailable'),
    ]];
}

function renderAstronomyEditorialFrontendConfiguration(): void
{
    $json = json_encode(astronomyEditorialFrontendConfiguration(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<script>globalThis.AstronomyEditorialConfiguration=' . ($json === false ? '{}' : $json) . ';</script>';
}
