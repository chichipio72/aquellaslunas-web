CREATE TABLE IF NOT EXISTS web_push_notification_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subscription_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    event_time_utc DATETIME NOT NULL,
    notification_time_utc DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL,
    attempted_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    error_message VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_subscription_event (subscription_id, notification_type, event_time_utc)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
