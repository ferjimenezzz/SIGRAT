/**
 * Summary: DDL Script for creating the 'lugares' table.
 * 
 * Explanation:
 * The 'lugares' table is structurally identical to the 'espacio' table.
 * However, its purpose is to register non-academic/functional spaces such as 
 * hallways, storage rooms, offices, and cubicles.
 * 
 * The 'tipo' ENUM has been updated to reflect these new categories.
 * The primary key is named 'lug_id'.
 */

CREATE TABLE `lugares` (
  `lug_id` int(11) NOT NULL AUTO_INCREMENT,
  `edificio` enum('CIC','PIDET') NOT NULL,
  `planta` enum('Baja', 'Alta') DEFAULT NULL,
  `nombre_numero` varchar(50) NOT NULL,
  `tipo` enum('Pasillo', 'Bodega', 'Oficina', 'Cubículo', 'Recepción', 'Cluster', 'Otro') DEFAULT NULL,
  `capacidad` int(11) DEFAULT NULL,
  `estatus` enum('Disponible','Ocupado','Mantenimiento','Inactivo') DEFAULT 'Disponible',
  `eliminado` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`lug_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
