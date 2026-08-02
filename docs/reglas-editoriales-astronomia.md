# Reglas editoriales de Aquellas Lunas

## Estado y fuentes

La capa editorial está centralizada. La API y PostgreSQL calculan los fenómenos,
intervalos, posiciones y clasificaciones astronómicas. La web interpreta esos
datos mediante catálogos cerrados y MySQL conserva únicamente modificaciones
administrativas.

- `includes/editorial-configuration.php`: defaults, rangos, placeholders y
  acceso efectivo a parámetros y mensajes.
- `includes/event-type-configuration.php`: catálogo de tipos, nombres,
  habilitación, superficies y relevancia para la noche.
- `/admin/presentacion/reglas.php`: reglas, cupos, orden y mensajes.
- `/admin/presentacion/`: nombres y visibilidad de tipos de eventos.

Sin una fila en MySQL se usa el default del catálogo. Si MySQL no está
disponible, el sitio conserva esos mismos defaults. El administrador nunca
ingresa PHP, SQL, JavaScript ni expresiones lógicas.

La página de reglas incluye una búsqueda exclusivamente cliente sobre nombres visibles, valores efectivos, defaults y placeholders. Normaliza mayúsculas y tildes, abre las secciones coincidentes y no consulta MySQL ni usa claves técnicas.

## Responsabilidades

### Datos y cálculos técnicos

Permanecen en la API o en helpers de código:

- cálculo y orden del ciclo lunar;
- posiciones, alturas, direcciones y separaciones;
- salida, puesta y recorte de intervalos;
- clasificación y geometría de eclipses;
- orden cronológico y por magnitud;
- mínimo matemático de nubosidad;
- fechas, zonas horarias, caché, paginado y timeouts;
- formateo numérico, unidades y validación de contratos.

### Reglas editoriales administrables

El catálogo permite modificar, dentro de límites seguros:

- bandas de altura y cercanía a Luna nueva;
- avisos de salida lunar;
- cercanía de conjunciones, libración destacable y superluna;
- Luna nueva fina y tolerancia de observaciones de Luna llena;
- partes del día y oportunidad del cinturón de Venus;
- nubosidad máxima para recomendar el cinturón de Venus;
- bandas y tolerancia temporal de nubosidad;
- ventanas y momentos de la experiencia nocturna;
- ventana y cantidad de eventos de “Lo próximo”;
- cupos y orden de Planetas, Estrellas y Luna entre los destacados.

Las precedencias astronómicas y la selección interna por tiempo o magnitud no
son configurables.

### Mensajes administrables

Son administrables los textos que traducen resultados técnicos a lenguaje
amigable:

- situación y próxima salida de la Luna;
- visibilidad lunar de “El cielo hoy”;
- conjunciones, fases, superluna, perigeo y apogeo;
- luz cenicienta, libraciones y observaciones de Luna llena;
- títulos, etiquetas, visibilidad y alerta geográfica de eclipses;
- estados y ventanas de “El cielo esta noche”;
- proximidad lunar y ayudas de observación;
- recomendaciones de nubosidad;
- cinturón de Venus;
- resúmenes de intervalos en “Sol y Luna”;
- fallbacks astronómicos genéricos.

Cada mensaje declara sus placeholders permitidos y obligatorios. Guardar un
placeholder desconocido, omitir uno obligatorio o exceder la longitud máxima
es rechazado.

## Fases de la Luna

Las cuatro fases exactas toman el nombre amigable del tipo de evento:

- Luna nueva;
- Cuarto creciente;
- Luna llena;
- Cuarto menguante.

Los nombres de las cuatro fases intermedias viven una única vez en el catálogo
editorial. La determinación de qué fase corresponde a cada fecha permanece en
código.

## Tipos y visibilidad de eventos

Cada tipo conocido configura:

- habilitación global;
- nombre amigable;
- superficies públicas;
- relevancia como evento de “El cielo esta noche”.

Los defaults de relevancia reproducen la política histórica: Luna llena,
conjunciones, eclipses, luz cenicienta, observaciones derivadas de Luna llena,
ápsides y libraciones. Los tipos desconocidos se ocultan de forma segura.

## Destacados nocturnos

El máximo general se combina con cupos por categoría y un orden administrable.
Dentro de Planetas y Estrellas se conserva el orden técnico recibido o el orden
por magnitud ya definido. Las categorías se recorren de arriba hacia abajo
hasta completar el máximo general.

## Defaults y restauración

Los controles muestran siempre el valor efectivo. “Predeterminado” significa
que no existe override; “Modificado” significa que MySQL lo reemplaza.
Restaurar un bloque elimina sus overrides y vuelve a mostrar inmediatamente los
defaults del catálogo.

## Lógica deliberadamente fija

No se administra:

- código o expresiones AND/OR;
- cálculos astronómicos;
- taxonomías y contratos técnicos;
- errores de API, carga o conexión;
- botones, formularios, etiquetas técnicas, ARIA y formatos de fecha;
- gráficos, mapas y detalles geométricos.

Esta separación permite editar la voz y el criterio de presentación sin
alterar qué ocurre astronómicamente ni cómo se obtiene el dato.
