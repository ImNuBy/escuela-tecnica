<?php
require_once '../../config.php';

// Verificar que sea una peticiÃ³n POST y que el usuario estÃ© autenticado
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

// Verificar que el usuario sea alumno
if ($_SESSION['tipo_usuario'] !== 'alumno') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo los alumnos pueden entregar actividades']);
    exit;
}

try {
    $actividad_id = (int)$_POST['actividad_id'];
    $comentario = trim($_POST['comentario'] ?? '');
    
    // Obtener datos del alumno
    $stmt = $pdo->prepare("SELECT id FROM alumnos WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$alumno) {
        echo json_encode(['success' => false, 'message' => 'No se encontraron datos del alumno']);
        exit;
    }
    
    $alumno_id = $alumno['id'];
    
    // Verificar que la actividad existe y estÃ¡ activa
    $stmt = $pdo->prepare("
        SELECT a.*, m.aÃ±o_id 
        FROM actividades a 
        JOIN materias m ON a.materia_id = m.id 
        WHERE a.id = ? AND a.activo = 1
    ");
    $stmt->execute([$actividad_id]);
    $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$actividad) {
        echo json_encode(['success' => false, 'message' => 'Actividad no encontrada o inactiva']);
        exit;
    }
    
    // Verificar que el alumno pertenece al aÃ±o de la actividad
    $stmt = $pdo->prepare("SELECT aÃ±o_id FROM alumnos WHERE id = ?");
    $stmt->execute([$alumno_id]);
    $alumno_aÃ±o = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($alumno_aÃ±o['aÃ±o_id'] != $actividad['aÃ±o_id']) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para entregar esta actividad']);
        exit;
    }
    
    // Verificar si ya existe una entrega
    $stmt = $pdo->prepare("
        SELECT id FROM entregas_actividades 
        WHERE actividad_id = ? AND alumno_id = ?
    ");
    $stmt->execute([$actividad_id, $alumno_id]);
    $entrega_existente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($entrega_existente) {
        echo json_encode(['success' => false, 'message' => 'Ya has entregado esta actividad']);
        exit;
    }
    
    // Procesar archivo si se subiÃ³
    $archivo_nombre = null;
    $archivo_ruta = null;
    
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo'];
        
        // Validar tamaÃ±o (10MB mÃ¡ximo)
        $max_size = 10 * 1024 * 1024;
        if ($archivo['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande. MÃ¡ximo 10MB permitido.']);
            exit;
        }
        
      // Validación básica del archivo (sin restricción de tipo)
// Solo validar que el nombre del archivo sea válido
if (empty(trim($archivo['name']))) {
    echo json_encode(['success' => false, 'message' => 'Nombre de archivo no válido.']);
    exit;
}
        
        // Crear directorio si no existe
        $upload_dir = '../../uploads/entregas/' . date('Y') . '/' . date('m') . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generar nombre Ãºnico para el archivo
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $archivo_nombre = $archivo['name'];
        $nuevo_nombre = 'entrega_' . $actividad_id . '_' . $alumno_id . '_' . time() . '.' . $extension;
        $archivo_ruta = $upload_dir . $nuevo_nombre;
        
        if (!move_uploaded_file($archivo['tmp_name'], $archivo_ruta)) {
            echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
            exit;
        }
        
        // Guardar ruta relativa para la base de datos
        $archivo_ruta = 'uploads/entregas/' . date('Y') . '/' . date('m') . '/' . $nuevo_nombre;
    }
    
    // Insertar la entrega en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO entregas_actividades 
        (actividad_id, alumno_id, archivo_nombre, archivo_ruta, comentario, fecha_entrega, estado) 
        VALUES (?, ?, ?, ?, ?, NOW(), 'entregado')
    ");
    
    $result = $stmt->execute([
        $actividad_id,
        $alumno_id, 
        $archivo_nombre,
        $archivo_ruta,
        $comentario
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Actividad entregada exitosamente',
            'data' => [
                'archivo_nombre' => $archivo_nombre,
                'comentario' => $comentario,
                'fecha_entrega' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la entrega']);
    }
    
} catch (Exception $e) {
    error_log("Error en entregar_actividad.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}