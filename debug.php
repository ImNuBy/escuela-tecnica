<?php
echo "<h1>🔍 DIAGNÓSTICO DEL SISTEMA</h1>";

// Verificar estructura de archivos
$archivos_necesarios = [
    'config.php',
    'noticias.php',
    'admin/usuarios.php',
    'admin/academico.php',
    'admin/asistencias.php',
    'admin/reportes.php',
    'includes/header.php',
    'includes/sidebar.php',
    'css/base.css',
    'css/noticias.css',
    'js/main.js'
];

echo "<h2>📁 Verificación de Archivos:</h2>";
foreach ($archivos_necesarios as $archivo) {
    $existe = file_exists($archivo);
    $estado = $existe ? "✅" : "❌";
    echo "$estado $archivo<br>";
}

// Verificar config
echo "<h2>🔧 Verificación de Config:</h2>";
try {
    require_once 'config.php';
    echo "✅ Config cargado<br>";
    echo "✅ Base de datos conectada<br>";
    
    // Verificar sesión
    if (isset($_SESSION['usuario_id'])) {
        echo "✅ Usuario logueado: " . $_SESSION['nombre_completo'] . "<br>";
        echo "✅ Tipo: " . $_SESSION['tipo_usuario'] . "<br>";
    } else {
        echo "❌ No hay usuario logueado<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error en config: " . $e->getMessage() . "<br>";
}

// Verificar permisos PHP
echo "<h2>🔒 Verificación de Permisos:</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Error Reporting: " . ini_get('display_errors') . "<br>";

// Mostrar errores recientes
echo "<h2>📋 Últimos Errores PHP:</h2>";
$log_file = ini_get('error_log');
if ($log_file && file_exists($log_file)) {
    $errors = file($log_file);
    $recent_errors = array_slice($errors, -10);
    foreach ($recent_errors as $error) {
        echo htmlspecialchars($error) . "<br>";
    }
} else {
    echo "No se encontró log de errores.<br>";
}

// Links de prueba
echo "<h2>🔗 Links de Prueba:</h2>";
echo '<a href="noticias.php">📢 Probar Noticias</a><br>';
echo '<a href="admin/usuarios.php">👥 Probar Admin/Usuarios</a><br>';
echo '<a href="dashboard.php">🏠 Ir al Dashboard</a><br>';
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #333; border-bottom: 2px solid #ccc; }
h2 { color: #666; margin-top: 20px; }
a { color: #007bff; text-decoration: none; padding: 5px 10px; background: #f8f9fa; border-radius: 4px; margin: 2px; display: inline-block; }
a:hover { background: #e9ecef; }
</style>