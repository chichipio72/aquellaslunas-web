<?php

return [
    'version' => 1,

    'visible' => true,

    'imagen' => '9f934b4a0ef9506d2c5cddb5880dd394518a0d7160aee97b4bfb21dc8179d7ac.jpg',

    'titulo' => '¿Qué ocurre durante un eclipse lunar?',

    'resumen' => 'Un eclipse lunar ocurre cuando la Luna atraviesa la sombra de la Tierra. Según su recorrido, el eclipse puede ser penumbral, parcial o total.',

    'palabras_clave' => [
        'eclipse lunar',
        'luna roja',
        'luna de sangre',
        'sombra de la Tierra',
        'umbra',
        'penumbra',
        'eclipse total',
        'eclipse parcial',
    ],

    'relaciones' => [
        'eclipse_lunar',
        'luna_llena',
        'umbra',
        'penumbra',
    ],

    'sabias_que' => [
        [
            'id' => 'sq-01',
            'visible' => true,
            'titulo' => '¿Sabías por qué la Luna puede verse rojiza durante un eclipse total?',
            'respuesta' => 'La atmósfera terrestre desvía hacia la sombra parte de la luz roja del Sol. Esa luz alcanza la superficie lunar y puede darle tonos cobrizos, anaranjados o rojizos.',
            'imagen' => NULL,
            'referencia' => [
                'articulo' => 'eclipses-lunares',
                'ancla' => 'color-rojizo',
            ],
        ],
        [
            'id' => 'sq-02',
            'visible' => true,
            'titulo' => '¿Sabías que no hay un eclipse lunar en cada Luna llena?',
            'respuesta' => 'La órbita de la Luna está inclinada respecto de la órbita terrestre alrededor del Sol. La mayoría de las veces, durante la Luna llena pasa por encima o por debajo de la sombra terrestre.',
            'imagen' => NULL,
            'referencia' => [
                'articulo' => 'eclipses-lunares',
                'ancla' => 'por-que-no-ocurren-cada-mes',
            ],
        ],
    ],

    'trivias' => [
        [
            'id' => 'tr-01',
            'visible' => true,
            'pregunta' => '¿En qué fase puede ocurrir un eclipse lunar?',
            'imagen' => NULL,
            'opciones' => [
                [
                    'texto' => 'Luna nueva',
                ],
                [
                    'texto' => 'Cuarto creciente',
                ],
                [
                    'texto' => 'Luna llena',
                    'explicacion' => 'Un eclipse lunar solo puede producirse durante la Luna llena, cuando la Tierra se encuentra aproximadamente entre el Sol y la Luna.',
                ],
                [
                    'texto' => 'Cuarto menguante',
                ],
            ],
            'referencia' => [
                'articulo' => 'eclipses-lunares',
                'ancla' => 'como-se-produce',
            ],
        ],
        [
            'id' => 'tr-02',
            'visible' => true,
            'pregunta' => '¿Qué parte de la sombra terrestre produce la etapa más oscura de un eclipse lunar?',
            'imagen' => NULL,
            'opciones' => [
                [
                    'texto' => 'La penumbra',
                ],
                [
                    'texto' => 'La umbra',
                    'explicacion' => 'La umbra es la zona central y más oscura de la sombra terrestre. Cuando la Luna entra en ella se produce la fase parcial o total del eclipse.',
                ],
                [
                    'texto' => 'La atmósfera lunar',
                ],
                [
                    'texto' => 'La corona solar',
                ],
                [
                    'texto' => 'El terminador lunar',
                ],
            ],
            'referencia' => [
                'articulo' => 'eclipses-lunares',
                'ancla' => 'umbra-y-penumbra',
            ],
        ],
    ],

    'articulo' => <<<'MD'
# ¿Qué ocurre durante un eclipse lunar?

Un eclipse lunar se produce cuando la Luna atraviesa la sombra de la Tierra. A diferencia de un eclipse solar, puede observarse al mismo tiempo desde una gran parte del lado nocturno del planeta.

## Cómo se produce {#como-se-produce}

Durante la Luna llena, la Tierra se encuentra aproximadamente entre el Sol y la Luna.

Si los tres cuerpos quedan suficientemente alineados, la sombra terrestre alcanza la superficie lunar y comienza el eclipse.

[[esquema tipo="eclipse-lunar-geometria"]]

## Umbra y penumbra {#umbra-y-penumbra}

La sombra de la Tierra no es uniforme.

La **penumbra** es la región exterior y menos oscura. Cuando la Luna la atraviesa, su brillo disminuye de manera sutil y el cambio puede ser difícil de notar.

La **umbra** es la región central y más oscura. Cuando una parte de la Luna entra en ella comienza un eclipse parcial. Si la Luna queda completamente dentro de la umbra, el eclipse es total.

## ¿Por qué la Luna se ve rojiza? {#color-rojizo}

Durante la totalidad, la Luna no suele desaparecer por completo.

La atmósfera terrestre filtra y desvía parte de la luz solar hacia el interior de la sombra. Los colores azulados se dispersan con mayor facilidad, mientras que una parte de la luz rojiza logra atravesar la atmósfera y alcanzar la Luna.

El color final puede variar entre gris oscuro, cobre, naranja o rojo profundo.

[[imagen src="eclipse-lunar-total.jpg" alt="Luna durante un eclipse lunar total"]]

## ¿Por qué no ocurren todos los meses? {#por-que-no-ocurren-cada-mes}

La órbita lunar está inclinada unos grados respecto del plano en el que la Tierra gira alrededor del Sol.

Por esa inclinación, en la mayoría de las lunas llenas nuestro satélite pasa por encima o por debajo de la sombra terrestre.

Los eclipses solo ocurren cuando la Luna llena se encuentra cerca de uno de los puntos donde ambos planos orbitales se cruzan.

## Tres tipos principales {#tipos-de-eclipse}

Un eclipse lunar puede ser:

- **Penumbral:** la Luna atraviesa solamente la penumbra.
- **Parcial:** una parte de la Luna entra en la umbra.
- **Total:** toda la Luna queda dentro de la umbra.

[[trivia id="tr-01"]]
MD,

];
