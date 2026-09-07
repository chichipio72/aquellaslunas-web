<?php

declare(strict_types=1);

require_once __DIR__ . '/lunar-embeds.php';
require_once __DIR__ . '/timezone-resolver.php';

use AstronomyEngine\EclipseObserver;
use AstronomyEngine\LunarEclipse;
use AstronomyEngine\LunarEclipseCalculator;
use AstronomyEngine\LunarEclipseLocalCalculator;
use AstronomyEngine\MoonDiskAppearanceCalculator;
use AstronomyEngine\SolarEclipse;
use AstronomyEngine\SolarEclipseCalculator;
use AstronomyEngine\SolarEclipseLocalCalculator;
use AstronomyEngine\SolarEclipseShadowGeometry;

function realEclipseInstance(array $query): array
{
    $latitude = lunarEmbedFloat($query['lat'] ?? null, -34.6037, -90.0, 90.0);
    $longitude = lunarEmbedFloat($query['lon'] ?? null, -58.3816, -180.0, 180.0);
    $dateText = is_string($query['date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $query['date']) === 1
        ? $query['date'] : '2026-03-03';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateText, new DateTimeZone('UTC'));
    if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $dateText) throw new InvalidArgumentException('Fecha de eclipse inválida.');
    return ['date' => $date, 'latitude' => $latitude, 'longitude' => $longitude, 'elevation_meters' => lunarEmbedFloat($query['elevation'] ?? null, 0.0, -500.0, 10000.0), 'timezone' => astronomyResolveTimezone($latitude, $longitude)];
}

function realLunarEclipseOptions(array $query): array
{
    return lunarEclipseEmbedOptions($query) + [
        'duration_seconds' => lunarEmbedFloat($query['duration'] ?? null, 40.0, 10.0, 180.0),
        'totality_brightness' => lunarEmbedFloat($query['totality_brightness'] ?? null, 0.55, 0.0, 2.0),
        'totality_copper_intensity' => lunarEmbedFloat($query['totality_copper_intensity'] ?? null, 0.7, 0.0, 2.0),
        'totality_max_darkness' => lunarEmbedFloat($query['totality_max_darkness'] ?? null, 0.88, 0.0, 1.0),
        'totality_gradient_contrast' => lunarEmbedFloat($query['totality_gradient_contrast'] ?? null, 1.25, 0.25, 3.0),
        'totality_edge_color' => lunarEmbedColor($query['totality_edge_color'] ?? null, '#d98b45'),
        'totality_deep_color' => lunarEmbedColor($query['totality_deep_color'] ?? null, '#5a120e'),
        'totality_saturation' => lunarEmbedFloat($query['totality_saturation'] ?? null, 1.1, 0.0, 2.0),
        'totality_texture_contrast' => lunarEmbedFloat($query['totality_texture_contrast'] ?? null, 0.58, 0.0, 1.5),
        'totality_gradient_softness' => lunarEmbedFloat($query['totality_gradient_softness'] ?? null, 0.16, 0.02, 0.5),
        'totality_atmospheric_irregularity' => lunarEmbedFloat($query['totality_atmospheric_irregularity'] ?? null, 0.1, 0.0, 0.5),
        'sky_brightness' => lunarEmbedFloat($query['sky_brightness'] ?? null, 0.0, 0.0, 2.0),
        'sky_color' => lunarEmbedColor($query['sky_color'] ?? null, '#02040a'),
    ];
}

function realSolarEclipseOptions(array $query): array
{
    $legacyLevel = static function (string $legacyKey, string $levelKey, float $default) use ($query): float {
        if (array_key_exists($levelKey, $query)) return lunarEmbedFloat($query[$levelKey], $default, 0.0, 10.0);
        if (!array_key_exists($legacyKey, $query)) return $default;
        return lunarEmbedBoolean($query[$legacyKey], false) ? 7.0 : 0.0;
    };
    $coronaLevel = $legacyLevel('corona', 'corona_level', 7.0);
    if (!array_key_exists('corona_level', $query) && array_key_exists('corona_intensity', $query) && $coronaLevel > 0.0) {
        $coronaLevel = lunarEmbedFloat($query['corona_intensity'], 1.0, 0.0, 2.0) * 5.0;
    }
    return [
        'autoplay' => lunarEmbedBoolean($query['autoplay'] ?? null, false), 'controls' => lunarEmbedBoolean($query['controls'] ?? null, true),
        'speed' => lunarEmbedFloat($query['speed'] ?? null, 1.0, 0.25, 8.0), 'duration_seconds' => lunarEmbedFloat($query['duration'] ?? null, 45.0, 10.0, 180.0),
        'corona_level' => $coronaLevel, 'prominence_level' => $legacyLevel('prominences', 'prominence_level', 4.0),
        'baily_level' => $legacyLevel('baily', 'baily_level', 6.0), 'sky_darkening' => lunarEmbedFloat($query['sky_darkening'] ?? null, 0.94, 0.0, 1.0),
        'totality_effects_before_seconds' => lunarEmbedFloat($query['totality_effects_before_seconds'] ?? null, 5.0, 0.0, 120.0),
        'totality_effects_after_seconds' => lunarEmbedFloat($query['totality_effects_after_seconds'] ?? null, 5.0, 0.0, 120.0),
        'exposure' => lunarEmbedFloat($query['exposure'] ?? null, 1.0, 0.5, 2.0),
        'sun_intensity' => lunarEmbedFloat($query['sun_intensity'] ?? null, 1.0, 0.0, 2.0),
        'sun_color' => lunarEmbedColor($query['sun_color'] ?? null, '#fff7df'),
        'limb_darkening' => lunarEmbedFloat($query['limb_darkening'] ?? null, 0.075, 0.0, 0.3),
        'moon_color' => lunarEmbedColor($query['moon_color'] ?? null, '#010104'),
        'sky_brightness' => lunarEmbedFloat($query['sky_brightness'] ?? null, 1.0, 0.0, 2.0),
        'sky_color' => lunarEmbedColor($query['sky_color'] ?? null, '#122343'),
        'corona_intensity' => lunarEmbedFloat($query['corona_brightness'] ?? null, 1.0, 0.0, 2.0),
        'corona_color' => lunarEmbedColor($query['corona_color'] ?? null, '#dcecff'),
        'prominence_intensity' => lunarEmbedFloat($query['prominence_intensity'] ?? null, 1.0, 0.0, 2.0),
        'prominence_color' => lunarEmbedColor($query['prominence_color'] ?? null, '#ff5839'),
        'baily_intensity' => lunarEmbedFloat($query['baily_intensity'] ?? null, 1.0, 0.0, 2.0),
        'baily_color' => lunarEmbedColor($query['baily_color'] ?? null, '#fffdf1'),
    ];
}

function realSolarSpaceEclipseOptions(array $query): array
{
    return [
        'autoplay' => lunarEmbedBoolean($query['autoplay'] ?? null, false),
        'controls' => lunarEmbedBoolean($query['controls'] ?? null, true),
        'speed' => lunarEmbedFloat($query['speed'] ?? null, 1.0, 0.25, 8.0),
        'duration_seconds' => lunarEmbedFloat($query['duration'] ?? null, 50.0, 10.0, 180.0),
        'show_moon' => lunarEmbedBoolean($query['show_moon'] ?? null, true),
        'show_penumbra' => lunarEmbedBoolean($query['show_penumbra'] ?? null, true),
        'penumbra_opacity' => lunarEmbedFloat($query['penumbra_opacity'] ?? null, 0.22, 0.0, 1.0),
        'show_core' => lunarEmbedBoolean($query['show_core'] ?? null, true),
        'core_opacity' => lunarEmbedFloat($query['core_opacity'] ?? null, 0.62, 0.0, 1.0),
        'show_terminator' => lunarEmbedBoolean($query['show_terminator'] ?? null, true),
        'ambient_intensity' => lunarEmbedFloat($query['ambient_intensity'] ?? null, 0.08, 0.0, 1.5),
        'exposure' => lunarEmbedFloat($query['exposure'] ?? null, 1.05, 0.5, 2.0),
        'distance_scale' => lunarEmbedFloat($query['distance_scale'] ?? null, 3.4, 2.2, 7.0),
        'rotation' => in_array(($query['rotation'] ?? null), ['free', 'locked'], true) ? (string) $query['rotation'] : 'free',
        'zoom' => lunarEmbedFloat($query['zoom'] ?? null, 1.0, 0.65, 1.8),
        'follow_shadow' => lunarEmbedBoolean($query['follow_shadow'] ?? null, false),
    ];
}

function eclipseObserver(array $instance): EclipseObserver
{
    return new EclipseObserver($instance['latitude'], $instance['longitude'], $instance['timezone'], $instance['elevation_meters']);
}

function realEclipseFind(string $kind, DateTimeImmutable $date): LunarEclipse|SolarEclipse
{
    $start=$date->modify('-2 days');$end=$date->modify('+3 days');
    $events=$kind==='lunar'?(new LunarEclipseCalculator())->events($start,$end):(new SolarEclipseCalculator())->events($start,$end);
    if ($events===[]) throw new RuntimeException('No existe un eclipse '.$kind.' en la fecha indicada.');
    usort($events,static fn($a,$b):int=>abs($a->maximum->getTimestamp()-$date->getTimestamp())<=>abs($b->maximum->getTimestamp()-$date->getTimestamp()));
    return $events[0];
}

function eclipseDates(array $contacts): array
{
    $result=[];foreach($contacts as $code=>$value){$date=$value instanceof DateTimeInterface?$value:($value['utc']??null);$result[$code]=$date instanceof DateTimeInterface?$date->format(DateTimeInterface::ATOM):null;}return$result;
}

function realLunarEclipsePayload(array $instance,array $options): array
{
    $event=realEclipseFind('lunar',$instance['date']);if(!$event instanceof LunarEclipse)throw new RuntimeException('Eclipse lunar inválido.');
    $observer=lunarEmbedObserver($instance);$local=(new LunarEclipseLocalCalculator())->calculate($event,eclipseObserver($instance));$calculator=new LunarEclipseCalculator();$appearance=new MoonDiskAppearanceCalculator();
    $start=$event->contacts->P1->modify('-30 minutes');$end=$event->contacts->P4->modify('+30 minutes');$duration=$end->getTimestamp()-$start->getTimestamp();$samples=[];
    for($i=0;$i<=180;$i++){$instant=$start->modify('+'.(string)(int)round($duration*$i/180).' seconds');$g=$calculator->geometryAt($instant);$a=$appearance->calculate($instant,$observer);$moonRadius=$g['moon_radius_radians'];$samples[]=['datetime'=>$instant->format(DateTimeInterface::ATOM),'shadow_east_moon_radii'=>$g['shadow_offset_east_radians']/$moonRadius,'shadow_north_moon_radii'=>$g['shadow_offset_north_radians']/$moonRadius,'umbra_radius_moon_radii'=>$g['umbra_radius_radians']/$moonRadius,'penumbra_radius_moon_radii'=>$g['penumbra_radius_radians']/$moonRadius,'moon'=>['longitude'=>$a['surface_geometry']['subobserver']['longitude_degrees'],'latitude'=>$a['surface_geometry']['subobserver']['latitude_degrees'],'disk_angle'=>$a['orientation']['lunar_north_screen_angle_degrees'],'celestial_north_screen_angle'=>$a['orientation']['celestial_north_screen_angle_degrees'],'altitude'=>$a['observer']['moon_altitude_degrees'],'azimuth'=>$a['observer']['moon_azimuth_degrees']]];}
    $payload=moonThreeRenderPayload($event->maximum,$observer,moonThreeRenderConfigurationLoad());unset($payload['wallpaper']);
    $payload['eclipse']=['kind'=>'lunar','classification'=>$event->classification,'maximum'=>$event->maximum->format(DateTimeInterface::ATOM),'timeline_start'=>$start->format(DateTimeInterface::ATOM),'timeline_end'=>$end->format(DateTimeInterface::ATOM),'contacts'=>eclipseDates($event->contacts->all()),'local_contacts'=>eclipseDates($local->contacts),'visible_interval'=>$local->visibleInterval?['start'=>$local->visibleInterval['start']->format(DateTimeInterface::ATOM),'end'=>$local->visibleInterval['end']->format(DateTimeInterface::ATOM)]:null,'visible'=>$local->visible,'timezone'=>$instance['timezone'],'observer'=>$instance,'magnitudes'=>$event->magnitudes,'samples'=>$samples,'animation'=>$options,'calculation_models'=>[$event->calculationModel,$local->calculationModel]];
    return $payload;
}

function realSolarEclipsePayload(array $instance,array $options): array
{
    $event=realEclipseFind('solar',$instance['date']);if(!$event instanceof SolarEclipse)throw new RuntimeException('Eclipse solar inválido.');
    $observer=eclipseObserver($instance);$calculator=new SolarEclipseLocalCalculator();$local=$calculator->calculate($event,$observer);$contactValues=array_values(array_filter(array_map(static fn($c)=>$c['utc']??null,$local->contacts)));
    $start=$contactValues!==[]?min($contactValues)->modify('-30 minutes'):$event->maximum->modify('-4 hours');$end=$contactValues!==[]?max($contactValues)->modify('+30 minutes'):$event->maximum->modify('+4 hours');$duration=$end->getTimestamp()-$start->getTimestamp();$samples=[];
    for($i=0;$i<=240;$i++){$instant=$start->modify('+'.(string)(int)round($duration*$i/240).' seconds');$g=$calculator->geometrySample($instant,$observer);$sr=$g['sun_radius'];$samples[]=['datetime'=>$instant->format(DateTimeInterface::ATOM),'moon_x_solar_radii'=>$g['offset_east_radians']/$sr,'moon_y_solar_radii'=>$g['offset_north_radians']/$sr,'moon_radius_solar_radii'=>$g['moon_radius']/$sr,'sun_altitude'=>$g['sun_altitude'],'sun_azimuth'=>$g['sun_azimuth'],'celestial_north_screen_angle'=>$g['celestial_north_screen_angle_degrees']];}
    $payload=['eclipse'=>['kind'=>'solar','global_classification'=>$event->classification,'local_classification'=>$local->localClassification,'maximum'=>($local->maximumLocalGeometry['utc']??$event->maximum)->format(DateTimeInterface::ATOM),'timeline_start'=>$start->format(DateTimeInterface::ATOM),'timeline_end'=>$end->format(DateTimeInterface::ATOM),'contacts'=>eclipseDates($local->contacts),'visible_interval'=>$local->visibleInterval?['start'=>$local->visibleInterval['start']->format(DateTimeInterface::ATOM),'end'=>$local->visibleInterval['end']->format(DateTimeInterface::ATOM)]:null,'visible'=>$local->visible,'timezone'=>$instance['timezone'],'observer'=>$instance,'magnitude'=>$local->localMagnitude,'obscuration'=>$local->obscuration,'central_duration_seconds'=>$local->centralDurationSeconds,'samples'=>$samples,'animation'=>$options,'calculation_models'=>[$event->calculationModel,$local->calculationModel]],'visual_note'=>'Corona, prominencias, perlas de Baily y anillo de diamantes son representaciones visuales; posiciones, tamaños y contactos provienen de la geometría topocéntrica.'];
    return $payload;
}

/** @return array<string,DateTimeImmutable> */
function realSolarSpaceContacts(SolarEclipse $event, SolarEclipseShadowGeometry $geometry): array
{
    $center = (float) $event->maximum->format('U.u');
    $date = static fn(float $timestamp): DateTimeImmutable => DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $timestamp), new DateTimeZone('UTC'))->setTimezone(new DateTimeZone('UTC'));
    $roots = static function (string $kind) use ($geometry, $center, $date): array {
        $result = [];
        $step = 300.0;
        $previousTime = $center - 21600.0;
        $previousValue = $geometry->contactMetric($date($previousTime), $kind);
        for ($time = $previousTime + $step; $time <= $center + 21600.0; $time += $step) {
            $value = $geometry->contactMetric($date($time), $kind);
            if (($previousValue <= 0.0 && $value > 0.0) || ($previousValue > 0.0 && $value <= 0.0)) {
                $low = $previousTime; $high = $time; $lowValue = $previousValue;
                while ($high - $low > 0.25) {
                    $middle = ($low + $high) / 2.0;
                    $middleValue = $geometry->contactMetric($date($middle), $kind);
                    if (($lowValue <= 0.0) === ($middleValue <= 0.0)) { $low = $middle; $lowValue = $middleValue; } else { $high = $middle; }
                }
                $result[] = ($low + $high) / 2.0;
            }
            $previousTime = $time; $previousValue = $value;
        }
        return $result;
    };
    $penumbra = $roots('penumbra');
    $core = $roots('core');
    $contacts = ['MAX' => $event->maximum];
    if (count($penumbra) >= 2) { $contacts['C1'] = $date($penumbra[0]); $contacts['C4'] = $date($penumbra[array_key_last($penumbra)]); }
    if (count($core) >= 2 && $event->classification !== 'partial') { $contacts['C2'] = $date($core[0]); $contacts['C3'] = $date($core[array_key_last($core)]); }
    return $contacts;
}

function realSolarSpaceEclipsePayload(array $instance, array $options): array
{
    $event = realEclipseFind('solar', $instance['date']);
    if (!$event instanceof SolarEclipse) throw new RuntimeException('Eclipse solar inválido.');
    $geometry = new SolarEclipseShadowGeometry();
    $contacts = realSolarSpaceContacts($event, $geometry);
    $start = ($contacts['C1'] ?? $event->maximum->modify('-4 hours'))->modify('-30 minutes');
    $end = ($contacts['C4'] ?? $event->maximum->modify('+4 hours'))->modify('+30 minutes');
    $seconds = $end->getTimestamp() - $start->getTimestamp();
    $samples = [];
    for ($index = 0; $index <= 180; $index++) {
        $instant = $start->modify('+' . (string) (int) round($seconds * $index / 180) . ' seconds');
        $samples[] = $geometry->calculate($instant);
    }
    return [
        'textures' => [
            'earth' => 'assets/images/moon-three/nasa-blue-marble-2048.png',
            'moon' => 'assets/images/moon-three/lroc_color_2k.jpg',
        ],
        'eclipse' => [
            'kind' => 'solar',
            'classification' => $event->classification,
            'maximum' => $event->maximum->format(DateTimeInterface::ATOM),
            'timeline_start' => $start->format(DateTimeInterface::ATOM),
            'timeline_end' => $end->format(DateTimeInterface::ATOM),
            'contacts' => eclipseDates($contacts),
            'timezone' => 'UTC',
            'visible' => true,
            'visibility_label' => 'Vista geocéntrica · hora UTC',
            'samples' => $samples,
            'animation' => $options,
            'calculation_models' => [$event->calculationModel, 'meeus-eclipse-inertial-trajectory-earth-fixed-shadow-v2'],
        ],
        'scale_note' => 'Distancias no a escala',
    ];
}
