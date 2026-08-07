CREATE TABLE IF NOT EXISTS web_push_device_config (
    subscription_id BIGINT UNSIGNED NOT NULL,
    device_name VARCHAR(100) NOT NULL,
    notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
    location_name VARCHAR(150) NOT NULL,
    latitude DECIMAL(9,6) NOT NULL,
    longitude DECIMAL(10,6) NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    quiet_hours_enabled TINYINT(1) NOT NULL DEFAULT 0,
    quiet_start_local TIME NULL,
    quiet_end_local TIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS web_push_notification_preferences (
    subscription_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    schedule_mode VARCHAR(30) NOT NULL,
    lead_minutes INT UNSIGNED NULL,
    delivery_local_time TIME NULL,
    delivery_day_offset SMALLINT NOT NULL DEFAULT 0,
    quiet_policy VARCHAR(20) NOT NULL,
    parameters_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (subscription_id, notification_type),
    KEY idx_web_push_preference_type_enabled (notification_type, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS web_push_scheduled_tests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subscription_id BIGINT UNSIGNED NOT NULL,
    scheduled_at_utc DATETIME NOT NULL,
    respect_quiet_hours TINYINT(1) NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    notification_log_id BIGINT UNSIGNED NULL,
    error_message VARCHAR(500) NULL,
    PRIMARY KEY (id),
    KEY idx_web_push_scheduled_test_due (status, scheduled_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
