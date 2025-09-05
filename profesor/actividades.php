<?php
require_once '../config.php';
verificarTipoUsuario(['profesor']);

$mensaje = '';
$tipo_mensaje = '';

// Obtener ID del profesor
try {
    $stmt = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profesor) {
        throw new Exception("No se encontró el profesor en la base de datos");
    }
    
    $profesor_id = $profesor['id'];
} catch (Exception $e) {
    die("Error al obtener datos del profesor: " . $e->getMessage());
}

// Procesar formulario de nueva actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'crear_actividad') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $materia_id = $_POST['materia_id'] ?? '';
    $fecha_entrega = !empty($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : null;
    
    if (!empty($titulo) && !empty($descripcion) && !empty($materia_id)) {
        try {
            // Verificar que la materia pertenezca al profesor
            $stmt = $pdo->prepare("SELECT id FROM materias WHERE id = ? AND profesor_id = ?");
            $stmt->execute([$materia_id, $_SESSION['usuario_id']]);
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO actividades (titulo, descripcion, materia_id, profesor_id, fecha_entrega) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$titulo, $descripcion, $materia_id, $profesor_id, $fecha_entrega]);
                $mensaje = 'Actividad creada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'No tiene permisos para crear actividades en esta materia';
                $tipo_mensaje = 'error';
            }
        } catch(PDOException $e) {
            $mensaje = 'Error al crear actividad: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
    }
}

// Procesar edición de actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'editar_actividad') {
    $id = $_POST['actividad_id'] ?? '';
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_entrega = !empty($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : null;
    
    if (!empty($titulo) && !empty($descripcion) && !empty($id)) {
        try {
            $stmt = $pdo->prepare("UPDATE actividades SET titulo = ?, descripcion = ?, fecha_entrega = ? WHERE id = ? AND profesor_id = ?");
            $stmt->execute([$titulo, $descripcion, $fecha_entrega, $id, $profesor_id]);
            
            if ($stmt->rowCount() > 0) {
                $mensaje = 'Actividad actualizada exitosamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'No se pudo actualizar la actividad o no tiene permisos';
                $tipo_mensaje = 'error';
            }
        } catch(PDOException $e) {
            $mensaje = 'Error al actualizar actividad: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Faltan datos obligatorios para actualizar la actividad';
        $tipo_mensaje = 'error';
    }
}

// Procesar eliminación de actividad
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $actividad_id = (int)$_GET['eliminar'];
    try {
        $stmt = $pdo->prepare("UPDATE actividades SET activo = 0 WHERE id = ? AND profesor_id = ?");
        $stmt->execute([$actividad_id, $profesor_id]);
        
        if ($stmt->rowCount() > 0) {
            $mensaje = 'Actividad eliminada exitosamente';
            $tipo_mensaje = 'success';
        } else {
            $mensaje = 'No se pudo eliminar la actividad o no tiene permisos';
            $tipo_mensaje = 'error';
        }
    } catch(PDOException $e) {
        $mensaje = 'Error al eliminar actividad: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Obtener materias del profesor
try {
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
} catch(PDOException $e) {
    die("Error al obtener materias: " . $e->getMessage());
}

// Obtener materia seleccionada
$materia_seleccionada = isset($_GET['materia']) && is_numeric($_GET['materia']) ? (int)$_GET['materia'] : 0;

// Si no se seleccionó materia válida, usar la primera disponible
if ($materia_seleccionada === 0 && count($materias) > 0) {
    $materia_seleccionada = $materias[0]['id'];
}

// Validar que la materia seleccionada pertenezca al profesor
if ($materia_seleccionada > 0) {
    $materia_valida = false;
    foreach ($materias as $materia) {
        if ($materia['id'] == $materia_seleccionada) {
            $materia_valida = true;
            break;
        }
    }
    if (!$materia_valida) {
        $materia_seleccionada = 0;
    }
}

// Obtener actividades de la materia seleccionada
$actividades = [];
if ($materia_seleccionada > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, m.nombre as materia_nombre,
                   COUNT(DISTINCT al.id) as total_alumnos,
                   COUNT(DISTINCT e.id) as total_entregas
            FROM actividades a
            JOIN materias m ON a.materia_id = m.id
            LEFT JOIN años an ON m.año_id = an.id
            LEFT JOIN alumnos al ON al.año_id = an.id
            LEFT JOIN usuarios u ON al.usuario_id = u.id AND u.activo = 1
            LEFT JOIN entregas_actividades e ON e.actividad_id = a.id
            WHERE a.materia_id = ? AND a.profesor_id = ? AND a.activo = 1
            GROUP BY a.id
            ORDER BY a.fecha_creacion DESC
        ");
        $stmt->execute([$materia_seleccionada, $profesor_id]);
        $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $mensaje = 'Error al obtener actividades: ' . $e->getMessage();
        $tipo_mensaje = 'error';
        $actividades = [];
    }
}

// Si se está editando una actividad específica
$actividad_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM actividades WHERE id = ? AND profesor_id = ?");
        $stmt->execute([$_GET['editar'], $profesor_id]);
        $actividad_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $mensaje = 'Error al obtener actividad para editar: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Función para determinar el estado de la actividad
function getEstadoActividad($fecha_entrega) {
    if (!$fecha_entrega) return ['estado' => 'sin_fecha', 'clase' => 'estado-sin-fecha', 'texto' => 'Sin fecha límite'];
    
    try {
        $hoy = new DateTime();
        $fecha_limite = new DateTime($fecha_entrega);
        
        if ($fecha_limite < $hoy) {
            return ['estado' => 'vencida', 'clase' => 'estado-vencida', 'texto' => 'Vencida'];
        } elseif ($fecha_limite->diff($hoy)->days <= 3) {
            return ['estado' => 'proxima', 'clase' => 'estado-proxima', 'texto' => 'Próxima a vencer'];
        } else {
            return ['estado' => 'activa', 'clase' => 'estado-activa', 'texto' => 'Activa'];
        }
    } catch (Exception $e) {
        return ['estado' => 'error', 'clase' => 'estado-error', 'texto' => 'Error en fecha'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Actividades - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/actividades.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="content">
            <div class="page-header">
                <h1>📝 Gestión de Actividades</h1>
                <p>Crear y administrar tareas y trabajos prácticos</p>
            </div>

            <!-- Mostrar mensajes -->
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo htmlspecialchars($tipo_mensaje); ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="actividades-container">
                <!-- Selector de Materia -->
                <div class="materia-selector">
                    <h2>📚 Seleccionar Materia</h2>
                    <div class="selector-grid">
                        <select id="materia-select" onchange="cambiarMateria()">
                            <option value="">Seleccionar materia...</option>
                            <?php foreach ($materias as $materia): ?>
                                <option value="<?php echo $materia['id']; ?>" 
                                        <?php echo $materia['id'] == $materia_seleccionada ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($materia['nombre'] . ' - ' . $materia['año_orientacion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if ($materia_seleccionada && !$actividad_editar): ?>
                            <button type="button" class="btn btn-primary" onclick="mostrarFormulario()">
                                ➕ Nueva Actividad
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($materia_seleccionada): ?>
                    <!-- Formulario para nueva actividad o edición -->
                    <div class="form-container" id="form-container" style="<?php echo $actividad_editar ? '' : 'display: none;'; ?>">
                        <div class="form-header">
                            <h2><?php echo $actividad_editar ? '✏️ Editar Actividad' : '📝 Nueva Actividad'; ?></h2>
                            <?php if (!$actividad_editar): ?>
                                <button type="button" class="btn btn-secondary" onclick="ocultarFormulario()">
                                    ❌ Cancelar
                                </button>
                            <?php else: ?>
                                <a href="actividades.php?materia=<?php echo $materia_seleccionada; ?>" class="btn btn-secondary">
                                    ❌ Cancelar
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="<?php echo $actividad_editar ? 'editar_actividad' : 'crear_actividad'; ?>">
                            <?php if ($actividad_editar): ?>
                                <input type="hidden" name="actividad_id" value="<?php echo $actividad_editar['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="materia_id" value="<?php echo $materia_seleccionada; ?>">
                            <?php endif; ?>
                            
                            <div class="form-grid">
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="titulo">Título de la Actividad *</label>
                                    <input type="text" name="titulo" id="titulo" required 
                                           value="<?php echo $actividad_editar ? htmlspecialchars($actividad_editar['titulo']) : ''; ?>"
                                           placeholder="Ej: Trabajo Práctico N° 1 - Variables y Estructuras">
                                </div>

                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="descripcion">Descripción y Consignas *</label>
                                    <textarea name="descripcion" id="descripcion" rows="6" required 
                                            placeholder="Describe detalladamente la actividad, objetivos, consignas y criterios de evaluación..."><?php echo $actividad_editar ? htmlspecialchars($actividad_editar['descripcion']) : ''; ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="fecha_entrega">Fecha de Entrega</label>
                                    <input type="date" name="fecha_entrega" id="fecha_entrega"
                                           value="<?php echo $actividad_editar && $actividad_editar['fecha_entrega'] ? $actividad_editar['fecha_entrega'] : ''; ?>"
                                           min="<?php echo date('Y-m-d'); ?>">
                                    <small>Opcional - Dejar vacío si no hay fecha límite</small>
                                </div>
                            </div>

                            <div style="margin-top: 1.5rem;">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $actividad_editar ? '💾 Actualizar Actividad' : '🚀 Crear Actividad'; ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Lista de actividades -->
                    <?php if (count($actividades) > 0): ?>
                        <div class="actividades-lista">
                            <div class="lista-header">
                                <h2>📋 Actividades Creadas (<?php echo count($actividades); ?>)</h2>
                                <input type="text" class="search-box" placeholder="Buscar actividad..." 
                                       onkeyup="filtrarActividades(this.value)">
                            </div>

                            <div class="actividades-grid" id="actividades-grid">
                                <?php foreach ($actividades as $actividad): ?>
                                    <?php $estado = getEstadoActividad($actividad['fecha_entrega']); ?>
                                    <div class="actividad-card" data-titulo="<?php echo htmlspecialchars(strtolower($actividad['titulo'])); ?>">
                                        <div class="actividad-header">
                                            <div class="actividad-titulo">
                                                <?php echo htmlspecialchars($actividad['titulo']); ?>
                                            </div>
                                            <div class="estado-badge <?php echo $estado['clase']; ?>">
                                                <?php echo $estado['texto']; ?>
                                            </div>
                                        </div>

                                        <div class="actividad-meta">
                                            <span>📅 Creada: <?php echo date('d/m/Y', strtotime($actividad['fecha_creacion'])); ?></span>
                                            <?php if ($actividad['fecha_entrega']): ?>
                                                <span>⏰ Entrega: <?php echo date('d/m/Y', strtotime($actividad['fecha_entrega'])); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="actividad-descripcion">
                                            <?php 
                                            $descripcion = htmlspecialchars($actividad['descripcion']);
                                            echo strlen($descripcion) > 150 ? substr($descripcion, 0, 150) . '...' : $descripcion;
                                            ?>
                                        </div>

                                        <div class="actividad-stats">
                                            <div class="stat-item">
                                                <span class="stat-number"><?php echo $actividad['total_alumnos']; ?></span>
                                                <span class="stat-label">Alumnos</span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-number"><?php echo $actividad['total_entregas']; ?></span>
                                                <span class="stat-label">Entregas</span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-number">
                                                    <?php echo $actividad['total_alumnos'] > 0 ? round(($actividad['total_entregas'] / $actividad['total_alumnos']) * 100) : 0; ?>%
                                                </span>
                                                <span class="stat-label">Completado</span>
                                            </div>
                                        </div>

                                        <div class="actividad-acciones">
                                            <a href="ver_entregas.php?actividad=<?php echo $actividad['id']; ?>" 
                                               class="btn btn-small btn-primary">
                                                👥 Ver Entregas
                                            </a>
                                            <a href="actividades.php?materia=<?php echo $materia_seleccionada; ?>&editar=<?php echo $actividad['id']; ?>" 
                                               class="btn btn-small btn-warning">
                                                ✏️ Editar
                                            </a>
                                            <button type="button" class="btn btn-small btn-danger" 
                                                    onclick="confirmarEliminar(<?php echo $actividad['id']; ?>, '<?php echo addslashes($actividad['titulo']); ?>')">
                                                🗑️ Eliminar
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="sin-contenido">
                            <h3>📝 No hay actividades creadas</h3>
                            <p>Comience creando su primera actividad para esta materia.</p>
                            <button type="button" class="btn btn-primary" onclick="mostrarFormulario()">
                                ➕ Crear Primera Actividad
                            </button>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="sin-contenido">
                        <h3>📚 Selecciona una materia</h3>
                        <p>Debe seleccionar una materia para gestionar sus actividades.</p>
                        <?php if (empty($materias)): ?>
                            <p style="color: var(--warning-color); margin-top: 1rem;">
                                <strong>No tiene materias asignadas.</strong> Contacte al administrador para que le asigne materias.
                            </p>
                        <?php endif; ?>
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
                window.location.href = `actividades.php?materia=${materiaId}`;
            } else {
                window.location.href = 'actividades.php';
            }
        }

        function mostrarFormulario() {
            const formContainer = document.getElementById('form-container');
            if (formContainer) {
                formContainer.style.display = 'block';
                formContainer.scrollIntoView({ behavior: 'smooth' });
                
                const tituloInput = document.getElementById('titulo');
                if (tituloInput) {
                    setTimeout(() => tituloInput.focus(), 500);
                }
            }
        }

        function ocultarFormulario() {
            const formContainer = document.getElementById('form-container');
            if (formContainer) {
                formContainer.style.display = 'none';
            }
        }

        function filtrarActividades(filtro) {
            const grid = document.getElementById('actividades-grid');
            if (!grid) return;
            
            const cards = grid.getElementsByClassName('actividad-card');
            const filtroLower = filtro.toLowerCase();
            
            for (let i = 0; i < cards.length; i++) {
                const titulo = cards[i].dataset.titulo || '';
                if (titulo.includes(filtroLower)) {
                    cards[i].style.display = '';
                } else {
                    cards[i].style.display = 'none';
                }
            }
        }

        function confirmarEliminar(actividadId, titulo) {
            if (confirm(`¿Está seguro que desea eliminar la actividad "${titulo}"?\n\nEsta acción no se puede deshacer.`)) {
                window.location.href = `actividades.php?materia=<?php echo $materia_seleccionada; ?>&eliminar=${actividadId}`;
            }
        }

        // Auto-ocultar mensajes después de 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const mensajes = document.querySelectorAll('.mensaje');
            mensajes.forEach(function(mensaje) {
                setTimeout(function() {
                    mensaje.style.opacity = '0';
                    setTimeout(function() {
                        mensaje.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>