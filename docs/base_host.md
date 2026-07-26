# Esquema MySQL de referencia para la tienda

Este SQL refleja las tablas que consume actualmente la web. No es una migración automática: antes de ejecutarlo hay que contrastarlo con el esquema real. La tabla `descargas` recibe permisos al aprobar pagos, pero todavía no existe un endpoint público que los consuma.

```sql
CREATE TABLE pedidos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo_publico CHAR(36) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    monto_total DECIMAL(10, 2) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'ARS',
    email_comprador VARCHAR(254) NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    pagado_en TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pedidos_codigo_publico (codigo_publico),
    KEY idx_pedidos_estado (estado),
    KEY idx_pedidos_creado_en (creado_en)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;


CREATE TABLE pedido_fotos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    foto_id CHAR(64) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    precio_unitario DECIMAL(10, 2) NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_pedido_foto (pedido_id, foto_id),
    KEY idx_pedido_fotos_foto_id (foto_id),

    CONSTRAINT fk_pedido_fotos_pedido
        FOREIGN KEY (pedido_id)
        REFERENCES pedidos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
    
    CREATE TABLE pagos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    proveedor VARCHAR(30) NOT NULL DEFAULT 'mercado_pago',
    referencia_externa VARCHAR(100) NULL,
    preferencia_proveedor_id VARCHAR(100) NULL,
    pago_proveedor_id VARCHAR(100) NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    estado_detalle VARCHAR(100) NULL,
    monto DECIMAL(10, 2) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'ARS',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    aprobado_en TIMESTAMP NULL,
    respuesta_json LONGTEXT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_pagos_preferencia_proveedor_id (preferencia_proveedor_id),
    UNIQUE KEY uk_pagos_pago_proveedor_id (pago_proveedor_id),
    KEY idx_pagos_pedido_id (pedido_id),
    KEY idx_pagos_estado (estado),

    CONSTRAINT fk_pagos_pedido
        FOREIGN KEY (pedido_id)
        REFERENCES pedidos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

 CREATE TABLE descargas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    token CHAR(64) NOT NULL,
    vence_en DATETIME NOT NULL,
    max_descargas INT UNSIGNED NOT NULL DEFAULT 5,
    cantidad_descargas INT UNSIGNED NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_descarga_en TIMESTAMP NULL,
    revocado_en TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_descargas_token (token),
    KEY idx_descargas_pedido_id (pedido_id),
    KEY idx_descargas_vence_en (vence_en),

    CONSTRAINT fk_descargas_pedido
        FOREIGN KEY (pedido_id)
        REFERENCES pedidos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
    
 CREATE TABLE fotos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    foto_id CHAR(64) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    archivo_original VARCHAR(500) NOT NULL,
    archivo_preview_tienda VARCHAR(500) NULL,
    archivo_preview_contenido VARCHAR(500) NULL,

    titulo VARCHAR(255) NULL,
    descripcion TEXT NULL,
    palabras_clave TEXT NULL,

    ancho_px INT UNSIGNED NULL,
    alto_px INT UNSIGNED NULL,
    fecha_captura DATETIME NULL,
    camara VARCHAR(255) NULL,
    lente VARCHAR(255) NULL,

    precio DECIMAL(10, 2) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'ARS',
    disponible TINYINT(1) NOT NULL DEFAULT 1,

    metadatos_json LONGTEXT NULL,

    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_fotos_foto_id (foto_id),
    UNIQUE KEY uk_fotos_archivo_original (archivo_original),
    KEY idx_fotos_disponible (disponible),
    KEY idx_fotos_fecha_captura (fecha_captura)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
    
ALTER TABLE pedido_fotos
    ADD COLUMN foto_registro_id BIGINT UNSIGNED NULL
        AFTER pedido_id,
    ADD KEY idx_pedido_fotos_foto_registro_id (foto_registro_id),
    ADD CONSTRAINT fk_pedido_fotos_foto
        FOREIGN KEY (foto_registro_id)
        REFERENCES fotos (id)
        ON DELETE SET NULL;
```
