<?php
require_once 'config.php';

$mensaje = '';
$tipo_mensaje = '';
$registro_exitoso = false;

if ($_POST) {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $dni = trim($_POST['dni']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $año_id = $_POST['año_id'];
    
    // Generar usuario automáticamente
    $usuario = strtolower($nombre . substr($apellido, 0, 3) . substr($dni, -3));
    $password = $usuario; // La contraseña es igual al usuario para alumnos
    
    if (!empty($nombre) && !empty($apellido) && !empty($dni) && !empty($año_id)) {
        try {
            $pdo->beginTransaction();
            
            // Verificar si el DNI ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM alumnos WHERE dni = ?");
            $stmt->execute([$dni]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Ya existe un alumno registrado con este DNI");
            }
            
            // Verificar si el usuario ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->fetchColumn() > 0) {
                // Si existe, agregar un número al final
                $contador = 1;
                $usuario_temp = $usuario;
                do {
                    $usuario_temp = $usuario . $contador;
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = ?");
                    $stmt->execute([$usuario_temp]);
                    $contador++;
                } while ($stmt->fetchColumn() > 0);
                $usuario = $usuario_temp;
                $password = $usuario;
            }
            
            // Insertar usuario
            $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, password, tipo_usuario, nombre, apellido, email) VALUES (?, ?, 'alumno', ?, ?, ?)");
            $stmt->execute([$usuario, $password, $nombre, $apellido, $email]);
            $usuario_id = $pdo->lastInsertId();
            
            // Insertar alumno
            $stmt = $pdo->prepare("INSERT INTO alumnos (usuario_id, año_id, dni, telefono, direccion, fecha_nacimiento) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_id, $año_id, $dni, $telefono, $direccion, $fecha_nacimiento]);
            
            $pdo->commit();
            $registro_exitoso = true;
            $mensaje = "Registro exitoso. Tu usuario es: <strong>$usuario</strong> y tu contraseña es: <strong>$password</strong>";
            $tipo_mensaje = 'success';
        } catch(Exception $e) {
            $pdo->rollBack();
            $mensaje = 'Error al registrarse: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
    }
}

// Obtener años disponibles
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
    <title>Registro de Alumno - Sistema Escolar</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/login.css">
    <style>
        .registro-form {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .form-grid-registro {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .registro-exitoso {
            background: #dcfce7;
            color: #166534;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 1rem;
            border: 1px solid #bbf7d0;
        }
        
        .credenciales {
            background: var(--gray-100);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .btn-volver {
            background: var(--gray-600);
            color: var(--white);
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            display: inline-block;
            margin-top: 1rem;
            transition: background-color 0.3s;
        }
        
        .btn-volver:hover {
            background: var(--gray-700);
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="school-logo">
                <h1>Escuela Técnica</h1>
                <p>Registro de Nuevo Alumno</p>
            </div>
            
            <?php if ($registro_exitoso): ?>
                <div class="registro-exitoso">
                    <h3>¡Registro Exitoso! 🎉</h3>
                    <p><?php echo $mensaje; ?></p>
                    <div class="credenciales">
                        <p><strong>Guarda estas credenciales:</strong></p>
                        <p>📱 Usuario: <strong><?php echo htmlspecialchars($usuario); ?></strong></p>
                        <p>🔑 Contraseña: <strong><?php echo htmlspecialchars($password); ?></strong></p>
                    </div>
                    <a href="index.php" class="btn-volver">Ir al Login</a>
                </div>
            <?php else: ?>
                <form method="POST" class="login-form registro-form">
                    <h2>Registrarse como Alumno</h2>
                    
                    <?php if ($mensaje): ?>
                        <div class="error-message"><?php echo $mensaje; ?></div>
                    <?php endif; ?>
                    
                    <div class="form-grid-registro">
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" id="nombre" name="nombre" required 
                                   value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="apellido">Apellido *</label>
                            <input type="text" id="apellido" name="apellido" required 
                                   value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="dni">DNI *</label>
                            <input type="text" id="dni" name="dni" required 
                                   value="<?php echo isset($_POST['dni']) ? htmlspecialchars($_POST['dni']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="text" id="telefono" name="telefono" 
                                   value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" 
                                   value="<?php echo isset($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : ''; ?>">
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="año_id">Año/Curso a Inscribirse *</label>
                            <select id="año_id" name="año_id" required>
                                <option value="">Seleccionar año...</option>
                                <?php foreach ($años as $año): ?>
                                    <option value="<?php echo $año['id']; ?>" 
                                            <?php echo (isset($_POST['año_id']) && $_POST['año_id'] == $año['id']) ? 'selected' : ''; ?>>
                                        <?php echo $año['año']; ?>° Año - <?php echo htmlspecialchars($año['orientacion_nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="direccion">Dirección</label>
                            <textarea id="direccion" name="direccion" rows="2" 
                                      placeholder="Dirección completa"><?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">Registrarse</button>
                    
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="index.php" class="btn-volver">Volver al Login</a>
                    </div>
                </form>
                
                <div class="user-types">
                    <h3>Información Importante:</h3>
                    <div class="user-type">
                        <strong>Usuario Automático:</strong> Se generará automáticamente basado en tu nombre y DNI
                    </div>
                    <div class="user-type">
                        <strong>Contraseña:</strong> Será igual a tu nombre de usuario
                    </div>
                    <div class="user-type">
                        <strong>Activación:</strong> Tu cuenta estará activa inmediatamente después del registro
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>