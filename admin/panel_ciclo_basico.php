<?php
require_once '../config.php';
verificarTipoUsuario(['administrador']);

// Obtener información del Ciclo Básico
$stmt = $pdo->prepare("SELECT id FROM orientaciones WHERE nombre LIKE '%básico%' OR nombre LIKE '%basico%'");
$stmt->execute();
$orientacion_cb = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orientacion_cb) {
    // Si no existe, usar la primera orientación como fallback
    $stmt = $pdo->query("SELECT id FROM orientaciones ORDER BY id LIMIT 1");
    $orientacion_cb = $stmt->fetch(PDO::FETCH_ASSOC);
}

$orientacion_id = $orientacion_cb['id'];

// Obtener años del Ciclo Básico
$stmt = $pdo->prepare("
    SELECT an.*, o.nombre as orientacion_nombre 
    FROM años an 
    JOIN orientaciones o ON an.orientacion_id = o.id 
    WHERE an.orientacion_id = ?
    ORDER BY an.año
");
$stmt->execute([$orientacion_id]);
$años_cb = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas generales del Ciclo Básico
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT al.id) as total_alumnos
    FROM alumnos al
    JOIN años an ON al.año_id = an.id
    JOIN usuarios u ON al.usuario_id = u.id
    WHERE an.orientacion_id = ? AND u.activo = 1
");
$stmt->execute([$orientacion_id]);
$stats_alumnos = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT m.id) as total_materias
    FROM materias m
    JOIN años an ON m.año_id = an.id
    WHERE an.orientacion_id = ?
");
$stmt->execute([$orientacion_id]);
$stats_materias = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT m.profesor_id) as total_profesores
    FROM materias m
    JOIN años an ON m.año_id = an.id
    WHERE an.orientacion_id = ? AND m.profesor_id IS NOT NULL
");
$stmt->execute([$orientacion_id]);
$stats_profesores = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener materias por año
$materias_por_año = [];
foreach ($años_cb as $año) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.nombre as profesor_nombre, u.apellido as profesor_apellido
        FROM materias m
        LEFT JOIN usuarios u ON m.profesor_id = u.id
        WHERE m.año_id = ?
        ORDER BY m.nombre
    ");
    $stmt->execute([$año['id']]);
    $materias_por_año[$año['año']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener horarios del Ciclo Básico
$stmt = $pdo->prepare("
    SELECT h.*, m.nombre as materia_nombre, an.año,
           u.nombre as profesor_nombre, u.apellido as profesor_apellido
    FROM horarios h
    JOIN materias m ON h.materia_id = m.id
    JOIN años an ON m.año_id = an.id
    JOIN orientaciones o ON an.orientacion_id = o.id
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    WHERE an.orientacion_id = ?
    ORDER BY an.año, h.dia_semana, h.hora_inicio
");
$stmt->execute([$orientacion_id]);
$horarios_cb = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar horarios por día y año
$horarios_organizados = [];
foreach ($horarios_cb as $horario) {
    $año = $horario['año'];
    $dia = $horario['dia_semana'];
    if (!isset($horarios_organizados[$año])) {
        $horarios_organizados[$año] = [
            'lunes' => [], 'martes' => [], 'miercoles' => [], 'jueves' => [], 'viernes' => []
        ];
    }
    $horarios_organizados[$año][$dia][] = $horario;
}

// Obtener alumnos por año
$alumnos_por_año = [];
foreach ($años_cb as $año) {
    $stmt = $pdo->prepare("
        SELECT u.nombre, u.apellido, u.usuario, al.dni, al.telefono
        FROM alumnos al
        JOIN usuarios u ON al.usuario_id = u.id
        WHERE al.año_id = ? AND u.activo = 1
        ORDER BY u.apellido, u.nombre
    ");
    $stmt->execute([$año['id']]);
    $alumnos_por_año[$año['año']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Noticias dirigidas al Ciclo Básico
$stmt = $pdo->prepare("
    SELECT n.*, u.nombre, u.apellido
    FROM noticias n
    JOIN usuarios u ON n.autor_id = u.id
    WHERE (n.dirigido_a = 'todos' OR n.dirigido_a = 'ciclo_basico') AND n.activo = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT 5
");
$stmt->execute();
$noticias_cb = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Panel Ciclo Básico - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/panel_ciclo_basico.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>🎓 Panel Ciclo Básico</h1>
                <p>Gestión integral del Ciclo Básico - Años 1°, 2° y 3°</p>
            </div>
            
            <div class="ciclo-basico-container">
                <!-- Estadísticas Generales -->
                <div class="estadisticas-cb">
                    <h2>📊 Estadísticas Generales</h2>
                    <div class="stats-grid">
                        <div class="stat-card stat-alumnos">
                            <div class="stat-icon">👨‍🎓</div>
                            <div class="stat-info">
                                <h3><?php echo $stats_alumnos['total_alumnos'] ?? 0; ?></h3>
                                <p>Alumnos Activos</p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-materias">
                            <div class="stat-icon">📚</div>
                            <div class="stat-info">
                                <h3><?php echo $stats_materias['total_materias'] ?? 0; ?></h3>
                                <p>Materias</p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-profesores">
                            <div class="stat-icon">👨‍🏫</div>
                            <div class="stat-info">
                                <h3><?php echo $stats_profesores['total_profesores'] ?? 0; ?></h3>
                                <p>Profesores</p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-años">
                            <div class="stat-icon">📅</div>
                            <div class="stat-info">
                                <h3><?php echo count($años_cb); ?></h3>
                                <p>Años Académicos</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="tabs-cb">
                    <button class="tab-button active" onclick="cambiarTab('materias-horarios')">📚 Materias y Horarios</button>
                    <button class="tab-button" onclick="cambiarTab('alumnos')">👨‍🎓 Alumnos</button>
                    <button class="tab-button" onclick="cambiarTab('horarios-completos')">🕒 Horarios Completos</button>
                    <button class="tab-button" onclick="cambiarTab('noticias')">📢 Noticias</button>
                </div>
                
                <!-- Tab Materias y Horarios -->
                <div id="materias-horarios" class="tab-content active">
                    <h2>📚 Materias por Año</h2>
                    
                    <?php if (!empty($años_cb)): ?>
                        <?php foreach ($años_cb as $año): ?>
                            <div class="año-section">
                                <div class="año-header">
                                    <h3><?php echo $año['año']; ?>° Año - <?php echo htmlspecialchars($año['orientacion_nombre']); ?></h3>
                                    <span class="badge-año"><?php echo count($materias_por_año[$año['año']] ?? []); ?> materias</span>
                                </div>
                                
                                <?php if (!empty($materias_por_año[$año['año']])): ?>
                                    <div class="materias-grid">
                                        <?php foreach ($materias_por_año[$año['año']] as $materia): ?>
                                            <div class="materia-card">
                                                <div class="materia-nombre">
                                                    <?php echo htmlspecialchars($materia['nombre']); ?>
                                                </div>
                                                
                                                <div class="profesor-info">
                                                    <strong>Profesor:</strong>
                                                    <?php if ($materia['profesor_nombre']): ?>
                                                        <?php echo htmlspecialchars($materia['profesor_nombre'] . ' ' . $materia['profesor_apellido']); ?>
                                                    <?php else: ?>
                                                        <span class="sin-asignar">Sin asignar</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Horarios de la materia -->
                                                <?php
                                                $horarios_materia = array_filter($horarios_cb, function($h) use ($materia) {
                                                    return $h['materia_id'] == $materia['id'];
                                                });
                                                ?>
                                                
                                                <?php if (!empty($horarios_materia)): ?>
                                                    <div class="horarios-materia">
                                                        <strong>Horarios:</strong>
                                                        <?php foreach ($horarios_materia as $horario): ?>
                                                            <div class="horario-item">
                                                                <span class="horario-dia"><?php echo ucfirst($horario['dia_semana']); ?></span>
                                                                <span class="horario-tiempo">
                                                                    <?php echo formatearHora($horario['hora_inicio']) . ' - ' . formatearHora($horario['hora_fin']); ?>
                                                                </span>
                                                                <?php if ($horario['aula']): ?>
                                                                    <span class="horario-aula"><?php echo htmlspecialchars($horario['aula']); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="sin-horarios">Sin horarios asignados</div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="sin-materias">
                                        <p>No hay materias asignadas para este año.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="sin-años">
                            <h3>⚠️ No se encontraron años del Ciclo Básico</h3>
                            <p>Por favor, configure los años académicos en la gestión académica.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tab Alumnos -->
                <div id="alumnos" class="tab-content">
                    <h2>👨‍🎓 Alumnos por Año</h2>
                    
                    <?php foreach ($años_cb as $año): ?>
                        <div class="año-alumnos-section">
                            <div class="año-header">
                                <h3><?php echo $año['año']; ?>° Año</h3>
                                <span class="badge-año"><?php echo count($alumnos_por_año[$año['año']] ?? []); ?> alumnos</span>
                            </div>
                            
                            <?php if (!empty($alumnos_por_año[$año['año']])): ?>
                                <div class="alumnos-grid">
                                    <?php foreach ($alumnos_por_año[$año['año']] as $alumno): ?>
                                        <div class="alumno-card">
                                            <div class="alumno-nombre">
                                                <?php echo htmlspecialchars($alumno['apellido'] . ', ' . $alumno['nombre']); ?>
                                            </div>
                                            <div class="alumno-usuario">@<?php echo htmlspecialchars($alumno['usuario']); ?></div>
                                            <?php if ($alumno['dni']): ?>
                                                <div class="alumno-dni">DNI: <?php echo htmlspecialchars($alumno['dni']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($alumno['telefono']): ?>
                                                <div class="alumno-telefono">📞 <?php echo htmlspecialchars($alumno['telefono']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="sin-alumnos">
                                    <p>No hay alumnos registrados en este año.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Tab Horarios Completos -->
                <div id="horarios-completos" class="tab-content">
                    <h2>🕒 Horarios Semanales</h2>
                    
                    <?php foreach ($años_cb as $año): ?>
                        <div class="horario-año-section">
                            <h3><?php echo $año['año']; ?>° Año - Horario Semanal</h3>
                            
                            <?php if (isset($horarios_organizados[$año['año']])): ?>
                                <div class="horario-semanal">
                                    <div class="horario-grid">
                                        <?php foreach ($dias_semana as $dia_clave => $dia_nombre): ?>
                                            <div class="dia-column">
                                                <div class="dia-header"><?php echo $dia_nombre; ?></div>
                                                
                                                <?php if (!empty($horarios_organizados[$año['año']][$dia_clave])): ?>
                                                    <?php foreach ($horarios_organizados[$año['año']][$dia_clave] as $horario): ?>
                                                        <div class="clase-item">
                                                            <div class="clase-hora">
                                                                <?php echo formatearHora($horario['hora_inicio']) . ' - ' . formatearHora($horario['hora_fin']); ?>
                                                            </div>
                                                            <div class="clase-materia">
                                                                <?php echo htmlspecialchars($horario['materia_nombre']); ?>
                                                            </div>
                                                            <?php if ($horario['aula']): ?>
                                                                <div class="clase-aula">
                                                                    📍 <?php echo htmlspecialchars($horario['aula']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if ($horario['profesor_nombre']): ?>
                                                                <div class="clase-profesor">
                                                                    👨‍🏫 <?php echo htmlspecialchars($horario['profesor_nombre'] . ' ' . $horario['profesor_apellido']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="sin-clases">Sin clases</div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="sin-horarios-año">
                                    <p>No hay horarios asignados para este año.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Tab Noticias -->
                <div id="noticias" class="tab-content">
                    <h2>📢 Noticias del Ciclo Básico</h2>
                    
                    <?php if (!empty($noticias_cb)): ?>
                        <div class="noticias-cb-grid">
                            <?php foreach ($noticias_cb as $noticia): ?>
                                <div class="noticia-cb-card">
                                    <div class="noticia-header">
                                        <h4><?php echo htmlspecialchars($noticia['titulo']); ?></h4>
                                        <span class="noticia-dirigido badge-<?php echo $noticia['dirigido_a']; ?>">
                                            <?php 
                                            $dirigido_labels = [
                                                'todos' => 'General',
                                                'ciclo_basico' => 'Ciclo Básico'
                                            ];
                                            echo $dirigido_labels[$noticia['dirigido_a']] ?? ucfirst($noticia['dirigido_a']);
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <div class="noticia-contenido">
                                        <?php echo nl2br(htmlspecialchars(substr($noticia['contenido'], 0, 200))); ?>
                                        <?php if (strlen($noticia['contenido']) > 200): ?>...<?php endif; ?>
                                    </div>
                                    
                                    <div class="noticia-meta">
                                        <span class="noticia-autor">
                                            👤 <?php echo htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellido']); ?>
                                        </span>
                                        <span class="noticia-fecha">
                                            📅 <?php echo formatearFecha($noticia['fecha_publicacion']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="sin-noticias-cb">
                            <h3>📭 No hay noticias</h3>
                            <p>No hay noticias dirigidas al Ciclo Básico en este momento.</p>
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
                                <h4>Gestionar Usuarios</h4>
                                <p>Administrar alumnos y profesores</p>
                            </div>
                        </a>
                        
                        <a href="../admin/academico.php" class="accion-card">
                            <div class="accion-icon">📚</div>
                            <div class="accion-texto">
                                <h4>Gestión Académica</h4>
                                <p>Materias y horarios</p>
                            </div>
                        </a>
                        
                        <a href="../admin/asistencias.php" class="accion-card">
                            <div class="accion-icon">📋</div>
                            <div class="accion-texto">
                                <h4>Ver Asistencias</h4>
                                <p>Control de asistencias</p>
                            </div>
                        </a>
                        
                        <a href="../noticias.php" class="accion-card">
                            <div class="accion-icon">📢</div>
                            <div class="accion-texto">
                                <h4>Publicar Noticia</h4>
                                <p>Comunicar al Ciclo Básico</p>
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
        
        // Efectos hover para las cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.materia-card, .alumno-card, .accion-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>