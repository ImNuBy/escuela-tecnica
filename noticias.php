<?php
require_once 'config.php';
verificarLogin();

$mensaje = '';
$tipo_mensaje = '';

// Verificar si hay mensaje desde la URL (cuando regresa de editar)
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipo_mensaje = isset($_GET['tipo']) ? $_GET['tipo'] : 'success';
}

// Procesar formulario de nueva noticia (solo administradores y profesores)
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear_noticia') {
    if (in_array($_SESSION['tipo_usuario'], ['administrador', 'profesor'])) {
        $titulo = trim($_POST['titulo']);
        $contenido = trim($_POST['contenido']);
        $dirigido_a = $_POST['dirigido_a'];
        
        if (!empty($titulo) && !empty($contenido)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO noticias (titulo, contenido, autor_id, dirigido_a) VALUES (?, ?, ?, ?)");
                $stmt->execute([$titulo, $contenido, $_SESSION['usuario_id'], $dirigido_a]);
                $mensaje = 'Noticia publicada exitosamente';
                $tipo_mensaje = 'success';
            } catch(PDOException $e) {
                $mensaje = 'Error al publicar noticia: ' . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        } else {
            $mensaje = 'Por favor complete todos los campos obligatorios';
            $tipo_mensaje = 'error';
        }
    }
}

// Procesar eliminación de noticia
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'eliminar_noticia') {
    if (in_array($_SESSION['tipo_usuario'], ['administrador', 'profesor'])) {
        $noticia_id = (int)$_POST['noticia_id'];
        
        // Verificar que el usuario puede eliminar esta noticia
        $stmt = $pdo->prepare("SELECT autor_id FROM noticias WHERE id = ?");
        $stmt->execute([$noticia_id]);
        $noticia_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($noticia_data && ($noticia_data['autor_id'] == $_SESSION['usuario_id'] || $_SESSION['tipo_usuario'] == 'administrador')) {
            try {
                $stmt = $pdo->prepare("UPDATE noticias SET activo = 0 WHERE id = ?");
                $stmt->execute([$noticia_id]);
                $mensaje = 'Noticia eliminada exitosamente';
                $tipo_mensaje = 'success';
            } catch(PDOException $e) {
                $mensaje = 'Error al eliminar noticia: ' . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        } else {
            $mensaje = 'No tiene permisos para eliminar esta noticia';
            $tipo_mensaje = 'error';
        }
    }
}

// Determinar el filtro de noticias según el tipo de usuario
$filtro_orientacion = '';
if ($_SESSION['tipo_usuario'] == 'alumno') {
    // Obtener la orientación del alumno
    $stmt = $pdo->prepare("
        SELECT o.nombre 
        FROM alumnos a 
        JOIN años an ON a.año_id = an.id 
        JOIN orientaciones o ON an.orientacion_id = o.id 
        WHERE a.usuario_id = ?
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    $orientacion_alumno = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($orientacion_alumno) {
        $orientacion_nombre = strtolower($orientacion_alumno['nombre']);
        if (strpos($orientacion_nombre, 'básico') !== false || strpos($orientacion_nombre, 'basico') !== false) {
            $filtro_orientacion = 'ciclo_basico';
        } elseif (strpos($orientacion_nombre, 'programación') !== false || strpos($orientacion_nombre, 'programacion') !== false) {
            $filtro_orientacion = 'programacion';
        } elseif (strpos($orientacion_nombre, 'electrónica') !== false || strpos($orientacion_nombre, 'electronica') !== false) {
            $filtro_orientacion = 'electronica';
        }
    }
}

// Obtener noticias
$where_clause = "WHERE n.activo = 1";
$params = [];

if ($_SESSION['tipo_usuario'] == 'alumno' && $filtro_orientacion) {
    $where_clause .= " AND (n.dirigido_a = 'todos' OR n.dirigido_a = ?)";
    $params[] = $filtro_orientacion;
} elseif ($_SESSION['tipo_usuario'] == 'profesor') {
    $where_clause .= " AND (n.dirigido_a = 'todos' OR n.dirigido_a = 'profesores')";
}

$stmt = $pdo->prepare("
    SELECT n.*, u.nombre, u.apellido, u.tipo_usuario
    FROM noticias n 
    JOIN usuarios u ON n.autor_id = u.id 
    $where_clause
    ORDER BY n.fecha_publicacion DESC
");
$stmt->execute($params);
$noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener filtro seleccionado
$filtro_actual = isset($_GET['filtro']) ? $_GET['filtro'] : 'todas';

// Filtrar noticias según selección
if ($filtro_actual != 'todas') {
    $noticias = array_filter($noticias, function($noticia) use ($filtro_actual) {
        return $noticia['dirigido_a'] == $filtro_actual;
    });
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noticias y Comunicados - Sistema Escolar</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/noticias.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>Noticias y Comunicados</h1>
                <p>Información importante de la escuela</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="noticias-container">
                <!-- Formulario para publicar (solo admin y profesores) -->
                <?php if (in_array($_SESSION['tipo_usuario'], ['administrador', 'profesor'])): ?>
                    <button class="toggle-form" onclick="toggleFormulario()">
                        📝 Publicar Nueva Noticia
                    </button>
                    
                    <div id="form-publicar" class="form-publicar form-hidden">
                        <h2>Publicar Nueva Noticia</h2>
                        <form method="POST" id="formPublicar">
                            <input type="hidden" name="action" value="crear_noticia">
                            
                            <div class="form-group">
                                <label for="titulo">Título *</label>
                                <input type="text" id="titulo" name="titulo" required 
                                       placeholder="Ingrese el título de la noticia">
                            </div>
                            
                            <div class="form-group">
                                <label for="dirigido_a">Dirigido a *</label>
                                <select id="dirigido_a" name="dirigido_a" required>
                                    <option value="todos">Todos</option>
                                    <option value="ciclo_basico">Ciclo Básico</option>
                                    <option value="programacion">Programación</option>
                                    <option value="electronica">Electrónica</option>
                                    <option value="profesores">Profesores</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="contenido">Contenido *</label>
                                <textarea id="contenido" name="contenido" rows="6" required 
                                          placeholder="Escriba el contenido de la noticia..."></textarea>
                            </div>
                            
                            <div style="display: flex; gap: 1rem;">
                                <button type="submit" class="btn-primary">Publicar Noticia</button>
                                <button type="button" onclick="toggleFormulario()" class="btn-secondary">Cancelar</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Filtros -->
                <div class="filtros-noticias">
                    <strong>Filtrar por:</strong>
                    <a href="?filtro=todas" class="filtro-btn <?php echo ($filtro_actual == 'todas') ? 'active' : ''; ?>">
                        Todas
                    </a>
                    <a href="?filtro=todos" class="filtro-btn <?php echo ($filtro_actual == 'todos') ? 'active' : ''; ?>">
                        Generales
                    </a>
                    <a href="?filtro=ciclo_basico" class="filtro-btn <?php echo ($filtro_actual == 'ciclo_basico') ? 'active' : ''; ?>">
                        Ciclo Básico
                    </a>
                    <a href="?filtro=programacion" class="filtro-btn <?php echo ($filtro_actual == 'programacion') ? 'active' : ''; ?>">
                        Programación
                    </a>
                    <a href="?filtro=electronica" class="filtro-btn <?php echo ($filtro_actual == 'electronica') ? 'active' : ''; ?>">
                        Electrónica
                    </a>
                    <?php if ($_SESSION['tipo_usuario'] != 'alumno'): ?>
                        <a href="?filtro=profesores" class="filtro-btn <?php echo ($filtro_actual == 'profesores') ? 'active' : ''; ?>">
                            Profesores
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Lista de noticias -->
                <div class="noticias-grid">
                    <?php if (!empty($noticias)): ?>
                        <?php foreach ($noticias as $noticia): ?>
                            <article class="noticia-card">
                                <div class="noticia-header">
                                    <div>
                                        <h2 class="noticia-titulo">
                                            <?php echo htmlspecialchars($noticia['titulo']); ?>
                                        </h2>
                                        <div class="noticia-meta">
                                            <div class="autor-info">
                                                <span>👤</span>
                                                <span><?php echo htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellido']); ?></span>
                                                <span>(<?php echo ucfirst($noticia['tipo_usuario']); ?>)</span>
                                            </div>
                                            <span>📅 <?php echo formatearFecha($noticia['fecha_publicacion']); ?></span>
                                        </div>
                                    </div>
                                    <div class="dirigido-badge badge-<?php echo $noticia['dirigido_a']; ?>">
                                        <?php 
                                        $dirigido_labels = [
                                            'todos' => 'Todos',
                                            'ciclo_basico' => 'Ciclo Básico',
                                            'programacion' => 'Programación',
                                            'electronica' => 'Electrónica',
                                            'profesores' => 'Profesores'
                                        ];
                                        echo $dirigido_labels[$noticia['dirigido_a']] ?? ucfirst($noticia['dirigido_a']);
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="noticia-contenido">
                                    <?php echo nl2br(htmlspecialchars($noticia['contenido'])); ?>
                                </div>
                                
                                <div class="noticia-fecha">
                                    Publicado el <?php echo date('d/m/Y H:i', strtotime($noticia['fecha_publicacion'])); ?>
                                </div>
                                
                                <?php if ($_SESSION['usuario_id'] == $noticia['autor_id'] || $_SESSION['tipo_usuario'] == 'administrador'): ?>
                                    <div class="noticia-acciones">
                                        <a href="editar_noticia.php?id=<?php echo $noticia['id']; ?>" class="btn-accion btn-editar">
                                            ✏️ Editar
                                        </a>
                                        <button onclick="eliminarNoticia(<?php echo $noticia['id']; ?>)" class="btn-accion btn-eliminar">
                                            🗑️ Eliminar
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="sin-noticias">
                            <h3>📢 No hay noticias disponibles</h3>
                            <p>No se encontraron noticias para mostrar en este momento.</p>
                            <?php if (in_array($_SESSION['tipo_usuario'], ['administrador', 'profesor'])): ?>
                                <button onclick="toggleFormulario()" class="btn-primary" style="margin-top: 1rem;">
                                    Publicar Primera Noticia
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal de confirmación para eliminar -->
    <div id="modal-confirmacion" class="confirmacion-overlay">
        <div class="confirmacion-modal">
            <h3>⚠️ Confirmar Eliminación</h3>
            <p>¿Está seguro que desea eliminar esta noticia?</p>
            <p style="font-size: 0.875rem; color: var(--gray-600); margin-top: 0.5rem;">
                Esta acción no se puede deshacer.
            </p>
            <div class="confirmacion-botones">
                <button onclick="confirmarEliminacion()" class="btn-primary" style="background: var(--danger-color);">
                    Sí, Eliminar
                </button>
                <button onclick="cerrarModal()" class="btn-secondary">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
    
    <script src="js/main.js"></script>
    <script>
        let noticiaAEliminar = null;
        
        function toggleFormulario() {
            const form = document.getElementById('form-publicar');
            const button = document.querySelector('.toggle-form');
            
            if (form.classList.contains('form-hidden')) {
                form.classList.remove('form-hidden');
                button.textContent = '❌ Cancelar';
                form.scrollIntoView({ behavior: 'smooth' });
            } else {
                form.classList.add('form-hidden');
                button.textContent = '📝 Publicar Nueva Noticia';
            }
        }
        
        function eliminarNoticia(noticiaId) {
            noticiaAEliminar = noticiaId;
            document.getElementById('modal-confirmacion').style.display = 'flex';
        }
        
        function cerrarModal() {
            document.getElementById('modal-confirmacion').style.display = 'none';
            noticiaAEliminar = null;
        }
        
        function confirmarEliminacion() {
            if (noticiaAEliminar) {
                // Crear formulario dinámico para enviar la eliminación
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'eliminar_noticia';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'noticia_id';
                idInput.value = noticiaAEliminar;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modal-confirmacion').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
        
        // Auto-resize textarea
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('contenido');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            }
        });
        
        // Validación del formulario
        document.getElementById('formPublicar')?.addEventListener('submit', function(e) {
            const titulo = document.getElementById('titulo').value.trim();
            const contenido = document.getElementById('contenido').value.trim();
            
            if (!titulo || !contenido) {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios.');
                return false;
            }
            
            if (titulo.length < 5) {
                e.preventDefault();
                alert('El título debe tener al menos 5 caracteres.');
                return false;
            }
            
            if (contenido.length < 10) {
                e.preventDefault();
                alert('El contenido debe tener al menos 10 caracteres.');
                return false;
            }
        });
    </script>
</body>
</html>