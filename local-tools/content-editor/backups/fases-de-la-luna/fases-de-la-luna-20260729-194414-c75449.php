<?php

return [
    'version' => 1,

    'visible' => true,

    'imagen' => NULL,

    'imagen_posicion_x' => 50.0,

    'imagen_posicion_y' => 50.0,

    'titulo' => '¿Por qué la Luna tiene fases?',

    'resumen' => 'Las fases lunares no se producen por la sombra de la Tierra, sino por la forma en que vemos la parte iluminada de la Luna mientras orbita nuestro planeta.',

    'palabras_clave' => [
        'luna',
        'fases',
        'luna llena',
        'luna nueva',
        'cuarto creciente',
        'cuarto menguante',
        'gibosa',
        'iluminación',
    ],

    'relaciones' => [
        'fase_lunar',
        'luna_llena',
        'luna_nueva',
    ],

    'sabias_que' => [
        [
            'id' => 'sq-01',
            'visible' => true,
            'titulo' => '¿Sabías por qué la Luna llena sale al atardecer?',
            'respuesta' => 'Porque durante la Luna llena nuestro satélite se encuentra aproximadamente en dirección opuesta al Sol. Cuando el Sol se oculta por el oeste, la Luna aparece por el este.',
            'imagen' => NULL,
            'referencia' => [
                'articulo' => 'fases-de-la-luna',
                'ancla' => 'luna-llena',
            ],
        ],
        [
            'id' => 'sq-02',
            'visible' => true,
            'titulo' => '¿Sabías que siempre vemos casi la misma cara de la Luna?',
            'respuesta' => 'La Luna tarda prácticamente el mismo tiempo en girar sobre su eje que en dar una vuelta alrededor de la Tierra. Por eso siempre nos muestra casi el mismo hemisferio.',
            'imagen' => NULL,
            'referencia' => [
                'articulo' => 'fases-de-la-luna',
                'ancla' => 'rotacion',
            ],
        ],
    ],

    'trivias' => [
        [
            'id' => 'tr-01',
            'visible' => true,
            'pregunta' => '¿Qué produce las fases de la Luna?',
            'imagen' => NULL,
            'opciones' => [
                [
                    'texto' => 'La sombra de la Tierra',
                ],
                [
                    'texto' => 'La parte iluminada que vemos desde la Tierra',
                    'explicacion' => 'La Luna siempre tiene una mitad iluminada por el Sol. Las fases dependen de cuánto de esa mitad iluminada podemos ver desde nuestro planeta.',
                ],
                [
                    'texto' => 'Las nubes de la atmósfera',
                ],
                [
                    'texto' => 'Cambios en el brillo del Sol',
                ],
            ],
            'referencia' => [
                'articulo' => 'fases-de-la-luna',
                'ancla' => 'por-que-hay-fases',
            ],
        ],
        [
            'id' => 'tr-02',
            'visible' => true,
            'pregunta' => '¿En qué fase ocurre un eclipse lunar total?',
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
                    'explicacion' => 'Los eclipses lunares solo pueden ocurrir durante la Luna llena, cuando la Tierra queda aproximadamente entre el Sol y la Luna.',
                ],
                [
                    'texto' => 'Cuarto menguante',
                ],
            ],
            'referencia' => [
                'articulo' => 'fases-de-la-luna',
                'ancla' => 'luna-llena',
            ],
        ],
    ],

    'articulo' => <<<'MD'
# ¿Por qué la Luna tiene fases?

Todas las noches vemos que la Luna cambia lentamente de aspecto. A veces aparece como un delgado creciente, otras como un disco completo y, entre ambos extremos, adopta muchas formas intermedias.

Sin embargo, **la Luna nunca cambia realmente de forma**. Lo que cambia es la parte iluminada que podemos ver desde la Tierra.

## La Luna siempre está iluminada {#mitad-iluminada}

El Sol ilumina permanentemente una mitad de la Luna. La otra mitad permanece en la oscuridad.

A medida que la Luna gira alrededor de la Tierra, nosotros observamos esa mitad iluminada desde distintos ángulos.

## ¿Por qué hay fases? {#por-que-hay-fases}

Cuando la Luna se encuentra entre la Tierra y el Sol vemos principalmente su lado oscuro y se produce la Luna nueva.

Con el paso de los días comienza a hacerse visible una pequeña parte iluminada: aparece el creciente.

Semanas después llegamos a la Luna llena, momento en que vemos prácticamente toda la cara iluminada.

[[esquema tipo="fases-lunares"]]

## Luna llena {#luna-llena}

La Luna llena sale aproximadamente cuando el Sol se pone y permanece visible durante toda la noche.

Por eso suele ser la fase más fácil de observar.

## Rotación sincronizada {#rotacion}

Aunque la Luna gira sobre su eje, tarda prácticamente el mismo tiempo en completar una rotación que en dar una vuelta alrededor de la Tierra.

Gracias a esa sincronización vemos siempre casi el mismo hemisferio.

[[trivia id="tr-01"]]
MD,

];
