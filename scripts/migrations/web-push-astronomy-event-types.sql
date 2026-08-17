INSERT IGNORE INTO web_push_notification_types
    (notification_type, display_name, description, available, admin_only, title_template, body_template,
     target_url, default_schedule_mode, default_lead_minutes, default_delivery_time,
     default_delivery_day_offset, default_quiet_policy, sort_order)
VALUES
    ('eclipse', 'Eclipses', 'Aviso a las 08:00 del día anterior para eclipses visibles desde tu ubicación.', 1, 0,
     'Eclipse mañana',
     'Mañana habrá un {eclipse_type} visible desde {location_name}. Máximo previsto a las {event_time}.',
     './eclipses.php', 'fixed_local_time', NULL, '08:00:00', -1, 'postpone', 20),
    ('lunar_conjunction', 'Conjunciones cercanas', 'Aviso a las 08:00 del día para encuentros Luna-planeta de hasta 3°.', 1, 0,
     'Conjunción cercana esta noche',
     'La Luna y {object_name} estarán a {separation} en el cielo de {location_name}.',
     './eventos.php', 'fixed_local_time', NULL, '08:00:00', 0, 'postpone', 30),
    ('satellite_transit', 'Tránsitos ISS/Tiangong', 'Aviso 60 minutos antes de un tránsito o paso extremadamente cercano al Sol o la Luna.', 1, 0,
     '{satellite_name} cerca de {target_name}',
     '{satellite_name} tendrá un {classification} de {target_name} a las {event_time} desde {location_name}.',
     './index.php', 'before_event', 60, NULL, 0, 'omit', 40);

