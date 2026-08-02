CREATE TABLE IF NOT EXISTS web_push_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    endpoint VARCHAR(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    endpoint_hash BINARY(32) NOT NULL,
    p256dh VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    auth VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_success_at DATETIME NULL,
    last_error_at DATETIME NULL,
    last_error_message VARCHAR(1000) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_web_push_endpoint_hash (endpoint_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
