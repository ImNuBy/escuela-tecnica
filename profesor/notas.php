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
        .notas-container {
            display: grid;
            gap: 2rem;
        }
        
        .materia-selector {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .form-container {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .notas-table {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nota-valor {
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            text-align: center;
        }
        
        .nota-excelente {
            background: #dcfce7;
            color: #166534;
        }
        
        .nota-buena {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .nota-regular {
            background: #fef3c7;
            color: #92400e;
        }
        
        .nota-insuficiente {
            background: #fef2f2;
            color: #dc2626;
        }
        
        .resumen-alumno {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .alumno-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .alumno-nombre {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
        }
        
        .promedio {
            font-size: 1.25rem;
            font-weight: 600;
            text-align: center;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }
        
        .mensaje {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .mensaje.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .mensaje.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>Gestión de Notas</h1>
                <p>Registrar y consultar calificaciones de alumnos</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="notas-container">
                <!-- Selector de Materia -->
                <div class="materia-selector">
                    <h2>Seleccionar Materia</h2>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <select onchange="cambiarMateria(this.value)" style="flex: 1; padding: 0.75rem;">
                            <option value="">Seleccionar materia...</option>
                            <?php foreach ($materias as $materia): ?>
                                <option value="<?php echo $materia['id']; ?>" 
                                        <?php echo ($materia['id'] == $materia_seleccionada) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($materia['nombre'] . ' - ' . $materia['año_orientacion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($materia_seleccionada): ?>
                            <button onclick="exportarNotas()" class="btn-secondary">Exportar Notas</button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($materia_seleccionada && !empty($alumnos)): ?>
                    <!-- Formulario para nueva nota -->
                    <div class="form-container">
                        <h2>Registrar Nueva Nota</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="crear_nota">
                            <input type="hidden" name="materia_id" value="<?php echo $materia_seleccionada; ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="alumno_id">Alumno *</label>
                                    <select id="alumno_id" name="alumno_id" required>
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
                                    <input type="number" id="nota" name="nota" min="1" max="10" step="0.1" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="tipo_evaluacion">Tipo de Evaluación *</label>
                                    <select id="tipo_evaluacion" name="tipo_evaluacion" required>
                                        <option value="">Seleccionar tipo...</option>
                                        <option value="Examen">Examen</option>
                                        <option value="Parcial">Parcial</option>
                                        <option value="Trabajo Práctico">Trabajo Práctico</option>
                                        <option value="Proyecto">Proyecto</option>
                                        <option value="Participación">Participación</option>
                                        <option value="Tarea">Tarea</option>
                                        <option value="Oral">Evaluación Oral</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="fecha">Fecha</label>
                                    <input type="date" id="fecha" name="fecha" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="observaciones">Observaciones</label>
                                    <textarea id="observaciones" name="observaciones" rows="3" 
                                              placeholder="Comentarios adicionales sobre la evaluación"></textarea>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-primary">Registrar Nota</button>
                        </form>
                    </div>
                    
                    <!-- Resumen por Alumno -->
                    <div>
                        <h2>Resumen de Calificaciones</h2>
                        <div class="resumen-alumno">
                            <?php
                            $alumnos_con_notas = [];
                            foreach ($alumnos as $alumno) {
                                $notas_alumno = array_filter($notas, function($n) use ($alumno) {
                                    return $n['alumno_id'] == $alumno['id'];
                                });
                                
                                $promedio = 0;
                                if (!empty($notas_alumno)) {
                                    $suma = array_sum(array_column($notas_alumno, 'nota'));
                                    $promedio = $suma / count($notas_alumno);
                                }
                                
                                $alumnos_con_notas[] = [
                                    'alumno' => $alumno,
                                    'notas' => $notas_alumno,
                                    'promedio' => $promedio,
                                    'cantidad_notas' => count($notas_alumno)
                                ];
                            }
                            
                            foreach ($alumnos_con_notas as $datos):
                                $promedio_clase = '';
                                if ($datos['promedio'] >= 8) $promedio_clase = 'nota-excelente';
                                elseif ($datos['promedio'] >= 7) $promedio_clase = 'nota-buena';
                                elseif ($datos['promedio'] >= 6) $promedio_clase = 'nota-regular';
                                else $promedio_clase = 'nota-insuficiente';
                            ?>
                                <div class="alumno-card">
                                    <div class="alumno-nombre">
                                        <?php echo htmlspecialchars($datos['alumno']['apellido'] . ', ' . $datos['alumno']['nombre']); ?>
                                    </div>
                                    
                                    <div class="promedio <?php echo $promedio_clase; ?>">
                                        Promedio: <?php echo $datos['cantidad_notas'] > 0 ? number_format($datos['promedio'], 2) : 'Sin notas'; ?>
                                    </div>
                                    
                                    <div style="font-size: 0.875rem; color: var(--gray-600);">
                                        Evaluaciones: <?php echo $datos['cantidad_notas']; ?>
                                    </div>
                                    
                                    <?php if (!empty($datos['notas'])): ?>
                                        <div style="margin-top: 1rem;">
                                            <strong>Últimas notas:</strong>
                                            <?php foreach (array_slice($datos['notas'], 0, 3) as $nota): ?>
                                                <div style="display: flex; justify-content: space-between; margin-top: 0.25rem; font-size: 0.875rem;">
                                                    <span><?php echo htmlspecialchars($nota['tipo_evaluacion']); ?></span>
                                                    <span class="nota-valor <?php 
                                                        if ($nota['nota'] >= 8) echo 'nota-excelente';
                                                        elseif ($nota['nota'] >= 7) echo 'nota-buena';
                                                        elseif ($nota['nota'] >= 6) echo 'nota-regular';
                                                        else echo 'nota-insuficiente';
                                                    ?>"><?php echo $nota['nota']; ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Tabla de todas las notas -->
                    <div class="notas-table">
                        <div class="table-header">
                            <h2>Historial de Notas</h2>
                            <input type="text" class="search-box" placeholder="Buscar..." id="searchInput">
                        </div>
                        
                        <div class="table-responsive">
                            <table id="notasTable">
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
                                <tbody>
                                    <?php foreach ($notas as $nota): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($nota['apellido'] . ', ' . $nota['nombre']); ?></td>
                                            <td>
                                                <span class="nota-valor <?php 
                                                    if ($nota['nota'] >= 8) echo 'nota-excelente';
                                                    elseif ($nota['nota'] >= 7) echo 'nota-buena';
                                                    elseif ($nota['nota'] >= 6) echo 'nota-regular';
                                                    else echo 'nota-insuficiente';
                                                ?>"><?php echo $nota['nota']; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($nota['tipo_evaluacion']); ?></td>
                                            <td><?php echo formatearFecha($nota['fecha']); ?></td>
                                            <td><?php echo htmlspecialchars($nota['observaciones']); ?></td>
                                            <td>
                                                <a href="editar_nota.php?id=<?php echo $nota['id']; ?>" class="btn-small btn-edit">Editar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                <?php elseif ($materia_seleccionada): ?>
                    <div style="text-align: center; padding: 3rem; background: var(--white); border-radius: var(--border-radius);">
                        <h3>No hay alumnos inscriptos en esta materia</h3>
                        <p>Contacte al administrador para verificar la asignación de alumnos.</p>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; background: var(--white); border-radius: var(--border-radius);">
                        <h3>Seleccione una materia para comenzar</h3>
                        <p>Elija una materia del selector superior para gestionar las notas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../js/main.js"></script>
    <script>
        function cambiarMateria(materiaId) {
            if (materiaId) {
                window.location.href = 'notas.php?materia=' + materiaId;
            }
        }
        
        function exportarNotas() {
            exportarExcel('notasTable', 'notas_materia_' + <?php echo $materia_seleccionada; ?>);
        }
        
        // Configurar filtro de búsqueda
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('notasTable')) {
                filtrarTabla('searchInput', 'notasTable');
            }
        });
    </script>
</body>
</html>