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
    <link rel="stylesheet" href="../css/mis_materias.css">
    <style>
        /* Estilos específicos para Mis Materias del Profesor */

.profesor-container {
    display: grid;
    gap: 2rem;
    animation: fadeInUp 0.6s ease-out;
}

/* Estadísticas del Profesor */
.estadisticas-profesor {
    background: var(--white);
    padding: 2rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.estadisticas-profesor::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2563eb, #3b82f6, #1d4ed8);
}

.estadisticas-profesor h2 {
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
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    color: var(--white);
    padding: 1.5rem;
    border-radius: var(--border-radius);
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    border: 1px solid transparent;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    transform: scale(0);
    transition: transform 0.3s ease;
}

.stat-card:hover::before {
    transform: scale(1);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.4);
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    display: block;
    position: relative;
    z-index: 1;
    opacity: 0.9;
    transition: transform 0.3s ease;
}

.stat-card:hover .stat-icon {
    transform: scale(1.1);
}

.stat-number {
    font-size: 2.25rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    position: relative;
    z-index: 1;
    display: block;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.95;
    position: relative;
    z-index: 1;
    font-weight: 500;
}

/* Secciones con títulos */
.seccion-titulo {
    color: var(--gray-800);
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 2px solid var(--gray-200);
    padding-bottom: 0.5rem;
}

/* Grid de materias del profesor */
.materias-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
}

.materia-card {
    background: var(--white);
    padding: 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
}

.materia-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #2563eb, #3b82f6);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.materia-card:hover::before {
    transform: scaleX(1);
}

.materia-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    border-color: #2563eb;
}

.materia-nombre {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.materia-nombre::before {
    content: '📖';
    font-size: 1rem;
}

.materia-curso {
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    color: var(--gray-700);
    padding: 0.375rem 0.875rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
    margin-bottom: 1rem;
    border: 1px solid var(--gray-300);
}

.materia-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
    font-size: 0.875rem;
    color: var(--gray-600);
    gap: 1rem;
}

.materia-stats span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 500;
}

/* Horarios dentro de materias */
.horarios-materia {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
    border: 1px solid var(--gray-200);
}

.horarios-materia h4 {
    color: var(--gray-800);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.horario-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.625rem 0;
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.875rem;
}

.horario-item:last-child {
    border-bottom: none;
}

.horario-dia {
    font-weight: 600;
    color: #2563eb;
    text-transform: capitalize;
    min-width: 80px;
}

.horario-tiempo {
    font-size: 0.875rem;
    color: var(--gray-600);
    font-family: monospace;
    flex: 1;
    text-align: center;
    background: var(--white);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    margin: 0 0.5rem;
}

.horario-aula {
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    color: var(--white);
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 500;
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);
}

.div-sin-horarios {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    padding: 1rem;
    border-radius: var(--border-radius);
    text-align: center;
    font-style: italic;
    border: 1px solid #f59e0b;
    font-weight: 500;
}

/* Acciones de materia */
.acciones-materia {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
}

.btn-small {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    position: relative;
    overflow: hidden;
}

.btn-small::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn-small:hover::before {
    left: 100%;
}

.btn-primary-small {
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    color: var(--white);
    border: 1px solid #2563eb;
}

.btn-primary-small:hover {
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.btn-secondary-small {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
}

.btn-secondary-small:hover {
    background: linear-gradient(135deg, #e5e7eb, #d1d5db);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    color: var(--gray-800);
}

/* Estado sin materias */
.sin-materias {
    text-align: center;
    padding: 3rem;
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 2px dashed var(--gray-300);
    color: var(--gray-600);
}

.sin-materias h3 {
    color: var(--gray-700);
    margin-bottom: 1rem;
    font-size: 1.25rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.sin-materias h3::before {
    content: '📚';
    font-size: 1.5rem;
}

.sin-materias p {
    color: var(--gray-600);
    line-height: 1.6;
    margin-bottom: 0.5rem;
}

/* Noticias para profesores */
.noticias-profesor {
    background: var(--white);
    padding: 2rem;
    border-radius: var(--border-radius);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--gray-200);
}

.noticias-profesor h2 {
    color: var(--gray-800);
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 2px solid var(--gray-200);
    padding-bottom: 0.5rem;
}

.noticia-card {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 1.5rem;
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.noticia-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.noticia-card:hover::before {
    transform: scaleY(1);
}

.noticia-card:hover {
    transform: translateX(8px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    border-color: #2563eb;
}

.noticia-titulo {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.75rem;
    font-size: 1.1rem;
    line-height: 1.3;
}

.noticia-contenido {
    color: var(--gray-700);
    line-height: 1.6;
    margin-bottom: 1rem;
    font-size: 0.95rem;
}

.noticia-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
    color: var(--gray-600);
    border-top: 1px solid var(--gray-200);
    padding-top: 0.75rem;
}

.noticia-meta span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Debug temporal */
.debug-info {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 2px solid #f59e0b;
    padding: 1.5rem;
    border-radius: var(--border-radius);
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
}

.debug-info h3 {
    color: #92400e;
    margin-bottom: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.debug-info p {
    margin-bottom: 0.5rem;
    color: #92400e;
}

.debug-info ul {
    margin-left: 1.5rem;
    color: #92400e;
}

.debug-info li {
    margin-bottom: 0.25rem;
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

.profesor-container > * {
    animation: fadeInUp 0.6s ease-out;
}

.profesor-container > *:nth-child(2) { animation-delay: 0.1s; }
.profesor-container > *:nth-child(3) { animation-delay: 0.2s; }
.profesor-container > *:nth-child(4) { animation-delay: 0.3s; }

.materia-card {
    animation: fadeInScale 0.5s ease-out;
}

.materia-card:nth-child(1) { animation-delay: 0.2s; }
.materia-card:nth-child(2) { animation-delay: 0.3s; }
.materia-card:nth-child(3) { animation-delay: 0.4s; }
.materia-card:nth-child(4) { animation-delay: 0.5s; }
.materia-card:nth-child(5) { animation-delay: 0.6s; }
.materia-card:nth-child(6) { animation-delay: 0.7s; }

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
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .materias-grid {
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    }
}

@media (max-width: 768px) {
    .profesor-container {
        gap: 1.5rem;
    }
    
    .estadisticas-profesor, .noticias-profesor {
        padding: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .materias-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .materia-stats {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
    
    .acciones-materia {
        flex-direction: column;
    }
    
    .btn-small {
        text-align: center;
        justify-content: center;
    }
    
    .horario-item {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
    
    .horario-tiempo {
        text-align: left;
        margin: 0;
    }
    
    .noticia-meta {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 1.25rem;
    }
    
    .stat-icon {
        font-size: 2rem;
    }
    
    .stat-number {
        font-size: 1.75rem;
    }
    
    .materia-card {
        padding: 1.25rem;
    }
    
    .debug-info {
        padding: 1rem;
    }
    
    .horarios-materia {
        padding: 0.75rem;
    }
    
    .acciones-materia {
        gap: 0.75rem;
    }
}

/* Mejoras de accesibilidad */
.btn-small:focus,
.stat-card:focus {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Estados de carga */
.profesor-container.loading {
    opacity: 0.7;
    pointer-events: none;
}

.materia-card.loading {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.7; }
    50% { opacity: 1; }
}

/* Acciones Rápidas */
.acciones-rapidas {
    background: var(--white);
    padding: 2rem;
    border-radius: var(--border-radius);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--gray-200);
}

.acciones-rapidas h2 {
    color: var(--gray-800);
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 2px solid var(--gray-200);
    padding-bottom: 0.5rem;
}

.acciones-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.accion-card {
    background: linear-gradient(135deg, var(--white), #f8fafc);
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
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.accion-card:hover::before {
    transform: scaleY(1);
}

.accion-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    color: #2563eb;
    border-color: #2563eb;
}

.accion-icon {
    font-size: 2.5rem;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    padding: 0.75rem;
    border-radius: 50%;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 60px;
    min-height: 60px;
}

.accion-card:hover .accion-icon {
    background: linear-gradient(135deg, #2563eb, #3b82f6);
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

/* Print styles */
@media print {
    .btn-small,
    .acciones-materia,
    .acciones-rapidas {
        display: none !important;
    }
    
    .materia-card,
    .estadisticas-profesor,
    .noticias-profesor {
        box-shadow: none;
        border: 1px solid #ccc;
        break-inside: avoid;
    }
    
    .page-header {
        border-bottom: 2px solid #000;
        margin-bottom: 1rem;
    }
    
    .profesor-container {
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
                                        <a href="../profesor/asistenciasprof.php?materia=<?php echo $materia['id']; ?>" class="btn-small btn-primary-small">
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
                <div class="acciones-rapidas">
                    <h2>⚡ Acciones Rápidas</h2>
                    <div class="acciones-grid">
                        <a href="../profesor/notas.php" class="accion-card">
                            <div class="accion-icon">📊</div>
                            <div class="accion-texto">
                                <h4>Gestionar Notas</h4>
                                <p>Registrar y consultar calificaciones</p>
                            </div>
                        </a>
                        
                        <a href="../profesor/asistencias.php" class="accion-card">
                            <div class="accion-icon">✅</div>
                            <div class="accion-texto">
                                <h4>Tomar Asistencia</h4>
                                <p>Control de presentes y ausentes</p>
                            </div>
                        </a>
                        
                        <a href="../profesor/actividades.php" class="accion-card">
                            <div class="accion-icon">📝</div>
                            <div class="accion-texto">
                                <h4>Actividades</h4>
                                <p>Crear y gestionar tareas</p>
                            </div>
                        </a>
                        
                        <a href="../profesor/horarios.php" class="accion-card">
                            <div class="accion-icon">🕒</div>
                            <div class="accion-texto">
                                <h4>Ver Horarios</h4>
                                <p>Consultar cronograma semanal</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../js/main.js"></script>
</body>
</html>