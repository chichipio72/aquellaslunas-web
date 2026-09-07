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

La página `que-podes-hacer.php` tiene su fuente editorial exclusiva en
`content-data/que-podes-hacer.json`. No volver a hardcodear sus textos o tarjetas en
PHP ni habilitar HTML desde ese archivo. Los cambios de esquema deben mantener la
validación y el escape de `includes/capabilities-content.php`; `content-data/` debe
continuar bloqueado para acceso HTTP aunque forme parte del despliegue.

La página `fuentes-y-creditos.php` aplica el mismo contrato: su contenido editorial
y todos los enlaces externos viven en `content-data/fuentes-y-creditos.json`,
validados y escapados exclusivamente por `includes/sources-credits-content.php`.
No hardcodear fuentes o créditos en la vista ni habilitar HTML desde el JSON.

El SEO público de contenidos es un contrato: el índice limpio y los artículos
válidos/visibles usan `index, follow`; las búsquedas internas usan `noindex, follow`;
cada artículo conserva canonical por slug, JSON-LD `Article` + `BreadcrumbList` y
breadcrumb visible. Las previews, entidades ocultas o inválidas no son indexables.
No bifurcar estas decisiones fuera de `includes/seo.php`, `contenidos.php` y
`contenido.php`.

El sitemap público conserva la URL `/astro/sitemap.xml`, resuelta por Apache
hacia `sitemap.php`. Las entradas de artículos se generan desde el catálogo
MySQL validado: sólo se publican artículos válidos y visibles, con la misma
canonical individual usada por `contenido.php`. No volver a mantener una lista
estática de artículos en XML.

Las relaciones de `contenido_articulos_relaciones` son editoriales, dirigidas
y ordenadas desde el artículo origen hacia un `slug_relacionado`. La vista
pública sólo convierte en enlaces los valores que coinciden con otro artículo
válido y visible; no debe inferir la relación inversa ni generar relaciones a
partir de palabras clave. Las entradas administrativas sólo admiten slugs exactos
de artículos existentes, sin autorrelaciones ni duplicados; los destinos ocultos
pueden conservarse y continúan filtrándose en la vista pública.

El slug de un artículo que ya fue publicado se considera una URL pública
estable y no debe modificarse normalmente. El editor conserva por ahora la
edición técnica del campo con una advertencia explícita; no existe todavía
historial de slugs ni redirección automática desde valores anteriores.

Los embeds en artículos entran exclusivamente mediante la directiva controlada
`[[embed url="..."]]`; nunca habilitan HTML o iframes escritos por el editor.
La política central de proveedores admite URLs HTTPS con host exacto
`chichipiosblog.com.ar` bajo `/astronomia/` y los widgets propios con host exacto
`aquellaslunas.com.ar` bajo `/astro/embeds/`. La única ruta propia adicional
permitida es `/astro/explorador/embed.php`, exclusivamente para configuraciones
compartibles v=1 del Explorador. El renderer controla
todos los atributos técnicos. Los embeds inválidos se omiten públicamente y se
informan como advertencias editoriales sin invalidar el resto del artículo.
El iframe, sus atributos de seguridad y su contenedor responsive pertenecen al
renderer; no permitir que Markdown o datos editoriales controlen `sandbox`, permisos,
HTML, tamaño arbitrario ni proveedores fuera de la allowlist.

La medición GA4 de trivias es progresiva y nunca debe bloquear su interacción:
`trivia_view` se deduplica por elemento visible, `trivia_answer` por primera respuesta
y `trivia_article_click` no intercepta la navegación. Mantener los parámetros
`trivia_codigo`, `articulo_slug` cuando exista, `correcta` y `opcion` según aplique.

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

La protección de frescura para páginas que pueden quedar suspendidas es opt-in y
reutiliza `includes/page-freshness.php` con `assets/js/page-freshness.js`. No cargarla
globalmente ni confundir una interacción visual con una actualización efectiva de
datos. Debe consumir `window.siteTimeContext`: hora simulada fija en debug y reloj
real del dispositivo sólo en modo normal, sin implementar otro reloj. El umbral
permanece centralizado y el aviso ofrece recarga manual; no agregar timers continuos
ni recargas automáticas.

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

El menú principal usa `includes/site-menu.php` y las tablas `site_menu_groups` y
`site_menu_sections` como fuente de verdad para grupos, orden y visibilidad pública o
administrativa. `Inicio` es siempre la primera entrada y queda fuera de los grupos.
No volver a hardcodear agrupaciones en `site-sections.php`, no usar las claves legadas
`menu.*.enabled` para renderizar y no eliminar un grupo mientras contenga secciones.
La visibilidad del menú nunca debe convertirse por sí sola en control de acceso.

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
- el cron permanente de Web Push invoca `scripts/run-scheduled-tasks.php`; los scripts de salida lunar y recordatorios generales quedan como wrappers CLI de diagnóstico, no como cron separados. El actualizador periódico de TLE `scripts/update-satellite-tles.php` es la única excepción independiente prevista;
- las migraciones automáticas compatibles se incorporan mediante la lista explícita y ordenada de `scripts/migrations/registry.php`; las destructivas o irreversibles deben marcarse manuales y nunca ejecutarse automáticamente.
- los tipos, disponibilidad global, plantillas, URL y valores predeterminados de avisos astronómicos viven en `web_push_notification_types`; `moonrise` y `test` deben renderizarse con los marcadores controlados del módulo común, sin volver a fijar textos o destinos en los procesadores;
- `moonrise`, `eclipse`, `lunar_conjunction` y `satellite_transit` comparten el contrato de proveedores y el procesador de `includes/web-push-astronomy.php`; no bifurcar programación, No molestar, deduplicación, render, envío ni historial por tipo;
- `available`, la preferencia `enabled`, la habilitación general del dispositivo y el estado técnico de la suscripción son capas independientes; desactivar un tipo global nunca debe borrar preferencias existentes.
- las campanas contextuales de portada sólo enlazan la configuración completa de
  `moonrise`, `eclipse`, `lunar_conjunction` o `satellite_transit`; nunca activan una
  preferencia ni representan una suscripción al evento puntual mostrado;
- cada configuración de dispositivo posee un ID público de soporte aleatorio e inmutable con formato `AL-XXXXXXXX`, almacenado en `web_push_device_config` y protegido por un índice único. Es sólo una referencia para localizar el dispositivo en administración: nunca autentica ni reemplaza la identificación mediante la `PushSubscription` completa, y no debe migrarse entre suscripciones distintas sin continuidad inequívoca.
- `ubicacion.php` puede copiar explícitamente la ubicación general a una configuración de avisos ya existente del navegador actual. Debe identificarla mediante la `PushSubscription` completa y actualizar sólo `location_name`, `latitude`, `longitude` y `timezone`; nunca crear configuración, activar avisos ni modificar suscripción o preferencias desde ese flujo.

---

## 21. Fuente de datos astronómicos portable

Las funcionalidades astronómicas web migradas utilizan `includes/astronomy-data.php` como punto de entrada. `daily`, `range`, `directions`, `moon/instant`, `altitude-profile` y `tonight` permiten elegir `api` o `php`: la elegida es primaria y la otra queda como fallback técnico. La selección se persiste con claves `astronomy.data_source.<funcionalidad>`.

Las clases `AstronomyEngine\` se cargan mediante el autoloader PSR-4 manual registrado en `includes/api-client.php`, apuntando a `astronomy-engine/src/`. Esta carga no depende de regenerar Composer ni de desplegar `vendor/`.

La API Python y el motor portable se alternan sólo desde esa capa común. No agregar llamadas directas desde páginas o proxies PHP para esas funcionalidades.

Los eventos mantienen además su selección de fuente independiente en `includes/astronomy-events.php`. Los eclipses PHP ya están validados e integrados mediante la fachada portable; no reimplementar sus cálculos en la web.

La Luna grande de la tarjeta “El cielo hoy”, la imagen lunar principal de
`cielo-de-hoy.php` y la sección pública explícita `luna-fecha-favorita.php` usan el componente Three.js de
`assets/js/moon-three-render.js`, alimentado en servidor por
`MoonDiskAppearanceCalculator` y configurado mediante `includes/moon-three-render.php`.
La Luna de portada puede abrir un visor modal exploratorio que conserva exactamente
la geometría del payload del instante, pero usa la apariencia y el relieve configurados
para `interactive.moon_three.*`: la rotación y el zoom son sólo transformaciones de
vista, “Volver a orientación real” las elimina y ninguna acción del visor modifica
fecha, fase o dirección solar. El normal map 8K se solicita recién al abrir ese visor
o alcanzar el umbral de Luna interactiva, con fallback al mapa estándar si WebGL no
admite texturas 8192.
El PNG anterior queda allí como fallback de WebGL. Todas las demás imágenes
lunares —incluidas escenas, fases y contenidos— conservan
`moon-image.php`, la colección precalculada o sus miniaturas actuales; no ampliar
el uso de Three.js a esas superficies sin una tarea específica. No introducir
render PNG con GD en runtime. El wallpaper de la sección nueva se compone en el
navegador y conserva fecha y hora en la URL para compartir y volver desde ubicación.
Su configuración `favorite.moon_three.*` es independiente de `home.moon_three.*`:
se inicializa una sola vez copiando la portada y añade color, brillo y degradado
horizontal de fondo. No volver a unir ambos conjuntos ni hacer que uno pise al otro.
`interactive.moon_three.*` es un tercer conjunto independiente, inicializado desde
la portada sólo al crearse. Debe alimentar tanto `luna-interactiva.php` como su
embed; no hacer que futuros ajustes de portada o fecha favorita lo sobrescriban.
Su normal map 8K es una capa progresiva: debe conservar la carga inicial con el DEM
configurado, respetar el umbral/modo administrativo y mantener fallback cuando WebGL
no admita texturas de 8192 píxeles. También lo reutilizan el visor ampliado de portada,
al abrirse o alcanzar el umbral, y la fecha favorita exclusivamente al exportar; estas
superficies nunca deben solicitarlo durante su carga inicial. No trasladarlo a otras
lunas sin una tarea expresa.

La herramienta pública `luna-interactiva.php` es la excepción adicional autorizada:
reutiliza la geometría astronómica y los mismos mapas, pero posee su propio módulo
Three.js para proyectar etiquetas desde coordenadas lunares 3D. Su catálogo curado
vive en `assets/data/moon-features.json`: la nomenclatura usa coordenadas
planetocéntricas IAU/USGS, longitud positiva al este, y los alunizajes conservan
su fuente editorial en `assets/data/moon-landings.json`. Ese catálogo incluye sólo
aterrizajes suaves, controlados y exitosos, genera los puntos de la capa y alimenta
sus fichas breves, reemplazando cualquier registro legado de `moon-features.json`;
no agregar allí nuevos alunizajes ni incorporar impactos, intentos fallidos o éxitos
ambiguos. La rotación exploratoria, capas, detalle y zoom se reflejan en
la URL; la iluminación puede ser solar realista o frontal completa sin modificar
la geometría; `embed=1` elimina encabezado y pie. No grabar etiquetas en la textura ni
mostrar puntos del hemisferio oculto a la cámara actual.
Las etiquetas curadas son interactivas y abren la misma ficha que el buscador. Si
una selección del Gazetteer corresponde a una etiqueta curada, se resalta y reutiliza
esa etiqueta; no superponerle el marcador temporal, reservado para objetos sin etiqueta.
La densidad de etiquetas usa `detail=auto` como valor predeterminado progresivo por
bandas de zoom, prioridad, tamaño, área disponible y colisiones. Los valores históricos
`detail=main` y `detail=more` conservan su significado en la página y en el embed. Los
accidentes que entran en la selección automática pueden conservar un marker interactivo
aunque su nombre no quede fijo; no volver a unir visibilidad del punto y de la etiqueta.
La atenuación configurable del normal map junto al terminador pertenece sólo a la
Luna interactiva y a su preview administrativa: mezcla la normal perturbada con la
normal geométrica usando la dirección solar real. No trasladarla a la Luna estática
de portada, a “La Luna de tu fecha favorita” ni al visor modal de portada.
El índice completo `assets/data/moon-gazetteer.json` se regenera offline desde
IAU/USGS con `tools/update-moon-gazetteer.py` y sirve sólo para búsqueda/ficha: no
convertirlo en etiquetas ni consultar el Gazetteer en runtime. El enriquecimiento
opcional vive separado en `assets/data/moon-geology-experiment.json`; no mezclar
datos oficiales, interpretaciones y métricas sin conservar evidencia y fuente.
El enriquecimiento masivo reproducible vive en `moon-geology-auto.json` y se
genera offline con `tools/build-moon-geology-auto.py`. La unidad USGS sólo expresa
qué polígono contiene el punto central; no atribuirla al accidente completo. Los
cruces LPI probables o ambiguos no se publican automáticamente, y los valores
modelados nunca deben presentarse como mediciones.
Las recomendaciones nocturnas de cráteres de portada y
`cielo-de-esta-noche.php` comparten exclusivamente
`includes/moon-crater-recommendations.php` y el índice compacto generado offline;
no duplicar la puntuación en las vistas ni cargar los catálogos científicos
completos durante cada request público. Sus enlaces usan el ID estable en
`luna-interactiva.php?feature=...`.
La localización científica es una capa de presentación separada en
`assets/data/moon-geology-es.json`: traducir enums mediante mappings y textos USGS
deduplicados por código de unidad. No reescribir los originales, duplicar una
traducción en miles de objetos ni mostrar IDs internos de fuentes. Las etimologías
individuales permanecen ocultas hasta contar con una estrategia específica.

Los widgets lunares para contenidos viven exclusivamente bajo `embeds/` y no se
incorporan a menú, portada ni sitemap. `embeds/luna-interactiva.php` monta el motor
existente sin modificar la página pública; bloqueo de giro y ocultamiento de
controles se resuelven en la superficie wrapper. `embeds/libracion-lunar.php`
recibe 121 muestras topocéntricas delimitadas por dos lunas nuevas consecutivas
calculadas por el motor PHP; realista e iluminado deben compartir exactamente ese
intervalo. Las interpola/renderiza en cliente y debe partir
de la orientación local `lunar_north_screen_angle_degrees`, congelar la referencia
celeste de pantalla del instante inicial y variar sólo el eje lunar; incorporar la
rotación diaria del campo en cada muestra hace oscilar el disco artificialmente. El tiempo del bucle siempre
avanza y un blend visual breve cierra el ciclo sin reproducirlo en sentido inverso.
La exportación WebM permanece cliente-side sobre el canvas y nunca incluye controles.
Su apariencia se resuelve exclusivamente desde `favorite.moon_three.*` y el mismo
payload debe alimentar la previsualización administrativa, la URL embebida y el
WebM; no agregar controles visuales paralelos ni trasladar esos valores al query
string del widget.
`embeds/fases-tierra-luna.php` conserva una única geometría temporal para ambos
cuerpos: la luz terrestre es la dirección solar lunar opuesta, su fracción
iluminada es complementaria y su diámetro aparente se presenta 3,67 veces mayor.
No reemplazar esta relación por imágenes de fases independientes ni publicar el
widget como página, entrada de menú o elemento de sitemap.
En su animación, fijar el norte celeste del instante inicial: la Luna debe variar
sólo por libración y el eje terrestre no debe oscilar con la rotación diaria del
campo local.
Las URLs y los iframes se construyen desde `admin/widgets-lunares/` y no constituyen
configuración persistente.
`embeds/escena-lunar.php` es una superficie interna configurable desde ese generador:
reutiliza `MoonDiskAppearanceCalculator` y `moon-three-render.js` sin modificar la
Luna interactiva. La fecha, hora y apariencia viven en la URL; la ubicación siempre
es la ubicación activa. Su cielo automático se deriva de la altura solar y los
controles visuales nunca alteran fase, libración u orientación observacional.
El degradado radial de esa escena mantiene parámetros de URL independientes para
color y brillo del centro, color y brillo del borde y extensión. En modo automático
los colores base se derivan de la altura solar; en modo manual se usan los elegidos.
La integración atmosférica de la Luna de esa escena añade luz cenicienta, mezcla de
la cara nocturna con el color central del cielo, halo y suavidad de limbo como ajustes
visuales de URL. Deben permanecer neutros por defecto en las demás superficies del
renderer y no pueden alterar la geometría o hacer transparente sólo la mitad oscura.
La opacidad y suavidad del limbo de la cara nocturna pueden revelar progresivamente
el cielo efectivo detrás del canvas, pero siempre se combinan con la mezcla cromática
y la luz cenicienta controladas; sus defaults fuera de esta escena preservan alfa 1.
Los presets de esta escena viven en `lunar_scene_presets` como configuración URL
validada y se gestionan exclusivamente desde el endpoint administrativo autenticado
con CSRF. Crear, actualizar, duplicar o eliminar un preset nunca modifica la ubicación
global ni convierte esos valores en configuración pública del sitio.
El widget `embeds/lunas-llenas-tamano.php` muestra siempre las próximas 12 lunas
llenas desde `date`, en orden cronológico y con dos filas de seis en escritorio.
La escala y el porcentaje usan el diámetro angular calculado por el motor respecto
del diámetro a la distancia lunar media de 384.400 km; no normalizarlos contra el
máximo del período ni generar una imagen distinta para cada evento.
El generador conserva sólo en `localStorage` la última edición de cada widget para
que F5 no restaure los defaults; no trasladar esa comodidad local a configuración
global o base de datos sin una tarea específica.

Los widgets `embeds/eclipse-lunar-real.php` y `embeds/eclipse-solar-real.php`
consumen series temporales preparadas en PHP desde los calculadores de eclipses y
su geometría topocéntrica; JavaScript sólo interpola y renderiza esas muestras.
Fecha y coordenadas pertenecen a la URL de cada instancia. Corona, prominencias,
perlas de Baily y anillo de diamantes son capas visuales y nunca alteran posiciones,
tamaños ni contactos calculados por el motor.
Sus parámetros canónicos son niveles visuales `0–10` (`corona_level`,
`prominence_level` y `baily_level`); las claves booleanas anteriores se aceptan
sólo como compatibilidad de URLs ya generadas.
Exposición, intensidades, oscurecimiento y colores del eclipse solar real son
también estado visual de la URL del embed; no trasladarlos al motor ni usarlos
para modificar la geometría o la clasificación local.
En el eclipse lunar real, el brillo y cobre adicionales de totalidad se aplican
sólo mediante una envolvente visual continua U2–máximo–U3; el shader de
parcialidad permanece como base y esos parámetros no modifican astronomía.
El gradiente de totalidad lunar se deriva en el shader de la distancia de cada
fragmento al centro y radio reales de la umbra; no sustituirlo por un tinte o una
textura fija ni aplicar sus controles fuera de la envolvente perceptual.
`embeds/eclipse-solar-espacio.php` es una visualización geocéntrica exclusivamente
embebible. Su geometría entra por `SolarEclipseShadowGeometry`, que reutiliza las
efemérides solares/lunares del motor y entrega vectores inerciales y terrestres,
conos e intersecciones; no calcular manchas de sombra arbitrarias en JavaScript.
La posición visual lunar debe conservar el orden vector geocéntrico inercial →
interpolación por tiempo real → compresión radial constante → Three.js. La
rotación sidérea pertenece a la Tierra y a la sombra superficial, nunca a la
trayectoria lunar. La distancia visual Luna–Tierra puede comprimirse, pero el
sombreado superficial debe derivarse de los discos aparentes reales para cada
punto de la esfera.

Las cercanías Luna–planetas/estrellas de “Esta noche” y su escena angular son un
único contrato de `TonightCalculator`, con umbral de 10° y muestras futuras respecto
del reloj vigente. La presentación prioriza planetas (hasta dos en el texto) y sólo
menciona la estrella más cercana cuando no hay planetas; el SVG conserva todos los
objetos válidos del instante. Portada, “El cielo hoy” y la página detallada de la
noche deben reutilizar `includes/home-tonight-scene.php`: no bifurcar cálculo,
selección, imágenes lunares ni proyección gráfica entre superficies.

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
y usa `astronomy-engine/cache/satellite/tle-cache.json`. La actualización
periódica entra por `scripts/update-satellite-tles.php`; las superficies web
usan inmediatamente cualquier entrada local válida aunque haya superado el TTL
y sólo permiten descarga síncrona, con timeout corto, cuando no existe ninguna.
El caché es estado de ejecución excluido de Git y despliegues. Ante una descarga
fallida se conserva exclusivamente el último TLE que vuelva a superar la
validación local; sin descarga ni caché válida debe fallar de forma explícita.
El modo offline usa `FixtureSatelliteTleProvider`.

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

## 24. Fotografía lunar

La herramienta pública `fotografia.php` conserva separados el estado astronómico
continuo (`includes/photography-scene.php`), la clasificación descriptiva, la
geometría de sensor/encuadre (`includes/photography-geometry.php`), el modelo
editorial (`includes/photography-editorial.php`) y el renderer. Esquema y Simulado
consumen exactamente el mismo estado y las mismas transformaciones de cámara; el
Simulado sólo agrega una capa visual calibrada mediante `photography.simulated.*` y
`includes/photography-simulation.php`, sin recalcular astronomía en JavaScript ni
rotar las guías del sensor. Su Luna 3D reutiliza Three.js, el albedo LROC, el normal
map LOLA y la geometría `surface_geometry` ya producida por
`MoonDiskAppearanceCalculator`; posición, tamaño, recorte y roll siguen perteneciendo
al planner 2D. WebGL es progresivo y conserva la Luna SVG como fallback. Las focales editoriales son referencias y la
focal máxima es un límite geométrico: nunca presentarlas como configuraciones
correctas, mejores o ideales. Los astros conservan sus posiciones reales y no se
mueven libremente para componer.

En la Luna 3D simulada, la zona sin luz solar directa toma siempre como fuente
cromática el color local del cielo detrás del disco. Su control administrativo sólo
puede variar intensidad o grado de integración respecto de ese mismo color; no debe
reintroducir un tono oscuro lunar absoluto. Una futura luz cenicienta será una capa
de iluminación adicional y separada, nunca el mecanismo para resolver esta base.
La calibración lunar conserva las anclas día/crepúsculo/noche por horizonte/cenit,
pero el ancla crepuscular de Luna baja se divide en solar y antisolar. Ambas se
interpolan continuamente con `smootherstep` según la separación Sol–Luna; el cielo
aplica el mismo eje al color de horizonte según la separación Sol–centro del
encuadre, mientras el cénit permanece compartido. Día, noche y Luna alta no se
bifurcan por dirección. No volver a reducirla a un único perfil global ni agregar saturación o color
atmosférico lunar como parte implícita de esa interpolación.
La mezcla por altura lunar conserva sólo las anclas horizonte/cenit y usa umbrales
administrables de inicio y fin con `smoothstep`; no agregar perfiles intermedios por
altura. Los límites solares noche/máximo de crepúsculo/día y los límites de máximo/
cielo limpio de la atmósfera lunar son configuraciones separadas y ordenadas.
El perfil diurno lava el microdetalle y el contraste, mezcla también la zona iluminada
con el cielo local, conserva sólo un refuerzo moderado junto al terminador y suaviza
el limbo mediante transparencia corta. Estos efectos se interpolan hacia los perfiles
crepuscular y nocturno; no resolverlos con blur del canvas ni con saturación.
La capa atmosférica lunar se aplica después de esa interpolación y depende continuamente
de la altura aparente real: modula brillo, contraste, microdetalle, integración y tono
cálido entre una altura de máximo y otra de cielo limpio. La calidez se interpola además
por altura solar y permanece deliberadamente baja de día. No convertir esta capa en
meteorología, blur, exposición de cámara o escalones cromáticos por altura.
El brillo iluminado de cada ancla es una intensidad base anterior a la extinción; la
atenuación atmosférica permanece en su control independiente y el estado interpolado
expone ambos factores para diagnóstico. No compensar una Luna baja alterando la
geometría, la fase o la incidencia solar del shader.
El resplandor de la Luna simulada es una capa WebGL global situada detrás del disco,
no otro perfil lunar. Su intensidad interpola continuamente fracción iluminada y las
bandas solares existentes; su distribución espacial reutiliza la dirección solar ya
orientada con libración y roll. Debe compartir escala, canvas y recorte de horizonte
con la esfera, tomar el tono iluminado como base y no reemplazar el brillo del material.
Su interruptor booleano debe omitir la capa y restaurar el encuadre interno anterior,
sin efectos residuales ni pérdida de resolución del disco; no integrar ese apagado en
el shader principal ni volver transparente la cara nocturna de la esfera.
La máscara exterior toca el radio geométrico sin invadirlo y evalúa una normal virtual
cercana al limbo contra la misma dirección solar 3D del material. Así puede envolver
suavemente el limbo próximo a la región iluminada sin convertir todo el disco en una
oclusión angular binaria ni aclarar la cara oscura interior.
El modo Simulado de `fotografia.php` es público y comparte exactamente el mismo estado
reproducible que Esquema. La barra superior muestra, para la fecha y ubicación activas,
salida/puesta de Sol y Luna obtenidas mediante `astronomyDataDaily()`; no calcular esos
horarios en el renderer ni crear otra fuente diaria.
En Simulado, la visibilidad de astros usa curvas continuas de altura solar separadas
para estrellas y planetas; `star_visibility` es exclusivamente la ganancia máxima de
noche. Objeto y etiqueta comparten la misma opacidad y desaparecen juntos bajo el
umbral perceptual. Esquema conserva siempre su representación geométrica, y una futura
fotometría por magnitud debe incorporarse como factor del objeto sin duplicar esas
curvas solares.
En móvil, Fotografía conserva un único visor sticky bajo la altura real y variable del
header. Los cambios puramente geométricos de cámara se renderizan y sincronizan en URL
en cliente; fecha, hora, sensor, orientación y selección que requieren nuevo estado de
servidor autoenvían el formulario con debounce y restauran la posición de scroll. El
submit visible queda sólo como fallback cuando JavaScript no está disponible.
La hora editable de Fotografía es siempre la base y `time_offset` conserva por separado
el desplazamiento exploratorio en minutos; el cálculo usa su suma y los enlaces de
eventos parten de offset cero. Los presets de sensor ocultan sus dimensiones, que sólo
se editan en Personalizado, y el zoom combina la focal real exacta con un slider de
progresión logarítmica sincronizado; ninguno de estos controles duplica astronomía en
el navegador.
El centrado público es un estado binario reproducible. Activarlo sólo autoriza un
recentrado puntual al cambiar fecha, hora u offset y al restablecer el encuadre; después
de cada disparo, el drag vuelve a ser manual sin desactivar esa preferencia. Seleccionar
elementos, cambiar focal, sensor u orientación nunca recentra por sí solo. El objetivo
del disparo se deriva siempre del conjunto Luna + horizonte + astros seleccionado.
La orientación inicial de enlaces con `scene` o `event_type` y sin `orientation`
explícita se elige una sola vez por el bounding box angular de la composición; una
relación de predominio 1,15 decide horizontal/vertical y la preferencia local sólo
desempata. Esta decisión temporal no se persiste como preferencia de equipo, salvo que
el usuario cambie después la orientación manualmente.
Los enlaces de eventos hacia Fotografía distinguen el instante canónico del fenómeno
(`event_time`) del instante local sugerido para representarlo (`observation_time`). Las
conjunciones reutilizan posiciones topocéntricas del planner y, si el máximo no es
simultáneamente visible, eligen el instante más cercano con ambos cuerpos al menos 3°
sobre el horizonte y separación todavía próxima. Salidas y puestas nunca se desplazan:
usan la convención compartida de limbo superior aparente, refracción estándar de 34′ y
radio lunar variable. En ese contexto, el renderer ubica además la línea del horizonte
en −34′ geométricos para que cruce el limbo superior cuyo centro está a
`−34′ − semidiámetro`; fuera de rise/set el horizonte conserva 0° geométrico. Los
eclipses conservan su instante/etapa explícita.

Los astros seleccionables de Fotografía se derivan de `ConjunctionCatalog`; no
mantener efemérides ni coordenadas estelares paralelas. El estado de eclipse se
consume exclusivamente desde `astronomyEvents()` y sus circunstancias locales
normalizadas. Los enlaces desde Lo próximo y Eventos Lunares se construyen con
`includes/photography-links.php`, sin imponer focales editoriales.

Las imágenes de ejemplo de escenas y variantes son referencias fotográficas reales
y completamente opcionales. Un valor `NULL` o ausente no reserva espacio, no muestra
placeholders ni mensajes y nunca condiciona clasificación, disponibilidad,
recomendaciones o parámetros fotográficos. Si existe una imagen válida, se asocia a
su escena o variante; no generar ni incorporar imágenes sustitutas.
El selector de referencias de `admin/fotografia` descubre exclusivamente archivos de
`assets/images/fotografia/referencias/`; no reutiliza ni mezcla el catálogo de
Contenidos. Al seleccionar una referencia nueva para una variante, los EXIF disponibles
reemplazan focal, apertura, velocidad e ISO, dejando vacío cada dato ausente; el modelo
de cámara es sólo informativo y la edición manual posterior sigue habilitada.
La importación editorial de Fotografía conserva merge como modo predeterminado. El modo
explícito “Reemplazar todo” valida antes de escribir, ignora IDs del paquete, elimina e
inserta sólo `photography_scene_variants` y `photography_scenes` dentro de una única
transacción y exige confirmación visible. La siembra inicial de escenas base ocurre sólo
cuando se crea un catálogo vacío, nunca para rellenar uno reemplazado.
En merge, una propiedad ausente conserva el valor almacenado y una propiedad presente
con `null` lo borra explícitamente; escenas y variantes existentes aceptan parches por
key, mientras las nuevas exigen sus campos mínimos de creación. Las pruebas de este
importador nunca usan `getWebDatabaseConnection()`: deben ejecutarse exclusivamente
sobre un repositorio aislado o una base en memoria, porque el entorno local comparte la
base MySQL remota real.

## 25. Trazabilidad astronómica

La trazabilidad reproducible entra exclusivamente por `includes/astronomy-trace.php` y se habilita con `astronomy.trace.enabled`. Las fachadas generales, eventos, contexto satelital y series del Explorador generan una fila por operación de alto nivel en `astronomy_request_log`; no instrumentar muestras, días, refinamientos ni calculadores internos. `request_id` puede vincularse en el futuro con reportes, mientras `session_trace_id` es anónimo y no debe derivarse de IDs reales, IP o user-agent. Una falla del log nunca debe interrumpir la consulta astronómica.

---

## 26. Instalación PWA

La identidad instalada es estable: `manifest.webmanifest` conserva `id`,
`start_url` y `scope` en `/astro/`, y todas las páginas enlazan exactamente
`/astro/manifest.webmanifest` sin query ni cache-busting. Las actualizaciones del
manifest se resuelven mediante sus headers HTTP de revalidación; no cambiar su URL
ni esos tres campos sin una migración explícita de identidad PWA.

La tarjeta promocional de portada y la acción del menú son dos disparadores del
mismo flujo en `assets/js/install-prompt.js`; no bifurcar detección, instrucciones,
prompt ni Analytics por superficie. El descarte de 30 días afecta sólo a la tarjeta:
la opción del menú debe seguir disponible. En modo `standalone`, `fullscreen` o
`navigator.standalone`, ambos disparadores quedan ocultos.

Todas las superficies consumen el estado único de `assets/js/install-prompt.js`:
`pwa`, `shortcut`, `ios_home_screen`, `open_external_browser`, `installed` o
`unavailable`. `pwa` exige conservar `beforeinstallprompt` y consumirlo una sola vez
mediante `prompt()`/`userChoice`. Sin ese evento, un navegador con mecanismo manual
puede ofrecer “Agregar acceso directo” junto con instrucciones, nunca presentarlo como
instalación ni simular una API inexistente. iOS conserva Compartir → Agregar a
pantalla de inicio; `appinstalled` y los modos instalados ocultan todos los CTA.

Instagram y Facebook requieren salida guiada de su navegador embebido. Android usa
un `intent:` restringido a Chrome, conserva la URL HTTP(S) y añade `install=1`; el
navegador de destino espera `beforeinstallprompt` y exige otro gesto en un diálogo,
sin disparar instalación automática. iOS nunca intenta abrir Safari mediante un
esquema artificial: muestra Compartir → Agregar a pantalla de inicio. No introducir
permisos, redirecciones automáticas ni un segundo mecanismo de intención.

---

## 27. Visor ISS y Tiangong

`iss-y-tiangong.php` consume series preparadas por
`includes/satellite-stations-visualization.php`: TLE mediante el proveedor cacheado,
SGP4, conversión terrestre, posición lunar y detección de tránsitos permanecen en
PHP. `assets/js/satellite-stations.js` sólo interpola y representa esas muestras.
Los próximos pasos visibles también se derivan allí con el mismo TLE, propagador y
transformación topocéntrica; no duplicar esa búsqueda en JavaScript ni en la vista.
La banda de tránsito es deliberadamente ilustrativa, centrada en el observador y
rotulada como no cartográfica; no reutilizarla como predicción geográfica precisa.

---

## 28. Infografías de eventos

Las infografías públicas de eventos consumen exclusivamente eventos resueltos por
`includes/astronomy-events.php` y la ubicación activa de
`includes/location-context.php`. `includes/event-infographic.php` transforma ese
resultado en un modelo editorial limitado: la plantilla y el renderer Canvas 2D no
recalculan astronomía ni reciben azimut, altura, separación, coordenadas u otros datos
técnicos del evento. La geometría de apariencia lunar queda encapsulada exclusivamente
en el componente Three.js compartido.
El MVP admite acercamientos Luna-planeta y eclipses lunares o solares observables, y
genera PNG en el navegador mediante Canvas 2D, sin persistencia ni endpoints
adicionales. Los eclipses no visibles localmente nunca reciben descarga normal.

Las conjunciones recuperadas de MariaDB conservan su instante y datos globales como
canónicos, pero `includes/astronomy-events.php` completa mediante el motor PHP portable
los campos locales de observabilidad que la tabla histórica no almacena. La coincidencia
exige mismo subtipo y mínimos separados por no más de seis horas; no trasladar este
enriquecimiento a las vistas ni confiar esos campos desde la URL.

La Luna de la infografía se genera con `moonThreeRenderPayload()` para el instante
visual efectivo y reutiliza el canvas transparente de `assets/js/moon-three-render.js`.
El Canvas 2D sólo la composita con el resto de la pieza: no volver a dibujar allí una
fase lunar aproximada, duplicar shaders o recalcular fase y orientación en JavaScript.

Las infografías de eclipses reutilizan exclusivamente los renderers reales de
`assets/js/real-lunar-eclipse-widget.js` y
`assets/js/real-solar-eclipse-widget.js`, con los payloads de
`includes/real-eclipse-embeds.php`. Se montan sin controles y publican una captura
del canvas para que el renderer editorial la componga; no reemplazarlos por el
simulador de Fotografía ni por discos simplificados. El instante visual es el máximo
del evento acotado al intervalo `first_visible_instant` / `last_visible_instant`, y
los rótulos Inicio, Máximo y Fin se derivan de ese mismo intervalo local.
