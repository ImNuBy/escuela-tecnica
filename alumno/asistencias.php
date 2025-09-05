<?php
require_once '../config.php';
verificarTipoUsuario(['alumno']);

// Función para calcular porcentaje de asistencia
function calcularPorcentajeAsistencia($presentes, $total) {
    if ($total == 0) return 0;
    return round(($presentes / $total) * 100, 2);
}

// Función para obtener estado de asistencia
function getEstadoAsistencia($presente, $justificado) {
    if ($presente) return 'presente';
    if ($justificado) return 'justificado';
    return 'ausente';
}

// Función para obtener color del estado
function getColorEstado($presente, $justificado) {
    if ($presente) return '#059669'; // Verde
    if ($justificado) return '#d97706'; // Amarillo
    return '#dc2626'; // Rojo
}

// Función para obtener icono del estado
function getIconoEstado($presente, $justificado) {
    if ($presente) return '✓';
    if ($justificado) return '!';
    return '✗';
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
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$año = isset($_GET['año']) ? (int)$_GET['año'] : date('Y');

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

// Construir consulta de asistencias
$where_clauses = ["a.alumno_id = ?"];
$params = [$alumno['id']];

if ($materia_id) {
    $where_clauses[] = "a.materia_id = ?";
    $params[] = $materia_id;
}

// Filtro por mes y año
$where_clauses[] = "MONTH(a.fecha) = ? AND YEAR(a.fecha) = ?";
$params[] = $mes;
$params[] = $año;

$where_clause = "WHERE " . implode(" AND ", $where_clauses);

// Obtener asistencias
$stmt = $pdo->prepare("
    SELECT a.*, 
           m.nombre as materia_nombre,
           u.nombre as profesor_nombre, u.apellido as profesor_apellido
    FROM asistencias a
    JOIN materias m ON a.materia_id = m.id
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    $where_clause
    ORDER BY a.fecha DESC, m.nombre
");
$stmt->execute($params);
$asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas generales del año actual
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_registros,
        SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) as total_presentes,
        SUM(CASE WHEN presente = 0 AND justificado = 1 THEN 1 ELSE 0 END) as total_justificadas,
        SUM(CASE WHEN presente = 0 AND justificado = 0 THEN 1 ELSE 0 END) as total_ausentes
    FROM asistencias a
    JOIN materias m ON a.materia_id = m.id
    WHERE a.alumno_id = ? AND YEAR(a.fecha) = ?
");
$stmt->execute([$alumno['id'], $año]);
$estadisticas_año = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener estadísticas por materia
$stmt = $pdo->prepare("
    SELECT 
        m.id,
        m.nombre as materia_nombre,
        COUNT(a.id) as total_clases,
        SUM(CASE WHEN a.presente = 1 THEN 1 ELSE 0 END) as presentes,
        SUM(CASE WHEN a.presente = 0 AND a.justificado = 1 THEN 1 ELSE 0 END) as justificadas,
        SUM(CASE WHEN a.presente = 0 AND a.justificado = 0 THEN 1 ELSE 0 END) as ausentes,
        u.nombre as profesor_nombre,
        u.apellido as profesor_apellido
    FROM materias m
    LEFT JOIN asistencias a ON m.id = a.materia_id AND a.alumno_id = ?
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    WHERE m.año_id = ?
    GROUP BY m.id, m.nombre, u.nombre, u.apellido
    ORDER BY m.nombre
");
$stmt->execute([$alumno['id'], $alumno['año_id']]);
$estadisticas_materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener horarios para mostrar clases programadas
$stmt = $pdo->prepare("
    SELECT h.*, m.nombre as materia_nombre
    FROM horarios h
    JOIN materias m ON h.materia_id = m.id
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

// Organizar asistencias por fecha para el calendario
$asistencias_por_fecha = [];
foreach ($asistencias as $asistencia) {
    $fecha = $asistencia['fecha'];
    $asistencias_por_fecha[$fecha][] = $asistencia;
}

// Generar calendario del mes
function generarCalendario($año, $mes, $asistencias_por_fecha) {
    $primer_dia = mktime(0, 0, 0, $mes, 1, $año);
    $dias_mes = date('t', $primer_dia);
    $dia_semana_inicio = date('w', $primer_dia);
    
    $calendario = [];
    $semana_actual = 0;
    
    // Completar días del mes anterior
    for ($i = 0; $i < $dia_semana_inicio; $i++) {
        $calendario[$semana_actual][] = null;
    }
    
    // Días del mes actual
    for ($dia = 1; $dia <= $dias_mes; $dia++) {
        $fecha = sprintf('%04d-%02d-%02d', $año, $mes, $dia);
        $calendario[$semana_actual][] = [
            'dia' => $dia,
            'fecha' => $fecha,
            'asistencias' => isset($asistencias_por_fecha[$fecha]) ? $asistencias_por_fecha[$fecha] : []
        ];
        
        if (($dia + $dia_semana_inicio) % 7 == 0) {
            $semana_actual++;
        }
    }
    
    return $calendario;
}

$calendario = generarCalendario($año, $mes, $asistencias_por_fecha);
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Asistencias - Sistema Escolar</title>
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
        
        .estadisticas-asistencia {
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
        }
        
        .estadistica-card.presente { border-top: 4px solid var(--success-color); }
        .estadistica-card.justificado { border-top: 4px solid var(--warning-color); }
        .estadistica-card.ausente { border-top: 4px solid var(--danger-color); }
        .estadistica-card.porcentaje { border-top: 4px solid var(--primary-color); }
        
        .estadistica-valor {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }
        
        .estadistica-valor.presente { color: var(--success-color); }
        .estadistica-valor.justificado { color: var(--warning-color); }
        .estadistica-valor.ausente { color: var(--danger-color); }
        .estadistica-valor.porcentaje { color: var(--primary-color); }
        
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
        
        .form-select, .form-input {
            padding: 0.5rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            background: var(--white);
            min-width: 150px;
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
        
        .calendario {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .calendario-header {
            background: var(--primary-color);
            color: var(--white);
            padding: 1.5rem;
            text-align: center;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .calendario-nav {
            background: none;
            border: none;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            transition: background-color 0.2s;
        }
        
        .calendario-nav:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .calendario-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }
        
        .dia-header {
            background: var(--gray-100);
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .dia-celda {
            min-height: 80px;
            padding: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
            border-right: 1px solid var(--gray-200);
            position: relative;
        }
        
        .dia-celda:nth-child(7n) {
            border-right: none;
        }
        
        .dia-numero {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .asistencia-punto {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin: 1px;
        }
        
        .asistencia-punto.presente { background: var(--success-color); }
        .asistencia-punto.justificado { background: var(--warning-color); }
        .asistencia-punto.ausente { background: var(--danger-color); }
        
        .asistencias-tabla {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .tabla-header {
            background: var(--primary-color);
            color: var(--white);
            padding: 1rem;
            text-align: center;
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
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .estado-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            color: white;
            text-align: center;
            display: inline-block;
            min-width: 80px;
        }
        
        .estado-badge.presente { background: var(--success-color); }
        .estado-badge.justificado { background: var(--warning-color); }
        .estado-badge.ausente { background: var(--danger-color); }
        
        .materia-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .materia-header {
            background: linear-gradient(45deg, var(--primary-color), #3b82f6);
            color: var(--white);
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .materia-info h3 {
            margin: 0;
            font-size: 1.25rem;
        }
        
        .materia-profesor {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-top: 0.25rem;
        }
        
        .asistencia-resumen {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 1rem;
            text-align: center;
        }
        
        .resumen-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.75rem;
            border-radius: var(--border-radius);
        }
        
        .resumen-numero {
            font-size: 1.5rem;
            font-weight: bold;
            display: block;
        }
        
        .progress-bar {
            background: var(--gray-200);
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-top: 1rem;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        .progress-fill.presente { background: var(--success-color); }
        
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
        
        .sin-asistencias {
            padding: 3rem;
            text-align: center;
            color: var(--gray-600);
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        @media (max-width: 768px) {
            .calendario-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .dia-celda {
                min-height: 60px;
                font-size: 0.875rem;
            }
            
            .materia-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
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
                <h1>Mis Asistencias</h1>
                <p>Control de asistencia de <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></p>
            </div>
            
            <!-- Información del alumno -->
            <div class="alumno-info">
                <h2><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></h2>
                <div class="curso-badge">
                    <?php echo $alumno['año']; ?>° Año - <?php echo htmlspecialchars($alumno['orientacion_nombre']); ?>
                </div>
            </div>
            
            <!-- Estadísticas generales -->
            <div class="estadisticas-asistencia">
                <div class="estadistica-card presente">
                    <h3>Clases Asistidas</h3>
                    <div class="estadistica-valor presente"><?php echo $estadisticas_año['total_presentes']; ?></div>
                    <small>en <?php echo $año; ?></small>
                </div>
                
                <div class="estadistica-card justificado">
                    <h3>Inasistencias Justificadas</h3>
                    <div class="estadistica-valor justificado"><?php echo $estadisticas_año['total_justificadas']; ?></div>
                    <small>faltas con justificativo</small>
                </div>
                
                <div class="estadistica-card ausente">
                    <h3>Ausencias</h3>
                    <div class="estadistica-valor ausente"><?php echo $estadisticas_año['total_ausentes']; ?></div>
                    <small>sin justificar</small>
                </div>
                
                <div class="estadistica-card porcentaje">
                    <h3>Porcentaje de Asistencia</h3>
                    <div class="estadistica-valor porcentaje">
                        <?php 
                        $porcentaje = calcularPorcentajeAsistencia($estadisticas_año['total_presentes'], $estadisticas_año['total_registros']);
                        echo $porcentaje . '%';
                        ?>
                    </div>
                    <small>del total de clases</small>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filtros">
                <div class="filtro-grupo">
                    <label for="materia-filtro"><strong>Materia:</strong></label>
                    <select id="materia-filtro" class="form-select" onchange="filtrarPorMateria(this.value)">
                        <option value="">Todas las materias</option>
                        <?php foreach ($materias as $materia): ?>
                            <option value="<?php echo $materia['id']; ?>" <?php echo $materia_id == $materia['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($materia['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="mes-filtro"><strong>Mes:</strong></label>
                    <select id="mes-filtro" class="form-select" onchange="filtrarPorFecha()">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $mes == $i ? 'selected' : ''; ?>>
                                <?php echo $meses[$i]; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <label for="año-filtro"><strong>Año:</strong></label>
                    <select id="año-filtro" class="form-select" onchange="filtrarPorFecha()">
                        <?php for ($i = $año - 2; $i <= $año + 1; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $año == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <?php if ($materia_id || $mes != date('n') || $año != date('Y')): ?>
                        <a href="asistencias.php" class="btn-primary">Ver todas</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-button active" onclick="cambiarTab('calendario')">Vista Calendario</button>
                <button class="tab-button" onclick="cambiarTab('lista')">Lista Detallada</button>
                <button class="tab-button" onclick="cambiarTab('por-materia')">Por Materia</button>
            </div>
            
            <!-- Tab Calendario -->
            <div id="calendario" class="tab-content active">
                <div class="calendario">
                    <div class="calendario-header">
                        <button class="calendario-nav" onclick="cambiarMes(-1)">‹</button>
                        <h3><?php echo $meses[$mes] . ' ' . $año; ?></h3>
                        <button class="calendario-nav" onclick="cambiarMes(1)">›</button>
                    </div>
                    
                    <div class="calendario-grid">
                        <div class="dia-header">Dom</div>
                        <div class="dia-header">Lun</div>
                        <div class="dia-header">Mar</div>
                        <div class="dia-header">Mié</div>
                        <div class="dia-header">Jue</div>
                        <div class="dia-header">Vie</div>
                        <div class="dia-header">Sab</div>
                        
                        <?php foreach ($calendario as $semana): ?>
                            <?php foreach ($semana as $dia): ?>
                                <div class="dia-celda">
                                    <?php if ($dia): ?>
                                        <div class="dia-numero"><?php echo $dia['dia']; ?></div>
                                        <?php if (!empty($dia['asistencias'])): ?>
                                            <?php foreach ($dia['asistencias'] as $asistencia): ?>
                                                <span class="asistencia-punto <?php echo getEstadoAsistencia($asistencia['presente'], $asistencia['justificado']); ?>"
                                                      title="<?php echo htmlspecialchars($asistencia['materia_nombre']); ?> - <?php echo ucfirst(getEstadoAsistencia($asistencia['presente'], $asistencia['justificado'])); ?>"></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Leyenda -->
                <div style="margin-top: 1rem; padding: 1rem; background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow);">
                    <h4>Leyenda:</h4>
                    <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                        <div><span class="asistencia-punto presente"></span> Presente</div>
                        <div><span class="asistencia-punto justificado"></span> Justificado</div>
                        <div><span class="asistencia-punto ausente"></span> Ausente</div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Lista -->
            <div id="lista" class="tab-content">
                <?php if (!empty($asistencias)): ?>
                    <div class="asistencias-tabla">
                        <div class="tabla-header">
                            <h3>Registro de Asistencias - <?php echo $meses[$mes] . ' ' . $año; ?></h3>
                        </div>
                        <div class="tabla-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Materia</th>
                                        <th>Estado</th>
                                        <th>Observaciones</th>
                                        <th>Profesor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($asistencias as $asistencia): ?>
                                        <tr>
                                            <td><?php echo formatearFecha($asistencia['fecha']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($asistencia['materia_nombre']); ?></strong></td>
                                            <td>
                                                <span class="estado-badge <?php echo getEstadoAsistencia($asistencia['presente'], $asistencia['justificado']); ?>">
                                                    <?php echo getIconoEstado($asistencia['presente'], $asistencia['justificado']); ?>
                                                    <?php echo ucfirst(getEstadoAsistencia($asistencia['presente'], $asistencia['justificado'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($asistencia['observaciones']): ?>
                                                    <?php echo htmlspecialchars($asistencia['observaciones']); ?>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-500);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($asistencia['profesor_nombre']): ?>
                                                    <?php echo htmlspecialchars($asistencia['profesor_nombre'] . ' ' . $asistencia['profesor_apellido']); ?>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-500);">No asignado</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sin-asistencias">
                        <h3>No hay registros de asistencia</h3>
                        <p>No se encontraron registros para el período seleccionado.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Por Materia -->
            <div id="por-materia" class="tab-content">
                <?php foreach ($estadisticas_materias as $estadistica): ?>
                    <div class="materia-card">
                        <div class="materia-header">
                            <div class="materia-info">
                                <h3><?php echo htmlspecialchars($estadistica['materia_nombre']); ?></h3>
                                <?php if ($estadistica['profesor_nombre']): ?>
                                    <div class="materia-profesor">
                                        Prof. <?php echo htmlspecialchars($estadistica['profesor_nombre'] . ' ' . $estadistica['profesor_apellido']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="asistencia-resumen">
                                <div class="resumen-item">
                                    <span class="resumen-numero"><?php echo $estadistica['presentes']; ?></span>
                                    <small>Presentes</small>
                                </div>
                                <div class="resumen-item">
                                    <span class="resumen-numero"><?php echo $estadistica['justificadas']; ?></span>
                                    <small>Justificadas</small>
                                </div>
                                <div class="resumen-item">
                                    <span class="resumen-numero"><?php echo $estadistica['ausentes']; ?></span>
                                    <small>Ausentes</small>
                                </div>
                                <div class="resumen-item">
                                    <span class="resumen-numero">
                                        <?php echo calcularPorcentajeAsistencia($estadistica['presentes'], $estadistica['total_clases']); ?>%
                                    </span>
                                    <small>Asistencia</small>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($estadistica['total_clases'] > 0): ?>
                            <div style="padding: 1.5rem;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                    <div>
                                        <strong>Total de clases:</strong> <?php echo $estadistica['total_clases']; ?>
                                    </div>
                                    <div>
                                        <strong>Clases asistidas:</strong> <?php echo $estadistica['presentes']; ?>
                                    </div>
                                    <div>
                                        <strong>Faltas justificadas:</strong> <?php echo $estadistica['justificadas']; ?>
                                    </div>
                                    <div>
                                        <strong>Faltas sin justificar:</strong> <?php echo $estadistica['ausentes']; ?>
                                    </div>
                                </div>
                                
                                <div class="progress-bar">
                                    <div class="progress-fill presente" 
                                         style="width: <?php echo calcularPorcentajeAsistencia($estadistica['presentes'], $estadistica['total_clases']); ?>%">
                                    </div>
                                </div>
                                
                                <div style="text-align: center; margin-top: 0.5rem;">
                                    <small>
                                        Porcentaje de asistencia: 
                                        <strong><?php echo calcularPorcentajeAsistencia($estadistica['presentes'], $estadistica['total_clases']); ?>%</strong>
                                    </small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="padding: 1.5rem; text-align: center; color: var(--gray-600);">
                                <p>No hay registros de asistencia para esta materia</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
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
        
        function filtrarPorMateria(materiaId) {
            const mes = document.getElementById('mes-filtro').value;
            const año = document.getElementById('año-filtro').value;
            let url = 'asistencias.php';
            const params = [];
            
            if (materiaId) params.push('materia=' + materiaId);
            if (mes) params.push('mes=' + mes);
            if (año) params.push('año=' + año);
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            window.location.href = url;
        }
        
        function filtrarPorFecha() {
            const materia = document.getElementById('materia-filtro').value;
            const mes = document.getElementById('mes-filtro').value;
            const año = document.getElementById('año-filtro').value;
            let url = 'asistencias.php';
            const params = [];
            
            if (materia) params.push('materia=' + materia);
            if (mes) params.push('mes=' + mes);
            if (año) params.push('año=' + año);
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            window.location.href = url;
        }
        
        function cambiarMes(direccion) {
            const mesActual = <?php echo $mes; ?>;
            const añoActual = <?php echo $año; ?>;
            let nuevoMes = mesActual + direccion;
            let nuevoAño = añoActual;
            
            if (nuevoMes > 12) {
                nuevoMes = 1;
                nuevoAño++;
            } else if (nuevoMes < 1) {
                nuevoMes = 12;
                nuevoAño--;
            }
            
            const materia = document.getElementById('materia-filtro').value;
            let url = 'asistencias.php?mes=' + nuevoMes + '&año=' + nuevoAño;
            
            if (materia) {
                url += '&materia=' + materia;
            }
            
            window.location.href = url;
        }
        
        // Animaciones de carga
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.estadistica-card, .materia-card');
            
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s, transform 0.5s';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Animar barras de progreso
            setTimeout(() => {
                const progressBars = document.querySelectorAll('.progress-fill');
                progressBars.forEach(bar => {
                    const width = bar.style.width;
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 200);
                });
            }, 1000);
            
            // Tooltip para puntos de asistencia
            const puntos = document.querySelectorAll('.asistencia-punto');
            puntos.forEach(punto => {
                punto.addEventListener('mouseenter', function(e) {
                    // Mostrar tooltip si es necesario
                });
            });
        });
        
        // Función para imprimir reporte
        function imprimirReporte() {
            window.print();
        }
        
        // Función para exportar (placeholder)
        function exportarAsistencias() {
            alert('Función de exportación en desarrollo');
        }
        
        // Resaltar día actual en el calendario
        document.addEventListener('DOMContentLoaded', function() {
            const hoy = new Date();
            const mesActual = <?php echo $mes; ?>;
            const añoActual = <?php echo $año; ?>;
            
            if (hoy.getMonth() + 1 === mesActual && hoy.getFullYear() === añoActual) {
                const diaActual = hoy.getDate();
                const celdas = document.querySelectorAll('.dia-celda');
                
                celdas.forEach(celda => {
                    const numero = celda.querySelector('.dia-numero');
                    if (numero && parseInt(numero.textContent) === diaActual) {
                        celda.style.backgroundColor = 'rgba(37, 99, 235, 0.1)';
                        celda.style.border = '2px solid var(--primary-color)';
                    }
                });
            }
        });
    </script>
</body>
</html>