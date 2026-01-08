-- Base de datos mejorada con índices y constraints adicionales
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS `alquiler_canchas`
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `alquiler_canchas`;

-- =============================
-- TABLA CANCHAS
-- =============================
CREATE TABLE IF NOT EXISTS `canchas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `precio_hora` decimal(10,2) NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activa` tinyint(1) DEFAULT '1',
  `capacidad_jugadores` int DEFAULT NULL,
  `tipo_superficie` enum('cesped_natural','cesped_sintetico','cemento','parquet') DEFAULT 'cesped_sintetico',
  `techada` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activa` (`activa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de canchas
INSERT INTO `canchas`
(`id`, `nombre`, `precio_hora`, `descripcion`, `activa`, `capacidad_jugadores`, `tipo_superficie`, `techada`)
VALUES
(1, 'Cancha 1', 5000.00, 'Cancha de fútbol 5 con césped sintético', 1, 10, 'cesped_sintetico', 0),
(2, 'Cancha 2', 5000.00, 'Cancha de fútbol 5 con césped sintético', 1, 10, 'cesped_sintetico', 0),
(3, 'Cancha 3', 6000.00, 'Cancha de fútbol 7 con césped sintético', 1, 14, 'cesped_sintetico', 0),
(4, 'Cancha 4', 6000.00, 'Cancha de fútbol 7 con césped sintético', 1, 14, 'cesped_sintetico', 0),
(5, 'Cancha 5', 8000.00, 'Cancha de fútbol 11 profesional', 1, 22, 'cesped_sintetico', 0);

-- =============================
-- TABLA CLIENTES
-- =============================
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dni` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_telefono` (`telefono`),
  KEY `idx_telefono` (`telefono`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================
-- TABLA RESERVAS
-- =============================
CREATE TABLE IF NOT EXISTS `reservas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cancha_id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `estado` enum('pendiente','confirmada','cancelada','completada') DEFAULT 'confirmada',
  `metodo_pago` enum('efectivo','transferencia','tarjeta','mercadopago') DEFAULT 'efectivo',
  `seña` decimal(10,2) DEFAULT '0.00',
  `saldo_pendiente` decimal(10,2) DEFAULT '0.00',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `cancelada_por` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo_cancelacion` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'sistema',
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_estado` (`estado`),
  KEY `idx_cancha_fecha` (`cancha_id`,`fecha`),
  CONSTRAINT `reservas_ibfk_1` FOREIGN KEY (`cancha_id`) REFERENCES `canchas` (`id`),
  CONSTRAINT `reservas_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `chk_hora_valida` CHECK (`hora_inicio` < `hora_fin`),
  CONSTRAINT `chk_total_positivo` CHECK (`total` >= 0),
  CONSTRAINT `chk_sena_valida` CHECK (`seña` >= 0 AND `seña` <= `total`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================
-- TRIGGERS
-- =============================
DELIMITER $$

CREATE TRIGGER `before_insert_reserva`
BEFORE INSERT ON `reservas`
FOR EACH ROW
BEGIN
    DECLARE reservas_existentes INT;

    IF NEW.estado != 'cancelada' THEN
        SELECT COUNT(*) INTO reservas_existentes
        FROM reservas
        WHERE cancha_id = NEW.cancha_id
          AND fecha = NEW.fecha
          AND estado IN ('pendiente','confirmada','completada')
          AND (
              (NEW.hora_inicio >= hora_inicio AND NEW.hora_inicio < hora_fin)
              OR (NEW.hora_fin > hora_inicio AND NEW.hora_fin <= hora_fin)
              OR (NEW.hora_inicio <= hora_inicio AND NEW.hora_fin >= hora_fin)
          );

        IF reservas_existentes > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya existe una reserva activa en ese horario para esta cancha';
        END IF;
    END IF;

    SET NEW.saldo_pendiente = NEW.total - NEW.seña;
END$$

-- =============================
-- PROCEDURE (ÚNICO CAMBIO: LEAVE CON ETIQUETA)
-- =============================
CREATE PROCEDURE `sp_crear_reserva`(
    IN p_cancha_id INT,
    IN p_cliente_id INT,
    IN p_fecha DATE,
    IN p_hora_inicio TIME,
    IN p_horas INT,
    IN p_seña DECIMAL(10,2),
    IN p_metodo_pago VARCHAR(20),
    IN p_notas TEXT,
    OUT p_reserva_id INT,
    OUT p_mensaje VARCHAR(255)
)
BEGIN
    DECLARE v_precio_hora DECIMAL(10,2);
    DECLARE v_hora_actual TIME;
    DECLARE v_hora_siguiente TIME;
    DECLARE v_contador INT DEFAULT 0;
    DECLARE v_reservas_existentes INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_mensaje = 'Error al crear la reserva. Intente nuevamente.';
        SET p_reserva_id = 0;
    END;

    START TRANSACTION;

    SELECT precio_hora INTO v_precio_hora
    FROM canchas
    WHERE id = p_cancha_id AND activa = 1;

    IF v_precio_hora IS NULL THEN
        SET p_mensaje = 'Cancha no disponible';
        SET p_reserva_id = 0;
        ROLLBACK;
    ELSE
        reserva_loop: WHILE v_contador < p_horas DO
            SET v_hora_actual = ADDTIME(p_hora_inicio, CONCAT(v_contador, ':00:00'));

            SELECT COUNT(*) INTO v_reservas_existentes
            FROM reservas
            WHERE cancha_id = p_cancha_id
              AND fecha = p_fecha
              AND hora_inicio = v_hora_actual
              AND estado IN ('pendiente','confirmada','completada');

            IF v_reservas_existentes > 0 THEN
                SET p_mensaje = CONCAT('Horario no disponible: ', TIME_FORMAT(v_hora_actual,'%H:%i'));
                SET p_reserva_id = 0;
                ROLLBACK;
                LEAVE reserva_loop;
            END IF;

            SET v_contador = v_contador + 1;
        END WHILE;

        SET v_contador = 0;
        WHILE v_contador < p_horas DO
            SET v_hora_actual = ADDTIME(p_hora_inicio, CONCAT(v_contador, ':00:00'));
            SET v_hora_siguiente = ADDTIME(v_hora_actual, '01:00:00');

            INSERT INTO reservas (
                cancha_id, cliente_id, fecha,
                hora_inicio, hora_fin,
                total, estado, seña, metodo_pago, notas
            ) VALUES (
                p_cancha_id, p_cliente_id, p_fecha,
                v_hora_actual, v_hora_siguiente,
                v_precio_hora, 'confirmada',
                p_seña / p_horas, p_metodo_pago, p_notas
            );

            IF v_contador = 0 THEN
                SET p_reserva_id = LAST_INSERT_ID();
            END IF;

            SET v_contador = v_contador + 1;
        END WHILE;

        COMMIT;
        SET p_mensaje = 'Reserva creada exitosamente';
    END IF;
END$$

DELIMITER ;

COMMIT;
