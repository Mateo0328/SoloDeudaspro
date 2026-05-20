-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 25-11-2025 a las 16:37:00
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `solodeudas`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cambiar_estado_prestamo` (IN `pid_prestamo` INT, IN `pestado` ENUM('pendiente','activo','pagado','vencido','cancelado'))   BEGIN
    UPDATE prestamo
    SET estado = pestado
    WHERE id_prestamo = pid_prestamo;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_crear_cliente` (IN `pid_prestamista` INT, IN `pnombre` VARCHAR(100), IN `pcorreo` VARCHAR(100), IN `ptelefono` VARCHAR(20), IN `pdireccion` VARCHAR(200))   BEGIN
    INSERT INTO cliente(id_prestamista, nombre, correo, telefono, direccion)
    VALUES(pid_prestamista, pnombre, pcorreo, ptelefono, pdireccion);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_crear_prestamo` (IN `pid_cliente` INT, IN `pid_prestamista` INT, IN `pmonto` DECIMAL(12,2), IN `pinteres` DECIMAL(5,2), IN `pfecha_inicio` DATE, IN `pfecha_venc` DATE, IN `pobservaciones` TEXT)   BEGIN
    INSERT INTO prestamo (
        id_cliente, id_prestamista, monto_total, tasa_interes,
        fecha_inicio, fecha_vencimiento, estado, observaciones
    )
    VALUES (
        pid_cliente, pid_prestamista, pmonto, pinteres,
        pfecha_inicio, pfecha_venc, 'activo', pobservaciones
    );
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_crear_recordatorio` (IN `pid_prestamo` INT, IN `pfecha` DATETIME, IN `pmedio` ENUM('whatsapp','sms','correo','llamada'))   BEGIN
    INSERT INTO recordatorio(id_prestamo, fecha_programada, medio, estado)
    VALUES(pid_prestamo, pfecha, pmedio, 'pendiente');
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generar_datos_masivos` ()   BEGIN
    DECLARE i INT DEFAULT 1;

    -- =====================
    -- PRESTAMISTA
    -- =====================
    SET i = 1;
    WHILE i <= 100 DO
        INSERT INTO prestamista(nombre, correo, contrasena, estado)
        VALUES(
            CONCAT('Prestamista ', i),
            CONCAT('prestamista', i, '@correo.com'),
            '123456',
            'activo'
        );
        SET i = i + 1;
    END WHILE;

    -- =====================
    -- CLIENTE
    -- =====================
    SET i = 1;
    WHILE i <= 100 DO
        INSERT INTO cliente(
            id_prestamista, nombre, correo, telefono, direccion
        )
        VALUES(
            FLOOR(1 + RAND()*100),
            CONCAT('Cliente ', i),
            CONCAT('cliente', i, '@correo.com'),
            CONCAT('3200000', LPAD(i, 3, '0')),
            CONCAT('Calle ', i, ' #', i, '-', i)
        );
        SET i = i + 1;
    END WHILE;

    -- =====================
    -- CONFIGURACION USUARIO
    -- =====================
    SET i = 1;
    WHILE i <= 100 DO
        INSERT INTO configuracion_usuario(
            id_prestamista, notificaciones, idioma, moneda
        )
        VALUES(
            i,
            1,
            'es',
            'COP'
        );
        SET i = i + 1;
    END WHILE;

    -- =====================
    -- LOG SESIONES
    -- =====================
    SET i = 1;
    WHILE i <= 100 DO
        INSERT INTO log_sesiones(
            id_prestamista, ip_usuario, navegador
        )
        VALUES(
            FLOOR(1 + RAND()*100),
            CONCAT('192.168.1.', i),
            'Chrome'
        );
        SET i = i + 1;
    END WHILE;

    -- =====================
    -- PRESTAMOS
    -- =====================
    SET i = 1;
    WHILE i <= 100 DO
        INSERT INTO prestamo(
            id_cliente,
            id_prestamista,
            monto_total,
            tasa_interes,
            fecha_inicio,
            fecha_vencimiento,
            estado,
            observaciones
        )
        VALUES(
            FLOOR(1 + RAND()*100),
            FLOOR(1 + RAND()*100),
            FLOOR(100000 + RAND()*5000000),
            5.0,
            CURDATE(),
            DATE_ADD(CURDATE(), INTERVAL 60 DAY),
            'activo',
            CONCAT('Observación préstamo ', i)
        );
        SET i = i + 1;
    END WHILE;

    -- =====================
    -- PAGOS
    -- =====================
    SET i = 1;
    WHILE i <= 100 DO
        INSERT INTO pago(
            id_prestamo,
            fecha_pago,
            monto_pagado,
            forma_pago,
            recibido_por
        )
        VALUES(
            FLOOR(1 + RAND()*100),
            CURDATE(),
            FLOOR(10000 + RAND()*100000),
            'efectivo',
            'sistema'
        );
        SET i = i + 1;
    END WHILE;

    -- =====================
    -- DOCUMENTOS
    -- =====================
    SET i = 1;
    WHILE i <= 100 DO
        INSERT INTO documento(
            id_prestamo,
            url,
            tipo_documento,
            nombre_archivo
        )
        VALUES(
            FLOOR(1 + RAND()*100),
            CONCAT('https://docs.solodeudas.com/doc', i, '.pdf'),
            'contrato',
            CONCAT('contrato_', i, '.pdf')
        );
        SET i = i + 1;
    END WHILE;

    -- =====================
    -- RECORDATORIOS
    -- =====================
    SET i = 1;
    WHILE i <= 100 DO
        INSERT INTO recordatorio(
            id_prestamo,
            fecha_programada,
            medio,
            estado
        )
        VALUES(
            FLOOR(1 + RAND()*100),
            DATE_ADD(NOW(), INTERVAL i DAY),
            'whatsapp',
            'pendiente'
        );
        SET i = i + 1;
    END WHILE;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_pago` (IN `pid_prestamo` INT, IN `pmonto` DECIMAL(12,2), IN `pforma_pago` VARCHAR(50), IN `precibido` VARCHAR(100))   BEGIN
    DECLARE total_pagado DECIMAL(12,2);
    DECLARE total_prestamo DECIMAL(12,2);

    INSERT INTO pago(id_prestamo, fecha_pago, monto_pagado, forma_pago, recibido_por)
    VALUES(pid_prestamo, NOW(), pmonto, pforma_pago, precibido);

    SELECT SUM(monto_pagado) INTO total_pagado 
    FROM pago WHERE id_prestamo = pid_prestamo;

    SELECT monto_total INTO total_prestamo 
    FROM prestamo WHERE id_prestamo = pid_prestamo;

    IF total_pagado >= total_prestamo THEN
        UPDATE prestamo SET estado = 'pagado'
        WHERE id_prestamo = pid_prestamo;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente`
--

CREATE TABLE `cliente` (
  `id_cliente` int(11) NOT NULL,
  `id_prestamista` int(11) DEFAULT NULL,
  `cedula` varchar(20) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nombre` varchar(100) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cliente`
--

INSERT INTO `cliente` (`id_cliente`, `id_prestamista`, `fecha_registro`, `last_update`, `nombre`, `correo`, `telefono`, `direccion`) VALUES
(1, 10, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 1', 'cliente1@correo.com', '3200000001', 'Calle 1 #1-1'),
(2, 14, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 2', 'cliente2@correo.com', '3200000002', 'Calle 2 #2-2'),
(3, 36, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 3', 'cliente3@correo.com', '3200000003', 'Calle 3 #3-3'),
(4, 39, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 4', 'cliente4@correo.com', '3200000004', 'Calle 4 #4-4'),
(5, 87, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 5', 'cliente5@correo.com', '3200000005', 'Calle 5 #5-5'),
(6, 19, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 6', 'cliente6@correo.com', '3200000006', 'Calle 6 #6-6'),
(7, 31, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 7', 'cliente7@correo.com', '3200000007', 'Calle 7 #7-7'),
(8, 100, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 8', 'cliente8@correo.com', '3200000008', 'Calle 8 #8-8'),
(9, 7, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 9', 'cliente9@correo.com', '3200000009', 'Calle 9 #9-9'),
(10, 34, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 10', 'cliente10@correo.com', '3200000010', 'Calle 10 #10-10'),
(11, 47, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 11', 'cliente11@correo.com', '3200000011', 'Calle 11 #11-11'),
(12, 35, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 12', 'cliente12@correo.com', '3200000012', 'Calle 12 #12-12'),
(13, 30, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 13', 'cliente13@correo.com', '3200000013', 'Calle 13 #13-13'),
(14, 47, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 14', 'cliente14@correo.com', '3200000014', 'Calle 14 #14-14'),
(15, 45, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 15', 'cliente15@correo.com', '3200000015', 'Calle 15 #15-15'),
(16, 84, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 16', 'cliente16@correo.com', '3200000016', 'Calle 16 #16-16'),
(17, 84, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 17', 'cliente17@correo.com', '3200000017', 'Calle 17 #17-17'),
(18, 67, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 18', 'cliente18@correo.com', '3200000018', 'Calle 18 #18-18'),
(19, 81, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 19', 'cliente19@correo.com', '3200000019', 'Calle 19 #19-19'),
(20, 4, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 20', 'cliente20@correo.com', '3200000020', 'Calle 20 #20-20'),
(21, 78, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 21', 'cliente21@correo.com', '3200000021', 'Calle 21 #21-21'),
(22, 79, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 22', 'cliente22@correo.com', '3200000022', 'Calle 22 #22-22'),
(23, 58, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 23', 'cliente23@correo.com', '3200000023', 'Calle 23 #23-23'),
(24, 54, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 24', 'cliente24@correo.com', '3200000024', 'Calle 24 #24-24'),
(25, 94, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 25', 'cliente25@correo.com', '3200000025', 'Calle 25 #25-25'),
(26, 7, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 26', 'cliente26@correo.com', '3200000026', 'Calle 26 #26-26'),
(27, 53, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 27', 'cliente27@correo.com', '3200000027', 'Calle 27 #27-27'),
(28, 45, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 28', 'cliente28@correo.com', '3200000028', 'Calle 28 #28-28'),
(29, 67, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 29', 'cliente29@correo.com', '3200000029', 'Calle 29 #29-29'),
(30, 97, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 30', 'cliente30@correo.com', '3200000030', 'Calle 30 #30-30'),
(31, 82, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 31', 'cliente31@correo.com', '3200000031', 'Calle 31 #31-31'),
(32, 21, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 32', 'cliente32@correo.com', '3200000032', 'Calle 32 #32-32'),
(33, 57, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 33', 'cliente33@correo.com', '3200000033', 'Calle 33 #33-33'),
(34, 23, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 34', 'cliente34@correo.com', '3200000034', 'Calle 34 #34-34'),
(35, 42, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 35', 'cliente35@correo.com', '3200000035', 'Calle 35 #35-35'),
(36, 42, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 36', 'cliente36@correo.com', '3200000036', 'Calle 36 #36-36'),
(37, 84, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 37', 'cliente37@correo.com', '3200000037', 'Calle 37 #37-37'),
(38, 94, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 38', 'cliente38@correo.com', '3200000038', 'Calle 38 #38-38'),
(39, 17, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 39', 'cliente39@correo.com', '3200000039', 'Calle 39 #39-39'),
(40, 100, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 40', 'cliente40@correo.com', '3200000040', 'Calle 40 #40-40'),
(41, 52, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 41', 'cliente41@correo.com', '3200000041', 'Calle 41 #41-41'),
(42, 58, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 42', 'cliente42@correo.com', '3200000042', 'Calle 42 #42-42'),
(43, 35, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 43', 'cliente43@correo.com', '3200000043', 'Calle 43 #43-43'),
(44, 98, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 44', 'cliente44@correo.com', '3200000044', 'Calle 44 #44-44'),
(45, 87, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 45', 'cliente45@correo.com', '3200000045', 'Calle 45 #45-45'),
(46, 41, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 46', 'cliente46@correo.com', '3200000046', 'Calle 46 #46-46'),
(47, 44, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 47', 'cliente47@correo.com', '3200000047', 'Calle 47 #47-47'),
(48, 96, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 48', 'cliente48@correo.com', '3200000048', 'Calle 48 #48-48'),
(49, 46, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 49', 'cliente49@correo.com', '3200000049', 'Calle 49 #49-49'),
(50, 41, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 50', 'cliente50@correo.com', '3200000050', 'Calle 50 #50-50'),
(51, 67, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 51', 'cliente51@correo.com', '3200000051', 'Calle 51 #51-51'),
(52, 12, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 52', 'cliente52@correo.com', '3200000052', 'Calle 52 #52-52'),
(53, 59, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 53', 'cliente53@correo.com', '3200000053', 'Calle 53 #53-53'),
(54, 57, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 54', 'cliente54@correo.com', '3200000054', 'Calle 54 #54-54'),
(55, 7, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 55', 'cliente55@correo.com', '3200000055', 'Calle 55 #55-55'),
(56, 65, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 56', 'cliente56@correo.com', '3200000056', 'Calle 56 #56-56'),
(57, 3, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 57', 'cliente57@correo.com', '3200000057', 'Calle 57 #57-57'),
(58, 21, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 58', 'cliente58@correo.com', '3200000058', 'Calle 58 #58-58'),
(59, 93, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 59', 'cliente59@correo.com', '3200000059', 'Calle 59 #59-59'),
(60, 2, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 60', 'cliente60@correo.com', '3200000060', 'Calle 60 #60-60'),
(61, 31, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 61', 'cliente61@correo.com', '3200000061', 'Calle 61 #61-61'),
(62, 50, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 62', 'cliente62@correo.com', '3200000062', 'Calle 62 #62-62'),
(63, 54, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 63', 'cliente63@correo.com', '3200000063', 'Calle 63 #63-63'),
(64, 21, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 64', 'cliente64@correo.com', '3200000064', 'Calle 64 #64-64'),
(65, 42, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 65', 'cliente65@correo.com', '3200000065', 'Calle 65 #65-65'),
(66, 45, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 66', 'cliente66@correo.com', '3200000066', 'Calle 66 #66-66'),
(67, 99, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 67', 'cliente67@correo.com', '3200000067', 'Calle 67 #67-67'),
(68, 61, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 68', 'cliente68@correo.com', '3200000068', 'Calle 68 #68-68'),
(69, 8, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 69', 'cliente69@correo.com', '3200000069', 'Calle 69 #69-69'),
(70, 57, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 70', 'cliente70@correo.com', '3200000070', 'Calle 70 #70-70'),
(71, 60, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 71', 'cliente71@correo.com', '3200000071', 'Calle 71 #71-71'),
(72, 29, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 72', 'cliente72@correo.com', '3200000072', 'Calle 72 #72-72'),
(73, 62, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 73', 'cliente73@correo.com', '3200000073', 'Calle 73 #73-73'),
(74, 25, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 74', 'cliente74@correo.com', '3200000074', 'Calle 74 #74-74'),
(75, 38, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 75', 'cliente75@correo.com', '3200000075', 'Calle 75 #75-75'),
(76, 12, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 76', 'cliente76@correo.com', '3200000076', 'Calle 76 #76-76'),
(77, 48, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 77', 'cliente77@correo.com', '3200000077', 'Calle 77 #77-77'),
(78, 4, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 78', 'cliente78@correo.com', '3200000078', 'Calle 78 #78-78'),
(79, 76, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 79', 'cliente79@correo.com', '3200000079', 'Calle 79 #79-79'),
(80, 68, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 80', 'cliente80@correo.com', '3200000080', 'Calle 80 #80-80'),
(81, 12, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 81', 'cliente81@correo.com', '3200000081', 'Calle 81 #81-81'),
(82, 56, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 82', 'cliente82@correo.com', '3200000082', 'Calle 82 #82-82'),
(83, 41, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 83', 'cliente83@correo.com', '3200000083', 'Calle 83 #83-83'),
(84, 36, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 84', 'cliente84@correo.com', '3200000084', 'Calle 84 #84-84'),
(85, 57, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 85', 'cliente85@correo.com', '3200000085', 'Calle 85 #85-85'),
(86, 77, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 86', 'cliente86@correo.com', '3200000086', 'Calle 86 #86-86'),
(87, 14, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 87', 'cliente87@correo.com', '3200000087', 'Calle 87 #87-87'),
(88, 38, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 88', 'cliente88@correo.com', '3200000088', 'Calle 88 #88-88'),
(89, 49, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 89', 'cliente89@correo.com', '3200000089', 'Calle 89 #89-89'),
(90, 29, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 90', 'cliente90@correo.com', '3200000090', 'Calle 90 #90-90'),
(91, 97, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 91', 'cliente91@correo.com', '3200000091', 'Calle 91 #91-91'),
(92, 98, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 92', 'cliente92@correo.com', '3200000092', 'Calle 92 #92-92'),
(93, 99, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 93', 'cliente93@correo.com', '3200000093', 'Calle 93 #93-93'),
(94, 99, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 94', 'cliente94@correo.com', '3200000094', 'Calle 94 #94-94'),
(95, 99, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 95', 'cliente95@correo.com', '3200000095', 'Calle 95 #95-95'),
(96, 95, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 96', 'cliente96@correo.com', '3200000096', 'Calle 96 #96-96'),
(97, 80, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 97', 'cliente97@correo.com', '3200000097', 'Calle 97 #97-97'),
(98, 12, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 98', 'cliente98@correo.com', '3200000098', 'Calle 98 #98-98'),
(99, 21, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 99', 'cliente99@correo.com', '3200000099', 'Calle 99 #99-99'),
(100, 70, '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Cliente 100', 'cliente100@correo.com', '3200000100', 'Calle 100 #100-100'),
(101, 102, '2025-11-21 23:16:30', '2025-11-21 23:16:30', 'Mateo Piedrahira', 'mateo@gmail.com', '3043345676', 'sin direccion');

--
-- Disparadores `cliente`
--
DELIMITER $$
CREATE TRIGGER `tr_cliente_traza` AFTER INSERT ON `cliente` FOR EACH ROW BEGIN
    INSERT INTO trazabilidad(accion, id_registro, descripcion, tabla_afectada, usuario)
    VALUES('INSERT', NEW.id_cliente, 'Cliente creado', 'cliente', USER());
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_usuario`
--

CREATE TABLE `configuracion_usuario` (
  `id_config` int(11) NOT NULL,
  `id_prestamista` int(11) DEFAULT NULL,
  `notificaciones` tinyint(4) DEFAULT 1,
  `idioma` varchar(20) DEFAULT NULL,
  `moneda` varchar(10) DEFAULT NULL,
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion_usuario`
--

INSERT INTO `configuracion_usuario` (`id_config`, `id_prestamista`, `notificaciones`, `idioma`, `moneda`, `last_update`) VALUES
(1, 1, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(2, 2, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(3, 3, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(4, 4, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(5, 5, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(6, 6, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(7, 7, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(8, 8, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(9, 9, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(10, 10, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(11, 11, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(12, 12, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(13, 13, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(14, 14, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(15, 15, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(16, 16, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(17, 17, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(18, 18, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(19, 19, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(20, 20, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(21, 21, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(22, 22, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(23, 23, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(24, 24, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(25, 25, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(26, 26, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(27, 27, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(28, 28, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(29, 29, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(30, 30, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(31, 31, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(32, 32, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(33, 33, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(34, 34, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(35, 35, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(36, 36, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(37, 37, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(38, 38, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(39, 39, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(40, 40, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(41, 41, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(42, 42, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(43, 43, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(44, 44, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(45, 45, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(46, 46, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(47, 47, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(48, 48, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(49, 49, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(50, 50, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(51, 51, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(52, 52, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(53, 53, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(54, 54, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(55, 55, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(56, 56, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(57, 57, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(58, 58, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(59, 59, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(60, 60, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(61, 61, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(62, 62, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(63, 63, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(64, 64, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(65, 65, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(66, 66, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(67, 67, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(68, 68, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(69, 69, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(70, 70, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(71, 71, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(72, 72, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(73, 73, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(74, 74, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(75, 75, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(76, 76, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(77, 77, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(78, 78, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(79, 79, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(80, 80, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(81, 81, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(82, 82, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(83, 83, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(84, 84, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(85, 85, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(86, 86, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(87, 87, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(88, 88, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(89, 89, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(90, 90, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(91, 91, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(92, 92, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(93, 93, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(94, 94, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(95, 95, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(96, 96, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(97, 97, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(98, 98, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(99, 99, 1, 'es', 'COP', '2025-11-21 18:16:12'),
(100, 100, 1, 'es', 'COP', '2025-11-21 18:16:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento`
--

CREATE TABLE `documento` (
  `id_documento` int(11) NOT NULL,
  `id_prestamo` int(11) DEFAULT NULL,
  `url` text DEFAULT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp(),
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tipo_documento` varchar(50) DEFAULT NULL,
  `nombre_archivo` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `documento`
--

INSERT INTO `documento` (`id_documento`, `id_prestamo`, `url`, `fecha_subida`, `last_update`, `tipo_documento`, `nombre_archivo`) VALUES
(1, 89, 'https://docs.solodeudas.com/doc1.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_1.pdf'),
(2, 57, 'https://docs.solodeudas.com/doc2.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_2.pdf'),
(3, 17, 'https://docs.solodeudas.com/doc3.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_3.pdf'),
(4, 14, 'https://docs.solodeudas.com/doc4.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_4.pdf'),
(5, 18, 'https://docs.solodeudas.com/doc5.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_5.pdf'),
(6, 46, 'https://docs.solodeudas.com/doc6.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_6.pdf'),
(7, 78, 'https://docs.solodeudas.com/doc7.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_7.pdf'),
(8, 51, 'https://docs.solodeudas.com/doc8.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_8.pdf'),
(9, 21, 'https://docs.solodeudas.com/doc9.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_9.pdf'),
(10, 51, 'https://docs.solodeudas.com/doc10.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_10.pdf'),
(11, 92, 'https://docs.solodeudas.com/doc11.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_11.pdf'),
(12, 5, 'https://docs.solodeudas.com/doc12.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_12.pdf'),
(13, 50, 'https://docs.solodeudas.com/doc13.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_13.pdf'),
(14, 34, 'https://docs.solodeudas.com/doc14.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_14.pdf'),
(15, 18, 'https://docs.solodeudas.com/doc15.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_15.pdf'),
(16, 88, 'https://docs.solodeudas.com/doc16.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_16.pdf'),
(17, 86, 'https://docs.solodeudas.com/doc17.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_17.pdf'),
(18, 67, 'https://docs.solodeudas.com/doc18.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_18.pdf'),
(19, 74, 'https://docs.solodeudas.com/doc19.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_19.pdf'),
(20, 68, 'https://docs.solodeudas.com/doc20.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_20.pdf'),
(21, 18, 'https://docs.solodeudas.com/doc21.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_21.pdf'),
(22, 86, 'https://docs.solodeudas.com/doc22.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_22.pdf'),
(23, 75, 'https://docs.solodeudas.com/doc23.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_23.pdf'),
(24, 15, 'https://docs.solodeudas.com/doc24.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_24.pdf'),
(25, 50, 'https://docs.solodeudas.com/doc25.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_25.pdf'),
(26, 7, 'https://docs.solodeudas.com/doc26.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_26.pdf'),
(27, 81, 'https://docs.solodeudas.com/doc27.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_27.pdf'),
(28, 85, 'https://docs.solodeudas.com/doc28.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_28.pdf'),
(29, 83, 'https://docs.solodeudas.com/doc29.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_29.pdf'),
(30, 60, 'https://docs.solodeudas.com/doc30.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_30.pdf'),
(31, 49, 'https://docs.solodeudas.com/doc31.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_31.pdf'),
(32, 67, 'https://docs.solodeudas.com/doc32.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_32.pdf'),
(33, 84, 'https://docs.solodeudas.com/doc33.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_33.pdf'),
(34, 21, 'https://docs.solodeudas.com/doc34.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_34.pdf'),
(35, 50, 'https://docs.solodeudas.com/doc35.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_35.pdf'),
(36, 86, 'https://docs.solodeudas.com/doc36.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_36.pdf'),
(37, 81, 'https://docs.solodeudas.com/doc37.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_37.pdf'),
(38, 46, 'https://docs.solodeudas.com/doc38.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_38.pdf'),
(39, 87, 'https://docs.solodeudas.com/doc39.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_39.pdf'),
(40, 95, 'https://docs.solodeudas.com/doc40.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_40.pdf'),
(41, 14, 'https://docs.solodeudas.com/doc41.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_41.pdf'),
(42, 86, 'https://docs.solodeudas.com/doc42.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_42.pdf'),
(43, 87, 'https://docs.solodeudas.com/doc43.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_43.pdf'),
(44, 74, 'https://docs.solodeudas.com/doc44.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_44.pdf'),
(45, 12, 'https://docs.solodeudas.com/doc45.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_45.pdf'),
(46, 36, 'https://docs.solodeudas.com/doc46.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_46.pdf'),
(47, 45, 'https://docs.solodeudas.com/doc47.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_47.pdf'),
(48, 14, 'https://docs.solodeudas.com/doc48.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_48.pdf'),
(49, 37, 'https://docs.solodeudas.com/doc49.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_49.pdf'),
(50, 43, 'https://docs.solodeudas.com/doc50.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_50.pdf'),
(51, 4, 'https://docs.solodeudas.com/doc51.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_51.pdf'),
(52, 90, 'https://docs.solodeudas.com/doc52.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_52.pdf'),
(53, 37, 'https://docs.solodeudas.com/doc53.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_53.pdf'),
(54, 13, 'https://docs.solodeudas.com/doc54.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_54.pdf'),
(55, 56, 'https://docs.solodeudas.com/doc55.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_55.pdf'),
(56, 40, 'https://docs.solodeudas.com/doc56.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_56.pdf'),
(57, 32, 'https://docs.solodeudas.com/doc57.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_57.pdf'),
(58, 38, 'https://docs.solodeudas.com/doc58.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_58.pdf'),
(59, 93, 'https://docs.solodeudas.com/doc59.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_59.pdf'),
(60, 53, 'https://docs.solodeudas.com/doc60.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_60.pdf'),
(61, 83, 'https://docs.solodeudas.com/doc61.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_61.pdf'),
(62, 58, 'https://docs.solodeudas.com/doc62.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_62.pdf'),
(63, 38, 'https://docs.solodeudas.com/doc63.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_63.pdf'),
(64, 17, 'https://docs.solodeudas.com/doc64.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_64.pdf'),
(65, 71, 'https://docs.solodeudas.com/doc65.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_65.pdf'),
(66, 3, 'https://docs.solodeudas.com/doc66.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_66.pdf'),
(67, 3, 'https://docs.solodeudas.com/doc67.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_67.pdf'),
(68, 3, 'https://docs.solodeudas.com/doc68.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_68.pdf'),
(69, 7, 'https://docs.solodeudas.com/doc69.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_69.pdf'),
(70, 26, 'https://docs.solodeudas.com/doc70.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_70.pdf'),
(71, 6, 'https://docs.solodeudas.com/doc71.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_71.pdf'),
(72, 53, 'https://docs.solodeudas.com/doc72.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_72.pdf'),
(73, 48, 'https://docs.solodeudas.com/doc73.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_73.pdf'),
(74, 77, 'https://docs.solodeudas.com/doc74.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_74.pdf'),
(75, 44, 'https://docs.solodeudas.com/doc75.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_75.pdf'),
(76, 88, 'https://docs.solodeudas.com/doc76.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_76.pdf'),
(77, 7, 'https://docs.solodeudas.com/doc77.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_77.pdf'),
(78, 69, 'https://docs.solodeudas.com/doc78.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_78.pdf'),
(79, 25, 'https://docs.solodeudas.com/doc79.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_79.pdf'),
(80, 16, 'https://docs.solodeudas.com/doc80.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_80.pdf'),
(81, 4, 'https://docs.solodeudas.com/doc81.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_81.pdf'),
(82, 72, 'https://docs.solodeudas.com/doc82.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_82.pdf'),
(83, 50, 'https://docs.solodeudas.com/doc83.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_83.pdf'),
(84, 32, 'https://docs.solodeudas.com/doc84.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_84.pdf'),
(85, 8, 'https://docs.solodeudas.com/doc85.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_85.pdf'),
(86, 45, 'https://docs.solodeudas.com/doc86.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_86.pdf'),
(87, 100, 'https://docs.solodeudas.com/doc87.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_87.pdf'),
(88, 63, 'https://docs.solodeudas.com/doc88.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_88.pdf'),
(89, 14, 'https://docs.solodeudas.com/doc89.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_89.pdf'),
(90, 80, 'https://docs.solodeudas.com/doc90.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_90.pdf'),
(91, 59, 'https://docs.solodeudas.com/doc91.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_91.pdf'),
(92, 52, 'https://docs.solodeudas.com/doc92.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_92.pdf'),
(93, 85, 'https://docs.solodeudas.com/doc93.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_93.pdf'),
(94, 70, 'https://docs.solodeudas.com/doc94.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_94.pdf'),
(95, 93, 'https://docs.solodeudas.com/doc95.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_95.pdf'),
(96, 54, 'https://docs.solodeudas.com/doc96.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_96.pdf'),
(97, 91, 'https://docs.solodeudas.com/doc97.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_97.pdf'),
(98, 91, 'https://docs.solodeudas.com/doc98.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_98.pdf'),
(99, 81, 'https://docs.solodeudas.com/doc99.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_99.pdf'),
(100, 33, 'https://docs.solodeudas.com/doc100.pdf', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'contrato', 'contrato_100.pdf');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_estado_prestamo`
--

CREATE TABLE `historial_estado_prestamo` (
  `id_historial` int(11) NOT NULL,
  `id_prestamo` int(11) DEFAULT NULL,
  `estado_anterior` varchar(20) DEFAULT NULL,
  `estado_nuevo` varchar(20) DEFAULT NULL,
  `usuario_responsable` int(11) DEFAULT NULL,
  `fecha_cambio` datetime DEFAULT current_timestamp(),
  `motivo` text DEFAULT NULL,
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_sesiones`
--

CREATE TABLE `log_sesiones` (
  `id_log` int(11) NOT NULL,
  `id_prestamista` int(11) DEFAULT NULL,
  `inicio_sesion` datetime DEFAULT current_timestamp(),
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ip_usuario` varchar(100) DEFAULT NULL,
  `navegador` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `log_sesiones`
--

INSERT INTO `log_sesiones` (`id_log`, `id_prestamista`, `inicio_sesion`, `last_update`, `ip_usuario`, `navegador`) VALUES
(1, 84, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.1', 'Chrome'),
(2, 10, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.2', 'Chrome'),
(3, 98, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.3', 'Chrome'),
(4, 59, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.4', 'Chrome'),
(5, 1, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.5', 'Chrome'),
(6, 28, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.6', 'Chrome'),
(7, 36, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.7', 'Chrome'),
(8, 96, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.8', 'Chrome'),
(9, 69, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.9', 'Chrome'),
(10, 59, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.10', 'Chrome'),
(11, 86, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.11', 'Chrome'),
(12, 52, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.12', 'Chrome'),
(13, 4, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.13', 'Chrome'),
(14, 62, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.14', 'Chrome'),
(15, 100, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.15', 'Chrome'),
(16, 11, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.16', 'Chrome'),
(17, 55, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.17', 'Chrome'),
(18, 42, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.18', 'Chrome'),
(19, 45, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.19', 'Chrome'),
(20, 96, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.20', 'Chrome'),
(21, 45, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.21', 'Chrome'),
(22, 39, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.22', 'Chrome'),
(23, 57, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.23', 'Chrome'),
(24, 68, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.24', 'Chrome'),
(25, 69, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.25', 'Chrome'),
(26, 39, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.26', 'Chrome'),
(27, 88, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.27', 'Chrome'),
(28, 23, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.28', 'Chrome'),
(29, 48, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.29', 'Chrome'),
(30, 73, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.30', 'Chrome'),
(31, 22, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.31', 'Chrome'),
(32, 89, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.32', 'Chrome'),
(33, 77, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.33', 'Chrome'),
(34, 20, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.34', 'Chrome'),
(35, 67, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.35', 'Chrome'),
(36, 77, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.36', 'Chrome'),
(37, 81, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.37', 'Chrome'),
(38, 74, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.38', 'Chrome'),
(39, 27, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.39', 'Chrome'),
(40, 13, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.40', 'Chrome'),
(41, 81, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.41', 'Chrome'),
(42, 66, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.42', 'Chrome'),
(43, 88, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.43', 'Chrome'),
(44, 43, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.44', 'Chrome'),
(45, 47, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.45', 'Chrome'),
(46, 7, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.46', 'Chrome'),
(47, 94, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.47', 'Chrome'),
(48, 47, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.48', 'Chrome'),
(49, 54, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.49', 'Chrome'),
(50, 30, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.50', 'Chrome'),
(51, 85, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.51', 'Chrome'),
(52, 35, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.52', 'Chrome'),
(53, 19, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.53', 'Chrome'),
(54, 88, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.54', 'Chrome'),
(55, 83, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.55', 'Chrome'),
(56, 53, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.56', 'Chrome'),
(57, 13, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.57', 'Chrome'),
(58, 6, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.58', 'Chrome'),
(59, 91, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.59', 'Chrome'),
(60, 35, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.60', 'Chrome'),
(61, 100, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.61', 'Chrome'),
(62, 98, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.62', 'Chrome'),
(63, 87, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.63', 'Chrome'),
(64, 40, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.64', 'Chrome'),
(65, 38, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.65', 'Chrome'),
(66, 72, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.66', 'Chrome'),
(67, 43, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.67', 'Chrome'),
(68, 1, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.68', 'Chrome'),
(69, 75, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.69', 'Chrome'),
(70, 70, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.70', 'Chrome'),
(71, 23, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.71', 'Chrome'),
(72, 8, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.72', 'Chrome'),
(73, 70, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.73', 'Chrome'),
(74, 24, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.74', 'Chrome'),
(75, 9, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.75', 'Chrome'),
(76, 75, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.76', 'Chrome'),
(77, 46, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.77', 'Chrome'),
(78, 6, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.78', 'Chrome'),
(79, 89, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.79', 'Chrome'),
(80, 26, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.80', 'Chrome'),
(81, 65, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.81', 'Chrome'),
(82, 46, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.82', 'Chrome'),
(83, 33, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.83', 'Chrome'),
(84, 29, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.84', 'Chrome'),
(85, 46, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.85', 'Chrome'),
(86, 41, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.86', 'Chrome'),
(87, 69, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.87', 'Chrome'),
(88, 19, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.88', 'Chrome'),
(89, 88, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.89', 'Chrome'),
(90, 83, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.90', 'Chrome'),
(91, 52, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.91', 'Chrome'),
(92, 8, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.92', 'Chrome'),
(93, 83, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.93', 'Chrome'),
(94, 90, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.94', 'Chrome'),
(95, 2, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.95', 'Chrome'),
(96, 38, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.96', 'Chrome'),
(97, 86, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.97', 'Chrome'),
(98, 16, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.98', 'Chrome'),
(99, 19, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.99', 'Chrome'),
(100, 45, '2025-11-21 18:16:12', '2025-11-21 18:16:12', '192.168.1.100', 'Chrome'),
(101, 101, '2025-11-21 18:48:00', '2025-11-21 18:48:12', '::1', 'Mozilla/5.0 (Wi'),
(102, 101, '2025-11-21 18:48:22', '2025-11-21 18:48:22', '::1', 'Mozilla/5.0 (Wi'),
(103, 102, '2025-11-21 21:20:32', '2025-11-21 21:20:32', '::1', 'Mozilla/5.0 (Wi'),
(104, 102, '2025-11-21 22:53:08', '2025-11-21 22:53:08', '::1', 'Mozilla/5.0 (Wi'),
(105, 103, '2025-11-25 09:54:20', '2025-11-25 09:54:20', '::1', 'Mozilla/5.0 (Wi');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago`
--

CREATE TABLE `pago` (
  `id_pago` int(11) NOT NULL,
  `id_prestamo` int(11) DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `monto_pagado` decimal(12,2) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `forma_pago` varchar(50) DEFAULT NULL,
  `recibido_por` varchar(100) DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL,
  `estado_pago` enum('pendiente','validado','rechazado') DEFAULT 'pendiente',
  `confirmado` tinyint(1) DEFAULT 0,
  `confirmado_por` int(11) DEFAULT NULL,
  `confirmado_fecha` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pago`
--

INSERT INTO `pago` (`id_pago`, `id_prestamo`, `fecha_pago`, `monto_pagado`, `observacion`, `forma_pago`, `recibido_por`, `last_update`) VALUES
(1, 81, '2025-11-21', 85372.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(2, 36, '2025-11-21', 63782.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(3, 61, '2025-11-21', 53508.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(4, 35, '2025-11-21', 52622.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(5, 10, '2025-11-21', 28244.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(6, 64, '2025-11-21', 73039.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(7, 25, '2025-11-21', 43562.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(8, 95, '2025-11-21', 80321.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(9, 70, '2025-11-21', 44054.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(10, 64, '2025-11-21', 24144.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(11, 81, '2025-11-21', 72258.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(12, 69, '2025-11-21', 65616.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(13, 73, '2025-11-21', 106392.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(14, 65, '2025-11-21', 40897.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(15, 63, '2025-11-21', 29527.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(16, 11, '2025-11-21', 102935.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(17, 34, '2025-11-21', 100031.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(18, 49, '2025-11-21', 84179.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(19, 25, '2025-11-21', 109117.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(20, 23, '2025-11-21', 25624.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(21, 11, '2025-11-21', 14804.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(22, 94, '2025-11-21', 60668.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(23, 75, '2025-11-21', 29458.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(24, 75, '2025-11-21', 23702.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(25, 46, '2025-11-21', 95220.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(26, 91, '2025-11-21', 105868.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(27, 9, '2025-11-21', 64521.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(28, 48, '2025-11-21', 83087.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(29, 24, '2025-11-21', 107973.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(30, 20, '2025-11-21', 13701.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(31, 60, '2025-11-21', 98563.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(32, 64, '2025-11-21', 59398.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(33, 58, '2025-11-21', 51497.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(34, 34, '2025-11-21', 53868.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(35, 19, '2025-11-21', 70015.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(36, 46, '2025-11-21', 55477.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(37, 93, '2025-11-21', 33933.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(38, 44, '2025-11-21', 55433.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(39, 97, '2025-11-21', 57836.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(40, 49, '2025-11-21', 109998.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(41, 54, '2025-11-21', 79434.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(42, 86, '2025-11-21', 29279.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(43, 40, '2025-11-21', 51396.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(44, 88, '2025-11-21', 23011.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(45, 3, '2025-11-21', 84653.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(46, 66, '2025-11-21', 11307.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(47, 12, '2025-11-21', 62965.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(48, 31, '2025-11-21', 104567.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(49, 81, '2025-11-21', 30089.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(50, 59, '2025-11-21', 40594.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(51, 79, '2025-11-21', 10498.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(52, 68, '2025-11-21', 44113.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(53, 70, '2025-11-21', 53677.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(54, 11, '2025-11-21', 32851.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(55, 82, '2025-11-21', 51071.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(56, 60, '2025-11-21', 84695.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(57, 95, '2025-11-21', 59568.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(58, 64, '2025-11-21', 79540.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(59, 57, '2025-11-21', 85152.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(60, 6, '2025-11-21', 12037.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(61, 94, '2025-11-21', 72332.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(62, 31, '2025-11-21', 75984.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(63, 39, '2025-11-21', 102716.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(64, 50, '2025-11-21', 77742.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(65, 92, '2025-11-21', 62666.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(66, 90, '2025-11-21', 100971.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(67, 86, '2025-11-21', 64461.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(68, 16, '2025-11-21', 26026.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(69, 33, '2025-11-21', 24357.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(70, 75, '2025-11-21', 38552.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(71, 20, '2025-11-21', 23282.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(72, 8, '2025-11-21', 105391.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(73, 56, '2025-11-21', 102888.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(74, 98, '2025-11-21', 16363.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(75, 41, '2025-11-21', 94913.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(76, 3, '2025-11-21', 66079.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(77, 74, '2025-11-21', 11295.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(78, 85, '2025-11-21', 29904.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(79, 46, '2025-11-21', 76519.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(80, 97, '2025-11-21', 94726.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(81, 34, '2025-11-21', 21126.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(82, 57, '2025-11-21', 58862.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(83, 75, '2025-11-21', 38253.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(84, 17, '2025-11-21', 107054.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(85, 37, '2025-11-21', 99745.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(86, 41, '2025-11-21', 41685.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(87, 38, '2025-11-21', 104305.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(88, 58, '2025-11-21', 16646.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(89, 60, '2025-11-21', 87538.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(90, 10, '2025-11-21', 23408.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(91, 40, '2025-11-21', 66906.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(92, 67, '2025-11-21', 70542.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(93, 4, '2025-11-21', 48153.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(94, 79, '2025-11-21', 90039.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(95, 64, '2025-11-21', 87358.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(96, 97, '2025-11-21', 59461.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(97, 59, '2025-11-21', 53535.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(98, 43, '2025-11-21', 92028.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(99, 83, '2025-11-21', 76714.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12'),
(100, 86, '2025-11-21', 39322.00, NULL, 'efectivo', 'sistema', '2025-11-21 18:16:12');

--
-- Disparadores `pago`
--
DELIMITER $$
CREATE TRIGGER `tr_pago_traza` AFTER INSERT ON `pago` FOR EACH ROW BEGIN
    INSERT INTO trazabilidad(accion, id_registro, descripcion, tabla_afectada, usuario)
    VALUES('INSERT', NEW.id_pago, 'Pago registrado', 'pago', USER());
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamista`
--

CREATE TABLE `prestamista` (
  `id_prestamista` int(11) NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `rol` enum('admin','prestamista') DEFAULT 'prestamista',
  `intentos_fallidos` int(11) DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `ultimo_cambio_password` datetime DEFAULT NULL,
  `requiere_cambio_password` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nombre` varchar(100) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `contrasena` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `prestamista`
--

INSERT INTO `prestamista` (`id_prestamista`, `estado`, `fecha_creacion`, `last_update`, `nombre`, `correo`, `contrasena`) VALUES
(1, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 1', 'prestamista1@correo.com', '123456'),
(2, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 2', 'prestamista2@correo.com', '123456'),
(3, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 3', 'prestamista3@correo.com', '123456'),
(4, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 4', 'prestamista4@correo.com', '123456'),
(5, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 5', 'prestamista5@correo.com', '123456'),
(6, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 6', 'prestamista6@correo.com', '123456'),
(7, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 7', 'prestamista7@correo.com', '123456'),
(8, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 8', 'prestamista8@correo.com', '123456'),
(9, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 9', 'prestamista9@correo.com', '123456'),
(10, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 10', 'prestamista10@correo.com', '123456'),
(11, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 11', 'prestamista11@correo.com', '123456'),
(12, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 12', 'prestamista12@correo.com', '123456'),
(13, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 13', 'prestamista13@correo.com', '123456'),
(14, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 14', 'prestamista14@correo.com', '123456'),
(15, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 15', 'prestamista15@correo.com', '123456'),
(16, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 16', 'prestamista16@correo.com', '123456'),
(17, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 17', 'prestamista17@correo.com', '123456'),
(18, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 18', 'prestamista18@correo.com', '123456'),
(19, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 19', 'prestamista19@correo.com', '123456'),
(20, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 20', 'prestamista20@correo.com', '123456'),
(21, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 21', 'prestamista21@correo.com', '123456'),
(22, 'activo', '2025-11-21 18:16:11', '2025-11-21 18:16:11', 'Prestamista 22', 'prestamista22@correo.com', '123456'),
(23, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 23', 'prestamista23@correo.com', '123456'),
(24, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 24', 'prestamista24@correo.com', '123456'),
(25, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 25', 'prestamista25@correo.com', '123456'),
(26, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 26', 'prestamista26@correo.com', '123456'),
(27, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 27', 'prestamista27@correo.com', '123456'),
(28, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 28', 'prestamista28@correo.com', '123456'),
(29, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 29', 'prestamista29@correo.com', '123456'),
(30, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 30', 'prestamista30@correo.com', '123456'),
(31, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 31', 'prestamista31@correo.com', '123456'),
(32, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 32', 'prestamista32@correo.com', '123456'),
(33, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 33', 'prestamista33@correo.com', '123456'),
(34, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 34', 'prestamista34@correo.com', '123456'),
(35, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 35', 'prestamista35@correo.com', '123456'),
(36, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 36', 'prestamista36@correo.com', '123456'),
(37, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 37', 'prestamista37@correo.com', '123456'),
(38, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 38', 'prestamista38@correo.com', '123456'),
(39, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 39', 'prestamista39@correo.com', '123456'),
(40, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 40', 'prestamista40@correo.com', '123456'),
(41, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 41', 'prestamista41@correo.com', '123456'),
(42, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 42', 'prestamista42@correo.com', '123456'),
(43, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 43', 'prestamista43@correo.com', '123456'),
(44, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 44', 'prestamista44@correo.com', '123456'),
(45, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 45', 'prestamista45@correo.com', '123456'),
(46, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 46', 'prestamista46@correo.com', '123456'),
(47, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 47', 'prestamista47@correo.com', '123456'),
(48, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 48', 'prestamista48@correo.com', '123456'),
(49, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 49', 'prestamista49@correo.com', '123456'),
(50, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 50', 'prestamista50@correo.com', '123456'),
(51, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 51', 'prestamista51@correo.com', '123456'),
(52, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 52', 'prestamista52@correo.com', '123456'),
(53, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 53', 'prestamista53@correo.com', '123456'),
(54, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 54', 'prestamista54@correo.com', '123456'),
(55, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 55', 'prestamista55@correo.com', '123456'),
(56, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 56', 'prestamista56@correo.com', '123456'),
(57, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 57', 'prestamista57@correo.com', '123456'),
(58, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 58', 'prestamista58@correo.com', '123456'),
(59, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 59', 'prestamista59@correo.com', '123456'),
(60, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 60', 'prestamista60@correo.com', '123456'),
(61, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 61', 'prestamista61@correo.com', '123456'),
(62, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 62', 'prestamista62@correo.com', '123456'),
(63, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 63', 'prestamista63@correo.com', '123456'),
(64, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 64', 'prestamista64@correo.com', '123456'),
(65, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 65', 'prestamista65@correo.com', '123456'),
(66, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 66', 'prestamista66@correo.com', '123456'),
(67, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 67', 'prestamista67@correo.com', '123456'),
(68, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 68', 'prestamista68@correo.com', '123456'),
(69, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 69', 'prestamista69@correo.com', '123456'),
(70, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 70', 'prestamista70@correo.com', '123456'),
(71, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 71', 'prestamista71@correo.com', '123456'),
(72, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 72', 'prestamista72@correo.com', '123456'),
(73, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 73', 'prestamista73@correo.com', '123456'),
(74, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 74', 'prestamista74@correo.com', '123456'),
(75, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 75', 'prestamista75@correo.com', '123456'),
(76, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 76', 'prestamista76@correo.com', '123456'),
(77, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 77', 'prestamista77@correo.com', '123456'),
(78, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 78', 'prestamista78@correo.com', '123456'),
(79, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 79', 'prestamista79@correo.com', '123456'),
(80, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 80', 'prestamista80@correo.com', '123456'),
(81, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 81', 'prestamista81@correo.com', '123456'),
(82, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 82', 'prestamista82@correo.com', '123456'),
(83, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 83', 'prestamista83@correo.com', '123456'),
(84, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 84', 'prestamista84@correo.com', '123456'),
(85, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 85', 'prestamista85@correo.com', '123456'),
(86, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 86', 'prestamista86@correo.com', '123456'),
(87, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 87', 'prestamista87@correo.com', '123456'),
(88, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 88', 'prestamista88@correo.com', '123456'),
(89, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 89', 'prestamista89@correo.com', '123456'),
(90, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 90', 'prestamista90@correo.com', '123456'),
(91, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 91', 'prestamista91@correo.com', '123456'),
(92, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 92', 'prestamista92@correo.com', '123456'),
(93, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 93', 'prestamista93@correo.com', '123456'),
(94, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 94', 'prestamista94@correo.com', '123456'),
(95, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 95', 'prestamista95@correo.com', '123456'),
(96, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 96', 'prestamista96@correo.com', '123456'),
(97, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 97', 'prestamista97@correo.com', '123456'),
(98, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 98', 'prestamista98@correo.com', '123456'),
(99, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 99', 'prestamista99@correo.com', '123456'),
(100, 'activo', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'Prestamista 100', 'prestamista100@correo.com', '123456'),
(101, 'activo', '2025-11-21 18:48:00', '2025-11-21 18:48:00', 'Johan Steven calderón Bedoya', 'stevencalderon113@gmail.com', '$2y$10$EM6ecSgflzypX6EnDplLBu/ZPoMMdZL5zaYPsU/JkOs3eiq.cMKFS'),
(102, 'activo', '2025-11-21 21:20:23', '2025-11-21 21:20:23', 'Steven Calderón', 'stivencalderon113@gmail.com', 'steven123'),
(103, 'activo', '2025-11-25 09:51:08', '2025-11-25 09:51:08', 'juan puto', 'mateo@gmail.com', '12345');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamo`
--

CREATE TABLE `prestamo` (
  `id_prestamo` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `id_prestamista` int(11) DEFAULT NULL,
  `monto_total` decimal(12,2) DEFAULT NULL,
  `tasa_interes` decimal(5,2) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `estado` enum('pendiente','activo','pagado','vencido','cancelado') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `prestamo`
--

INSERT INTO `prestamo` (`id_prestamo`, `id_cliente`, `id_prestamista`, `monto_total`, `tasa_interes`, `fecha_inicio`, `fecha_vencimiento`, `estado`, `observaciones`, `last_update`) VALUES
(1, 70, 16, 3473429.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 1', '2025-11-21 18:16:12'),
(2, 91, 53, 4497222.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 2', '2025-11-21 18:16:12'),
(3, 84, 53, 795183.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 3', '2025-11-21 18:16:12'),
(4, 12, 15, 1989706.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 4', '2025-11-21 18:16:12'),
(5, 47, 18, 2506551.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 5', '2025-11-21 18:16:12'),
(6, 89, 100, 1602028.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 6', '2025-11-21 18:16:12'),
(7, 53, 73, 285421.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 7', '2025-11-21 18:16:12'),
(8, 2, 99, 4378941.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 8', '2025-11-21 18:16:12'),
(9, 34, 10, 2408928.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 9', '2025-11-21 18:16:12'),
(10, 4, 79, 4282877.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 10', '2025-11-21 18:16:12'),
(11, 82, 59, 2389132.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 11', '2025-11-21 18:16:12'),
(12, 55, 34, 292909.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 12', '2025-11-21 18:16:12'),
(13, 20, 87, 3726170.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 13', '2025-11-21 18:16:12'),
(14, 4, 2, 4985080.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 14', '2025-11-21 18:16:12'),
(15, 83, 22, 2999811.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 15', '2025-11-21 18:16:12'),
(16, 26, 56, 187758.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 16', '2025-11-21 18:16:12'),
(17, 41, 100, 3825746.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 17', '2025-11-21 18:16:12'),
(18, 75, 48, 790251.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 18', '2025-11-21 18:16:12'),
(19, 28, 95, 4724954.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 19', '2025-11-21 18:16:12'),
(20, 78, 13, 1459600.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 20', '2025-11-21 18:16:12'),
(21, 100, 16, 3994266.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 21', '2025-11-21 18:16:12'),
(22, 44, 86, 4932101.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 22', '2025-11-21 18:16:12'),
(23, 27, 42, 1522908.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 23', '2025-11-21 18:16:12'),
(24, 18, 5, 3465964.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 24', '2025-11-21 18:16:12'),
(25, 24, 18, 958672.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 25', '2025-11-21 18:16:12'),
(26, 33, 11, 2912726.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 26', '2025-11-21 18:16:12'),
(27, 50, 77, 1933962.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 27', '2025-11-21 18:16:12'),
(28, 53, 55, 873364.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 28', '2025-11-21 18:16:12'),
(29, 13, 18, 2452183.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 29', '2025-11-21 18:16:12'),
(30, 85, 80, 2393826.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 30', '2025-11-21 18:16:12'),
(31, 91, 15, 5097174.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 31', '2025-11-21 18:16:12'),
(32, 58, 86, 2940643.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 32', '2025-11-21 18:16:12'),
(33, 28, 66, 2427777.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 33', '2025-11-21 18:16:12'),
(34, 36, 40, 4624820.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 34', '2025-11-21 18:16:12'),
(35, 34, 97, 4235698.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 35', '2025-11-21 18:16:12'),
(36, 24, 69, 3785760.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 36', '2025-11-21 18:16:12'),
(37, 63, 91, 3296962.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 37', '2025-11-21 18:16:12'),
(38, 50, 55, 1341442.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 38', '2025-11-21 18:16:12'),
(39, 61, 29, 3097413.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 39', '2025-11-21 18:16:12'),
(40, 15, 94, 1374159.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 40', '2025-11-21 18:16:12'),
(41, 46, 52, 1102576.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 41', '2025-11-21 18:16:12'),
(42, 47, 71, 903844.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 42', '2025-11-21 18:16:12'),
(43, 68, 90, 2337968.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 43', '2025-11-21 18:16:12'),
(44, 56, 43, 2433663.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 44', '2025-11-21 18:16:12'),
(45, 6, 89, 1367946.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 45', '2025-11-21 18:16:12'),
(46, 62, 31, 3526820.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 46', '2025-11-21 18:16:12'),
(47, 52, 51, 5011050.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 47', '2025-11-21 18:16:12'),
(48, 41, 6, 459925.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 48', '2025-11-21 18:16:12'),
(49, 20, 77, 1295695.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 49', '2025-11-21 18:16:12'),
(50, 90, 78, 1037679.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 50', '2025-11-21 18:16:12'),
(51, 61, 48, 2830563.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 51', '2025-11-21 18:16:12'),
(52, 31, 92, 3227729.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 52', '2025-11-21 18:16:12'),
(53, 40, 10, 1593160.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 53', '2025-11-21 18:16:12'),
(54, 21, 13, 104162.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 54', '2025-11-21 18:16:12'),
(55, 64, 19, 117640.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 55', '2025-11-21 18:16:12'),
(56, 47, 34, 1398876.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 56', '2025-11-21 18:16:12'),
(57, 30, 72, 3533858.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 57', '2025-11-21 18:16:12'),
(58, 29, 36, 4738978.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 58', '2025-11-21 18:16:12'),
(59, 58, 8, 3504082.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 59', '2025-11-21 18:16:12'),
(60, 17, 79, 2174199.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 60', '2025-11-21 18:16:12'),
(61, 73, 40, 4125715.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 61', '2025-11-21 18:16:12'),
(62, 84, 75, 1153857.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 62', '2025-11-21 18:16:12'),
(63, 84, 53, 714299.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 63', '2025-11-21 18:16:12'),
(64, 5, 86, 789812.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 64', '2025-11-21 18:16:12'),
(65, 13, 23, 3698887.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 65', '2025-11-21 18:16:12'),
(66, 94, 54, 4386092.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 66', '2025-11-21 18:16:12'),
(67, 69, 84, 817413.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 67', '2025-11-21 18:16:12'),
(68, 21, 59, 1768625.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 68', '2025-11-21 18:16:12'),
(69, 91, 51, 4219897.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 69', '2025-11-21 18:16:12'),
(70, 61, 55, 4638935.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 70', '2025-11-21 18:16:12'),
(71, 91, 83, 1977629.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 71', '2025-11-21 18:16:12'),
(72, 42, 96, 2759100.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 72', '2025-11-21 18:16:12'),
(73, 80, 36, 2132071.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 73', '2025-11-21 18:16:12'),
(74, 97, 62, 870001.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 74', '2025-11-21 18:16:12'),
(75, 94, 24, 1834165.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 75', '2025-11-21 18:16:12'),
(76, 4, 14, 3043195.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 76', '2025-11-21 18:16:12'),
(77, 53, 87, 3733201.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 77', '2025-11-21 18:16:12'),
(78, 6, 8, 1289811.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 78', '2025-11-21 18:16:12'),
(79, 96, 6, 2188505.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 79', '2025-11-21 18:16:12'),
(80, 93, 36, 5055506.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 80', '2025-11-21 18:16:12'),
(81, 91, 55, 150853.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 81', '2025-11-21 18:16:12'),
(82, 42, 7, 369643.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 82', '2025-11-21 18:16:12'),
(83, 9, 26, 298469.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 83', '2025-11-21 18:16:12'),
(84, 43, 100, 3697151.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 84', '2025-11-21 18:16:12'),
(85, 61, 86, 2513492.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 85', '2025-11-21 18:16:12'),
(86, 84, 75, 1056305.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 86', '2025-11-21 18:16:12'),
(87, 74, 10, 1440124.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 87', '2025-11-21 18:16:12'),
(88, 6, 50, 1489740.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 88', '2025-11-21 18:16:12'),
(89, 92, 75, 5095849.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 89', '2025-11-21 18:16:12'),
(90, 75, 74, 2244990.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 90', '2025-11-21 18:16:12'),
(91, 95, 44, 1811280.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 91', '2025-11-21 18:16:12'),
(92, 41, 1, 4087718.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 92', '2025-11-21 18:16:12'),
(93, 98, 50, 2912951.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 93', '2025-11-21 18:16:12'),
(94, 32, 89, 2467720.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 94', '2025-11-21 18:16:12'),
(95, 72, 17, 3567930.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 95', '2025-11-21 18:16:12'),
(96, 96, 72, 3606002.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 96', '2025-11-21 18:16:12'),
(97, 36, 69, 1946057.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 97', '2025-11-21 18:16:12'),
(98, 78, 79, 3122582.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 98', '2025-11-21 18:16:12'),
(99, 66, 48, 2063059.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 99', '2025-11-21 18:16:12'),
(100, 55, 55, 535020.00, 5.00, '2025-11-21', '2026-01-20', 'activo', 'Observación préstamo 100', '2025-11-21 18:16:12'),
(101, 101, 102, 800000.00, 7.00, '2025-05-04', '2025-06-04', 'activo', 'sin observaciones', '2025-11-21 23:17:24');

--
-- Disparadores `prestamo`
--
DELIMITER $$
CREATE TRIGGER `tr_estado_historial` AFTER UPDATE ON `prestamo` FOR EACH ROW BEGIN
    IF OLD.estado <> NEW.estado THEN
        INSERT INTO historial_estado_prestamo(
            id_prestamo, estado_anterior, estado_nuevo, motivo
        ) VALUES (
            NEW.id_prestamo, OLD.estado, NEW.estado, 'Cambio automático'
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_prestamo_traza_insert` AFTER INSERT ON `prestamo` FOR EACH ROW BEGIN
    INSERT INTO trazabilidad(accion, id_registro, descripcion, tabla_afectada, usuario)
    VALUES('INSERT', NEW.id_prestamo, 'Préstamo creado', 'prestamo', USER());
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recordatorio`
--

CREATE TABLE `recordatorio` (
  `id_recordatorio` int(11) NOT NULL,
  `id_prestamo` int(11) DEFAULT NULL,
  `fecha_programada` datetime DEFAULT NULL,
  `medio` enum('whatsapp','sms','correo','llamada') DEFAULT NULL,
  `estado` enum('pendiente','enviado','fallido') DEFAULT NULL,
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ediciones_prestamo`
--

CREATE TABLE `ediciones_prestamo` (
  `id_edicion` int(11) NOT NULL,
  `id_prestamo` int(11) NOT NULL,
  `solicitado_por` int(11) NOT NULL,
  `fecha_solicitud` datetime DEFAULT current_timestamp(),
  `cambios` text NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `revisado_por` int(11) DEFAULT NULL,
  `fecha_revision` datetime DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones_usuario`
--

CREATE TABLE `sesiones_usuario` (
  `id_sesion` int(11) NOT NULL,
  `id_prestamista` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `estado` enum('abierta','cerrada') NOT NULL DEFAULT 'abierta',
  `ip_usuario` varchar(100) DEFAULT NULL,
  `navegador` varchar(255) DEFAULT NULL,
  `inicio_sesion` datetime NOT NULL DEFAULT current_timestamp(),
  `ultima_actividad` datetime NOT NULL DEFAULT current_timestamp(),
  `cierre_sesion` datetime DEFAULT NULL,
  `motivo_cierre` varchar(100) DEFAULT NULL,
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `recordatorio`
--

INSERT INTO `recordatorio` (`id_recordatorio`, `id_prestamo`, `fecha_programada`, `medio`, `estado`, `last_update`) VALUES
(1, 20, '2025-11-22 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(2, 3, '2025-11-23 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(3, 54, '2025-11-24 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(4, 58, '2025-11-25 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(5, 29, '2025-11-26 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(6, 69, '2025-11-27 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(7, 58, '2025-11-28 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(8, 81, '2025-11-29 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(9, 33, '2025-11-30 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(10, 19, '2025-12-01 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(11, 96, '2025-12-02 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(12, 21, '2025-12-03 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(13, 20, '2025-12-04 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(14, 33, '2025-12-05 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(15, 6, '2025-12-06 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(16, 32, '2025-12-07 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(17, 40, '2025-12-08 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(18, 3, '2025-12-09 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(19, 96, '2025-12-10 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(20, 72, '2025-12-11 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(21, 70, '2025-12-12 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(22, 32, '2025-12-13 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(23, 51, '2025-12-14 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(24, 57, '2025-12-15 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(25, 34, '2025-12-16 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(26, 96, '2025-12-17 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(27, 80, '2025-12-18 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(28, 11, '2025-12-19 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(29, 13, '2025-12-20 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(30, 32, '2025-12-21 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(31, 22, '2025-12-22 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(32, 13, '2025-12-23 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(33, 98, '2025-12-24 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(34, 52, '2025-12-25 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(35, 65, '2025-12-26 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(36, 67, '2025-12-27 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(37, 41, '2025-12-28 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(38, 3, '2025-12-29 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(39, 92, '2025-12-30 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(40, 48, '2025-12-31 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(41, 65, '2026-01-01 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(42, 81, '2026-01-02 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(43, 11, '2026-01-03 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(44, 10, '2026-01-04 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(45, 17, '2026-01-05 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(46, 56, '2026-01-06 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(47, 27, '2026-01-07 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(48, 68, '2026-01-08 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(49, 58, '2026-01-09 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(50, 83, '2026-01-10 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(51, 43, '2026-01-11 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(52, 66, '2026-01-12 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(53, 1, '2026-01-13 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(54, 6, '2026-01-14 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(55, 28, '2026-01-15 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(56, 19, '2026-01-16 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(57, 11, '2026-01-17 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(58, 99, '2026-01-18 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(59, 61, '2026-01-19 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(60, 8, '2026-01-20 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(61, 57, '2026-01-21 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(62, 60, '2026-01-22 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(63, 26, '2026-01-23 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(64, 53, '2026-01-24 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(65, 86, '2026-01-25 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(66, 68, '2026-01-26 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(67, 83, '2026-01-27 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(68, 10, '2026-01-28 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(69, 99, '2026-01-29 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(70, 65, '2026-01-30 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(71, 28, '2026-01-31 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(72, 47, '2026-02-01 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(73, 47, '2026-02-02 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(74, 96, '2026-02-03 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(75, 37, '2026-02-04 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(76, 97, '2026-02-05 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(77, 71, '2026-02-06 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(78, 67, '2026-02-07 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(79, 20, '2026-02-08 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(80, 99, '2026-02-09 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(81, 34, '2026-02-10 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(82, 74, '2026-02-11 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(83, 68, '2026-02-12 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(84, 17, '2026-02-13 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(85, 79, '2026-02-14 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(86, 45, '2026-02-15 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(87, 88, '2026-02-16 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(88, 5, '2026-02-17 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(89, 62, '2026-02-18 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(90, 91, '2026-02-19 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(91, 70, '2026-02-20 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(92, 78, '2026-02-21 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(93, 80, '2026-02-22 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(94, 65, '2026-02-23 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(95, 82, '2026-02-24 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(96, 18, '2026-02-25 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(97, 40, '2026-02-26 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(98, 49, '2026-02-27 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(99, 21, '2026-02-28 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12'),
(100, 60, '2026-03-01 18:16:12', 'whatsapp', 'pendiente', '2025-11-21 18:16:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trazabilidad`
--

CREATE TABLE `trazabilidad` (
  `id_traza` int(11) NOT NULL,
  `accion` enum('INSERT','UPDATE','DELETE') DEFAULT NULL,
  `id_registro` int(11) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_evento` datetime DEFAULT current_timestamp(),
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `usuario` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `trazabilidad`
--

INSERT INTO `trazabilidad` (`id_traza`, `accion`, `id_registro`, `descripcion`, `fecha_evento`, `last_update`, `tabla_afectada`, `usuario`) VALUES
(1, 'INSERT', 1, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(2, 'INSERT', 2, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(3, 'INSERT', 3, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(4, 'INSERT', 4, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(5, 'INSERT', 5, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(6, 'INSERT', 6, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(7, 'INSERT', 7, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(8, 'INSERT', 8, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(9, 'INSERT', 9, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(10, 'INSERT', 10, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(11, 'INSERT', 11, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(12, 'INSERT', 12, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(13, 'INSERT', 13, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(14, 'INSERT', 14, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(15, 'INSERT', 15, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(16, 'INSERT', 16, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(17, 'INSERT', 17, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(18, 'INSERT', 18, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(19, 'INSERT', 19, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(20, 'INSERT', 20, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(21, 'INSERT', 21, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(22, 'INSERT', 22, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(23, 'INSERT', 23, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(24, 'INSERT', 24, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(25, 'INSERT', 25, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(26, 'INSERT', 26, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(27, 'INSERT', 27, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(28, 'INSERT', 28, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(29, 'INSERT', 29, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(30, 'INSERT', 30, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(31, 'INSERT', 31, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(32, 'INSERT', 32, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(33, 'INSERT', 33, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(34, 'INSERT', 34, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(35, 'INSERT', 35, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(36, 'INSERT', 36, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(37, 'INSERT', 37, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(38, 'INSERT', 38, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(39, 'INSERT', 39, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(40, 'INSERT', 40, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(41, 'INSERT', 41, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(42, 'INSERT', 42, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(43, 'INSERT', 43, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(44, 'INSERT', 44, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(45, 'INSERT', 45, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(46, 'INSERT', 46, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(47, 'INSERT', 47, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(48, 'INSERT', 48, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(49, 'INSERT', 49, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(50, 'INSERT', 50, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(51, 'INSERT', 51, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(52, 'INSERT', 52, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(53, 'INSERT', 53, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(54, 'INSERT', 54, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(55, 'INSERT', 55, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(56, 'INSERT', 56, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(57, 'INSERT', 57, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(58, 'INSERT', 58, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(59, 'INSERT', 59, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(60, 'INSERT', 60, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(61, 'INSERT', 61, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(62, 'INSERT', 62, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(63, 'INSERT', 63, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(64, 'INSERT', 64, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(65, 'INSERT', 65, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(66, 'INSERT', 66, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(67, 'INSERT', 67, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(68, 'INSERT', 68, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(69, 'INSERT', 69, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(70, 'INSERT', 70, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(71, 'INSERT', 71, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(72, 'INSERT', 72, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(73, 'INSERT', 73, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(74, 'INSERT', 74, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(75, 'INSERT', 75, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(76, 'INSERT', 76, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(77, 'INSERT', 77, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(78, 'INSERT', 78, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(79, 'INSERT', 79, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(80, 'INSERT', 80, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(81, 'INSERT', 81, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(82, 'INSERT', 82, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(83, 'INSERT', 83, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(84, 'INSERT', 84, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(85, 'INSERT', 85, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(86, 'INSERT', 86, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(87, 'INSERT', 87, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(88, 'INSERT', 88, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(89, 'INSERT', 89, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(90, 'INSERT', 90, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(91, 'INSERT', 91, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(92, 'INSERT', 92, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(93, 'INSERT', 93, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(94, 'INSERT', 94, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(95, 'INSERT', 95, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(96, 'INSERT', 96, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(97, 'INSERT', 97, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(98, 'INSERT', 98, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(99, 'INSERT', 99, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(100, 'INSERT', 100, 'Cliente creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'cliente', 'root@localhost'),
(101, 'INSERT', 1, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(102, 'INSERT', 2, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(103, 'INSERT', 3, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(104, 'INSERT', 4, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(105, 'INSERT', 5, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(106, 'INSERT', 6, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(107, 'INSERT', 7, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(108, 'INSERT', 8, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(109, 'INSERT', 9, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(110, 'INSERT', 10, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(111, 'INSERT', 11, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(112, 'INSERT', 12, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(113, 'INSERT', 13, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(114, 'INSERT', 14, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(115, 'INSERT', 15, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(116, 'INSERT', 16, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(117, 'INSERT', 17, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(118, 'INSERT', 18, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(119, 'INSERT', 19, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(120, 'INSERT', 20, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(121, 'INSERT', 21, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(122, 'INSERT', 22, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(123, 'INSERT', 23, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(124, 'INSERT', 24, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(125, 'INSERT', 25, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(126, 'INSERT', 26, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(127, 'INSERT', 27, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(128, 'INSERT', 28, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(129, 'INSERT', 29, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(130, 'INSERT', 30, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(131, 'INSERT', 31, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(132, 'INSERT', 32, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(133, 'INSERT', 33, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(134, 'INSERT', 34, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(135, 'INSERT', 35, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(136, 'INSERT', 36, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(137, 'INSERT', 37, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(138, 'INSERT', 38, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(139, 'INSERT', 39, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(140, 'INSERT', 40, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(141, 'INSERT', 41, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(142, 'INSERT', 42, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(143, 'INSERT', 43, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(144, 'INSERT', 44, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(145, 'INSERT', 45, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(146, 'INSERT', 46, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(147, 'INSERT', 47, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(148, 'INSERT', 48, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(149, 'INSERT', 49, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(150, 'INSERT', 50, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(151, 'INSERT', 51, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(152, 'INSERT', 52, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(153, 'INSERT', 53, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(154, 'INSERT', 54, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(155, 'INSERT', 55, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(156, 'INSERT', 56, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(157, 'INSERT', 57, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(158, 'INSERT', 58, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(159, 'INSERT', 59, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(160, 'INSERT', 60, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(161, 'INSERT', 61, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(162, 'INSERT', 62, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(163, 'INSERT', 63, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(164, 'INSERT', 64, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(165, 'INSERT', 65, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(166, 'INSERT', 66, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(167, 'INSERT', 67, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(168, 'INSERT', 68, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(169, 'INSERT', 69, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(170, 'INSERT', 70, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(171, 'INSERT', 71, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(172, 'INSERT', 72, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(173, 'INSERT', 73, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(174, 'INSERT', 74, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(175, 'INSERT', 75, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(176, 'INSERT', 76, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(177, 'INSERT', 77, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(178, 'INSERT', 78, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(179, 'INSERT', 79, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(180, 'INSERT', 80, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(181, 'INSERT', 81, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(182, 'INSERT', 82, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(183, 'INSERT', 83, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(184, 'INSERT', 84, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(185, 'INSERT', 85, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(186, 'INSERT', 86, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(187, 'INSERT', 87, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(188, 'INSERT', 88, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(189, 'INSERT', 89, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(190, 'INSERT', 90, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(191, 'INSERT', 91, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(192, 'INSERT', 92, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(193, 'INSERT', 93, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(194, 'INSERT', 94, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(195, 'INSERT', 95, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(196, 'INSERT', 96, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(197, 'INSERT', 97, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(198, 'INSERT', 98, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(199, 'INSERT', 99, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(200, 'INSERT', 100, 'Préstamo creado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'prestamo', 'root@localhost'),
(201, 'INSERT', 1, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(202, 'INSERT', 2, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(203, 'INSERT', 3, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(204, 'INSERT', 4, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(205, 'INSERT', 5, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(206, 'INSERT', 6, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(207, 'INSERT', 7, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(208, 'INSERT', 8, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(209, 'INSERT', 9, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(210, 'INSERT', 10, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(211, 'INSERT', 11, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(212, 'INSERT', 12, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(213, 'INSERT', 13, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(214, 'INSERT', 14, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(215, 'INSERT', 15, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(216, 'INSERT', 16, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(217, 'INSERT', 17, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(218, 'INSERT', 18, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(219, 'INSERT', 19, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(220, 'INSERT', 20, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(221, 'INSERT', 21, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(222, 'INSERT', 22, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(223, 'INSERT', 23, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(224, 'INSERT', 24, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(225, 'INSERT', 25, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(226, 'INSERT', 26, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(227, 'INSERT', 27, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(228, 'INSERT', 28, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(229, 'INSERT', 29, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(230, 'INSERT', 30, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(231, 'INSERT', 31, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(232, 'INSERT', 32, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(233, 'INSERT', 33, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(234, 'INSERT', 34, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(235, 'INSERT', 35, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(236, 'INSERT', 36, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(237, 'INSERT', 37, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(238, 'INSERT', 38, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(239, 'INSERT', 39, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(240, 'INSERT', 40, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(241, 'INSERT', 41, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(242, 'INSERT', 42, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(243, 'INSERT', 43, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(244, 'INSERT', 44, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(245, 'INSERT', 45, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(246, 'INSERT', 46, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(247, 'INSERT', 47, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(248, 'INSERT', 48, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(249, 'INSERT', 49, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(250, 'INSERT', 50, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(251, 'INSERT', 51, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(252, 'INSERT', 52, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(253, 'INSERT', 53, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(254, 'INSERT', 54, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(255, 'INSERT', 55, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(256, 'INSERT', 56, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(257, 'INSERT', 57, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(258, 'INSERT', 58, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(259, 'INSERT', 59, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(260, 'INSERT', 60, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(261, 'INSERT', 61, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(262, 'INSERT', 62, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(263, 'INSERT', 63, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(264, 'INSERT', 64, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(265, 'INSERT', 65, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(266, 'INSERT', 66, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(267, 'INSERT', 67, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(268, 'INSERT', 68, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(269, 'INSERT', 69, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(270, 'INSERT', 70, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(271, 'INSERT', 71, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(272, 'INSERT', 72, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(273, 'INSERT', 73, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(274, 'INSERT', 74, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(275, 'INSERT', 75, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(276, 'INSERT', 76, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(277, 'INSERT', 77, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(278, 'INSERT', 78, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(279, 'INSERT', 79, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(280, 'INSERT', 80, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(281, 'INSERT', 81, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(282, 'INSERT', 82, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(283, 'INSERT', 83, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(284, 'INSERT', 84, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(285, 'INSERT', 85, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(286, 'INSERT', 86, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(287, 'INSERT', 87, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(288, 'INSERT', 88, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(289, 'INSERT', 89, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(290, 'INSERT', 90, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(291, 'INSERT', 91, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(292, 'INSERT', 92, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(293, 'INSERT', 93, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(294, 'INSERT', 94, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(295, 'INSERT', 95, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(296, 'INSERT', 96, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(297, 'INSERT', 97, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(298, 'INSERT', 98, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(299, 'INSERT', 99, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(300, 'INSERT', 100, 'Pago registrado', '2025-11-21 18:16:12', '2025-11-21 18:16:12', 'pago', 'root@localhost'),
(301, 'INSERT', 101, 'Registro de nuevo prestamista ID: 101', '2025-11-21 18:48:00', '2025-11-21 18:48:00', 'prestamista', 'Johan Steven calderón Bedoya'),
(302, 'UPDATE', 101, 'Cierre de sesión', '2025-11-21 18:48:12', '2025-11-21 18:48:12', 'prestamista', 'Johan Steven calderón Bedoya'),
(303, 'INSERT', 102, 'Inicio de sesión del prestamista ID: 101', '2025-11-21 18:48:22', '2025-11-21 18:48:22', 'prestamista', 'Johan Steven calderón Bedoya'),
(304, 'INSERT', 101, 'Cliente creado', '2025-11-21 23:16:30', '2025-11-21 23:16:30', 'cliente', 'root@localhost'),
(305, 'INSERT', 101, 'Préstamo creado', '2025-11-21 23:17:24', '2025-11-21 23:17:24', 'prestamo', 'root@localhost');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `contrasena` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `uq_cliente_cedula` (`cedula`),
  ADD KEY `id_prestamista` (`id_prestamista`);

--
-- Indices de la tabla `ediciones_prestamo`
--
ALTER TABLE `ediciones_prestamo`
  ADD PRIMARY KEY (`id_edicion`),
  ADD KEY `idx_ediciones_prestamo` (`id_prestamo`,`estado`),
  ADD KEY `idx_ediciones_solicitante` (`solicitado_por`,`estado`);

--
-- Indices de la tabla `sesiones_usuario`
--
ALTER TABLE `sesiones_usuario`
  ADD PRIMARY KEY (`id_sesion`),
  ADD KEY `idx_sesiones_usuario` (`id_prestamista`,`estado`),
  ADD KEY `idx_sesiones_session` (`session_id`);

--
-- Indices de la tabla `configuracion_usuario`
--
ALTER TABLE `configuracion_usuario`
  ADD PRIMARY KEY (`id_config`),
  ADD KEY `id_prestamista` (`id_prestamista`);

--
-- Indices de la tabla `documento`
--
ALTER TABLE `documento`
  ADD PRIMARY KEY (`id_documento`),
  ADD KEY `id_prestamo` (`id_prestamo`);

--
-- Indices de la tabla `historial_estado_prestamo`
--
ALTER TABLE `historial_estado_prestamo`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_prestamo` (`id_prestamo`);

--
-- Indices de la tabla `log_sesiones`
--
ALTER TABLE `log_sesiones`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_prestamista` (`id_prestamista`);

--
-- Indices de la tabla `pago`
--
ALTER TABLE `pago`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_prestamo` (`id_prestamo`);

--
-- Indices de la tabla `prestamista`
--
ALTER TABLE `prestamista`
  ADD PRIMARY KEY (`id_prestamista`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- Indices de la tabla `prestamo`
--
ALTER TABLE `prestamo`
  ADD PRIMARY KEY (`id_prestamo`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_prestamista` (`id_prestamista`);

--
-- Indices de la tabla `recordatorio`
--
ALTER TABLE `recordatorio`
  ADD PRIMARY KEY (`id_recordatorio`),
  ADD KEY `id_prestamo` (`id_prestamo`);

--
-- Indices de la tabla `trazabilidad`
--
ALTER TABLE `trazabilidad`
  ADD PRIMARY KEY (`id_traza`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `cliente`
--
ALTER TABLE `cliente`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT de la tabla `configuracion_usuario`
--
ALTER TABLE `configuracion_usuario`
  MODIFY `id_config` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT de la tabla `documento`
--
ALTER TABLE `documento`
  MODIFY `id_documento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT de la tabla `historial_estado_prestamo`
--
ALTER TABLE `historial_estado_prestamo`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `log_sesiones`
--
ALTER TABLE `log_sesiones`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT de la tabla `pago`
--
ALTER TABLE `pago`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT de la tabla `prestamista`
--
ALTER TABLE `prestamista`
  MODIFY `id_prestamista` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT de la tabla `prestamo`
--
ALTER TABLE `prestamo`
  MODIFY `id_prestamo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT de la tabla `recordatorio`
--
ALTER TABLE `recordatorio`
  MODIFY `id_recordatorio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT de la tabla `trazabilidad`
--
ALTER TABLE `trazabilidad`
  MODIFY `id_traza` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=306;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD CONSTRAINT `cliente_ibfk_1` FOREIGN KEY (`id_prestamista`) REFERENCES `prestamista` (`id_prestamista`);

--
-- Filtros para la tabla `configuracion_usuario`
--
ALTER TABLE `configuracion_usuario`
  ADD CONSTRAINT `configuracion_usuario_ibfk_1` FOREIGN KEY (`id_prestamista`) REFERENCES `prestamista` (`id_prestamista`);

--
-- Filtros para la tabla `documento`
--
ALTER TABLE `documento`
  ADD CONSTRAINT `documento_ibfk_1` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);

--
-- Filtros para la tabla `historial_estado_prestamo`
--
ALTER TABLE `historial_estado_prestamo`
  ADD CONSTRAINT `historial_estado_prestamo_ibfk_1` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);

--
-- Filtros para la tabla `log_sesiones`
--
ALTER TABLE `log_sesiones`
  ADD CONSTRAINT `log_sesiones_ibfk_1` FOREIGN KEY (`id_prestamista`) REFERENCES `prestamista` (`id_prestamista`);

--
-- Filtros para la tabla `pago`
--
ALTER TABLE `pago`
  ADD CONSTRAINT `pago_ibfk_1` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);

--
-- Filtros para la tabla `prestamo`
--
ALTER TABLE `prestamo`
  ADD CONSTRAINT `prestamo_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  ADD CONSTRAINT `prestamo_ibfk_2` FOREIGN KEY (`id_prestamista`) REFERENCES `prestamista` (`id_prestamista`);

--
-- Filtros para la tabla `recordatorio`
--
ALTER TABLE `recordatorio`
  ADD CONSTRAINT `recordatorio_ibfk_1` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamo` (`id_prestamo`);
COMMIT;

--
-- Triggers de Seguridad y Negocio
--
DELIMITER $$

-- RN09A: El pago debe estar asociado a un préstamo activo
CREATE TRIGGER `trg_pago_prestamo_activo` BEFORE INSERT ON `pago` FOR EACH ROW 
BEGIN
    DECLARE est VARCHAR(20);
    SELECT estado INTO est FROM prestamo WHERE id_prestamo = NEW.id_prestamo LIMIT 1;
    IF est IS NULL OR est <> 'activo' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN09A: El pago debe estar asociado a un préstamo activo';
    END IF;
END$$

-- RN19C: Bloqueo de modificación de pagos ya validados
CREATE TRIGGER `trg_pago_no_modificar_confirmado` BEFORE UPDATE ON `pago` FOR EACH ROW 
BEGIN
    IF OLD.confirmado = 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN11A: El pago no puede modificarse después de confirmarse';
    END IF;
END$$

-- RN11A/RN12A: El pago no puede modificarse después de validarse o rechazarse
CREATE TRIGGER `trg_pago_no_modificar_finalizado` BEFORE UPDATE ON `pago` FOR EACH ROW 
BEGIN
    IF OLD.estado_pago IN ('validado','rechazado') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RN11A/RN12A: El pago no puede modificarse después de validarse o rechazarse';
    END IF;
END$$

DELIMITER ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
