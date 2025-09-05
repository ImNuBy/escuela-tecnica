<?php
require_once '../../config.php';

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo '<div style="text-align: center; color: #dc2626; padding: 2rem;">Acceso no autorizado</div>';
    exit;
}

// Verificar que sea alumno
if ($_SESSION['tipo_usuario'] !== 'alumno') {
    http_response_code(403);
    echo '<div style="text-align: center; color: #dc2626; padding: 2rem;">Solo los alumnos pueden ver sus entregas</div>';
    exit;
}

try {
    $actividad_id = (int)$_GET['actividad_id'];
    
    // Obtener datos del alumno
    $stmt = $pdo->prepare("SELECT id FROM alumnos WHERE usuario_id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$alumno) {
        echo '<div style="text-align: center; color: #dc2626; padding: 2rem;">No se encontraron datos del alumno</div>';
        exit;
    }
    
    // Obtener datos de la entrega
    $stmt = $pdo->prepare("
        SELECT ea.*, a.titulo as actividad_titulo, a.descripcion as actividad_descripcion,
               a.fecha_entrega as actividad_fecha_entrega, m.nombre as materia_nombre,
               u.nombre as profesor_nombre, u.apellido as profesor_apellido
        FROM entregas_actividades ea
        JOIN actividades a ON ea.actividad_id = a.id
        JOIN materias m ON a.materia_id = m.id
        LEFT JOIN usuarios u ON m.profesor_id = u.id
        WHERE ea.actividad_id = ? AND ea.alumno_id = ?
    ");
    $stmt->execute([$actividad_id, $alumno['id']]);
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entrega) {
        echo '<div style="text-align: center; color: #d97706; padding: 2rem;">No se encontró la entrega</div>';
        exit;
    }
    
    // Función para formatear fecha
    function formatearFechaCompleta($fecha) {
        if (!$fecha) return 'No especificada';
        return date('d/m/Y H:i', strtotime($fecha));
    }
    
    // Función para obtener clase CSS del estado
    function getClaseEstado($estado) {
        switch ($estado) {
            case 'entregado': return 'warning';
            case 'revisado': return 'info';
            case 'aprobado': return 'success';
            case 'rechazado': return 'danger';
            default: return 'secondary';
        }
    }
    
    // Función para obtener texto del estado
    function getTextoEstado($estado) {
        switch ($estado) {
            case 'entregado': return 'Entregado - Pendiente de revisión';
            case 'revisado': return 'Revisado por el profesor';
            case 'aprobado': return 'Aprobado';
            case 'rechazado': return 'Rechazado - Requiere correcciones';
            default: return ucfirst($estado);
        }
    }
    
    ?>
    
    <style>
        .detalle-entrega {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .info-section {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .info-section h4 {
            margin: 0 0 0.75rem 0;
            color: #374151;
            font-size: 1.1rem;
        }
        
        .info-item {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .info-label {
            font-weight: 600;
            color: #4b5563;
            min-width: 120px;
        }
        
        .info-value {
            color: #1f2937;
            flex: 1;
        }
        
        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge.success {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge.warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge.info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge.danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .calificacion-display {
            font-size: 1.5rem;
            font-weight: bold;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: white;
            display: inline-block;
            margin: 0.5rem 0;
        }
        
        .calificacion-display.alta { background: #059669; }
        .calificacion-display.media { background: #d97706; }
        .calificacion-display.baja { background: #dc2626; }
        
        .archivo-info {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .archivo-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .archivo-link:hover {
            text-decoration: underline;
        }
        
        .comentario-section {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .feedback-profesor {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .sin-datos {
            color: #6b7280;
            font-style: italic;
        }
        
        .timeline-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
        }
        
        .timeline-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #2563eb;
        }
        
        .timeline-content {
            flex: 1;
        }
    </style>
    
    <div class="detalle-entrega">
        <!-- Información de la actividad -->
        <div class="info-section">
            <h4>📝 Información de la Actividad</h4>
            <div class="info-item">
                <span class="info-label">Título:</span>
                <span class="info-value"><?php echo htmlspecialchars($entrega['actividad_titulo']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Materia:</span>
                <span class="info-value"><?php echo htmlspecialchars($entrega['materia_nombre']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Profesor:</span>
                <span class="info-value">
                    <?php echo $entrega['profesor_nombre'] && $entrega['profesor_apellido'] ? 
                        htmlspecialchars($entrega['profesor_nombre'] . ' ' . $entrega['profesor_apellido']) : 
                        'No asignado'; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Fecha límite:</span>
                <span class="info-value"><?php echo formatearFechaCompleta($entrega['actividad_fecha_entrega']); ?></span>
            </div>
        </div>
        
        <!-- Estado y calificación -->
        <div class="info-section">
            <h4>📊 Estado de la Entrega</h4>
            <div class="info-item">
                <span class="info-label">Estado:</span>
                <span class="info-value">
                    <span class="badge <?php echo getClaseEstado($entrega['estado']); ?>">
                        <?php echo getTextoEstado($entrega['estado']); ?>
                    </span>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Fecha de entrega:</span>
                <span class="info-value"><?php echo formatearFechaCompleta($entrega['fecha_entrega']); ?></span>
            </div>
            
            <?php if ($entrega['calificacion']): ?>
                <div class="info-item">
                    <span class="info-label">Calificación:</span>
                    <span class="info-value">
                        <?php
                        $nota = (float)$entrega['calificacion'];
                        $clase_cal = $nota >= 8 ? 'alta' : ($nota >= 6 ? 'media' : 'baja');
                        ?>
                        <span class="calificacion-display <?php echo $clase_cal; ?>">
                            <?php echo number_format($nota, 2); ?>/10
                        </span>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Archivo entregado -->
        <?php if ($entrega['archivo_nombre']): ?>
            <div class="info-section">
                <h4>📎 Archivo Entregado</h4>
                <div class="archivo-info">
                    <div class="info-item">
                        <span class="info-label">Nombre:</span>
                        <span class="info-value"><?php echo htmlspecialchars($entrega['archivo_nombre']); ?></span>
                    </div>
                    <?php if ($entrega['archivo_ruta'] && file_exists('../../' . $entrega['archivo_ruta'])): ?>
                        <div class="info-item">
                            <span class="info-label">Descargar:</span>
                            <span class="info-value">
                                <a href="../../<?php echo htmlspecialchars($entrega['archivo_ruta']); ?>" 
                                   class="archivo-link" 
                                   target="_blank" 
                                   download="<?php echo htmlspecialchars($entrega['archivo_nombre']); ?>">
                                    📥 Descargar archivo
                                </a>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tamaño:</span>
                            <span class="info-value">
                                <?php 
                                $size = filesize('../../' . $entrega['archivo_ruta']);
                                echo formatBytes($size);
                                ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="info-item">
                            <span class="info-label">Estado:</span>
                            <span class="info-value sin-datos">Archivo no disponible</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Comentario del alumno -->
        <?php if ($entrega['comentario']): ?>
            <div class="info-section">
                <h4>💬 Tu Comentario</h4>
                <div class="comentario-section">
                    <?php echo nl2br(htmlspecialchars($entrega['comentario'])); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Feedback del profesor -->
        <?php if ($entrega['feedback_profesor']): ?>
            <div class="info-section">
                <h4>🎓 Feedback del Profesor</h4>
                <div class="feedback-profesor">
                    <?php echo nl2br(htmlspecialchars($entrega['feedback_profesor'])); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Timeline de la entrega -->
        <div class="info-section">
            <h4>⏱️ Historial</h4>
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <strong>Entrega realizada</strong><br>
                    <small><?php echo formatearFechaCompleta($entrega['fecha_entrega']); ?></small>
                </div>
            </div>
            
            <?php if (in_array($entrega['estado'], ['revisado', 'aprobado', 'rechazado'])): ?>
                <div class="timeline-item">
                    <div class="timeline-dot" style="background: <?php echo $entrega['estado'] === 'aprobado' ? '#059669' : ($entrega['estado'] === 'rechazado' ? '#dc2626' : '#2563eb'); ?>"></div>
                    <div class="timeline-content">
                        <strong><?php echo getTextoEstado($entrega['estado']); ?></strong>
                        <?php if ($entrega['calificacion']): ?>
                            <br><small>Calificación: <?php echo number_format($entrega['calificacion'], 2); ?>/10</small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Acciones adicionales -->
        <div style="margin-top: 2rem; text-align: center;">
            <?php if ($entrega['estado'] === 'rechazado'): ?>
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <strong style="color: #991b1b;">⚠️ Tu entrega fue rechazada</strong><br>
                    <small style="color: #7f1d1d;">Revisa el feedback del profesor y realiza las correcciones necesarias.</small>
                </div>
            <?php endif; ?>
            
            <button onclick="cerrarModalDetalle()" style="background: #2563eb; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; cursor: pointer; font-weight: 500;">
                Cerrar
            </button>
        </div>
    </div>
    
    <?php
    
    // Función para formatear bytes
    function formatBytes($size, $precision = 2) {
        $base = log($size, 1024);
        $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');   
        return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
    }
    
} catch (Exception $e) {
    error_log("Error en obtener_detalle_entrega.php: " . $e->getMessage());
    echo '<div style="text-align: center; color: #dc2626; padding: 2rem;">Error al cargar los detalles de la entrega</div>';
}