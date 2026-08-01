#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/content-system.php';
require_once __DIR__ . '/../includes/web-database.php';

const IMPORT_EXIT_OK = 0;
const IMPORT_EXIT_FAILURE = 1;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse en CLI.\n");
    exit(IMPORT_EXIT_FAILURE);
}

/**
 * @return array{dry_run: bool, help: bool}
 */
function parseOptions(array $argv): array
{
    $options = ['dry_run' => false, 'help' => false];
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--dry-run') {
            $options['dry_run'] = true;
            continue;
        }
        if ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
            continue;
        }
        throw new InvalidArgumentException('Argumento no reconocido: ' . $argument);
    }
    return $options;
}

function printHelp(): void
{
    echo "Uso:\n";
    echo "  php scripts/import-content-to-web-db.php [--dry-run]\n\n";
    echo "Opciones:\n";
    echo "  --dry-run   Valida y muestra lo que importaria sin escribir en la base.\n";
    echo "  -h, --help  Muestra esta ayuda.\n";
}

/**
 * @return array{slug: string, filename: string, raw: array, article: array, trivias: array, facts: array, errors: array<int, array{field: string, message: string}>}
 */
function loadArticleCandidate(string $directory, string $filename): array
{
    $slug = astronomyContentSlugFromFilename($filename);
    if ($slug === null) {
        return [
            'slug' => $filename,
            'filename' => $filename,
            'raw' => [],
            'article' => [],
            'trivias' => [],
            'facts' => [],
            'errors' => [astronomyContentError('archivo', 'El nombre no forma un slug valido.')],
        ];
    }

    try {
        $raw = (static fn(string $path) => require $path)($directory . '/' . $filename);
    } catch (Throwable $exception) {
        return [
            'slug' => $slug,
            'filename' => $filename,
            'raw' => [],
            'article' => [],
            'trivias' => [],
            'facts' => [],
            'errors' => [astronomyContentError('archivo', 'El archivo produjo un error al cargarse: ' . $exception->getMessage())],
        ];
    }

    $article = astronomyContentValidateArticle($slug, $raw, $filename);
    $errors = $article['errors'] ?? [];

    $trivias = [];
    $triviaById = [];
    foreach (array_values(is_array($raw['trivias'] ?? null) ? $raw['trivias'] : []) as $index => $triviaRaw) {
        $trivia = astronomyContentValidateTrivia($triviaRaw, $slug, $index);
        if (isset($triviaById[$trivia['id']])) {
            $trivia['errors'][] = astronomyContentError('trivias.' . $index . '.id', 'El identificador esta repetido dentro del articulo.');
        }
        $trivia['valid'] = ($trivia['errors'] ?? []) === [];
        $triviaById[$trivia['id']] = $trivia;
        $trivias[] = $trivia;
        $errors = array_merge($errors, $trivia['errors'] ?? []);
    }

    $facts = [];
    $factIds = [];
    foreach (array_values(is_array($raw['sabias_que'] ?? null) ? $raw['sabias_que'] : []) as $index => $factRaw) {
        $fact = astronomyContentValidateFact($factRaw, $slug, $index);
        if (isset($factIds[$fact['id']])) {
            $fact['errors'][] = astronomyContentError('sabias_que.' . $index . '.id', 'El identificador esta repetido dentro del articulo.');
        }
        $fact['valid'] = ($fact['errors'] ?? []) === [];
        $factIds[$fact['id']] = true;
        $facts[] = $fact;
        $errors = array_merge($errors, $fact['errors'] ?? []);
    }

    foreach ($article['components'] ?? [] as $index => $component) {
        if (($component['type'] ?? null) !== 'trivia' || !is_array($component['attributes'] ?? null)) {
            continue;
        }
        $componentTriviaId = (string) ($component['attributes']['id'] ?? '');
        if (!isset($triviaById[$componentTriviaId]) || ($triviaById[$componentTriviaId]['valid'] ?? false) !== true) {
            $errors[] = astronomyContentError(
                'articulo.componentes.' . $index,
                'La trivia incrustada no existe o tiene errores.'
            );
        }
    }

    return [
        'slug' => $slug,
        'filename' => $filename,
        'raw' => is_array($raw) ? $raw : [],
        'article' => $article,
        'trivias' => $trivias,
        'facts' => $facts,
        'errors' => $errors,
    ];
}

/**
 * @param array{slug: string, raw: array} $candidate
 */
function articleInsertPayload(array $candidate): array
{
    $raw = $candidate['raw'];
    return [
        ':slug' => $candidate['slug'],
        ':version' => (int) ($raw['version'] ?? 1),
        ':visible' => ($raw['visible'] ?? false) ? 1 : 0,
        ':titulo' => (string) ($raw['titulo'] ?? ''),
        ':resumen' => (string) ($raw['resumen'] ?? ''),
        ':markdown' => (string) ($raw['articulo'] ?? ''),
        ':imagen_principal' => astronomyContentNonEmptyString($raw['imagen'] ?? null) ? trim((string) $raw['imagen']) : null,
    ];
}

function importCandidate(PDO $connection, array $candidate, bool $dryRun): array
{
    $raw = $candidate['raw'];

    $wordCount = count(is_array($raw['palabras_clave'] ?? null) ? $raw['palabras_clave'] : []);
    $relationCount = count(is_array($raw['relaciones'] ?? null) ? $raw['relaciones'] : []);
    $triviaCount = count(is_array($raw['trivias'] ?? null) ? $raw['trivias'] : []);
    $factCount = count(is_array($raw['sabias_que'] ?? null) ? $raw['sabias_que'] : []);
    $optionCount = 0;
    foreach (is_array($raw['trivias'] ?? null) ? $raw['trivias'] : [] as $trivia) {
        $optionCount += count(is_array($trivia['opciones'] ?? null) ? $trivia['opciones'] : []);
    }

    $existsQuery = $connection->prepare('SELECT id FROM contenido_articulos WHERE slug = :slug LIMIT 1');
    $existsQuery->execute([':slug' => $candidate['slug']]);
    $existingId = $existsQuery->fetchColumn();
    if ($existingId !== false) {
        return [
            'status' => 'skipped',
            'slug' => $candidate['slug'],
            'article_id' => (int) $existingId,
            'trivias' => 0,
            'options' => 0,
            'facts' => 0,
            'words' => 0,
            'relations' => 0,
            'reason' => 'Ya existe un articulo con ese slug.',
        ];
    }

    if ($dryRun) {
        return [
            'status' => 'imported',
            'slug' => $candidate['slug'],
            'article_id' => null,
            'trivias' => $triviaCount,
            'options' => $optionCount,
            'facts' => $factCount,
            'words' => $wordCount,
            'relations' => $relationCount,
            'reason' => null,
        ];
    }

    $insertArticle = $connection->prepare(
        'INSERT INTO contenido_articulos (slug, version, visible, titulo, resumen, markdown, imagen_principal) '
        . 'VALUES (:slug, :version, :visible, :titulo, :resumen, :markdown, :imagen_principal)'
    );
    $insertWord = $connection->prepare(
        'INSERT INTO contenido_articulos_palabras_clave (articulo_id, palabra_clave, orden) '
        . 'VALUES (:articulo_id, :palabra_clave, :orden)'
    );
    $insertRelation = $connection->prepare(
        'INSERT INTO contenido_articulos_relaciones (articulo_id, slug_relacionado, orden) '
        . 'VALUES (:articulo_id, :slug_relacionado, :orden)'
    );
    $insertTrivia = $connection->prepare(
        'INSERT INTO contenido_trivias (articulo_id, codigo, pregunta, imagen, visible, orden) '
        . 'VALUES (:articulo_id, :codigo, :pregunta, :imagen, :visible, :orden)'
    );
    $insertOption = $connection->prepare(
        'INSERT INTO contenido_trivia_opciones (trivia_id, texto, correcta, explicacion, orden) '
        . 'VALUES (:trivia_id, :texto, :correcta, :explicacion, :orden)'
    );
    $insertFact = $connection->prepare(
        'INSERT INTO contenido_sabias_que (articulo_id, codigo, frase, detalle, imagen, visible, orden) '
        . 'VALUES (:articulo_id, :codigo, :frase, :detalle, :imagen, :visible, :orden)'
    );

    $connection->beginTransaction();
    try {
        $insertArticle->execute(articleInsertPayload($candidate));
        $articleId = (int) $connection->lastInsertId();

        foreach (array_values(is_array($raw['palabras_clave'] ?? null) ? $raw['palabras_clave'] : []) as $index => $word) {
            $insertWord->execute([
                ':articulo_id' => $articleId,
                ':palabra_clave' => (string) $word,
                ':orden' => $index,
            ]);
        }

        foreach (array_values(is_array($raw['relaciones'] ?? null) ? $raw['relaciones'] : []) as $index => $relatedSlug) {
            $insertRelation->execute([
                ':articulo_id' => $articleId,
                ':slug_relacionado' => (string) $relatedSlug,
                ':orden' => $index,
            ]);
        }

        foreach (array_values(is_array($raw['trivias'] ?? null) ? $raw['trivias'] : []) as $triviaIndex => $trivia) {
            $insertTrivia->execute([
                ':articulo_id' => $articleId,
                ':codigo' => (string) ($trivia['id'] ?? ''),
                ':pregunta' => (string) ($trivia['pregunta'] ?? ''),
                ':imagen' => astronomyContentNonEmptyString($trivia['imagen'] ?? null) ? trim((string) $trivia['imagen']) : null,
                ':visible' => ($trivia['visible'] ?? false) ? 1 : 0,
                ':orden' => $triviaIndex,
            ]);
            $triviaId = (int) $connection->lastInsertId();

            foreach (array_values(is_array($trivia['opciones'] ?? null) ? $trivia['opciones'] : []) as $optionIndex => $option) {
                $hasExplanation = is_array($option)
                    && array_key_exists('explicacion', $option)
                    && astronomyContentNonEmptyString($option['explicacion'] ?? null);
                $insertOption->execute([
                    ':trivia_id' => $triviaId,
                    ':texto' => (string) ($option['texto'] ?? ''),
                    ':correcta' => $hasExplanation ? 1 : 0,
                    ':explicacion' => $hasExplanation ? (string) $option['explicacion'] : null,
                    ':orden' => $optionIndex,
                ]);
            }
        }

        foreach (array_values(is_array($raw['sabias_que'] ?? null) ? $raw['sabias_que'] : []) as $factIndex => $fact) {
            $insertFact->execute([
                ':articulo_id' => $articleId,
                ':codigo' => (string) ($fact['id'] ?? ''),
                ':frase' => (string) ($fact['titulo'] ?? ''),
                ':detalle' => (string) ($fact['respuesta'] ?? ''),
                ':imagen' => astronomyContentNonEmptyString($fact['imagen'] ?? null) ? trim((string) $fact['imagen']) : null,
                ':visible' => ($fact['visible'] ?? false) ? 1 : 0,
                ':orden' => $factIndex,
            ]);
        }

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }

    return [
        'status' => 'imported',
        'slug' => $candidate['slug'],
        'article_id' => $articleId,
        'trivias' => $triviaCount,
        'options' => $optionCount,
        'facts' => $factCount,
        'words' => $wordCount,
        'relations' => $relationCount,
        'reason' => null,
    ];
}

function main(array $argv): int
{
    try {
        $options = parseOptions($argv);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n\n");
        printHelp();
        return IMPORT_EXIT_FAILURE;
    }

    if ($options['help']) {
        printHelp();
        return IMPORT_EXIT_OK;
    }

    $dryRun = $options['dry_run'];
    $contentDirectory = ASTRONOMY_CONTENT_DIRECTORY;

    if (!is_dir($contentDirectory)) {
        fwrite(STDERR, 'No existe el directorio de contenidos: ' . $contentDirectory . "\n");
        return IMPORT_EXIT_FAILURE;
    }

    $files = array_values(array_filter(
        scandir($contentDirectory) ?: [],
        static fn(string $filename): bool => pathinfo($filename, PATHINFO_EXTENSION) === 'php'
    ));
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    $summary = [
        'read' => 0,
        'imported' => 0,
        'skipped' => 0,
        'trivias' => 0,
        'options' => 0,
        'facts' => 0,
        'errors' => [],
    ];

    $connection = getWebDatabaseConnection();
    $connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    echo "Importador de contenidos (" . ($dryRun ? 'dry-run' : 'escritura real') . ")\n";
    echo "Directorio: " . $contentDirectory . "\n\n";

    foreach ($files as $filename) {
        $summary['read']++;
        $candidate = loadArticleCandidate($contentDirectory, $filename);

        if (($candidate['errors'] ?? []) !== []) {
            $messages = array_map(
                static fn(array $error): string => ($error['field'] ?? 'contenido') . ': ' . ($error['message'] ?? 'Error'),
                $candidate['errors']
            );
            $summary['errors'][] = [
                'slug' => $candidate['slug'],
                'message' => implode(' | ', $messages),
            ];
            echo '[ERROR] ' . $candidate['slug'] . ' -> ' . implode(' | ', $messages) . "\n";
            continue;
        }

        try {
            $result = importCandidate($connection, $candidate, $dryRun);
        } catch (Throwable $exception) {
            $summary['errors'][] = [
                'slug' => $candidate['slug'],
                'message' => $exception->getMessage(),
            ];
            echo '[ERROR] ' . $candidate['slug'] . ' -> ' . $exception->getMessage() . "\n";
            continue;
        }

        if (($result['status'] ?? '') === 'skipped') {
            $summary['skipped']++;
            echo '[OMITIDO] ' . $candidate['slug'] . ' -> ' . ($result['reason'] ?? 'sin motivo') . "\n";
            continue;
        }

        $summary['imported']++;
        $summary['trivias'] += (int) ($result['trivias'] ?? 0);
        $summary['options'] += (int) ($result['options'] ?? 0);
        $summary['facts'] += (int) ($result['facts'] ?? 0);
        echo '[IMPORTADO] ' . $candidate['slug']
            . ' -> trivias=' . (int) ($result['trivias'] ?? 0)
            . ', opciones=' . (int) ($result['options'] ?? 0)
            . ', sabias_que=' . (int) ($result['facts'] ?? 0)
            . ($dryRun ? ' (simulado)' : '')
            . "\n";
    }

    echo "\nResumen\n";
    echo 'Articulos leidos: ' . $summary['read'] . "\n";
    echo 'Articulos importados: ' . $summary['imported'] . "\n";
    echo 'Articulos omitidos: ' . $summary['skipped'] . "\n";
    echo 'Trivias importadas: ' . $summary['trivias'] . "\n";
    echo 'Opciones importadas: ' . $summary['options'] . "\n";
    echo 'Sabias que importados: ' . $summary['facts'] . "\n";
    echo 'Errores encontrados: ' . count($summary['errors']) . "\n";

    if ($summary['errors'] !== []) {
        echo "\nDetalle de errores\n";
        foreach ($summary['errors'] as $entry) {
            echo '- ' . ($entry['slug'] ?? 'sin-slug') . ': ' . ($entry['message'] ?? 'Error desconocido') . "\n";
        }
    }

    return $summary['errors'] === [] ? IMPORT_EXIT_OK : IMPORT_EXIT_FAILURE;
}

exit(main($argv));
