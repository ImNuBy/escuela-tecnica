<?php
require_once 'config.php';
verificarLogin();

$mensaje = '';
$tipo_mensaje = '';

// Obtener datos del usuario actual
$usuario_actual = obtenerUsuarioActual($pdo);

// Obtener datos específicos según el tipo de usuario
$datos_especificos = [];
if ($usuario_actual['tipo_usuario'] == 'alumno') {
    $stmt = $pdo->prepare("
        SELECT a.*, an.año, o.nombre as orientacion_nombre, o.descripcion as orientacion_descripcion
        FROM alumnos a
        JOIN años an ON a.año_id = an.id
        JOIN orientaciones o ON an.orientacion_id = o.id
        WHERE a.usuario_id = ?
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $datos_especificos = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($usuario_actual['tipo_usuario'] == 'profesor') {
    $stmt = $pdo->prepare("
        SELECT p.*, COUNT(m.id) as total_materias
        FROM profesores p
        LEFT JOIN materias m ON p.usuario_id = m.profesor_id
        WHERE p.usuario_id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $datos_especificos = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Procesar formulario de actualización
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'actualizar_perfil') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $email = trim($_POST['email']);
    
    if (!empty($nombre) && !empty($apellido)) {
        try {
            $pdo->beginTransaction();
            
            // Actualizar tabla usuarios
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, email = ? WHERE id = ?");
            $stmt->execute([$nombre, $apellido, $email, $_SESSION['usuario_id']]);
            
            // Actualizar datos específicos según tipo de usuario
            if ($usuario_actual['tipo_usuario'] == 'alumno') {
                $telefono = trim($_POST['telefono']);
                $direccion = trim($_POST['direccion']);
                $fecha_nacimiento = $_POST['fecha_nacimiento'];
                
                $stmt = $pdo->prepare("UPDATE alumnos SET telefono = ?, direccion = ?, fecha_nacimiento = ? WHERE usuario_id = ?");
                $stmt->execute([$telefono, $direccion, $fecha_nacimiento, $_SESSION['usuario_id']]);
            } elseif ($usuario_actual['tipo_usuario'] == 'profesor') {
                $telefono = trim($_POST['telefono']);
                $especialidad = trim($_POST['especialidad']);
                
                $stmt = $pdo->prepare("UPDATE profesores SET telefono = ?, especialidad = ? WHERE usuario_id = ?");
                $stmt->execute([$telefono, $especialidad, $_SESSION['usuario_id']]);
            }
            
            $pdo->commit();
            
            // Actualizar sesión
            $_SESSION['nombre_completo'] = $nombre . ' ' . $apellido;
            
            $mensaje = 'Perfil actualizado exitosamente';
            $tipo_mensaje = 'success';
            
            // Recargar datos
            $usuario_actual = obtenerUsuarioActual($pdo);
            if ($usuario_actual['tipo_usuario'] == 'alumno') {
                $stmt = $pdo->prepare("
                    SELECT a.*, an.año, o.nombre as orientacion_nombre, o.descripcion as orientacion_descripcion
                    FROM alumnos a
                    JOIN años an ON a.año_id = an.id
                    JOIN orientaciones o ON an.orientacion_id = o.id
                    WHERE a.usuario_id = ?
                ");
                $stmt->execute([$_SESSION['usuario_id']]);
                $datos_especificos = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($usuario_actual['tipo_usuario'] == 'profesor') {
                $stmt = $pdo->prepare("
                    SELECT p.*, COUNT(m.id) as total_materias
                    FROM profesores p
                    LEFT JOIN materias m ON p.usuario_id = m.profesor_id
                    WHERE p.usuario_id = ?
                    GROUP BY p.id
                ");
                $stmt->execute([$_SESSION['usuario_id']]);
                $datos_especificos = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $mensaje = 'Error al actualizar perfil: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
    }
}

// Procesar cambio de contraseña
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'cambiar_password') {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];
    
    if (!empty($password_actual) && !empty($password_nueva) && !empty($password_confirmar)) {
        if ($password_nueva === $password_confirmar) {
            // Verificar contraseña actual
            $verificacion_ok = false;
            
            if ($usuario_actual['tipo_usuario'] == 'alumno') {
                // Para alumnos, verificar contraseña simple
                $verificacion_ok = ($password_actual === $usuario_actual['password']);
            } else {
                // Para admin/profesores, verificar hash
                $verificacion_ok = password_verify($password_actual, $usuario_actual['password']);
            }
            
            if ($verificacion_ok) {
                try {
                    if ($usuario_actual['tipo_usuario'] == 'alumno') {
                        // Para alumnos, guardar contraseña simple
                        $nueva_password = $password_nueva;
                    } else {
                        // Para admin/profesores, hashear contraseña
                        $nueva_password = password_hash($password_nueva, PASSWORD_DEFAULT);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $stmt->execute([$nueva_password, $_SESSION['usuario_id']]);
                    
                    $mensaje = 'Contraseña cambiada exitosamente';
                    $tipo_mensaje = 'success';
                } catch(PDOException $e) {
                    $mensaje = 'Error al cambiar contraseña: ' . $e->getMessage();
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'La contraseña actual es incorrecta';
                $tipo_mensaje = 'error';
            }
        } else {
            $mensaje = 'Las contraseñas nuevas no coinciden';
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos de contraseña';
        $tipo_mensaje = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Sistema Escolar</title>
    <link rel="stylesheet" href="css/base.css">
    <style>
        .perfil-container {
            display: grid;
            gap: 2rem;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .perfil-header {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .perfil-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            animation: float 20s ease-in-out infinite;
        }
        
        .perfil-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            position: relative;
            z-index: 1;
        }
        
        .perfil-info {
            position: relative;
            z-index: 1;
        }
        
        .perfil-nombre {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .perfil-tipo {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.9rem;
        }
        
        .tabs {
            display: flex;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .tab-button {
            flex: 1;
            padding: 1rem;
            background: var(--gray-100);
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            color: var(--gray-600);
        }
        
        .tab-button.active {
            background: var(--primary-color);
            color: var(--white);
        }
        
        .tab-content {
            display: none;
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .datos-readonly {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
        }
        
        .datos-readonly label {
            font-weight: 600;
            color: var(--gray-700);
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        
        .datos-readonly .valor {
            color: var(--gray-800);
            font-size: 1rem;
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
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(1deg); }
        }
        
        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .perfil-header {
                padding: 1.5rem;
            }
            
            .tab-content {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>Mi Perfil</h1>
                <p>Gestionar información personal y configuración de cuenta</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <div class="perfil-container">
                <!-- Header del perfil -->
                <div class="perfil-header">
                    <div class="perfil-avatar">
                        <?php 
                        $iconos = [
                            'administrador' => '👑',
                            'profesor' => '👨‍🏫',
                            'alumno' => '👨‍🎓'
                        ];
                        echo $iconos[$usuario_actual['tipo_usuario']] ?? '👤';
                        ?>
                    </div>
                    <div class="perfil-info">
                        <div class="perfil-nombre">
                            <?php echo htmlspecialchars($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']); ?>
                        </div>
                        <div class="perfil-tipo">
                            <?php echo ucfirst($usuario_actual['tipo_usuario']); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab-button active" onclick="cambiarTab('datos-personales')">
                        📝 Datos Personales
                    </button>
                    <button class="tab-button" onclick="cambiarTab('seguridad')">
                        🔐 Seguridad
                    </button>
                    <button class="tab-button" onclick="cambiarTab('informacion')">
                        ℹ️ Información
                    </button>
                </div>
                
                <!-- Tab Datos Personales -->
                <div id="datos-personales" class="tab-content active">
                    <h2>Información Personal</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="actualizar_perfil">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="usuario">Usuario (No editable)</label>
                                <div class="datos-readonly">
                                    <div class="valor"><?php echo htmlspecialchars($usuario_actual['usuario']); ?></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="tipo_usuario">Tipo de Usuario</label>
                                <div class="datos-readonly">
                                    <div class="valor"><?php echo ucfirst($usuario_actual['tipo_usuario']); ?></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="nombre">Nombre *</label>
                                <input type="text" id="nombre" name="nombre" required 
                                       value="<?php echo htmlspecialchars($usuario_actual['nombre']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="apellido">Apellido *</label>
                                <input type="text" id="apellido" name="apellido" required 
                                       value="<?php echo htmlspecialchars($usuario_actual['apellido']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($usuario_actual['email']); ?>">
                            </div>
                            
                            <?php if ($usuario_actual['tipo_usuario'] == 'alumno' && $datos_especificos): ?>
                                <div class="form-group">
                                    <label for="telefono">Teléfono</label>
                                    <input type="text" id="telefono" name="telefono" 
                                           value="<?php echo htmlspecialchars($datos_especificos['telefono']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                                    <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" 
                                           value="<?php echo $datos_especificos['fecha_nacimiento']; ?>">
                                </div>
                                
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="direccion">Dirección</label>
                                    <textarea id="direccion" name="direccion" rows="3"><?php echo htmlspecialchars($datos_especificos['direccion']); ?></textarea>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($usuario_actual['tipo_usuario'] == 'profesor' && $datos_especificos): ?>
                                <div class="form-group">
                                    <label for="telefono">Teléfono</label>
                                    <input type="text" id="telefono" name="telefono" 
                                           value="<?php echo htmlspecialchars($datos_especificos['telefono']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="especialidad">Especialidad</label>
                                    <input type="text" id="especialidad" name="especialidad" 
                                           value="<?php echo htmlspecialchars($datos_especificos['especialidad']); ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top: 2rem;">
                            <button type="submit" class="btn-primary">Actualizar Datos</button>
                        </div>
                    </form>
                </div>
                
                <!-- Tab Seguridad -->
                <div id="seguridad" class="tab-content">
                    <h2>Cambiar Contraseña</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="cambiar_password">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="password_actual">Contraseña Actual *</label>
                                <input type="password" id="password_actual" name="password_actual" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="password_nueva">Nueva Contraseña *</label>
                                <input type="password" id="password_nueva" name="password_nueva" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="password_confirmar">Confirmar Nueva Contraseña *</label>
                                <input type="password" id="password_confirmar" name="password_confirmar" required>
                            </div>
                        </div>
                        
                        <div style="margin-top: 2rem;">
                            <button type="submit" class="btn-primary">Cambiar Contraseña</button>
                        </div>
                        
                        <div style="margin-top: 1rem; padding: 1rem; background: var(--gray-100); border-radius: var(--border-radius);">
                            <h4>Consejos de Seguridad:</h4>
                            <ul style="margin: 0.5rem 0; padding-left: 1.5rem; font-size: 0.875rem;">
                                <li>Use una contraseña de al menos 8 caracteres</li>
                                <li>Combine letras mayúsculas, minúsculas y números</li>
                                <li>No comparta su contraseña con nadie</li>
                                <li>Cambie su contraseña regularmente</li>
                            </ul>
                        </div>
                    </form>
                </div>
                
                <!-- Tab Información -->
                <div id="informacion" class="tab-content">
                    <h2>Información de la Cuenta</h2>
                    
                    <div class="form-grid">
                        <div class="datos-readonly">
                            <label>Fecha de Creación</label>
                            <div class="valor">
                                <?php echo formatearFecha($usuario_actual['fecha_creacion']); ?>
                            </div>
                        </div>
                        
                        <div class="datos-readonly">
                            <label>Estado de la Cuenta</label>
                            <div class="valor">
                                <?php echo $usuario_actual['activo'] ? '✅ Activa' : '❌ Inactiva'; ?>
                            </div>
                        </div>
                        
                        <?php if ($usuario_actual['tipo_usuario'] == 'alumno' && $datos_especificos): ?>
                            <div class="datos-readonly">
                                <label>Año/Curso</label>
                                <div class="valor">
                                    <?php echo $datos_especificos['año']; ?>° - <?php echo htmlspecialchars($datos_especificos['orientacion_nombre']); ?>
                                </div>
                            </div>
                            
                            <div class="datos-readonly">
                                <label>DNI</label>
                                <div class="valor">
                                    <?php echo htmlspecialchars($datos_especificos['dni']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($usuario_actual['tipo_usuario'] == 'profesor' && $datos_especificos): ?>
                            <div class="datos-readonly">
                                <label>Total de Materias</label>
                                <div class="valor">
                                    <?php echo $datos_especificos['total_materias']; ?> materias asignadas
                                </div>
                            </div>
                            
                            <div class="datos-readonly">
                                <label>DNI</label>
                                <div class="valor">
                                    <?php echo htmlspecialchars($datos_especificos['dni']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($usuario_actual['tipo_usuario'] == 'alumno' && $datos_especificos): ?>
                        <div style="margin-top: 2rem; padding: 1.5rem; background: var(--gray-50); border-radius: var(--border-radius);">
                            <h3>Información Académica</h3>
                            <p><strong>Orientación:</strong> <?php echo htmlspecialchars($datos_especificos['orientacion_nombre']); ?></p>
                            <p><strong>Descripción:</strong> <?php echo htmlspecialchars($datos_especificos['orientacion_descripcion']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="js/main.js"></script>
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
        
        // Validar contraseñas coincidan
        document.getElementById('password_confirmar').addEventListener('input', function() {
            const nueva = document.getElementById('password_nueva').value;
            const confirmar = this.value;
            
            if (nueva !== confirmar) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>