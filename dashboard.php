<?php
require_once 'config.php';
verificarLogin();

$usuario_actual = obtenerUsuarioActual($pdo);

// Obtener estadísticas según el tipo de usuario
$estadisticas = [];

if ($usuario_actual['tipo_usuario'] == 'administrador') {
    // Estadísticas para administradores
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'alumno' AND activo = 1");
    $estadisticas['total_alumnos'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'profesor' AND activo = 1");
    $estadisticas['total_profesores'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM materias");
    $estadisticas['total_materias'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM noticias WHERE activo = 1");
    $estadisticas['total_noticias'] = $stmt->fetch()['total'];
}

// Obtener noticias recientes
$stmt = $pdo->prepare("
    SELECT n.*, u.nombre, u.apellido 
    FROM noticias n 
    JOIN usuarios u ON n.autor_id = u.id 
    WHERE n.activo = 1 
    ORDER BY n.fecha_publicacion DESC 
    LIMIT 5
");
$stmt->execute();
$noticias_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema Escolar</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>Panel de Control</h1>
                <p>Bienvenido, <?php echo htmlspecialchars($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']); ?></p>
            </div>
            
            <?php if ($usuario_actual['tipo_usuario'] == 'administrador'): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['total_alumnos']; ?></h3>
                            <p>Alumnos Activos</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">👨‍🏫</div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['total_profesores']; ?></h3>
                            <p>Profesores</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📚</div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['total_materias']; ?></h3>
                            <p>Materias</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📢</div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['total_noticias']; ?></h3>
                            <p>Noticias Activas</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="dashboard-content">
                <div class="panel-grid">
                    <?php if ($usuario_actual['tipo_usuario'] == 'administrador'): ?>
                        <div class="panel-card">
                            <h3>Gestión de Usuarios</h3>
                            <p>Administrar profesores, alumnos y sus datos</p>
                            <a href="admin/usuarios.php" class="btn-primary">Acceder</a>
                        </div>
                        
                        <div class="panel-card">
                            <h3>Gestión Académica</h3>
                            <p>Materias, horarios y años</p>
                            <a href="admin/academico.php" class="btn-primary">Acceder</a>
                        </div>
                        
                        <div class="panel-card">
                            <h3>Asistencias</h3>
                            <p>Control de asistencias de alumnos y profesores</p>
                            <a href="admin/asistencias.php" class="btn-primary">Acceder</a>
                        </div>
                        
                        <div class="panel-card">
                            <h3>Reportes</h3>
                            <p>Reportes y comunicados de alumnos</p>
                            <a href="admin/reportes.php" class="btn-primary">Acceder</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($usuario_actual['tipo_usuario'] == 'profesor'): ?>
                        <div class="panel-card">
                            <h3>Mis Materias</h3>
                            <p>Gestionar materias asignadas</p>
                            <a href="profesor/materias.php" class="btn-primary">Acceder</a>
                        </div>
                        
                        <div class="panel-card">
                            <h3>Notas y Evaluaciones</h3>
                            <p>Registrar notas de alumnos</p>
                            <a href="profesor/notas.php" class="btn-primary">Acceder</a>
                        </div>
                        
                        <div class="panel-card">
                            <h3>Asistencias</h3>
                            <p>Control de asistencias</p>
                            <a href="profesor/asistencias.php" class="btn-primary">Acceder</a>
                        </div>
                        
                        <div class="panel-card">
                            <h3>Actividades</h3>
                            <p>Crear y gestionar actividades</p>
                            <a href="profesor/actividades.php" class="btn-primary">Acceder</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($usuario_actual['tipo_usuario'] == 'alumno'): ?>
                        <div class="panel-card">
                            <h3>Mis Materias</h3>
                            <p>Ver materias y horarios</p>
                            <a href="alumno/materias.php" class="btn-primary">Acceder</a>
                        </div>
                        
                        <div class="panel-card">
                            <h3>Mis Notas</h3>
                            <p>Consultar calificaciones</p>
                            <a href="alumno/notas.php" class="btn-primary">Acceder</a>
                        </div>
                        
                        <div class="panel-card">
                            <h3>Asistencias</h3>
                            <p>Ver registro de asistencias</p>
                            <a href="alumno/asistencias.php" class="btn-primary">Acceder</a>
                        </div>
                        
                        <div class="panel-card">
                            <h3>Actividades</h3>
                            <p>Ver actividades asignadas</p>
                            <a href="alumno/actividades.php" class="btn-primary">Acceder</a>
                        </div>
                        
                        <div class="panel-card">
                            <h3>Reportar</h3>
                            <p>Enviar reportes o comunicaciones</p>
                            <a href="alumno/reportes.php" class="btn-primary">Acceder</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($noticias_recientes)): ?>
                <div class="noticias-section">
                    <h2>Noticias y Comunicados Recientes</h2>
                    <div class="noticias-grid">
                        <?php foreach ($noticias_recientes as $noticia): ?>
                            <div class="noticia-card">
                                <h4><?php echo htmlspecialchars($noticia['titulo']); ?></h4>
                                <p class="noticia-autor">
                                    Por: <?php echo htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellido']); ?> - 
                                    <?php echo formatearFecha($noticia['fecha_publicacion']); ?>
                                </p>
                                <p class="noticia-contenido">
                                    <?php echo nl2br(htmlspecialchars(substr($noticia['contenido'], 0, 150))); ?>
                                    <?php if (strlen($noticia['contenido']) > 150): ?>...<?php endif; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="js/main.js"></script>
</body>
</html>