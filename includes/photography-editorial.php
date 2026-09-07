<?php

declare(strict_types=1);

function photographyEditorialInitialize(PDO $connection): void
{
    $connection->exec('CREATE TABLE IF NOT EXISTS photography_scenes (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,scene_key VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,title VARCHAR(160) NOT NULL,description TEXT NOT NULL,sort_order INT NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,example_image_path VARCHAR(255) NULL,editorial_notes TEXT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $connection->exec('CREATE TABLE IF NOT EXISTS photography_scene_variants (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,scene_id BIGINT UNSIGNED NOT NULL,variant_key VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,title VARCHAR(160) NOT NULL,description TEXT NOT NULL,sort_order INT NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,example_image_path VARCHAR(255) NULL,editorial_notes TEXT NULL,reference_focal_mm DECIMAL(8,2) NULL,aperture VARCHAR(40) NULL,shutter_speed VARCHAR(40) NULL,iso_value VARCHAR(40) NULL,phone_note TEXT NULL,phone_viability ENUM(\'no\',\'limited\',\'good\') NOT NULL DEFAULT \'limited\',created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_photography_variant (scene_id,variant_key),CONSTRAINT fk_photography_variant_scene FOREIGN KEY (scene_id) REFERENCES photography_scenes(id) ON UPDATE CASCADE ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $seedEditorialCatalog = (int) $connection->query('SELECT COUNT(*) FROM photography_scenes')->fetchColumn() === 0;
    if ($seedEditorialCatalog) {
    $scene = $connection->prepare('INSERT INTO photography_scenes (scene_key,title,description,sort_order,active) VALUES (:scene_key,:title,:description,:sort_order,1) ON DUPLICATE KEY UPDATE scene_key=scene_key');
    foreach ([
        ['moon_alone', 'Luna sola', 'La Luna como único astro del encuadre.', 10],
        ['moon_with_horizon', 'Luna con horizonte', 'La altura lunar se relaciona con el paisaje y la línea del horizonte.', 20],
        ['moon_with_objects', 'Luna con otros astros', 'Encuentros amplios o cerrados entre la Luna y astros relevantes.', 30],
        ['eclipse', 'Eclipse', 'La etapa local del eclipse dentro de un encuadre lunar real.', 40],
    ] as [$key, $title, $description, $order]) $scene->execute(['scene_key' => $key, 'title' => $title, 'description' => $description, 'sort_order' => $order]);
    $ids = $connection->query("SELECT scene_key,id FROM photography_scenes WHERE scene_key IN ('moon_alone','moon_with_horizon','moon_with_objects','eclipse')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $variant = $connection->prepare('INSERT INTO photography_scene_variants (scene_id,variant_key,title,description,sort_order,active,phone_note,phone_viability) VALUES (:scene_id,:variant_key,:title,:description,:sort_order,1,:phone_note,:phone_viability) ON DUPLICATE KEY UPDATE variant_key=variant_key');
    if (isset($ids['moon_alone'])) $variant->execute(['scene_id' => $ids['moon_alone'], 'variant_key' => 'disk_detail', 'title' => 'Detalle del disco', 'description' => 'Una posible referencia para destacar el disco lunar.', 'sort_order' => 10, 'phone_note' => 'En celular conviene integrar la Luna en una composición más amplia.', 'phone_viability' => 'limited']);
    if (isset($ids['moon_alone'])) $variant->execute(['scene_id' => $ids['moon_alone'], 'variant_key' => 'wide_context', 'title' => 'Luna en contexto', 'description' => 'Una composición más amplia donde la Luna no necesita llenar el cuadro.', 'sort_order' => 20, 'phone_note' => 'Esta variante puede aprovechar especialmente bien la cámara de un celular.', 'phone_viability' => 'good']);
    if (isset($ids['moon_with_horizon'])) $variant->execute(['scene_id' => $ids['moon_with_horizon'], 'variant_key' => 'visible_landscape', 'title' => 'Paisaje visible', 'description' => 'Una posible elección que conserva información del paisaje.', 'sort_order' => 10, 'phone_note' => 'Los celulares pueden ser especialmente útiles en paisajes y crepúsculos amplios.', 'phone_viability' => 'good']);
    if (isset($ids['moon_with_objects'])) $variant->execute(['scene_id' => $ids['moon_with_objects'], 'variant_key' => 'natural', 'title' => 'Encuentro natural', 'description' => 'Una posible configuración que conserva aire alrededor del encuentro.', 'sort_order' => 10, 'phone_note' => 'Los encuentros amplios y el crepúsculo pueden funcionar bien con celular.', 'phone_viability' => 'good']);
    if (isset($ids['moon_with_objects'])) $variant->execute(['scene_id' => $ids['moon_with_objects'], 'variant_key' => 'moon_protagonist', 'title' => 'Luna protagonista', 'description' => 'Una variante que da mayor peso visual a la Luna sin mover los astros.', 'sort_order' => 20, 'phone_note' => 'La viabilidad depende de cuán amplio sea el encuentro y de la cámara disponible.', 'phone_viability' => 'limited']);
    if (isset($ids['eclipse'])) $variant->execute(['scene_id' => $ids['eclipse'], 'variant_key' => 'documentary', 'title' => 'Registro documental', 'description' => 'Una posible referencia para registrar la etapa local calculada.', 'sort_order' => 10, 'phone_note' => 'Un celular puede integrar el eclipse en un paisaje, aunque el detalle del disco será limitado.', 'phone_viability' => 'limited']);
    }
    try {
        $menu = $connection->prepare("INSERT INTO site_menu_sections (section_id,group_id,sort_order,public_visible,admin_visible) VALUES ('photography','explore',5,1,1) ON DUPLICATE KEY UPDATE section_id=section_id");
        $menu->execute();
    } catch (Throwable $exception) {
        // El modelo editorial no depende de que el menú administrable ya exista.
    }
}

function photographyEditorialCatalog(PDO $connection): array
{
    $scenes = $connection->query('SELECT * FROM photography_scenes ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
    $variants = $connection->query('SELECT * FROM photography_scene_variants ORDER BY scene_id,sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($scenes as &$scene) $scene['variants'] = array_values(array_filter($variants, static fn(array $variant): bool => (int) $variant['scene_id'] === (int) $scene['id']));
    return $scenes;
}

function photographyEditorialUpdate(PDO $connection, array $input): void
{
    $sceneId = filter_var($input['scene_id'] ?? null, FILTER_VALIDATE_INT);
    $variantId = filter_var($input['variant_id'] ?? null, FILTER_VALIDATE_INT);
    $title = trim((string) ($input['title'] ?? '')); $description = trim((string) ($input['description'] ?? ''));
    if ($title === '' || strlen($title) > 160 || $description === '') throw new InvalidArgumentException('Título y descripción son obligatorios.');
    $image = trim((string) ($input['example_image_path'] ?? ''));
    if ($image !== '' && preg_match('#^assets/images/[A-Za-z0-9_./ -]+\.(?:jpe?g|png|webp)$#i', $image) !== 1) throw new InvalidArgumentException('La imagen debe ser un asset local permitido.');
    if ($variantId) {
        $viability = (string) ($input['phone_viability'] ?? 'limited'); if (!in_array($viability, ['no', 'limited', 'good'], true)) throw new InvalidArgumentException('Viabilidad inválida.');
        $focal = trim((string) ($input['reference_focal_mm'] ?? '')); if ($focal !== '' && (!is_numeric($focal) || (float) $focal <= 0 || (float) $focal > 5000)) throw new InvalidArgumentException('Focal inválida.');
        $statement = $connection->prepare('UPDATE photography_scene_variants SET title=:title,description=:description,sort_order=:sort_order,active=:active,example_image_path=:image,editorial_notes=:notes,reference_focal_mm=:focal,aperture=:aperture,shutter_speed=:shutter,iso_value=:iso,phone_note=:phone_note,phone_viability=:viability WHERE id=:id');
        $statement->execute(['id' => $variantId, 'title' => $title, 'description' => $description, 'sort_order' => (int) ($input['sort_order'] ?? 0), 'active' => !empty($input['active']) ? 1 : 0, 'image' => $image ?: null, 'notes' => trim((string) ($input['editorial_notes'] ?? '')) ?: null, 'focal' => $focal ?: null, 'aperture' => trim((string) ($input['aperture'] ?? '')) ?: null, 'shutter' => trim((string) ($input['shutter_speed'] ?? '')) ?: null, 'iso' => trim((string) ($input['iso_value'] ?? '')) ?: null, 'phone_note' => trim((string) ($input['phone_note'] ?? '')) ?: null, 'viability' => $viability]);
    } elseif ($sceneId) {
        $statement = $connection->prepare('UPDATE photography_scenes SET title=:title,description=:description,sort_order=:sort_order,active=:active,example_image_path=:image,editorial_notes=:notes WHERE id=:id');
        $statement->execute(['id' => $sceneId, 'title' => $title, 'description' => $description, 'sort_order' => (int) ($input['sort_order'] ?? 0), 'active' => !empty($input['active']) ? 1 : 0, 'image' => $image ?: null, 'notes' => trim((string) ($input['editorial_notes'] ?? '')) ?: null]);
    } else throw new InvalidArgumentException('Registro editorial inválido.');
}
