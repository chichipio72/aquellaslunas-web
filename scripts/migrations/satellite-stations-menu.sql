INSERT INTO site_menu_sections (section_id,group_id,sort_order,public_visible,admin_visible)
VALUES ('satellite_stations','explore',35,1,1)
ON DUPLICATE KEY UPDATE section_id=section_id;
