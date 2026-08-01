# Administración y Laboratorio Astronómico

## Alcance

Este documento describe la arquitectura actual del área privada de Aquellas Lunas y
del Laboratorio Astronómico. El área administrativa no forma parte de la navegación
pública, no se incluye en el sitemap y comparte una única autenticación. El
laboratorio es una herramienta de consulta: lee la tabla MySQL
`datos_astronomicos`, pero no modifica ni precalcula sus datos.

## Área administrativa

### Presentación del sitio: tipos de eventos

`/admin/presentacion/` administra la primera capa de presentación editorial de los
eventos astronómicos. El catálogo se muestra por familias comprensibles (fases,
órbita, libraciones, conjunciones, eclipses y observaciones derivadas) y permite
editar el nombre amigable, la habilitación global, las superficies públicas en
las que aparece cada tipo y su relevancia para “El cielo esta noche”. Los
identificadores técnicos y el renderer no se
exponen como campos editables.

La configuración vive en MySQL en `admin_tipos_eventos` y
`admin_tipos_eventos_superficies`. La inicialización es idempotente: agrega el
catálogo conocido y sus superficies iniciales, pero nunca pisa cambios
editoriales ya guardados. Las superficies forman un catálogo cerrado: Lo
próximo, Próximas fases, El cielo hoy, El cielo esta noche, Eventos lunares y
Eclipses. La prioridad de las listas continúa siendo cronológica. La selección
resumida nocturna permite administrar cupos y orden de categorías sin exponer
prioridades numéricas ni reordenar efemérides.

La web aplica la habilitación global y por superficie mediante
`includes/event-type-configuration.php`. Si MySQL no está disponible, usa el
catálogo incorporado que reproduce el comportamiento anterior. Un tipo
desconocido no se publica y se registra en el log. La configuración sólo controla
visibilidad y presentación: la API y PostgreSQL siguen siendo la fuente técnica,
y ningún cálculo ni evento astronómico se duplica en MySQL.

La segunda área, `/admin/presentacion/reglas.php`, administra reglas y mensajes
por contexto. Umbral y texto aparecen juntos en cada bloque, con unidades y
límites definidos por el catálogo PHP. Los mensajes admiten únicamente los
placeholders indicados junto al campo y muestran una vista previa con datos
ficticios. Cada bloque puede restaurarse, con confirmación, eliminando sus
overrides para volver a los defaults ejecutables.

La precedencia se muestra en el orden real pero permanece fija: no se ofrece un
constructor AND/OR ni movimiento de condiciones porque los compositores de Luna,
noche y eclipses combinan estados técnicos conocidos. La configuración de
nubosidad llega al navegador como JSON validado y escapado; el resto se consume
por helpers PHP comunes.

### Estructura de `/admin`

| Ruta | Responsabilidad |
|---|---|
| `/admin/` y `/admin/index.php` | Panel general y punto de entrada autenticado. |
| `/admin/login.php` | Formulario y validación de las credenciales existentes. |
| `/admin/logout.php` | Cierre de sesión por `POST` con CSRF. |
| `/admin/fotos.php` | Administración de la galería y tienda. |
| `/admin/laboratorio-astronomico.php` | Interfaz del Laboratorio Astronómico. |
| `/admin/contenidos/` y `/admin/contenidos/index.php` | Editor administrativo de artículos, trivias y bloques “Sabías que...”. |
| `/admin/api/datos-astronomicos.php` | Endpoint JSON privado del laboratorio. |

`admin/index.php` no redirige a una herramienta concreta: presenta tarjetas para
Galería y tienda, Laboratorio Astronómico y Contenidos editoriales. Una nueva herramienta debe agregarse al
panel y a la navegación común sólo si corresponde que sea accesible desde todo el
administrador.

Todas las páginas privadas envían cabeceras `no-store` y
`X-Robots-Tag: noindex, nofollow, noarchive`. Las páginas HTML agregan además la
directiva meta equivalente. No deben agregarse enlaces desde menús públicos,
listados públicos ni `sitemap.xml`.

### Autenticación y flujo de login

La implementación histórica se conserva en `includes/store-admin-auth.php`; sus
nombres `storeAdmin*` y `store_admin_*` no implican una autenticación exclusiva de
la tienda.

- Nombre de sesión PHP: `aquellas_lunas_admin`.
- Indicador autenticado: `$_SESSION['store_admin_authenticated']`, expuesto como
  `STORE_ADMIN_SESSION_KEY`.
- Credenciales: `STORE_ADMIN_USER` y `STORE_ADMIN_PASSWORD_HASH`, o sus claves
  equivalentes en la configuración externa de producción.
- Usuario: comparación constante mediante `hash_equals()`.
- Contraseña: `password_verify()` contra el hash configurado.
- Cookie: ruta `/`, `HttpOnly`, `SameSite=Lax`, `Secure` bajo HTTPS y duración de
  sesión.
- Login correcto: regenera el ID de sesión, establece el indicador autenticado y
  redirige con HTTP 303 a `admin/index.php`.
- Usuario autenticado que abre el login: vuelve al panel.
- Acceso HTML no autorizado: `requireStoreAdminAuthentication()` redirige a
  `login.php`.
- Acceso al endpoint sin sesión: responde JSON con HTTP 401; no redirige ni entrega
  HTML.
- Logout: sólo `POST`, valida CSRF, vacía y destruye sesión y cookie.

El sistema actual no conserva una URL de retorno arbitraria después del login. No
debe incorporarse otro usuario, otra cookie o un middleware paralelo para una nueva
herramienta.

### Navegación y componentes compartidos

`includes/store-admin-navigation.php` genera el encabezado común mediante
`renderStoreAdminNavigation($activeSection, $title)`. Incluye Inicio, Contenidos,
Galería, Laboratorio y el formulario seguro para cerrar sesión; aplica
`aria-current="page"` y la clase activa correspondiente.

La navegación resuelve enlaces válidos en todo `/admin` a partir de la ruta actual
del script, en lugar de depender de concatenaciones manuales de prefijos relativos.
La ruta canónica del editor es `/admin/contenidos/`.

El componente es adaptable a pantallas angostas y evita duplicar el HTML y el token
CSRF.

Los componentes principales que debe reutilizar una herramienta administrativa son:

- `includes/store-admin-auth.php`: sesión, cabeceras, autenticación, CSRF y logout.
- `includes/store-admin-navigation.php`: encabezado y navegación.
- `includes/api-config.php`: lectura validada de `.env` y configuración externa.
- `includes/web-database.php`: conexión PDO a `WEB_DB`.
- `assets/css/styles.css`: sistema visual oscuro y controles administrativos.

Una página nueva debe iniciar la sesión, exigir autenticación antes de emitir
contenido, enviar las cabeceras privadas, usar la navegación común, escapar toda
salida HTML y emplear botones y controles estilizados. Un endpoint nuevo debe
responder errores JSON genéricos, desactivar `display_errors`, registrar únicamente
el tipo técnico necesario y no exponer rutas, DSN ni credenciales.

### Módulos administrativos actuales

- Fotos (`/admin/fotos.php`): gestión de disponibilidad, precio y metadatos editoriales
  de galería/tienda.
- Laboratorio astronómico (`/admin/laboratorio-astronomico.php`): análisis de series
  y extremos sobre `datos_astronomicos` en modo sólo consulta.
- Contenidos (`/admin/contenidos/`): edición estructurada de artículos editoriales en
  MySQL con validación y persistencia transaccional.

## Editor de contenidos

### Fuente de datos y alcance

El editor administrativo de contenidos usa MySQL como única fuente y como destino
de escritura.

La lectura y escritura del módulo se realizan sobre tablas de contenido mediante
`getWebDatabaseConnection()`; no se escriben archivos PHP durante el guardado
administrativo.

### Flujo de guardado

El guardado se ejecuta en una única transacción que cubre:

- artículo base (`contenido_articulos`),
- palabras clave (`contenido_articulos_palabras_clave`),
- relaciones (`contenido_articulos_relaciones`),
- trivias (`contenido_trivias`),
- opciones de trivia (`contenido_trivia_opciones`),
- bloques “Sabías que...” (`contenido_sabias_que`).

Ante cualquier falla, la operación revierte completa (`ROLLBACK`) y no persiste un
estado parcial.

### Validaciones implementadas

El editor valida, antes de escribir:

- slug con formato válido y unicidad en base,
- versión numérica válida (>= 1),
- campos obligatorios (`titulo`, `resumen`, `articulo`),
- listas de `palabras_clave` y `relaciones`,
- `trivias` y `sabias_que` como colecciones listadas,
- códigos únicos dentro de cada colección,
- imágenes pertenecientes a la galería permitida,
- exactamente una opción correcta por trivia,
- coherencia entre referencias `[[trivia id="..."]]` en Markdown y trivias reales,
- codificación UTF-8 válida,
- consistencia de orden persistido para colecciones y opciones,
- advertencias de registros huérfanos en tablas relacionadas.

### Relaciones entre artículos, trivias y “Sabías que...”

Cada artículo (`contenido_articulos`) puede tener múltiples:

- trivias (`contenido_trivias`),
- bloques “Sabías que...” (`contenido_sabias_que`),
- palabras clave y relaciones internas por slug.

Cada trivia puede tener múltiples opciones (`contenido_trivia_opciones`) y una sola
opción correcta representada por `correcta = 1` con explicación asociada.

### Importador histórico de migración

La migración inicial desde archivos PHP quedó documentada en
`scripts/migrations/import-content-to-web-db.php`.

Ese script se conserva sólo como historial técnico de la carga inicial:

- valida candidatos desde una carpeta de entrada explícita en tiempo de ejecución,
- inserta en tablas MySQL de contenido,
- soporta `--dry-run` para simulación,
- no sobrescribe artículos existentes por slug.

### Política actual de sobrescritura

La política actual del importador es conservadora: no sobrescribe contenido ya
existente en MySQL y omite el slug duplicado. La actualización de contenido existente
se realiza desde el editor administrativo autenticado.

## Base de datos de contenidos

Las tablas editoriales activas son:

- `contenido_articulos`: tabla raíz por slug, versión, visibilidad, título, resumen,
  markdown e imagen principal.
- `contenido_articulos_palabras_clave`: lista ordenada de palabras clave por artículo.
- `contenido_articulos_relaciones`: lista ordenada de slugs relacionados por artículo.
- `contenido_trivias`: trivias asociadas a un artículo con orden, visibilidad,
  pregunta e imagen opcional.
- `contenido_trivia_opciones`: opciones de cada trivia con orden, bandera de correcta
  y explicación.
- `contenido_sabias_que`: bloques “Sabías que...” asociados a artículo con orden,
  visibilidad, frase y detalle.

Relaciones lógicas:

- `contenido_articulos` 1:N `contenido_trivias`.
- `contenido_trivias` 1:N `contenido_trivia_opciones`.
- `contenido_articulos` 1:N `contenido_sabias_que`.
- `contenido_articulos` 1:N `contenido_articulos_palabras_clave`.
- `contenido_articulos` 1:N `contenido_articulos_relaciones`.

## Modo Administrador

Decisión de arquitectura:

Cuando un usuario tenga sesión administrativa válida, el sitio público podrá habilitar
herramientas adicionales sólo para ese usuario, sin depender de `APP_ENV=local`.

Ejemplos previstos:

- enlaces “Editar artículo”,
- accesos rápidos al editor,
- funciones de depuración,
- herramientas de mantenimiento,
- información técnica no visible para visitantes normales.

Esta línea reemplaza progresivamente utilidades históricas exclusivas de entorno local.

## Entorno local y sesiones

Durante la migración del editor se detectó un conflicto entre la sesión administrativa
(`aquellas_lunas_admin`) y la sesión usada por la simulación temporal local
(`aquellas_lunas_local`).

Causa:

- incluir componentes que cargan `includes/current-datetime.php` antes de iniciar la
  sesión administrativa podía activar la sesión local primero.

Efecto:

- la autenticación administrativa no encontraba `STORE_ADMIN_SESSION_KEY` en la
  sesión activa y trataba al usuario como no autenticado.

Solución aplicada:

- en módulos admin, ejecutar siempre primero
  `startStoreAdminSession()` y `requireStoreAdminAuthentication()`;
- cargar después componentes que puedan iniciar otras sesiones en entorno local.

Regla permanente:

"Ningún módulo administrativo debe incluir componentes que puedan iniciar otra sesión
antes de ejecutar `startStoreAdminSession()` y `requireStoreAdminAuthentication()`."

## Pendientes

Próximas líneas de trabajo definidas:

- habilitación pública progresiva de contenidos desde la capa editorial MySQL,
- integración de configuración del sitio desde `admin_configuracion_sitio`,
- editor administrativo para configuración del sitio,
- importación masiva de contenido mediante texto estructurado,
- mejoras del flujo editorial ya unificado en MySQL,
- automatización de cargas masivas en formato estructurado.

## Laboratorio Astronómico

### Objetivo y arquitectura

El laboratorio permite explorar series históricas de la tabla
`datos_astronomicos` sin editarla. La página PHP entrega el formulario, el catálogo
de controles y la configuración inicial; JavaScript consulta bajo demanda el
endpoint privado y construye el gráfico.

```text
admin/laboratorio-astronomico.php
              |
              v
assets/js/astronomy-laboratory.js
              |
        GET con sesión
              v
admin/api/datos-astronomicos.php
              |
     includes/web-database.php
              |
      WEB_DB / datos_astronomicos
              |
          JSON validado
              v
       Apache ECharts 6.1
```

La conexión `WEB_DB` usa `WEB_DB_HOST`, `WEB_DB_PORT`, `WEB_DB_NAME`,
`WEB_DB_USER` y `WEB_DB_PASSWORD` —o las claves externas `web_db_*`— mediante PDO,
`utf8mb4`, excepciones, resultados asociativos y consultas preparadas. Esta ruta no
consume la API FastAPI de astronomía.

Responsabilidades:

- `includes/astronomy-laboratory.php`: catálogo central de campos, expresiones SQL,
  tipos, unidades, escalas, agrupación visual, fases permitidas y helpers derivados.
- `includes/astronomy-laboratory-extrema.php`: catálogo reducido y consulta del
  modo Extremos locales.
- `admin/api/datos-astronomicos.php`: autenticación, validación, composición de la
  consulta y contrato JSON.
- `admin/laboratorio-astronomico.php`: interfaz accesible, fechas, variables y
  configuración serializada para JavaScript.
- `assets/js/astronomy-laboratory.js`: estado del formulario, rangos rápidos,
  ECharts, ejes, leyendas, tooltips, zoom y mensajes.
- `assets/css/styles.css`: presentación de ancho amplio, controles, paneles y
  adaptación móvil.

### Contrato del endpoint

El endpoint acepta únicamente `GET` autenticado.

Parámetros comunes:

- `modo`: `diario` por defecto o `extremos`.
- `fecha_desde` y `fecha_hasta`: fechas reales `AAAA-MM-DD`, con fecha desde no
  posterior a fecha hasta. La interfaz ofrece el intervalo disponible 1900–2100.

Serie diaria:

- `campos`: lista separada por comas, validada contra el catálogo central.
- `fases`: lista opcional separada por comas; sólo admite Luna nueva, Cuarto
  creciente, Luna llena y Cuarto menguante.

Extremos:

- `variable`: una entrada del catálogo específico de extremos.
- `tipo_extremo`: `maximo`, `minimo` o `ambos`.

No existe un máximo general de diez años. Los rangos rápidos retrospectivos restan
años a la fecha final; los futuros suman años a la fecha inicial. Ambos ajustan el
29 de febrero y respetan 1900–2100.

Los campos SQL nunca se toman directamente del request. El nombre solicitado debe
existir en la lista blanca y la consulta usa sólo la expresión controlada del
catálogo. Fechas, fases y umbrales se enlazan como parámetros preparados.

Los errores de validación usan HTTP 400; autenticación, 401; método, 405; fallo de
consulta o infraestructura, 503. La respuesta pública nunca incluye la excepción,
rutas internas, usuario, contraseña o DSN.

#### JSON de Serie diaria

```json
{
  "modo": "diario",
  "columns": ["fecha", "distancia_luna_km", "iluminacion_porc"],
  "field_types": {
    "fecha": "date",
    "distancia_luna_km": "number",
    "iluminacion_porc": "number"
  },
  "field_units": {
    "distancia_luna_km": " km",
    "iluminacion_porc": "%"
  },
  "field_scales": {
    "distancia_luna_km": "distance_km",
    "iluminacion_porc": "percentage"
  },
  "scale_groups": {},
  "available_years": {"min": 1900, "max": 2100},
  "rows": [
    {
      "fecha": "2026-01-01",
      "distancia_luna_km": 363124.0,
      "iluminacion_porc": 93.2
    }
  ]
}
```

`scale_groups` contiene la definición completa de cada eje. Si se solicitaron
marcadores de superluna o miniluna, la respuesta agrega `event_thresholds` y cada
fila marcada puede incluir `_event_marker_details`.

#### JSON de Extremos locales

```json
{
  "modo": "extremos",
  "variable": "distancia_luna_km",
  "variable_label": "Distancia de la Luna (km)",
  "field_type": "number",
  "scale_group": "distance_km",
  "unidad": "km",
  "desde": "2020-01-01",
  "hasta": "2025-12-31",
  "tipo_extremo": "ambos",
  "series_labels": {
    "maximos": "Apogeos",
    "minimos": "Perigeos"
  },
  "series": {
    "maximos": [{"fecha": "2020-01-02", "valor": 404579.0}],
    "minimos": [{"fecha": "2020-01-13", "valor": 365958.0}]
  },
  "conteos": {"maximos": 1, "minimos": 1},
  "rango": {},
  "advertencia": null
}
```

### Catálogo central de variables

Cada entrada de `astronomyLaboratoryFieldDefinitions()` usa:

| Clave | Función |
|---|---|
| `label` | Nombre completo en leyenda y tooltip. |
| `short_label` | Nombre compacto del selector. |
| `type` | `number`, `time_fraction`, `time_duration` o `event_marker`. |
| `scale_group` | Grupo de unidad/escala; `null` para marcadores. |
| `unit` | Sufijo visual opcional. |
| `body` | Columna visual `moon` o `sun`. |
| `group` | Sección del selector: horarios, diferencias, amplitudes, azimutes u otras. |
| `sql` | Columna o expresión SQL controlada. |
| `event` | Estrategia especial para un marcador, en lugar de `sql`. |

Variables físicas, leídas directamente de columnas:

- Horas: `hora_salida_luna`, `hora_puesta_luna`, `hora_salida_sol`,
  `hora_puesta_sol`.
- Diferencias: `diferencia_salida_luna_min`, `diferencia_puesta_luna_min`,
  `diferencia_salida_sol_min`, `diferencia_puesta_sol_min`.
- Azimutes: `azimut_salida_luna`, `azimut_puesta_luna`,
  `azimut_salida_sol`, `azimut_puesta_sol`.
- Otras: `distancia_luna_km`, `distancia_sol_km`, `iluminacion_porc`,
  `dia_ciclo_lunar`, `latitud_ecliptica_luna`, `fraccion_anio_tropico` y
  `angulo_nodo_sol`.

Variables derivadas durante la consulta:

- Amplitudes de salida y puesta lunares y solares: azimut menos 90° o 270°.
- `duracion_dia` y `duracion_noche`: diferencias modulares de los horarios
  solares, expresadas como fracción de día.
- `tiempo_luna_sobre_horizonte`: construye timestamps completos; para cada salida
  usa la primera puesta posterior del mismo día o del siguiente. Rechaza ciclos
  incompletos o mayores de 24 horas y conserva `NULL`.
- `superluna_llena` y `miniluna_llena`: marcadores calculados sobre las lunas
  llenas usando los percentiles 10 y 90 de las distancias de 1900–2100.

`hora_fase_lunar` no forma parte del catálogo seleccionable.

### Grupos de ejes, unidades y escalas

No se agrupan todos los números en un mismo eje. Cada serie consume el eje de su
`scale_group`; varias variables del mismo grupo comparten eje. Se permiten como
máximo cuatro grupos simultáneos, sin limitar la cantidad de series dentro de esos
grupos.

| Grupo | Variables típicas | Eje |
|---|---|---|
| `distance_km` | Distancias lunar y solar | Distancia (km), automática |
| `percentage` | Iluminación | Porcentaje (%), datos 0–100 |
| `time_fraction` | Horas de salida y puesta | Hora, 0–1, etiquetas `HH:MM` |
| `time_duration` | Duraciones y permanencia lunar | Duración, 0–1, etiquetas `HH:MM` |
| `angle_degrees` | Azimutes, amplitudes y ángulos | Ángulo (°), automática |
| `difference_minutes` | Diferencias diarias | Diferencia (min), automática |
| `cycle_days` | Día del ciclo lunar | Días, automática |
| `fraction` | Fracción del año trópico | Fracción, datos 0–1 |

Los ejes se crean sólo para los grupos seleccionados y alternan izquierda, derecha,
izquierda con offset y derecha con offset. `grid.left` y `grid.right` crecen según
la cantidad de ejes. Los marcadores de eventos no crean un eje propio cuando ya
existe una escala física.

Los valores numéricos de ejes y tooltips usan formato `es-AR`, separador de miles y
exactamente dos decimales. Las horas y duraciones se mantienen en `HH:MM`; el
redondeo a minutos normaliza el acarreo y evita valores como `18:60`. Los `NULL` se
presentan como “Sin dato” en contextos textuales.

La paleta diaria se asigna en orden:
`#8eb4ff`, `#f2d486`, `#89d6c6`, `#d9a5ff`, `#ff9f9f`, `#9ccf75` y
`#f7b267`. Extremos usa dorado `#f2d486` para máximos y azul `#8eb4ff` para
mínimos. La leyenda es interactiva y desplazable.

El gráfico permite zoom con rueda, desplazamiento, selección rectangular, control
inferior y restauración. No hay actualmente exportación a CSV, JSON, PNG o SVG; el
toolbox expone sólo zoom y restauración.

### Modo Serie diaria

Serie diaria admite selección múltiple de variables y filtro múltiple de fases.
Consulta una fila por fecha, ordenada ascendentemente. Los valores `TIME` de MySQL
se convierten en el endpoint a una fracción entre 0 y 1; los números se entregan
como `float`.

Las expresiones derivadas se calculan en la misma consulta para que el catálogo sea
la única definición funcional. No se duplican fórmulas en JavaScript. En particular,
la permanencia lunar no resta automáticamente salida y puesta de la misma fila:
empareja datetimes cronológicos y puede usar la puesta siguiente.

Un `NULL` permanece `null` en JSON, no se transforma en cero y ECharts no dibuja
ese punto. `connectNulls: false` corta la línea; no se calculan duraciones parciales.
Para series extensas se usa muestreo LTTB, se ocultan símbolos a partir de 100 filas
y se desactiva la animación desde 1500 filas. La resolución fuente sigue siendo
diaria.

### Modo Extremos locales

Este modo busca cambios de tendencia locales en una sola variable compatible. La
consulta usa CTE:

1. `calculada`: obtiene fecha y valor físico o derivado.
2. `base`: excluye `NULL`.
3. `serie`: obtiene fechas y valores anterior y siguiente mediante `LAG()` y
   `LEAD()`.
4. `clasificada`: etiqueta máximos y mínimos.

Un máximo cumple `valor > anterior` y `valor >= siguiente`. Un mínimo cumple
`valor < anterior` y `valor <= siguiente`. Además, ambos vecinos deben estar
exactamente a un día; así un hueco por `NULL` o una fecha ausente no convierte
puntos separados por varios días en vecinos.

Variables compatibles:

| Variable | Mínimo | Recomendado |
|---|---:|---:|
| Distancia lunar | 2 años | 5 años |
| Amplitud de salida lunar | 2 años | 5 años |
| Amplitud de puesta lunar | 2 años | 5 años |
| Tiempo lunar sobre el horizonte | 2 años | 5 años |
| Diferencia diaria de salida lunar | 2 años | 5 años |
| Duración del día | 5 años | 10 años |
| Amplitud de salida solar | 5 años | 10 años |
| Amplitud de puesta solar | 5 años | 10 años |

El endpoint puede devolver máximos, mínimos o ambas series. Distancia lunar usa los
nombres astronómicos Apogeos y Perigeos; las demás variables usan etiquetas
descriptivas. Cada serie tiene color, leyenda y conteo independientes.

Los extremos son extremos de la muestra diaria, no el instante físico interpolado
del fenómeno. No se suaviza la curva ni se infieren puntos entre fechas. Se calculan
bajo demanda porque el volumen 1900–2100 es manejable, las expresiones continúan
centralizadas y no se justifica todavía una tabla materializada que deba
sincronizarse con los datos fuente.

Como referencia local sobre MariaDB 10.11, la distancia lunar tardó alrededor de
68 ms para 5 años, 69 ms para 10, 85 ms para 20 y 168 ms para 1900–2100; una
derivada solar de 10 años rondó 72 ms. Son mediciones orientativas, no límites
contractuales.

## Extensibilidad

### Agregar una variable diaria física

1. Confirmar el nombre, tipo y semántica reales en MySQL.
2. Agregar una entrada a `astronomyLaboratoryFieldDefinitions()` con una columna
   SQL fija, tipo, unidad, escala, cuerpo y grupo.
3. Agregar un grupo de escala sólo si ninguna unidad existente es compatible.
4. Verificar selector, metadatos JSON, eje, tooltip, `NULL` y consulta HTTP.
5. Agregar pruebas PHP, JavaScript y HTTP.

No es necesario modificar manualmente la lista blanca: se deriva del catálogo.

### Agregar una variable derivada

1. Implementar la expresión controlada en `includes/astronomy-laboratory.php`;
   usar un helper cuando la fórmula sea compleja.
2. Conservar `NULL` si faltan entradas; no usar cero como reemplazo.
3. Definir límites físicos o temporales para descartar combinaciones inválidas.
4. Mantener alias de tabla compatibles con `d` y usar consultas acotadas e
   indexables si se requieren filas vecinas.
5. Completar los mismos metadatos y pruebas que una variable física.

La fórmula no debe recibirse desde el cliente ni reimplementarse en ECharts.

### Habilitar una serie de extremos

1. La variable debe existir y tener una expresión `sql` en el catálogo diario.
2. Agregarla a `astronomyLaboratoryExtremaVariables()` con `analysis_label`,
   `minimum_years`, `recommended_years` y, si corresponde, nombres específicos para
   máximos y mínimos.
3. Confirmar que la magnitud no sea circular y que la resolución diaria permita una
   interpretación útil.
4. Probar máximo, mínimo, ambos, rangos cortos, `NULL` y huecos de fechas.

### Agregar un grupo de ejes

1. Incorporarlo en `astronomyLaboratoryScaleGroups()` con etiqueta, límites,
   intervalo y formatter cuando correspondan.
2. Asignar el `scale_group` a las variables compatibles.
3. Actualizar el formatter JavaScript sólo si la unidad necesita una representación
   distinta de número con dos decimales o `HH:MM`.
4. Probar índices, offsets y márgenes con uno a cuatro grupos.

No debe aumentarse el límite de cuatro grupos sin revisar legibilidad y espacio
lateral.

### Agregar un modo de análisis

1. Definir un valor controlado nuevo para `modo` y un contrato JSON propio.
2. Crear un catálogo de variables permitido si no coincide con los actuales.
3. Separar cálculo/consulta en un include, manteniendo el endpoint como
   controlador.
4. Agregar controles dentro del formulario existente y una función de render
   específica en JavaScript.
5. Reutilizar autenticación, fechas, conexión, manejo de errores, metadatos y
   estilos.
6. Documentar resolución, supuestos, rendimiento y limitaciones antes de habilitarlo.

## Validación y pruebas

La cobertura relevante está en:

- `tests/astronomy-laboratory.php`: catálogo, escalas, horarios y expresiones
  derivadas.
- `tests/astronomy-laboratory-extrema.php`: catálogo, rangos, consultas y exclusión
  de valores inválidos.
- `tests/astronomy-laboratory.js`: formatters, fechas, ejes, series y tooltips.
- `tests/store-admin-http.sh`: autenticación, HTML, cabeceras y contrato HTTP/JSON.

Ejecución habitual:

```bash
docker compose exec -T web php tests/astronomy-laboratory.php
docker compose exec -T web php tests/astronomy-laboratory-extrema.php
docker compose exec -T web bash tests/store-admin-http.sh http://localhost
```

## Pendientes del laboratorio

- Evaluar una unión visual opcional de huecos pequeños por `NULL`, con un límite
  explícito de días y sin alterar los datos ni el cálculo de extremos.
- Definir reglas diferentes de conexión de segmentos para variables físicas,
  duraciones y marcadores.
- Comparar dos variables de extremos y resolver escalas, sincronización temporal y
  semántica de máximos contra mínimos.
- Considerar precálculo o materialización sólo si crecen el volumen, la concurrencia
  o el costo medido; definir entonces invalidación y trazabilidad.
- Diseñar exportación explícita de datos filtrados y, por separado, exportación de
  la imagen del gráfico.
- Incorporar diagnósticos opcionales de ciclos lunares emparejados sin exponer SQL
  ni convertirlos en una interfaz de edición.
- Evaluar una URL de retorno segura tras el login si el administrador incorpora más
  puntos de entrada profundos.
- Documentar objetivos de rendimiento y presupuesto de puntos si el uso deja de ser
  individual o la tabla supera el intervalo 1900–2100.
