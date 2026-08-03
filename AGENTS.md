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
- el piloto se administra desde `/admin/notificaciones-prueba.php`; no exponer todavía controles públicos de suscripción;
- el envío administrativo del hosting usa `minishlink/web-push` mediante Composer y `symfony/polyfill-mbstring`; `vendor/` generado desde `composer.lock` se excluye del despliegue normal y sólo se transfiere usando `scripts/desplegar.sh --include-vendor`;
- el script Python con `pywebpush` se conserva como alternativa manual de la Mini PC;
- `service-worker.js` atiende solamente `push` y `notificationclick`; no agregar caché u offline como efecto colateral de cambios en notificaciones.

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
