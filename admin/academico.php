<?php
require_once '../config.php';
verificarTipoUsuario(['administrador']);

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario de nueva materia
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear_materia') {
    $nombre = trim($_POST['nombre']);
    $año_id = $_POST['año_id'];
    $profesor_id = $_POST['profesor_id'] ? $_POST['profesor_id'] : null;
    
    if (!empty($nombre) && !empty($año_id)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO materias (nombre, año_id, profesor_id) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $año_id, $profesor_id]);
            $mensaje = 'Materia creada exitosamente';
            $tipo_mensaje = 'success';
        } catch(PDOException $e) {
            $mensaje = 'Error al crear materia: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
    }
}

// Procesar formulario de nuevo horario
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear_horario') {
    $materia_id = $_POST['materia_id'];
    $dia_semana = $_POST['dia_semana'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];
    $aula = trim($_POST['aula']);
    
    if (!empty($materia_id) && !empty($dia_semana) && !empty($hora_inicio) && !empty($hora_fin)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO horarios (materia_id, dia_semana, hora_inicio, hora_fin, aula) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$materia_id, $dia_semana, $hora_inicio, $hora_fin, $aula]);
            $mensaje = 'Horario creado exitosamente';
            $tipo_mensaje = 'success';
        } catch(PDOException $e) {
            $mensaje = 'Error al crear horario: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
    }
}

// Obtener datos para los formularios
$stmt = $pdo->query("
    SELECT a.*, o.nombre as orientacion_nombre 
    FROM años a 
    JOIN orientaciones o ON a.orientacion_id = o.id 
    ORDER BY a.año, o.nombre
");
$años = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT u.id, u.nombre, u.apellido, p.especialidad
    FROM usuarios u 
    JOIN profesores p ON u.id = p.usuario_id 
    WHERE u.activo = 1 AND u.tipo_usuario = 'profesor'
    ORDER BY u.apellido, u.nombre
");
$profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener materias
$stmt = $pdo->query("
    SELECT m.*, 
           CONCAT(a.año, '° - ', o.nombre) as año_orientacion,
           CONCAT(u.nombre, ' ', u.apellido) as profesor_nombre
    FROM materias m
    JOIN años a ON m.año_id = a.id
    JOIN orientaciones o ON a.orientacion_id = o.id
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    ORDER BY a.año, o.nombre, m.nombre
");
$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener horarios
$stmt = $pdo->query("
    SELECT h.*, m.nombre as materia_nombre,
           CONCAT(a.año, '° - ', o.nombre) as año_orientacion,
           CONCAT(u.nombre, ' ', u.apellido) as profesor_nombre
    FROM horarios h
    JOIN materias m ON h.materia_id = m.id
    JOIN años a ON m.año_id = a.id
    JOIN orientaciones o ON a.orientacion_id = o.id
    LEFT JOIN usuarios u ON m.profesor_id = u.id
    ORDER BY h.dia_semana, h.hora_inicio
");
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dias_semana = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Académica - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/academico.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>Gestión Académica</h1>
                <p>Administrar materias, horarios y estructura académica</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="academico-container">
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab-button active" onclick="cambiarTab('materias')">Materias</button>
                    <button class="tab-button" onclick="cambiarTab('horarios')">Horarios</button>
                    <button class="tab-button" onclick="cambiarTab('vista-horarios')">Vista de Horarios</button>
                </div>
                
                <!-- Tab Materias -->
                <div id="materias" class="tab-content active">
                    <h2>Gestión de Materias</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="crear_materia">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nombre">Nombre de la Materia *</label>
                                <input type="text" id="nombre" name="nombre" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="año_id">Año/Curso *</label>
                                <select id="año_id" name="año_id" required>
                                    <option value="">Seleccionar año...</option>
                                    <?php foreach ($años as $año): ?>
                                        <option value="<?php echo $año['id']; ?>">
                                            <?php echo $año['año']; ?>° - <?php echo htmlspecialchars($año['orientacion_nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="profesor_id">Profesor Asignado</label>
                                <select id="profesor_id" name="profesor_id">
                                    <option value="">Sin asignar...</option>
                                    <?php foreach ($profesores as $profesor): ?>
                                        <option value="<?php echo $profesor['id']; ?>">
                                            <?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellido']); ?>
                                            <?php if ($profesor['especialidad']): ?>
                                                - <?php echo htmlspecialchars($profesor['especialidad']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary">Crear Materia</button>
                    </form>
                    
                    <div style="margin-top: 2rem;">
                        <h3>Lista de Materias</h3>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Materia</th>
                                        <th>Año/Orientación</th>
                                        <th>Profesor</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materias as $materia): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($materia['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($materia['año_orientacion']); ?></td>
                                            <td><?php echo htmlspecialchars($materia['profesor_nombre'] ?: 'Sin asignar'); ?></td>
                                            <td>
                                                <a href="editar_materia.php?id=<?php echo $materia['id']; ?>" class="btn-small btn-edit">Editar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Horarios -->
                <div id="horarios" class="tab-content">
                    <h2>Gestión de Horarios</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="crear_horario">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="materia_id_horario">Materia *</label>
                                <select id="materia_id_horario" name="materia_id" required>
                                    <option value="">Seleccionar materia...</option>
                                    <?php foreach ($materias as $materia): ?>
                                        <option value="<?php echo $materia['id']; ?>">
                                            <?php echo htmlspecialchars($materia['nombre'] . ' - ' . $materia['año_orientacion']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="dia_semana">Día de la Semana *</label>
                                <select id="dia_semana" name="dia_semana" required>
                                    <option value="">Seleccionar día...</option>
                                    <?php foreach ($dias_semana as $dia): ?>
                                        <option value="<?php echo $dia; ?>"><?php echo ucfirst($dia); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="hora_inicio">Hora de Inicio *</label>
                                <input type="time" id="hora_inicio" name="hora_inicio" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="hora_fin">Hora de Fin *</label>
                                <input type="time" id="hora_fin" name="hora_fin" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="aula">Aula</label>
                                <input type="text" id="aula" name="aula" placeholder="Ej: A101, Lab1">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary">Crear Horario</button>
                    </form>
                    
                    <div style="margin-top: 2rem;">
                        <h3>Lista de Horarios</h3>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Materia</th>
                                        <th>Año/Orientación</th>
                                        <th>Día</th>
                                        <th>Horario</th>
                                        <th>Aula</th>
                                        <th>Profesor</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($horarios as $horario): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($horario['materia_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($horario['año_orientacion']); ?></td>
                                            <td><?php echo ucfirst($horario['dia_semana']); ?></td>
                                            <td><?php echo formatearHora($horario['hora_inicio']) . ' - ' . formatearHora($horario['hora_fin']); ?></td>
                                            <td><?php echo htmlspecialchars($horario['aula']); ?></td>
                                            <td><?php echo htmlspecialchars($horario['profesor_nombre'] ?: 'Sin asignar'); ?></td>
                                            <td>
                                                <a href="editar_horario.php?id=<?php echo $horario['id']; ?>" class="btn-small btn-edit">Editar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Vista de Horarios -->
                <div id="vista-horarios" class="tab-content">
                    <h2>Vista de Horarios Semanales</h2>
                    
                    <div class="horarios-grid">
                        <?php foreach ($dias_semana as $dia): ?>
                            <div class="dia-column">
                                <div class="dia-header"><?php echo ucfirst($dia); ?></div>
                                <?php
                                $horarios_dia = array_filter($horarios, function($h) use ($dia) {
                                    return $h['dia_semana'] == $dia;
                                });
                                
                                usort($horarios_dia, function($a, $b) {
                                    return strcmp($a['hora_inicio'], $b['hora_inicio']);
                                });
                                
                                foreach ($horarios_dia as $horario):
                                ?>
                                    <div class="horario-item">
                                        <div class="horario-time">
                                            <?php echo formatearHora($horario['hora_inicio']) . ' - ' . formatearHora($horario['hora_fin']); ?>
                                        </div>
                                        <div class="horario-materia"><?php echo htmlspecialchars($horario['materia_nombre']); ?></div>
                                        <div class="horario-aula"><?php echo htmlspecialchars($horario['aula']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
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
    </script>
</body>
</html>