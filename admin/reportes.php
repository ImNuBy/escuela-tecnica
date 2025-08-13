<?php
require_once '../config.php';
verificarTipoUsuario(['administrador']);

$mensaje = '';
$tipo_mensaje = '';

// Procesar respuesta a reporte
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'responder_reporte') {
    $reporte_id = (int)$_POST['reporte_id'];
    $estado = $_POST['estado'];
    $respuesta = trim($_POST['respuesta']);
    
    if (!empty($reporte_id) && !empty($estado)) {
        try {
            $stmt = $pdo->prepare("UPDATE reportes_alumnos SET estado = ?, respuesta = ? WHERE id = ?");
            $stmt->execute([$estado, $respuesta, $reporte_id]);
            $mensaje = 'Reporte actualizado exitosamente';
            $tipo_mensaje = 'success';
        } catch(PDOException $e) {
            $mensaje = 'Error al actualizar reporte: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

// Filtros
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if ($filtro_estado) {
    $where_conditions[] = "r.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_tipo) {
    $where_conditions[] = "r.tipo_reporte = ?";
    $params[] = $filtro_tipo;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Obtener reportes
$stmt = $pdo->prepare("
    SELECT r.*, u.nombre, u.apellido, u.usuario,
           CONCAT(a.año, '° - ', o.nombre) as curso_alumno
    FROM reportes_alumnos r
    JOIN alumnos al ON r.alumno_id = al.id
    JOIN usuarios u ON al.usuario_id = u.id
    JOIN años a ON al.año_id = a.id
    JOIN orientaciones o ON a.orientacion_id = o.id
    $where_clause
    ORDER BY r.fecha_reporte DESC
");
$stmt->execute($params);
$reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'revisado' THEN 1 ELSE 0 END) as revisados,
        SUM(CASE WHEN estado = 'resuelto' THEN 1 ELSE 0 END) as resueltos
    FROM reportes_alumnos
");
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Reportes - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/reportes.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="page-header">
                <h1>📝 Gestión de Reportes</h1>
                <p>Administrar reportes y comunicaciones de alumnos</p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="reportes-admin-container">
                <!-- Estadísticas -->
                <div class="estadisticas-reportes">
                    <div class="stat-reporte">
                        <div class="stat-numero stat-total"><?php echo $estadisticas['total']; ?></div>
                        <div>Total Reportes</div>
                    </div>
                    <div class="stat-reporte">
                        <div class="stat-numero stat-pendientes"><?php echo $estadisticas['pendientes']; ?></div>
                        <div>Pendientes</div>
                    </div>
                    <div class="stat-reporte">
                        <div class="stat-numero stat-revisados"><?php echo $estadisticas['revisados']; ?></div>
                        <div>Revisados</div>
                    </div>
                    <div class="stat-reporte">
                        <div class="stat-numero stat-resueltos"><?php echo $estadisticas['resueltos']; ?></div>
                        <div>Resueltos</div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="filtros-reportes">
                    <h3>🔍 Filtrar Reportes</h3>
                    <form method="GET" class="filtros-grid">
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select name="estado" id="estado">
                                <option value="">Todos los estados</option>
                                <option value="pendiente" <?php echo ($filtro_estado == 'pendiente') ? 'selected' : ''; ?>>Pendientes</option>
                                <option value="revisado" <?php echo ($filtro_estado == 'revisado') ? 'selected' : ''; ?>>Revisados</option>
                                <option value="resuelto" <?php echo ($filtro_estado == 'resuelto') ? 'selected' : ''; ?>>Resueltos</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo">Tipo</label>
                            <select name="tipo" id="tipo">
                                <option value="">Todos los tipos</option>
                                <option value="inasistencia" <?php echo ($filtro_tipo == 'inasistencia') ? 'selected' : ''; ?>>Inasistencias</option>
                                <option value="problema" <?php echo ($filtro_tipo == 'problema') ? 'selected' : ''; ?>>Problemas</option>
                                <option value="sugerencia" <?php echo ($filtro_tipo == 'sugerencia') ? 'selected' : ''; ?>>Sugerencias</option>
                                <option value="otro" <?php echo ($filtro_tipo == 'otro') ? 'selected' : ''; ?>>Otros</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-primary">Filtrar</button>
                            <a href="reportes.php" class="btn-secondary">Limpiar</a>
                        </div>
                    </form>
                </div>
                
                <!-- Lista de reportes -->
                <div class="reportes-lista">
                    <div class="lista-header">
                        <h2>📨 Reportes de Alumnos (<?php echo count($reportes); ?>)</h2>
                        <button onclick="exportarReportes()" class="btn-secondary">📤 Exportar Lista</button>
                    </div>
                    
                    <?php if (!empty($reportes)): ?>
                        <?php foreach ($reportes as $reporte): ?>
                            <div class="reporte-admin-item">
                                <div class="reporte-admin-header">
                                    <div>
                                        <div class="alumno-info">
                                            <?php echo htmlspecialchars($reporte['nombre'] . ' ' . $reporte['apellido']); ?>
                                            <span style="font-weight: normal; color: var(--gray-600);">
                                                (<?php echo htmlspecialchars($reporte['usuario']); ?>)
                                            </span>
                                        </div>
                                        <div class="curso-info">
                                            <?php echo htmlspecialchars($reporte['curso_alumno']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="tipo-reporte-badge tipo-<?php echo $reporte['tipo_reporte']; ?>">
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
                                
                                <div class="fecha-reporte">
                                    📅 Reportado el <?php echo date('d/m/Y H:i', strtotime($reporte['fecha_reporte'])); ?>
                                </div>
                                
                                <div class="reporte-contenido">
                                    <div class="reporte-titulo-admin">
                                        <?php echo htmlspecialchars($reporte['titulo']); ?>
                                    </div>
                                    <div class="reporte-descripcion-admin">
                                        <?php echo nl2br(htmlspecialchars($reporte['descripcion'])); ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($reporte['respuesta'])): ?>
                                    <div class="respuesta-existente">
                                        <strong>💬 Respuesta enviada:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($reporte['respuesta'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Formulario de respuesta -->
                                <div class="respuesta-form">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="responder_reporte">
                                        <input type="hidden" name="reporte_id" value="<?php echo $reporte['id']; ?>">
                                        
                                        <div class="form-respuesta-grid">
                                            <div class="form-group">
                                                <label for="estado_<?php echo $reporte['id']; ?>">Cambiar Estado</label>
                                                <select name="estado" id="estado_<?php echo $reporte['id']; ?>" required>
                                                    <option value="pendiente" <?php echo ($reporte['estado'] == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                                    <option value="revisado" <?php echo ($reporte['estado'] == 'revisado') ? 'selected' : ''; ?>>Revisado</option>
                                                    <option value="resuelto" <?php echo ($reporte['estado'] == 'resuelto') ? 'selected' : ''; ?>>Resuelto</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="respuesta_<?php echo $reporte['id']; ?>">Respuesta (opcional)</label>
                                                <textarea name="respuesta" id="respuesta_<?php echo $reporte['id']; ?>" 
                                                          rows="2" placeholder="Enviar respuesta al alumno..."><?php echo htmlspecialchars($reporte['respuesta']); ?></textarea>
                                            </div>
                                            
                                            <div class="form-group">
                                                <button type="submit" class="btn-primary">💾 Actualizar</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="sin-reportes-admin">
                            <h3>📭 No hay reportes</h3>
                            <p>No se encontraron reportes con los filtros seleccionados.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../js/main.js"></script>
    <script>
        function exportarReportes() {
            // Crear tabla temporal para exportar
            const reportes = <?php echo json_encode($reportes); ?>;
            
            let csvContent = "Alumno,Curso,Tipo,Título,Descripción,Estado,Fecha,Respuesta\n";
            
            reportes.forEach(reporte => {
                const fila = [
                    `"${reporte.nombre} ${reporte.apellido}"`,
                    `"${reporte.curso_alumno}"`,
                    `"${reporte.tipo_reporte}"`,
                    `"${reporte.titulo}"`,
                    `"${reporte.descripcion.replace(/"/g, '""')}"`,
                    `"${reporte.estado}"`,
                    `"${new Date(reporte.fecha_reporte).toLocaleDateString('es-ES')}"`,
                    `"${reporte.respuesta || ''}"`
                ].join(',');
                csvContent += fila + "\n";
            });
            
            // Descargar archivo
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'reportes_alumnos_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            mostrarToast('Reportes exportados exitosamente', 'success');
        }
        
        // Auto-resize textareas
        document.addEventListener('DOMContentLoaded', function() {
            const textareas = document.querySelectorAll('textarea[name="respuesta"]');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });
        });
        
        // Marcar como leído cuando se cambia el estado
        document.querySelectorAll('select[name="estado"]').forEach(select => {
            select.addEventListener('change', function() {
                if (this.value !== 'pendiente') {
                    const reporteItem = this.closest('.reporte-admin-item');
                    reporteItem.style.backgroundColor = '#f8fafc';
                }
            });
        });
    </script>
</body>
</html>