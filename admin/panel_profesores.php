<?php
require_once '../config.php';
verificarTipoUsuario(['administrador']);

// Obtener estadísticas generales de profesores
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT p.id) as total_profesores
    FROM profesores p
    JOIN usuarios u ON p.usuario_id = u.id
    WHERE u.activo = 1
");
$stats_profesores = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT COUNT(DISTINCT m.id) as total_materias_asignadas
    FROM materias m
    WHERE m.profesor_id IS NOT NULL
");
$stats_materias_asignadas = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT COUNT(DISTINCT m.id) as total_materias_sin_asignar
    FROM materias m
    WHERE m.profesor_id IS NULL
");
$stats_materias_sin_asignar = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT COUNT(DISTINCT p.especialidad) as total_especialidades
    FROM profesores p
    JOIN usuarios u ON p.usuario_id = u.id
    WHERE u.activo = 1 AND p.especialidad IS NOT NULL AND p.especialidad != ''
");
$stats_especialidades = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener todos los profesores con sus datos
$stmt = $pdo->query("
    SELECT p.*, u.nombre, u.apellido, u.email, u.activo,
           COUNT(DISTINCT m.id) as total_materias,
           GROUP_CONCAT(DISTINCT CONCAT(m.nombre, ' (', an.año, '° ', o.nombre, ')') SEPARATOR ', ') as materias_detalle
    FROM profesores p
    JOIN usuarios u ON p.usuario_id = u.id
    LEFT JOIN materias m ON p.usuario_id = m.profesor_id
    LEFT JOIN años an ON m.año_id = an.id
    LEFT JOIN orientaciones o ON an.orientacion_id = o.id
    WHERE u.activo = 1
    GROUP BY p.id, u.nombre, u.apellido, u.email, u.activo, p.dni, p.telefono, p.especialidad
    ORDER BY u.apellido, u.nombre
");
$profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener profesores por especialidad
$stmt = $pdo->query("
    SELECT COALESCE(p.especialidad, 'Sin especialidad') as especialidad,
           COUNT(*) as cantidad
    FROM profesores p
    JOIN usuarios u ON p.usuario_id = u.id
    WHERE u.activo = 1
    GROUP BY p.especialidad
    ORDER BY cantidad DESC
");
$profesores_por_especialidad = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener materias sin profesor asignado
$stmt = $pdo->query("
    SELECT m.*, CONCAT(an.año, '° - ', o.nombre) as curso
    FROM materias m
    JOIN años an ON m.año_id = an.id
    JOIN orientaciones o ON an.orientacion_id = o.id
    WHERE m.profesor_id IS NULL
    ORDER BY an.año, m.nombre
");
$materias_sin_profesor = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Noticias dirigidas a profesores
$stmt = $pdo->query("
    SELECT n.*, u.nombre, u.apellido
    FROM noticias n
    JOIN usuarios u ON n.autor_id = u.id
    WHERE (n.dirigido_a = 'todos' OR n.dirigido_a = 'profesores') AND n.activo = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT 5
");
$noticias_profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Profesores - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <style>
        /* CSS ESPECÍFICO PANEL PROFESORES */
        .profesores-container {
            display: grid;
            gap: 2rem;
            animation: fadeInUp 0.6s ease-out;
        }

        /* Estadísticas - IGUAL QUE PANEL ELECTRÓNICA */
        .estadisticas-prof {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .estadisticas-prof::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #8b5cf6, #a855f7, #c084fc);
        }

        .estadisticas-prof h2 {
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--white), #faf9ff);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #8b5cf6, #a855f7);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border-color: #8b5cf6;
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            opacity: 0.9;
            transition: transform 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .stat-description {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        /* Tabs */
        .tabs-prof {
            display: flex;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            position: relative;
        }

        .tabs-prof::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #8b5cf6, #a855f7, #c084fc, #ddd6fe);
        }

        .tab-button {
            flex: 1;
            padding: 1.25rem;
            background: var(--gray-100);
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            color: var(--gray-600);
            position: relative;
        }

        .tab-button.active {
            background: linear-gradient(135deg, #8b5cf6, #a855f7);
            color: var(--white);
        }

        .tab-button:hover:not(.active) {
            background: var(--gray-200);
            color: var(--gray-800);
        }

        .tab-content {
            display: none;
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .tab-content.active {
            display: block;
        }

        .tab-content h2 {
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            border-bottom: 2px solid var(--gray-200);
            padding-bottom: 0.5rem;
        }

        /* Grid profesores */
        .profesores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .profesor-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .profesor-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #8b5cf6, #a855f7);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .profesor-card:hover::before {
            transform: scaleX(1);
        }

        .profesor-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border-color: #8b5cf6;
        }

        .profesor-nombre {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profesor-nombre::before {
            content: '👨‍🏫';
            font-size: 1rem;
        }

        .profesor-especialidad {
            background: linear-gradient(135deg, #8b5cf6, #a855f7);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .profesor-info {
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .profesor-info strong {
            color: var(--gray-800);
        }

        .profesor-materias {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
            border: 1px solid var(--gray-200);
        }

        .profesor-materias strong {
            color: var(--gray-800);
            font-size: 0.9rem;
            display: block;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profesor-materias strong::before {
            content: '📚';
            font-size: 0.875rem;
        }

        .materia-item {
            background: var(--white);
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.25rem;
            border-radius: 6px;
            font-size: 0.875rem;
            border-left: 3px solid #8b5cf6;
            transition: all 0.2s ease;
        }

        .materia-item:hover {
            transform: translateX(3px);
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.15);
        }

        .sin-materias {
            color: var(--warning-color);
            font-style: italic;
            font-size: 0.875rem;
            text-align: center;
            padding: 1rem;
            background: #fef3c7;
            border-radius: 6px;
            border: 1px solid #fde68a;
        }

        /* Especialidades */
        .especialidades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .especialidad-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .especialidad-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #8b5cf6, #a855f7);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .especialidad-card:hover::before {
            transform: scaleX(1);
        }

        .especialidad-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
            border-color: #8b5cf6;
        }

        .especialidad-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
            opacity: 0.8;
            transition: transform 0.3s ease;
        }

        .especialidad-card:hover .especialidad-icon {
            transform: scale(1.1);
        }

        .especialidad-nombre {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .especialidad-cantidad {
            font-size: 1.5rem;
            font-weight: 700;
            color: #8b5cf6;
            margin-bottom: 0.25rem;
        }

        .especialidad-descripcion {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        /* Materias sin profesor */
        .materias-sin-profesor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .materia-sin-profesor {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            padding: 1rem;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--warning-color);
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            border: 1px solid #f59e0b;
        }

        .materia-sin-profesor:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2);
        }

        .materia-sin-profesor-nombre {
            font-weight: 600;
            color: #92400e;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }

        .materia-sin-profesor-año {
            color: #92400e;
            font-size: 0.875rem;
            opacity: 0.9;
        }

        /* Acciones rápidas - IGUAL QUE PANEL ELECTRÓNICA */
        .acciones-rapidas {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .acciones-rapidas h2 {
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .acciones-rapidas h2::before {
            content: '⚡';
            font-size: 1.25rem;
        }

        .acciones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .accion-card {
            background: linear-gradient(135deg, #faf9ff, #f3e8ff);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: var(--gray-800);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .accion-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: #8b5cf6;
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .accion-card:hover::before {
            transform: scaleY(1);
        }

        .accion-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            color: #8b5cf6;
            border-color: #8b5cf6;
        }

        .accion-icon {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
            padding: 1rem;
            border-radius: 50%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 60px;
            min-height: 60px;
        }

        .accion-card:hover .accion-icon {
            background: linear-gradient(135deg, #8b5cf6, #a855f7);
            color: var(--white);
            transform: scale(1.1) rotate(5deg);
        }

        .accion-texto h4 {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .accion-texto p {
            color: var(--gray-600);
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.4;
        }

        .accion-card:hover .accion-texto p {
            color: var(--gray-700);
        }

        /* Estados sin contenido */
        .sin-contenido,
        .sin-profesores,
        .sin-materias-sin-profesor,
        .sin-noticias-prof {
            text-align: center;
            padding: 3rem;
            color: var(--gray-600);
            background: var(--gray-50);
            border-radius: var(--border-radius);
            border: 2px dashed var(--gray-300);
        }

        .sin-contenido h3,
        .sin-profesores h3,
        .sin-materias-sin-profesor h3,
        .sin-noticias-prof h3 {
            color: var(--gray-700);
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        /* Botones */
        .btn-small {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            text-decoration: none;
            border-radius: 6px;
            margin-right: 0.375rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            border: 1px solid transparent;
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--warning-color), #f59e0b);
            color: var(--white);
            border-color: var(--warning-color);
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #b45309, #d97706);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
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

        .profesores-container > * {
            animation: fadeInUp 0.6s ease-out;
        }

        .profesores-container > *:nth-child(2) { animation-delay: 0.1s; }
        .profesores-container > *:nth-child(3) { animation-delay: 0.2s; }

        .stat-card {
            animation: slideInUp 0.4s ease-out;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .accion-card {
            animation: fadeInScale 0.5s ease-out;
        }

        .accion-card:nth-child(1) { animation-delay: 0.2s; }
        .accion-card:nth-child(2) { animation-delay: 0.3s; }
        .accion-card:nth-child(3) { animation-delay: 0.4s; }
        .accion-card:nth-child(4) { animation-delay: 0.5s; }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tabs-prof {
                flex-direction: column;
            }
            
            .tab-button {
                border-bottom: 1px solid var(--gray-200);
            }
            
            .tab-button:last-child {
                border-bottom: none;
            }
            
            .profesores-grid, .especialidades-grid, .acciones-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .stat-icon {
                font-size: 2.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .estadisticas-prof {
                padding: 1.5rem;
            }
            
            .accion-card {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }
            
            .accion-icon {
                font-size: 2rem;
                min-width: 50px;
                min-height: 50px;
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
                <h1>👨‍🏫 Panel de Profesores</h1>
                <p>Gestión integral del cuerpo docente</p>
            </div>
            
            <div class="profesores-container">
                <!-- Estadísticas Generales -->
                <div class="estadisticas-prof">
                    <h2>📊 Estadísticas del Cuerpo Docente</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">👨‍🏫</div>
                            <div class="stat-number"><?php echo $stats_profesores['total_profesores'] ?? 0; ?></div>
                            <div class="stat-label">Profesores Activos</div>
                            <div class="stat-description">Total en el sistema</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">🎯</div>
                            <div class="stat-number"><?php echo $stats_especialidades['total_especialidades'] ?? 0; ?></div>
                            <div class="stat-label">Especialidades</div>
                            <div class="stat-description">Áreas de conocimiento</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📚</div>
                            <div class="stat-number"><?php echo $stats_materias_asignadas['total_materias_asignadas'] ?? 0; ?></div>
                            <div class="stat-label">Materias Asignadas</div>
                            <div class="stat-description">Con profesor designado</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">⚠️</div>
                            <div class="stat-number"><?php echo $stats_materias_sin_asignar['total_materias_sin_asignar'] ?? 0; ?></div>
                            <div class="stat-label">Sin Asignar</div>
                            <div class="stat-description">Requieren atención</div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="tabs-prof">
                    <button class="tab-button active" onclick="cambiarTab('lista-profesores')">👥 Lista de Profesores</button>
                    <button class="tab-button" onclick="cambiarTab('especialidades')">🎯 Por Especialidad</button>
                    <button class="tab-button" onclick="cambiarTab('materias-sin-asignar')">⚠️ Sin Asignar</button>
                    <button class="tab-button" onclick="cambiarTab('noticias')">📢 Noticias</button>
                </div>
                
                <!-- Tab Lista de Profesores -->
                <div id="lista-profesores" class="tab-content active">
                    <h2>👥 Cuerpo Docente</h2>
                    
                    <?php if (!empty($profesores)): ?>
                        <div class="profesores-grid">
                            <?php foreach ($profesores as $profesor): ?>
                                <div class="profesor-card">
                                    <div class="profesor-nombre">
                                        <?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellido']); ?>
                                    </div>
                                    
                                    <?php if ($profesor['especialidad']): ?>
                                        <div class="profesor-especialidad">
                                            <?php echo htmlspecialchars($profesor['especialidad']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="profesor-info">
                                        <strong>📧 Email:</strong> <?php echo htmlspecialchars($profesor['email'] ?: 'No especificado'); ?>
                                    </div>
                                    
                                    <?php if ($profesor['telefono']): ?>
                                        <div class="profesor-info">
                                            <strong>📞 Teléfono:</strong> <?php echo htmlspecialchars($profesor['telefono']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($profesor['dni']): ?>
                                        <div class="profesor-info">
                                            <strong>🆔 DNI:</strong> <?php echo htmlspecialchars($profesor['dni']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="profesor-materias">
                                        <strong>Materias Asignadas (<?php echo $profesor['total_materias']; ?>):</strong>
                                        <?php if ($profesor['materias_detalle']): ?>
                                            <?php foreach (explode(', ', $profesor['materias_detalle']) as $materia): ?>
                                                <div class="materia-item"><?php echo htmlspecialchars($materia); ?></div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="sin-materias">Sin materias asignadas</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="sin-profesores">
                            <h3>👨‍🏫 No hay profesores registrados</h3>
                            <p>No se encontraron profesores en el sistema.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tab Especialidades -->
                <div id="especialidades" class="tab-content">
                    <h2>🎯 Distribución por Especialidades</h2>
                    
                    <?php if (!empty($profesores_por_especialidad)): ?>
                        <div class="especialidades-grid">
                            <?php 
                            $iconos_especialidad = [
                                'Programación' => '💻',
                                'programacion' => '💻',
                                'Electrónica' => '🔌',
                                'electronica' => '🔌',
                                'Matemática' => '📐',
                                'matematica' => '📐',
                                'Lengua' => '📝',
                                'lengua' => '📝',
                                'Inglés' => '🌐',
                                'ingles' => '🌐',
                                'Sin especialidad' => '❓'
                            ];
                            
                            foreach ($profesores_por_especialidad as $especialidad): 
                            ?>
                                <div class="especialidad-card">
                                    <div class="especialidad-icon">
                                        <?php echo $iconos_especialidad[$especialidad['especialidad']] ?? '🎓'; ?>
                                    </div>
                                    <div class="especialidad-nombre"><?php echo htmlspecialchars($especialidad['especialidad']); ?></div>
                                    <div class="especialidad-cantidad"><?php echo $especialidad['cantidad']; ?></div>
                                    <div class="especialidad-descripcion">
                                        <?php echo $especialidad['cantidad'] == 1 ? 'profesor' : 'profesores'; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="sin-contenido">
                            <h3>🎯 No hay especialidades definidas</h3>
                            <p>Configure las especialidades de los profesores en sus perfiles.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tab Materias Sin Asignar -->
                <div id="materias-sin-asignar" class="tab-content">
                    <h2>⚠️ Materias Pendientes de Asignación</h2>
                    
                    <?php if (!empty($materias_sin_profesor)): ?>
                        <div class="materias-sin-profesor-grid">
                            <?php foreach ($materias_sin_profesor as $materia): ?>
                                <div class="materia-sin-profesor">
                                    <div class="materia-sin-profesor-nombre">
                                        <?php echo htmlspecialchars($materia['nombre']); ?>
                                    </div>
                                    <div class="materia-sin-profesor-año">
                                        <?php echo htmlspecialchars($materia['curso']); ?>
                                    </div>
                                    <div style="margin-top: 0.5rem;">
                                        <a href="../admin/academico.php" class="btn-small btn-edit">
                                            Asignar Profesor
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="sin-materias-sin-profesor">
                            <h3>✅ Excelente Organización</h3>
                            <p>Todas las materias tienen un profesor asignado.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tab Noticias -->
                <div id="noticias" class="tab-content">
                    <h2>📢 Comunicaciones para Profesores</h2>
                    
                    <?php if (!empty($noticias_profesores)): ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem;">
                            <?php foreach ($noticias_profesores as $noticia): ?>
                                <div style="background: var(--white); padding: 1.5rem; border-radius: var(--border-radius); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); transition: all 0.3s ease; border: 1px solid var(--gray-200); position: relative; overflow: hidden;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; gap: 1rem;">
                                        <h4 style="color: var(--gray-800); font-weight: 600; font-size: 1.1rem; line-height: 1.3; margin: 0;">
                                            <?php echo htmlspecialchars($noticia['titulo']); ?>
                                        </h4>
                                        <span style="background: linear-gradient(135deg, #f3e8ff, #e9d5ff); color: #7c3aed; border: 1px solid #c4b5fd; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.75rem; font-weight: 500; white-space: nowrap;">
                                            <?php 
                                            $dirigido_labels = [
                                                'todos' => 'General',
                                                'profesores' => 'Profesores'
                                            ];
                                            echo $dirigido_labels[$noticia['dirigido_a']] ?? ucfirst($noticia['dirigido_a']);
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <div style="color: var(--gray-700); line-height: 1.6; margin-bottom: 1rem; font-size: 0.95rem;">
                                        <?php echo nl2br(htmlspecialchars(substr($noticia['contenido'], 0, 200))); ?>
                                        <?php if (strlen($noticia['contenido']) > 200): ?>...<?php endif; ?>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; color: var(--gray-600); border-top: 1px solid var(--gray-200); padding-top: 1rem;">
                                        <span style="display: flex; align-items: center; gap: 0.25rem;">
                                            👤 <?php echo htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellido']); ?>
                                        </span>
                                        <span style="display: flex; align-items: center; gap: 0.25rem;">
                                            📅 <?php echo formatearFecha($noticia['fecha_publicacion']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="text-align: center; margin-top: 1.5rem;">
                            <a href="../noticias.php" class="btn-primary">Ver Todas las Noticias</a>
                        </div>
                    <?php else: ?>
                        <div class="sin-noticias-prof">
                            <h3>📭 Sin Noticias</h3>
                            <p>No hay comunicaciones dirigidas a profesores en este momento.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Acciones Rápidas -->
                <div class="acciones-rapidas">
                    <h2>⚡ Acciones Rápidas</h2>
                    <div class="acciones-grid">
                        <a href="../admin/usuarios.php" class="accion-card">
                            <div class="accion-icon">👥</div>
                            <div class="accion-texto">
                                <h4>Gestionar Profesores</h4>
                                <p>Crear y administrar cuentas de profesores</p>
                            </div>
                        </a>
                        
                        <a href="../admin/academico.php" class="accion-card">
                            <div class="accion-icon">📚</div>
                            <div class="accion-texto">
                                <h4>Asignar Materias</h4>
                                <p>Configurar materias y horarios</p>
                            </div>
                        </a>
                        
                        <a href="../admin/asistencias.php" class="accion-card">
                            <div class="accion-icon">📊</div>
                            <div class="accion-texto">
                                <h4>Ver Reportes</h4>
                                <p>Seguimiento y estadísticas docentes</p>
                            </div>
                        </a>
                        
                        <a href="../noticias.php" class="accion-card">
                            <div class="accion-icon">📢</div>
                            <div class="accion-texto">
                                <h4>Comunicaciones</h4>
                                <p>Publicar noticias para profesores</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../js/main.js"></script>
    <script>
        function cambiarTab(tabName) {
            // Ocultar todos los tabs
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remover clase active de todos los botones
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Mostrar el tab seleccionado
            document.getElementById(tabName).classList.add('active');
            
            // Activar el botón correspondiente
            event.target.classList.add('active');
        }
        
        // Animación de entrada para las cards y conteo de estadísticas
        document.addEventListener('DOMContentLoaded', function() {
            // Animación de conteo para estadísticas
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalNumber = parseInt(stat.textContent);
                if (finalNumber > 0) {
                    let currentNumber = 0;
                    const increment = Math.max(1, Math.ceil(finalNumber / 20));
                    
                    const timer = setInterval(() => {
                        currentNumber += increment;
                        if (currentNumber >= finalNumber) {
                            currentNumber = finalNumber;
                            clearInterval(timer);
                        }
                        stat.textContent = currentNumber;
                    }, 80);
                }
            });
            
            // Intersection Observer para animaciones
            const cards = document.querySelectorAll('.profesor-card, .accion-card, .especialidad-card');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationDelay = `${index * 0.1}s`;
                        entry.target.classList.add('animate-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            
            cards.forEach(card => {
                observer.observe(card);
            });
        });
    </script>
</body>
</html>