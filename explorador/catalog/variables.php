<?php

declare(strict_types=1);

$variable = static function (
    string $key, string $name, string $shortName, string $category, string $description,
    string $unit, string $scaleGroup, string $scope, string $provider, array $dependencies,
    int $precision, int $order, string $type = 'number'
): array {
    return compact('key', 'name', 'shortName', 'category', 'description', 'type', 'unit',
        'scaleGroup', 'scope', 'provider', 'dependencies', 'precision', 'order');
};
$extrema = static function (array $definition, string $label, int $minimumYears, int $recommendedYears,
    string $maximumLabel, string $minimumLabel): array {
    return $definition + ['extrema_supported' => true, 'extrema_label' => $label,
        'extrema_minimum_years' => $minimumYears, 'extrema_recommended_years' => $recommendedYears,
        'extrema_maximum_label' => $maximumLabel, 'extrema_minimum_label' => $minimumLabel];
};

return [
    'moon_illumination' => $variable('moon_illumination', 'Iluminación lunar', 'Iluminación', 'Luna', 'Fracción iluminada del disco lunar a las 00:00 locales.', '%', 'percent', 'global', 'moon_instant', [], 4, 10),
    'moon_phase_angle' => $variable('moon_phase_angle', 'Ángulo de fase lunar', 'Ángulo de fase', 'Ciclos y geometría', 'Ángulo Sol-Luna derivado de la fracción iluminada; 0° en Luna llena y 180° en Luna nueva.', '°', 'angle', 'global', 'moon_instant', [], 4, 20),
    'moon_distance_geocentric' => $extrema($variable('moon_distance_geocentric', 'Distancia lunar geocéntrica', 'Distancia geocéntrica', 'Luna', 'Distancia desde el centro de la Tierra a las 00:00 locales.', 'km', 'distance', 'global', 'moon_instant', [], 2, 30), 'Distancia lunar geocéntrica', 2, 5, 'Apogeos diarios', 'Perigeos diarios'),
    'moon_distance_topocentric' => $extrema($variable('moon_distance_topocentric', 'Distancia lunar topocéntrica', 'Distancia topocéntrica', 'Observación local', 'Distancia desde el observador a las 00:00 locales.', 'km', 'distance', 'local', 'moon_instant', [], 2, 40), 'Distancia lunar topocéntrica', 2, 5, 'Máximos diarios de distancia topocéntrica', 'Mínimos diarios de distancia topocéntrica'),
    'moon_ecliptic_latitude' => $variable('moon_ecliptic_latitude', 'Latitud eclíptica lunar', 'Latitud eclíptica', 'Ciclos y geometría', 'Latitud eclíptica geocéntrica a las 00:00 locales.', '°', 'angle_signed', 'global', 'moon_instant', [], 4, 50),
    'moon_right_ascension' => $variable('moon_right_ascension', 'Ascensión recta lunar', 'Ascensión recta', 'Ciclos y geometría', 'Ascensión recta geocéntrica a las 00:00 locales.', 'h', 'hours_angle', 'global', 'moon_instant', [], 5, 60),
    'moon_declination' => $variable('moon_declination', 'Declinación lunar', 'Declinación', 'Ciclos y geometría', 'Declinación geocéntrica a las 00:00 locales.', '°', 'angle_signed', 'global', 'moon_instant', [], 4, 70),
    'moon_altitude' => $variable('moon_altitude', 'Altura lunar', 'Altura lunar', 'Observación local', 'Altura topocéntrica sin refracción a las 00:00 locales.', '°', 'altitude', 'local', 'moon_instant', [], 4, 80),
    'moon_azimuth' => $variable('moon_azimuth', 'Azimut lunar', 'Azimut lunar', 'Observación local', 'Azimut topocéntrico a las 00:00 locales.', '°', 'azimuth', 'local', 'moon_instant', [], 4, 90),
    'sun_altitude' => $variable('sun_altitude', 'Altura solar', 'Altura solar', 'Sol', 'Altura topocéntrica sin refracción a las 00:00 locales.', '°', 'altitude', 'local', 'sun_instant', [], 4, 110),
    'sun_azimuth' => $variable('sun_azimuth', 'Azimut solar', 'Azimut solar', 'Sol', 'Azimut topocéntrico a las 00:00 locales.', '°', 'azimuth', 'local', 'sun_instant', [], 4, 120),
    'moonrise_time' => $variable('moonrise_time', 'Hora de salida lunar', 'Salida lunar', 'Luna', 'Hora local decimal de la primera salida lunar del día.', 'hora local', 'local_time', 'local', 'moon_events', [], 4, 210),
    'moonset_time' => $variable('moonset_time', 'Hora de puesta lunar', 'Puesta lunar', 'Luna', 'Hora local decimal de la primera puesta lunar del día.', 'hora local', 'local_time', 'local', 'moon_events', [], 4, 220),
    'moonrise_azimuth' => $variable('moonrise_azimuth', 'Azimut de salida lunar', 'Azimut salida lunar', 'Observación local', 'Azimut lunar calculado en el instante de salida.', '°', 'azimuth', 'local', 'moon_events', [], 3, 230),
    'moonset_azimuth' => $variable('moonset_azimuth', 'Azimut de puesta lunar', 'Azimut puesta lunar', 'Observación local', 'Azimut lunar calculado en el instante de puesta.', '°', 'azimuth', 'local', 'moon_events', [], 3, 240),
    'moon_above_horizon_hours' => $extrema($variable('moon_above_horizon_hours', 'Tiempo lunar sobre el horizonte', 'Luna visible', 'Observación local', 'Suma de los intervalos de visibilidad del contrato lunar diario.', 'h', 'duration', 'local', 'moon_events', [], 4, 250), 'Tiempo lunar sobre el horizonte', 2, 5, 'Máximos locales de tiempo sobre el horizonte', 'Mínimos locales de tiempo sobre el horizonte'),
    'sunrise_time' => $variable('sunrise_time', 'Hora de salida solar', 'Salida solar', 'Sol', 'Hora local decimal de la salida solar.', 'hora local', 'local_time', 'local', 'sun_events', [], 4, 310),
    'sunset_time' => $variable('sunset_time', 'Hora de puesta solar', 'Puesta solar', 'Sol', 'Hora local decimal de la puesta solar.', 'hora local', 'local_time', 'local', 'sun_events', [], 4, 320),
    'sunrise_azimuth' => $variable('sunrise_azimuth', 'Azimut de salida solar', 'Azimut salida solar', 'Observación local', 'Azimut solar calculado en el instante de salida.', '°', 'azimuth', 'local', 'sun_events', [], 3, 330),
    'sunset_azimuth' => $variable('sunset_azimuth', 'Azimut de puesta solar', 'Azimut puesta solar', 'Observación local', 'Azimut solar calculado en el instante de puesta.', '°', 'azimuth', 'local', 'sun_events', [], 3, 340),
    'daylight_hours' => $extrema($variable('daylight_hours', 'Duración del día', 'Duración del día', 'Sol', 'Tiempo del día civil con el Sol sobre el horizonte convencional.', 'h', 'duration', 'local', 'sun_events', [], 4, 350), 'Duración del día', 5, 10, 'Máximos locales de duración del día', 'Mínimos locales de duración del día'),
    'night_hours' => $extrema($variable('night_hours', 'Duración de la noche', 'Duración de la noche', 'Observación local', 'Duración del día civil menos el tiempo con el Sol sobre el horizonte.', 'h', 'duration', 'local', 'sun_events', [], 4, 360), 'Duración de la noche', 5, 10, 'Máximos locales de duración de la noche', 'Mínimos locales de duración de la noche'),
    'moonrise_amplitude' => $extrema($variable('moonrise_amplitude', 'Amplitud de salida lunar', 'Amplitud salida lunar', 'Ciclos y geometría', 'Desplazamiento angular respecto del este: 90° menos azimut; positivo hacia el norte.', '°', 'amplitude', 'local', 'derived', ['moonrise_azimuth'], 3, 410), 'Amplitud de salida lunar', 2, 5, 'Máximos locales de amplitud de salida lunar', 'Mínimos locales de amplitud de salida lunar'),
    'moonset_amplitude' => $extrema($variable('moonset_amplitude', 'Amplitud de puesta lunar', 'Amplitud puesta lunar', 'Ciclos y geometría', 'Desplazamiento angular respecto del oeste: azimut menos 270°; positivo hacia el norte.', '°', 'amplitude', 'local', 'derived', ['moonset_azimuth'], 3, 420), 'Amplitud de puesta lunar', 2, 5, 'Máximos locales de amplitud de puesta lunar', 'Mínimos locales de amplitud de puesta lunar'),
    'sunrise_amplitude' => $extrema($variable('sunrise_amplitude', 'Amplitud de salida solar', 'Amplitud salida solar', 'Ciclos y geometría', 'Desplazamiento angular respecto del este: 90° menos azimut; positivo hacia el norte.', '°', 'amplitude', 'local', 'derived', ['sunrise_azimuth'], 3, 430), 'Amplitud de salida solar', 5, 10, 'Máximos locales de amplitud de salida solar', 'Mínimos locales de amplitud de salida solar'),
    'sunset_amplitude' => $extrema($variable('sunset_amplitude', 'Amplitud de puesta solar', 'Amplitud puesta solar', 'Ciclos y geometría', 'Desplazamiento angular respecto del oeste: azimut menos 270°; positivo hacia el norte.', '°', 'amplitude', 'local', 'derived', ['sunset_azimuth'], 3, 440), 'Amplitud de puesta solar', 5, 10, 'Máximos locales de amplitud de puesta solar', 'Mínimos locales de amplitud de puesta solar'),
    'moonrise_daily_difference' => $extrema($variable('moonrise_daily_difference', 'Diferencia diaria de salida lunar', 'Δ salida lunar', 'Ciclos y geometría', 'Minutos de adelanto o retraso frente al día civil anterior; positivo significa más tarde.', 'min', 'time_difference', 'local', 'derived', ['moonrise_time'], 2, 450), 'Diferencia diaria de salida lunar', 2, 5, 'Máximos locales de diferencia de salida lunar', 'Mínimos locales de diferencia de salida lunar'),
    'moonset_daily_difference' => $extrema($variable('moonset_daily_difference', 'Diferencia diaria de puesta lunar', 'Δ puesta lunar', 'Ciclos y geometría', 'Minutos de adelanto o retraso frente al día civil anterior; positivo significa más tarde.', 'min', 'time_difference', 'local', 'derived', ['moonset_time'], 2, 460), 'Diferencia diaria de puesta lunar', 2, 5, 'Máximos locales de diferencia de puesta lunar', 'Mínimos locales de diferencia de puesta lunar'),
    'sunrise_daily_difference' => $extrema($variable('sunrise_daily_difference', 'Diferencia diaria de salida solar', 'Δ salida solar', 'Ciclos y geometría', 'Minutos de adelanto o retraso frente al día civil anterior; positivo significa más tarde.', 'min', 'time_difference', 'local', 'derived', ['sunrise_time'], 2, 470), 'Diferencia diaria de salida solar', 5, 10, 'Máximos locales de diferencia de salida solar', 'Mínimos locales de diferencia de salida solar'),
    'sunset_daily_difference' => $extrema($variable('sunset_daily_difference', 'Diferencia diaria de puesta solar', 'Δ puesta solar', 'Ciclos y geometría', 'Minutos de adelanto o retraso frente al día civil anterior; positivo significa más tarde.', 'min', 'time_difference', 'local', 'derived', ['sunset_time'], 2, 480), 'Diferencia diaria de puesta solar', 5, 10, 'Máximos locales de diferencia de puesta solar', 'Mínimos locales de diferencia de puesta solar'),
];
