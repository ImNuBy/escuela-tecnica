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

// Obtener horarios completos
$stmt = $pdo->prepare("
    SELECT h.*, 
           m.nombre as materia_nombre,
           u.nombre as profesor_nombre, 
           u.apellido as profesor_apellido
    FROM horarios h
    JOIN materias m ON h.materia_id = m.id
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    WHERE m.año_id = ?
    ORDER BY 
        CASE h.dia_semana 
            WHEN 'lunes' THEN 1
            WHEN 'martes' THEN 2
            WHEN 'miercoles' THEN 3
            WHEN 'jueves' THEN 4
            WHEN 'viernes' THEN 5
        END,
        h.hora_inicio
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

// Función para obtener el siguiente horario
function obtenerSiguienteClase($horarios_por_dia) {
    $dias_semana = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];
    $dia_actual = date('w'); // 0=domingo, 1=lunes, etc.
    $hora_actual = date('H:i:s');
    
    // Convertir día de la semana
    $dia_actual_nombre = '';
    switch($dia_actual) {
        case 1: $dia_actual_nombre = 'lunes'; break;
        case 2: $dia_actual_nombre = 'martes'; break;
        case 3: $dia_actual_nombre = 'miercoles'; break;
        case 4: $dia_actual_nombre = 'jueves'; break;
        case 5: $dia_actual_nombre = 'viernes'; break;
        default: $dia_actual_nombre = 'lunes'; // Si es fin de semana, buscar desde lunes
    }
    
    // Buscar en el día actual
    if (isset($horarios_por_dia[$dia_actual_nombre])) {
        foreach ($horarios_por_dia[$dia_actual_nombre] as $horario) {
            if ($horario['hora_inicio'] > $hora_actual) {
                return $horario;
            }
        }
    }
    
    // Buscar en los días siguientes
    $dias_restantes = array_slice($dias_semana, array_search($dia_actual_nombre, $dias_semana) + 1);
    foreach ($dias_restantes as $dia) {
        if (!empty($horarios_por_dia[$dia])) {
            return $horarios_por_dia[$dia][0];
        }
    }
    
    // Si no hay más clases esta semana, buscar la primera clase de la siguiente semana
    foreach ($dias_semana as $dia) {
        if (!empty($horarios_por_dia[$dia])) {
            return $horarios_por_dia[$dia][0];
        }
    }
    
    return null;
}

$siguiente_clase = obtenerSiguienteClase($horarios_por_dia);

// Obtener estadísticas del horario
$total_horas_semanales = 0;
$total_materias = count($materias);
$aulas_utilizadas = [];
$profesores_involucrados = [];

foreach ($horarios as $horario) {
    $inicio = new DateTime($horario['hora_inicio']);
    $fin = new DateTime($horario['hora_fin']);
    $diferencia = $inicio->diff($fin);
    $total_horas_semanales += $diferencia->h + ($diferencia->i / 60);
    
    if ($horario['aula'] && !in_array($horario['aula'], $aulas_utilizadas)) {
        $aulas_utilizadas[] = $horario['aula'];
    }
    
    if ($horario['profesor_nombre'] && !in_array($horario['profesor_nombre'], $profesores_involucrados)) {
        $profesores_involucrados[] = $horario['profesor_nombre'] . ' ' . $horario['profesor_apellido'];
    }
}

$dias_semana = [
    'lunes' => 'Lunes',
    'martes' => 'Martes', 
    'miercoles' => 'Miércoles',
    'jueves' => 'Jueves',
    'viernes' => 'Viernes'
];

// Generar colores para las materias
$colores_materias = [
    '#2563eb', '#dc2626', '#059669', '#d97706', '#7c3aed',
    '#db2777', '#0891b2', '#65a30d', '#c2410c', '#4338ca'
];

$colores_por_materia = [];
$i = 0;
foreach ($materias as $materia) {
    $colores_por_materia[$materia['id']] = $colores_materias[$i % count($colores_materias)];
    $i++;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Horario - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/alumno.css">
    <style>
        :root {
            --white: #ffffff;
            --primary-color: #2563eb;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
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
        
        .siguiente-clase {
            background: linear-gradient(45deg, var(--primary-color), #3b82f6);
            color: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .siguiente-clase h3 {
            margin: 0 0 1rem 0;
            font-size: 1.25rem;
        }
        
        .clase-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .clase-detalle {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
        }
        
        .clase-detalle strong {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .clase-valor {
            font-size: 1.1rem;
            font-weight: bold;
        }
        
        .estadisticas-horario {
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
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .estadistica-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .estadistica-valor {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
            color: var(--primary-color);
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
            min-height: 600px;
        }
        
        .dia-column {
            border-right: 1px solid var(--gray-200);
            position: relative;
        }
        
        .dia-column:last-child {
            border-right: none;
        }
        
        .dia-header {
            font-weight: 600;
            text-align: center;
            padding: 1rem;
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-800);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .dia-contenido {
            padding: 1rem;
            min-height: 500px;
        }
        
        .clase-bloque {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: var(--border-radius);
            color: var(--white);
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        
        .clase-bloque:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .clase-hora {
            font-weight: bold;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .clase-materia {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        
        .clase-profesor, .clase-aula {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-bottom: 0.25rem;
        }
        
        .horario-tabla {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .tabla-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        th {
            background: var(--primary-color);
            color: var(--white);
            font-weight: 600;
            text-align: center;
        }
        
        .horario-celda {
            text-align: center;
            vertical-align: middle;
            position: relative;
        }
        
        .clase-minibloque {
            background: var(--primary-color);
            color: var(--white);
            padding: 0.5rem;
            border-radius: 4px;
            margin: 0.25rem;
            font-size: 0.75rem;
            line-height: 1.2;
        }
        
        .materias-lista {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .materia-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .materia-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .materia-header {
            padding: 1.5rem;
            color: var(--white);
            position: relative;
        }
        
        .materia-nombre {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
        }
        
        .materia-profesor {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .materia-contenido {
            padding: 1.5rem;
        }
        
        .horarios-materia {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .horario-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .horario-item:last-child {
            border-bottom: none;
        }
        
        .horario-dia-tiempo {
            display: flex;
            flex-direction: column;
        }
        
        .horario-dia {
            font-weight: 500;
            color: var(--gray-800);
        }
        
        .horario-tiempo {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .horario-aula {
            background: var(--gray-100);
            color: var(--gray-800);
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .sin-horarios {
            text-align: center;
            padding: 3rem;
            color: var(--gray-600);
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
            padding: 0.75rem 1.5rem;
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
            padding: 0.75rem 1.5rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
            margin-left: 0.5rem;
        }
        
        .btn-secondary:hover {
            background: var(--gray-200);
        }
        
        .acciones {
            text-align: center;
            margin-top: 2rem;
            padding: 2rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .reloj-tiempo-real {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .reloj {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .fecha-actual {
            color: var(--gray-600);
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .horario-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .dia-column {
                border-right: none;
                border-bottom: 1px solid var(--gray-200);
            }
            
            .dia-column:last-child {
                border-bottom: none;
            }
            
            .clase-info {
                grid-template-columns: 1fr;
            }
            
            .estadisticas-horario {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tabla-wrapper {
                font-size: 0.875rem;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
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
                <h1>Mi Horario de Clases</h1>
                <p>Organización semanal de <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></p>
            </div>
            
            <!-- Información del alumno -->
            <div class="alumno-info">
                <h2><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></h2>
                <div class="curso-badge">
                    <?php echo $alumno['año']; ?>° Año - <?php echo htmlspecialchars($alumno['orientacion_nombre']); ?>
                </div>
            </div>
            
            <!-- Reloj y fecha actual -->
            <div class="reloj-tiempo-real">
                <div class="reloj" id="reloj-actual"></div>
                <div class="fecha-actual" id="fecha-actual"></div>
            </div>
            
            <!-- Siguiente clase -->
            <?php if ($siguiente_clase): ?>
                <div class="siguiente-clase">
                    <h3>Próxima Clase</h3>
                    <div class="clase-info">
                        <div class="clase-detalle">
                            <strong>Materia</strong>
                            <div class="clase-valor"><?php echo htmlspecialchars($siguiente_clase['materia_nombre']); ?></div>
                        </div>
                        <div class="clase-detalle">
                            <strong>Horario</strong>
                            <div class="clase-valor">
                                <?php echo formatearHora($siguiente_clase['hora_inicio']) . ' - ' . formatearHora($siguiente_clase['hora_fin']); ?>
                            </div>
                        </div>
                        <div class="clase-detalle">
                            <strong>Día</strong>
                            <div class="clase-valor"><?php echo ucfirst($siguiente_clase['dia_semana']); ?></div>
                        </div>
                        <?php if ($siguiente_clase['aula']): ?>
                            <div class="clase-detalle">
                                <strong>Aula</strong>
                                <div class="clase-valor"><?php echo htmlspecialchars($siguiente_clase['aula']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($siguiente_clase['profesor_nombre']): ?>
                            <div class="clase-detalle">
                                <strong>Profesor</strong>
                                <div class="clase-valor">
                                    <?php echo htmlspecialchars($siguiente_clase['profesor_nombre'] . ' ' . $siguiente_clase['profesor_apellido']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Estadísticas del horario -->
            <div class="estadisticas-horario">
                <div class="estadistica-card">
                    <h3>Total Materias</h3>
                    <div class="estadistica-valor"><?php echo $total_materias; ?></div>
                    <small>materias asignadas</small>
                </div>
                
                <div class="estadistica-card">
                    <h3>Horas Semanales</h3>
                    <div class="estadistica-valor"><?php echo number_format($total_horas_semanales, 1); ?></div>
                    <small>horas de clase</small>
                </div>
                
                <div class="estadistica-card">
                    <h3>Aulas</h3>
                    <div class="estadistica-valor"><?php echo count($aulas_utilizadas); ?></div>
                    <small>diferentes aulas</small>
                </div>
                
                <div class="estadistica-card">
                    <h3>Profesores</h3>
                    <div class="estadistica-valor"><?php echo count($profesores_involucrados); ?></div>
                    <small>docentes involucrados</small>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-button active" onclick="cambiarTab('vista-semanal')">Vista Semanal</button>
                <button class="tab-button" onclick="cambiarTab('vista-tabla')">Tabla de Horarios</button>
                <button class="tab-button" onclick="cambiarTab('por-materias')">Por Materias</button>
            </div>
            
            <!-- Tab Vista Semanal -->
            <div id="vista-semanal" class="tab-content active">
                <?php if (!empty($horarios)): ?>
                    <div class="horario-semanal">
                        <div class="horario-header">
                            <h3>Horario Semanal</h3>
                            <p><?php echo $alumno['año']; ?>° Año - <?php echo htmlspecialchars($alumno['orientacion_nombre']); ?></p>
                        </div>
                        
                        <div class="horario-grid">
                            <?php foreach ($dias_semana as $dia_clave => $dia_nombre): ?>
                                <div class="dia-column">
                                    <div class="dia-header"><?php echo $dia_nombre; ?></div>
                                    <div class="dia-contenido">
                                        <?php if (!empty($horarios_por_dia[$dia_clave])): ?>
                                            <?php foreach ($horarios_por_dia[$dia_clave] as $horario): ?>
                                                <div class="clase-bloque" 
                                                     style="background: <?php echo $colores_por_materia[$horario['materia_id']] ?? '#6b7280'; ?>"
                                                     onclick="mostrarDetalleClase(<?php echo htmlspecialchars(json_encode($horario)); ?>)">
                                                    <div class="clase-hora">
                                                        <?php echo formatearHora($horario['hora_inicio']) . ' - ' . formatearHora($horario['hora_fin']); ?>
                                                    </div>
                                                    <div class="clase-materia">
                                                        <?php echo htmlspecialchars($horario['materia_nombre']); ?>
                                                    </div>
                                                    <?php if ($horario['aula']): ?>
                                                        <div class="clase-aula">
                                                            Aula: <?php echo htmlspecialchars($horario['aula']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($horario['profesor_nombre']): ?>
                                                        <div class="clase-profesor">
                                                            <?php echo htmlspecialchars($horario['profesor_nombre'] . ' ' . $horario['profesor_apellido']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div style="text-align: center; color: var(--gray-500); padding: 2rem 0; font-style: italic;">
                                                Sin clases programadas
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sin-horarios">
                        <h3>No hay horarios asignados</h3>
                        <p>Contacte al administrador para verificar la asignación de horarios.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Tabla de Horarios -->
            <div id="vista-tabla" class="tab-content">
                <?php if (!empty($horarios)): ?>
                    <div class="horario-tabla">
                        <div class="horario-header">
                            <h3>Tabla de Horarios Completa</h3>
                        </div>
                        <div class="tabla-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Día</th>
                                        <th>Horario</th>
                                        <th>Materia</th>
                                        <th>Profesor</th>
                                        <th>Aula</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($horarios as $horario): ?>
                                        <tr>
                                            <td><strong><?php echo ucfirst($horario['dia_semana']); ?></strong></td>
                                            <td>
                                                <?php echo formatearHora($horario['hora_inicio']) . ' - ' . formatearHora($horario['hora_fin']); ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($horario['materia_nombre']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($horario['profesor_nombre']): ?>
                                                    <?php echo htmlspecialchars($horario['profesor_nombre'] . ' ' . $horario['profesor_apellido']); ?>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-500);">No asignado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($horario['aula']): ?>
                                                    <span class="horario-aula"><?php echo htmlspecialchars($horario['aula']); ?></span>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-500);">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sin-horarios">
                        <h3>No hay horarios asignados</h3>
                        <p>Contacte al administrador para verificar la asignación de horarios.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Por Materias -->
            <div id="por-materias" class="tab-content">
                <div class="materias-lista">
                    <?php foreach ($materias as $materia): ?>
                        <div class="materia-card">
                            <div class="materia-header" style="background: <?php echo $colores_por_materia[$materia['id']] ?? '#6b7280'; ?>;">
                                <div class="materia-nombre"><?php echo htmlspecialchars($materia['nombre']); ?></div>
                                <?php if ($materia['profesor_nombre']): ?>
                                    <div class="materia-profesor">
                                        Prof. <?php echo htmlspecialchars($materia['profesor_nombre'] . ' ' . $materia['profesor_apellido']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="materia-contenido">
                                <?php
                                $horarios_materia = array_filter($horarios, function($h) use ($materia) {
                                    return $h['materia_id'] == $materia['id'];
                                });
                                ?>
                                
                                <?php if (!empty($horarios_materia)): ?>
                                    <ul class="horarios-materia">
                                        <?php foreach ($horarios_materia as $horario): ?>
                                            <li class="horario-item">
                                                <div class="horario-dia-tiempo">
                                                    <div class="horario-dia"><?php echo ucfirst($horario['dia_semana']); ?></div>
                                                    <div class="horario-tiempo">
                                                        <?php echo formatearHora($horario['hora_inicio']) . ' - ' . formatearHora($horario['hora_fin']); ?>
                                                    </div>
                                                </div>
                                                <?php if ($horario['aula']): ?>
                                                    <div class="horario-aula"><?php echo htmlspecialchars($horario['aula']); ?></div>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    
                                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); font-size: 0.875rem; color: var(--gray-600);">
                                        <strong>Total horas semanales:</strong> 
                                        <?php
                                        $horas_materia = 0;
                                        foreach ($horarios_materia as $h) {
                                            $inicio = new DateTime($h['hora_inicio']);
                                            $fin = new DateTime($h['hora_fin']);
                                            $diferencia = $inicio->diff($fin);
                                            $horas_materia += $diferencia->h + ($diferencia->i / 60);
                                        }
                                        echo number_format($horas_materia, 1) . ' horas';
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <div style="text-align: center; color: var(--gray-500); padding: 2rem; font-style: italic;">
                                        Horarios no asignados
                                    </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 1rem; text-align: center;">
                                    <a href="../alumno/notas.php?materia=<?php echo $materia['id']; ?>" class="btn-primary">Ver Notas</a>
                                    <a href="../alumno/actividades.php?materia=<?php echo $materia['id']; ?>" class="btn-secondary">Actividades</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Acciones adicionales -->
            <div class="acciones">
                <h3>Opciones de Horario</h3>
                <p>Gestiona tu horario y obtén información adicional</p>
                <div style="margin-top: 1.5rem;">
                    <button onclick="imprimirHorario()" class="btn-primary">Imprimir Horario</button>
                    <button onclick="descargarHorario()" class="btn-secondary">Descargar PDF</button>
                    <button onclick="compartirHorario()" class="btn-secondary">Compartir</button>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal para detalles de clase -->
    <div id="modal-clase" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--white); border-radius: var(--border-radius); padding: 2rem; max-width: 500px; width: 90%; box-shadow: var(--shadow-lg);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 id="modal-titulo" style="margin: 0;">Detalle de Clase</h3>
                <button onclick="cerrarModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div id="modal-contenido"></div>
            <div style="text-align: center; margin-top: 1.5rem;">
                <button onclick="cerrarModal()" class="btn-primary">Cerrar</button>
            </div>
        </div>
    </div>
    
    <script src="../js/main.js"></script>
    <script>
        // Actualizar reloj en tiempo real
        function actualizarReloj() {
            const ahora = new Date();
            const reloj = document.getElementById('reloj-actual');
            const fecha = document.getElementById('fecha-actual');
            
            const horas = ahora.getHours().toString().padStart(2, '0');
            const minutos = ahora.getMinutes().toString().padStart(2, '0');
            const segundos = ahora.getSeconds().toString().padStart(2, '0');
            
            reloj.textContent = `${horas}:${minutos}:${segundos}`;
            
            const opciones = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            fecha.textContent = ahora.toLocaleDateString('es-ES', opciones);
        }
        
        // Actualizar cada segundo
        setInterval(actualizarReloj, 1000);
        actualizarReloj(); // Llamar inmediatamente
        
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
        
        function mostrarDetalleClase(horario) {
            const modal = document.getElementById('modal-clase');
            const titulo = document.getElementById('modal-titulo');
            const contenido = document.getElementById('modal-contenido');
            
            titulo.textContent = horario.materia_nombre;
            
            let html = `
                <div style="display: grid; gap: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--gray-50); border-radius: var(--border-radius);">
                        <strong>Día:</strong>
                        <span>${horario.dia_semana.charAt(0).toUpperCase() + horario.dia_semana.slice(1)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--gray-50); border-radius: var(--border-radius);">
                        <strong>Horario:</strong>
                        <span>${formatearHoraJS(horario.hora_inicio)} - ${formatearHoraJS(horario.hora_fin)}</span>
                    </div>
            `;
            
            if (horario.aula) {
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--gray-50); border-radius: var(--border-radius);">
                        <strong>Aula:</strong>
                        <span>${horario.aula}</span>
                    </div>
                `;
            }
            
            if (horario.profesor_nombre) {
                html += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--gray-50); border-radius: var(--border-radius);">
                        <strong>Profesor:</strong>
                        <span>${horario.profesor_nombre} ${horario.profesor_apellido || ''}</span>
                    </div>
                `;
            }
            
            html += '</div>';
            contenido.innerHTML = html;
            
            modal.style.display = 'flex';
        }
        
        function cerrarModal() {
            document.getElementById('modal-clase').style.display = 'none';
        }
        
        function formatearHoraJS(hora) {
            const [h, m] = hora.split(':');
            return `${h}:${m}`;
        }
        
        function imprimirHorario() {
            const contenido = document.getElementById('vista-semanal').innerHTML;
            const ventanaImpresion = window.open('', '', 'width=800,height=600');
            
            ventanaImpresion.document.write(`
                <html>
                <head>
                    <title>Horario de Clases - <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 20px; 
                            font-size: 12px;
                        }
                        .horario-grid { 
                            display: grid; 
                            grid-template-columns: repeat(5, 1fr); 
                            gap: 1px; 
                            border: 1px solid #ccc;
                        }
                        .dia-column { 
                            border: 1px solid #ccc; 
                        }
                        .dia-header { 
                            font-weight: bold; 
                            text-align: center; 
                            background: #f0f0f0; 
                            padding: 10px; 
                        }
                        .dia-contenido {
                            padding: 10px;
                            min-height: 400px;
                        }
                        .clase-bloque { 
                            background: #e3f2fd; 
                            padding: 8px; 
                            margin: 5px 0; 
                            border-radius: 4px; 
                            border-left: 4px solid #2196f3;
                        }
                        .clase-hora { 
                            font-weight: bold; 
                            font-size: 11px;
                            margin-bottom: 3px;
                        }
                        .clase-materia { 
                            font-weight: bold; 
                            margin-bottom: 3px;
                        }
                        .clase-profesor, .clase-aula { 
                            font-size: 10px; 
                            color: #666;
                        }
                        @media print {
                            body { margin: 0; font-size: 10px; }
                            .horario-grid { font-size: 10px; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Horario de Clases</h1>
                    <h2><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></h2>
                    <h3><?php echo $alumno['año']; ?>° Año - <?php echo htmlspecialchars($alumno['orientacion_nombre']); ?></h3>
                    <hr>
                    ${contenido}
                    <div style="margin-top: 20px; font-size: 10px; color: #666;">
                        Generado el: ${new Date().toLocaleDateString('es-ES')}
                    </div>
                </body>
                </html>
            `);
            
            ventanaImpresion.document.close();
            ventanaImpresion.focus();
            ventanaImpresion.print();
        }
        
        function descargarHorario() {
            // Simulación de descarga PDF
            mostrarToast('Función de descarga PDF en desarrollo', 'info');
        }
        
        function compartirHorario() {
            if (navigator.share) {
                navigator.share({
                    title: 'Mi Horario de Clases',
                    text: 'Consulta mi horario de clases',
                    url: window.location.href
                });
            } else {
                // Fallback: copiar al portapapeles
                navigator.clipboard.writeText(window.location.href).then(() => {
                    mostrarToast('Enlace copiado al portapapeles', 'success');
                });
            }
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modal-clase').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
        
        // Animaciones de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.estadistica-card, .materia-card, .clase-bloque');
            
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s, transform 0.5s';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
            });
            
            // Efecto hover para las clases
            const claseBloques = document.querySelectorAll('.clase-bloque');
            claseBloques.forEach(bloque => {
                bloque.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.zIndex = '10';
                });
                
                bloque.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.zIndex = '1';
                });
            });
            
            // Resaltar clase actual si corresponde
            resaltarClaseActual();
        });
        
        function resaltarClaseActual() {
            const ahora = new Date();
            const diaActual = ahora.getDay(); // 0=domingo, 1=lunes, etc.
            const horaActual = ahora.getHours() * 100 + ahora.getMinutes(); // Formato HHMM
            
            const diasSemana = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
            const diaActualNombre = diasSemana[diaActual];
            
            if (diaActualNombre && diaActualNombre !== 'domingo' && diaActualNombre !== 'sabado') {
                const clases = document.querySelectorAll('.clase-bloque');
                clases.forEach(clase => {
                    const horarioTexto = clase.querySelector('.clase-hora').textContent;
                    const [inicio] = horarioTexto.split(' - ');
                    const [hora, minuto] = inicio.split(':');
                    const horaInicio = parseInt(hora) * 100 + parseInt(minuto);
                    
                    // Si es el día actual y la clase está en curso o próxima a empezar
                    if (Math.abs(horaInicio - horaActual) <= 30) { // 30 minutos de margen
                        clase.style.boxShadow = '0 0 20px rgba(37, 99, 235, 0.5)';
                        clase.style.border = '2px solid var(--primary-color)';
                    }
                });
            }
        }
        
        // Actualizar resaltado cada minuto
        setInterval(resaltarClaseActual, 60000);
    </script>
</body>
</html>