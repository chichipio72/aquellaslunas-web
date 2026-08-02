# Motor astronómico PHP

Este paquete contiene exclusivamente código astronómico PHP reutilizable. Sus
clases viven en `src/` bajo el namespace `AstronomyEngine`.

El motor recibe datos astronómicos y devuelve resultados. No depende de la
interfaz del laboratorio, Docker, la API Python ni código de comparación o
benchmark.

Los contratos se incorporarán cálculo por cálculo. Cuando exista más de una
implementación de un cálculo, compartirán el contrato específico de ese
cálculo; no se crea todavía una interfaz general sin requisitos concretos.

`LunarEventCalculator` encuentra fases principales exactas (perfil `preciso`),
perigeos, apogeos, pasos por nodos, extremos de libración y conjunciones
(perfil `normal`). Las fases usan Meeus capítulo 49; la libración usa la
formulación óptica y física de Meeus capítulo 53; las conjunciones combinan
elementos planetarios aproximados publicados por JPL con el catálogo
Hipparcos/SIMBAD utilizado por la referencia.

`LunarEclipseCalculator` añade eclipses lunares globales mediante geometría
dinámica de sombra y una serie completa de Meeus capítulo 47 aislada en
`MeeusEclipsePositionCalculator`. La batería comparativa 2025–2030 cubre los
14 eventos de referencia y coincide en sus clasificaciones. No incluye
eclipses solares ni circunstancias locales.

`SolarEclipseCalculator` detecta y clasifica eclipses solares globales con el
método analítico de Meeus capítulo 54. Devuelve máximo, `gamma`, `u`, magnitud
parcial y geometría auxiliar. No calcula circunstancias locales, mapas ni
contactos globales distintos de MAX; el bloque permanece pendiente de la
validación manual de intervalos del laboratorio.

`LunarEclipseLocalCalculator` y `SolarEclipseLocalCalculator` añaden
circunstancias para un `EclipseObserver`. El bloque lunar evalúa individualmente
los contactos globales y cruces de horizonte; el solar resuelve discos
aparentes topocéntricos, C1–C4, clase, magnitud y cobertura local. Este bloque
permanece pendiente de validación manual y no está declarado listo para
producto.

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
El contrato está documentado en
[`../docs/php-moon-image.md`](../docs/php-moon-image.md).

`TonightCalculator` compone posiciones solares, lunares, planetarias y de un
catálogo fijo para producir ventanas observables entre mediodías, con los
modos `summary` y `full`. Los datos Hipparcos/SIMBAD están separados en
`TonightCatalog`. El contrato y sus diferencias de modelo están en
[`../docs/php-tonight.md`](../docs/php-tonight.md).

La guía de traslado, dependencias, inventario y estado de validación está en
[`ENTREGA.md`](ENTREGA.md).
