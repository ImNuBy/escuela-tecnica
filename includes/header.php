<?php
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . getBaseUrl() . 'index.php');
    exit();
}

// Función para obtener la URL base correcta
function getBaseUrl() {
    $currentDir = dirname($_SERVER['PHP_SELF']);
    if (strpos($currentDir, '/admin') !== false || 
        strpos($currentDir, '/profesor') !== false || 
        strpos($currentDir, '/alumno') !== false) {
        return '../';
    }
    return './';
}

$baseUrl = getBaseUrl();
?>

<header class="main-header">
    <div class="header-left">
        <div class="logo">
            <h2>🏫 Escuela Técnica</h2>
        </div>
        <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>
    </div>
    
    <nav class="header-nav">
        <div class="nav-links">
            <a href="<?php echo $baseUrl; ?>dashboard.php" class="nav-link">🏠 Inicio</a>
            <?php if ($_SESSION['tipo_usuario'] == 'administrador'): ?>
                <a href="<?php echo $baseUrl; ?>admin/usuarios.php" class="nav-link">👥 Usuarios</a>
                <a href="<?php echo $baseUrl; ?>admin/academico.php" class="nav-link">📚 Académico</a>
            <?php endif; ?>
            
            <a href="<?php echo $baseUrl; ?>noticias.php" class="nav-link">📢 Noticias</a>
        </div>
        
        <div class="user-menu">
            <div class="user-info">
                <span class="user-name"><?php echo h($_SESSION['nombre_completo']); ?></span>
                <span class="user-type"><?php echo ucfirst($_SESSION['tipo_usuario']); ?></span>
            </div>
            
            <div class="user-actions">
                <a href="<?php echo $baseUrl; ?>perfil.php" class="btn-secondary">👤 Mi Perfil</a>
                <a href="<?php echo $baseUrl; ?>logout.php" class="btn-logout">🚪 Cerrar Sesión</a>
            </div>
        </div>
    </nav>
</header>