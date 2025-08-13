<?php
require_once '../config.php';
verificarTipoUsuario(['administrador']);

$mensaje = '';
$tipo_mensaje = '';

// Verificar si hay mensaje desde URL
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = isset($_GET['tipo']) ? $_GET['tipo'] : 'success';
}

// Procesar formulario de nuevo usuario
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear') {
    $usuario = trim($_POST['usuario']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    $tipo_usuario = $_POST['tipo_usuario'];
    $año_id = isset($_POST['año_id']) ? $_POST['año_id'] : null;
    
    if (!empty($usuario) && !empty($nombre) && !empty($apellido) && !empty($tipo_usuario)) {
        try {
            $pdo->beginTransaction();
            
            // Generar contraseña
            if ($tipo_usuario == 'alumno') {
                $password = $usuario; // Para alumnos, la contraseña es su usuario
            } else {
                $password = password_hash('password', PASSWORD_DEFAULT); // Para admin/profesores
            }
            
            // Insertar usuario
            $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, password, tipo_usuario, nombre, apellido, email) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$usuario, $password, $tipo_usuario, $nombre, $apellido, $email]);
            $usuario_id = $pdo->lastInsertId();
            
            // Insertar datos específicos según tipo
            if ($tipo_usuario == 'alumno' && $año_id) {
                $stmt = $pdo->prepare("INSERT INTO alumnos (usuario_id, año_id) VALUES (?, ?)");
                $stmt->execute([$usuario_id, $año_id]);
            } elseif ($tipo_usuario == 'profesor') {
                $stmt = $pdo->prepare("INSERT INTO profesores (usuario_id) VALUES (?)");
                $stmt->execute([$usuario_id]);
            }
            
            $pdo->commit();
            $mensaje = 'Usuario creado exitosamente';
            $tipo_mensaje = 'success';
        } catch(PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $mensaje = 'El usuario ya existe';
            } else {
                $mensaje = 'Error al crear usuario: ' . $e->getMessage();
            }
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
    }
}

// Procesar desactivación - CORREGIDO
if ($_GET && isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    
    // Verificar que no sea el usuario admin principal
    $stmt = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $user_data = $stmt->fetch();
    
    if ($user_data && $user_data['usuario'] != 'admin') {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: usuarios.php?mensaje=Usuario desactivado exitosamente&tipo=success');
            exit();
        } catch(PDOException $e) {
            header('Location: usuarios.php?mensaje=Error al desactivar usuario&tipo=error');
            exit();
        }
    } else {
        header('Location: usuarios.php?mensaje=No se puede desactivar el usuario administrador principal&tipo=error');
        exit();
    }
}

// Obtener lista de usuarios - CONSULTA CORREGIDA
$stmt = $pdo->query("
    SELECT u.*, 
           CASE 
               WHEN u.tipo_usuario = 'alumno' THEN CONCAT(IFNULL(an.año, 'N/A'), '° - ', IFNULL(o.nombre, 'N/A'))
               WHEN u.tipo_usuario = 'profesor' THEN IFNULL(p.especialidad, 'N/A')
               ELSE 'N/A'
           END as info_adicional
    FROM usuarios u
    LEFT JOIN alumnos a ON u.id = a.usuario_id
    LEFT JOIN años an ON a.año_id = an.id
    LEFT JOIN orientaciones o ON an.orientacion_id = o.id
    LEFT JOIN profesores p ON u.id = p.usuario_id
    WHERE u.activo = 1
    ORDER BY u.tipo_usuario, u.apellido, u.nombre
");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener años para el formulario
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
    <title>Gestión de Usuarios - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/usuarios.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>👥 Gestión de Usuarios</h1>
                <p>Administrar profesores, alumnos y personal administrativo</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo h($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="usuarios-container">
                <!-- Formulario para crear usuario -->
                <div class="form-container">
                    <h2>➕ Crear Nuevo Usuario</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="crear">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="usuario">Usuario *</label>
                                <input type="text" id="usuario" name="usuario" required 
                                       placeholder="Nombre de usuario único">
                            </div>
                            
                            <div class="form-group">
                                <label for="nombre">Nombre *</label>
                                <input type="text" id="nombre" name="nombre" required 
                                       placeholder="Nombre del usuario">
                            </div>
                            
                            <div class="form-group">
                                <label for="apellido">Apellido *</label>
                                <input type="text" id="apellido" name="apellido" required 
                                       placeholder="Apellido del usuario">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" 
                                       placeholder="correo@ejemplo.com">
                            </div>
                            
                            <div class="form-group">
                                <label for="tipo_usuario">Tipo de Usuario *</label>
                                <select id="tipo_usuario" name="tipo_usuario" required onchange="mostrarCamposAdicionales()">
                                    <option value="">Seleccionar...</option>
                                    <option value="administrador">👑 Administrador</option>
                                    <option value="profesor">👨‍🏫 Profesor</option>
                                    <option value="alumno">👨‍🎓 Alumno</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="año_group" style="display: none;">
                                <label for="año_id">Año/Curso</label>
                                <select id="año_id" name="año_id">
                                    <option value="">Seleccionar año...</option>
                                    <?php foreach ($años as $año): ?>
                                        <option value="<?php echo $año['id']; ?>">
                                            <?php echo $año['año']; ?>° - <?php echo h($año['orientacion_nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <button type="submit" class="btn-primary">➕ Crear Usuario</button>
                        </div>
                        
                        <div class="password-info">
                            <h4>🔐 Información sobre contraseñas:</h4>
                            <p><strong>Administradores y Profesores:</strong> Contraseña por defecto "password" (debe cambiarse)</p>
                            <p><strong>Alumnos:</strong> Su nombre de usuario será su contraseña</p>
                        </div>
                    </form>
                </div>
                
                <!-- Lista de usuarios -->
                <div class="usuarios-table">
                    <div class="table-header">
                        <h2>📋 Lista de Usuarios <span class="user-count"><?php echo count($usuarios); ?></span></h2>
                        <input type="text" class="search-box" placeholder="🔍 Buscar usuarios..." id="searchInput">
                    </div>
                    
                    <div class="table-responsive">
                        <table id="usuariosTable">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Nombre Completo</th>
                                    <th>Email</th>
                                    <th>Tipo</th>
                                    <th>Información Adicional</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td data-label="Usuario"><?php echo h($usuario['usuario']); ?></td>
                                        <td data-label="Nombre"><?php echo h($usuario['nombre'] . ' ' . $usuario['apellido']); ?></td>
                                        <td data-label="Email"><?php echo h($usuario['email'] ?? ''); ?></td>
                                        <td data-label="Tipo">
                                            <span class="user-badge badge-<?php echo $usuario['tipo_usuario']; ?>">
                                                <?php 
                                                $iconos = [
                                                    'administrador' => '👑',
                                                    'profesor' => '👨‍🏫',
                                                    'alumno' => '👨‍🎓'
                                                ];
                                                echo $iconos[$usuario['tipo_usuario']] . ' ' . ucfirst($usuario['tipo_usuario']); 
                                                ?>
                                            </span>
                                        </td>
                                        <td data-label="Info"><?php echo h($usuario['info_adicional']); ?></td>
                                        <td data-label="Acciones">
                                            <a href="editar_usuario.php?id=<?php echo $usuario['id']; ?>" 
                                               class="btn-small btn-edit" title="Editar usuario">
                                               ✏️ Editar
                                            </a>
                                            <?php if ($usuario['usuario'] != 'admin'): ?>
                                                <a href="javascript:void(0)" 
                                                   class="btn-small btn-delete" 
                                                   onclick="confirmarDesactivacion(<?php echo $usuario['id']; ?>, '<?php echo h($usuario['nombre'] . ' ' . $usuario['apellido']); ?>')"
                                                   title="Desactivar usuario">
                                                   🗑️ Desactivar
                                                </a>
                                            <?php else: ?>
                                                <span class="btn-small" style="background: #6b7280; color: white; cursor: not-allowed;" title="No se puede desactivar">
                                                    🔒 Protegido
                                                </span>
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
        function mostrarCamposAdicionales() {
            const tipoUsuario = document.getElementById('tipo_usuario').value;
            const añoGroup = document.getElementById('año_group');
            
            if (tipoUsuario === 'alumno') {
                añoGroup.style.display = 'block';
                añoGroup.classList.add('show');
                document.getElementById('año_id').required = true;
            } else {
                añoGroup.style.display = 'none';
                añoGroup.classList.remove('show');
                document.getElementById('año_id').required = false;
            }
        }
        
        function confirmarDesactivacion(userId, nombreUsuario) {
            if (confirm(`¿Está seguro que desea DESACTIVAR al usuario:\n\n"${nombreUsuario}"?\n\n⚠️ El usuario no podrá acceder al sistema, pero se puede reactivar más tarde.`)) {
                window.location.href = `usuarios.php?eliminar=${userId}`;
            }
        }
        
        // Configurar filtro de búsqueda
        document.addEventListener('DOMContentLoaded', function() {
            filtrarTabla('searchInput', 'usuariosTable');
            
            // Resaltar filas al pasar el mouse
            const filas = document.querySelectorAll('#usuariosTable tbody tr');
            filas.forEach(fila => {
                fila.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f0f9ff';
                    this.style.transform = 'scale(1.01)';
                });
                
                fila.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>