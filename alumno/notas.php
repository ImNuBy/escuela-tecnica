<?php
require_once '../config.php';
verificarTipoUsuario(['alumno']);

// Función para calcular tiempo transcurrido
function tiempoTranscurrido($fecha) {
    $ahora = new DateTime();
    $fechaNota = new DateTime($fecha);
    $diferencia = $ahora->diff($fechaNota);
    
    if ($diferencia->days == 0) {
        if ($diferencia->h == 0) {
            return $diferencia->i == 0 ? 'Hace un momento' : 'Hace ' . $diferencia->i . ' minuto(s)';
        }
        return 'Hace ' . $diferencia->h . ' hora(s)';
    } elseif ($diferencia->days == 1) {
        return 'Ayer';
    } elseif ($diferencia->days < 7) {
        return 'Hace ' . $diferencia->days . ' días';
    } else {
        return date('d/m/Y', strtotime($fecha));
    }
}

// Función para calcular promedio
function calcularPromedio($notas) {
    if (empty($notas)) return 0;
    $suma = array_sum($notas);
    return round($suma / count($notas), 2);
}

// Función para obtener color de nota
function getColorNota($nota) {
    if ($nota >= 8) return '#059669'; // Verde
    if ($nota >= 6) return '#d97706'; // Amarillo
    return '#dc2626'; // Rojo
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
$tipo_evaluacion = isset($_GET['tipo']) ? $_GET['tipo'] : null;

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

// Construir consulta de notas
$where_clauses = ["n.alumno_id = ?"];
$params = [$alumno['id']];

if ($materia_id) {
    $where_clauses[] = "n.materia_id = ?";
    $params[] = $materia_id;
}

if ($tipo_evaluacion) {
    $where_clauses[] = "n.tipo_evaluacion = ?";
    $params[] = $tipo_evaluacion;
}

$where_clause = "WHERE " . implode(" AND ", $where_clauses);

// Obtener notas
$stmt = $pdo->prepare("
    SELECT n.*, 
           m.nombre as materia_nombre,
           u.nombre as profesor_nombre, u.apellido as profesor_apellido
    FROM notas n
    JOIN materias m ON n.materia_id = m.id
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    $where_clause
    ORDER BY n.fecha DESC, m.nombre
");
$stmt->execute($params);
$notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas generales
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_notas,
        AVG(n.nota) as promedio_general,
        MAX(n.nota) as nota_maxima,
        MIN(n.nota) as nota_minima
    FROM notas n
    JOIN materias m ON n.materia_id = m.id
    WHERE n.alumno_id = ? AND n.nota IS NOT NULL
");
$stmt->execute([$alumno['id']]);
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener promedios por materia
$stmt = $pdo->prepare("
    SELECT 
        m.id,
        m.nombre as materia_nombre,
        COUNT(n.nota) as cantidad_notas,
        AVG(n.nota) as promedio,
        MAX(n.nota) as nota_maxima,
        MIN(n.nota) as nota_minima,
        u.nombre as profesor_nombre, 
        u.apellido as profesor_apellido
    FROM materias m
    LEFT JOIN notas n ON m.id = n.materia_id AND n.alumno_id = ?
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    WHERE m.año_id = ?
    GROUP BY m.id, m.nombre, u.nombre, u.apellido
    ORDER BY m.nombre
");
$stmt->execute([$alumno['id'], $alumno['año_id']]);
$promedios_materia = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener tipos de evaluación únicos
$stmt = $pdo->prepare("
    SELECT DISTINCT tipo_evaluacion
    FROM notas n
    JOIN materias m ON n.materia_id = m.id
    WHERE n.alumno_id = ? AND tipo_evaluacion IS NOT NULL
    ORDER BY tipo_evaluacion
");
$stmt->execute([$alumno['id']]);
$tipos_evaluacion = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Organizar notas por materia
$notas_por_materia = [];
foreach ($notas as $nota) {
    $notas_por_materia[$nota['materia_id']][] = $nota;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Notas - Sistema Escolar</title>
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
        
        .estadisticas-generales {
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
            border-top: 4px solid var(--primary-color);
        }
        
        .estadistica-valor {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }
        
        .estadistica-valor.promedio { color: var(--primary-color); }
        .estadistica-valor.total { color: var(--success-color); }
        .estadistica-valor.maxima { color: var(--success-color); }
        .estadistica-valor.minima { color: var(--warning-color); }
        
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
        
        .notas-tabla {
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
        
        .nota-valor {
            font-weight: bold;
            font-size: 1.1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            color: white;
            text-align: center;
            min-width: 40px;
            display: inline-block;
        }
        
        .materia-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
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
        
        .promedio-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            text-align: center;
        }
        
        .promedio-valor {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .sin-notas {
            padding: 3rem;
            text-align: center;
            color: var(--gray-600);
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .progress-bar {
            background: var(--gray-200);
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
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
        
        @media (max-width: 768px) {
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
                <h1>Mis Calificaciones</h1>
                <p>Historial académico de <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></p>
            </div>
            
            <!-- Información del alumno -->
            <div class="alumno-info">
                <h2><?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></h2>
                <div class="curso-badge">
                    <?php echo $alumno['año']; ?>° Año - <?php echo htmlspecialchars($alumno['orientacion_nombre']); ?>
                </div>
            </div>
            
            <!-- Estadísticas generales -->
            <div class="estadisticas-generales">
                <div class="estadistica-card">
                    <h3>Promedio General</h3>
                    <div class="estadistica-valor promedio">
                        <?php echo $estadisticas['promedio_general'] ? number_format($estadisticas['promedio_general'], 2) : 'S/N'; ?>
                    </div>
                    <small>sobre 10 puntos</small>
                </div>
                
                <div class="estadistica-card">
                    <h3>Total de Notas</h3>
                    <div class="estadistica-valor total"><?php echo $estadisticas['total_notas']; ?></div>
                    <small>evaluaciones registradas</small>
                </div>
                
                <div class="estadistica-card">
                    <h3>Nota Más Alta</h3>
                    <div class="estadistica-valor maxima">
                        <?php echo $estadisticas['nota_maxima'] ? number_format($estadisticas['nota_maxima'], 2) : 'S/N'; ?>
                    </div>
                    <small>calificación máxima</small>
                </div>
                
                <div class="estadistica-card">
                    <h3>Nota Más Baja</h3>
                    <div class="estadistica-valor minima">
                        <?php echo $estadisticas['nota_minima'] ? number_format($estadisticas['nota_minima'], 2) : 'S/N'; ?>
                    </div>
                    <small>calificación mínima</small>
                </div>
            </div>
            
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
                    
                    <?php if (!empty($tipos_evaluacion)): ?>
                        <label for="tipo-filtro"><strong>Tipo de evaluación:</strong></label>
                        <select id="tipo-filtro" class="form-select" onchange="filtrarPorTipo(this.value)">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($tipos_evaluacion as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo $tipo_evaluacion === $tipo ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(htmlspecialchars($tipo)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    
                    <?php if ($materia_id || $tipo_evaluacion): ?>
                        <a href="notas.php" class="btn-primary">Ver todas</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-button active" onclick="cambiarTab('todas')">Todas las Notas</button>
                <button class="tab-button" onclick="cambiarTab('por-materia')">Por Materia</button>
                <button class="tab-button" onclick="cambiarTab('promedios')">Promedios</button>
            </div>
            
            <!-- Tab Todas las Notas -->
            <div id="todas" class="tab-content active">
                <?php if (!empty($notas)): ?>
                    <div class="notas-tabla">
                        <div class="tabla-header">
                            <h3>Historial de Calificaciones</h3>
                        </div>
                        <div class="tabla-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Materia</th>
                                        <th>Tipo</th>
                                        <th>Nota</th>
                                        <th>Observaciones</th>
                                        <th>Profesor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notas as $nota): ?>
                                        <tr>
                                            <td><?php echo $nota['fecha'] ? formatearFecha($nota['fecha']) : 'S/F'; ?></td>
                                            <td><strong><?php echo htmlspecialchars($nota['materia_nombre']); ?></strong></td>
                                            <td><?php echo ucfirst(htmlspecialchars($nota['tipo_evaluacion'] ?? 'N/E')); ?></td>
                                            <td>
                                                <?php if ($nota['nota'] !== null): ?>
                                                    <span class="nota-valor" style="background-color: <?php echo getColorNota($nota['nota']); ?>">
                                                        <?php echo number_format($nota['nota'], 2); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-500);">Sin calificar</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($nota['observaciones']): ?>
                                                    <?php echo htmlspecialchars($nota['observaciones']); ?>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-500);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($nota['profesor_nombre']): ?>
                                                    <?php echo htmlspecialchars($nota['profesor_nombre'] . ' ' . $nota['profesor_apellido']); ?>
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
                    <div class="sin-notas">
                        <h3>No hay calificaciones registradas</h3>
                        <p>Aún no tienes notas con los filtros seleccionados.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Por Materia -->
            <div id="por-materia" class="tab-content">
                <?php if (!empty($notas_por_materia)): ?>
                    <?php foreach ($materias as $materia): ?>
                        <?php if (isset($notas_por_materia[$materia['id']])): ?>
                            <div class="materia-card">
                                <div class="materia-header">
                                    <div class="materia-info">
                                        <h3><?php echo htmlspecialchars($materia['nombre']); ?></h3>
                                        <?php if ($materia['profesor_nombre']): ?>
                                            <div class="materia-profesor">
                                                Prof. <?php echo htmlspecialchars($materia['profesor_nombre'] . ' ' . $materia['profesor_apellido']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="promedio-badge">
                                        <div>Promedio</div>
                                        <div class="promedio-valor">
                                            <?php
                                            $notas_materia = array_column($notas_por_materia[$materia['id']], 'nota');
                                            $notas_materia = array_filter($notas_materia, function($n) { return $n !== null; });
                                            echo !empty($notas_materia) ? number_format(calcularPromedio($notas_materia), 2) : 'S/N';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="tabla-wrapper">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Tipo</th>
                                                <th>Nota</th>
                                                <th>Observaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($notas_por_materia[$materia['id']] as $nota): ?>
                                                <tr>
                                                    <td><?php echo $nota['fecha'] ? formatearFecha($nota['fecha']) : 'S/F'; ?></td>
                                                    <td><?php echo ucfirst(htmlspecialchars($nota['tipo_evaluacion'] ?? 'N/E')); ?></td>
                                                    <td>
                                                        <?php if ($nota['nota'] !== null): ?>
                                                            <span class="nota-valor" style="background-color: <?php echo getColorNota($nota['nota']); ?>">
                                                                <?php echo number_format($nota['nota'], 2); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="color: var(--gray-500);">Sin calificar</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($nota['observaciones']): ?>
                                                            <?php echo htmlspecialchars($nota['observaciones']); ?>
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
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="sin-notas">
                        <h3>No hay notas por materia</h3>
                        <p>No se encontraron calificaciones organizadas por materia.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Promedios -->
            <div id="promedios" class="tab-content">
                <div class="notas-tabla">
                    <div class="tabla-header">
                        <h3>Promedios por Materia</h3>
                    </div>
                    <div class="tabla-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Materia</th>
                                    <th>Profesor</th>
                                    <th>Cantidad de Notas</th>
                                    <th>Promedio</th>
                                    <th>Nota Máxima</th>
                                    <th>Nota Mínima</th>
                                    <th>Progreso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($promedios_materia as $promedio): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($promedio['materia_nombre']); ?></strong></td>
                                        <td>
                                            <?php if ($promedio['profesor_nombre']): ?>
                                                <?php echo htmlspecialchars($promedio['profesor_nombre'] . ' ' . $promedio['profesor_apellido']); ?>
                                            <?php else: ?>
                                                <span style="color: var(--gray-500);">No asignado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $promedio['cantidad_notas']; ?></td>
                                        <td>
                                            <?php if ($promedio['promedio']): ?>
                                                <span class="nota-valor" style="background-color: <?php echo getColorNota($promedio['promedio']); ?>">
                                                    <?php echo number_format($promedio['promedio'], 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--gray-500);">Sin notas</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $promedio['nota_maxima'] ? number_format($promedio['nota_maxima'], 2) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo $promedio['nota_minima'] ? number_format($promedio['nota_minima'], 2) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($promedio['promedio']): ?>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" 
                                                         style="width: <?php echo ($promedio['promedio'] / 10) * 100; ?>%; 
                                                                background-color: <?php echo getColorNota($promedio['promedio']); ?>">
                                                    </div>
                                                </div>
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
            const tipoActual = document.getElementById('tipo-filtro') ? document.getElementById('tipo-filtro').value : '';
            let url = 'notas.php';
            const params = [];
            
            if (materiaId) params.push('materia=' + materiaId);
            if (tipoActual) params.push('tipo=' + tipoActual);
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            window.location.href = url;
        }
        
        function filtrarPorTipo(tipo) {
            const materiaActual = document.getElementById('materia-filtro').value;
            let url = 'notas.php';
            const params = [];
            
            if (materiaActual) params.push('materia=' + materiaActual);
            if (tipo) params.push('tipo=' + tipo);
            
            if (params.length > 0) {
                url += '?' + params.join('&');
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
        });
        
        // Función para mostrar detalles de nota (opcional)
        function mostrarDetalleNota(notaId) {
            // Implementar modal con detalles si es necesario
            console.log('Mostrar detalle de nota:', notaId);
        }
        
        // Función para imprimir reporte
        function imprimirReporte() {
            window.print();
        }
        
        // Función para exportar datos (placeholder)
        function exportarNotas() {
            alert('Función de exportación en desarrollo');
        }
    </script>
</body>
</html>