CREATE TABLE IF NOT EXISTS web_push_notification_types (
    notification_type VARCHAR(50) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    available TINYINT(1) NOT NULL DEFAULT 0,
    admin_only TINYINT(1) NOT NULL DEFAULT 0,
    title_template VARCHAR(180) NOT NULL,
    body_template VARCHAR(500) NOT NULL,
    target_url VARCHAR(500) NOT NULL,
    default_schedule_mode VARCHAR(30) NOT NULL,
    default_lead_minutes INT UNSIGNED NULL,
    default_delivery_time TIME NULL,
    default_delivery_day_offset SMALLINT NOT NULL DEFAULT 0,
    default_quiet_policy VARCHAR(20) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_type),
    KEY idx_web_push_notification_type_visibility (available, admin_only, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO web_push_notification_types
    (notification_type, display_name, description, available, admin_only, title_template, body_template,
     target_url, default_schedule_mode, default_lead_minutes, default_delivery_time,
     default_delivery_day_offset, default_quiet_policy, sort_order)
VALUES
    ('moonrise', 'Salida de la Luna', 'Aviso previo a la salida local de la Luna.', 1, 0,
     'La Luna sale pronto',
     'Salida prevista a las {event_time} en {location_name}.', './sol-y-luna.php',
     'before_event', 15, NULL, 0, 'omit', 10),
    ('test', 'Prueba del sistema', 'Notificación real programada desde la administración.', 1, 1,
     'Prueba de Aquellas Lunas',
     'Notificación automática programada para las {event_time}.', './sol-y-luna.php',
     'before_event', 0, NULL, 0, 'omit', 1000);
