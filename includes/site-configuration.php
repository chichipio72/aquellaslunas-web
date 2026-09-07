<?php

declare(strict_types=1);

require_once __DIR__ . '/web-database.php';

function astronomySiteConfigCatalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $catalog = [
        'content.enabled' => [
            'group' => 'content',
            'label' => 'Contenidos públicos',
            'default' => true,
            'description' => 'Habilita el acceso público a contenidos editoriales.',
        ],

        'astronomy.trace.enabled' => [
            'group' => 'diagnostics',
            'label' => 'Trazabilidad de consultas astronómicas',
            'default' => false,
            'description' => 'Registra entradas y respuestas finales para reproducir consultas astronómicas. No guarda identidad personal, IP ni user-agent.',
        ],

        'menu.home.enabled' => [
            'group' => 'menu',
            'label' => 'Inicio',
            'default' => true,
            'description' => 'Muestra la entrada Inicio en el menú principal.',
        ],
        'menu.today.enabled' => [
            'group' => 'menu',
            'label' => 'El cielo hoy',
            'default' => true,
            'description' => 'Muestra la entrada El cielo hoy en el menú principal.',
        ],
        'menu.tonight.enabled' => [
            'group' => 'menu',
            'label' => 'El cielo esta noche',
            'default' => true,
            'description' => 'Muestra la entrada El cielo esta noche en el menú principal.',
        ],
        'menu.sun_moon.enabled' => [
            'group' => 'menu',
            'label' => 'Calendario solar y lunar',
            'default' => true,
            'description' => 'Muestra la entrada Calendario solar y lunar en el menú principal.',
        ],
        'menu.interactive_moon.enabled' => [
            'group' => 'menu',
            'label' => 'Luna interactiva',
            'default' => true,
            'description' => 'Muestra la herramienta Luna interactiva en el menú principal.',
        ],
        'menu.events.enabled' => [
            'group' => 'menu',
            'label' => 'Eventos lunares',
            'default' => true,
            'description' => 'Muestra la entrada Eventos lunares en el menú principal.',
        ],
        'menu.eclipses.enabled' => [
            'group' => 'menu',
            'label' => 'Eclipses',
            'default' => true,
            'description' => 'Muestra la entrada Eclipses en el menú principal.',
        ],
        'menu.planner.enabled' => [
            'group' => 'menu',
            'label' => 'Planificador',
            'default' => true,
            'description' => 'Muestra la entrada Planificador en el menú principal.',
        ],
        'menu.explorer.enabled' => [
            'group' => 'menu',
            'label' => 'Explorador astronómico',
            'default' => true,
            'description' => 'Muestra la entrada Explorador astronómico en el menú principal; la URL directa continúa disponible al ocultarla.',
        ],
        'menu.gallery.enabled' => [
            'group' => 'menu',
            'label' => 'Galería',
            'default' => false,
            'description' => 'Muestra la entrada Galería en el menú principal.',
        ],
        'menu.content.enabled' => [
            'group' => 'menu',
            'label' => 'Contenidos',
            'default' => true,
            'description' => 'Muestra la entrada Contenidos en el menú principal.',
        ],
        'menu.location.enabled' => [
            'group' => 'menu',
            'label' => 'Ubicación',
            'default' => true,
            'description' => 'Muestra la entrada Ubicación en el menú principal.',
        ],
        'menu.notifications.enabled' => [
            'group' => 'menu',
            'label' => 'Configurar notificaciones',
            'default' => true,
            'description' => 'Muestra el acceso público a las preferencias de notificaciones de este dispositivo.',
        ],
        'menu.moon_songs.enabled' => [
            'group' => 'menu',
            'label' => 'Canciones a la Luna',
            'default' => true,
            'description' => 'Muestra la playlist lunar en el menú principal.',
        ],
        'menu.capabilities.enabled' => [
            'group' => 'menu',
            'label' => 'Qué ofrece Aquellas Lunas',
            'default' => true,
            'description' => 'Muestra la entrada Qué ofrece Aquellas Lunas en el menú principal.',
        ],
        'menu.about.enabled' => [
            'group' => 'menu',
            'label' => 'Acerca del sitio',
            'default' => true,
            'description' => 'Muestra la entrada Acerca del sitio en el menú principal.',
        ],
        'menu.administration.enabled' => [
            'group' => 'menu',
            'label' => 'Administración',
            'default' => true,
            'description' => 'Muestra la entrada Administración en el menú principal.',
        ],

        'home.today.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta El cielo hoy',
            'default' => true,
            'description' => 'Muestra la tarjeta El cielo hoy en la portada.',
        ],
        'home.tonight.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta El cielo esta noche',
            'default' => true,
            'description' => 'Muestra la tarjeta El cielo esta noche en la portada.',
        ],
        'home.phases.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta Próximas fases',
            'default' => true,
            'description' => 'Muestra la tarjeta Próximas fases en la portada.',
        ],
        'home.upcoming.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta Lo próximo',
            'default' => true,
            'description' => 'Muestra la tarjeta Lo próximo en la portada.',
        ],
        'home.satellite_transits.enabled' => [
            'group' => 'home',
            'label' => 'Cálculo de tránsitos satelitales',
            'default' => true,
            'description' => 'Calcula y muestra acercamientos y tránsitos de ISS y Tiangong frente al Sol y la Luna en la portada.',
        ],
        'home.explore_sky.enabled' => [
            'group' => 'home',
            'label' => 'Bloque Explorá el cielo',
            'default' => true,
            'description' => 'Muestra el bloque Explorá el cielo en la portada.',
        ],
        'home.trivia.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta Trivia',
            'default' => true,
            'description' => 'Muestra la tarjeta Trivia en la portada.',
        ],
        'home.sabias_que.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta Sabías que',
            'default' => true,
            'description' => 'Muestra la tarjeta Sabías que en la portada.',
        ],
        'home.install.enabled' => [
            'group' => 'home',
            'label' => 'Tarjeta Guardar / instalar',
            'default' => true,
            'description' => 'Muestra la tarjeta Guardar o instalar Aquellas Lunas en la portada.',
        ],

        'eclipse.upcoming_notice.enabled' => [
            'group' => 'eclipses',
            'type' => 'boolean',
            'label' => 'Mostrar avisos de eclipses próximos',
            'default' => true,
            'description' => 'Muestra en portada un aviso cuando exista un eclipse visible desde la ubicación activa dentro del período configurado.',
        ],
        'eclipse.upcoming_notice.days' => [
            'group' => 'eclipses',
            'type' => 'integer',
            'label' => 'Anticipación del aviso',
            'default' => 10,
            'min' => 1,
            'max' => 90,
            'description' => 'Cantidad de días hacia adelante que revisa el aviso de portada.',
        ],
        'eclipse.widget.solar_url' => [
            'group' => 'eclipses',
            'type' => 'url',
            'label' => 'URL base/configuración Eclipse solar real',
            'default' => 'https://aquellaslunas.com.ar/astro/embeds/eclipse-solar-real.php?date=2026-08-12&lat=42.4168&lon=-3.7038&elevation=0&autoplay=0&speed=1&duration=45&controls=1&corona_level=10&prominence_level=10&baily_level=10&exposure=1&sun_intensity=1&sun_color=%23bb6e16&limb_darkening=0.075&moon_color=%23010104&sky_darkening=0.51&sky_brightness=0.25&sky_color=%23264887&corona_brightness=1&corona_color=%23d8dde4&prominence_intensity=1&prominence_color=%23ff5839&baily_intensity=1&baily_color=%23fffdf1',
            'description' => 'URL completa generada para el widget solar. Fecha y coordenadas se reemplazan al usarla.',
        ],
        'eclipse.widget.lunar_url' => [
            'group' => 'eclipses',
            'type' => 'url',
            'label' => 'URL base/configuración Eclipse lunar real',
            'default' => 'https://aquellaslunas.com.ar/astro/embeds/eclipse-lunar-real.php?date=2026-03-03&lat=-34.6037&lon=-58.3816&elevation=0&autoplay=0&speed=1&duration=40&controls=1&sun_intensity=3.2&ambient_intensity=0.14&exposure=1.15&penumbra_darkness=.3&umbra_darkness=0.955&copper_intensity=0.26&copper_color=%23b24e0a&totality_brightness=0.55&totality_copper_intensity=0.95&totality_max_darkness=0.88&totality_gradient_contrast=1.25&totality_edge_color=%23d98b45&totality_deep_color=%235a120e&totality_saturation=1.1&totality_texture_contrast=0.58&totality_gradient_softness=0.16&totality_atmospheric_irregularity=0.1',
            'description' => 'URL completa generada para el widget lunar. Fecha y coordenadas se reemplazan al usarla.',
        ],

        'photography.simulated.day_zenith' => ['group' => 'photography_simulated', 'type' => 'color', 'label' => 'Día · cenit', 'default' => '#397fc0', 'description' => 'Color alto del cielo diurno simulado.'],
        'photography.simulated.day_horizon' => ['group' => 'photography_simulated', 'type' => 'color', 'label' => 'Día · horizonte', 'default' => '#b8d8e8', 'description' => 'Color del horizonte con el Sol alto.'],
        'photography.simulated.twilight_zenith' => ['group' => 'photography_simulated', 'type' => 'color', 'label' => 'Crepúsculo · cenit', 'default' => '#182b52', 'description' => 'Color alto del cielo crepuscular.'],
        'photography.simulated.twilight_horizon' => ['group' => 'photography_simulated', 'type' => 'color', 'label' => 'Crepúsculo · horizonte solar', 'default' => '#d67b58', 'description' => 'Color del horizonte crepuscular en la dirección del Sol.'],
        'photography.simulated.twilight_horizon_antisolar' => ['group' => 'photography_simulated', 'type' => 'color', 'label' => 'Crepúsculo · horizonte antisolar', 'default' => '#d67b58', 'description' => 'Color del horizonte crepuscular en la dirección opuesta al Sol.'],
        'photography.simulated.night_zenith' => ['group' => 'photography_simulated', 'type' => 'color', 'label' => 'Noche · cenit', 'default' => '#030711', 'description' => 'Color alto del cielo nocturno.'],
        'photography.simulated.night_horizon' => ['group' => 'photography_simulated', 'type' => 'color', 'label' => 'Noche · horizonte', 'default' => '#101b2c', 'description' => 'Color del horizonte nocturno.'],
        'photography.simulated.day_transition_altitude' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Inicio de cielo diurno', 'default' => 8.0, 'min' => -5.0, 'max' => 30.0, 'description' => 'Altura solar en grados a partir de la cual predomina el cielo diurno.'],
        'photography.simulated.twilight_center_altitude' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Máximo de crepúsculo', 'default' => -3.0, 'min' => -12.0, 'max' => 4.0, 'description' => 'Altura solar donde el perfil crepuscular alcanza su peso máximo. Debe quedar entre noche y día.'],
        'photography.simulated.night_transition_altitude' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Inicio de noche', 'default' => -18.0, 'min' => -30.0, 'max' => -6.0, 'description' => 'Altura solar en grados a partir de la cual predomina el cielo nocturno.'],
        'photography.simulated.sun_direction_influence' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Influencia de dirección solar', 'default' => 0.55, 'min' => 0.0, 'max' => 1.5, 'description' => 'Intensidad del aclarado continuo hacia la posición real del Sol.'],
        'photography.simulated.horizon_intensity' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Intensidad del horizonte', 'default' => 0.72, 'min' => 0.0, 'max' => 1.5, 'description' => 'Peso del color de horizonte según la altura dentro del cuadro.'],
        'photography.simulated.star_visibility' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Visibilidad de astros', 'default' => 1.0, 'min' => 0.0, 'max' => 2.0, 'description' => 'Ganancia máxima/nocturna de estrellas y planetas. Su visibilidad durante el crepúsculo se atenúa automáticamente según la altura solar.'],
        'photography.simulated.moon_halo_enabled' => ['group' => 'photography_simulated', 'type' => 'boolean', 'label' => 'Resplandor lunar activo', 'default' => true, 'description' => 'Activa la capa Three.js independiente situada detrás del disco lunar.'],
        'photography.simulated.moon_halo_max_intensity' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Intensidad máxima', 'default' => 0.28, 'min' => 0.0, 'max' => 1.5, 'description' => 'Opacidad máxima del resplandor para una Luna llena nocturna. No modifica el brillo del disco.'],
        'photography.simulated.moon_halo_radius' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Radio del resplandor', 'default' => 1.65, 'min' => 1.05, 'max' => 3.0, 'description' => 'Extensión difusa expresada en radios lunares desde el centro del disco.'],
        'photography.simulated.moon_halo_phase_start_percent' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Iluminación de inicio', 'default' => 70.0, 'min' => 0.0, 'max' => 99.0, 'description' => 'Porcentaje iluminado desde el cual el resplandor comienza a crecer suavemente.'],
        'photography.simulated.moon_low_to_high_start_altitude' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Inicio de transición a Luna alta', 'default' => 0.0, 'min' => -1.0, 'max' => 60.0, 'description' => 'Por debajo de esta altura lunar domina completamente el perfil de horizonte.'],
        'photography.simulated.moon_low_to_high_end_altitude' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Fin de transición a Luna alta', 'default' => 60.0, 'min' => 1.0, 'max' => 90.0, 'description' => 'Por encima de esta altura lunar domina completamente el perfil de cenit.'],
        'photography.simulated.atmosphere_horizon_altitude' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Altura de efecto máximo', 'default' => 0.5, 'min' => -1.0, 'max' => 5.0, 'description' => 'Altura lunar en grados donde la capa atmosférica alcanza su máximo.'],
        'photography.simulated.atmosphere_clear_altitude' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Altura de atmósfera limpia', 'default' => 30.0, 'min' => 10.0, 'max' => 60.0, 'description' => 'Altura lunar en grados donde el efecto ya es prácticamente nulo.'],
        'photography.simulated.atmosphere_extinction_strength' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Fuerza de extinción', 'default' => 0.38, 'min' => 0.0, 'max' => 1.0, 'description' => 'Pérdida gradual de brillo cuando la Luna se acerca al horizonte.'],
        'photography.simulated.atmosphere_contrast_loss' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Pérdida de contraste', 'default' => 0.48, 'min' => 0.0, 'max' => 1.0, 'description' => 'Reducción máxima de contraste junto al horizonte.'],
        'photography.simulated.atmosphere_detail_loss' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Pérdida de detalle', 'default' => 0.42, 'min' => 0.0, 'max' => 1.0, 'description' => 'Atenuación máxima del microdetalle, sin aplicar desenfoque.'],
        'photography.simulated.atmosphere_haze_integration' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Bruma e integración', 'default' => 0.3, 'min' => 0.0, 'max' => 1.0, 'description' => 'Mezcla adicional del disco con la atmósfera local.'],
        'photography.simulated.atmosphere_warm_color' => ['group' => 'photography_simulated', 'type' => 'color', 'label' => 'Tono cálido bajo', 'default' => '#e5a06d', 'description' => 'Destino cromático neutro-cálido para una Luna muy baja.'],
        'photography.simulated.atmosphere_warmth_day' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Calidez de día', 'default' => 0.12, 'min' => 0.0, 'max' => 1.0, 'description' => 'Calidez máxima diurna; conviene mantenerla baja.'],
        'photography.simulated.atmosphere_warmth_twilight' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Calidez de crepúsculo', 'default' => 0.42, 'min' => 0.0, 'max' => 1.0, 'description' => 'Calidez máxima durante el crepúsculo.'],
        'photography.simulated.atmosphere_warmth_night' => ['group' => 'photography_simulated', 'type' => 'decimal', 'label' => 'Calidez nocturna', 'default' => 0.68, 'min' => 0.0, 'max' => 1.0, 'description' => 'Calidez máxima de una Luna muy baja durante la noche.'],
    ];

    $moonProfiles = [
        'day_high' => 'Día · cenit', 'day_low' => 'Día · horizonte',
        'twilight_high' => 'Crepúsculo · cenit', 'twilight_low' => 'Crepúsculo · horizonte · solar',
        'twilight_low_antisolar' => 'Crepúsculo · horizonte · antisolar',
        'night_high' => 'Noche · cenit', 'night_low' => 'Noche · horizonte',
    ];
    $moonParameters = [
        'lit_color' => ['type' => 'color', 'label' => 'Tono iluminado', 'default' => '#eaf3ff', 'description' => 'Tono neutro y frío de la zona iluminada.'],
        'lit_brightness' => ['type' => 'decimal', 'label' => 'Brillo iluminado', 'default' => 2.8, 'min' => 0.0, 'max' => 20.0, 'description' => 'Intensidad base de la zona iluminada, antes de la atenuación atmosférica.'],
        'dark_brightness' => ['type' => 'decimal', 'label' => 'Brillo oscuro', 'default' => 1.0, 'min' => 0.0, 'max' => 1.5, 'description' => 'Intensidad de la sombra basada siempre en el cielo local.'],
        'dark_sky_mix' => ['type' => 'decimal', 'label' => 'Integración con el cielo', 'default' => 1.0, 'min' => 0.0, 'max' => 1.0, 'description' => 'Aproximación de intensidad al cielo local, sin introducir otro color.'],
        'contrast' => ['type' => 'decimal', 'label' => 'Contraste lunar', 'default' => 1.12, 'min' => 0.5, 'max' => 2.5, 'description' => 'Contraste de la textura lunar.'],
        'texture_visibility' => ['type' => 'decimal', 'label' => 'Visibilidad de textura', 'default' => 1.0, 'min' => 0.0, 'max' => 1.5, 'description' => 'Cantidad de detalle superficial visible; no modifica la geometría.'],
        'lit_sky_mix' => ['type' => 'decimal', 'label' => 'Integración iluminada', 'default' => 0.0, 'min' => 0.0, 'max' => 0.8, 'description' => 'Mezcla suave de la zona iluminada con el cielo local.'],
        'terminator_detail' => ['type' => 'decimal', 'label' => 'Detalle junto al terminador', 'default' => 0.0, 'min' => 0.0, 'max' => 1.0, 'description' => 'Conserva textura cerca del terminador sin exagerar el relieve.'],
        'limb_softness' => ['type' => 'decimal', 'label' => 'Suavidad del borde', 'default' => 0.0, 'min' => 0.0, 'max' => 0.15, 'description' => 'Atenúa sólo el borde del disco para evitar un recorte duro.'],
    ];
    foreach ($moonProfiles as $profileKey => $profileLabel) {
        foreach ($moonParameters as $parameterKey => $definition) {
            if (str_starts_with($profileKey, 'day_')) {
                $dayDefaults = ['lit_color' => '#f2f7fc', 'contrast' => 0.78, 'texture_visibility' => 0.42, 'lit_sky_mix' => 0.24, 'terminator_detail' => 0.22, 'limb_softness' => 0.055, 'dark_brightness' => 1.0, 'dark_sky_mix' => 1.0];
                if (array_key_exists($parameterKey, $dayDefaults)) $definition['default'] = $dayDefaults[$parameterKey];
            } elseif (str_starts_with($profileKey, 'twilight_')) {
                $twilightDefaults = ['lit_color' => '#eef4fb', 'contrast' => 0.96, 'texture_visibility' => 0.72, 'lit_sky_mix' => 0.1, 'terminator_detail' => 0.12, 'limb_softness' => 0.025];
                if (array_key_exists($parameterKey, $twilightDefaults)) $definition['default'] = $twilightDefaults[$parameterKey];
            }
            $definition['group'] = 'photography_simulated';
            $definition['label'] = $profileLabel . ' · ' . $definition['label'];
            $definition['moon_profile'] = $profileKey;
            $definition['moon_parameter'] = $parameterKey;
            $catalog['photography.simulated.moon_' . $profileKey . '_' . $parameterKey] = $definition;
        }
    }

    return $catalog;
}

function astronomySiteConfigGroups(): array
{
    return [
        'content' => 'Contenido',
        'diagnostics' => 'Diagnóstico y trazabilidad',
        'menu' => 'Menú principal',
        'home' => 'Portada',
        'eclipses' => 'Eclipses',
    ];
}

function astronomySiteMenuConfigBySectionId(): array
{
    return [
        'home' => 'menu.home.enabled',
        'today' => 'menu.today.enabled',
        'tonight' => 'menu.tonight.enabled',
        'sun_moon' => 'menu.sun_moon.enabled',
        'interactive_moon' => 'menu.interactive_moon.enabled',
        'events' => 'menu.events.enabled',
        'eclipses' => 'menu.eclipses.enabled',
        'planner' => 'menu.planner.enabled',
        'explorer' => 'menu.explorer.enabled',
        'gallery' => 'menu.gallery.enabled',
        'content' => 'menu.content.enabled',
        'location' => 'menu.location.enabled',
        'notifications' => 'menu.notifications.enabled',
        'moon_songs' => 'menu.moon_songs.enabled',
        'capabilities' => 'menu.capabilities.enabled',
        'about' => 'menu.about.enabled',
        'administration' => 'menu.administration.enabled',
    ];
}

function astronomySiteHomeConfigByBlockId(): array
{
    return [
        'today' => 'home.today.enabled',
        'tonight' => 'home.tonight.enabled',
        'phases' => 'home.phases.enabled',
        'upcoming' => 'home.upcoming.enabled',
        'explore_sky' => 'home.explore_sky.enabled',
        'trivia' => 'home.trivia.enabled',
        'sabias_que' => 'home.sabias_que.enabled',
        'install' => 'home.install.enabled',
    ];
}

function astronomySiteConfigDefaults(): array
{
    $defaults = [];
    foreach (astronomySiteConfigCatalog() as $key => $definition) {
        $defaults[$key] = $definition['default'] ?? false;
    }
    return $defaults;
}

function astronomySiteConfigResetCache(): void
{
    $cache = &astronomySiteConfigRuntimeCache();
    $cache = [
        'loaded' => false,
        'values' => [],
        'error_reported' => false,
    ];
}

function &astronomySiteConfigRuntimeCache(): array
{
    static $cache = [
        'loaded' => false,
        'values' => [],
        'error_reported' => false,
    ];
    return $cache;
}

function astronomySiteConfigValidateKey(string $key): void
{
    if (!array_key_exists($key, astronomySiteConfigCatalog())) {
        throw new InvalidArgumentException('La clave de configuración no está permitida: ' . $key);
    }
}

function astronomySiteConfigValueToBool(mixed $value, bool $default): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return $default;
    }
    if (in_array($normalized, ['1', 'true', 'on', 'yes', 'si', 'sí'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
        return false;
    }
    return $default;
}

function astronomySiteConfigBoolString(bool $value): string
{
    return $value ? '1' : '0';
}

/** @return array<string, mixed> */
function astronomySiteConfigNormalizeValue(string $key, mixed $value): mixed
{
    astronomySiteConfigValidateKey($key);
    $definition = astronomySiteConfigCatalog()[$key];
    $default = $definition['default'] ?? false;
    $type = (string) ($definition['type'] ?? 'boolean');
    if ($type === 'boolean') {
        return astronomySiteConfigValueToBool($value, $default === true);
    }
    if ($type === 'integer') {
        $normalized = filter_var($value, FILTER_VALIDATE_INT);
        if ($normalized === false) {
            return (int) $default;
        }
        return max((int) ($definition['min'] ?? PHP_INT_MIN), min((int) ($definition['max'] ?? PHP_INT_MAX), (int) $normalized));
    }
    if ($type === 'url') {
        $normalized = trim((string) $value);
        return filter_var($normalized, FILTER_VALIDATE_URL) !== false ? $normalized : (string) $default;
    }
    if ($type === 'decimal') {
        if (!is_numeric($value) || !is_finite((float) $value)) return (float) $default;
        return max((float) ($definition['min'] ?? -PHP_FLOAT_MAX), min((float) ($definition['max'] ?? PHP_FLOAT_MAX), (float) $value));
    }
    if ($type === 'color') {
        $normalized = strtolower(trim((string) $value));
        return preg_match('/^#[0-9a-f]{6}$/', $normalized) === 1 ? $normalized : (string) $default;
    }
    return $default;
}

function astronomySiteConfigSerializeValue(string $key, mixed $value): string
{
    $definition = astronomySiteConfigCatalog()[$key] ?? null;
    if (!is_array($definition)) {
        throw new InvalidArgumentException('La clave de configuración no está permitida: ' . $key);
    }
    $normalized = astronomySiteConfigNormalizeValue($key, $value);
    return ($definition['type'] ?? 'boolean') === 'boolean'
        ? astronomySiteConfigBoolString($normalized === true)
        : (string) $normalized;
}

function astronomySiteConfigLoadAll(?callable $connectionFactory = null): array
{
    $defaults = astronomySiteConfigDefaults();
    $cache = &astronomySiteConfigRuntimeCache();

    if ($connectionFactory === null && $cache['loaded'] === true) {
        return $cache['values'];
    }

    $values = $defaults;

    try {
        $connection = $connectionFactory !== null
            ? $connectionFactory()
            : getWebDatabaseConnection();
        if (!$connection instanceof PDO) {
            throw new RuntimeException('La configuración del sitio requiere una conexión PDO válida.');
        }

        $keys = array_keys($defaults);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $statement = $connection->prepare(
            'SELECT clave, valor FROM admin_configuracion_sitio WHERE clave IN (' . $placeholders . ')'
        );
        $statement->execute($keys);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) ($row['clave'] ?? '');
            if (!array_key_exists($key, $defaults)) {
                continue;
            }
            $values[$key] = astronomySiteConfigNormalizeValue($key, $row['valor'] ?? null);
        }
    } catch (Throwable $exception) {
        if ($cache['error_reported'] !== true) {
            error_log('Aquellas Lunas site config load error: ' . $exception->getMessage());
            $cache['error_reported'] = true;
        }
    }

    if ($connectionFactory === null) {
        $cache['loaded'] = true;
        $cache['values'] = $values;
    }

    return $values;
}

function astronomySiteConfigValue(string $key, mixed $fallback = null, ?callable $connectionFactory = null): mixed
{
    astronomySiteConfigValidateKey($key);
    $values = astronomySiteConfigLoadAll($connectionFactory);
    return array_key_exists($key, $values) ? $values[$key] : ($fallback ?? astronomySiteConfigCatalog()[$key]['default']);
}

function astronomySiteConfigBool(string $key, ?bool $fallback = null, ?callable $connectionFactory = null): bool
{
    astronomySiteConfigValidateKey($key);
    $defaults = astronomySiteConfigDefaults();
    $defaultValue = $fallback ?? ($defaults[$key] ?? false);
    $values = astronomySiteConfigLoadAll($connectionFactory);
    return astronomySiteConfigValueToBool($values[$key] ?? null, $defaultValue);
}

function astronomySiteConfigInitialize(PDO $connection): array
{
    $catalog = astronomySiteConfigCatalog();
    $statement = $connection->prepare(
        'INSERT INTO admin_configuracion_sitio (clave, valor, descripcion) '
        . 'VALUES (:clave, :valor, :descripcion) '
        . 'ON DUPLICATE KEY UPDATE clave = clave'
    );

    $inserted = 0;
    foreach ($catalog as $key => $definition) {
        $statement->execute([
            ':clave' => $key,
            ':valor' => astronomySiteConfigSerializeValue($key, $definition['default'] ?? false),
            ':descripcion' => (string) ($definition['description'] ?? ''),
        ]);
        if ($statement->rowCount() === 1) {
            $inserted++;
        }
    }

    astronomySiteConfigResetCache();
    return [
        'inserted' => $inserted,
        'total' => count($catalog),
    ];
}

function astronomySiteConfigUpdateValues(PDO $connection, array $updates): void
{
    foreach ($updates as $key => $value) {
        astronomySiteConfigValidateKey((string) $key);
        astronomySiteConfigNormalizeValue((string) $key, $value);
    }
    $connection->beginTransaction();
    try {
        astronomySiteConfigInitialize($connection);
        $statement = $connection->prepare('UPDATE admin_configuracion_sitio SET valor = :valor WHERE clave = :clave');
        foreach ($updates as $key => $value) {
            $statement->execute([':clave' => (string) $key, ':valor' => astronomySiteConfigSerializeValue((string) $key, $value)]);
        }
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
    astronomySiteConfigResetCache();
}

function astronomySiteConfigUpdate(PDO $connection, array $updates): void
{
    foreach ($updates as $key => $value) {
        astronomySiteConfigValidateKey((string) $key);
        if (!is_bool($value)) {
            throw new InvalidArgumentException('La actualización de configuración requiere booleanos por clave.');
        }
    }

    $connection->beginTransaction();
    try {
        astronomySiteConfigInitialize($connection);

        $updateStatement = $connection->prepare(
            'UPDATE admin_configuracion_sitio SET valor = :valor WHERE clave = :clave'
        );

        foreach ($updates as $key => $value) {
            $updateStatement->execute([
                ':clave' => (string) $key,
                ':valor' => astronomySiteConfigBoolString($value),
            ]);
        }

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }

    astronomySiteConfigResetCache();
}

function astronomySiteConfigGroupEntries(array $values): array
{
    $grouped = [];
    foreach (astronomySiteConfigGroups() as $groupId => $groupLabel) {
        $grouped[$groupId] = [
            'id' => $groupId,
            'label' => $groupLabel,
            'entries' => [],
        ];
    }

    foreach (astronomySiteConfigCatalog() as $key => $definition) {
        $groupId = (string) ($definition['group'] ?? 'content');
        if (!isset($grouped[$groupId])) {
            continue;
        }
        $grouped[$groupId]['entries'][] = [
            'key' => $key,
            'label' => (string) ($definition['label'] ?? $key),
            'description' => (string) ($definition['description'] ?? ''),
            'type' => (string) ($definition['type'] ?? 'boolean'),
            'min' => $definition['min'] ?? null,
            'max' => $definition['max'] ?? null,
            'value' => astronomySiteConfigNormalizeValue($key, $values[$key] ?? null),
        ];
    }

    return array_values($grouped);
}

function astronomySiteMenuEntryEnabled(string $sectionId): bool
{
    $map = astronomySiteMenuConfigBySectionId();
    if (!isset($map[$sectionId])) {
        return true;
    }
    return astronomySiteConfigBool($map[$sectionId]);
}

function astronomySiteHomeBlockEnabled(string $blockId): bool
{
    $map = astronomySiteHomeConfigByBlockId();
    if (!isset($map[$blockId])) {
        return true;
    }
    return astronomySiteConfigBool($map[$blockId]);
}
