<?php
require_once 'config.php';
verificarLogin();

$mensaje = '';
$tipo_mensaje = '';
$noticia_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$noticia_id) {
    header('Location: noticias.php');
    exit();
}

// Verificar que el usuario puede editar esta noticia
$stmt = $pdo->prepare("SELECT * FROM noticias WHERE id = ?");
$stmt->execute([$noticia_id]);
$noticia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$noticia) {
    header('Location: noticias.php');
    exit();
}

// Verificar permisos (solo el autor o administrador puede editar)
if ($noticia['autor_id'] != $_SESSION['usuario_id'] && $_SESSION['tipo_usuario'] != 'administrador') {
    header('Location: noticias.php');
    exit();
}

// Procesar formulario de edición
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'editar_noticia') {
    $titulo = trim($_POST['titulo']);
    $contenido = trim($_POST['contenido']);
    $dirigido_a = $_POST['dirigido_a'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if (!empty($titulo) && !empty($contenido)) {
        try {
            $stmt = $pdo->prepare("UPDATE noticias SET titulo = ?, contenido = ?, dirigido_a = ?, activo = ? WHERE id = ?");
            $stmt->execute([$titulo, $contenido, $dirigido_a, $activo, $noticia_id]);
            $mensaje = 'Noticia actualizada exitosamente';
            $tipo_mensaje = 'success';
            
            // Recargar datos de la noticia
            $stmt = $pdo->prepare("SELECT * FROM noticias WHERE id = ?");
            $stmt->execute([$noticia_id]);
            $noticia = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            $mensaje = 'Error al actualizar noticia: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
    }
}

// Procesar eliminación
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'eliminar_noticia') {
    try {
        $stmt = $pdo->prepare("UPDATE noticias SET activo = 0 WHERE id = ?");
        $stmt->execute([$noticia_id]);
        header('Location: noticias.php?mensaje=Noticia eliminada exitosamente');
        exit();
    } catch(PDOException $e) {
        $mensaje = 'Error al eliminar noticia: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Noticia - Sistema Escolar</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/noticias.css">
    <style>
        .editar-container {
            max-width: 800px;
            margin: 0 auto;
            display: grid;
            gap: 2rem;
        }
        
        .header-edicion {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            border-left: 4px solid var(--warning-color);
        }
        
        .header-edicion h1 {
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .header-edicion h1::before {
            content: '✏️';
        }
        
        .form-edicion {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
            line-height: 1.6;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .botones-accion {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .botones-principales {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-eliminar {
            background: var(--danger-color);
            color: var(--white);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-eliminar:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        
        .preview-noticia {
            background: var(--gray-50);
            padding: 2rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
        }
        
        .preview-noticia h3 {
            color: var(--gray-800);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .preview-noticia h3::before {
            content: '👁️';
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="editar-container">
                <!-- Header -->
                <div class="header-edicion">
                    <h1>Editar Noticia</h1>
                    <p>Modifica la información de la noticia seleccionada</p>
                </div>
                
                <?php if ($mensaje): ?>
                    <div class="mensaje <?php echo $tipo_mensaje; ?>">
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Formulario de edición -->
                <div class="form-edicion">
                    <form method="POST" id="formEdicion">
                        <input type="hidden" name="action" value="editar_noticia">
                        
                        <div class="form-group">
                            <label for="titulo">Título *</label>
                            <input type="text" id="titulo" name="titulo" required 
                                   value="<?php echo htmlspecialchars($noticia['titulo']); ?>"
                                   placeholder="Ingrese el título de la noticia">
                        </div>
                        
                        <div class="form-group">
                            <label for="dirigido_a">Dirigido a *</label>
                            <select id="dirigido_a" name="dirigido_a" required>
                                <option value="todos" <?php echo ($noticia['dirigido_a'] == 'todos') ? 'selected' : ''; ?>>Todos</option>
                                <option value="ciclo_basico" <?php echo ($noticia['dirigido_a'] == 'ciclo_basico') ? 'selected' : ''; ?>>Ciclo Básico</option>
                                <option value="programacion" <?php echo ($noticia['dirigido_a'] == 'programacion') ? 'selected' : ''; ?>>Programación</option>
                                <option value="electronica" <?php echo ($noticia['dirigido_a'] == 'electronica') ? 'selected' : ''; ?>>Electrónica</option>
                                <option value="profesores" <?php echo ($noticia['dirigido_a'] == 'profesores') ? 'selected' : ''; ?>>Profesores</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="contenido">Contenido *</label>
                            <textarea id="contenido" name="contenido" required 
                                      placeholder="Escriba el contenido de la noticia..."><?php echo htmlspecialchars($noticia['contenido']); ?></textarea>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="activo" name="activo" <?php echo $noticia['activo'] ? 'checked' : ''; ?>>
                            <label for="activo">Noticia activa (visible para usuarios)</label>
                        </div>
                        
                        <div class="botones-accion">
                            <div class="botones-principales">
                                <button type="submit" class="btn-primary">💾 Guardar Cambios</button>
                                <a href="noticias.php" class="btn-secondary">↩️ Volver a Noticias</a>
                            </div>
                            
                            <button type="button" onclick="confirmarEliminacion()" class="btn-eliminar">
                                🗑️ Eliminar Noticia
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Vista previa -->
                <div class="preview-noticia">
                    <h3>Vista Previa</h3>
                    <div class="noticia-preview-content">
                        <h4 id="preview-titulo"><?php echo htmlspecialchars($noticia['titulo']); ?></h4>
                        <div class="dirigido-badge badge-<?php echo $noticia['dirigido_a']; ?>" id="preview-dirigido">
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
                        <div id="preview-contenido" style="margin-top: 1rem; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($noticia['contenido'])); ?>
                        </div>
                        <div style="margin-top: 1rem; font-size: 0.875rem; color: var(--gray-600);">
                            Estado: <span id="preview-estado"><?php echo $noticia['activo'] ? '✅ Activa' : '❌ Inactiva'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Formulario oculto para eliminación -->
    <form id="formEliminar" method="POST" style="display: none;">
        <input type="hidden" name="action" value="eliminar_noticia">
    </form>
    
    <script src="js/main.js"></script>
    <script>
        // Actualizar vista previa en tiempo real
        document.getElementById('titulo').addEventListener('input', function() {
            document.getElementById('preview-titulo').textContent = this.value || 'Título de la noticia';
        });
        
        document.getElementById('contenido').addEventListener('input', function() {
            document.getElementById('preview-contenido').innerHTML = this.value.replace(/\n/g, '<br>') || 'Contenido de la noticia...';
        });
        
        document.getElementById('dirigido_a').addEventListener('change', function() {
            const labels = {
                'todos': 'Todos',
                'ciclo_basico': 'Ciclo Básico',
                'programacion': 'Programación',
                'electronica': 'Electrónica',
                'profesores': 'Profesores'
            };
            
            const badge = document.getElementById('preview-dirigido');
            badge.textContent = labels[this.value];
            badge.className = 'dirigido-badge badge-' + this.value;
        });
        
        document.getElementById('activo').addEventListener('change', function() {
            document.getElementById('preview-estado').innerHTML = this.checked ? '✅ Activa' : '❌ Inactiva';
        });
        
        // Auto-resize textarea
        document.getElementById('contenido').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
        
        // Confirmación de eliminación
        function confirmarEliminacion() {
            if (confirm('⚠️ ¿Está seguro que desea eliminar esta noticia?\n\nEsta acción no se puede deshacer.')) {
                if (confirm('🔄 Confirme nuevamente: ¿Eliminar la noticia definitivamente?')) {
                    document.getElementById('formEliminar').submit();
                }
            }
        }
        
        // Validación antes de enviar
        document.getElementById('formEdicion').addEventListener('submit', function(e) {
            const titulo = document.getElementById('titulo').value.trim();
            const contenido = document.getElementById('contenido').value.trim();
            
            if (!titulo || !contenido) {
                e.preventDefault();
                alert('❌ Por favor complete todos los campos obligatorios.');
                return false;
            }
            
            if (titulo.length < 5) {
                e.preventDefault();
                alert('❌ El título debe tener al menos 5 caracteres.');
                return false;
            }
            
            if (contenido.length < 10) {
                e.preventDefault();
                alert('❌ El contenido debe tener al menos 10 caracteres.');
                return false;
            }
            
            return true;
        });
        
        // Configurar auto-resize inicial
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('contenido');
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        });
    </script>
</body>
</html>