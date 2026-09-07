INSERT INTO admin_configuracion_sitio (clave, valor, descripcion)
SELECT 'photography.simulated.twilight_horizon_antisolar', valor, 'Color del horizonte crepuscular en la dirección opuesta al Sol.'
FROM admin_configuracion_sitio WHERE clave = 'photography.simulated.twilight_horizon'
ON DUPLICATE KEY UPDATE clave = VALUES(clave);

INSERT INTO admin_configuracion_sitio (clave, valor, descripcion)
SELECT CONCAT('photography.simulated.moon_twilight_low_antisolar_', suffix.valor), source.valor,
       CONCAT('Ancla antisolar copiada desde ', source.clave)
FROM (
    SELECT 'lit_color' AS valor UNION ALL SELECT 'lit_brightness' UNION ALL SELECT 'dark_brightness'
    UNION ALL SELECT 'dark_sky_mix' UNION ALL SELECT 'contrast' UNION ALL SELECT 'texture_visibility'
    UNION ALL SELECT 'lit_sky_mix' UNION ALL SELECT 'terminator_detail' UNION ALL SELECT 'limb_softness'
) AS suffix
JOIN admin_configuracion_sitio AS source
  ON source.clave = CONCAT('photography.simulated.moon_twilight_low_', suffix.valor)
ON DUPLICATE KEY UPDATE clave = VALUES(clave);
