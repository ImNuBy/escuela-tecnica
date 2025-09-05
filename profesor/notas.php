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

// Procesar formulario de nueva nota
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear_nota') {
    $alumno_id = $_POST['alumno_id'];
    $materia_id = $_POST['materia_id'];
    $nota = $_POST['nota'];
    $tipo_evaluacion = trim($_POST['tipo_evaluacion']);
    $fecha = $_POST['fecha'];
    $observaciones = trim($_POST['observaciones']);
    
    if (!empty($alumno_id) && !empty($materia_id) && !empty($nota) && !empty($tipo_evaluacion)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notas (alumno_id, materia_id, nota, tipo_evaluacion, fecha, observaciones) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$alumno_id, $materia_id, $nota, $tipo_evaluacion, $fecha, $observaciones]);
            $mensaje = 'Nota registrada exitosamente';
            $tipo_mensaje = 'success';
        } catch(PDOException $e) {
            $mensaje = 'Error al registrar nota: ' . $e->getMessage();
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

// Obtener materia seleccionada
$materia_seleccionada = isset($_GET['materia']) ? (int)$_GET['materia'] : (count($materias) > 0 ? $materias[0]['id'] : 0);

// Obtener alumnos de la materia seleccionada
$alumnos = [];
if ($materia_seleccionada) {
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
}

// Obtener notas existentes
$notas = [];
if ($materia_seleccionada) {
    $stmt = $pdo->prepare("
        SELECT n.*, u.nombre, u.apellido, u.usuario, m.nombre as materia_nombre
        FROM notas n
        JOIN alumnos al ON n.alumno_id = al.id
        JOIN usuarios u ON al.usuario_id = u.id
        JOIN materias m ON n.materia_id = m.id
        WHERE n.materia_id = ?
        ORDER BY u.apellido, u.nombre, n.fecha DESC
    ");
    $stmt->execute([$materia_seleccionada]);
    $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para determinar clase de nota
function getClaseNota($nota) {
    if ($nota >= 8) return 'nota-excelente';
    if ($nota >= 6) return 'nota-buena';
    if ($nota >= 4) return 'nota-regular';
    return 'nota-insuficiente';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Notas - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/profesor.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="content">
            <div class="page-header">
                <h1>📊 Gestión de Notas</h1>
                <p>Registrar y consultar calificaciones de alumnos</p>
            </div>

            <!-- Mostrar mensajes -->
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="notas-container">
                <!-- Selector de Materia -->
                <div class="materia-selector">
                    <h2>📚 Seleccionar Materia</h2>
                    <div class="selector-grid">
                        <select id="materia-select" onchange="cambiarMateria()">
                            <?php foreach ($materias as $materia): ?>
                                <option value="<?php echo $materia['id']; ?>" 
                                        <?php echo $materia['id'] == $materia_seleccionada ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($materia['nombre'] . ' - ' . $materia['año_orientacion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if ($materia_seleccionada): ?>
                            <button type="button" class="btn btn-secondary" onclick="exportarNotas()">
                                📤 Exportar Notas
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($materia_seleccionada && count($alumnos) > 0): ?>
                    <!-- Formulario para nueva nota -->
                    <div class="form-container">
                        <h2>📝 Registrar Nueva Nota</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="crear_nota">
                            <input type="hidden" name="materia_id" value="<?php echo $materia_seleccionada; ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="alumno_id">Alumno *</label>
                                    <select name="alumno_id" id="alumno_id" required>
                                        <option value="">Seleccionar alumno...</option>
                                        <?php foreach ($alumnos as $alumno): ?>
                                            <option value="<?php echo $alumno['id']; ?>">
                                                <?php echo htmlspecialchars($alumno['apellido'] . ', ' . $alumno['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="nota">Nota *</label>
                                    <input type="number" name="nota" id="nota" min="1" max="10" step="0.1" required>
                                </div>

                                <div class="form-group">
                                    <label for="tipo_evaluacion">Tipo de Evaluación *</label>
                                    <select name="tipo_evaluacion" id="tipo_evaluacion" required>
                                        <option value="">Seleccionar tipo...</option>
                                        <option value="Examen">Examen</option>
                                        <option value="Parcial">Parcial</option>
                                        <option value="Trabajo Práctico">Trabajo Práctico</option>
                                        <option value="Oral">Oral</option>
                                        <option value="Proyecto">Proyecto</option>
                                        <option value="Tarea">Tarea</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="fecha">Fecha</label>
                                    <input type="date" name="fecha" id="fecha" value="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="observaciones">Observaciones</label>
                                    <textarea name="observaciones" id="observaciones" rows="3" 
                                            placeholder="Comentarios adicionales..."></textarea>
                                </div>
                            </div>

                            <div style="margin-top: 1.5rem;">
                                <button type="submit" class="btn btn-primary">
                                    💾 Registrar Nota
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tabla de notas existentes -->
                    <?php if (count($notas) > 0): ?>
                        <div class="notas-table">
                            <div class="table-header">
                                <h2>📋 Notas Registradas</h2>
                                <input type="text" class="search-box" placeholder="Buscar alumno..." 
                                       onkeyup="filtrarNotas(this.value)">
                            </div>

                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Alumno</th>
                                            <th>Nota</th>
                                            <th>Tipo</th>
                                            <th>Fecha</th>
                                            <th>Observaciones</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="notas-tbody">
                                        <?php foreach ($notas as $nota): ?>
                                            <tr>
                                                <td data-label="Alumno">
                                                    <?php echo htmlspecialchars($nota['apellido'] . ', ' . $nota['nombre']); ?>
                                                </td>
                                                <td data-label="Nota">
                                                    <span class="nota-valor <?php echo getClaseNota($nota['nota']); ?>">
                                                        <?php echo number_format($nota['nota'], 1); ?>
                                                    </span>
                                                </td>
                                                <td data-label="Tipo">
                                                    <?php echo htmlspecialchars($nota['tipo_evaluacion']); ?>
                                                </td>
                                                <td data-label="Fecha">
                                                    <?php echo date('d/m/Y', strtotime($nota['fecha'])); ?>
                                                </td>
                                                <td data-label="Observaciones">
                                                    <?php echo htmlspecialchars($nota['observaciones'] ?: '-'); ?>
                                                </td>
                                                <td data-label="Acciones">
                                                    <button type="button" class="btn btn-small btn-warning" 
                                                            onclick="editarNota(<?php echo $nota['id']; ?>)">
                                                        ✏️ Editar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Resumen por alumno -->
                        <?php
                        // Calcular promedios por alumno
                        $promedios = [];
                        foreach ($notas as $nota) {
                            $alumno_key = $nota['alumno_id'];
                            if (!isset($promedios[$alumno_key])) {
                                $promedios[$alumno_key] = [
                                    'nombre' => $nota['nombre'],
                                    'apellido' => $nota['apellido'],
                                    'notas' => [],
                                    'suma' => 0,
                                    'count' => 0
                                ];
                            }
                            $promedios[$alumno_key]['notas'][] = $nota;
                            $promedios[$alumno_key]['suma'] += $nota['nota'];
                            $promedios[$alumno_key]['count']++;
                        }
                        ?>

                        <div class="resumen-alumno">
                            <?php foreach ($promedios as $promedio): ?>
                                <?php $promedio_valor = $promedio['suma'] / $promedio['count']; ?>
                                <div class="alumno-card">
                                    <div class="alumno-nombre">
                                        <?php echo htmlspecialchars($promedio['apellido'] . ', ' . $promedio['nombre']); ?>
                                    </div>
                                    
                                    <div class="promedio <?php echo getClaseNota($promedio_valor); ?>">
                                        <?php echo number_format($promedio_valor, 1); ?>
                                    </div>

                                    <div class="info-alumno">
                                        Total de evaluaciones: <?php echo $promedio['count']; ?>
                                    </div>

                                    <div class="ultimas-notas">
                                        <strong>Últimas 3 notas:</strong>
                                        <?php 
                                        $ultimas = array_slice($promedio['notas'], 0, 3);
                                        foreach ($ultimas as $ultima): 
                                        ?>
                                            <div class="nota-item">
                                                <span class="nota-tipo"><?php echo htmlspecialchars($ultima['tipo_evaluacion']); ?></span>
                                                <span class="nota-valor <?php echo getClaseNota($ultima['nota']); ?>">
                                                    <?php echo number_format($ultima['nota'], 1); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php else: ?>
                        <div class="sin-contenido">
                            <h3>📝 No hay notas registradas</h3>
                            <p>Comience registrando la primera nota para esta materia.</p>
                        </div>
                    <?php endif; ?>

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
            </div>
        </main>
    </div>

    <script>
        function cambiarMateria() {
            const select = document.getElementById('materia-select');
            const materiaId = select.value;
            if (materiaId) {
                window.location.href = `notas.php?materia=${materiaId}`;
            }
        }

        function filtrarNotas(filtro) {
            const tbody = document.getElementById('notas-tbody');
            const filas = tbody.getElementsByTagName('tr');
            
            for (let i = 0; i < filas.length; i++) {
                const alumno = filas[i].getElementsByTagName('td')[0].textContent;
                if (alumno.toLowerCase().includes(filtro.toLowerCase())) {
                    filas[i].style.display = '';
                } else {
                    filas[i].style.display = 'none';
                }
            }
        }

        function editarNota(notaId) {
            // Implementar edición de nota
            alert('Funcionalidad de edición en desarrollo. ID: ' + notaId);
        }

        function exportarNotas() {
            const materiaId = document.getElementById('materia-select').value;
            if (materiaId) {
                window.open(`exportar_notas.php?materia=${materiaId}`, '_blank');
            }
        }
    </script>
</body>
</html>
