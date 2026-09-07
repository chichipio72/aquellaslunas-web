<?php

declare(strict_types=1);

const PHOTOGRAPHY_EDITORIAL_JSON_VERSION = 1;

function photographyEditorialJsonNullable(mixed $value): mixed
{
    return $value === null || $value === '' ? null : $value;
}

function photographyEditorialExportPackage(array $catalog): array
{
    $scenes = [];
    foreach ($catalog as $scene) {
        $variants = [];
        foreach ($scene['variants'] ?? [] as $variant) {
            $variants[] = [
                'id' => (int) $variant['id'],
                'key' => (string) $variant['variant_key'],
                'active' => (bool) $variant['active'],
                'order' => (int) $variant['sort_order'],
                'title' => (string) $variant['title'],
                'description' => (string) $variant['description'],
                'image' => photographyEditorialJsonNullable($variant['example_image_path'] ?? null),
                'notes' => photographyEditorialJsonNullable($variant['editorial_notes'] ?? null),
                'reference_focal_mm' => isset($variant['reference_focal_mm']) ? (float) $variant['reference_focal_mm'] : null,
                'aperture' => photographyEditorialJsonNullable($variant['aperture'] ?? null),
                'shutter_speed' => photographyEditorialJsonNullable($variant['shutter_speed'] ?? null),
                'iso' => photographyEditorialJsonNullable($variant['iso_value'] ?? null),
                'phone_note' => photographyEditorialJsonNullable($variant['phone_note'] ?? null),
                'phone_viability' => (string) $variant['phone_viability'],
            ];
        }
        $scenes[] = [
            'id' => (int) $scene['id'],
            'key' => (string) $scene['scene_key'],
            'active' => (bool) $scene['active'],
            'order' => (int) $scene['sort_order'],
            'title' => (string) $scene['title'],
            'description' => (string) $scene['description'],
            'image' => photographyEditorialJsonNullable($scene['example_image_path'] ?? null),
            'notes' => photographyEditorialJsonNullable($scene['editorial_notes'] ?? null),
            'variants' => $variants,
        ];
    }
    return ['version' => PHOTOGRAPHY_EDITORIAL_JSON_VERSION, 'scenes' => $scenes];
}

function photographyEditorialExportJson(array $catalog): string
{
    return json_encode(
        photographyEditorialExportPackage($catalog),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
}

function photographyEditorialJsonError(string $path, string $message): array
{
    return ['field' => $path, 'message' => $message];
}

function photographyEditorialJsonValidateKeys(array $value, array $required, array $optional, string $path, array &$errors): void
{
    foreach ($required as $key) {
        if (!array_key_exists($key, $value)) $errors[] = photographyEditorialJsonError($path . '.' . $key, 'Falta este campo obligatorio.');
    }
    $allowed = array_flip(array_merge($required, $optional));
    foreach (array_keys($value) as $key) {
        if (!is_string($key) || !isset($allowed[$key])) $errors[] = photographyEditorialJsonError($path, 'Contiene el campo desconocido “' . (string) $key . '”.');
    }
}

function photographyEditorialJsonValidateKey(mixed $value, string $path, array &$errors): ?string
{
    if (!is_string($value) || preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $value) !== 1 || strlen($value) > 80) {
        $errors[] = photographyEditorialJsonError($path, 'Debe usar minúsculas, números y guiones bajos, con un máximo de 80 caracteres.');
        return null;
    }
    return $value;
}

function photographyEditorialJsonValidateString(mixed $value, string $path, int $maxLength, bool $nullable, array &$errors): ?string
{
    if ($nullable && $value === null) return null;
    if (!is_string($value)) {
        $errors[] = photographyEditorialJsonError($path, $nullable ? 'Debe ser texto o null.' : 'Debe ser texto.');
        return null;
    }
    if (!$nullable && trim($value) === '') $errors[] = photographyEditorialJsonError($path, 'No puede estar vacío.');
    if (strlen($value) > $maxLength) $errors[] = photographyEditorialJsonError($path, 'Supera el máximo de ' . $maxLength . ' caracteres.');
    if (preg_match('//u', $value) !== 1) $errors[] = photographyEditorialJsonError($path, 'Debe contener texto UTF-8 válido.');
    return $value;
}

function photographyEditorialJsonValidateImage(mixed $value, string $path, array &$errors): ?string
{
    $image = photographyEditorialJsonValidateString($value, $path, 255, true, $errors);
    if ($image !== null && preg_match('#^assets/images/[A-Za-z0-9_./ -]+\.(?:jpe?g|png|webp)$#i', $image) !== 1) {
        $errors[] = photographyEditorialJsonError($path, 'Debe ser una ruta de imagen local permitida bajo assets/images/.');
    }
    return $image;
}

function photographyEditorialValidateJson(string $json, string $mode = 'replace'): array
{
    if (!in_array($mode, ['merge', 'replace'], true)) throw new InvalidArgumentException('Modo de importación inválido.');
    $partial = $mode === 'merge';
    $errors = [];
    try {
        $package = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        return ['valid' => false, 'data' => null, 'summary' => null, 'errors' => [photographyEditorialJsonError('json', 'JSON inválido: ' . $exception->getMessage())]];
    }
    if (!is_array($package) || array_is_list($package)) {
        return ['valid' => false, 'data' => null, 'summary' => null, 'errors' => [photographyEditorialJsonError('json', 'La raíz debe ser un objeto JSON.')]];
    }
    photographyEditorialJsonValidateKeys($package, ['version', 'scenes'], [], 'json', $errors);
    if (($package['version'] ?? null) !== PHOTOGRAPHY_EDITORIAL_JSON_VERSION) $errors[] = photographyEditorialJsonError('version', 'Debe ser exactamente 1.');
    $rawScenes = $package['scenes'] ?? null;
    if (!is_array($rawScenes) || !array_is_list($rawScenes)) {
        $errors[] = photographyEditorialJsonError('scenes', 'Debe ser una lista JSON.');
        $rawScenes = [];
    }

    $normalizedScenes = [];
    $sceneKeys = [];
    $variantCount = 0;
    foreach ($rawScenes as $sceneIndex => $rawScene) {
        $path = 'scenes.' . $sceneIndex;
        if (!is_array($rawScene) || array_is_list($rawScene)) { $errors[] = photographyEditorialJsonError($path, 'Debe ser un objeto JSON.'); continue; }
        $sceneFields = ['active','order','title','description','image','notes','variants'];
        photographyEditorialJsonValidateKeys(
            $rawScene,
            $partial ? ['key'] : ['key','active','order','title','description','notes','variants'],
            $partial ? array_merge(['id'], $sceneFields) : ['id','image'],
            $path,
            $errors
        );
        $key = photographyEditorialJsonValidateKey($rawScene['key'] ?? null, $path . '.key', $errors);
        if ($key !== null && isset($sceneKeys[$key])) $errors[] = photographyEditorialJsonError($path . '.key', 'La key está repetida.');
        if ($key !== null) $sceneKeys[$key] = true;
        $scene = ['key' => $key];
        if (array_key_exists('id', $rawScene)) {
            $id = $rawScene['id'];
            if ($id !== null && (!is_int($id) || $id < 1)) $errors[] = photographyEditorialJsonError($path . '.id', 'Debe ser un entero positivo o puede omitirse para una escena nueva.');
            $scene['id'] = $id;
        }
        if (array_key_exists('active', $rawScene)) {
            if (!is_bool($rawScene['active'])) $errors[] = photographyEditorialJsonError($path . '.active', 'Debe ser true o false.');
            $scene['active'] = $rawScene['active'];
        }
        if (array_key_exists('order', $rawScene)) {
            if (!is_int($rawScene['order'])) $errors[] = photographyEditorialJsonError($path . '.order', 'Debe ser un entero.');
            $scene['order'] = $rawScene['order'];
        }
        if (array_key_exists('title', $rawScene)) $scene['title'] = photographyEditorialJsonValidateString($rawScene['title'], $path . '.title', 160, false, $errors);
        if (array_key_exists('description', $rawScene)) $scene['description'] = photographyEditorialJsonValidateString($rawScene['description'], $path . '.description', 65535, false, $errors);
        if (array_key_exists('image', $rawScene)) $scene['image'] = photographyEditorialJsonValidateImage($rawScene['image'], $path . '.image', $errors);
        if (array_key_exists('notes', $rawScene)) $scene['notes'] = photographyEditorialJsonValidateString($rawScene['notes'], $path . '.notes', 65535, true, $errors);
        $rawVariants = $rawScene['variants'] ?? [];
        if (array_key_exists('variants', $rawScene) && (!is_array($rawVariants) || !array_is_list($rawVariants))) { $errors[] = photographyEditorialJsonError($path . '.variants', 'Debe ser una lista JSON.'); $rawVariants = []; }
        $variants = [];
        $variantKeys = [];
        foreach ($rawVariants as $variantIndex => $rawVariant) {
            $variantPath = $path . '.variants.' . $variantIndex;
            if (!is_array($rawVariant) || array_is_list($rawVariant)) { $errors[] = photographyEditorialJsonError($variantPath, 'Debe ser un objeto JSON.'); continue; }
            $variantFields = ['active','order','title','description','image','notes','reference_focal_mm','aperture','shutter_speed','iso','phone_note','phone_viability'];
            $required = ['key','active','order','title','description','notes','reference_focal_mm','aperture','shutter_speed','iso','phone_note','phone_viability'];
            photographyEditorialJsonValidateKeys($rawVariant, $partial ? ['key'] : $required, $partial ? array_merge(['id'], $variantFields) : ['id','image'], $variantPath, $errors);
            $variantKey = photographyEditorialJsonValidateKey($rawVariant['key'] ?? null, $variantPath . '.key', $errors);
            if ($variantKey !== null && isset($variantKeys[$variantKey])) $errors[] = photographyEditorialJsonError($variantPath . '.key', 'La key está repetida dentro de la escena.');
            if ($variantKey !== null) $variantKeys[$variantKey] = true;
            $variant = ['key' => $variantKey];
            if (array_key_exists('id', $rawVariant)) {
                $variantId = $rawVariant['id'];
                if ($variantId !== null && (!is_int($variantId) || $variantId < 1)) $errors[] = photographyEditorialJsonError($variantPath . '.id', 'Debe ser un entero positivo o puede omitirse para una variante nueva.');
                $variant['id'] = $variantId;
            }
            if (array_key_exists('active', $rawVariant)) {
                if (!is_bool($rawVariant['active'])) $errors[] = photographyEditorialJsonError($variantPath . '.active', 'Debe ser true o false.');
                $variant['active'] = $rawVariant['active'];
            }
            if (array_key_exists('order', $rawVariant)) {
                if (!is_int($rawVariant['order'])) $errors[] = photographyEditorialJsonError($variantPath . '.order', 'Debe ser un entero.');
                $variant['order'] = $rawVariant['order'];
            }
            if (array_key_exists('title', $rawVariant)) $variant['title'] = photographyEditorialJsonValidateString($rawVariant['title'], $variantPath . '.title', 160, false, $errors);
            if (array_key_exists('description', $rawVariant)) $variant['description'] = photographyEditorialJsonValidateString($rawVariant['description'], $variantPath . '.description', 65535, false, $errors);
            if (array_key_exists('image', $rawVariant)) $variant['image'] = photographyEditorialJsonValidateImage($rawVariant['image'], $variantPath . '.image', $errors);
            if (array_key_exists('notes', $rawVariant)) $variant['notes'] = photographyEditorialJsonValidateString($rawVariant['notes'], $variantPath . '.notes', 65535, true, $errors);
            if (array_key_exists('reference_focal_mm', $rawVariant)) {
                $focal = $rawVariant['reference_focal_mm'];
                if ($focal !== null && ((!is_int($focal) && !is_float($focal)) || $focal <= 0 || $focal > 5000)) $errors[] = photographyEditorialJsonError($variantPath . '.reference_focal_mm', 'Debe ser un número mayor que 0 y menor o igual a 5000, o null.');
                $variant['reference_focal_mm'] = $focal;
            }
            foreach (['aperture'=>40,'shutter_speed'=>40,'iso'=>40,'phone_note'=>65535] as $field => $limit) {
                if (array_key_exists($field, $rawVariant)) $variant[$field] = photographyEditorialJsonValidateString($rawVariant[$field], $variantPath . '.' . $field, $limit, true, $errors);
            }
            if (array_key_exists('phone_viability', $rawVariant)) {
                $viability = $rawVariant['phone_viability'];
                if (!is_string($viability) || !in_array($viability, ['no','limited','good'], true)) $errors[] = photographyEditorialJsonError($variantPath . '.phone_viability', 'Debe ser "no", "limited" o "good".');
                $variant['phone_viability'] = $viability;
            }
            $variants[] = $variant;
            $variantCount++;
        }
        if (array_key_exists('variants', $rawScene) || !$partial) $scene['variants'] = $variants;
        $normalizedScenes[] = $scene;
    }
    return ['valid'=>$errors === [],'data'=>$errors === [] ? ['version'=>1,'scenes'=>$normalizedScenes] : null,'summary'=>$errors === [] ? ['scenes'=>count($normalizedScenes),'variants'=>$variantCount] : null,'errors'=>$errors];
}

function photographyEditorialValidateImportAgainstDatabase(PDO $connection, array $data): array
{
    $errors = [];
    $sceneQuery = $connection->prepare('SELECT id FROM photography_scenes WHERE scene_key = :key LIMIT 1');
    $variantQuery = $connection->prepare('SELECT id FROM photography_scene_variants WHERE scene_id = :scene_id AND variant_key = :key LIMIT 1');
    foreach ($data['scenes'] as $sceneIndex => $scene) {
        $sceneQuery->execute(['key' => $scene['key']]);
        $existingSceneId = $sceneQuery->fetchColumn();
        $sceneId = $scene['id'] ?? null;
        if ($existingSceneId === false && $sceneId !== null) $errors[] = photographyEditorialJsonError('scenes.' . $sceneIndex . '.id', 'La escena es nueva: omití su ID para que la base lo asigne.');
        if ($existingSceneId !== false && $sceneId !== null && (int) $sceneId !== (int) $existingSceneId) $errors[] = photographyEditorialJsonError('scenes.' . $sceneIndex . '.id', 'No coincide con el ID actual de esta key.');
        if ($existingSceneId === false) {
            foreach (['active','order','title','description'] as $field) if (!array_key_exists($field, $scene)) $errors[] = photographyEditorialJsonError('scenes.' . $sceneIndex . '.' . $field, 'Es obligatorio para crear una escena nueva.');
        }
        foreach ($scene['variants'] ?? [] as $variantIndex => $variant) {
            $existingVariantId = false;
            if ($existingSceneId !== false) {
                $variantQuery->execute(['scene_id' => (int) $existingSceneId, 'key' => $variant['key']]);
                $existingVariantId = $variantQuery->fetchColumn();
            }
            $path = 'scenes.' . $sceneIndex . '.variants.' . $variantIndex . '.id';
            $variantId = $variant['id'] ?? null;
            if ($existingVariantId === false && $variantId !== null) $errors[] = photographyEditorialJsonError($path, 'La variante es nueva en esta escena: omití su ID para que la base lo asigne.');
            if ($existingVariantId !== false && $variantId !== null && (int) $variantId !== (int) $existingVariantId) $errors[] = photographyEditorialJsonError($path, 'No coincide con el ID actual de esta key dentro de la escena.');
            if ($existingVariantId === false) {
                foreach (['active','order','title','description','phone_viability'] as $field) if (!array_key_exists($field, $variant)) $errors[] = photographyEditorialJsonError('scenes.' . $sceneIndex . '.variants.' . $variantIndex . '.' . $field, 'Es obligatorio para crear una variante nueva.');
            }
        }
    }
    return $errors;
}

function photographyEditorialDynamicUpdate(PDO $connection, string $table, int $id, array $patch, array $mapping): void
{
    $assignments = [];
    $parameters = ['id' => $id];
    foreach ($mapping as $jsonField => [$column, $parameter]) {
        if (!array_key_exists($jsonField, $patch)) continue;
        $assignments[] = $column . '=:' . $parameter;
        $value = $patch[$jsonField];
        $parameters[$parameter] = $jsonField === 'active' ? ($value ? 1 : 0) : $value;
    }
    if ($assignments === []) return;
    $statement = $connection->prepare('UPDATE ' . $table . ' SET ' . implode(',', $assignments) . ' WHERE id=:id');
    $statement->execute($parameters);
}

function photographyEditorialImportPackage(PDO $connection, array $data): void
{
    $databaseErrors = photographyEditorialValidateImportAgainstDatabase($connection, $data);
    if ($databaseErrors !== []) throw new InvalidArgumentException($databaseErrors[0]['field'] . ': ' . $databaseErrors[0]['message']);
    $ownsTransaction = !$connection->inTransaction();
    if ($ownsTransaction) $connection->beginTransaction();
    try {
        $findScene = $connection->prepare('SELECT id FROM photography_scenes WHERE scene_key=:key LIMIT 1');
        $insertScene = $connection->prepare('INSERT INTO photography_scenes (scene_key,title,description,sort_order,active,example_image_path,editorial_notes) VALUES (:key,:title,:description,:sort_order,:active,:image,:notes)');
        $findVariant = $connection->prepare('SELECT id FROM photography_scene_variants WHERE scene_id=:scene_id AND variant_key=:key LIMIT 1');
        $insertVariant = $connection->prepare('INSERT INTO photography_scene_variants (scene_id,variant_key,title,description,sort_order,active,example_image_path,editorial_notes,reference_focal_mm,aperture,shutter_speed,iso_value,phone_note,phone_viability) VALUES (:scene_id,:key,:title,:description,:sort_order,:active,:image,:notes,:focal,:aperture,:shutter,:iso,:phone_note,:viability)');
        $sceneMapping = ['title'=>['title','title'],'description'=>['description','description'],'order'=>['sort_order','sort_order'],'active'=>['active','active'],'image'=>['example_image_path','image'],'notes'=>['editorial_notes','notes']];
        $variantMapping = ['title'=>['title','title'],'description'=>['description','description'],'order'=>['sort_order','sort_order'],'active'=>['active','active'],'image'=>['example_image_path','image'],'notes'=>['editorial_notes','notes'],'reference_focal_mm'=>['reference_focal_mm','focal'],'aperture'=>['aperture','aperture'],'shutter_speed'=>['shutter_speed','shutter'],'iso'=>['iso_value','iso'],'phone_note'=>['phone_note','phone_note'],'phone_viability'=>['phone_viability','viability']];
        foreach ($data['scenes'] as $scene) {
            $findScene->execute(['key'=>$scene['key']]);
            $sceneId = $findScene->fetchColumn();
            $values = ['title'=>$scene['title'] ?? null,'description'=>$scene['description'] ?? null,'sort_order'=>$scene['order'] ?? null,'active'=>($scene['active'] ?? false) ? 1 : 0,'image'=>$scene['image'] ?? null,'notes'=>$scene['notes'] ?? null];
            if ($sceneId === false) { $insertScene->execute(array_merge(['key'=>$scene['key']], $values)); $sceneId = (int) $connection->lastInsertId(); }
            else { photographyEditorialDynamicUpdate($connection, 'photography_scenes', (int) $sceneId, $scene, $sceneMapping); $sceneId = (int) $sceneId; }
            foreach ($scene['variants'] ?? [] as $variant) {
                $findVariant->execute(['scene_id'=>$sceneId,'key'=>$variant['key']]);
                $variantId = $findVariant->fetchColumn();
                $variantValues = ['title'=>$variant['title'] ?? null,'description'=>$variant['description'] ?? null,'sort_order'=>$variant['order'] ?? null,'active'=>($variant['active'] ?? false) ? 1 : 0,'image'=>$variant['image'] ?? null,'notes'=>$variant['notes'] ?? null,'focal'=>$variant['reference_focal_mm'] ?? null,'aperture'=>$variant['aperture'] ?? null,'shutter'=>$variant['shutter_speed'] ?? null,'iso'=>$variant['iso'] ?? null,'phone_note'=>$variant['phone_note'] ?? null,'viability'=>$variant['phone_viability'] ?? null];
                if ($variantId === false) $insertVariant->execute(array_merge(['scene_id'=>$sceneId,'key'=>$variant['key']], $variantValues));
                else photographyEditorialDynamicUpdate($connection, 'photography_scene_variants', (int) $variantId, $variant, $variantMapping);
            }
        }
        if ($ownsTransaction) $connection->commit();
    } catch (Throwable $exception) {
        if ($ownsTransaction && $connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function photographyEditorialPackageWithoutIds(array $data): array
{
    foreach ($data['scenes'] as &$scene) {
        $scene['id'] = null;
        foreach ($scene['variants'] as &$variant) $variant['id'] = null;
        unset($variant);
    }
    unset($scene);
    return $data;
}

/** @return array{scenes:int,variants:int,selected_scene_id:int} */
function photographyEditorialReplacePackage(PDO $connection, array $data): array
{
    if (($data['scenes'] ?? []) === []) throw new InvalidArgumentException('Reemplazar todo requiere al menos una escena.');
    $data = photographyEditorialPackageWithoutIds($data);
    $sceneCount = count($data['scenes']);
    $variantCount = array_sum(array_map(static fn(array $scene): int => count($scene['variants']), $data['scenes']));
    $ownsTransaction = !$connection->inTransaction();
    if ($ownsTransaction) $connection->beginTransaction();
    try {
        $connection->exec('DELETE FROM photography_scene_variants');
        $connection->exec('DELETE FROM photography_scenes');
        photographyEditorialImportPackage($connection, $data);
        $selectedSceneId = $connection->query('SELECT id FROM photography_scenes ORDER BY sort_order,id LIMIT 1')->fetchColumn();
        if ($selectedSceneId === false) throw new RuntimeException('La importación no creó una escena seleccionable.');
        if ($ownsTransaction) $connection->commit();
        return ['scenes' => $sceneCount, 'variants' => $variantCount, 'selected_scene_id' => (int) $selectedSceneId];
    } catch (Throwable $exception) {
        if ($ownsTransaction && $connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}
