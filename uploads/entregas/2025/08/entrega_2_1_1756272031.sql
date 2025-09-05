-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 26-06-2025 a las 00:48:02
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `clinica`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `camas`
--

CREATE TABLE `camas` (
  `id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `ocupada` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `camas`
--

INSERT INTO `camas` (`id`, `numero`, `ocupada`) VALUES
(1, 1, 0),
(2, 1, 0),
(3, 2, 1),
(4, 4, 0),
(5, 5, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consejos_salud`
--

CREATE TABLE `consejos_salud` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `contenido` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `consejos_salud`
--

INSERT INTO `consejos_salud` (`id`, `titulo`, `contenido`) VALUES
(1, 'Dormí bien', 'Dormir al menos 7 horas mejora el sistema inmunológico.'),
(2, 'Lavado de manos', 'Lavate las manos frecuentemente para prevenir enfermedades.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consultorios`
--

CREATE TABLE `consultorios` (
  `id` int(11) NOT NULL,
  `numero` varchar(10) NOT NULL,
  `piso` varchar(10) NOT NULL,
  `id_especialidad` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `consultorios`
--

INSERT INTO `consultorios` (`id`, `numero`, `piso`, `id_especialidad`) VALUES
(4, '001', '3', 2),
(5, '6', '1', 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialidades`
--

CREATE TABLE `especialidades` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `especialidades`
--

INSERT INTO `especialidades` (`id`, `nombre`) VALUES
(5, 'cardiologia'),
(1, 'dentista'),
(2, 'dermatologo'),
(7, 'dnvsfnvj'),
(8, 'eeeeeaaaAA'),
(10, 'gastroenterologo'),
(4, 'nutricionista'),
(3, 'oculista'),
(9, 'pepe');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `internaciones`
--

CREATE TABLE `internaciones` (
  `id` int(11) NOT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `id_cama` int(11) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `diagnostico` text DEFAULT NULL,
  `fecha_alta` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `internaciones`
--

INSERT INTO `internaciones` (`id`, `id_paciente`, `id_cama`, `fecha_ingreso`, `diagnostico`, `fecha_alta`) VALUES
(1, 2, 2, '2025-06-19', NULL, '2025-06-11'),
(2, 5, 4, '2025-06-27', NULL, '2025-06-11'),
(3, 5, 2, '2025-06-27', NULL, '2025-06-11'),
(4, 5, 4, '2025-06-12', NULL, '2025-06-11'),
(5, 5, 2, '2025-06-05', NULL, '2025-06-11'),
(6, 5, 1, '2025-06-12', NULL, '2025-06-11'),
(7, 5, 3, '2025-06-12', NULL, '2025-06-11'),
(8, 1, 1, '2025-06-20', NULL, '2025-06-25'),
(9, 1, 3, '2025-06-07', NULL, NULL),
(10, 5, 5, '2025-06-26', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medicos`
--

CREATE TABLE `medicos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `especialidad` varchar(100) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `id_especialidad` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `medicos`
--

INSERT INTO `medicos` (`id`, `nombre`, `especialidad`, `dni`, `email`, `telefono`, `id_especialidad`) VALUES
(1, 'rios', 'uu', '2222', 'uu@gmail.com', 'iiiii', 2),
(2, 'manaos', '', '2222', 'uu@gmail.com', '3333', 4),
(3, 'kiki', '', '67', 'sdjas@gmail.com', '890', 2),
(4, 'edi', '', '45', 'edi@gmail.com', '4325', 3),
(5, 'manaos', '', '2222', 'uu@gmail.com', '3333', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `noticias_medicas`
--

CREATE TABLE `noticias_medicas` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `contenido` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `noticias_medicas`
--

INSERT INTO `noticias_medicas` (`id`, `titulo`, `contenido`) VALUES
(1, 'Nuevo estudio sobre diabetes', 'Un estudio reciente muestra avances en el tratamiento de la diabetes tipo 2.'),
(2, 'Vacunas COVID-19', 'Nuevas dosis disponibles en hospitales públicos.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pacientes`
--

CREATE TABLE `pacientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pacientes`
--

INSERT INTO `pacientes` (`id`, `nombre`, `dni`, `email`, `telefono`, `fecha_nacimiento`, `fecha_registro`) VALUES
(1, 'wnrj', '2222222', 'udekjahfsk@gmail.com', 'tdjkjkd', '2025-05-21', '2025-05-28 16:45:47'),
(2, 'hhbfeafj', 'fae', 'sdjas@gmail.com', '677898', '0000-00-00', '2025-05-28 16:45:47'),
(5, 'pepes', '1111', 'x@gmail.com', '232332', '2019-07-10', '2025-06-04 16:03:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos`
--

CREATE TABLE `turnos` (
  `id` int(11) NOT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `id_medico` int(11) DEFAULT NULL,
  `fecha_turno` datetime DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `turnos`
--

INSERT INTO `turnos` (`id`, `id_paciente`, `id_medico`, `fecha_turno`, `observaciones`) VALUES
(1, 1, 1, '2025-05-30 16:27:00', 'esta enfermo'),
(2, 1, 2, '2025-05-23 23:03:00', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `rol` enum('supervisor','usuario') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `clave`, `rol`) VALUES
(3, 'superadmin', 'supervisor123', 'supervisor'),
(4, 'usuario1', 'usuario123', 'usuario'),
(5, '222', '222', 'supervisor');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `camas`
--
ALTER TABLE `camas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `consejos_salud`
--
ALTER TABLE `consejos_salud`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `consultorios`
--
ALTER TABLE `consultorios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_especialidad` (`id_especialidad`);

--
-- Indices de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `internaciones`
--
ALTER TABLE `internaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_paciente` (`id_paciente`),
  ADD KEY `id_cama` (`id_cama`);

--
-- Indices de la tabla `medicos`
--
ALTER TABLE `medicos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_especialidad` (`id_especialidad`);

--
-- Indices de la tabla `noticias_medicas`
--
ALTER TABLE `noticias_medicas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni` (`dni`);

--
-- Indices de la tabla `turnos`
--
ALTER TABLE `turnos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_paciente` (`id_paciente`),
  ADD KEY `id_medico` (`id_medico`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `camas`
--
ALTER TABLE `camas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `consejos_salud`
--
ALTER TABLE `consejos_salud`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `consultorios`
--
ALTER TABLE `consultorios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `internaciones`
--
ALTER TABLE `internaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `medicos`
--
ALTER TABLE `medicos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `noticias_medicas`
--
ALTER TABLE `noticias_medicas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `turnos`
--
ALTER TABLE `turnos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `consultorios`
--
ALTER TABLE `consultorios`
  ADD CONSTRAINT `consultorios_ibfk_1` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`);

--
-- Filtros para la tabla `internaciones`
--
ALTER TABLE `internaciones`
  ADD CONSTRAINT `internaciones_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id`),
  ADD CONSTRAINT `internaciones_ibfk_2` FOREIGN KEY (`id_cama`) REFERENCES `camas` (`id`);

--
-- Filtros para la tabla `medicos`
--
ALTER TABLE `medicos`
  ADD CONSTRAINT `fk_especialidad` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `turnos`
--
ALTER TABLE `turnos`
  ADD CONSTRAINT `turnos_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `turnos_ibfk_2` FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
