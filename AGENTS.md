# AGENTS.md — Reglas permanentes del proyecto Aquellas Lunas

Este archivo contiene reglas que deben respetarse en cualquier modificación del proyecto web **Aquellas Lunas**.

Antes de realizar cambios, revisar estas reglas y la implementación existente.

Si una tarea introduce una nueva decisión permanente de arquitectura, compatibilidad, diseño o desarrollo, actualizar este archivo cuando corresponda.

---

## 1. Regla general: entender antes de modificar

Antes de cambiar código:

1. Revisar la implementación actual relacionada con la tarea.
2. Reutilizar componentes, funciones, estilos y patrones existentes siempre que sea razonable.
3. No asumir que una funcionalidad no existe sin buscarla primero.
4. No realizar refactors, rediseños ni modificaciones colaterales que no hayan sido solicitados.
5. Preservar el comportamiento existente salvo que la tarea indique expresamente lo contrario.

El objetivo es hacer cambios pequeños, controlados y compatibles con la arquitectura existente.

---

## 2. Entornos

El proyecto tiene diferencias importantes entre desarrollo local y producción.

### Desarrollo local

El proyecto corre en una Mini PC con Ubuntu Server utilizando Docker.

La aplicación web PHP corre dentro del contenedor:

    web-astro

PHP NO está instalado directamente en el host.

Por lo tanto, no intentar ejecutar desde el host:

    php archivo.php
    php -l archivo.php

Las comprobaciones PHP deben ejecutarse dentro del contenedor.

Ejemplos:

    docker exec web-astro php -l /var/www/html/contenidos.php

    docker exec web-astro php /var/www/html/tests/content-search.php

Las rutas exactas deben verificarse según los volúmenes y estructura actual del contenedor.

### Producción

La web se ejecuta en un hosting cPanel/Apache con PHP.

La URL pública utiliza:

    /astro/

No asumir que las extensiones PHP disponibles en Docker también existen en producción.

**Producción es el entorno que determina la compatibilidad final.**

---

## 3. Compatibilidad PHP

Esta regla es especialmente importante.

El contenedor local dispone de extensiones PHP que pueden no existir en el hosting.

### mbstring

No utilizar funciones `mb_*` en código destinado a producción salvo que se haya comprobado expresamente que la extensión está disponible en el hosting.

Ejemplos a evitar:

    mb_strlen()
    mb_substr()
    mb_strtolower()
    mb_stripos()

Ya ocurrió que código funcionando correctamente en Docker produjo un error fatal en producción porque `mbstring` no estaba disponible.

Preferir soluciones basadas en PHP estándar cuando sean suficientes.

### Otras extensiones

No introducir dependencias nuevas de extensiones como:

    intl
    mbstring
    imagick

u otras extensiones opcionales sin comprobar primero su disponibilidad en producción.

Si una funcionalidad puede resolverse razonablemente con PHP base, preferir esa solución.

---

## 4. Pruebas PHP y compatibilidad con producción

Cuando sea pertinente, además de las pruebas normales ejecutar pruebas PHP sin cargar extensiones configuradas:

    docker exec web-astro php -n /var/www/html/tests/archivo.php

Esto permite descubrir dependencias accidentales de extensiones disponibles localmente pero ausentes en producción.

No utilizar `php -n` indiscriminadamente si una prueba depende legítimamente de una extensión requerida por el proyecto.

La prueba debe utilizarse especialmente para código que debería funcionar exclusivamente con PHP base.

---

## 5. Arquitectura web

Respetar la arquitectura existente.

Cuando una funcionalidad pública necesita información de la API astronómica, el navegador no debe comunicarse directamente con FastAPI salvo que la arquitectura existente de esa funcionalidad indique expresamente lo contrario.

El patrón normal es:

    Navegador
        ↓
    Web PHP
        ↓
    API FastAPI
        ↓
    Web PHP
        ↓
    Navegador

No exponer innecesariamente endpoints internos, configuración, credenciales ni detalles de infraestructura al navegador.

---

## 6. Diseño visual

Aquellas Lunas utiliza una interfaz oscura, sobria y coherente.

Todo elemento nuevo debe integrarse con el sistema visual existente.

### Controles interactivos

Nunca dejar:

- enlaces azules subrayados por defecto;
- botones grises nativos;
- inputs con aspecto nativo sin integrar;
- selects sin estilizar;
- controles que parezcan ajenos al resto del sitio.

Todo enlace, botón, buscador, selector o control interactivo nuevo debe reutilizar o extender los estilos existentes.

Contemplar cuando corresponda:

- estado normal;
- hover;
- focus;
- active;
- visited;
- disabled.

Mantener accesibilidad mediante foco visible y semántica apropiada.

---

## 7. Responsive

Todos los cambios visuales deben comprobarse al menos conceptualmente en:

- escritorio;
- pantallas angostas;
- móvil.

No considerar terminado un cambio visual únicamente porque funciona correctamente en escritorio.

Evitar aumentar innecesariamente la altura de tarjetas o introducir espacios vacíos cuando la composición actual permita aprovechar el ancho disponible.

---

## 8. Reutilización

Antes de crear:

- una nueva clase CSS;
- un componente;
- un helper PHP;
- una función JavaScript;
- una tarjeta;
- un botón;
- un sistema de mensajes;

buscar si ya existe un patrón equivalente.

Evitar crear variantes casi idénticas de componentes existentes.

Si hace falta una variante, preferir extender el componente existente de forma clara.

---

## 9. Contenidos públicos

Los contenidos públicos se gestionan actualmente desde base de datos y desde la administración del sitio.

Los artículos pueden contener:

- título;
- resumen;
- Markdown;
- palabras clave;
- relaciones;
- trivias;
- bloques "¿Sabías que...?";
- imagen principal opcional.

No asumir estructuras antiguas basadas en archivos PHP si la implementación actual ya fue migrada a base de datos.

Antes de modificar el sistema de contenidos, revisar el esquema y repositorios actuales.

El sitemap público conserva la URL `/astro/sitemap.xml`, resuelta por Apache
hacia `sitemap.php`. Las entradas de artículos se generan desde el catálogo
MySQL validado: sólo se publican artículos válidos y visibles, con la misma
canonical individual usada por `contenido.php`. No volver a mantener una lista
estática de artículos en XML.

Las relaciones de `contenido_articulos_relaciones` son editoriales, dirigidas
y ordenadas desde el artículo origen hacia un `slug_relacionado`. La vista
pública sólo convierte en enlaces los valores que coinciden con otro artículo
válido y visible; no debe inferir la relación inversa ni generar relaciones a
partir de palabras clave.

El slug de un artículo que ya fue publicado se considera una URL pública
estable y no debe modificarse normalmente. El editor conserva por ahora la
edición técnica del campo con una advertencia explícita; no existe todavía
historial de slugs ni redirección automática desde valores anteriores.

Los embeds en artículos entran exclusivamente mediante la directiva controlada
`[[embed url="..."]]`; nunca habilitan HTML o iframes escritos por el editor.
La política central de proveedores sólo admite inicialmente URLs HTTPS con host
exacto `chichipiosblog.com.ar` y path bajo `/astronomia/`. El renderer controla
todos los atributos técnicos. Los embeds inválidos se omiten públicamente y se
informan como advertencias editoriales sin invalidar el resto del artículo.

---

## 10. Texto y controles fuera de tarjetas

Mantener el criterio visual existente del sitio.

Evitar introducir textos, avisos o controles sueltos fuera de los contenedores y tarjetas que estructuran cada página.

Cuando sea necesario mostrar:

- ausencia de resultados;
- errores amigables;
- información complementaria;
- estados vacíos;

integrarlos visualmente en los componentes existentes.

---

## 11. Ubicación, fecha y hora

El sitio posee un sistema centralizado para ubicación y contexto temporal.

No implementar mecanismos paralelos para obtener, guardar o mostrar ubicación, fecha u hora sin revisar primero ese sistema.

La fecha y la ubicación general del usuario deben mantenerse centralizadas en el encabezado cuando corresponda, evitando repeticiones innecesarias en el cuerpo de las páginas.

La ubicación persistida es un bloque atómico de latitud, longitud, elevación y
zona horaria. La zona IANA se resuelve siempre en el servidor desde coordenadas
mediante `includes/timezone-resolver.php`; no aceptar como autoridad el timezone
del navegador ni conservar el de una ubicación anterior. Las cookies heredadas
se validan y migran al construir `astronomyLocationContext()`.

En entorno local existe además simulación temporal para pruebas.

No romper ni ignorar ese contexto al desarrollar funcionalidades dependientes de fecha u hora.

---

## 12. Configuración

La web busca centralizar progresivamente parámetros y comportamiento configurable desde administración.

Antes de hardcodear:

- umbrales;
- textos operativos;
- habilitación de secciones;
- parámetros que probablemente cambien;
- reglas de mensajes;

comprobar si ya existe una configuración administrativa o un mecanismo previsto para almacenarlos.

No convertir automáticamente todo valor en configuración: hacerlo cuando tenga sentido operativo.

---

## 13. JavaScript

No agregar JavaScript si la funcionalidad puede resolverse de forma sencilla con la arquitectura existente y sin deteriorar la experiencia.

Cuando se utilice JavaScript:

- evitar dependencias nuevas innecesarias;
- reutilizar scripts y patrones existentes;
- mantener funcionamiento razonable ante errores;
- no duplicar lógica que ya resuelve PHP.

---

## 14. Base de datos

Antes de modificar tablas o agregar estructuras nuevas:

1. revisar el esquema existente;
2. comprobar si la información ya está almacenada;
3. evitar duplicar datos;
4. evitar migraciones innecesarias;
5. considerar compatibilidad con los datos existentes.

No crear tablas nuevas simplemente para resolver funcionalidades que puedan implementarse razonablemente con la estructura actual.

---

## 15. Seguridad y producción

Nunca:

- exponer credenciales;
- escribir secretos en código;
- registrar contraseñas o tokens;
- agregar secretos al repositorio;
- asumir que valores de `.env` pueden enviarse al navegador.

Utilizar los mecanismos de configuración existentes.

No modificar `.env` de producción salvo que la tarea lo requiera expresamente y el cambio haya sido indicado.

---

## 16. Cambios mínimos

Una tarea debe modificar solamente lo necesario.

Si durante el trabajo se descubre otro problema que no bloquea la tarea:

- informarlo;
- no corregirlo automáticamente salvo que sea trivial, seguro y claramente parte del mismo problema.

Evitar el patrón de "ya que estamos".

---

## 17. Al finalizar una tarea

Informar de manera concreta:

1. qué se modificó;
2. archivos modificados;
3. decisiones técnicas relevantes;
4. pruebas ejecutadas;
5. resultado de las pruebas;
6. cualquier limitación o aspecto que no pudo verificarse;
7. si existe alguna decisión nueva que deba documentarse como regla permanente.

No afirmar que algo fue probado si solamente fue inspeccionado por código.

Distinguir entre:

- validación estática;
- pruebas automatizadas;
- prueba local en Docker;
- prueba real en producción.

---

## 18. Documentación

La documentación existente debe considerarse parte del proyecto.

Antes de introducir cambios arquitectónicos importantes, revisar los documentos relevantes dentro de:

    docs/

No duplicar grandes explicaciones en este archivo.

`AGENTS.md` contiene reglas permanentes y restricciones que un agente necesita recordar siempre.

Los documentos de `docs/` contienen la explicación detallada de arquitectura, despliegue, entornos y funcionalidades.

---

## 19. Nueva regla permanente

Cuando una tarea revele una limitación o criterio que probablemente vuelva a afectar desarrollos futuros, evaluar agregarlo a este archivo.

Ejemplos:

- incompatibilidades del hosting;
- convenciones de diseño;
- arquitectura obligatoria;
- mecanismos centrales que no deben duplicarse;
- decisiones de seguridad;
- procedimientos especiales de prueba.

El objetivo es que los mismos errores no tengan que descubrirse nuevamente en conversaciones futuras.

---

## 20. Web Push

El MVP Web Push conserva estas separaciones:

- las suscripciones anónimas viven en `WEB_DB`;
- el navegador recibe únicamente la clave VAPID pública;
- la clave VAPID privada queda fuera de Git y se usa sólo en procesos server-side;
- el piloto permite que cada navegador gestione su propia suscripción y preferencias desde `/notificaciones.php`; la identificación pública se basa en la `PushSubscription` completa y nunca en un ID numérico; `/admin/notificaciones-prueba.php` y `/admin/notificaciones-astronomicas.php` se conservan para pruebas, supervisión y correcciones;
- el envío administrativo del hosting usa `minishlink/web-push` mediante Composer y `symfony/polyfill-mbstring`; `vendor/` generado desde `composer.lock` se excluye del despliegue normal y sólo se transfiere usando `scripts/desplegar.sh --include-vendor`;
- el script Python con `pywebpush` se conserva como alternativa manual de la Mini PC;
- `service-worker.js` atiende solamente `push` y `notificationclick`; no agregar caché u offline como efecto colateral de cambios en notificaciones.
- el único cron permanente del hosting invoca `scripts/run-scheduled-tasks.php`; los scripts de salida lunar y recordatorios generales quedan como wrappers CLI de diagnóstico, no como cron separados;
- las migraciones automáticas compatibles se incorporan mediante la lista explícita y ordenada de `scripts/migrations/registry.php`; las destructivas o irreversibles deben marcarse manuales y nunca ejecutarse automáticamente.
- los tipos, disponibilidad global, plantillas, URL y valores predeterminados de avisos astronómicos viven en `web_push_notification_types`; `moonrise` y `test` deben renderizarse con los marcadores controlados del módulo común, sin volver a fijar textos o destinos en los procesadores;
- `moonrise`, `eclipse`, `lunar_conjunction` y `satellite_transit` comparten el contrato de proveedores y el procesador de `includes/web-push-astronomy.php`; no bifurcar programación, No molestar, deduplicación, render, envío ni historial por tipo;
- `available`, la preferencia `enabled`, la habilitación general del dispositivo y el estado técnico de la suscripción son capas independientes; desactivar un tipo global nunca debe borrar preferencias existentes.
- cada configuración de dispositivo posee un ID público de soporte aleatorio e inmutable con formato `AL-XXXXXXXX`, almacenado en `web_push_device_config` y protegido por un índice único. Es sólo una referencia para localizar el dispositivo en administración: nunca autentica ni reemplaza la identificación mediante la `PushSubscription` completa, y no debe migrarse entre suscripciones distintas sin continuidad inequívoca.

---

## 21. Fuente de datos astronómicos portable

Las funcionalidades astronómicas web migradas utilizan `includes/astronomy-data.php` como punto de entrada. `daily`, `range`, `directions`, `moon/instant`, `altitude-profile` y `tonight` permiten elegir `api` o `php`: la elegida es primaria y la otra queda como fallback técnico. La selección se persiste con claves `astronomy.data_source.<funcionalidad>`.

Las clases `AstronomyEngine\` se cargan mediante el autoloader PSR-4 manual registrado en `includes/api-client.php`, apuntando a `astronomy-engine/src/`. Esta carga no depende de regenerar Composer ni de desplegar `vendor/`.

La API Python y el motor portable se alternan sólo desde esa capa común. No agregar llamadas directas desde páginas o proxies PHP para esas funcionalidades.

Los eventos mantienen además su selección de fuente independiente en `includes/astronomy-events.php`. Los eclipses PHP ya están validados e integrados mediante la fachada portable; no reimplementar sus cálculos en la web.

La imagen lunar principal permite elegir la colección precalculada de
`assets/images/moon-phases-large/` o la API. La alternativa actúa como fallback
y el PNG pequeño queda siempre como último recurso. La orientación aparente se aplica en frontend con el ángulo de
`MoonBrightLimbCalculator` y CSS, manteniendo el contenedor centrado y con
`overflow: visible`. No introducir render PNG con GD en runtime; la API de
imagen y el PNG estático pequeño quedan como fallbacks.

---

## 22. Fuentes de eventos astronómicos

- las páginas deben solicitar eventos mediante `includes/astronomy-events.php`, sin elegir ni fusionar fuentes;
- la fuente se resuelve por `event_group` con precedencia configuración administrativa persistida → variable de entorno específica → `api`;
- `moon_phase`, `lunar_apsis`, `lunar_orbit`, `lunar_libration`, `lunar_conjunction` y `eclipse` admiten `api`, `database`, `php`, `auto` y `compare`;
- al seleccionar `api`, una falla técnica aplica API → MariaDB → PHP en los grupos con tres fuentes; una respuesta válida vacía nunca dispara fallback;
- la selección administrativa vive en `admin_configuracion_sitio`, nunca en `astronomical_events`;
- ninguna integración debe eliminar los adaptadores existentes ni escribir o corregir automáticamente una fuente desde `compare`.

Las consultas PHP lunares deben propagar los grupos solicitados hasta
`LunarEventCalculator::calculate()`. No invocar el cálculo completo para luego
descartar fases, ápsides, nodos, libraciones o conjunciones no pedidas. La clave
de caché debe distinguir la combinación exacta de grupos.

La cobertura contractual de `astronomical_events` es 1900–2050. En `auto`, MariaDB se usa sólo cuando cubre por completo el intervalo pedido y PHP resuelve lo que quede fuera. Para eclipses recuperados de MariaDB, la geometría global persistida se conserva y las circunstancias locales faltantes se completan con los calculadores PHP validados.

`satellite-lunar-transits` no forma parte de esta migración; no trasladarlo a estas fachadas sin una tarea específica.

---

## 23. Motor satelital PHP

El primer bloque satelital vive bajo `AstronomyEngine\Satellite`: TLE local,
SGP4 near-earth WGS72, estado TEME y transformación topocéntrica WGS84. Se
carga con el mismo autoloader manual del motor y no depende de Python, red ni
extensiones nativas. No confundir TEME con las coordenadas aparentes/de fecha
de `EclipseTopocentricGeometry`, ni reutilizar esa clase para satélites.

La reducción usa DUT1 explícito y configurable, cero por defecto, y movimiento
polar cero. Una futura incorporación de EOP debe ser explícita y validada
contra los fixtures; no agregar correcciones ocultas. Los fixtures fijos viven
en `tests/fixtures/satellite/`.

`SatelliteLunarTransitDetector` busca acercamientos y tránsitos lunares con
esos TLE locales, `AstronomyObserver` y `MeeusLunarCalculator`. Es una pieza
portable del motor con CLI y benchmark propios: no conectarla a fachadas,
endpoints, almacenamiento, descargas, caché, “Esta noche”, “Lo próximo” ni
notificaciones sin una tarea específica.

La operación con TLE recientes entra por `SatelliteTransitService` (o por el
adaptador lunar legado `SatelliteLunarTransitService`) y
`CachedCelesTrakTleProvider`: sólo admite ISS 25544 y Tiangong 48274, consulta
CelesTrak con `FORMAT=TLE`, valida checksum y NORAD antes de publicar un valor,
y usa `astronomy-engine/cache/satellite/tle-cache.json` con TTL predeterminado
de seis horas. El caché es estado de ejecución excluido de Git y despliegues.
Ante una descarga fallida se conserva exclusivamente el último TLE que vuelva
a superar la validación local; sin descarga ni caché válida debe fallar de
forma explícita. El modo offline usa `FixtureSatelliteTleProvider`.

La entrada unificada para búsquedas nuevas es `SatelliteTransitService`, con
objetivos `moon`, `sun` o ambos. `SatelliteAngularTransitDetector` concentra
muestreo, separación, clasificación, refinamiento y contactos; Luna y Sol
mantienen proveedores geométricos separados. No volver a bifurcar un detector
completo por cuerpo. Para el Sol la grilla fina adaptativa es de dos segundos
sólo dentro de pasos preseleccionados, con refinamiento continuo y contactos a
alta resolución. Toda salida solar, especialmente una futura salida pública,
debe incluir que nunca se observe el Sol directamente ni con instrumentos sin
un filtro solar certificado.

En la portada, `includes/home-satellite-context.php` es la única entrada al
servicio satelital. Si `home.satellite_transits.enabled` está activa, se ejecuta
una vez después de resolver ubicación, reloj y ventana de `tonight`, y conserva
el resultado combinado ISS + Tiangong / Sol + Luna en
`$homePageContext['satellite']`; desactivada debe cortar antes de resolver TLE.
La vista de `tonight` recibe sólo los eventos lunares cuyo máximo cae dentro de
la noche civil local; la vista de `upcoming` recibe el resto dentro de 48 horas.
Ambas presentan sólo `transit` y `very_close`. No invocar nuevamente
`SatelliteTransitService` desde tarjetas, helpers de presentación ni secciones.

---

## 24. Trazabilidad astronómica

La trazabilidad reproducible entra exclusivamente por `includes/astronomy-trace.php` y se habilita con `astronomy.trace.enabled`. Las fachadas generales, eventos, contexto satelital y series del Explorador generan una fila por operación de alto nivel en `astronomy_request_log`; no instrumentar muestras, días, refinamientos ni calculadores internos. `request_id` puede vincularse en el futuro con reportes, mientras `session_trace_id` es anónimo y no debe derivarse de IDs reales, IP o user-agent. Una falla del log nunca debe interrumpir la consulta astronómica.
