<?php

return [
    'version' => 1,

    'visible' => true,

    'imagen' => '31998a632c82a0136dde1e4466ff6618181c72932e7a47aa5e4289e8d93c1bbc.jpg',

    'imagen_posicion_x' => 50.0,

    'imagen_posicion_y' => 50.0,

    'titulo' => '¿Qué relación tiene la Pascua con la Luna llena?',

    'resumen' => 'La fecha de la Pascua cambia todos los años porque depende de la Luna. Desde hace siglos se calcula a partir de la primera Luna llena que sigue al equinoccio de marzo.',

    'palabras_clave' => [
        'Pascua',
        'Semana Santa',
        'Luna llena',
        'equinoccio',
        'calendario',
        'primavera',
        'calendario lunar',
    ],

    'relaciones' => [
        'moon_phase',
        'full_moon',
        'calendar',
    ],

    'sabias_que' => [
        [
            'id' => 'sq-01',
            'visible' => true,
            'titulo' => '¿Sabías que la Pascua puede celebrarse con más de un mes de diferencia según el año?',
            'respuesta' => 'Como depende de la primera Luna llena posterior al equinoccio de marzo, la fecha puede variar entre fines de marzo y fines de abril.',
            'imagen' => NULL,
        ],
        [
            'id' => 'sq-02',
            'visible' => true,
            'titulo' => '¿Sabías que la Luna llena de Pascua no siempre coincide con la Luna llena astronómica?',
            'respuesta' => 'Las iglesias cristianas utilizan un calendario eclesiástico basado en reglas tradicionales, por lo que en algunos años puede diferir levemente de las fases calculadas por la astronomía.',
            'imagen' => NULL,
        ],
    ],

    'trivias' => [
        [
            'id' => 'tr-01',
            'visible' => true,
            'pregunta' => '¿Qué fase de la Luna interviene en el cálculo de la fecha de Pascua?',
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
                    'explicacion' => 'La Pascua se determina a partir de la primera Luna llena posterior al equinoccio de marzo.',
                ],
                [
                    'texto' => 'Cuarto menguante',
                ],
            ],
        ],
    ],

    'articulo' => <<<'MD'
# ¿Qué relación tiene la Pascua con la Luna llena?

La fecha de la Pascua cambia todos los años porque no depende únicamente del calendario. Su cálculo también tiene en cuenta el movimiento de la Luna.

## ¿Cómo se calcula? {#como-se-calcula}

La regla utilizada por la mayoría de las iglesias cristianas establece que la Pascua se celebra el **primer domingo después de la primera Luna llena posterior al equinoccio de marzo**.

Como la fecha de esa Luna llena cambia cada año, también cambia la fecha de la Pascua.

## ¿Cuándo puede celebrarse? {#cuando-se-celebra}

La Pascua puede caer entre el **22 de marzo y el 25 de abril**.

Si la primera Luna llena ocurre poco después del equinoccio, la celebración será temprana. Si tarda varias semanas en llegar, la Pascua también se retrasa.

## ¿Por qué se eligió esta regla? {#origen}

Desde los primeros siglos del cristianismo se buscó que la celebración mantuviera una relación con la festividad judía de la Pascua, que también está ligada al calendario lunar.

Con el tiempo se estableció una regla común para que la fecha pudiera calcularse de manera uniforme.

## El calendario eclesiástico {#calendario-eclesiastico}

Aunque la regla se basa en la Luna llena, no siempre se utiliza la fase astronómica observada en el cielo.

Las iglesias emplean un calendario eclesiástico que aproxima las fases de la Luna mediante un método tradicional. En algunos años la fecha obtenida puede diferir unos días respecto de la Luna llena calculada por la astronomía.

## Una tradición que une el Sol y la Luna

La fecha de la Pascua depende de dos fenómenos astronómicos: el equinoccio de marzo, relacionado con el movimiento de la Tierra alrededor del Sol, y la Luna llena, determinada por la órbita de la Luna.

Por eso es una de las festividades más conocidas cuyo calendario está directamente vinculado con la astronomía.
MD,

];
