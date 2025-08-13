<?php
// Determinar la URL base según la ubicación del archivo
$currentDir = dirname($_SERVER['PHP_SELF']);
if (strpos($currentDir, '/admin') !== false || 
    strpos($currentDir, '/profesor') !== false || 
    strpos($currentDir, '/alumno') !== false) {
    $baseUrl = '../';
} else {
    $baseUrl = './';
}

// Función para verificar si el enlace está activo
function isActiveLink($link) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return (strpos($link, $currentPage) !== false) ? 'active' : '';
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3>📚 Menú Principal</h3>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li><a href="<?php echo $baseUrl; ?>dashboard.php" class="nav-item <?php echo isActiveLink('dashboard.php'); ?>">🏠 Panel Principal</a></li>
            
            <?php if ($_SESSION['tipo_usuario'] == 'administrador'): ?>
                <li class="nav-section">⚙️ Administración</li>
                <li><a href="<?php echo $baseUrl; ?>admin/usuarios.php" class="nav-item <?php echo isActiveLink('usuarios.php'); ?>">👥 Gestión de Usuarios</a></li>
                <li><a href="<?php echo $baseUrl; ?>admin/academico.php" class="nav-item <?php echo isActiveLink('academico.php'); ?>">📚 Gestión Académica</a></li>
                <li><a href="<?php echo $baseUrl; ?>admin/asistencias.php" class="nav-item <?php echo isActiveLink('asistencias.php'); ?>">📋 Asistencias</a></li>
                <li><a href="<?php echo $baseUrl; ?>admin/reportes.php" class="nav-item <?php echo isActiveLink('reportes.php'); ?>">📝 Reportes</a></li>
                
                <li class="nav-section">📊 Paneles por Orientación</li>
                <li><a href="<?php echo $baseUrl; ?>admin/panel_ciclo_basico.php" class="nav-item">🎓 Ciclo Básico</a></li>
                <li><a href="<?php echo $baseUrl; ?>admin/panel_programacion.php" class="nav-item">💻 Programación</a></li>
                <li><a href="<?php echo $baseUrl; ?>admin/panel_electronica.php" class="nav-item">⚡ Electrónica</a></li>
                <li><a href="<?php echo $baseUrl; ?>admin/panel_profesores.php" class="nav-item">👨‍🏫 Profesores</a></li>
            <?php endif; ?>
            
            <?php if ($_SESSION['tipo_usuario'] == 'profesor'): ?>
                <li class="nav-section">👨‍🏫 Profesor</li>
                <li><a href="<?php echo $baseUrl; ?>profesor/materias.php" class="nav-item <?php echo isActiveLink('materias.php'); ?>">📖 Mis Materias</a></li>
                <li><a href="<?php echo $baseUrl; ?>profesor/notas.php" class="nav-item <?php echo isActiveLink('notas.php'); ?>">📊 Notas</a></li>
                <li><a href="<?php echo $baseUrl; ?>profesor/asistencias.php" class="nav-item <?php echo isActiveLink('asistencias.php'); ?>">✅ Asistencias</a></li>
                <li><a href="<?php echo $baseUrl; ?>profesor/actividades.php" class="nav-item <?php echo isActiveLink('actividades.php'); ?>">📝 Actividades</a></li>
                <li><a href="<?php echo $baseUrl; ?>profesor/horarios.php" class="nav-item <?php echo isActiveLink('horarios.php'); ?>">🕒 Horarios</a></li>
            <?php endif; ?>
            
            <?php if ($_SESSION['tipo_usuario'] == 'alumno'): ?>
                <li class="nav-section">👨‍🎓 Estudiante</li>
                <li><a href="<?php echo $baseUrl; ?>alumno/materias.php" class="nav-item <?php echo isActiveLink('materias.php'); ?>">📖 Mis Materias</a></li>
                <li><a href="<?php echo $baseUrl; ?>alumno/notas.php" class="nav-item <?php echo isActiveLink('notas.php'); ?>">📊 Mis Notas</a></li>
                <li><a href="<?php echo $baseUrl; ?>alumno/asistencias.php" class="nav-item <?php echo isActiveLink('asistencias.php'); ?>">✅ Mis Asistencias</a></li>
                <li><a href="<?php echo $baseUrl; ?>alumno/actividades.php" class="nav-item <?php echo isActiveLink('actividades.php'); ?>">📝 Actividades</a></li>
                <li><a href="<?php echo $baseUrl; ?>alumno/horarios.php" class="nav-item <?php echo isActiveLink('horarios.php'); ?>">🕒 Horarios</a></li>
                <li><a href="<?php echo $baseUrl; ?>alumno/reportes.php" class="nav-item <?php echo isActiveLink('reportes.php'); ?>">📮 Reportar</a></li>
            <?php endif; ?>
            
            <li class="nav-section">🌐 General</li>
            <li><a href="<?php echo $baseUrl; ?>noticias.php" class="nav-item <?php echo isActiveLink('noticias.php'); ?>">📢 Noticias</a></li>
            <li><a href="<?php echo $baseUrl; ?>perfil.php" class="nav-item <?php echo isActiveLink('perfil.php'); ?>">👤 Mi Perfil</a></li>
        </ul>
    </nav>
</aside>