INSERT INTO site_menu_sections (section_id,group_id,sort_order,public_visible,admin_visible)
VALUES ('sources_credits','about',15,1,1)
ON DUPLICATE KEY UPDATE section_id=section_id;
