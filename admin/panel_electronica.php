<?php
require_once '../config.php';
verificarTipoUsuario(['administrador']);

// Obtener información de la orientación Electrónica
$stmt = $pdo->prepare("SELECT id FROM orientaciones WHERE nombre LIKE '%electrónica%' OR nombre LIKE '%electronica%'");
$stmt->execute();
$orientacion_elect = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orientacion_elect) {
    // Si no existe, usar la tercera orientación como fallback
    $stmt = $pdo->query("SELECT id FROM orientaciones ORDER BY id LIMIT 2,1");
    $orientacion_elect = $stmt->fetch(PDO::FETCH_ASSOC);
}

$orientacion_id = $orientacion_elect['id'];

// Obtener años de Electrónica (4to, 5to, 6to, 7mo)
$stmt = $pdo->prepare("
    SELECT an.*, o.nombre as orientacion_nombre 
    FROM años an 
    JOIN orientaciones o ON an.orientacion_id = o.id 
    WHERE an.orientacion_id = ? AND an.año >= 4
    ORDER BY an.año
");
$stmt->execute([$orientacion_id]);
$años_elect = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas generales de Electrónica
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
foreach ($años_elect as $año) {
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

// Obtener horarios de Electrónica
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
$horarios_elect = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar horarios por día y año
$horarios_organizados = [];
foreach ($horarios_elect as $horario) {
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
foreach ($años_elect as $año) {
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

// Noticias dirigidas a Electrónica
$stmt = $pdo->prepare("
    SELECT n.*, u.nombre, u.apellido
    FROM noticias n
    JOIN usuarios u ON n.autor_id = u.id
    WHERE (n.dirigido_a = 'todos' OR n.dirigido_a = 'electronica') AND n.activo = 1
    ORDER BY n.fecha_publicacion DESC
    LIMIT 5
");
$stmt->execute();
$noticias_elect = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dias_semana = [
    'lunes' => 'Lunes',
    'martes' => 'Martes',
    'miercoles' => 'Miércoles',
    'jueves' => 'Jueves',
    'viernes' => 'Viernes'
];

// Información de la carrera de Electrónica hasta 7° año
$años_electronica = [
    4 => ['nombre' => '4° Año', 'descripcion' => 'Fundamentos de Electrónica'],
    5 => ['nombre' => '5° Año', 'descripcion' => 'Electrónica Analógica'],
    6 => ['nombre' => '6° Año', 'descripcion' => 'Electrónica Digital'],
    7 => ['nombre' => '7° Año', 'descripcion' => 'Especialización y Proyectos Finales']
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Electrónica - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/panel_programacion.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>⚡ Panel Electrónica</h1>
                <p>Gestión integral de la orientación Electrónica - 4°, 5°, 6° y 7° Año</p>
            </div>
            
            <div class="programacion-container">
                <!-- Estadísticas Generales -->
                <div class="estadisticas-prog">
                    <h2>📊 Estadísticas de Electrónica</h2>
                    <div class="stats-grid">
                        <div class="stat-card stat-alumnos">
                            <div class="stat-icon">👨‍🔧</div>
                            <div class="stat-info">
                                <h3><?php echo $stats_alumnos['total_alumnos'] ?? 0; ?></h3>
                                <p>Estudiantes de Electrónica</p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-materias">
                            <div class="stat-icon">⚡</div>
                            <div class="stat-info">
                                <h3><?php echo $stats_materias['total_materias'] ?? 0; ?></h3>
                                <p>Materias Técnicas</p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-profesores">
                            <div class="stat-icon">👨‍🏫</div>
                            <div class="stat-info">
                                <h3><?php echo $stats_profesores['total_profesores'] ?? 0; ?></h3>
                                <p>Profesores Especializados</p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-años">
                            <div class="stat-icon">🎯</div>
                            <div class="stat-info">
                                <h3><?php echo count($años_elect); ?></h3>
                                <p>Años Académicos (4° a 7°)</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Información de la Carrera -->
                <div class="info-carrera">
                    <h2>🔌 Carrera de Electrónica</h2>
                    <div class="carrera-grid">
                        <?php foreach ($años_electronica as $num_año => $info): ?>
                            <div class="año-info-card">
                                <div class="año-numero"><?php echo $info['nombre']; ?></div>
                                <div class="año-descripcion"><?php echo $info['descripcion']; ?></div>
                                <div class="año-materias">
                                    <?php 
                                    $materias_count = count($materias_por_año[$num_año] ?? []);
                                    echo $materias_count . ' materia' . ($materias_count != 1 ? 's' : '');
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="tabs-prog">
                    <button class="tab-button active" onclick="cambiarTab('materias-horarios')">⚡ Materias y Horarios</button>
                    <button class="tab-button" onclick="cambiarTab('estudiantes')">👨‍🔧 Estudiantes</button>
                    <button class="tab-button" onclick="cambiarTab('horarios-completos')">📅 Horarios Completos</button>
                    <button class="tab-button" onclick="cambiarTab('tecnologias')">🔧 Tecnologías</button>
                    <button class="tab-button" onclick="cambiarTab('noticias')">📢 Noticias</button>
                </div>
                
                <!-- Tab Materias y Horarios -->
                <div id="materias-horarios" class="tab-content active">
                    <h2>⚡ Materias por Año</h2>
                    
                    <?php if (!empty($años_elect)): ?>
                        <?php foreach ($años_elect as $año): ?>
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
                                                $horarios_materia = array_filter($horarios_elect, function($h) use ($materia) {
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
                            <h3>⚠️ No se encontraron años de Electrónica</h3>
                            <p>Por favor, configure los años académicos en la gestión académica.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tab Estudiantes -->
                <div id="estudiantes" class="tab-content">
                    <h2>👨‍🔧 Estudiantes de Electrónica</h2>
                    
                    <?php foreach ($años_elect as $año): ?>
                        <div class="año-estudiantes-section">
                            <div class="año-header">
                                <h3><?php echo $año['año']; ?>° Año</h3>
                                <span class="badge-año"><?php echo count($alumnos_por_año[$año['año']] ?? []); ?> estudiantes</span>
                            </div>
                            
                            <?php if (!empty($alumnos_por_año[$año['año']])): ?>
                                <div class="estudiantes-grid">
                                    <?php foreach ($alumnos_por_año[$año['año']] as $alumno): ?>
                                        <div class="estudiante-card">
                                            <div class="estudiante-nombre">
                                                <?php echo htmlspecialchars($alumno['apellido'] . ', ' . $alumno['nombre']); ?>
                                            </div>
                                            <div class="estudiante-usuario">@<?php echo htmlspecialchars($alumno['usuario']); ?></div>
                                            <?php if ($alumno['dni']): ?>
                                                <div class="estudiante-dni">DNI: <?php echo htmlspecialchars($alumno['dni']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($alumno['telefono']): ?>
                                                <div class="estudiante-telefono">📞 <?php echo htmlspecialchars($alumno['telefono']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="sin-estudiantes">
                                    <p>No hay estudiantes registrados en este año.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Tab Horarios Completos -->
                <div id="horarios-completos" class="tab-content">
                    <h2>📅 Horarios Semanales de Electrónica</h2>
                    
                    <?php foreach ($años_elect as $año): ?>
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
                
                <!-- Tab Tecnologías -->
                <div id="tecnologias" class="tab-content">
                    <h2>🔧 Tecnologías y Herramientas</h2>
                    
                    <div class="tecnologias-grid">
                        <div class="tech-category">
                            <h3>4° Año - Fundamentos</h3>
                            <div class="tech-items">
                                <div class="tech-item">Circuitos Básicos</div>
                                <div class="tech-item">Ley de Ohm</div>
                                <div class="tech-item">Multímetros</div>
                                <div class="tech-item">Soldadura</div>
                            </div>
                        </div>
                        
                        <div class="tech-category">
                            <h3>5° Año - Analógica</h3>
                            <div class="tech-items">
                                <div class="tech-item">Amplificadores</div>
                                <div class="tech-item">Filtros</div>
                                <div class="tech-item">Osciladores</div>
                                <div class="tech-item">Transistores</div>
                            </div>
                        </div>
                        
                        <div class="tech-category">
                            <h3>6° Año - Digital</h3>
                            <div class="tech-items">
                                <div class="tech-item">Compuertas Lógicas</div>
                                <div class="tech-item">Contadores</div>
                                <div class="tech-item">Memorias</div>
                                <div class="tech-item">PLC</div>
                            </div>
                        </div>
                        
                        <div class="tech-category">
                            <h3>7° Año - Especialización</h3>
                            <div class="tech-items">
                                <div class="tech-item">Microcontroladores</div>
                                <div class="tech-item">Arduino & ESP32</div>
                                <div class="tech-item">Automatización Industrial</div>
                                <div class="tech-item">IoT</div>
                                <div class="tech-item">Proyecto Final</div>
                                <div class="tech-item">Práctica Profesionalizante</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Noticias -->
                <div id="noticias" class="tab-content">
                    <h2>📢 Noticias de Electrónica</h2>
                    
                    <?php if (!empty($noticias_elect)): ?>
                        <div class="noticias-prog-grid">
                            <?php foreach ($noticias_elect as $noticia): ?>
                                <div class="noticia-prog-card">
                                    <div class="noticia-header">
                                        <h4><?php echo htmlspecialchars($noticia['titulo']); ?></h4>
                                        <span class="noticia-dirigido badge-<?php echo $noticia['dirigido_a']; ?>">
                                            <?php 
                                            $dirigido_labels = [
                                                'todos' => 'General',
                                                'electronica' => 'Electrónica'
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
                        <div class="sin-noticias-prog">
                            <h3>📭 No hay noticias</h3>
                            <p>No hay noticias dirigidas a Electrónica en este momento.</p>
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
                                <h4>Gestionar Estudiantes</h4>
                                <p>Administrar estudiantes de electrónica</p>
                            </div>
                        </a>
                        
                        <a href="../admin/academico.php" class="accion-card">
                            <div class="accion-icon">⚡</div>
                            <div class="accion-texto">
                                <h4>Materias Técnicas</h4>
                                <p>Configurar materias de electrónica</p>
                            </div>
                        </a>
                        
                        <a href="../admin/asistencias.php" class="accion-card">
                            <div class="accion-icon">📊</div>
                            <div class="accion-texto">
                                <h4>Seguimiento Académico</h4>
                                <p>Ver progreso de estudiantes</p>
                            </div>
                        </a>
                        
                        <a href="../noticias.php" class="accion-card">
                            <div class="accion-icon">📢</div>
                            <div class="accion-texto">
                                <h4>Comunicar</h4>
                                <p>Publicar noticias técnicas</p>
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
            const cards = document.querySelectorAll('.materia-card, .estudiante-card, .accion-card, .tech-item');
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
