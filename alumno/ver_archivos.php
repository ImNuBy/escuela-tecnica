<?php
require_once '../config.php';
verificarTipoUsuario(['alumno', 'administrador']);

// Obtener el ID del reporte
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.0 404 Not Found');
    die('Archivo no encontrado');
}

$reporte_id = (int)$_GET['id'];

try {
    // Si es alumno, verificar que el reporte le pertenece
    if ($_SESSION['tipo_usuario'] === 'alumno') {
        // Obtener ID del alumno
        $stmt = $pdo->prepare("SELECT id FROM alumnos WHERE usuario_id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$alumno) {
            header('HTTP/1.0 403 Forbidden');
            die('Acceso denegado');
        }
        
        // Verificar que el reporte pertenece al alumno
        $stmt = $pdo->prepare("
            SELECT archivo_nombre, archivo_ruta, archivo_tipo, archivo_tamaño 
            FROM reportes_alumnos 
            WHERE id = ? AND alumno_id = ? AND archivo_ruta IS NOT NULL
        ");
        $stmt->execute([$reporte_id, $alumno['id']]);
    } else {
        // Los administradores pueden ver cualquier archivo
        $stmt = $pdo->prepare("
            SELECT archivo_nombre, archivo_ruta, archivo_tipo, archivo_tamaño 
            FROM reportes_alumnos 
            WHERE id = ? AND archivo_ruta IS NOT NULL
        ");
        $stmt->execute([$reporte_id]);
    }
    
    $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$archivo || !file_exists($archivo['archivo_ruta'])) {
        header('HTTP/1.0 404 Not Found');
        die('Archivo no encontrado');
    }
    
    // Determinar el tipo de contenido
    $archivo_ruta = $archivo['archivo_ruta'];
    $archivo_nombre = $archivo['archivo_nombre'];
    $archivo_tipo = $archivo['archivo_tipo'];
    
    // Verificar que el archivo existe y es legible
    if (!is_readable($archivo_ruta)) {
        header('HTTP/1.0 404 Not Found');
        die('Archivo no accesible');
    }
    
    // Determinar si es imagen para mostrar inline o forzar descarga
    $es_imagen = in_array($archivo_tipo, [
        'image/jpeg', 
        'image/jpg', 
        'image/png', 
        'image/gif', 
        'image/webp'
    ]);
    
    // Si es imagen y no se fuerza la descarga, mostrar inline
    if ($es_imagen && !isset($_GET['download'])) {
        // Mostrar imagen en el navegador
        header('Content-Type: ' . $archivo_tipo);
        header('Content-Length: ' . filesize($archivo_ruta));
        header('Cache-Control: public, max-age=3600');
        header('Content-Disposition: inline; filename="' . addslashes($archivo_nombre) . '"');
    } else {
        // Forzar descarga
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($archivo_ruta));
        header('Content-Disposition: attachment; filename="' . addslashes($archivo_nombre) . '"');
        header('Cache-Control: no-cache, must-revalidate');
    }
    
    // Leer y enviar el archivo
    readfile($archivo_ruta);
    
} catch (Exception $e) {
    error_log("Error en ver_archivo.php: " . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    die('Error interno del servidor');
}
?>