<?php
session_start();

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'escuela_tecnica');
define('DB_USER', 'root');
define('DB_PASS', '');

// Conexión a la base de datos con configuración UTF-8 CORREGIDA
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Establecer charset explícitamente - CORREGIDO
    $pdo->exec("SET character_set_client=utf8mb4");
    $pdo->exec("SET character_set_connection=utf8mb4");
    $pdo->exec("SET character_set_results=utf8mb4");
    $pdo->exec("SET collation_connection=utf8mb4_unicode_ci");
    
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Función para verificar si el usuario está logueado
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/index.php');
        exit();
    }
}

// Función para verificar el tipo de usuario
function verificarTipoUsuario($tiposPermitidos) {
    verificarLogin();
    if (!in_array($_SESSION['tipo_usuario'], $tiposPermitidos)) {
        header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/dashboard.php');
        exit();
    }
}

// Función para obtener información del usuario actual
function obtenerUsuarioActual($pdo) {
    if (isset($_SESSION['usuario_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

// Función para obtener años por orientación
function obtenerAñosPorOrientacion($pdo, $orientacion = null) {
    if ($orientacion) {
        $stmt = $pdo->prepare("
            SELECT a.*, o.nombre as orientacion_nombre 
            FROM años a 
            JOIN orientaciones o ON a.orientacion_id = o.id 
            WHERE o.nombre LIKE ?
        ");
        $stmt->execute(['%' . $orientacion . '%']);
    } else {
        $stmt = $pdo->prepare("
            SELECT a.*, o.nombre as orientacion_nombre 
            FROM años a 
            JOIN orientaciones o ON a.orientacion_id = o.id
        ");
        $stmt->execute();
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para formatear fecha - CORREGIDA
function formatearFecha($fecha) {
    if (!$fecha || $fecha == '0000-00-00' || $fecha == '0000-00-00 00:00:00') return 'N/A';
    
    try {
        $timestamp = strtotime($fecha);
        if ($timestamp === false) return 'Fecha inválida';
        
        return date('d/m/Y', $timestamp);
    } catch (Exception $e) {
        return 'Error en fecha';
    }
}

// Función para formatear hora - CORREGIDA
function formatearHora($hora) {
    if (!$hora || $hora == '00:00:00') return 'N/A';
    
    try {
        // Si ya es formato HH:MM, devolverlo
        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return $hora;
        }
        
        // Si es formato HH:MM:SS, convertir a HH:MM
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
            return substr($hora, 0, 5);
        }
        
        // Si es timestamp, formatear
        $timestamp = strtotime($hora);
        if ($timestamp === false) return 'Hora inválida';
        
        return date('H:i', $timestamp);
    } catch (Exception $e) {
        return 'Error en hora';
    }
}

// Función para escapar HTML (seguridad)
function h($string) {
    if ($string === null || $string === '') return '';
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Función para debug (opcional - solo en desarrollo)
function debug($var, $die = false) {
    echo '<pre style="background: #f4f4f4; border: 1px solid #ddd; padding: 10px; margin: 10px 0;">';
    var_dump($var);
    echo '</pre>';
    if ($die) die();
}

// Función para verificar si es petición AJAX
function esAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Función para obtener la URL base del proyecto
function obtenerUrlBase() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return $protocol . '://' . $host . $path . '/';
}

// Configurar zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configurar encoding para PHP
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Headers para UTF-8 y seguridad
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Configurar manejo de errores (solo en desarrollo)
if (defined('DEBUG') && DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Función para obtener mensajes flash de sesión
function obtenerMensaje() {
    if (isset($_SESSION['mensaje'])) {
        $mensaje = $_SESSION['mensaje'];
        $tipo = $_SESSION['tipo_mensaje'] ?? 'info';
        unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);
        return ['mensaje' => $mensaje, 'tipo' => $tipo];
    }
    return null;
}

// Función para establecer mensajes flash
function establecerMensaje($mensaje, $tipo = 'success') {
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo_mensaje'] = $tipo;
}

// Función para limpiar entrada de datos
function limpiarEntrada($data) {
    if (is_array($data)) {
        return array_map('limpiarEntrada', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Función para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Función para generar token CSRF
function generarTokenCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función para verificar token CSRF
function verificarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>