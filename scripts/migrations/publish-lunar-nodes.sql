CREATE TEMPORARY TABLE lunar_nodes_to_publish (id BIGINT UNSIGNED NOT NULL PRIMARY KEY);

INSERT INTO lunar_nodes_to_publish (id)
SELECT t.id FROM admin_tipos_eventos t
LEFT JOIN admin_tipos_eventos_superficies s ON s.tipo_evento_id=t.id
WHERE t.scope='persisted' AND t.event_group='lunar_orbit'
  AND ((t.event_type='ascending_node' AND t.nombre_amigable='Nodo lunar ascendente')
    OR (t.event_type='descending_node' AND t.nombre_amigable='Nodo lunar descendente'))
  AND t.habilitado=0 AND (t.relevante_esta_noche IS NULL OR t.relevante_esta_noche=0)
GROUP BY t.id HAVING COUNT(s.tipo_evento_id)=0;

UPDATE admin_tipos_eventos t
INNER JOIN lunar_nodes_to_publish n ON n.id=t.id
SET t.habilitado=1,t.relevante_esta_noche=0;

INSERT INTO admin_tipos_eventos_superficies (tipo_evento_id,superficie,posicion)
SELECT id,'home_upcoming',0 FROM lunar_nodes_to_publish
ON DUPLICATE KEY UPDATE tipo_evento_id=tipo_evento_id;

INSERT INTO admin_tipos_eventos_superficies (tipo_evento_id,superficie,posicion)
SELECT id,'today',1 FROM lunar_nodes_to_publish
ON DUPLICATE KEY UPDATE tipo_evento_id=tipo_evento_id;

INSERT INTO admin_tipos_eventos_superficies (tipo_evento_id,superficie,posicion)
SELECT id,'events',2 FROM lunar_nodes_to_publish
ON DUPLICATE KEY UPDATE tipo_evento_id=tipo_evento_id;

DROP TEMPORARY TABLE lunar_nodes_to_publish;
