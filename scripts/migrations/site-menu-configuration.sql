CREATE TABLE site_menu_groups (
    id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    label VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE site_menu_sections (
    section_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    group_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    public_visible TINYINT(1) NOT NULL DEFAULT 1,
    admin_visible TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_site_menu_sections_group FOREIGN KEY (group_id)
        REFERENCES site_menu_groups(id) ON UPDATE CASCADE ON DELETE RESTRICT
);
