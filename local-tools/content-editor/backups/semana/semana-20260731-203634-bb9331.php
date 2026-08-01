<?php

return [
    'version' => 1,

    'visible' => true,

    'imagen' => NULL,

    'imagen_posicion_x' => 50.0,

    'imagen_posicion_y' => 50.0,

    'titulo' => '¿Qué relación tiene la Luna con los días de la semana?',

    'resumen' => 'Los nombres de varios días de la semana conservan una antigua relación con los astros. La Luna, el Sol y los planetas visibles dieron nombre a los días en distintas culturas e idiomas.',

    'palabras_clave' => [
        'Luna',
        'lunes',
        'días de la semana',
        'calendario',
        'planetas',
        'etimología',
        'historia de la astronomía',
    ],

    'relaciones' => [
        'moon',
        'calendar',
        'history',
    ],

    'sabias_que' => [
        [
            'id' => 'sq-01',
            'visible' => true,
            'titulo' => '¿Sabías que Monday conserva una referencia astronómica muy antigua?',
            'respuesta' => 'La palabra inglesa Monday proviene de una expresión que significa "día de la Luna". Es el equivalente del antiguo nombre latino que dio origen a nuestro lunes.',
            'imagen' => NULL,
            'referencia' => [
                'articulo' => 'la-luna-y-los-dias-de-la-semana',
                'ancla' => 'otros-idiomas',
            ],
        ],
        [
            'id' => 'sq-02',
            'visible' => true,
            'titulo' => '¿Sabías que el alemán conserva la misma relación entre un día de la semana y la Luna?',
            'respuesta' => 'En alemán, Montag significa literalmente "día de la Luna". La relación se mantuvo durante siglos, aunque hoy la mayoría de las personas usa el nombre sin pensar en su origen.',
            'imagen' => NULL,
            'referencia' => [
                'articulo' => 'la-luna-y-los-dias-de-la-semana',
                'ancla' => 'otros-idiomas',
            ],
        ],
    ],

    'trivias' => [
        [
            'id' => 'tr-01',
            'visible' => true,
            'pregunta' => '¿Cuál de estas palabras significa originalmente "día de la Luna"?',
            'imagen' => NULL,
            'opciones' => [
                [
                    'texto' => 'Thursday',
                ],
                [
                    'texto' => 'Monday',
                    'explicacion' => 'Monday proviene de una antigua expresión inglesa que significa "día de la Luna".',
                ],
                [
                    'texto' => 'Saturday',
                ],
                [
                    'texto' => 'Sunday',
                ],
            ],
            'referencia' => [
                'articulo' => 'la-luna-y-los-dias-de-la-semana',
                'ancla' => 'otros-idiomas',
            ],
        ],
        [
            'id' => 'tr-02',
            'visible' => true,
            'pregunta' => '¿Cuál de estos días no conserva en español el nombre de un astro o planeta?',
            'imagen' => NULL,
            'opciones' => [
                [
                    'texto' => 'Martes',
                ],
                [
                    'texto' => 'Miércoles',
                ],
                [
                    'texto' => 'Viernes',
                ],
                [
                    'texto' => 'Domingo',
                    'explicacion' => 'Domingo tiene un origen cristiano y significa "día del Señor". No conserva el nombre de un astro o planeta.',
                ],
            ],
            'referencia' => [
                'articulo' => 'la-luna-y-los-dias-de-la-semana',
                'ancla' => 'los-siete-astros',
            ],
        ],
        [
            'id' => 'tr-03',
            'visible' => true,
            'pregunta' => '¿Qué astro dio origen al nombre Sonntag en alemán?',
            'imagen' => NULL,
            'opciones' => [
                [
                    'texto' => 'La Luna',
                ],
                [
                    'texto' => 'El Sol',
                    'explicacion' => 'Sonntag significa "día del Sol". Es equivalente al inglés Sunday.',
                ],
                [
                    'texto' => 'Saturno',
                ],
                [
                    'texto' => 'Mercurio',
                ],
            ],
            'referencia' => [
                'articulo' => 'la-luna-y-los-dias-de-la-semana',
                'ancla' => 'otros-idiomas',
            ],
        ],
    ],

    'articulo' => <<<'MD'
# ¿Qué relación tiene la Luna con los días de la semana?

Los nombres de los días parecen formar parte natural del calendario, pero varios de ellos esconden una antigua relación con el cielo.

La Luna, el Sol y los planetas visibles a simple vista dieron nombre a los días de la semana en distintas culturas.

## Los siete astros {#los-siete-astros}

En la antigüedad se conocían siete astros que parecían desplazarse por el cielo respecto de las estrellas: el Sol, la Luna, Mercurio, Venus, Marte, Júpiter y Saturno.

Cada uno de ellos fue asociado con un día de la semana.

Esta tradición pasó por distintas culturas hasta llegar al mundo romano y, más tarde, a muchos de los idiomas actuales.

## La Luna y el lunes {#luna-y-lunes}

En latín, el lunes era llamado *dies Lunae*, que significa **día de la Luna**.

Con el paso del tiempo, esa expresión dio origen a la palabra lunes en español y a nombres equivalentes en otros idiomas.

La relación ya no suele resultar evidente, pero sigue escondida en una palabra que usamos todas las semanas.

## Los planetas en nuestro calendario {#planetas-en-el-calendario}

Otros días conservan también sus antiguos nombres astronómicos.

Martes proviene de Marte, miércoles de Mercurio, jueves de Júpiter y viernes de Venus.

Sábado y domingo cambiaron de nombre en español por influencia de las tradiciones judía y cristiana.

## La misma historia en otros idiomas {#otros-idiomas}

En inglés, **Monday** significa originalmente "día de la Luna", mientras que **Sunday** conserva la relación con el Sol.

En alemán ocurre algo similar con **Montag**, el día de la Luna, y **Sonntag**, el día del Sol.

Otros nombres cambiaron porque algunos pueblos reemplazaron a los dioses romanos por divinidades propias, pero mantuvieron la idea de dedicar cada día a una figura vinculada con un astro.

## Una huella de la astronomía antigua

Los días de la semana son una pequeña huella de la forma en que las civilizaciones antiguas observaban y organizaban el cielo.

Cada vez que decimos lunes, martes o miércoles usamos, sin pensarlo, nombres nacidos de la Luna y los planetas.
MD,

];