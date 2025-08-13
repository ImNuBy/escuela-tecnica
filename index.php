<?php
require_once 'config.php';

$error = '';

if ($_POST) {
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);
    
    if (!empty($usuario) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Para administradores y profesores verificar password hasheado
                if ($user['tipo_usuario'] != 'alumno') {
                    if (password_verify($password, $user['password'])) {
                        $_SESSION['usuario_id'] = $user['id'];
                        $_SESSION['usuario'] = $user['usuario'];
                        $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
                        $_SESSION['nombre_completo'] = $user['nombre'] . ' ' . $user['apellido'];
                        
                        header('Location: dashboard.php');
                        exit();
                    } else {
                        $error = 'Usuario o contraseña incorrectos';
                    }
                } else {
                    // Para alumnos, password simple
                    if ($password === $user['password']) {
                        $_SESSION['usuario_id'] = $user['id'];
                        $_SESSION['usuario'] = $user['usuario'];
                        $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
                        $_SESSION['nombre_completo'] = $user['nombre'] . ' ' . $user['apellido'];
                        
                        header('Location: dashboard.php');
                        exit();
                    } else {
                        $error = 'Usuario o contraseña incorrectos';
                    }
                }
            } else {
                $error = 'Usuario o contraseña incorrectos';
            }
        } catch(PDOException $e) {
            $error = 'Error del sistema. Intente nuevamente.';
        }
    } else {
        $error = 'Por favor complete todos los campos';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Escolar - Login</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="school-logo">
                <h1>Escuela Técnica</h1>
                <p>Sistema de Gestión Escolar</p>
            </div>
            
            <form method="POST" class="login-form">
                <h2>Iniciar Sesión</h2>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="usuario">Usuario:</label>
                    <input type="text" id="usuario" name="usuario" required 
                           value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn-login">Ingresar</button>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <p style="color: var(--gray-600); font-size: 0.9rem;">
                        ¿Eres un nuevo alumno? 
                        <a href="registro.php" style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                            Registrarse aquí
                        </a>
                    </p>
                </div>
            </form>
            
            <div class="user-types">
                <h3>Tipos de Usuario:</h3>
                <div class="user-type">
                    <strong>Administradores:</strong> Director y Secretario
                </div>
                <div class="user-type">
                    <strong>Profesores:</strong> Acceso con credenciales asignadas
                </div>
                <div class="user-type">
                    <strong>Alumnos:</strong> Acceso con usuario y contraseña personal
                </div>
            </div>
            
            <div class="demo-users">
                <h4>Usuarios de prueba:</h4>
                <p><strong>Admin:</strong> admin / password</p>
                <p><strong>Profesor:</strong> prof001 / password</p>
                <p><strong>Alumno:</strong> alumno001 / alumno001</p>
            </div>
        </div>
    </div>
</body>
</html>