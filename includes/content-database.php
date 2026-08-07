<?php

declare(strict_types=1);

/**
 * @return array{slug:string,titulo:string,resumen:string,visible:bool,actualizado_en:string,trivias_count:int,sabias_que_count:int}
 */
function astronomyContentDbMapListRow(array $row): array
{
    return [
        'slug' => (string) ($row['slug'] ?? ''),
        'titulo' => (string) ($row['titulo'] ?? ''),
        'resumen' => (string) ($row['resumen'] ?? ''),
        'visible' => ((int) ($row['visible'] ?? 0)) === 1,
        'actualizado_en' => (string) ($row['actualizado_en'] ?? ''),
        'trivias_count' => (int) ($row['trivias_count'] ?? 0),
        'sabias_que_count' => (int) ($row['sabias_que_count'] ?? 0),
    ];
}

/**
 * @return array<int, array{slug:string,titulo:string,resumen:string,visible:bool,actualizado_en:string,trivias_count:int,sabias_que_count:int}>
 */
function astronomyContentDbListArticles(PDO $connection): array
{
    $statement = $connection->query(
        'SELECT '
        . 'a.slug, a.titulo, a.resumen, a.visible, '
        . 'DATE_FORMAT(a.actualizado_en, "%Y-%m-%d %H:%i:%s") AS actualizado_en, '
        . 'COALESCE(t.cantidad, 0) AS trivias_count, '
        . 'COALESCE(s.cantidad, 0) AS sabias_que_count '
        . 'FROM contenido_articulos a '
        . 'LEFT JOIN (SELECT articulo_id, COUNT(*) AS cantidad FROM contenido_trivias GROUP BY articulo_id) t ON t.articulo_id = a.id '
        . 'LEFT JOIN (SELECT articulo_id, COUNT(*) AS cantidad FROM contenido_sabias_que GROUP BY articulo_id) s ON s.articulo_id = a.id '
        . 'ORDER BY a.slug ASC'
    );

    $articles = [];
    foreach ($statement as $row) {
        if (is_array($row)) {
            $articles[] = astronomyContentDbMapListRow($row);
        }
    }
    return $articles;
}

/**
 * @return array<int, array{field:string,message:string}>
 */
function astronomyContentDbGlobalWarnings(PDO $connection): array
{
    $warnings = [];
    $checks = [
        'contenido_trivias' => 'SELECT COUNT(*) FROM contenido_trivias t LEFT JOIN contenido_articulos a ON a.id = t.articulo_id WHERE a.id IS NULL',
        'contenido_sabias_que' => 'SELECT COUNT(*) FROM contenido_sabias_que s LEFT JOIN contenido_articulos a ON a.id = s.articulo_id WHERE a.id IS NULL',
        'contenido_trivia_opciones' => 'SELECT COUNT(*) FROM contenido_trivia_opciones o LEFT JOIN contenido_trivias t ON t.id = o.trivia_id WHERE t.id IS NULL',
    ];
    foreach ($checks as $field => $sql) {
        $count = (int) $connection->query($sql)->fetchColumn();
        if ($count > 0) {
            $warnings[] = astronomyContentError($field, 'Se detectaron ' . $count . ' registros huérfanos.');
        }
    }
    return $warnings;
}

function astronomyContentDbNullableText(mixed $value): ?string
{
    $text = trim((string) $value);
    return $text === '' ? null : $text;
}

/** @return array<string,mixed>|null */
function astronomyContentDbRandomHomeTrivia(PDO $connection, array $excludedIds = []): ?array
{
    $parameters = [];
    $excludedSql = '';
    if ($excludedIds !== []) {
        $placeholders = [];
        foreach (array_values($excludedIds) as $index => $id) {
            $key = ':excluded_' . $index;
            $placeholders[] = $key;
            $parameters[$key] = (int) $id;
        }
        $excludedSql = ' AND t.id NOT IN (' . implode(',', $placeholders) . ')';
    }
    $statement = $connection->prepare(
        'SELECT t.id AS database_id,t.codigo,t.pregunta,t.imagen,t.visible,a.slug '
        . 'FROM contenido_trivias t INNER JOIN contenido_articulos a ON a.id=t.articulo_id '
        . 'WHERE t.visible=1 AND a.visible=1' . $excludedSql . ' ORDER BY RAND() LIMIT 1'
    );
    $statement->execute($parameters);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/** @return list<array<string,mixed>> */
function astronomyContentDbHomeTriviaOptions(PDO $connection, int $triviaId): array
{
    $statement = $connection->prepare(
        'SELECT texto,correcta,explicacion FROM contenido_trivia_opciones '
        . 'WHERE trivia_id=:trivia_id ORDER BY orden ASC,id ASC'
    );
    $statement->execute(['trivia_id' => $triviaId]);
    return array_values(array_filter($statement->fetchAll(PDO::FETCH_ASSOC), 'is_array'));
}

/** @return array<string,mixed>|null */
function astronomyContentDbRandomHomeFact(PDO $connection, array $excludedIds = []): ?array
{
    $parameters = [];
    $excludedSql = '';
    if ($excludedIds !== []) {
        $placeholders = [];
        foreach (array_values($excludedIds) as $index => $id) {
            $key = ':excluded_' . $index;
            $placeholders[] = $key;
            $parameters[$key] = (int) $id;
        }
        $excludedSql = ' AND s.id NOT IN (' . implode(',', $placeholders) . ')';
    }
    $statement = $connection->prepare(
        'SELECT s.id AS database_id,s.codigo,s.frase,s.detalle,s.imagen,s.visible,a.slug '
        . 'FROM contenido_sabias_que s INNER JOIN contenido_articulos a ON a.id=s.articulo_id '
        . 'WHERE s.visible=1 AND a.visible=1' . $excludedSql . ' ORDER BY RAND() LIMIT 1'
    );
    $statement->execute($parameters);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/**
 * @return array{raw:array,metadata:array{slug:string,actualizado_en:string,article_id:int}}|null
 */
function astronomyContentDbLoadArticleRaw(PDO $connection, string $slug): ?array
{
    $articleStatement = $connection->prepare(
        'SELECT id, slug, version, visible, titulo, resumen, markdown, imagen_principal, '
        . 'DATE_FORMAT(actualizado_en, "%Y-%m-%d %H:%i:%s") AS actualizado_en '
        . 'FROM contenido_articulos WHERE slug = :slug LIMIT 1'
    );
    $articleStatement->execute(['slug' => $slug]);
    $article = $articleStatement->fetch();
    if (!is_array($article)) {
        return null;
    }

    $articleId = (int) ($article['id'] ?? 0);

    $wordsStatement = $connection->prepare(
        'SELECT palabra_clave FROM contenido_articulos_palabras_clave WHERE articulo_id = :articulo_id ORDER BY orden ASC, palabra_clave ASC'
    );
    $wordsStatement->execute(['articulo_id' => $articleId]);
    $palabrasClave = array_map(static fn(array $row): string => (string) ($row['palabra_clave'] ?? ''), $wordsStatement->fetchAll());

    $relationsStatement = $connection->prepare(
        'SELECT slug_relacionado FROM contenido_articulos_relaciones WHERE articulo_id = :articulo_id ORDER BY orden ASC, slug_relacionado ASC'
    );
    $relationsStatement->execute(['articulo_id' => $articleId]);
    $relaciones = array_map(static fn(array $row): string => (string) ($row['slug_relacionado'] ?? ''), $relationsStatement->fetchAll());

    $triviaStatement = $connection->prepare(
        'SELECT id, codigo, pregunta, imagen, visible, orden FROM contenido_trivias WHERE articulo_id = :articulo_id ORDER BY orden ASC, id ASC'
    );
    $triviaStatement->execute(['articulo_id' => $articleId]);
    $triviaRows = $triviaStatement->fetchAll();

    $optionStatement = $connection->prepare(
        'SELECT texto, correcta, explicacion, orden FROM contenido_trivia_opciones WHERE trivia_id = :trivia_id ORDER BY orden ASC, id ASC'
    );

    $trivias = [];
    foreach ($triviaRows as $triviaRow) {
        $triviaId = (int) ($triviaRow['id'] ?? 0);
        $optionStatement->execute(['trivia_id' => $triviaId]);
        $optionRows = $optionStatement->fetchAll();

        $options = [];
        foreach ($optionRows as $optionRow) {
            $option = [
                'texto' => (string) ($optionRow['texto'] ?? ''),
                '_orden' => (int) ($optionRow['orden'] ?? 0),
            ];
            if ((int) ($optionRow['correcta'] ?? 0) === 1) {
                $option['explicacion'] = $optionRow['explicacion'] !== null ? (string) $optionRow['explicacion'] : '';
            }
            $options[] = $option;
        }

        $trivias[] = [
            'id' => (string) ($triviaRow['codigo'] ?? ''),
            'visible' => ((int) ($triviaRow['visible'] ?? 0)) === 1,
            'pregunta' => (string) ($triviaRow['pregunta'] ?? ''),
            'imagen' => astronomyContentDbNullableText($triviaRow['imagen'] ?? null),
            'opciones' => $options,
            '_orden' => (int) ($triviaRow['orden'] ?? 0),
        ];
    }

    $factStatement = $connection->prepare(
        'SELECT codigo, frase, detalle, imagen, visible, orden FROM contenido_sabias_que WHERE articulo_id = :articulo_id ORDER BY orden ASC, id ASC'
    );
    $factStatement->execute(['articulo_id' => $articleId]);
    $factRows = $factStatement->fetchAll();

    $sabiasQue = [];
    foreach ($factRows as $factRow) {
        $sabiasQue[] = [
            'id' => (string) ($factRow['codigo'] ?? ''),
            'visible' => ((int) ($factRow['visible'] ?? 0)) === 1,
            'titulo' => (string) ($factRow['frase'] ?? ''),
            'respuesta' => (string) ($factRow['detalle'] ?? ''),
            'imagen' => astronomyContentDbNullableText($factRow['imagen'] ?? null),
            '_orden' => (int) ($factRow['orden'] ?? 0),
        ];
    }

    return [
        'raw' => [
            'version' => max(1, (int) ($article['version'] ?? 1)),
            'visible' => ((int) ($article['visible'] ?? 0)) === 1,
            'imagen' => astronomyContentDbNullableText($article['imagen_principal'] ?? null),
            'imagen_posicion_x' => 50,
            'imagen_posicion_y' => 50,
            'titulo' => (string) ($article['titulo'] ?? ''),
            'resumen' => (string) ($article['resumen'] ?? ''),
            'palabras_clave' => $palabrasClave,
            'relaciones' => $relaciones,
            'sabias_que' => $sabiasQue,
            'trivias' => $trivias,
            'articulo' => (string) ($article['markdown'] ?? ''),
        ],
        'metadata' => [
            'slug' => (string) ($article['slug'] ?? $slug),
            'actualizado_en' => (string) ($article['actualizado_en'] ?? ''),
            'article_id' => $articleId,
        ],
    ];
}
