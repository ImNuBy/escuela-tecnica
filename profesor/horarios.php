<?php
require_once '../config.php';
verificarTipoUsuario(['profesor']);

$mensaje = '';
$tipo_mensaje = '';

// Obtener información del profesor
$stmt = $pdo->prepare("SELECT * FROM profesores WHERE usuario_id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$profesor = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener horarios del profesor con información completa
$stmt = $pdo->prepare("
    SELECT h.*, m.nombre as materia_nombre, 
           CONCAT(a.año, '° - ', o.nombre) as año_orientacion,
           COUNT(DISTINCT al.id) as total_alumnos
    FROM horarios h
    JOIN materias m ON h.materia_id = m.id
    JOIN años a ON m.año_id = a.id
    JOIN orientaciones o ON a.orientacion_id = o.id
    LEFT JOIN alumnos al ON al.año_id = a.id
    LEFT JOIN usuarios u ON al.usuario_id = u.id AND u.activo = 1
    WHERE m.profesor_id = ?
    GROUP BY h.id, h.materia_id, h.dia_semana, h.hora_inicio, h.hora_fin, h.aula, m.nombre, a.año, o.nombre
    ORDER BY 
        CASE h.dia_semana 
            WHEN 'lunes' THEN 1 
            WHEN 'martes' THEN 2 
            WHEN 'miercoles' THEN 3 
            WHEN 'jueves' THEN 4 
            WHEN 'viernes' THEN 5 
        END, h.hora_inicio
");
$stmt->execute([$_SESSION['usuario_id']]);
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar horarios por día
$horarios_por_dia = [];
$horas_disponibles = [];

foreach ($horarios as $horario) {
    $horarios_por_dia[$horario['dia_semana']][] = $horario;
    $horas_disponibles[] = $horario['hora_inicio'];
    $horas_disponibles[] = $horario['hora_fin'];
}

// Obtener rango de horas único y ordenado
$horas_disponibles = array_unique($horas_disponibles);
sort($horas_disponibles);

// Calcular estadísticas
$total_horas_semana = 0;
$materias_diferentes = [];

foreach ($horarios as $horario) {
    $inicio = new DateTime($horario['hora_inicio']);
    $fin = new DateTime($horario['hora_fin']);
    $diferencia = $inicio->diff($fin);
    $total_horas_semana += $diferencia->h + ($diferencia->i / 60);
    
    $materias_diferentes[$horario['materia_id']] = $horario['materia_nombre'];
}

$stats = [
    'total_clases' => count($horarios),
    'total_horas' => $total_horas_semana,
    'materias_diferentes' => count($materias_diferentes),
    'dias_trabajo' => count($horarios_por_dia)
];

$dias_semana = [
    'lunes' => 'Lunes',
    'martes' => 'Martes',
    'miercoles' => 'Miércoles',
    'jueves' => 'Jueves',
    'viernes' => 'Viernes'
];

// Generar grilla de horarios
$grilla_horarios = [];
for ($i = 0; $i < count($horas_disponibles) - 1; $i++) {
    $hora_inicio = $horas_disponibles[$i];
    $hora_fin = $horas_disponibles[$i + 1];
    
    foreach ($dias_semana as $dia_key => $dia_nombre) {
        $clase_encontrada = null;
        
        foreach ($horarios as $horario) {
            if ($horario['dia_semana'] === $dia_key && 
                $horario['hora_inicio'] === $hora_inicio) {
                $clase_encontrada = $horario;
                break;
            }
        }
        
        $grilla_horarios[$hora_inicio][$dia_key] = $clase_encontrada;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Horario - Sistema Escolar</title>
    <link rel="stylesheet" href="../css/base.css">
    <style>
        /* Estilos específicos para Horarios del Profesor */
        .horarios-container {
            display: grid;
            gap: 2rem;
            animation: fadeInUp 0.6s ease-out;
        }

        /* Estadísticas del horario */
        .stats-horarios {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .stats-horarios::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #059669, #10b981, #047857);
        }

        .stats-horarios h2 {
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #059669, #10b981);
            color: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: scale(0);
            transition: transform 0.3s ease;
        }

        .stat-card:hover::before {
            transform: scale(1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(5, 150, 105, 0.4);
            background: linear-gradient(135deg, #047857, #059669);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            display: block;
            position: relative;
            z-index: 1;
            opacity: 0.9;
        }

        .stat-number {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }

        /* Grilla de horarios */
        .horario-semanal {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .horario-semanal h2 {
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 2px solid var(--gray-200);
            padding-bottom: 0.5rem;
        }

        .tabla-horarios {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
            background: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .tabla-horarios th {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            color: var(--gray-800);
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            border: 1px solid var(--gray-200);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tabla-horarios th:first-child {
            background: linear-gradient(135deg, #059669, #10b981);
            color: var(--white);
            width: 120px;
        }

        .tabla-horarios td {
            padding: 0;
            border: 1px solid var(--gray-200);
            height: 80px;
            vertical-align: top;
            position: relative;
        }

        .tabla-horarios td.hora {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            color: var(--gray-700);
            font-weight: 600;
            text-align: center;
            font-size: 0.85rem;
            padding: 1rem 0.5rem;
            vertical-align: middle;
            border-right: 2px solid var(--gray-300);
        }

        .clase-item {
            background: linear-gradient(135deg, #ddd6fe, #c4b5fd);
            border: 2px solid #8b5cf6;
            border-radius: 8px;
            padding: 0.75rem;
            margin: 2px;
            height: calc(100% - 4px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .clase-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: #8b5cf6;
        }

        .clase-item:hover {
            transform: scale(1.02);
            background: linear-gradient(135deg, #c4b5fd, #a78bfa);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
            border-color: #7c3aed;
        }

        .clase-item:hover::before {
            background: #7c3aed;
            width: 6px;
        }

        .clase-nombre {
            font-weight: 600;
            font-size: 0.8rem;
            color: #4c1d95;
            margin-bottom: 0.25rem;
            line-height: 1.2;
        }

        .clase-curso {
            font-size: 0.7rem;
            color: #6d28d9;
            margin-bottom: 0.25rem;
            opacity: 0.9;
        }

        .clase-aula {
            background: #8b5cf6;
            color: var(--white);
            padding: 0.125rem 0.5rem;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 500;
            display: inline-block;
            margin-top: auto;
        }

        .celda-vacia {
            background: linear-gradient(135deg, #f9fafb, #f3f4f6);
            position: relative;
        }

        .celda-vacia::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            background: radial-gradient(circle, var(--gray-300) 20%, transparent 20%);
            opacity: 0.3;
        }

        /* Lista de horarios por día */
        .horarios-lista {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .horarios-lista h2 {
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 2px solid var(--gray-200);
            padding-bottom: 0.5rem;
        }

        .dias-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .dia-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .dia-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #059669, #10b981);
        }

        .dia-titulo {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .clase-dia-item {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 0.75rem;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .clase-dia-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: #8b5cf6;
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .clase-dia-item:hover::before {
            transform: scaleY(1);
        }

        .clase-dia-item:hover {
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-color: #8b5cf6;
        }

        .clase-dia-item:last-child {
            margin-bottom: 0;
        }

        .clase-dia-nombre {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .clase-dia-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .clase-dia-horario {
            font-family: monospace;
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-weight: 500;
            color: var(--gray-700);
        }

        .clase-dia-aula {
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-weight: 500;
            font-size: 0.8rem;
        }

        .dia-sin-clases {
            text-align: center;
            color: var(--gray-500);
            font-style: italic;
            padding: 2rem;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: var(--border-radius);
            border: 1px solid #f59e0b;
        }

        /* Estado sin horarios */
        .sin-horarios {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 2px dashed var(--gray-300);
        }

        .sin-horarios h3 {
            color: var(--gray-700);
            margin-bottom: 1rem;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .sin-horarios p {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        /* Botones de acción */
        .acciones-horario {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn-horario {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary-horario {
            background: linear-gradient(135deg, #059669, #10b981);
            color: var(--white);
            border: 1px solid #059669;
        }

        .btn-primary-horario:hover {
            background: linear-gradient(135deg, #047857, #059669);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.4);
        }

        .btn-secondary-horario {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary-horario:hover {
            background: linear-gradient(135deg, #e5e7eb, #d1d5db);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .horarios-container > * {
            animation: fadeInUp 0.6s ease-out;
        }

        .horarios-container > *:nth-child(2) { animation-delay: 0.1s; }
        .horarios-container > *:nth-child(3) { animation-delay: 0.2s; }
        .horarios-container > *:nth-child(4) { animation-delay: 0.3s; }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dias-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .horarios-container {
                gap: 1.5rem;
            }
            
            .stats-horarios, .horario-semanal, .horarios-lista {
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .dias-grid {
                grid-template-columns: 1fr;
            }
            
            .tabla-horarios th, .tabla-horarios td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
            
            .clase-item {
                padding: 0.5rem;
            }
            
            .acciones-horario {
                flex-direction: column;
                align-items: center;
            }
            
            .clase-dia-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .tabla-horarios {
                font-size: 0.75rem;
            }
            
            .clase-nombre {
                font-size: 0.7rem;
            }
            
            .clase-curso {
                font-size: 0.6rem;
            }
            
            .clase-aula {
                font-size: 0.6rem;
                padding: 0.1rem 0.4rem;
            }
        }

        /* Print styles */
        @media print {
            .acciones-horario {
                display: none !important;
            }
            
            .horarios-container {
                gap: 1rem;
            }
            
            .stats-horarios, .horario-semanal, .horarios-lista {
                box-shadow: none;
                border: 1px solid #ccc;
                break-inside: avoid;
            }
            
            .tabla-horarios {
                break-inside: avoid;
            }
            
            .clase-item {
                background: #f0f0f0 !important;
                border-color: #999 !important;
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
                <h1>📅 Mi Horario</h1>
                <p>Cronograma semanal de <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></p>
            </div>
            
            <?php if ($mensaje): ?>
                <div class="mensaje <?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="horarios-container">
                <!-- Estadísticas del horario -->
                <div class="stats-horarios">
                    <h2>📊 Resumen Semanal</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">🕒</div>
                            <div class="stat-number"><?php echo $stats['total_clases']; ?></div>
                            <div class="stat-label">Clases Semanales</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">⏰</div>
                            <div class="stat-number"><?php echo number_format($stats['total_horas'], 1); ?></div>
                            <div class="stat-label">Horas Totales</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📚</div>
                            <div class="stat-number"><?php echo $stats['materias_diferentes']; ?></div>
                            <div class="stat-label">Materias Diferentes</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📅</div>
                            <div class="stat-number"><?php echo $stats['dias_trabajo']; ?></div>
                            <div class="stat-label">Días de Trabajo</div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($horarios)): ?>
                    <!-- Grilla semanal de horarios -->
                    <div class="horario-semanal">
                        <h2>📋 Vista Semanal</h2>
                        <div style="overflow-x: auto;">
                            <table class="tabla-horarios">
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <?php foreach ($dias_semana as $dia_key => $dia_nombre): ?>
                                            <th><?php echo $dia_nombre; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grilla_horarios as $hora => $dias): ?>
                                        <tr>
                                            <td class="hora">
                                                <?php echo formatearHora($hora); ?>
                                            </td>
                                            <?php foreach ($dias_semana as $dia_key => $dia_nombre): ?>
                                                <td>
                                                    <?php if (isset($dias[$dia_key]) && $dias[$dia_key]): ?>
                                                        <?php $clase = $dias[$dia_key]; ?>
                                                        <div class="clase-item" title="<?php echo htmlspecialchars($clase['materia_nombre'] . ' - ' . $clase['año_orientacion']); ?>">
                                                            <div class="clase-nombre"><?php echo htmlspecialchars($clase['materia_nombre']); ?></div>
                                                            <div class="clase-curso"><?php echo htmlspecialchars($clase['año_orientacion']); ?></div>
                                                            <?php if ($clase['aula']): ?>
                                                                <div class="clase-aula"><?php echo htmlspecialchars($clase['aula']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="celda-vacia"></div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Lista detallada por días -->
                    <div class="horarios-lista">
                        <h2>📝 Detalle por Días</h2>
                        <div class="dias-grid">
                            <?php foreach ($dias_semana as $dia_key => $dia_nombre): ?>
                                <div class="dia-card">
                                    <div class="dia-titulo"><?php echo $dia_nombre; ?></div>
                                    
                                    <?php if (!empty($horarios_por_dia[$dia_key])): ?>
                                        <?php foreach ($horarios_por_dia[$dia_key] as $horario): ?>
                                            <div class="clase-dia-item">
                                                <div class="clase-dia-nombre">
                                                    <?php echo htmlspecialchars($horario['materia_nombre']); ?>
                                                </div>
                                                <div class="clase-dia-info">
                                                    <div>
                                                        <div class="clase-dia-horario">
                                                            <?php echo formatearHora($horario['hora_inicio']) . ' - ' . formatearHora($horario['hora_fin']); ?>
                                                        </div>
                                                        <div style="font-size: 0.8rem; color: var(--gray-600); margin-top: 0.25rem;">
                                                            <?php echo htmlspecialchars($horario['año_orientacion']); ?>
                                                            <?php if ($horario['total_alumnos'] > 0): ?>
                                                                • <?php echo $horario['total_alumnos']; ?> alumnos
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($horario['aula']): ?>
                                                        <div class="clase-dia-aula">
                                                            <?php echo htmlspecialchars($horario['aula']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="dia-sin-clases">
                                            Sin clases programadas
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Acciones -->
                    <div class="acciones-horario">
                        <a href="../profesor/materias.php" class="btn-horario btn-primary-horario">
                            📚 Ver Mis Materias
                        </a>
                        <button onclick="window.print()" class="btn-horario btn-secondary-horario">
                            🖨️ Imprimir Horario
                        </button>
                        <a href="../dashboard.php" class="btn-horario btn-secondary-horario">
                            🏠 Volver al Dashboard
                        </a>
                    </div>
                    
                <?php else: ?>
                    <!-- Estado sin horarios -->
                    <div class="sin-horarios">
                        <h3>No tienes horarios asignados</h3>
                        <p>Actualmente no tienes clases programadas en tu horario semanal.</p>
                        <p>Contacta al administrador para que configure tus horarios de materias.</p>
                        
                        <div class="acciones-horario">
                            <a href="../profesor/materias.php" class="btn-horario btn-primary-horario">
                                Ver Mis Materias
                            </a>
                            <a href="../dashboard.php" class="btn-horario btn-secondary-horario">
                                Volver al Dashboard
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script src="../js/main.js"></script>
    <script>
        // Función para mostrar detalles de clase en modal (opcional)
        document.querySelectorAll('.clase-item').forEach(item => {
            item.addEventListener('click', function() {
                const nombre = this.querySelector('.clase-nombre').textContent;
                const curso = this.querySelector('.clase-curso').textContent;
                const aula = this.querySelector('.clase-aula')?.textContent || 'Sin aula asignada';
                
                alert(`Materia: ${nombre}\nCurso: ${curso}\nAula: ${aula}`);
            });
        });
        
        // Resaltar la hora actual
        function resaltarHoraActual() {
            const ahora = new Date();
            const horaActual = ahora.getHours().toString().padStart(2, '0') + ':' + 
                              ahora.getMinutes().toString().padStart(2, '0') + ':00';
            const diaActual = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'][ahora.getDay()];
            
            // Encontrar y resaltar la celda correspondiente a la hora actual
            const celdas = document.querySelectorAll('.tabla-horarios td');
            celdas.forEach(celda => {
                if (celda.classList.contains('hora')) {
                    const horaTexto = celda.textContent.trim();
                    if (horaTexto.includes(horaActual.substring(0, 5))) {
                        celda.style.background = 'linear-gradient(135deg, #059669, #10b981)';
                        celda.style.color = 'white';
                    }
                }
            });
        }
        
        // Ejecutar al cargar la página
        document.addEventListener('DOMContentLoaded', resaltarHoraActual);
    </script>
</body>
</html>