<?php
require_once '../config.php';
verificarTipoUsuario(['alumno']);

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

// Obtener horarios
$stmt = $pdo->prepare("
    SELECT h.*, m.nombre as materia_nombre,
           u.nombre as profesor_nombre, u.apellido as profesor_apellido
    FROM horarios h
    JOIN materias m ON h.materia_id = m.id
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    WHERE m.año_id = ?
    ORDER BY h.dia_semana, h.hora_inicio
");
$stmt->execute([$alumno['año_id']]);
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar horarios por día
$horarios_por_dia = [
    'lunes' => [],
    'martes' => [],
    'miercoles' => [],
    'jueves' => [],
    'viernes' => []
];

foreach ($horarios as $horario) {
    $horarios_por_dia[$horario['dia_semana']][] = $horario;
}

// Ordenar horarios de cada día por hora
foreach ($horarios_por_dia as &$dia) {
    usort($dia, function($a, $b) {
        return strcmp($a['hora_inicio'], $b['hora_inicio']);
    });
}

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
    <link rel="stylesheet" href="../css/alumno.css">
</head>
        .alumno-info {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .curso-badge {
            background: var(--primary-color);
            color: var(--white);
            padding: 0.5rem 1.5rem;
            border-radius: 9999px;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.5rem;
        }
        
        .materias-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .materia-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .materia-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .materia-nombre {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
        }
        
        .profesor-info {
            color: var(--gray-600);
            margin-bottom: 1rem;
        }
        
        .horarios-materia {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
        }
        
        .horario-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .horario-item:last-child {
            border-bottom: none;
        }
        
        .horario-dia {
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .horario-tiempo {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .horario-aula {
            background: var(--primary-color);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .horario-semanal {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .horario-header {
            background: var(--primary-color);
            color: var(--white);
            padding: 1.5rem;
            text-align: center;
        }
        
        .horario-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0;
            min-height: 500px;
        }
        
        .dia-column {
            border-right: 1px solid var(--gray-200);
            padding: 1rem;
        }
        
        .dia-column:last-child {
            border-right: none;
        }
        
        .dia-header {
            font-weight: 600;
            text-align: center;
            padding: 0.75rem;
            background: var(--gray-100);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            color: var(--gray-800);
        }
        
        .clase-item {
            background: linear-gradient(45deg, var(--primary-color), #3b82f6);
            color: var(--white);
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: var(--border-radius);
            font-size: 0.875rem;
        }
        
        .clase-hora {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .clase-materia {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .clase-aula {
            opacity: 0.9;
            font-size: 0.75rem;
        }
        
        .tabs {
            display: flex;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .tab-button {
            flex: 1;
            padding: 1rem;
            background: var(--gray-100);
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .tab-button.active {
            background: var(--primary-color);
            color: var(--white);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .horario-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .dia-column {
                border-right: none;
                border-bottom: 1px solid var(--gray-200);
                padding: 1rem;
            }
            
            .dia-column:last-child {
                border-bottom: none;
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
                <h1>Mis Materias y Horarios</h1>
                <p>Información académica de <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></p>
            </div>
            
            <!-- Información del alumno -->
            <div class="alumno-info">
                <h2><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></h2>
                <div class="curso-badge">
                    <?php echo $alumno['año']; ?>° Año - <?php echo htmlspecialchars($alumno['orientacion_nombre']); ?>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-button active" onclick="cambiarTab('materias')">Mis Materias</button>
                <button class="tab-button" onclick="cambiarTab('horario-semanal')">Horario Semanal</button>
            </div>
            
            <!-- Tab Materias -->
            <div id="materias" class="tab-content active">
                <h2>Materias del Curso</h2>
                
                <?php if (!empty($materias)): ?>
                    <div class="materias-grid">
                        <?php foreach ($materias as $materia): ?>
                            <div class="materia-card">
                                <div class="materia-nombre">
                                    <?php echo htmlspecialchars($materia['nombre']); ?>
                                </div>
                                
                                <div class="profesor-info">
                                    <strong>Profesor:</strong> 
                                    <?php if ($materia['profesor_nombre']): ?>
                                        <?php echo htmlspecialchars($materia['profesor_nombre'] . ' ' . $materia['profesor_apellido']); ?>
                                    <?php else: ?>
                                        Por asignar
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Horarios de esta materia -->
                                <?php
                                $horarios_materia = array_filter($horarios, function($h) use ($materia) {
                                    return $h['materia_id'] == $materia['id'];
                                });
                                ?>
                                
                                <?php if (!empty($horarios_materia)): ?>
                                    <div class="horarios-materia">
                                        <h4>Horarios:</h4>
                                        <?php foreach ($horarios_materia as $horario): ?>
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
                                    <div style="background: var(--gray-100); padding: 1rem; border-radius: var(--border-radius); text-align: center; color: var(--gray-600);">
                                        Horarios no asignados
                                    </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 1rem; text-align: center;">
                                    <a href="../alumno/notas.php?materia=<?php echo $materia['id']; ?>" class="btn-primary" style="margin-right: 0.5rem;">Ver Notas</a>
                                    <a href="../alumno/actividades.php?materia=<?php echo $materia['id']; ?>" class="btn-secondary">Actividades</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; background: var(--white); border-radius: var(--border-radius);">
                        <h3>No hay materias asignadas</h3>
                        <p>Contacte al administrador para verificar su asignación de materias.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Horario Semanal -->
            <div id="horario-semanal" class="tab-content">
                <div class="horario-semanal">
                    <div class="horario-header">
                        <h2>Horario Semanal</h2>
                        <p><?php echo $alumno['año']; ?>° Año - <?php echo htmlspecialchars($alumno['orientacion_nombre']); ?></p>
                    </div>
                    
                    <div class="horario-grid">
                        <?php foreach ($dias_semana as $dia_clave => $dia_nombre): ?>
                            <div class="dia-column">
                                <div class="dia-header"><?php echo $dia_nombre; ?></div>
                                
                                <?php if (!empty($horarios_por_dia[$dia_clave])): ?>
                                    <?php foreach ($horarios_por_dia[$dia_clave] as $horario): ?>
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
                                                <div class="clase-aula">
                                                    👨‍🏫 <?php echo htmlspecialchars($horario['profesor_nombre'] . ' ' . $horario['profesor_apellido']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="text-align: center; color: var(--gray-500); padding: 2rem 0; font-style: italic;">
                                        Sin clases
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Información adicional -->
                <div style="margin-top: 2rem; background: var(--white); padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--shadow);">
                    <h3>Información del Horario</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <div>
                            <strong>Total de materias:</strong> <?php echo count($materias); ?>
                        </div>
                        <div>
                            <strong>Horas semanales:</strong> 
                            <?php
                            $total_horas = 0;
                            foreach ($horarios as $horario) {
                                $inicio = new DateTime($horario['hora_inicio']);
                                $fin = new DateTime($horario['hora_fin']);
                                $diferencia = $inicio->diff($fin);
                                $total_horas += $diferencia->h + ($diferencia->i / 60);
                            }
                            echo number_format($total_horas, 1) . ' horas';
                            ?>
                        </div>
                        <div>
                            <strong>Año académico:</strong> <?php echo $alumno['año']; ?>° Año
                        </div>
                        <div>
                            <strong>Orientación:</strong> <?php echo htmlspecialchars($alumno['orientacion_nombre']); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Botones de acción -->
                <div style="margin-top: 2rem; text-align: center;">
                    <button onclick="imprimirHorario()" class="btn-primary">Imprimir Horario</button>
                    <button onclick="exportarHorario()" class="btn-secondary">Exportar como PDF</button>
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
        
        function imprimirHorario() {
            // Abrir ventana de impresión solo del horario
            const horarioContent = document.getElementById('horario-semanal').innerHTML;
            const ventanaImpresion = window.open('', '', 'width=800,height=600');
            
            ventanaImpresion.document.write(`
                <html>
                <head>
                    <title>Horario Semanal</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .horario-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
                        .dia-column { border: 1px solid #ccc; padding: 10px; }
                        .dia-header { font-weight: bold; text-align: center; background: #f0f0f0; padding: 5px; margin-bottom: 10px; }
                        .clase-item { background: #e3f2fd; padding: 8px; margin: 5px 0; border-radius: 4px; }
                        .clase-hora { font-weight: bold; }
                        @media print {
                            body { margin: 0; }
                            .horario-grid { font-size: 12px; }
                        }
                    </style>
                </head>
                <body>
                    <h2>Horario Semanal - <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></h2>
                    <p><?php echo $alumno['año']; ?>° Año - <?php echo htmlspecialchars($alumno['orientacion_nombre']); ?></p>
                    ${horarioContent}
                </body>
                </html>
            `);
            
            ventanaImpresion.document.close();
            ventanaImpresion.focus();
            ventanaImpresion.print();
        }
        
        function exportarHorario() {
            // Simulación de exportación a PDF (en implementación real usarías una librería como jsPDF)
            mostrarToast('Función de exportar PDF en desarrollo', 'info');
        }
        
        // Agregar efecto hover a las materias
        document.addEventListener('DOMContentLoaded', function() {
            const materiaCards = document.querySelectorAll('.materia-card');
            materiaCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>