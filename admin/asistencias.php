<?php
require_once '../config.php';
verificarTipoUsuario(['administrador']);

$mensaje = '';
$tipo_mensaje = '';

// Procesar registro de asistencia
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'registrar_asistencia') {
    $alumno_id = $_POST['alumno_id'];
    $materia_id = $_POST['materia_id'];
    $fecha = $_POST['fecha'];
    $presente = isset($_POST['presente']) ? 1 : 0;
    $justificado = isset($_POST['justificado']) ? 1 : 0;
    $observaciones = trim($_POST['observaciones']);
    
    if (!empty($alumno_id) && !empty($materia_id) && !empty($fecha)) {
        try {
            // Verificar si ya existe registro para esta fecha
            $stmt = $pdo->prepare("SELECT id FROM asistencias WHERE alumno_id = ? AND materia_id = ? AND fecha = ?");
            $stmt->execute([$alumno_id, $materia_id, $fecha]);
            
            if ($stmt->fetch()) {
                $mensaje = 'Ya existe un registro de asistencia para esta fecha';
                $tipo_mensaje = 'warning';
            } else {
                $stmt = $pdo->prepare("INSERT INTO asistencias (alumno_id, materia_id, fecha, presente, justificado, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$alumno_id, $materia_id, $fecha, $presente, $justificado, $observaciones]);
                $mensaje = 'Asistencia registrada exitosamente';
                $tipo_mensaje = 'success';
            }
        } catch(PDOException $e) {
            $mensaje = 'Error al registrar asistencia: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
    }
}

// Filtros
$filtro_materia = isset($_GET['materia']) ? (int)$_GET['materia'] : 0;
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-t');

// Obtener materias para filtros
$stmt = $pdo->query("
    SELECT m.*, CONCAT(a.año, '° - ', o.nombre) as año_orientacion,
           CONCAT(u.nombre, ' ', u.apellido) as profesor_nombre
    FROM materias m
    JOIN años a ON m.año_id = a.id
    JOIN orientaciones o ON a.orientacion_id = o.id
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    ORDER BY a.año, m.nombre
");
$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir consulta con filtros
$where_conditions = ["ast.fecha BETWEEN ? AND ?"];
$params = [$filtro_fecha_desde, $filtro_fecha_hasta];

if ($filtro_materia) {
    $where_conditions[] = "ast.materia_id = ?";
    $params[] = $filtro_materia;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Obtener registros de asistencia
$stmt = $pdo->prepare("
    SELECT ast.*, u.nombre, u.apellido, u.usuario,
           m.nombre as materia_nombre,
           CONCAT(a.año, '° - ', o.nombre) as curso
    FROM asistencias ast
    JOIN alumnos al ON ast.alumno_id = al.id
    JOIN usuarios u ON al.usuario_id = u.id
    JOIN materias m ON ast.materia_id = m.id
    JOIN años a ON m.año_id = a.id
    JOIN orientaciones o ON a.orientacion_id = o.id
    $where_clause
    ORDER BY ast.fecha DESC, u.apellido, u.nombre
");
$stmt->execute($params);
$asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas generales
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_registros,
        SUM(presente) as total_presentes,
        SUM(CASE WHEN presente = 0 THEN 1 ELSE 0 END) as total_ausentes,
        SUM(CASE WHEN presente = 0 AND justificado = 1 THEN 1 ELSE 0 END) as total_justificadas,
        SUM(CASE WHEN presente = 0 AND justificado = 0 THEN 1 ELSE 0 END) as total_injustificadas
    FROM asistencias 
    WHERE fecha BETWEEN ? AND ?
");
$stmt->execute([$filtro_fecha_desde, $filtro_fecha_hasta]);
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Estadísticas por materia
$stmt = $pdo->prepare("
    SELECT m.nombre as materia,
           COUNT(*) as total,
           SUM(ast.presente) as presentes,
           SUM(CASE WHEN ast.presente = 0 THEN 1 ELSE 0 END) as ausentes,
           ROUND((SUM(ast.presente) * 100.0 / COUNT(*)), 2) as porcentaje_asistencia
    FROM asistencias ast
    JOIN materias m ON ast.materia_id = m.id
    WHERE ast.fecha BETWEEN ? AND ?
    GROUP BY m.id, m.nombre
    ORDER BY porcentaje_asistencia DESC
");
$stmt->execute([$filtro_fecha_desde, $filtro_fecha_hasta]);
$estadisticas_materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Alumnos con mayor ausentismo
$stmt = $pdo->prepare("
    SELECT u.nombre, u.apellido, u.usuario,
           CONCAT(a.año, '° - ', o.nombre) as curso,
           COUNT(*) as total_registros,
           SUM(ast.presente) as presentes,
           SUM(CASE WHEN ast.presente = 0 THEN 1 ELSE 0 END) as ausentes,
           ROUND((SUM(CASE WHEN ast.presente = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as porcentaje_ausentismo
    FROM asistencias ast
    JOIN alumnos al ON ast.alumno_id = al.id
    JOIN usuarios u ON al.usuario_id = u.id
    JOIN años a ON al.año_id = a.id
    JOIN orientaciones o ON a.orientacion_id = o.id
    WHERE ast.fecha BETWEEN ? AND ?
    GROUP BY al.id, u.nombre, u.apellido, u.usuario, curso
    HAVING ausentes > 0
    ORDER BY porcentaje_ausentismo DESC
    LIMIT 10
");
$stmt->execute([$filtro_fecha_desde, $filtro_fecha_hasta]);
$alumnos_ausentismo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener alumnos para el formulario
$alumnos = [];
if ($filtro_materia) {
    $stmt = $pdo->prepare("
        SELECT al.id, u.nombre, u.apellido, u.usuario
        FROM alumnos al
        JOIN usuarios u ON al.usuario_id = u.id
        JOIN materias m ON al.año_id = m.año_id
        WHERE m.id = ? AND u.activo = 1
        ORDER BY u.apellido, u.nombre
    ");
    $stmt->execute([$filtro_materia]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Asistencias - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/asistencias.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>📋 Control de Asistencias</h1>
                <p>Gestionar y monitorear asistencias de alumnos</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo h($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="asistencias-container">
                <!-- Estadísticas Generales -->
                <div class="estadisticas-generales">
                    <h2>📊 Estadísticas del Período</h2>
                    <div class="stats-grid">
                        <div class="stat-card stat-total">
                            <div class="stat-icon">📚</div>
                            <div class="stat-info">
                                <h3><?php echo number_format($estadisticas['total_registros'] ?? 0); ?></h3>
                                <p>Total Registros</p>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-presente">
                            <div class="stat-icon">✅</div>
                            <div class="stat-info">
                                <h3><?php echo number_format($estadisticas['total_presentes'] ?? 0); ?></h3>
                                <p>Presentes</p>
                                <small><?php echo $estadisticas['total_registros'] > 0 ? round(($estadisticas['total_presentes'] / $estadisticas['total_registros']) * 100, 1) : 0; ?>%</small>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-ausente">
                            <div class="stat-icon">❌</div>
                            <div class="stat-info">
                                <h3><?php echo number_format($estadisticas['total_ausentes'] ?? 0); ?></h3>
                                <p>Ausentes</p>
                                <small><?php echo $estadisticas['total_registros'] > 0 ? round(($estadisticas['total_ausentes'] / $estadisticas['total_registros']) * 100, 1) : 0; ?>%</small>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-justificada">
                            <div class="stat-icon">📋</div>
                            <div class="stat-info">
                                <h3><?php echo number_format($estadisticas['total_justificadas'] ?? 0); ?></h3>
                                <p>Justificadas</p>
                                <small><?php echo $estadisticas['total_ausentes'] > 0 ? round(($estadisticas['total_justificadas'] / $estadisticas['total_ausentes']) * 100, 1) : 0; ?>% del total de ausencias</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="filtros-asistencias">
                    <h2>🔍 Filtros</h2>
                    <form method="GET" class="filtros-form">
                        <div class="filtros-grid">
                            <div class="form-group">
                                <label for="materia">Materia</label>
                                <select name="materia" id="materia" onchange="this.form.submit()">
                                    <option value="">Todas las materias</option>
                                    <?php foreach ($materias as $materia): ?>
                                        <option value="<?php echo $materia['id']; ?>" 
                                                <?php echo ($filtro_materia == $materia['id']) ? 'selected' : ''; ?>>
                                            <?php echo h($materia['nombre'] . ' - ' . $materia['año_orientacion']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="fecha_desde">Desde</label>
                                <input type="date" name="fecha_desde" id="fecha_desde" 
                                       value="<?php echo $filtro_fecha_desde; ?>" onchange="this.form.submit()">
                            </div>
                            
                            <div class="form-group">
                                <label for="fecha_hasta">Hasta</label>
                                <input type="date" name="fecha_hasta" id="fecha_hasta" 
                                       value="<?php echo $filtro_fecha_hasta; ?>" onchange="this.form.submit()">
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn-primary">Filtrar</button>
                                <a href="asistencias.php" class="btn-secondary">Limpiar</a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Formulario de registro -->
                <?php if ($filtro_materia && !empty($alumnos)): ?>
                <div class="registro-asistencia">
                    <h2>➕ Registrar Asistencia</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="registrar_asistencia">
                        <input type="hidden" name="materia_id" value="<?php echo $filtro_materia; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="alumno_id">Alumno *</label>
                                <select id="alumno_id" name="alumno_id" required>
                                    <option value="">Seleccionar alumno...</option>
                                    <?php foreach ($alumnos as $alumno): ?>
                                        <option value="<?php echo $alumno['id']; ?>">
                                            <?php echo h($alumno['apellido'] . ', ' . $alumno['nombre'] . ' (' . $alumno['usuario'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="fecha">Fecha *</label>
                                <input type="date" id="fecha" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group checkbox-group">
                                <label>
                                    <input type="checkbox" name="presente" checked> Presente
                                </label>
                                <label>
                                    <input type="checkbox" name="justificado"> Justificado (si ausente)
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label for="observaciones">Observaciones</label>
                                <textarea id="observaciones" name="observaciones" rows="2" 
                                          placeholder="Comentarios adicionales..."></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary">📝 Registrar Asistencia</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Estadísticas por Materia -->
                <?php if (!empty($estadisticas_materias)): ?>
                <div class="estadisticas-materias">
                    <h2>📈 Estadísticas por Materia</h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Materia</th>
                                    <th>Total Registros</th>
                                    <th>Presentes</th>
                                    <th>Ausentes</th>
                                    <th>% Asistencia</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($estadisticas_materias as $stat): ?>
                                    <tr>
                                        <td><?php echo h($stat['materia']); ?></td>
                                        <td><?php echo number_format($stat['total']); ?></td>
                                        <td class="presente"><?php echo number_format($stat['presentes']); ?></td>
                                        <td class="ausente"><?php echo number_format($stat['ausentes']); ?></td>
                                        <td>
                                            <div class="porcentaje-bar">
                                                <div class="porcentaje-fill" style="width: <?php echo $stat['porcentaje_asistencia']; ?>%"></div>
                                                <span><?php echo $stat['porcentaje_asistencia']; ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($stat['porcentaje_asistencia'] >= 90): ?>
                                                <span class="estado-excelente">🟢 Excelente</span>
                                            <?php elseif ($stat['porcentaje_asistencia'] >= 80): ?>
                                                <span class="estado-bueno">🟡 Bueno</span>
                                            <?php elseif ($stat['porcentaje_asistencia'] >= 70): ?>
                                                <span class="estado-regular">🟠 Regular</span>
                                            <?php else: ?>
                                                <span class="estado-critico">🔴 Crítico</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Alumnos con Mayor Ausentismo -->
                <?php if (!empty($alumnos_ausentismo)): ?>
                <div class="ausentismo-critico">
                    <h2>⚠️ Alumnos con Mayor Ausentismo</h2>
                    <div class="alumnos-grid">
                        <?php foreach ($alumnos_ausentismo as $alumno): ?>
                            <div class="alumno-ausentismo-card">
                                <div class="alumno-info">
                                    <h4><?php echo h($alumno['nombre'] . ' ' . $alumno['apellido']); ?></h4>
                                    <p><?php echo h($alumno['curso']); ?></p>
                                    <span class="usuario">@<?php echo h($alumno['usuario']); ?></span>
                                </div>
                                <div class="ausentismo-stats">
                                    <div class="stat-ausentismo">
                                        <span class="numero"><?php echo $alumno['ausentes']; ?></span>
                                        <span class="label">Ausencias</span>
                                    </div>
                                    <div class="porcentaje-ausentismo">
                                        <div class="circulo-progreso" data-porcentaje="<?php echo $alumno['porcentaje_ausentismo']; ?>">
                                            <span><?php echo $alumno['porcentaje_ausentismo']; ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Lista de Asistencias -->
                <div class="lista-asistencias">
                    <div class="lista-header">
                        <h2>📋 Registros de Asistencia (<?php echo count($asistencias); ?>)</h2>
                        <button onclick="exportarAsistencias()" class="btn-secondary">📤 Exportar</button>
                    </div>
                    
                    <?php if (!empty($asistencias)): ?>
                        <div class="table-responsive">
                            <table id="asistenciasTable">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Alumno</th>
                                        <th>Curso</th>
                                        <th>Materia</th>
                                        <th>Estado</th>
                                        <th>Observaciones</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($asistencias as $asistencia): ?>
                                        <tr>
                                            <td data-label="Fecha"><?php echo formatearFecha($asistencia['fecha']); ?></td>
                                            <td data-label="Alumno">
                                                <?php echo h($asistencia['apellido'] . ', ' . $asistencia['nombre']); ?>
                                                <small>(@<?php echo h($asistencia['usuario']); ?>)</small>
                                            </td>
                                            <td data-label="Curso"><?php echo h($asistencia['curso']); ?></td>
                                            <td data-label="Materia"><?php echo h($asistencia['materia_nombre']); ?></td>
                                            <td data-label="Estado">
                                                <?php if ($asistencia['presente']): ?>
                                                    <span class="estado-presente">✅ Presente</span>
                                                <?php else: ?>
                                                    <?php if ($asistencia['justificado']): ?>
                                                        <span class="estado-justificado">📋 Ausente Justificado</span>
                                                    <?php else: ?>
                                                        <span class="estado-ausente">❌ Ausente</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Observaciones"><?php echo h($asistencia['observaciones']); ?></td>
                                            <td data-label="Acciones">
                                                <a href="editar_asistencia.php?id=<?php echo $asistencia['id']; ?>" 
                                                   class="btn-small btn-edit">✏️ Editar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="sin-registros">
                            <h3>📭 No hay registros</h3>
                            <p>No se encontraron registros de asistencia con los filtros seleccionados.</p>
                            <?php if (!$filtro_materia): ?>
                                <p><strong>💡 Consejo:</strong> Selecciona una materia para poder registrar asistencias.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../js/main.js"></script>
    <script>
        function exportarAsistencias() {
            exportarExcel('asistenciasTable', 'asistencias_' + new Date().toISOString().slice(0,10));
        }
        
        // Configurar filtro de búsqueda si hay tabla
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('asistenciasTable')) {
                // Agregar campo de búsqueda dinámico
                const listaHeader = document.querySelector('.lista-header');
                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.placeholder = '🔍 Buscar en registros...';
                searchInput.className = 'search-box';
                searchInput.id = 'searchAsistencias';
                listaHeader.insertBefore(searchInput, listaHeader.lastElementChild);
                
                filtrarTabla('searchAsistencias', 'asistenciasTable');
            }
            
            // Animar círculos de progreso
            const circulos = document.querySelectorAll('.circulo-progreso');
            circulos.forEach(circulo => {
                const porcentaje = circulo.getAttribute('data-porcentaje');
                const color = porcentaje > 30 ? '#dc2626' : porcentaje > 20 ? '#d97706' : '#059669';
                circulo.style.background = `conic-gradient(${color} ${porcentaje * 3.6}deg, #e5e7eb 0deg)`;
            });
        });
    </script>
</body>
</html>