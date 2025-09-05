<?php
require_once '../config.php';
verificarTipoUsuario(['profesor']);

$mensaje = '';
$tipo_mensaje = '';

// Obtener ID del profesor
$stmt = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$profesor = $stmt->fetch(PDO::FETCH_ASSOC);
$profesor_id = $profesor['id'];

// Procesar formulario de asistencia
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'marcar_asistencias') {
    $materia_id = $_POST['materia_id'];
    $fecha = $_POST['fecha'];
    $asistencias_data = $_POST['asistencias'] ?? [];
    
    if (!empty($materia_id) && !empty($fecha) && !empty($asistencias_data)) {
        try {
            $pdo->beginTransaction();
            
            // Eliminar asistencias existentes para esa fecha y materia
            $stmt = $pdo->prepare("DELETE FROM asistencias WHERE materia_id = ? AND fecha = ?");
            $stmt->execute([$materia_id, $fecha]);
            
            // Insertar nuevas asistencias
            $stmt = $pdo->prepare("INSERT INTO asistencias (alumno_id, materia_id, fecha, presente, justificado, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($asistencias_data as $alumno_id => $asistencia) {
                $presente = isset($asistencia['presente']) ? 1 : 0;
                $justificado = isset($asistencia['justificado']) ? 1 : 0;
                $observaciones = trim($asistencia['observaciones'] ?? '');
                
                $stmt->execute([$alumno_id, $materia_id, $fecha, $presente, $justificado, $observaciones]);
            }
            
            $pdo->commit();
            $mensaje = 'Asistencias registradas correctamente';
            $tipo_mensaje = 'success';
        } catch(PDOException $e) {
            $pdo->rollBack();
            $mensaje = 'Error al registrar asistencias: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
    }
}

// Obtener materias del profesor
$stmt = $pdo->prepare("
    SELECT m.*, CONCAT(a.año, '° - ', o.nombre) as año_orientacion
    FROM materias m
    JOIN años a ON m.año_id = a.id
    JOIN orientaciones o ON a.orientacion_id = o.id
    WHERE m.profesor_id = ?
    ORDER BY a.año, m.nombre
");
$stmt->execute([$_SESSION['usuario_id']]);
$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener materia y fecha seleccionadas
$materia_seleccionada = isset($_GET['materia']) ? (int)$_GET['materia'] : (count($materias) > 0 ? $materias[0]['id'] : 0);
$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Obtener alumnos de la materia seleccionada
$alumnos = [];
$asistencias_existentes = [];
if ($materia_seleccionada) {
    // Obtener alumnos
    $stmt = $pdo->prepare("
        SELECT al.id, u.nombre, u.apellido, u.usuario
        FROM alumnos al
        JOIN usuarios u ON al.usuario_id = u.id
        JOIN materias m ON al.año_id = m.año_id
        WHERE m.id = ? AND u.activo = 1
        ORDER BY u.apellido, u.nombre
    ");
    $stmt->execute([$materia_seleccionada]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener asistencias existentes para la fecha
    $stmt = $pdo->prepare("
        SELECT alumno_id, presente, justificado, observaciones
        FROM asistencias
        WHERE materia_id = ? AND fecha = ?
    ");
    $stmt->execute([$materia_seleccionada, $fecha_seleccionada]);
    $asistencias_temp = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($asistencias_temp as $asistencia) {
        $asistencias_existentes[$asistencia['alumno_id']] = $asistencia;
    }
}

// Obtener estadísticas de asistencia por materia
$estadisticas_materias = [];
if (!empty($materias)) {
    foreach ($materias as $materia) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_registros,
                SUM(presente) as total_presentes,
                SUM(CASE WHEN presente = 0 THEN 1 ELSE 0 END) as total_ausentes,
                ROUND((SUM(presente) * 100.0 / COUNT(*)), 2) as porcentaje_asistencia
            FROM asistencias 
            WHERE materia_id = ?
                AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$materia['id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $estadisticas_materias[$materia['id']] = $stats;
    }
}

// Obtener resumen de asistencias recientes
$stmt = $pdo->prepare("
    SELECT ast.fecha, m.nombre as materia_nombre,
           COUNT(*) as total_alumnos,
           SUM(ast.presente) as presentes,
           SUM(CASE WHEN ast.presente = 0 THEN 1 ELSE 0 END) as ausentes
    FROM asistencias ast
    JOIN materias m ON ast.materia_id = m.id
    WHERE m.profesor_id = ?
        AND ast.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY ast.fecha, m.id, m.nombre
    ORDER BY ast.fecha DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['usuario_id']]);
$resumen_reciente = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para determinar clase de asistencia
function getClaseAsistencia($porcentaje) {
    if ($porcentaje >= 85) return 'asistencia-excelente';
    if ($porcentaje >= 75) return 'asistencia-buena';
    if ($porcentaje >= 60) return 'asistencia-regular';
    return 'asistencia-baja';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Asistencias - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/asistenciasprof.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="content">
            <div class="page-header">
                <h1>📋 Control de Asistencias</h1>
                <p>Registrar y gestionar asistencias de alumnos</p>
            </div>

            <!-- Mostrar mensajes -->
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="asistencias-container">
                <!-- Estadísticas Generales -->
                <div class="estadisticas-panel">
                    <h2>📊 Resumen de Asistencias</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">📚</div>
                            <div class="stat-info">
                                <h3><?php echo count($materias); ?></h3>
                                <p>Materias Asignadas</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">👥</div>
                            <div class="stat-info">
                                <h3><?php echo count($alumnos); ?></h3>
                                <p>Alumnos Actuales</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📅</div>
                            <div class="stat-info">
                                <h3><?php echo count($resumen_reciente); ?></h3>
                                <p>Clases Esta Semana</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">⏰</div>
                            <div class="stat-info">
                                <h3><?php echo date('d/m/Y'); ?></h3>
                                <p>Fecha Actual</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selector de Materia y Fecha -->
                <div class="selector-panel">
                    <h2>🎯 Seleccionar Clase</h2>
                    <div class="selector-grid">
                        <div class="form-group">
                            <label for="materia-select">Materia</label>
                            <select id="materia-select" onchange="cambiarMateria()">
                                <?php foreach ($materias as $materia): ?>
                                    <option value="<?php echo $materia['id']; ?>" 
                                            <?php echo $materia['id'] == $materia_seleccionada ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($materia['nombre'] . ' - ' . $materia['año_orientacion']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha-select">Fecha de Clase</label>
                            <input type="date" id="fecha-select" value="<?php echo $fecha_seleccionada; ?>" 
                                   onchange="cambiarFecha()" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="acciones-rapidas">
                            <button type="button" class="btn btn-secondary" onclick="copiarAsistenciaAnterior()">
                                📋 Copiar Día Anterior
                            </button>
                            
                            <button type="button" class="btn btn-success" onclick="marcarTodosPresentes()">
                                ✅ Todos Presentes
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($materia_seleccionada && count($alumnos) > 0): ?>
                    <!-- Formulario de Asistencias -->
                    <div class="asistencias-form">
                        <div class="form-header">
                            <h2>✏️ Registrar Asistencias</h2>
                            <div class="fecha-info">
                                <span class="fecha-display"><?php echo h(formatearFecha($fecha_seleccionada)); ?></span>
                                <span class="materia-display"><?php 
                                    $materia_actual = array_filter($materias, function($m) use ($materia_seleccionada) {
                                        return $m['id'] == $materia_seleccionada;
                                    });
                                    $materia_actual = reset($materia_actual);
                                    echo htmlspecialchars($materia_actual['nombre']);
                                ?></span>
                            </div>
                        </div>

                        <form method="POST" action="" id="asistencias-form">
                            <input type="hidden" name="action" value="marcar_asistencias">
                            <input type="hidden" name="materia_id" value="<?php echo $materia_seleccionada; ?>">
                            <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">

                            <div class="alumnos-grid">
                                <?php foreach ($alumnos as $alumno): ?>
                                    <?php 
                                    $asistencia_actual = $asistencias_existentes[$alumno['id']] ?? null;
                                    $presente = $asistencia_actual ? $asistencia_actual['presente'] : 1;
                                    $justificado = $asistencia_actual ? $asistencia_actual['justificado'] : 0;
                                    $observaciones = $asistencia_actual ? $asistencia_actual['observaciones'] : '';
                                    ?>
                                    <div class="alumno-card <?php echo $presente ? 'presente' : ($justificado ? 'justificado' : 'ausente'); ?>">
                                        <div class="alumno-info">
                                            <div class="alumno-nombre">
                                                <?php echo htmlspecialchars($alumno['apellido'] . ', ' . $alumno['nombre']); ?>
                                            </div>
                                            <div class="alumno-usuario">
                                                @<?php echo htmlspecialchars($alumno['usuario']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="asistencia-controles">
                                            <div class="checkbox-group">
                                                <label class="checkbox-label presente-label">
                                                    <input type="checkbox" 
                                                           name="asistencias[<?php echo $alumno['id']; ?>][presente]" 
                                                           value="1" 
                                                           <?php echo $presente ? 'checked' : ''; ?>
                                                           onchange="toggleAsistencia(this, <?php echo $alumno['id']; ?>)">
                                                    <span class="checkmark"></span>
                                                    Presente
                                                </label>
                                                
                                                <label class="checkbox-label justificado-label">
                                                    <input type="checkbox" 
                                                           name="asistencias[<?php echo $alumno['id']; ?>][justificado]" 
                                                           value="1" 
                                                           <?php echo $justificado ? 'checked' : ''; ?>
                                                           <?php echo $presente ? 'disabled' : ''; ?>>
                                                    <span class="checkmark"></span>
                                                    Justificado
                                                </label>
                                            </div>
                                            
                                            <div class="observaciones-group">
                                                <textarea name="asistencias[<?php echo $alumno['id']; ?>][observaciones]" 
                                                          placeholder="Observaciones..."
                                                          rows="2"><?php echo htmlspecialchars($observaciones); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-large">
                                    💾 Guardar Asistencias
                                </button>
                                
                                <button type="button" class="btn btn-secondary" onclick="resetearFormulario()">
                                    🔄 Restablecer
                                </button>
                                
                                <button type="button" class="btn btn-info" onclick="previsualizarReporte()">
                                    📊 Vista Previa
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Resumen de la Clase -->
                    <div class="resumen-clase">
                        <h2>📈 Resumen de la Clase</h2>
                        <div id="resumen-dinamico" class="resumen-stats">
                            <div class="resumen-item">
                                <span class="resumen-numero" id="total-alumnos"><?php echo count($alumnos); ?></span>
                                <span class="resumen-label">Total Alumnos</span>
                            </div>
                            <div class="resumen-item presente">
                                <span class="resumen-numero" id="total-presentes"><?php echo array_sum(array_column($asistencias_existentes, 'presente')); ?></span>
                                <span class="resumen-label">Presentes</span>
                            </div>
                            <div class="resumen-item ausente">
                                <span class="resumen-numero" id="total-ausentes"><?php echo count($asistencias_existentes) - array_sum(array_column($asistencias_existentes, 'presente')); ?></span>
                                <span class="resumen-label">Ausentes</span>
                            </div>
                            <div class="resumen-item justificado">
                                <span class="resumen-numero" id="total-justificados"><?php echo array_sum(array_column($asistencias_existentes, 'justificado')); ?></span>
                                <span class="resumen-label">Justificados</span>
                            </div>
                        </div>
                    </div>

                <?php elseif ($materia_seleccionada): ?>
                    <div class="sin-contenido">
                        <h3>👥 No hay alumnos en esta materia</h3>
                        <p>Verifique que haya alumnos inscriptos en el año correspondiente a esta materia.</p>
                    </div>

                <?php else: ?>
                    <div class="sin-contenido">
                        <h3>📚 No tiene materias asignadas</h3>
                        <p>Contacte al administrador para que le asigne materias.</p>
                    </div>
                <?php endif; ?>

                <!-- Historial Reciente -->
                <?php if (!empty($resumen_reciente)): ?>
                    <div class="historial-panel">
                        <h2>🕐 Clases Recientes</h2>
                        <div class="historial-grid">
                            <?php foreach ($resumen_reciente as $clase): ?>
                                <div class="historial-item">
                                    <div class="historial-fecha">
                                        <?php echo h(formatearFecha($clase['fecha'])); ?>
                                    </div>
                                    <div class="historial-materia">
                                        <?php echo htmlspecialchars($clase['materia_nombre']); ?>
                                    </div>
                                    <div class="historial-stats">
                                        <span class="stat presente"><?php echo $clase['presentes']; ?> presentes</span>
                                        <span class="stat ausente"><?php echo $clase['ausentes']; ?> ausentes</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Estadísticas por Materia -->
                <?php if (!empty($estadisticas_materias)): ?>
                    <div class="estadisticas-materias">
                        <h2>📊 Estadísticas por Materia (Últimos 30 días)</h2>
                        <div class="materias-stats-grid">
                            <?php foreach ($materias as $materia): ?>
                                <?php $stats = $estadisticas_materias[$materia['id']]; ?>
                                <div class="materia-stat-card">
                                    <div class="materia-nombre">
                                        <?php echo htmlspecialchars($materia['nombre']); ?>
                                    </div>
                                    <div class="materia-curso">
                                        <?php echo htmlspecialchars($materia['año_orientacion']); ?>
                                    </div>
                                    <div class="porcentaje-asistencia <?php echo getClaseAsistencia($stats['porcentaje_asistencia'] ?? 0); ?>">
                                        <?php echo number_format($stats['porcentaje_asistencia'] ?? 0, 1); ?>%
                                    </div>
                                    <div class="materia-detalle">
                                        <?php echo ($stats['total_presentes'] ?? 0); ?> de <?php echo ($stats['total_registros'] ?? 0); ?> presentes
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function cambiarMateria() {
            const select = document.getElementById('materia-select');
            const fecha = document.getElementById('fecha-select').value;
            const materiaId = select.value;
            if (materiaId) {
                window.location.href = `asistencias.php?materia=${materiaId}&fecha=${fecha}`;
            }
        }

        function cambiarFecha() {
            const fecha = document.getElementById('fecha-select').value;
            const materia = document.getElementById('materia-select').value;
            if (materia && fecha) {
                window.location.href = `asistencias.php?materia=${materia}&fecha=${fecha}`;
            }
        }

        function toggleAsistencia(checkbox, alumnoId) {
            const card = checkbox.closest('.alumno-card');
            const justificadoCheckbox = card.querySelector('input[name="asistencias[' + alumnoId + '][justificado]"]');
            
            if (checkbox.checked) {
                // Si está presente, desabilitar justificado
                justificadoCheckbox.disabled = true;
                justificadoCheckbox.checked = false;
                card.className = 'alumno-card presente';
            } else {
                // Si no está presente, habilitar justificado
                justificadoCheckbox.disabled = false;
                card.className = 'alumno-card ausente';
            }
            
            actualizarResumen();
        }

        function marcarTodosPresentes() {
            const checkboxes = document.querySelectorAll('input[name*="[presente]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
                const alumnoId = checkbox.name.match(/\[(\d+)\]/)[1];
                toggleAsistencia(checkbox, alumnoId);
            });
        }

        function resetearFormulario() {
            if (confirm('¿Está seguro de que desea restablecer el formulario?')) {
                location.reload();
            }
        }

        function copiarAsistenciaAnterior() {
            const materia = document.getElementById('materia-select').value;
            const fecha = document.getElementById('fecha-select').value;
            
            if (confirm('¿Desea copiar las asistencias del día anterior?')) {
                // Esta funcionalidad requeriría una llamada AJAX
                alert('Funcionalidad en desarrollo');
            }
        }

        function actualizarResumen() {
            const totalAlumnos = document.querySelectorAll('.alumno-card').length;
            const presentes = document.querySelectorAll('input[name*="[presente]"]:checked').length;
            const ausentes = totalAlumnos - presentes;
            const justificados = document.querySelectorAll('input[name*="[justificado]"]:checked').length;
            
            document.getElementById('total-alumnos').textContent = totalAlumnos;
            document.getElementById('total-presentes').textContent = presentes;
            document.getElementById('total-ausentes').textContent = ausentes;
            document.getElementById('total-justificados').textContent = justificados;
        }

        function previsualizarReporte() {
            // Generar vista previa del reporte
            alert('Vista previa del reporte en desarrollo');
        }

        // Inicializar resumen al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            actualizarResumen();
            
            // Agregar listeners para cambios en justificados
            const justificadoCheckboxes = document.querySelectorAll('input[name*="[justificado]"]');
            justificadoCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', actualizarResumen);
            });
        });
    </script>
</body>
</html>