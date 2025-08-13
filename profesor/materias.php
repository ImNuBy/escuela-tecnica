<?php
require_once '../config.php';
verificarTipoUsuario(['profesor']);

$mensaje = '';
$tipo_mensaje = '';

// Obtener materias del profesor
$stmt = $pdo->prepare("
    SELECT m.*, CONCAT(a.año, '° - ', o.nombre) as año_orientacion,
           COUNT(DISTINCT al.id) as total_alumnos
    FROM materias m
    JOIN años a ON m.año_id = a.id
    JOIN orientaciones o ON a.orientacion_id = o.id
    LEFT JOIN alumnos al ON al.año_id = a.id
    LEFT JOIN usuarios u ON al.usuario_id = u.id AND u.activo = 1
    WHERE m.profesor_id = ?
    GROUP BY m.id, m.nombre, a.año, o.nombre
    ORDER BY a.año, m.nombre
");
$stmt->execute([$_SESSION['usuario_id']]);
$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener horarios de las materias del profesor
$horarios = [];
if (!empty($materias)) {
    $materia_ids = array_column($materias, 'id');
    $placeholders = str_repeat('?,', count($materia_ids) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT h.*, m.nombre as materia_nombre
        FROM horarios h
        JOIN materias m ON h.materia_id = m.id
        WHERE h.materia_id IN ($placeholders)
        ORDER BY h.dia_semana, h.hora_inicio
    ");
    $stmt->execute($materia_ids);
    $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Organizar horarios por materia
$horarios_por_materia = [];
foreach ($horarios as $horario) {
    $horarios_por_materia[$horario['materia_id']][] = $horario;
}

// Obtener estadísticas generales del profesor
$stats = [
    'total_materias' => count($materias),
    'total_alumnos' => array_sum(array_column($materias, 'total_alumnos')),
    'total_horarios' => count($horarios)
];

// Calcular horas semanales
$total_horas = 0;
foreach ($horarios as $horario) {
    $inicio = new DateTime($horario['hora_inicio']);
    $fin = new DateTime($horario['hora_fin']);
    $diferencia = $inicio->diff($fin);
    $total_horas += $diferencia->h + ($diferencia->i / 60);
}
$stats['horas_semanales'] = $total_horas;

// Obtener noticias dirigidas a profesores
$stmt = $pdo->prepare("
    SELECT n.*, u.nombre, u.apellido
    FROM noticias n
    JOIN usuarios u ON n.autor_id = u.id
    WHERE (n.dirigido_a = 'todos' OR n.dirigido_a = 'profesores') AND n.activo = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT 3
");
$stmt->execute();
$noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dias_semana = [
    'lunes' => 'Lunes',
    'martes' => 'Martes',
    'miercoles' => 'Miércoles',
    'jueves' => 'Jueves',
    'viernes' => 'Viernes'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Materias - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/profesor.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>👨‍🏫 Mis Materias</h1>
                <p>Panel de control para <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="profesor-container">
                <!-- Estadísticas del Profesor -->
                <div class="estadisticas-profesor">
                    <h2>📊 Mi Resumen</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">📚</div>
                            <div class="stat-number"><?php echo $stats['total_materias']; ?></div>
                            <div class="stat-label">Materias Asignadas</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">👨‍🎓</div>
                            <div class="stat-number"><?php echo $stats['total_alumnos']; ?></div>
                            <div class="stat-label">Total Alumnos</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">🕒</div>
                            <div class="stat-number"><?php echo number_format($stats['horas_semanales'], 1); ?></div>
                            <div class="stat-label">Horas Semanales</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📅</div>
                            <div class="stat-number"><?php echo $stats['total_horarios']; ?></div>
                            <div class="stat-label">Clases por Semana</div>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de Materias -->
                <?php if (!empty($materias)): ?>
                    <div>
                        <h2 class="seccion-titulo">📖 Mis Materias (<?php echo count($materias); ?>)</h2>
                        <div class="materias-grid">
                            <?php foreach ($materias as $materia): ?>
                                <div class="materia-card">
                                    <div class="materia-nombre">
                                        <?php echo htmlspecialchars($materia['nombre']); ?>
                                    </div>
                                    
                                    <div class="materia-curso">
                                        <?php echo htmlspecialchars($materia['año_orientacion']); ?>
                                    </div>
                                    
                                    <div class="materia-stats">
                                        <span>👥 <?php echo $materia['total_alumnos']; ?> alumnos</span>
                                        <span>🕒 <?php echo count($horarios_por_materia[$materia['id']] ?? []); ?> clases/semana</span>
                                    </div>
                                    
                                    <!-- Horarios de la materia -->
                                    <?php if (!empty($horarios_por_materia[$materia['id']])): ?>
                                        <div class="horarios-materia">
                                            <h4>📅 Horarios:</h4>
                                            <?php foreach ($horarios_por_materia[$materia['id']] as $horario): ?>
                                                <div class="horario-item">
                                                    <div>
                                                        <div class="horario-dia"><?php echo ucfirst($horario['dia_semana']); ?></div>
                                                        <div class="horario-tiempo">
                                                            <?php echo formatearHora($horario['hora_inicio']) . ' - ' . formatearHora($horario['hora_fin']); ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($horario['aula']): ?>
                                                        <div class="horario-aula"><?php echo htmlspecialchars($horario['aula']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="div-sin-horarios">
                                            Sin horarios asignados
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Acciones -->
                                    <div class="acciones-materia">
                                        <a href="../profesor/notas.php?materia=<?php echo $materia['id']; ?>" class="btn-small btn-primary-small">
                                            📊 Notas
                                        </a>
                                        <a href="../profesor/asistencias.php?materia=<?php echo $materia['id']; ?>" class="btn-small btn-primary-small">
                                            ✅ Asistencias
                                        </a>
                                        <a href="../profesor/actividades.php?materia=<?php echo $materia['id']; ?>" class="btn-small btn-secondary-small">
                                            📝 Actividades
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sin-materias">
                        <h3>📚 No tienes materias asignadas</h3>
                        <p>Contacta al administrador para que te asigne materias.</p>
                        <p style="margin-top: 1rem;">
                            <a href="../dashboard.php" class="btn-primary">🏠 Volver al Panel Principal</a>
                        </p>
                    </div>
                <?php endif; ?>
                
                <!-- Noticias para Profesores -->
                <?php if (!empty($noticias)): ?>
                    <div class="noticias-profesor">
                        <h2>📢 Comunicados</h2>
                        <?php foreach ($noticias as $noticia): ?>
                            <div class="noticia-card">
                                <div class="noticia-titulo">
                                    <?php echo htmlspecialchars($noticia['titulo']); ?>
                                </div>
                                <div class="noticia-contenido">
                                    <?php echo nl2br(htmlspecialchars(substr($noticia['contenido'], 0, 150))); ?>
                                    <?php if (strlen($noticia['contenido']) > 150): ?>...<?php endif; ?>
                                </div>
                                <div class="noticia-meta">
                                    <span>👤 <?php echo htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellido']); ?></span>
                                    <span>📅 <?php echo formatearFecha($noticia['fecha_publicacion']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="../noticias.php" class="btn-secondary">Ver Todas las Noticias</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Acciones Rápidas -->
                <div class="seccion-acciones">
                    <h2>⚡ Acciones Rápidas</h2>
                    <div class="grid-acciones">