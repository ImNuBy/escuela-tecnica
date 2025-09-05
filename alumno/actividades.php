<?php
require_once '../config.php';
verificarTipoUsuario(['alumno']);

// Función para calcular tiempo transcurrido
function tiempoTranscurrido($fecha) {
    $ahora = new DateTime();
    $fechaActividad = new DateTime($fecha);
    $diferencia = $ahora->diff($fechaActividad);
    
    if ($diferencia->invert) {
        // Fecha pasada
        if ($diferencia->days == 0) {
            if ($diferencia->h == 0) {
                return 'Hace ' . $diferencia->i . ' minuto(s)';
            }
            return 'Hace ' . $diferencia->h . ' hora(s)';
        } elseif ($diferencia->days == 1) {
            return 'Ayer';
        } elseif ($diferencia->days < 7) {
            return 'Hace ' . $diferencia->days . ' días';
        } else {
            return date('d/m/Y', strtotime($fecha));
        }
    } else {
        // Fecha futura
        if ($diferencia->days == 0) {
            if ($diferencia->h == 0) {
                return 'En ' . $diferencia->i . ' minuto(s)';
            }
            return 'En ' . $diferencia->h . ' hora(s)';
        } elseif ($diferencia->days == 1) {
            return 'Mañana';
        } else {
            return 'En ' . $diferencia->days . ' días';
        }
    }
}

// Función para obtener el estado de entrega
function getEstadoEntrega($actividad_id, $alumno_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT estado, calificacion, fecha_entrega, archivo_nombre
        FROM entregas_actividades 
        WHERE actividad_id = ? AND alumno_id = ?
    ");
    $stmt->execute([$actividad_id, $alumno_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para determinar prioridad basada en fecha de entrega
function getPrioridadActividad($fecha_entrega) {
    if (!$fecha_entrega) return 'baja';
    
    $hoy = new DateTime();
    $entrega = new DateTime($fecha_entrega);
    $diferencia = $hoy->diff($entrega);
    
    if ($entrega < $hoy) return 'vencida';
    if ($diferencia->days <= 1) return 'alta';
    if ($diferencia->days <= 3) return 'media';
    return 'baja';
}

// Obtener datos del alumno
$stmt = $pdo->prepare("
    SELECT a.*, an.año, o.nombre as orientacion_nombre
    FROM alumnos a
    JOIN años an ON a.año_id = an.id
    JOIN orientaciones o ON an.orientacion_id = o.id
    WHERE a.usuario_id = ?
");
$stmt->execute([$_SESSION['usuario_id']]);
$alumno = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alumno) {
    die("Error: No se encontraron datos del alumno.");
}

// Filtros
$materia_id = isset($_GET['materia']) ? (int)$_GET['materia'] : null;
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : null;

// Obtener materias del alumno
$stmt = $pdo->prepare("
    SELECT m.*, u.nombre as profesor_nombre, u.apellido as profesor_apellido
    FROM materias m
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    WHERE m.año_id = ?
    ORDER BY m.nombre
");
$stmt->execute([$alumno['año_id']]);
$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir consulta de actividades
$where_clauses = ["m.año_id = ?", "a.activo = 1"];
$params = [$alumno['año_id']];

if ($materia_id) {
    $where_clauses[] = "a.materia_id = ?";
    $params[] = $materia_id;
}

$where_clause = "WHERE " . implode(" AND ", $where_clauses);

// Obtener actividades
$stmt = $pdo->prepare("
    SELECT a.*, 
           m.nombre as materia_nombre,
           u.nombre as profesor_nombre, u.apellido as profesor_apellido
    FROM actividades a
    JOIN materias m ON a.materia_id = m.id
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    $where_clause
    ORDER BY a.fecha_entrega ASC, a.fecha_creacion DESC
");
$stmt->execute($params);
$actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agregar información de entrega a cada actividad
foreach ($actividades as &$actividad) {
    $entrega = getEstadoEntrega($actividad['id'], $alumno['id'], $pdo);
    $actividad['entrega'] = $entrega;
    $actividad['prioridad'] = getPrioridadActividad($actividad['fecha_entrega']);
}

// Filtrar por estado si se especifica
if ($estado_filtro) {
    $actividades = array_filter($actividades, function($actividad) use ($estado_filtro) {
        switch ($estado_filtro) {
            case 'pendientes':
                return !$actividad['entrega'];
            case 'entregadas':
                return $actividad['entrega'] && $actividad['entrega']['estado'] === 'entregado';
            case 'revisadas':
                return $actividad['entrega'] && in_array($actividad['entrega']['estado'], ['revisado', 'aprobado', 'rechazado']);
            case 'vencidas':
                return $actividad['prioridad'] === 'vencida' && !$actividad['entrega'];
            default:
                return true;
        }
    });
}

// Estadísticas
$total_actividades = count($actividades);
$entregadas = count(array_filter($actividades, function($a) { return $a['entrega']; }));
$pendientes = $total_actividades - $entregadas;
$vencidas = count(array_filter($actividades, function($a) { return $a['prioridad'] === 'vencida' && !$a['entrega']; }));

// Próximas entregas (próximos 7 días)
$proximas_entregas = array_filter($actividades, function($actividad) {
    if (!$actividad['fecha_entrega'] || $actividad['entrega']) return false;
    
    $hoy = new DateTime();
    $entrega = new DateTime($actividad['fecha_entrega']);
    $diferencia = $hoy->diff($entrega);
    
    return !$diferencia->invert && $diferencia->days <= 7;
});

// Manejar la entrega de actividades vía AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'entregar_actividad') {
    header('Content-Type: application/json');
    
    try {
        $actividad_id = (int)$_POST['actividad_id'];
        $comentario = trim($_POST['comentario'] ?? '');
        
        // Verificar que la actividad existe y está activa
        $stmt = $pdo->prepare("
            SELECT a.*, m.año_id 
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
        
        // Verificar que el alumno pertenece al año de la actividad
        if ($alumno['año_id'] != $actividad['año_id']) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para entregar esta actividad']);
            exit;
        }
        
        // Verificar si ya existe una entrega
        $stmt = $pdo->prepare("
            SELECT id FROM entregas_actividades 
            WHERE actividad_id = ? AND alumno_id = ?
        ");
        $stmt->execute([$actividad_id, $alumno['id']]);
        $entrega_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($entrega_existente) {
            echo json_encode(['success' => false, 'message' => 'Ya has entregado esta actividad']);
            exit;
        }
        
        // Procesar archivo si se subió
        $archivo_nombre = null;
        $archivo_ruta = null;
        
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['archivo'];
            
            // Validar tamaño (10MB máximo)
            $max_size = 10 * 1024 * 1024;
            if ($archivo['size'] > $max_size) {
                echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande. Máximo 10MB permitido.']);
                exit;
            }
            
            // Validación básica del archivo (sin restricción de tipo)
            // Solo validar que el nombre del archivo sea válido
            if (empty(trim($archivo['name']))) {
                echo json_encode(['success' => false, 'message' => 'Nombre de archivo no válido.']);
                exit;
            }
            
            // Crear directorio si no existe
            $upload_dir = '../uploads/entregas/' . date('Y') . '/' . date('m') . '/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generar nombre único para el archivo
            $file_extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
            $archivo_nombre = $archivo['name'];
            $nuevo_nombre = 'entrega_' . $actividad_id . '_' . $alumno['id'] . '_' . time() . '.' . $file_extension;
            $archivo_ruta_completa = $upload_dir . $nuevo_nombre;
            
            if (!move_uploaded_file($archivo['tmp_name'], $archivo_ruta_completa)) {
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
            $alumno['id'], 
            $archivo_nombre,
            $archivo_ruta,
            $comentario
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Actividad entregada exitosamente'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar la entrega']);
        }
        
    } catch (Exception $e) {
        error_log("Error en entrega de actividad: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
    }
    exit;
}

// Manejar obtención de detalles de entrega vía AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'obtener_detalle') {
    $actividad_id = (int)$_GET['actividad_id'];
    
    // Obtener datos de la entrega
    $stmt = $pdo->prepare("
        SELECT ea.*, a.titulo as actividad_titulo, a.descripcion as actividad_descripcion,
               a.fecha_entrega as actividad_fecha_entrega, m.nombre as materia_nombre,
               u.nombre as profesor_nombre, u.apellido as profesor_apellido
        FROM entregas_actividades ea
        JOIN actividades a ON ea.actividad_id = a.id
        JOIN materias m ON a.materia_id = m.id
        LEFT JOIN usuarios u ON m.profesor_id = u.id
        WHERE ea.actividad_id = ? AND ea.alumno_id = ?
    ");
    $stmt->execute([$actividad_id, $alumno['id']]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($entrega) {
        // Formatear datos para el modal
        $detalles = [
            'actividad' => $entrega['actividad_titulo'],
            'materia' => $entrega['materia_nombre'],
            'profesor' => ($entrega['profesor_nombre'] && $entrega['profesor_apellido']) ? 
                         $entrega['profesor_nombre'] . ' ' . $entrega['profesor_apellido'] : 'No asignado',
            'fecha_limite' => $entrega['actividad_fecha_entrega'] ? formatearFecha($entrega['actividad_fecha_entrega']) : 'No especificada',
            'estado' => ucfirst($entrega['estado']),
            'fecha_entrega' => formatearFecha($entrega['fecha_entrega']),
            'archivo' => $entrega['archivo_nombre'],
            'comentario' => $entrega['comentario'],
            'calificacion' => $entrega['calificacion'] ? number_format($entrega['calificacion'], 2) : null,
            'feedback' => $entrega['feedback_profesor'],
            'archivo_ruta' => $entrega['archivo_ruta']
        ];
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $detalles]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Entrega no encontrada']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Actividades - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/alumno.css">
    <style>
        :root {
            --white: #ffffff;
            --primary-color: #2563eb;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --info-color: #0891b2;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --border-radius: 8px;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .estadisticas-actividades {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .estadistica-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            border-top: 4px solid;
            transition: transform 0.2s;
        }
        
        .estadistica-card:hover {
            transform: translateY(-2px);
        }
        
        .estadistica-card.total { border-top-color: var(--primary-color); }
        .estadistica-card.entregadas { border-top-color: var(--success-color); }
        .estadistica-card.pendientes { border-top-color: var(--warning-color); }
        .estadistica-card.vencidas { border-top-color: var(--danger-color); }
        
        .estadistica-valor {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }
        
        .estadistica-valor.total { color: var(--primary-color); }
        .estadistica-valor.entregadas { color: var(--success-color); }
        .estadistica-valor.pendientes { color: var(--warning-color); }
        .estadistica-valor.vencidas { color: var(--danger-color); }
        
        .filtros {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .filtro-grupo {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .form-select {
            padding: 0.5rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            background: var(--white);
            min-width: 200px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
        }
        
        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-800);
            padding: 0.5rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn-secondary:hover {
            background: var(--gray-200);
        }
        
        .actividades-container {
            display: grid;
            gap: 1.5rem;
        }
        
        .actividad-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        
        .actividad-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .actividad-card.prioridad-alta { border-left-color: var(--danger-color); }
        .actividad-card.prioridad-media { border-left-color: var(--warning-color); }
        .actividad-card.prioridad-baja { border-left-color: var(--primary-color); }
        .actividad-card.prioridad-vencida { border-left-color: #ef4444; background: #fef2f2; }
        
        .actividad-header {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .actividad-info h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            color: var(--gray-800);
        }
        
        .actividad-materia {
            background: var(--primary-color);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 0.5rem;
        }
        
        .actividad-fecha {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .estado-badges {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge.pendiente {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge.entregado {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge.revisado {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge.aprobado {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge.rechazado {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge.vencida {
            background: #fee2e2;
            color: #991b1b;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .prioridad-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        .prioridad-alta { background: var(--danger-color); }
        .prioridad-media { background: var(--warning-color); }
        .prioridad-baja { background: var(--primary-color); }
        .prioridad-vencida { background: #ef4444; }
        
        .actividad-descripcion {
            padding: 0 1.5rem;
            color: var(--gray-700);
            line-height: 1.5;
            margin-bottom: 1rem;
        }
        
        .actividad-actions {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .entrega-info {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .calificacion {
            font-weight: bold;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            color: white;
        }
        
        .calificacion.alta { background: var(--success-color); }
        .calificacion.media { background: var(--warning-color); }
        .calificacion.baja { background: var(--danger-color); }
        
        .sin-actividades {
            background: var(--white);
            padding: 3rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            color: var(--gray-600);
        }
        
        .proximas-entregas {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border-left: 4px solid var(--warning-color);
        }
        
        .proxima-entrega-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .proxima-entrega-item:last-child {
            border-bottom: none;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: var(--white);
            margin: 2rem auto;
            padding: 0;
            border-radius: var(--border-radius);
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .close {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--gray-500);
            cursor: pointer;
        }
        
        .close:hover {
            color: var(--gray-800);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            color: white;
            font-weight: 500;
            z-index: 10000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .toast.toast-success { background: var(--success-color); }
        .toast.toast-error { background: var(--danger-color); }
        .toast.toast-warning { background: var(--warning-color); }
        .toast.toast-info { background: var(--primary-color); }
        
        @media (max-width: 768px) {
            .actividad-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .estado-badges {
                align-items: flex-start;
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .actividad-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .modal-content {
                margin: 1rem;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>Mis Actividades</h1>
                <p>Gestión de tareas y trabajos de <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></p>
            </div>
            
            <!-- Estadísticas -->
            <div class="estadisticas-actividades">
                <div class="estadistica-card total">
                    <h3>Total</h3>
                    <div class="estadistica-valor total"><?php echo $total_actividades; ?></div>
                    <small>actividades asignadas</small>
                </div>
                
                <div class="estadistica-card entregadas">
                    <h3>Entregadas</h3>
                    <div class="estadistica-valor entregadas"><?php echo $entregadas; ?></div>
                    <small>trabajos completados</small>
                </div>
                
                <div class="estadistica-card pendientes">
                    <h3>Pendientes</h3>
                    <div class="estadistica-valor pendientes"><?php echo $pendientes; ?></div>
                    <small>por entregar</small>
                </div>
                
                <div class="estadistica-card vencidas">
                    <h3>Vencidas</h3>
                    <div class="estadistica-valor vencidas"><?php echo $vencidas; ?></div>
                    <small>fuera de tiempo</small>
                </div>
            </div>
            
            <!-- Próximas entregas -->
            <?php if (!empty($proximas_entregas)): ?>
                <div class="proximas-entregas">
                    <h3>⏰ Próximas entregas (próximos 7 días)</h3>
                    <?php foreach (array_slice($proximas_entregas, 0, 5) as $proxima): ?>
                        <div class="proxima-entrega-item">
                            <div>
                                <strong><?php echo htmlspecialchars($proxima['titulo']); ?></strong>
                                <small> - <?php echo htmlspecialchars($proxima['materia_nombre']); ?></small>
                            </div>
                            <div>
                                <span class="prioridad-indicator prioridad-<?php echo $proxima['prioridad']; ?>"></span>
                                <?php echo $proxima['fecha_entrega'] ? tiempoTranscurrido($proxima['fecha_entrega']) : 'Sin fecha'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="filtros">
                <div class="filtro-grupo">
                    <label for="materia-filtro"><strong>Filtrar por materia:</strong></label>
                    <select id="materia-filtro" class="form-select" onchange="filtrarPorMateria(this.value)">
                        <option value="">Todas las materias</option>
                        <?php foreach ($materias as $materia): ?>
                            <option value="<?php echo $materia['id']; ?>" <?php echo $materia_id == $materia['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($materia['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="estado-filtro"><strong>Estado:</strong></label>
                    <select id="estado-filtro" class="form-select" onchange="filtrarPorEstado(this.value)">
                        <option value="">Todas</option>
                        <option value="pendientes" <?php echo $estado_filtro === 'pendientes' ? 'selected' : ''; ?>>Pendientes</option>
                        <option value="entregadas" <?php echo $estado_filtro === 'entregadas' ? 'selected' : ''; ?>>Entregadas</option>
                        <option value="revisadas" <?php echo $estado_filtro === 'revisadas' ? 'selected' : ''; ?>>Revisadas</option>
                        <option value="vencidas" <?php echo $estado_filtro === 'vencidas' ? 'selected' : ''; ?>>Vencidas</option>
                    </select>
                    
                    <?php if ($materia_id || $estado_filtro): ?>
                        <a href="actividades.php" class="btn-primary">Ver todas</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Lista de actividades -->
            <?php if (!empty($actividades)): ?>
                <div class="actividades-container">
                    <?php foreach ($actividades as $actividad): ?>
                        <div class="actividad-card prioridad-<?php echo $actividad['prioridad']; ?>">
                            <div class="actividad-header">
                                <div class="actividad-info">
                                    <div class="actividad-materia">
                                        <?php echo htmlspecialchars($actividad['materia_nombre']); ?>
                                    </div>
                                    <h3><?php echo htmlspecialchars($actividad['titulo']); ?></h3>
                                    <div class="actividad-fecha">
                                        <strong>Creada:</strong> <?php echo formatearFecha($actividad['fecha_creacion']); ?>
                                        <?php if ($actividad['fecha_entrega']): ?>
                                            | <strong>Vence:</strong> <?php echo formatearFecha($actividad['fecha_entrega']); ?>
                                            (<?php echo tiempoTranscurrido($actividad['fecha_entrega']); ?>)
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="estado-badges">
                                    <span class="prioridad-indicator prioridad-<?php echo $actividad['prioridad']; ?>"></span>
                                    
                                    <?php if ($actividad['prioridad'] === 'vencida' && !$actividad['entrega']): ?>
                                        <span class="badge vencida">Vencida</span>
                                    <?php elseif ($actividad['entrega']): ?>
                                        <span class="badge <?php echo $actividad['entrega']['estado']; ?>">
                                            <?php echo ucfirst($actividad['entrega']['estado']); ?>
                                        </span>
                                        <?php if ($actividad['entrega']['calificacion']): ?>
                                            <?php
                                            $nota = $actividad['entrega']['calificacion'];
                                            $clase_cal = $nota >= 8 ? 'alta' : ($nota >= 6 ? 'media' : 'baja');
                                            ?>
                                            <span class="calificacion <?php echo $clase_cal; ?>">
                                                <?php echo number_format($nota, 2); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge pendiente">Pendiente</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="actividad-descripcion">
                                <?php echo nl2br(htmlspecialchars($actividad['descripcion'])); ?>
                            </div>
                            
                            <div class="actividad-actions">
                                <div class="entrega-info">
                                    <?php if ($actividad['entrega']): ?>
                                        <strong>Entregado:</strong> <?php echo formatearFecha($actividad['entrega']['fecha_entrega']); ?>
                                        <?php if ($actividad['entrega']['archivo_nombre']): ?>
                                            | <strong>Archivo:</strong> <?php echo htmlspecialchars($actividad['entrega']['archivo_nombre']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--warning-color);">
                                            <strong>Sin entregar</strong>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <?php if (!$actividad['entrega'] && $actividad['prioridad'] !== 'vencida'): ?>
                                        <button onclick="abrirModalEntrega(<?php echo $actividad['id']; ?>, '<?php echo htmlspecialchars($actividad['titulo'], ENT_QUOTES); ?>')" 
                                                class="btn-primary">
                                            Entregar
                                        </button>
                                    <?php elseif ($actividad['entrega']): ?>
                                        <button onclick="verDetalleEntrega(<?php echo $actividad['id']; ?>)" 
                                                class="btn-primary" style="background: var(--success-color);">
                                            Ver entrega
                                        </button>
                                    <?php elseif ($actividad['prioridad'] === 'vencida' && !$actividad['entrega']): ?>
                                        <span style="color: var(--danger-color); font-weight: 500;">
                                            ⚠️ Vencida
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="sin-actividades">
                    <h3>No hay actividades</h3>
                    <p>No se encontraron actividades con los filtros seleccionados.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Modal para entregar actividad -->
    <div id="modalEntrega" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitulo">Entregar Actividad</h3>
                <span class="close" onclick="cerrarModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="formEntrega" enctype="multipart/form-data">
                    <input type="hidden" id="actividadId" name="actividad_id">
                    <input type="hidden" name="action" value="entregar_actividad">
                    
                    <div class="form-group">
                        <label for="archivo">Archivo (opcional):</label>
                        <input type="file" id="archivo" name="archivo" class="form-control">
                        <small style="color: var(--gray-600);">Se aceptan todos los tipos de archivo. Máximo 10MB.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="comentario">Comentario:</label>
                        <textarea id="comentario" name="comentario" class="form-control" 
                                  placeholder="Añade un comentario sobre tu entrega (opcional)"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <button type="button" onclick="cerrarModal()" class="btn-secondary">Cancelar</button>
                        <button type="submit" class="btn-primary">Entregar Actividad</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para ver detalle de entrega -->
    <div id="modalDetalle" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detalle de Entrega</h3>
                <span class="close" onclick="cerrarModalDetalle()">&times;</span>
            </div>
            <div class="modal-body" id="detalleEntregaContent">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>
    
    <script>
        function filtrarPorMateria(materiaId) {
            const estadoActual = document.getElementById('estado-filtro').value;
            let url = 'actividades.php';
            const params = [];
            
            if (materiaId) params.push('materia=' + materiaId);
            if (estadoActual) params.push('estado=' + estadoActual);
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            window.location.href = url;
        }
        
        function filtrarPorEstado(estado) {
            const materiaActual = document.getElementById('materia-filtro').value;
            let url = 'actividades.php';
            const params = [];
            
            if (materiaActual) params.push('materia=' + materiaActual);
            if (estado) params.push('estado=' + estado);
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            window.location.href = url;
        }
        
        function abrirModalEntrega(actividadId, titulo) {
            document.getElementById('actividadId').value = actividadId;
            document.getElementById('modalTitulo').textContent = 'Entregar: ' + titulo;
            document.getElementById('modalEntrega').style.display = 'block';
            
            // Reset form
            document.getElementById('formEntrega').reset();
            document.getElementById('actividadId').value = actividadId;
        }
        
        function cerrarModal() {
            document.getElementById('modalEntrega').style.display = 'none';
        }
        
        function cerrarModalDetalle() {
            document.getElementById('modalDetalle').style.display = 'none';
        }
        
        function verDetalleEntrega(actividadId) {
            // Cargar detalles de la entrega vía AJAX
            fetch('actividades.php?action=obtener_detalle&actividad_id=' + actividadId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const detalle = data.data;
                        let html = `
                            <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                                <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                    <h4 style="margin: 0 0 0.75rem 0; color: #374151;">📋 Información de la Actividad</h4>
                                    <div style="margin-bottom: 0.5rem;"><strong>Título:</strong> ${detalle.actividad}</div>
                                    <div style="margin-bottom: 0.5rem;"><strong>Materia:</strong> ${detalle.materia}</div>
                                    <div style="margin-bottom: 0.5rem;"><strong>Profesor:</strong> ${detalle.profesor}</div>
                                    <div><strong>Fecha límite:</strong> ${detalle.fecha_limite}</div>
                                </div>
                                
                                <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                    <h4 style="margin: 0 0 0.75rem 0; color: #374151;">📊 Estado de la Entrega</h4>
                                    <div style="margin-bottom: 0.5rem;"><strong>Estado:</strong> <span style="background: #d1fae5; color: #065f46; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.875rem;">${detalle.estado}</span></div>
                                    <div style="margin-bottom: 0.5rem;"><strong>Fecha de entrega:</strong> ${detalle.fecha_entrega}</div>
                                    ${detalle.calificacion ? `<div><strong>Calificación:</strong> <span style="font-size: 1.25rem; font-weight: bold; color: #059669;">${detalle.calificacion}/10</span></div>` : ''}
                                </div>
                                
                                ${detalle.archivo ? `
                                <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                    <h4 style="margin: 0 0 0.75rem 0; color: #374151;">📎 Archivo Entregado</h4>
                                    <div><strong>Archivo:</strong> ${detalle.archivo}</div>
                                    ${detalle.archivo_ruta ? `<div style="margin-top: 0.5rem;"><a href="../${detalle.archivo_ruta}" target="_blank" style="color: #2563eb; text-decoration: none;">📥 Descargar archivo</a></div>` : ''}
                                </div>
                                ` : ''}
                                
                                ${detalle.comentario ? `
                                <div style="background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                    <h4 style="margin: 0 0 0.75rem 0; color: #374151;">💬 Tu Comentario</h4>
                                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem;">${detalle.comentario.replace(/\n/g, '<br>')}</div>
                                </div>
                                ` : ''}
                                
                                ${detalle.feedback ? `
                                <div style="background: #eff6ff; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #bfdbfe;">
                                    <h4 style="margin: 0 0 0.75rem 0; color: #374151;">🎓 Feedback del Profesor</h4>
                                    <div>${detalle.feedback.replace(/\n/g, '<br>')}</div>
                                </div>
                                ` : ''}
                                
                                <div style="text-align: center; margin-top: 2rem;">
                                    <button onclick="cerrarModalDetalle()" style="background: #2563eb; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                        Cerrar
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('detalleEntregaContent').innerHTML = html;
                        document.getElementById('modalDetalle').style.display = 'block';
                    } else {
                        mostrarToast(data.message || 'Error al cargar los detalles', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarToast('Error al cargar los detalles', 'error');
                });
        }
        
        // Manejar envío del formulario de entrega
        document.getElementById('formEntrega').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            
            // Deshabilitar botón durante el envío
            submitBtn.disabled = true;
            submitBtn.textContent = 'Entregando...';
            
            fetch('actividades.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarToast('Actividad entregada exitosamente', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    mostrarToast(data.message || 'Error al entregar la actividad', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarToast('Error de conexión', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Entregar Actividad';
            });
        });
        
        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            const modalEntrega = document.getElementById('modalEntrega');
            const modalDetalle = document.getElementById('modalDetalle');
            
            if (event.target === modalEntrega) {
                cerrarModal();
            }
            if (event.target === modalDetalle) {
                cerrarModalDetalle();
            }
        };
        
        // Validar tamaño de archivo
        document.getElementById('archivo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const maxSize = 10 * 1024 * 1024; // 10MB
                
                if (file.size > maxSize) {
                    mostrarToast('El archivo es demasiado grande. Máximo 10MB permitido.', 'error');
                    e.target.value = '';
                    return;
                }
            }
        });
        
        // Función para mostrar toast notifications
        function mostrarToast(mensaje, tipo = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${tipo}`;
            toast.textContent = mensaje;
            document.body.appendChild(toast);
            
            // Animar entrada
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 100);
            
            // Remover después de 4 segundos
            setTimeout(() => {
                toast.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 4000);
        }
        
        // Animaciones de carga
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.actividad-card, .estadistica-card');
            
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s, transform 0.5s';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>