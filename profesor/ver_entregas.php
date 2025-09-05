<?php
require_once '../config.php';
verificarTipoUsuario(['profesor', 'administrador']); // Permitir admin y profesores

$mensaje = '';
$tipo_mensaje = '';
$es_admin = ($_SESSION['tipo_usuario'] === 'administrador');

// Verificar que se haya proporcionado el ID de actividad
if (!isset($_GET['actividad']) || !is_numeric($_GET['actividad'])) {
    header('Location: actividades.php');
    exit();
}

$actividad_id = (int)$_GET['actividad'];

// Lógica diferente para admin vs profesor
if ($es_admin) {
    // Admin puede ver cualquier actividad
    $profesor_id = null;
} else {
    // Obtener ID del profesor
    try {
        $stmt = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profesor) {
            throw new Exception("No se encontrÓ el profesor en la base de datos");
        }
        
        $profesor_id = $profesor['id'];
    } catch (Exception $e) {
        die("Error al obtener datos del profesor: " . $e->getMessage());
    }
}

// Verificar que la actividad exista (y pertenezca al profesor si no es admin)
try {
    $query = "
        SELECT a.*, m.nombre as materia_nombre, 
               CONCAT(an.año, 'º - ', o.nombre) as año_orientacion,
               u.nombre as profesor_nombre, u.apellido as profesor_apellido
        FROM actividades a
        JOIN materias m ON a.materia_id = m.id
        JOIN años an ON m.año_id = an.id
        JOIN orientaciones o ON an.orientacion_id = o.id
        JOIN profesores p ON a.profesor_id = p.id
        JOIN usuarios u ON p.usuario_id = u.id
        WHERE a.id = ? AND a.activo = 1
    ";
    
    $params = [$actividad_id];
    
    // Si no es admin, agregar filtro por profesor
    if (!$es_admin) {
        $query .= " AND a.profesor_id = ?";
        $params[] = $profesor_id;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $actividad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$actividad) {
        $mensaje = $es_admin ? 'Actividad no encontrada' : 'Actividad no encontrada o no tiene permisos para verla';
        $tipo_mensaje = 'error';
        header('Location: actividades.php');
        exit();
    }
} catch (Exception $e) {
    die("Error al obtener actividad: " . $e->getMessage());
}

// Obtener alumnos del año con sus entregas
try {
    $stmt = $pdo->prepare("
        SELECT al.id as alumno_id,
               u.nombre, u.apellido,
               e.id as entrega_id,
               e.archivo_nombre,
               e.archivo_ruta,
               e.comentario,
               e.fecha_entrega,
               e.calificacion,
               e.feedback_profesor,
               e.estado
        FROM alumnos al
        JOIN usuarios u ON al.usuario_id = u.id
        JOIN años an ON al.año_id = an.id
        JOIN materias m ON m.año_id = an.id
        LEFT JOIN entregas_actividades e ON e.alumno_id = al.id AND e.actividad_id = ?
        WHERE m.id = ? AND u.activo = 1
        ORDER BY u.apellido, u.nombre
    ");
    $stmt->execute([$actividad_id, $actividad['materia_id']]);
    $alumnos_entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $mensaje = 'Error al obtener entregas: ' . $e->getMessage();
    $tipo_mensaje = 'error';
    $alumnos_entregas = [];
}

// Procesar calificación si se envió (solo profesores o admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calificar') {
    $entrega_id = $_POST['entrega_id'] ?? '';
    $calificacion = $_POST['calificacion'] ?? '';
    $feedback = trim($_POST['feedback'] ?? '');
    
    if (!empty($entrega_id) && !empty($calificacion)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE entregas_actividades 
                SET calificacion = ?, feedback_profesor = ?, estado = 'revisado'
                WHERE id = ?
            ");
            $stmt->execute([$calificacion, $feedback, $entrega_id]);
            
            if ($stmt->rowCount() > 0) {
                $mensaje = 'Calificación guardada exitosamente';
                $tipo_mensaje = 'success';
                // Recargar la página para mostrar los cambios
                header("Location: ver_entregas.php?actividad=$actividad_id");
                exit();
            } else {
                $mensaje = 'No se pudo guardar la calificación';
                $tipo_mensaje = 'error';
            }
        } catch (Exception $e) {
            $mensaje = 'Error al guardar calificación: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Debe ingresar una calificación';
        $tipo_mensaje = 'error';
    }
}

// Calcular estadísticas
$total_alumnos = count($alumnos_entregas);
$total_entregas = count(array_filter($alumnos_entregas, fn($item) => !empty($item['entrega_id'])));
$porcentaje_entregado = $total_alumnos > 0 ? round(($total_entregas / $total_alumnos) * 100) : 0;

// Función para determinar si un archivo es imagen
function esImagen($archivo_ruta) {
    if (empty($archivo_ruta) || !file_exists($archivo_ruta)) {
        return false;
    }
    
    $extensiones_imagen = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extension = strtolower(pathinfo($archivo_ruta, PATHINFO_EXTENSION));
    return in_array($extension, $extensiones_imagen);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Entregas - <?php echo htmlspecialchars($actividad['titulo']); ?></title>
    <link rel="stylesheet" href="../css/base.css">
    
    <!-- CSS específico para entregas integrado -->
    <style>
        /* Estilos para Visualización de Entregas */
        .entregas-container {
            display: grid;
            gap: 2rem;
            animation: fadeInUp 0.6s ease-out;
        }

        /* Información de la Actividad */
        .actividad-info {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border-left: 4px solid #2563eb;
        }

        .actividad-info h2 {
            color: var(--gray-800);
            margin-bottom: 1rem;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .actividad-detalles .detalle-item {
            margin-bottom: 1rem;
        }

        .actividad-detalles strong {
            color: var(--gray-800);
            font-weight: 600;
        }

        .actividad-detalles p {
            color: var(--gray-700);
            line-height: 1.6;
            margin: 0.5rem 0;
            background: #f8fafc;
            padding: 1rem;
            border-radius: 6px;
            border-left: 3px solid #e2e8f0;
        }

        .actividad-meta-info {
            display: flex;
            gap: 2rem;
            font-size: 0.9rem;
            color: var(--gray-600);
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
            flex-wrap: wrap;
        }

        .badge-admin {
            background: linear-gradient(135deg, #7c3aed, #a855f7);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Estadísticas de Entregas */
        .estadisticas-entregas {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .estadisticas-entregas h2 {
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            border-bottom: 2px solid var(--gray-200);
            padding-bottom: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.4);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            display: block;
        }

        .stat-number {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.95;
            font-weight: 500;
        }

        /* Lista de Entregas */
        .entregas-lista {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .lista-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-200);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .lista-header h2 {
            color: var(--gray-800);
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .filtros-entregas {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filtros-entregas select {
            padding: 0.5rem 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .filtros-entregas select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Grid de Entregas */
        .entregas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 1.5rem;
        }

        .entrega-card {
            background: var(--white);
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .entrega-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            transition: all 0.3s ease;
        }

        .entrega-card.pendiente::before {
            background: #ef4444;
        }

        .entrega-card.entregada::before,
        .entrega-card.a_tiempo::before {
            background: #10b981;
        }

        .entrega-card.tarde::before {
            background: #f59e0b;
        }

        .entrega-card.revisado::before {
            background: #3b82f6;
        }

        .entrega-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .alumno-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .alumno-info h3 {
            color: var(--gray-800);
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }

        .usuario-info {
            color: var(--gray-600);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .estado-entrega {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .estado-pendiente {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .estado-entregada,
        .estado-a-tiempo {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 1px solid #10b981;
        }

        .estado-revisado {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1d4ed8;
            border: 1px solid #3b82f6;
        }

        /* Contenido de Entrega */
        .entrega-contenido {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .fecha-entrega {
            background: #f1f5f9;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.9rem;
            color: var(--gray-700);
            border-left: 3px solid #2563eb;
        }

        .contenido-entrega {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 6px;
            border-left: 3px solid #e2e8f0;
        }

        .contenido-entrega strong {
            color: var(--gray-800);
            display: block;
            margin-bottom: 0.5rem;
        }

        .contenido-entrega p {
            color: var(--gray-700);
            line-height: 1.6;
            margin: 0;
            background: transparent;
            padding: 0;
            border: none;
        }

        /* Archivo entrega con preview de imagen */
        .archivo-entrega {
            background: #fef7ff;
            padding: 1rem;
            border-radius: 6px;
            border-left: 3px solid #a855f7;
        }

        .archivo-entrega strong {
            color: var(--gray-800);
            display: block;
            margin-bottom: 0.5rem;
        }

        .archivo-content {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .archivo-preview {
            flex-shrink: 0;
        }

        .imagen-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 6px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .imagen-preview:hover {
            transform: scale(1.05);
        }

        .archivo-info {
            flex: 1;
        }

        .archivo-link {
            color: #7c3aed;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s ease;
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            margin-top: 0.5rem;
        }

        .archivo-link:hover {
            color: #5b21b6;
            border-color: #7c3aed;
            background: #faf5ff;
        }

        /* Modal para ver imágenes */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            cursor: pointer;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.3);
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 25px;
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            background: rgba(0, 0, 0, 0.5);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        /* Sistema de Calificación */
        .calificacion-section {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border: 1px solid #0ea5e9;
            margin-top: 1rem;
        }

        .calificacion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .calificacion-header strong {
            color: var(--gray-800);
        }

        .calificacion-actual {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 1rem;
            font-weight: 700;
            color: white;
            text-align: center;
            min-width: 60px;
        }

        .calificacion-excelente {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .calificacion-buena {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .calificacion-regular {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .calificacion-insuficiente {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .calificacion-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-group-inline {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .form-group-inline label {
            font-weight: 600;
            color: var(--gray-700);
            min-width: 50px;
        }

        .calificacion-input {
            padding: 0.5rem;
            border: 2px solid var(--gray-300);
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            width: 100px;
            transition: all 0.3s ease;
        }

        .calificacion-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid var(--gray-300);
            border-radius: 6px;
            font-size: 0.9rem;
            line-height: 1.5;
            resize: vertical;
            min-height: 80px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Sin Entrega */
        .sin-entrega {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border: 2px dashed #fca5a5;
            border-radius: var(--border-radius);
            color: var(--gray-700);
        }

        .mensaje-sin-entrega {
            font-size: 1.1rem;
            font-weight: 600;
            color: #dc2626;
            margin-bottom: 0.5rem;
        }

        .entrega-vencida {
            font-size: 0.9rem;
            color: #b91c1c;
            font-weight: 500;
        }

        /* Sin contenido */
        .sin-contenido {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 2px dashed var(--gray-300);
            color: var(--gray-600);
        }

        .sin-contenido h3 {
            color: var(--gray-700);
            margin-bottom: 1rem;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .sin-contenido p {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        /* Mensajes */
        .mensaje {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
        }

        .mensaje.success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 1px solid #10b981;
        }

        .mensaje.error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        /* Modo solo lectura para admin */
        .solo-lectura {
            opacity: 0.7;
            pointer-events: none;
        }

        .nota-admin {
            background: linear-gradient(135deg, #fef7ff, #fdf4ff);
            border: 1px solid #d8b4fe;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #7c2d12;
        }

        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .entregas-container {
                gap: 1.5rem;
            }
            
            .actividad-info,
            .estadisticas-entregas,
            .entregas-lista {
                padding: 1.5rem;
            }
            
            .lista-header {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            
            .entregas-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .alumno-header {
                flex-direction: column;
                gap: 0.75rem;
                align-items: stretch;
            }
            
            .estado-entrega {
                align-self: flex-start;
                width: fit-content;
            }
            
            .actividad-meta-info {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .calificacion-header {
                flex-direction: column;
                gap: 0.75rem;
                align-items: flex-start;
            }
            
            .form-group-inline {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }
            
            .form-group-inline label {
                min-width: unset;
            }
            
            .calificacion-input {
                width: 100%;
                max-width: 150px;
            }

            .archivo-content {
                flex-direction: column;
            }
            
            .imagen-preview {
                max-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .actividad-info,
            .estadisticas-entregas,
            .entregas-lista {
                padding: 1rem;
            }
            
            .entrega-card {
                padding: 1.25rem;
            }
            
            .calificacion-section {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
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
                <h1>Ver Entregas <?php if ($es_admin): ?><span class="badge-admin">ADMIN</span><?php endif; ?></h1>
                <p>Gestión de entregas para: <?php echo htmlspecialchars($actividad['titulo']); ?></p>
            </div>

            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo htmlspecialchars($tipo_mensaje); ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="entregas-container">
                <!-- Información de la Actividad -->
                <div class="actividad-info">
                    <h2><?php echo htmlspecialchars($actividad['titulo']); ?></h2>
                    <div class="actividad-detalles">
                        <div class="detalle-item">
                            <strong>Materia:</strong> <?php echo htmlspecialchars($actividad['materia_nombre']); ?>
                        </div>
                        <div class="detalle-item">
                            <strong>Curso:</strong> <?php echo htmlspecialchars($actividad['año_orientacion']); ?>
                        </div>
                        <?php if ($es_admin): ?>
                            <div class="detalle-item">
                                <strong>Profesor:</strong> <?php echo htmlspecialchars($actividad['profesor_nombre'] . ' ' . $actividad['profesor_apellido']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="detalle-item">
                            <strong>Descripción:</strong>
                            <p><?php echo nl2br(htmlspecialchars($actividad['descripcion'])); ?></p>
                        </div>
                    </div>
                    <div class="actividad-meta-info">
                        <span>Creada: <?php echo date('d/m/Y', strtotime($actividad['fecha_creacion'])); ?></span>
                        <?php if ($actividad['fecha_entrega']): ?>
                            <span>Fecha límite: <?php echo date('d/m/Y', strtotime($actividad['fecha_entrega'])); ?></span>
                        <?php else: ?>
                            <span>Sin fecha límite</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="estadisticas-entregas">
                    <h2>Estadísticas de Entregas</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">👥</div>
                            <div class="stat-number"><?php echo $total_alumnos; ?></div>
                            <div class="stat-label">Total Alumnos</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">📄</div>
                            <div class="stat-number"><?php echo $total_entregas; ?></div>
                            <div class="stat-label">Entregas Recibidas</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">📈</div>
                            <div class="stat-number"><?php echo $porcentaje_entregado; ?>%</div>
                            <div class="stat-label">Porcentaje Entregado</div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Entregas -->
                <div class="entregas-lista">
                    <div class="lista-header">
                        <h2>Lista de Alumnos y Entregas</h2>
                        <div class="filtros-entregas">
                            <select id="filtro-estado" onchange="filtrarEntregas()">
                                <option value="">Todos los estados</option>
                                <option value="entregado">Entregados</option>
                                <option value="pendiente">Pendientes</option>
                                <option value="revisado">Revisados</option>
                            </select>
                        </div>
                    </div>

                    <?php if (empty($alumnos_entregas)): ?>
                        <div class="sin-contenido">
                            <h3>No hay alumnos inscriptos</h3>
                            <p>No hay alumnos inscriptos en esta materia.</p>
                        </div>
                    <?php else: ?>
                        <div class="entregas-grid" id="entregas-grid">
                            <?php foreach ($alumnos_entregas as $item): ?>
                                <?php 
                                $tiene_entrega = !empty($item['entrega_id']);
                                $estado_clase = $tiene_entrega ? 'entregada' : 'pendiente';
                                if ($tiene_entrega && $item['estado'] === 'revisado') {
                                    $estado_clase = 'revisado';
                                }
                                ?>
                                <div class="entrega-card <?php echo $estado_clase; ?>" data-estado="<?php echo $tiene_entrega ? 'entregado' : 'pendiente'; ?>">
                                    <div class="alumno-header">
                                        <div class="alumno-info">
                                            <h3><?php echo htmlspecialchars($item['apellido'] . ', ' . $item['nombre']); ?></h3>
                                            <div class="usuario-info">ID: <?php echo $item['alumno_id']; ?></div>
                                        </div>
                                        <div class="estado-entrega estado-<?php echo str_replace('_', '-', $estado_clase); ?>">
                                            <?php 
                                            switch ($estado_clase) {
                                                case 'pendiente': echo 'Pendiente'; break;
                                                case 'entregada': echo 'Entregado'; break;
                                                case 'revisado': echo 'Revisado'; break;
                                                default: echo 'Desconocido'; break;
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class="entrega-contenido">
                                        <?php if ($tiene_entrega): ?>
                                            <div class="fecha-entrega">
                                                <strong>Fecha de entrega:</strong> 
                                                <?php echo date('d/m/Y H:i', strtotime($item['fecha_entrega'])); ?>
                                            </div>

                                            <?php if ($item['comentario']): ?>
                                                <div class="contenido-entrega">
                                                    <strong>Comentario del alumno:</strong>
                                                    <p><?php echo nl2br(htmlspecialchars($item['comentario'])); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($item['archivo_nombre']): ?>
                                                <div class="archivo-entrega">
                                                    <strong>Archivo adjunto:</strong>
                                                    <div class="archivo-content">
                                                        <?php if (esImagen($item['archivo_ruta'])): ?>
                                                            <div class="archivo-preview">
                                                                <img src="<?php echo htmlspecialchars($item['archivo_ruta']); ?>" 
                                                                     alt="<?php echo htmlspecialchars($item['archivo_nombre']); ?>"
                                                                     class="imagen-preview"
                                                                     onclick="abrirModal('<?php echo htmlspecialchars($item['archivo_ruta']); ?>', '<?php echo htmlspecialchars($item['archivo_nombre']); ?>')">
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="archivo-info">
                                                            <div style="font-weight: 600; margin-bottom: 0.5rem;">
                                                                <?php echo htmlspecialchars($item['archivo_nombre']); ?>
                                                            </div>
                                                            <a href="<?php echo htmlspecialchars($item['archivo_ruta']); ?>" 
                                                               class="archivo-link" target="_blank">
                                                                <?php if (esImagen($item['archivo_ruta'])): ?>
                                                                    🖼️ Ver imagen completa
                                                                <?php else: ?>
                                                                    📎 Descargar archivo
                                                                <?php endif; ?>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Sistema de Calificación -->
                                            <div class="calificacion-section <?php echo $es_admin ? 'solo-lectura' : ''; ?>">
                                                <div class="calificacion-header">
                                                    <strong>Calificación:</strong>
                                                    <?php if ($item['calificacion']): ?>
                                                        <?php 
                                                        $nota = floatval($item['calificacion']);
                                                        $clase_nota = '';
                                                        if ($nota >= 8) $clase_nota = 'excelente';
                                                        elseif ($nota >= 6) $clase_nota = 'buena';
                                                        elseif ($nota >= 4) $clase_nota = 'regular';
                                                        else $clase_nota = 'insuficiente';
                                                        ?>
                                                        <span class="calificacion-actual calificacion-<?php echo $clase_nota; ?>">
                                                            <?php echo number_format($nota, 1); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="color: var(--gray-500);">Sin calificar</span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($item['feedback_profesor']): ?>
                                                    <div style="background: #f8fafc; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; border-left: 3px solid #3b82f6;">
                                                        <strong style="color: var(--gray-800); display: block; margin-bottom: 0.5rem;">Comentarios del profesor:</strong>
                                                        <p style="color: var(--gray-700); line-height: 1.6; margin: 0;"><?php echo nl2br(htmlspecialchars($item['feedback_profesor'])); ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!$es_admin): ?>
                                                    <form method="POST" class="calificacion-form">
                                                        <input type="hidden" name="action" value="calificar">
                                                        <input type="hidden" name="entrega_id" value="<?php echo $item['entrega_id']; ?>">
                                                        
                                                        <div class="form-group-inline">
                                                            <label for="calificacion-<?php echo $item['entrega_id']; ?>">Nota:</label>
                                                            <input type="number" 
                                                                   name="calificacion" 
                                                                   id="calificacion-<?php echo $item['entrega_id']; ?>"
                                                                   class="calificacion-input"
                                                                   min="1" max="10" step="0.1" 
                                                                   value="<?php echo $item['calificacion'] ?? ''; ?>" 
                                                                   placeholder="1-10">
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label for="feedback-<?php echo $item['entrega_id']; ?>">Comentarios:</label>
                                                            <textarea name="feedback" 
                                                                      id="feedback-<?php echo $item['entrega_id']; ?>"
                                                                      placeholder="Comentarios y sugerencias para el alumno..."><?php echo htmlspecialchars($item['feedback_profesor'] ?? ''); ?></textarea>
                                                        </div>
                                                        
                                                        <button type="submit" class="btn btn-primary">
                                                            💾 Guardar Calificación
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="nota-admin">
                                                        ℹ️ Como administrador, puedes ver las calificaciones pero no modificarlas. Solo los profesores pueden calificar entregas.
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                        <?php else: ?>
                                            <div class="sin-entrega">
                                                <div class="mensaje-sin-entrega">No ha entregado la actividad</div>
                                                <?php if ($actividad['fecha_entrega'] && strtotime($actividad['fecha_entrega']) < time()): ?>
                                                    <div class="entrega-vencida">⚠️ Fecha de entrega vencida</div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Botón de regreso -->
                <div style="text-align: center; margin-top: 2rem;">
                    <?php if ($es_admin): ?>
                        <a href="../admin/actividades.php" class="btn btn-secondary">
                            ⬅️ Volver al Panel de Admin
                        </a>
                    <?php else: ?>
                        <a href="actividades.php?materia=<?php echo $actividad['materia_id']; ?>" class="btn btn-secondary">
                            ⬅️ Volver a Actividades
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para ver imágenes -->
    <div id="modalImagen" class="modal" onclick="cerrarModal()">
        <span class="modal-close" onclick="cerrarModal()">&times;</span>
        <img class="modal-content" id="imagenModal">
    </div>

    <script>
        function filtrarEntregas() {
            const filtro = document.getElementById('filtro-estado').value;
            const grid = document.getElementById('entregas-grid');
            const cards = grid.querySelectorAll('.entrega-card');
            
            cards.forEach(card => {
                const estado = card.dataset.estado;
                if (!filtro || estado === filtro || 
                    (filtro === 'revisado' && card.classList.contains('revisado'))) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function abrirModal(rutaImagen, nombreArchivo) {
            const modal = document.getElementById('modalImagen');
            const modalImg = document.getElementById('imagenModal');
            modal.style.display = 'block';
            modalImg.src = rutaImagen;
            modalImg.alt = nombreArchivo;
        }

        function cerrarModal() {
            const modal = document.getElementById('modalImagen');
            modal.style.display = 'none';
        }

        // Auto-ocultar mensajes después de 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const mensajes = document.querySelectorAll('.mensaje');
            mensajes.forEach(function(mensaje) {
                setTimeout(function() {
                    mensaje.style.opacity = '0';
                    setTimeout(function() {
                        mensaje.remove();
                    }, 300);
                }, 5000);
            });

            // Cerrar modal con tecla Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cerrarModal();
                }
            });
        });
    </script>
</body>
</html>