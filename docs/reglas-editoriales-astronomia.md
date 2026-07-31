# Reglas editoriales de presentación astronómica

Relevamiento del comportamiento vigente al 28 de julio de 2026. Este documento
describe cómo la capa web convierte datos astronómicos en textos y señales visibles.
No redefine los cálculos de la API ni propone nuevos umbrales: registra los que usa
actualmente el código.

## Alcance y límite entre API y web

La web recibe de la API datos numéricos y, en algunos contratos, clasificaciones ya
resueltas. Por eso hay dos clases de reglas:

- **reglas web**: comparaciones, textos y mapeos implementados en este repositorio;
- **reglas heredadas de la API**: estados como `above_horizon`,
  `visibility_status`, `direction`, `moon_proximity`, `observation_aid`,
  `temporal_classification` y clasificaciones locales de eclipses. La web decide
  cómo redactarlos, pero sus umbrales astronómicos no están en este árbol.

En particular, “visible” no siempre significa lo mismo. En la portada lunar y los
perfiles equivale a estar sobre el horizonte; en “Esta noche” depende de las
ventanas y estados que entrega `/v1/astronomy/tonight`; en eclipses depende de la
clasificación local de la API. Ninguna de esas reglas incorpora por sí sola
nubosidad, brillo del cielo u obstrucciones del horizonte.

## Fase lunar actual, ciclo e imágenes

| Caso visible | Regla y umbral vigente | Implementación | Documentación previa |
| --- | --- | --- | --- |
| Nombre de la fase actual en Inicio y “El cielo hoy” | `includes/moon-phase-presentation.php` ordena los eventos exactos. En el día civil local del evento muestra Luna nueva, Cuarto creciente, Luna llena o Cuarto menguante. Los demás días usa la fase posterior al último evento: Luna creciente, Luna gibosa creciente, Luna gibosa menguante o Luna menguante. La iluminación no participa. Si faltan eventos suficientes muestra “Fase no disponible”. | `astronomyMoonPhaseLabelForLocalDate()`, consumida por `index.php` y `cielo-de-hoy.php` | Centralizada desde el 28 de julio de 2026. |
| Próximas cuatro fases | Sólo acepta eventos `moon_phase` con subtipos `new_moon`, `first_quarter`, `full_moon` y `last_quarter`; los rotula “Luna nueva”, “Cuarto creciente”, “Luna llena” y “Cuarto menguante”. Conserva el primer evento futuro de cada subtipo y luego ordena por fecha. | `index.php`, `$phaseLabels` y construcción de `$nextPhases` | Parcialmente en `docs/arquitectura.md`, sección del planificador. |
| Etiqueta amigable en datos de libración | Mapea nombres ingleses a Luna nueva, Cuarto creciente, Luna llena, Cuarto menguante, Luna creciente fina, Luna menguante fina, Luna gibosa creciente y Luna gibosa menguante; un valor desconocido se normaliza (`-`/`_` a espacios) y se capitaliza. | `includes/event-presentation.php`, `astronomyMoonPhaseFriendlyLabel()` y `astronomyLibrationPhaseSummary()` | No estaba detallado. |
| Ciclo técnico creciente/menguante en el detalle de “El cielo hoy” | `age_days < 14,765` produce “Creciente”; desde `14,765` produce “Menguante”. Es un dato técnico separado y no decide el nombre editorial visible. | `cielo-de-hoy.php`, asignación de `$moonTrend` | Documentado aquí. |
| Miniatura de fase | Edad válida: `0 <= age_days < 29,530588853`. Antes de la mitad del mes sinódico el asset es `waxing`; desde la mitad, `waning`. La iluminación se redondea al entero 0–100 y la orientación usa hemisferio sur si `latitud < 0`, norte en caso contrario. | `includes/moon-images.php`, `moonPhaseDirectionFromAge()` y `moonPhaseThumbnail()` | Arquitectura sólo mencionaba el helper, no sus límites. |
| Superluna | Sólo un evento `moon_phase/full_moon` con `apparent_size_percent >= 105` por defecto cambia a título “Superluna”. El umbral es configurable entre 90 y 120 mediante `SUPERMOON_MIN_APPARENT_SIZE_PERCENT` o `supermoon_min_apparent_size_percent`. | `includes/event-presentation.php`, `astronomyEventPresentation()`; carga en `includes/api-config.php` | Sí: `README.md`, `docs/arquitectura.md`, `docs/configuracion.md` y `docs/despliegue.md`. |

La portada también calcula `$isSupermoon` con el mismo umbral para su presentación
visual. A diferencia de los eventos, no restringe esa bandera a un subtipo: parte
del dato diario y del tamaño aparente actual.

## Direcciones por azimut

`homeMoonDirection()` y `astronomyEventAzimuthDirection()` implementan por separado
la misma rosa de ocho sectores. Normalizan el azimut a `[0, 360)` y aplican
`floor((azimut + 22,5) / 45) % 8`.

| Sector | Intervalo, con 360° equivalente a 0° | Texto |
| --- | --- | --- |
| Norte | `[337,5°, 360°)` y `[0°, 22,5°)` | `norte` |
| Noreste | `[22,5°, 67,5°)` | `noreste` |
| Este | `[67,5°, 112,5°)` | `este` |
| Sudeste | `[112,5°, 157,5°)` | `sudeste` |
| Sur | `[157,5°, 202,5°)` | `sur` |
| Sudoeste | `[202,5°, 247,5°)` | `sudoeste` |
| Oeste | `[247,5°, 292,5°)` | `oeste` |
| Noroeste | `[292,5°, 337,5°)` | `noroeste` |

Implementaciones y consumidores:

- `includes/home-sky.php`, `homeMoonDirection()`: frase de situación lunar de
  Inicio; reutilizada por `cielo-de-hoy.php`, `todayDirectionLabel()`, para mostrar
  azimut numérico más cardinal y para seleccionar oportunidades hacia
  noreste/este/sudeste;
- `includes/event-presentation.php`, `astronomyEventAzimuthDirection()`: dirección
  del Sol durante el máximo de un eclipse;
- `includes/tonight.php`, `astronomyTonightDirection()`: **no calcula azimut**.
  Recibe de la API un texto `direction`; conserva `arriba` y ante cualquier otro
  valor no vacío antepone “hacia el ”;
- `assets/js/planner.js`: muestra azimut con un decimal y dibuja la línea geodésica,
  pero no lo convierte a cardinal;
- libraciones y luz cenicienta usan direcciones semánticas del subtipo o detalles,
  no un azimut: `includes/event-presentation.php`,
  `astronomyLibrationDirectionMetadata()` y el bloque `earthshine`.

La fórmula de ocho sectores no estaba documentada antes de este relevamiento.

## Altura y situación visible de la Luna en Inicio

`includes/home-sky.php`, `homeMoonSituationPresentation()`, aplica las reglas en
este orden; las primeras tienen prioridad sobre las siguientes:

1. Si `above_horizon === false`, no usa altura ni fase: presenta el aviso de próxima
   salida o “No está sobre el horizonte”.
2. Si la diferencia a la Luna nueva más cercana es `<= 1 día`, dice “Está sobre el
   horizonte, pero es prácticamente imposible verla”.
3. Si la diferencia es `> 1` y `<= 3 días`, dice “Está muy finita y cuesta
   encontrarla a simple vista”.
4. En los demás casos usa `homeMoonVisibleSituation()`:

| Altura | Texto de altura | Dirección |
| --- | --- | --- |
| `< 15°` | `muy baja` | agrega el sector cardinal |
| `>= 15°` y `< 35°` | `baja` | agrega el sector cardinal |
| `>= 35°` y `< 60°` | `a media altura` | agrega el sector cardinal |
| `>= 60°` y `< 80°` | `muy alta. Mirá casi hacia arriba` | omite dirección |
| `>= 80°` | `prácticamente sobre tu cabeza` | omite dirección |

La diferencia respecto de Luna nueva se obtiene primero del evento real más cercano.
Si no hay uno válido, `homeNewMoonDifferenceFromAge()` usa un mes sinódico medio de
`29,530588 días` y toma la menor distancia a cualquiera de sus extremos.

Las fronteras de altura, dirección y cercanía a Luna nueva están cubiertas por
`tests/home-moon-situation.php`. Antes sólo se documentaban en `README.md` los tres
grupos generales, sin los umbrales de altura ni la rosa de azimut.

## Próxima salida lunar en Inicio

`includes/home-sky.php`, `homeMoonriseNoticePresentation()`, calcula los minutos con
redondeo hacia arriba y aplica:

| Tiempo restante | Texto | Nivel visual |
| --- | --- | --- |
| sin salida futura o por encima de la ventana máxima | `No está sobre el horizonte.` | ninguno |
| `>= 60 min` y dentro de la ventana | `La Luna saldrá a las HH:MM.` | ninguno |
| `>= 15 min` y `< 60 min` | `La Luna saldrá en X minutos.` | `soon` |
| `>= 5 min` y `< 15 min` | `La Luna está por salir.` | `imminent` |
| `< 5 min` | `Preparate: la Luna está por salir.` | `now` |

La ventana máxima vale 120 minutos por defecto y es configurable entre 1 y 1440 con
`MOONRISE_NOTICE_MAX_MINUTES` o `moonrise_notice_max_minutes`. Si la salida del día
ya pasó o falta, Inicio consulta condicionalmente el día siguiente. Esta regla ya
estaba documentada en `README.md`, `docs/arquitectura.md`,
`docs/configuracion.md` y `docs/despliegue.md`.

## Visibilidad lunar en “El cielo hoy”

`cielo-de-hoy.php`, `todayMoonSummary()`, usa los intervalos de visibilidad de la API
y el reloj local:

- sin intervalos: “No estará sobre el horizonte durante esta fecha”;
- durante un intervalo del día actual: si restan de 1 a 60 minutos, “Ya está
  visible y se pondrá dentro de X minutos”; en otro caso, “seguirá viéndose durante”
  la parte del día en que termina;
- antes del próximo intervalo: si faltan de 1 a 60 minutos, “saldrá dentro de X
  minutos”; en otro caso, “volverá a verse durante” la parte del día de inicio;
- después de todos los intervalos: no volverá a estar sobre el horizonte ese día;
- para otra fecha, resume uno o varios intervalos por partes del día.

`todayDayPart()` define madrugada `< 06:00`, mañana `06:00–11:59`, tarde
`12:00–18:59` y noche desde `19:00`. Esta redacción y sus umbrales no estaban
documentados.

## Visibilidad y textos de “Esta noche”

La API entrega `night.start`, `night.end`, ventanas por objeto y estados. La web
tiene dos presentaciones en `includes/tonight.php`.

La presentación básica (`astronomyTonightPlanetSentence()` y
`astronomyTonightObjectSentence()`) traduce:

- `visible_now`: “está/Visible ahora”, dirección sólo en este estado y final
  “hasta las HH:MM”; si no hay fin, “durante el resto de la noche”;
- `visible_later`: “aparecerá/Será visible desde las HH:MM”; si falta inicio,
  “más tarde”; si falta fin, “hasta el amanecer”;
- ayudas `naked_eye`, `binoculars` y `telescope`: “a simple vista”,
  “preferentemente con binoculares” y “con telescopio”;
- proximidad lunar heredada de la API: `very_close` → “muy cerca” y `close` →
  “cerca”; nunca se aplica a la propia Luna.

La presentación natural de la página detallada recalcula el estado efectivo y
recorta cada ventana a la noche (`astronomyTonightRelevantObject()` y
`astronomyTonightNaturalSentence()`):

| Condición web | Texto o decisión |
| --- | --- |
| ventana terminada, vacía o incompleta | omite el objeto |
| inicio en el primer 20% de la noche | etiqueta `Al anochecer` |
| inicio en el último 28% (`posición >= 0,72`) | etiqueta `Antes del amanecer` |
| entre ambos | etiqueta `Durante la noche` |
| visible ahora y final a 10 min o menos del fin nocturno | `seguirá viéndose hasta el amanecer` |
| visible ahora con 180 min o más restantes | `Seguirá visible hasta las HH:MM` |
| visible ahora con menos de 180 min | `Está visible ahora hasta las HH:MM` |
| comienzo a 45 min o menos del inicio nocturno | se considera `desde el anochecer` o `al comenzar la noche` |
| final a 10 min o menos del fin nocturno | se considera `hasta el amanecer` |
| ventana futura de 240 min o más, sin los casos anteriores | `durante gran parte de la noche, desde las HH:MM` |
| otra ventana futura | `Aparecerá a las HH:MM y seguirá visible hasta las HH:MM` |

El estado general de la noche usa otro umbral de 180 minutos: con al menos ese tiempo
restante dice que quedan “varias horas”; por debajo informa la hora de finalización.
Los destacados eligen hasta dos planetas, después la estrella de menor magnitud
numérica y, si aún hay menos de dos elementos, la Luna; el máximo final es tres.

`docs/arquitectura.md` ya explicaba los estados heredados y el uso de dirección, pero
no inventariaba estos umbrales ni todas las frases. Hay cobertura parcial en
`tests/tonight-presentation.php` y `tests/tonight-full-router.php`.

## Otras reglas basadas en umbrales o clasificaciones

| Área | Regla vigente | Implementación | Documentación previa |
| --- | --- | --- | --- |
| Conjunciones | Separación `<= 1°`: “muy juntas”; `> 1°` y `<= 3°`: “muy cerca”; mayor: “cerca”. La simultaneidad visible depende de `both_above_horizon` de la API. | `includes/event-presentation.php`, `astronomyEventPresentation()` | No detallada. |
| Libración | Amplitud `>= 7,2°`: recomienda telescopio o fotografía detallada; menor: la describe como efecto sutil para comparar fotos. | mismo helper | No detallada. |
| Oportunidad de Luna llena | La web no fija tolerancia al presentar: redacta la diferencia redondeada y `temporal_classification` de la API. Inicio solicita eventos con `max_difference_minutes=70`. | `includes/event-presentation.php`, `astronomyFullMoonObservationSummary()`; consulta en `index.php` | El parámetro 70 figuraba en arquitectura, no la traducción completa. |
| Luna fina y luz cenicienta | Se presentan los amaneceres locales -2 y -1 y los atardeceres +1 y +2 respecto de Luna nueva. Una oportunidad que también cumple la regla científica de luz cenicienta conserva esa condición en la misma tarjeta; nunca se crean dos tarjetas. En el día civil de Luna nueva, una iluminación real `< 0,4 %` oculta la tarjeta sin redondear el valor para decidir. El resumen redondea sólo al mostrar e incluye iluminación, separación Sol-Luna y diferencia temporal; horarios, intervalo útil y nubosidad completan la tarjeta. | Construcción astronómica en API: `app/services/local_thin_moon.py` y fusión en `app/services/lunar_events/service.py`; presentación y filtro compartidos: `includes/event-presentation.php`, `astronomyEarthshineObservationDetails()` y `astronomyEarthshineEventIsDisplayable()`; consumidores `index.php` y `eventos.php`. | Agregada en esta revisión. |
| “Cinturón de Venus” experimental | Iluminación `>= 95%`, salida lunar a no más de 90 min de la puesta solar, dirección de salida noreste/este/sudeste y existencia de crepúsculo civil vespertino. | `cielo-de-hoy.php`, `$venusBeltOpportunity`; texto en `assets/js/today-visual-experiment.js` | No documentada. |
| Nubosidad | `0–20%` Despejado; `21–50%` Algunas nubes; `51–80%` Mayormente nublado; `81–100%` Cubierto. Un valor horario sirve para un evento sólo si está a no más de 30 min. Caché: 30 min. | `assets/js/cloud-cover.js`, `cloudCategory()`, `nearestCloudCover()` y `nearestCloudLayers()` | Arquitectura explicaba fuente/caché, no categorías ni tolerancia. Fronteras probadas en `tests/cloud-cover.js`. |
| Mejor hora Luna/nubes | Elige la menor nubosidad entre puntos que caen dentro de intervalos lunares; si no hay puntos coincidentes, toma el mínimo de todos y cambia el texto a “La menor nubosidad del día”, sin afirmar visibilidad lunar. | `assets/js/today.js` | No documentada. |
| Perfiles de altura | Horizonte visual y relleno en `0°`; interpola cruces linealmente. La escala agrega margen de 5°, redondea a decenas y queda limitada a `[-90°, 90°]`, con al menos `[-10°, 10°]`. | `assets/js/home-sky.js` | Sí para horizonte/interpolación en arquitectura; no para escala. |
| Planificador | Una posición instantánea sólo dibuja línea si `above_horizon` es verdadero. Salidas y puestas se dibujan si existen. La altura se muestra numérica, sin adjetivo. | `assets/js/planner.js` | Sí en arquitectura. |
| Calendario solar/lunar | Cero intervalos → “No visible durante el día”; un intervalo de medianoche a medianoche → “Visible todo el día”; cualquier otro único → “1 intervalo de visibilidad”; varios → “N intervalos de visibilidad”. | `sol-y-luna.php`, `formatVisibilitySummary()` | No detallada. |
| Eclipses | La web traduce clasificaciones locales (`not_visible`, `visible_partial`, `visible_total`, `visible_annular`, `visible_hybrid`, `visible_penumbral_only`); no calcula sus umbrales. Magnitud y alturas sólo se formatean. | `includes/event-presentation.php` y `eclipses.php` | Sí en arquitectura a nivel general. |

## Diagnóstico de centralización

### Estado actual

La centralización es **parcial**:

- el nombre editorial de la fase actual está centralizado en
  `moon-phase-presentation.php`; Inicio lunar sigue en `home-sky.php`, noche en
  `tonight.php` y eventos en `event-presentation.php`;
- la configuración externa sólo cubre superluna, ventana de aviso lunar e intervalo
  de perfiles;
- los textos de “El cielo hoy” viven directamente en `cielo-de-hoy.php`;
- nubosidad y gráficos tienen sus decisiones en JavaScript;
- no existe un catálogo único de textos ni un registro común de umbrales editoriales.

### Duplicaciones y divergencias

- La conversión azimut → ocho cardinales está duplicada exactamente entre
  `homeMoonDirection()` y `astronomyEventAzimuthDirection()`.
- Las nociones “sobre el horizonte”, “visible ahora” y “visible esta noche” se
  presentan en al menos cuatro rutas con contratos distintos. No son duplicaciones
  exactas, pero sus nombres parecidos facilitan inconsistencias.
- Inicio, “El cielo hoy” y “Esta noche” mantienen tres motores de frases temporales
  separados y vocabularios distintos: “Sale”, “saldrá”, “volverá a verse”,
  “aparecerá”, “será visible” y “seguirá viéndose”.
- El dato técnico creciente/menguante usa `14,765` en `cielo-de-hoy.php`, mientras
  las miniaturas usan la mitad de `29,530588853`; ya no deciden el nombre editorial,
  pero siguen siendo fronteras conceptualmente relacionadas sin constante compartida.
- Eclipses traducen clasificaciones tanto en `includes/event-presentation.php` como
  en `eclipses.php`, con textos cortos diferentes.
- Hay dos familias nocturnas dentro de `tonight.php`: frases básicas y frases
  naturales. Comparten datos, pero no un compositor común.

### Dificultad para volver configurables textos y umbrales

- **Baja**: valores escalares aislados ya pasados por helpers, como alturas
  15/35/60/80, cercanía a Luna nueva 1/3, conjunciones 1/3, libración 7,2,
  categorías de nubes o tolerancias nocturnas. Requieren extraer constantes y
  conservar pruebas de frontera.
- **Media**: textos de un único dominio (`home-sky.php` o `tonight.php`). Conviene
  usar plantillas con variables tipadas y mantener la puntuación en un compositor,
  no simples reemplazos de cadenas.
- **Media/alta**: configuración operativa desde entorno para todos los textos. El
  mecanismo actual de `api-config.php` está orientado a escalares y booleanos; no
  valida catálogos anidados, traducciones, placeholders ni coherencia entre PHP y
  JavaScript.
- **Alta**: unificar la semántica de visibilidad sin cambiar comportamiento. Antes
  hay que modelar explícitamente horizonte geométrico, ventana nocturna,
  observabilidad de API, eclipse local y nubosidad como conceptos diferentes.

### Cambios estructurales necesarios

Sin aplicarlos todavía, una centralización segura requeriría:

1. crear un módulo PHP de política editorial con constantes/objetos de valor para
   sectores de azimut, bandas de altura, ventanas temporales y textos;
2. hacer que `home-sky.php`, `tonight.php`, `event-presentation.php` y
   `cielo-de-hoy.php` consuman esa política, manteniendo funciones adaptadoras para
   los contratos actuales;
3. separar en nombres y tipos la visibilidad geométrica, nocturna, observacional y
   de eclipses;
4. definir un catálogo de mensajes con placeholders validados y variantes por
   contexto, en vez de concatenaciones distribuidas;
5. exponer a JavaScript sólo el subconjunto de política necesario mediante JSON
   generado por PHP, evitando mantener otra copia manual de los umbrales;
6. ampliar pruebas de frontera y snapshots de textos antes de mover lógica, para
   demostrar que la refactorización no altera el comportamiento;
7. decidir qué valores son política de producto configurable y cuáles deben seguir
   versionados como reglas editoriales. No todos los textos conviene exponer como
   variables de entorno.

## Archivos revisados

Implementación principal:

- `index.php`, `cielo-de-hoy.php`, `cielo-de-esta-noche.php`,
  `sol-y-luna.php`, `eventos.php`, `eclipses.php` y `planificador.php`;
- `includes/home-sky.php`, `includes/tonight.php`,
  `includes/event-presentation.php`, `includes/presentation.php`,
  `includes/moon-images.php`, `includes/api-config.php` e
  `includes/astronomy-icon.php`;
- `assets/js/home-sky.js`, `assets/js/today.js`,
  `assets/js/today-visual-experiment.js`, `assets/js/cloud-cover.js`,
  `assets/js/planner.js`, `assets/js/astro-map.js` y
  `assets/js/sky-timeline.js`.

Documentación y pruebas contrastadas:

- `README.md`, `docs/arquitectura.md`, `docs/configuracion.md`,
  `docs/despliegue.md`, `docs/entorno-local.md` y `docs/estado-actual.md`;
- `tests/home-moon-situation.php`, `tests/tonight-presentation.php`,
  `tests/tonight-full-router.php`, `tests/cloud-cover.js`,
  `tests/event-presentation-libration.php` y
  `tests/event-presentation-eclipse.php`.
