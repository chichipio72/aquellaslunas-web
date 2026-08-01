<?php

require_once __DIR__ . '/../includes/current-datetime.php';

function appEnvironmentAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function appEnvironmentSet(?string $appEnv, ?string $simulation, ?string $content): void
{
    putenv($appEnv === null ? 'APP_ENV' : 'APP_ENV=' . $appEnv);
    putenv($simulation === null ? 'LOCAL_TIME_SIMULATION_ENABLED' : 'LOCAL_TIME_SIMULATION_ENABLED=' . $simulation);
    putenv($content === null ? 'CONTENT_ENABLED_IN_PRODUCTION' : 'CONTENT_ENABLED_IN_PRODUCTION=' . $content);
}

appEnvironmentSet('local', 'true', 'false');
appEnvironmentAssert(appEnvironment() === 'local', 'El escenario local no fue reconocido.');
appEnvironmentAssert(isLocalEnvironment(), 'isLocalEnvironment falló en local.');
appEnvironmentAssert(!isProductionEnvironment(), 'Local fue clasificado también como producción.');
appEnvironmentAssert(astronomyLocalTimeSimulationEnabled(), 'El simulador no se habilitó en local.');
appEnvironmentAssert(isContentEnabled(), 'El contenido público quedó condicionado por APP_ENV en local.');

appEnvironmentSet('local', 'false', null);
appEnvironmentAssert(isLocalEnvironment(), 'El entorno local dependió de la bandera del simulador.');
appEnvironmentAssert(!astronomyLocalTimeSimulationEnabled(), 'El simulador ignoró su bandera deshabilitada.');
appEnvironmentAssert(isContentEnabled(), 'El contenido local dependió de una bandera de entorno obsoleta.');

appEnvironmentSet('production', 'true', 'false');
appEnvironmentAssert(isProductionEnvironment(), 'Producción explícita no fue reconocida.');
appEnvironmentAssert(!astronomyLocalTimeSimulationEnabled(), 'El simulador se habilitó en producción sin sesión admin.');
appEnvironmentAssert(isContentEnabled(), 'El contenido público dependió de APP_ENV=production.');

appEnvironmentSet(null, null, null);
appEnvironmentAssert(appEnvironment() === 'production', 'La ausencia de APP_ENV no asumió producción.');
appEnvironmentAssert(!astronomyLocalTimeSimulationEnabled(), 'La ausencia de variables habilitó el simulador.');
appEnvironmentAssert(isContentEnabled(), 'La ausencia de APP_ENV deshabilitó contenido público.');

appEnvironmentSet('development', 'true', 'false');
appEnvironmentAssert(isProductionEnvironment(), 'Un APP_ENV inválido no asumió producción.');
appEnvironmentAssert(!astronomyLocalTimeSimulationEnabled(), 'Un APP_ENV inválido habilitó el simulador.');
appEnvironmentAssert(isContentEnabled(), 'Un APP_ENV inválido alteró la publicación de contenido.');

appEnvironmentSet('production', 'false', 'true');
appEnvironmentAssert(!isLocalEnvironment(), 'La publicación de contenido habilitó funciones locales.');
appEnvironmentAssert(!astronomyLocalTimeSimulationEnabled(), 'La publicación de contenido habilitó el simulador.');
appEnvironmentAssert(isContentEnabled(), 'El contenido explícitamente habilitado no se publicó.');

appEnvironmentSet(null, null, null);

echo "app-environment: ok\n";
