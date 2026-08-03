# Entrega del motor astronómico PHP

## 1. Unidad portable

El directorio integrado que constituye la unidad portable es:

```text
/srv/proyectos/astronomia/web/astronomy-engine/
```

Debe copiarse completo, conservando `composer.json`, `README.md`, este documento,
`src/` y `assets/`. El directorio `cache/moon/` es almacenamiento de ejecución:
debe crearse escribible en destino, pero sus PNG generados no deben copiarse.

La implementación vive en el namespace `AstronomyEngine` y se carga mediante
PSR-4:

```json
{
  "AstronomyEngine\\": "src/"
}
```

El paquete tiene su propio `composer.json`, no tiene dependencias de terceros
ni un `vendor` propio. La web registra en `includes/api-client.php` un
autoloader PSR-4 manual para `AstronomyEngine\`, apuntando a
`astronomy-engine/src/`; por eso la carga del motor no depende de regenerar
Composer ni de sincronizar `vendor/`. La asignación equivalente es:

```json
{
  "AstronomyEngine\\": "astronomy-engine/src/"
}
```

Composer no es un servicio
necesario durante la ejecución; si la aplicación proporciona otro autoloader
PSR-4 compatible, el cálculo tampoco depende del ejecutable Composer.

Requisitos declarados y comprobados:

- PHP `>=8.5`;
- extensión estándar `date` y clases `DateTimeImmutable`/`DateTimeZone`;
- funciones matemáticas estándar de PHP;
- `ext-gd` con soporte PNG sólo para ejecutar `MoonImageRenderer`;
- SPL para excepciones e interfaces incluidas con PHP.

La integración productiva sirve 404 PNG precalculados y rota la imagen en CSS,
por lo que no requiere GD en runtime. El motor tampoco requiere `ext-mbstring`,
`ext-curl`, extensiones de base de datos, procesos
externos ni dependencias Composer adicionales. El código de `src/` no depende
de Docker, Python, HTTP, PostgreSQL, MariaDB/MySQL, HTML ni del laboratorio.

### Inventario de clases del motor

| Archivo/clase | Responsabilidad |
|---|---|
| `SolarPositionCalculator.php` | Contrato de posición solar. |
| `MeeusSolarPositionCalculator.php` | Posición solar geométrica topocéntrica. |
| `SolarPosition.php` | Resultado de posición solar. |
| `SolarDayCalculator.php` | Eventos y períodos de un día solar local. |
| `SolarDay.php`, `SolarPeriod.php` | Resultados del bloque solar diario. |
| `MeeusLunarCalculator.php` | Posición, distancia, iluminación y fase lunar instantánea. |
| `LunarPosition.php` | Resultado lunar instantáneo. |
| `LunarDayCalculator.php` | Salida, puesta e intervalos lunares de un día local. |
| `LunarDay.php`, `LunarVisibilityInterval.php` | Resultados del bloque lunar diario. |
| `PrincipalPhaseCalculator.php` | Instantes de las cuatro fases principales. |
| `SolsticeCalculator.php` | Instantes UTC de los solsticios de junio y diciembre mediante Meeus capítulo 27. |
| `LunarLibrationCalculator.php` | Libración geocéntrica óptica y física. |
| `LunarEventCalculator.php` | Fases, ápsides, nodos, libraciones y conjunciones por intervalo, con selección opcional de grupos para evitar cálculos no solicitados. |
| `LunarEvent.php` | Resultado común de un evento lunar. |
| `LunarEclipseCalculator.php` | Detección y geometría global de eclipses lunares validada e integrada. |
| `LunarEclipse.php`, `LunarEclipseContacts.php` | Resultados globales de eclipse lunar y contactos. |
| `MeeusEclipsePositionCalculator.php` | Serie completa de Meeus capítulo 47 y posición solar aparente coherente, exclusiva de la geometría de eclipses. |
| `SolarEclipseCalculator.php` | Detección, máximo y clasificación global de eclipses solares mediante Meeus capítulo 54. |
| `SolarEclipse.php` | Resultado portable de un eclipse solar global. |
| `EclipseObserver.php` | Coordenadas, elevación y zona horaria del observador local. |
| `EclipseTopocentricGeometry.php` | Reducción vectorial topocéntrica Sol/Luna para eclipses. |
| `LunarEclipseLocalCalculator.php`, `LunarEclipseLocalCircumstances.php` | Visibilidad, contactos y horizonte lunar local. |
| `SolarEclipseLocalCalculator.php`, `SolarEclipseLocalCircumstances.php` | Contactos solares topocéntricos, clase, magnitud, cobertura y horizonte local. |
| `Facade/AstronomyObserver.php` | Observador compartido por las fachadas portables. |
| `Facade/DailyAstronomyFacade.php` | Composición del estado diario solar y lunar. |
| `Facade/AstronomyRangeFacade.php` | Iteración y proyección resumida de daily, hasta 366 días. |
| `Facade/AstronomyDirectionsFacade.php` | Posiciones instantáneas y azimuts de rise/set. |
| `Facade/MoonInstantFacade.php` | Contrato lunar instantáneo y derivados matemáticos simples. |
| `Facade/FullMoonObservationFacade.php` | Oportunidades locales de mañana/tarde alrededor de Lunas llenas calculadas por PHP. |
| `Facade/EarthshineFacade.php` | Oportunidades generales de Luna fina y ventanas estrictas de earthshine alrededor de Lunas nuevas calculadas por PHP. |
| `Facade/AstronomyEventsFacade.php` | Selección, composición y normalización unificada de todas las familias de eventos disponibles. |
| `Facade/AltitudeProfileFacade.php` | Tres series diarias de altura/azimut para Sol o Luna, con comparación estacional solar. |
| `Facade/FacadeSupport.php` | Normalización temporal, intervalos y duraciones compartidas. |
| `PrecisionProfile.php` | Perfiles internos `rapido`, `normal` y `preciso`. |
| `ApproximatePlanetCalculator.php` | Coordenadas planetarias aproximadas y coordenadas solares auxiliares. |
| `ConjunctionCatalog.php`, `ConjunctionTarget.php` | Catálogo de objetivos de conjunción. |
| `EquatorialCoordinates.php` | Resultado ecuatorial auxiliar. |
| `MoonBrightLimbCalculator.php` | Orientación local del limbo lunar iluminado para el modo aparente. |
| `MoonImageRenderer.php` | Sombreado, orientación, PNG y caché de la imagen lunar con GD. |
| `MoonImageRenderResult.php` | PNG, metadatos, caché y tiempos del renderer. |
| `TonightCatalog.php` | Veinte estrellas Hipparcos y tres centros de cúmulos usados por visibilidad nocturna. |
| `TonightCalculator.php` | Noche civil, ventanas y visibilidad de planetas, Luna, estrellas y cúmulos. |

## 2. Funciones astronómicas disponibles

### Sol

- altitud y azimut geométricos topocéntricos;
- declinación y ángulo horario auxiliares;
- salida y puesta;
- mediodía solar;
- crepúsculos civil, náutico y astronómico;
- hora azul y hora dorada;
- períodos diarios asociados.

El motor no aplica refracción atmosférica a la posición instantánea. Los
eventos diarios usan los umbrales geométricos documentados en el código.

### Luna diaria

- altitud y azimut geométricos topocéntricos;
- distancia geocéntrica y topocéntrica;
- longitud y latitud eclípticas, ascensión recta y declinación;
- iluminación, ángulo de ciclo, edad y nombre descriptivo de fase;
- moonrise, moonset e intervalos dentro del día civil local.

### Eventos lunares no-eclipse

- luna nueva, cuarto creciente, luna llena y cuarto menguante;
- perigeo y apogeo;
- nodo ascendente y nodo descendente;
- extremos de libración este, oeste, norte y sur;
- conjunciones lunares geocéntricas y sus campos locales de visibilidad.

Los eclipses lunares globales y sus circunstancias locales están validados e
integrados mediante sus calculadores separados.

Los eclipses solares globales están implementados con Meeus capítulo 54 y
devuelven clasificación, MAX, `gamma`, `u` y geometría disponible. No incluyen
visibilidad local, mapas, trayectoria ni contactos globales distintos de MAX.
La fachada portable combina este resultado global con el calculador local ya
validado e integrado en producto.

Las circunstancias locales lunares y solares están implementadas como clases
separadas. Reutilizan la infraestructura diaria de horizonte y comparten sólo
la reducción topocéntrica necesaria. Están validadas e integradas en producto;
la guía y los campos comparables permanecen en `docs/php-local-eclipses.md` del
laboratorio.

### Eventos locales derivados

- observación de Luna llena en los días locales `-1`, `0` y `+1`, emparejando
  moonrise/sunset o moonset/sunrise y aplicando los umbrales configurados;
- oportunidades de Luna fina en los días `-2`, `-1`, `+1` y `+2` alrededor de
  Luna nueva;
- ventanas estrictas de earthshine mediante muestreo configurable, 15 minutos
  por defecto, con iluminación `2%..15%`, Luna sobre el horizonte y Sol por
  debajo de `-6°`.

Estas salidas son composiciones de fases, días y posiciones existentes. Su
metodología y validación manual están documentadas en
`docs/php-derived-observation-events.md` del laboratorio. No se ejecutó una
batería general y no se declara aquí una conclusión de precisión comparativa.

`AstronomyEventsFacade` reúne estas salidas con fases, ápsides, nodos,
libraciones, conjunciones y eclipses globales/locales bajo un contrato común.
La selección pública, alias, normalización y deduplicación están documentados
en `docs/php-events-facade.md` del laboratorio.

### Perfiles de altura

`AltitudeProfileFacade` conserva el día completo, incluidas alturas negativas,
y genera tres series: víspera/fecha/siguiente para la Luna, o invierno/fecha/
verano para el Sol. El muestreo es configurable entre 5 y 60 minutos y usa 15
por defecto. `SolsticeCalculator` calcula sólo junio y diciembre; la asignación
estacional depende de la latitud. El contrato se documenta en
`docs/php-altitude-profile.md` del laboratorio.

## 3. Estado de validación y precisión registrada

Esta sección resume resultados ya obtenidos. No implica que Skyfield sea una
verdad independiente; registra compatibilidad con la referencia Python usada
por el laboratorio.

| Fenómeno | Evidencia disponible | Estado para traslado |
|---|---|---|
| Posición solar | Batería de 80 casos de 2026: diferencia media de altitud `0.002188773°`, P95 `0.006913800°`, máxima `0.007076629°`; diferencia media de azimut `0.005596977°`, P95 `0.012362586°`, máxima `0.045475308°`. | Suficientemente validado para integrar manteniendo sus definiciones geométricas. |
| Bloque solar diario | Batería web histórica y casos polares preparados; cruces, períodos y ausencias se representan explícitamente. El repositorio no conserva un informe numérico final de la ejecución completa. | Implementado; conservar validación web durante la integración. |
| Luna instantánea y diaria | Comparación web de posición, distancia, iluminación, moonrise/moonset e intervalos. El repositorio no conserva el resumen numérico completo de la última ejecución. | Implementado; limitación principal: serie lunar truncada de 20 términos. |
| Fases principales | En el smoke de enero-febrero de 2026, seis fases quedaron emparejadas con diferencias temporales entre `23.909` y `47.745` segundos. La batería manual 2026–2030 fue preparada y ejecutada externamente, pero su informe numérico no quedó versionado. | Suficientemente validado para traslado con la diferencia de modelo documentada. |
| Ápsides y nodos | La ejecución 2026–2030 comunicada durante el laboratorio mantuvo conteos y emparejamientos; no quedó un informe estadístico persistido en archivos. | Funcionalmente validado; precisión temporal no fue objeto de mejora final. |
| Conjunciones | Smoke de 40 días desde 2026-06-01: `15` conjunciones emparejadas. La batería 2026–2030 comunicada mostró el bloque alineado en conteos; las estadísticas completas no están versionadas. | Funcionalmente validado dentro de las limitaciones del modelo aproximado. |
| Libración | Antes de la corrección, la batería 2026–2030 produjo `268` eventos Python, `268` PHP y `268` emparejados, pero valores longitudinales incorrectos. Después de corregir `W`, se revalidó sólo la ventana de enero de 2026. | Algoritmo corregido y smoke validado; falta repetir manualmente la batería 2026–2030 para cerrar estadísticas agregadas posteriores a la corrección. |
| Eclipse lunar global | Casos total/parcial/penumbral, penumbral rasante y ausencia correctamente clasificados. Tras la corrección, en 2025–2030: Python `14`, PHP `14`, emparejados `14`, sólo Python `0`, sólo PHP `0`; clases coincidentes `14/14`. Diferencia máxima: MAX `127.925 s`, contactos `171.105 s`, magnitud umbral `0.001834`, penumbral `0.001862`. | Validado para detección y clasificación global dentro de 2025–2030. Los tiempos y magnitudes conservan las diferencias numéricas documentadas; no incluye visibilidad local. |

Las baterías, sus clientes Python y las estadísticas pertenecen al laboratorio,
no al paquete portable.

## 4. Corrección de libración

La formulación implementada sigue el capítulo 53 de Jean Meeus. La causa del
defecto longitudinal era usar:

```text
W = lambda - 0.00478 sin(Omega)
```

en lugar de:

```text
W = lambda - Omega
```

La implementación corregida calcula `A` mediante `atan2(y, x)`, forma
`l' = A - F` y normaliza únicamente esa diferencia al intervalo firmado
`-180°..+180°`. Las correcciones físicas se suman una sola vez. Convenciones:
este y norte positivos; oeste y sur negativos.

Resultados finales del smoke de enero de 2026:

| Evento | Python | PHP | Diferencia de valor | Otro eje Python/PHP |
|---|---:|---:|---:|---:|
| Sur, 2026-01-01 | `-6.577083°` | `-6.549409°` | `0.027674°` | `-0.715003° / -0.697715°` |
| Este, 2026-01-07 | `+6.999196°` | `+7.021509°` | `0.022313°` | `-0.019454° / +0.023365°` |
| Norte, 2026-01-14 | `+6.704555°` | `+6.722212°` | `0.017657°` | `-0.179787° / -0.177659°` |
| Oeste, 2026-01-22 | `-5.405660°` | `-5.399270°` | `0.006390°` | `-0.784304° / -0.794344°` |

La ventana conservó cinco extremos antes y después de la corrección; los cinco
quedaron emparejados. Una comprobación interna lanza una excepción si una
componente supera `15°` en valor absoluto; nunca recorta el resultado.

Limitación restante: Meeus usa series analíticas aproximadas, mientras la
referencia utiliza el marco físico `MOON_ME_DE421`. La batería completa
2026–2030 posterior a la corrección queda como validación manual pendiente.

## 5. Conjunciones

Se admiten exactamente estos objetivos:

- planetas: Mercurio, Venus, Marte, Júpiter y Saturno;
- estrellas Hipparcos: Aldebarán, Elnath, Alhena, Pólux, Régulo,
  Zubenelgenubi, Spica, Antares, Nunki y Deneb Algedi;
- centros de cúmulos SIMBAD: Pléyades, Híades y M44/Pesebre.

Los planetas usan elementos keplerianos aproximados publicados por JPL para
1800–2050. No usan DE421, tiempo de luz, aberración completa ni perturbaciones
planetarias de alta precisión. El uso fuera de ese intervalo es extrapolación
del modelo y no está validado.

Las estrellas usan coordenadas Hipparcos y movimiento propio; los cúmulos usan
centros ICRS de SIMBAD. Ambos se precesan a fecha. El modelo PHP no incorpora
todos los efectos astrométricos aparentes ni reproduce los kernels DE421.

El evento es un mínimo geocéntrico de separación, muestreado cada hora y
refinado numéricamente; sólo se emite si la separación es menor o igual a
`5°`. La parte local muestrea `±3 h` cada diez minutos y aplica los umbrales de
altura lunar/objetivo `10°`, altura solar `-6°` y elongación solar `15°`.

## 6. Qué copiar y qué excluir

### COPIAR AL PROYECTO PRINCIPAL

Copiar como una unidad:

```text
astronomy-engine/composer.json
astronomy-engine/README.md
astronomy-engine/ENTREGA.md
astronomy-engine/src/
astronomy-engine/assets/Luna llena.png
```

Dentro de `src/` deben copiarse todos los archivos `.php` enumerados en la
tabla de inventario. `src/.gitkeep` puede omitirse.

La documentación técnica complementaria puede conservarse junto al proyecto
principal si se desea mantener el detalle de cada fórmula:

```text
docs/php-solar-implementation.md
docs/php-solar-block.md
docs/php-lunar-block.md
docs/php-lunar-events.md
docs/php-derived-observation-events.md
docs/php-events-facade.md
docs/php-altitude-profile.md
docs/php-moon-image.md
docs/php-tonight.md
```

Estos documentos no son necesarios para ejecutar el motor.

### NO COPIAR

```text
comparison/
public/
tests/
vendor/
docker-compose.yml
Dockerfile
apache.conf
composer.json              # Composer raíz del laboratorio
composer.lock              # lock del laboratorio
CONTEXTO.md
comparison/README.md
docs/python-*.md
docs/solar-position-validation.md
```

Tampoco copiar resultados temporales, clientes HTTP Python, páginas de
validación, informes, baterías ni configuración Docker. Ninguno es requerido
por `AstronomyEngine`.

## 7. Interfaz pública mínima existente

No se requiere una nueva fachada para integrar el código. Una aplicación puede
usar directamente estas entradas públicas:

```php
use AstronomyEngine\LunarDayCalculator;
use AstronomyEngine\LunarEclipseCalculator;
use AstronomyEngine\LunarEventCalculator;
use AstronomyEngine\MeeusLunarCalculator;
use AstronomyEngine\MeeusSolarPositionCalculator;
use AstronomyEngine\SolarDayCalculator;
use AstronomyEngine\Facade\AstronomyObserver;
use AstronomyEngine\Facade\AstronomyEventsFacade;
use AstronomyEngine\Facade\AltitudeProfileFacade;
use AstronomyEngine\Facade\EarthshineFacade;
use AstronomyEngine\Facade\FullMoonObservationFacade;

$solar = new MeeusSolarPositionCalculator();
$solarPosition = $solar->calculate($instant, $latitude, $longitude, $elevation);
$solarDay = (new SolarDayCalculator($solar))->calculate(
    $localDate,
    $latitude,
    $longitude,
    $elevation,
);

$lunar = new MeeusLunarCalculator();
$lunarPosition = $lunar->calculate($instant, $latitude, $longitude, $elevation);
$lunarDay = (new LunarDayCalculator($lunar))->calculate(
    $localDate,
    $latitude,
    $longitude,
    $elevation,
);

$lunarEvents = (new LunarEventCalculator($lunar))->calculate(
    $startUtc,
    $endUtc,
    $latitude,
    $longitude,
    ['moon_phase'], // opcional; omitir para calcular todos los grupos
);

$lunarEclipses = (new LunarEclipseCalculator($lunar))->events(
    $startUtc,
    $endUtc,
);

$observer = new AstronomyObserver($latitude, $longitude, $timezone, $elevation);
$fullMoonOpportunities = (new FullMoonObservationFacade())->between(
    $startUtc,
    $endUtc,
    $observer,
    90,
);
$thinMoonAndEarthshine = (new EarthshineFacade())->between(
    $startUtc,
    $endUtc,
    $observer,
);

$events = (new AstronomyEventsFacade())->between(
    startDate: $startUtc,
    observer: $observer,
    days: 30,
    types: null,
    options: ['max_difference_minutes' => 90],
);

$moonAltitudeProfile = (new AltitudeProfileFacade())->calculate(
    $localDate,
    $observer,
    ['target' => 'moon', 'interval_minutes' => 15],
);
```

`$instant`, `$localDate`, `$startUtc` y `$endUtc` son instancias de
`DateTimeImmutable`. Latitud y longitud se expresan en grados, longitud
positiva al este; elevación en metros. Para eventos puramente geocéntricos las
coordenadas sólo afectan los campos locales de conjunciones.
