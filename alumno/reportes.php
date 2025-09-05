<?php
require_once '../config.php';
verificarTipoUsuario(['alumno']);

$mensaje = '';
$tipo_mensaje = '';

// Obtener ID del alumno
$stmt = $pdo->prepare("SELECT id FROM alumnos WHERE usuario_id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$alumno = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alumno) {
    header('Location: ../index.php');
    exit();
}

$alumno_id = $alumno['id'];

// Configuración para archivos
$upload_dir = '../uploads/reportes/';
$max_file_size = 5 * 1024 * 1024; // 5MB
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

// Crear directorio si no existe
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Procesar formulario de nuevo reporte
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear_reporte') {
    $tipo_reporte = $_POST['tipo_reporte'] ?? '';
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    // Variables para archivo
    $archivo_nombre = null;
    $archivo_ruta = null;
    $archivo_tipo = null;
    $archivo_tamaño = null;
    
    // Validar campos obligatorios
    if (empty($tipo_reporte) || empty($titulo) || empty($descripcion)) {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
    } else {
        try {
            // Procesar archivo adjunto si existe
            if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] == UPLOAD_ERR_OK) {
                $file = $_FILES['archivo'];
                
                // Validar tipo de archivo
                if (!in_array($file['type'], $allowed_types)) {
                    throw new Exception('Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF, WEBP) y PDF.');
                }
                
                // Validar tamaño
                if ($file['size'] > $max_file_size) {
                    throw new Exception('El archivo es demasiado grande. Máximo 5MB permitidos.');
                }
                
                // Generar nombre único
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $archivo_nombre = $file['name'];
                $nombre_unico = 'reporte_' . $alumno_id . '_' . time() . '.' . $extension;
                $archivo_ruta = $upload_dir . $nombre_unico;
                $archivo_tipo = $file['type'];
                $archivo_tamaño = $file['size'];
                
                // Mover archivo
                if (!move_uploaded_file($file['tmp_name'], $archivo_ruta)) {
                    throw new Exception('Error al subir el archivo. Inténtelo nuevamente.');
                }
            }
            
            // Insertar reporte en base de datos
            $stmt = $pdo->prepare("
                INSERT INTO reportes_alumnos 
                (alumno_id, tipo_reporte, titulo, descripcion, archivo_nombre, archivo_ruta, archivo_tipo, archivo_tamaño) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $alumno_id, 
                $tipo_reporte, 
                $titulo, 
                $descripcion, 
                $archivo_nombre, 
                $archivo_ruta, 
                $archivo_tipo, 
                $archivo_tamaño
            ]);
            
            $mensaje = 'Reporte enviado exitosamente. Será revisado por el personal administrativo.';
            $tipo_mensaje = 'success';
            
        } catch(Exception $e) {
            // Eliminar archivo si se subió pero falló la inserción
            if (isset($archivo_ruta) && file_exists($archivo_ruta)) {
                unlink($archivo_ruta);
            }
            $mensaje = 'Error al enviar reporte: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

// Obtener reportes del alumno
$stmt = $pdo->prepare("
    SELECT * FROM reportes_alumnos 
    WHERE alumno_id = ? 
    ORDER BY fecha_reporte DESC
");
$stmt->execute([$alumno_id]);
$reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Reportes - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <style>
        :root {
            --primary-color: #3b82f6;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --border-radius: 8px;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        
        .main-container {
            display: flex;
            min-height: 100vh;
        }
        
        .content {
            flex: 1;
            padding: 2rem;
            margin-left: 250px;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            color: var(--gray-600);
            font-size: 1.1rem;
        }
        
        .reportes-container {
            display: grid;
            gap: 2rem;
        }
        
        .form-reporte {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .form-reporte h2 {
            margin-bottom: 1.5rem;
            color: var(--gray-800);
            font-size: 1.5rem;
        }
        
        .tipos-reporte {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .tipo-card {
            background: var(--gray-50);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border: 2px solid transparent;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }
        
        .tipo-card:hover {
            border-color: var(--primary-color);
            background: #eff6ff;
        }
        
        .tipo-card.selected {
            border-color: var(--primary-color);
            background: #eff6ff;
        }
        
        .tipo-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .tipo-titulo {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }
        
        .tipo-descripcion {
            font-size: 0.875rem;
            color: var(--gray-600);
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
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .file-upload-area {
            border: 2px dashed var(--gray-200);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
        }
        
        .file-upload-area:hover {
            border-color: var(--primary-color);
            background: #fafbff;
        }
        
        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background: #eff6ff;
        }
        
        .file-upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .upload-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-600);
        }
        
        .upload-text {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
        }
        
        .upload-hint {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .file-preview {
            display: none;
            background: var(--gray-100);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .preview-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .preview-icon {
            font-size: 2rem;
        }
        
        .preview-info {
            flex: 1;
        }
        
        .preview-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .preview-size {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .remove-file {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .reportes-historial {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .historial-header {
            background: var(--gray-100);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .historial-header h2 {
            margin: 0 0 0.5rem 0;
            color: var(--gray-800);
        }
        
        .historial-header p {
            margin: 0;
            color: var(--gray-600);
        }
        
        .reporte-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            transition: background-color 0.2s;
        }
        
        .reporte-item:hover {
            background: var(--gray-50);
        }
        
        .reporte-item:last-child {
            border-bottom: none;
        }
        
        .reporte-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .reporte-titulo {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 1.1rem;
        }
        
        .estado-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .estado-pendiente {
            background: #fef3c7;
            color: #92400e;
        }
        
        .estado-revisado {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .estado-resuelto {
            background: #dcfce7;
            color: #166534;
        }
        
        .reporte-tipo {
            background: var(--primary-color);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        
        .reporte-fecha {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 1rem;
        }
        
        .reporte-descripcion {
            color: var(--gray-700);
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .archivo-adjunto {
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .archivo-icon {
            font-size: 1.5rem;
        }
        
        .archivo-info {
            flex: 1;
        }
        
        .archivo-nombre {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .archivo-size {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .ver-archivo {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-size: 0.875rem;
            cursor: pointer;
        }
        
        .reporte-respuesta {
            background: #f0f9ff;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }
        
        .respuesta-titulo {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
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
        
        .sin-reportes {
            text-align: center;
            padding: 3rem;
            color: var(--gray-600);
        }
        
        .sin-reportes h3 {
            margin-bottom: 1rem;
            color: var(--gray-700);
        }
        
        .tips-box {
            background: var(--gray-100);
            padding: 1rem;
            border-radius: var(--border-radius);
            font-size: 0.875rem;
        }
        
        .tips-box strong {
            color: var(--gray-800);
        }
        
        .tips-box ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
        
        .tips-box li {
            margin-bottom: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .tipos-reporte {
                grid-template-columns: 1fr;
            }
            
            .reporte-header {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>Reportes y Comunicaciones</h1>
                <p>Envía reportes de inasistencias, problemas o sugerencias</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="reportes-container">
                <!-- Formulario para nuevo reporte -->
                <div class="form-reporte">
                    <h2>Enviar Nuevo Reporte</h2>
                    
                    <form method="POST" id="formReporte" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="crear_reporte">
                        <input type="hidden" name="tipo_reporte" id="tipoReporteHidden">
                        
                        <div class="form-group">
                            <label>Tipo de Reporte *</label>
                            <div class="tipos-reporte">
                                <div class="tipo-card" onclick="seleccionarTipo('inasistencia')">
                                    <div class="tipo-icon">🏥</div>
                                    <div class="tipo-titulo">Justificar Inasistencia</div>
                                    <div class="tipo-descripcion">Reportar ausencia por enfermedad u otros motivos</div>
                                </div>
                                
                                <div class="tipo-card" onclick="seleccionarTipo('problema')">
                                    <div class="tipo-icon">⚠️</div>
                                    <div class="tipo-titulo">Reportar Problema</div>
                                    <div class="tipo-descripcion">Informar sobre problemas académicos o de infraestructura</div>
                                </div>
                                
                                <div class="tipo-card" onclick="seleccionarTipo('sugerencia')">
                                    <div class="tipo-icon">💡</div>
                                    <div class="tipo-titulo">Enviar Sugerencia</div>
                                    <div class="tipo-descripcion">Proponer mejoras o nuevas ideas</div>
                                </div>
                                
                                <div class="tipo-card" onclick="seleccionarTipo('otro')">
                                    <div class="tipo-icon">📝</div>
                                    <div class="tipo-titulo">Otro</div>
                                    <div class="tipo-descripcion">Cualquier otro tipo de comunicación</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="titulo">Título del Reporte *</label>
                            <input type="text" id="titulo" name="titulo" required 
                                   placeholder="Ingrese un título descriptivo">
                        </div>
                        
                        <div class="form-group">
                            <label for="descripcion">Descripción Detallada *</label>
                            <textarea id="descripcion" name="descripcion" rows="6" required 
                                      placeholder="Describa detalladamente su reporte..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="archivo">Archivo Adjunto (Opcional)</label>
                            <div class="file-upload-area" onclick="document.getElementById('archivo').click()">
                                <input type="file" id="archivo" name="archivo" class="file-upload-input" 
                                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
                                <div class="upload-icon">📎</div>
                                <div class="upload-text">Haz clic para seleccionar un archivo</div>
                                <div class="upload-hint">O arrastra y suelta aquí<br>
                                    Formatos: JPG, PNG, GIF, WEBP, PDF (Máx. 5MB)</div>
                            </div>
                            <div class="file-preview" id="filePreview">
                                <div class="preview-content">
                                    <div class="preview-icon" id="previewIcon">📄</div>
                                    <div class="preview-info">
                                        <div class="preview-name" id="previewName"></div>
                                        <div class="preview-size" id="previewSize"></div>
                                    </div>
                                    <button type="button" class="remove-file" onclick="removeFile()">×</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="tips-box">
                                <strong>💡 Consejos para un buen reporte:</strong>
                                <ul>
                                    <li>Sea específico y claro en su descripción</li>
                                    <li>Incluya fechas y horarios cuando sea relevante</li>
                                    <li>Para inasistencias, mencione el motivo y las fechas</li>
                                    <li>Para problemas, explique qué ocurrió y cuándo</li>
                                    <li>Adjunte fotos si ayudan a explicar el problema</li>
                                </ul>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary" id="btnEnviar" disabled>
                            📤 Enviar Reporte
                        </button>
                    </form>
                </div>
                
                <!-- Historial de reportes -->
                <div class="reportes-historial">
                    <div class="historial-header">
                        <h2>Mis Reportes Enviados</h2>
                        <p>Historial de todas tus comunicaciones y su estado</p>
                    </div>
                    
                    <?php if (!empty($reportes)): ?>
                        <?php foreach ($reportes as $reporte): ?>
                            <div class="reporte-item">
                                <div class="reporte-header">
                                    <div>
                                        <div class="reporte-tipo">
                                            <?php 
                                            $tipos = [
                                                'inasistencia' => '🏥 Inasistencia',
                                                'problema' => '⚠️ Problema',
                                                'sugerencia' => '💡 Sugerencia',
                                                'otro' => '📝 Otro'
                                            ];
                                            echo $tipos[$reporte['tipo_reporte']] ?? ucfirst($reporte['tipo_reporte']);
                                            ?>
                                        </div>
                                        <div class="reporte-titulo">
                                            <?php echo htmlspecialchars($reporte['titulo']); ?>
                                        </div>
                                    </div>
                                    <div class="estado-badge estado-<?php echo $reporte['estado']; ?>">
                                        <?php 
                                        $estados = [
                                            'pendiente' => '⏳ Pendiente',
                                            'revisado' => '👀 Revisado',
                                            'resuelto' => '✅ Resuelto'
                                        ];
                                        echo $estados[$reporte['estado']] ?? ucfirst($reporte['estado']);
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="reporte-fecha">
                                    📅 Enviado el <?php echo date('d/m/Y H:i', strtotime($reporte['fecha_reporte'])); ?>
                                </div>
                                
                                <div class="reporte-descripcion">
                                    <?php echo nl2br(htmlspecialchars($reporte['descripcion'])); ?>
                                </div>
                                
                                <?php if (!empty($reporte['archivo_nombre'])): ?>
                                    <div class="archivo-adjunto">
                                        <div class="archivo-icon">
                                            <?php 
                                            $ext = strtolower(pathinfo($reporte['archivo_nombre'], PATHINFO_EXTENSION));
                                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                                echo '🖼️';
                                            } else {
                                                echo '📄';
                                            }
                                            ?>
                                        </div>
                                        <div class="archivo-info">
                                            <div class="archivo-nombre"><?php echo htmlspecialchars($reporte['archivo_nombre']); ?></div>
                                            <div class="archivo-size">
                                                <?php 
                                                if ($reporte['archivo_tamaño']) {
                                                    echo formatBytes($reporte['archivo_tamaño']); 
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <a href="ver_archivo.php?id=<?php echo $reporte['id']; ?>" 
                                           target="_blank" class="ver-archivo">Ver Archivo</a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($reporte['respuesta'])): ?>
                                    <div class="reporte-respuesta">
                                        <div class="respuesta-titulo">💬 Respuesta del Personal:</div>
                                        <div><?php echo nl2br(htmlspecialchars($reporte['respuesta'])); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="sin-reportes">
                            <h3>🔭 No tienes reportes enviados</h3>
                            <p>Cuando envíes tu primer reporte, aparecerá aquí con su estado de seguimiento.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        let tipoSeleccionado = '';
        
        function seleccionarTipo(tipo) {
            // Remover selección anterior
            document.querySelectorAll('.tipo-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Seleccionar nuevo tipo
            event.currentTarget.classList.add('selected');
            tipoSeleccionado = tipo;
            
            // Actualizar campo hidden
            document.getElementById('tipoReporteHidden').value = tipo;
            
            // Habilitar botón enviar
            verificarFormulario();
            
            // Actualizar placeholder del título según el tipo
            const titulo = document.getElementById('titulo');
            const placeholders = {
                'inasistencia': 'Ej: Inasistencia por enfermedad del 15/03/2024',
                'problema': 'Ej: Problema con el proyector del aula A101',
                'sugerencia': 'Ej: Propuesta para mejorar el laboratorio de informática',
                'otro': 'Ej: Consulta sobre horarios de examen'
            };
            
            titulo.placeholder = placeholders[tipo] || 'Ingrese un título descriptivo';
        }
        
        function verificarFormulario() {
            const titulo = document.getElementById('titulo').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            const btnEnviar = document.getElementById('btnEnviar');
            
            if (tipoSeleccionado && titulo && descripcion) {
                btnEnviar.disabled = false;
                btnEnviar.style.opacity = '1';
            } else {
                btnEnviar.disabled = true;
                btnEnviar.style.opacity = '0.6';
            }
        }
        
        // Funciones para manejo de archivos
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function getFileIcon(fileName) {
            const ext = fileName.split('.').pop().toLowerCase();
            const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            return imageExts.includes(ext) ? '🖼️' : '📄';
        }
        
        function showFilePreview(file) {
            const preview = document.getElementById('filePreview');
            const previewIcon = document.getElementById('previewIcon');
            const previewName = document.getElementById('previewName');
            const previewSize = document.getElementById('previewSize');
            
            previewIcon.textContent = getFileIcon(file.name);
            previewName.textContent = file.name;
            previewSize.textContent = formatBytes(file.size);
            
            preview.style.display = 'block';
        }
        
        function removeFile() {
            const fileInput = document.getElementById('archivo');
            const preview = document.getElementById('filePreview');
            
            fileInput.value = '';
            preview.style.display = 'none';
        }
        
        function validateFile(file) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
            
            if (!allowedTypes.includes(file.type)) {
                alert('Tipo de archivo no permitido. Solo se permiten imágenes (JPG, PNG, GIF, WEBP) y PDF.');
                return false;
            }
            
            if (file.size > maxSize) {
                alert('El archivo es demasiado grande. Máximo 5MB permitidos.');
                return false;
            }
            
            return true;
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('archivo');
            const uploadArea = document.querySelector('.file-upload-area');
            
            // Event listeners para verificar formulario
            document.getElementById('titulo').addEventListener('input', verificarFormulario);
            document.getElementById('descripcion').addEventListener('input', verificarFormulario);
            
            // Auto-resize textarea
            const textarea = document.getElementById('descripcion');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            
            // Manejo de archivos
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (validateFile(file)) {
                        showFilePreview(file);
                    } else {
                        this.value = '';
                    }
                }
            });
            
            // Drag and drop
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const file = files[0];
                    if (validateFile(file)) {
                        fileInput.files = files;
                        showFilePreview(file);
                    }
                }
            });
        });
        
        // Validación antes de enviar
        document.getElementById('formReporte').addEventListener('submit', function(e) {
            if (!tipoSeleccionado) {
                e.preventDefault();
                alert('Por favor seleccione un tipo de reporte');
                return false;
            }
            
            const titulo = document.getElementById('titulo').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            
            if (!titulo || !descripcion) {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios');
                return false;
            }
            
            // Validar archivo si se seleccionó uno
            const fileInput = document.getElementById('archivo');
            if (fileInput.files.length > 0) {
                if (!validateFile(fileInput.files[0])) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
    </script>
</body>
</html>

<?php
// Función helper para formatear bytes
function formatBytes($bytes, $precision = 2) {
    if ($bytes === 0) return '0 Bytes';
    $base = log($bytes, 1024);
    $suffixes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}
?>