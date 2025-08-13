<?php
require_once '../config.php';
verificarTipoUsuario(['administrador']);

$mensaje = '';
$tipo_mensaje = '';
$usuario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$usuario_id) {
    header('Location: usuarios.php');
    exit();
}

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: usuarios.php?mensaje=Usuario no encontrado&tipo=error');
    exit();
}

// Obtener datos específicos según el tipo
$datos_especificos = [];
if ($usuario['tipo_usuario'] == 'alumno') {
    $stmt = $pdo->prepare("
        SELECT a.*, an.año, o.nombre as orientacion_nombre 
        FROM alumnos a
        JOIN años an ON a.año_id = an.id
        JOIN orientaciones o ON an.orientacion_id = o.id
        WHERE a.usuario_id = ?
    ");
    $stmt->execute([$usuario_id]);
    $datos_especificos = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($usuario['tipo_usuario'] == 'profesor') {
    $stmt = $pdo->prepare("SELECT * FROM profesores WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $datos_especificos = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Procesar formulario de actualización
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'actualizar') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if (!empty($nombre) && !empty($apellido)) {
        try {
            $pdo->beginTransaction();
            
            // Actualizar tabla usuarios
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $apellido, $email, $activo, $usuario_id]);
            
            // Actualizar datos específicos según tipo
            if ($usuario['tipo_usuario'] == 'alumno') {
                $dni = trim($_POST['dni']);
                $telefono = trim($_POST['telefono']);
                $direccion = trim($_POST['direccion']);
                $fecha_nacimiento = $_POST['fecha_nacimiento'];
                $año_id = $_POST['año_id'];
                
                $stmt = $pdo->prepare("UPDATE alumnos SET dni = ?, telefono = ?, direccion = ?, fecha_nacimiento = ?, año_id = ? WHERE usuario_id = ?");
                $stmt->execute([$dni, $telefono, $direccion, $fecha_nacimiento, $año_id, $usuario_id]);
            } elseif ($usuario['tipo_usuario'] == 'profesor') {
                $dni = trim($_POST['dni']);
                $telefono = trim($_POST['telefono']);
                $especialidad = trim($_POST['especialidad']);
                
                $stmt = $pdo->prepare("UPDATE profesores SET dni = ?, telefono = ?, especialidad = ? WHERE usuario_id = ?");
                $stmt->execute([$dni, $telefono, $especialidad, $usuario_id]);
            }
            
            $pdo->commit();
            $mensaje = 'Usuario actualizado exitosamente';
            $tipo_mensaje = 'success';
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $mensaje = 'Error al actualizar usuario: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
    }
}

// Obtener años para alumnos
$stmt = $pdo->query("
    SELECT a.*, o.nombre as orientacion_nombre 
    FROM años a 
    JOIN orientaciones o ON a.orientacion_id = o.id 
    ORDER BY a.año, o.nombre
");
$años = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/usuarios.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>✏️ Editar Usuario</h1>
                <p>Modificar información de: <?php echo h($usuario['nombre'] . ' ' . $usuario['apellido']); ?></p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo h($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="usuarios-container">
                <div class="form-container">
                    <h2>Información del Usuario</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="actualizar">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="usuario">Usuario (No editable)</label>
                                <input type="text" id="usuario" value="<?php echo h($usuario['usuario']); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="tipo_usuario">Tipo de Usuario</label>
                                <input type="text" id="tipo_usuario" value="<?php echo ucfirst($usuario['tipo_usuario']); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="nombre">Nombre *</label>
                                <input type="text" id="nombre" name="nombre" required 
                                       value="<?php echo h($usuario['nombre']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="apellido">Apellido *</label>
                                <input type="text" id="apellido" name="apellido" required 
                                       value="<?php echo h($usuario['apellido']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo h($usuario['email']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="activo" <?php echo $usuario['activo'] ? 'checked' : ''; ?>>
                                    Usuario activo
                                </label>
                            </div>
                        </div>
                        
                        <?php if ($usuario['tipo_usuario'] == 'alumno' && $datos_especificos): ?>
                            <h3>Información de Alumno</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="dni">DNI</label>
                                    <input type="text" id="dni" name="dni" 
                                           value="<?php echo h($datos_especificos['dni']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="telefono">Teléfono</label>
                                    <input type="text" id="telefono" name="telefono" 
                                           value="<?php echo h($datos_especificos['telefono']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" 
                                           value="<?php echo $datos_especificos['fecha_nacimiento']; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="año_id">Año/Curso</label>
                                    <select id="año_id" name="año_id">
                                        <?php foreach ($años as $año): ?>
                                            <option value="<?php echo $año['id']; ?>" 
                                                    <?php echo ($datos_especificos['año_id'] == $año['id']) ? 'selected' : ''; ?>>
                                                <?php echo $año['año']; ?>° - <?php echo h($año['orientacion_nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="direccion">Dirección</label>
                                    <textarea id="direccion" name="direccion" rows="3"><?php echo h($datos_especificos['direccion']); ?></textarea>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($usuario['tipo_usuario'] == 'profesor' && $datos_especificos): ?>
                            <h3>Información de Profesor</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="dni">DNI</label>
                                    <input type="text" id="dni" name="dni" 
                                           value="<?php echo h($datos_especificos['dni']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="telefono">Teléfono</label>
                                    <input type="text" id="telefono" name="telefono" 
                                           value="<?php echo h($datos_especificos['telefono']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="especialidad">Especialidad</label>
                                    <input type="text" id="especialidad" name="especialidad" 
                                           value="<?php echo h($datos_especificos['especialidad']); ?>">
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                            <button type="submit" class="btn-primary">💾 Guardar Cambios</button>
                            <a href="usuarios.php" class="btn-secondary">↩️ Volver</a>
                            <?php if ($usuario['usuario'] != 'admin'): ?>
                                <button type="button" onclick="confirmarEliminacion(<?php echo $usuario_id; ?>)" class="btn-danger">
                                    🗑️ Desactivar Usuario
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../js/main.js"></script>
    <script>
        function confirmarEliminacion(userId) {
            if (confirm('¿Está seguro que desea desactivar este usuario?\n\nEsta acción se puede revertir.')) {
                window.location.href = `usuarios.php?eliminar=${userId}`;
            }
        }
    </script>
</body>
</html>