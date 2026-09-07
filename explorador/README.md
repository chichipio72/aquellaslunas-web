# Explorador astronómico

El Explorador astronómico es una sección pública de Aquellas Lunas para comparar
series del Sol y la Luna por período y ubicación. Sus herramientas CLI de prueba
permanecen separadas de la pantalla pública.

## Integración pública

La URL pública es `/explorador/`. La página reutiliza encabezado, menú, ubicación,
pie, analítica, favicon y SEO del sitio, conservando un contenedor propio amplio
para controles y gráfico. En el menú aparece junto a Planificador con la clave
administrativa `menu.explorer.enabled`, habilitada de manera predeterminada.
Deshabilitarla oculta solamente la entrada del menú: la URL directa y sus
endpoints continúan disponibles.

La interfaz compacta es la presentación principal. Durante su período de prueba,
la presentación anterior se conserva en `/explorador/clasico.php`, accesible sólo
por URL directa y sin entrada propia en el menú. Ambas renderizan
`interfaz-base.php` y comparten catálogo, formulario, ECharts, JavaScript
funcional y endpoints; `pruebas-interfaz.css` y `pruebas-interfaz.js` contienen
exclusivamente la presentación y la interacción compactas.

Para revertir el intercambio basta con hacer que `index.php` cargue directamente
`interfaz-base.php` y retirar su transformación compacta. `clasico.php` puede
eliminarse después de verificar la reversión; no es necesario modificar endpoints,
catálogo ni assets funcionales.

Las descripciones del catálogo alimentan las ayudas accesibles de cada variable.
El mismo componente contextual se usa en modos, períodos, fases, relación,
ubicación y enlaces compartibles; funciona con hover, foco, teclado y toque.
El público conserva únicamente el progreso funcional del procesamiento: estado,
porcentaje, fechas procesadas, tiempo transcurrido y cancelación. El resumen y
los detalles técnicos de métricas se renderizan solamente para una sesión
administrativa autenticada, usando la autenticación oficial del sitio.

Los títulos de los ejes verticales se resuelven mediante el catálogo explícito
`catalog/scale-groups.php`. Sus magnitudes están normalizadas en español y la
unidad se muestra uniformemente entre paréntesis en el título del eje.

## Arquitectura del prototipo

```text
index.php / clasico.php → interfaz-base.php + assets/
        ↓ GET progresivo / fallback
api/series-stream.php (NDJSON) / api/series.php (JSON)
        ↓
SeriesRequest → SeriesPlanner → SeriesBuilder
        ↓                         ↓
VariableCatalog              AstronomyEngine PHP
```

El endpoint no usa MariaDB, FastAPI, Composer ni los includes generales de la
web. El motor se carga con el autoloader PSR-4 manual propio del subproyecto.

La protección de desarrollo `SeriesRequest::MAXIMUM_DEVELOPMENT_DAYS` permite
como máximo 73.050 días, aproximadamente 200 años. No es un límite de producto.

## Catálogo y proveedores

El catálogo declarativo vive en `catalog/variables.php`. Cada definición incluye
clave, nombres, categoría, descripción, tipo, unidad, grupo de escala, alcance,
proveedor, dependencias, redondeo y orden.

Los proveedores iniciales son:

- `moon_instant`: una posición lunar a las 00:00 locales por fecha;
- `sun_instant`: una posición solar a las 00:00 locales por fecha;
- `moon_events`: eventos e intervalos lunares diarios;
- `sun_events`: eventos y duraciones solares diarios;
- `derived`: amplitudes y diferencias que reutilizan valores anteriores.

Los proveedores de eventos usan `SolarEventsRangeCalculator` y
`LunarEventsRangeCalculator`. El primero obtiene el día inicial con el
calculador diario y proyecta cada evento 86.400 segundos absolutos; busca en
ventanas de ±5, ±30 y ±120 minutos. El segundo traslada el último evento por
la duración real del día civil y, cuando hay dos eventos consecutivos, suma la
deriva observada; usa ventanas de ±10, ±60 y ±240 minutos.

Cada salida y puesta conserva un predictor independiente. Si falta una semilla,
la ventana no encierra el cruce o hubo un día sin ese evento, se usa el
calculador diario validado. Los cruces predictivos se refinan hasta 0,05 segundos.
El contrato de rango solar incluye salida, puesta, azimutes y duraciones de día
y noche; el lunar incluye salida, puesta, azimutes y tiempo sobre el horizonte.

`SeriesBuilder::USE_PREDICTIVE_EVENT_RANGES` es el selector temporal de
desarrollo. Cambiarlo a `false` restaura el camino diario anterior sin exponer
esta opción en la interfaz pública.

Las horas se entregan como horas locales decimales para poder graficarlas. Las
diferencias diarias se definen como el intervalo real entre eventos menos el
intervalo real entre los comienzos de ambos días civiles. Un valor positivo
significa que el evento ocurrió más tarde respecto del día anterior. Esta
definición contempla cambios de offset horario.

Las amplitudes usan signo norte positivo: `90° - azimut` en salidas y
`azimut - 270°` en puestas.

## Uso web

Con los contenedores del proyecto activos, abrir:

```text
http://localhost/explorador/
```

ECharts `5.6.0` se carga exclusivamente desde la copia local compartida en
`vendor/frontend/echarts/5.6.0/echarts.min.js`. El Explorador usa la ruta relativa
`../vendor/frontend/echarts/5.6.0/echarts.min.js`, válida tanto en la raíz local
como bajo `/astro/` en producción. No hay fallback al CDN; si el archivo falta o
no carga, la API y las métricas siguen funcionando y la interfaz muestra un aviso
en el lugar del gráfico.

Como `vendor/` se excluye de los despliegues normales, la primera publicación de
esta versión debe ejecutarse expresamente con:

```bash
scripts/desplegar.sh --include-vendor
```

Antes puede comprobarse la inclusión con
`scripts/desplegar.sh --list-local --include-vendor` y verificar que aparezcan
los cuatro archivos de `vendor/frontend/echarts/5.6.0/`. Los despliegues normales
posteriores no necesitan `--include-vendor` mientras esa carpeta no cambie.

Para una actualización futura se debe elegir una versión exacta, crear otra
carpeta versionada con su distribución, licencia y README, actualizar la ruta en
`explorador/index.php`, validar el gráfico y realizar una nueva publicación
inicial con `--include-vendor`. No se reemplaza silenciosamente la versión actual.

Los campos de año son accesos rápidos: el año inicial completa el 1 de enero y
el final completa el 31 de diciembre. Las fechas siguen siendo los valores que
se envían a la API. Los rangos hacia atrás usan `fecha final + 1 día − N años`;
los rangos hacia adelante usan `fecha inicial + N años − 1 día`. De ese modo
son inclusivos sin agregar accidentalmente un año civil y conservan el 29 de
febrero de forma coherente.

### Ubicación de observación

La interfaz obtiene su ubicación inicial exclusivamente de
`astronomyLocationContext()`: nombre o etiqueta, coordenadas, zona horaria,
confirmación y fallback son los mismos del resto de Aquellas Lunas. El enlace
**Cambiar ubicación** abre `ubicacion.php` y lleva una ruta de retorno interna
validada bajo el prefijo real de la instalación, tanto en `/` como en `/astro/`.

El Explorador presenta esa ubicación como contexto de cálculo en una línea
compacta. No ofrece campos propios para editar coordenadas o zona horaria:
**Cambiar** lleva al selector general y, después de confirmar, el retorno carga
la nueva ubicación oficial sin iniciar automáticamente un cálculo.

Los endpoints permanecen independientes de cookies: la interfaz siempre envía
explícitamente `lat`, `lon` y `timezone` en cada solicitud.

### URLs compartibles

**Compartir configuración** construye una URL legible con formato `v=1`, la
coloca en la barra actual mediante `history.replaceState()` y la copia con
Clipboard API. Si el portapapeles no está disponible, muestra un campo compacto
para copia manual. El límite es 4.000 caracteres y nunca se trunca una URL.

Los parámetros generales son `v`, `modo`, `desde`, `hasta`, `lat`, `lon`, `tz`
y la etiqueta descriptiva opcional `ubicacion`. En Serie diaria se agregan
`campos`, `fases` o `dias=todos`, `relacion`, `a` y `b`. En Extremos locales se
usan `variable` y `tipo`. Las listas conservan claves internas y orden estable.
La URL incluye solamente el modo efectivo, no el estado oculto del otro modo.

Al abrir un enlace `v=1`, sus parámetros válidos tienen prioridad sobre la
ubicación general del receptor. La línea de contexto muestra esa ubicación y
señala discretamente que proviene del enlace compartido, sin modificar cookies.
Una configuración completamente válida inicia automáticamente el cálculo
progresivo normal; una URL común sin `v` nunca lo hace.

Se validan versión, fechas, límite de rango, coordenadas, zona horaria, catálogo,
fases y su cobertura 1900–2050, modo, extremos y análisis A/B. Las partes
inválidas vuelven a valores seguros, muestran una advertencia y evitan la
autoejecución. La etiqueta es sólo descriptiva, se limita a 80 caracteres y no
participa del cálculo.

El enlace contiene coordenadas y zona horaria, pero no cookies, sesiones,
resultados, métricas, zoom, leyenda ni progreso. Para evolucionar el contrato se
debe introducir explícitamente `v=2`, mantener el lector de `v=1` mientras sea
necesario y documentar una migración; una versión desconocida no se interpreta.

El selector visual agrupa las variables del catálogo en dominios Luna y Sol,
subsecciones de valores instantáneos y eventos, y contenedores indivisibles de
parejas semánticas. En monitores anchos aparecen varias parejas por fila; en
anchos intermedios se reduce la cantidad sin separar cada pareja; en móvil se
muestra una pareja por fila y sus dos tarjetas se apilan sólo cuando el ancho es
menor a 450 px.

Las variables cuyo `scaleGroup` es `local_time` se dividen sólo para su dibujo:
si dos valores válidos consecutivos difieren más de 12 horas, o aparece un
`null`, comienza un tramo nuevo. Los valores originales permanecen intactos para
tooltips, análisis y consultas. Todos los tramos comparten nombre, color y estado
de leyenda; el eje conserva el dominio civil `00:00`–`24:00`.

### Filtro por fases principales

La sección **Fases lunares** permite conservar todos los días o elegir una o
varias claves estables: `new_moon`, `first_quarter`, `full_moon` y
`last_quarter`. El parámetro HTTP opcional usa esas claves separadas por comas:

```text
fases=new_moon,full_moon
```

Las fases proceden exclusivamente de `astronomical_events` en MariaDB, usando
`event_group=moon_phase`, `event_type` y `event_time`. La consulta UTC se amplía
un día a cada lado; cada instante se convierte después a la zona solicitada y
recién entonces se compara su fecha civil con el rango. Si MariaDB no está
disponible, el modo filtrado devuelve un error claro y no cae en todos los días
ni recalcula fases con PHP. El modo diario no abre una conexión a la base.
La cobertura contractual disponible para este filtro es 1900–2050; los rangos
filtrados fuera de esos límites se rechazan en lugar de devolver cobertura
parcial silenciosa.

Con el filtro activo, `SeriesBuilder` recibe directamente esas fechas y sólo
calcula sus variables. Los eventos separados usan por ahora los calculadores
diarios validados; la predicción consecutiva de rangos no se aplica entre fases
distantes. Para diferencias diarias se calcula además el día civil anterior de
cada fecha como auxiliar, sin devolverlo ni compararlo con la fase anterior.
La respuesta agrega `main_phase` y `phase_instant` a cada fila y describe la
selección en `date_selection`.

El evento NDJSON `start` informa `calendar_days`, `selected_dates`, `phases` y
los proveedores activos. Las unidades globales son fechas seleccionadas por
proveedor, no días calendario. Proxies o configuraciones de hosting todavía
pueden introducir buffering pese a las cabeceras del endpoint.

### Análisis de relación

El análisis se ejecuta enteramente en el navegador sobre las filas ya cargadas.
Cambiar A, B o activar **Coincidencia / oposición** y **Refuerzo positivo /
negativo** no genera otra consulta HTTP. Para cada variable se excluyen valores
nulos, vacíos, no numéricos o no finitos y se normaliza independientemente:

```text
N(x) = 2 × (x − mínimo) / (máximo − mínimo) − 1
```

El centro es `(mínimo observado + máximo observado) / 2`, no la media
aritmética. Una serie constante se normaliza a cero. Coincidencia/oposición usa
`N(A) × N(B)`; refuerzo usa `(N(A) + N(B)) / 2`. Ninguno de los dos métodos es
correlación estadística ni mide evolución entre días.

Si A o B es nula en una fecha, el índice también es nulo y la banda conserva un
hueco. Con filtro lunar, mínimos y máximos se obtienen exclusivamente de las
fechas de fase devueltas. Las horas decimales, duraciones y ángulos pueden
participar; la normalización es lineal, por lo que azimutes y otras variables
circulares requieren cautela interpretativa.

Cada método activo agrega debajo del gráfico una banda heatmap sincronizada con
el zoom y el cursor temporales. Las bandas no forman parte de la leyenda y no se
eliminan al ocultar A o B. Al cambiar métodos o variables de relación se
restauran el zoom y la visibilidad previa de las series.

### Extremos locales

El modo `modo=extremos` calcula una única variable sobre una serie diaria
continua y devuelve solamente sus máximos y mínimos locales. Un máximo cumple:

```text
actual > anterior y actual >= siguiente
```

Un mínimo cumple:

```text
actual < anterior y actual <= siguiente
```

La asimetría selecciona de forma determinista el primer punto de una meseta.
Los tres valores deben ser finitos y pertenecer a días civiles consecutivos; un
`null` o un hueco corta la continuidad. Se calcula un día anterior y uno
posterior como auxiliares para clasificar los bordes. Las diferencias diarias
usan dos días anteriores porque el primer auxiliar necesita su propia base.
Ninguna fecha auxiliar se incluye en la respuesta.

Las variables habilitadas por metadatos del catálogo son las dos distancias
lunares, amplitudes lunares y solares, tiempo lunar sobre el horizonte,
duraciones del día y la noche y las cuatro diferencias diarias. Las variables
lunares sugieren un mínimo de 2 años y recomiendan 5; las solares sugieren 5 y
recomiendan 10. Los rangos menores muestran una advertencia, pero no se bloquean.

Extremos locales es incompatible con el filtro de fases tanto en la interfaz
como en backend. El endpoint tradicional y el NDJSON aceptan `variable` y
`tipo_extremo=maximo|minimo|ambos`; la respuesta separada contiene `series`,
`counts`, etiquetas, advertencia y métricas, pero no la serie diaria completa.
El progreso cuenta también los vecinos auxiliares y agrega la etapa
`detecting_extrema` antes de serializar.

Los puntos de distancia denominados apogeos o perigeos diarios son extremos de
la muestra a las 00:00 locales, no el instante físico interpolado del fenómeno.
El gráfico muestra máximos dorados y mínimos azules como series independientes.

El endpoint acepta exclusivamente GET:

```text
/explorador/api/series.php?fecha_desde=2020-01-01&fecha_hasta=2020-01-03&lat=-34.6037&lon=-58.3816&timezone=America%2FArgentina%2FBuenos_Aires&campos=moon_illumination,moon_distance_geocentric
```

La respuesta contiene `columns`, `field_metadata`, `request`, `metrics` y
`rows`. Las métricas separan planificación, astronomía, derivadas, construcción
de filas, serialización, total, memoria y tamaño aproximado.

La interfaz usa preferentemente `series-stream.php`, que entrega eventos NDJSON
`start`, `progress`, `stage` y `complete` a medida que avanza el trabajo real.
El porcentaje astronómico global pondera por días y cantidad de proveedores.
Cada proveedor informa al comenzar, al terminar y, durante el recorrido, cada
25 días como mínimo o cuando transcurren unos 150 ms; en rangos extensos el
paso también se ajusta para evitar más de unas cien actualizaciones por etapa.
Las etapas `derived`, `rows` y `serialization` conservan el último porcentaje
astronómico y se identifican por su nombre.
Los navegadores sin lectura de streams conservan `series.php` como fallback con
un indicador indeterminado. Cancelar aborta la solicitud activa y conserva el
último gráfico completo.

El endpoint desactiva compresión y buffers PHP cuando el entorno lo permite,
usa vaciado explícito y envía cabeceras anti-buffering. La prueba HTTP local
verifica la llegada anticipada; un proxy o hosting que ignore esas cabeceras
todavía podría agrupar la salida, limitación que debe comprobarse al desplegar.

## Variables implementadas

- Luna instantánea: iluminación, ángulo de fase, elongación geocéntrica Sol–Luna,
  diámetro aparente geocéntrico, distancias geocéntrica y topocéntrica, latitud
  y longitud eclípticas, argumento medio de latitud (`Ángulo Luna–nodo`),
  longitud del nodo ascendente medio, ascensión recta, declinación, altura y azimut.
- Sol instantáneo: altura, azimut, distancia Tierra–Sol geocéntrica y ecuación
  del tiempo (tiempo solar aparente menos tiempo solar medio), y Ángulo Sol–nodo.
- Eventos lunares: salida, puesta, sus azimutes y tiempo sobre el horizonte.
- Eventos solares: salida, puesta, sus azimutes, duración del día y de la noche.
- Derivadas: amplitudes de salida y puesta y diferencias diarias de los cuatro
  eventos.

### Ciclos lunares y nodos

La longitud eclíptica lunar es geocéntrica aparente y está referida al
equinoccio verdadero de fecha. Su vuelta de 360° permite observar el mes sidéreo.
El `Ángulo Luna–nodo` usa directamente el argumento medio de latitud `F` del
modelo Meeus: 0° corresponde al nodo ascendente medio y 180° al descendente.
Su vuelta representa el mes dracónico. Por eso ambas series tienen períodos
ligeramente distintos.

El nodo publicado es el nodo ascendente **medio**, referido al equinoccio medio
de fecha; no es un nodo verdadero. `Ángulo Sol–nodo` resta ese nodo medio de la
longitud eclíptica aparente del Sol. Las proximidades a 0° y 180° muestran las
temporadas aproximadas de eclipses, pero no marcan eclipses concretos.

Las cuatro magnitudes son circulares (0°–360°). El gráfico corta la línea en
cada envoltura sin alterar valores ni tooltips. Pueden usarse con filtros de fase
y Análisis de relación, cuya normalización lineal requiere cautela con datos
circulares. No están habilitadas para Extremos locales.

## Pruebas

```bash
docker exec web-astro php /var/www/html/explorador/tests/smoke.php
sh explorador/tests/endpoint-http.sh http://localhost/explorador
sh explorador/tests/stream-http.sh http://localhost/explorador
docker exec web-astro php /var/www/html/explorador/tests/phase-filter.php
sh explorador/tests/phase-http.sh http://localhost/explorador
node explorador/tests/relation-analysis.js # cuando Node esté disponible
node explorador/tests/vertical-axis-control.js # cuando Node esté disponible
node explorador/tests/share-config.js # cuando Node esté disponible
docker exec web-astro php /var/www/html/explorador/tests/extrema.php
sh explorador/tests/extrema-http.sh http://localhost/explorador
docker exec web-astro php /var/www/html/explorador/tests/location-integration.php
```

## Navegación del gráfico

La rueda y el arrastre sin modificadores conservan la navegación horizontal de
ECharts. Con `Shift`, la rueda ajusta el rango vertical alrededor del valor bajo
el cursor y el arrastre desplaza el eje Y activo. El eje se elige por proximidad
visual; si hay uno solo, también queda activo dentro de la grilla principal. Las
bandas del Análisis de relación no participan.

El rango vertical se conserva por grupo de escala durante redibujos locales del
frontend (leyenda y configuración del análisis), y se reinicia con una nueva
consulta o con la herramienta Restaurar. En un arrastre, mover el puntero hacia
abajo desplaza la ventana hacia valores mayores.

## Vista embebible

La superficie compacta se carga con una configuración compartida válida:

```html
<iframe
  src="/astro/explorador/embed.php?v=1&amp;modo=diario&amp;desde=2026-01-01&amp;hasta=2026-12-31&amp;lat=-34.6037&amp;lon=-58.3816&amp;tz=America%2FArgentina%2FBuenos_Aires&amp;campos=moon_ecliptic_longitude%2Cmoon_node_angle&amp;dias=todos"
  title="Comparación de ciclos lunares"
  loading="lazy">
</iframe>
```

El embed usa exactamente el parser v=1, el catálogo, los endpoints de series,
el progreso NDJSON y el render ECharts del Explorador completo. Variables,
ubicación, fases, modo, relación A/B y configuración de extremos quedan fijos.
El usuario sólo puede cambiar fechas, años y rangos temporales rápidos.

`Abrir en el Explorador` reconstruye `/explorador/?v=1&...` con la configuración
efectiva y el último intervalo calculado. `alto` es una opción exclusivamente
visual, limitada a 320–900 px y excluida del estado v=1. `titulo` permite cambiar
el encabezado breve del embed y tampoco forma parte del estado astronómico.

Una URL ausente o inválida muestra un error y no calcula valores predeterminados.
La respuesta declara `X-Frame-Options: SAMEORIGIN` y
`Content-Security-Policy: frame-ancestors 'self'`: por ahora sólo se permite
incluirla en páginas del mismo origen de Aquellas Lunas.

Los administradores autenticados disponen además de **Código para insertar**
junto a la acción pública de compartir. Esta herramienta editorial reutiliza
la configuración v=1 visible, permite elegir altura y título, y entrega el
`iframe` completo con la URL pública de producción. El botón, la modal y el
código no se renderizan para visitantes públicos.

## Decisiones pendientes

- nombre e integración pública;
- política definitiva de rangos;
- disponibilidad local de ECharts;
- streaming o paginación para respuestas grandes;
- validación de experiencia y memoria en navegadores reales.

## Benchmark CLI

El benchmark local de series diarias se conserva como herramienta técnica. No
utiliza la API HTTP, MariaDB, Composer ni la configuración administrativa de la
web.

## Uso

Desde `explorador/benchmarks/`:

```bash
php benchmark-series.php \
  --scenario=moon-instant \
  --from=2020-01-01 \
  --to=2020-12-31 \
  --lat=-34.6037 \
  --lon=-58.3816 \
  --timezone=America/Argentina/Buenos_Aires
```

Los escenarios disponibles son `range-facade`, `daily-full`, `moon-instant`,
`sun-instant`, `moon-events`, `sun-events` y `all`. Sin argumentos se ejecuta
`moon-instant` para el año 2020 en Buenos Aires.

Cada conjunto ejecuta por defecto un calentamiento y tres repeticiones medidas:

```bash
php benchmark-series.php --scenario=moon-instant --preset=10y --repeat=5 --warmup=1
```

Los presets disponibles son `1y` (2020-01-01 a 2020-12-31), `10y`
(2011-01-01 a 2020-12-31), `50y` (1971-01-01 a 2020-12-31) y `150y`
(1871-01-01 a 2020-12-31). Un par explícito `--from` y `--to` tiene prioridad
sobre el preset.

Para conservar también la serie completa:

```bash
php benchmark-series.php --scenario=moon-instant --save-series
```

Para ejecutar las verificaciones rápidas:

```bash
php benchmark-series.php --self-test
```

El rango es inclusivo. Todas las variables instantáneas se calculan a las
`00:00:00` locales. Los archivos de resumen y, opcionalmente, las series se
guardan en `benchmarks/results/`. El progreso se escribe en STDERR y el resumen
final en STDOUT. Se genera un JSON por repetición medida, un JSON agregado y un
CSV con una fila por repetición. Los calentamientos no se guardan.

## Rangos de referencia

`scenarios.php` documenta rangos absolutos de 1, 10, 50 y 150 años. No se
ejecutan automáticamente. `range-facade` conserva el límite contractual de 366
días del motor.
