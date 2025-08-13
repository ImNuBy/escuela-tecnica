<?php
require_once '../config.php';
verificarTipoUsuario(['alumno']);

$mensaje = '';
$tipo_mensaje = '';

// Obtener ID del alumno
$stmt = $pdo->prepare("SELECT id FROM alumnos WHERE usuario_id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$alumno = $stmt->fetch(PDO::FETCH_ASSOC);
$alumno_id = $alumno['id'];

// Procesar formulario de nuevo reporte
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'crear_reporte') {
    $tipo_reporte = $_POST['tipo_reporte'];
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    
    if (!empty($tipo_reporte) && !empty($titulo) && !empty($descripcion)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO reportes_alumnos (alumno_id, tipo_reporte, titulo, descripcion) VALUES (?, ?, ?, ?)");
            $stmt->execute([$alumno_id, $tipo_reporte, $titulo, $descripcion]);
            $mensaje = 'Reporte enviado exitosamente. Será revisado por el personal administrativo.';
            $tipo_mensaje = 'success';
        } catch(PDOException $e) {
            $mensaje = 'Error al enviar reporte: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = 'Por favor complete todos los campos obligatorios';
        $tipo_mensaje = 'error';
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
    <link rel="stylesheet" href="../css/reportes.css">
</head>
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
                    
                    <form method="POST" id="formReporte">
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
                            <div style="background: var(--gray-100); padding: 1rem; border-radius: var(--border-radius); font-size: 0.875rem;">
                                <strong>💡 Consejos para un buen reporte:</strong>
                                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                    <li>Sea específico y claro en su descripción</li>
                                    <li>Incluya fechas y horarios cuando sea relevante</li>
                                    <li>Para inasistencias, mencione el motivo y las fechas</li>
                                    <li>Para problemas, explique qué ocurrió y cuándo</li>
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
                            <h3>📭 No tienes reportes enviados</h3>
                            <p>Cuando envíes tu primer reporte, aparecerá aquí con su estado de seguimiento.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../js/main.js"></script>
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
        
        // Event listeners para verificar formulario
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('titulo').addEventListener('input', verificarFormulario);
            document.getElementById('descripcion').addEventListener('input', verificarFormulario);
            
            // Auto-resize textarea
            const textarea = document.getElementById('descripcion');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
        
        // Validación antes de enviar
        document.getElementById('formReporte').addEventListener('submit', function(e) {
            if (!tipoSeleccionado) {
                e.preventDefault();
                mostrarToast('Por favor seleccione un tipo de reporte', 'error');
                return false;
            }
            
            if (!validarFormulario('formReporte')) {
                e.preventDefault();
                mostrarToast('Por favor complete todos los campos obligatorios', 'error');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>