ALTER TABLE web_push_device_config
    ADD COLUMN support_id VARCHAR(11) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER subscription_id;

ALTER TABLE web_push_device_config
    ADD UNIQUE KEY uk_web_push_device_support_id (support_id);

ALTER TABLE web_push_device_config
    MODIFY support_id VARCHAR(11) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
