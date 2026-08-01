<?php

return [
    'version' => 1,

    'visible' => true,

    'imagen' => '301b83e6b4a54232c828b70f983a22177173ad2ce2b606629d9155eb2fe335de.jpg',

    'imagen_posicion_x' => 50.0,

    'imagen_posicion_y' => 50.0,

    'titulo' => '¿Qué es una superluna?',

    'resumen' => 'Una superluna ocurre cuando la Luna llena coincide con un momento en que nuestro satélite se encuentra cerca de la Tierra. Aunque el cambio es real, suele ser más sutil de lo que muchas veces se cree.',

    'palabras_clave' => [
        'superluna',
        'perigeo',
        'luna llena',
        'distancia Tierra Luna',
        'órbita lunar',
        'tamaño aparente',
        'brillo de la Luna',
    ],

    'relaciones' => [
        'moon',
        'luna_llena',
        'perigeo',
    ],

    'sabias_que' => [
        [
            'id' => 'sq-01',
            'visible' => true,
            'titulo' => '¿Sabías que una superluna no siempre es la Luna llena más cercana del año?',
            'respuesta' => 'Puede haber varias superlunas en un mismo año. La más cercana a la Tierra suele llamarse popularmente "la superluna del año".',
            'imagen' => NULL,
        ],
        [
            'id' => 'sq-02',
            'visible' => true,
            'titulo' => '¿Sabías que el término "superluna" no nació en la astronomía?',
            'respuesta' => 'Fue propuesto por un astrólogo en 1979 y luego se popularizó en los medios. Los astrónomos suelen hablar simplemente de una Luna llena cercana al perigeo.',
            'imagen' => NULL,
        ],
    ],

    'trivias' => [
        [
            'id' => 'tr-01',
            'visible' => true,
            'pregunta' => '¿Qué hace que una Luna llena sea considerada una superluna?',
            'imagen' => NULL,
            'opciones' => [
                [
                    'texto' => 'Que ocurra durante el invierno',
                ],
                [
                    'texto' => 'Que coincida con un eclipse',
                ],
                [
                    'texto' => 'Que ocurra cerca del perigeo',
                    'explicacion' => 'Una superluna es una Luna llena que ocurre cuando la Luna está cerca del perigeo, el punto de su órbita más próximo a la Tierra.',
                ],
                [
                    'texto' => 'Que se vea de color rojizo',
                ],
            ],
        ],
        [
            'id' => 'tr-02',
            'visible' => true,
            'pregunta' => '¿Cuánto más grande puede verse aproximadamente una superluna respecto de una microluna?',
            'imagen' => NULL,
            'opciones' => [
                [
                    'texto' => 'Alrededor de un 14%',
                    'explicacion' => 'La diferencia de diámetro aparente entre una superluna y una microluna ronda el 14%, aunque resulta difícil de apreciar sin compararlas.',
                ],
                [
                    'texto' => 'Un 50%',
                ],
                [
                    'texto' => 'El doble',
                ],
                [
                    'texto' => 'No existe ninguna diferencia',
                ],
            ],
        ],
    ],

    'articulo' => <<<'MD'
# ¿Qué es una superluna?

Una superluna ocurre cuando la Luna llena coincide con un momento en que nuestro satélite se encuentra cerca del punto más próximo de su órbita alrededor de la Tierra.

## ¿Por qué ocurre? {#que-es}

La órbita de la Luna no es un círculo perfecto, sino una elipse.

Por eso, a lo largo de cada mes la distancia entre la Tierra y la Luna cambia ligeramente.

El punto de mayor cercanía recibe el nombre de **perigeo**, mientras que el más lejano se llama **apogeo**.

## ¿Se ve realmente más grande? {#que-tan-grande}

Sí, aunque el cambio suele ser menos llamativo de lo que muestran muchas fotografías publicadas en internet.

Durante una superluna, el diámetro aparente puede ser aproximadamente un 14 % mayor que durante una microluna.

También puede verse cerca de un 30 % más brillante.

## ¿Vale la pena observarla? {#observar}

Aunque la diferencia de tamaño es difícil de apreciar a simple vista, una superluna sigue siendo una excelente oportunidad para observar y fotografiar nuestro satélite.

Cuando aparece cerca del horizonte puede dar además la impresión de ser enorme debido a una ilusión óptica conocida como **la ilusión lunar**.

## Un nombre muy popular {#nombre}

El término **superluna** no forma parte de la nomenclatura astronómica tradicional.

Se hizo popular porque resulta sencillo de comprender y describir, aunque los astrónomos suelen referirse a este fenómeno como una **Luna llena cercana al perigeo**.
MD,

];
