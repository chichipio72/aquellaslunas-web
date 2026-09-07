# Motor astronómico PHP

Este paquete contiene exclusivamente código astronómico PHP reutilizable. Sus
clases viven en `src/` bajo el namespace `AstronomyEngine`.

El motor recibe datos astronómicos y devuelve resultados. No depende de la
interfaz del laboratorio, Docker, la API Python ni código de comparación o
benchmark.

La web integra estos contratos mediante las fachadas portables y conserva la
API Python sólo como fuente opcional o fallback. El paquete sigue desacoplado
de esa integración y no accede a HTTP ni a bases de datos.

`LunarEventCalculator` encuentra fases principales exactas (perfil `preciso`),
perigeos, apogeos, pasos por nodos, extremos de libración y conjunciones
(perfil `normal`). Las fases usan Meeus capítulo 49; la libración usa la
formulación óptica y física de Meeus capítulo 53; las conjunciones combinan
elementos planetarios aproximados publicados por JPL con el catálogo
Hipparcos/SIMBAD utilizado por la referencia.

`calculate()` acepta opcionalmente una lista de grupos: `moon_phase`,
`lunar_apsis`, `lunar_orbit`, `lunar_libration` y `lunar_conjunction`. Cuando se
indica, ejecuta sólo los algoritmos correspondientes; varias entradas calculan
únicamente esa combinación. Omitir el argumento conserva el comportamiento
histórico de calcular todos los grupos. Esta selección no modifica algoritmos,
resultados ni perfiles de precisión.

`LunarEclipseCalculator` añade eclipses lunares globales mediante geometría
dinámica de sombra y una serie completa de Meeus capítulo 47 aislada en
`MeeusEclipsePositionCalculator`. La batería comparativa 2025–2030 cubre los
14 eventos de referencia y coincide en sus clasificaciones; las circunstancias
del observador se resuelven por separado con el calculador local.

`SolarEclipseCalculator` detecta y clasifica eclipses solares globales con el
método analítico de Meeus capítulo 54. Devuelve máximo, `gamma`, `u`, magnitud
parcial y geometría auxiliar. Las circunstancias locales se calculan con la
clase específica; los mapas mundiales no pertenecen al motor portable.

`LunarEclipseLocalCalculator` y `SolarEclipseLocalCalculator` añaden
circunstancias para un `EclipseObserver`. El bloque lunar evalúa individualmente
los contactos globales y cruces de horizonte; el solar resuelve discos
aparentes topocéntricos, C1–C4, clase, magnitud y cobertura local. Este bloque
está validado e integrado en la fachada de eventos usada por la web.

El subnamespace `AstronomyEngine\Facade` contiene compositores portables para
los contratos conceptuales `daily`, `range`, `directions`, `moonInstant`,
observación local de Luna llena y oportunidades de Luna fina/earthshine.
`FullMoonObservationFacade` y `EarthshineFacade` derivan esos resultados de
fases, días y posiciones calculados por PHP. Reutilizan exclusivamente los
calculadores del motor, no exponen HTTP y no dependen de Python ni de una base
de datos.

`AstronomyEventsFacade` unifica esas familias con fases, ápsides, nodos,
libraciones, conjunciones y eclipses globales/locales. Aplica selección de
tipos, normalización, orden y deduplicación para devolver el contrato común
`type/subtype/datetime/end_datetime/title/details`, sin agregar cálculos
astronómicos.

`AltitudeProfileFacade` genera perfiles topocéntricos diarios de Sol y Luna
mediante los calculadores existentes. Para el perfil solar,
`SolsticeCalculator` aporta únicamente los solsticios de junio y diciembre con
Meeus capítulo 27 y los asigna a invierno/verano según el hemisferio.

`MoonImageRenderer` genera PNG lunares portables mediante GD, con orientación
fija, por hemisferio o aparente local, sombreado configurable y caché de
archivos. Reutiliza los calculadores existentes para la orientación aparente.
La integración de imágenes y sus superficies vigentes está documentada en
[`../docs/funcionalidades-y-motor.md`](../docs/funcionalidades-y-motor.md).

La integración pública combina superficies Three.js expresamente autorizadas,
una colección PNG precalculada y `moon-image.php` como fallback/compatibilidad.
`MoonImageRenderer` no es el renderer principal de esas superficies, por lo que
GD no es un requisito del núcleo astronómico.

`TonightCalculator` compone posiciones solares, lunares, planetarias y de un
catálogo fijo para producir ventanas observables entre mediodías, con los
modos `summary` y `full`. Los datos Hipparcos/SIMBAD están separados en
`TonightCatalog`. El contrato y su integración pública están resumidos en
[`../docs/funcionalidades-y-motor.md`](../docs/funcionalidades-y-motor.md) y
detallados en [`../docs/arquitectura.md`](../docs/arquitectura.md).

El subnamespace `AstronomyEngine\Satellite` contiene el primer bloque orbital
portable: parseo estricto de TLE, propagación SGP4 near-earth con WGS72 y
reducción TEME a altura, azimut y distancia mediante observador WGS84. El port
SGP4 controlado conserva la licencia MIT de `satellite-js` en
`src/Satellite/Sgp4/LICENSE-satellite-js.txt`. `SatelliteLunarTransitDetector`
busca acercamientos y tránsitos lunares de ISS y Tiangong con los TLE locales,
grilla adaptativa y refinamiento de contactos. La búsqueda común vive en
`SatelliteAngularTransitDetector`: conserva proveedores geométricos separados
para Luna y Sol y evita duplicar propagación, clasificación, mínimos y
contactos. El Sol usa posición aparente topocéntrica, radio angular variable y
una grilla fina de dos segundos limitada a pasos visibles. El CLI unificado es
`scripts/find-satellite-transits.php`. `SatelliteTransitService`
resuelve ISS 25544 y Tiangong 48274 mediante CelesTrak y un caché JSON local
con TTL de seis horas, conserva el último TLE válido ante fallos y expone edad,
confianza, advertencias y contadores. `--offline-fixtures` evita toda descarga.
La transformación terrestre usa DUT1 configurable (cero por defecto) y
movimiento polar cero; una futura incorporación de EOP debe hacerse
explícitamente, sin alterar silenciosamente este contrato reproducible.

Toda salida solar debe conservar la advertencia: nunca observar el Sol
directamente ni con instrumentos sin un filtro solar certificado.

La guía de traslado, dependencias, inventario y estado de validación está en
[`ENTREGA.md`](ENTREGA.md).
