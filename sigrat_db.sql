-- SIGRAT DATABASE - Instalación desde Cero
-- Versión: 3.0 (Instalación Limpia)

-- 1. CREACIÓN DE LA BASE DE DATOS
DROP DATABASE IF EXISTS `sigrat_db`;
CREATE DATABASE `sigrat_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `sigrat_db`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 2. CATÁLOGOS BASE
-- --------------------------------------------------------

CREATE TABLE `roles` (
  `rol_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `permisos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`rol_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles` (`rol_id`, `nombre`, `descripcion`) VALUES
(1, 'Administrador', 'Control total del sistema'),
(2, 'Personal', 'Gestión de espacios y activos'),
(3, 'Estudiante', 'Solicitudes de reserva');

CREATE TABLE `espacio` (
  `esp_id` int(11) NOT NULL AUTO_INCREMENT,
  `edificio` enum('CIC','PIDET') NOT NULL,
  `nombre_numero` varchar(50) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `capacidad` int(11) DEFAULT NULL,
  `estatus` enum('Disponible','Ocupado','Mantenimiento','Inactivo') DEFAULT 'Disponible',
  `eliminado` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`esp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `espacio` (`esp_id`, `edificio`, `nombre_numero`, `tipo`, `capacidad`) VALUES
(1, 'CIC', 'Laboratorio L1', 'Laboratorio', 25),
(2, 'PIDET', 'Aula P10', 'Aula', 30);

-- --------------------------------------------------------
-- 3. INFRAESTRUCTURA RFID (MAESTRA Y LECTORES)
-- --------------------------------------------------------

CREATE TABLE `tarjeta_rfid` (
  `uid_tag` varchar(50) NOT NULL,
  `tipo_tag` enum('Activo', 'Personal', 'Llave', 'Desconocido') NOT NULL DEFAULT 'Desconocido',
  `fecha_alta` timestamp NOT NULL DEFAULT current_timestamp(),
  `estatus` enum('Activo', 'Inactivo', 'Extraviado') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`uid_tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tarjeta_rfid` (`uid_tag`, `tipo_tag`) VALUES 
('E200001', 'Personal'),
('A100001', 'Activo'),
('K500001', 'Llave');

CREATE TABLE `dispositivo_lector` (
  `lec_id` int(11) NOT NULL AUTO_INCREMENT,
  `esp_id` int(11) DEFAULT NULL,
  `ubicacion_desc` varchar(100) NOT NULL,
  `mac_address` varchar(17) DEFAULT NULL,
  `estatus` enum('Operativa','Mantenimiento','Fuera de Servicio') DEFAULT 'Operativa',
  PRIMARY KEY (`lec_id`),
  CONSTRAINT `fk_lector_espacio` FOREIGN KEY (`esp_id`) REFERENCES `espacio` (`esp_id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dispositivo_lector` (`lec_id`, `esp_id`, `ubicacion_desc`) VALUES
(1, 1, 'Entrada Principal CIC'),
(2, 2, 'Acceso PIDET');

-- --------------------------------------------------------
-- 4. USUARIOS Y ACTIVOS
-- --------------------------------------------------------

CREATE TABLE `usuario` (
  `us_id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `rol_id` int(11) DEFAULT NULL,
  `tag_id` varchar(50) DEFAULT NULL,
  `estatus` enum('Activo','Inactivo') DEFAULT 'Activo',
  PRIMARY KEY (`us_id`),
  UNIQUE KEY `correo` (`correo`),
  CONSTRAINT `fk_user_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`rol_id`),
  CONSTRAINT `fk_user_rfid` FOREIGN KEY (`tag_id`) REFERENCES `tarjeta_rfid` (`uid_tag`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `usuario` (`us_id`, `nombre`, `correo`, `contrasena`, `rol_id`, `tag_id`) VALUES
(1, 'Admin SIGRAT', 'admin@sigrat.edu', '$2y$10$TNi2pFNpml8zZPMIGFC8Qu3o785eCFgmHafP4zIjek.RACw2MRh0W', 1, 'E200001');

CREATE TABLE `activo` (
  `act_id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) DEFAULT NULL,
  `marca` varchar(50) DEFAULT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `num_serie` varchar(100) DEFAULT NULL,
  `num_inv` varchar(100) DEFAULT NULL,
  `estatus` varchar(20) DEFAULT 'Disponible',
  `tag_id` varchar(50) DEFAULT NULL,
  `esp_asignado` int(11) DEFAULT NULL,
  PRIMARY KEY (`act_id`),
  UNIQUE KEY `num_inv` (`num_inv`),
  CONSTRAINT `fk_activo_rfid` FOREIGN KEY (`tag_id`) REFERENCES `tarjeta_rfid` (`uid_tag`) ON DELETE SET NULL,
  CONSTRAINT `fk_activo_espacio` FOREIGN KEY (`esp_asignado`) REFERENCES `espacio` (`esp_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `activo` (`tipo`, `marca`, `modelo`, `num_inv`, `tag_id`) VALUES
('Laptop', 'Dell', 'Latitude 5420', 'INV-001', 'A100001');

-- --------------------------------------------------------
-- 5. LOGS Y OPERACIONES
-- --------------------------------------------------------

CREATE TABLE `rfid_logs_lectura` (
  `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uid_tag` varchar(50) NOT NULL,
  `lec_id` int(11) NOT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo_evento` enum('IN', 'OUT', 'CHECK') NOT NULL DEFAULT 'CHECK',
  PRIMARY KEY (`log_id`),
  CONSTRAINT `fk_log_tag` FOREIGN KEY (`uid_tag`) REFERENCES `tarjeta_rfid` (`uid_tag`),
  CONSTRAINT `fk_log_lec` FOREIGN KEY (`lec_id`) REFERENCES `dispositivo_lector` (`lec_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Updated reservation table with approval workflow
CREATE TABLE `reserva` (
  `re_id` int(11) NOT NULL AUTO_INCREMENT,
  `us_id` int(11) DEFAULT NULL,
  `esp_id` int(11) DEFAULT NULL,
  `fecha_uso` date NOT NULL,
  `hora_ent` time NOT NULL,
  `hora_sal` time NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) NULL,
  `approved_at` DATETIME NULL,
  PRIMARY KEY (`re_id`),
  CONSTRAINT `fk_res_user` FOREIGN KEY (`us_id`) REFERENCES `usuario` (`us_id`),
  CONSTRAINT `fk_res_esp` FOREIGN KEY (`esp_id`) REFERENCES `espacio` (`esp_id`),
  CONSTRAINT `fk_res_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `usuario` (`us_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `bitacora` (
  `bit_id` int(11) NOT NULL AUTO_INCREMENT,
  `us_id` int(11) DEFAULT NULL,
  `accion` varchar(255) NOT NULL,
  `modulo_afectado` varchar(50) DEFAULT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`bit_id`),
  CONSTRAINT `fk_bit_user` FOREIGN KEY (`us_id`) REFERENCES `usuario` (`us_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
