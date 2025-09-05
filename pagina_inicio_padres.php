<?php
// Archivo: inicio.php
// Configuración básica
$nombre_escuela = "Escuela Técnica Provincial";
$eslogan = "Formando el Futuro Tecnológico";
$direccion = "Av. Educación Técnica 1234, Buenos Aires";
$telefono = "(011) 4567-8900";
$email = "info@escuelatecnica.edu.ar";
$horarios = "Lunes a Viernes: 8:00 - 18:00";

// Conectar a la base de datos para obtener algunas estadísticas
try {
    $pdo = new PDO("mysql:host=localhost;dbname=escuela_tecnica;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener estadísticas básicas
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'alumno' AND activo = 1");
    $total_alumnos = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'profesor' AND activo = 1");
    $total_profesores = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM materias");
    $total_materias = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM orientaciones");
    $total_orientaciones = $stmt->fetchColumn();
    
    // Obtener noticias recientes para mostrar
    $stmt = $pdo->query("
        SELECT n.*, u.nombre, u.apellido 
        FROM noticias n 
        JOIN usuarios u ON n.autor_id = u.id 
        WHERE n.activo = 1 AND n.dirigido_a IN ('todos', 'ciclo_basico') 
        ORDER BY n.fecha_publicacion DESC 
        LIMIT 3
    ");
    $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // Si no se puede conectar, usar valores por defecto
    $total_alumnos = 450;
    $total_profesores = 35;
    $total_materias = 25;
    $total_orientaciones = 3;
    $noticias = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nombre_escuela; ?> - <?php echo $eslogan; ?></title>
    <meta name="description" content="Escuela Técnica con orientaciones en Programación y Electrónica. Formamos profesionales del futuro con educación de calidad.">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #f59e0b;
            --success-color: #10b981;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --border-radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--gray-800);
            background: var(--white);
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--white);
            padding: 1rem 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-link {
            color: var(--white);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn-login {
            background: var(--accent-color);
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: #d97706;
        }

        /* Mobile menu */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--white);
            padding: 8rem 0 4rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
            animation: slideUp 1s ease-out;
        }

        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            animation: slideUp 1s ease-out 0.3s both;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: slideUp 1s ease-out 0.6s both;
        }

        .btn {
            padding: 1rem 2rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-primary {
            background: var(--accent-color);
            color: var(--white);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        /* Estadísticas */
        .stats {
            background: var(--white);
            padding: 2rem 0;
            margin-top: -2rem;
            position: relative;
            z-index: 2;
        }

        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-100);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray-600);
            font-weight: 500;
        }

        /* Secciones principales */
        .section {
            padding: 4rem 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 2.5rem;
            color: var(--gray-800);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .section-subtitle {
            font-size: 1.125rem;
            color: var(--gray-600);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Grid layouts */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            align-items: center;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        /* Cards */
        .card {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--gray-800);
        }

        .card p {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* Orientaciones */
        .orientaciones {
            background: var(--gray-50);
        }

        .orientacion-card {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            border-top: 4px solid var(--primary-color);
        }

        .orientacion-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .orientacion-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        /* Noticias */
        .noticias {
            padding: 4rem 0;
            background: var(--white);
        }

        .noticia-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }

        .noticia-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .noticia-fecha {
            color: var(--gray-500);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .noticia-titulo {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--gray-800);
        }

        .noticia-contenido {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* Contacto */
        .contacto {
            background: var(--gray-50);
            padding: 4rem 0;
        }

        .contacto-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }

        .contacto-info {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .contacto-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .contacto-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .contacto-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
            width: 40px;
            text-align: center;
        }

        .mapa {
            background: linear-gradient(135deg, var(--gray-200), var(--gray-300));
            border-radius: var(--border-radius);
            height: 350px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
            font-size: 1.125rem;
            position: relative;
            overflow: hidden;
        }

        .mapa::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="%234b5563" opacity="0.3"/></svg>');
            background-size: 20px 20px;
        }

        /* Footer */
        .footer {
            background: var(--gray-900);
            color: var(--white);
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            margin-bottom: 1rem;
            color: var(--white);
        }

        .footer-section p,
        .footer-section li {
            color: var(--gray-300);
            margin-bottom: 0.5rem;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section a {
            color: var(--gray-300);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section a:hover {
            color: var(--white);
        }

        .footer-bottom {
            border-top: 1px solid var(--gray-700);
            padding-top: 2rem;
            text-align: center;
            color: var(--gray-400);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .section-title {
                font-size: 2rem;
            }

            .contacto-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .grid-2,
            .grid-3 {
                grid-template-columns: 1fr;
            }

            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }

        /* Animaciones adicionales */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Header scroll effect */
        .header.scrolled {
            background: rgba(37, 99, 235, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header" id="header">
        <nav class="nav-container">
            <div class="logo">
                🏫 <?php echo $nombre_escuela; ?>
            </div>
            <ul class="nav-menu">
                <li><a href="#inicio" class="nav-link">Inicio</a></li>
                <li><a href="#nosotros" class="nav-link">Nosotros</a></li>
                <li><a href="#orientaciones" class="nav-link">Orientaciones</a></li>
                <li><a href="#noticias" class="nav-link">Noticias</a></li>
                <li><a href="#contacto" class="nav-link">Contacto</a></li>
                <li><a href="index.php" class="btn-login">Ingresar al Sistema</a></li>
            </ul>
            <button class="mobile-menu-btn">☰</button>
        </nav>
    </header>

    <!-- Hero Section -->
    <section id="inicio" class="hero">
        <div class="hero-content">
            <h1><?php echo $nombre_escuela; ?></h1>
            <p><?php echo $eslogan; ?> - Educación técnica de excelencia con orientaciones especializadas en Programación y Electrónica</p>
            <div class="hero-buttons">
                <a href="#nosotros" class="btn btn-primary">Conoce Más</a>
                <a href="registro.php" class="btn btn-secondary">Inscribirse</a>
                <a href="index.php" class="btn btn-primary">Acceder al Sistema</a>
            </div>
        </div>
    </section>

    <!-- Estadísticas -->
    <section class="stats">
        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-card fade-in">
                    <div class="stat-number"><?php echo $total_alumnos; ?>+</div>
                    <div class="stat-label">Estudiantes Activos</div>
                </div>
                <div class="stat-card fade-in">
                    <div class="stat-number"><?php echo $total_profesores; ?>+</div>
                    <div class="stat-label">Profesores</div>
                </div>
                <div class="stat-card fade-in">
                    <div class="stat-number"><?php echo $total_materias; ?>+</div>
                    <div class="stat-label">Materias</div>
                </div>
                <div class="stat-card fade-in">
                    <div class="stat-number">20+</div>
                    <div class="stat-label">Años de Experiencia</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sobre Nosotros -->
    <section id="nosotros" class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Sobre Nuestra Institución</h2>
                <p class="section-subtitle">Una escuela técnica comprometida con la excelencia educativa y la formación integral de nuestros estudiantes</p>
            </div>
            
            <div class="grid-2">
                <div>
                    <h3 style="font-size: 2rem; margin-bottom: 1rem; color: var(--gray-800);">Nuestra Misión</h3>
                    <p style="font-size: 1.1rem; line-height: 1.6; color: var(--gray-600); margin-bottom: 1rem;">
                        Formar técnicos competentes y ciudadanos responsables, capaces de adaptarse a los cambios tecnológicos y contribuir al desarrollo de la sociedad mediante una educación de calidad.
                    </p>
                    <p style="font-size: 1.1rem; line-height: 1.6; color: var(--gray-600); margin-bottom: 1rem;">
                        Con más de 20 años de trayectoria, nos hemos consolidado como una institución de referencia en la formación técnica, combinando conocimientos teóricos sólidos con práctica intensiva en laboratorios equipados con tecnología de última generación.
                    </p>
                </div>
                <div class="grid-3" style="grid-template-columns: 1fr;">
                    <div class="card fade-in">
                        <div class="card-icon">🎯</div>
                        <h3>Excelencia Académica</h3>
                        <p>Programas educativos actualizados y docentes altamente capacitados para brindar la mejor formación técnica.</p>
                    </div>
                    <div class="card fade-in">
                        <div class="card-icon">🔬</div>
                        <h3>Tecnología Avanzada</h3>
                        <p>Laboratorios equipados con herramientas modernas para práctica real en programación y electrónica.</p>
                    </div>
                    <div class="card fade-in">
                        <div class="card-icon">🌟</div>
                        <h3>Formación Integral</h3>
                        <p>Desarrollo de competencias técnicas y humanas para formar ciudadanos comprometidos.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Orientaciones -->
    <section id="orientaciones" class="section orientaciones">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Nuestras Orientaciones</h2>
                <p class="section-subtitle">Dos especializaciones técnicas de alto nivel que preparan a nuestros estudiantes para el futuro tecnológico</p>
            </div>
            
            <div class="grid-2">
                <div class="orientacion-card fade-in">
                    <div class="orientacion-icon">💻</div>
                    <h3>Técnico en Programación</h3>
                    <p>Formamos desarrolladores de software con conocimientos en múltiples lenguajes de programación, desarrollo web, bases de datos y metodologías ágiles.</p>
                    <ul style="text-align: left; margin: 1rem 0; color: var(--gray-600);">
                        <li>• Desarrollo Web Full Stack</li>
                        <li>• Programación Orientada a Objetos</li>
                        <li>• Bases de Datos Avanzadas</li>
                        <li>• Desarrollo de Aplicaciones Móviles</li>
                        <li>• Metodologías Ágiles</li>
                    </ul>
                </div>
                
                <div class="orientacion-card fade-in">
                    <div class="orientacion-icon">⚡</div>
                    <h3>Técnico en Electrónica</h3>
                    <p>Especializamos técnicos en sistemas electrónicos, automatización industrial, microcontroladores y sistemas embebidos.</p>
                    <ul style="text-align: left; margin: 1rem 0; color: var(--gray-600);">
                        <li>• Electrónica Digital Avanzada</li>
                        <li>• Microcontroladores y Arduino</li>
                        <li>• Sistemas Embebidos</li>
                        <li>• Automatización Industrial</li>
                        <li>• Diseño de Circuitos</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Noticias -->
    <?php if (!empty($noticias)): ?>
    <section id="noticias" class="section noticias">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Últimas Noticias</h2>
                <p class="section-subtitle">Mantente informado sobre las novedades de nuestra institución</p>
            </div>
            
            <div class="grid-3">
                <?php foreach ($noticias as $noticia): ?>
                <div class="noticia-card fade-in">
                    <div class="noticia-fecha">
                        <?php echo date('d/m/Y', strtotime($noticia['fecha_publicacion'])); ?>
                    </div>
                    <h3 class="noticia-titulo"><?php echo htmlspecialchars($noticia['titulo']); ?></h3>
                    <p class="noticia-contenido">
                        <?php echo htmlspecialchars(substr($noticia['contenido'], 0, 150)) . (strlen($noticia['contenido']) > 150 ? '...' : ''); ?>
                    </p>
                    <p style="font-size: 0.875rem; color: var(--gray-500); margin-top: 0.75rem;">
                        Por: <?php echo htmlspecialchars($noticia['nombre'] . ' ' . $noticia['apellido']); ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Contacto -->
    <section id="contacto" class="section contacto">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Contacto e Información</h2>
                <p class="section-subtitle">Estamos aquí para ayudarte. Contáctanos para más información</p>
            </div>
            
            <div class="contacto-grid">
                <div class="contacto-info">
                    <div class="contacto-item fade-in">
                        <div class="contacto-icon">📍</div>
                        <div>
                            <h4>Dirección</h4>
                            <p><?php echo $direccion; ?></p>
                        </div>
                    </div>
                    
                    <div class="contacto-item fade-in">
                        <div class="contacto-icon">📞</div>
                        <div>
                            <h4>Teléfono</h4>
                            <p><?php echo $telefono; ?></p>
                        </div>
                    </div>
                    
                    <div class="contacto-item fade-in">
                        <div class="contacto-icon">✉️</div>
                        <div>
                            <h4>Email</h4>
                            <p><?php echo $email; ?></p>
                        </div>
                    </div>
                    
                    <div class="contacto-item fade-in">
                        <div class="contacto-icon">🕒</div>
                        <div>
                            <h4>Horarios de Atención</h4>
                            <p><?php echo $horarios; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="mapa fade-in">
                    <div style="text-align: center;">
                        <h3 style="margin-bottom: 1rem; color: var(--gray-700);">📍 Nuestra Ubicación</h3>
                        <p style="margin-bottom: 1rem;"><?php echo $direccion; ?></p>
                        <div style="background: var(--white); padding: 1rem; border-radius: 8px; display: inline-block; box-shadow: var(--shadow);">
                            <p style="font-size: 0.9rem; color: var(--gray-600);">🗺️ Mapa interactivo disponible próximamente</p>
                            <p style="font-size: 0.875rem; color: var(--gray-500); margin-top: 0.5rem;">
                                Fácil acceso en transporte público<br>
                                Estacionamiento disponible
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Proyectos Destacados -->
    <section class="section" style="background: var(--white);">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Proyectos de Nuestros Estudiantes</h2>
                <p class="section-subtitle">Conoce algunos de los proyectos innovadores desarrollados por nuestros alumnos</p>
            </div>
            
            <div class="grid-3">
                <div class="card fade-in" style="border-top: 4px solid var(--primary-color);">
                    <div class="card-icon">🤖</div>
                    <h3>Robot Educativo Interactivo</h3>
                    <p>Estudiantes de electrónica desarrollaron un robot para enseñar programación a niños, utilizando Arduino y sensores avanzados. El proyecto fue premiado en la feria de ciencias provincial.</p>
                </div>
                
                <div class="card fade-in" style="border-top: 4px solid var(--success-color);">
                    <div class="card-icon">📱</div>
                    <h3>App de Gestión Escolar</h3>
                    <p>Aplicación móvil desarrollada por alumnos de programación para gestionar horarios, notas y comunicación entre estudiantes, padres y profesores. Actualmente en uso en varias escuelas.</p>
                </div>
                
                <div class="card fade-in" style="border-top: 4px solid var(--accent-color);">
                    <div class="card-icon">🏠</div>
                    <h3>Sistema Domótico IoT</h3>
                    <p>Proyecto interdisciplinario que combina programación y electrónica para crear un sistema de hogar inteligente con control remoto y automatización completa.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="section" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: var(--white); text-align: center;">
        <div class="container">
            <h2 style="font-size: 2.5rem; margin-bottom: 1rem; font-weight: 700;">¿Listo para Formar Parte de Nuestro Futuro?</h2>
            <p style="font-size: 1.25rem; margin-bottom: 2rem; opacity: 0.9;">
                Únete a nuestra comunidad educativa y desarrolla las habilidades que el mundo tecnológico necesita
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="registro.php" class="btn" style="background: var(--accent-color); color: var(--white); box-shadow: var(--shadow);">
                    📝 Inscribirse Ahora
                </a>
                <a href="index.php" class="btn" style="background: rgba(255, 255, 255, 0.2); color: var(--white); border: 2px solid rgba(255, 255, 255, 0.3);">
                    🔐 Acceder al Sistema
                </a>
                <a href="#contacto" class="btn" style="background: transparent; color: var(--white); border: 2px solid var(--white);">
                    💬 Más Información
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>🏫 <?php echo $nombre_escuela; ?></h3>
                    <p><?php echo $eslogan; ?></p>
                    <p style="margin-top: 1rem;">Formando profesionales técnicos desde hace más de 20 años con excelencia académica y compromiso social.</p>
                </div>
                
                <div class="footer-section">
                    <h3>📞 Contacto</h3>
                    <p>📍 <?php echo $direccion; ?></p>
                    <p>☎️ <?php echo $telefono; ?></p>
                    <p>✉️ <?php echo $email; ?></p>
                    <p>🕒 <?php echo $horarios; ?></p>
                </div>
                
                <div class="footer-section">
                    <h3>🎓 Orientaciones</h3>
                    <ul>
                        <li><a href="#orientaciones">💻 Técnico en Programación</a></li>
                        <li><a href="#orientaciones">⚡ Técnico en Electrónica</a></li>
                        <li><a href="#nosotros">📚 Ciclo Básico</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>🔗 Enlaces Rápidos</h3>
                    <ul>
                        <li><a href="index.php">🔐 Ingresar al Sistema</a></li>
                        <li><a href="registro.php">📝 Registro de Alumnos</a></li>
                        <li><a href="#noticias">📰 Noticias</a></li>
                        <li><a href="#contacto">📧 Contacto</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo $nombre_escuela; ?>. Todos los derechos reservados.</p>
                <p style="margin-top: 0.5rem; font-size: 0.875rem;">
                    Diseñado con ❤️ para la educación técnica | Sistema de Gestión Escolar v2.0
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scrolling para enlaces internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                    
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Animaciones al hacer scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });

        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.getElementById('header');
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Contador animado para estadísticas
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            const speed = 200;
            
            counters.forEach(counter => {
                const animate = () => {
                    const value = +counter.getAttribute('data-target') || +counter.innerText.replace('+', '');
                    const data = +counter.innerText.replace('+', '');
                    const time = value / speed;
                    
                    if (data < value) {
                        counter.innerText = Math.ceil(data + time) + (counter.getAttribute('data-suffix') || '+');
                        setTimeout(animate, 1);
                    } else {
                        counter.innerText = value + (counter.getAttribute('data-suffix') || '+');
                    }
                }
                animate();
            });
        }

        // Activar contador cuando las estadísticas sean visibles
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        const statsSection = document.querySelector('.stats');
        if (statsSection) {
            statsObserver.observe(statsSection);
        }

        // Mobile menu functionality (básico)
        document.querySelector('.mobile-menu-btn').addEventListener('click', function() {
            const navMenu = document.querySelector('.nav-menu');
            if (navMenu.style.display === 'flex') {
                navMenu.style.display = 'none';
            } else {
                navMenu.style.display = 'flex';
                navMenu.style.flexDirection = 'column';
                navMenu.style.position = 'absolute';
                navMenu.style.top = '100%';
                navMenu.style.left = '0';
                navMenu.style.right = '0';
                navMenu.style.background = 'var(--primary-color)';
                navMenu.style.padding = '1rem';
                navMenu.style.boxShadow = 'var(--shadow-lg)';
            }
        });

        // Efectos adicionales de interacción
        document.querySelectorAll('.card, .contacto-item, .stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
                this.style.transition = 'all 0.3s ease';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Preloader simple
        window.addEventListener('load', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });

        // Parallax effect ligero para el hero
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero');
            if (hero && scrolled < hero.offsetHeight) {
                hero.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });
    </script>
</body>
</html>